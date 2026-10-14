<?php
//require_once('../cors.php'); // Adjust path if needed

require_once('portfolio.php');
//require_once('../database.php');

$db = new MySQLDB();
//$userUCC = '5011782684';
$userUCC = isset($_POST['ucc']) ? $_POST['ucc'] : '';

if (empty($userUCC)) {
    echo json_encode(["Id" => 2, "Result" => "fail", "Message" => "UCC is required", "Success" => false]);
    exit;
}

// Fetch portfolio data for the given UCC
list($portfolioInfo, $zeroHoldingInfo, $totalHoldingsMF, $totalAssetInfo, $HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo, $HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo, $HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo, $goalsFundInfo) = calculateHoldings($userUCC);

$response = [
    "Id" => 0,
    "Result" => "Pass",
    "Message" => "Folio data retrieved successfully",
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
    "Success" => true
];

echo json_encode($response);
?>
