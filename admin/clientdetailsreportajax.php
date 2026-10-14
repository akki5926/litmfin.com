<?php
require_once('auth_guard.php');

/**
 * clientdetailsreportajax.php
 * ──────────────────────────────────────────────────────────────────────────
 * AJAX back-end for the Client Detail Report page.
 *
 * Search scenarios:
 *  1. client_code provided (dates optional)  → NSE API (getClientDetailReport)
 *  2. date range only (no client_code)       → NSE API (getClientDetailReport)
 *  3. both client_code + dates               → NSE API (client_code wins)
 *  4. neither                                → 400 error
 *
 * Actions:
 *   fetch_report   POST: client_code, from_date (DD-MM-YYYY), to_date (DD-MM-YYYY)
 *   add_client     POST: all client / bank fields
 * ──────────────────────────────────────────────────────────────────────────
 */

header('Content-Type: application/json');
if (!isset($_SESSION)) { session_start(); }

require_once('database.php');
require_once('../api/nseapiconnect.php');

// ── Auth guard ──────────────────────────────────────────────────────────────
if (!(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$db     = new MYSQLDB();
$conn   = $db->getConnection();   // MySQLi connection used throughout
$action = $_GET['action'] ?? '';


/* ══════════════════════════════════════════════════════════════════════════════
   HELPER FUNCTIONS
   ══════════════════════════════════════════════════════════════════════════════ */
function validateClientData($data) {
    $errors = [];
    
    if (empty($data['primary_holder_name'])) {
        $errors[] = 'Primary holder name is required';
    }
    
    if (!empty($data['primary_holder_pan']) && !preg_match('/[A-Z]{5}[0-9]{4}[A-Z]{1}/', $data['primary_holder_pan'])) {
        $errors[] = 'Valid PAN is required (Format: ABCDE1234F)';
    }
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (!empty($data['mobile_no']) && !preg_match('/^[0-9]{10}$/', $data['mobile_no'])) {
        $errors[] = 'Mobile number must be 10 digits';
    }
    
    if (!empty($data['primary_holder_dob'])) {
        $dob = DateTime::createFromFormat('Y-m-d', $data['primary_holder_dob']);
        if (!$dob || $dob > new DateTime()) {
            $errors[] = 'Invalid date of birth';
        }
    }
    
    return $errors;
}

function resolveTaxStatusCode($conn, $taxStatusDisplay) {
    global $db;
    return $db->resolveTaxStatusCode($taxStatusDisplay);
}

function resolveHoldingNatureCode($conn, $holdingNatureDisplay) {
    global $db;
    return $db->resolveHoldingNatureCode($holdingNatureDisplay);
}

function resolveBankCode($conn, $bankName1) {
    global $db;
    return $db->resolveBankCode($bankName1);
}

// Check if client exists in DB (for status badge only)
function checkClientExists($conn, $clientCode) {
    global $db;
    return $db->checkClientExistsByCode($clientCode);
}

// Get client DB record for update (only when needed)
function getClientDBRecord($conn, $clientCode) {
    global $db;
    return $db->getClientDBRecordByCode($clientCode);
}


/* ══════════════════════════════════════════════════════════════════════════════
   ACTION: fetch_report - ALWAYS FROM NSE API ONLY
   ══════════════════════════════════════════════════════════════════════════════ */
if ($action === 'fetch_report' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $fromDate   = trim($_POST['from_date']   ?? '');
    $toDate     = trim($_POST['to_date']     ?? '');
    $clientCode = trim($_POST['client_code'] ?? '');

    if (empty($clientCode) && (empty($fromDate) || empty($toDate))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Provide either a Client Code OR both From Date and To Date.'
        ]);
        exit;
    }

    try {
        $nse = new NseApiConnect();
        $apiParams = [];
        
        if (!empty($clientCode)) {
            $apiParams['client_code'] = $clientCode;
            if (!empty($fromDate) && !empty($toDate)) {
                $apiParams['from_date'] = $fromDate;
                $apiParams['to_date'] = $toDate;
            }
        } else {
            $apiParams['from_date'] = $fromDate;
            $apiParams['to_date'] = $toDate;
        }
        
        $apiResult = $nse->getClientDetailReport($apiParams);

        if (($apiResult['http_code'] ?? 0) !== 200 || empty($apiResult['json'])) {
            echo json_encode([
                'success' => false,
                'error'   => 'NSE API call failed: ' . ($apiResult['error'] ?? 'Unknown error')
            ]);
            exit;
        }

        $jsonData = $apiResult['json'];

        if (!empty($jsonData['error_remark'])) {
            echo json_encode(['success' => false, 'error' => $jsonData['error_remark']]);
            exit;
        }

        $reportData = [];
        if (isset($jsonData['report_data']) && is_array($jsonData['report_data'])) {
            $reportData = $jsonData['report_data'];
        } elseif (isset($jsonData[0]) && is_array($jsonData[0])) {
            $reportData = $jsonData;
        } elseif (isset($jsonData['client_code']) || isset($jsonData['clientCode'])) {
            $reportData = [$jsonData];
        } else {
            $reportData = [$jsonData];
        }

        // Process data - ONLY from NSE, just check DB for existence
        $processedData = array_map(function ($client) use ($conn) {
            $cc = $client['client_code'] ?? $client['clientCode'] ?? '';
            
            $existsInDB = checkClientExists($conn, $cc);
            
            $dob = $client['primary_holder_dob'] ?? $client['dateOfBirth'] ?? '';
            if (!empty($dob) && strpos($dob, '/') !== false) {
                $parts = explode('/', $dob);
                $dob = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
            }
            
            $nseCreatedAt = $client['created_at'] ?? $client['createdAt'] ?? $client['CREATED_AT'] ?? '';
            
            // Get bank account details from NSE
            $accountType1 = $client['bank1_account_type'] ?? '';
            $accountNo1 = $client['bank1_account_no'] ?? '';
            $ifscCode1 = $client['bank1_ifsc'] ?? '';
            $bankName1 = $client['bank1_name'] ?? '';
            
            return [
                'client_code'    => $cc,
                'client_name'    => $client['primary_holder_name'] ?? $client['clientName'] ?? '',
                'pan'            => $client['primary_holder_pan'] ?? $client['pan'] ?? '',
                'email'          => $client['first_holder_email'] ?? $client['emailId'] ?? '',
                'mobile'         => $client['first_holder_mobile_no'] ?? $client['mobileNo'] ?? '',
                'fatca_exists'   => $client['fatca_exists'] ?? 'N',
                'ucc_status'     => $client['ucc_status'] ?? '',
                'ucc_remediation'=> $client['ucc_remediation'] ?? '',
                'exists_in_db'   => $existsInDB,
                'user_id'        => null,
                'tax_status'     => $client['tax_status'] ?? $client['taxStatus'] ?? '',
                'holding_nature' => $client['holding_nature'] ?? $client['holdingNature'] ?? '',
                'dob'            => $dob,
                'account_type_1' => $accountType1,
                'account_no_1'   => $accountNo1,
                'ifsc_code_1'    => $ifscCode1,
                'created_at'     => $nseCreatedAt,
                'bank1_name'     => $bankName1,
            ];
        }, $reportData);

        echo json_encode([
            'success'      => true,
            'data'         => $processedData,
            'total'        => count($processedData),
            'search_type'  => !empty($clientCode) ? 'client_code' : 'date_range',
            'search_value' => !empty($clientCode) ? $clientCode : "$fromDate to $toDate",
        ]);

    } catch (Exception $e) {
        error_log('clientdetailsreportajax [fetch]: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Internal error: ' . $e->getMessage()]);
    }
    exit;
}


