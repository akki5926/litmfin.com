<?php
require_once __DIR__ . '/../api/db/mfddb.php';
$db = new MfdDb();
echo "=== UBO CODE MASTER DATA ===\n";
print_r($db->getFatcaUboCode());
