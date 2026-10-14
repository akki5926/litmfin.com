<?php
require_once('auth_guard.php');

	// these functions called from admin\js\ad_functions.js
	ini_set('MAX_EXECUTION_TIME', '-1');
	ini_set('memory_limit', '-1');
	ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
	require_once('database.php');
	require_once __DIR__ . '/database-nseinvest-Api.php';
	require_once('excel/xlsxwriter.php');
	$writeExcel = new XLSXWriter();
	$db = new MYSQLDB();
	$mfdb = new DB_NSEINVEST_API();
	$method= '';
	$outputStr= '';

	/*if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'isIinExist'){
		global $outputStr;
		$iin= $_REQUEST['iin'];
		$result = $db->isIIinExist($iin);
			if($result === false){
				$outputStr .= "IIN doest not Exist";
			}else{
				$outputStr .= "IIN Exist ";
			}
	}*/
	
	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'AddingNewFaq'){
		global $outputStr;
		$faqPage= $_REQUEST['pageName'];
		$faqHeading= stripslashes($_REQUEST['fHeading']);
		$faqDetail = stripslashes($_REQUEST['fDetail']);
		
		$result = $db->addFaq($faqPage,$faqHeading,$faqDetail);
			if($result){
				$outputStr .= "Data inserted successfully";
			}else{
				$outputStr .= "Data inserted failed due to " .$db ->getError();
			}
	}
	
	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'UpdatingFaq'){
		global $outputStr;
		$faqPage= $_REQUEST['pageName'];
		$faqHeading= $_REQUEST['fHeading'];
		$faqDetail = stripslashes($_REQUEST['fDetail']);
		$faqId = $_REQUEST['fId'];
		$result = $db->ModifyingFaq($faqPage,$faqHeading,$faqDetail,$faqId);
			if($result){
				$outputStr .= "Data Updated successfully";
			}else{
				$outputStr .= "Data Updated failed due to " .$db ->getError();
			}
	}


	
	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'ChangeAdminPassword'){
		global $outputStr;
		$old_pass = $_REQUEST['oldPass'];
		$new_pass = $_REQUEST['newpass'];
		$confirm_pass = $_REQUEST['confirmpass'];
		$admin_Id = $_REQUEST['fId'];
		
			if($new_pass === $confirm_pass){
				$result = $db->changeAdminPasswrod(md5($new_pass),$admin_Id,md5($old_pass));
				if($result){
					$outputStr .= "Password Changed successfully.";
				}else{
					$outputStr .= "Password Changed failed due to " .$db ->getError();
				}
			}else{
					$outputStr .="New Password or Confirm Password not Matched";
			}
	}

	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'addNewSubAdmin'){
		global $outputStr;
		$username = $_REQUEST['usrname'];
		$new_pass = $_REQUEST['newpass'];
		
			if(trim($username) != ""){
				$result = $db->addSubAdminToDb($username,md5($new_pass));
				 if ($result == true) {
					$outputStr .=  "Sub admin added successfully!";
				  } else {
					$outputStr .=  "Error: Adding Sub admin fail!";// . $sql . "<br>" . mysqli_error($conn);
				  }
			}else{
					$outputStr .="Username can not be empty";
			}
	}
	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'editSubAdmin'){
		global $outputStr;
		$id = $_REQUEST['id'];
		$username = $_REQUEST['username'];
		$newusername = $_REQUEST['newusername'];
		
			if(trim($username) != ""){
				$result = $db->updateSubAdmin($id,$newusername);
				 if ($result == true) {
					$outputStr .=  "Sub admin $username changed to $newusername successfully!";
				  } else {
					$outputStr .=  "Error: Sub admin $username update fail!";// . $sql . "<br>" . mysqli_error($conn);
				  }
			}else{
					$outputStr .="Username can not be empty";
			}
	}

	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'resetSubAdmin'){
		global $outputStr;
		$id = $_REQUEST['id'];
		$pass = $_REQUEST['pass'];
		
			if(trim($pass) != ""){
				$result = $db->updateSubAdminPass($id,md5($pass));
				 if ($result == true) {
					$outputStr .=  "Sub admin password updated successfully!";
				  } else {
					$outputStr .=  "Error: Sub admin password update fail!";// . $sql . "<br>" . mysqli_error($conn);
				  }
			}else{
					$outputStr .="Password can not be empty";
			}
	}


	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'deleteSubAdmin'){
		global $outputStr;
		$id = $_REQUEST['id'];
		$username = $_REQUEST['username'];
		$result = $db->deleteSubAdmin($id);
		if ($result == true) {
			$outputStr .=  "Sub admin $username deleted successfully!";
		} else {
			$outputStr .=  "Error: Sub admin $username deletion fail!";// . $sql . "<br>" . mysqli_error($conn);
		}
	}
	
	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'ChangeBrokerPassword'){
		global $outputStr;
		$old_pass = $_REQUEST['oldPass'];
		$new_pass = $_REQUEST['newpass'];
		$confirm_pass = $_REQUEST['confirmpass'];
		
		
			if($new_pass === $confirm_pass){
				$result = $db->changeBrokersPasswrod($new_pass);
				if($result){
					$outputStr .= "Password update successfully";
				}else{
					$outputStr .= "Password Changed failed due to " .$db ->getError();
				}
			}else{
					$outputStr .="New Password or Confirm Password not Matched";
			}
	}


