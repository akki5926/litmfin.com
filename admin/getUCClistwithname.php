<?php
require_once('auth_guard.php');

require_once('database.php');
$db = new MYSQLDB();

$isSubAdmin = isset($_SESSION["superuser"]) && $_SESSION["superuser"] == 0;
$subAdminId = $_SESSION["userid"] ?? 0;

// Query the database to retrieve customer UCCs
if ($isSubAdmin) {
    $result = $db->getAllUCC4subAdmin($subAdminId);
} else {
    $result = $db->getAllUCC();
}

// Prepare an array to store the customer data
$customerData = [];

if ($result != false) {
    // Create an associative array to store unique UCCs as keys
    $uniqueUCCs = [];

    // Fetch each row and add the customer data to the array
    foreach ($result as $row) {
        $UCC = $row["CLIENT_CODE"];
        $name = $row["PRIMARY_HOLDER_NAME"];

        // Check if the UCC is unique before adding it
        if (!isset($uniqueUCCs[$UCC])) {
            $customerData[] = [
                "UCC" => $UCC,
                "name" => $name
            ];
            $uniqueUCCs[$UCC] = true;
        }
    }
}

// ============================================================
// NEW: Get additional UCCs from tb_users table for master data
// This ensures all clients (including those not yet activated)
// are available for autocomplete in the master data section
// ============================================================
if ($isSubAdmin) {
    $masterResult = $db->getAllUCCFromUsers4subAdmin($subAdminId);
} else {
    $masterResult = $db->getAllUCCFromUsers();
}

if ($masterResult != false && is_array($masterResult)) {
    foreach ($masterResult as $row) {
        $UCC = $row["CLIENT_CODE"] ?? '';
        $name = $row["PRIMARY_HOLDER_NAME"] ?? '';

        // Skip if empty UCC
        if (empty($UCC)) {
            continue;
        }

        // Check if the UCC is unique before adding it
        if (!isset($uniqueUCCs[$UCC])) {
            $customerData[] = [
                "UCC" => $UCC,
                "name" => $name
            ];
            $uniqueUCCs[$UCC] = true;
        }
    }
}

// Sort the customer data by UCC for better user experience
usort($customerData, function($a, $b) {
    return strcmp($a['UCC'], $b['UCC']);
});

// Send the customer data as a JSON response
header("Content-Type: application/json");
echo json_encode($customerData);
?>
