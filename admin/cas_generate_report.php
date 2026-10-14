<?php
require_once('auth_guard.php');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/cas_portfolio_calc.php';
require_once __DIR__ . '/benchmark_xirr.php';

use Mpdf\Mpdf;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$ucc = $_GET['ucc'] ?? $_POST['ucc'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? 'view';
$advFilter = $_GET['adv_filter'] ?? $_POST['adv_filter'] ?? 'exclude';
$advArn = $_GET['adv_arn'] ?? $_POST['adv_arn'] ?? '';

if (empty($ucc)) {
    die("UCC is required.");
}

$db = new MYSQLDB();

// Subadmin check
$isSubAdmin = isset($_SESSION["superuser"]) && $_SESSION["superuser"] == 0;
if ($isSubAdmin) {
    $subAdminId = $_SESSION["userid"] ?? 0;
    if (!$db->isClientAssignedToSubadmin($ucc, $subAdminId)) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-family: sans-serif; border: 1px solid #ef9a9a; border-radius: 5px;'><strong>Access Denied:</strong> This client CAS report is not assigned to your subadmin account.</div>");
    }
}

$profile = $db->getProfileData($ucc);
$clientName = $profile[0]['PRIMARY_HOLDER_NAME'] ?? 'Client';
$clientEmail = $profile[0]['EMAIL'] ?? '';

// Calculate CAS Holdings using the new function
$casData = calculateCasHoldings($ucc, $advFilter, $advArn);

// Unpack the massive return array
list($portfolioInfo, $ZeroHoldingFundInfo, $totalHoldingsMF, $totalAssetInfo, 
     $HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo, 
     $HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo, 
     $HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo, 
     $goalsFundInfo, $totalCashFlows, $totalTimePeriods, 
     $fundCashFlowRegistry, $companyAgg, $sectorAgg, $fundMetrics, 
     $totalMFValue, $portfolioMcap, $runningSIPInfo, $sipTransactions, $mcapHistory) = $casData;

    $totalCurrentValue = (float)str_replace(',', '', $totalAssetInfo[0]['Total_Current_Value'] ?? 0);
    $bm = new BenchmarkXirr($db->getPDO());

    // Calculate ALL assets (Includes HELD and SOLD)
    $sinceInception = $bm->computeBenchmarkFromRegistry($fundCashFlowRegistry, 'nifty500', 'ALL');
    $holdingDaysAll = $sinceInception['holding_days'] ?? 0;
    $rolling3yr     = [];
    $rolling5yr     = [];
    if ($holdingDaysAll >= 1095) {
        $rolling3yr = $bm->computeRollingXirrFromRegistry($fundCashFlowRegistry, $totalCurrentValue, 3, 'nifty500', 'ALL');
    }
    if ($holdingDaysAll >= 1825) {
        $rolling5yr = $bm->computeRollingXirrFromRegistry($fundCashFlowRegistry, $totalCurrentValue, 5, 'nifty500', 'ALL');
    }

    // Calculate ONLY HELD assets
    $sinceInceptionHeld = $bm->computeBenchmarkFromRegistry($fundCashFlowRegistry, 'nifty500', 'HELD');
    $holdingDaysHeld    = $sinceInceptionHeld['holding_days'] ?? 0;
    $rolling3yrHeld     = [];
    $rolling5yrHeld     = [];
    if ($holdingDaysHeld >= 1095) {
        $rolling3yrHeld = $bm->computeRollingXirrFromRegistry($fundCashFlowRegistry, $totalCurrentValue, 3, 'nifty500', 'HELD');
    }
    if ($holdingDaysHeld >= 1825) {
        $rolling5yrHeld = $bm->computeRollingXirrFromRegistry($fundCashFlowRegistry, $totalCurrentValue, 5, 'nifty500', 'HELD');
    }

    $headName = $clientName;
    $userpan  = $profile[0]['PRIMARY_HOLDER_PAN'] ?? 'PAN not found';
    $address  = $profile[0]['ADDRESS_1'] ?? 'Not available';

    $isCasReport = true;

    ob_start();
    include __DIR__ . '/report-templates/report_main.php';
    $html = ob_get_clean();

