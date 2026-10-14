<?php

class MFCentralAPI
{
    private $config;
    private $token;
    private $baseUrl = "https://uatservices.mfcentral.com";
    private $privateKey;
    private $publicKey;
    private $debug = false; // set true to see full request/response/crypto trace

    public function __construct($configPath)
    {
        $this->config = json_decode(file_get_contents($configPath), true);

        $this->privateKey = file_get_contents("private.pem");
        $this->publicKey  = file_get_contents("public.pem");
    }

    public function generateToken()
    {
        $url = $this->baseUrl . "/oauth/token";

        $headers = [
            "Authorization: Basic " . base64_encode(
                $this->config['ClientId'] . ":" . $this->config['ClientSecret']
            ),
            "Content-Type: application/x-www-form-urlencoded"
        ];

        $post = http_build_query([
            "username" => $this->config['UserName'],
            "password" => $this->config['Password'],
            "grant_type" => "password"
        ]);

        $response = $this->curl($url, $headers, $post);

        $json = json_decode($response, true);

        if (!$json) {
            die("❌ Token JSON decode failed");
        }

        if (!isset($json['access_token'])) {
            print_r($json);
            die("❌ access_token missing");
        }

        $this->token = $json['access_token'];
        echo "✅ Token generated successfully\n";
    }

