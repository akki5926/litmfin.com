<?php
require_once('auth_guard.php');

/**
 * send_report_email.php
 *
 * Generates a portfolio PDF (via generatereport.php) and emails it to the client.
 *
 * Can be called:
 *   A) From browser : send_report_email.php?i=IIN[&family=1]
 *   B) From PHP     : require_once 'send_report_email.php';
 *                     sendReportEmail(string $userIIN, int $family = 0): string
 *
 * All PDF rendering is delegated to generateReport() in generatereport.php.
 * SMTP configuration is read from $db->getSmtpConfig() — same source used by
 * $db->sendMailViaSMTP() — so credentials are always in one place (config file).
 * This file adds PDF attachment support on top of that.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';
// Define this constant BEFORE including generatereport.php.
// It prevents the browser entry point inside generatereport.php from
// auto-firing (with $_REQUEST['i']) the moment the file is included,
// which would consume the output buffer and produce a blank PDF.
define('GENERATING_REPORT', true);
require_once __DIR__ . '/generatereport.php';   // provides generateReport()

// -- Browser entry point -------------------------------------------------------
$action = $_REQUEST['action'] ?? 'send';

$isSubAdmin = isset($_SESSION["superuser"]) && $_SESSION["superuser"] == 0;
$subAdminId = $_SESSION["userid"] ?? 0;

if ($action === 'auto_email_stats') {
    // Return JSON stats for the badge in consolidatedPortfolio.php
    try {
        $db    = new MYSQLDB();
        if ($isSubAdmin) {
            $stats = $db->getAutoEmailStats4subAdmin($subAdminId);
        } else {
            $stats = $db->getAutoEmailStats();
        }
        header('Content-Type: application/json');
        echo json_encode($stats);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'send' && isset($_REQUEST['i'])) {
    if ($isSubAdmin) {
        $dbCheck = new MYSQLDB();
        if (!$dbCheck->isClientAssignedToSubadmin(trim($_REQUEST['i']), $subAdminId)) {
            echo "Error: Access Denied. This client portfolio is not assigned to your subadmin account.";
            exit;
        }
    }
    $isBulk = !empty($_REQUEST['is_bulk']);
    $result = sendReportEmail(
        trim($_REQUEST['i']),
        (int)($_REQUEST['family'] ?? 0),
        $isBulk
    );
    echo $result;
    exit;
}

// -----------------------------------------------------------------------------
/**
 * Generate the portfolio PDF (via generatereport.php) and email it to the client.
 *
 * @param  string $userIIN  Client IIN / UCC
 * @param  int    $family   1 = family report, 0 = individual
 * @param  bool   $isBulk   True if sending in bulk (respects auto-email pref)
 * @return string           Status message
 */