// Setup mPDF
$mpdf = new Mpdf([
    'mode'              => 'utf-8',
    'format'            => 'A4',
    'default_font_size' => 8,
    'default_font'      => 'dejavusans',
    'margin_left'       => 10,
    'margin_right'      => 10,
    'margin_top'        => 22,
    'margin_bottom'     => 15,
    'margin_header'     => 5,
    'margin_footer'     => 5,
    'tempDir'           => __DIR__ . '/tmp'
]);

$mpdf->SetTitle($headName . ' - CAS Portfolio Report');

$logoPath = __DIR__ . '/../img/logo.png';
$logoTag  = file_exists($logoPath)
    ? '<img src="data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) . '" style="height:18px;"/>'
    : '<strong style="font-size:9pt;color:#2C5F8A;">LitmFin</strong>';

$mpdf->SetHTMLHeader('
    <table width="100%" style="border-bottom:1px solid #ddd;padding-bottom:3px;">
        <tr>
            <td style="width:40%">' . $logoTag . '</td>
            <td style="text-align:right;color:#888;font-size:8pt;font-family:sans-serif;">
                CAS Portfolio Statement &mdash; ' . htmlspecialchars($headName) . '
            </td>
        </tr>
    </table>
');
$mpdf->SetHTMLFooter('
    <table width="100%">
        <tr>
            <td style="font-size:7pt;color:#aaa;font-family:sans-serif;">
                LitmFin Pvt Ltd &bull; {DATE j M Y} &bull; Confidential
            </td>
            <td style="text-align:right;font-size:7pt;color:#aaa;font-family:sans-serif;">
                Page {PAGENO} of {nbpg}
            </td>
        </tr>
    </table>
');

$cssFile = __DIR__ . '/report-templates/report_styles.css';
if (file_exists($cssFile)) {
    $mpdf->WriteHTML(file_get_contents($cssFile), \Mpdf\HTMLParserMode::HEADER_CSS);
}
$mpdf->WriteHTML($html);

$safeName = preg_replace('/[^A-Za-z0-9]/', '_', $clientName);
$filename = $safeName . '_CAS_Portfolio_' . date('dMy') . '.pdf';

if ($action === 'view') {
    $mpdf->Output($filename, 'I'); // Inline view
} elseif ($action === 'download') {
    $mpdf->Output($filename, 'D'); // Force Download
} elseif ($action === 'email') {
    header('Content-Type: application/json');
    
    if (empty($clientEmail)) {
        echo json_encode(['success' => false, 'message' => 'No email address found for this client.']);
        exit;
    }
    
    $tmpPath = __DIR__ . '/tmp/' . $filename;
    $mpdf->Output($tmpPath, 'F');
    
    $companyConfig = $db->getCompanyConfig();
    $supportEmail = $companyConfig['support_email'] ?? 'support@example.com';
    $companyName = $companyConfig['name'] ?? 'LitmFin';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'localhost';
        $mail->Port = 25;
        $mail->SMTPAuth = false;
        $mail->setFrom($supportEmail, $companyName);
        $mail->addAddress($clientEmail, $clientName);
        $mail->Subject = 'Your CAS Consolidated Portfolio Report';
        $mail->Body = "Dear {$clientName},<br><br>Please find attached your latest CAS Consolidated Portfolio Report outlining your current holdings and historical performance.<br><br>Regards,<br>{$companyName}";
        $mail->isHTML(true);
        $mail->addAttachment($tmpPath, $filename);
        $mail->send();
        
        @unlink($tmpPath);
        echo json_encode(['success' => true, 'message' => "Report successfully sent to {$clientEmail}."]);
    } catch (Exception $e) {
        @unlink($tmpPath);
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Error: ' . $mail->ErrorInfo]);
    }
}