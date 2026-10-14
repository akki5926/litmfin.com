<?php
require_once('auth_guard.php');

if (!isset($_SESSION)) { session_start(); }
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// ============================================================
// BUG FIX: Check $_FILES['fileToUpload'] (matches input name)
//           Previously was $_FILES['file'] — caused "No file uploaded" error
// ============================================================
if (isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['fileToUpload'];
    $fileType = $_POST['fileType'] ?? '';
    $remarks  = $_POST['remarks'] ?? '';

    switch ($fileType) {
        case 'Fund House':
            handleFundHouseFile($file, $remarks);
            break;
        case 'Transaction Type Master':
            handleTransactionTypeFile($file, $remarks);
            break;
        case 'MF Master File':
            // BUG FIX: MF Master File uploads CSV → uses batchUpsertSchemes into mtb_scheme_master
            handleMFSchemeMasterCSV($file, $remarks);
            break;
        case 'Bond Master File':
            handleBondMasterFile($file, $remarks);
            break;
        case 'Bond NAV':
            handleBondNAVFile($file, $remarks);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type selected: ' . htmlspecialchars($fileType)]);
            break;
    }
} else {
    // Provide a helpful error showing what PHP actually received
    $uploadError = $_FILES['fileToUpload']['error'] ?? 'key_missing';
    $uploadErrorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was selected for upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        'key_missing'         => 'File key "fileToUpload" not found in $_FILES. Check form enctype and field name.',
    ];
    $errorMsg = $uploadErrorMessages[$uploadError] ?? "Unknown upload error code: $uploadError";
    echo json_encode(['status' => 'error', 'message' => 'Upload failed: ' . $errorMsg]);
}

// ============================================================
// FUND HOUSE (AMC) — Excel upload → mtb_amc
// ============================================================
function handleFundHouseFile($excelFile, $remarks) {
    require_once('database.php');
    $db = new MySQLDB();
    $inputFileName = $excelFile['tmp_name'];

    if (!is_readable($inputFileName)) {
        echo json_encode(['status' => 'error', 'message' => 'Uploaded file is not readable on server.']);
        return;
    }

    $lookupTable = [
        'AMC_CODE'           => 'AMC_CODE',
        'LONG_NAME'          => 'LONG_NAME',
        'LAST_MODIFIED_DATE' => 'LAST_MODIFIED_DATE',
        'BANNED_COUNTRY'     => 'BANNED_COUNTRY',
        'ALLOW_PAUSE_SIP'    => 'ALLOW_PAUSE_SIP',
        'ALLOW_PAUSE_COUNT'  => 'ALLOW_PAUSE_COUNT',
    ];
    $conditionsCol = ['AMC_CODE' => 'A'];
    $dbtable    = 'mtb_amc';
    $primaryKey = 'AMC_ID';

    $logMsg = ExportExcel2DB($db, $dbtable, $primaryKey, $inputFileName, $lookupTable, $conditionsCol);
    $db->insertAdminLog(mb_substr(strip_tags($logMsg), 0, 250), null);
    echo json_encode(['status' => 'success', 'message' => $logMsg]);
}

// ============================================================
// TRANSACTION TYPE MASTER — Excel upload → tb_cams_trx_type
// ============================================================
function handleTransactionTypeFile($excelFile, $remarks) {
    require_once('database.php');
    $db = new MySQLDB();
    $inputFileName = $excelFile['tmp_name'];

    if (!is_readable($inputFileName)) {
        echo json_encode(['status' => 'error', 'message' => 'Uploaded file is not readable on server.']);
        return;
    }

    $lookupTable = [
        'TRXN_TYPE'  => 'trx_type',
        'TRXN_DESC'  => 'trx_desc',
        'TRXN_DB_CR' => 'trx_db_cr',
        'TRXN_SIGN'  => 'trx_sign',
        'REMARKS'    => 'remarks',
    ];
    $conditionsCol = ['trx_type' => 'A'];
    $dbtable    = 'tb_cams_trx_type';
    $primaryKey = 'sl_no';

    $logMsg = ExportExcel2DB($db, $dbtable, $primaryKey, $inputFileName, $lookupTable, $conditionsCol);
    $db->insertAdminLog(mb_substr(strip_tags($logMsg), 0, 250), null);
    echo json_encode(['status' => 'success', 'message' => $logMsg]);
}