function sendReportEmail(string $userIIN, int $family = 0, bool $isBulk = false): string
{
    // -- 1. Fetch client profile -----------------------------------------------
    $db            = new MYSQLDB();
    $companyConfig = $db->getCompanyConfig();

    $profiledata = $db->getProfileData($userIIN);
    $headName    = 'Name Not Found';
    $userEmailId = '';
    foreach ($profiledata as $row) {
        $headName    = $row['PRIMARY_HOLDER_NAME'] ?? 'Name Not Found';
        $userEmailId = $row['EMAIL']               ?? '';
        break;
    }

    if (empty($userEmailId)) {
        return "Email could not be sent: no email address found for IIN $userIIN.";
    }

    // Respect AUTO_EMAIL flag ONLY during bulk sends
    if ($isBulk) {
        $userId        = $profiledata[0]['id'] ?? null;
        $autoEmailPref = $userId ? $db->getAutoEmailPreference($userId) : 1; // default allow if no id
        if ($autoEmailPref === 0) {
            return "Skipped $headName — Auto Email is disabled for this account.";
        }
    }

    // -- 2. Generate PDF and save it to disk -----------------------------------
    $reportsDir = __DIR__ . '/emails/';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }

    // Close any active output buffers before calling generateReport().
    // generateReport() uses ob_start()/ob_get_clean() internally to capture
    // the HTML template. If an outer buffer is already open (from the browser
    // request that triggered this file), ob_get_clean() only cleans the inner
    // buffer and $html comes back empty — producing a blank PDF.
    // We drain all active buffers here, then re-open one clean buffer after.
    $obLevel = ob_get_level();
    for ($i = 0; $i < $obLevel; $i++) {
        ob_end_clean();
    }

    $generateResult = generateReport($userIIN, $family, [
        'save'        => true,
        'reports_dir' => $reportsDir,
    ]);

    // Re-open one buffer so subsequent output is handled normally.
    ob_start();

    if (empty($generateResult['pdf']) || !file_exists($generateResult['pdf'])) {
        return "Email could not be sent to $headName: PDF generation failed.";
    }

    $pdfPath        = $generateResult['pdf'];
    $pdfBasename    = basename($pdfPath);

    // -- 3. Build email summary data from DB -----------------------------------
    //    We pull totalAssetInfo directly from calculateHoldings() so that the
    //    email summary table is consistent with the PDF content.
    $mailIndividualData = [];
    $mailFamilyData     = [];

    if ($family) {
        // Family: accumulate totals across all members
        $familyDetails = $db->getFamilyByUCC($userIIN);
        $members = [
            ['iin' => $userIIN,                                'relation' => 'Self'],
            ['iin' => $familyDetails['member1_UCC'] ?? '',     'relation' => $familyDetails['member1_rel'] ?? ''],
            ['iin' => $familyDetails['member2_UCC'] ?? '',     'relation' => $familyDetails['member2_rel'] ?? ''],
            ['iin' => $familyDetails['member3_UCC'] ?? '',     'relation' => $familyDetails['member3_rel'] ?? ''],
        ];

        $summableFields = [
            'Total_Equity_Cost',   'Total_Debt_Cost',   'Total_Hybrid_Cost',   'Total_Gold_Cost',
            'Total_Equity_Curr_Value', 'Total_Debt_Curr_Value', 'Total_Hybrid_Curr_Value', 'Total_Gold_Curr_Value',
            'Total_Unrealized', 'Total_Booked', 'Total_Current_Value', 'Total_Cost_Value',
        ];

        $familyTotals = [];

        foreach ($members as $member) {
            if (empty($member['iin'])) continue;

            list(, , , $totalAssetInfo) = calculateHoldings($member['iin']);

            if (empty($totalAssetInfo[0])) continue;

            if (empty($familyTotals)) {
                foreach ($totalAssetInfo[0] as $k => $v) {
                    $familyTotals[$k] = in_array($k, $summableFields)
                        ? (float) str_replace(',', '', $v)
                        : $v;
                }
            } else {
                foreach ($summableFields as $k) {
                    $familyTotals[$k] = ($familyTotals[$k] ?? 0)
                        + (float) str_replace(',', '', $totalAssetInfo[0][$k] ?? 0);
                }
            }
        }

        // Compute asset shares
        $totalCV = $familyTotals['Total_Current_Value'] ?? 0;
        foreach (['Equity', 'Debt', 'Hybrid', 'Gold'] as $cls) {
            $familyTotals["{$cls}_Asset_Share"] = $totalCV > 0
                ? round($familyTotals["Total_{$cls}_Curr_Value"] / $totalCV * 100, 2)
                : 0;
        }

        // Format for display
        foreach ($familyTotals as $k => $v) {
            if (in_array($k, $summableFields)) {
                $familyTotals[$k] = number_format($v, 2, '.', ',');
            } elseif (substr($k, -12) === '_Asset_Share') {
                $familyTotals[$k] = number_format($v, 2, '.', '');
            }
        }

        $mailFamilyData = [$familyTotals];

    } else {
        // Individual: use asset summary rows for the email table
        list(, , , $totalAssetInfo) = calculateHoldings($userIIN);
        $assets = $totalAssetInfo[0] ?? [];

        $rows = [
            ['Equity', 'Total_Equity_Cost',  'Total_Equity_Curr_Value',  'Total_Equity_Return'],
            ['Debt',   'Total_Debt_Cost',     'Total_Debt_Curr_Value',    'Total_Debt_Return'],
            ['Hybrid', 'Total_Hybrid_Cost',   'Total_Hybrid_Curr_Value',  'Total_Hybrid_Return'],
            ['Gold',   'Total_Gold_Cost',     'Total_Gold_Curr_Value',    'Total_Gold_Return'],
            ['Total',  'Total_Cost_Value',    'Total_Current_Value',      'Total_Holding_Return'],
        ];
        foreach ($rows as [$label, $costKey, $currKey, $xirrKey]) {
            $mailIndividualData[] = [[
                $label,
                $assets[$costKey]  ?? '0.00',
                '',
                '',
                $assets[$currKey]  ?? '0.00',
                $assets[$xirrKey]  ?? '0.00',
            ]];
        }
    }

    // -- 4. Compose & send email -----------------------------------------------
    return _dispatchEmail(
        $db,
        $headName,
        $userEmailId,
        $mailIndividualData,
        $mailFamilyData,
        $family,
        $pdfPath,
        $pdfBasename,
        $companyConfig
    );
}


