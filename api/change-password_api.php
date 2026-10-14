<?php
// api/change-password_api.php - DEBUG VERSION

// Start output buffering
ob_start();

// Include CORS first
require_once __DIR__ . '/includes/cors.php';

// Include database file
if (!class_exists('MfdDb')) {
    require_once __DIR__ . '/../db/mfddb.php';
}

// Create database instance
$db = new MfdDb();

// Function to validate password strength
function validatePassword($password) {
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number";
    }
    
    if (!preg_match('/[@$!%*?&]/', $password)) {
        return "Password must contain at least one special character (@$!%*?&)";
    }
    
    return null;
}

// Function to extract user ID from JWT token (simple version)
function extractUserIdFromToken($token) {
    if (empty($token)) {
        return null;
    }
    
    try {
        // Split the token
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            return null;
        }
        
        // Decode the payload (base64url decode)
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
        $payloadData = json_decode($payload, true);
        
        return $payloadData['user_id'] ?? $payloadData['userId'] ?? $payloadData['id'] ?? null;
        
    } catch (Exception $e) {
        error_log("JWT decode error: " . $e->getMessage());
        return null;
    }
}

// Main API logic
try {
    // DEBUG: Log request information
    error_log("=== CHANGE PASSWORD REQUEST ===");
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
    
    // Get all headers for debugging
    $allHeaders = getallheaders();
    error_log("Headers received:");
    foreach ($allHeaders as $key => $value) {
        error_log("  $key: $value");
    }
    
    // Check Authorization header (case-insensitive)
    $authHeader = '';
    foreach ($allHeaders as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
    
    error_log("Auth Header found: " . ($authHeader ? 'YES' : 'NO'));
    error_log("Auth Header value: " . $authHeader);
    
    // Get raw input for debugging
    $rawInput = file_get_contents('php://input');
    error_log("Raw input length: " . strlen($rawInput));
    error_log("Raw input (first 500 chars): " . substr($rawInput, 0, 500));
    
    // Parse JSON input
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        $inputError = json_last_error_msg();
        error_log("JSON parse error: " . $inputError);
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid JSON input: ' . $inputError,
            'debug' => [
                'raw_input' => $rawInput,
                'json_error' => $inputError
            ]
        ]);
        exit;
    }
    
    error_log("Parsed input keys: " . implode(', ', array_keys($input)));
    
    // Validate required fields
    $requiredFields = ['oldPassword', 'newPassword', 'confirmPassword'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields: ' . implode(', ', $missingFields)
        ]);
        exit;
    }
    
    $oldPassword = trim($input['oldPassword']);
    $newPassword = trim($input['newPassword']);
    $confirmPassword = trim($input['confirmPassword']);
    
    // =============================================
    // GET USER ID FROM AUTHORIZATION HEADER
    // =============================================
    
    $userId = null;
    
    // Check Bearer token
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
        error_log("Bearer token extracted, length: " . strlen($token));
        $userId = extractUserIdFromToken($token);
        error_log("User ID from token: " . ($userId ?? 'NULL'));
    }
    
    // Check for token in input (for testing)
    if (!$userId && isset($input['token'])) {
        error_log("Checking for token in input body");
        $userId = extractUserIdFromToken($input['token']);
    }
    
    // FOR TESTING ONLY: Allow user ID in input
    if (!$userId && isset($input['userId']) && is_numeric($input['userId'])) {
        error_log("Using userId from input (FOR TESTING ONLY)");
        $userId = (int)$input['userId'];
    }
    
    if (!$userId) {
        // More detailed error
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'User not authenticated. Please login first.',
            'debug' => [
                'auth_header_received' => !empty($authHeader),
                'auth_header_value' => $authHeader,
                'has_token_in_body' => isset($input['token']),
                'has_userId_in_body' => isset($input['userId']),
                'all_headers_keys' => array_keys($allHeaders),
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? ''
            ]
        ]);
        exit;
    }
    
    error_log("Authenticated User ID: " . $userId);
    
    // =============================================
    // PASSWORD VALIDATION
    // =============================================
    
    // Validate passwords match
    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        exit;
    }
    
    // Check if new password is different
    if ($oldPassword === $newPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be different from old password']);
        exit;
    }
    
    // Validate password strength
    $passwordError = validatePassword($newPassword);
    if ($passwordError) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $passwordError]);
        exit;
    }
    
    // =============================================
    // GET USER FROM DATABASE
    // =============================================
    
    $user = $db->getUserById($userId);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'message' => 'User not found',
            'debug' => ['user_id_requested' => $userId]
        ]);
        exit;
    }
    
    // Debug user info
    error_log("User found: " . ($user['EMAIL'] ?? 'No email'));
    error_log("Has password hash: " . (isset($user['PASSWORD_HASH']) ? 'YES' : 'NO'));
    
    // =============================================
    // VERIFY OLD PASSWORD
    // =============================================
    
    $storedPasswordHash = $user['PASSWORD_HASH'] ?? '';
    
    if (empty($storedPasswordHash)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'No password found for user. Please use password reset.'
        ]);
        exit;
    }
    
    // Try different password verification methods
    $passwordValid = false;
    $verificationMethod = 'none';
    
    // 1. Check password_hash() verification
    if (password_verify($oldPassword, $storedPasswordHash)) {
        $passwordValid = true;
        $verificationMethod = 'password_hash';
        error_log("Password verified via password_hash()");
    }
    // 2. Check MD5
    elseif (md5($oldPassword) === $storedPasswordHash) {
        $passwordValid = true;
        $verificationMethod = 'md5';
        error_log("Password verified via MD5");
    }
    // 3. Check plain text (for testing)
    elseif ($oldPassword === $storedPasswordHash) {
        $passwordValid = true;
        $verificationMethod = 'plain_text';
        error_log("Password verified via plain text");
    }
    // 4. Check SHA1
    elseif (sha1($oldPassword) === $storedPasswordHash) {
        $passwordValid = true;
        $verificationMethod = 'sha1';
        error_log("Password verified via SHA1");
    }
    
    if (!$passwordValid) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Old password is incorrect',
            'debug' => [
                'verification_method' => $verificationMethod,
                'stored_hash_length' => strlen($storedPasswordHash),
                'stored_hash_prefix' => substr($storedPasswordHash, 0, 20),
                'old_password_length' => strlen($oldPassword)
            ]
        ]);
        exit;
    }
    
    error_log("Old password verified successfully via: " . $verificationMethod);
    
    // =============================================
    // UPDATE PASSWORD
    // =============================================
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update in database
    $success = $db->updatePassword($userId, $hashedPassword);
    
    if ($success) {
        // Update login timestamp
        $db->updateLoginTimestamp($userId);
        
        error_log("Password updated successfully for user ID: " . $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully!'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update password in database'
        ]);
    }
    
} catch (Exception $e) {
    error_log("EXCEPTION in change-password: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();