// ============================================================
// MF SCHEME MASTER — CSV upload → mtb_scheme_master
// Uses batchUpsertSchemes() from database.php (bulk ON DUPLICATE KEY UPDATE)
// CSV columns map to SchemeMaster-Template.xlsx column headers
// ============================================================
function handleMFSchemeMasterCSV($csvFile, $remarks) {
    require_once('database.php');
    $db = new MySQLDB();

    $tmpPath = $csvFile['tmp_name'];
    $origName = $csvFile['name'];

    if (!is_readable($tmpPath)) {
        echo json_encode(['status' => 'error', 'message' => 'Uploaded CSV file is not readable on server.']);
        return;
    }

    // Detect delimiter (comma or pipe — NSE uses pipe '|')
    $firstLine = fgets(fopen($tmpPath, 'r'));
    $delimiter = (substr_count($firstLine, '|') > substr_count($firstLine, ',')) ? '|' : ',';

    $handle = fopen($tmpPath, 'r');
    if (!$handle) {
        echo json_encode(['status' => 'error', 'message' => 'Could not open uploaded CSV file.']);
        return;
    }

    // Read header row — strip BOM if present
    $rawHeader = fgetcsv($handle, 0, $delimiter);
    if (!$rawHeader) {
        fclose($handle);
        echo json_encode(['status' => 'error', 'message' => 'CSV file appears to be empty or header row is missing.']);
        return;
    }

    // Clean header names (trim whitespace, remove BOM)
    $headers = array_map(function($h) {
        return trim(str_replace("\xEF\xBB\xBF", '', $h)); // remove UTF-8 BOM
    }, $rawHeader);

    // -------------------------------------------------------
    // CSV column header → mtb_scheme_master DB column mapping
    // Based on SchemeMaster-Template.xlsx column headers
    // -------------------------------------------------------
    $csvToDb = [
        'UNIQUE SR NO'                      => 'unique_sr_no',
        'SCHEME CODE'                       => 'scheme_code',
        'RTA SCHEME CODE'                   => 'rta_scheme_code',
        'AMC SCHEME CODE'                   => 'amc_scheme_code',
        'ISIN'                              => 'isin',
        'AMC CODE'                          => 'amc_code',
        'SCHEME TYPE'                       => 'scheme_type',
        'PLAN TYPE'                         => 'plan_type',
        'SCHEME NAME'                       => 'scheme_name',
        'PURCHASE ALLOWED'                  => 'purchase_allowed',
        'PURCHASE TRANSACTION MODE'         => 'purchase_transaction_mode',
        'NEW PURCHASE MIN AMOUNT'           => 'new_purchase_min_amount',
        'ADDITIONAL PURCHASE MIN AMOUNT'    => 'additional_purchase_min_amount',
        'ADDITIONAL PURCHASE MAX AMOUNT'    => 'additional_purchase_max_amount',
        'PURCHASE AMOUNT MULTIPLIER'        => 'purchase_amount_multiplier',
        'PURCHASE CUTOFF TIME'              => 'purchase_cutoff_time',
        'REDEMPTION ALLOWED'                => 'redemption_allowed',
        'REDEMPTION TRANSACTION MODE'       => 'redemption_transaction_mode',
        'REDEMPTION MIN QTY'                => 'redemption_min_qty',
        'REDEMPTION QTY MULTIPLIER'         => 'redemption_qty_multiplier',
        'REDEMPTION MAX QTY'                => 'redemption_max_qty',
        'REDEMPTION MIN AMOUNT'             => 'redemption_min_amount',
        'REDEMPTION MAX AMOUNT'             => 'redemption_max_amount',
        'REDEMPTION AMOUNT MULTIPLIER'      => 'redemption_amount_multiplier',
        'REDEMPTION CUTOFF TIME'            => 'redemption_cutoff_time',
        'RTA AGENT CODE'                    => 'rta_agent_code',
        'AMC ACTIVE FLAG'                   => 'amc_active_flag',
        'DIV REINVEST FLAG'                 => 'div_reinvest_flag',
        'SIP ALLOWED'                       => 'sip_allowed',
        'STP ENABLED'                       => 'stp_enabled',
        'SWP ENABLED'                       => 'swp_enabled',
        'SWITCH ALLOWED'                    => 'switch_allowed',
        'SETTLEMENT TYPE'                   => 'settlement_type',
        'AMC IND'                           => 'amc_ind',
        'FACE VALUE'                        => 'face_value',
        'SCHEME START DATE'                 => 'scheme_start_date',
        'MATURITY DATE'                     => 'maturity_date',
        'EXIT LOAD FLAG'                    => 'exit_load_flag',
        'EXIT LOAD'                         => 'exit_load',
        'LOCK IN PERIOD_FLAG'               => 'lock_in_period_flag',
        'LOCK IN PERIOD'                    => 'lock_in_period',
        'CHANNEL PARTNER CODE'              => 'channel_partner_code',
        'REOPENING DATE'                    => 'reopening_date',
        'OPEN/CLOSE ENDED SCHEME'           => 'open_close_ended_scheme',
    ];

    // Date columns that need reformatting
    $dateCols = ['scheme_start_date', 'maturity_date', 'reopening_date'];

    // Build column index map: csv_column_index => db_field_name
    $colMap = [];
    foreach ($headers as $idx => $headerName) {
        $clean = trim($headerName);
        if (isset($csvToDb[$clean])) {
            $colMap[$idx] = $csvToDb[$clean];
        }
    }

    if (empty($colMap)) {
        fclose($handle);
        echo json_encode([
            'status'  => 'error',
            'message' => 'No recognizable column headers found in CSV. Expected headers like "SCHEME CODE", "ISIN", "AMC CODE" etc. '
                       . 'Got: ' . implode(', ', array_slice($headers, 0, 10))
        ]);
        return;
    }

    // Check scheme_code column exists (required for upsert)
    $schemeCodeIdx = array_search('scheme_code', $colMap);
    if ($schemeCodeIdx === false) {
        fclose($handle);
        echo json_encode(['status' => 'error', 'message' => 'CSV must contain a "SCHEME CODE" column.']);
        return;
    }

    // Process rows in batches of 500
    $BATCH_SIZE  = 500;
    $batch       = [];
    $totalInsert = 0;
    $totalUpdate = 0;
    $totalSkip   = 0;
    $rowNum      = 1; // header was row 1
    $errors      = [];

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rowNum++;

        // Skip blank rows
        $nonEmpty = array_filter($row, fn($v) => trim($v) !== '');
        if (empty($nonEmpty)) {
            $totalSkip++;
            continue;
        }

        // Build record
        $record = [];
        foreach ($colMap as $idx => $dbField) {
            $val = isset($row[$idx]) ? trim($row[$idx]) : '';

            // Normalize date columns to Y-m-d
            if (in_array($dbField, $dateCols)) {
                $val = normalizeDateString($val);
            }

            $record[$dbField] = ($val === '') ? null : $val;
        }

        // scheme_code is mandatory
        if (empty($record['scheme_code'])) {
            $totalSkip++;
            continue;
        }

        // Default fund_cat if missing (required by batchUpsertSchemes column list)
        if (!isset($record['fund_cat']) || $record['fund_cat'] === null) {
            $record['fund_cat'] = guessFundCat($record['scheme_type'] ?? '');
        }

        $batch[] = $record;

        // Flush batch
        if (count($batch) >= $BATCH_SIZE) {
            try {
                $result      = $db->batchUpsertSchemes($batch);
                $totalInsert += $result['inserted'];
                $totalUpdate += $result['updated'];
            } catch (Exception $e) {
                $errors[] = 'Batch error near row ' . $rowNum . ': ' . $e->getMessage();
                error_log('handleMFSchemeMasterCSV batch error: ' . $e->getMessage());
            }
            $batch = [];
        }
    }
    fclose($handle);

    // Flush remaining rows
    if (!empty($batch)) {
        try {
            $result      = $db->batchUpsertSchemes($batch);
            $totalInsert += $result['inserted'];
            $totalUpdate += $result['updated'];
        } catch (Exception $e) {
            $errors[] = 'Final batch error: ' . $e->getMessage();
            error_log('handleMFSchemeMasterCSV final batch error: ' . $e->getMessage());
        }
    }

    // Build response message
    $parts = [];
    if ($totalInsert > 0) $parts[] = "$totalInsert new scheme(s) inserted";
    if ($totalUpdate > 0) $parts[] = "$totalUpdate scheme(s) updated";
    if ($totalSkip  > 0) $parts[] = "$totalSkip row(s) skipped (blank or missing scheme code)";

    $status  = empty($errors) ? 'success' : 'error';
    $message = empty($parts)
        ? 'No records were processed. Please check your CSV file format.'
        : implode(', ', $parts) . '.';

    if (!empty($errors)) {
        $message .= ' Errors: ' . implode('; ', array_slice($errors, 0, 5));
    }

    $db->insertAdminLog(mb_substr(strip_tags($message), 0, 250), null);
    echo json_encode(['status' => $status, 'message' => $message]);
}

