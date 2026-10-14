<?php
require_once __DIR__ . '/includes/cors.php';

// Simple authentication using JWT token
function authenticateRequest() {
    $token = getBearerToken();
    
    if (!$token) {
        error_log("No bearer token found");
        return null;
    }
    
    //error_log("Token received: " . substr($token, 0, 20) . "...");
    
    // Try to verify JWT token
    $userId = verifyJwtToken($token);
    
    if ($userId) {
        //error_log("Authentication successful for user ID: " . $userId);
        return $userId;
    }
    
    error_log("JWT verification failed");
    return null;
}

// ==================== MAIN REQUEST HANDLING ====================

//error_log("=== PERSONAL DETAILS API REQUEST START ===");
//error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
//error_log("Request URI: " . $_SERVER['REQUEST_URI']);
//error_log("Remote Addr: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));

// Authenticate user
$userId = authenticateRequest();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // GET endpoint: Fetch personal details
    
    if (!$userId) {
        error_log("GET: Authentication failed");
        sendResponse(false, 'Authentication required. Please login again.', null, 401);
    }
    
    //error_log("GET request for user ID: " . $userId);
    
    try {
        $personalDetails = $db->getPersonalDetails($userId);
        
        if ($personalDetails) {
            //error_log("GET: Found personal details for user ID: " . $userId);
            
            // Format dates for display (they're already in YYYY-MM-DD format from database)
            $responseData = $personalDetails;
            
            // Map database column names to frontend field names if needed
            // Add any additional mapping here
            $responseData['primaryHolderDOB'] = $personalDetails['PRIMARY_HOLDER_DOB'] ?? '';
            $responseData['secondHolderDOB'] = $personalDetails['SECOND_HOLDER_DOB'] ?? '';
            $responseData['thirdHolderDOB'] = $personalDetails['THIRD_HOLDER_DOB'] ?? '';
            $responseData['guardianDOB'] = $personalDetails['GUARDIAN_DOB'] ?? '';
            
            sendResponse(true, 'Personal details retrieved successfully', $responseData);
        } else {
            error_log("GET: No personal details found for user ID: " . $userId);
            sendResponse(true, 'No personal details found', null);
        }
    } catch (Exception $e) {
        error_log("GET Exception: " . $e->getMessage());
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
} elseif ($method === 'POST') {
    // POST endpoint: Save/Update personal details
    
    if (!$userId) {
        error_log("POST: Authentication failed");
        sendResponse(false, 'Authentication required. Please login again.', null, 401);
    }
    
    //error_log("POST request for user ID: " . $userId);
    
    // Get input data (JSON payload or $_POST)
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    if (!$input || !is_array($input)) {
        error_log("POST ERROR: Input is null or not an array");
        sendResponse(false, 'Invalid input data. Please provide valid details.');
    }
    
    // Log input summary
    //error_log("POST Input summary - Total fields: " . count($input));
    
    try {
        // FIX: Sanitize date fields to ensure empty strings become NULL
        $dateFields = ['primaryHolderDOB', 'secondHolderDOB', 'thirdHolderDOB', 'guardianDOB'];
        foreach ($dateFields as $field) {
            if (isset($input[$field]) && empty($input[$field])) {
                $input[$field] = null;
            }
        }

        $result = $db->savePersonalDetails($userId, $input);
        
        if ($result) {
            // Fetch updated data to return
            $updatedData = $db->getPersonalDetails($userId);
            sendResponse(true, 'Personal details saved successfully', $updatedData);
        } else {
            error_log("POST ERROR: savePersonalDetails returned false");
            sendResponse(false, 'Failed to save personal details.');
        }
    } catch (Exception $e) {
        error_log("POST EXCEPTION: " . $e->getMessage());
        sendResponse(false, 'Error: ' . $e->getMessage());
    }
} else {
    error_log("Method not allowed: " . $method);
    sendResponse(false, 'Method not allowed', null, 405);
}

//error_log("=== PERSONAL DETAILS API REQUEST END ===");