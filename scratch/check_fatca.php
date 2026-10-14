<?php
require_once __DIR__ . '/../api/db/mfddb.php';
$db = new MfdDb();
$pdo = $db->getPdo();

echo "=== mtb_fatca_tax_status (first 5) ===\n";
print_r(array_slice($db->getFatcaTaxStatus(), 0, 5));

echo "\n=== mtb_fatca_add_type ===\n";
print_r($db->getFatcaAddType());

echo "\n=== mtb_fatca_identity_doc_type ===\n";
print_r($db->getFatcaIdentityDocType());

echo "\n=== mtb_fatca_wealth_sc ===\n";
print_r($db->getFatcaWealthSc());

echo "\n=== mtb_fatca_occupation ===\n";
print_r($db->getFatcaOccupation());

echo "\n=== mtb_fatca_income_range ===\n";
print_r($db->getFatcaIncomeRange());