// ============================================================
// BOND MASTER — Excel upload → mtb_other_investment
// ============================================================
function handleBondMasterFile($excelFile, $remarks) {
    require_once('database.php');
    $db = new MySQLDB();
    $inputFileName = $excelFile['tmp_name'];

    if (!is_readable($inputFileName)) {
        echo json_encode(['status' => 'error', 'message' => 'Uploaded file is not readable on server.']);
        return;
    }

    $lookupTable = [
        'Asset Class'        => 'asset_class',
        'Bond Name'          => 'investment_name',
        'Face Value'         => 'face_value',
        'NSE Code'           => 'nse_code',
        'BSE Code'           => 'bse_code',
        'Coupon Rate'        => 'interest_rate',
        'Payout Frequency'   => 'payout_frequency',
        'Redemption Date'    => 'redemption_date',
        'Int Amt per Payout' => 'int_amt_per_payout',
        'interest pay date'  => 'interest_pay_date',
    ];
    $conditionsCol = ['investment_name' => 'B'];
    $dbtable    = 'mtb_other_investment';
    $primaryKey = 'slno';

    $logMsg = ExportExcel2DB($db, $dbtable, $primaryKey, $inputFileName, $lookupTable, $conditionsCol);
    $db->insertAdminLog(mb_substr(strip_tags($logMsg), 0, 250), null);
    echo json_encode(['status' => 'success', 'message' => $logMsg]);
}