if (isset($_REQUEST['method']) && $_REQUEST['method'] === 'transactionapi') {
    
    $fromDate = $_REQUEST['fromDate'] ?? '';
    $toDate = $_REQUEST['toDate'] ?? '';
    $transactionType = $_REQUEST['selectTransationType'] ?? '';
    $transactionStatus = $_REQUEST['selectTransactionStatus'] ?? '';
    $clientCode = $_REQUEST['custIIN'] ?? '';

    // Convert dates from YYYY-MM-DD to DD-MM-YYYY format
    $fromDate = trim($fromDate ?? '');
    $toDate = trim($toDate ?? '');

    // Handle date logic similar to SIP report
    if ($fromDate === '' && $toDate === '') {
        // Case 1: Both empty → last 7 days (based on NSE API max 7 days gap)
        $convrtFromDate = date('d-m-Y', strtotime('-6 days'));
        $convrtToDate   = date('d-m-Y');
        
    } elseif ($fromDate !== '' && $toDate === '') {
        // Case 2: fromDate given, toDate empty → fromDate + 7 days
        $convrtFromDate = date('d-m-Y', strtotime($fromDate));
        $convrtToDate   = date('d-m-Y', strtotime($fromDate . ' +6 days'));
        
    } else {
        // Case 3: Both provided → use given range
        $convrtFromDate = date('d-m-Y', strtotime($fromDate));
        $convrtToDate   = date('d-m-Y', strtotime($toDate));
    }

    // Prepare API request parameters
    $apiParams = [
        "from_date" => $convrtFromDate,
        "to_date"   => $convrtToDate,
        "date_type" => "REQUEST_DATE" // Default as per spec
    ];

    // Add optional parameters if provided
    if ($clientCode !== '') {
        $apiParams["client_code"] = $clientCode;
    }
    
    // Note: The old API had transaction type and status filters
    // The new API doesn't seem to have these exact fields in the sample
    // You might need to check NSE documentation for equivalent fields
    // For now, we'll pass them if they exist in the new API format
    if ($transactionType !== '') {
        // Check NSE documentation for equivalent field name
        // This might be "transaction_type" or similar
        $apiParams["transaction_type"] = $transactionType;
    }
    
    if ($transactionStatus !== '') {
        // Check NSE documentation for equivalent field name
        // This might be "order_status" or similar
        $apiParams["order_status"] = $transactionStatus;
    }

    // Call the new NSE API
    $apiResult = $mfdb->callNseApi('/v2/reports/TRANSACTION_DETAIL_REPORT', $apiParams);

    $httpCode = $apiResult['http_code'] ?? 0;
    $rows     = [];

    if (
        $httpCode === 200 &&
        ($apiResult['json']['response_status'] ?? '') === 'S'
    ) {
        $rows = $apiResult['json']['report_data'] ?? [];
        $totalRecords = $apiResult['json']['report_data_total'] ?? 0;
    }

    /* ===============================
       Inline Styling (safe for AJAX)
       =============================== */
    echo '
    <style>
        .trans-heading {
            background: #2c3e50;
            color: #ffffff;
            padding: 10px 14px;
            margin-bottom: 10px;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 600;
        }

        .trans-table {
            border-collapse: collapse;
            font-size: 13px;
            width: 100%;
        }

        .trans-table th {
            background: #34495e;
            color: #ffffff;
            text-align: left;
            position: sticky;
            top: 0;
        }

        .trans-table td, .trans-table th {
            padding: 7px 8px;
            border: 1px solid #dcdcdc;
            vertical-align: top;
        }

        .trans-table tbody tr:nth-child(even) {
            background-color: #f7f9fb;
        }

        .trans-table tbody tr:hover {
            background-color: #eef2f7;
        }
        
        .amount-cell {
            text-align: right;
            font-family: monospace;
        }
        
        .status-success {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-pending {
            color: #f39c12;
            font-weight: 600;
        }
        
        .status-failed {
            color: #e74c3c;
            font-weight: 600;
        }
    </style>
    ';

    /* ===============================
       Report Summary Heading
       =============================== */
    echo '<div class="trans-heading">';
    echo 'Transaction Report: ' . htmlspecialchars($convrtFromDate) . ' to ' . htmlspecialchars($convrtToDate);
    
    if ($clientCode !== '') {
        if (!empty($rows)) {
            $clientName = $rows[0]['primary_holder_name'] ?? '';
            echo '<br>Client Code: ' . htmlspecialchars($clientCode);
            echo ' &nbsp;|&nbsp; Client Name: ' . htmlspecialchars($clientName);
        }
    }
    
    if (isset($totalRecords)) {
        echo '<br>Total Records: ' . $totalRecords;
    }
    
    echo '</div>';

    /* ===============================
       Table Output
       =============================== */
    echo '<div style="overflow-x:auto; max-height: 600px;">';
    echo '<table class="trans-table">';

    // Table Header
    echo '<thead><tr>';
    echo '<th>#</th>';
    
    if ($clientCode === '') {
        echo '<th>Client Code</th>';
        echo '<th>Client Name</th>';
    }
    
    echo '
        <th>Transaction Date</th>
        <th>Product Type</th>
        <th>Product ID</th>
        <th>AMC</th>
        <th>Scheme</th>
        <th>Transaction Type</th>
        <th>Amount/Units</th>
        <th>Order Status</th>
        <th>Folio/DP No</th>
        <th>Payment Status</th>
    ';
    echo '</tr></thead><tbody>';

    // Table Body
    if (!empty($rows)) {
        $srNo = 1;
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . $srNo++ . '</td>';
            
            if ($clientCode === '') {
                echo '<td>' . htmlspecialchars($row['client_code'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($row['primary_holder_name'] ?? '') . '</td>';
            }
            
            // Format transaction date
            $trxDate = $row['product_reg_date_time'] ?? $row['entry_date'] ?? '';
            echo '<td>' . htmlspecialchars($trxDate) . '</td>';
            
            echo '<td>' . htmlspecialchars($row['product_type'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['product_id'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['amc_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['scheme_name_from'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['transaction_type'] ?? '') . '</td>';
            
            // Amount/Units
            $amountUnits = $row['amount_units'] ?? '';
            if ($amountUnits) {
                echo '<td class="amount-cell">' . htmlspecialchars($amountUnits) . '</td>';
            } else {
                echo '<td class="amount-cell">-</td>';
            }
            
            // Order Status with styling
            $orderStatus = $row['order_status'] ?? '';
            $statusClass = '';
            if (stripos($orderStatus, 'success') !== false || 
                stripos($orderStatus, 'completed') !== false ||
                stripos($orderStatus, 'processed') !== false) {
                $statusClass = 'status-success';
            } elseif (stripos($orderStatus, 'pending') !== false || 
                     stripos($orderStatus, 'processing') !== false) {
                $statusClass = 'status-pending';
            } elseif (stripos($orderStatus, 'rejected') !== false || 
                     stripos($orderStatus, 'failed') !== false ||
                     stripos($orderStatus, 'invalid') !== false) {
                $statusClass = 'status-failed';
            }
            echo '<td class="' . $statusClass . '">' . htmlspecialchars($orderStatus) . '</td>';
            
            // Folio/DP Number
            $folioNo = $row['dp_folio_no'] ?? $row['folio_no'] ?? '';
            echo '<td>' . htmlspecialchars($folioNo) . '</td>';
            
            // Payment Status
            $paymentStatus = $row['payment_status'] ?? '';
            $paymentClass = '';
            if ($paymentStatus === 'SUCCESS') {
                $paymentClass = 'status-success';
            } elseif ($paymentStatus === 'PENDING') {
                $paymentClass = 'status-pending';
            } elseif ($paymentStatus === 'FAILED') {
                $paymentClass = 'status-failed';
            }
            echo '<td class="' . $paymentClass . '">' . htmlspecialchars($paymentStatus) . '</td>';
            
            echo '</tr>';
        }
    } else {
        $colspan = $clientCode === '' ? 12 : 10;
        echo '<tr>
                <td colspan="' . $colspan . '" style="text-align:center;color:red;padding:20px;">
                    No transaction records found for the selected criteria
                </td>
              </tr>';
        
        // If there was an error message from API
        if (isset($apiResult['json']['error_remark']) && $apiResult['json']['error_remark']) {
            echo '<tr>
                    <td colspan="' . $colspan . '" style="text-align:center;color:orange;padding:10px;">
                        Error: ' . htmlspecialchars($apiResult['json']['error_remark']) . '
                    </td>
                  </tr>';
        }
    }

    echo '</tbody></table></div>';

    // Note: Excel download functionality has been removed as per your request
    // You can add it back later using the $rows data if needed
}

// New Transaction History Handler (NSE API)
if (isset($_REQUEST['method']) && $_REQUEST['method'] === 'fetchTransactionHistory') {
    
    $fromDate = trim($_REQUEST['from_date'] ?? '');
    $toDate = trim($_REQUEST['to_date'] ?? '');
    $transactionType = trim($_REQUEST['transaction_type'] ?? 'Above All');
    $transactionStatus = trim($_REQUEST['transaction_status'] ?? 'ALL');
    $ucc = trim($_REQUEST['ucc'] ?? '');

    // Build API request parameters
    $apiParams = [
        "from_date" => $fromDate,
        "to_date"   => $toDate
    ];

    // Note: Transaction Type filtering is handled client-side based on redemption_payout_amount field
    // We do NOT send transaction_type to NSE API
    
    // Add transaction status if not 'ALL'
    if ($transactionStatus !== '' && $transactionStatus !== 'ALL') {
        $apiParams["order_status"] = $transactionStatus;
    }

    // Add Customer UCC (Client Code) if provided
    if ($ucc !== '') {
        $apiParams["client_code"] = $ucc;
    }

    // Log the request
    error_log("\n" . str_repeat("=", 80));
    error_log("in data_manuplation.php:TRANSACTION HISTORY - NSE API CALL");
    error_log("Timestamp: " . date('Y-m-d H:i:s'));
    error_log(str_repeat("-", 80));
    error_log("REQUEST DATA SENT TO NSE API:");
    error_log("Endpoint: /v2/reports/TRANSACTION_DETAIL_REPORT");
    error_log("Request Method: POST");
    error_log("Request Parameters:");
    error_log("  from_date: " . $fromDate);
    error_log("  to_date: " . $toDate);
    error_log("  transaction_type: " . ($transactionType !== 'ALL' ? $transactionType : 'Not set (ALL - client-side filtering)'));
    error_log("  transaction_status: " . ($transactionStatus !== 'ALL' ? $transactionStatus : 'Not set (ALL)'));
    error_log("  client_code: " . ($ucc !== '' ? $ucc : 'Not set'));
    error_log("Raw JSON: " . json_encode($apiParams));

    // Call the NSE API endpoint
    $apiResult = $mfdb->callNseApi('/v2/reports/TRANSACTION_DETAIL_REPORT', $apiParams);

    $httpCode = $apiResult['http_code'] ?? 0;
    $rows     = [];
    $totalRecords = 0;

    // Log the response
    error_log(str_repeat("-", 80));
    error_log("RESPONSE RECEIVED FROM NSE API:");
    error_log("HTTP Status Code: " . $httpCode);
    error_log("Response Status: " . ($apiResult['json']['response_status'] ?? 'N/A'));
    error_log("Response Message: " . ($apiResult['json']['response_msg'] ?? 'N/A'));
    error_log("Total Records Returned: " . ($apiResult['json']['report_data_total'] ?? 0));
    error_log("Error Remark: " . ($apiResult['json']['error_remark'] ?? 'None'));
    
    // Log sample data if available
    if (isset($apiResult['json']['report_data']) && is_array($apiResult['json']['report_data'])) {
        $dataCount = count($apiResult['json']['report_data']);
        error_log("Data Records: " . $dataCount . " records");
        if ($dataCount > 0 && $dataCount <= 2) {
            foreach ($apiResult['json']['report_data'] as $idx => $record) {
                error_log("  Sample Record " . ($idx + 1) . ": " . json_encode($record));
            }
        } elseif ($dataCount > 2) {
            error_log("  First Record (Sample): " . json_encode($apiResult['json']['report_data'][0]));
        }
    }
    error_log(str_repeat("=", 80) . "\n");

    if (
        $httpCode === 200 &&
        ($apiResult['json']['response_status'] ?? '') === 'S'
    ) {
        $rows = $apiResult['json']['report_data'] ?? [];
        $totalRecords = $apiResult['json']['report_data_total'] ?? 0;
    }

    /* ===============================
       Inline Styling (safe for AJAX)
       =============================== */
    echo '
    <style>
        .trans-heading {
            background: #2c3e50;
            color: #ffffff;
            padding: 10px 14px;
            margin-bottom: 10px;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 600;
        }

        .trans-table {
            border-collapse: collapse;
            font-size: 13px;
            width: 100%;
        }

        .trans-table th {
            background: #34495e;
            color: #ffffff;
            text-align: left;
            position: sticky;
            top: 0;
        }

        .trans-table td, .trans-table th {
            padding: 7px 8px;
            border: 1px solid #dcdcdc;
            vertical-align: top;
        }

        .trans-table tbody tr:nth-child(even) {
            background-color: #f7f9fb;
        }

        .trans-table tbody tr:hover {
            background-color: #eef2f7;
        }
        
        .amount-cell {
            text-align: right;
            font-family: monospace;
        }
        
        .status-success {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-pending {
            color: #f39c12;
            font-weight: 600;
        }
        
        .status-failed {
            color: #e74c3c;
            font-weight: 600;
        }
    </style>
    ';

    /* ===============================
       Report Summary Heading
       =============================== */
    echo '<div class="trans-heading">';
    echo 'Transaction Report: ' . htmlspecialchars($fromDate) . ' to ' . htmlspecialchars($toDate);
    
    if ($transactionType !== '' && $transactionType !== 'Above All') {
        echo '<br>Transaction Type: ' . htmlspecialchars($transactionType);
    }
    
    echo '</div>';

    /* ===============================
       Table Output
       =============================== */
    echo '<div style="overflow-x:auto; max-height: 600px;">';
    echo '<table class="trans-table">';

    // Table Header
    echo '<thead><tr>';
    echo '<th>#</th>';
    echo '<th>Client Code</th>';
    echo '<th>Client/Applicant Name</th>';
    echo '<th>Folio/DP No</th>';
    echo '
        <th>Transaction Date</th>
        <th>Product Type</th>
        <th>Scheme</th>
        <th>Amount/Units</th>
        <th>Order Status</th>
        <th>Order Remark</th>
    ';
    echo '</tr></thead><tbody>';

    // Table Body - Count filtered records
    $filteredRecordCount = 0;
    if (!empty($rows)) {
        $srNo = 1;
        foreach ($rows as $row) {
            // Filter by transaction status based on order_status exact value
            $orderStatusUpper = strtoupper($row['order_status'] ?? '');
            $shouldDisplay = false;
            
            // Check transaction type based on redemption_payout_amount field
            $redemptionAmount = $row['redemption_payout_amount'] ?? '';
            $isRedemption = !empty($redemptionAmount);
            
            if ($transactionStatus === 'ALL') {
                // Show all records
                $shouldDisplay = true;
            } elseif ($transactionStatus === 'VALID') {
                // Show only records where order_status = VALID
                if ($orderStatusUpper === 'VALID') {
                    $shouldDisplay = true;
                }
            } elseif ($transactionStatus === 'INVALID') {
                // Show only records where order_status = INVALID
                if ($orderStatusUpper === 'INVALID') {
                    $shouldDisplay = true;
                }
            }
            
            // Apply transaction type filter
            if ($shouldDisplay && $transactionType !== '' && $transactionType !== 'ALL') {
                if ($transactionType === 'Purchase') {
                    // Show only if redemption_payout_amount is NOT available
                    $shouldDisplay = !$isRedemption;
                } elseif ($transactionType === 'Redemption') {
                    // Show only if redemption_payout_amount IS available
                    $shouldDisplay = $isRedemption;
                }
            }
            
            if ($shouldDisplay) {
                $filteredRecordCount++;
            echo '<tr>';
            echo '<td>' . $srNo++ . '</td>';
            echo '<td>' . htmlspecialchars($row['client_code'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['applicant_name'] ?? $row['primary_holder_name'] ?? '') . '</td>';
            
            // Folio/DP Number
            $folioNo = $row['dp_folio_no'] ?? $row['folio_no'] ?? $row['folio_number'] ?? '';
            echo '<td>' . htmlspecialchars($folioNo) . '</td>';
            
            // Format transaction date
            $trxDate = $row['product_reg_date_time'] ?? $row['entry_date'] ?? '';
            echo '<td>' . htmlspecialchars($trxDate) . '</td>';
            
            echo '<td>' . htmlspecialchars($row['product_type'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['scheme_name_from'] ?? '') . '</td>';
            
            // Amount/Units
            $amountUnits = $row['amount_units'] ?? '';
            if ($amountUnits) {
                echo '<td class="amount-cell">' . htmlspecialchars($amountUnits) . '</td>';
            } else {
                echo '<td class="amount-cell">-</td>';
            }
            
            // Order Status with styling
            $orderStatus = $row['order_status'] ?? '';
            $statusClass = '';
            if (stripos($orderStatus, 'success') !== false || 
                stripos($orderStatus, 'completed') !== false ||
                stripos($orderStatus, 'processed') !== false) {
                $statusClass = 'status-success';
            } elseif (stripos($orderStatus, 'pending') !== false || 
                     stripos($orderStatus, 'processing') !== false) {
                $statusClass = 'status-pending';
            } elseif (stripos($orderStatus, 'rejected') !== false || 
                     stripos($orderStatus, 'failed') !== false ||
                     stripos($orderStatus, 'invalid') !== false ||
                     stripos($orderStatus, 'error') !== false) {
                $statusClass = 'status-failed';
                
                // Log INVALID order status errors with detailed sample data
                if (stripos($orderStatus, 'invalid') !== false) {
                    error_log("\n" . str_repeat("!", 80));
                    error_log("INVALID ORDER STATUS DETECTED - TRANSACTION HISTORY");
                    error_log("Timestamp: " . date('Y-m-d H:i:s'));
                    error_log(str_repeat("-", 80));
                    error_log("TRANSACTION DETAILS:");
                    error_log("  Member Code: " . ($row['member_code'] ?? 'N/A'));
                    error_log("  Client Code: " . ($row['client_code'] ?? 'N/A'));
                    error_log("  Applicant Name: " . ($row['applicant_name'] ?? $row['primary_holder_name'] ?? 'N/A'));
                    error_log("");
                    error_log("INVESTMENT DETAILS:");
                    error_log("  Product Type: " . ($row['product_type'] ?? 'N/A'));
                    error_log("  Product ID: " . ($row['product_id'] ?? 'N/A'));
                    error_log("  AMC Name: " . ($row['amc_name'] ?? 'N/A'));
                    error_log("  Scheme: " . ($row['scheme_name_from'] ?? 'N/A'));
                    error_log("");
                    error_log("TRANSACTION STATUS:");
                    error_log("  Transaction Type: " . ($row['transaction_type'] ?? 'N/A'));
                    error_log("  Order Status: " . $orderStatus . " (INVALID)");
                    error_log("  Payment Status: " . ($row['payment_status'] ?? 'N/A'));
                    error_log("");
                    error_log("AMOUNT DETAILS:");
                    error_log("  Amount/Units: " . ($row['amount_units'] ?? 'N/A'));
                    error_log("");
                    error_log("DATE & FOLIO DETAILS:");
                    error_log("  Transaction Date: " . ($row['product_reg_date_time'] ?? $row['entry_date'] ?? 'N/A'));
                    error_log("  Entry Date: " . ($row['entry_date'] ?? 'N/A'));
                    error_log("  Folio/DP No: " . ($row['dp_folio_no'] ?? $row['folio_no'] ?? $row['folio_number'] ?? 'N/A'));
                    error_log("");
                    error_log("COMPLETE RECORD (JSON):");
                    error_log(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    error_log(str_repeat("!", 80) . "\n");
                }
            }
            echo '<td class="' . $statusClass . '">' . htmlspecialchars($orderStatus) . '</td>';
            
            // Order Remark
            $orderRemark = $row['order_remark'] ?? $row['remarks'] ?? '';
            echo '<td>' . htmlspecialchars($orderRemark) . '</td>';
            
            echo '</tr>';
					}
        }
    } else {
        echo '<tr>
                <td colspan="10" style="text-align:center;color:red;padding:20px;">
                    No transaction records found for the selected criteria
                </td>
              </tr>';
        
        // If there was an error message from API
        if (isset($apiResult['json']['error_remark']) && $apiResult['json']['error_remark']) {
            echo '<tr>
                    <td colspan="10" style="text-align:center;color:orange;padding:10px;">
                        Error: ' . htmlspecialchars($apiResult['json']['error_remark']) . '
                    </td>
                  </tr>';
        }
    }

    echo '</tbody></table></div>';
    
    // Display filtered record count
    if ($filteredRecordCount > 0) {
        if ($transactionStatus === 'ALL') {
            echo '<div style="margin-top:15px; padding:10px; background-color:#e8f5e9; border-left:4px solid #4caf50;"><strong>Total Records Displayed: ' . $filteredRecordCount . ' (All records)</strong></div>';
        } else {
            echo '<div style="margin-top:15px; padding:10px; background-color:#e3f2fd; border-left:4px solid #2196f3;"><strong>Total Records Displayed: ' . $filteredRecordCount . ' (' . htmlspecialchars($transactionStatus) . ' records)</strong></div>';
        }
    }
}

	/*
	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'transactionapi'){
		
		$response_array = [];

		$fromDate = $_REQUEST['fromDate'];
		$toDate = $_REQUEST['toDate'];
		$transactionType = $_REQUEST['selectTransationType'];

		$transactionStatus = $_REQUEST['selectTransactionStatus'];
		$checkUserIIN = $_REQUEST['custIIN'];

		$convrtFromDate = date('d-M-Y', strtotime($fromDate));
		
	    $convrtToDate = date('d-M-Y', strtotime($toDate));
		

		$apiDetails = $db->getApiDetails();
	     if($apiDetails){
	        $appId = "";
	        $appPass ="";
	        $brockerCode ="";
	        $app_url = "";

	        foreach($apiDetails as $row){
	            $appId = $row['appId'];
	            $appPass =$row['appPass'];
	            $brockerCode = $row['brockerCode'];
	            $app_url = $row['apiDomain'];
	        }
	    }
	
		$heading_Excel = ["CUSTOMER_ID"=>"string","INVESTOR_NAME"=>"string","TRXN_TYPE"=>"string",
					"AMOUNT" =>"string","UNITS"=>"string","All_Units"=>"string","AMC_NAME" =>"string",
					"SCHEME NAME"=>"string","REGISTRAR_ID" =>"string","FOLIO_NO."=>"string","USER_CODE" =>"string",
					"USER_TRXN_NO"=>"string","APPLICATION_NO"=>"string","PAYMENT_MODE"=>"string", "INSTRM_NO"=>"string","BANK_NAME"=>"string",
					"ACC_NO"=>"string","PAYMENT_REF_NO"=>"string","ENTRY_DATE"=>"string","AUTHORIZED_DATE_TIME"=>"string","TRXN_STATUS"=>"string"
					,"FUND_TRANS_DATE"=>"string","REMARKS"=>"string","RTA_STATUS"=>"string","REJECTED_REASON"=>"string","REJECTION_DATE"=>"string","UMRN_NO"=>"string","FUNDCLEAR_DATE"=>"string"];
		$writeExcel->writeSheetHeader("sheet1",$heading_Excel);
		// echo $writeExcel;
		$xml_data = '<?xml version="1.0" encoding="UTF-8"?>
				<NMFIIService>
					<service_request>
						<appln_id>'.$appId.'</appln_id>
						<password>'.$appPass.'</password> 
						<broker_code>'.$brockerCode.'</broker_code> 
						<from_date>'.$convrtFromDate.'</from_date>
						<to_date>'.$convrtToDate.'</to_date>
						<initiated_by>B</initiated_by>
						<iin>'.$checkUserIIN.'</iin>   
						<trxn_type>'.$transactionType.'</trxn_type>
						<trxn_status>'.$transactionStatus.'</trxn_status>
					</service_request>
				</NMFIIService>';
				
		$url	=	$app_url."NMFIITrxnService/NMFTrxnService/TRXN_DETAILED_REPORT";
	
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);



		$output = curl_exec($ch);
		file_put_contents('debugg/trxn_detailed_status_request_data.xml',$xml_data);
		file_put_contents('debugg/trxn_detailed_status_response_data.xml', $output);
		$data = new SimpleXMLElement($output);


        $dataSearch = $data->children('urn:schemas-microsoft-com:xml-diffgram-v1')->children()->NMFIISERVICES;
        	
        // service status
        foreach ($dataSearch->service_status as $status):
            $service_code = $status->service_return_code;   
        endforeach;

        if($service_code == 0){
        		//echo "hello3";
			//service responses
        		date_default_timezone_set("Asia/Kolkata");
			 $currdate = date("d-m-y h-m-s");
			 $src = "ExcelFiles/Transaction_Details_Report_".$currdate.".xlsx";
			// echo $src;
			 echo '<div style="margin-bottom:20px;"><a href="'.$src.'" style="float: right;">Download Report</a></div>';
			echo "<table style='border:2px;'  >
				<tr>
						<th>Customer Id</th>
						<th>Customer Name</th>
						<th>Scheme Name</th>
						<th>Transaction Type</th>
						<th>Amount/Units</th>
						<th>Transaction Date</th>
						<th>Status</th>
						
				</tr>
			";
			
			foreach ($dataSearch->service_response as $response):
				
				$response_array[] = array("custId"=>"$response->CUSTOMER_ID","investorName"=>"$response->INVESTOR_NAME","transationType"=>"$response->TRXN_TYPE","amount"=>"$response->AMOUNT","units"=>"$response->UNITS","schemeName"=>"$response->SCHEME_NAME",
												 "entryDate"=>"$response->ENTRY_DATE","transationSta"=>"$response->TRXN_STATUS");

				

				$response_array2 = array("$response->CUSTOMER_ID","$response->INVESTOR_NAME",
										"$response->TRXN_TYPE","$response->AMOUNT",
										"$response->UNITS","$response->ALL_UNITS",
										"$response->AMC_NAME","$response->SCHEME_NAME",
										"$response->REGISTRAR_ID","$response->FOLIO_NO",

										"$response->USER_CODE","$response->USER_TRXNNO",

										"$response->APPLICATION_NO","$response->PAYMENT_MODE",
										"$response->INSTRM_NO","$response->BANK_NAME",
										"$response->AC_NO","$response->PAYMENT_REF_NO",

										"$response->ENTRY_DATE","$response->AUTHORIZED_DATE_TIME",
										"$response->TRXN_STATUS","$response->FUND_TRANS_DATE",
										"$response->REMARKS","$response->RTA_STATUS",
										"$response->REJECTED_REASON","$response->REJECTION_DATE",
										"$response->UMRN_NO","$response->FUND_CLEARED_DATE","$response->TARGET_PRODUCT");
				$writeExcel->writeSheetRow("sheet1",$response_array2);

				
				$db->saveNseApiTransactionsInDb($response->CUSTOMER_ID,$response->INVESTOR_NAME,$response->TRXN_TYPE,$response->AMOUNT,$response->UNITS,$response->ALL_UNITS,$response->AMC_NAME,$response->SCHEME_NAME,$response->REGISTRAR_ID,$response->FOLIO_NO,$response->USER_CODE,$response->USER_TRXNNO,$response->APPLICATION_NO,$response->PAYMENT_MODE,$response->INSTRM_NO,$response->BANK_NAME,$response->AC_NO,$response->PAYMENT_REF_NO,$response->ENTRY_DATE,$response->AUTHORIZED_DATE_TIME,$response->TRXN_STATUS,$response->FUND_TRANS_DATE,$response->REMARKS,$response->RTA_STATUS,$response->REJECTED_REASON,$response->REJECTION_DATE,$response->UMRN_NO,$response->FUND_CLEARED_DATE,$response->TARGET_PRODUCT); 
                
			
			endforeach;
			 
			 //echo $currdate;



			$writeExcel->writeToFile("ExcelFiles/Transaction_Details_Report_".$currdate.".xlsx");

			foreach ($dataSearch->service_response as $response):
				echo "<tr>

					<td>".$response->CUSTOMER_ID."</td>
					<td>".$response->INVESTOR_NAME."</td>

					<td>".$response->SCHEME_NAME."</td>

					<td>".$response->TRXN_TYPE."</td>";
				if($response->ALL_UNITS == "Yes")
					echo "<td>All Units</td>";
				else
					if($response->AMOUNT > 0)
						echo "<td>Rs ".$response->AMOUNT."</td>";
					else
						echo "<td>".$response->UNITS."</td>";
				
				echo "<td>".$response->ENTRY_DATE."</td>
					<td>".$response->TRXN_STATUS."</td>
				</tr>";


			endforeach;


			echo "</table>";
		
        }else{
		//service responses
			$request_array = [];
            foreach ($dataSearch->service_response as $response):
            	if($response->Status_Desc == "No Data Found"){
            		echo '<p style="color:red;font-size:20px;">'.$response->Status_Desc.'</p>';
            	}else{
            		echo '<p style="color:red;font-size:20px;">'.$response->return_msg.'</p>';
            	}
            	
            	
                $request_array[] = $response->return_msg;
            endforeach;
            // echo $request_array;
           
        }	
	}
	*/
	$uploadMethod ='';
	if(isset($_POST['hiddenmethod'])!=''){
		$uploadMethod = $_POST['hiddenmethod'];
	}
	
	if($uploadMethod!='' && $uploadMethod === 'UploadMfList'){
		
		function uploadfile(){
			global $target_file;
			global $outputStr;
			$target_dir = "uploads/";
			$imageFileType = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));
			// Secure the filename by hashing the original name and appending uniqid
			$safeName = uniqid('upload_') . '.' . $imageFileType;
			$target_file = $target_dir . $safeName;
			$uploadOk = 1;

			// Check if file already exists
			/*	if (file_exists($target_file)) {
				echo "Sorry, file already exists.";
				$uploadOk = 0;
			}
			*/
			// Check file size
			if ($_FILES["fileToUpload"]["size"] > 7000000) {
				$outputStr.= "<br> Sorry, your file is too large.";
				$uploadOk = 0;
			}
			// Allow certain file formats
			if($imageFileType != "xlsx" && $imageFileType != "xls" && $imageFileType != "csv" ) {
				$outputStr.= "<br> Sorry, only xlsx, xls & csv files are allowed.";
				$uploadOk = 0;
			}
			// Check if $uploadOk is set to 0 by an error
			if ($uploadOk == 0) {
				$outputStr.= "<br> Sorry, your file was not uploaded.";
			// if everything is ok, try to upload file
			} else {
				if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
					$outputStr .= "<br>The file <a href=\"$target_file\" download>".basename($target_file)."</a> has been uploaded.";
				} else {
					$outputStr.= "Sorry, there was an error uploading your file.";
				}
				
				
			}
		}

		
		function insertIntoDB($str,$amcCode,$productCode,$fieldsName){
			global $db;
			return $db->insertOrUpdateMFMasterBulk($str, $amcCode, $productCode, $fieldsName);
		}

		function ExportPP2DB($dbtable,$tfile,$fCount)
		{
		
			global $db;
			$productCode ='';
			$amcCode = '';
			$target_file = $tfile;
			$databasetable = $dbtable;
			$count1 = 0;
			$count2 = 0;
			$count3 = 0;
			$upCountS = 0;
			$upCountF = 0;
			$lookupTable = array('AMC_CODE'=>'amc_code','PRODUCT_CODE'=>'product_code','PRODUCT_LONG_NAME'=>'product_long_name','SYSTEMATIC_FREQUENCIES'=>'systematic_frequencies','SIP_DATES'=>'sip_dates','STP_DATES'=>'stp_dates','SWP_DATES'=>'swp_dates','PURCHASE_ALLOWED'=>'purchase_allowed','SWITCH_ALLOWED'=>'switch_allowed','REDEMPTION_ALLOWED'=>'redemption_allowed','SIP_ALLOWED'=>'sip_allowed','STP_ALLOWED'=>'stp_allowed','SWP_ALLOWED'=>'swp_allowed','REINVEST_TAG'=>'reinvest_tag','PRODUCT_CATEGORY'=>'product_category','ISIN'=>'isin','LAST_MODIFIED_DATE'=>'last_modified_date','ACTIVE_FLAG'=>'active_flag','ASSET_CLASS'=>'asset_class','SUB_FUND_CODE'=>'sub_fund_code','PLAN_TYPE'=>'plan_type','INSURANCE_ENABLED'=>'insurance_enabled','RTA_CODE'=>'rtacode','NFO_ENABLED'=>'nfo_enabled','NFO_CLOSE_DATE'=>'nfo_close_date','NFO_SIP_EFFECTIVE_DATE'=>'nfo_sip_effective_date','ELSS'=>'elss','ETF'=>'etf','Arbitrage'=>'arbitrage','Small-cap'=>'small_cap','Mid-cap'=>'mid_cap','Multi-cap'=>'multi_cap','Large-cap'=>'large_cap','Focussed'=>'focussed','Sector Funds'=>'sector_funds','Other Equities'=>'other_equities','Equity'=>'equity','Hybrid'=>'hybrid','Debt'=>'debt','Other Debt Funds'=>'other_debt','Bond Fund'=>'bond','Credit Risk'=>'credit_risk','Short Term Plan'=>'stp','Gilt'=>'gilt','Cash/Liquid'=>'cash_liquid','FOF'=>'fof','Gold'=>'gold','RTACODE'=>'rtacode','Focused'=>'focussed','Bond'=>'bond','STP'=>'stp');
				
			set_include_path(get_include_path() . PATH_SEPARATOR . 'Classes/');
			include 'PHPExcel/IOFactory.php';

			// This is the file path to be uploaded.
			$inputFileName = $target_file;
			//echo "Selected file:".$inputFileName;
			try {
				$objPHPExcel = PHPExcel_IOFactory::load($inputFileName);
			} catch(Exception $e) {
				die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
			}


			$allDataInSheet = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
			$arrayCount = count($allDataInSheet);  // Here get total count of row in that Excel sheet

			//echo "<br><br> date: $date , fuelcompany=$fuelcompany";
			//$fuelcompanyid = getId("fuelcompany","fuelcompanyname",$fuelcompany,"fuelcompanyid");
			//echo "<br><br>Row1 Data : datefield = $datefield , date = $date,fuelcompanyfield = $fuelcompanyfield, fuelcompany = $fuelcompany, fuelcompanyid = $fuelcompanyid <br>";
			

			$alpha=array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","AB","AC","AD","AE","AF","AG","AH","AI","AJ","AK","AL","AM","AN","AO","AP","AQ","AR","AS","AT","AU","AV");
			
			$str =array();
			$alphaCount = sizeof($alpha);
			$dateField = array();
			for($k=0;$k<$alphaCount;$k++){
				if($allDataInSheet[1]["$alpha[$k]"]!=''){
					//$strField = strtolower($allDataInSheet[1]["$alpha[$k]"]);
					$fieldsName[$k] = $lookupTable[$allDataInSheet[1]["$alpha[$k]"]];
					if(stripos("open_date,created_date,last_modified_date,nfo_close_date,nfo_sip_effective_date" , $fieldsName[$k]) !=FALSE){
						array_push($dateField, $k);
						//$dateField[] = $k;
					}
					/*
					if (strpos($strField, '/') !== false) {
						$strField = str_replace("/","_",$strField);
						$fieldsName[$k] = $strField;
					}else if(strpos($strField, ' ') !== false){
						$strField = str_replace(" ","_",$strField);
						$fieldsName[$k] = $strField;
					}else if(strpos($strField, '-') !== false){
						$strField = str_replace("-","_",$strField);
						$fieldsName[$k] = $strField;
					}else{
						$fieldsName[$k] = $strField;
					}
					*/
				}else{
					continue;
				}
			
			}
			//echo $arrayCount;
			for($row=2;$row<=$arrayCount;$row++){
			   if($allDataInSheet[$row]["A"]!=''){
				   $amcCode = $allDataInSheet[$row]["A"];
				   $productCode = $allDataInSheet[$row]["B"];
					for($col=0;$col<=$alphaCount;$col++){
						if (array_key_exists($col, $alpha)){
							$value_data = $allDataInSheet[$row]["$alpha[$col]"];
							if (in_array($col, $dateField)){
								if(trim($value_data) != "" || trim($value_data) != null){
									$convrtToDate = date('Y-m-d', strtotime($value_data));
									//echo "Convert Date: ".$convrtToDate;
									$value_data = $convrtToDate;
									
								}
								
							}
							if($value_data==''){
								$value_data = 'NULL';
								if($str==""){
									if($value_data == 'NULL'){
										$str[$col]=$value_data;
									}else{
										$str[$col]='"'.$value_data.'"';
									}
										
								}else{
									if($value_data == 'NULL'){
										$str[$col]= $value_data;
									}else{
										$str[$col]= '"'.$value_data.'"';
									}
									
								}
							}else{
								if($str==""){
									$str[$col]='"'.$value_data.'"';
									//echo "<br>$col : Before = ".$value_data;
								}else{
									//echo ", After = ".$value_data;
									$str[$col]='"'.$value_data.'"';
									//$str[$col]=$str2;
									//echo "<br> String data : $str2 | " .$str[$col];
								}
							}
							
						}
					}
			   }else{
				   $count3++;
				   continue;
			   }
				//echo "<br> String data 2: " ;
				//var_dump($str);
				
				$msg = insertIntoDB($str,$amcCode,$productCode,$fieldsName);
				if($msg == "Inserted Successfully"){
					$count1++;
				}
				if($msg == "Insert Failed"){
					$count2++;
				}
				if($msg == "Updated Successfully"){
					$upCountS++;
				}
				if($msg == "Updation Failed"){
					$upCountF++;
				}
				 $str=array();
				 
			}
			echo "<br> Total Record Inserted := " .$count1;
			echo "<br> Total Insertion Failed := " .$count2;
			echo "<br> Total Empty Record ingnored := " .$count3;
			echo "<br> Total Record Updated := " .$upCountS;
			echo "<br> Total Record Updation Failed := " .$upCountF;

			$logMsg = 'Total Record Inserted := '.$count1.', ';
			$logMsg .= 'Total Insertion Failed :='.$count2.', ';
			$logMsg .= 'Total Record Updated := '.$upCountS.', ';
			$logMsg .= 'Total Record Updation Failed := '.$upCountF.', ';
			return $logMsg;

			/*	for($k=0;$k<$fCount;$k++){
				$fieldList[$k] = strtolower(trim($allDataInSheet[2]["$alpha[$k]"]));
					//echo "<br><br> field name: $k=".$fieldList[$k];
			}
			//$fieldCount = count($fieldList);
			*/
		
		}


		function updateLog($msg1){
	global $db;
	$remarks = NULL;
	if(isset($_REQUEST['remarks'])) $remarks = $_REQUEST['remarks'];
	$db->insertAdminLog($msg1, $remarks);
}

		uploadfile();
		
		
		$msg1 = ExportPP2DB("tb_mf_master",$target_file,10);
		
		updateLog($msg1);

	}


	// City Upload


	if($uploadMethod!='' && $uploadMethod === 'cityUpload'){
		
		function uploadfile_city(){
			global $target_file;
			global $outputStr;
			$target_dir = "uploads/";
			$imageFileType = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));
			$safeName = uniqid('city_') . '.' . $imageFileType;
			$target_file = $target_dir . $safeName;
			$uploadOk = 1;

			// Check if file already exists
			/*	if (file_exists($target_file)) {
				echo "Sorry, file already exists.";
				$uploadOk = 0;
			}
			*/
			// Check file size
			if ($_FILES["fileToUpload"]["size"] > 7000000) {
				$outputStr.= "<br> Sorry, your file is too large.";
				$uploadOk = 0;
			}
			// Allow certain file formats
			if($imageFileType != "xlsx" && $imageFileType != "xls" && $imageFileType != "csv" ) {
				$outputStr.= "<br> Sorry, only xlsx, xls & csv files are allowed.";
				$uploadOk = 0;
			}
			// Check if $uploadOk is set to 0 by an error
			if ($uploadOk == 0) {
				$outputStr.= "<br> Sorry, your file was not uploaded.";
			// if everything is ok, try to upload file
			} else {
				if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
					$outputStr .= "<br>The file <a href=\"$target_file\" download>".basename($target_file)."</a> has been uploaded.";
				} else {
					$outputStr.= "Sorry, there was an error uploading your file.";
				}
				
				
			}
		}

		function connectDB_city(){
			/*require_once("config.php");
			global $db;
			$db = new MYSQLDB();
			if ($conn->connect_error) {
			 	die("Connection failed: " . $conn->connect_error);
			}*/
			global $conn,$db;
			$config = parse_ini_file("/home/rrj6671syt4c/Production/config.ini"); 
			$server= $_SERVER["SERVER_ADDR"];
			$localhostIps = array("127.0.0.1","::1");
			if (in_array($server, $localhostIps)) 
				$config = parse_ini_file("../../../Production/aw_config.ini");
			$descpass = $db->decrypt($config['DB_PASSWORD']);
			$db = new MYSQLDB();			
		}

		function insertIntoDB_city($dbtable,$colCount,$fieldsName,$fieldsData){
	global $db;
	$success = $db->insertDynamicRecord($dbtable, $fieldsName, $fieldsData);
	if ($success) {
		return "Inserted Successfully";
	} else {
		return "Already exists";
	}
}

		function ExportPP2DB_city($dbtable,$tfile,$fCount)
		{
			global $db;
			$productCode ='';
			$amcCode = '';
			$target_file = $tfile;
			
			$count1 = 0;
			$count2 = 0;
			$count3 = 0;
			$upCountS = 0;
			$upCountF = 0;
		
			set_include_path(get_include_path() . PATH_SEPARATOR . 'Classes/');
			include 'PHPExcel/IOFactory.php';

			// This is the file path to be uploaded.
			$inputFileName = $target_file;
			//echo "Selected file:".$inputFileName;
			try {
				$objPHPExcel = PHPExcel_IOFactory::load($inputFileName);
			} catch(Exception $e) {
				die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
			}


			$allDataInSheet = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
			$arrayCount = count($allDataInSheet);  // Here get total count of row in that Excel sheet

			//echo "<br><br> date: $date , fuelcompany=$fuelcompany";
			//$fuelcompanyid = getId("fuelcompany","fuelcompanyname",$fuelcompany,"fuelcompanyid");
			//echo "<br><br>Row1 Data : datefield = $datefield , date = $date,fuelcompanyfield = $fuelcompanyfield, fuelcompany = $fuelcompany, fuelcompanyid = $fuelcompanyid <br>";
			

			$alpha=array("A","B","C","D");
			
			$str =array();
			$alphaCount = sizeof($alpha);
			$fieldsName = array();
			$fieldsData = array();
			for($k=0;$k<$alphaCount;$k++){
				if($allDataInSheet[1]["$alpha[$k]"]!=''){
					$fieldsName[$k] = strtolower($allDataInSheet[1]["$alpha[$k]"]);
				}else{
					continue;
				}
				
			}
			//echo $arrayCount;
			for($row=2;$row<=$arrayCount;$row++){
			   if($allDataInSheet[$row]["A"]!=''){
				   for($k=0;$k<$alphaCount;$k++){
						if($allDataInSheet[$row]["$alpha[$k]"]!=''){
							$fieldsData[$k] = strtolower($allDataInSheet[$row]["$alpha[$k]"]);
						}else{
							continue;
						}
						
					}
				   
			   }else{
				   $count3++;
				   continue;
			   }
				//echo "<br> String data 2: " ;
				//var_dump($str);
				
				$msg = insertIntoDB_city($dbtable,$alphaCount,$fieldsName,$fieldsData);
				if($msg == "Inserted Successfully"){
					$count1++;
				}
				if($msg == "Insert Failed"){
					$count2++;
				}
				if($msg == "Already exists"){
					$upCountS++;
				}
				// if($msg == "Updation Failed"){
				// 	$upCountF++;
				// }
				 $str=array();
				 
			}
			echo "<br> Total Record Inserted := " .$count1;
			echo "<br> Total Insertion Failed := " .$count2;
			echo "<br> Total Empty Record ingnored := " .$count3;
			echo "<br> Total Record Already exists := " .$upCountS;
			// echo "<br> Total Record Updation Failed := " .$upCountF;

			$logMsg = 'City Pincode :  Total Record Inserted := '.$count1.', ';
			$logMsg .= 'City Pincode : Total Insertion Failed :='.$count2.', ';
			$logMsg .= 'City Pincode : Total Record Already exists := '.$upCountS.', ';
			// $logMsg .= 'Total Record Updation Failed := '.$upCountF.', ';
			
			
			
			return $logMsg;

			/*	for($k=0;$k<$fCount;$k++){
				$fieldList[$k] = strtolower(trim($allDataInSheet[2]["$alpha[$k]"]));
					//echo "<br><br> field name: $k=".$fieldList[$k];
			}
			//$fieldCount = count($fieldList);
			*/
			
		}


		function updateLog_city($msg1){
	global $db;
	$remarks = NULL;
	if(isset($_REQUEST['remarks'])) $remarks = $_REQUEST['remarks'];
	$db->insertAdminLog($msg1, $remarks);
}

		uploadfile_city();
		
		connectDB_city();
		$msg1 = ExportPP2DB_city("mtb_city_pincode",$target_file,10);
		
		updateLog_city($msg1);

	}
	
		if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'transactionreports'){
		
		$fromDate = $_REQUEST['fromDate'];
		$toDate = $_REQUEST['toDate'];
		$transactionType = $_REQUEST['selectTransationType'];

		$transactionStatus = $_REQUEST['selectTransactionStatus'];
		$checkUserIIN = $_REQUEST['custIIN'];

		//$convrtFromDate = date('Y-m-d', strtotime($fromDate));
		
	   // $convrtToDate = date('Y-m-d', strtotime($toDate));
		

		$results = $db->getTransactionDetails($fromDate, $toDate, $checkUserIIN,$transactionType);

		if (!empty($results)) {
			//print_r($results);
			echo '<table style="font-size: 12px;">';

			// Display table headers
			echo '<tr>';
			echo '<th>Sl No</th>';
			echo '<th>IIN</th>';
			echo '<th>PAN</th>';
			echo '<th>Name</th>';
			echo '<th>Process Date</th>';
			echo '<th>Transaction Number</th>';
			echo '<th>Transaction Head</th>';
			echo '<th>Product Code</th>';
			echo '<th>Fund</th>';
			echo '<th>Folio Number</th>';
			echo '<th>Scheme Code</th>';
			echo '<th>Fund Description</th>';
			echo '<th>Price</th>';
			echo '<th>Units</th>';
			echo '<th>Amount</th>';
			echo '<th>Transaction Mode</th>';
			echo '<th>Transaction Status</th>';
			echo '</tr>';

			// Display table rows
			foreach ($results as $row) {
				echo '<tr>';
				echo '<td>' . $row['slno'] . '</td>';
				echo '<td>' . $row['iin'] . '</td>';
				echo '<td>' . $row['pan_no'] . '</td>';
				echo '<td>' . $row['investor_name'] . '</td>';
				echo '<td>' . $row['process_date'] . '</td>';
				echo '<td>' . $row['trxn_num'] . '</td>';
				echo '<td>' . $row['trxn_head'] . '</td>';
				echo '<td>' . $row['prod_code'] . '</td>';
				echo '<td>' . $row['fund'] . '</td>';
				echo '<td>' . $row['folio_num'] . '</td>';
				echo '<td>' . $row['scheme_code'] . '</td>';
				echo '<td>' . $row['fund_desc'] . '</td>';
				echo '<td>' . $row['price'] . '</td>';
				echo '<td>' . $row['units'] . '</td>';
				echo '<td>' . $row['amount'] . '</td>';
				echo '<td>' . $row['trxn_mode'] . '</td>';
				echo '<td>' . $row['trxn_status'] . '</td>';
				echo '</tr>';
			}

			echo '</table>';
		} else {
			echo 'No results found.';
		}



		// echo $writeExcel;
	
		/*{
		//service responses
			$request_array = [];
            foreach ($dataSearch->service_response as $response):
            	if($response->Status_Desc == "No Data Found"){
            		echo '<p style="color:red;font-size:20px;">'.$response->Status_Desc.'</p>';
            	}else{
            		echo '<p style="color:red;font-size:20px;">'.$response->return_msg.'</p>';
            	}
            	
            	
                $request_array[] = $response->return_msg;
            endforeach;
            // echo $request_array;
           
        }	*/
	}

	/* 
	 * ============================================================
	 * REMOVED: sipreportsNseInvest method - This was causing the
	 * issue of fetching data for ALL 243 users from database
	 * ============================================================
	 */

	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'sipreportsNse'){
		
		$response_array = [];
		$fromDate = $_REQUEST['fromDate'];
		$toDate = $_REQUEST['toDate'];
		//$transactionType = $_REQUEST['selectTransationType'];
		//$transactionStatus = $_REQUEST['selectTransactionStatus'];
		$checkUserIIN = $_REQUEST['custIIN'];
		$convrtFromDate = date('d-M-Y', strtotime($fromDate));
	    $convrtToDate = date('d-M-Y', strtotime($toDate));
		
		$apiDetails = $db->getApiDetails();
	     if($apiDetails){
	        $appId = "";
	        $appPass ="";
	        $brockerCode ="";
	        $app_url = "";

	        foreach($apiDetails as $row){
				 $appId = $row['appId'];
				$appPass =$row['appPass'];
				$brockerCode = $row['brockerCode'];
				$euin = $row['EUIN'];
				$app_url = $row['apiDomain'];
	        }
	    }
		$plans = array('SIP', 'SWP', 'STP');
		foreach ($plans as $plan) {
			//echo "Plan Type: $plan<br>";
		
			$xml_data = '<?xml version="1.0" encoding="UTF-8"?>
				<NMFIIService>
					<service_request>
						<appln_id>'.$appId.'</appln_id>
                        <password>'.$appPass.'</password>
                        <broker_code>'.$brockerCode.'</broker_code>
                        <fromdate>'.$convrtFromDate.'</fromdate>
                        <todate>'.$convrtToDate.'</todate>
                        <report_type>'.$plan.'</report_type>
                        <ceased_trxn>Y</ceased_trxn>
                        <cust_id>'.$checkUserIIN.'</cust_id>
                        <unique_no></unique_no>
                        <systematic_maturity>N</systematic_maturity>   
					</service_request>
				</NMFIIService>';
				
			$url = $app_url."NMFIITrxnService/NMFTrxnService/SYSREGISTRATIONSREPORT";
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			$output = curl_exec($ch);
			file_put_contents('debugg/running_'.$plan.'_report_request.xml',$xml_data);
			file_put_contents('debugg/running_'.$plan.'_report_response_data.xml', $output);
			$data = new SimpleXMLElement($output);
			$dataSearch = $data->children('urn:schemas-microsoft-com:xml-diffgram-v1')->children()->NMFIISERVICES;
        	// service status
            foreach ($dataSearch->service_status as $status):
                $service_code = $status->service_return_code;   
            endforeach;

            if($service_code == 0){
				
				
			// echo "<table style='border:2px;'  >
			// 	<tr>
			// 			<th>Customer_Id</th>
			// 			<th>Customer_name</th>
			// 			<th>Plan Name</th>
			// 			<th>Bank Name</th>
			// 			<th>Scheme</th>
			// 			<th>Amount</th>
			// 			<th>frequency</th>
			// 			<th>Inst date</th>
			// 			<th>From date</th>
			// 			<th>To date</th>
			// 	</tr>
			// ";

                //$res = $db->deleteallSipdata($userId);
                //service responses
                    foreach ($dataSearch->service_response as $response):
                        $foliono = (string)$response->FOLIONO;
                        $auto_trxn_no = (string)$response->AUTO_TRXN_NO;
						$instrm_ac_type  = (string)$response->INSTRM_AC_TYPE;
						$amc_code = (string)$response->AMC_CODE;
						$product_code = (string)$response->PRODUCT_CODE;
						$today = (string)$response->TODAY;
						$fromscheme = (string)$response->FROMSCHEME;
						$toscheme = (string)$response->TOSCHEME;
						$targetproduct = (string)$response->TARGETPRODUCT;
						$frequency = (string)$response->FREQUENCY;
						$instdate = (string)$response->INSTDATE;
						$instrm_date = (string)$response->INSTRM_DATE;
						$amount = (float)$response->AMOUNT;
						$unit = (float)$response->UNIT;
						$achamount = (float)$response->ACHAMOUNT;
						$topup = (string)$response->TOPUP;
						$topupamt = (float)$response->TOPUPAMT;
						$topupfrequency = (string)$response->TOPUPFREQUENCY;
						$payment_mechanism = (string)$response->PAYMENT_MECHANISM;
						$until_cancelled = (string)$response->UNTIL_CANCELLED;
						$ifsc_code = (string)$response->IFSC_CODE;
						$mobile_no = (string)$response->MOBILE_NO;
						$email = (string)$response->EMAIL;
						$debit_amount_type = (string)$response->DEBIT_AMOUNT_TYPE;
						$cheq_micr_no = (string)$response->CHEQ_MICR_NO;
						$umrn = (string)$response->UMRN;
						$debitac = (string)$response->DEBITAC;
						$noofinstall = (int)$response->NOOFINSTALL;
						$debitbank = (string)$response->DEBITBANK;
						$customer_id = (string)$response->CUSTOMER_ID;
						$investor_name = (string)$response->INVESTOR_NAME;
						$iin_activation_flag = (string)$response->IIN_ACTIVATION_FLAG;
						$unique_ref_no = (string)$response->UNIQUE_REF_NO;
						$remarks = (string)$response->REMARKS;
						$ceaseinitiatedby = (string)$response->CEASEINITIATEDBY;
						$auto_trxn_type = (string)$response->AUTO_TRXN_TYPE;
						$sub_broker_arn = (string)$response->SUB_BROKER_ARN;
						$arn_emp_code = (string)$response->ARN_EMP_CODE;
						$fh_pan_no = (string)$response->FH_PAN_NO;
						$guardian = (string)$response->GUARDIAN;
						$hold_nature_desc = (string)$response->HOLD_NATURE_DESC;
						$branch = (string)$response->BRANCH;
						$broker_code = (string)$response->BROKER_CODE;
						$long_name = (string)$response->LONG_NAME;
						$application_no = (string)$response->APPLICATION_NO;
						$fromdate = (string)$response->FROMDATE;
						$todate = (string)$response->TODATE;
						$ach_from_date = (string)$response->ACH_FROM_DATE;
						$ach_end_date = (string)$response->ACH_END_DATE;
						$cease_date = (string)$response->CEASE_DATE;
						$date_of_birth = (string)$response->DATE_OF_BIRTH;
						$cease_status = (string)$response->CEASE_STATUS;
						$sys_trxn_auth_date = (string)$response->SYS_TRXN_AUTH_DATE;
						$sys_cease_auth_date = (string)$response->SYS_CEASE_AUTH_DATE;
						$trx_status = (string)$response->TRXN_STATUS;
						$freedom_sip = (string)$response->FREEDOM_SIP;
						$freedom_sip_tenure = (string)$response->FREEDOM_SIP_TENURE;
						$freedom_swp_amount = (float)$response->FREEDOM_SWP_AMOUNT;
						$freedom_target_scheme = (string)$response->FREEDOM_TARGET_SCHEME;
						$sip_reg_no = (string)$response->SIP_REG_NO;
						$entry_date = (string)$response->ENTRY_DATE;
						$bank_dec_flag = (string)$response->BANK_DEC_FLAG;

						// echo "<tr>
						// <td>".$customer_id."</td>
						// <td>".$investor_name."</td>
						// <td>".$auto_trxn_type."</td>
						// <td>".$debitbank."</td>
						// <td>".$fromscheme."</td>
						// <td>".$amount."</td>
						// <td>".$frequency."</td>
						// <td>".$instdate."</td>
						// <td>".$fromdate."</td>
						// <td>".$todate."</td>
						// </tr>";

                       $instrm_date = date('Y-m-d H:i:s', strtotime($instrm_date));
                       $sys_trxn_auth_date = date('Y-m-d H:i:s', strtotime($sys_trxn_auth_date));
                       $sys_cease_auth_date = date('Y-m-d H:i:s', strtotime($sys_cease_auth_date));
                       $entry_date = date('Y-m-d H:i:s', strtotime($entry_date));
					   
                        $db->saveSipResponseInDb($customer_id, $investor_name, $foliono, $auto_trxn_no, $instrm_ac_type, $amc_code,$product_code, $today, $fromscheme, $toscheme, $targetproduct, $frequency, $instdate,$instrm_date, $amount, $unit, $achamount, $topup, $topupamt, $topupfrequency,$payment_mechanism, $until_cancelled, $ifsc_code, $mobile_no, $email, $debit_amount_type,$cheq_micr_no, $umrn, $debitac, $noofinstall, $debitbank, $iin_activation_flag,$unique_ref_no, $remarks, $ceaseinitiatedby, $auto_trxn_type, $sub_broker_arn,$arn_emp_code, $fh_pan_no, $guardian, $hold_nature_desc, $branch, $broker_code,$long_name, $application_no, $fromdate, $todate, $ach_from_date, $ach_end_date,$cease_date, $date_of_birth, $cease_status, $sys_trxn_auth_date, $sys_cease_auth_date,$trx_status, $freedom_sip, $freedom_sip_tenure, $freedom_swp_amount,$freedom_target_scheme, $sip_reg_no, $entry_date, $bank_dec_flag ); 
                    
                       // echo $statusAllData;

                        $statusAllData[] = array("customer_id"=>$customer_id, "investor_name"=>$investor_name,"folioNumber"=>$foliono,"amcCode"=>$amc_code,"productCode"=>$product_code,"amount" =>$amount);  
                    endforeach;
					// echo "</table>";
                //echo json_encode(array("Id"=>0,"Message"=>"Success","Result"=>$statusAllData,"Success"=>true),JSON_UNESCAPED_SLASHES);
            }else{
				//service responses
				$request_array = [];
				foreach ($dataSearch->service_response as $response):
					if($response->Status_Desc == "No Data Found"){
						echo '<p style="color:red;font-size:20px;">'.$plan." : ".$response->Status_Desc.'</p>';
					}else{
						echo '<p style="color:red;font-size:20px;">'.$response->return_msg.'</p>';
					}
					
					
					$request_array[] = $response->return_msg;
				endforeach;
				// echo $request_array;
			}
        
			/// cease report:
			$url_cease =  "https://www.nsenmf.com/NMFIITrxnService/NMFTrxnService/SYSCEASEREPORT";
			$ch_cease = curl_init($url_cease);
			curl_setopt($ch_cease, CURLOPT_RETURNTRANSFER, 0);
			curl_setopt($ch_cease, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch_cease, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch_cease, CURLOPT_POST, 1);
			curl_setopt($ch_cease, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
			curl_setopt($ch_cease, CURLOPT_POSTFIELDS, $xml_data);
			curl_setopt($ch_cease, CURLOPT_RETURNTRANSFER, 1);

			$output_cease = curl_exec($ch_cease);
			file_put_contents('debugg/cease_'.$plan.'_report_request.xml',$xml_data);
			file_put_contents('debugg/cease_'.$plan.'_report_response_data.xml', $output_cease);
			$data_cease = new SimpleXMLElement($output_cease);
			$dataSearch_cease = $data_cease->children('urn:schemas-microsoft-com:xml-diffgram-v1')->children()->NMFIISERVICES;
			// service status
			foreach ($dataSearch_cease->service_status as $status):
				$service_code_cease = $status->service_return_code;   
			endforeach;
			if($service_code_cease == 0){
				foreach ($dataSearch_cease->service_response as $response):
					$foliono = (string)$response->FOLIONO;
					$amount = (float)$response->AMOUNT;
					$unit = (float)$response->UNIT;
					$customer_id = (string)$response->CUSTOMER_ID;
					$cease_date = (string)$response->CEASE_DATE;
					$cease_status = "Trxn Ceased";
					$sip_reg_no = (string)$response->SIP_REG_NO;
					$sys_cease_auth_date = (string)$response->SYS_CEASE_AUTH_DATE;
					$sys_cease_auth_date = date('Y-m-d H:i:s', strtotime($sys_cease_auth_date));
					$db->updateSipCeaseInDb($customer_id, $foliono,$amount, $unit, $cease_date, $cease_status,$sip_reg_no,$sys_cease_auth_date ); 
				endforeach;
			}
		}
		$sipDataFromDb = $db->getSipResponseFromDbDatewise($convrtFromDate,$convrtToDate,$checkUserIIN);
		echo "<table style='border:2px;font-size:80%'  >
				<tr>
					<th>Customer_Id</th>
					<th>Customer_name</th>
					<th>Plan Name</th>
					<th>Bank Name</th>
					<th>Scheme</th>
					<th>Amount</th>
					<th>frequency</th>
					<th>Inst date</th>
					<th>From date</th>
					<th>To date</th>
					<th>Cease Status</th>
				</tr>
			";

		foreach ($sipDataFromDb as $row)	{
			echo "<tr>
						<td>".$row['customer_id']."</td>
						<td>".$row['investor_name']."</td>
						<td>".$row['auto_trxn_type']."</td>
						<td>".$row['debitbank']."</td>
						<td>".$row['fromscheme']."</td>
						<td>".$row['amount']."</td>
						<td>".$row['frequency']."</td>
						<td>".$row['instdate']."</td>
						<td>".$row['fromdate']."</td>
						<td>".$row['todate']."</td>
						<td>".$row['cease_status']."</td>
					</tr>";
			//$statusAllData[] = array("foliono" => "$foliono","amccode" => "$amccode","productcode" => "$productcode","sipdate" => "$sipdate","amount" => "$amount","achamount"=>"$achamount","fromdate" => "$fromdate","todate" => "$todate","achfromdate"=>"$achfromdate","achtodate" =>"$achtodate","scheme" => "$scheme","status" => "$status");
		}	
		echo "</table>";
	}  // end of sipreportsNse
	
	
		if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'sipreports'){
		
		$fromDate = $_REQUEST['fromDate'];
		$toDate = $_REQUEST['toDate'];
		$checkUserIIN = $_REQUEST['custIIN'];

		//$convrtFromDate = date('Y-m-d', strtotime($fromDate));
		
	   // $convrtToDate = date('Y-m-d', strtotime($toDate));
		

		$results = $db->getSipDetails($fromDate, $toDate, $checkUserIIN);

		if (!empty($results)) {
			//print_r($results);
			echo '<table style="font-size: 12px;">';

			// Display table headers
			echo '<tr>';
			echo '<th>Sl No</th>';
			echo '<th>IIN</th>';
			echo '<th>PAN</th>';
			echo '<th>Name</th>';
			echo '<th>Product</th>';
			echo '<th>Scheme</th>';
			echo '<th>Folio Number</th>';
			echo '<th>Amount</th>';
			echo '<th>From Date</th>';
			echo '<th>To Date</th>';
			echo '<th>Period Day</th>';
			echo '<th>Bank</th>';
			echo '<th>Scheme Code</th>';
			echo '<th>request_ref_no</th>';
			echo '</tr>';

			// Display table rows
			foreach ($results as $row) {
				echo '<tr>';
				echo '<td>' . $row['slno'] . '</td>';
				echo '<td>' . $row['iin'] . '</td>';
				echo '<td>' . $row['pan_no'] . '</td>';
				echo '<td>' . $row['inv_name'] . '</td>';
				echo '<td>' . $row['product'] . '</td>';
				echo '<td>' . $row['scheme'] . '</td>';
				echo '<td>' . $row['folio_no'] . '</td>';
				echo '<td>' . $row['auto_amount'] . '</td>';
				echo '<td>' . $row['from_date'] . '</td>';
				echo '<td>' . $row['to_date'] . '</td>';
				echo '<td>' . $row['period_day'] . '</td>';
				echo '<td>' . $row['bank'] . '</td>';
				echo '<td>' . $row['scheme_code'] . '</td>';
				echo '<td>' . $row['request_ref_no'] . '</td>';
				echo '</tr>';
			}

			echo '</table>';
		} else {
			echo 'No results found.';
		}



		// echo $writeExcel;
	
		/*{
		//service responses
			$request_array = [];
            foreach ($dataSearch->service_response as $response):
            	if($response->Status_Desc == "No Data Found"){
            		echo '<p style="color:red;font-size:20px;">'.$response->Status_Desc.'</p>';
            	}else{
            		echo '<p style="color:red;font-size:20px;">'.$response->return_msg.'</p>';
            	}
            	
            	
                $request_array[] = $response->return_msg;
            endforeach;
            // echo $request_array;
           
        }	*/
	}
	
	if($uploadMethod!='' && $uploadMethod === 'backupFile'){
	
	
		function createBackup()
		{
			global $db;
			$mysqldumpPath = 'mysqldump';
			if(file_exists('/Applications/XAMPP/xamppfiles/bin/mysqldump')) {
			    $mysqldumpPath = '/Applications/XAMPP/xamppfiles/bin/mysqldump';
			}

			$dbConfig = $db->getDatabaseConfig();
			$hostname = $dbConfig['host'];
			$username = $dbConfig['username'];
			$password = $dbConfig['password'];
			$database = $dbConfig['dbname'];

			// Define the path for the dump file
			$backupDir = "dbbackup/";
			if (!file_exists($backupDir)) {
				mkdir($backupDir, 0777, true); // Create the directory if it doesn't exist
			}
			// Set timezone to Indian Standard Time (IST)
			date_default_timezone_set('Asia/Kolkata');
			// Construct the timestamp in Indian timezone
			$timestamp = date('d_M_Y_g_i_s_a');

			// Define the backup file name with the timestamp
			$fileName = $backupDir . 'bkup_' . $database . '_' . $timestamp . '.sql';

			// Define the path for the dump file
			//$fileName = 'bkup_' . $database . date('_d_M_Y_G_i_s_a') . '.sql';

			// Build the mysqldump command
			$passwordEscaped = escapeshellarg($password);
			$command = "$mysqldumpPath -h $hostname -u $username -p$passwordEscaped $database > $fileName";

			// Execute the command
			exec($command, $output, $returnValue);

			// Check if the command executed successfully
			if ($returnValue === 0) {
				echo "<p class='success'>Database backup created successfully. <a href='$fileName' download>Download Backup</a></p>";
			} else {
				echo "<p>Error creating database backup.</p>";
				print_r($output); // Print any errors or output from the command
			}
		}
		
		
		createBackup();

	}
	
	if($uploadMethod != '' && $uploadMethod === 'restoreFile') {
		// Directory to store uploaded files
		$target_dir = "uploads/";

		// Check if file is uploaded successfully
		if(isset($_FILES["fileToUpload"]["tmp_name"]) && !empty($_FILES["fileToUpload"]["tmp_name"])) {
			$backupFile =  $target_dir . basename($_FILES["fileToUpload"]["name"]);
			
			// Move uploaded file to target directory
			if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $backupFile)) {
				echo "<br>The file <a href=\"$backupFile\" download>".basename($backupFile)."</a> has been uploaded.";

				global $db;
				$sqlQueries = file_get_contents($backupFile);
				$queries = explode(";", $sqlQueries);

				foreach ($queries as $query) {
					$query = trim($query);
					if (!empty($query)) {
						$db->query($query);
					}
				}

				echo "<p class='success'>Database restoration completed.</p>";
			} else {
				echo "Sorry, there was an error uploading your file.";
			}
		} else {
			echo "No file uploaded.";
		}
	}



	/*	if(isset($_REQUEST['method']) && $_REQUEST['method'] === 'restoreFile'){
		//echo $_FILES["fileToUpload"]["tmp_name"];
		function executeQuery($conn, $query)
		{
			return $conn->query($query);
		}
		//echo "in restoredb";
		$target_dir = "uploads/";
		$backupFile =  $target_dir . basename($_FILES["fileToUpload"]["name"]);
		
		if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $backupFile)) {
			$outputStr = "<br>The file <a href=\"$backupFile\" download>".basename($backupFile)."</a> has been uploaded.";
		} else {
			$outputStr = "Sorry, there was an error uploading your file.";
		}
		echo $outputStr;
		    // Connect to MySQL
		$server= $_SERVER["SERVER_ADDR"];
		$localhostIps = array("127.0.0.1","::1");
		if (in_array($server, $localhostIps)) 
		{
			$config = parse_ini_file("../../../Production/aw_config.ini");
			$mysqldumpPath = 'C:\wamp64\bin\mysql\mysql5.7.31\bin\mysqldump.exe';
		}
		else
		{
			$config = parse_ini_file("/home/rrj6671syt4c/Production/config.ini"); 
		}

		$decrypt_key = $config['decrypt_key'];
		$descpass = $db->decrypt($config['DB_PASSWORD'],$decrypt_key);
		$hostname = $config['DB_HOST'];
		$username = $config['DB_USER'];
		$password = $descpass;
		$database = $config['DB_NAME'];
		$db = new MYSQLDB();

		// Check connection
		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}

		// Read SQL dump file
		$sqlQueries = file_get_contents($backupFile);

		// Separate queries
		$queries = explode(";", $sqlQueries);

		// Execute each query
		foreach ($queries as $query) {
			$query = trim($query);

			if (!empty($query)) {
				executeQuery($conn, $query);
			}
		}

		// Close MySQL connection
		$conn->close();

		echo "<p class='success'>Database restoration completed.</p>";
	}*/
	echo $outputStr;
?>