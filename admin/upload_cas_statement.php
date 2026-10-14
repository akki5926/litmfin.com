<?php
require_once('auth_guard.php');

if (!isset($_SESSION)) { session_start(); }
require_once('database.php');

// Handle AJAX requests
if (isset($_POST['action'])) {
    $db = new MYSQLDB();
    header('Content-Type: application/json');

    $isSubAdmin = isset($_SESSION["superuser"]) && $_SESSION["superuser"] == 0;
    $subAdminId = $_SESSION["userid"] ?? 0;
    
    if ($_POST['action'] === 'get_user_info') {
        $ucc = $_POST['ucc'] ?? '';
        if (!$ucc) {
            echo json_encode(['success' => false, 'message' => 'UCC is required']);
            exit;
        }
        if ($isSubAdmin && !$db->isClientAssignedToSubadmin($ucc, $subAdminId)) {
            echo json_encode(['success' => false, 'message' => 'Access Denied: This customer is not assigned to you.']);
            exit;
        }
        $user = $db->getUserByClientCode($ucc);
        if ($user) {
            echo json_encode([
                'success' => true, 
                'name' => $user['PRIMARY_HOLDER_NAME'], 
                'pan' => $user['PRIMARY_HOLDER_PAN']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_temp_cas') {
        $tempJsonPath = $_POST['tempJsonPath'] ?? '';
        if ($tempJsonPath && file_exists($tempJsonPath) && strpos($tempJsonPath, 'temp_') !== false && strpos($tempJsonPath, '.json') !== false) {
            @unlink($tempJsonPath);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    if ($_POST['action'] === 'get_cas_list') {
        $data = $db->fetchCasTableData($isSubAdmin ? $subAdminId : null);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    
    if ($_POST['action'] === 'upload_cas') {
        ini_set('memory_limit', '512M'); // Increase memory limit if needed
        
        $ucc = $_POST['ucc'] ?? '';
        $name = $_POST['name'] ?? '';
        $pan = $_POST['pan'] ?? '';
        
        if (empty($ucc) || !isset($_FILES['cas_file']) || $_FILES['cas_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'UCC and valid file are required']);
            exit;
        }
        if ($isSubAdmin && !$db->isClientAssignedToSubadmin($ucc, $subAdminId)) {
            echo json_encode(['success' => false, 'message' => 'Access Denied: This customer is not assigned to you.']);
            exit;
        }
        
        $uploadDir = __DIR__ . '/uploads/casstatements/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['cas_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, ['xlsx', 'xls', 'csv'])) {
            echo json_encode(['success' => false, 'message' => 'Only Excel files (.xlsx, .xls, .csv) are allowed']);
            exit;
        }
        
        // Cleanup old temp files
        $oldFiles = glob($uploadDir . 'temp_*');
        if ($oldFiles) {
            $now = time();
            foreach ($oldFiles as $f) {
                if (is_file($f) && ($now - filemtime($f) >= 3600)) {
                    @unlink($f);
                }
            }
        }

        // 1. Move to a temporary path
        $tempName = 'temp_' . time() . '_' . mt_rand(1000, 9999);
        $tempFilePath = $uploadDir . $tempName . '.' . $fileExtension;
        
        if (!move_uploaded_file($_FILES['cas_file']['tmp_name'], $tempFilePath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file to temporary folder.']);
            exit;
        }
        
        session_write_close();

        // 2. Extract Excel data
        require_once __DIR__ . '/vendor/autoload.php';
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFilePath);
            $sheetNames = $spreadsheet->getSheetNames();
            $valSheetName = null;
            $txSheetName = null;

            foreach ($sheetNames as $sn) {
                $sn_lower = strtolower($sn);
                if (strpos($sn_lower, 'validation') !== false) {
                    $valSheetName = $sn;
                } elseif (strpos($sn_lower, 'transaction') !== false) {
                    $txSheetName = $sn;
                }
            }
            if (!$txSheetName && !empty($sheetNames)) {
                $txSheetName = $sheetNames[0];
            }

            $data = ['validation_report' => [], 'all_transactions' => []];

            if ($valSheetName) {
                $sheet = $spreadsheet->getSheetByName($valSheetName);
                $rows = $sheet->toArray(null, true, true, true);
                if (!empty($rows)) {
                    $headers = [];
                    foreach ($rows as $idx => $row) {
                        $rowStr = implode(' ', array_filter($row));
                        // Look for a row that looks like headers
                        if (stripos($rowStr, 'AMC') !== false && stripos($rowStr, 'Fund') !== false) {
                            $headers = $row;
                            $rows = array_slice($rows, $idx + 1);
                            break;
                        }
                    }
                    if (empty($headers)) {
                        $headers = array_shift($rows);
                    }
                    
                    foreach ($rows as $row) {
                        if (empty(array_filter($row))) continue;
                        $item = [];
                        foreach ($headers as $col => $headerName) {
                            if ($headerName) {
                                $cleanHeader = str_replace(' ', '_', trim($headerName));
                                $item[$cleanHeader] = $row[$col];
                                if ($cleanHeader !== trim($headerName)) {
                                    $item[trim($headerName)] = $row[$col];
                                }
                            }
                        }
                        if (!isset($item['SECTION']) && isset($item['AMC'])) {
                            $item['SECTION'] = 'FUND_LEVEL';
                        }
                        if (!empty($item['AMC']) || !empty($item['Fund_Name'])) {
                            $data['validation_report'][] = $item;
                        }
                    }
                }
            }

            if ($txSheetName) {
                $sheet = $spreadsheet->getSheetByName($txSheetName);
                $rows = $sheet->toArray(null, true, true, true);
                if (!empty($rows)) {
                    $headers = [];
                    foreach ($rows as $idx => $row) {
                        $rowStr = implode(' ', array_filter($row));
                        if (stripos($rowStr, 'AMC') !== false && (stripos($rowStr, 'Transaction') !== false || stripos($rowStr, 'Date') !== false)) {
                            $headers = $row;
                            $rows = array_slice($rows, $idx + 1);
                            break;
                        }
                    }
                    if (empty($headers)) {
                        $headers = array_shift($rows);
                    }

                    foreach ($rows as $row) {
                        if (empty(array_filter($row))) continue;
                        $item = [];
                        foreach ($headers as $col => $headerName) {
                            if ($headerName) {
                                $cleanHeader = str_replace(' ', '_', trim($headerName));
                                $item[$cleanHeader] = $row[$col];
                                if ($cleanHeader !== trim($headerName)) {
                                    $item[trim($headerName)] = $row[$col];
                                }
                            }
                        }
                        if (isset($item['Is_System'])) {
                            $isSys = strtolower(trim((string)$item['Is_System']));
                            $item['Is_System'] = ($isSys === 'true' || $isSys === '1' || $isSys === 'yes');
                        }
                        if (!empty($item['AMC']) || !empty($item['Date'])) {
                            $data['all_transactions'][] = $item;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            @unlink($tempFilePath);
            echo json_encode(['success' => false, 'message' => 'Error reading Excel file: ' . $e->getMessage()]);
            exit;
        }
        
        if (empty($data['all_transactions'])) {
            @unlink($tempFilePath);
            echo json_encode(['success' => false, 'message' => 'Could not find any transactions. Please ensure the Excel file has a transactions sheet.']);
            exit;
        }
        
        $tempJsonPath = $uploadDir . $tempName . '.json';
        file_put_contents($tempJsonPath, json_encode($data));

        
        // 3. Validate Name match
        $parsedName = '';
        $parsedPan = '';
        if (!empty($data['all_transactions'])) {
            foreach ($data['all_transactions'] as $txn) {
                if (!empty($txn['Investor_Name']) && empty($parsedName)) {
                    $parsedName = $txn['Investor_Name'];
                }
                if (!empty($txn['PAN']) && empty($parsedPan)) {
                    $parsedPan = $txn['PAN'];
                }
                if ($parsedName && $parsedPan) break;
            }
        }
        
        $matchFound = false;
        if ($parsedName) {
            $dbNameClean = strtoupper(preg_replace('/[^A-Z]/', '', $name));
            $parsedNameClean = strtoupper(preg_replace('/[^A-Z]/', '', $parsedName));
            if ($dbNameClean && $parsedNameClean) {
                if (strpos($parsedNameClean, $dbNameClean) !== false || strpos($dbNameClean, $parsedNameClean) !== false) {
                    $matchFound = true;
                } else {
                    $dbWords = array_filter(explode(' ', strtoupper(preg_replace('/[^A-Z\s]/', '', $name))));
                    $parsedWords = array_filter(explode(' ', strtoupper(preg_replace('/[^A-Z\s]/', '', $parsedName))));
                    $common = array_intersect($dbWords, $parsedWords);
                    if (count($common) >= min(2, count($dbWords))) {
                        $matchFound = true;
                    }
                }
            }
        }
        
        if (!$matchFound && $parsedPan && $pan) {
            if (strtoupper(trim($parsedPan)) === strtoupper(trim($pan))) {
                $matchFound = true;
            }
        }
        
        if (!$matchFound) {
            @unlink($tempFilePath);
            @unlink($tempJsonPath);
            $foundMsg = $parsedName ? "Found: '$parsedName'" : "Name not found in PDF";
            echo json_encode(['success' => false, 'message' => "Client name mismatch. Selected: '$name', $foundMsg. Please select the correct file."]);
            exit;
        }

        // 4. File Naming and Moving (Validation passed)
        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $name);
        $safeName = trim(preg_replace('/_+/', '_', $safeName), '_');
        $dateStr = strtoupper(date('dMy')); // e.g. 14OCT25
        $finalName = $safeName . '_' . $dateStr . '.' . $fileExtension;
        $finalFilePath = $uploadDir . $finalName;
        
        // Delete old physical file if exists
        $oldFile = $db->getCasFile($ucc);
        if ($oldFile && file_exists(__DIR__ . '/' . $oldFile) && is_file(__DIR__ . '/' . $oldFile)) {
            @unlink(__DIR__ . '/' . $oldFile);
        }
        
        // Delete old entry
        $db->deleteCasStatements($ucc);

        @rename($tempFilePath, $finalFilePath);
        
        // Save entry to database tracking location
        $db->saveCasStm($ucc, 'uploads/casstatements/' . $finalName, $name, $pan);
        
        // 5. Parsed JSON is already saved to temp file to await user review
        
        // Package response for UI Validation Builder
        $data['tempJsonPath'] = $tempJsonPath;
        
        echo json_encode(['success' => true, 'parsed_data' => $data]);
        exit;
    }

    if ($_POST['action'] === 'save_parsed_cas') {
        $selectedKeysJson = $_POST['selected_keys'] ?? '[]';
        $selectedKeys = json_decode($selectedKeysJson, true);
        if (!is_array($selectedKeys)) $selectedKeys = [];
        
        $validKeysMap = array_flip($selectedKeys);

        $ucc = $_POST['ucc'] ?? '';
        $name = $_POST['name'] ?? '';
        $pan = $_POST['pan'] ?? '';
        $tempJsonPath = $_POST['tempJsonPath'] ?? '';

        if (empty($ucc)) {
            echo json_encode(['success' => false, 'message' => 'UCC is required']);
            exit;
        }
        if ($isSubAdmin && !$db->isClientAssignedToSubadmin($ucc, $subAdminId)) {
            echo json_encode(['success' => false, 'message' => 'Access Denied: This customer is not assigned to you.']);
            exit;
        }

        if (!file_exists($tempJsonPath)) {
            echo json_encode(['success' => false, 'message' => 'Temporary data missing. Please upload the PDF again.']);
            exit;
        }

        $data = json_decode(file_get_contents($tempJsonPath), true);
        
        // DELETE existing validation and transaction data
        $db->deleteCasSummary($ucc);
        $db->deleteCasTransactions($ucc);
        
        // 1. Build validation lookup for "clean" mode filtering
        $validStatus = [];
        if (!empty($data['validation_report'])) {
            // Extract ISINs from transactions for summary lookup
            $isinLookup = [];
            if (!empty($data['all_transactions'])) {
                foreach ($data['all_transactions'] as $txn) {
                    $k = trim($txn['AMC'] ?? '') . '|' . trim($txn['ISIN'] ?? '') . '|' . trim($txn['Fund_Name'] ?? '') . '|' . trim($txn['Folio_No'] ?? '');
                    if (!empty($txn['ISIN']) && !isset($isinLookup[$k])) {
                        $isinLookup[$k] = $txn['ISIN'];
                    }
                }
            }

            foreach ($data['validation_report'] as $vr) {
                if (($vr['SECTION'] ?? '') === 'FUND_LEVEL') {
                    $amc = $vr['AMC'] ?? '';
                    $fundName = $vr['Fund_Name'] ?? '';
                    $folioNo = $vr['Folio_No'] ?? '';
                    $isin = $vr['ISIN'] ?? '';
                    
                    $key = trim($amc) . '|' . trim($isin) . '|' . trim($fundName) . '|' . trim($folioNo);
                    $validStatus[$key] = $vr['Unit_Status'] ?? 'UNKNOWN';
                    
                    $isin = $isinLookup[$key] ?? '';
                    if(empty($isin)) {
                        $legacyKey = trim($amc) . '|' . trim($fundName) . '|' . trim($folioNo);
                        $isin = $isinLookup[$legacyKey] ?? '';
                    }
                    
                    $open = $vr['Opening_Balance'] ?? 0;
                    $repClose = $vr['Reported_Closing'] ?? 0;
                    $calcClose = $vr['Calculated_Closing'] ?? 0;
                    $diff = $vr['Unit_Difference'] ?? 0;
                    $status = $vr['Unit_Status'] ?? 'UNKNOWN';
                    
                    $db->insertCasSummary($ucc, $amc, $fundName, $folioNo, $isin, $open, $repClose, $calcClose, $diff, $status);
                }
            }
        }

        // 2. Insert Transactions
        $transactions_inserted = 0;
        
        if (!empty($data['all_transactions'])) {
            foreach ($data['all_transactions'] as $txn) {
                $amc = $txn['AMC'] ?? '';
                $scheme_name = $txn['Fund_Name'] ?? '';
                $folio_num = $txn['Folio_No'] ?? '';
                $isin = $txn['ISIN'] ?? '';
                
                $key = trim($amc) . '|' . trim($isin) . '|' . trim($scheme_name) . '|' . trim($folio_num);
                
                // Enforce manual selection from frontend
                if (!isset($validKeysMap[$key])) {
                    // Fallback to old key format just in case
                    $oldKey = trim($amc) . '|' . trim($scheme_name) . '|' . trim($folio_num);
                    if (!isset($validKeysMap[$oldKey])) continue;
                    $key = $oldKey;
                }
                
                $unitStatus = $validStatus[$key] ?? 'UNKNOWN';
                
                $validation_status = 'VALIDATED';
                if ($unitStatus === 'MISMATCH') {
                    $validation_status = 'Unit Mismatch';
                } elseif ($unitStatus === 'SLIGHT_DIFFERENCE') {
                    $validation_status = 'Slight Difference';
                } elseif ($unitStatus !== 'OK' && $unitStatus !== 'UNKNOWN') {
                    $validation_status = $unitStatus; // fallback for other non-OK statuses
                }

                $transaction_type = $txn['Transaction_Type'] ?? '';
                
                // Filter out non-financial / system transactions to avoid flooding the table
                $system_types = [
                    'ADDRESS_UPDATED', 'NOMINEE_UPDATED', 'BANK_MANDATE_UPDATED', 
                    'KYC_UPDATED', 'KYC_FAILED', 'KYC_DEACTIVATED',
                    'SIP_REGISTERED', 'STP_REGISTERED', 'SIP_INVALID', 'STP_INVALID', 
                    'CLOSING', 'TRANSMISSION_IN', 'DOB_UPDATE', 
                    'PAN_CHANGE_REJECT', 'PAN_CHANGED', 'FOLIO_UNLOCK', 'FOLIO_LOCK', 
                    'BANK_PAYOUT_UPDATED', 'CHANGE_OF_BROKER'
                ];

                if (!empty($txn['Is_System']) || in_array($transaction_type, $system_types)) {
                    continue;
                }

                $rawDate = $txn['Date'] ?? $txn['date'] ?? null;
                $date = null;
                if (!empty($rawDate) && $rawDate !== 'NaT') {
                    $ts = is_numeric($rawDate) ? intval((float)$rawDate / 1000) : strtotime($rawDate);
                    if ($ts !== false && $ts > 0) {
                        $date = date("Y-m-d", $ts);
                    }
                }

                $amount = $txn['Amount'] ?? 0;
                $units = $txn['Units'] ?? 0;
                
                // Skip UNKNOWN transactions that have no financial value
                if ($transaction_type === 'UNKNOWN' && empty($amount) && empty($units)) {
                    continue;
                }
                
                $nav = $txn['NAV'] ?? 0;
                $description = substr($txn['Original_Description'] ?? '', 0, 250);
                $advisor_arn = $txn['Advisor'] ?? null;
                $isin = $txn['ISIN'] ?? null;

                if ($db->insertCasTransaction($ucc, $folio_num, $scheme_name, $amc, $transaction_type, $date, $amount, $units, $nav, $description, $advisor_arn, $isin, $validation_status)) {
                    $transactions_inserted++;
                }
            }
        }

        // 3. Clean Up Temp Json
        @unlink($tempJsonPath);

        echo json_encode([
            'success' => true, 
            'message' => "Successfully parsed and saved {$transactions_inserted} transactions for UCC: {$ucc}."
        ]);
        exit;
    }
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-file-invoice me-2"></i>Upload CAS Statements</h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Local Parser Executable:</strong> Since the server environment does not support python packages, please run the CAS parser locally to generate the Excel file, then upload it here.
                        <br>
                        <a href="cas_parser/CAS-Parser-App/dist/LITM_CAS_Parser.zip" class="btn btn-sm btn-primary mt-2" download>
                            <i class="fas fa-download me-1"></i> Download LITM_CAS_Parser.zip
                        </a>
                    </div>

                    <div id="statusDisplay"></div>

                    <form id="casUploadForm" enctype="multipart/form-data">
                        <div class="row g-3 align-items-end mb-4">
                            <div class="col-md-5">
                                <label for="custUCC" class="form-label fw-bold"><i class="fas fa-user me-1"></i> Customer UCC / Name *</label>
                                <div class="autocomplete-container" style="position: relative; width: 100%;">
                                    <input type="text" class="form-control" id="custUCC" placeholder="Enter UCC or Name" autocomplete="off" required>
                                    <div id="autocomplete-list-custUCC" style="display:none; position: absolute; top: 100%; left: 0; width: 100%; border: 1px solid #ddd; background-color: white; max-height: 250px; overflow-y: auto; z-index: 1050; border-radius: 0 0 8px 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Selected Customer</label>
                                <div class="form-control bg-light text-muted" id="selectedCustomerName">Not selected</div>
                                <input type="hidden" id="selectedCustomerNameHidden" name="name">
                                <input type="hidden" id="selectedUCC" name="ucc">
                                <input type="hidden" id="selectedPAN" name="pan">
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-secondary w-100 py-2 fw-bold" id="clearCustomerBtn" onclick="clearCustomerSelection()">
                                    <i class="fas fa-eraser me-1"></i> Clear
                                </button>
                            </div>
                        </div>

                        <div id="fileUploadGroup" style="display: none;">
                            <hr class="mb-4 text-muted">
                            <div class="mb-4">
                                <label class="form-label fw-bold"><i class="fas fa-file-excel me-1"></i> Select Parsed CAS Excel File *</label>
                                <input type="file" class="form-control" id="casFile" name="cas_file" accept=".xlsx, .xls, .csv" required>
                                <div class="form-text text-info"><small>Only Excel format is supported (.xlsx, .xls, .csv).</small></div>
                            </div>

                            <div class="text-center mt-4" id="submitGroup" style="display: none;">
                                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold" id="btnSubmit">
                                    <i class="fas fa-cloud-upload-alt me-2"></i> Upload & Process
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="innerProcessing" style="display: none;" class="text-center mt-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 fw-bold text-primary">Uploading and Processing CAS Statement... Please wait.</p>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-list me-2"></i>Uploaded CAS Statements</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadCasList()"><i class="fas fa-sync-alt"></i> Refresh</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-hover table-striped mb-0" id="casListTable">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th>UCC</th>
                                    <th>Name</th>
                                    <th>PAN Number</th>
                                    <th>Last Updated Date</th>
                                    <th>File Name Saved</th>
                                </tr>
                            </thead>
                            <tbody id="casListBody">
                                <tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#autocomplete-list-custUCC div {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}
#autocomplete-list-custUCC div:hover {
    background-color: #f8f9fa;
}
</style>

<script>
$(document).ready(function() {
    $('#loader').hide();

    // Load the initial table data
    loadCasList();

    // Autocomplete logic
    $('#custUCC').on('input', function() {
        const val = $(this).val().toLowerCase();
        const list = $('#autocomplete-list-custUCC');
        
        if (val.length < 2) {
            list.hide();
            return;
        }
        
        $.get('getUCClistwithname.php')
            .done(function(data) {
                if (typeof data === 'string') {
                    try { data = JSON.parse(data); } catch (e) { data = []; }
                }
                if (!Array.isArray(data)) { data = []; }
                
                const matches = data.filter(entry => 
                    (entry.UCC && entry.UCC.toLowerCase().includes(val)) || 
                    (entry.name && entry.name.toLowerCase().includes(val))
                );
                
                list.empty();
                if (matches.length === 0) {
                    list.hide();
                    return;
                }
                
                matches.forEach(entry => {
                    list.append(`<div onclick="selectCustomer('${entry.UCC}')">${entry.UCC} - ${escapeHtml(entry.name)}</div>`);
                });
                list.show();
            })
            .fail(function(err) {
                console.error('Error loading customer list:', err);
            });
    });

    // Close autocomplete on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.autocomplete-container').length) {
            $('#autocomplete-list-custUCC').hide();
        }
    });

    // Show submit button when file is selected
    $('#casFile').change(function() {
        if ($(this).val()) {
            $('#submitGroup').fadeIn();
        } else {
            $('#submitGroup').hide();
        }
    });

    // Form submission via AJAX
    $('#casUploadForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'upload_cas');
        
        const statusDiv = $('#statusDisplay');
        statusDiv.empty();
        
        $('#innerProcessing').show();
        $('#casUploadForm').hide();

        $.ajax({
            url: 'upload_cas_statement.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response) {
                $('#innerProcessing').hide();
                
                if (!response.success) {
                    $('#casUploadForm').show();
                    statusDiv.html(`
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i> ${response.message || 'Unknown error occurred'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    loadCasList(); // Refresh to show saved file
                    if(response.raw) console.error('Raw Output:', response.raw);
                    return;
                }

                // Success! Build the parsing validation UI
                const data = response.parsed_data;
                const validation = data.validation_report || [];
                const fundLevelRows = validation.filter(r => r.SECTION === 'FUND_LEVEL');
                const numFunds = fundLevelRows.length;
                const numOK = fundLevelRows.filter(r => r.Unit_Status === 'OK').length;
                const numMismatch = numFunds - numOK;

                const mergedRows = fundLevelRows;

                if (numFunds === 0) {
                    statusDiv.html(`
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i> PDF saved successfully, but no valid funds or transactions were found in the parser.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    loadCasList();
                    $('#casUploadForm').show();
                    $('#casFile').val('');
                    $('#submitGroup').hide();
                    return;
                }

                const numSlight = fundLevelRows.filter(r => r.Unit_Status === 'SLIGHT_DIFFERENCE').length;
                const numHardMismatch = fundLevelRows.filter(r => r.Unit_Status === 'MISMATCH').length;
                
                let summaryClass = (numHardMismatch === 0) ? 'text-success' : 'text-warning text-dark';
                let summaryText = `${numFunds} funds parsed — `;
                if (numHardMismatch === 0 && numSlight === 0) {
                    summaryText += `<span class="text-success">All ${numOK} OK</span>`;
                } else {
                    let errs = [];
                    if (numHardMismatch > 0) errs.push(`<span class="text-danger">${numHardMismatch} unit mismatches</span>`);
                    if (numSlight > 0) errs.push(`<span class="text-warning text-dark">${numSlight} slight differences</span>`);
                    summaryText += errs.join(' and ');
                    summaryText += `. Review carefully before saving.`;
                }

                let resultHtml = `
                    <div class="card mt-3 shadow-sm border-0 bg-light">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><i class="fas fa-clipboard-check me-2"></i> Parsing Summary</h5>
                            <p class="lead ${summaryClass} mb-3 fw-bold">${summaryText}</p>
                            <div class="d-flex gap-3">
                                <button class="btn btn-primary flex-fill fw-bold" id="saveSelectedBtn">
                                    <i class="fas fa-save me-2"></i> Save Selected Funds
                                </button>
                                <button class="btn btn-secondary fw-bold" id="cancelParsingBtn">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4 border-0 shadow-sm">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-list-check me-2"></i> Integrated Fund Validation</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" style="font-size:12px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center"><input type="checkbox" id="selectAllCb"></th>
                                            <th>AMC</th>
                                            <th>Fund Name</th>
                                            <th>Folio No</th>
                                            <th title="Unit difference (Reported vs Calculated)">Unit Diff (Rep vs Calc)</th>
                                            <th title="Unit balance continuity check">Unit Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${mergedRows.map(row => {
                                            const isUnitOk = row.Unit_Status === 'OK' || row.Unit_Status === 'SLIGHT_DIFFERENCE';
                                            
                                            // Pre-check row if it's "clean" enough
                                            const defaultCheck = isUnitOk ? 'checked' : '';
                                            
                                            let unitBadge = row.Unit_Status === 'OK' ? 'text-success fw-bold' : (row.Unit_Status === 'SLIGHT_DIFFERENCE' ? 'text-warning text-dark fw-bold' : 'text-danger fw-bold');
                                            let displayStatus = row.Unit_Status === 'SLIGHT_DIFFERENCE' ? 'Slight Difference' : (row.Unit_Status || 'N/A');
                                            let diffText = row.Unit_Difference !== undefined && row.Unit_Difference !== null ? Number(row.Unit_Difference).toFixed(4) : 'N/A';
                                            
                                            let rowKey = escapeHtml(row.AMC) + '|' + escapeHtml(row.ISIN || '') + '|' + escapeHtml(row.Fund_Name) + '|' + escapeHtml(row.Folio_No);
                                            
                                            return `
                                                <tr>
                                                    <td class="text-center">
                                                        <input type="checkbox" class="fund-cb" value="${rowKey}" ${defaultCheck}>
                                                    </td>
                                                    <td>${escapeHtml(row.AMC)}</td>
                                                    <td style="max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(row.Fund_Name)}">${escapeHtml(row.Fund_Name)}</td>
                                                    <td>${escapeHtml(row.Folio_No)}</td>
                                                    <td>${diffText}</td>
                                                    <td class="${unitBadge}">${escapeHtml(displayStatus)}</td>
                                                </tr>
                                            `;
                                        }).join('') || '<tr><td colspan="6" class="text-center">No fund data available</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;

                statusDiv.html(resultHtml).show();

                // Handle select all checkbox
                $('#selectAllCb').on('change', function() {
                    $('.fund-cb').prop('checked', $(this).prop('checked'));
                });

                // Set select all state initially based on checks
                if ($('.fund-cb:not(:checked)').length === 0 && $('.fund-cb').length > 0) {
                    $('#selectAllCb').prop('checked', true);
                }

                // Bind Buttons
                $('#saveSelectedBtn').click(() => {
                    const selectedKeys = [];
                    $('.fund-cb:checked').each(function() {
                        selectedKeys.push($(this).val());
                    });
                    
                    if (selectedKeys.length === 0) {
                        alert('Please select at least one fund to save.');
                        return;
                    }
                    
                    saveParsedCas(selectedKeys, data.tempJsonPath);
                });
                
                $('#cancelParsingBtn').click(() => {
                    // Clean up temporary JSON file
                    $.post('upload_cas_statement.php', { action: 'delete_temp_cas', tempJsonPath: data.tempJsonPath });
                    
                    statusDiv.empty();
                    $('#casUploadForm').show();
                    $('#casFile').val('');
                    $('#submitGroup').hide();
                });
            },
            error: function(xhr, status, error) {
                $('#innerProcessing').hide();
                $('#casUploadForm').show();
                $('#statusDisplay').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> System error occurred during upload.</div>').show();
                console.error(error);
            }
        });
    });
});

function saveParsedCas(selectedKeys, tempJsonPath) {
    $('#innerProcessing').find('p').text('Saving parsed data to database... Please wait.');
    $('#innerProcessing').show();
    $('#statusDisplay').hide();

    $.ajax({
        url: 'upload_cas_statement.php',
        type: 'POST',
        data: {
            action: 'save_parsed_cas',
            selected_keys: JSON.stringify(selectedKeys),
            ucc: $('#selectedUCC').val(),
            name: $('#selectedCustomerNameHidden').val(),
            pan: $('#selectedPAN').val(),
            tempJsonPath: tempJsonPath
        },
        dataType: 'json',
        success: function(response) {
            $('#innerProcessing').hide();
            $('#statusDisplay').show();
            if (response.success) {
                $('#statusDisplay').html(`<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                $('#casUploadForm').show();
                $('#casFile').val('');
                $('#submitGroup').hide();
                loadCasList();
            } else {
                $('#statusDisplay').html(`<div class="alert alert-danger alert-dismissible"><i class="fas fa-exclamation-circle me-2"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                $('#casUploadForm').show();
            }
        },
        error: function(xhr) {
            $('#innerProcessing').hide();
            $('#statusDisplay').show().html(`<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> Error saving data: ${xhr.statusText}</div>`);
            $('#casUploadForm').show();
        }
    });
}

function loadCasList() {
    const tbody = $('#casListBody');
    tbody.html('<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading data...</td></tr>');
    
    $.ajax({
        url: 'upload_cas_statement.php',
        type: 'POST',
        data: { action: 'get_cas_list' },
        dataType: 'json',
        success: function(response) {
            tbody.empty();
            if (response.success && response.data && response.data.length > 0) {
                response.data.forEach(item => {
                    // Prefer the database table's name/pan if populated, fallback to users table
                    const name = item.client_name || item.PRIMARY_HOLDER_NAME || '-';
                    const pan = item.pan_no || item.PRIMARY_HOLDER_PAN || '-';
                    const date = item.uploaded_date ? new Date(item.uploaded_date).toLocaleString() : '-';
                    const fileName = item.cas_file ? item.cas_file.split('/').pop() : '-';
                    
                    tbody.append(`
                        <tr>
                            <td><strong>${escapeHtml(item.CLIENT_CODE)}</strong></td>
                            <td>${escapeHtml(name)}</td>
                            <td>${escapeHtml(pan)}</td>
                            <td>${escapeHtml(date)}</td>
                            <td><span class="badge bg-secondary">${escapeHtml(fileName)}</span></td>
                        </tr>
                    `);
                });
            } else {
                tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">No CAS statements uploaded yet.</td></tr>');
            }
        },
        error: function() {
            tbody.html('<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fas fa-exclamation-circle me-1"></i> Failed to load data.</td></tr>');
        }
    });
}

function selectCustomer(ucc) {
    $('#autocomplete-list-custUCC').hide();
    
    // Fetch user details to auto-fill name and extract PAN as the default PDF password
    $.ajax({
        url: 'upload_cas_statement.php',
        type: 'POST',
        data: { action: 'get_user_info', ucc: ucc },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#custUCC').val(ucc + ' - ' + response.name);
                $('#selectedCustomerName').removeClass('text-muted').addClass('text-dark fw-bold').text(response.name);
                $('#selectedCustomerNameHidden').val(response.name);
                $('#selectedUCC').val(ucc);
                $('#selectedPAN').val(response.pan);
                
                $('#fileUploadGroup').fadeIn();
            } else {
                alert('User details could not be retrieved.');
            }
        },
        error: function() {
            alert('Error connecting to the server to fetch user details.');
        }
    });
}

function clearCustomerSelection() {
    $('#custUCC').val('');
    $('#selectedCustomerName').removeClass('text-dark fw-bold').addClass('text-muted').text('Not selected');
    $('#selectedCustomerNameHidden').val('');
    $('#selectedUCC').val('');
    $('#selectedPAN').val('');
    $('#pdfPassword').val('');
    $('#casFile').val('');
    
    $('#fileUploadGroup, #passwordGroup, #submitGroup').hide();
    $('#statusDisplay').empty();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}
</script>
<style>
.ui-autocomplete {
    position: absolute;
    z-index: 1050 !important;
    max-height: 200px;
    overflow-y: auto;
    overflow-x: hidden;
    background-color: #fff;
    border: 1px solid #ccc;
}
</style>