// ============================================================
// BOND NAV — Excel upload → tb_other_investment_nav
// ============================================================
function handleBondNAVFile($excelFile, $remarks) {
    require_once('database.php');
    $db = new MySQLDB();
    $inputFileName = $excelFile['tmp_name'];

    if (!is_readable($inputFileName)) {
        echo json_encode(['status' => 'error', 'message' => 'Uploaded file is not readable on server.']);
        return;
    }

    $lookupTable = [
        'Name'        => 'name',
        'Face Value'  => 'face_value',
        'NSE Code'    => 'nse_code',
        'BSE Code'    => 'bse_code',
        'Current NAV' => 'current_nav',
        'NAV date'    => 'nav_date',
    ];
    $conditionsCol = ['name' => 'A'];
    $dbtable    = 'tb_other_investment_nav';
    $primaryKey = 'slno';

    $logMsg = ExportExcel2DB($db, $dbtable, $primaryKey, $inputFileName, $lookupTable, $conditionsCol);
    $db->insertAdminLog(mb_substr(strip_tags($logMsg), 0, 250), null);
    echo json_encode(['status' => 'success', 'message' => $logMsg]);
}

// ============================================================
// GENERIC EXCEL → DB HANDLER (Fund House, Transaction Type, Bond)
// ============================================================
function ExportExcel2DB($db, $dbtable, $primaryKey, $inputFileName, $lookupTable, $conditionsCol) {
    $countRA = 0; // inserted
    $countUp = 0; // updated
    $countIF = 0; // insert/update failed
    $countRI = 0; // rows skipped (blank)
    $errMsg  = '';

    try {
        $spreadsheet = IOFactory::load($inputFileName);
        $worksheet   = $spreadsheet->getActiveSheet();

        $highestRow    = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColIdx = Coordinate::columnIndexFromString($highestColumn);

        // Read and clean headers from row 1
        $headers = [];
        for ($col = 1; $col <= $highestColIdx; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $val    = $worksheet->getCell($letter . '1')->getValue();
            if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $val = $val->getPlainText();
            }
            $headers[$col] = trim(preg_replace('/\s+/', ' ', str_replace("\xC2\xA0", ' ', (string)$val)));
        }

        // Map column index → DB field
        $columnMapping = [];
        $dateColIdxs   = [];
        foreach ($headers as $colIdx => $headerName) {
            if (isset($lookupTable[$headerName])) {
                $dbField = $lookupTable[$headerName];
                $columnMapping[$colIdx] = $dbField;
                if (stripos($dbField, 'date') !== false || stripos($dbField, 'modified') !== false) {
                    $dateColIdxs[] = $colIdx;
                }
            }
        }

        if (empty($columnMapping)) {
            return 'No matching columns found. Please check the file format and headers.';
        }

        // Map condition column letters to numeric indices
        $conditionColIdxs = [];
        foreach ($conditionsCol as $field => $letter) {
            $conditionColIdxs[$field] = Coordinate::columnIndexFromString($letter);
        }

        // Get table columns for NULL handling
        $tableColumns = getTableColumns($db, $dbtable);

        // Process rows starting from row 2
        for ($row = 2; $row <= $highestRow; $row++) {
            $firstLetter = Coordinate::stringFromColumnIndex(1);
            $firstVal    = $worksheet->getCell($firstLetter . $row)->getValue();
            if ($firstVal instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $firstVal = $firstVal->getPlainText();
            }

            if ($firstVal === null || $firstVal === '') {
                $countRI++;
                continue;
            }

            // Build WHERE conditions string
            $conditionsStr = '';
            foreach ($conditionColIdxs as $field => $colIdx) {
                $letter   = Coordinate::stringFromColumnIndex($colIdx);
                $cellVal  = $worksheet->getCell($letter . $row)->getValue();
                if ($cellVal instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $cellVal = $cellVal->getPlainText();
                }
                $cellVal  = addslashes((string)$cellVal);
                $conditionsStr .= "`$field` = '$cellVal' AND ";
            }
            $conditionsStr = rtrim($conditionsStr, ' AND');

            // Build field values
            $fieldValues = [];
            $skipRow     = false;

            foreach ($columnMapping as $colIdx => $dbField) {
                $letter   = Coordinate::stringFromColumnIndex($colIdx);
                $cellVal  = $worksheet->getCell($letter . $row)->getValue();
                if ($cellVal instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $cellVal = $cellVal->getPlainText();
                }

                $allowsNull = isset($tableColumns[$dbField]['null']) && $tableColumns[$dbField]['null'] === 'YES';
                $isRequired = isset($tableColumns[$dbField]['null']) && $tableColumns[$dbField]['null'] === 'NO';

                if ($cellVal === null || $cellVal === '' || $cellVal === 'NULL') {
                    if ($isRequired && $dbField !== $primaryKey) {
                        $skipRow = true;
                        $errMsg .= "Required field '$dbField' empty at row $row. ";
                        break;
                    }
                    $fieldValues[$dbField] = 'NULL';
                    continue;
                }

                // Date handling
                if (in_array($colIdx, $dateColIdxs)) {
                    $fieldValues[$dbField] = convertExcelDate($cellVal);
                    continue;
                }

                $fieldValues[$dbField] = (string)$cellVal;
            }

            if ($skipRow) {
                $countIF++;
                continue;
            }

            $msg = $db->insertOrUpdateDynamicRecordWithConditions($dbtable, $primaryKey, $fieldValues, $conditionsStr, $tableColumns);

            if ($msg === 'Inserted Successfully') {
                $countRA++;
            } elseif ($msg === 'Updated Successfully' || $msg === 'Updated Successfully (No changes)') {
                $countUp++;
            } else {
                $countIF++;
                $errMsg .= $msg . ' ';
            }
        }

        // Build summary
        $logMsg = '';
        if ($countRA > 0) $logMsg .= "Records Inserted: $countRA<br>";
        if ($countUp > 0) $logMsg .= "Records Updated: $countUp<br>";
        if ($countRI > 0) $logMsg .= "Empty rows skipped: $countRI<br>";
        if ($countIF > 0) {
            $logMsg .= "<span style='color:red;'>Failed: $countIF";
            if (!empty($errMsg)) $logMsg .= ' — ' . htmlspecialchars(mb_substr($errMsg, 0, 300));
            $logMsg .= "</span><br>";
        }

        return $logMsg ?: 'No records were processed.';

    } catch (Exception $e) {
        error_log('ExportExcel2DB error: ' . $e->getMessage());
        return 'Error processing file: ' . $e->getMessage();
    }
}

