<?php
// ================================================================
// DISABLE ERROR DISPLAY FOR AJAX RESPONSES
// ================================================================
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

// Clear any output buffers that might have been started
while (ob_get_level()) {
    ob_end_clean();
}

// ================================================================
// DEFINE API_CALL FOR auth_guard
// ================================================================
define('API_CALL', true);

require_once('auth_guard.php');

// ================================================================
// SET JSON HEADER BEFORE ANY OUTPUT
// ================================================================
header('Content-Type: application/json');

require_once('database.php');
require_once('../api/nseapiconnect.php');

$db     = new MYSQLDB();
$conn   = $db->getConnection();
$action = $_GET['action'] ?? '';

/* ══════════════════════════════════════════════════════════════════════════════
   ACTION: fetch_fatca - Fetch and save FATCA Report from NSE API
   ══════════════════════════════════════════════════════════════════════════════ */
if ($action === 'fetch_fatca' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientCodeInput = trim($_POST['client_code'] ?? '');
    $fromDate        = trim($_POST['from_date'] ?? '');
    $toDate          = trim($_POST['to_date'] ?? '');

    // Always parse and validate dates since NSE API requires from_date and to_date
    if (empty($fromDate) || empty($toDate)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'From Date and To Date are both mandatory.'
        ]);
        exit;
    }

    $fromTimestamp = strtotime($fromDate);
    $toTimestamp   = strtotime($toDate);
    $diff = ($toTimestamp - $fromTimestamp) / (60 * 60 * 24);

    if ($diff < 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'From Date cannot be after To Date.'
        ]);
        exit;
    }
    if ($diff > 30) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Please enter the correct detail'
        ]);
        exit;
    }

    $payload = [
        'from_date' => date('d-m-Y', $fromTimestamp),
        'to_date'   => date('d-m-Y', $toTimestamp)
    ];

    if (!empty($clientCodeInput)) {
        // UCC / Client Code / PAN is provided. Resolve to PAN/PEKRN numbers.
        $rawItems = array_filter(array_map('trim', explode(',', $clientCodeInput)));
        if (count($rawItems) > 50) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'A maximum of 50 comma-separated Client Codes is allowed.'
            ]);
            exit;
        }

        $resolvedIdentifiers = [];

        foreach ($rawItems as $item) {
            // Check if item is already a PAN (10 chars, alphanumeric)
            if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', $item)) {
                $resolvedIdentifiers[] = strtoupper($item);
                continue;
            }
            // Check if item is a PEKRN (usually 10 chars, begins with PEKRN prefix or matches formats)
            if (preg_match('/^[A-Z0-9]{10}$/i', $item) && stripos($item, 'PE') === 0) {
                $resolvedIdentifiers[] = strtoupper($item);
                continue;
            }

            // Otherwise, treat as Client Code and lookup PAN/PEKRN from database
            $escapedItem = $db->safeEscape($item);
            $userSql = "SELECT `PRIMARY_HOLDER_PAN`, `PRIMARY_HOLDER_PAN` as `pan_no` FROM `tb_users` WHERE `CLIENT_CODE` = '{$escapedItem}' LIMIT 1";
            $userRow = $db->selectSingle($userSql);
            
            if ($userRow) {
                $pan = trim($userRow['PRIMARY_HOLDER_PAN'] ?? $userRow['pan_no'] ?? '');
                if (!empty($pan) && $pan !== 'NA') {
                    $resolvedIdentifiers[] = strtoupper($pan);
                } else {
                    // Try PEKRN from local database
                    $pekSql = "SELECT `pekrn` FROM `fatca_details` WHERE `user_id` = (SELECT `id` FROM `tb_users` WHERE `CLIENT_CODE` = '{$escapedItem}' LIMIT 1) LIMIT 1";
                    $pekRow = $db->selectSingle($pekSql);
                    if ($pekRow && !empty(trim($pekRow['pekrn'] ?? ''))) {
                        $resolvedIdentifiers[] = strtoupper(trim($pekRow['pekrn']));
                    }
                }
            }
        }

        $resolvedIdentifiers = array_unique(array_filter($resolvedIdentifiers));

        if (empty($resolvedIdentifiers)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Could not find a valid PAN or PEKRN in database for the provided Client Code(s).'
            ]);
            exit;
        }

        $payload['pan_pkern_no'] = implode(',', $resolvedIdentifiers);
    }

    try {
        $nse = new NseApiConnect();
        $apiResult = $nse->getFatcaReportFromNse($payload);

        error_log("FATCA Live API Response for payload " . json_encode($payload) . ": " . print_r($apiResult, true));

        if (($apiResult['http_code'] ?? 0) !== 200 || empty($apiResult['json'])) {
            echo json_encode([
                'success' => false,
                'error'   => 'NSE API call failed: ' . ($apiResult['error'] ?? 'Unknown API connectivity error')
            ]);
            exit;
        }

        $jsonData = $apiResult['json'];

        if (!empty($jsonData['error_remark'])) {
            echo json_encode([
                'success' => false, 
                'error'   => $jsonData['error_remark']
            ]);
            exit;
        }

        if (($jsonData['response_status'] ?? '') !== 'S' || empty($jsonData['report_data'])) {
            echo json_encode([
                'success' => false,
                'error'   => 'API returned non-success status or empty report data: ' . ($jsonData['error_remark'] ?? 'No records found')
            ]);
            exit;
        }

        $reportData = $jsonData['report_data'];
        $savedCount = 0;
        $savedItems = [];

        $conn->begin_transaction();
        
        foreach ($reportData as $item) {
            if ($db->saveFetchedFatcaDetails($item)) {
                $savedCount++;
                $savedItems[] = [
                    'pan_rp'   => $item['pan_rp'] ?? '',
                    'inv_name' => $item['inv_name'] ?? '',
                    'pekrn'    => $item['pekrn'] ?? ''
                ];
            }
        }
        
        $conn->commit();

        while (ob_get_level()) {
            ob_end_clean();
        }

        echo json_encode([
            'success'     => true,
            'message'     => "Successfully synced {$savedCount} FATCA records.",
            'saved_count' => $savedCount,
            'report_data' => $savedItems
        ]);

    } catch (Exception $e) {
        if ($conn->in_transaction()) {
            $conn->rollback();
        }
        error_log('fatcareportajax [fetch_fatca] ERROR: ' . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error'   => 'Internal error: ' . $e->getMessage()
        ]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid or missing action.']);
?>
