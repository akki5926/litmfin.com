<?php
// grandFamilyFolioData.php - Specific API for family portfolio data with IIN parameter

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
    // This API requires IIN parameter
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['iin']) || empty($input['iin'])) {
        echo json_encode(array(
            "Id" => 1,
            "Result" => "fail", 
            "Message" => "IIN parameter is required",
            "Success" => false
        ));
        exit;
    }
    
    $targetIIN = $input['iin'];
    $statusAllData = [];
    
    $portfolioInfo = array();
    $totalAssetInfo = array();
    $zeroHoldingInfo = array();
    
    $holdingsData = calculateHoldings($targetIIN);
    list($portfolioInfo, $zeroHoldingInfo, $totalHoldingsMF, $totalAssetInfo, $HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo, $HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo, $HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo, $goalsFundInfo) = $holdingsData;
    $HoldingInfoSif = $holdingsData[24] ?? [];
    $TotalHoldingInfoSif = $holdingsData[25] ?? [];
    $SoldSifInfo = $holdingsData[26] ?? [];
    
    $families = $db->getFamiliesByAnyIIN($targetIIN);
    
    echo json_encode(array(
        "Id" => 0,
        "Result" => "Pass",
        "Message" => "Family portfolio data displayed",
        "portfolioInfo" => $portfolioInfo,
        "zeroHoldingInfo" => $zeroHoldingInfo,
        "totalHoldingsMF" => $totalHoldingsMF,
        "totalAssetInfo" => $totalAssetInfo,
        "HoldingInfoBonds" => $HoldingInfoBonds,
        "TotalHoldingInfoBonds" => $TotalHoldingInfoBonds,
        "ZeroHoldingBondInfo" => $ZeroHoldingBondInfo,
        "HoldingInfoPms" => $HoldingInfoPms,
        "TotalHoldingInfoPms" => $TotalHoldingInfoPms,
        "SoldPmsInfo" => $SoldPmsInfo,
        "HoldingInfoAif" => $HoldingInfoAif,
        "TotalHoldingInfoAif" => $TotalHoldingInfoAif,
        "SoldAifInfo" => $SoldAifInfo,
        "HoldingInfoSif" => $HoldingInfoSif,
        "TotalHoldingInfoSif" => $TotalHoldingInfoSif,
        "SoldSifInfo" => $SoldSifInfo,
        "goalsFundInfo" => $goalsFundInfo,
        "Success" => true,
        "families" => $families 
    ));
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        "Id" => 1,
        "Result" => "Fail",
        "Message" => "An error occurred while processing your request: " . $e->getMessage(),
        "Success" => false
    ));
    exit;
}
?>