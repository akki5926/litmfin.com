<?php
// ================================================================
// DISABLE ERROR DISPLAY FOR AJAX REQUESTS
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
   ACTION: fetch_master - Fetch and save Client Master Data from NSE API
   ══════════════════════════════════════════════════════════════════════════════ */
if ($action === 'fetch_master' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $clientCode = trim($_POST['client_code'] ?? '');

    if (empty($clientCode)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Client Code / UCC is required.'
        ]);
        exit;
    }

    // Validate UCC format
    if (!preg_match('/^[A-Z0-9]{6,10}$/i', $clientCode)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid Client Code format. Please enter a valid UCC.'
        ]);
        exit;
    }

    try {
        $nse = new NseApiConnect();
        $apiParams = ['client_code' => $clientCode];
        
        $apiResult = $nse->getClientMasterReport($apiParams);

        if (($apiResult['http_code'] ?? 0) !== 200 || empty($apiResult['json'])) {
            echo json_encode([
                'success' => false,
                'error'   => 'NSE API call failed: ' . ($apiResult['error'] ?? 'Unknown error')
            ]);
            exit;
        }

        $jsonData = $apiResult['json'];

        if (!empty($jsonData['error_remark'])) {
            echo json_encode([
                'success' => false, 
                'error' => $jsonData['error_remark']
            ]);
            exit;
        }

        // Extract client data from response
        $clientData = null;
        if (isset($jsonData['report_data']) && is_array($jsonData['report_data']) && !empty($jsonData['report_data'])) {
            $clientData = $jsonData['report_data'][0];
        } elseif (isset($jsonData['client_code']) || isset($jsonData['clientCode'])) {
            $clientData = $jsonData;
        } else {
            foreach ($jsonData as $key => $value) {
                if (is_array($value) && !empty($value) && isset($value[0]['client_code'])) {
                    $clientData = $value[0];
                    break;
                }
            }
        }

        if (empty($clientData) || empty($clientData['client_code'] ?? '')) {
            echo json_encode([
                'success' => false,
                'error'   => 'No client data found for UCC: ' . $clientCode
            ]);
            exit;
        }

        // Handle ucc_status (from POST or fallback to getClientDetailReport)
        $uccStatus = trim($_POST['ucc_status'] ?? '');
        if ($uccStatus === '') {
            if (!empty($clientData['ucc_status'])) {
                $uccStatus = trim($clientData['ucc_status']);
            } else {
                try {
                    $detailResult = $nse->getClientDetailReport(['client_code' => $clientCode]);
                    if (($detailResult['http_code'] ?? 0) === 200 && !empty($detailResult['json'])) {
                        $detailJson = $detailResult['json'];
                        $detailRows = $detailJson['report_data'] ?? ($detailJson[0] ?? [$detailJson]);
                        if (is_array($detailRows) && !empty($detailRows)) {
                            $firstRow = isset($detailRows[0]) && is_array($detailRows[0]) ? $detailRows[0] : $detailRows;
                            $uccStatus = trim($firstRow['ucc_status'] ?? $firstRow['uccStatus'] ?? '');
                        }
                    }
                } catch (Exception $ex) {
                    error_log('Failed to fetch fallback ucc_status from getClientDetailReport: ' . $ex->getMessage());
                }
            }
        }

        if ($uccStatus !== '') {
            $clientData['ucc_status'] = $uccStatus;
        }

        // Check if user exists in tb_users
        $existingUser = $db->getClientByCode($clientCode);
        
        // Determine action based on whether user exists
        if ($existingUser !== false) {
            // User exists - UPDATE
            error_log("User exists, calling updateClientMasterData for: " . $clientCode);
            $saveResult = $db->updateClientMasterData($clientData);
        } else {
            // User doesn't exist - INSERT
            error_log("User not found, calling saveClientMasterData for: " . $clientCode);
            $saveResult = $db->saveClientMasterData($clientData);
        }

        // Clear any output buffer before sending JSON
        while (ob_get_level()) {
            ob_end_clean();
        }

        if ($saveResult['success']) {
            // Fetch the saved/updated client data for display
            $savedClient = $db->getClientByCode($clientCode);
            
            echo json_encode([
                'success' => true,
                'message' => $saveResult['message'],
                'action' => $saveResult['action'] ?? 'insert',
                'client_data' => $savedClient,
                'api_data' => $clientData,
                'affected_rows' => $saveResult['affected_rows'] ?? 0
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => $saveResult['message'] ?? 'Failed to save client data'
            ]);
        }

    } catch (Exception $e) {
        error_log('clientmasterreportajax [fetch_master]: ' . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Internal error: ' . $e->getMessage()
        ]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid or missing action.']);
?>