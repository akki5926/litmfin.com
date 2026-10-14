<?php
require_once('auth_guard.php');

ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once('database.php');


class NAVImporter {
    private $db;
    private $uploadDir = __DIR__ . '/uploads/'; // Directory to store uploaded files
    private $logsDir = __DIR__ . '/logs/';
    private $logFile;
    private $parameterLogFile;
    
    public function __construct() {
        $this->db = new MYSQLDB();
        
        $this->logFile = $this->logsDir . 'import_log.txt';
        $this->parameterLogFile = $this->logsDir . 'parameter_log.txt';
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            @mkdir($this->uploadDir, 0777, true);
        }
        
        // Create logs directory if it doesn't exist
        if (!file_exists($this->logsDir)) {
            @mkdir($this->logsDir, 0777, true);
        }
    }
    
    /**
     * Helper function to safely get array values (for PHP 5.6 compatibility)
     */
    private function getValue($array, $index, $default = '') {
        return isset($array[$index]) ? $array[$index] : $default;
    }
    
    /**
     * Handle file upload via AJAX
     */
    public function handleFileUpload() {
        $response = array('success' => false, 'message' => '', 'file' => '');
        
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file'];
            $fileName = time() . '_SIF_' . basename($file['name']);
            $targetPath = $this->uploadDir . $fileName;
            $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
            
            // Check if file is CSV or XLSX
            if ($fileType != 'csv' && $fileType != 'xlsx') {
                $response['message'] = 'Only CSV or XLSX files are allowed.';
                return $response;
            }
            
            // Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $response['message'] = 'File size too large. Maximum 5MB allowed.';
                return $response;
            }
            
            // Upload file
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $response['success'] = true;
                $response['message'] = 'File uploaded successfully.';
                $response['file'] = $targetPath;
            } else {
                $response['message'] = 'Failed to upload file.';
            }
        } else {
            $response['message'] = 'No file selected or upload error.';
        }
        
        return $response;
    }
    
    /**
     * Fetch and parse SIF NAV data directly from AMFI India website
     */
    public function fetchFromAMFI() {
        if (is_writable($this->logsDir)) @file_put_contents($this->logFile, '');
        if (is_writable($this->logsDir)) @file_put_contents($this->parameterLogFile, '');
        
        $startTime = microtime(true);
        $this->log("=== Fetching SIF NAV from AMFI India website ===");
        
        $url = 'https://www.amfiindia.com/sif/latest-nav';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($html === false || $httpCode != 200) {
            $this->log("ERROR: Failed to fetch AMFI page. HTTP: {$httpCode}, Error: {$curlError}");
            return array('success' => false, 'message' => "Failed to fetch data from AMFI website (HTTP {$httpCode}). Error: {$curlError}");
        }
        
        $this->log("Fetched AMFI page successfully. Size: " . strlen($html) . " bytes");
        
        // Parse the HTML table to extract SIF data
        $data = $this->parseAMFIHtml($html);
        
        if (empty($data)) {
            $this->log("No SIF data found in AMFI HTML page, falling back to SIF History API...");
            return $this->fetchSIFFromAMFIHistory();
        }
        
        $this->log("Parsed " . count($data) . " SIF records from AMFI website");
        
        // Import to database
        $result = $this->importToDatabase($data);
        
        $this->log("Import completed. Inserted: {$result['inserted']}, Updated: {$result['updated']}, Failed: {$result['failed']}");
        
        $duration = round(microtime(true) - $startTime, 2);
        
        return array(
            'success' => true,
            'message' => 'Fetched and imported from AMFI website successfully.',
            'result' => $result,
            'duration' => $duration,
            'source' => 'AMFI Website'
        );
    }
    
    /**
     * Fetch MF NAV data from AMFI NAVAll.txt (for Mutual Funds)
     * Format: scheme_code;isin1;isin2;scheme_name;nav;date (semicolon-separated)
     */
    public function fetchMFFromAMFI() {
        if (is_writable($this->logsDir)) @file_put_contents($this->logFile, '');
        if (is_writable($this->logsDir)) @file_put_contents($this->parameterLogFile, '');
        
        $startTime = microtime(true);
        $this->log("=== Fetching MF NAV from AMFI (NAVAll.txt) ===");
        
        $url = 'https://www.amfiindia.com/spages/NAVAll.txt';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($content === false || $httpCode != 200) {
            $this->log("ERROR: Failed to fetch AMFI NAVAll.txt. HTTP: {$httpCode}, Error: {$curlError}");
            return array('success' => false, 'message' => "Failed to fetch MF NAV from AMFI (HTTP {$httpCode}). Error: {$curlError}");
        }
        
        $lines = explode("\n", $content);
        $this->log("Fetched NAVAll.txt successfully. Lines: " . count($lines));
        
        $inserted = 0;
        $updated = 0;
        $alreadyExist = 0;
        $failed = 0;
        $total = 0;
        $now = time();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Must contain ;INF (has ISIN)
            if (strpos($line, ';INF') === false) continue;
            
            // Skip Direct plans
            if (stripos($line, 'Direct') !== false) continue;
            
            $dataArray = array_map('trim', explode(';', $line));
            $fieldCount = count($dataArray);
            if ($fieldCount < 6) continue;
            
            if ($fieldCount >= 8) {
                // AMFI NAVAll.txt standard 8-field format:
                // Scheme Code;ISIN Div Payout/ ISIN Growth;ISIN Div Reinvestment;Scheme Name;Plan;Option;Net Asset Value;Date
                $scheme_code = $dataArray[0];
                $isin1 = $dataArray[1];
                $isin2 = $dataArray[2];
                $scheme_name = $dataArray[3];
                $plan = $dataArray[4];
                $option = $dataArray[5];
                $nav = $dataArray[6];
                $nav_dateStr = $dataArray[7];
            } else {
                // Fallback format for lines with fewer fields
                $scheme_code = $dataArray[0];
                $isin1 = $dataArray[1];
                $isin2 = ($fieldCount > 6) ? $dataArray[2] : '';
                $scheme_name = ($fieldCount > 6) ? $dataArray[3] : $dataArray[2];
                $nav = $dataArray[$fieldCount - 2];
                $nav_dateStr = $dataArray[$fieldCount - 1];
            }

            if (empty($nav) || !is_numeric($nav)) continue;
            
            // Parse date format d-M-Y (e.g. 24-Aug-2026)
            $d = date_parse_from_format('d-M-Y', $nav_dateStr);
            if (!$d || !$d['year']) continue;
            $dateFormatted = sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']);
            $nav_date = strtotime($dateFormatted);
            if ($nav_date === false) continue;
            
            $datediff = ceil(($now - $nav_date) / 86400);
            if ($datediff > 90) continue;
            
            $isinList = array();
            if (!empty($isin1) && strlen($isin1) > 10 && strpos($isin1, 'INF') === 0) {
                $isinList[] = array('isin' => $isin1, 'name' => $scheme_name);
            }
            if (!empty($isin2) && strlen($isin2) > 10 && strpos($isin2, 'INF') === 0 && $isin2 !== $isin1) {
                $reinvestName = (stripos($scheme_name, 'Reinvest') === false) ? $scheme_name . ' (Reinvest)' : $scheme_name;
                $isinList[] = array('isin' => $isin2, 'name' => $reinvestName);
            }

            if (empty($isinList)) continue;
            
            foreach ($isinList as $item) {
                $total++;
                try {
                    $retResult = $this->db->updateNAV($item['isin'], $nav, $dateFormatted, $scheme_code, $item['name']);
                    if ($retResult == 'Inserted') {
                        $inserted++;
                    } elseif ($retResult == 'Updated') {
                        $updated++;
                    } elseif ($retResult == 'already exist') {
                        $alreadyExist++;
                    } else {
                        $failed++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    $this->log("ERROR: MF AMFI - " . $e->getMessage());
                }
            }
        }
        
        $duration = round(microtime(true) - $startTime, 2);
        $this->log("MF AMFI Import done. Inserted: {$inserted}, Updated: {$updated}, Already Exist: {$alreadyExist}, Failed: {$failed}, Total: {$total}");
        
        return array(
            'success' => true,
            'message' => 'MF NAV imported from AMFI successfully.',
            'result' => array('inserted' => $inserted, 'updated' => $updated, 'already_exist' => $alreadyExist, 'failed' => $failed, 'total' => $total),
            'duration' => $duration,
            'source' => 'AMFI NAVAll.txt'
        );
    }
    
    /**
     * Fetch MF NAV data from NSE API (for Mutual Funds)
     */
    public function fetchMFFromNSE() {
        if (is_writable($this->logsDir)) @file_put_contents($this->logFile, '');
        if (is_writable($this->logsDir)) @file_put_contents($this->parameterLogFile, '');
        
        $startTime = microtime(true);
        $this->log("=== Fetching MF NAV from NSE API ===");
        
        try {
            require_once '../api/nseapiconnect.php';
            $nseService = new NseApiConnect();
            $payload = [
                        "file_type"=> "NAV"
                        ];
            $apiResult = $nseService->getMasterFromNse($payload);
            $httpCode = isset($apiResult['http_code']) ? $apiResult['http_code'] : 0;
            $response = isset($apiResult['response']) ? $apiResult['response'] : '';
            
            //error_log("[NSE Master API] Request - Payload: " . json_encode($payload) . " | HTTP Code: " . $httpCode . " | Response: " . (strlen($response) > 200 ? substr($response, 0, 200) . "..." : $response));
            
        } catch (Exception $e) {
            $this->log("ERROR: NSE API call failed - " . $e->getMessage());
            return array('success' => false, 'message' => 'NSE API Error: ' . $e->getMessage());
        }
        
        if (empty($response)) {
            $this->log("ERROR: Empty response from NSE API. HTTP: {$httpCode}");
            return array('success' => false, 'message' => "Empty response from NSE API (HTTP {$httpCode})");
        }
        
        $this->log("NSE API response received. Size: " . strlen($response) . " bytes");
        
        $lines = preg_split('/\r\n|\n|\r/', trim($response));
        $this->log("Parsed " . count($lines) . " lines from NSE response");

        $inserted = 0;
        $updated = 0;
        $alreadyExist = 0;
        $failed = 0;
        $total = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            
            $parts = explode('|', $line);
            
            if (count($parts) < 7) {
                $parts = array_pad($parts, 8, '');
            }
            
            $nav_dateStr = trim($parts[0]);
            $scheme_code = trim($parts[1]);
            $scheme_name = trim($parts[2]);
            $isin = trim($parts[5]);
            $nav = trim($parts[6]);

            if (empty($isin) || empty($nav)) continue;
            
            $dateFormatted = $this->formatDate($nav_dateStr);
            
            $total++;

            try {
                $retResult = $this->db->updateNAV($isin, $nav, $dateFormatted, $scheme_code, $scheme_name);
                
                if ($retResult == 'Inserted') {
                    $inserted++;
                } elseif ($retResult == 'Updated') {
                    $updated++;
                } elseif ($retResult == 'already exist') {
                    $alreadyExist++;
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                $failed++;
                $this->log("ERROR processing ISIN {$isin}: " . $e->getMessage());
            }
        }
        
        $duration = round(microtime(true) - $startTime, 2);
        $this->log("NSE MF Import done. Inserted: {$inserted}, Updated: {$updated}, Already Exist: {$alreadyExist}, Failed: {$failed}, Total Processed: {$total}, Duration: {$duration}s");
        
        return array(
            'success' => ($inserted > 0 || $updated > 0 || $alreadyExist > 0),
            'message' => "Imported MF NAV records from NSE. Inserted: {$inserted}, Updated: {$updated}.",
            'result' => array('inserted' => $inserted, 'updated' => $updated, 'already_exist' => $alreadyExist, 'failed' => $failed, 'total' => $total),
            'duration' => $duration,
            'source' => 'NSE API'
        );
    }

    /**
     * Fetch SIF NAV data from AMFI History Report (1 day previous)
     */
    public function fetchSIFFromAMFIHistory() {
        if (is_writable($this->logsDir)) @file_put_contents($this->logFile, '');
        if (is_writable($this->logsDir)) @file_put_contents($this->parameterLogFile, '');
        
        $startTime = microtime(true);
        
        $maxAttempts = 10;
        $attempt = 0;
        $currentDate = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $content = '';
        $dateStr = '';
        
        while ($attempt < $maxAttempts) {
            $dateStr = $currentDate->format('d-M-Y');
            $url = 'https://portal.amfiindia.com/SIF_DownloadNAVHistoryReport.aspx?frmdt=' . $dateStr;
            
            $this->log("=== Fetching SIF NAV History from AMFI for date: {$dateStr} ===");
            $this->log("URL: {$url}");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($content === false || $httpCode != 200) {
                $this->log("ERROR: Failed to fetch AMFI SIF History. HTTP: {$httpCode}, Error: {$curlError}");
                return array('success' => false, 'message' => "Failed to fetch SIF History from AMFI (HTTP {$httpCode}). Error: {$curlError}");
            }
            
            $isHtmlError = (strpos($content, '<html') !== false || strpos($content, '<HTML') !== false || strpos($content, '<!DOCTYPE') !== false || strpos($content, 'Welcome to AMFI!') !== false);
            $hasNoDataText = (strpos($content, 'No data found') !== false);
            
            if ($isHtmlError || $hasNoDataText || empty(trim($content))) {
                $this->log("No data found for {$dateStr} (HTML error or empty), trying previous date.");
                $currentDate->modify('-1 day');
                $attempt++;
            } else {
                break;
            }
        }
        
        if ($attempt >= $maxAttempts) {
            $this->log("No valid SIF data found in the last {$maxAttempts} days.");
            return array('success' => false, 'message' => "No valid SIF data found in the last {$maxAttempts} days. Check AMFI website.");
        }
        
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $this->log("Fetched successfully. Lines: " . count($lines));
        
        $data = array();
        $rowCount = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $delimiter = (strpos($line, ';') !== false) ? ';' : ',';
            $parts = str_getcsv($line, $delimiter);
            $fieldCount = count($parts);
            
            if ($fieldCount < 5) continue;
            
            if (!is_numeric($parts[0]) && stripos($parts[0], 'SIF-') !== 0 && stripos($parts[0], 'INF') !== 0) {
                if (stripos($parts[0], 'Scheme') !== false || stripos($parts[1], 'Scheme') !== false) continue;
            }
            
            $rowCount++;
            
            if ($fieldCount >= 8) {
                // AMFI SIF History CSV format:
                // Scheme Code;NAV Name;Plan;Option;ISIN Div Payout/ISIN Growth;ISIN Div Reinvestment;Net Asset Value;Date
                $schemeCode = trim($parts[0]);
                $schemeName = trim($parts[1]);
                $plan = trim($parts[2]);
                $option = trim($parts[3]);
                $colC = trim($parts[4]);
                $colD = trim($parts[5]);
                $nav = trim($parts[6]);
                $dateStrRaw = trim($parts[7]);
            } elseif ($fieldCount >= 6) {
                $schemeCode = trim($parts[0]);
                $schemeName = trim($parts[1]);
                $colC = trim($parts[2]);
                $colD = trim($parts[3]);
                $nav = trim($parts[4]);
                $dateStrRaw = trim($parts[$fieldCount - 1]);
            } else {
                continue;
            }
            
            if (strpos($schemeCode, 'INF') === 0) {
                $colC = $schemeCode;
                $schemeCode = 'SIF-Unknown';
            }
            
            if (empty($nav) || !is_numeric($nav)) continue;
            
            $isinRecords = array();
            if (!empty($colC) && $colC != '-' && preg_match('/^INF/', $colC)) {
                $isinRecords[] = array('isin' => $colC, 'type' => 'Growth');
            }
            if (!empty($colD) && $colD != '-' && preg_match('/^INF/', $colD) && $colD != $colC) {
                $isinRecords[] = array('isin' => $colD, 'type' => 'Reinvestment');
            }
            
            if (empty($isinRecords)) continue;
            
            $formattedDate = $this->formatDate($dateStrRaw);
            
            foreach ($isinRecords as $isinRecord) {
                $isin = $isinRecord['isin'];
                $type = $isinRecord['type'];
                $modifiedSchemeName = $schemeName;
                if ($type == 'Reinvestment' && strpos($schemeName, 'Reinvest') === false) {
                    $modifiedSchemeName = $schemeName . ' (Reinvest)';
                }
                
                $data[] = array(
                    'isin' => $isin,
                    'nav' => $nav,
                    'nav_date' => $formattedDate,
                    'scheme_code' => $schemeCode,
                    'scheme_name' => $modifiedSchemeName,
                    'isin_type' => $type
                );
            }
        }
        
        if (empty($data)) {
            $this->log("No valid SIF data found in the response.");
            return array('success' => false, 'message' => 'No valid SIF data found for the date. Check URL or data format.');
        }
        
        $this->log("Parsed " . count($data) . " SIF records from AMFI History API.");
        
        $result = $this->importToDatabase($data);
        
        $this->log("Import completed. Inserted: {$result['inserted']}, Updated: {$result['updated']}, Failed: {$result['failed']}");
        
        $duration = round(microtime(true) - $startTime, 2);
        
        return array(
            'success' => true,
            'message' => 'Fetched and imported SIF NAV history successfully.',
            'result' => $result,
            'duration' => $duration,
            'source' => 'AMFI SIF History (' . $dateStr . ')'
        );
    }
    
    /**
     * Parse AMFI SIF latest-nav HTML page to extract NAV data
     */
    private function parseAMFIHtml($html) {
        $data = array();
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tr');
        
        if ($rows->length == 0) {
            $this->log("WARNING: No table rows found in HTML. Trying regex fallback.");
            return $this->parseAMFIHtmlRegex($html);
        }
        
        $this->log("Found " . $rows->length . " table rows to process");
        
        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);
            if ($cells->length < 5) continue;
            
            $firstCell = trim($cells->item(0)->textContent);
            
            if (strpos($firstCell, 'SIF-') !== 0) continue;
            
            $schemeCode = trim($firstCell);
            $schemeName = trim($cells->item(1)->textContent);
            $colC = trim($cells->item(2)->textContent);
            $colD = trim($cells->item(3)->textContent);
            $nav = trim($cells->item(4)->textContent);
            $dateStr = ($cells->item(5) !== null) ? trim($cells->item(5)->textContent) : '';
            
            $this->log("AMFI Row: Code=[{$schemeCode}], Name=[{$schemeName}], ISIN_G=[{$colC}], ISIN_R=[{$colD}], NAV=[{$nav}], Date=[{$dateStr}]");
            
            $isinRecords = array();
            if (!empty($colC) && $colC != '-' && preg_match('/^INF/', $colC)) {
                $isinRecords[] = array('isin' => $colC, 'type' => 'Growth');
            }
            if (!empty($colD) && $colD != '-' && preg_match('/^INF/', $colD)) {
                $isinRecords[] = array('isin' => $colD, 'type' => 'Reinvestment');
            }
            
            if (empty($isinRecords)) {
                $this->log("WARNING: Skipping {$schemeCode} - No valid ISIN found");
                continue;
            }
            
            if (!empty($schemeCode) && !empty($schemeName) && !empty($nav) && !empty($dateStr)) {
                $formattedDate = $this->formatDate($dateStr);
                
                foreach ($isinRecords as $isinRecord) {
                    $isin = $isinRecord['isin'];
                    $type = $isinRecord['type'];
                    $modifiedSchemeName = $schemeName;
                    if ($type == 'Reinvestment' && strpos($schemeName, 'Reinvest') === false) {
                        $modifiedSchemeName = $schemeName . ' (Reinvest)';
                    }
                    
                    $data[] = array(
                        'isin' => $isin,
                        'nav' => $nav,
                        'nav_date' => $formattedDate,
                        'scheme_code' => $schemeCode,
                        'scheme_name' => $modifiedSchemeName,
                        'isin_type' => $type
                    );
                }
            }
        }
        
        if (empty($data)) {
            $this->log("DOM parsing found no SIF data. Trying regex fallback.");
            return $this->parseAMFIHtmlRegex($html);
        }
        
        return $data;
    }
    
    /**
     * Regex fallback parser for AMFI HTML
     */
    private function parseAMFIHtmlRegex($html) {
        $data = array();
        
        $pattern = '/SIF-(\d+)\s*[\|<\/td>].*?<td[^>]*>(.*?)<\/td>.*?<td[^>]*>(.*?)<\/td>.*?<td[^>]*>(.*?)<\/td>.*?<td[^>]*>(.*?)<\/td>.*?<td[^>]*>(.*?)<\/td>/si';
        
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            $this->log("Regex found " . count($matches) . " SIF records");
            
            foreach ($matches as $match) {
                $schemeCode = 'SIF-' . trim(strip_tags($match[1]));
                $schemeName = trim(strip_tags($match[2]));
                $colC = trim(strip_tags($match[3]));
                $colD = trim(strip_tags($match[4]));
                $nav = trim(strip_tags($match[5]));
                $dateStr = trim(strip_tags($match[6]));
                
                $isinRecords = array();
                if (!empty($colC) && $colC != '-' && preg_match('/^INF/', $colC)) {
                    $isinRecords[] = array('isin' => $colC, 'type' => 'Growth');
                }
                if (!empty($colD) && $colD != '-' && preg_match('/^INF/', $colD)) {
                    $isinRecords[] = array('isin' => $colD, 'type' => 'Reinvestment');
                }
                
                if (empty($isinRecords)) continue;
                
                if (!empty($schemeCode) && !empty($schemeName) && !empty($nav) && !empty($dateStr)) {
                    $formattedDate = $this->formatDate($dateStr);
                    
                    foreach ($isinRecords as $isinRecord) {
                        $isin = $isinRecord['isin'];
                        $type = $isinRecord['type'];
                        $modifiedSchemeName = $schemeName;
                        if ($type == 'Reinvestment' && strpos($schemeName, 'Reinvest') === false) {
                            $modifiedSchemeName = $schemeName . ' (Reinvest)';
                        }
                        
                        $data[] = array(
                            'isin' => $isin,
                            'nav' => $nav,
                            'nav_date' => $formattedDate,
                            'scheme_code' => $schemeCode,
                            'scheme_name' => $modifiedSchemeName,
                            'isin_type' => $type
                        );
                    }
                }
            }
        } else {
            $this->log("Regex fallback also found no SIF data");
        }
        
        return $data;
    }
    
    /**
     * Main function to import data from uploaded file
     */
    public function importFromFile($filePath) {
        if (is_writable($this->logsDir)) @file_put_contents($this->logFile, '');
        if (is_writable($this->logsDir)) @file_put_contents($this->parameterLogFile, '');
        
        $startTime = microtime(true);
        
        $this->log("=== AIF NAV Import Started at " . date('Y-m-d H:i:s') . " ===");
        $this->log("Importing file: " . $filePath);
        
        try {
            if (!file_exists($filePath)) {
                $this->log("ERROR: File not found: " . $filePath);
                return array('success' => false, 'message' => 'File not found.');
            }
            
            $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($fileExt == 'xlsx') {
                $data = $this->readXLSX($filePath);
            } else {
                $data = $this->readCSV($filePath);
            }
            
            if (empty($data)) {
                $this->log("No valid data found in uploaded file");
                return array('success' => false, 'message' => 'No valid data found in the file. Ensure rows start with SIF- codes.');
            }
            
            $result = $this->importToDatabase($data);
            
            $this->log("Import completed. Inserted: {$result['inserted']}, Updated: {$result['updated']}, Failed: {$result['failed']}");
            
            $duration = round(microtime(true) - $startTime, 2);
            
            return array(
                'success' => true,
                'message' => 'Import completed successfully.',
                'result' => $result,
                'file' => $filePath,
                'duration' => $duration
            );
            
        } catch (Exception $e) {
            $this->log("CRITICAL ERROR: " . $e->getMessage());
            return array('success' => false, 'message' => 'Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Read and parse CSV file for tb_nav table
     */
    private function readCSV($filePath) {
        $data = array();
        $rowCount = 0;
        $foundData = false;
        
        $this->log("Starting to read CSV file: " . $filePath);
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rowCount++;
                
                if (empty(array_filter($row))) {
                    continue;
                }
                
                if (isset($row[0]) && strpos($row[0], 'SIF-') === 0) {
                    $foundData = true;
                    
                    $schemeCode = trim($row[0]);
                    $schemeName = trim($this->getValue($row, 1));
                    $colC = trim($this->getValue($row, 2));
                    $colD = trim($this->getValue($row, 3));
                    
                    $isinRecords = array();
                    
                    if (!empty($colC) && $colC != '-') {
                        $isinRecords[] = array(
                            'isin' => $colC,
                            'type' => 'Growth'
                        );
                    }
                    
                    if (!empty($colD) && $colD != '-') {
                        $isinRecords[] = array(
                            'isin' => $colD,
                            'type' => 'Reinvestment'
                        );
                    }
                    
                    if (empty($isinRecords)) {
                        $this->log("WARNING: Skipping row $rowCount - No valid ISIN found in either column");
                        continue;
                    }
                    
                    $nav = trim($this->getValue($row, 4));
                    $dateStr = trim($this->getValue($row, 5));
                    
                    $this->log("RAW CSV Row {$rowCount}: SIF Code=[{$schemeCode}], Scheme Name=[{$schemeName}], ISIN Col C=[{$colC}], ISIN Col D=[{$colD}], Found " . count($isinRecords) . " ISIN(s), NAV=[{$nav}], Date=[{$dateStr}]");
                    
                    if (!empty($schemeCode) && !empty($schemeName) && !empty($nav) && !empty($dateStr)) {
                        $formattedDate = $this->formatDate($dateStr);
                        
                        foreach ($isinRecords as $isinRecord) {
                            $isin = $isinRecord['isin'];
                            $type = $isinRecord['type'];
                            
                            $modifiedSchemeName = $schemeName;
                            if ($type == 'Reinvestment' && strpos($schemeName, 'Reinvest') === false) {
                                $modifiedSchemeName = $schemeName . ' (Reinvest)';
                            }
                            
                            $this->log("VALID Row {$rowCount} ({$type}): Scheme Code={$schemeCode}, Scheme Name={$modifiedSchemeName}, ISIN={$isin}, NAV={$nav}, Original Date={$dateStr}, Formatted Date={$formattedDate}");
                            
                            $data[] = array(
                                'isin' => $isin,
                                'nav' => $nav,
                                'nav_date' => $formattedDate,
                                'scheme_code' => $schemeCode,
                                'scheme_name' => $modifiedSchemeName,
                                'isin_type' => $type
                            );
                        }
                    } else {
                        $missing = array();
                        if (empty($schemeCode)) $missing[] = 'Scheme Code';
                        if (empty($schemeName)) $missing[] = 'Scheme Name';
                        if (empty($nav)) $missing[] = 'NAV Value';
                        if (empty($dateStr)) $missing[] = 'Date';
                        
                        $this->log("WARNING: Skipping row $rowCount - Missing data: " . implode(', ', $missing));
                    }
                }
            }
            fclose($handle);
        } else {
            $this->log("ERROR: Could not open CSV file: " . $filePath);
            return array();
        }
        
        if (!$foundData) {
            $this->log("WARNING: No SIF codes found in CSV file. Check file format.");
        }
        
        $this->log("Read " . count($data) . " valid records from CSV for tb_nav table");
        return $data;
    }
    
    /**
     * Read and parse XLSX file for tb_nav table using native PHP ZipArchive
     */
    private function readXLSX($filePath) {
        $data = array();
        $this->log("Starting to read XLSX file: " . $filePath);
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            $this->log("ERROR: Could not open XLSX file: " . $filePath);
            return array();
        }
        
        $sharedStrings = array();
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $ssDoc = new SimpleXMLElement($ssXml);
            foreach ($ssDoc->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string)$si->t;
                } elseif (isset($si->r)) {
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                }
                $sharedStrings[] = $text;
            }
        }
        
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $this->log("ERROR: Could not read sheet1.xml from XLSX");
            $zip->close();
            return array();
        }
        
        $sheetDoc = new SimpleXMLElement($sheetXml);
        $foundData = false;
        $rowCount = 0;
        
        foreach ($sheetDoc->sheetData->row as $xmlRow) {
            $rowCount++;
            $row = array();
            $maxCol = 0;
            
            foreach ($xmlRow->c as $cell) {
                $ref = (string)$cell['r'];
                preg_match('/^([A-Z]+)/', $ref, $matches);
                $colLetters = $matches[1];
                $colIndex = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - ord('A') + 1);
                }
                $colIndex--;
                
                $value = '';
                $type = isset($cell['t']) ? (string)$cell['t'] : '';
                
                if ($type == 's') {
                    $idx = intval((string)$cell->v);
                    $value = isset($sharedStrings[$idx]) ? $sharedStrings[$idx] : '';
                } elseif ($type == 'inlineStr') {
                    $value = (string)$cell->is->t;
                } else {
                    $value = (string)$cell->v;
                }
                
                $row[$colIndex] = $value;
                if ($colIndex > $maxCol) $maxCol = $colIndex;
            }
            
            for ($i = 0; $i <= $maxCol; $i++) {
                if (!isset($row[$i])) $row[$i] = '';
            }
            ksort($row);
            
            if (empty(array_filter($row))) continue;
            
            if (isset($row[0]) && strpos($row[0], 'SIF-') === 0) {
                $foundData = true;
                
                $schemeCode = trim($row[0]);
                $schemeName = trim($this->getValue($row, 1));
                $colC = trim($this->getValue($row, 2));
                $colD = trim($this->getValue($row, 3));
                
                $isinRecords = array();
                if (!empty($colC) && $colC != '-') {
                    $isinRecords[] = array('isin' => $colC, 'type' => 'Growth');
                }
                if (!empty($colD) && $colD != '-') {
                    $isinRecords[] = array('isin' => $colD, 'type' => 'Reinvestment');
                }
                
                if (empty($isinRecords)) {
                    $this->log("WARNING: Skipping row $rowCount - No valid ISIN found");
                    continue;
                }
                
                $nav = trim($this->getValue($row, 4));
                $dateStr = trim($this->getValue($row, 5));
                
                if (is_numeric($dateStr) && intval($dateStr) > 40000) {
                    $unixTimestamp = ($dateStr - 25569) * 86400;
                    $dateStr = date('Y-m-d', $unixTimestamp);
                }
                
                $this->log("RAW XLSX Row {$rowCount}: SIF Code=[{$schemeCode}], Name=[{$schemeName}], ISIN C=[{$colC}], ISIN D=[{$colD}], NAV=[{$nav}], Date=[{$dateStr}]");
                
                if (!empty($schemeCode) && !empty($schemeName) && !empty($nav) && !empty($dateStr)) {
                    $formattedDate = $this->formatDate($dateStr);
                    
                    foreach ($isinRecords as $isinRecord) {
                        $isin = $isinRecord['isin'];
                        $type = $isinRecord['type'];
                        $modifiedSchemeName = $schemeName;
                        if ($type == 'Reinvestment' && strpos($schemeName, 'Reinvest') === false) {
                            $modifiedSchemeName = $schemeName . ' (Reinvest)';
                        }
                        
                        $this->log("VALID Row {$rowCount} ({$type}): Code={$schemeCode}, Name={$modifiedSchemeName}, ISIN={$isin}, NAV={$nav}, Date={$formattedDate}");
                        
                        $data[] = array(
                            'isin' => $isin,
                            'nav' => $nav,
                            'nav_date' => $formattedDate,
                            'scheme_code' => $schemeCode,
                            'scheme_name' => $modifiedSchemeName,
                            'isin_type' => $type
                        );
                    }
                } else {
                    $missing = array();
                    if (empty($schemeCode)) $missing[] = 'Scheme Code';
                    if (empty($schemeName)) $missing[] = 'Scheme Name';
                    if (empty($nav)) $missing[] = 'NAV Value';
                    if (empty($dateStr)) $missing[] = 'Date';
                    $this->log("WARNING: Skipping row $rowCount - Missing: " . implode(', ', $missing));
                }
            }
        }
        
        $zip->close();
        
        if (!$foundData) {
            $this->log("WARNING: No SIF codes found in XLSX file. Check file format.");
        }
        
        $this->log("Read " . count($data) . " valid records from XLSX for tb_nav table");
        return $data;
    }
    
    /**
     * Import data to tb_nav table using the updateNAV() function
     */
    private function importToDatabase($data) {
        $inserted = 0;
        $updated = 0;
        $alreadyExist = 0;
        $failed = 0;
        
        $this->log("Starting database import with " . count($data) . " records");
        
        foreach ($data as $index => $record) {
            $isin = $record['isin'];
            $nav = $record['nav'];
            $navDate = $record['nav_date'];
            $schemeCode = $record['scheme_code'];
            $schemeName = $record['scheme_name'];
            $isinType = isset($record['isin_type']) ? $record['isin_type'] : 'Unknown';
            
            try {
                $result = $this->db->updateNAV($isin, $nav, $navDate, $schemeCode, $schemeName);
                
                if ($result == "Inserted") {
                    $inserted++;
                    $this->log("SUCCESS: INSERTED - {$schemeCode} - {$schemeName} (ISIN: {$isin}, Type: {$isinType})");
                } elseif ($result == "Updated") {
                    $updated++;
                    $this->log("SUCCESS: UPDATED - {$schemeCode} - {$schemeName} (ISIN: {$isin}, Type: {$isinType})");
                } elseif ($result == "already exist") {
                    $alreadyExist++;
                    $this->log("INFO: ALREADY EXISTS - {$schemeCode} - {$schemeName} (ISIN: {$isin}, Type: {$isinType})");
                } else {
                    $failed++;
                    $this->log("ERROR: Unknown result for {$schemeCode} - {$result}");
                }
            } catch (Exception $e) {
                $failed++;
                $this->log("ERROR: Exception for Record " . ($index + 1) . " - " . $e->getMessage());
            }
        }
        
        $this->log("Database import completed. Inserted: {$inserted}, Updated: {$updated}, Already Exist: {$alreadyExist}, Failed: {$failed}");
        
        return array(
            'inserted' => $inserted,
            'updated' => $updated,
            'already_exist' => $alreadyExist,
            'failed' => $failed,
            'total' => count($data)
        );
    }
    
    /**
     * Format date from "27 Feb 2026" to "2026-02-27"
     */
    private function formatDate($dateStr) {
        $dateStr = trim($dateStr);
        $date = null;
        
        if (!$date) $date = DateTime::createFromFormat('d M Y', $dateStr);
        if (!$date) $date = DateTime::createFromFormat('d F Y', $dateStr);
        if (!$date) $date = DateTime::createFromFormat('d-M-Y', $dateStr);
        if (!$date) $date = DateTime::createFromFormat('Y-m-d', $dateStr);
        
        if ($date) {
            $formatted = $date->format('Y-m-d');
            //$this->log("Date conversion: '{$dateStr}' -> '{$formatted}'");
            return $formatted;
        }
        
        $this->log("WARNING: Could not parse date: '{$dateStr}', using current date");
        return date('Y-m-d');
    }
    
    /**
     * Log message to file
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        static $firstLog = true;
        
        // Exit silently if the file/folder lacks permissions so it doesn't crash the script
        if (!is_writable(dirname($this->logFile)) && (!file_exists($this->logFile) || !is_writable($this->logFile))) {
            return; 
        }
        
        if ($firstLog) {
            file_put_contents($this->logFile, $logMessage);
            $firstLog = false;
        } else {
            file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        }
    }
    
    /**
     * Output inline loadPage script
     */
    private function outputLoadPageScript() {
        echo "<script>
            function loadPage(url) {
                document.getElementById('main_content').innerHTML = '<div class=\"text-center mt-5\"><div class=\"spinner-border text-primary\"></div><p class=\"mt-2\">Loading...</p></div>';
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        document.getElementById('main_content').innerHTML = xhr.responseText;
                    }
                };
                xhr.send();
            }
        </script>";
    }
    
    /**
     * Display the import page content (no HTML/HEAD/BODY tags)
     */
    public function displayPage() {
        // Handle AJAX search
        if (isset($_GET['ajax_search_nav'])) {
            $searchTerm = isset($_GET['q']) ? $_GET['q'] : '';
            if (empty($searchTerm)) {
                $result = $this->db->getRecentSIFNAVRecords(500);
            } else {
                $result = $this->db->searchNAVRecords($searchTerm, 500);
            }
            
            $output = "";
            if (is_array($result) || ($result && $result instanceof mysqli_result)) {
                $rows = is_array($result) ? $result : array();
                if ($result instanceof mysqli_result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $rows[] = $row;
                    }
                    mysqli_free_result($result);
                }
                
                if (empty($rows)) {
                    $output .= "<tr><td colspan='5' class='text-center text-muted py-3'>No records found matching '" . htmlspecialchars($searchTerm) . "'</td></tr>";
                } else {
                    foreach ($rows as $row) {
                        $output .= "<tr>
                                <td><small>" . htmlspecialchars($row['isin'] ?? '') . "</small></td>
                                <td><small>{$row['scheme_code']}</small></td>
                                <td><small>{$row['scheme_name']}</small></td>
                                <td><small>{$row['nav']}</small></td>
                                <td><small>{$row['nav_date']}</small></td>
                              </tr>";
                    }
                }
            } else {
                $output .= "<tr><td colspan='5' class='text-center text-muted py-3'>Error fetching records.</td></tr>";
            }
            echo $output;
            return;
        }

        // Handle file upload via AJAX
        if (isset($_FILES['csv_file'])) {
            $uploadResult = $this->handleFileUpload();
            if ($uploadResult['success']) {
                $importResult = $this->importFromFile($uploadResult['file']);
                if ($importResult['success']) {
                    $this->displayResults($importResult['result'], $importResult['file'], $importResult['duration']);
                } else {
                    echo "<div class='alert alert-danger'>Error: " . $importResult['message'] . "</div>";
                    echo "<button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>← Back</button>";
                    $this->outputLoadPageScript();
                }
            } else {
                echo "<div class='alert alert-danger'>Upload Error: " . $uploadResult['message'] . "</div>";
                echo "<button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>← Back</button>";
                $this->outputLoadPageScript();
            }
            return;
        }
        
        // Handle import from existing file
        if (isset($_GET['import'])) {
            $filePath = $_GET['import'];
            if (strpos($filePath, $this->uploadDir) === 0 && file_exists($filePath)) {
                $importResult = $this->importFromFile($filePath);
                if ($importResult['success']) {
                    $this->displayResults($importResult['result'], $importResult['file'], $importResult['duration']);
                } else {
                    echo "<div class='alert alert-danger'>Error: " . $importResult['message'] . "</div>";
                    echo "<button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>← Back</button>";
                    $this->outputLoadPageScript();
                }
            } else {
                echo "<div class='alert alert-danger'>Invalid file path.</div>";
                echo "<button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>← Back</button>";
                $this->outputLoadPageScript();
            }
            return;
        }
        
        // Handle fetch from AMFI website
        if (isset($_GET['fetch_amfi'])) {
            $fetchResult = $this->fetchFromAMFI();
            if ($fetchResult['success']) {
                $this->displayResults($fetchResult['result'], 'AMFI Website (Live)', $fetchResult['duration']);
            } else {
                echo "<div class='alert alert-danger'>Error: " . $fetchResult['message'] . "</div>";
                echo "<button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>← Back</button>";
                $this->outputLoadPageScript();
            }
            return;
        }
        
        // Handle MF NAV fetch from AMFI
        if (isset($_GET['fetch_mf_amfi'])) {
            $fetchResult = $this->fetchMFFromAMFI();
            if ($fetchResult['success']) {
                $this->displayMFResults($fetchResult);
            } else {
                echo "<div class='alert alert-danger'>Error: " . $fetchResult['message'] . "</div>";
                echo "<button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>← Back</button>";
                $this->outputLoadPageScript();
            }
            return;
        }
        
        // Handle MF NAV fetch from NSE API
        if (isset($_GET['fetch_mf_nse'])) {
            $fetchResult = $this->fetchMFFromNSE();
            if ($fetchResult['success']) {
                $this->displayMFResults($fetchResult);
            } else {
                echo "<div class='alert alert-danger'>Error: " . $fetchResult['message'] . "</div>";
                echo "<button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>← Back</button>";
                $this->outputLoadPageScript();
            }
            return;
        }
        
        // Handle SIF NAV fetch from AMFI History
        if (isset($_GET['fetch_sif_amfi_history'])) {
            $fetchResult = $this->fetchSIFFromAMFIHistory();
            if ($fetchResult['success']) {
                $sourceName = isset($fetchResult['source']) ? $fetchResult['source'] : 'AMFI SIF History';
                $this->displayResults($fetchResult['result'], $sourceName, $fetchResult['duration']);
            } else {
                echo "<div class='alert alert-danger'>Error: " . $fetchResult['message'] . "</div>";
                echo "<button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>← Back</button>";
                $this->outputLoadPageScript();
            }
            return;
        }
        
        // Display main page
        $this->displayUploadForm();
    }
    
    /**
     * Display upload form (content only) - merged with nav_management Customer UCC section
     */
    private function displayUploadForm() {
        $csvFiles = glob($this->uploadDir . "*.csv");
        $xlsxFiles = glob($this->uploadDir . "*.xlsx");
        $files = array_merge($csvFiles ? $csvFiles : array(), $xlsxFiles ? $xlsxFiles : array());

        // ─── CSS for NAV Management section ──────────────────────────────────────
        echo "<style>
            .nm-selection-panel { background:#fff; border-radius:16px; padding:20px 24px; margin-bottom:20px; box-shadow:0 6px 24px rgba(0,0,0,0.08); border:1px solid #eee; }
            .nm-form-row { display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end; }
            .nm-form-group { flex:1; min-width:200px; position:relative; }
            .nm-form-group label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px; }
            .nm-form-group input, .nm-form-group select { width:100%; padding:10px 13px; border:2px solid #e0e0e0; border-radius:10px; font-size:14px; transition:all 0.3s; background:#fafafa; color:#333; }
            .nm-form-group input:focus { outline:none; border-color:#667eea; background:white; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }
            #nm-autocomplete-list { position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #e0e0e0; border-radius:10px; max-height:200px; overflow-y:auto; z-index:9999; box-shadow:0 8px 20px rgba(0,0,0,0.1); }
            #nm-autocomplete-list div { padding:10px 13px; cursor:pointer; font-size:13px; color:#333; transition:background 0.2s; }
            #nm-autocomplete-list div:hover { background:#f5f0ff; color:#667eea; }
            .nm-btn-orange { padding:10px 22px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; background:#fd7101; color:white; transition:all 0.3s; white-space:nowrap; }
            .nm-btn-orange:hover { background:#e66500; transform:translateY(-1px); box-shadow:0 4px 12px rgba(253,113,1,0.3); }
            #nm-status { font-size:13px; font-weight:500; padding:8px 13px; border-radius:9px; background:#f8f9fa; min-width:150px; }

            .nm-management-section { background:#fff; border-radius:16px; padding:24px; margin-bottom:20px; box-shadow:0 6px 24px rgba(0,0,0,0.08); border:1px solid #eee; }
            .nm-type-selector { background:#f8f9fa; border-radius:10px; padding:7px; display:flex; gap:8px; margin-bottom:18px; }
            .nm-type-btn { flex:1; padding:11px; text-align:center; border-radius:8px; cursor:pointer; transition:all 0.3s; font-weight:600; font-size:13px; background:white; border:2px solid #e0e0e0; color:#666; }
            .nm-type-btn.active { background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-color:#667eea; color:white; }
            .nm-type-btn:hover:not(.active) { border-color:#667eea; background:#f5f0ff; }
            .nm-section-header { border-bottom:3px solid #667eea; padding-bottom:12px; margin-bottom:20px; }
            .nm-section-header h3 { color:#333; font-size:17px; font-weight:700; }
            .nm-form-inline { display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end; margin-bottom:18px; }
            .nm-input-field { display:flex; flex-direction:column; flex:1; min-width:170px; }
            .nm-input-field label { font-size:12px; font-weight:600; color:#555; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px; }
            .nm-input-field input, .nm-input-field select { padding:9px 12px; border:2px solid #e0e0e0; border-radius:10px; font-size:14px; transition:all 0.3s; background:#fafafa; color:#333; }
            .nm-input-field input:focus, .nm-input-field select:focus { outline:none; border-color:#667eea; background:white; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }
            .nm-field-action { display:flex; align-items:flex-end; }
            .nm-data-controls { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin:16px 0; }
            .nm-entries-selector { display:flex; align-items:center; gap:7px; font-size:13px; color:#555; }
            .nm-entries-selector select { padding:5px 9px; border:2px solid #e0e0e0; border-radius:7px; font-size:13px; background:#fafafa; }
            .nm-search-box { display:flex; align-items:center; gap:7px; font-size:13px; color:#555; }
            .nm-search-box input { padding:6px 11px; border:2px solid #e0e0e0; border-radius:7px; font-size:13px; width:210px; background:#fafafa; }
            .nm-table-container { overflow-x:auto; border-radius:12px; border:1px solid #e0e0e0; background:white; }
            .nm-nav-table { width:100%; border-collapse:collapse; font-size:13px; }
            .nm-nav-table thead { background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
            .nm-nav-table th { padding:13px 15px; text-align:left; font-weight:600; color:white; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
            .nm-nav-table td { padding:11px 15px; border-bottom:1px solid #f0f0f0; color:#444; }
            .nm-nav-table tbody tr:hover { background:#faf5ff; }
            .nm-nav-table tbody tr:nth-child(even) { background:#fafafa; }
            .nm-pagination-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:14px; }
            .nm-entries-info { font-size:13px; color:#666; }
            .nm-pagination-buttons { display:flex; gap:5px; align-items:center; }
            .nm-page-btn { padding:5px 11px; border:2px solid #e0e0e0; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; background:white; color:#555; transition:all 0.2s; }
            .nm-page-btn:hover:not(:disabled) { border-color:#667eea; color:#667eea; }
            .nm-page-btn.active { background:#667eea; border-color:#667eea; color:white; }
            .nm-page-btn:disabled { opacity:0.4; cursor:not-allowed; }
            .nm-action-btn { color:#667eea; text-decoration:none; font-weight:600; font-size:12px; padding:4px 10px; border-radius:5px; background:#f0f0ff; display:inline-block; transition:all 0.2s; cursor:pointer; }
            .nm-action-btn:hover { background:#667eea; color:white; }
            .nm-delete-btn { color:#e53e3e; background:#fff0f0; }
            .nm-delete-btn:hover { background:#e53e3e; color:white; }
            .nm-no-data { text-align:center; padding:36px; color:#999; font-size:14px; }
            @media(max-width:768px){ .nm-form-row,.nm-form-inline{ flex-direction:column; } .nm-form-group,.nm-input-field{ width:100%; } .nm-type-selector{ flex-direction:column; } .nm-data-controls,.nm-pagination-footer{ flex-direction:column; align-items:flex-start; } }
        </style>";

        // ─── Section 0: Global Mutual Fund NAV Search ───────────────────────────────
        echo "<div class='nm-selection-panel' style='padding:0; overflow:visible; margin-bottom: 25px;'>
                <div style='background:linear-gradient(135deg,#00b09b 0%,#96c93d 100%); padding:12px 20px; border-radius:15px 15px 0 0;'>
                    <h5 style='margin:0; font-weight:700; color:#fff; font-size:15px;'>
                        <i class='fas fa-search me-2'></i> Global Mutual Fund & SIF NAV Search
                    </h5>
                </div>
                <div style='padding:20px 24px;'>
                    <div class='nm-form-row'>
                        <div class='nm-form-group' style='flex:2;'>
                            <label>Search ISIN, Code, or Scheme Name</label>
                            <input type='text' id='global_mf_search' placeholder='Type here to search NAVs...' onkeyup='searchGlobalMFNav()'>
                        </div>
                    </div>
                    <div id='global_mf_results' style='display:none; margin-top:15px;'>
                        <div class='nm-table-container' style='max-height: 250px;'>
                            <table class='nm-nav-table'>
                                <thead style='position: sticky; top: 0; background: #00b09b; color: white;'>
                                    <tr>
                                        <th>ISIN</th>
                                        <th>Code</th>
                                        <th>Scheme Name</th>
                                        <th>NAV</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id='global_mf_tbody'>
                                    <!-- AJAX results -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
              </div>";

        // ─── Section 1: Customer UCC Selection ───────────────────────────────────
        echo "<div class='nm-selection-panel' style='padding:0; overflow:visible;'>
                <div style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:12px 20px; border-radius:15px 15px 0 0;'>
                    <h5 style='margin:0; font-weight:700; color:#fff; font-size:15px;'>
                        <i class='fas fa-user-circle me-2'></i> Customer Specific NAV & Payouts
                    </h5>
                </div>
                <div style='padding:20px 24px;'>
                <div class='nm-form-row'>
                    <div class='nm-form-group'>
                        <label>Customer UCC <span style='font-weight: 400; text-transform: none; font-size: 11px; letter-spacing: 0;'>(for PMS NAV / AIF NAV & Payout)</span></label>
                        <input type='text' id='nm_custUCC' placeholder='Enter UCC or Name for PMS NAV / AIF NAV & Payout' autocomplete='off'>
                        <div id='nm-autocomplete-list'></div>
                    </div>
                    <div class='nm-form-group' style='flex:0;'>
                        <label>&nbsp;</label>
                        <button type='button' class='nm-btn-orange' onclick='nmSelectCustomer()'>Select Customer</button>
                    </div>
                    <div class='nm-form-group' style='flex:0; min-width:155px;'>
                        <label>&nbsp;</label>
                        <div id='nm-status'></div>
                    </div>
                </div>
                </div><!-- end inner padding div -->
              </div>";

        // ─── Section 2: Management Div (hidden until UCC selected) ────────────────
        echo "<div id='nm_management_div' style='display:none;'>
                <div class='nm-management-section'>

                    <div class='nm-type-selector'>
                        <div class='nm-type-btn' data-type='pms' onclick=\"nmSwitchNavType('pms')\">
                            <i class='fas fa-chart-line'></i> PMS NAV
                        </div>
                        <div class='nm-type-btn' data-type='aif_nav' onclick=\"nmSwitchNavType('aif_nav')\">
                            <i class='fas fa-hdd'></i> AIF NAV
                        </div>
                        <div class='nm-type-btn' data-type='aif_payout' onclick=\"nmSwitchNavType('aif_payout')\">
                            <i class='fas fa-coins'></i> AIF Payout
                        </div>
                        <div style='display:flex; align-items:center; padding:0 6px;'>
                            <button type='button' onclick='nmClearCustomer()' style='padding:10px 16px; border:2px solid #e0e0e0; border-radius:8px; background:white; color:#666; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; transition:all 0.3s;' onmouseover=\"this.style.borderColor='#667eea'; this.style.color='#667eea';\" onmouseout=\"this.style.borderColor='#e0e0e0'; this.style.color='#666';\">
                                &#8592; Back to Import
                            </button>
                        </div>
                    </div>

                    <div class='nm-section-header'>
                        <h3 id='nm_formhead'>Add PMS Current Value</h3>
                    </div>

                    <!-- PMS Form -->
                    <form id='nm_pms_form' onsubmit='nmAddPmsRecord(); return false;' style='display:none;'>
                        <div class='nm-form-inline'>
                            <div class='nm-input-field'>
                                <label>PMS Name</label>
                                <select id='nm_pms_name' required></select>
                            </div>
                            <div class='nm-input-field'>
                                <label>Date</label>
                                <input type='date' id='nm_pms_current_date'>
                            </div>
                            <div class='nm-input-field'>
                                <label>Current Value</label>
                                <input type='text' id='nm_pms_current_value' required placeholder='Enter amount' oninput=\"this.value=this.value.replace(/[^0-9.]/g,'');\">
                            </div>
                            <div class='nm-field-action'>
                                <input type='hidden' id='nm_pms_action' value='add'>
                                <input type='hidden' id='nm_pms_slno' value='0'>
                                <button type='submit' class='nm-btn-orange' id='nm_pms_submit_btn'>Add PMS</button>
                            </div>
                        </div>
                    </form>

                    <!-- AIF NAV Form -->
                    <form id='nm_aif_nav_form' onsubmit='nmAddAifNavRecord(); return false;' style='display:none;'>
                        <div class='nm-form-inline'>
                            <div class='nm-input-field'>
                                <label>AIF Name</label>
                                <select id='nm_aif_nav_name' required></select>
                            </div>
                            <div class='nm-input-field'>
                                <label>Date</label>
                                <input type='date' id='nm_aif_nav_current_date'>
                            </div>
                            <div class='nm-input-field'>
                                <label>Current NAV</label>
                                <input type='text' id='nm_aif_nav_current_value' required placeholder='Enter NAV' oninput=\"this.value=this.value.replace(/[^0-9.]/g,'');\">
                            </div>
                            <div class='nm-field-action'>
                                <input type='hidden' id='nm_aif_nav_action' value='add'>
                                <input type='hidden' id='nm_aif_nav_slno' value='0'>
                                <button type='submit' class='nm-btn-orange' id='nm_aif_nav_submit_btn'>Update NAV</button>
                            </div>
                        </div>
                    </form>

                    <!-- AIF Payout Form -->
                    <form id='nm_aif_payout_form' onsubmit='nmAddAifPayoutRecord(); return false;' style='display:none;'>
                        <div class='nm-form-inline'>
                            <div class='nm-input-field'>
                                <label>AIF Name</label>
                                <select id='nm_aif_payout_name' required></select>
                            </div>
                            <div class='nm-input-field'>
                                <label>Date</label>
                                <input type='date' id='nm_aif_payout_date'>
                            </div>
                            <div class='nm-input-field'>
                                <label>Amount</label>
                                <input type='text' id='nm_aif_payout_amount' required placeholder='Enter amount' oninput=\"this.value=this.value.replace(/[^0-9.]/g,'');\">
                            </div>
                            <div class='nm-input-field'>
                                <label>Remarks</label>
                                <input type='text' id='nm_aif_payout_remarks' placeholder='Optional'>
                            </div>
                            <div class='nm-field-action'>
                                <input type='hidden' id='nm_aif_payout_action' value='add'>
                                <input type='hidden' id='nm_aif_payout_slno' value='0'>
                                <button type='submit' class='nm-btn-orange' id='nm_aif_payout_submit_btn'>Add AIF Payout</button>
                            </div>
                        </div>
                    </form>

                    <!-- Table Controls -->
                    <div class='nm-data-controls'>
                        <div class='nm-entries-selector'>
                            Show
                            <select id='nm_entries_per_page' onchange='nmChangePageSize()'>
                                <option value='10'>10</option>
                                <option value='25'>25</option>
                                <option value='50'>50</option>
                                <option value='100'>100</option>
                            </select>
                            entries
                        </div>
                        <div class='nm-search-box'>
                            Search: <input type='text' id='nm_search_input' placeholder='Search records...' onkeyup='nmSearchData()'>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class='nm-table-container'>
                        <table class='nm-nav-table'>
                            <thead id='nm_table_header'>
                                <tr><th>Name</th><th>Date</th><th>Value</th><th>Remarks</th><th width='80'>Edit</th><th width='80'>Delete</th></tr>
                            </thead>
                            <tbody id='nm_table_body'>
                                <tr><td colspan='6' class='nm-no-data'>Select a Customer UCC to view records</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class='nm-pagination-footer'>
                        <div class='nm-entries-info' id='nm_entries_info'>Showing 0 to 0 of 0 entries</div>
                        <div class='nm-pagination-buttons' id='nm_pagination_buttons'></div>
                    </div>

                </div>
              </div>";

        // ─── Section 3: AMFI/MF Import Section (unchanged from original) ──────────
        echo "<div id='amfi_import_section'>";
        echo "<div class='card mb-4'>
                <div class='card-header bg-dark text-white'>
                    <h5 class='mb-0'><i class='fas fa-chart-line'></i> Mutual Fund & SIF NAV Import</h5>
                </div>
                <div class='card-body'>
                    <div class='alert alert-secondary'>
                        <i class='fas fa-info-circle'></i>
                        Import all Mutual Fund NAV data from AMFI or NSE.
                    </div>
                    
                    <button onclick='fetchMFFromAMFI()' class='btn btn-primary'>
                        <i class='fas fa-download'></i> Import MF NAV from AMFI
                    </button>
                    
                    <button onclick='fetchMFFromNSE()' class='btn btn-warning ms-2'>
                        <i class='fas fa-download'></i> Import MF NAV from NSE
                    </button>
                    
                    <button onclick='fetchSIFFromAMFIHistory()' class='btn btn-info ms-2'>
                        <i class='fas fa-download'></i> Import SIF NAV from AMFI
                    </button>
                    
                    <div id='mfProgress' class='mt-3' style='display:none;'>
                        <div class='spinner-border text-primary' role='status'>
                            <span class='sr-only'>Loading...</span>
                        </div>
                        <span class='ms-2'>Fetching NAV data... This may take a few minutes.</span>
                    </div>
                </div>
              </div>";
        
        // ─── Section: Upload SIF NAV File and BOND NAV File (merged card) ───────
        echo "<div class='card'>
                <div class='card-header bg-primary text-white'>
                    <h5 class='mb-0'><i class='fas fa-upload'></i> Upload SIF NAV File and BOND NAV File</h5>
                </div>
                <div class='card-body'>
                    <div class='row g-4'>

                        <!-- ── Left col: SIF NAV ── -->
                        <div class='col-md-6'>
                            <div class='h-100 p-3 border rounded' style='border-color:#cfe2ff !important; background:#f8fbff;'>
                                <h6 class='fw-bold text-primary mb-3'>
                                    <i class='fas fa-file-csv me-1'></i> SIF NAV File
                                </h6>
                                <div class='alert alert-info py-2 mb-3' style='font-size:13px;'>
                                    <i class='fas fa-info-circle'></i>
                                    <strong>Format:</strong> CSV or XLSX — SIF Code, SIF NAV Name, ISIN Growth, ISIN Reinvest, NAV, Date
                                </div>
                                <div class='mb-3'>
                                    <label for='csv_file' class='form-label fw-semibold' style='font-size:13px;'>Select CSV or XLSX File:</label>
                                    <input type='file' class='form-control form-control-sm' id='csv_file' accept='.csv,.xlsx'>
                                </div>
                                <div class='d-flex gap-2 flex-wrap'>
                                    <button onclick='uploadFile()' class='btn btn-success btn-sm'>
                                        <i class='fas fa-upload me-1'></i> Upload &amp; Import SIF NAVs
                                    </button>
                                    <a href='https://www.amfiindia.com/sif/latest-nav' target='_blank' class='btn btn-outline-info btn-sm'>
                                        <i class='fas fa-download me-1'></i> Download SIF from AMFI
                                    </a>
                                </div>
                                <div id='uploadProgress' class='mt-3' style='display:none;'>
                                    <div class='spinner-border spinner-border-sm text-primary' role='status'></div>
                                    <span class='ms-2 text-primary' style='font-size:13px;'>Processing... Please wait.</span>
                                </div>
                            </div>
                        </div>

                        <!-- ── Right col: Bond NAV ── -->
                        <div class='col-md-6'>
                            <div class='h-100 p-3 border rounded' style='border-color:#ffe69c !important; background:#fffdf0;'>
                                <h6 class='fw-bold text-warning mb-3'>
                                    <i class='fas fa-file-excel me-1'></i> Bond NAV File
                                </h6>
                                <div class='alert alert-warning py-2 mb-3' style='font-size:13px;'>
                                    <i class='fas fa-info-circle'></i>
                                    <strong>Required:</strong> Daily NAV updates for Bonds. Excel file (.xls, .xlsx).
                                    <a href='bond_nav_template.xlsx' class='btn btn-outline-secondary btn-sm ms-2 py-0' download style='font-size:11px;'>
                                        <i class='fas fa-download me-1'></i> Template
                                    </a>
                                </div>
                                <div class='mb-3'>
                                    <label for='bond_nav_file' class='form-label fw-semibold' style='font-size:13px;'>Select Excel File (.xls, .xlsx):</label>
                                    <div class='input-group input-group-sm'>
                                        <input type='file' class='form-control' id='bond_nav_file' accept='.xls,.xlsx'>
                                        <label class='input-group-text' for='bond_nav_file'><i class='fas fa-folder-open'></i></label>
                                    </div>
                                </div>
                                <div class='mb-3'>
                                    <label for='bond_nav_remarks' class='form-label fw-semibold' style='font-size:13px;'>Remarks (Optional)</label>
                                    <textarea class='form-control form-control-sm' id='bond_nav_remarks' rows='2' placeholder='Enter any notes about this batch...'></textarea>
                                </div>
                                <button id='bondNavUploadBtn' onclick='uploadBondNavFile()' class='btn btn-warning btn-sm fw-bold'>
                                    <i class='fas fa-cloud-upload-alt me-1'></i> Start Upload
                                </button>
                                <div id='bondNavProgress' class='mt-3' style='display:none;'>
                                    <div class='spinner-border spinner-border-sm text-warning' role='status'></div>
                                    <span class='ms-2 text-warning fw-bold' style='font-size:13px;'>Processing, please wait...</span>
                                </div>
                                <div id='bondNavResult' class='mt-2'></div>
                            </div>
                        </div>

                    </div>
                </div>
              </div>";
        
        if (!empty($files)) {
            echo "<div class='card mt-4'>
                    <div class='card-header bg-secondary text-white'>
                        <h5 class='mb-0'><i class='fas fa-history'></i> Previously Uploaded SIF Files</h5>
                    </div>
                    <div class='card-body p-0'>
                        <div class='list-group list-group-flush'>";
            
            array_multisort(array_map('filemtime', $files), SORT_DESC, $files);
            
            foreach (array_slice($files, 0, 5) as $file) {
                $fileName = basename($file);
                $dt = new DateTime("@" . filemtime($file));
                $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                $fileTime = $dt->format('Y-m-d H:i');
                $fileSize = round(filesize($file) / 1024, 1) . ' KB';
                
                echo "<div class='list-group-item list-group-item-action d-flex justify-content-between align-items-center' 
                           onclick='importFile(\"" . htmlspecialchars($file) . "\")' style='cursor:pointer;'>
                        <div>
                            <i class='fas fa-file-csv text-success'></i> 
                            <span class='ms-2'>$fileName</span>
                            <small class='text-muted ms-2'>$fileTime</small>
                        </div>
                        <span class='badge bg-primary rounded-pill'>$fileSize</span>
                      </div>";
            }
            
            echo "      </div>
                    </div>
                  </div>";
        }
        
        echo "</div>"; // end #amfi_import_section
        
        // ─── JavaScript: AMFI import functions (original, unchanged) ─────────────
        echo "<script>
            var navPageUrl = 'getnav-from-amfi.php';
            
            function uploadFile() {
                var fileInput = document.getElementById('csv_file');
                if (fileInput.files.length === 0) { alert('Please select a file'); return; }
                document.getElementById('uploadProgress').style.display = 'block';
                var formData = new FormData();
                formData.append('csv_file', fileInput.files[0]);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', navPageUrl, true);
                xhr.timeout = 300000;
                xhr.onload = function() {
                    document.getElementById('uploadProgress').style.display = 'none';
                    if (xhr.status === 200) {
                        document.getElementById('main_content').innerHTML = xhr.responseText;
                    } else { alert('Error uploading file (HTTP ' + xhr.status + ')'); }
                };
                xhr.onerror = function() { document.getElementById('uploadProgress').style.display = 'none'; alert('Network error occurred during upload.'); };
                xhr.ontimeout = function() { document.getElementById('uploadProgress').style.display = 'none'; alert('Upload timed out.'); };
                xhr.send(formData);
            }
            
            function importFile(filePath) {
                if (confirm('Are you sure you want to import this file? This may take 1-2 minutes.')) {
                    document.getElementById('main_content').innerHTML = '<div class=\"text-center mt-5\"><div class=\"spinner-border text-primary\"></div><p class=\"mt-2\">Processing... Please wait.</p></div>';
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', navPageUrl + '?import=' + encodeURIComponent(filePath), true);
                    xhr.timeout = 300000;
                    xhr.onload = function() {
                        if (xhr.status === 200) { document.getElementById('main_content').innerHTML = xhr.responseText; }
                        else { document.getElementById('main_content').innerHTML = '<div class=\"alert alert-danger\">Error importing file (HTTP ' + xhr.status + ')</div>'; }
                    };
                    xhr.onerror = function() { document.getElementById('main_content').innerHTML = '<div class=\"alert alert-danger\">Network error occurred during import.</div>'; };
                    xhr.ontimeout = function() { document.getElementById('main_content').innerHTML = '<div class=\"alert alert-danger\">Import timed out.</div>'; };
                    xhr.send();
                }
            }
            
            function fetchMFFromAMFI() {
                if (confirm('Fetch all MF NAV data from AMFI? This may take a few minutes.')) {
                    document.getElementById('mfProgress').style.display = 'block';
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', navPageUrl + '?fetch_mf_amfi=1', true);
                    xhr.timeout = 600000;
                    xhr.onload = function() {
                        document.getElementById('mfProgress').style.display = 'none';
                        if (xhr.status === 200) { document.getElementById('main_content').innerHTML = xhr.responseText; }
                        else { alert('Error fetching MF NAV from AMFI (HTTP ' + xhr.status + ')'); }
                    };
                    xhr.onerror = function() { document.getElementById('mfProgress').style.display = 'none'; alert('Network error while fetching MF NAV from AMFI.'); };
                    xhr.ontimeout = function() { document.getElementById('mfProgress').style.display = 'none'; alert('MF AMFI fetch timed out.'); };
                    xhr.send();
                }
            }
            
            function fetchMFFromNSE() {
                if (confirm('Fetch all MF NAV data from NSE? This may take a few minutes.')) {
                    document.getElementById('mfProgress').style.display = 'block';
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', navPageUrl + '?fetch_mf_nse=1', true);
                    xhr.timeout = 600000;
                    xhr.onload = function() {
                        document.getElementById('mfProgress').style.display = 'none';
                        if (xhr.status === 200) { document.getElementById('main_content').innerHTML = xhr.responseText; }
                        else { alert('Error fetching MF NAV from NSE (HTTP ' + xhr.status + ')'); }
                    };
                    xhr.onerror = function() { document.getElementById('mfProgress').style.display = 'none'; alert('Network error while fetching MF NAV from NSE.'); };
                    xhr.ontimeout = function() { document.getElementById('mfProgress').style.display = 'none'; alert('MF NSE fetch timed out.'); };
                    xhr.send();
                }
            }
            
            function uploadBondNavFile() {
                var fileInput = document.getElementById('bond_nav_file');
                var remarks = document.getElementById('bond_nav_remarks').value;
                var resultDiv = document.getElementById('bondNavResult');
                var uploadBtn = document.getElementById('bondNavUploadBtn');

                if (!fileInput || fileInput.files.length === 0) {
                    resultDiv.innerHTML = '<div class=\"alert alert-warning\"><i class=\"fas fa-exclamation-triangle me-2\"></i>Please select a file first!</div>';
                    return;
                }

                var formData = new FormData();
                formData.append('fileToUpload', fileInput.files[0]);
                formData.append('fileType', 'Bond NAV');
                formData.append('remarks', remarks);

                document.getElementById('bondNavProgress').style.display = 'block';
                resultDiv.innerHTML = '';
                uploadBtn.disabled = true;

                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'extractmasfiletodb.php', true);
                xhr.timeout = 300000;
                xhr.onload = function() {
                    document.getElementById('bondNavProgress').style.display = 'none';
                    uploadBtn.disabled = false;
                    if (xhr.status === 200) {
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            var isSuccess = xhr.responseText.toLowerCase().includes('success');
                            var alertClass = isSuccess ? 'alert-success' : 'alert-danger';
                            var icon = isSuccess ? 'fa-check-circle' : 'fa-times-circle';
                            resultDiv.innerHTML = '<div class=\"alert ' + alertClass + ' mt-3\"><i class=\"fas ' + icon + ' me-2\"></i>' + resp.message + '</div>';
                        } catch(e) {
                            resultDiv.innerHTML = '<div class=\"alert alert-danger mt-3\">Invalid server response format.<br><small style=\"word-break:break-all;\">Raw Response: ' + xhr.responseText.substring(0, 300) + '</small></div>';
                        }
                    } else {
                        resultDiv.innerHTML = '<div class=\"alert alert-danger mt-3\"><i class=\"fas fa-exclamation-circle me-2\"></i>An error occurred during upload (HTTP ' + xhr.status + ').</div>';
                    }
                };
                xhr.onerror = function() {
                    document.getElementById('bondNavProgress').style.display = 'none';
                    uploadBtn.disabled = false;
                    resultDiv.innerHTML = '<div class=\"alert alert-danger mt-3\"><i class=\"fas fa-exclamation-circle me-2\"></i>A network error occurred during upload.</div>';
                };
                xhr.ontimeout = function() {
                    document.getElementById('bondNavProgress').style.display = 'none';
                    uploadBtn.disabled = false;
                    resultDiv.innerHTML = '<div class=\"alert alert-danger mt-3\"><i class=\"fas fa-exclamation-circle me-2\"></i>Upload timed out. Please try again.</div>';
                };
                xhr.send(formData);
            }

            function fetchSIFFromAMFIHistory() {
                if (confirm('Fetch SIF NAV data from AMFI History? This may take a few minutes.')) {
                    document.getElementById('mfProgress').style.display = 'block';
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', navPageUrl + '?fetch_sif_amfi_history=1', true);
                    xhr.timeout = 600000;
                    xhr.onload = function() {
                        document.getElementById('mfProgress').style.display = 'none';
                        if (xhr.status === 200) { document.getElementById('main_content').innerHTML = xhr.responseText; }
                        else { alert('Error fetching SIF NAV from AMFI (HTTP ' + xhr.status + ')'); }
                    };
                    xhr.onerror = function() { document.getElementById('mfProgress').style.display = 'none'; alert('Network error while fetching SIF NAV from AMFI.'); };
                    xhr.ontimeout = function() { document.getElementById('mfProgress').style.display = 'none'; alert('SIF AMFI fetch timed out.'); };
                    xhr.send();
                }
            }
            
            function loadPage(url) {
                document.getElementById('main_content').innerHTML = '<div class=\"text-center mt-5\"><div class=\"spinner-border text-primary\"></div><p class=\"mt-2\">Loading...</p></div>';
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.onload = function() {
                    if (xhr.status === 200) { document.getElementById('main_content').innerHTML = xhr.responseText; }
                    else { document.getElementById('main_content').innerHTML = '<div class=\"alert alert-danger\">Error loading page.</div>'; }
                };
                xhr.onerror = function() { document.getElementById('main_content').innerHTML = '<div class=\"alert alert-danger\">Network error.</div>'; };
                xhr.send();
            }

            // ── Global MF Search JS ──
            let globalMFSearchTimeout;
            function searchGlobalMFNav() {
                const query = document.getElementById('global_mf_search').value;
                const resultsDiv = document.getElementById('global_mf_results');
                const tbody = document.getElementById('global_mf_tbody');
                
                if (query.trim().length === 0) {
                    resultsDiv.style.display = 'none';
                    return;
                }
                
                resultsDiv.style.display = 'block';
                tbody.innerHTML = '<tr><td colspan=\"5\" class=\"nm-no-data\" style=\"padding:15px;\">Searching...</td></tr>';
                
                clearTimeout(globalMFSearchTimeout);
                globalMFSearchTimeout = setTimeout(function() {
                    fetch('update_nav.php?method=searchMFNav&q=' + encodeURIComponent(query), { credentials: 'same-origin' })
                    .then(res => {
                        if(!res.ok) throw new Error('Network response was not ok');
                        return res.json();
                    })
                    .then(data => {
                        if (!data || data.length === 0) {
                            const escapedQuery = query.replace(/[&<>\'\"]/g, function(m) {
                                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#039;' }[m];
                            });
                            tbody.innerHTML = '<tr><td colspan=\"5\" class=\"nm-no-data\" style=\"padding:15px;\">No records found matching \\'' + escapedQuery + '\\'</td></tr>';
                            return;
                        }
                        
                        let html = '';
                        data.forEach(row => {
                            let sName = row.scheme_name || '';
                            const escapedIsin = row.isin ? row.isin.replace(/[&<>\'\"]/g, function(m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#039;' }[m]; }) : '';
                            const escapedCode = row.scheme_code ? row.scheme_code.replace(/[&<>\'\"]/g, function(m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#039;' }[m]; }) : '';
                            const escapedName = sName.replace(/[&<>\'\"]/g, function(m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#039;' }[m]; });
                            const escapedNav = row.nav ? String(row.nav).replace(/[&<>\'\"]/g, function(m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#039;' }[m]; }) : '';
                            const escapedDate = row.nav_date ? row.nav_date.replace(/[&<>\'\"]/g, function(m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#039;' }[m]; }) : '';
                            
                            html += '<tr>' +
                                '<td style=\"padding:8px; border-bottom:1px solid #eee;\"><small>' + escapedIsin + '</small></td>' +
                                '<td style=\"padding:8px; border-bottom:1px solid #eee;\"><small>' + escapedCode + '</small></td>' +
                                '<td style=\"padding:8px; border-bottom:1px solid #eee;\"><small>' + escapedName + '</small></td>' +
                                '<td style=\"padding:8px; border-bottom:1px solid #eee;\"><small>' + escapedNav + '</small></td>' +
                                '<td style=\"padding:8px; border-bottom:1px solid #eee;\"><small>' + escapedDate + '</small></td>' +
                            '</tr>';
                        });
                        tbody.innerHTML = html;
                    })
                    .catch(err => {
                        console.error(\"Search Error: \", err);
                        tbody.innerHTML = '<tr><td colspan=\"5\" class=\"nm-no-data\" style=\"color:#e53e3e; padding:20px; font-weight:bold;\">Error fetching records. Check console for details.</td></tr>';
                    });
                }, 400);
            }

            // ── NAV Management JS (from nav_management.php, prefixed with nm_) ────

            var nmCurrentNavType = 'pms';
            var nmCurrentUCC = '';
            var nmAllData = [];
            var nmFilteredData = [];
            var nmCurrentPage = 1;
            var nmPageSize = 10;

            function nmSwitchNavType(type) {
                nmCurrentNavType = type;
                document.querySelectorAll('.nm-type-btn').forEach(function(btn) { btn.classList.remove('active'); });
                document.querySelector('.nm-type-btn[data-type=\"' + type + '\"]').classList.add('active');
                var headers = { 'pms': 'Add PMS Current Value', 'aif_nav': 'Update AIF Current NAV', 'aif_payout': 'Add AIF Payout' };
                document.getElementById('nm_formhead').innerHTML = headers[type];
                document.getElementById('nm_pms_form').style.display = 'none';
                document.getElementById('nm_aif_nav_form').style.display = 'none';
                document.getElementById('nm_aif_payout_form').style.display = 'none';
                if (type === 'pms') {
                    document.getElementById('nm_pms_form').style.display = 'flex';
                    nmUpdateNamesDropdown('pms');
                    nmUpdateTableHeaders('pms');
                } else if (type === 'aif_nav') {
                    document.getElementById('nm_aif_nav_form').style.display = 'flex';
                    nmUpdateNamesDropdown('aif_nav');
                    nmUpdateTableHeaders('aif_nav');
                } else if (type === 'aif_payout') {
                    document.getElementById('nm_aif_payout_form').style.display = 'flex';
                    nmUpdateNamesDropdown('aif_payout');
                    nmUpdateTableHeaders('aif_payout');
                }
                nmResetFormByType(type);
                nmFetchData();
            }

            function nmUpdateTableHeaders(type) {
                var thead = document.getElementById('nm_table_header');
                if (type === 'pms') {
                    thead.innerHTML = '<tr><th>PMS Name</th><th>Date</th><th>Current Value</th><th width=\"80\">Edit</th><th width=\"80\">Delete</th></tr>';
                } else if (type === 'aif_nav') {
                    thead.innerHTML = '<tr><th>AIF Name</th><th>Date</th><th>NAV Value</th><th width=\"80\">Edit</th><th width=\"80\">Delete</th></tr>';
                } else if (type === 'aif_payout') {
                    thead.innerHTML = '<tr><th>AIF Name</th><th>Date</th><th>Amount</th><th>Remarks</th><th width=\"80\">Edit</th><th width=\"80\">Delete</th></tr>';
                }
            }

            function nmUpdateNamesDropdown(type) {
                if (!nmCurrentUCC) return;
                if (type === 'pms') {
                    var sel = document.getElementById('nm_pms_name');
                    sel.innerHTML = '<option value=\"\">Loading...</option>';
                    fetch('getInvesteddata.php?method=getPmsNames&UCC=' + encodeURIComponent(nmCurrentUCC))
                        .then(function(r) { return r.text(); })
                        .then(function(html) { sel.innerHTML = html; });
                } else if (type === 'aif_nav' || type === 'aif_payout') {
                    var selId = (type === 'aif_nav') ? 'nm_aif_nav_name' : 'nm_aif_payout_name';
                    var sel = document.getElementById(selId);
                    sel.innerHTML = '<option value=\"\">Loading...</option>';
                    fetch('getInvesteddata.php?method=getAifNames&UCC=' + encodeURIComponent(nmCurrentUCC))
                        .then(function(r) { return r.text(); })
                        .then(function(html) { sel.innerHTML = html; });
                }
            }

            function nmResetFormByType(type) {
                if (type === 'pms') {
                    document.getElementById('nm_pms_slno').value = '';
                    document.getElementById('nm_pms_current_value').value = '';
                    document.getElementById('nm_pms_current_date').value = '';
                    document.getElementById('nm_pms_action').value = 'add';
                    document.getElementById('nm_pms_submit_btn').innerHTML = 'Add PMS';
                    document.getElementById('nm_formhead').innerHTML = 'Add PMS Current Value';
                } else if (type === 'aif_nav') {
                    document.getElementById('nm_aif_nav_slno').value = '';
                    document.getElementById('nm_aif_nav_current_value').value = '';
                    document.getElementById('nm_aif_nav_current_date').value = '';
                    document.getElementById('nm_aif_nav_action').value = 'add';
                    document.getElementById('nm_aif_nav_submit_btn').innerHTML = 'Update NAV';
                    document.getElementById('nm_formhead').innerHTML = 'Update AIF Current NAV';
                } else if (type === 'aif_payout') {
                    document.getElementById('nm_aif_payout_slno').value = '';
                    document.getElementById('nm_aif_payout_amount').value = '';
                    document.getElementById('nm_aif_payout_date').value = '';
                    document.getElementById('nm_aif_payout_remarks').value = '';
                    document.getElementById('nm_aif_payout_action').value = 'add';
                    document.getElementById('nm_aif_payout_submit_btn').innerHTML = 'Add AIF Payout';
                    document.getElementById('nm_formhead').innerHTML = 'Add AIF Payout';
                }
            }

            function nmAddPmsRecord() {
                var UCC = nmCurrentUCC;
                var slno = document.getElementById('nm_pms_slno').value || 0;
                var name = document.getElementById('nm_pms_name').value;
                var currentValue = document.getElementById('nm_pms_current_value').value || 0;
                var currentDate = document.getElementById('nm_pms_current_date').value || '';
                var action = document.getElementById('nm_pms_action').value;
                if (!name) { nmSetStatus('Please select PMS Name', 'red'); return; }
                if (!currentValue || currentValue == 0) { nmSetStatus('Please enter current value', 'red'); return; }
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'update_nav.php', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        nmSetStatus(xhr.responseText, xhr.responseText.includes('Error') ? 'red' : 'green');
                        nmFetchData(); nmResetFormByType('pms');
                    }
                };
                xhr.send('method=updatePmsNav&tp=pms&UCC=' + encodeURIComponent(UCC) + '&name=' + encodeURIComponent(name) + '&current_value=' + currentValue + '&current_date=' + currentDate + '&action=' + action + '&slno=' + slno);
            }

            function nmAddAifNavRecord() {
                var UCC = nmCurrentUCC;
                var slno = document.getElementById('nm_aif_nav_slno').value || 0;
                var name = document.getElementById('nm_aif_nav_name').value;
                var currentValue = document.getElementById('nm_aif_nav_current_value').value || 0;
                var currentDate = document.getElementById('nm_aif_nav_current_date').value || '';
                var action = document.getElementById('nm_aif_nav_action').value;
                if (!name) { nmSetStatus('Please select AIF Name', 'red'); return; }
                if (!currentValue || currentValue == 0) { nmSetStatus('Please enter NAV value', 'red'); return; }
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'update_nav.php', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        nmSetStatus(xhr.responseText, xhr.responseText.includes('Error') ? 'red' : 'green');
                        nmFetchData(); nmResetFormByType('aif_nav');
                    }
                };
                xhr.send('method=updateAifNav&tp=aif&UCC=' + encodeURIComponent(UCC) + '&name=' + encodeURIComponent(name) + '&current_value=' + currentValue + '&current_date=' + currentDate + '&action=' + action + '&slno=' + slno);
            }

            function nmAddAifPayoutRecord() {
                var UCC = nmCurrentUCC;
                var slno = document.getElementById('nm_aif_payout_slno').value || 0;
                var name = document.getElementById('nm_aif_payout_name').value;
                var payoutValue = document.getElementById('nm_aif_payout_amount').value || 0;
                var payoutDate = document.getElementById('nm_aif_payout_date').value || '';
                var remarks = document.getElementById('nm_aif_payout_remarks').value || '';
                var action = document.getElementById('nm_aif_payout_action').value;
                if (!name) { nmSetStatus('Please select AIF Name', 'red'); return; }
                if (!payoutValue || payoutValue == 0) { nmSetStatus('Please enter payout amount', 'red'); return; }
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'update_nav.php', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        nmSetStatus(xhr.responseText, xhr.responseText.includes('Error') ? 'red' : 'green');
                        nmFetchData();
                        if (action !== 'edit') {
                            document.getElementById('nm_aif_payout_amount').value = '';
                            document.getElementById('nm_aif_payout_date').value = '';
                            document.getElementById('nm_aif_payout_remarks').value = '';
                        } else { nmResetFormByType('aif_payout'); }
                    }
                };
                xhr.send('method=updateAifPayout&tp=aif&UCC=' + encodeURIComponent(UCC) + '&name=' + encodeURIComponent(name) + '&payout_amount=' + payoutValue + '&payout_date=' + payoutDate + '&remarks=' + encodeURIComponent(remarks) + '&action=' + action + '&slno=' + slno);
            }

            function nmEditRecord(slno, type) {
                if (type === 'pms') {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'update_nav.php?method=getPmsNavDataBySlno&nav_slno=' + slno);
                    xhr.onload = function() {
                        var data = JSON.parse(xhr.responseText);
                        document.getElementById('nm_pms_name').value = data.pms_name;
                        document.getElementById('nm_pms_current_value').value = data.cur_value;
                        document.getElementById('nm_pms_current_date').value = data.nav_date;
                        document.getElementById('nm_pms_action').value = 'edit';
                        document.getElementById('nm_pms_slno').value = slno;
                        document.getElementById('nm_pms_submit_btn').innerHTML = 'Update PMS';
                        document.getElementById('nm_formhead').innerHTML = 'Edit PMS Current Value';
                    };
                    xhr.send();
                } else if (type === 'aif_nav') {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'update_nav.php?method=getAifNavDataBySlno&nav_slno=' + slno);
                    xhr.onload = function() {
                        var data = JSON.parse(xhr.responseText);
                        document.getElementById('nm_aif_nav_name').value = data.aif_name;
                        document.getElementById('nm_aif_nav_current_value').value = data.cur_value;
                        document.getElementById('nm_aif_nav_current_date').value = data.nav_date;
                        document.getElementById('nm_aif_nav_action').value = 'edit';
                        document.getElementById('nm_aif_nav_slno').value = slno;
                        document.getElementById('nm_aif_nav_submit_btn').innerHTML = 'Update AIF';
                        document.getElementById('nm_formhead').innerHTML = 'Edit AIF Current NAV';
                    };
                    xhr.send();
                } else if (type === 'aif_payout') {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'update_nav.php?method=getAifPayoutDataBySlno&slno=' + slno);
                    xhr.onload = function() {
                        var data = JSON.parse(xhr.responseText);
                        document.getElementById('nm_aif_payout_name').value = data.aif_name;
                        document.getElementById('nm_aif_payout_amount').value = data.payout_amount;
                        document.getElementById('nm_aif_payout_date').value = data.payout_date;
                        document.getElementById('nm_aif_payout_remarks').value = data.remarks || '';
                        document.getElementById('nm_aif_payout_action').value = 'edit';
                        document.getElementById('nm_aif_payout_slno').value = slno;
                        document.getElementById('nm_aif_payout_submit_btn').innerHTML = 'Update AIF Payout';
                        document.getElementById('nm_formhead').innerHTML = 'Edit AIF Payout';
                    };
                    xhr.send();
                }
            }

            function nmDeleteRecord(investmentId, iName, type) {
                if (!confirm('Are you sure you want to delete ' + iName + '?')) return;
                var method = '';
                if (type === 'pms') method = 'del_pms_nav';
                else if (type === 'aif_nav') method = 'del_aif_nav';
                else if (type === 'aif_payout') method = 'del_aif_payout';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'update_nav.php', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        nmSetStatus(xhr.responseText, xhr.responseText.includes('Error') ? 'red' : 'green');
                        nmFetchData(); nmResetFormByType(nmCurrentNavType);
                    }
                };
                xhr.send('method=' + method + '&investmentId=' + investmentId + '&iname=' + encodeURIComponent(iName));
            }

            function nmFetchData() {
                var method = '';
                if (nmCurrentNavType === 'pms') method = 'getPmsNavData';
                else if (nmCurrentNavType === 'aif_nav') method = 'getAifNavData';
                else if (nmCurrentNavType === 'aif_payout') method = 'getAifPayoutData';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'update_nav.php', true);
                xhr.responseType = 'json';
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        nmAllData = xhr.response || [];
                        nmFilteredData = nmAllData.slice();
                        nmCurrentPage = 1;
                        document.getElementById('nm_search_input').value = '';
                        nmRenderTable();
                    }
                };
                xhr.send('method=' + method + '&UCC=' + encodeURIComponent(nmCurrentUCC));
            }

            function nmSearchData() {
                var term = document.getElementById('nm_search_input').value.toLowerCase();
                nmFilteredData = term === '' ? nmAllData.slice() : nmAllData.filter(function(item) {
                    var name = item.pms_name || item.aif_name || '';
                    var date = item.nav_date || item.payout_date || '';
                    var value = String(item.cur_value || item.payout_amount || '');
                    var remarks = item.remarks || '';
                    return name.toLowerCase().indexOf(term) !== -1 || date.toLowerCase().indexOf(term) !== -1 || value.toLowerCase().indexOf(term) !== -1 || remarks.toLowerCase().indexOf(term) !== -1;
                });
                nmCurrentPage = 1;
                nmRenderTable();
            }

            function nmChangePageSize() {
                nmPageSize = parseInt(document.getElementById('nm_entries_per_page').value);
                nmCurrentPage = 1;
                nmRenderTable();
            }

            function nmRenderTable() {
                var tbody = document.getElementById('nm_table_body');
                var total = nmFilteredData.length;
                var totalPages = Math.ceil(total / nmPageSize);
                var startIdx = (nmCurrentPage - 1) * nmPageSize;
                var endIdx = Math.min(startIdx + nmPageSize, total);
                var pageData = nmFilteredData.slice(startIdx, endIdx);
                tbody.innerHTML = '';
                if (pageData.length === 0) {
                    var cols = (nmCurrentNavType === 'aif_payout') ? 6 : 5;
                    tbody.innerHTML = '<tr><td colspan=\"' + cols + '\" class=\"nm-no-data\">No records found</td></tr>';
                } else {
                    for (var i = 0; i < pageData.length; i++) {
                        var item = pageData[i];
                        var row = '<tr>';
                        if (nmCurrentNavType === 'pms') {
                            row += '<td>' + nmEscape(item.pms_name || '') + '</td>';
                            row += '<td>' + nmEscape(item.nav_date || '') + '</td>';
                            row += '<td>' + nmEscape(item.cur_value || '') + '</td>';
                            row += '<td><a class=\"nm-action-btn\" onclick=\"nmEditRecord(' + item.slno + ',\\'pms\\'); return false;\">Edit</a></td>';
                            row += '<td><a class=\"nm-action-btn nm-delete-btn\" onclick=\"nmDeleteRecord(' + item.slno + ',\\'' + nmEscape(item.pms_name || '') + '\\',\\'pms\\'); return false;\">Delete</a></td>';
                        } else if (nmCurrentNavType === 'aif_nav') {
                            row += '<td>' + nmEscape(item.aif_name || '') + '</td>';
                            row += '<td>' + nmEscape(item.nav_date || '') + '</td>';
                            row += '<td>' + nmEscape(item.cur_value || '') + '</td>';
                            row += '<td><a class=\"nm-action-btn\" onclick=\"nmEditRecord(' + item.slno + ',\\'aif_nav\\'); return false;\">Edit</a></td>';
                            row += '<td><a class=\"nm-action-btn nm-delete-btn\" onclick=\"nmDeleteRecord(' + item.slno + ',\\'' + nmEscape(item.aif_name || '') + '\\',\\'aif_nav\\'); return false;\">Delete</a></td>';
                        } else if (nmCurrentNavType === 'aif_payout') {
                            row += '<td>' + nmEscape(item.aif_name || '') + '</td>';
                            row += '<td>' + nmEscape(item.payout_date || '') + '</td>';
                            row += '<td>' + nmEscape(item.payout_amount || '') + '</td>';
                            row += '<td>' + nmEscape(item.remarks || '') + '</td>';
                            row += '<td><a class=\"nm-action-btn\" onclick=\"nmEditRecord(' + item.slno + ',\\'aif_payout\\'); return false;\">Edit</a></td>';
                            row += '<td><a class=\"nm-action-btn nm-delete-btn\" onclick=\"nmDeleteRecord(' + item.slno + ',\\'' + nmEscape(item.aif_name || '') + '\\',\\'aif_payout\\'); return false;\">Delete</a></td>';
                        }
                        row += '</tr>';
                        tbody.innerHTML += row;
                    }
                }
                var info = document.getElementById('nm_entries_info');
                if (total === 0) {
                    info.innerHTML = 'Showing 0 to 0 of 0 entries';
                } else {
                    var txt = 'Showing ' + (startIdx + 1) + ' to ' + endIdx + ' of ' + total + ' entries';
                    if (nmAllData.length !== total) txt += ' (filtered from ' + nmAllData.length + ' total entries)';
                    info.innerHTML = txt;
                }
                nmRenderPagination(totalPages);
            }

            function nmEscape(text) {
                if (!text) return '';
                var d = document.createElement('div');
                d.appendChild(document.createTextNode(text));
                return d.innerHTML;
            }

            function nmRenderPagination(totalPages) {
                var div = document.getElementById('nm_pagination_buttons');
                div.innerHTML = '';
                if (totalPages <= 1) return;
                var prev = document.createElement('button');
                prev.className = 'nm-page-btn'; prev.innerHTML = 'Previous'; prev.disabled = (nmCurrentPage === 1);
                prev.onclick = function() { if (nmCurrentPage > 1) { nmCurrentPage--; nmRenderTable(); } };
                div.appendChild(prev);
                var maxBtns = 5, start = Math.max(1, nmCurrentPage - 2), end = Math.min(totalPages, start + maxBtns - 1);
                start = Math.max(1, end - maxBtns + 1);
                for (var i = start; i <= end; i++) {
                    var btn = document.createElement('button');
                    btn.className = 'nm-page-btn' + (i === nmCurrentPage ? ' active' : '');
                    btn.innerHTML = i;
                    btn.onclick = (function(p) { return function() { nmCurrentPage = p; nmRenderTable(); }; })(i);
                    div.appendChild(btn);
                }
                var next = document.createElement('button');
                next.className = 'nm-page-btn'; next.innerHTML = 'Next'; next.disabled = (nmCurrentPage >= totalPages);
                next.onclick = function() { if (nmCurrentPage < totalPages) { nmCurrentPage++; nmRenderTable(); } };
                div.appendChild(next);
            }

            function nmSetStatus(msg, color) {
                var el = document.getElementById('nm-status');
                el.innerHTML = msg; el.style.color = color || '#333';
            }

            function nmSelectCustomer() {
                var UCC = document.getElementById('nm_custUCC').value.trim();
                if (UCC === '') { nmSetStatus('Please enter Customer UCC', 'red'); return; }
                nmGetUCCName(UCC, function(response) {
                    if (response == false) {
                        nmSetStatus('Error: UCC ' + UCC + ' does not exist!', 'red');
                        return;
                    }
                    nmSetStatus(response, 'blue');
                    nmCurrentUCC = UCC;
                    // Hide AMFI import section, show management
                    document.getElementById('amfi_import_section').style.display = 'none';
                    var mgmtDiv = document.getElementById('nm_management_div');
                    mgmtDiv.style.display = 'none';
                    nmSwitchNavType('pms');
                    mgmtDiv.style.display = 'block';
                    mgmtDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }

            function nmClearCustomer() {
                nmCurrentUCC = '';
                document.getElementById('nm_custUCC').value = '';
                nmSetStatus('', '');
                document.getElementById('nm_management_div').style.display = 'none';
                document.getElementById('amfi_import_section').style.display = 'block';
                document.getElementById('amfi_import_section').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            function nmGetUCCName(UCC, callback) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'getInvesteddata.php', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) { callback(JSON.parse(xhr.responseText)); }
                };
                xhr.send('method=getUCCName&UCC=' + encodeURIComponent(UCC));
            }

            // Autocomplete for UCC input (using event delegation for AJAX compatibility)
            var jq = window.jQuery || window.$;
            if (jq) {
                jq(document).off('input.autoAmfi').on('input.autoAmfi', '#nm_custUCC', function() {
                    var inputEl = this;
                    var val = inputEl.value.toLowerCase();
                    var list = document.getElementById('nm-autocomplete-list');
                    if (list) list.innerHTML = '';
                    if (val === '') { if (list) list.style.display = 'none'; return; }
                    fetch('getUCClistwithname.php')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var matching = data.filter(function(e) {
                                return e.UCC.toLowerCase().indexOf(val) !== -1 || e.name.toLowerCase().indexOf(val) !== -1;
                            });
                            for (var i = 0; i < matching.length; i++) {
                                var e = matching[i];
                                var opt = document.createElement('div');
                                opt.innerHTML = e.UCC + ' - ' + e.name;
                                opt.addEventListener('mousedown', (function(ucc) {
                                    return function(evt) {
                                        evt.preventDefault();
                                        inputEl.value = ucc;
                                        if (list) {
                                            list.innerHTML = '';
                                            list.style.display = 'none';
                                        }
                                    };
                                })(e.UCC));
                                if (list) list.appendChild(opt);
                            }
                            if (list) list.style.display = matching.length > 0 ? 'block' : 'none';
                        })
                        .catch(function(err) { console.error(err); });
                });

                // Allow Enter key to trigger Select Customer
                jq(document).off('keydown.autoAmfi').on('keydown.autoAmfi', '#nm_custUCC', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); nmSelectCustomer(); }
                });

                jq(document).off('click.autoAmfi').on('click.autoAmfi', function(e) {
                    if (e.target.id !== 'nm_custUCC') {
                        var list = document.getElementById('nm-autocomplete-list');
                        if (list) list.style.display = 'none';
                    }
                });
            }
        </script>";
    }
    
    /**
     * Display import results (content only)
     */
    private function displayResults($result, $filePath, $duration = 0) {
        $counts = $this->db->getRecordCountsByType();
        $growthCount = isset($counts['growth_count']) ? $counts['growth_count'] : 0;
        $reinvestCount = isset($counts['reinvest_count']) ? $counts['reinvest_count'] : 0;
        $totalCount = isset($counts['total_count']) ? $counts['total_count'] : 0;
        
        $fileSizeDisplay = file_exists($filePath) ? round(filesize($filePath) / 1024, 2) . " KB" : "N/A (API/Live)";

        echo "<div class='card'>
                <div class='card-header bg-success text-white d-flex justify-content-between align-items-center'>
                    <h5 class='mb-0'><i class='fas fa-check-circle'></i> Import Results</h5>
                    <button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-light btn-sm text-success fw-bold'>
                        <i class='fas fa-arrow-left'></i> Upload Another File
                    </button>
                </div>
                <div class='card-body'>
                    <div class='alert alert-secondary'>
                        <i class='fas fa-file'></i> <strong>File:</strong> " . basename($filePath) . " | 
                        <strong>Size:</strong> " . $fileSizeDisplay . " |
                        <strong>Completed at:</strong> " . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('h:i:s A') . " IST |
                        <strong>Duration:</strong> " . $duration . " sec
                    </div>
                    
                    <div class='row text-center mb-4'>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3 class='text-success'>" . $result['inserted'] . "</h3>
                                <small>Inserted</small>
                            </div>
                        </div>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3 class='text-warning'>" . $result['updated'] . "</h3>
                                <small>Updated</small>
                            </div>
                        </div>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3 class='text-info'>" . (isset($result['already_exist']) ? $result['already_exist'] : 0) . "</h3>
                                <small>Already Exist</small>
                            </div>
                        </div>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3 class='text-danger'>" . $result['failed'] . "</h3>
                                <small>Failed</small>
                            </div>
                        </div>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3>" . (isset($result['total']) ? $result['total'] : 0) . "</h3>
                                <small>Total</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class='mb-3'>
                        <span class='badge bg-success p-2'>Growth: " . $growthCount . "</span>
                        <span class='badge bg-warning text-dark p-2 ms-2'>Reinvest: " . $reinvestCount . "</span>
                        <span class='badge bg-info p-2 ms-2'>Total DB: " . $totalCount . "</span>
                    </div>";
        
        $this->showRecentRecordsMinimal();
        
        $logContent = '';
        if (file_exists($this->logFile)) {
            $logs = file($this->logFile);
            $logs = array_slice($logs, -20);
            $logContent = implode('', array_reverse($logs));
        }
        
        echo "<h6 class='mt-4 mb-2'>Logs:</h6>
              <pre style='max-height: 200px; overflow: auto; font-size: 11px; background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px;'>" . htmlspecialchars($logContent) . "</pre>
              
              <button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>
                  <i class='fas fa-arrow-left'></i> Upload Another File
              </button>
              
              <script>
              function loadPage(url) {
                  document.getElementById('main_content').innerHTML = '<div class=\"text-center mt-5\"><div class=\"spinner-border text-primary\"></div><p class=\"mt-2\">Loading...</p></div>';
                  var xhr = new XMLHttpRequest();
                  xhr.open('GET', url, true);
                  xhr.onload = function() {
                      if (xhr.status === 200) { document.getElementById('main_content').innerHTML = xhr.responseText; }
                  };
                  xhr.send();
              }
              </script>
            </div>
          </div>";
    }
    
    /**
     * Display MF import results
     */
    private function displayMFResults($fetchResult) {
        $result = $fetchResult['result'];
        $duration = isset($fetchResult['duration']) ? $fetchResult['duration'] : 0;
        $source = isset($fetchResult['source']) ? $fetchResult['source'] : 'Unknown';
        
        echo "<div class='card'>
                <div class='card-header bg-success text-white d-flex justify-content-between align-items-center'>
                    <h5 class='mb-0'><i class='fas fa-check-circle'></i> MF NAV Import Results</h5>
                    <button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-light btn-sm text-success fw-bold'>
                        <i class='fas fa-arrow-left'></i> Back
                    </button>
                </div>
                <div class='card-body'>
                    <div class='alert alert-secondary'>
                        <i class='fas fa-database'></i> <strong>Source:</strong> " . htmlspecialchars($source) . " |
                        <strong>Completed at:</strong> " . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('h:i:s A') . " IST |
                        <strong>Duration:</strong> " . $duration . " sec
                    </div>
                    
                    <div class='row text-center mb-4'>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3 class='text-success'>" . $result['inserted'] . "</h3>
                                <small>Inserted / Saved</small>
                            </div>
                        </div>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3 class='text-warning'>" . $result['updated'] . "</h3>
                                <small>Updated / Existing</small>
                            </div>
                        </div>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3 class='text-info'>" . (isset($result['already_exist']) ? $result['already_exist'] : 0) . "</h3>
                                <small>Already Exist</small>
                            </div>
                        </div>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3 class='text-danger'>" . $result['failed'] . "</h3>
                                <small>Failed</small>
                            </div>
                        </div>
                        <div class='col'>
                            <div class='border rounded p-3'>
                                <h3>" . (isset($result['total']) ? $result['total'] : 0) . "</h3>
                                <small>Total Processed</small>
                            </div>
                        </div>
                    </div>";
        
        $logContent = '';
        if (file_exists($this->logFile)) {
            $logs = file($this->logFile);
            $logs = array_slice($logs, -20);
            $logContent = implode('', array_reverse($logs));
        }
        
        echo "<h6 class='mt-4 mb-2'>Logs:</h6>
              <pre style='max-height: 200px; overflow: auto; font-size: 11px; background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px;'>" . htmlspecialchars($logContent) . "</pre>
              
              <button onclick='loadPage(\"getnav-from-amfi.php\")' class='btn btn-secondary mt-3'>
                  <i class='fas fa-arrow-left'></i> Back
              </button>";
        
        $this->outputLoadPageScript();
        
        echo "  </div>
              </div>";
    }
    
    /**
     * Show recent records (minimal version)
     */
    private function showRecentRecordsMinimal() {
        $result = $this->db->getRecentSIFNAVRecords(500);
        
        if (is_array($result)) {
            if (empty($result)) {
                echo "<p class='text-muted mt-3'>No recent records found.</p>";
                return;
            }
            
            echo "<h6 class='mt-3 mb-2'>Recent Records:</h6>
                  <div class='table-responsive' style='max-height: 300px; overflow-y: auto;'>
                    <table class='table table-sm table-bordered'>
                        <thead class='thead-light'>
                            <tr>
                                <th>ISIN</th>
                                <th>Code</th>
                                <th>Scheme name</th>
                                <th>NAV</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>";
            
            foreach ($result as $row) {
                echo "<tr>
                        <td><small>" . htmlspecialchars($row['isin'] ?? '') . "</small></td>
                        <td><small>{$row['scheme_code']}</small></td>
                        <td><small>{$row['scheme_name']}</small></td>
                        <td><small>{$row['nav']}</small></td>
                        <td><small>{$row['nav_date']}</small></td>
                      </tr>";
            }
            
            echo "      </tbody>
                    </table>
                  </div>";
            
        } elseif ($result && $result instanceof mysqli_result) {
            if (mysqli_num_rows($result) > 0) {
                echo "<h6 class='mt-3 mb-2'>Recent Records:</h6>
                      <div class='table-responsive' style='max-height: 300px; overflow-y: auto;'>
                        <table class='table table-sm table-bordered'>
                            <thead class='thead-light'>
                                <tr>
                                    <th>ISIN</th>
                                    <th>Code</th>
                                    <th>Scheme name</th>
                                    <th>NAV</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>";
                
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>
                            <td><small>" . htmlspecialchars($row['isin'] ?? '') . "</small></td>
                            <td><small>{$row['scheme_code']}</small></td>
                            <td><small>{$row['scheme_name']}</small></td>
                            <td><small>{$row['nav']}</small></td>
                            <td><small>{$row['nav_date']}</small></td>
                          </tr>";
                }
                
                echo "      </tbody>
                        </table>
                      </div>";
                mysqli_free_result($result);
            } else {
                echo "<p class='text-muted mt-3'>No recent records found.</p>";
            }
        } else {
            echo "<p class='text-muted mt-3'>No recent records found.</p>";
        }
    }
}

// Main execution - only output content, no HTML wrapper
$importer = new NAVImporter();
$importer->displayPage();
?>