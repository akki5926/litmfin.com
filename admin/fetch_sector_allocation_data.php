<?php
require_once('auth_guard.php');


set_time_limit(0);

require_once __DIR__ . '/database.php';

$db  = new MYSQLDB();
$pdo = $db->getPDO();

echo "<pre>";

echo "STEP 1: Fetching ISINs...\n";

// --- Fetch ISINs ---
$funds = $db->getDistinctISINs();


// Save JSON for Python
//file_put_contents(__DIR__ . "/data/fund_list.json", json_encode($funds, JSON_PRETTY_PRINT));

$dataDir = __DIR__ . "/data";

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

file_put_contents($dataDir . "/fund_list.json", json_encode($funds, JSON_PRETTY_PRINT));

echo "Fetched " . count($funds) . " funds\n";

/*
// --- STEP 2: Run Python ---
echo "\nSTEP 2: Running Python...\n";

// Windows example (adjust if needed)
$pythonPath = "python"; // or full path like C:\\Python39\\python.exe
$cmd 		= $pythonPath . " \"" . __DIR__ . "/python/process_funds.py\" 2>&1";
$output 	= shell_exec($cmd);

echo $output;

*/

// --- STEP 3: Insert into DB ---
echo "\nSTEP 3: Inserting into DB...\n";

//$json = file_get_contents(__DIR__ . "/data/fund_output.json");

$jsonFile = __DIR__ . "/data/fund_output.json";

if (!file_exists($jsonFile)) {
    die("ERROR: Python output file not found");
}

// Wait until file is stable
$lastSize 		= 0;
$stableCount 	= 0;

while ($stableCount < 3) {
    clearstatcache();
    $currentSize = filesize($jsonFile);

    if ($currentSize === $lastSize && $currentSize > 0) {
        $stableCount++;
    } else {
        $stableCount = 0;
    }

    $lastSize = $currentSize;
    usleep(300000); // 0.3 sec
}

// Now read safely
$json = file_get_contents($jsonFile);

/*
// SHOW FIRST 200 CHARACTERS
echo "START:\n";
echo substr($json, 0, 200);
echo "\n---\n";

// SHOW LAST 200 CHARACTERS
echo "END:\n";
echo substr($json, -200);
echo "\n---\n";

exit;
*/
// Remove BOM if any

$json = preg_replace('/^\xEF\xBB\xBF/', '', $json);

$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("ERROR: JSON decode failed - " . json_last_error_msg());
}

if (!is_array($data)) {
    die("Invalid data format from Python");
}


$inserted = 0;
$errors = 0;

foreach ($data as $f) {
    try {
        $db->upsertFundYahooData($f);
        $inserted++;
    } catch (Exception $e) {
        $errors++;
        echo "ERROR inserting ISIN: " . ($f['isin'] ?? 'UNKNOWN') . "
";
        echo $e->getMessage() . "

";
        continue;
    }
}


echo "Inserted/Updated: $inserted records\n";
echo "Errors: $errors\n";

echo "\nDONE ✅";

echo "</pre>";