/* ══════════════════════════════════════════════════════════════════════════════
   ACTION: add_client (Add or Update a client in the local DB)
   ══════════════════════════════════════════════════════════════════════════════ */
if ($action === 'add_client' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $clientCode           = trim($_POST['client_code']         ?? '');
    $userId               = trim($_POST['user_id']             ?? '');
    $primaryHolderName    = trim($_POST['primary_holder_name'] ?? '');
    $primaryHolderDob     = trim($_POST['primary_holder_dob']  ?? '');
    $primaryHolderPan     = trim($_POST['primary_holder_pan']  ?? '');
    $taxStatusDisplay     = trim($_POST['tax_status']          ?? '');
    $holdingNatureDisplay = trim($_POST['holding_nature']      ?? '');
    $email                = trim($_POST['email']               ?? '');
    $mobileNo             = trim($_POST['mobile_no']           ?? '');
    $accountType1         = trim($_POST['account_type_1']      ?? '');
    $accountNo1           = trim($_POST['account_no_1']        ?? '');
    $bankName1            = trim($_POST['bank_name_1']         ?? '');
    $ifscCode1            = trim($_POST['ifsc_code_1']         ?? '');
    $nseCreatedAtRaw      = trim($_POST['nse_created_at']      ?? '');

    if (empty($clientCode)) {
        echo json_encode(['success' => false, 'error' => 'Client code is required.']);
        exit;
    }

    $validationErrors = validateClientData([
        'primary_holder_name' => $primaryHolderName,
        'primary_holder_pan' => $primaryHolderPan,
        'email' => $email,
        'mobile_no' => $mobileNo,
        'primary_holder_dob' => $primaryHolderDob
    ]);

    if (!empty($validationErrors)) {
        echo json_encode(['success' => false, 'error' => implode(', ', $validationErrors)]);
        exit;
    }

    try {
        $formattedDob = !empty($primaryHolderDob) ? $primaryHolderDob : null;

        $nseCreatedAt = null;
        if (!empty($nseCreatedAtRaw)) {
            $raw = $nseCreatedAtRaw;
            if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})/', $raw, $m)) {
                $raw = $m[3] . '-' . $m[2] . '-' . $m[1];
            }
            $ts = strtotime($raw);
            if ($ts !== false) {
                $nseCreatedAt = date('Y-m-d H:i:s', $ts);
            }
        }

        $taxStatusCode = resolveTaxStatusCode($conn, $taxStatusDisplay);
        $holdingNatureCode = resolveHoldingNatureCode($conn, $holdingNatureDisplay);
        $bdBankCode = resolveBankCode($conn, $bankName1);

        $clientData = [
            'clientCode' => $clientCode,
            'primaryHolderName' => $primaryHolderName,
            'formattedDob' => $formattedDob,
            'primaryHolderPan' => $primaryHolderPan,
            'taxStatusCode' => $taxStatusCode,
            'holdingNatureCode' => $holdingNatureCode,
            'email' => $email,
            'mobileNo' => $mobileNo,
            'accountType1' => $accountType1,
            'accountNo1' => $accountNo1,
            'bdBankCode' => $bdBankCode,
            'ifscCode1' => $ifscCode1,
            'nseCreatedAt' => $nseCreatedAt,
            'userId' => $userId ?? null
        ];

        if (!empty($userId)) {
            // Update existing user via ID
            $db->updateClientRecordFromReport($clientData);
            echo json_encode(['success' => true, 'message' => 'Client updated successfully.', 'action' => 'update']);
        } else {
            // Check if client code already exists
            $existing = $db->getClientDBRecordByCode($clientCode);

            if ($existing) {
                $clientData['userId'] = $existing['id'];
                $db->updateClientRecordFromReport($clientData);
                echo json_encode(['success' => true, 'message' => 'Client updated successfully.', 'action' => 'update']);
            } else {
                // Insert new user
                $newId = $db->insertClientRecordFromReport($clientData);
                echo json_encode(['success' => true, 'message' => 'Client added successfully.', 'action' => 'insert', 'user_id' => $newId]);
            }
        }

    } catch (Exception $e) {
        error_log('clientdetailsreportajax [add_client]: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}


http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid or missing action.']);
?>
