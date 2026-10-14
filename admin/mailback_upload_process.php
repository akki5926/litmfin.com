<?php
require_once('auth_guard.php');

// process_mailback_upload.php
if(!isset($_SESSION)){session_start();}
require_once('database.php');
$db = new MYSQLDB();

/**
 * ========== FIELD MAPPING CONFIGURATION ==========
 * Preserved from your original configuration to ensure database compatibility.
 */
$FIELD_MAPPINGS = [
    'KFINTECH' => [
        'MFSD201' => [
            'amc_code'        => ['AMC_CODE', 'FMCODE', 'Fund'],
            'product_code'    => ['Product Code', 'PRCODE1'],
            'scheme_code'     => ['Scheme Code', 'SCH_CODE'],
            'scheme_name'     => ['Scheme'],
            'fund_desc'       => ['Fund Description', 'FUNDDESC'],
            'plan'            => ['Plan'],
            'div_option'      => ['Dividend Option', 'DIVOPT'],
            'isin'            => ['ISIN'],
            'folio_number'    => ['Folio Number', 'TD_ACNO', 'FOLIO_CHK'],
            'alt_folio'       => ['ALTFOLIO'],
            'common_account_number' => ['Common Account Number', 'CAN'],
            'ft_account_no'   => ['FTACCNO'],
            'investor_name'   => ['Investor Name', 'INVNAME', 'INV_NAME'],
            'pan1'            => ['PAN1'],
            'pan2'            => ['PAN2'],
            'pan3'            => ['PAN3'],
            'investor_state'  => ['InvestorState'],
            'city_category'   => ['CityCategory', 'CITYCATEG5'],
            'transaction_number' => ['Transaction Number', 'TD_TRNO', 'TRXNNO'],
            'application_number' => ['Application Number', 'TD_APPNO'],
            'transaction_id'     => ['Transaction ID'],
            'transaction_type'   => ['Transaction Type', 'TD_TRTYPE', 'TRXNTYPE'],
            'transaction_sub_type'=> ['SubTranType', 'TRXNSUBTYP'],
            'transaction_mode'   => ['Transaction Mode', 'TRNMODE', 'TRXNMODE'],
            'transaction_status' => ['Transaction Status', 'TRNSTAT', 'TRXNSTAT'],
            'transaction_description' => ['Transaction Description', 'TRDESC'],
            'transaction_flag'   => ['Transaction Head', 'TRFLAG'],
            'exchange_org_tr_type'=> ['Exchange OrgTrType'],
            'electronic_tr_flag' => ['Electronic transaction Flag'],
            'transaction_date' => ['Transaction Date', 'TD_PRDT', 'TRADDATE'],
            'process_date'     => ['Process Date', 'POSTDATE'],
            'nav_date'         => ['Nav Date', 'NAVDATE'],
            'purchase_date'    => ['Purchase Date'],
            'sip_reg_date'     => ['SIP Regn Date', 'SIPREGDT'],
            'instrument_date'  => ['Instrument Date'],
            'report_date'      => ['Report Date', 'REP_DATE'],
            'units'           => ['Units', 'TD_UNITS'],
            'amount'          => ['Amount', 'TD_AMT'],
            'nav'             => ['Nav', 'TD_NAV'],
            'price'           => ['Price', 'PURPRICE'],
            'load_amount'     => ['Load Amount', 'LOAD1'],
            'stamp_duty'      => ['Stamp Duty Charges', 'STAMPDUTY'],
            'stt'             => ['STT'],
            'tds_amount'      => ['TDSAmount', 'TDSAMOUNT'],
            'tr_charges'      => ['TrCharges', 'TRCHARGES'],
            'agent_code'      => ['Agent Code', 'TD_AGENT', 'BROKCODE'],
            'broker_code'     => ['Broker Code'],
            'sub_broker_code' => ['Sub-Broker Code', 'SUBBROK'],
            'sub_broker_arn'  => ['Sub Broker ARN Code', 'SUB_BRK_ARN'],
            'brokerage_percentage' => ['Brokerage Percentage', 'BROKPERC'],
            'commission'      => ['Commission', 'BROKCOMM'],
            'euin'            => ['EUIN'],
            'euin_valid_indicator' => ['EUIN Valid Indicator'],
            'euin_declaration_indicator' => ['EUIN Declaration Indicator'],
            'instrument_number' => ['Instrument Number', 'CHQNO'],
            'instrument_bank'   => ['Instrument Bank'],
            'ihno'              => ['IHNO'],
            'branch_code'      => ['Branch Code'],
            'branch_name'      => ['Branch Name'],
            'inward_number'    => ['Inward Number', 'INWARDNUM0'],
            'remarks'          => ['Remarks'],
            'status'           => ['Status'],
            'asset_type'       => ['AssetType'],
            'sub_tran_type'    => ['SUBTRTYPE'],
        ]
    ],
    'CAMS' => [
        'WBR10' => [
            'transaction_number' => ['TRXNNO'],
            'transaction_id'     => ['USRTRXNO', 'TRXNNO'],
            'transaction_date' => ['TRADDATE'],
            'transaction_type' => ['TRXNTYPE'],
            'transaction_mode' => ['TRXNMODE'],
            'transaction_status' => ['TRXNSTAT'],
            'amount' => ['AMOUNT'],
            'units' => ['UNITS'],
            'nav' => ['PURPRICE'],
            'price' => ['Price', 'PURPRICE'],
            'nav_date'         => ['TRADDATE'],
            'folio_number' => ['FOLIO_CHK'],
            'scheme_code' => ['SCH_CODE'],
            'investor_name' => ['INV_NAME'],
            'amc_code' => ['AMC_CODE'],
            'process_date' => ['POSTDATE'],
            'report_date' => ['REP_DATE'],
            'agent_code' => ['BROKCODE'],
            'sub_broker_code' => ['SUBBROK'],
            'brokerage_percentage' => ['BROKPERC'],
            'commission' => ['BROKCOMM'],
                'transaction_sub_type' => ['TRXNSUBTYP'],
                'stt' => ['STT'],
                'status' => ['TAX_STATUS'],
                'transaction_flag' => ['TRXN_SIGN'],
                'sub_broker_arn' => ['SUB_BRK_ARN'],
                'euin' => ['EUIN'],
                'broker_code' => ['BROKER_CODE'],
            'stamp_duty' => ['STAMP_DUTY'],
            'investor_state' => ['GST_STATE_CODE'],
            'seq_no' => ['SEQ_NO']
        ],
        'WBR49' => [
                'sip_reg_no' => ['SIP_REG_NO', 'FT_SIP_REGNO', 'REQUEST_REF_NO'],
                'auto_trno' => ['AUTO_TRNO'],
                'auto_trn_type' => ['AUT_TRNTYP'],
                'amc_code' => ['AMC_CODE'],
            'folio_number' => ['FOLIO_NO'],
                'investor_name' => ['INV_NAME', 'INVESTOR_NAME'],
                'pan' => ['PAN', 'USR_PAN_NO'],
                'scheme_code' => ['PRODUCT', 'SCHEME_CODE'],
                'scheme_name' => ['SCHEME', 'SCHEME_NAME'],
                'target_scheme' => ['TARGET_SCHEME'],
                'sip_amount' => ['AUTO_AMOUNT', 'SIP_AMOUNT'],
                'sip_frequency' => ['PERIODICITY', 'FREQUENCY'],
                'sip_instalment_date' => ['PERIOD_DAY', 'INSTALMENT_DATE'],
                'sip_start_date' => ['FROM_DATE', 'START_DATE'],
                'sip_end_date' => ['TO_DATE', 'END_DATE'],
                'cease_date' => ['CEASE_DATE'],
                'reg_date' => ['REG_DATE'],
            'sip_status' => ['SIP_STATUS'],
                'bank_account_no' => ['AC_NO', 'BANK_ACCOUNT_NO'],
                'bank_name' => ['BANK', 'BANK_NAME'],
                'ifsc_code' => ['IFSC_CODE'],
                'euin' => ['EUIN'],
                'sub_broker_code' => ['SUBBROKER', 'SUB_ARN_CODE'],
                'remarks' => ['REMARKS']
        ],
        'WBR9' => [
            'FOLIOCHK' => ['FOLIOCHK', 'FOLIO_NO', 'FOLIO_CHK'],
            'INV_NAME' => ['INV_NAME', 'INVESTOR_NAME'],
            'ADDRESS1' => ['ADDRESS1'],
            'ADDRESS2' => ['ADDRESS2'],
            'ADDRESS3' => ['ADDRESS3'],
            'CITY' => ['CITY'],
            'PINCODE' => ['PINCODE'],
            'PRODUCT' => ['PRODUCT'],
            'SCH_NAME' => ['SCH_NAME', 'SCHEME_NAME'],
            'REP_DATE' => ['REP_DATE'],
            'CLOS_BAL' => ['CLOS_BAL', 'TOTAL_UNITS'],
            'RUPEE_BAL' => ['RUPEE_BAL', 'CURRENT_VALUE'],
            'JNT_NAME1' => ['JNT_NAME1'],
            'JNT_NAME2' => ['JNT_NAME2'],
            'PHONE_OFF' => ['PHONE_OFF'],
            'PHONE_RES' => ['PHONE_RES'],
            'EMAIL' => ['EMAIL'],
            'HOLDING_NATURE' => ['HOLDING_NATURE'],
            'UIN_NO' => ['UIN_NO'],
            'PAN_NO' => ['PAN_NO', 'USR_PAN_NO'],
            'JOINT1_PAN' => ['JOINT1_PAN'],
            'JOINT2_PAN' => ['JOINT2_PAN'],
            'GUARD_PAN' => ['GUARD_PAN'],
            'TAX_STATUS' => ['TAX_STATUS'],
            'BROKER_CODE' => ['BROKER_CODE'],
            'SUBBROKER' => ['SUBBROKER'],
            'REINV_FLAG' => ['REINV_FLAG'],
            'BANK_NAME' => ['BANK_NAME'],
            'BRANCH' => ['BRANCH'],
            'AC_TYPE' => ['AC_TYPE'],
            'AC_NO' => ['AC_NO'],
            'B_ADDRESS1' => ['B_ADDRESS1'],
            'B_ADDRESS2' => ['B_ADDRESS2'],
            'B_ADDRESS3' => ['B_ADDRESS3'],
            'B_CITY' => ['B_CITY'],
            'B_PINCODE' => ['B_PINCODE'],
            'INV_DOB' => ['INV_DOB'],
            'MOBILE_NO' => ['MOBILE_NO'],
            'OCCUPATION' => ['OCCUPATION'],
            'INV_IIN' => ['INV_IIN'],
            'NOM_NAME' => ['NOM_NAME'],
            'RELATION' => ['RELATION'],
            'NOM_ADDR1' => ['NOM_ADDR1'],
            'NOM_ADDR2' => ['NOM_ADDR2'],
            'NOM_ADDR3' => ['NOM_ADDR3'],
            'NOM_CITY' => ['NOM_CITY'],
            'NOM_STATE' => ['NOM_STATE'],
            'NOM_PINCODE' => ['NOM_PINCODE'],
            'NOM_PH_OFF' => ['NOM_PH_OFF'],
            'NOM_PH_RES' => ['NOM_PH_RES'],
            'NOM_EMAIL' => ['NOM_EMAIL'],
            'NOM_PERCENTAGE' => ['NOM_PERCENTAGE'],
            'NOM2_NAME' => ['NOM2_NAME'],
            'NOM2_RELATION' => ['NOM2_RELATION'],
            'NOM2_ADDR1' => ['NOM2_ADDR1'],
            'NOM2_ADDR2' => ['NOM2_ADDR2'],
            'NOM2_ADDR3' => ['NOM2_ADDR3'],
            'NOM2_CITY' => ['NOM2_CITY'],
            'NOM2_STATE' => ['NOM2_STATE'],
            'NOM2_PINCODE' => ['NOM2_PINCODE'],
            'NOM2_PH_OFF' => ['NOM2_PH_OFF'],
            'NOM2_PH_RES' => ['NOM2_PH_RES'],
            'NOM2_EMAIL' => ['NOM2_EMAIL'],
            'NOM2_PERCENTAGE' => ['NOM2_PERCENTAGE'],
            'NOM3_NAME' => ['NOM3_NAME'],
            'NOM3_RELATION' => ['NOM3_RELATION'],
            'NOM3_ADDR1' => ['NOM3_ADDR1'],
            'NOM3_ADDR2' => ['NOM3_ADDR2'],
            'NOM3_ADDR3' => ['NOM3_ADDR3'],
            'NOM3_CITY' => ['NOM3_CITY'],
            'NOM3_STATE' => ['NOM3_STATE'],
            'NOM3_PINCODE' => ['NOM3_PINCODE'],
            'NOM3_PH_OFF' => ['NOM3_PH_OFF'],
            'NOM3_PH_RES' => ['NOM3_PH_RES'],
            'NOM3_EMAIL' => ['NOM3_EMAIL'],
            'NOM3_PERCENTAGE' => ['NOM3_PERCENTAGE'],
            'IFSC_CODE' => ['IFSC_CODE'],
            'DP_ID' => ['DP_ID'],
            'DEMAT' => ['DEMAT'],
            'GUARD_NAME' => ['GUARD_NAME'],
            'BROKCODE' => ['BROKCODE'],
            'FOLIO_DATE' => ['FOLIO_DATE'],
            'AADHAAR' => ['AADHAAR'],
            'TPA_LINKED' => ['TPA_LINKED'],
            'FH_CKYC_NO' => ['FH_CKYC_NO'],
            'JH1_CKYC' => ['JH1_CKYC'],
            'JH2_CKYC' => ['JH2_CKYC'],
            'G_CKYC_NO' => ['G_CKYC_NO'],
            'JH1_DOB' => ['JH1_DOB'],
            'JH2_DOB' => ['JH2_DOB'],
            'GUARDIAN_DOB' => ['GUARDIAN_DOB'],
            'AMC_CODE' => ['AMC_CODE'],
            'GST_STATE_CODE' => ['GST_STATE_CODE'],
            'FOLIO_OLD' => ['FOLIO_OLD'],
            'SCHEME_FOLIO_NUMBER' => ['SCHEME_FOLIO_NUMBER'],
            'COUNTRY' => ['COUNTRY'],
            'REMARKS' => ['REMARKS'],
            'JH1_EMAIL' => ['JH1_EMAIL'],
            'JH2_EMAIL' => ['JH2_EMAIL'],
            'JH1_MOBILE_NO' => ['JH1_MOBILE_NO'],
            'JH2_MOBILE_NO' => ['JH2_MOBILE_NO']
        ]
    ]
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rta = $_POST['rta'] ?? '';
    $document_type = $_POST['document_type'] ?? '';
    
    // Validation
    if (empty($rta) || empty($document_type)) {
        echo "<strong>Error:</strong> RTA and Document Type are required.";
        exit;
    }
    
    if (!isset($_FILES['mailback_file']) || $_FILES['mailback_file']['error'] != UPLOAD_ERR_OK) {
        echo "<strong>Error:</strong> File upload failed.";
        exit;
    }
    
    $upload_dir = 'uploads/mailback/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file = $_FILES['mailback_file'];
    $filename = date('Ymd_His') . '_' . basename($file['name']);
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $result = processMailbackFile($db, $target_path, $rta, $document_type, $file['name']);
        
        if (isset($result['error_msg'])) {
            echo "<div class='alert alert-danger text-start'><strong>Error:</strong><br><pre style='white-space: pre-wrap; word-wrap: break-word; font-size: 11px;'>" . htmlspecialchars($result['error_msg']) . "</pre></div>";
            exit;
        }
        
        // Save to Logs via Database Method
        $db->saveUploadLog($rta, $document_type, $file['name'], $result['records_inserted'] + $result['records_updated'], $result['skipped']);
        
        $skip_details = [];
        if ($result['skipped_duplicate'] > 0) $skip_details[] = $result['skipped_duplicate'] . " already exist";
        if ($result['skipped_invalid'] > 0) $skip_details[] = $result['skipped_invalid'] . " invalid/missing folio";
        if ($result['skipped_db_error'] > 0) $skip_details[] = $result['skipped_db_error'] . " database errors";
        
        $skip_msg = $result['skipped'] > 0 ? ", " . $result['skipped'] . " skipped (" . implode(', ', $skip_details) . ")." : ".";
        $update_msg = $result['records_updated'] > 0 ? " and " . $result['records_updated'] . " updated" : "";
        
        echo "<strong>Success!</strong> " . $result['records_inserted'] . " records inserted" . $update_msg . $skip_msg;

        // Sync data after processing
        $sync_logs = $db->syncMailbackData();
        //error_log("Mailback Sync Logs for " . ($file['name'] ?? 'Unknown File') . ": " . implode(" | ", $sync_logs));
        echo "<br/><small>Sync process completed.</small>";
    } else {
        echo "<strong>Error:</strong> Failed to save file.";
    }
    exit;
}

