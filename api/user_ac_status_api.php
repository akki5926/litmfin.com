<?php
/**
 * User Account Status API Endpoints
 * user_ac_status_api.php
 */
require_once __DIR__ . '/includes/cors.php';

// Simple authentication using JWT token
function authenticateRequest() {
    $token = getBearerToken();
    
    if (!$token) {
        error_log("No bearer token found");
        return null;
    }
    
    $userId = verifyJwtToken($token);
    
    if ($userId) {
        return $userId;
    }
    
    error_log("JWT verification failed");
    return null;
}

// Helper function to get a single status field from mtb_user_status table
function getUserStatusField($userId, $field) {
    global $db;
    return $db->getUserStatusField($userId, $field);
}

// Check if user status record exists
function userStatusRecordExists($userId) {
    global $db;
    return $db->userStatusRecordExists($userId);
}

// Helper function to update user status - uses UPDATE or INSERT based on existence
function updateUserStatus($userId, $field, $value) {
    global $db;
    return $db->updateUserStatusField($userId, $field, $value);
}

// DEBUG function to check current FATCA status (READ-ONLY)
function debugFATCAStatus($userId) {
    global $db;
    return $db->debugFATCAStatus($userId);
}

// Function to ensure user status record exists with all default values
function ensureUserStatusRecord($userId) {
    global $db;
    return $db->ensureUserStatusRecord($userId);
}

// Function to sync user status from actual user data in tb_users table
function syncUserStatusFromUserData($userId) {
    global $db;
    $updated = false;
    
    try {
        // Ensure record exists first
        ensureUserStatusRecord($userId);
        
        $userData = $db->getUserDataForSync($userId);
        
        if (!$userData) {
            error_log("User not found in tb_users table: " . $userId);
            return false;
        }
        
        // 1. Check KYC status
        if(getUserStatusField($userId, 'kyc_complient') != 'Y') {
            require_once 'nseapiconnect.php';
            $nseApiObj = new NseApiConnect();
            $kycResult = $nseApiObj->KycCheck($userId);
            if(isset($kycResult['json']['kyc_status']) && $kycResult['json']['kyc_status'] == 'S') {
                updateUserStatus($userId, 'kyc_complient', 'Y');
                 $db->updateUserKycComplaint($userId, '1');
                $updated = true;
            }
            if(isset($kycResult['json']['kyc_status_remark']) && $kycResult['json']['kyc_status_remark'] != '') {
                $db->updateKycStatusRemark($userId, $kycResult['json']['kyc_status_remark']);
            }
        }
        
        // 2. Check Risk Profile status
        $currentRiskProfile = getUserStatusField($userId, 'risk_profile_submitted');
        $newRiskProfileStatus = 'N';
        if (isset($userData['RISK_CATEGORY_ID']) && 
            !empty($userData['RISK_CATEGORY_ID']) && 
            $userData['RISK_CATEGORY_ID'] > 0) {
            $newRiskProfileStatus = 'Y';
        }
        
        if ($currentRiskProfile != $newRiskProfileStatus) {
            if (updateUserStatus($userId, 'risk_profile_submitted', $newRiskProfileStatus)) {
                $updated = true;
            }
        }
        
        // 3. Check Personal Details status
        $currentPersonalDetails = getUserStatusField($userId, 'personal_details_submitted');
        $newPersonalDetailsStatus = 'N';
        
        if (!empty($userData['PRIMARY_HOLDER_NAME']) && 
            !empty($userData['PRIMARY_HOLDER_DOB']) &&
            !empty($userData['EMAIL']) &&
            !empty($userData['INDIAN_MOBILE_NO']) &&
            !empty($userData['PRIMARY_HOLDER_PAN'])) {
            $newPersonalDetailsStatus = 'Y';
        }
        
        if ($currentPersonalDetails != $newPersonalDetailsStatus) {
            if (updateUserStatus($userId, 'personal_details_submitted', $newPersonalDetailsStatus)) {
                $updated = true;
            }
        }
        
        // 4. Check Nominee Details status - FIXED: Check both NOMINATION = 'N' (opted out) OR has nominee name
        $currentNomineeDetails = getUserStatusField($userId, 'nominee_details_submitted');
        // If user has nominee name OR has explicitly opted out of nomination (NOMINATION = 'N'), mark as completed
        $hasNomineeName = !empty($userData['NOMINEE_1_NAME']);
        $hasOptedOut = (isset($userData['NOMINATION']) && $userData['NOMINATION'] === 'N');
        $newNomineeDetailsStatus = ($hasNomineeName || $hasOptedOut) ? 'Y' : 'N';
        
        if ($currentNomineeDetails != $newNomineeDetailsStatus) {
            if (updateUserStatus($userId, 'nominee_details_submitted', $newNomineeDetailsStatus)) {
                $updated = true;
                error_log("Nominee status updated for user $userId: hasNomineeName=$hasNomineeName, hasOptedOut=$hasOptedOut, newStatus=$newNomineeDetailsStatus");
            }
        }
        
        // 5. Sync from NSE if required fields are still pending
        $fatcaStatus = getUserStatusField($userId, 'fatca_done');
        $activationStatus = getUserStatusField($userId, 'activation_status');
        
        if ($fatcaStatus === 'N' || $activationStatus === 'N') {
            if (!empty($userData['CLIENT_CODE'])) {
                require_once __DIR__ . '/nseapiconnect.php';
                $syncApiObj = new NseApiConnect();
                $syncApiObj->syncClientStatus($userData['CLIENT_CODE']);
                $updated = true;
            }
        }
        
        // 6. Check Bank Details status
        $currentBankDetails = getUserStatusField($userId, 'bank_details_submitted');
        $newBankDetailsStatus = 'N';
        
        if (!empty($userData['ACCOUNT_NO_1']) && 
            !empty($userData['IFSC_CODE_1']) &&
            !empty($userData['BD_BANK_CODE_1'])) {
            $newBankDetailsStatus = 'Y';
        }
        
        if ($currentBankDetails != $newBankDetailsStatus) {
            if (updateUserStatus($userId, 'bank_details_submitted', $newBankDetailsStatus)) {
                $updated = true;
            }
        }
        
        return $updated;
        
    } catch (Exception $e) {
        error_log("Error syncing user status: " . $e->getMessage());
        return false;
    }
}

