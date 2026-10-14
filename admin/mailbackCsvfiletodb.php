<?php
require_once('auth_guard.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);
//set_time_limit(500);

// Check if a file was uploaded
if (isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileType = $_REQUEST['fileType'];

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
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type.']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No file was uploaded.']);
}
function processWbr9($excelFile) {
    require_once('database.php');
    $db = new MySQLDB();
    $inputFileName = $excelFile['tmp_name'];

    $lookupTable = ['foliochk' => 'folio_num','inv_name' => 'inv_name','address1' => 'address1','address2' => 'address2','address3' => 'address3','city' => 'city','pincode' => 'pincode','product' => 'product','sch_name' => 'sch_name','rep_date' => 'rep_date','clos_bal' => 'clos_bal','rupee_bal' => 'rupee_bal','jnt_name1' => 'jnt_name1','jnt_name2' => 'jnt_name2','phone_off' => 'phone_off','phone_res' => 'phone_res','email' => 'email','holding_nature' => 'holding_nature','uin_no' => 'uin_no','pan_no' => 'usr_pan_no','joint1_pan' => 'joint1_pan','joint2_pan' => 'joint2_pan','guard_pan' => 'guard_pan','tax_status' => 'tax_status','broker_code' => 'broker_code','subbroker' => 'subbroker','reinv_flag' => 'reinv_flag','bank_name' => 'bank_name','branch' => 'branch','ac_type' => 'ac_type','ac_no' => 'ac_no','b_address1' => 'b_address1','b_address2' => 'b_address2','b_address3' => 'b_address3','b_city' => 'b_city','b_pincode' => 'b_pincode','inv_dob' => 'inv_dob','mobile_no' => 'mobile_no','occupation' => 'occupation','inv_iin' => 'inv_iin','nom_name' => 'nom_name','relation' => 'relation','nom_addr1' => 'nom_addr1','nom_addr2' => 'nom_addr2','nom_addr3' => 'nom_addr3','nom_city' => 'nom_city','nom_state' => 'nom_state','nom_pincode' => 'nom_pincode','nom_ph_off' => 'nom_ph_off','nom_ph_res' => 'nom_ph_res','nom_email' => 'nom_email','nom_percentage' => 'nom_percentage','nom2_name' => 'nom2_name','nom2_relation' => 'nom2_relation','nom2_addr1' => 'nom2_addr1','nom2_addr2' => 'nom2_addr2','nom2_addr3' => 'nom2_addr3','nom2_city' => 'nom2_city','nom2_state' => 'nom2_state','nom2_pincode' => 'nom2_pincode','nom2_ph_off' => 'nom2_ph_off','nom2_ph_res' => 'nom2_ph_res','nom2_email' => 'nom2_email','nom2_percentage' => 'nom2_percentage','nom3_name' => 'nom3_name','nom3_relation' => 'nom3_relation','nom3_addr1' => 'nom3_addr1','nom3_addr2' => 'nom3_addr2','nom3_addr3' => 'nom3_addr3','nom3_city' => 'nom3_city','nom3_state' => 'nom3_state','nom3_pincode' => 'nom3_pincode','nom3_ph_off' => 'nom3_ph_off','nom3_ph_res' => 'nom3_ph_res','nom3_email' => 'nom3_email','nom3_percentage' => 'nom3_percentage','ifsc_code' => 'ifsc_code','dp_id' => 'dp_id','demat' => 'demat','guard_name' => 'guard_name','brokcode' => 'brokcode','folio_date' => 'folio_date','aadhaar' => 'aadhaar','tpa_linked' => 'tpa_linked','fh_ckyc_no' => 'fh_ckyc_no','jh1_ckyc' => 'jh1_ckyc','jh2_ckyc' => 'jh2_ckyc','g_ckyc_no' => 'g_ckyc_no','jh1_dob' => 'jh1_dob','jh2_dob' => 'jh2_dob','guardian_dob' => 'guardian_dob','amc_code' => 'amc_code','gst_state_code' => 'gst_state_code','folio_old' => 'folio_old','scheme_folio_number' => 'scheme_folio_number'];

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
    $headers = array_map('strtolower', array_map('trim', explode("\t", str_replace("'", '', $headerLine))));

    // Validate headers
    $missingHeaders = array_diff($expectedHeaders, $headers);
    if (!empty($missingHeaders)) {
        echo json_encode([
            'status' => 'Error',
            'message' => 'The file is invalid. Missing headers: ' . implode(', ', $missingHeaders)
        ]);
        return;
    }

    $dbtable = "tb_folio_wbr9";
    $uniqueFields = ['folio_num'];
    $dateFields = ['rep_date', 'inv_dob', 'folio_date', 'jh1_dob', 'jh2_dob', 'guardian_dob'];
    $decimalFields = ['clos_bal', 'rupee_bal'];

    // Process the file
    $logMsg = ExportTxt2DB($db, $dbtable, $inputFileName, $lookupTable, $uniqueFields, $dateFields, $decimalFields);

    // Check the result and return the status
    if (strpos($logMsg, 'Fail') !== false) {
        $status = 'Error';
    } else {
        $status = 'Success';
    }

    echo json_encode(['status' => $status, 'message' => $logMsg]);
}

