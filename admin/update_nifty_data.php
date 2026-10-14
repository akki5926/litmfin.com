<?php
require_once('auth_guard.php');

ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once 'database.php';

class NiftyDataUpdater {
    private $db;
    private $logsDir = __DIR__ . '/logs/';
    private $logFile;
    
    public function __construct() {
        $this->db = new MySQLDB();
        
        $this->logFile = $this->logsDir . 'nifty_update_log.txt';
        
        // Create logs directory if it doesn't exist
        if (!file_exists($this->logsDir)) {
            @mkdir($this->logsDir, 0777, true);
        }
    }
    
    /**
     * Handle CSV data submission via POST
     */
    private function handleCsvUpload() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_csv') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['csv_file']['tmp_name'];
                $inserted = 0;
                $updated = 0;
                $failed = 0;

                if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                    $headerSkipped = false;
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (!$headerSkipped) {
                            if (isset($data[0]) && stripos($data[0], 'Date') !== false) {
                                $headerSkipped = true;
                            }
                            continue;
                        }

                        if (count($data) >= 5) {
                            $dateStr = trim($data[0]);
                            $closeVal = str_replace(',', '', trim($data[4]));
                            
                            if (empty($dateStr) || empty($closeVal)) continue;

                            $dateObj = DateTime::createFromFormat('d-M-y', $dateStr);
                            if (!$dateObj) $dateObj = DateTime::createFromFormat('d-M-Y', $dateStr);
                            if (!$dateObj) {
                                $ts = strtotime($dateStr);
                                if ($ts) $dateObj = (new DateTime())->setTimestamp($ts);
                            }

                            if ($dateObj && is_numeric($closeVal)) {
                                $formattedDate = $dateObj->format('Y-m-d');
                                $nifty500Val = floatval($closeVal);
                                
                                $result = $this->db->insertOrUpdateNifty500($formattedDate, $nifty500Val);
                                if ($result['success']) {
                                    if ($result['affected_rows'] > 0) {
                                        if ($result['affected_rows'] == 1) $inserted++;
                                        else $updated++;
                                    } else {
                                        $updated++;
                                    }
                                } else {
                                    $failed++;
                                }
                            } else {
                                $failed++;
                            }
                        }
                    }
                    fclose($handle);

                    $this->log("CSV Upload completed. Inserted: {$inserted}, Updated: {$updated}, Failed/Skipped: {$failed}");
                    echo json_encode(array(
                        'success' => true,
                        'message' => "CSV Processed! Inserted: {$inserted}, Updated: {$updated}, Failed/Skipped: {$failed}"
                    ));
                    exit;
                }
            }
            echo json_encode(array('success' => false, 'message' => 'File upload failed or no file selected.'));
            exit;
        }
    }

    /**
     * Log message to file
     */
    private function log($message) {
        $timestamp = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        static $firstLog = true;
        
        if ($firstLog) {
            file_put_contents($this->logFile, $logMessage);
            $firstLog = false;
        } else {
            file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        }
    }
    
    /**
     * Check if Python script exists and is executable
     */
    private function checkPythonScript() {
        $scriptPath = 'fetch_nifty50_yahooquery.py';
        
        if (!file_exists($scriptPath)) {
            $this->log("ERROR: Python script not found at: " . realpath($scriptPath));
            return false;
        }
        
        $this->log("Python script found at: " . realpath($scriptPath));
        return true;
    }
    
    private function getPythonPath() {
        // Try different Python commands
        $pythonCommands = ['python3', 'python', 'py'];
        
        foreach ($pythonCommands as $cmd) {
            exec($cmd . ' --version 2>&1', $output, $returnCode);
            if ($returnCode === 0) {
                $this->log("Found Python executable: {$cmd}");
                return $cmd;
            }
        }
        
        return false;
    }
    
    /**
     * Fetch Nifty data using Python script with better error handling
     */
    private function fetchNiftyDataFromPython() {
        $this->log("=== Fetching Nifty Data from Python Script ===");
        
        // Check if Python script exists
        if (!$this->checkPythonScript()) {
            return array('success' => false, 'error' => 'Python script not found', 'inserted' => 0, 'updated' => 0, 'failed' => 0);
        }
        
        // Find Python executable
        $pythonCmd = $this->getPythonPath();
        if (!$pythonCmd) {
            $this->log("ERROR: Python not found on system");
            return array('success' => false, 'error' => 'Python not found on system', 'inserted' => 0, 'updated' => 0, 'failed' => 0);
        }
        
        // Run Python script with full error capture
        $command = $pythonCmd . ' fetch_nifty50_yahooquery.py 2>&1';
        $this->log("Executing: {$command}");
        
        exec($command, $output, $returnVar);
        
        // Log Python script output
        if (!empty($output)) {
            $this->log("Python script output:");
            foreach ($output as $line) {
                $this->log("  " . $line);
            }
        }
        
        $this->log("Python return code: {$returnVar}");
        
        $inserted = 0;
        $updated = 0;
        $failed = 0;
        $jsonFile = 'temp_nifty50_data.json';
        
        if ($returnVar === 0 && file_exists($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $data = json_decode($jsonContent, true);
            
            if ($data && is_array($data) && count($data) > 0) {
                $this->log("Successfully parsed " . count($data) . " records from JSON");
                
                foreach ($data as $row) {
                    $date = isset($row['trade_date']) ? $row['trade_date'] : null;
                    $n50 = isset($row['nifty50']) && $row['nifty50'] !== null ? floatval($row['nifty50']) : null;
                    $n500 = isset($row['nifty500']) && $row['nifty500'] !== null ? floatval($row['nifty500']) : null;
                    
                    if ($date) {
                        $result = $this->db->insertOrUpdateNiftyData($date, $n50, $n500);
                        if ($result['success']) {
                            if ($result['affected_rows'] > 0) {
                                if ($result['affected_rows'] == 1) {
                                    $inserted++;
                                } else {
                                    $updated++;
                                }
                            } else {
                                // No change - record already exists with same values
                                $updated++;
                            }
                        } else {
                            $failed++;
                            $this->log("ERROR: Failed to insert/update date {$date}: " . $result['error']);
                        }
                    } else {
                        $failed++;
                        $this->log("ERROR: Missing trade_date in record");
                    }
                }
            } else {
                $this->log("ERROR: Invalid or empty JSON data");
                if (file_exists($jsonFile)) {
                    $this->log("JSON content: " . substr($jsonContent, 0, 500));
                }
            }
            unlink($jsonFile);
        } else {
            $this->log("ERROR: Python script failed. No valid JSON file generated.");
            if (file_exists($jsonFile)) {
                $this->log("JSON file exists but may be invalid");
                unlink($jsonFile);
            }
        }
        
        $this->log("Python fetch completed. Inserted: {$inserted}, Updated: {$updated}, Failed: {$failed}");
        
        return array(
            'success' => ($inserted > 0 || $updated > 0),
            'inserted' => $inserted,
            'updated' => $updated,
            'failed' => $failed,
            'return_code' => $returnVar
        );
    }
    

    
    /**
     * Handle manual data submission via POST
     */
    private function handleManualSave() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_manual') {
            $dates = isset($_POST['dates']) ? $_POST['dates'] : [];
            $nifty50 = isset($_POST['nifty50']) ? $_POST['nifty50'] : [];
            $nifty500 = isset($_POST['nifty500']) ? $_POST['nifty500'] : [];
            
            $manualSaved = 0;
            $manualUpdated = 0;
            $manualFailed = 0;
            
            foreach ($dates as $index => $date) {
                $val50 = !empty($nifty50[$index]) ? floatval($nifty50[$index]) : null;
                $val500 = !empty($nifty500[$index]) ? floatval($nifty500[$index]) : null;
                
                if ($val50 !== null || $val500 !== null) {
                    $result = $this->db->insertOrUpdateManualNiftyData($date, $val50, $val500);
                    if ($result['success']) {
                        if ($result['affected_rows'] > 0) {
                            if ($result['affected_rows'] == 1) {
                                $manualSaved++;
                            } else {
                                $manualUpdated++;
                            }
                        } else {
                            $manualSaved++;
                        }
                    } else {
                        $manualFailed++;
                        $this->log("ERROR: Manual save failed for date {$date}: " . $result['error']);
                    }
                }
            }
            
            $this->log("Manual save completed. Inserted: {$manualSaved}, Updated: {$manualUpdated}, Failed: {$manualFailed}");
            
            echo json_encode(array(
                'success' => true,
                'inserted' => $manualSaved,
                'updated' => $manualUpdated,
                'failed' => $manualFailed,
                'message' => "Manual Update Success! Saved: {$manualSaved}, Updated: {$manualUpdated}, Failed: {$manualFailed}"
            ));
            exit;
        }
    }
    
    /**
     * Get database status and missing dates
     */
    private function getDatabaseStatus() {
        return $this->db->getNiftyDatabaseStatus();
    }
    
    /**
     * Generate missing weekdays for manual entry
     */
    private function getMissingDates($lastDate) {
        $missingDates = [];
        
        if ($lastDate) {
            $startDate = new DateTime($lastDate);
            $startDate->modify('+1 day');
        } else {
            $startDate = new DateTime('2024-01-01');
        }
        
        $endDate = new DateTime();
        
        while ($startDate <= $endDate) {
            if ($startDate->format('N') <= 5) {
                $missingDates[] = $startDate->format('Y-m-d');
            }
            $startDate->modify('+1 day');
        }
        
        return $missingDates;
    }
    
    /**
     * Display the main page content
     */
    public function displayPage() {
        // Handle manual save AJAX request
        $this->handleManualSave();
        
        // Handle CSV Upload AJAX request
        $this->handleCsvUpload();
        
        // Handle auto fetch request
        if (isset($_GET['fetch_auto'])) {
            $result = $this->fetchNiftyDataFromPython();
            $this->displayAutoResults($result);
            return;
        }
        
        // Display main form
        $this->displayMainForm();
    }
    
    /**
     * Display auto fetch results (no back button, stays on same page)
     */
    private function displayAutoResults($result) {
        $isSuccess = $result['success'] || ($result['inserted'] > 0 || $result['updated'] > 0);
        
        if ($result['inserted'] == 0 && $result['updated'] > 0 && $result['failed'] == 0) {
            $alertClass = 'alert-info';
            $statusIcon = 'fa-info-circle';
            $titleMsg = 'Database is already up to date! Existing records verified.';
        } else {
            $alertClass = $isSuccess ? 'alert-success' : 'alert-warning';
            $statusIcon = $isSuccess ? 'fa-check-circle' : 'fa-exclamation-triangle';
            $titleMsg = $isSuccess ? 'Auto Fetch Completed!' : 'Auto Fetch Completed with Issues';
        }
        ?>
        <div id="autoFetchResult">
            <div class="alert <?php echo $alertClass; ?> alert-dismissible fade show" role="alert">
                <i class="fas <?php echo $statusIcon; ?>"></i>
                <strong><?php echo $titleMsg; ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            
            <div class="row text-center mb-4">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <h3 class="text-success"><?php echo $result['inserted']; ?></h3>
                        <small>Inserted</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <h3 class="text-warning"><?php echo $result['updated']; ?></h3>
                        <small>Updated</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <h3 class="text-danger"><?php echo $result['failed']; ?></h3>
                        <small>Failed / Skipped</small>
                    </div>
                </div>
            </div>
            
            <script>
            // Refresh the page content after 2 seconds to show updated data
            setTimeout(function() {
                loadPage('update_nifty_data.php');
            }, 2000);
            </script>
        </div>
        <?php
    }
    
    /**
     * Display main form with manual entry
     */
    private function displayMainForm() {
        $status = $this->getDatabaseStatus();
        $missingDates = $this->getMissingDates($status['last_date']);
        ?>
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Nifty Index Data Management</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-secondary">
                    <i class="fas fa-info-circle"></i>
                    Import Nifty 500 historical data.
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <button onclick="fetchAutoNifty()" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-cloud-download-alt"></i> Auto Fetch from Yahoo Finance
                        </button>
                    </div>
                </div>
                
                <div id="autoProgress" style="display:none;" class="mt-3 mb-3 text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <span class="ms-2">Fetching Nifty data from Yahoo Finance... This may take a few moments.</span>
                </div>
                
                <div id="autoFetchContainer"></div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-file-csv"></i> Manual CSV Upload (NSE Format)</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-secondary">
                    <i class="fas fa-info-circle"></i> If Yahoo Finance is delayed, you can download the <strong>Nifty 500</strong> historical CSV from <a href="https://www.nseindia.com/reports-indices-historical-index-data" target="_blank" class="text-primary fw-bold">NSE India</a> and upload it here.
                </div>
                <form id="csvNiftyForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_csv">
                    <div class="input-group mb-3">
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        <button type="button" id="btnUploadCsv" class="btn btn-success">
                            <i class="fas fa-upload"></i> Upload CSV
                        </button>
                    </div>
                </form>
                <div id="csv_status_msg"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Database Status & Manual Entry</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-secondary">
                    <strong>Database Status:</strong> Updated till <?php echo $status['last_date'] ?: 'None'; ?> | 
                    <strong>Total Records:</strong> <?php echo $status['total_records']; ?>
                </div>
                
                <?php if (empty($missingDates)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> All data is up to date including today!
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Missing data for <?php echo count($missingDates); ?> weekday(s). Please enter values below.
                        <br><small>(Note: Some weekdays might be market holidays, which do not have trading data. You can skip those or use the CSV upload above).</small>
                    </div>
                    
                    <form id="manualNiftyForm">
                        <input type="hidden" name="action" value="save_manual">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <!-- <th>Nifty 50</th> -->
                                        <th>Nifty 500</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($missingDates as $mDate): ?>
                                    <tr>
                                        <td>
                                            <?php echo $mDate; ?>
                                            <input type="hidden" name="dates[]" value="<?php echo $mDate; ?>">
                                        </td>
                                        <!-- <td>
                                            <input type="number" step="0.01" name="nifty50[]" class="form-control form-control-sm" placeholder="Enter Nifty 50">
                                        </td> -->
                                        <td>
                                            <input type="number" step="0.01" name="nifty500[]" class="form-control form-control-sm" placeholder="Enter Nifty 500">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" id="btnSaveManual" class="btn btn-success mt-3">
                            <i class="fas fa-save"></i> Update Missing Days
                        </button>
                    </form>
                    <div id="manual_status_msg" class="mt-3"></div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($status['recent_records'])): ?>
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Trade Date</th>
                                <!-- <th>Nifty 50</th> -->
                                <th>Nifty 500</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($status['recent_records'] as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['trade_date']); ?></td>
                                <!-- <td><?php echo $record['nifty50'] ? number_format($record['nifty50'], 2) : '-'; ?></td> -->
                                <td><?php echo $record['nifty500'] ? number_format($record['nifty500'], 2) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php $this->showLogs(); ?>
        
        <script>
        var niftyPageUrl = 'update_nifty_data.php';
        
        function fetchAutoNifty() {
            if (confirm('Fetch Nifty data from Yahoo Finance? This may take a few moments.')) {
                document.getElementById('autoProgress').style.display = 'block';
                document.getElementById('autoFetchContainer').innerHTML = '';
                
                var xhr = new XMLHttpRequest();
                xhr.open('GET', niftyPageUrl + '?fetch_auto=1', true);
                xhr.timeout = 300000;
                
                xhr.onload = function() {
                    document.getElementById('autoProgress').style.display = 'none';
                    if (xhr.status === 200) {
                        // Display results in the container without leaving the page
                        document.getElementById('autoFetchContainer').innerHTML = xhr.responseText;
                        // Scroll to results
                        document.getElementById('autoFetchContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        document.getElementById('autoFetchContainer').innerHTML = 
                            '<div class="alert alert-danger">Error fetching Nifty data (HTTP ' + xhr.status + ')</div>';
                    }
                };
                
                xhr.onerror = function() {
                    document.getElementById('autoProgress').style.display = 'none';
                    document.getElementById('autoFetchContainer').innerHTML = 
                        '<div class="alert alert-danger">Network error occurred while fetching Nifty data.</div>';
                };
                
                xhr.ontimeout = function() {
                    document.getElementById('autoProgress').style.display = 'none';
                    document.getElementById('autoFetchContainer').innerHTML = 
                        '<div class="alert alert-danger">Fetch timed out. The server took too long to respond.</div>';
                };
                
                xhr.send();
            }
        }
        
        function loadPage(url) {
            document.getElementById('main_content').innerHTML = '<div class="text-center mt-5"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div>';
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    document.getElementById('main_content').innerHTML = xhr.responseText;
                }
            };
            xhr.send();
        }
        
        $(document).ready(function() {
            $("#btnUploadCsv").click(function() {
                var $btn = $(this);
                var fileInput = $('input[name="csv_file"]')[0];
                if (fileInput.files.length === 0) {
                    alert("Please select a CSV file first.");
                    return;
                }

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
                var formData = new FormData($("#csvNiftyForm")[0]);
                
                $.ajax({
                    url: "update_nifty_data.php",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            $("#csv_status_msg").html('<div class="alert alert-success mt-2"><strong>Success!</strong> ' + response.message + '</div>');
                            setTimeout(function() { loadPage('update_nifty_data.php'); }, 2500);
                        } else {
                            $("#csv_status_msg").html('<div class="alert alert-danger mt-2"><strong>Error!</strong> ' + response.message + '</div>');
                            $btn.prop('disabled', false).html('<i class="fas fa-upload"></i> Upload CSV');
                        }
                    },
                    error: function() {
                        $("#csv_status_msg").html('<div class="alert alert-danger mt-2"><strong>Error!</strong> Failed to upload CSV. Please try again.</div>');
                        $btn.prop('disabled', false).html('<i class="fas fa-upload"></i> Upload CSV');
                    }
                });
            });

            $("#btnSaveManual").click(function() {
                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                
                $.ajax({
                    url: "update_nifty_data.php",
                    type: "POST",
                    data: $("#manualNiftyForm").serialize(),
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            $("#manual_status_msg").html(
                                '<div class="alert alert-success mt-2">' +
                                '<strong>Success!</strong> ' + response.message +
                                '</div>'
                            );
                            $("#btnSaveManual").hide();
                            $("#manualNiftyForm").css('opacity', '0.5').css('pointer-events', 'none');
                            setTimeout(function() {
                                loadPage('update_nifty_data.php');
                            }, 2000);
                        } else {
                            $("#manual_status_msg").html(
                                '<div class="alert alert-danger mt-2">' +
                                '<strong>Error!</strong> ' + response.message +
                                '</div>'
                            );
                            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Missing Days');
                        }
                    },
                    error: function(xhr, status, error) {
                        $("#manual_status_msg").html(
                            '<div class="alert alert-danger mt-2">' +
                            '<strong>Error!</strong> Failed to save data. Please try again.' +
                            '</div>'
                        );
                        $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Missing Days');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Show log file contents
     */
    private function showLogs() {
        $logContent = '';
        if (file_exists($this->logFile)) {
            $logs = file($this->logFile);
            $logs = array_slice($logs, -30);
            $logContent = implode('', array_reverse($logs));
        }
        
        if (!empty($logContent)) {
            echo '<div class="card mt-4">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0"><i class="fas fa-terminal"></i> Activity Logs</h6>
                    </div>
                    <div class="card-body p-0">
                        <pre style="max-height: 200px; overflow: auto; font-size: 11px; background: #1e1e1e; color: #d4d4d4; padding: 10px; margin: 0; border-radius: 0 0 8px 8px;">' . htmlspecialchars($logContent) . '</pre>
                    </div>
                  </div>';
        }
    }
}

// Main execution - only output content, no HTML wrapper
$updater = new NiftyDataUpdater();
$updater->displayPage();
?>