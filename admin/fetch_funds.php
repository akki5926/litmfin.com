<?php
require_once('auth_guard.php');

require_once __DIR__ . '/database.php';

$db  = new MYSQLDB();
$pdo = $db->getPDO();

$sql = "
SELECT DISTINCT 
    TRIM(isin) AS isin,
    MAX(scheme_name) AS scheme_name
FROM tb_transaction_mailback
WHERE isin IS NOT NULL AND isin != ''
GROUP BY isin
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

file_put_contents(__DIR__ . "/data/fund_list.json", json_encode($data, JSON_PRETTY_PRINT));

echo "Fund list exported\n";

?>