function processWbr49($excelFile) {
    require_once('database.php');
    $db = new MYSQLDB();
    $inputFileName = $excelFile['tmp_name'];

    // Predefined lookup table for mapping headers to database columns
    $lookupTable = [
        'product' => 'product',
        'scheme' => 'scheme',
        'folio_no' => 'folio_no',
        'inv_name' => 'inv_name',
        'aut_trntyp' => 'aut_trntyp',
        'auto_trno' => 'auto_trno',
        'auto_amount' => 'auto_amount',
        'from_date' => 'from_date',
        'to_date' => 'to_date',
        'cease_date' => 'cease_date',
        'periodicity' => 'periodicity',
        'period_day' => 'period_day',
        'inv_iin' => 'inv_iin',
        'payment_mode' => 'payment_mode',
        'target_scheme' => 'target_scheme',
        'reg_date' => 'reg_date',
        'subbroker' => 'subbroker',
        'remarks' => 'remarks',
        'top_up_frq' => 'top_up_frq',
        'top_up_amt' => 'top_up_amt',
        'ac_type' => 'ac_type',
        'bank' => 'bank',
        'branch' => 'branch',
        'instrm_no' => 'instrm_no',
        'cheq_micr_no' => 'cheq_micr_no',
        'ac_holder_name' => 'ac_holder_name',
        'pan' => 'usr_pan_no',
        'top_up_perc' => 'top_up_perc',
        'euin' => 'euin',
        'sub_arn_code' => 'sub_arn_code',
        'ter_location' => 'ter_location',
        'scheme_code' => 'scheme_code',
        'target_scheme_code' => 'target_scheme_code',
        'amc_code' => 'amc_code',
        'user_code' => 'user_code',
        'package_name' => 'package_name',
        'special_product' => 'special_product',
        'subtrxndesc' => 'subtrxndesc',
        'pause_from_date' => 'pause_from_date',
        'pause_to_date' => 'pause_to_date',
        'folio_old' => 'folio_old',
        'ft_sip_regno' => 'ft_sip_regno',
        'scheme_folio_number' => 'scheme_folio_number',
        'request_ref_no' => 'request_ref_no'
    ];

    $expectedHeaders = array_keys($lookupTable);

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
    $headers = array_map('strtolower', array_map('trim', explode("\t", str_replace("'", '', $headerLine))));

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
    $dbtable = "cams_wbr49_sip";
    $uniqueFields = ['product', 'folio_no', 'auto_trno'];
    $dateFields = ['from_date', 'to_date', 'cease_date', 'reg_date', 'pause_from_date', 'pause_to_date'];
    $decimalFields = [];

    // Process the file
    $logMsg = ExportTxt2DB($db, $dbtable, $inputFileName, $lookupTable, $uniqueFields, $dateFields, $decimalFields);

    // Log the result
    //updateLog($db, $logMsg);

    // Check the result and return the status
    if (strpos($logMsg, 'Fail') !== false) {
        $status = 'Error';
    } else {
        $status = 'Success';
    }

    echo json_encode(['status' => $status, 'message' => $logMsg]);
}


