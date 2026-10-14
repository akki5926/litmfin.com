<?php
/**
 * Nominee Details API Endpoint
 */
require_once __DIR__ . '/includes/cors.php';

// Authentication check function
function authenticateUser() {
    $headers = getallheaders();
    $token = '';
    
    // Check Authorization header
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }
    
    // Check for token in GET parameter (fallback)
    if (empty($token) && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    if (empty($token)) {
        sendResponse(false, 'Authentication token required', null, 401);
    }
    
    // In a real application, validate the token against your authentication system
    // For now, we'll check if it's a valid JWT-like token
    if (strlen($token) < 10) {
        sendResponse(false, 'Invalid authentication token', null, 401);
    }
    
    return $token;
}

// Get user ID from token (simplified - in production, decode JWT or check session)
function getUserIdFromToken($token) {
    // Check for user_id in GET parameter first (if provided by client application)
    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
        return $_GET['user_id'];
    }
    
    // Extract user ID from the authenticated token
    $parts = explode('.', $token);
    if (count($parts) >= 2) {
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        if (isset($payload['user_id'])) {
            return $payload['user_id'];
        }
    }
    
    sendResponse(false, 'User ID not found in token', null, 401);
}

try {
    $db = new MfdDb();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetRequest($db);
            break;
            
        case 'POST':
            handlePostRequest($db);
            break;
            
        default:
            sendResponse(false, 'Method not allowed', null, 405);
    }
    
} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}

function handleGetRequest(MfdDb $db) {
    // Authenticate user
    $token = authenticateUser();
    
    // Get user ID
    $userId = getUserIdFromToken($token);
    
    // Get action parameter
    $action = $_GET['action'] ?? 'get';
    
    if ($action === 'get') {
        // Get nominee details for user
        $nomineeDetails = $db->getNomineeDetails($userId);
        
        if ($nomineeDetails !== false) {
            sendResponse(true, 'Nominee details retrieved successfully', $nomineeDetails);
        } else {
            sendResponse(true, 'No nominee details found', null);
        }
    } else {
        sendResponse(false, 'Invalid action for GET request. Use "get"', null, 400);
    }
}

function handlePostRequest(MfdDb $db) {
    // Authenticate user
    $token = authenticateUser();
    
    // Get user ID
    $userId = getUserIdFromToken($token);
    
    // Get POST data (JSON or $_POST)
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    if (!$input || !is_array($input)) {
        sendResponse(false, 'No data provided', null, 400);
    }
    
    // Get action
    $action = $_GET['action'] ?? 'save';
    
    if ($action === 'save') {
        try {
            // Save/update nominee details
            $success = $db->saveNomineeDetails($userId, $input);
            
            if ($success) {
                sendResponse(true, 'Nominee details saved successfully');
            } else {
                sendResponse(false, 'Failed to save nominee details');
            }
            
        } catch (Exception $e) {
            sendResponse(false, 'Error saving nominee details: ' . $e->getMessage(), null, 500);
        }
    } else {
        sendResponse(false, 'Invalid action for POST request. Use "save"', null, 400);
    }
}