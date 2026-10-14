<?php
require_once('auth_guard.php');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

$fillSample 	= true; // or false

$db  = new MYSQLDB();
$pdo = $db->getPDO();

$txnSql = "
    SELECT 
        m.scheme_code, 
        m.amc_code,
        mast.isin,
        mast.scheme_name,
        -- Fetching the full AMC name from mtb_amc
        COALESCE(amc.LONG_NAME, m.amc_code) as amc_name,
        MAX(m.rta) AS rta
    FROM tb_transaction_mailback m
    LEFT JOIN mtb_scheme_master mast 
        ON TRIM(m.scheme_code) = TRIM(mast.rta_scheme_code)
    LEFT JOIN mtb_amc amc 
        ON TRIM(m.amc_code) = TRIM(amc.AMC_CODE)
    GROUP BY m.scheme_code, m.amc_code  
    ORDER BY amc_name ASC, mast.scheme_name ASC";


/*
// AMC name needs to be fetched from amc master in below query
$txnSql = "
    SELECT 
    scheme_code,
    amc_code,
    MAX(isin) AS isin,
    MAX(scheme_name) AS scheme_name
FROM tb_transaction_mailback  
GROUP BY scheme_code, amc_code  
ORDER BY `scheme_name` ASC";
*/

/*
// v2
$txnSql = "
    SELECT 
        m.scheme_code, 
        m.amc_code,
        MAX(m.isin) AS isin,
        MAX(m.scheme_name) AS scheme_name,
        -- Fetch the full AMC Name from the master table
        amc.LONG_NAME AS amc_name
    FROM tb_transaction_mailback m
    -- Join with the AMC master table
    LEFT JOIN mtb_amc amc 
        ON TRIM(m.amc_code) = TRIM(amc.AMC_CODE)
    GROUP BY m.scheme_code, m.amc_code  
    ORDER BY amc.LONG_NAME ASC, scheme_name ASC";
*/


// v3
$txnSql = "
    SELECT 
        m.scheme_code, 
        m.amc_code,
        MAX(m.isin) AS isin,
        MAX(m.scheme_name) AS scheme_name,
        COALESCE(NULLIF(TRIM(amc.LONG_NAME), ''), '') AS amc_name
    FROM tb_transaction_mailback m
    LEFT JOIN mtb_amc amc 
        ON TRIM(m.amc_code) = TRIM(amc.AMC_CODE)
    GROUP BY m.scheme_code, m.amc_code  
    ORDER BY 
        -- Force empty AMC names to the end
        CASE WHEN COALESCE(amc.LONG_NAME, '') = '' THEN 1 ELSE 0 END ASC,
        amc_name ASC, 
        scheme_name ASC";
		
try {
    $stmt = $pdo->query($txnSql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($data)) die("No data found.");

    file_put_contents('temp_fund_data.json', json_encode($data));

    // Ensure we call the script using the absolute path
    //$scriptPath = __DIR__ . '/build_mcap_excel_tool.py'.($fillSample ? "1" : "0");
    // $output = shell_exec("python \"$scriptPath\" 2>&1");
    
	$scriptPath = __DIR__ . '/build_mcap_excel_tool.py';
	$arg = $fillSample ? "1" : "0";

	$command = "python \"$scriptPath\" $arg 2>&1";
	$output = shell_exec($command);

    echo "<pre>$output</pre>";
    echo "<strong>Success!</strong> File generated in: " . __DIR__;

} catch (\PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}