function processMailbackFile($db, $file_path, $rta, $document_type, $original_name) {
    global $FIELD_MAPPINGS;
    $log = ['success' => false, 'records_inserted' => 0, 'records_updated' => 0, 'skipped' => 0, 'skipped_duplicate' => 0, 'skipped_invalid' => 0, 'skipped_db_error' => 0];
    
    try {
        $conn = $db->getConnection();

        $content = trim(file_get_contents($file_path));
        
        // Convert file encoding to UTF-8 to safely insert into utf8mb4 tables
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        $content = str_replace("\xEF\xBF\xBD", "", $content); // remove unicode replacement chars
        
        // Normalize line endings to \n to support older Mac (\r) and Windows (\r\n) files
        $content = preg_replace("/\r\n|\r/", "\n", $content);
        $lines = explode("\n", $content);
        
        $delimiter = detectDelimiter($lines[0]);
        $header = parseHeader($lines[0], $delimiter);
        $field_mapping = getFieldMapping($rta, $document_type, $header);
        
        if ($document_type === 'MFSD201') {
            if (!isset($field_mapping['transaction_id'])) {
                $log['success'] = false;
                $log['error_msg'] = "The file is invalid. Missing required column: Transaction ID. Please ensure the file is 'Without Split, no Common Format'.";
                return $log;
            }
        }
        
        $start_row = isHeaderRow($header) ? 1 : 0;
        
        for ($i = $start_row; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            
            $record = parseDataRow($line, $delimiter, $header, $field_mapping, $rta, $document_type);
            
            if ($record && (!empty($record['folio_number']) || !empty($record['FOLIOCHK']))) {
                
                $is_update = false;
                
                $dupCheck = $db->handleMailbackDuplicates($document_type, $record);
                if ($dupCheck['skipped']) {
                    $log['skipped']++;
                    $log['skipped_duplicate']++;
                    continue;
                }
                if ($dupCheck['is_update']) {
                    $is_update = true;
                }

                $record['rta'] = $rta;
                $record['document_type'] = $document_type;
                $record['uploaded_by'] = $_SESSION['userid'] ?? 0;
                $record['original_filename'] = $original_name;
                
                // Call central DB method
                if ($db->insertMailbackRecord($document_type, $record)) {
                    if ($is_update) {
                        $log['records_updated']++;
                    } else {
                        $log['records_inserted']++;
                    }
                } else {
                    $log['skipped']++;
                    $log['skipped_db_error']++;
                }
            } else {
                $log['skipped']++;
                $log['skipped_invalid']++;
            }
        }
        $log['success'] = true;
    } catch (Throwable $e) { 
        $log['success'] = false;
        $log['error_msg'] = "Processing halted due to exception on line " . ($i ?? 'unknown') . ": " . $e->getMessage();
        if (isset($record)) {
            $log['error_msg'] .= "\n\nRecord Data Attempted:\n" . print_r($record, true);
        }
    }
    return $log;
}

