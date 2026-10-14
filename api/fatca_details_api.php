<?php
require_once __DIR__ . '/includes/cors.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if (!in_array($method, ['GET', 'POST'])) {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Verify user authentication
function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function verifyAuth() {
    $db = new MfdDb();
    $secret = $db->getSecretCode();

    if (empty($secret)) {
        sendResponse(false, 'Server configuration error', null, 500);
    }

    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader)) {
        sendResponse(false, 'Authentication required', null, 401);
    }

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        sendResponse(false, 'Invalid Authorization header', null, 401);
    }

    $jwt = $matches[1];
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        sendResponse(false, 'Invalid token', null, 401);
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;
    $header  = json_decode(base64UrlDecode($headerB64), true);
    $payload = json_decode(base64UrlDecode($payloadB64), true);

    if (!$header || !$payload) {
        error_log("JWT decode failed");
        sendResponse(false, 'Invalid token', null, 401);
    }

    $signatureCheck = hash_hmac(
        'sha256',
        $headerB64 . "." . $payloadB64,
        $secret,
        true
    );

    $expectedSignature = rtrim(strtr(base64_encode($signatureCheck), '+/', '-_'), '=');

    if (!hash_equals($expectedSignature, $signatureB64)) {
        error_log("JWT signature mismatch");
        sendResponse(false, 'Invalid token signature', null, 401);
    }

    if (isset($payload['exp']) && time() > $payload['exp']) {
        error_log("JWT expired");
        sendResponse(false, 'Token expired', null, 401);
    }

    if (!isset($payload['user_id']) || !is_numeric($payload['user_id'])) {
        error_log("user_id missing in token");
        sendResponse(false, 'Invalid token payload', null, 401);
    }

    $userId = (int) $payload['user_id'];

    return $userId;
}

// Route actions
switch ($action) {
    case 'get_fatca_profile':
        $userId = verifyAuth();
        handleGetFatcaProfile($db, $userId);
        break;
        
    case 'save_fatca_profile':
        $userId = verifyAuth();
        handleSaveFatcaProfile($db, $userId);
        break;
        
    case 'clear_fatca_profile':
        $userId = verifyAuth();
        handleClearFatcaProfile($db, $userId);
        break;
        
    case 'get_master_data':
        $type = $_GET['type'] ?? '';
        handleGetMasterData($db, $type);
        break;
        
    // NEW: Sync FATCA status from NSE
    case 'sync_fatca_status':
        $userId = verifyAuth();
        handleSyncFatcaStatus($db, $userId);
        break;
        
    // NEW: Get client status from database
    case 'get_client_status':
        $userId = verifyAuth();
        handleGetClientStatus($db, $userId);
        break;
        
    default:
        sendResponse(
            false,
            'Invalid action. Available actions: get_fatca_profile, save_fatca_profile, clear_fatca_profile, get_master_data, sync_fatca_status, get_client_status',
            null,
            404
        );
}

/**
 * Get FATCA profile for user
 */
