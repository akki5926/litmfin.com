<?php
require_once __DIR__ . '/../api/db/mfddb.php';
$db = new MfdDb();
$pdo = $db->getPdo();

$stmt = $pdo->query("SELECT * FROM fatca_details ORDER BY updated_at DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total FATCA rows in database: " . count($rows) . "\n\n";

foreach ($rows as $idx => $row) {
    echo "--- Row #{$idx} (user_id: {$row['user_id']}, PAN: {$row['pan_rp']}, Name: {$row['inv_name']}) ---\n";
    foreach ($row as $field => $val) {
        if ($val === null || $val === '' || $val === '0' || $val === '0000-00-00') {
            echo "  [EMPTY/ZERO] {$field} => " . var_export($val, true) . "\n";
        } else {
            echo "  [FILLED]     {$field} => {$val}\n";
        }
    }
    echo "\n";
}
