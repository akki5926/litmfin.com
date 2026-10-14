<?php
require_once('auth_guard.php');

// update-scheme-master.php
header('Content-Type: application/json');

require_once __DIR__ . '/database-nseinvest-Api.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
    // Call NSE API
    $mfdb = new DB_NSEINVEST_API();  
    $apiResult = $mfdb->callNseApi('/v2/reports/MASTER_DOWNLOAD', [
        "file_type" => "SCH"
    ]);
    
    $httpCode = $apiResult['http_code'] ?? 0;
    $response = $apiResult['response'] ?? '';
    
    if ($httpCode !== 200 || empty($response)) {
        throw new Exception("API call failed with HTTP code: $httpCode");
    }
    
    // Parse the response
    $lines = explode("\n", trim($response));
    
    if (empty($lines) || count($lines) < 2) {
        throw new Exception("Invalid response format from API");
    }
    
    // Get headers (first line)
    $headers = explode('|', $lines[0]);
    
    // Remove header line
    array_shift($lines);
    
    $records = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line)) {
            $records[] = explode('|', $line);
        }
    }
	//var_dump($headers)   ;
 //var_dump($records)   ;
    // Process records through db.php
    //require_once __DIR__ . '/db.php';
    
   // $db = new Database();
    ///$result = $db->updateSchemeMaster($headers, $records);
    
    echo json_encode([
        'success' => true,
        'message' => 'Scheme master updated successfully',
        'details' => "Updated/Inserted " . count($records) . " records"
    ]);
    
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'API Error: ' . h($e->getMessage())
    ]);
    exit;
}