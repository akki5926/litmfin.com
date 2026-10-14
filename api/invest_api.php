<?php
// Include CORS file at the very beginning
require_once __DIR__ . '/includes/cors.php';

// Note: The cors.php already handles:
// 1. CORS headers
// 2. OPTIONS preflight requests
// 3. sendResponse function

// Get request method and data
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Log for debugging
//error_log("Investment API called: method=$method, action=$action");

// Get request data
$input = [];
$rawInput = file_get_contents('php://input');

if ($method === 'POST' || $method === 'PUT') {
    if (!empty($rawInput)) {
        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not JSON, try form data
            $input = $_POST;
        }
    } else {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

// Log input for debugging
//error_log("API Input: " . print_r($input, true));

// Get authorization header
$headers = getallheaders();
$authToken = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
//error_log("Auth token present: " . (!empty($authToken) ? 'Yes' : 'No'));

// Initialize database - Note: cors.php already creates $db
// But we need to make sure it's available
if (!isset($db)) {
    require_once __DIR__ . '/config.php';
    $db = new MfdDb();
}

// Extract user ID strictly from the authenticated token
$userId = null;

if (!empty($authToken)) {
    if (method_exists($db, 'extractUserIdFromToken')) {
        $userId = $db->extractUserIdFromToken($authToken);
    }
}

// If still no user ID, check for user_id in Authorization header pattern
if (!$userId && !empty($authToken) && strpos($authToken, 'Bearer ') === 0) {
    $token = str_replace('Bearer ', '', $authToken);
    // Simple token parsing - you should use proper JWT validation
    $tokenParts = explode('.', $token);
    if (count($tokenParts) === 3) {
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        if ($payload && isset($payload['user_id'])) {
            $userId = $payload['user_id'];
        }
    }
}

//error_log("User ID determined: " . ($userId ?: 'Not found'));

// Check if user ID is required for the current action
$actionsRequiringAuth = ['buy_mf', 'redeem', 'additional-buy', 'sip'];
if (in_array($action, $actionsRequiringAuth) && !$userId) {
    sendResponse(false, 'Authentication required. Please log in again.', null, 401);
}

// Route based on action
switch($action) {
    case 'buy_mf':
        handleBuy($db, $input, $userId);
        break;
        
    case 'redeem':
        handleRedeem($db, $input, $userId);
        break;
        
    case 'additional-buy':
        handleAdditionalBuy($db, $input, $userId);
        break;
        
    case 'get_folios':
        handleGetFolios($db, $input, $userId);
        break;
        
    case 'sip':
        handleSip($db, $input, $userId);
        break;
        
    case 'test':
        // Test endpoint to verify API is working
        sendResponse(true, 'Investment API is working', [
            'server_time' => date('Y-m-d H:i:s'),
            'method' => $method,
            'action' => $action,
            'user_id' => $userId,
            'cors_supported' => true
        ]);
        break;
        
    default:
        sendResponse(false, 'Invalid action specified. Available: buy_mf, redeem, additional-buy, sip, test', null, 404);
        break;
}

/**
 * Handle mutual fund purchase
 */
function handleBuy($db, $data, $userId)
{
    try {
        //error_log("handleBuy called with data: " . print_r($data, true));
        
        // Validate required fields
        $required = ['scheme_code', 'amount', 'fund_house_code'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendResponse(false, "$field is required");
                return;
            }
        }

        // Fetch investor data
        $userData = $db->getInvestBuyData($userId);
        if (empty($userData)) {
            sendResponse(false, "Investor data not found for user ID: $userId");
            return;
        }

        // Check if user is KYC compliant
        if (($userData['kyc_complient'] ?? 'N') !== 'Y') {
            sendResponse(false, "KYC not completed. Please complete KYC before investing");
            return;
        }

        // Fetch EUIN
        $euinNumber = $db->getEuinNumber();
        $arnNumber = $db->getArnNumber();
        //error_log("EUIN Number: " . ($euinNumber ?: 'Not found'));

        // Use scheme code as-is from database (already in NSE format, e.g., AXSSGP-GR)
        $schemeCode = $data['scheme_code'];
        //error_log("Scheme Code: $schemeCode");

        // Prepare NSE payload
        $payload = [
            "transaction_details" => [
                [
                    "order_ref_number"     => "",
                    "scheme_code"          => $schemeCode,
                    "trxn_type"            => "P",
                    "buy_sell_type"        => "FRESH",
                    "client_code"          => $userData['CLIENT_CODE'] ?? '',
                    "demat_physical"       => "P",
                    "order_amount"         => (string)$data['amount'],
                    "folio_no"             => "",
                    "remarks"              => "Order Remark",
                    "kyc_flag"             => $userData['kyc_complient'] ?? 'Y',
                    "sub_broker_code"      => "",
                    "euin_number"          => $euinNumber ?: "",
                    "euin_declaration"     => "Y",
                    "min_redemption_flag"  => "N",
                    "dpc_flag"             => "Y",
                    "all_units"            => "N",
                    "redemption_units"     => "",
                    "sub_broker_arn"       => $arnNumber ?: "",
                    "bank_ref_no"          => "",
                    "account_no"           => "",
                    "mobile_no"            => $userData['INDIAN_MOBILE_NO'] ?? '',
                    "email"                => $userData['EMAIL'] ?? '',
                    "mandate_id"           => "",
                    "filler1"              => ""
                ]
            ]
        ];

        //error_log("NSE Payload: " . json_encode($payload));

        // Call NSE API
        require_once __DIR__ . '/nseapiconnect.php';
        $nseService = new NseApiConnect();
        $result = $nseService->callNseBuyApi($payload);

        // Check if result is valid
        if (!isset($result['json']) || !isset($result['json']['transaction_details'][0])) {
            sendResponse(false, 'Invalid NSE response', $result);
            return;
        }

        $txn = $result['json']['transaction_details'][0];

        // Success case
        if (!empty($txn['trxn_order_id']) && ($txn['trxn_status'] ?? '') === 'TRXN SUCCESS') {
            // Record the transaction in database
            $transactionData = [
                'user_id' => $userId,
                'order_id' => $txn['trxn_order_id'],
                'scheme_code' => $schemeCode,
                'amount' => $data['amount'],
                'status' => 'success',
                'nse_response' => json_encode($txn),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Save transaction to database
            if (method_exists($db, 'saveTransaction')) {
                $db->saveTransaction($transactionData);
            }
            
            sendResponse(
                true,
                'Order placed successfully. Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.',
                [
                    'order_id' => $txn['trxn_order_id'],
                    'status'   => $txn['trxn_status'],
                    'scheme_code' => $txn['scheme_code'] ?? $schemeCode,
                    'message' => $txn['trxn_remark'] ?? 'Order placed successfully. Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.',
                    'amount' => $data['amount']
                ]
            );
            return;
        }

        // Failure case
        $errorMessage = ($txn['trxn_status'] ?? 'TRXN FAILED') . ' : ' . ($txn['trxn_remark'] ?? 'Unknown error');
        sendResponse(false, $errorMessage, [
            'scheme_code' => $txn['scheme_code'] ?? $schemeCode,
            'nse_response' => $txn
        ]);
        
    } catch (Exception $e) {
        error_log("Exception in handleBuy: " . $e->getMessage());
        sendResponse(false, 'Error processing buy request: ' . $e->getMessage());
    }
}

/**
 * Handle mutual fund redemption
 */
function handleRedeem($db, $data, $userId)
{
    try {
        // Validate required fields
        $required = ['scheme_code', 'units', 'fund_house_code'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendResponse(false, "$field is required");
                return;
            }
        }

        // Fetch investor data
        $userData = $db->getInvestBuyData($userId);
        if (empty($userData)) {
            sendResponse(false, "Investor data not found");
            return;
        }

        // We assume the frontend has already validated units availability against CAS portfolio
        if (empty($data['folio_no'])) {
            sendResponse(false, "Folio number is required");
            return;
        }

        // Fetch EUIN
        $euinNumber = $db->getEuinNumber();
        $arnNumber = $db->getArnNumber();

        // Use scheme code as-is from database (already in NSE format, e.g., AXSSGP-GR)
        $schemeCode = $data['scheme_code'];

        // Prepare redemption payload
        $payload = [
            "transaction_details" => [
                [
                    "order_ref_number"     => "",
                    "scheme_code"          => $schemeCode,
                    "trxn_type"            => "R",
                    "buy_sell_type"        => "REDEMPTION",
                    "client_code"          => $userData['CLIENT_CODE'] ?? '',
                    "demat_physical"       => "P",
                    "order_amount"         => (isset($data['amount']) && $data['amount'] > 0) ? (string)$data['amount'] : "",
                    "folio_no"             => $data['folio_no'] ?? "",
                    "remarks"              => "Redemption Request",
                    "kyc_flag"             => $userData['kyc_complient'] ?? 'Y',
                    "sub_broker_code"      => "",
                    "euin_number"          => $euinNumber ?: "",
                    "euin_declaration"     => "Y",
                    "min_redemption_flag"  => "N",
                    "dpc_flag"             => "Y",
                    "all_units"            => ($data['redeem_type'] ?? '') === 'all' ? "Y" : "N",
                    "redemption_units"     => (isset($data['units']) && $data['units'] > 0) ? (string)$data['units'] : "",
                    "sub_broker_arn"       => $arnNumber ?: "",
                    "bank_ref_no"          => "",
                    "account_no"           => "",
                    "mobile_no"            => $userData['INDIAN_MOBILE_NO'] ?? '',
                    "email"                => $userData['EMAIL'] ?? '',
                    "mandate_id"           => "",
                    "filler1"              => "",
                    "member_unique_id"     => "MUPUR001"
                ]
            ]
        ];

        // Call NSE API for redemption
        require_once __DIR__ . '/nseapiconnect.php';
        $nseService = new NseApiConnect();
        $result = $nseService->callNseRedemptionApi($payload);

        if (!isset($result['json']) || !isset($result['json']['transaction_details'][0])) {
            sendResponse(false, 'Invalid NSE response', $result);
            return;
        }

        $txn = $result['json']['transaction_details'][0];

        if (!empty($txn['trxn_order_id']) && ($txn['trxn_status'] ?? '') === 'TRXN SUCCESS') {
            // Record redemption transaction
            $transactionData = [
                'user_id' => $userId,
                'order_id' => $txn['trxn_order_id'],
                'scheme_code' => $schemeCode,
                'units' => $data['units'],
                'type' => 'redemption',
                'status' => 'success',
                'nse_response' => json_encode($txn),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (method_exists($db, 'saveTransaction')) {
                $db->saveTransaction($transactionData);
            }
            
            sendResponse(
                true,
                'Order placed successfully. Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.',
                [
                    'order_id' => $txn['trxn_order_id'],
                    'status'   => $txn['trxn_status'],
                    'scheme_code' => $schemeCode,
                    'units' => $data['units'],
                    'message' => $txn['trxn_remark'] ?? 'Redemption request submitted'
                ]
            );
            return;
        }

        $errorMessage = ($txn['trxn_status'] ?? 'TRXN FAILED') . ' : ' . ($txn['trxn_remark'] ?? 'Unknown error');
        sendResponse(false, $errorMessage, [
            'scheme_code' => $schemeCode,
            'nse_response' => $txn
        ]);
        
    } catch (Exception $e) {
        error_log("Exception in handleRedeem: " . $e->getMessage());
        sendResponse(false, 'Error processing redemption: ' . $e->getMessage());
    }
}

/**
 * Handle additional purchase (top-up)
 */
function handleAdditionalBuy($db, $data, $userId)
{
    try {
        // Validate required fields
        $required = ['scheme_code', 'amount', 'fund_house_code', 'folio_no'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendResponse(false, "$field is required");
                return;
            }
        }

        // Fetch investor data
        $userData = $db->getInvestBuyData($userId);
        if (empty($userData)) {
            sendResponse(false, "Investor data not found");
            return;
        }

        // Check if folio exists
        $folioExists = method_exists($db, 'checkFolioExists') ? 
                      $db->checkFolioExists($userId, $data['folio_no']) : true;
        
        if (!$folioExists) {
            sendResponse(false, "Invalid folio number");
            return;
        }

        // Fetch EUIN
        $euinNumber = $db->getEuinNumber();
        $arnNumber = $db->getArnNumber();

        // Use scheme code as-is from database (already in NSE format, e.g., AXSSGP-GR)
        $schemeCode = $data['scheme_code'];

        // Prepare additional purchase payload
        $payload = [
            "transaction_details" => [
                [
                    "order_ref_number"     => "",
                    "scheme_code"          => $schemeCode,
                    "trxn_type"            => "P",
                    "buy_sell_type"        => "ADDITIONAL",
                    "client_code"          => $userData['CLIENT_CODE'] ?? '',
                    "demat_physical"       => "P",
                    "order_amount"         => (string)$data['amount'],
                    "folio_no"             => $data['folio_no'],
                    "remarks"              => "Additional Purchase",
                    "kyc_flag"             => $userData['kyc_complient'] ?? 'Y',
                    "sub_broker_code"      => "",
                    "euin_number"          => $euinNumber ?: "",
                    "euin_declaration"     => "Y",
                    "min_redemption_flag"  => "N",
                    "dpc_flag"             => "Y",
                    "all_units"            => "N",
                    "redemption_units"     => "",
                    "sub_broker_arn"       => $arnNumber ?: "",
                    "bank_ref_no"          => "",
                    "account_no"           => "",
                    "mobile_no"            => $userData['INDIAN_MOBILE_NO'] ?? '',
                    "email"                => $userData['EMAIL'] ?? '',
                    "mandate_id"           => "",
                    "filler1"              => ""
                ]
            ]
        ];

        // Call NSE API
        require_once __DIR__ . '/nseapiconnect.php';
        $nseService = new NseApiConnect();
        $result = $nseService->callNseBuyApi($payload);

        if (!isset($result['json']) || !isset($result['json']['transaction_details'][0])) {
            sendResponse(false, 'Invalid NSE response', $result);
            return;
        }

        $txn = $result['json']['transaction_details'][0];

        if (!empty($txn['trxn_order_id']) && ($txn['trxn_status'] ?? '') === 'TRXN SUCCESS') {
            // Record transaction
            $transactionData = [
                'user_id' => $userId,
                'order_id' => $txn['trxn_order_id'],
                'scheme_code' => $schemeCode,
                'amount' => $data['amount'],
                'folio_no' => $data['folio_no'],
                'type' => 'additional_purchase',
                'status' => 'success',
                'nse_response' => json_encode($txn),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (method_exists($db, 'saveTransaction')) {
                $db->saveTransaction($transactionData);
            }
            
            sendResponse(
                true,
                'Order placed successfully. Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.',
                [
                    'order_id' => $txn['trxn_order_id'],
                    'status'   => $txn['trxn_status'],
                    'scheme_code' => $schemeCode,
                    'amount' => $data['amount'],
                    'folio_no' => $data['folio_no'],
                    'message' => $txn['trxn_remark'] ?? 'Order placed successfully. Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.'
                ]
            );
            return;
        }

        $errorMessage = ($txn['trxn_status'] ?? 'TRXN FAILED') . ' : ' . ($txn['trxn_remark'] ?? 'Unknown error');
        sendResponse(false, $errorMessage, [
            'scheme_code' => $schemeCode,
            'nse_response' => $txn
        ]);
        
    } catch (Exception $e) {
        error_log("Exception in handleAdditionalBuy: " . $e->getMessage());
        sendResponse(false, 'Error processing additional purchase: ' . $e->getMessage());
    }
}

/**
 * Handle SIP (Systematic Investment Plan) creation
 */
function handleSip($db, $data, $userId)
{
    try {
        // Validate required fields - matching frontend field names
        $required = ['scheme_code', 'monthly_amount', 'fund_house_code', 'day_of_sip', 'duration_years','mandate_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                sendResponse(false, "$field is required");
                return;
            }
        }

        // Validate monthly amount
        $monthlyAmount = floatval($data['monthly_amount']);
        if ($monthlyAmount <= 0) {
            sendResponse(false, "Monthly amount must be greater than 0");
            return;
        }

        // Validate day of SIP (1-25)
        $dayOfSip = intval($data['day_of_sip']);
        if ($dayOfSip < 1 || $dayOfSip > 25) {
            sendResponse(false, "SIP day must be between 1 and 25");
            return;
        }

        // Validate duration in years
        $durationYears = intval($data['duration_years']);
        if ($durationYears < 1 || $durationYears > 15) {
            sendResponse(false, "Duration must be between 1 and 15 years");
            return;
        }

        // Validate initial amount if provided
        $initialAmount = isset($data['initial_amount']) ? floatval($data['initial_amount']) : 0;
        if ($initialAmount < 0) {
            sendResponse(false, "Initial amount cannot be negative");
            return;
        }
//error_log("data received=".print_r($data,true));
        $mandateId = trim((string)($data['mandate_id'] ?? ''));

        // Fetch investor data
        $userData = $db->getInvestBuyData($userId);
        if (empty($userData)) {
            sendResponse(false, "Investor data not found");
            return;
        }

        // Use scheme code as-is from database (already in NSE format, e.g., AXSSGP-GR)
        $schemeCode = $data['scheme_code'];

        // Calculate start date (next occurrence of the selected day)
        $currentDay = intval(date('d'));
        
        if ($dayOfSip > $currentDay) {
            // SIP day is later in current month
            $startDate = mktime(0, 0, 0, date('m'), $dayOfSip, date('Y'));
        } else {
            // SIP day is next month
            $startDate = mktime(0, 0, 0, date('m') + 1, $dayOfSip, date('Y'));
        }

        // Calculate end date based on duration
        $endDate = strtotime("+$durationYears years", $startDate);
        
        // Calculate installment number (months in duration)
        $installmentNo = $durationYears * 12;

        // Fetch EUIN
        $euinNumber = $db->getEuinNumber();
        $arnNumber = $db->getArnNumber();

        // Prepare NSE XSIP payload
        $nsePayload = [
            "reg_data" => [
                [
                    "amc_code" => $data['fund_house_code'],
                    "sch_code" => $schemeCode,
                    "client_code" => $userData['CLIENT_CODE'] ?? '',
                    "bank_ref_no" => "BANK" . time(), // Generate unique bank ref number
                    "trans_mode" => "P", // Physical
                    "dp_txn_mode" => "P", // Cumulative/Physical
                    "start_date" => date('d/m/Y', $startDate),
                    "frequency_type" => "MONTHLY",
                    "frequency_allowed" => "1",
                    "installment_amount" => (string)round($monthlyAmount, 2),
                    "status" => "1",
                    "member_code" => $data['member_code'] ?? '',
                    "folio_no" => $data['folio_no'] ?? "",
                    "sip_remarks" => "",
                    "installment_no" => (string)$installmentNo,
                    "convenience_fee" => "0",
                    "xsip_mandate_id" => $mandateId,
                    "sub_broker_code" => "",
                    "euin_number" => $euinNumber ?: "",
                    "euin_declaration" => "Y",
                    "dpc_flag" => "Y",
                    "first_order_today" => "N",
                    "isip_mandate" => "",
                    "sub_broker_arn" => $arnNumber ?: "",
                    "primary_holder_mobile" => $userData['INDIAN_MOBILE_NO'] ?? '',
                    "primary_holder_email" => $userData['EMAIL'] ?? '',
                    "step_up_required" => "N",
                    "step_up_start_date" => "",
                    "step_up_end_date" => "",
                    "step_up_frequency" => "",
                    "step_up_amount" => "",
                    "filler_1" => "",
                    "filler_2" => "",
                    "filler_3" => "",
                    "filler_4" => "",
                    "filler_5" => ""
                ]
            ]
        ];

        //error_log("NSE XSIP Payload: " . json_encode($nsePayload));

        // Call NSE XSIP API
        require_once __DIR__ . '/nseapiconnect.php';
        $nseService = new NseApiConnect();
        $result = $nseService->callNseXsipApi($nsePayload);

        //error_log("NSE XSIP Response: " . print_r($result, true));

        // Check if result is valid
        if (!isset($result['json']['reg_data']) || !is_array($result['json']['reg_data']) || empty($result['json']['reg_data'])) {
            sendResponse(false, 'Invalid NSE response', $result);
            return;
        }

        $regResponse = $result['json']['reg_data'][0];

        // Success case
        if (($regResponse['reg_status'] ?? '') === 'REG_SUCCESS') {
            // Record the SIP transaction in database
            $sipData = [
                'user_id' => $userId,
                'reg_id' => $regResponse['reg_id'] ?? '',
                'scheme_code' => $schemeCode,
                'scheme_name' => $data['scheme_name'] ?? null,
                'amc_code' => $data['fund_house_code'],
                'fund_house_name' => $data['fund_house_name'] ?? null,
                'monthly_amount' => $monthlyAmount,
                'initial_amount' => $initialAmount,
                'day_of_sip' => $dayOfSip,
                'duration_years' => $durationYears,
                'start_date' => date('Y-m-d', $startDate),
                'end_date' => date('Y-m-d', $endDate),
                'installment_no' => $installmentNo,
                'status' => 'active',
                'nse_response' => json_encode($regResponse),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Save SIP to database
            if (method_exists($db, 'createSIP')) {
                $sipId = $db->createSIP($sipData);
            }
            
            sendResponse(
                true,
                'Order placed successfully. Please authorize it through the NSE email sent from noreply@nse.co.in to your registered email address.',
                [
                    'sip_id' => $sipId ?? null,
                    'reg_id' => $regResponse['reg_id'] ?? '',
                    'scheme_code' => $schemeCode,
                    'scheme_name' => $data['scheme_name'] ?? null,
                    'monthly_amount' => $monthlyAmount,
                    'initial_amount' => $initialAmount,
                    'day_of_sip' => $dayOfSip,
                    'start_date' => date('Y-m-d', $startDate),
                    'end_date' => date('Y-m-d', $endDate),
                    'status' => 'active',
                    'reg_status' => 'REG_SUCCESS',
                    'reg_remark' => $regResponse['reg_remark'] ?? ''
                ]
            );
            return;
        }

        // Failure case
        $errorMessage = 'SIP registration failed: ' . ($regResponse['reg_status'] ?? 'UNKNOWN_ERROR');
        if (!empty($regResponse['reg_remark'])) {
            $errorMessage .= ' - ' . $regResponse['reg_remark'];
        }
        
        sendResponse(false, $errorMessage, [
            'scheme_code' => $schemeCode,
            'reg_status' => $regResponse['reg_status'] ?? 'FAILED',
            'nse_response' => $regResponse
        ]);
        
    } catch (Exception $e) {
        error_log("Exception in handleSip: " . $e->getMessage());
        sendResponse(false, 'Error creating SIP: ' . $e->getMessage());
    }
}

/**
 * Handle fetching folios for a scheme
 */
function handleGetFolios($db, $data, $userId)
{
    try {
        if (empty($data['scheme_code'])) {
            sendResponse(false, "Scheme code is required");
            return;
        }

        // Default to logged-in user's UCC if no client_code provided
        $clientCode = isset($data['client_code']) ? $data['client_code'] : $db->getUserUccById($userId);
        if (empty($clientCode)) {
            sendResponse(false, "Client code not found");
            return;
        }

        $schemeCode = $data['scheme_code'];
        $folios = [];

        $folios = $db->getFoliosForScheme($clientCode, $schemeCode);

        if (empty($folios)) {
            $debug = [
                'clientCode' => $clientCode,
                'schemeCode' => $schemeCode,
            ];
            sendResponse(false, 'Debug: ' . json_encode($debug), ['folios' => $folios]);
            return;
        }

        sendResponse(true, 'Folios fetched successfully', ['folios' => $folios]);
    } catch (Exception $e) {
        error_log("Exception in handleGetFolios: " . $e->getMessage());
        sendResponse(false, 'Error fetching folios: ' . $e->getMessage());
    }
}
?>
