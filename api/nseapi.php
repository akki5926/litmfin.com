<?php
//nseapi.php
class NseAPI{
	private function buildNseAuthHeader($c): string
    {
        $loginUserId = $c['login_user_id'];
        $apiSecret   = $c['secret_key'];   // earlier "secretKey"
        $licenseKey  = $c['member_key'];   // earlier "memberKey"

        $separator = '|';
        $random_number = random_int(1, 10000000000);
        $plain_text = $apiSecret . $separator . $random_number;

        $salt = bin2hex(random_bytes(16));
        $iv   = bin2hex(random_bytes(16));

        $key = hash_pbkdf2('sha1', $licenseKey, hex2bin($salt), 1000, 16, true);

        $ivBin = hex2bin($iv);
        if ($ivBin === false) {
            throw new RuntimeException("Invalid IV hex generated for AES IV.");
        }

        $cipherRaw = openssl_encrypt($plain_text, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $ivBin);
        if ($cipherRaw === false) {
            throw new RuntimeException("openssl_encrypt failed: " . openssl_error_string());
        }

        $ciphertext_b64 = base64_encode($cipherRaw);
        $aesPassword = $iv . "::" . $salt . "::" . $ciphertext_b64;
        $encryptedPassword = base64_encode($aesPassword);
        $authString = $loginUserId . ":" . $encryptedPassword;

        return "Basic " . base64_encode($authString);
    }
	public function callNseApi($creds, string $urlPostFix, ?array $payload = null, array $options = []): array
	{
    $method  = strtoupper($options['method'] ?? 'POST');
    $useUat  = !empty($options['use_uat']);
    $timeout = isset($options['timeout']) ? (int)$options['timeout'] : 60;
    $caFile  = $options['ca_file'] ?? null;

    $endpointPath = $creds['api_url'] . $urlPostFix;

    if (preg_match('#^https?://#i', $endpointPath)) {
        $url = $endpointPath;
    }

    // DEBUG: basic request info
    //error_log('[NSE API] URL=' . $url);
    //error_log('[NSE API] Method=' . $method);

    $authHeader = $this->buildNseAuthHeader($creds);
//error_log("[NSE API] authHeader = " . ($authHeader ?: 'EMPTY')); // Shows the actual value

    $headers = [
        "Content-Type: application/json",
        "memberId: " . $creds['tm_code'],
        "Authorization: " . $authHeader,
        "User-Agent: MFDB/1.0",
        "Accept: application/json, text/plain, */*",
    ];

    if (!empty($options['extra_headers'])) {
        $extra = $options['extra_headers'];
        if (array_values($extra) === $extra) {
            $headers = array_merge($headers, $extra);
        } else {
            foreach ($extra as $k => $v) {
                $headers[] = $k . ': ' . $v;
            }
        }
    }

    // DEBUG: headers count (don’t log auth token)
    //error_log('[NSE API] Headers count=' . count($headers));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $timeout));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    if ($caFile) {
        curl_setopt($ch, CURLOPT_CAINFO, $caFile);
        // DEBUG
        //error_log('[NSE API] Using CA file=' . $caFile);
    }