function detectDelimiter($line) {
    $delimiters = ["\t", "~", ",", "|"];
    $max_count = 0; $best_delimiter = "\t";
    foreach ($delimiters as $d) {
        $count = substr_count($line, $d);
        if ($count > $max_count) { $max_count = $count; $best_delimiter = $d; }
    }
    return $best_delimiter;
}

function parseHeader($line, $delimiter) {
    $headers = explode($delimiter, $line);
    return array_map(function($h) { return trim($h, " \t\n\r\0\x0B'\""); }, $headers);
}

function getFieldMapping($rta, $document_type, $headers) {
    global $FIELD_MAPPINGS;
    $mapping = [];
    $config = $FIELD_MAPPINGS[$rta][$document_type] ?? [];
    foreach ($config as $db_field => $possible_names) {
        foreach ($headers as $index => $header) {
            if (in_array(strtoupper(trim($header)), array_map('strtoupper', $possible_names))) {
                $mapping[$db_field] = $index;
                break;
            }
        }
    }
    return $mapping;
}

function parseDataRow($line, $delimiter, $headers, $mapping, $rta, $doc) {
    $fields = explode($delimiter, $line);
    $record = [];
    foreach ($mapping as $db_field => $index) {
        if (isset($fields[$index])) {
            $val = trim($fields[$index], " \t\n\r\0\x0B'\"");
            
            // Specifically extract only the date part for nav_date in WBR10
            if ($doc === 'WBR10' && $db_field === 'nav_date') {
                $parts = explode(' ', $val);
                $val = str_replace('/', '-', $parts[0]);
            }
            
            // Translate '+' to 'P' and '-' to 'R' regardless of stray quotes
            if ($db_field === 'transaction_flag') {
                if (strpos($val, '+') !== false) $val = 'P';
                elseif (strpos($val, '-') !== false) $val = 'R';
            }
            
            // Extract only the part before '/' for folio_number and FOLIOCHK
            if ($db_field === 'folio_number' || $db_field === 'FOLIOCHK') {
                $parts = explode('/', $val);
                $val = trim($parts[0]);
            }
            
            $formatted_value = formatValueByFieldType($db_field, $val);
            if ($formatted_value !== null && $formatted_value !== '') {
                $record[$db_field] = $formatted_value;
            }
        }
    }
    return $record;
}

