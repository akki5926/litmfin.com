<?php
/**
 * Recommended Funds API Endpoints
 */
require_once __DIR__ . '/includes/cors.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug function to log SQL queries and errors
function debugLog($message, $data = null) {
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $logMessage .= ' - ' . print_r($data, true);
        } else {
            $logMessage .= ' - ' . $data;
        }
    }
    // error_log($logMessage);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

debugLog("API Request", [
    'method' => $method,
    'action' => $action,
    'GET' => $_GET,
    'REQUEST_URI' => $_SERVER['REQUEST_URI']
]);

if ($method !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Central routing
switch ($action) {
    
    case 'get_by_category':
        $category_id = $_GET['category_id'] ?? '';
        debugLog("get_by_category called", ['category_id' => $category_id]);
        if (!$category_id) {
            sendResponse(false, 'category_id parameter is required');
        }
        handleGetFundsByCategory($db, $category_id);
        break;
        
    case 'get_risk_profile':
        $risk_code = $_GET['risk_code'] ?? '';
        debugLog("get_risk_profile called", ['risk_code' => $risk_code]);
        if (!$risk_code) {
            sendResponse(false, 'risk_code parameter is required');
        }
        handleGetRiskProfile($db, $risk_code);
        break;
        
    case 'get_all_funds':
        debugLog("get_all_funds called");
        handleGetAllRecommendedFunds($db);
        break;
        
    case 'get_categories':
        debugLog("get_categories called");
        handleGetFundCategories($db);
        break;
        
    default:
        debugLog("Invalid action", $action);
        sendResponse(
            false,
            'Invalid action. Available actions: get_by_category, get_risk_profile, get_all_funds, get_categories',
            null,
            404
        );
}
/**
 * Get recommended funds by category ID
 */
function handleGetFundsByCategory(MfdDb $db, string $category_id): void {
    try {
        debugLog("handleGetFundsByCategory started", ['category_id' => $category_id]);
        
        $processedFunds = $db->getRecommendedFundsWithSchemeInfo($category_id);
        
        if (empty($processedFunds)) {
            sendResponse(true, "No recommended funds found for category ID: {$category_id}", []);
        } else {
            sendResponse(true, "Recommended funds retrieved successfully", $processedFunds);
        }
        
    } catch (Exception $e) {
        debugLog("Exception in handleGetFundsByCategory", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Get risk profile details
 */
function handleGetRiskProfile(MfdDb $db, string $risk_code): void {
    try {
        debugLog("handleGetRiskProfile started", ['risk_code' => $risk_code]);
        
        $profile = $db->getRiskProfileDetailsByCode($risk_code);
        
        if (!$profile) {
            sendResponse(false, "Risk profile not found for code: {$risk_code}");
        } else {
            sendResponse(true, "Risk profile retrieved successfully", $profile);
        }
        
    } catch (Exception $e) {
        debugLog("Exception in handleGetRiskProfile", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Get all recommended funds
 */
function handleGetAllRecommendedFunds(MfdDb $db): void {
    try {
        debugLog("handleGetAllRecommendedFunds started");
        
        $funds = $db->getAllRecommendedFunds();
        
        sendResponse(true, "All recommended funds retrieved successfully", $funds);
        
    } catch (Exception $e) {
        debugLog("Exception in handleGetAllRecommendedFunds", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Get all fund categories
 */
function handleGetFundCategories(MfdDb $db): void {
    try {
        debugLog("handleGetFundCategories started");
        
        $categories = $db->getRecommendedFundCategories();
        
        sendResponse(true, "Fund categories retrieved successfully", $categories);
        
    } catch (Exception $e) {
        debugLog("Exception in handleGetFundCategories", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}
?>