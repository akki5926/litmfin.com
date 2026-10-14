<?php
/**
 * Mandate Registration API Endpoints
 * Handles SIP mandate registration (fetch, save, sync status, refresh)
 */
require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/nseapiconnect.php';

// Get authorization header
$headers = getallheaders();
$authToken = $headers['Authorization'] ?? '';

// Extract user ID strictly from the authenticated token
$userId = null;
if (!empty($authToken)) {
    $userId = extractUserIdFromToken($authToken);
}

if (!$userId) {
    sendResponse(false, 'User ID is required', null, 400);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
//error_log("user id=".$userId.", method= ".$method);
switch ($method) {
    case 'GET':
        if ($action === 'refresh') {
            // NEW: Refresh mandate status from NSE for this user only
            handleRefreshRequest($db, $userId);
        } else {
            handleGetRequest($db, $userId);
        }
        break;
    case 'POST':
        if ($action === 'sync') {
            handleSyncRequest($db, $userId);
        } else {
            handlePostRequest($db, $userId);
        }
        break;
    default:
        sendResponse(false, 'Method not allowed', null, 405);
}

function extractUserIdFromToken($token) {
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $parts[1]))), true);
            return $payload['user_id'] ?? null;
        }
    }
    return null;
}

/**
 * GET action=refresh
 * Calls NSE MANDATE_STATUS for this user's client code,
 * stores results in mandate_details, returns updated list.
 */