    private function curl($url, $headers, $post)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            die("❌ CURL ERROR: " . curl_error($ch));
        }

        return $response;
    }

    private function curlPost($url, $headers, $body)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            die("❌ CURL ERROR: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 422 is expected when polling for statement generation
        if ($httpCode >= 400 && $httpCode !== 422) {
            echo "⚠️ HTTP Error Code: " . $httpCode . "\n";
        }

        return $response;
    }

    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Encrypt function matching Java's CryptUtils.encryptDecrypt()
     * Based on PDF page 17-18
     */
    private function encrypt($payload)
    {
        // Step 1: Get SHA-256 hash of the encryption key and convert to hex
        $hash = hash('sha256', $this->config['EncryptionDecryptionKey'], false);
        
        // Step 2: Take first 32 characters of the hex hash (this becomes the key as ASCII bytes)
        $keyHex = substr($hash, 0, 32);
        $key = $keyHex; // Use hex string directly as key (32 bytes)
        
        // Step 3: IV - take first 16 bytes of "globalaesvectors"
        $ivString = "globalaesvectors";
        $iv = substr($ivString, 0, 16); // Take first 16 characters: "globalassetsec" (16 bytes)
        
        if ($this->debug) {
            echo "\n--- ENCRYPT DEBUG ---\n";
            echo "Encryption Key (hex string): " . $key . "\n";
            echo "Key length: " . strlen($key) . " bytes\n";
            echo "IV: " . $iv . "\n";
            echo "IV length: " . strlen($iv) . " bytes\n";
        }
        
        // Encrypt using AES-256-CBC
        $encrypted = openssl_encrypt($payload, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            echo "❌ Encryption failed: " . openssl_error_string() . "\n";
            return null;
        }
        
        // Return URL-safe base64 encoded
        return $this->base64url_encode($encrypted);
    }

    /**
     * Decrypt function matching Java's CryptUtils.encryptDecrypt()
     */

	private function decrypt($payload)
	{
		// Step 1: Get SHA-256 hash of the encryption key and convert to hex
		$hash = hash('sha256', $this->config['EncryptionDecryptionKey'], false);
		
		// Step 2: Take first 32 characters of the hex hash
		$keyHex = substr($hash, 0, 32);
		$key = $keyHex;
		
		// Step 3: IV - take first 16 bytes
		$ivString = "globalaesvectors";
		$iv = substr($ivString, 0, 16);
		
		// Decode from URL-safe base64
		$decoded = $this->base64url_decode($payload);
		
		// Decrypt
		$decrypted = openssl_decrypt($decoded, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
		
		if ($decrypted === false) {
			echo "❌ Decryption failed: " . openssl_error_string() . "\n";
			return null;
		}
		
		// Remove PKCS7 padding and find JSON
		// The decrypted string has garbage at the beginning
		if (preg_match('/\{.*\}/s', $decrypted, $matches)) {
			$decrypted = $matches[0];
		}
		
		return $decrypted;
	}
		private function sign($payload)
    {
        $header = [
            "alg" => "RS256",
            "b64" => false,
            "crit" => ["b64"]
        ];

        $encodedHeader = $this->base64url_encode(json_encode($header));

        // Detached payload → NOT base64 encoded (raw string)
        $signingInput = $encodedHeader . "." . $payload;

        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $encodedSignature = $this->base64url_encode($signature);

        return $encodedHeader . ".." . $encodedSignature;
    }

    private function verify($payload, $jws)
    {
        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            echo "❌ Invalid JWS format\n";
            return false;
        }

        list($encodedHeader, $empty, $encodedSignature) = $parts;

        $signature = $this->base64url_decode($encodedSignature);

        $signingInput = $encodedHeader . "." . $payload;

        $result = openssl_verify(
            $signingInput,
            $signature,
            $this->publicKey,
            OPENSSL_ALGO_SHA256
        );
        
        return $result === 1;
    }

    public function callAPI($endpoint, $payload)
    {
        $url = $this->baseUrl . $endpoint;

        if ($this->debug) {
            echo "\n--- REQUEST PAYLOAD ---\n";
            echo $payload . "\n";
        }

        $encrypted = $this->encrypt($payload);
        
        if ($encrypted === null) {
            die("❌ Encryption failed");
        }
        
        if ($this->debug) {
            echo "\n--- ENCRYPTED (URL-safe) ---\n";
            echo $encrypted . "\n";
        }

        $signature = $this->sign($encrypted);
        if ($this->debug) {
            echo "\n--- JWS SIGNATURE ---\n";
            echo $signature . "\n";
        }

        $body = json_encode([
            "request" => $encrypted,
            "signature" => $signature
        ]);

        $headers = [
            "Content-Type: application/json",
            "ClientId: " . $this->config['ClientId'],
            "Authorization: Bearer " . $this->token
        ];

        if ($this->debug) {
            echo "\n--- SENDING REQUEST TO: " . $url . " ---\n";
        }
        
        $response = $this->curlPost($url, $headers, $body);

        if ($this->debug) {
            echo "\n--- RAW API RESPONSE ---\n";
            echo $response . "\n";
        }

        $json = json_decode($response, true);

        if (!$json) {
            die("❌ JSON decode failed. Raw response: " . $response);
        }

        if (isset($json['errors'])) {
            echo "\n--- API ERRORS ---\n";
            print_r($json['errors']);
            return $json;
        }

        if (!isset($json['response'])) {
            if (isset($json['code']) || isset($json['message'])) {
                echo "\n--- API ERROR RESPONSE ---\n";
                print_r($json);
                return $json;
            }
            die("❌ No 'response' field from API");
        }

        if ($this->debug) {
            echo "\n--- VERIFYING SIGNATURE ---\n";
        }

        if (!$this->verify($json['response'], $json['signature'])) {
            die("❌ Signature verification failed");
        }

        if ($this->debug) {
            echo "✅ Signature verified successfully\n";
        }

        $decrypted = $this->decrypt($json['response']);
        
        if ($decrypted === null || $decrypted === '') {
            // Per spec 3.4, a successful investorconsent call returns an empty
            // body (HTTP 202 <<empty>>). This is NOT an error condition.
            if ($this->debug) {
                echo "✅ Empty response body (expected for this endpoint on success)\n";
            }
            return ['accepted' => true];
        }

        if ($this->debug) {
            echo "\n--- DECRYPTED RESPONSE ---\n";
            echo $decrypted . "\n";
        }

        $result = json_decode($decrypted, true);
        
        if (!$result && json_last_error() !== JSON_ERROR_NONE) {
            echo "⚠️ Warning: Decrypted response is not valid JSON: " . json_last_error_msg() . "\n";
            return ['raw' => $decrypted];
        }

        return $result;
    }
}