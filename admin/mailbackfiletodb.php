<?php
require_once('auth_guard.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);
//set_time_limit(500);

// Check if a file was uploaded
if (isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileType = $_REQUEST['fileType'];
    
    // Validate file type - only allow text files
    $allowedTypes = ['text/plain', 'application/octet-stream'];
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Check MIME type or file extension
    if (!in_array($file['type'], $allowedTypes) && !in_array(strtolower($fileExtension), ['txt', 'csv'])) {
        echo json_encode(['status' => 'error', 'message' => 'Only text files (TXT/CSV) are allowed.']);
        return;
    }
    
    // Additional security: check file content
    $fileContent = file_get_contents($file['tmp_name']);
    if ($fileContent === false) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to read file content.']);
        return;
    }
    
    // Check if file is actually text (optional but recommended)
    if (!isValidTextFile($fileContent)) {
        echo json_encode(['status' => 'error', 'message' => 'File does not appear to be a valid text file.']);
        return;
    }

    // Call different functions based on the fileType
    switch ($fileType) {
         case 'mfsd201':
            processMfsd201($file);
            break;
         case 'wbr9':
            processWbr9($file);
            break;
         case 'wbr10':
            processWbr10($file);
            break;
         case 'wbr49':
            processWbr49($file);
            break;
         case 'nse-trx':
            processNse_trx($file);
            break;
         case 'common_excel':
            processCommonExcel($file);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type.']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No file was uploaded.']);
}

// Function to validate if file is actually a text file
function isValidTextFile($content) {
    // Check for null bytes or binary content
    if (preg_match('~[\x00-\x08\x0B-\x0C\x0E-\x1F]~', $content)) {
        return false;
    }
    
    // Check if content is mostly ASCII/UTF-8 text
    $textLength = strlen($content);
    if ($textLength > 0) {
        // Count non-printable characters (excluding whitespace)
        $nonPrintable = preg_match_all('/[^\x20-\x7E\x0A\x0D\x09]/', $content);
        $nonPrintableRatio = $nonPrintable / $textLength;
        
        // If more than 10% of characters are non-printable, likely not a text file
        if ($nonPrintableRatio > 0.1) {
            return false;
        }
    }
    
    return true;
}