function handleRefreshRequest(MfdDb $db, $userId) {
    try {
        $user = $db->getUserById($userId);

        if (!$user) {
            sendResponse(false, 'User not found', null, 404);
            return;
        }

        $clientCode = $user['CLIENT_CODE'] ?? '';

        if (empty($clientCode)) {
            // User has no client code – return empty mandate list with flag
            sendResponse(true, 'Client code not available. Please complete KYC/UCC registration.', [
                'mandates'        => [],
                'has_client_code' => false,
                'client_code'     => null,
                'active_mandate'  => null,
                'refresh_result'  => null,
            ]);
            return;
        }

        // Date range: last 180 days → today (wide enough to catch all mandates)
        $fromDate = date('d/m/Y', strtotime('-180 days'));
        $toDate   = date('d/m/Y');

        $nseConnect = new NseApiConnect();

        $payload = [
            'from_date'   => $fromDate,
            'to_date'     => $toDate,
            'client_code' => $clientCode,
            'mandate_id'  => '',
            'status'      => '',
        ];

        $apiResponse = $nseConnect->getMandateStatusFromNse($payload);

        $refreshResult = [
            'total_fetched' => 0,
            'updated'       => 0,
            'new_records'   => 0,
            'nse_status'    => $apiResponse['http_code'] ?? 0,
            'errors'        => [],
        ];

        if (($apiResponse['http_code'] ?? 0) == 200) {
            $jsonData   = $apiResponse['json'] ?? [];
            $reportData = $jsonData['report_data'] ?? [];

            $refreshResult['total_fetched'] = count($reportData);

            foreach ($reportData as $mandateData) {
                // Only process records belonging to this client code
                if (($mandateData['clientCode'] ?? '') !== $clientCode) {
                    continue;
                }

                // Try to match existing record
                $existing = null;

                if (!empty($mandateData['memberMandateId'])) {
                    $existing = $db->getMandateByMemberMandateId($mandateData['memberMandateId']);
                }
                if (!$existing && !empty($mandateData['mandateId'])) {
                    $existing = $db->getMandateByMandateId($mandateData['mandateId']);
                }

                // Build the record
                $startDate      = formatNseDate($mandateData['startDate'] ?? '');
                $endDate        = formatNseDate($mandateData['endDate'] ?? '');
                $approvedDate   = formatNseDate($mandateData['approvedDate'] ?? '');
                $regDate        = formatNseDate($mandateData['registrationDate'] ?? '');
                $dateOfUpload   = formatNseDate($mandateData['dateOfUpload'] ?? '');
                $dateOfReupload = formatNseDate($mandateData['dateOfReupload'] ?? '');
                $localStatus    = mapMandateStatus($mandateData['status'] ?? '');

                $record = [
                    'mandate_id'              => $mandateData['mandateId'] ?? null,
                    'client_code'             => $clientCode,
                    'client_name'             => $mandateData['clientName'] ?? ($user['PRIMARY_HOLDER_NAME'] ?? ''),
                    'member_code'             => $mandateData['memberCode'] ?? '',
                    'bank_name'               => $mandateData['bankName'] ?? '',
                    'bank_branch'             => $mandateData['bankBranch'] ?? '',
                    'amount'                  => $mandateData['amount'] ?? 0,
                    'mandate_type'            => $mandateData['mandateType'] ?? 'X',
                    'mandate_collection_type' => $mandateData['mandateCollectionType'] ?? 'ENACH',
                    'account_no'              => $mandateData['bankAccountNumber'] ?? '',
                    'ac_type'                 => 'SB',
                    'ifsc_code'               => '',
                    'micr_no'                 => '',
                    'start_date'              => $startDate,
                    'end_date'                => $endDate,
                    'approved_date'           => $approvedDate,
                    'date_of_upload'          => $dateOfUpload,
                    'date_of_reupload'        => $dateOfReupload,
                    'registration_date'       => $regDate,
                    'registration_id'         => $mandateData['umrnNo'] ?? '',
                    'umrn_no'                 => $mandateData['umrnNo'] ?? '',
                    'reg_status'              => $localStatus,
                    'reg_remark'              => trim($mandateData['remarks'] ?? ''),
                    'approved_remark'         => trim($mandateData['remarks'] ?? ''),
                    'error_remark'            => $jsonData['error_remark'] ?? '',
                    'member_mandate_no'       => $mandateData['memberMandateId'] ?? null,
                ];

                if ($existing) {
                    $db->updateMandateFromNse($existing['id'], $record);
                    $refreshResult['updated']++;
                } else {
                    $record['user_id'] = $userId;
                    $newId = $db->createMandateFromNseData($record);
                    if ($newId) {
                        $refreshResult['new_records']++;
                    }
                }
            }
        } else {
            $refreshResult['errors'][] = $apiResponse['error'] ?? 'NSE API call failed';
        }

        // Return refreshed mandates from DB for this user
        $mandates = $db->getMandatesByUserId($userId);

        $activeMandate = null;
        $today = date('Y-m-d');
        foreach ($mandates as $m) {
            if ($m['reg_status'] === 'APPROVED'
                && $m['start_date'] <= $today
                && $m['end_date']   >= $today) {
                $activeMandate = $m;
                break;
            }
        }

        sendResponse(true, 'Mandate status refreshed from NSE', [
            'mandates'        => $mandates,
            'has_client_code' => true,
            'client_code'     => $clientCode,
            'active_mandate'  => $activeMandate,
            'refresh_result'  => $refreshResult,
        ]);

    } catch (Exception $e) {
       // error_log("Refresh Mandates error: " . $e->getMessage());
        sendResponse(false, 'Refresh failed: ' . $e->getMessage(), null, 500);
    }
}

/**
 * GET - Fetch all mandates for a user (DB only, no NSE call)
 */