// ============================================================
// HELPER — get table column metadata for NULL checks
// ============================================================
function getTableColumns($db, $tableName) {
    $columns = [];
    $result  = $db->query('SHOW COLUMNS FROM `' . preg_replace('/[^a-zA-Z0-9_]/', '', $tableName) . '`');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = [
                'type'    => $row['Type'],
                'null'    => $row['Null'],
                'key'     => $row['Key'],
                'default' => $row['Default'],
            ];
        }
        $result->free();
    }
    return $columns;
}

// ============================================================
// HELPER — convert Excel numeric or string date to Y-m-d
// ============================================================
function convertExcelDate($excelDate) {
    if ($excelDate === null || $excelDate === '') {
        return 'NULL';
    }

    if (is_numeric($excelDate)) {
        try {
            $ts = Date::excelToTimestamp($excelDate);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        } catch (Exception $e) { /* fall through */ }
    }

    $dateStr = trim((string)$excelDate);
    if ($dateStr === '') return 'NULL';

    $formats = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-M-Y', 'm/d/Y', 'd.m.Y', 'j-M-Y', 'd M Y'];
    foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat($fmt, $dateStr);
        if ($d !== false) {
            return $d->format('Y-m-d');
        }
    }

    $ts = strtotime($dateStr);
    return ($ts !== false && $ts !== -1) ? date('Y-m-d', $ts) : 'NULL';
}