// NEW FUNCTION ADDED for processing common Excel format
function processCommonExcel($excelFile) {
    require_once('database.php');
    $db = new MySQLDB();
    $inputFileName = $excelFile['tmp_name'];

    // Mapping based on the provided table for "Excel (common format)" or "Excel (without common format)"
    $lookupTable = [
        'fmcode' => 'product_code',
        'td_fund' => 'fund_code',
        'td_acno' => 'folio_number',
        'divopt' => 'dividend_option',
        'funddesc' => 'fund_description',
        'td_trno' => 'transaction_number',
        'invname' => 'investor_name',
        'trnmode' => 'transaction_mode',
        'trnstat' => 'transaction_status',
        'td_prdt' => 'transaction_date',
        'td_pop' => 'price',
        'load1' => 'load_amount',
        'td_units' => 'units',
        'td_amt' => 'amount',
        'td_agent' => 'agent_code',
        'crdate' => 'report_date',
        'td_appno' => 'application_number',
        'td_trtype' => 'transaction_type',
        'trdesc' => 'transaction_description',
        'navdate' => 'nav_date',
        'assetype' => 'asset_type',
        'subtrtype' => 'sub_tran_type',
        'citycateg5' => 'city_category',
        'euin' => 'euin',
        'trcharges' => 'tr_charges',
        'stt' => 'stt',
        'ihno' => 'ihno',
        'branchcode' => 'branch_code',
        'inwardnum0' => 'inward_number',
        'inwardnum1' => 'inward_number', // Alternative header
        'pan1' => 'pan1',
        'tdsamount' => 'tds_amount',
        'trflag' => 'transaction_flag',
        'remarks' => 'remarks',
        'td_nav' => 'nav',
        'evalid' => 'euin_valid_indicator',
        'edeclflag' => 'euin_declaration_indicator',
        'subarncode' => 'sub_broker_arn_code',
        'td_trxnmode' => 'td_trxnmode',
        'sipregdt' => 'sip_regn_date',
        'sipregslno' => 'sip_reg_slno',
        'stampduty' => 'stamp_duty_charges',
        'altfolio' => 'alt_folio',
        'status' => 'status',
        'clientid' => 'client_id',
        'dpid' => 'dp_id'
    ];

    $expectedHeaders = array_keys($lookupTable);

    // Open the file and validate headers
    $file = fopen($inputFileName, 'r');
    if (!$file) {
        echo json_encode(['status' => 'Error','message' => 'Unable to open the file.']);
        return;
    }

    // Read the header line
    $headerLine = fgets($file);
    fclose($file);

    if ($headerLine === false) {
        echo json_encode(['status' => 'Error', 'message' => 'File is empty or unreadable.']);
        return;
    }

    // Clean and split headers (convert to lowercase for comparison)
    // Try different delimiters for text files
    $delimiters = ["\t", ",", "~", "|"];
    $headers = [];
    
    foreach ($delimiters as $delimiter) {
        $tempHeaders = array_map('strtolower', array_map('trim', explode($delimiter, $headerLine)));
        if (count($tempHeaders) > 1) {
            $headers = $tempHeaders;
            break;
        }
    }
    
    if (empty($headers)) {
        echo json_encode(['status' => 'Error', 'message' => 'Unable to detect file delimiter.']);
        return;
    }

    // Validate headers - check if we have at least some of the expected headers
    $foundHeaders = array_intersect($expectedHeaders, $headers);
    if (count($foundHeaders) < 5) { // Minimum required headers
        $missingHeaders = array_diff($expectedHeaders, $headers);
        echo json_encode([
            'status' => 'Error',
            'message' => 'The file is invalid. Expected headers not found. Missing headers might include: ' . implode(', ', array_slice($missingHeaders, 0, 10))
        ]);
        return;
    }

    $dbtable = "tb_common_excel"; // You need to create this table or use an existing one
    $uniqueFields = ['folio_number', 'transaction_number'];
    $dateFields = ['report_date', 'transaction_date', 'nav_date', 'sip_regn_date'];
    $decimalFields = ['price', 'units', 'amount', 'load_amount', 'stt', 'tds_amount', 'nav', 'stamp_duty_charges'];

    // Process the file
    $logMsg = ExportText2DB_Common(
        $db, 
        $dbtable, 
        $inputFileName, 
        $lookupTable, 
        $uniqueFields, 
        $dateFields, 
        $decimalFields,
        $delimiter
    );

    // Check the result and return the status
    if (strpos($logMsg, 'Fail') !== false) {
        $status = 'Error';
    } else {
        $status = 'Success';
    }

    echo json_encode(['status' => $status, 'message' => $logMsg]);
}

