
<?php
require_once('auth_guard.php');

// getnav-from-Nse.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/database-nseinvest-Api.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
	$mfdb = new DB_NSEINVEST_API();  
    $apiResult = $mfdb->callNseApi('/v2/reports/MASTER_DOWNLOAD', [
        "file_type" => "SCH"
    ]);
    $httpCode = $apiResult['http_code'] ?? 0;
    $response = $apiResult['response'] ?? '';
} catch (Throwable $e) {
    echo "<h2>API Error</h2><pre>" . h($e->getMessage()) . "</pre>";
    exit;
}


// Save raw response for inspection
$responseFile = __DIR__ . '/nse_api_response.json';
@file_put_contents($responseFile, $response);

