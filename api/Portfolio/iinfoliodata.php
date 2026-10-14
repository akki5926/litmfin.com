<?php
//require_once('../cors.php'); // Adjust path if needed

require_once('../../admin/portfolio.php');
//require_once('../database.php');

$db = new MySQLDB();
//$userIIN = '5011782684';
$userIIN = isset($_POST['iin']) ? $_POST['iin'] : '';

if (empty($userIIN)) {
    echo json_encode(["Id" => 2, "Result" => "fail", "Message" => "IIN is required", "Success" => false]);
    exit;
}

// Fetch portfolio data for the given IIN
$holdingsData = calculateHoldings($userIIN);
list($portfolioInfo, $zeroHoldingInfo, $totalHoldingsMF, $totalAssetInfo, $HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo, $HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo, $HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo, $goalsFundInfo) = $holdingsData;
$HoldingInfoSif = $holdingsData[24] ?? [];
$TotalHoldingInfoSif = $holdingsData[25] ?? [];
$SoldSifInfo = $holdingsData[26] ?? [];

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
    "HoldingInfoSif" => $HoldingInfoSif,
    "TotalHoldingInfoSif" => $TotalHoldingInfoSif,
    "SoldSifInfo" => $SoldSifInfo,
    "Success" => true
];

echo json_encode($response);
?>