function processWbr10($excelFile) {
    require_once('database.php');
    $db = new MYSQLDB();
    $inputFileName = $excelFile['tmp_name'];

    // Predefined lookup table for mapping headers to database columns
    $lookupTable = [
        'amc_code' => 'fund',
        'folio_chk' => 'folio_num',
        'sch_code' => 'scheme_code',
        'trxn_sign' => 'trxn_sign',
        'trxnno' => 'trxn_num',
        'trxntype' => 'trxn_flag',
        'inv_name' => 'investor_name',
        'trxnmode' => 'trxn_mode',
        'trxnstat' => 'trxn_status',
        'traddate' => 'trxn_date',
        'postdate' => 'trxn_process_date',
        'purprice' => 'trxn_nav',
        'units' => 'trxn_units',
        'amount' => 'trxn_amount',
        'brokcode' => 'agent_code',
        'rep_date' => 'report_date',
        'trxnsubtyp' => 'trxn_sub',
        'stt' => 'stt',
        'tax_status' => 'status',
        'euin' => 'euin',
        'sub_brk_arn' => 'sub_broker_arn_code'
    ];

    $expectedHeaders = array_keys($lookupTable);

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
    $headers = array_map('strtolower', array_map('trim', explode("\t", str_replace("'", '', $headerLine))));

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
    $uniqueFields = ['folio_num', 'trxn_num'];
    $dateFields = ['report_date', 'trxn_date', 'trxn_process_date'];
    $decimalFields = ['purprice', 'units', 'amount', 'stt'];
    $substituteField = 'trxn_sign';
    $substituteValue = ['+' => 'P', '-' => 'R'];
    $rta = "CAMS";

    // Process the file
    $logMsg = ExportTxt2DB_wbr10(
        $db,
        $dbtable,
        $inputFileName,
        $lookupTable,
        $substituteField,
        $substituteValue,
        $rta,
        $uniqueFields,
        $dateFields,
        $decimalFields
    );

    // Log the result
    // updateLog($db, $logMsg);

    // Check the result and return the status
    if (strpos($logMsg, 'Fail') !== false) {
        $status = 'Error';
    } else {
        $status = 'Success';
    }

    echo json_encode(['status' => $status, 'message' => $logMsg]);
}

function ExportTxt2DB($db, $dbtable, $inputFileName, $lookupTable, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
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
            return strtolower(trim($item, "'\"")); // Remove single quotes and trim
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
                //$logMessages[] = "Unrecognized column in TXT header: '$col'";
            }
        }

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) { // Tab-separated values
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\""); // Remove single quotes and handle missing values
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
                    //$logMessages[] = "Skipped: Record with unique key '$uniqueKey' already exists.";
                    continue;  // Skip this iteration and move to the next row
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
                //$logMessages[] = "Success: Inserted record.";
            } else {
                $failCount++;
                //$logMessages[] = "Fail: Could not insert record. Error: " . $db->error;
            }
        }

        fclose($handle);

        // Add summary counts to the log messages
        //$logMessages[] = "Total unrecognized columns: $unrecognizedColumns";
        $logMessages[] = "Total skipped records as duplicates: $skippedCount";
        $logMessages[] = "Total successfully inserted records: $successCount";
        $logMessages[] = "Total failed insertions: $failCount";

        return implode("<br>", $logMessages);
    } else {
        return 'Fail: Unable to open the input file.';
    }
}

