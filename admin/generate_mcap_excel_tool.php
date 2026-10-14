<?php
require_once('auth_guard.php');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

$fillSample 	= true; // or false

$db  = new MYSQLDB();
$pdo = $db->getPDO();

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'missing_months') {
    header('Content-Type: application/json');
    $maxSql = "SELECT MAX(month_year) AS max_month FROM tb_funds_mcap_data WHERE month_year IS NOT NULL";
    $maxMonth = $pdo->query($maxSql)->fetchColumn();
    $lastFilled = $maxMonth ? date('Y-m-01', strtotime($maxMonth)) : null;
    $lastCompleted = date('Y-m-01', strtotime('first day of previous month'));

    $missingMonths = [];
    if ($lastFilled) {
        for ($ts = strtotime('+1 month', strtotime($lastFilled)); $ts <= strtotime($lastCompleted); $ts = strtotime('+1 month', $ts)) {
            $missingMonths[] = [
                'value' => date('Y-m-01', $ts),
                'label' => date('F Y', $ts)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'missing_months' => $missingMonths,
        'last_filled' => $lastFilled,
        'last_completed' => $lastCompleted
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'download_file' && isset($_GET['file'])) {
    $fileName = basename($_GET['file']);
    $tempDir = __DIR__ . '/tmp_mcap_excel';
    $filePath = $tempDir . '/' . $fileName;

    if (!file_exists($filePath)) {
        http_response_code(404);
        echo "File not found.";
        exit;
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    // remove the delivered file
    @unlink($filePath);

    // if the temp dir is empty after removing this file, delete the directory
    try {
        $files = glob($tempDir . '/*');
        if ($files === false || count($files) === 0) {
            @rmdir($tempDir);
        }
    } catch (\Throwable $e) {
        // ignore cleanup errors
    }
    exit;
}

$isBlank = isset($_REQUEST['blank']) && $_REQUEST['blank'] == '1';
$selectedMonths = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generate_tool') {
    if (!empty($_POST['selected_months'])) {
        $selectedMonths = array_map('trim', (array)$_POST['selected_months']);
    }
}

try {
    // 1. Fetch Unique ISINs from transactions
    $txnSql = "SELECT DISTINCT isin FROM tb_transaction_mailback WHERE isin IS NOT NULL AND isin != ''";
    $txnData = $pdo->query($txnSql)->fetchAll(PDO::FETCH_ASSOC);

    if (empty($txnData)) die("No data found.");

    // 2. Fetch Scheme Master Data to map names
    $smSql = "SELECT isin, scheme_name, amc_code, scheme_code FROM mtb_scheme_master WHERE isin IS NOT NULL AND isin != ''";
    $smRaw = $pdo->query($smSql)->fetchAll(PDO::FETCH_ASSOC);
    $smMap = [];
    foreach ($smRaw as $row) {
        $smMap[trim($row['isin'])] = $row;
    }

    // 3. Format Data for Python
    $data = [];
    foreach ($txnData as $row) {
        $isin = trim($row['isin']);

        $sm_scheme = isset($smMap[$isin]) ? trim($smMap[$isin]['scheme_name']) : '';
        $sm_amc = isset($smMap[$isin]) ? trim($smMap[$isin]['amc_code']) : '';
        $sm_code = isset($smMap[$isin]) ? trim($smMap[$isin]['scheme_code']) : '';

        $scheme_name = $sm_scheme ?: 'Unknown Fund';
        $amc_code = $sm_amc;
        $amc_name = $sm_amc ?: 'Uncategorized';

        $data[] = [
            'isin' => $isin,
            'scheme_code' => $sm_code,
            'amc_code' => $amc_code,
            'scheme_name' => $scheme_name,
            'amc_name' => $amc_name
        ];
    }

    // 4. Sort Data (Uncategorized at the bottom, then alphabetical)
    usort($data, function($a, $b) {
        if ($a['amc_name'] === 'Uncategorized' && $b['amc_name'] !== 'Uncategorized') return 1;
        if ($a['amc_name'] !== 'Uncategorized' && $b['amc_name'] === 'Uncategorized') return -1;
        
        $amcCmp = strcmp($a['amc_name'], $b['amc_name']);
        if ($amcCmp !== 0) return $amcCmp;
        
        return strcmp($a['scheme_name'], $b['scheme_name']);
    });

    $mcapData = [];
    $existingMonths = [];

    // If it's not a blank tool request, fetch existing MCAP data
    if (!$isBlank) {
        $mcapSql = "SELECT isin, month_year, large_cap, mid_cap, small_cap, cash, debt FROM tb_funds_mcap_data";
        $mcapStmt = $pdo->query($mcapSql);
        $mcapDataRaw = $mcapStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($mcapDataRaw as $row) {
            $isin = trim($row['isin']);
            $month = date('Y-m-01', strtotime($row['month_year']));
            $existingMonths[$month] = true;
            $mcapData[$isin][$month] = [
                'Large Cap' => (float)$row['large_cap'],
                'Mid Cap' => (float)$row['mid_cap'],
                'Small Cap' => (float)$row['small_cap'],
                'Cash' => (float)$row['cash'],
                'Debt' => (float)$row['debt']
            ];
        }
    }

    foreach ($data as &$fund) {
        $isin = $fund['isin'];
        // Cast to object so json_encode outputs {} instead of []
        $fund['mcap_data'] = isset($mcapData[$isin]) ? (object)$mcapData[$isin] : new stdClass();
    }

    $outputMonths = [];
    if ($isBlank) {
        if (!empty($selectedMonths)) {
            $outputMonths = $selectedMonths;
        } elseif (!empty($existingMonths)) {
            $outputMonths = array_keys($existingMonths);
        }
    } else {
        if (!empty($selectedMonths)) {
            $outputMonths = array_unique(array_merge(array_keys($existingMonths), $selectedMonths));
        } else {
            $outputMonths = array_keys($existingMonths);
        }
    }
    sort($outputMonths);

    if (empty($outputMonths)) {
        header('Content-Type: application/json');
        @unlink('temp_fund_data.json');
        echo json_encode([
            'success' => false,
            'message' => 'No month columns selected or available for generation.'
        ]);
        exit;
    }

    // If user selected months, pass them as first argument to Python (comma separated YYYY-mm-01)
    $monthsArg = implode(',', $outputMonths);

    // Determine missing months between last filled month and last completed month
    $missingMonths = [];
    if (!$isBlank) {
        try {
            $maxSql = "SELECT MAX(month_year) AS max_month FROM tb_funds_mcap_data WHERE month_year IS NOT NULL";
            $maxMonth = $pdo->query($maxSql)->fetchColumn();
            if ($maxMonth) {
                $lastFilled = date('Y-m-01', strtotime($maxMonth));
            } else {
                // no data at all, treat as no last filled
                $lastFilled = null;
            }
        } catch (\Exception $e) {
            $lastFilled = null;
        }

        // Last completed month should exclude the current month (current month not over)
        $lastCompleted = date('Y-m-01', strtotime('first day of previous month'));

        if ($lastFilled) {
            $start = strtotime('+1 month', strtotime($lastFilled));
            $end = strtotime($lastCompleted);

            for ($ts = $start; $ts <= $end; $ts = strtotime('+1 month', $ts)) {
                $missingMonths[] = date('Y-m-01', $ts);
            }
        }
    }

    // Write temp data JSON
    file_put_contents('temp_fund_data.json', json_encode($data));

    // Ensure we call the script using the absolute path
    $scriptPath = __DIR__ . '/build_mcap_excel_tool.py';
    $arg = "0"; // Force sample filling off since we use real data

    // If user selected months, pass them as first argument to Python (comma separated YYYY-mm-01)
    $monthsArg = '';
    if (!empty($selectedMonths)) {
        $monthsArg = implode(',', array_map('trim', $selectedMonths));
    }
    
    // Find correct python executable (Linux servers usually use python3)
    $pythonCmd = 'python';
    foreach (['python3', 'python', 'py'] as $cmd) {
        exec($cmd . ' --version 2>&1', $out, $returnCode);
        if ($returnCode === 0) {
            $pythonCmd = $cmd;
            break;
        }
    }

    // Generate dynamic file name with today's date
    $dateSuffix = date('M_d_Y'); // e.g. May_12_2026
    $filePrefix = $isBlank ? "Market_Cap_Allocation_Tool_Blank" : "Market_Cap_Allocation_Tool";
    $outputFileName = "{$filePrefix}_{$dateSuffix}_" . uniqid() . ".xlsx";
    $tempDir = __DIR__ . '/tmp_mcap_excel';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    $outputFilePath = $tempDir . '/' . $outputFileName;

    // Build command: pass selected months (if any) as first arg, blank flag as second, file path as third
    $blankArg = $isBlank ? '1' : '0';
    $safeMonths = escapeshellarg($monthsArg);
    $safeBlank = escapeshellarg($blankArg);
    $safeFile = escapeshellarg($outputFilePath);
    $command = "$pythonCmd " . escapeshellarg($scriptPath) . " $safeMonths $safeBlank $safeFile 2>&1";
    $output = shell_exec($command);

    if (file_exists($outputFilePath)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'file' => $outputFileName,
            'message' => 'Excel generated successfully.'
        ]);
        // cleanup temp JSON
        @unlink('temp_fund_data.json');
        exit;
    }

    header('Content-Type: application/json');
    // cleanup temp JSON even on failure
    @unlink('temp_fund_data.json');
    echo json_encode([
        'success' => false,
        'message' => 'Python script failed: ' . trim($output)
    ]);
    exit;

} catch (\PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}