<?php
/**
 * Bank Account API Endpoints - Update existing user record
 */
require_once __DIR__ . '/includes/cors.php';



// Get authorization header
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';



// If no user ID in request, try to get from token
if (!empty($token)) {
    $userId = $db->extractUserIdFromToken($token);
}


//$userId = $db->extractUserIdFromToken($token);

// Validate token
if (empty($token)) {
    http_response_code(401);
    echo json_encode([
        'response_status' => 'E',
        'message' => 'Authentication token is required'
    ]);
    exit();
}

// Get request body
$requestBody = file_get_contents('php://input');
$requestData = json_decode($requestBody, true);

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'response_status' => 'E',
        'message' => 'Invalid JSON format'
    ]);
    exit();
}
/*
// Validate required fields
if (empty($requestData['userId'])) {
    sendResponse(false, 'User ID is required', null, 400);
}*/

// Get dates from request
$fromDate = isset($requestData['fromDate']) ? trim($requestData['fromDate']) : '';
$toDate = isset($requestData['toDate']) ? trim($requestData['toDate']) : '';

// Validate date range
$dateValidation = validateDateRange($fromDate, $toDate);
if ($dateValidation['status'] === 'error') {
    http_response_code(400);
    echo json_encode([
        'response_status' => 'E',
        'message' => $dateValidation['message']
    ]);
    exit();
}

// Use validated dates
$fromDate = $dateValidation['from_date'];
$toDate = $dateValidation['to_date'];

// Format dates from YYYY-MM-DD to DD-MM-YYYY for NSE API
$fromDateFormatted = date('d-m-Y', strtotime($fromDate));
$toDateFormatted = date('d-m-Y', strtotime($toDate));

// Include database connection


try {
    // Get client code from database using token
   // $db = new MfdDb();
    //$userId = $db->extractUserIdFromToken($token);
   
    if (!$userId) {
        http_response_code(400);
        echo json_encode([
            'response_status' => 'E',
            'message' => 'Unable to find userId. Invalid token or user ID.'
        ]);
        exit();
    }
    $clientCode = $db->getUserUccById($userId);
    
    // Call NSE API
    $nseResponse = callNseApi(
        $fromDateFormatted,
        $toDateFormatted,
        $clientCode,
    );
    sendResponse(true, 'Transactions fetched successfully', $nseResponse);

        // Process NSE response
        /*$processedData = processNseResponse($nseResponse['data']);
        
        // Log successful API call
       
        
        echo json_encode([
            'response_status' => 'S',
            'report_data_total' => count($processedData),
            'report_data' => $processedData,
            'message' => 'Transactions fetched successfully'
        ]);*/
   
    
} catch (Exception $e) {
    sendResponse(false, 'API error: ' . $e->getMessage());
}

exit();

/**
 * Validate date range according to business rules
 */
function validateDateRange($fromDate, $toDate) {
    $today = date('Y-m-d');
    
    // If both dates are empty, use last 7 days
    if (empty($fromDate) && empty($toDate)) {
        $toDate = $today;
        $fromDate = date('Y-m-d', strtotime('-7 days'));
        
        return [
            'status' => 'success',
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'message' => 'Using last 7 days as default'
        ];
    }
    
    // If only from date is provided
    if (!empty($fromDate) && empty($toDate)) {
        $from = new DateTime($fromDate);
        $to = new DateTime($fromDate);
        $to->modify('+7 days');
        
        $toDate = $to->format('Y-m-d');
        $todayObj = new DateTime($today);
        
        // If calculated to date is in future, use today
        if ($to > $todayObj) {
            $toDate = $today;
        }
        
        return [
            'status' => 'success',
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'message' => 'To date calculated as from date + 7 days'
        ];
    }
    
    // Validate both dates are provided
    if (empty($fromDate) || empty($toDate)) {
        return [
            'status' => 'error',
            'message' => 'Both from date and to date are required'
        ];
    }
    
    // Validate date formats
    $fromObj = DateTime::createFromFormat('Y-m-d', $fromDate);
    $toObj = DateTime::createFromFormat('Y-m-d', $toDate);
    $todayObj = new DateTime($today);
    
    if (!$fromObj || !$toObj) {
        return [
            'status' => 'error',
            'message' => 'Invalid date format. Use YYYY-MM-DD'
        ];
    }
    
    // Reset time components to midnight to avoid time-comparison issues
    $fromObj->setTime(0, 0, 0);
    $toObj->setTime(0, 0, 0);
    $todayObj->setTime(0, 0, 0);
    
    // Check if dates are in future
    if ($fromObj > $todayObj) {
        return [
            'status' => 'error',
            'message' => 'From date cannot be in future'
        ];
    }
    
    if ($toObj > $todayObj) {
        return [
            'status' => 'error',
            'message' => 'To date cannot be in future'
        ];
    }
    
    // Check if from date is before to date
    if ($fromObj > $toObj) {
        return [
            'status' => 'error',
            'message' => 'From date cannot be after to date'
        ];
    }
    
    // Check if date range exceeds 7 days
    $interval = $fromObj->diff($toObj);
    if ($interval->days > 7) {
        return [
            'status' => 'error',
            'message' => 'Date range cannot exceed 7 days'
        ];
    }
    
    return [
        'status' => 'success',
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'message' => 'Dates validated successfully'
    ];
}