    $bodyForLog = null;

    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $body = $payload !== null ? json_encode($payload) : '';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        if ($method !== 'POST') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $bodyForLog = $body;
		//error_log('[NSE API] Payload =' . $body);
        // DEBUG: payload size only
        //error_log('[NSE API] Payload bytes=' . strlen((string)$body));

    } elseif ($method === 'GET' && !empty($payload)) {
        $qs = http_build_query($payload);
        $urlWithQs = $url . (strpos($url, '?') === false ? '?' : '&') . $qs;
        curl_setopt($ch, CURLOPT_URL, $urlWithQs);

        $bodyForLog = $qs;

        // DEBUG
        //error_log('[NSE API] Query string=' . $qs);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $resp       = curl_exec($ch);
    $curlErrNo  = curl_errno($ch);
    $curlErr    = $curlErrNo ? curl_error($ch) : null;
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($curlErrNo) {
        $errResp = ['error' => "cURL error ({$curlErrNo}): {$curlErr}"];
        $this->logToDebugJsonFile($url, $payload, $errResp, (int)$httpCode, $options);
        throw new RuntimeException("cURL error while calling {$url}: {$curlErr}");
    }

    $decoded = null;
    if ($resp !== false) {
        $json = json_decode($resp, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $decoded = $json;
        }
    }

    $logResponseData = $decoded !== null ? $decoded : $resp;
    $this->logToDebugJsonFile($url, $payload, $logResponseData, (int)$httpCode, $options);

    if ($httpCode >= 400) {
        $excerpt = is_string($resp)
            ? (strlen($resp) > 500 ? substr($resp, 0, 500) . '...truncated' : $resp)
            : '';

        throw new RuntimeException("HTTP error {$httpCode} from {$url}: " . $excerpt);
    }

    return [
        'http_code' => (int)$httpCode,
        'response'  => $resp,
        'json'      => $decoded,
    ];
}

    private function logToDebugJsonFile(string $url, ?array $payload, $response, int $httpCode, array $options = []): void
    {
        try {
            $debugDir = __DIR__ . '/Debug';
            if (!is_dir($debugDir)) {
                @mkdir($debugDir, 0777, true);
            }

            $clientCode = '';
            $clientName = '';

            if (!empty($options['client_code'])) $clientCode = (string)$options['client_code'];
            if (!empty($options['client_name'])) $clientName = (string)$options['client_name'];

            $userId = $options['user_id'] ?? null;
            $pan    = null;

            // Deep extraction from payload
            $this->extractUserIdentifiers($payload, $clientCode, $clientName, $userId, $pan);

            // Deep extraction from response if still missing
            if (empty($clientCode) && empty($clientName) && empty($userId) && empty($pan)) {
                $this->extractUserIdentifiers($response, $clientCode, $clientName, $userId, $pan);
            }

            if (empty($clientCode) || empty($clientName)) {
                try {
                    if (!class_exists('MfdDb')) {
                        @require_once __DIR__ . '/db/mfddb.php';
                    }
                    if (class_exists('MfdDb')) {
                        $db = new MfdDb();
                        $userInfo = null;
                        if (!empty($userId)) {
                            $userInfo = method_exists($db, 'getUserProfileDetails') ? $db->getUserProfileDetails($userId) : null;
                        }
                        if (!$userInfo && !empty($clientCode)) {
                            $userInfo = method_exists($db, 'getUserProfileDetails') ? $db->getUserProfileDetails($clientCode) : null;
                        }
                        if (!$userInfo && !empty($pan)) {
                            $userInfo = method_exists($db, 'getUserByIdentifier') ? $db->getUserByIdentifier($pan) : null;
                        }

                        if ($userInfo) {
                            if (empty($clientCode)) {
                                $clientCode = $userInfo['CLIENT_CODE'] ?? $userInfo['client_code'] ?? '';
                            }
                            if (empty($clientName)) {
                                $clientName = $userInfo['PRIMARY_HOLDER_NAME'] ?? $userInfo['primary_holder_name'] ?? '';
                            }
                        }
                    }
                } catch (Throwable $dbEx) {
                    // Ignore DB lookup failure during debug log
                }
            }

            if (!empty($clientName)) {
                $clientName = preg_replace('/\s+/', ' ', trim($clientName));
            }

            if (empty($clientCode) && empty($clientName)) {
                $filename = "unknown_user.json";
            } else {
                $cleanCode = preg_replace('/[^A-Za-z0-9]/', '', (string)($clientCode ?: 'NOCode'));
                $cleanName = preg_replace('/[^A-Za-z0-9]/', '_', (string)($clientName ?: 'NOName'));
                $cleanName = preg_replace('/_+/', '_', trim($cleanName, '_'));

                $filename = "{$cleanCode}_{$cleanName}.json";
            }

            $filePath = $debugDir . '/' . $filename;

            $cleanPayload  = $this->sanitizeDataForLog($payload);
            $cleanResponse = $this->sanitizeDataForLog($response);

            $logEntry = [
                'timestamp'   => date('Y-m-d H:i:s T'),
                'url'         => $url,
                'http_code'   => $httpCode,
                'client_code' => $clientCode,
                'client_name' => $clientName,
                'request'     => $cleanPayload,
                'response'    => $cleanResponse
            ];

            $logs = [];
            if (file_exists($filePath)) {
                $existingContent = @file_get_contents($filePath);
                if ($existingContent) {
                    $decoded = @json_decode($existingContent, true);
                    if (is_array($decoded)) {
                        $logs = $decoded;
                    }
                }
            }

            $logs[] = $logEntry;

            @file_put_contents(
                $filePath,
                json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
        } catch (Throwable $e) {
            error_log("logToDebugJsonFile error: " . $e->getMessage());
        }
    }

    private function sanitizeDataForLog($data)
    {
        if (is_array($data)) {
            $clean = [];
            foreach ($data as $k => $v) {
                $clean[$k] = $this->sanitizeDataForLog($v);
            }
            return $clean;
        } elseif (is_string($data)) {
            if (strlen($data) > 1000 && preg_match('/^[A-Za-z0-9+\/=]+$/', substr($data, 0, 100))) {
                return '[BASE64_DATA... ' . strlen($data) . ' bytes]';
            }
        }
        return $data;
    }

    private function extractUserIdentifiers($data, &$clientCode, &$clientName, &$userId, &$pan): void
    {
        if (!is_array($data)) return;

        foreach ($data as $key => $val) {
            $kLower = strtolower((string)$key);

            if (is_array($val)) {
                $this->extractUserIdentifiers($val, $clientCode, $clientName, $userId, $pan);
                continue;
            }

            if (is_scalar($val) && (string)$val !== '') {
                if (empty($clientCode) && in_array($kLower, ['client_code', 'client_code_no', 'clientcode', 'client_id'], true)) {
                    $clientCode = (string)$val;
                }
                if (empty($clientName) && in_array($kLower, ['client_name', 'primary_holder_name', 'inv_name', 'name', 'holder_name'], true)) {
                    $clientName = (string)$val;
                }
                if (empty($userId) && in_array($kLower, ['user_id', 'userid', 'id'], true) && is_numeric($val)) {
                    $userId = (int)$val;
                }
                if (empty($pan) && in_array($kLower, ['pan', 'pan_no', 'pan_rp', 'primary_holder_pan'], true)) {
                    $pan = (string)$val;
                }
            }
        }
    }

}
?>
