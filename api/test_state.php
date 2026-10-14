<?php
require_once __DIR__ . '/db/mfddb.php';
$db = new MfdDb();
$stmt = $db->getPDO()->query("SELECT id FROM vw_client_profiles WHERE CLIENT_CODE='5013768803' LIMIT 1");
$id = $stmt->fetchColumn();
$p = $db->getUserAllData($id);
echo "STATE: " . $p['reg_details']['state'] . "\n";
echo "COUNTRY: " . $p['reg_details']['country'] . "\n";
print_r($p['reg_details']);
