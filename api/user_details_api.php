<?php
/**
 * User Details API - Get user PAN, Name, and DOB
 * Supports both JWT token authentication and URL parameter fallback
 */
require_once __DIR__ . '/includes/cors.php';

// ---------------- AUTH FUNCTIONS ----------------
/**
 * Decode JWT token and validate signature
 */
function decodeJWT($token) {
    $db = new MfdDb();
    $secret = $db->getSecretCode();

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new Exception('Invalid token format');
    }

    $headerB64 = $parts[0];
    $payloadB64 = $parts[1];
    $signatureB64 = $parts[2];

    // Verify signature
    $signatureCheck = hash_hmac('sha256', $headerB64 . "." . $payloadB64, $secret, true);
    $expectedSignature = rtrim(strtr(base64_encode($signatureCheck), '+/', '-_'), '=');
    
    if (!hash_equals($expectedSignature, $signatureB64)) {
        throw new Exception('Invalid token signature');
    }

    // Decode payload
    $payload = json_decode(
        base64_decode(strtr($payloadB64, '-_', '+/')),
        true
    );

    if (!$payload) {
        throw new Exception('Invalid token payload');
    }

    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        throw new Exception('Token expired');
    }

    return $payload;
}

/**
 * Extract user ID from Authorization header
 */
function getUserIdFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return null;
    }
    
    $token = $matches[1];
    $payload = decodeJWT($token);
    
    return $payload['user_id'] ?? null;
}

// ---------------- MAIN REQUEST HANDLER ----------------
try {
    // Only allow GET method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendResponse(false, 'Method not allowed. Use GET method.', null, 405);
    }

    $db = new MfdDb();
    $userId = null;
    
    // METHOD 1: Check if user_id is provided in URL parameter (for development/fallback)
    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
        $userId = $_GET['user_id'];
        
        // Validate that user_id is numeric
        if (!is_numeric($userId)) {
            sendResponse(false, 'Invalid user ID format', null, 400);
        }
        
        // Get user details using existing MfdDb method
        $user = $db->getUserById($userId);
        
        if ($user) {
            sendResponse(true, 'User details retrieved successfully', [
                'PRIMARY_HOLDER_PAN'  => $user['PRIMARY_HOLDER_PAN'] ?? '',
                'PRIMARY_HOLDER_NAME' => $user['PRIMARY_HOLDER_NAME'] ?? '',
                'PRIMARY_HOLDER_DOB'  => $user['PRIMARY_HOLDER_DOB'] ?? ''
            ]);
        } else {
            sendResponse(false, 'User not found', null, 404);
        }
        exit;
    }
    
    // METHOD 2: If no user_id in URL, try to get from JWT token
    $userId = getUserIdFromToken();
    
    if (!$userId) {
        sendResponse(false, 'Authorization token required. Please provide a valid token or user_id parameter.', null, 401);
    }
    
    // Get user details using existing MfdDb method
    $user = $db->getUserById($userId);
    
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }
    
    // Return only the required fields
    sendResponse(true, 'User details retrieved successfully', [
        'PRIMARY_HOLDER_PAN'  => $user['PRIMARY_HOLDER_PAN'] ?? '',
        'PRIMARY_HOLDER_NAME' => $user['PRIMARY_HOLDER_NAME'] ?? '',
        'PRIMARY_HOLDER_DOB'  => $user['PRIMARY_HOLDER_DOB'] ?? ''
    ]);

} catch (Exception $e) {
    error_log('user_details_api error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}