/**
 * Call NSE API with proper authentication
 */
function callNseApi( $fromDate, $toDate, $clientCode) {
	require_once 'nseapiconnect.php';

	$nseService = new NseApiConnect();
	$payload = [
			"from_date" => $fromDate, "to_date"=> $toDate , "client_code"=> $clientCode
		];
	$result = $nseService->getTransactionDetailFromNse($payload);

	return $result;
}

/**
 * Process NSE API response to match expected format
 */
function processNseResponse($nseData) {
    // Check if NSE response has the expected structure
    if (!isset($nseData['report_data']) || !is_array($nseData['report_data'])) {
        return [];
    }
    
    $processedTransactions = [];
    
    foreach ($nseData['report_data'] as $transaction) {
        // Format date from DD-MM-YYYY HH:MM:SS to DD-MM-YYYY HH:MM:SS (or keep as is)
        $regDateTime = $transaction['product_reg_date_time'] ?? '';
        
        $processedTransaction = [
            'member_code' => $transaction['member_code'] ?? '',
            'client_code' => $transaction['client_code'] ?? '',
            'mode_of_holding' => $transaction['mode_of_holding'] ?? '',
            'tax_status' => $transaction['tax_status'] ?? '',
            'primary_holder_name' => $transaction['primary_holder_name'] ?? '',
            'primary_holder_pan' => $transaction['primary_holder_pan'] ?? '',
            'product_type' => $transaction['product_type'] ?? '',
            'product_id' => $transaction['product_id'] ?? '',
            'product_reg_date_time' => $regDateTime,
            'frequency' => $transaction['frequency'] ?? '',
            'transaction_type' => $transaction['transaction_type'] ?? '',
            'sub_broker_code' => $transaction['sub_broker_code'] ?? '',
            'sub_broker_arn' => $transaction['sub_broker_arn'] ?? '',
            'euin' => $transaction['euin'] ?? '',
            'amc_name' => $transaction['amc_name'] ?? '',
            'rta_name' => $transaction['rta_name'] ?? '',
            'scheme_name_from' => $transaction['scheme_name_from'] ?? '',
            'scheme_code_from' => $transaction['scheme_code_from'] ?? '',
            'scheme_name_to' => $transaction['scheme_name_to'] ?? '',
            'scheme_code_to' => $transaction['scheme_code_to'] ?? '',
            'dp_folio_no' => $transaction['dp_folio_no'] ?? '',
            'amount_units' => $transaction['amount_units'] ?? '',
            'primary_holder_mobile' => $transaction['primary_holder_mobile'] ?? '',
            'primary_holder_email' => $transaction['primary_holder_email'] ?? '',
            'primary_holder_auth_mode' => $transaction['primary_holder_auth_mode'] ?? '',
            'primary_holder_auth_status' => $transaction['primary_holder_auth_status'] ?? '',
            'primary_holder_auth_date_time' => $transaction['primary_holder_auth_date_time'] ?? '',
            'second_holder_name' => $transaction['second_holder_name'] ?? '',
            'second_holder_pan' => $transaction['second_holder_pan'] ?? '',
            'secondary_holder_mobile' => $transaction['secondary_holder_mobile'] ?? '',
            'secondary_holder_email' => $transaction['secondary_holder_email'] ?? '',
            'secondary_holder_auth_mode' => $transaction['secondary_holder_auth_mode'] ?? '',
            'secondary_holder_auth_status' => $transaction['secondary_holder_auth_status'] ?? '',
            'secondary_holder_auth_date_time' => $transaction['secondary_holder_auth_date_time'] ?? '',
            'third_holder_name' => $transaction['third_holder_name'] ?? '',
            'third_holder_pan' => $transaction['third_holder_pan'] ?? '',
            'third_holder_mobile' => $transaction['third_holder_mobile'] ?? '',
            'third_holder_email' => $transaction['third_holder_email'] ?? '',
            'third_holder_auth_mode' => $transaction['third_holder_auth_mode'] ?? '',
            'third_holder_auth_status' => $transaction['third_holder_auth_status'] ?? '',
            'third_holder_auth_date_time' => $transaction['third_holder_auth_date_time'] ?? '',
            'mode_of_payment' => $transaction['mode_of_payment'] ?? '',
            'payment_bank_name' => $transaction['payment_bank_name'] ?? '',
            'payment_account_no' => $transaction['payment_account_no'] ?? '',
            'payment_mandate_id' => $transaction['payment_mandate_id'] ?? '',
            'payment_account_ifsc' => $transaction['payment_account_ifsc'] ?? '',
            'payment_umrn' => $transaction['payment_umrn'] ?? '',
            'payment_date_time' => $transaction['payment_date_time'] ?? '',
            'payment_status' => $transaction['payment_status'] ?? '',
            'payment_reference_no' => $transaction['payment_reference_no'] ?? '',
            'reconciliation_status' => $transaction['reconciliation_status'] ?? '',
            'reconciliation_date_time' => $transaction['reconciliation_date_time'] ?? '',
            'pg_mis_utr_no' => $transaction['pg_mis_utr_no'] ?? '',
            'amc_fund_payout_date_time' => $transaction['amc_fund_payout_date_time'] ?? '',
            'amc_payout_utr_no' => $transaction['amc_payout_utr_no'] ?? '',
            'order_status' => $transaction['order_status'] ?? '',
            'order_remark' => $transaction['order_remark'] ?? '',
            'rta_rejection_date' => $transaction['rta_rejection_date'] ?? '',
            'alloted_nav' => $transaction['alloted_nav'] ?? '',
            'alloted_unit' => $transaction['alloted_unit'] ?? '',
            'alloted_amount' => $transaction['alloted_amount'] ?? '',
            'stt' => $transaction['stt'] ?? '',
            'folio_no' => $transaction['folio_no'] ?? '',
            'refund_date' => $transaction['refund_date'] ?? '',
            'refund_amount' => $transaction['refund_amount'] ?? '',
            'refund_account_status' => $transaction['refund_account_status'] ?? '',
            'redemption_payment_account_type' => $transaction['redemption_payment_account_type'] ?? '',
            'redemption_payout_amount' => $transaction['redemption_payout_amount'] ?? '',
            'redemption_payout_date_time' => $transaction['redemption_payout_date_time'] ?? '',
            'redemption_payment_entity' => $transaction['redemption_payment_entity'] ?? '',
            'entry_by' => $transaction['entry_by'] ?? ''
        ];
        
        $processedTransactions[] = $processedTransaction;
    }
    
    return $processedTransactions;
}

/**
 * Log API call details for debugging
 */
function logApiCallDetails($url, $payload, $response, $httpCode, $error, $curlInfo) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => $url,
        'payload' => $payload,
        'response_code' => $httpCode,
        'error' => $error,
        'response_preview' => substr($response, 0, 500), // First 500 chars
        'curl_info' => [
            'total_time' => $curlInfo['total_time'] ?? 0,
            'connect_time' => $curlInfo['connect_time'] ?? 0,
            'namelookup_time' => $curlInfo['namelookup_time'] ?? 0,
        ]
    ];
    
    $logFile = __DIR__ . '/../logs/nse_api_' . date('Y-m-d') . '.log';
    $logMessage = json_encode($logData, JSON_PRETTY_PRINT) . "\n---\n";
    
    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Write to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}