// ============================================================
// HELPER — normalize date strings from CSV rows
// ============================================================
function normalizeDateString($val) {
    if (empty($val)) return null;

    // Already Y-m-d?
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;

    // d-M-Y e.g. 01-Jan-2010
    $d = DateTime::createFromFormat('d-M-Y', $val);
    if ($d) return $d->format('Y-m-d');

    // d/m/Y
    $d = DateTime::createFromFormat('d/m/Y', $val);
    if ($d) return $d->format('Y-m-d');

    // m/d/Y
    $d = DateTime::createFromFormat('m/d/Y', $val);
    if ($d) return $d->format('Y-m-d');

    // strtotime fallback
    $ts = strtotime($val);
    return ($ts !== false && $ts > 0) ? date('Y-m-d', $ts) : null;
}

// ============================================================
// HELPER — guess fund_cat from scheme_type for CSV rows
// ============================================================
function guessFundCat($schemeType) {
    $map = [
        'DEBT'      => 'Debt',
        'EQUITY'    => 'Other Equity',
        'ETF'       => 'Other Equity',
        'LIQUID'    => 'Debt',
        'OVERNIGHT' => 'Debt',
        'HYBRID'    => 'Hybrid',
    ];
    return $map[strtoupper(trim($schemeType))] ?? 'Other';
}