// =============================================================================
// Internal helpers
// =============================================================================

/**
 * Build email body and send via PHPMailer, using the same SMTP config
 * as $db->sendMailViaSMTP() — credentials stay in one place (config file).
 * Adds PDF attachment support on top.
 */
function _dispatchEmail(
    MYSQLDB $db,
    string  $headName,
    string  $userEmailId,
    array   $mailIndividualData,
    array   $mailFamilyData,
    int     $family,
    string  $pdfPath,
    string  $pdfBasename,
    array   $companyConfig
): string {
    // -- SMTP config from $db (same source as $db->sendMailViaSMTP) ------------
    $smtp = $db->getSmtpConfig();

    $dateFormatted = _formatDate();
    $subject       = $family ? 'Family Portfolio Statement' : 'Portfolio Statement';
    $emailContent  = generateEmailContent(
        $headName, $dateFormatted, $family,
        $mailIndividualData, $mailFamilyData, $companyConfig
    );

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();

        // Mirror the same SSL options used in $db->sendMailViaSMTP()
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->Host       = $smtp['email_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['from_email'];
        $mail->Password   = trim($smtp['from_email_password'], '"');
        $mail->SMTPSecure = $smtp['secure'];
        $mail->Port       = (int) $smtp['email_port'];

        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        $mail->addAddress($userEmailId, $headName);

        if (!empty($smtp['cc_email']))  $mail->addCC($smtp['cc_email']);
        if (!empty($smtp['bcc_email'])) $mail->addBCC($smtp['bcc_email']);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $emailContent;
        $mail->AltBody = 'Please view this email in an HTML-capable email client.';

        // Attach the generated PDF
        $mail->addAttachment($pdfPath, $pdfBasename);

        $mail->send();
        return "We have sent an email to $headName successfully!";

    } catch (Exception $e) {
        return "Email could not be sent to $headName. Error: " . $mail->ErrorInfo;
    }
}


/**
 * Build the full HTML email body.
 */
