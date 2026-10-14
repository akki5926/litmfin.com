<?php
/**
 * User Account Status API Endpoints
 * get_cas_portfolio_api.php
 */
define('API_CALL', true);
require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/../admin/database.php';
ini_set('display_errors', 0);
$db = new MySQLDB();

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
        //error_log("grand_folio_api.php : Authentication successful for user ID = " . $userId);
        return $userId;
    }
    
    //error_log("JWT verification failed");
    return null;
}

// ==================== MAIN REQUEST HANDLING ====================
/*
error_log("=== User Account Status API REQUEST START ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Remote Addr: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
*/
// Authenticate user
$userId = authenticateRequest();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // GET endpoint: Fetch User Account Status details
    
    if (!$userId) {
        //error_log("GET: Authentication failed");
        sendResponse(false, 'Authentication required. Please login again.', null, 401);
    }
    
    //error_log("GET request for user ID: " . $userId);
    
    try {
		//error_log("grand_folio_api.php line 154: user id = " . $userId);
		require_once('../admin/cas_portfolio_calc.php');
		//error_log("grand_folio_api.php line 156: user id = " . $userId);
			$statusAllData = [];
			
			$userUCC = $db->getUserUccById($userId);

			//error_log("grand_folio_api.php : UCC = " . $userUCC);
			if(!empty($userUCC)) {
				$portfolioInfo = array();
				$totalAssetInfo = array();
				$zeroHoldingInfo = array();
				
				$casData = calculateCasHoldings($userUCC);
				list($portfolioInfo, $ZeroHoldingFundInfo, $totalHoldingsMF, $totalAssetInfo, 
					$HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo, 
					$HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo, 
					$HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo, 
					$goalsFundInfo, $totalCashFlows, $totalTimePeriods, 
					$fundCashFlowRegistry, $companyAgg, $sectorAgg, $fundMetrics, 
					$totalMFValue, $portfolioMcap, $runningSIPInfo, $sipTransactions, $mcapHistory) = $casData;
				//error_log("gget_cas_portfolio_api.php :  = " . print_r($runningSIPInfo, true));
				$families = '';//$db->getFamiliesByAnyIIN($userUCC);
				
				echo json_encode(array(
					"Id"=>0,
					"Result"=>"Pass",
					"Message"=>"all Status displayed",
					"portfolioInfo"=>$portfolioInfo,
					"zeroHoldingInfo"=>$zeroHoldingInfo,
					"totalHoldingsMF"=>$totalHoldingsMF,
					"totalAssetInfo"=>$totalAssetInfo,
					"HoldingInfoBonds"=>$HoldingInfoBonds,
					"TotalHoldingInfoBonds"=>$TotalHoldingInfoBonds,
					"ZeroHoldingBondInfo"=>$ZeroHoldingBondInfo,
					"HoldingInfoPms"=>$HoldingInfoPms,
					"TotalHoldingInfoPms"=>$TotalHoldingInfoPms,
					"SoldPmsInfo"=>$SoldPmsInfo,
					"HoldingInfoAif"=>$HoldingInfoAif,
					"TotalHoldingInfoAif"=>$TotalHoldingInfoAif,
					"SoldAifInfo"=>$SoldAifInfo,
					"goalsFundInfo"=>$goalsFundInfo,
					"runningSIPInfo"=>$runningSIPInfo,
					"Success" => true,
					"families" => $families 
				));
				exit;
			} else {
				echo json_encode(array("Id"=>1,"Result"=>'fail',"Message"=>'User IIN not found',"Success"=>false));
				exit;
			}
		
	} catch (Exception $e) {
		http_response_code(500);
		echo json_encode(array(
			"Id" => 1,
			"Result" => "Fail",
			"Message" => "An error occurred while processing your request",
			"Success" => false
		));
		exit;
	}

} elseif ($method === 'POST') {
    // POST endpoint: Save/Update User Account Status 
    
} else {
    error_log("Method not allowed: " . $method);
    sendResponse(false, 'Method not allowed', null, 405);
}

//error_log("=== User Account Status API REQUEST END ===");
?>