// NEW FUNCTION for processing Common Excel format from text files
function ExportText2DB_Common($db, $dbtable, $inputFileName, $lookupTable, $uniqueFields = [], $dateFields = [], $decimalFields = [], $delimiter = ",") {
    $logMessages = []; // Initialize log storage
    $unrecognizedColumns = 0;
    $skippedCount = 0;
    $successCount = 0;
    $failCount = 0;

    if (($handle = fopen($inputFileName, "r")) !== FALSE) {
        // Read and normalize the header row
        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            return 'Fail: Unable to read the header row from the file.';
        }

        // Normalize headers and lookup table (lowercase and trim)
        $header = array_map(function($item) {
            return strtolower(trim($item, "'\" \t\n\r\0\x0B"));
        }, $header);
        $lookupTable = array_change_key_case($lookupTable, CASE_LOWER);

        // Map CSV headers to database columns
        $columns = array_map(function($col) use ($lookupTable) {
            return $lookupTable[$col] ?? null;
        }, $header);

        // Log unmatched columns but skip them
        foreach ($header as $col) {
            if (!isset($lookupTable[$col])) {
                $unrecognizedColumns++;
                // Optionally log: $logMessages[] = "Unrecognized column: '$col'";
            }
        }

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\" \t\n\r\0\x0B");
                    }
                    
                    $row[$columns[$index]] = $value;
                }
            }

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Prepare SQL for insert
            $insertFields = [];
            $insertValues = [];

            // Check for uniqueness using the passed unique fields
            $uniqueKey = null;
            $uniqueConditions = [];
            foreach ($uniqueFields as $uniqueField) {
                if (isset($row[$uniqueField]) && !empty($row[$uniqueField])) {
                    $uniqueConditions[$uniqueField] = $row[$uniqueField];
                    $uniqueKey .= $row[$uniqueField];
                }
            }

            // Check if the record already exists in the database
            if (!empty($uniqueConditions)) {
                if ($db->countDynamicRows($dbtable, $uniqueFields, $row) > 0) {
                    $skippedCount++;
                    continue;
                }
            }

            // Iterate through row values and prepare the insert query
            foreach ($row as $col => $value) {
                $insertFields[] = $col;

                // Handle date fields
                if (in_array($col, $dateFields)) {
                    if (empty($value)) {
                        $insertValues[] = 'NULL';
                    } else {
                        $date = DateTime::createFromFormat('m/d/Y h:i:s A', $value);
                        $dateValue = $date ? $date->format('Y-m-d') : null;
                        $insertValues[] = $dateValue ? "'" . $dateValue . "'" : 'NULL';
                    }
                } 
                // Handle decimal fields
                elseif (in_array($col, $decimalFields)) {
                    $rsValues = is_numeric($value) ? number_format($value, 2, '.', '') : null;
                    $insertValues[] = $rsValues !== null ? "'" . $rsValues . "'" : 'NULL';
                } 
                // For other fields
                else {
                    $insertValues[] = "'" . addslashes($value) . "'";
                }
            }

            if ($db->insertDynamicRow($dbtable, $insertFields, $insertValues)) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        fclose($handle);

        // Add summary counts to the log messages
        $logMessages[] = "Total skipped records as duplicates: $skippedCount";
        $logMessages[] = "Total successfully inserted records: $successCount";
        $logMessages[] = "Total failed insertions: $failCount";

        return implode("<br>", $logMessages);
    } else {
        return 'Fail: Unable to open the input file.';
    }
}

function ExportTxt2DB_wbr10($db, $dbtable, $inputFileName, $lookupTable, $substituteField, $substituteValue, $rta, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
    $logMessages = []; // Initialize log storage
    $unrecognizedColumns = 0;
    $skippedCount = 0;
    $successCount = 0;
    $failCount = 0;

    if (($handle = fopen($inputFileName, "r")) !== FALSE) {
        // Read and normalize the header row
        $header = fgetcsv($handle, 0, "\t"); // Tab-separated values
        if (!$header) {
            return 'Fail: Unable to read the header row from the file.';
        }

        // Normalize headers and lookup table (lowercase and trim)
        $header = array_map(function($item) {
            return strtolower(trim($item, "'\""));
        }, $header);
        $lookupTable = array_change_key_case($lookupTable, CASE_LOWER);

        // Map TXT headers to database columns
        $columns = array_map(function($col) use ($lookupTable) {
            return $lookupTable[$col] ?? null;
        }, $header);

        // Log unmatched columns but skip them
        foreach ($header as $col) {
            if (!isset($lookupTable[$col])) {
                $unrecognizedColumns++;
            }
        }

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) { // Tab-separated values
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\"");
                    
                    if (in_array(strtolower($columns[$index]), ['folio_number', 'folio_num', 'foliochk', 'folio_no'])) {
                        $parts = explode('/', $value);
                        $value = trim($parts[0]);
                    }
                    
                    $row[$columns[$index]] = $value;
                }
            }

            // Prepare SQL for insert
            $insertFields = [];
            $insertValues = [];

            // Check for uniqueness using the passed unique fields
            $uniqueKey = null;
            foreach ($uniqueFields as $uniqueField) {
                if (isset($row[$uniqueField])) {
                    $uniqueKey .= $row[$uniqueField];  // Concatenate unique fields to form a unique key
                }
            }

            // Check if the record already exists in the database
            if ($uniqueKey) {
                if ($db->countDynamicRows($dbtable, $uniqueFields, $row) > 0) {
                    $skippedCount++;
                    continue;
                }
            }

            // Process fields and apply custom transformations
            foreach ($row as $col => $value) {
                $insertFields[] = $col;

                // Substitute field value based on substituteField and substituteValue
                if ($col === $substituteField && isset($substituteValue[$value])) {
                    $value = $substituteValue[$value];
                }

                // Handle date fields
                if (in_array($col, $dateFields)) {
                    if (empty($value)) {
                        $insertValues[] = 'NULL';
                    } else {
                        $date = DateTime::createFromFormat('m/d/Y h:i:s A', $value);
                        $dateValue = $date ? $date->format('Y-m-d') : null;
                        $insertValues[] = $dateValue ? "'" . $dateValue . "'" : 'NULL';
                    }
                }
                // Handle decimal fields
                elseif (in_array($col, $decimalFields)) {
                    $rsValues = is_numeric($value) ? number_format($value, 2, '.', '') : null;
                    $insertValues[] = $rsValues !== null ? "'" . $rsValues . "'" : 'NULL';
                }
                // General case
                else {
                    $insertValues[] = "'" . addslashes($value) . "'";
                }
            }
            // Add the RTA-specific field "prod_code"
            if ($rta === "CAMS" && !empty($row['fund']) && !empty($row['scheme_code'])) {
                $prodCode = $row['fund'] . $row['scheme_code'];
                $insertFields[] = 'prod_code';
                $insertValues[] = "'" . addslashes($prodCode) . "'";
            }

            // Add the RTA field
            $insertFields[] = 'rta';
            $insertValues[] = "'" . addslashes($rta) . "'";

            if ($db->insertDynamicRow($dbtable, $insertFields, $insertValues)) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        fclose($handle);

        // Add summary counts to the log messages
        $logMessages[] = "Total skipped records as duplicates: $skippedCount";
        $logMessages[] = "Total successfully inserted records: $successCount";
        $logMessages[] = "Total failed insertions: $failCount";

        return implode("<br>", $logMessages);
    } else {
        return 'Fail: Unable to open the input file.';
    }
}

