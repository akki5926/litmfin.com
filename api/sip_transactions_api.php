<?php
/**
 * SIP Transactions API Endpoints
 */
require_once __DIR__ . '/includes/cors.php';

// Simple authentication using JWT token
function authenticateRequest() {
    $token = getBearerToken();
    
    if (!$token) {
        error_log("No bearer token found");
        return null;
    }
    
    $userId = verifyJwtToken($token);
    
    if ($userId) {
        return $userId;
    }
    
    error_log("JWT verification failed");
    return null;
}

$userId = authenticateRequest();
if (!$userId) {
    sendResponse(false, 'Authentication required. Please login again.', null, 401);
}

// Get client code for the user
$clientCode = $db->getUserUccById($userId);

if (empty($clientCode)) {
    sendResponse(true, 'No client UCC found', [
        'response_status' => 'S',
        'report_data_total' => 0,
        'report_data' => []
    ]);
    exit();
}

try {
    // Fetch SIP and SWP data from the database
    $dbData = $db->getSipSwpDataByClientCode($clientCode);
    
    $processedData = [];
    foreach ($dbData as $row) {
        // Map database columns to the response structure
        // Convert YYYY-MM-DD dates to DD MMM YYYY format if they exist
        $xsipRegDate = !empty($row['xsip_registration_date']) && $row['xsip_registration_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['xsip_registration_date'])) : '';
        $startDate = !empty($row['start_date']) && $row['start_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['start_date'])) : '';
        $endDate = !empty($row['end_date']) && $row['end_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['end_date'])) : '';
        
        $swpRegDate = !empty($row['swp_registration_date']) && $row['swp_registration_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['swp_registration_date'])) : '';
        $swpStartDate = !empty($row['swp_start_date']) && $row['swp_start_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['swp_start_date'])) : '';
        $swpEndDate = !empty($row['swp_end_date']) && $row['swp_end_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['swp_end_date'])) : '';
        
        $stpRegDate = !empty($row['stp_registration_date']) && $row['stp_registration_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['stp_registration_date'])) : '';
        $stpStartDate = !empty($row['stp_start_date']) && $row['stp_start_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['stp_start_date'])) : '';
        $stpEndDate = !empty($row['stp_end_date']) && $row['stp_end_date'] !== '0000-00-00' ? date('d M Y', strtotime($row['stp_end_date'])) : '';

        $processedData[] = [
            'id' => $row['id'],
            'registration_type' => $row['registration_type'] ?? 'SIP',
            'member_code' => $row['member_code'] ?? '',
            'client_code' => $row['client_code'] ?? '',
            'client_name' => $row['client_name'] ?? '',
            'pg_bank_ref_no' => $row['pg_bank_ref_no'] ?? '',
            'xsip_registration_no' => $row['xsip_registration_no'] ?? '',
            'xsip_registration_date' => $xsipRegDate,
            'amc_name' => $row['amc_name'] ?? '',
            'rta_scheme_code' => $row['rta_scheme_code'] ?? '',
            'scheme_name' => $row['scheme_name'] ?? '',
            'frequency_type' => $row['frequency_type'] ?? '',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'installments_amount' => $row['installments_amount'] ?? '',
            'brokerage' => $row['brokerage'] ?? '',
            'entry_by' => $row['entry_by'] ?? '',
            'nse_mandate_id' => $row['nse_mandate_id'] ?? '',
            'dpc_flag' => $row['dpc_flag'] ?? '',
            'dp_trans' => $row['dp_trans'] ?? '',
            'sub_broker' => $row['sub_broker'] ?? '',
            'euin_no' => $row['euin_no'] ?? '',
            'euin_declaration' => $row['euin_declaration'] ?? '',
            'first_order_today' => $row['first_order_today'] ?? '',
            'folio_number' => $row['folio_number'] ?? '',
            'status' => $row['status'] ?? '',
            'remarks' => $row['remarks'] ?? '',
            'sub_broker_arn' => $row['sub_broker_arn'] ?? '',
            'no_of_installments' => $row['no_of_installments'] ?? '',
            'exchange_remark' => $row['exchange_remark'] ?? '',
            'health_declaration_flag' => $row['health_declaration_flag'] ?? '',
            'nominee_dob' => $row['nominee_dob'] ?? '',
            'disclaimer_flag' => $row['disclaimer_flag'] ?? '',
            'internal_ref_no' => $row['internal_ref_no'] ?? '',
            'primary_holder_email' => $row['primary_holder_email'] ?? '',
            'primary_holder_mobile' => $row['primary_holder_mobile'] ?? '',
            'second_holder_email' => $row['second_holder_email'] ?? '',
            'second_holder_mobile' => $row['second_holder_mobile'] ?? '',
            'third_holder_email' => $row['third_holder_email'] ?? '',
            'third_holder_mobile' => $row['third_holder_mobile'] ?? '',
            'member_unique_id' => $row['member_unique_id'] ?? '',
            
            // STP specific columns
            'stp_registration_no' => $row['stp_registration_no'] ?? '',
            'from_amc_name' => $row['from_amc_name'] ?? '',
            'to_amc_name' => $row['to_amc_name'] ?? '',
            'from_scheme_name' => $row['from_scheme_name'] ?? '',
            'to_scheme_name' => $row['to_scheme_name'] ?? '',
            'stp_registration_date' => $stpRegDate,
            'stp_start_date' => $stpStartDate,
            'stp_end_date' => $stpEndDate,
            'trxn_mode' => $row['trxn_mode'] ?? '',
            'transfer_amount' => $row['transfer_amount'] ?? '',
            'transfer_units' => $row['transfer_units'] ?? '',
            'no_of_transfers' => $row['no_of_transfers'] ?? '',
            'first_order_todays_flag' => $row['first_order_todays_flag'] ?? '',
            'sub_br_code' => $row['sub_br_code'] ?? '',
            'sub_br_arn_code' => $row['sub_br_arn_code'] ?? '',
            'buy_sell_type' => $row['buy_sell_type'] ?? '',
            'from_nse_scheme_code' => $row['from_nse_scheme_code'] ?? '',
            'to_nse_scheme_code' => $row['to_nse_scheme_code'] ?? '',
            
            // SWP specific columns
            'swp_registration_no' => $row['swp_registration_no'] ?? '',
            'swp_registration_date' => $swpRegDate,
            'swp_start_date' => $swpStartDate,
            'swp_end_date' => $swpEndDate,
            'withdrawl_amount' => $row['withdrawl_amount'] ?? '',
            'withdrawal_units' => $row['withdrawal_units'] ?? '',
            'no_of_withdrawls' => $row['no_of_withdrawls'] ?? '',
            'bank_account_no' => $row['bank_account_no'] ?? '',
            'created_at' => $row['created_at'] ?? ''
        ];
    }
    
    sendResponse(true, 'SIP/SWP transactions fetched successfully from database', [
        'response_status' => 'S',
        'report_data_total' => count($processedData),
        'report_data' => $processedData
    ]);
} catch (Exception $e) {
    sendResponse(false, 'API error: ' . $e->getMessage(), null, 500);
}

exit();