function formatValueByFieldType($field, $value) {
    $dates = ['transaction_date', 'process_date', 'nav_date', 'report_date', 'sip_start_date', 'sip_end_date', 'as_of_date', 'purchase_date', 'sip_reg_date', 'instrument_date', 'REP_DATE', 'INV_DOB', 'FOLIO_DATE', 'JH1_DOB', 'JH2_DOB', 'GUARDIAN_DOB', 'cease_date', 'reg_date'];
    if (in_array($field, $dates)) return parseDate($value);
    
    $nums = ['amount', 'units', 'nav', 'price', 'sip_amount', 'total_units', 'current_nav', 'current_value', 'average_cost', 'total_cost', 'unrealized_gain_loss', 'CLOS_BAL', 'RUPEE_BAL', 'NOM_PERCENTAGE', 'NOM2_PERCENTAGE', 'NOM3_PERCENTAGE', 'load_amount', 'stt', 'tds_amount', 'tr_charges', 'stamp_duty', 'brokerage_percentage', 'commission'];
    if (in_array($field, $nums)) return floatval(preg_replace('/[^0-9.-]/', '', $value));
    
    return $value;
}

function parseDate($str) {
    if (empty($str)) return null;
    
    $date = DateTime::createFromFormat('m/d/Y h:i:s A', trim($str));
    if ($date) return $date->format('Y-m-d');
    
    if (preg_match('/^\d{8}$/', trim($str))) {
        $date = DateTime::createFromFormat('Ymd', trim($str));
        if ($date) return $date->format('Y-m-d');
    }
    
    $ts = strtotime(str_replace('/', '-', $str));
    if (!$ts) $ts = strtotime($str);
    
    return $ts ? date('Y-m-d', $ts) : null;
}

function isHeaderRow($headers) {
    $header_keywords = ['FOLIO', 'DATE', 'TRADDATE', 'FOLIO_CHK', 'FOLIOCHK', 'FOLIO NUMBER', 'PRODUCT CODE', 'FOLIO_NO', 'PRODUCT'];
    foreach ($headers as $h) {
        if (in_array(strtoupper($h), $header_keywords)) return true;
    }
    return false;
}

function isValidRecord($record, $doc) {
    return !empty($record['folio_number']) || !empty($record['FOLIOCHK']);
}
?>