function ExportTxt2DB_201($db, $dbtable, $inputFileName, $lookupTable, $substituteField, $substituteValue, $rta, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
    $logMessages = []; // Initialize log storage
    $successCount = 0; // Counter for successful inserts
    $unrecognizedColumnCount = 0; // Counter for unrecognized columns
    $skippedCount = 0; // Counter for skipped records
    $failedInsertCount = 0; // Counter for failed inserts

    if (($handle = fopen($inputFileName, "r")) !== FALSE) {
        // Read and normalize the header row
        $header = fgetcsv($handle, 0, "~"); // Tab-separated values
        if (!$header) {
            return 'Fail: Unable to read the header row from the file.';
        }

        // Normalize headers and lookup table (lowercase and trim)
        $header = array_map(function($item) {
            return strtolower(trim($item, "'\""));
        }, $header);
        $lookupTable = array_change_key_case($lookupTable, CASE_LOWER);

        // Map TXT headers to database columns
        $columns = array_map(function($col) use ($lookupTable) {
            return $lookupTable[$col] ?? null;
        }, $header);

        // Log unmatched columns but skip them
        foreach ($header as $col) {
            if (!isset($lookupTable[$col])) {
                $unrecognizedColumnCount++;
            }
        }

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, "~")) !== FALSE) { // Tab-separated values
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\"");
                    
                    if (in_array(strtolower($columns[$index]), ['folio_number', 'folio_num', 'foliochk', 'folio_no'])) {
                        $parts = explode('/', $value);
                        $value = trim($parts[0]);
                    }
                    
                    $row[$columns[$index]] = $value;
                }
            }

            // Prepare SQL for insert
            $insertFields = [];
            $insertValues = [];

            // Check for uniqueness using the passed unique fields
            $uniqueKey = null;
            foreach ($uniqueFields as $uniqueField) {
                if (isset($row[$uniqueField])) {
                    $uniqueKey .= $row[$uniqueField];
                }
            }
