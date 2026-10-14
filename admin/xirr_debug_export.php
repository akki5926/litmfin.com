<?php
require_once('auth_guard.php');

/**
 * xirr_debug_export.php — XIRR debug Excel download (browser URL)
 * Usage: xirr_debug_export.php?i=IIN_NUMBER
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';
//require_once __DIR__ . '/financial_class.php';
require_once __DIR__ . '/benchmark_xirr.php';
require_once __DIR__ . '/xirr_helpers.php';

if (empty($_REQUEST['i'])) die('Usage: xirr_debug_export.php?i=IIN_NUMBER');

$userIIN = trim($_REQUEST['i']);
$db      = new MYSQLDB();
$pdo     = $db->getPDO();

require_once __DIR__ . '/portfolio.php';

list($portfolioInfo, $ZeroHoldingFundInfo, $totalHoldingsMF, $totalAssetInfo,
     $HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo,
     $HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo,
     $HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo,
     $goalsFundInfo, $totalCashFlows, $totalTimePeriods,
     $fundCashFlowRegistry
) = calculateHoldings($userIIN);

if (empty($fundCashFlowRegistry)) die('No cashflow data for IIN: ' . htmlspecialchars($userIIN));

$headName = '';
foreach ($db->getProfileData($userIIN) as $row) { $headName = $row['usr_full_name'] ?? ''; break; }

$rows        = buildXirrRows($fundCashFlowRegistry, $pdo);
$xirrSummary = buildXirrSummaryTable($fundCashFlowRegistry, $pdo);

if (empty($rows)) die('No transaction rows for IIN: ' . htmlspecialchars($userIIN));

$spreadsheet = buildXirrSpreadsheet($rows, $userIIN, $headName, $xirrSummary);
$safe        = preg_replace('/[^A-Za-z0-9_\-]/', '_', $headName ?: $userIIN);
$filename    = $safe . '_XIRR_Debug_' . date('dMY') . '.xlsx';

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
$spreadsheet->disconnectWorksheets();
exit;