function handleGetRequest(MfdDb $db, $userId) {
    try {
        $user = $db->getUserById($userId);
//error_log("user=".$user);
        if (!$user) {
            sendResponse(false, 'User not found', null, 404);
            return;
        }

        $clientCode = $user['CLIENT_CODE'] ?? '';
//error_log("clientCode=".$clientCode);
        if (empty($clientCode)) {
            sendResponse(true, 'Client code not available. Please complete KYC/UCC registration first.', [
                'mandates'        => [],
                'has_client_code' => false,
                'client_code'     => null,
                'active_mandate'  => null,
            ]);
            return;
        }

        $mandates = $db->getMandatesByUserId($userId);

        $activeMandate = null;
        $today = date('Y-m-d');
        foreach ($mandates as $mandate) {
            if ($mandate['reg_status'] === 'APPROVED'
                && $mandate['start_date'] <= $today
                && $mandate['end_date']   >= $today) {
                $activeMandate = $mandate;
                break;
            }
        }
//error_log("mandates".print_r( $mandates , true));
        sendResponse(true, 'Mandates retrieved successfully', [
            'mandates'        => $mandates,
            'has_client_code' => true,
            'client_code'     => $clientCode,
            'active_mandate'  => $activeMandate,
        ]);

    } catch (Exception $e) {
        //error_log("GET Mandates error: " . $e->getMessage());
        sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

/**
 * POST - Create a new mandate
 */
function handlePostRequest(MfdDb $db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendResponse(false, 'Invalid JSON input', null, 400);
        return;
    }

    $requiredFields = ['start_date', 'end_date', 'amount'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            sendResponse(false, "Missing required field: {$field}", null, 400);
            return;
        }
    }

    $datePattern = '/^\d{2}\/\d{2}\/\d{4}$/';

    if (!preg_match($datePattern, $input['start_date'])) {
        sendResponse(false, 'Invalid start_date format. Use DD/MM/YYYY', null, 400);
        return;
    }

    if (!preg_match($datePattern, $input['end_date'])) {
        sendResponse(false, 'Invalid end_date format. Use DD/MM/YYYY', null, 400);
        return;
    }

    $startDate = DateTime::createFromFormat('d/m/Y', $input['start_date']);
    $endDate   = DateTime::createFromFormat('d/m/Y', $input['end_date']);

    if (!$startDate || !$endDate) {
        sendResponse(false, 'Invalid date value', null, 400);
        return;
    }

    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $startDate->setTime(0, 0, 0);
    $endDate->setTime(0, 0, 0);

    if ($startDate < $today) {
        sendResponse(false, 'Start date cannot be in the past', null, 400);
        return;
    }

    if ($endDate <= $startDate) {
        sendResponse(false, 'End date must be after start date', null, 400);
        return;
    }

    $maxEndDate = new DateTime();
    $maxEndDate->setTime(0, 0, 0);
    $maxEndDate->modify('+15 years');

    if ($endDate > $maxEndDate) {
        sendResponse(false, 'End date cannot exceed 15 years from today', null, 400);
        return;
    }

    $amount = $input['amount'];
    if (!is_numeric($amount) || $amount <= 0) {
        sendResponse(false, 'Amount must be a positive number', null, 400);
        return;
    }

    if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', (string)$amount)) {
        sendResponse(false, 'Amount exceeds maximum allowed (13 digits total, 2 decimal places)', null, 400);
        return;
    }

    $user = $db->getUserById($userId);
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
        return;
    }

    $clientCode = $user['CLIENT_CODE'] ?? '';
    if (empty($clientCode)) {
        sendResponse(false, 'Client code not available. Please complete KYC/UCC registration first.', null, 400);
        return;
    }

    try {
        $existingActive = $db->getApprovedMandateByUserId($userId);
        if ($existingActive) {
            //error_log("User {$userId} already has active mandate {$existingActive['id']}, creating another");
        }

        $mysqlStartDate = $startDate->format('Y-m-d');
        $mysqlEndDate   = $endDate->format('Y-m-d');

        $mandateData = [
            'user_id'    => $userId,
            'amount'     => $amount,
            'start_date' => $mysqlStartDate,
            'end_date'   => $mysqlEndDate,
        ];

        $mandateId = $db->createMandate($mandateData);

        if (!$mandateId) {
            sendResponse(false, 'Failed to create mandate record', null, 500);
            return;
        }

        $nseSuccess = false;
        $nseMsg     = '';
        $apiResult  = null;

        try {
            $nseConnect = new NseApiConnect();
            $apiResult  = $nseConnect->registerMandateOnNse((int) $userId, (int) $mandateId);

            $json = $apiResult['json'] ?? $apiResult;
            $reg  = [];

            if (is_array($json)) {
                if (!empty($json['reg_data'][0]) && is_array($json['reg_data'][0])) {
                    $reg = $json['reg_data'][0];
                } elseif (!empty($json['reg_details'][0]) && is_array($json['reg_details'][0])) {
                    $reg = $json['reg_details'][0];
                } elseif (isset($json[0]) && is_array($json[0])) {
                    $reg = $json[0];
                } elseif (isset($json['reg_status'])) {
                    $reg = $json;
                }
            }

            $status = $reg['reg_status'] ?? ($apiResult['error'] ?? 'UNKNOWN');
            $remark = trim($reg['reg_remark'] ?? '');
            $nseMsg = $status . ($remark !== '' ? ": {$remark}" : '');

            $successStatuses = ['REG_SUCCESS', 'SUCCESS', 'APPROVED', 'PENDING'];
            $nseSuccess = in_array(strtoupper($status), $successStatuses);

        } catch (Exception $nseEx) {
            //error_log("Mandate NSE call error: " . $nseEx->getMessage());
            $nseMsg     = 'NSE registration failed: ' . $nseEx->getMessage();
            $nseSuccess = false;
        }

        $newMandate = $db->getMandateById($mandateId);

        $responseData = [
            'mandate'      => $newMandate,
            'nse_response' => $apiResult,
        ];

        if ($nseSuccess) {
            sendResponse(true, 'Mandate registered successfully. ' . $nseMsg, $responseData, 201);
        } else {
            sendResponse(false, 'Mandate registration submitted but NSE processing may be pending. ' . $nseMsg, $responseData, 200);
        }

    } catch (Exception $e) {
       // error_log("POST Mandate error: " . $e->getMessage());
        sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

/**
 * POST with action=sync - Sync mandate status from NSE (custom date range)
 */
function handleSyncRequest(MfdDb $db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendResponse(false, 'Invalid JSON input', null, 400);
        return;
    }

    $user = $db->getUserById($userId);
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
        return;
    }

    $clientCode = $input['client_code'] ?? $user['CLIENT_CODE'] ?? '';

    if (empty($clientCode)) {
        sendResponse(false, 'Client code not available', null, 400);
        return;
    }

    $fromDate = $input['from_date'] ?? date('d/m/Y', strtotime('-30 days'));
    $toDate   = $input['to_date'] ?? date('d/m/Y');

    $datePattern = '/^\d{2}\/\d{2}\/\d{4}$/';
    if (!preg_match($datePattern, $fromDate) || !preg_match($datePattern, $toDate)) {
        sendResponse(false, 'Invalid date format. Use DD/MM/YYYY', null, 400);
        return;
    }

    try {
        $nseConnect = new NseApiConnect();

        $payload = [
            'from_date'   => $fromDate,
            'to_date'     => $toDate,
            'client_code' => $clientCode,
        ];

        $apiResponse = $nseConnect->getMandateStatusFromNse($payload);

        if ($apiResponse['http_code'] != 200) {
            sendResponse(false, 'Failed to fetch mandate status from NSE: ' . ($apiResponse['error'] ?? 'Unknown error'), null, 500);
            return;
        }

        $jsonData   = $apiResponse['json'] ?? [];
        $reportData = $jsonData['report_data'] ?? [];

        if (empty($reportData)) {
            sendResponse(true, 'No mandate status records found for this client', [
                'total_fetched' => 0,
                'updated'       => 0,
                'new_records'   => 0,
                'mandates'      => $db->getMandatesByUserId($userId),
            ]);
            return;
        }

        $updated    = 0;
        $newRecords = 0;

        foreach ($reportData as $mandateData) {
            $existing = null;

            if (!empty($mandateData['memberMandateId'])) {
                $existing = $db->getMandateByMemberMandateId($mandateData['memberMandateId']);
            }

            if (!$existing && !empty($mandateData['mandateId'])) {
                $existing = $db->getMandateByMandateId($mandateData['mandateId']);
            }

            if (!$existing) {
                $pendingMandates = $db->getMandatesByUserId($userId);
                foreach ($pendingMandates as $pm) {
                    if ($pm['reg_status'] === 'PENDING' && empty($pm['member_mandate_no'])) {
                        $existing = $pm;
                        break;
                    }
                }
            }

            $registrationDate = formatNseDate($mandateData['registrationDate'] ?? '');
            $startDate        = formatNseDate($mandateData['startDate'] ?? '');
            $endDate          = formatNseDate($mandateData['endDate'] ?? '');
            $approvedDate     = formatNseDate($mandateData['approvedDate'] ?? '');
            $dateOfUpload     = formatNseDate($mandateData['dateOfUpload'] ?? '');
            $dateOfReupload   = formatNseDate($mandateData['dateOfReupload'] ?? '');
            $localStatus      = mapMandateStatus($mandateData['status'] ?? '');

            $mandateRecord = [
                'mandate_id'              => $mandateData['mandateId'] ?? null,
                'client_code'             => $mandateData['clientCode'] ?? $clientCode,
                'client_name'             => $mandateData['clientName'] ?? ($user['PRIMARY_HOLDER_NAME'] ?? ''),
                'member_code'             => $mandateData['memberCode'] ?? '',
                'bank_name'               => $mandateData['bankName'] ?? '',
                'bank_branch'             => $mandateData['bankBranch'] ?? '',
                'amount'                  => $mandateData['amount'] ?? 0,
                'mandate_type'            => $mandateData['mandateType'] ?? 'X',
                'mandate_collection_type' => $mandateData['mandateCollectionType'] ?? 'ENACH',
                'account_no'              => $mandateData['bankAccountNumber'] ?? '',
                'ac_type'                 => 'SB',
                'ifsc_code'               => '',
                'micr_no'                 => '',
                'start_date'              => $startDate,
                'end_date'                => $endDate,
                'approved_date'           => $approvedDate,
                'date_of_upload'          => $dateOfUpload,
                'date_of_reupload'        => $dateOfReupload,
                'registration_date'       => $registrationDate,
                'registration_id'         => $mandateData['umrnNo'] ?? '',
                'umrn_no'                 => $mandateData['umrnNo'] ?? '',
                'reg_status'              => $localStatus,
                'reg_remark'              => trim($mandateData['remarks'] ?? ''),
                'approved_remark'         => trim($mandateData['remarks'] ?? ''),
                'error_remark'            => $jsonData['error_remark'] ?? '',
                'member_mandate_no'       => $mandateData['memberMandateId'] ?? null,
            ];

            if ($existing) {
                $db->updateMandateFromNse($existing['id'], $mandateRecord);
                $updated++;
            } else {
                if (($mandateData['clientCode'] ?? '') === $clientCode) {
                    $mandateRecord['user_id'] = $userId;
                    $newId = $db->createMandateFromNseData($mandateRecord);
                    if ($newId) {
                        $newRecords++;
                    }
                }
            }
        }

        $allMandates = $db->getMandatesByUserId($userId);

        sendResponse(true, 'Mandate status synced successfully', [
            'total_fetched' => count($reportData),
            'updated'       => $updated,
            'new_records'   => $newRecords,
            'mandates'      => $allMandates,
        ]);

    } catch (Exception $e) {
        //error_log("Sync Mandate error: " . $e->getMessage());
        sendResponse(false, 'Sync failed: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Format NSE date (DD/MM/YYYY) → MySQL date (Y-m-d)
 */
function formatNseDate($nseDate) {
    if (empty($nseDate) || trim($nseDate) === '' || trim($nseDate) === ' ') {
        return null;
    }
    $date = DateTime::createFromFormat('d/m/Y', trim($nseDate));
    if ($date !== false) {
        return $date->format('Y-m-d');
    }
    return null;
}

/**
 * Map NSE mandate status → local status
 */
function mapMandateStatus($nseStatus) {
    $statusMap = [
        'APPROVED'    => 'APPROVED',
        'ACTIVE'      => 'APPROVED',
        'PENDING'     => 'PENDING',
        'REJECTED'    => 'REJECTED',
        'CANCELLED'   => 'CANCELLED',
        'EXPIRED'     => 'EXPIRED',
        'FAILED'      => 'REJECTED',
        'REG_SUCCESS' => 'APPROVED',
    ];
    return $statusMap[$nseStatus] ?? 'PENDING';
}


?>