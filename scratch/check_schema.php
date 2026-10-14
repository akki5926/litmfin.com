<?php
require_once __DIR__ . '/../api/db/mfddb.php';
$db = new MfdDb();
$pdo = $db->getPdo();

$stmt = $pdo->query("DESCRIBE fatca_details");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Field                | Type            | Null | Key | Default\n";
echo "--------------------------------------------------------------\n";
foreach ($columns as $col) {
    echo sprintf("%-20s | %-15s | %-4s | %-3s | %s\n", 
        $col['Field'], 
        $col['Type'], 
        $col['Null'], 
        $col['Key'], 
        $col['Default'] ?? 'NULL'
    );
}
