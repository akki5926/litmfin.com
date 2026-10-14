<?php
// Disable displaying HTML errors in JSON API responses
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Ensure output buffering is active to catch warnings/notices
if (ob_get_level() === 0) {
    ob_start();
}

if (headers_sent()) {
    return;
}

// Initialize database connection only if not already defined
if (!isset($db)) {
    require_once __DIR__ . '/../db/mfddb.php';
    $db = new MfdDb();
}

$config = $db->getCorsConfig();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = $config['allowed_origins'] ?? [];

// ── Dev-origin fallback ────────────────────────────────────────────────────
// Derive the frontend dev origin from the NEXT_PUBLIC_API_BASE_URL env var.
// e.g. API is http://localhost/site/api → frontend dev is http://localhost:3000
// No value is hardcoded; if the env var is absent nothing is added.
$apiBaseUrl = getenv('NEXT_PUBLIC_API_BASE_URL') ?: '';
if ($apiBaseUrl) {
    $parsed = parse_url($apiBaseUrl);
    $apiHost = $parsed['host'] ?? '';
    // If the API is on localhost, allow the Next.js dev server on the same host
    if ($apiHost === 'localhost' || $apiHost === '127.0.0.1') {
        $devPort = getenv('NEXT_PUBLIC_DEV_PORT') ?: '3000'; // override via env if needed
        $devOrigin = ($parsed['scheme'] ?? 'http') . '://' . $apiHost . ':' . $devPort;
        if (!in_array($devOrigin, $allowedOrigins, true)) {
            $allowedOrigins[] = $devOrigin;
        }
    }
}
// ──────────────────────────────────────────────────────────────────────────

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
}

if (!empty($config['allow_credentials'])) {
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: ' . ($config['allow_methods'] ?? 'GET,POST,PUT,OPTIONS'));
header('Access-Control-Allow-Headers: ' . ($config['allow_headers'] ?? 'Content-Type,Authorization'));
header('Content-Type: application/json');

// Handle preflight immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function sendResponse(bool $success, string $message, $data = null, int $statusCode = 200): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ]);
    exit();
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateJWT(array $payload) {
    $db = new MfdDb();
    $secret = $db->getSecretCode();
    $expirySeconds = (int) $db->getTokenExpiry();

    if (empty($secret)) {
        throw new Exception('JWT secret not configured');
    }

    $header = [
        'alg' => 'HS256',
        'typ' => 'JWT'
    ];

    $issuedAt = time();
    $expiresAt = $issuedAt + $expirySeconds;

    // Standard JWT claims
    $payload['iat'] = $issuedAt;
    $payload['exp'] = $expiresAt;

    $base64Header  = base64UrlEncode(json_encode($header));
    $base64Payload = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac(
        'sha256',
        $base64Header . "." . $base64Payload,
        $secret,
        true
    );

    $base64Signature = base64UrlEncode($signature);

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

// Add this at the very top of users.php, before any other code
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}

// Also add for str_ends_with if you might need it
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ('' === $needle || $needle === $haystack) {
            return true;
        }
        
        if ('' === $haystack) {
            return false;
        }
        
        $needleLength = strlen($needle);
        return $needleLength <= strlen($haystack) && 0 === substr_compare($haystack, $needle, -$needleLength);
    }
}

// And str_contains for PHP 8+
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return '' === $needle || false !== strpos($haystack, $needle);
    }
}

function getBearerToken() {
    $headers = getallheaders();
    
    // Check for Authorization header (case-sensitive first)
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    
    // Check for authorization header (lowercase)
    if (isset($headers['authorization'])) {
        if (preg_match('/Bearer\s+(\S+)/', $headers['authorization'], $matches)) {
            return $matches[1];
        }
    }
    
    // Check for HTTP_AUTHORIZATION server variable
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

function verifyJwtToken($token) {
    if (!$token) {
        error_log("No token provided");
        return null;
    }
    
    try {
        // Split the token into parts
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            error_log("Invalid token format");
            return null;
        }
        
        // Decode the payload (middle part)
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        if (!$payload || !is_array($payload)) {
            error_log("Failed to decode payload");
            return null;
        }

        // Reject expired tokens — without this check, expired JWTs are accepted
        // forever and the backend timeout never fires
        if (isset($payload['exp']) && time() > $payload['exp']) {
            error_log("JWT token has expired");
            return null;
        }
        
        // Check for user ID in different possible locations
        if (isset($payload['userId'])) {
            return $payload['userId'];
        } elseif (isset($payload['user_id'])) {
            return $payload['user_id'];
        } elseif (isset($payload['id'])) {
            return $payload['id'];
        } elseif (isset($payload['sub'])) {
            return $payload['sub'];
        }
        
        error_log("No user ID found in payload");
        return null;
        
    } catch (Exception $e) {
        error_log("JWT verification exception: " . $e->getMessage());
        return null;
    }
}
?>