// Check if all requirements are met and activate account
function checkAndActivateAccount($userId) {
    global $db;
    return $db->checkAndActivateAccount($userId);
}

// Function to get user status with proper field mapping for frontend
function getUserStatusForFrontend($userId) {
    global $db;
    return $db->getUserStatusForFrontend($userId);
}

// ==================== MAIN REQUEST HANDLING ====================
$requestId = uniqid('req_', true);

// Authenticate user
$userId = authenticateRequest();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!$userId) {
        sendResponse(false, 'Authentication required. Please login again.', null, 401);
    }
    
    try {
        // Ensure record exists before syncing
        ensureUserStatusRecord($userId);
        
        // Sync all status
        $syncResult = syncUserStatusFromUserData($userId);
        
        // Get final status for response
        $userAccountStatus = getUserStatusForFrontend($userId);
        
        if (!$userAccountStatus) {
            error_log("ERROR: Could not retrieve user status after sync");
            sendResponse(false, 'Could not retrieve user status');
        }
        
        sendResponse(true, 'User account status retrieved', $userAccountStatus);
        
    } catch (Exception $e) {
        error_log("GET Exception: " . $e->getMessage());
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
} elseif ($method === 'POST') {
    error_log("POST method not implemented");
    sendResponse(false, 'Method not implemented', null, 501);
} else {
    error_log("Method not allowed: " . $method);
    sendResponse(false, 'Method not allowed', null, 405);
}
?>