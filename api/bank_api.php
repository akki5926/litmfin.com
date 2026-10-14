<?php
/**
 * Bank Account API Endpoints - Update existing user record
 */
require_once __DIR__ . '/includes/cors.php';

// Get authorization header
$headers = getallheaders();
$authToken = $headers['Authorization'] ?? '';

// Extract user ID strictly from the authenticated token
$userId = null;
if (!empty($authToken)) {
    $userId = extractUserIdFromToken($authToken);
}

// If no user_id from token, check URL parameter (for development/fallback)
if (!$userId && isset($_GET['user_id'])) {
    $userId = $_GET['user_id'];
}

// For PUT requests, also check JSON input
if (!$userId && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['user_id'])) {
        $userId = $input['user_id'];
    }
}

if (!$userId) {
    sendResponse(false, 'User ID is required', null, 400);
}

// Create database connection
$db = new MfdDb();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetRequest($db, $userId);
        break;
    case 'PUT':
        handlePutRequest($db, $userId);
        break;
    default:
        sendResponse(false, 'Method not allowed', null, 405);
}

/**
 * Extract user ID from JWT token
 */
function extractUserIdFromToken($token) {
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+', $parts[1]))), true);
            return $payload['user_id'] ?? null;
        }
    }
    return null;
}

/**
 * Handle GET request - Retrieve bank account details
 */
function handleGetRequest(MfdDb $db, $userId) {
    try {
        $account = $db->getBankAccountByUserId($userId);
        
        if ($account) {
            // Add bank name for display
            if (!empty($account['bdBankCode'])) {
                $bankDetails = $db->getBankByBdBankCode($account['bdBankCode']);
                if ($bankDetails) {
                    $account['bankName'] = $bankDetails['BD_BANK_NAME'] ?? $bankDetails['bd_bank_name'] ?? '';
                }
            }
            sendResponse(true, 'Bank account retrieved successfully', $account);
        } else {
            sendResponse(true, 'No bank account found', null);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Handle PUT request - Update bank account details
 */
function handlePutRequest(MfdDb $db, $userId) {
    // Parse input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(false, 'Invalid JSON input', null, 400);
        return;
    }

    // Validate required fields
    $requiredFields = ['accountHolderName', 'accountType', 'bdBankCode', 'accountNo', 'ifscCode', 'bankBranch'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missingFields), null, 400);
        return;
    }
    
    // Validate account number format
    if (!preg_match('/^[0-9]{9,18}$/', $input['accountNo'])) {
        sendResponse(false, 'Account number must be 9-18 digits only', null, 400);
        return;
    }
    
    // Validate IFSC code format
    $ifscCode = strtoupper($input['ifscCode']);
    if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifscCode)) {
        sendResponse(false, 'Invalid IFSC format. Must be 11 characters (e.g., SBIN0001234)', null, 400);
        return;
    }
    
    try {
        // Update bank account
        $result = $db->updateBankAccount($userId, $input);
        
        if ($result) {
            // Get the updated account with bank details
            $updatedAccount = $db->getBankAccountByUserId($userId);
            
            // Add bank name for display
            if (!empty($updatedAccount['bdBankCode'])) {
                $bankDetails = $db->getBankByBdBankCode($updatedAccount['bdBankCode']);
                if ($bankDetails) {
                    $updatedAccount['bankName'] = $bankDetails['BD_BANK_NAME'] ?? $bankDetails['bd_bank_name'] ?? '';
                }
            }
            
            // Create UCC (Unique Client Code)
            $uccResult = createUcc($userId, $db);
            
            // Prepare response message
            $json = $uccResult['json'] ?? [];
            $reg = $json['reg_details'][0] ?? [];
            $status = $reg['reg_status'] ?? 'PENDING';
            $remark = trim($reg['reg_remark'] ?? '');
            
            $responseMsg = "Bank account updated successfully";
            if ($status !== 'UNKNOWN') {
                $responseMsg .= ", UCC Registration: " . $status;
            }
            if ($remark !== '') {
                $responseMsg .= ", " . $remark;
            }
            
            sendResponse(true, $responseMsg, $updatedAccount);
        } else {
            sendResponse(false, 'Failed to update bank account. Please try again.', null, 500);
        }
    } catch (Exception $e) {
        error_log("Bank API PUT error: " . $e->getMessage());
        sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Create UCC (Unique Client Code) for the user
 */
function createUcc($userId, $db): array
{
    require_once __DIR__ . '/nseapiconnect.php';
    $uccService = new NseApiConnect($db);
    $uccResult = $uccService->createUcc($userId);
    return $uccResult;
}