<?php
require_once __DIR__ . '/../api/db/mfddb.php';
$db = new MfdDb();
$pdo = $db->getPdo();

echo "Repairing fatca_details records with invalid addr_type='0'...\n";
$stmt = $pdo->query("UPDATE fatca_details SET addr_type = '2' WHERE addr_type = '0' OR addr_type = '' OR addr_type IS NULL");
$affected = $stmt->rowCount();
echo "Repaired {$affected} records where addr_type was '0' or empty.\n";
