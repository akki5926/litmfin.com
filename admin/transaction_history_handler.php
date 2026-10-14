<?php
define('API_CALL', true);

require_once('auth_guard.php');
@set_time_limit(180);


require_once('database.php');
require_once __DIR__ . '/../api/nseapiconnect.php';

header('Content-Type: application/json');

try {
    // Get request data
    $fromDate = isset($_POST['from_date']) ? trim($_POST['from_date']) : '';
    $toDate = isset($_POST['to_date']) ? trim($_POST['to_date']) : '';
    $transactionStatus = isset($_POST['transaction_status']) ? trim($_POST['transaction_status']) : 'ALL';
    $transactionType = isset($_POST['transaction_type']) ? trim($_POST['transaction_type']) : 'ALL';
    $clientCode = isset($_POST['client_code']) ? trim($_POST['client_code']) : '';

    // Validate required fields
    if (empty($fromDate) || empty($toDate)) {
        echo json_encode([
            'success' => false,
            'error' => 'From date and to date are required'
        ]);
        exit;
    }

    // Initialize NSE API connection
    $nseApiConnect = new NseApiConnect();

    // Build API request parameters
    $apiParams = [
        'from_date' => $fromDate,
        'to_date' => $toDate
    ];

    // Add client code if provided
    if (!empty($clientCode)) {
        $apiParams['client_code'] = $clientCode;
    }

    // Call NSE API through nseconnectapi.php
    $apiResult = $nseApiConnect->getTransactionDetailFromNse($apiParams);

    // Check if API call was successful
    if (isset($apiResult['error'])) {
        echo json_encode([
            'success' => false,
            'error' => $apiResult['error']
        ]);
        exit;
    }

    // Parse response
    $httpCode = isset($apiResult['http_code']) ? $apiResult['http_code'] : 0;
    $responseData = [];

    if ($httpCode === 200 && isset($apiResult['json'])) {
        $json = $apiResult['json'];
        
        // Check response status
        if (isset($json['response_status']) && $json['response_status'] === 'S') {
            $responseData = isset($json['report_data']) ? $json['report_data'] : [];
        } else {
            // Check for error message
            $errorMsg = isset($json['error_remark']) ? $json['error_remark'] : 'Unknown error from NSE API';
            echo json_encode([
                'success' => false,
                'error' => $errorMsg
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'API returned HTTP code: ' . $httpCode
        ]);
        exit;
    }

    // Return success response with data
    echo json_encode([
        'success' => true,
        'data' => $responseData,
        'count' => count($responseData)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage()
    ]);
}
?>