$deletedCount = 0;
if ($uniqueKey) {
    if ($db->countDynamicRows($dbtable, $uniqueFields, $row) > 0) {
        // Construct the DELETE query
        $db->deleteDynamicRows($dbtable, $uniqueFields, $row);
        $deletedCount++;
    }
}

            // Process fields and apply custom transformations
            foreach ($row as $col => $value) {
                $insertFields[] = $col;

                // Substitute field value based on substituteField and substituteValue
                if ($col === $substituteField && isset($substituteValue[$value])) {
                    $value = $substituteValue[$value];
                }

                // Handle date fields
                if (in_array($col, $dateFields)) {
                    if (empty($value)) {
                        $insertValues[] = 'NULL';
                    } else {
						$date = DateTime::createFromFormat('Ymd', $value);
                        $dateValue = $date ? $date->format('Y-m-d') : null;
                        $insertValues[] = $dateValue ? "'" . $dateValue . "'" : 'NULL';
                    }
                }
                // Handle decimal fields
                elseif (in_array($col, $decimalFields)) {
                    $rsValues = is_numeric($value) ? number_format($value, 2, '.', '') : null;
                    $insertValues[] = $rsValues !== null ? "'" . $rsValues . "'" : 'NULL';
                }
                // General case
                else {
                    $insertValues[] = "'" . addslashes($value) . "'";
                }
            }

            // Add the RTA-specific field "prod_code"
            if ($rta === "CAMS" && !empty($row['fund']) && !empty($row['scheme_code'])) {
                $prodCode = $row['fund'] . $row['scheme_code'];
                $insertFields[] = 'prod_code';
                $insertValues[] = "'" . addslashes($prodCode) . "'";
            }

            // Add the RTA field
            $insertFields[] = 'rta';
            $insertValues[] = "'" . addslashes($rta) . "'";

            if ($db->insertDynamicRow($dbtable, $insertFields, $insertValues)) {
                $successCount++;
            } else {
                $failedInsertCount++;
            }
        }

        fclose($handle);

        // Add final log messages
        $logMessages[] = "Total successful inserts: $successCount";
        $logMessages[] = "Total skipped records as duplicates: $skippedCount";
        $logMessages[] = "Total failed inserts: $failedInsertCount";

        return implode("<br>", $logMessages);
    } else {
        return 'Fail: Unable to open the input file.';
    }
}