function generateEmailContent(
    string $headName,
    string $dateFormatted,
    int    $isFamily,
    array  $individualData,
    array  $familyData,
    array  $companyConfig = []
): string {
    $reportType  = $isFamily ? 'Family Portfolio' : 'Portfolio';
    $companyName = $companyConfig['name']          ?? '';
    $website     = $companyConfig['website']        ?? '';
    $phone       = $companyConfig['contact_phone']  ?? '';
    $address     = $companyConfig['address']        ?? '';
    $logoUrl     = $companyConfig['logo_url']        ?? '';

    $portfolioTable = $isFamily
        ? generateFamilyPortfolioTable($familyData)
        : generateIndividualPortfolioTable($individualData);

    $allocationChart = $isFamily
        ? generatePortfolioAllocation($familyData, true)
        : generatePortfolioAllocation($individualData, false);

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\"UTF-8\">
        <style>
            body { font-family: Arial, sans-serif; }
            table { border-collapse: collapse; }
            th, td { border: 1px solid #cccccc; padding: 8px; }
        </style>
    </head>
    <body>
        <p>Dear $headName,</p>
        <p>Please find attached your {$reportType} report as on $dateFormatted.</p>

        <b>{$reportType} Summary</b><br><br>
        $portfolioTable

        $allocationChart
        <br>
        <p>The detailed portfolio information is available in the attached report.</p>
        <p>For any clarity, kindly get in touch with us at the number below.</p>

        <br>
        <p>Regards,<br>
        <span style='color:blue'><b>_________________________________</b></span><br>
        Customer Support<br>
        $companyName<br>
        $phone<br>
        $address<br>
        <a href='$website'>$website</a><br>
        " . ($logoUrl ? "<img src='$logoUrl' alt='Logo' width='150'>" : '') . "
        </p>
    </body>
    </html>";
}


/**
 * Build individual portfolio summary table for the email body.
 */
function generateIndividualPortfolioTable(array $portfolioData): string
{
    $table = '<table border="1" cellpadding="6" cellspacing="0" width="40%"
                    style="border-color:#dddddd;">
        <tr bgcolor="#f0f0f0">
            <th align="left"  style="border:1px solid #dddddd;padding:8px;">Asset Class</th>
            <th align="right" style="border:1px solid #dddddd;padding:8px;">Cost Value</th>
            <th align="right" style="border:1px solid #dddddd;padding:8px;">Current Value</th>
            <th align="right" style="border:1px solid #dddddd;padding:8px;">XIRR (%)</th>
        </tr>';

    $assetRows = [];
    $totalRow  = null;

    foreach ($portfolioData as $data) {
        $row = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;
        if (!isset($row[0], $row[1], $row[4], $row[5])) continue;

        if ($row[0] === 'Total') {
            $totalRow = $row;
        } else {
            $cv = (float) str_replace(',', '', $row[4]);
            if ($cv > 0) {
                $assetRows[] = ['name' => $row[0], 'cost' => $row[1],
                                'curr' => $row[4], 'xirr' => $row[5], '_cv' => $cv];
            }
        }
    }

    // Sort ascending by current value
    usort($assetRows, fn($a, $b) => $a['_cv'] <=> $b['_cv']);

    foreach ($assetRows as $r) {
        $table .= "<tr>
            <td style=\"border:1px solid #dddddd;padding:6px;\"><strong>{$r['name']}</strong></td>
            <td align=\"right\" style=\"border:1px solid #dddddd;padding:6px;\">{$r['cost']}</td>
            <td align=\"right\" style=\"border:1px solid #dddddd;padding:6px;\">{$r['curr']}</td>
            <td align=\"right\" style=\"border:1px solid #dddddd;padding:6px;\">{$r['xirr']}</td>
        </tr>";
    }

    if ($totalRow) {
        $table .= "<tr bgcolor=\"#e8f4fd\">
            <td style=\"border:1px solid #dddddd;padding:8px;\"><strong>{$totalRow[0]}</strong></td>
            <td align=\"right\" style=\"border:1px solid #dddddd;padding:8px;\"><strong>{$totalRow[1]}</strong></td>
            <td align=\"right\" style=\"border:1px solid #dddddd;padding:8px;\"><strong>{$totalRow[4]}</strong></td>
            <td align=\"right\" style=\"border:1px solid #dddddd;padding:8px;\"><strong>{$totalRow[5]}</strong></td>
        </tr>";
    }

    $table .= '</table>';
    return $table;
}


/**
 * Build family portfolio summary table for the email body.
 */
function generateFamilyPortfolioTable(array $familyData): string
{
    $data = isset($familyData[0]) && is_array($familyData[0]) ? $familyData[0] : $familyData;

    $table = '<table border="1" cellpadding="6" cellspacing="0" width="40%"
                    style="border-color:#dddddd;">
        <tr bgcolor="#f0f0f0">
            <th align="left"  style="border:1px solid #dddddd;padding:8px;">Asset Class</th>
            <th align="right" style="border:1px solid #dddddd;padding:8px;">Cost Value</th>
            <th align="right" style="border:1px solid #dddddd;padding:8px;">Current Value</th>
        </tr>';

    $assetMap = [
        'Equity' => ['Total_Equity_Cost', 'Total_Equity_Curr_Value'],
        'Debt'   => ['Total_Debt_Cost',   'Total_Debt_Curr_Value'],
        'Hybrid' => ['Total_Hybrid_Cost', 'Total_Hybrid_Curr_Value'],
        'Gold'   => ['Total_Gold_Cost',   'Total_Gold_Curr_Value'],
    ];

    $assetRows = [];
    foreach ($assetMap as $name => [$costKey, $currKey]) {
        $cv = (float) str_replace(',', '', $data[$currKey] ?? '0');
        if ($cv > 0) {
            $assetRows[] = [
                'name' => $name,
                'cost' => $data[$costKey] ?? '0.00',
                'curr' => $data[$currKey] ?? '0.00',
                '_cv'  => $cv,
            ];
        }
    }

    usort($assetRows, fn($a, $b) => $a['_cv'] <=> $b['_cv']);

    foreach ($assetRows as $r) {
        $table .= "<tr>
            <td style=\"border:1px solid #dddddd;padding:6px;\"><strong>{$r['name']}</strong></td>
            <td align=\"right\" style=\"border:1px solid #dddddd;padding:6px;\">{$r['cost']}</td>
            <td align=\"right\" style=\"border:1px solid #dddddd;padding:6px;\">{$r['curr']}</td>
        </tr>";
    }

    $totalCost = $data['Total_Cost_Value']    ?? '0.00';
    $totalCurr = $data['Total_Current_Value'] ?? '0.00';
    $table .= "<tr bgcolor=\"#e8f4fd\">
        <td style=\"border:1px solid #dddddd;padding:8px;\"><strong>Total</strong></td>
        <td align=\"right\" style=\"border:1px solid #dddddd;padding:8px;\"><strong>$totalCost</strong></td>
        <td align=\"right\" style=\"border:1px solid #dddddd;padding:8px;\"><strong>$totalCurr</strong></td>
    </tr>";

    $table .= '</table>';
    return $table;
}


/**
 * Build the allocation percentage table shown below the summary in the email.
 */
function generatePortfolioAllocation(array $portfolioData, bool $isFamily = false): string
{
    if ($isFamily) {
        $data        = isset($portfolioData[0]) && is_array($portfolioData[0]) ? $portfolioData[0] : $portfolioData;
        $assetShares = [
            'Equity' => (float)($data['Equity_Asset_Share'] ?? 0),
            'Debt'   => (float)($data['Debt_Asset_Share']   ?? 0),
            'Hybrid' => (float)($data['Hybrid_Asset_Share'] ?? 0),
            'Gold'   => (float)($data['Gold_Asset_Share']   ?? 0),
        ];
    } else {
        $assetShares = _calcIndividualShares($portfolioData);
    }

    asort($assetShares);   // ascending

    $table = '<br><br>
    <table border="0" cellpadding="8" cellspacing="0" width="35%">
        <tr bgcolor="#f8f9fa">
            <th colspan="2" align="center"
                style="border:1px solid #e0e0e0;padding:10px;background-color:#f8f9fa;">
                Portfolio Allocation
            </th>
        </tr>';

    foreach ($assetShares as $name => $share) {
        if ($share > 0) {
            $table .= "<tr>
                <td width=\"40%\"><strong>$name</strong></td>
                <td width=\"60%\" align=\"right\"><strong>{$share}%</strong></td>
            </tr>";
        }
    }

    $table .= '</table>';
    return $table;
}


/**
 * Calculate asset allocation percentages for an individual portfolio
 * from the $mailIndividualData rows (same format as the old code).
 */
function _calcIndividualShares(array $portfolioData): array
{
    $shares     = [];
    $totalValue = 0;

    // Find the Total row first
    foreach ($portfolioData as $data) {
        $row = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;
        if (isset($row[0], $row[4]) && $row[0] === 'Total') {
            $totalValue = (float) str_replace(',', '', $row[4]);
            break;
        }
    }

    if ($totalValue > 0) {
        foreach ($portfolioData as $data) {
            $row = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;
            if (isset($row[0], $row[4]) && $row[0] !== 'Total') {
                $cv = (float) str_replace(',', '', $row[4]);
                $shares[$row[0]] = round($cv / $totalValue * 100, 2);
            }
        }
    }

    return $shares;
}


/**
 * Format today's date as "30th April 2026".
 */
function _formatDate(): string
{
    $day   = (int) date('j');
    $month = date('F');
    $year  = date('Y');

    if (in_array($day, [1, 21, 31])) {
        $suffix = 'st';
    } elseif (in_array($day, [2, 22])) {
        $suffix = 'nd';
    } elseif (in_array($day, [3, 23])) {
        $suffix = 'rd';
    } else {
        $suffix = 'th';
    }

    return $day . $suffix . ' ' . $month . ' ' . $year;
}


function alert(string $msg): void
{
    echo "<script type='text/javascript'>alert('$msg');</script>";
}

//============================================================+
// END OF FILE
//============================================================+