function ExportTxt2DB_old2($db, $dbtable, $inputFileName, $lookupTable, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
    if (($handle = fopen($inputFileName, "r")) !== FALSE) {
        // Read and normalize the header row
        $header = fgetcsv($handle, 0, "\t"); // Tab-separated values
        if (!$header) {
            return 'Fail: Unable to read the header row from the file.';
        }

        // Normalize headers and lookup table (lowercase and trim)
        $header = array_map(function($item) {
            return strtolower(trim($item, "'\"")); // Remove single quotes and trim
        }, $header);
        $lookupTable = array_change_key_case($lookupTable, CASE_LOWER);

        // Map TXT headers to database columns
        $columns = array_map(function($col) use ($lookupTable) {
            return $lookupTable[$col] ?? null;
        }, $header);

        // Log unmatched columns but skip them
        foreach ($header as $col) {
            if (!isset($lookupTable[$col])) {
                echo "Unrecognized column in TXT header: '$col'\n";
            }
        }

        $logMessages = [];

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) { // Tab-separated values
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\""); // Remove single quotes and handle missing values
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
                    // Record exists, skip insert and log message
                    $logMessages[] = "Skipped: Record with unique key '$uniqueKey' already exists.";
                    continue;  // Skip this iteration and move to the next row
                }
            }

            // Iterate through row values and prepare the insert query
            foreach ($row as $col => $value) {
                $insertFields[] = $col;

                // Handle date fields
                if (in_array($col, $dateFields)) {
                    // Check if the date value is not empty
                    if (empty($value)) {
                        // Set to NULL if the date is empty
                        $insertValues[] = 'NULL';
                    } else {
                        // Format the date if it's provided
                        $date = DateTime::createFromFormat('m/d/Y h:i:s A', $value);
                        $dateValue = $date ? $date->format('Y-m-d') : null;

                        // If the date is valid, add it to insertValues, otherwise set to NULL
                        $insertValues[] = $dateValue ? "'" . $dateValue . "'" : 'NULL';
                    }
                } 
                // Handle decimal fields
                elseif (in_array($col, $decimalFields)) {
                    // Format numeric values (e.g., DECIMAL(18, 2))
                    $rsValues = is_numeric($value) ? number_format($value, 2, '.', '') : null;
                    $insertValues[] = $rsValues !== null ? "'" . $rsValues . "'" : 'NULL';
                } 
                // For other fields
                else {
                    $insertValues[] = "'" . addslashes($value) . "'";
                }
            }

            if ($db->insertDynamicRow($dbtable, $insertFields, $insertValues)) {
                $logMessages[] = "Success: Inserted record.";
            } else {
                $logMessages[] = "Fail: Could not insert record. Error: " . $db->error;
            }
        }

        fclose($handle);
        return implode("\n", $logMessages);
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
            return strtolower(trim($item, "'\"")); // Remove single quotes and trim
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
                //$logMessages[] = "Unrecognized column in TXT header: '$col'";
            }
        }

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) { // Tab-separated values
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\""); // Remove single quotes and handle missing values
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
                    //$logMessages[] = "Skipped: Record with unique key '$uniqueKey' already exists.";
                    continue;  // Skip this iteration and move to the next row
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
                //$logMessages[] = "Success: Inserted record.";
            } else {
                $failCount++;
                //$logMessages[] = "Fail: Could not insert record. Error: " . $db->error;
            }
        }

        fclose($handle);

        // Add summary counts to the log messages
       // $logMessages[] = "Total unrecognized columns: $unrecognizedColumns";
        $logMessages[] = "Total skipped records as duplicates: $skippedCount";
        $logMessages[] = "Total successfully inserted records: $successCount";
        $logMessages[] = "Total failed insertions: $failCount";

        return implode("<br>", $logMessages);
    } else {
        return 'Fail: Unable to open the input file.';
    }
}

function ExportTxt2DB_wbr10_old($db, $dbtable, $inputFileName, $lookupTable, $substituteField, $substituteValue, $rta, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
    if (($handle = fopen($inputFileName, "r")) !== FALSE) {
        // Read and normalize the header row
        $header = fgetcsv($handle, 0, "\t"); // Tab-separated values
        if (!$header) {
            return 'Fail: Unable to read the header row from the file.';
        }

        // Normalize headers and lookup table (lowercase and trim)
        $header = array_map(function($item) {
            return strtolower(trim($item, "'\"")); // Remove single quotes and trim
        }, $header);
        $lookupTable = array_change_key_case($lookupTable, CASE_LOWER);

        // Map TXT headers to database columns
        $columns = array_map(function($col) use ($lookupTable) {
            return $lookupTable[$col] ?? null;
        }, $header);

        // Log unmatched columns but skip them
        foreach ($header as $col) {
            if (!isset($lookupTable[$col])) {
                echo "Unrecognized column in TXT header: '$col'\n";
            }
        }

        $logMessages = [];

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) { // Tab-separated values
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\""); // Remove single quotes and handle missing values
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
                    // Record exists, skip insert and log message
                    $logMessages[] = "Skipped: Record with unique key '$uniqueKey' already exists.";
                    continue;  // Skip this iteration and move to the next row
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
                $logMessages[] = "Success: Inserted record.";
            } else {
                $logMessages[] = "Fail: Could not insert record. Error: " . $db->error;
            }
        }

        fclose($handle);
        return implode("\n", $logMessages);
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
            return strtolower(trim($item, "'\"")); // Remove single quotes and trim
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
                    $value = trim($data[$index] ?? '', "'\""); // Remove single quotes and handle missing values
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
$deletedCount = 0;
if ($uniqueKey) {
    if ($db->countDynamicRows($dbtable, $uniqueFields, $row) > 0) {
        // Construct the DELETE query
        $db->deleteDynamicRows($dbtable, $uniqueFields, $row);
        $deletedCount++; // Increment deleted counter if needed
    }
}


            // Check if the record already exists in the database
         /*   if ($uniqueKey) {
                if ($db->countDynamicRows($dbtable, $uniqueFields, $row) > 0) {
                    $skippedCount++; // Increment skipped counter
                    continue;  // Skip this iteration and move to the next row
                }
            }*/

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
                       // $date = DateTime::createFromFormat('m/d/Y h:i:s A', $value);
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
                $successCount++; // Increment success counter
            } else {
                $failedInsertCount++; // Increment failed insert counter
            }
        }

        fclose($handle);

        // Add final log messages
       /* if ($unrecognizedColumnCount > 0) {
            $logMessages[] = "Total unrecognized columns in TXT header: $unrecognizedColumnCount";
        }*/
        $logMessages[] = "Total successful inserts: $successCount";
        $logMessages[] = "Total skipped records as duplicates: $skippedCount";
        $logMessages[] = "Total failed inserts: $failedInsertCount";

        return implode("<br>", $logMessages);
    } else {
        return 'Fail: Unable to open the input file.';
    }
}