function handleGetFatcaProfile(MfdDb $db, int $userId): void {
    try {
        $fatcaData = $db->getFatcaDetailsByUserId($userId);
        
        if ($fatcaData) {
            // Trim all fields in the retrieved profile row to remove trailing whitespace
            foreach ($fatcaData as $key => $val) {
                if (is_string($val)) {
                    $fatcaData[$key] = trim($val);
                }
            }

            if (!empty($fatcaData['dob'])) {
                $fatcaData['dob'] = date('Y-m-d', strtotime($fatcaData['dob']));
            }
            if (!empty($fatcaData['nw_date'])) {
                $fatcaData['nw_date'] = date('Y-m-d', strtotime($fatcaData['nw_date']));
            }
            if (!empty($fatcaData['ubo_dob'])) {
                $fatcaData['ubo_dob'] = date('Y-m-d', strtotime($fatcaData['ubo_dob']));
            }
            
            // Normalize fields by stripping leading zeros to match frontend master data options
            $fieldsToNormalize = ['tax_status_us', 'occ_code', 'srce_wealt', 'addr_type', 'ubo_occ'];
            foreach ($fieldsToNormalize as $field) {
                if (isset($fatcaData[$field]) && $fatcaData[$field] !== '') {
                    $trimmed = ltrim($fatcaData[$field], '0');
                    $fatcaData[$field] = ($trimmed === '') ? '0' : $trimmed;
                }
            }
            
            // If UBO Declaration Flag (ubo_df) or NPO Form (npo_form) is empty, default them to 'N' (No)
            if (empty($fatcaData['ubo_df'])) {
                $fatcaData['ubo_df'] = 'N';
            }
            if (empty($fatcaData['npo_form'])) {
                $fatcaData['npo_form'] = 'N';
            }
            
            sendResponse(true, 'FATCA profile retrieved successfully', $fatcaData);
        } else {
            sendResponse(true, 'No FATCA profile found', null);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving FATCA profile: ' . $e->getMessage());
    }
}

/**
 * Save/Update FATCA profile
 */
function handleSaveFatcaProfile(MfdDb $db, int $userId): void {
    try {
        $user = $db->getUserFatcaInfo($userId);
        
        if (!$user) {
            sendResponse(false, 'User not found');
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
                $formattedDob = !empty($input['dob']) && $input['dob'] !== '-0001-11-30' ? date('Y-m-d', strtotime($input['dob'])) : null;
        $formattedNwDate = !empty($input['nw_date']) && $input['nw_date'] !== '-0001-11-30' ? date('Y-m-d', strtotime($input['nw_date'])) : null;
        $formattedUboDob = !empty($input['ubo_dob']) && $input['ubo_dob'] !== '-0001-11-30' ? date('Y-m-d', strtotime($input['ubo_dob'])) : null;
        
        $padNumeric = function($val) {
            if ($val !== null && $val !== '' && is_numeric($val)) {
                return str_pad($val, 2, '0', STR_PAD_LEFT);
            }
            return $val;
        };

        $fatcaData = [
            'user_id' => $userId,
            'pan_rp' => $user['PRIMARY_HOLDER_PAN'],
            'inv_name' => $user['PRIMARY_HOLDER_NAME'],
            'dob' => $formattedDob,
            'pekrn' => $input['pekrn'] ?? '',
            'fr_name' => $input['fr_name'] ?? '',
            'sp_name' => $input['sp_name'] ?? '',
            'tax_status_us' => $padNumeric($input['tax_status_us'] ?? ''),
            'data_src' => 'P',
            'addr_type' => $input['addr_type'] ?? '',
            'po_bir_inc' => $input['po_bir_inc'] ?? '',
            'co_bir_inc' => $input['co_bir_inc'] ?? '',
            'log_name' => $user['email'],
            'tax_res1' => $input['tax_res1'] ?? '',
            'tpin1' => $input['tpin1'] ?? '',
            'id1_type' => $input['id1_type'] ?? '',
            'tax_res2' => $input['tax_res2'] ?? '',
            'tpin2' => $input['tpin2'] ?? '',
            'id2_type' => $input['id2_type'] ?? '',
            'tax_res3' => $input['tax_res3'] ?? '',
            'tpin3' => $input['tpin3'] ?? '',
            'id3_type' => $input['id3_type'] ?? '',
            'tax_res4' => $input['tax_res4'] ?? '',
            'tpin4' => $input['tpin4'] ?? '',
            'id4_type' => $input['id4_type'] ?? '',
            'srce_wealt' => $padNumeric($input['srce_wealt'] ?? ''),
            'corp_servs' => $input['corp_servs'] ?? '',
            'inc_slab' => $input['inc_slab'] ?? '',
            'net_worth' => isset($input['net_worth']) ? floatval($input['net_worth']) : 0,
            'nw_date' => $formattedNwDate,
            'pep_flag' => $input['pep_flag'] ?? 'N',
            'occ_code' => $padNumeric($input['occ_code'] ?? ''),
            'occ_type' => $input['occ_type'] ?? '',
            'exemp_code' => $input['exemp_code'] ?? '',
            'ffi_drnfe' => $input['ffi_drnfe'] ?? '',
            'giin_no' => $input['giin_no'] ?? '',
            'spr_entity' => $input['spr_entity'] ?? '',
            'giin_na' => $input['giin_na'] ?? '',
            'giin_exemc' => $input['giin_exemc'] ?? '',
            'nffe_catg' => $input['nffe_catg'] ?? '',
            'act_nfe_sc' => $input['act_nfe_sc'] ?? '',
            'nature_bus' => $input['nature_bus'] ?? '',
            'rel_listed' => $input['rel_listed'] ?? 'N',
            'exch_name' => 'O',
            'aadhaar_rp' => $input['aadhaar_rp'] ?? '',
            'ubo_appl' => $input['ubo_appl'] ?? 'N',
            'ubo_count' => isset($input['ubo_count']) ? intval($input['ubo_count']) : 0,
            'ubo_name' => $input['ubo_name'] ?? '',
            'ubo_pan' => $input['ubo_pan'] ?? '',
            'ubo_nation' => $input['ubo_nation'] ?? '',
            'ubo_add1' => $input['ubo_add1'] ?? '',
            'ubo_add2' => $input['ubo_add2'] ?? '',
            'ubo_add3' => $input['ubo_add3'] ?? '',
            'ubo_city' => $input['ubo_city'] ?? '',
            'ubo_pin' => $input['ubo_pin'] ?? '',
            'ubo_state' => $input['ubo_state'] ?? '',
            'ubo_cntry' => $input['ubo_cntry'] ?? '',
            'ubo_add_type' => $input['ubo_add_type'] ?? '',
            'ubo_ctr' => $input['ubo_ctr'] ?? '',
            'ubo_tin' => $input['ubo_tin'] ?? '',
            'ubo_id_ty' => $input['ubo_id_ty'] ?? '',
            'ubo_cob' => $input['ubo_cob'] ?? '',
            'ubo_dob' => $formattedUboDob,
            'ubo_gender' => $input['ubo_gender'] ?? '',
            'ubo_fr_nam' => $input['ubo_fr_nam'] ?? '',
            'ubo_occ' => $padNumeric($input['ubo_occ'] ?? ''),
            'ubo_occ_ty' => $input['ubo_occ_ty'] ?? '',
            'ubo_tel' => $input['ubo_tel'] ?? '',
            'ubo_mobile' => $input['ubo_mobile'] ?? '',
            'ubo_code' => $input['ubo_code'] ?? '',
            'ubo_hol_pc' => isset($input['ubo_hol_pc']) ? floatval($input['ubo_hol_pc']) : 0,
            'sdf_flag' => $input['sdf_flag'] ?? 'Y',
            'ubo_df' => $input['ubo_df'] ?? 'N',
            'new_cha' => $input['new_cha'] ?? 'N',
            'npo_form' => $input['npo_form'] ?? 'N',
            'npo_dcl' => $input['npo_dcl'] ?? '',
            'npo_rgno' => $input['npo_rgno'] ?? '',
            'ubo_exch' => $input['ubo_exch'] ?? '',
            'ubo_isin' => $input['ubo_isin'] ?? '',
            'ubo_rel_li' => $input['ubo_rel_li'] ?? '',
            'ubo_categ' => $input['ubo_categ'] ?? '',
            'ubo_pep_fl' => $input['ubo_pep_fl'] ?? '',
            'ubo_email' => $input['ubo_email'] ?? '',
            'ubo_smo_de' => $input['ubo_smo_de'] ?? '',
            'filler1' => $input['filler1'] ?? '',
            'filler2' => $input['filler2'] ?? '',
        ];
            
        $success = $db->saveFatcaDetails($userId, $fatcaData);
        
        if ($success === false) {
            sendResponse(false, 'Failed to save FATCA details. Please check the data and try again.');
            return;
        }
        
        // ========== FIX: Update both tables ==========
        if (!$db->setFatcaDeclarationStatus($userId, true)) {
            error_log("Error updating status tables for user_id: {$userId}");
        }
        // ========== END FIX ==========

        // Push FATCA registration to NSE
        try {
            require_once __DIR__ . '/nseapiconnect.php';
            $nseApi = new NseApiConnect();
            $nseApi->createFatcaOnNseCommon($userId);
            
            // Re-fetch user to get client code if newly assigned
            $updatedUser = $db->getUserById($userId);
            $clientCodeToSync = $updatedUser['CLIENT_CODE'] ?? $user['CLIENT_CODE'] ?? null;
            if (!empty($clientCodeToSync)) {
                $nseApi->syncClientStatus($clientCodeToSync);
            }
        } catch (Exception $nseEx) {
            error_log("Error pushing FATCA to NSE for user {$userId}: " . $nseEx->getMessage());
        }
        
        $resData = [
            'reg_status' => 'PROCESSED',
            'fatca_declaration_updated' => true
        ];

        sendResponse(
            true,
            'FATCA profile saved successfully',
            $resData
        );
        
    } catch (Exception $e) {
        sendResponse(false, 'Error processing FATCA profile: ' . $e->getMessage());
    }
}
/**
 * Clear FATCA profile
 */
function handleClearFatcaProfile(MfdDb $db, int $userId): void {
    try {
        $pan = $db->getUserPanById($userId);
        
        if (!$pan) {
            sendResponse(false, 'User not found');
        }
        
        try {
            $success = $db->clearFatcaDetails($pan);
        } catch (Exception $e) {
            // Continue
        }
        
        if (!$db->setFatcaDeclarationStatus($userId, false)) {
            error_log("Error clearing status tables for user_id: {$userId}");
        }
        
        sendResponse(true, 'FATCA profile cleared successfully');
        
    } catch (Exception $e) {
        sendResponse(false, 'Error clearing FATCA profile: ' . $e->getMessage());
    }
}

/**
 * Get FATCA master data
 */
function handleGetMasterData(MfdDb $db, string $type): void {
    try {
        $data = [];
        
        switch ($type) {
            case 'tax_status':
                $data = $db->getFatcaTaxStatus();
                break;
            case 'occupation':
                $data = $db->getFatcaOccupation();
                break;
            case 'income_range':
                $data = $db->getFatcaIncomeRange();
                break;
            case 'wealth_source':
                $data = $db->getFatcaWealthSc();
                break;
            case 'ubo_code':
                $data = $db->getFatcaUboCode();
                break;
            case 'giin_exempt':
                $data = $db->getFatcaGiinExempt();
                break;
            case 'exempt_code':
                $data = $db->getFatcaExemptCode();
                break;
            case 'active_nfe_sub_cat':
                $data = $db->getFatcaActNfeSubCat();
                break;
            case 'identity_type':
                $data = $db->getFatcaIdentityType();
                break;
            case 'account_type':
                $data = $db->getFatcaAccType();
                break;
            case 'address_type':
                $data = $db->getFatcaAddType();
                break;
            case 'identity_doc_type':
                $data = $db->getFatcaIdentityDocType();
                break;
            case 'corporate_service_sector':
                $data = $db->getFatcaCorpServSec();
                break;
            case 'all':
                $data = [
                    'tax_status' => $db->getFatcaTaxStatus(),
                    'occupation' => $db->getFatcaOccupation(),
                    'income_range' => $db->getFatcaIncomeRange(),
                    'wealth_source' => $db->getFatcaWealthSc(),
                    'ubo_code' => $db->getFatcaUboCode(),
                    'giin_exempt' => $db->getFatcaGiinExempt(),
                    'exempt_code' => $db->getFatcaExemptCode(),
                    'active_nfe_sub_cat' => $db->getFatcaActNfeSubCat(),
                    'identity_type' => $db->getFatcaIdentityType(),
                    'account_type' => $db->getFatcaAccType(),
                    'address_type' => $db->getFatcaAddType(),
                    'identity_doc_type' => $db->getFatcaIdentityDocType(),
                    'corporate_service_sector' => $db->getFatcaCorpServSec(),
                ];
                break;
            default:
                sendResponse(false, 'Invalid master data type');
                return;
        }
        
        sendResponse(true, 'Master data retrieved successfully', $data);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving master data: ' . $e->getMessage());
    }
}

// ==================== NEW FUNCTIONS ====================

/**
 * Sync FATCA and UCC status from NSE
 */
function handleSyncFatcaStatus(MfdDb $db, int $userId): void {
    try {
        // Get user details
        $user = $db->getUserById($userId);
        
        if (!$user || empty($user['CLIENT_CODE'])) {
            sendResponse(false, 'User not found or client code not assigned. Please complete your profile first.');
            return;
        }
        
        // Initialize NSE API connection
        require_once __DIR__ . '/nseapiconnect.php';
        $nseApi = new NseApiConnect();
        
        // Sync client status from NSE
        $result = $nseApi->syncClientStatus($user['CLIENT_CODE']);
        
        if ($result['success'] && $result['updated']) {
            // Get updated status from database
            $status = $db->getUserCompleteStatus($userId);
            
            sendResponse(true, 'Status synced successfully', [
                'fatca_done' => $status['fatca_done'] ?? 'N',
                'activation_status' => $status['activation_status'] ?? 'INACTIVE',
                'deactivation_message' => $status['deactivation_message'] ?? '',
                'is_activated' => ($status['activation_status'] ?? '') === 'Y',
                'client_data' => $result['data']
            ]);
        } else {
            $errorMsg = !empty($result['errors']) ? implode(', ', $result['errors']) : 'Failed to sync status';
            sendResponse(false, $errorMsg);
        }
        
    } catch (Exception $e) {
        error_log("Error syncing FATCA status: " . $e->getMessage());
        sendResponse(false, 'Error syncing status: ' . $e->getMessage());
    }
}

/**
 * Get client status from database
 */
function handleGetClientStatus(MfdDb $db, int $userId): void {
    try {
        $status = $db->getUserCompleteStatus($userId);
        
        if ($status) {
            sendResponse(true, 'Client status retrieved successfully', $status);
        } else {
            sendResponse(true, 'No status record found', null);
        }
        
    } catch (Exception $e) {
        sendResponse(false, 'Error getting client status: ' . $e->getMessage());
    }
}

function createFatcaOnNseCommon($userId): array {
    require_once 'nseapiconnect.php';
    $nseService = new NseApiConnect();
    $result = $nseService->createFatcaOnNseCommon($userId);
    return $result;
}