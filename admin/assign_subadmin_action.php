<?php
require_once('auth_guard.php');

// Cache busting headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private");
header("Pragma: no-cache");
header("Expires: 0");

require_once('database.php');
$db = new MYSQLDB();

// Handle getting sub-admins list
if (isset($_GET['action']) && $_GET['action'] === 'get_subadmins') {
    header('Content-Type: application/json');
    try {
        $subadmins = $db->getSubAdmins();
        $result = [];
        foreach ($subadmins as $sa) {
            $result[] = [
                'id' => $sa['ad_id'],
                'name' => $sa['ad_user_name']
            ];
        }
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle Auto Email update
if (isset($_POST['action']) && $_POST['action'] === 'update_auto_email') {
    header('Content-Type: application/json');
    try {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $autoEmail = isset($_POST['auto_email']) ? intval($_POST['auto_email']) : 0;
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            exit;
        }
        
        // Ensure it's 0 or 1
        $autoEmail = ($autoEmail == 1) ? 1 : 0;
        
        $result = $db->updateAutoEmailPreference($userId, $autoEmail);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Auto email preference updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update auto email preference']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle assignment
if (isset($_POST['user_id']) && isset($_POST['subadmin_id'])) {
    $userId = $_POST['user_id'];
    $subadminId = $_POST['subadmin_id'];

    $result = $db->assignSubAdminToUser($userId, $subadminId);

    if ($result) {
        echo "Sub-admin assigned successfully.";
    } else {
        echo "Failed to assign sub-admin.";
    }
} else {
    echo "Invalid input.";
}
?>