function ExportTxt2DB_201_old($db, $dbtable, $inputFileName, $lookupTable, $substituteField, $substituteValue, $rta, $uniqueFields = [], $dateFields = [], $decimalFields = []) {
    if (($handle = fopen($inputFileName, "r")) !== FALSE) {
        // Read and normalize the header row
        $header = fgetcsv($handle, 0, "~"); // Tab-separated values
        if (!$header) {
            return 'Fail: Unable to read the header row from the file.';
        }

        // Normalize headers and lookup table (lowercase and trim)
        $header = array_map(function($item) {
            return strtolower(trim($item, "'\"")); // Remove single quotes and trim
        }, $header);
        $lookupTable = array_change_key_case($lookupTable, CASE_LOWER);

        // Map TXT headers to database columns
        $columns = array_map(function($col) use ($lookupTable) {
            return $lookupTable[$col] ?? null;
        }, $header);

        // Log unmatched columns but skip them
        foreach ($header as $col) {
            if (!isset($lookupTable[$col])) {
                echo "Unrecognized column in TXT header: '$col'\n";
            }
        }

        $logMessages = [];

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, "~")) !== FALSE) { // Tab-separated values
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\""); // Remove single quotes and handle missing values
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
                    // Record exists, skip insert and log message
                    $logMessages[] = "Skipped: Record with unique key '$uniqueKey' already exists.";
                    continue;  // Skip this iteration and move to the next row
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
                $logMessages[] = "Success: Inserted record.";
            } else {
                $logMessages[] = "Fail: Could not insert record. Error: " . $db->error;
            }
        }

        fclose($handle);
        return implode("\n", $logMessages);
    } else {
        return 'Fail: Unable to open the input file.';
    }
}


function ExportCsv2DB($db, $dbtable, $inputFileName, $lookupTable) {
    if (($handle = fopen($inputFileName, "r")) !== FALSE) {
        // Read and normalize the header row
        $header = fgetcsv($handle);
        if (!$header) {
            return 'Fail: Unable to read the header row from the file.';
        }

        // Normalize headers and lookup table (lowercase and trim)
        $header = array_map(function($item) {
            return strtolower(trim($item, "'\"")); // Remove unwanted quotes
        }, $header);
        $lookupTable = array_change_key_case($lookupTable, CASE_LOWER);

        // Map CSV headers to database columns
        $columns = array_map(function($col) use ($lookupTable) {
            return $lookupTable[$col] ?? null;
        }, $header);

        // Log unmatched columns but skip them
        foreach ($header as $col) {
            if (!isset($lookupTable[$col])) {
                echo "Unrecognized column in CSV header: '$col'\n";
            }
        }

        $logMessages = [];

        // Read each row and process it
        while (($data = fgetcsv($handle, 0, ',', '"')) !== FALSE) {
            // Combine the row data with columns, skipping unrecognized fields
            $row = [];
            foreach ($header as $index => $col) {
                if (isset($columns[$index]) && $columns[$index]) {
                    $value = trim($data[$index] ?? '', "'\""); // Remove extra quotes and handle missing values
                    $row[$columns[$index]] = $value;
                }
            }

            // Prepare SQL for insert
            $insertFields = [];
            $insertValues = [];

            foreach ($row as $col => $value) {
                $insertFields[] = $col;
                // Escape values to prevent SQL injection
                $insertValues[] = "'" . addslashes($value) . "'";
            }

            if ($db->insertDynamicRow($dbtable, $insertFields, $insertValues)) {
                $logMessages[] = "Success: Inserted record.";
            } else {
                $logMessages[] = "Fail: Could not insert record. Error: " . $db->error;
            }
        }

        fclose($handle);
        return implode("\n", $logMessages);
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
//var_dump($headers);
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





?>