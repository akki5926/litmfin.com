<?php
// Handle preflight OPTIONS request first
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    $allowed_origins = [
        'http://localhost:4200',
        'https://www.litmfin.com'
    ];
    
    $http_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($http_origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $http_origin");
    } else {
        header("Access-Control-Allow-Origin: http://localhost:4200");
    }
    
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header('P3P: CP="CAO PSA OUR"');
    header("Content-Type: application/json; charset=utf-8");
    http_response_code(200);
    exit(0);
}

// Start session only for actual requests
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set CORS headers for actual requests
$allowed_origins = [
    'http://localhost:4200',
    'https://www.litmfin.com'
];

$http_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($http_origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $http_origin");
} else {
    header("Access-Control-Allow-Origin: http://localhost:4200");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('P3P: CP="CAO PSA OUR"');
header("Content-Type: application/json; charset=utf-8");

// Turn off error display for production, but log errors
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain'); // just for debugging

require_once('../../admin/portfolio.php');
$db = new MySQLDB();

// Error handler to catch fatal errors
function handleShutdown() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode(array(
            "Id" => 1,
            "Result" => "Fail", 
            "Message" => "Internal server error occurred",
            "Success" => false
        ));
    }
}
register_shutdown_function('handleShutdown');

try {
    if(isset($_SESSION['Thruesday27FebUID'])) {
        $user_id = $_SESSION['Thruesday27FebUID'];
        $statusAllData = [];
        
        $userIIN = $db->getUserIIN($user_id);
        if(isset($userIIN)) {
            $portfolioInfo = array();
            $totalAssetInfo = array();
            $zeroHoldingInfo = array();
            
            $holdingsData = calculateHoldings($userIIN);
            list($portfolioInfo, $zeroHoldingInfo, $totalHoldingsMF, $totalAssetInfo, $HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo, $HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo, $HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo, $goalsFundInfo) = $holdingsData;
            $HoldingInfoSif = $holdingsData[24] ?? [];
            $TotalHoldingInfoSif = $holdingsData[25] ?? [];
            $SoldSifInfo = $holdingsData[26] ?? [];
			
            $families = $db->getFamiliesByAnyIIN($userIIN);
			
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
                "HoldingInfoSif"=>$HoldingInfoSif,
                "TotalHoldingInfoSif"=>$TotalHoldingInfoSif,
                "SoldSifInfo"=>$SoldSifInfo,
                "goalsFundInfo"=>$goalsFundInfo,
                "Success" => true,
				"families" => $families 
            ));
            exit;
        } else {
            echo json_encode(array("Id"=>1,"Result"=>'fail',"Message"=>'User IIN not found',"Success"=>false));
			exit;
        }
    } else {
        echo json_encode(array("Id"=>1,"Result"=>'fail',"Message"=>'Session Expired, please login again!',"Success"=>false));
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
?>