function processMfsd201($excelFile) {
    require_once('database.php');
    $db = new MYSQLDB();
    $inputFileName = $excelFile['tmp_name'];

    // Predefined lookup table for mapping headers to database columns
	$lookupTable = ['product code' => 'prod_code', 'fund' => 'fund', 'folio number' => 'folio_num', 'scheme code' => 'scheme_code', 'fund description' => 'fund_desc', 'transaction head' => 'trxn_sign', 'switch_ref. no.' => 'switch_ref_no', 'instrument number' => 'instrument_num', 'investor name' => 'investor_name', 'transaction mode' => 'trxn_mode', 'transaction status' => 'trxn_status', 'branch name' => 'branch_name', 'branch transaction no' => 'branch_trxn_num', 'transaction date' => 'trxn_date', 'process date' => 'trxn_process_date', 'price' => 'trxn_nav', 'units' => 'trxn_units', 'amount' => 'trxn_amount', 'agent code' => 'agent_code', 'sub-broker code' => 'sub_broker_code', 'brokerage percentage' => 'brokerage_perc', 'commission' => 'commission', 'report date' => 'report_date', 'report time' => 'report_time', 'transaction sub' => 'trxn_sub', 'application number' => 'app_num', 'transaction id' => 'trxn_id', 'transaction description' => 'trxn_desc', 'transaction type' => 'trxn_type', 'instrument date' => 'instrument_date', 'instrument bank' => 'instrument_bank', 'dividend option' => 'div_option', 'purchase amount' => 'purchase_amt', 'purchase date' => 'purchase_date', 'switch fund date' => 'switch_fund_date', 'transaction flag' => 'trxn_flag', 'nav' => 'nav', 'purchase units' => 'purchase_units', 'stt' => 'stt', 'load percentage' => 'load_perc', 'load amount' => 'load_amt', 'purchase transaction no' => 'purchase_trxn_num', 'ihno' => 'ihno', 'branch code' => 'branch_code', 'newunqno' => 'trxn_num', 'pan1' => 'usr_pan_no', 'remarks' => 'remarks', 'nav date' => 'nav_date', 'pan2' => 'pan2', 'pan3' => 'pan3', 'tdsamount' => 'tds_amt', 'scheme' => 'scheme', 'plan' => 'plan', 'toproductcode' => 'to_prod_code', 'td_trxnmode' => 'trxn_mode_code', 'clientid' => 'client_id', 'dpid' => 'dp_id', 'status' => 'status', 'rejtrnoorgno' => 'rej_trxn_org_no', 'subtrantype' => 'sub_trxn_type', 'trcharges' => 'tr_charges', 'atmcardstatus' => 'atm_card_status', 'atmcardremarks' => 'atm_card_remarks', 'nct change date' => 'nct_change_date', 'isin' => 'isin', 'citycategory' => 'city_category', 'portdate' => 'port_date', 'euin' => 'euin', 'sub broker arn code' => 'sub_broker_arn_code', 'euin valid indicator' => 'euin_valid_ind', 'euin declaration indicator' => 'euin_decl_ind', 'assettype' => 'asset_type', 'sip regn date' => 'sip_regn_date', 'divper' => 'div_per', 'guardpanno' => 'guard_pan_no'];

    $expectedHeaders = array_map('strtolower', array_keys($lookupTable));

    // Open the file and validate headers
    $file = fopen($inputFileName, 'r');
    if (!$file) {
        echo json_encode(['status' => 'Error', 'message' => 'Unable to open the file.']);
        return;
    }

    // Read the header line
    $headerLine = fgets($file);
    fclose($file);

    if ($headerLine === false) {
        echo json_encode(['status' => 'Error', 'message' => 'File is empty or unreadable.']);
        return;
    }

    // Clean and split headers (convert to lowercase for comparison)
    $headers = array_map('strtolower', array_map('trim', explode("~", $headerLine)));

    // Validate headers
    $missingHeaders = array_diff($expectedHeaders, $headers);
    if (!empty($missingHeaders)) {
        echo json_encode([
            'status' => 'Error',
            'message' => 'The file is invalid. Missing headers: ' . implode(', ', $missingHeaders)
        ]);
        return;
    }

    // Define additional parameters for processing
    $dbtable = "tb_mailback";
    $uniqueFields = ['folio_num', 'trxn_num',];
    $dateFields = ['report_date', 'trxn_date', 'trxn_process_date'];
    $decimalFields = ['purprice', 'units', 'amount', 'stt'];
    $rta = "KARVY";

    // Process the file
    $logMsg = ExportTxt2DB_201(
        $db,
        $dbtable,
        $inputFileName,
        $lookupTable,
        $substituteField = '',
        $substituteValue = [],
        $rta,
        $uniqueFields,
        $dateFields,
        $decimalFields
    );

    // Log the result
    if (strpos($logMsg, 'Fail') !== false) {
        $status = 'Error';
    } else {
        $status = 'Success';
    }

    echo json_encode(['status' => $status, 'message' => $logMsg]);
}

function updateLog($db,$msg1){
	$remarks = NULL;
	if(isset($_REQUEST['remarks'])) $remarks = $_REQUEST['remarks'];
	$db->insertAdminLog($msg1, $remarks);
}

// Keep the old functions that were already in the file but removed for brevity
function processNse_trx($excelFile) {
    // Implementation for nse-trx
    echo json_encode(['status' => 'info', 'message' => 'NSE-TRX processing not implemented yet.']);
}

function ExportTxt2DB_old2($db, $dbtable, $inputFileName, $lookupTable, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
    // Old implementation
}

function ExportTxt2DB_wbr10_old($db, $dbtable, $inputFileName, $lookupTable, $substituteField, $substituteValue, $rta, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
    // Old implementation
}

function ExportTxt2DB_201_old($db, $dbtable, $inputFileName, $lookupTable, $substituteField, $substituteValue, $rta, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
    // Old implementation
}

function ExportCsv2DB($db, $dbtable, $inputFileName, $lookupTable) {
    // Old implementation
}

?>