<?php
require_once('auth_guard.php');

// Pure AJAX handler - no HTML output
header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");

// Disable error reporting
error_reporting(0);
ini_set('display_errors', 0);

require_once('database.php');
$db = new MYSQLDB();

$response = ['success' => false, 'error' => 'Unknown error'];

if (isset($_POST['action']) && $_POST['action'] === 'update_auto_email') {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $autoEmail = isset($_POST['auto_email']) ? intval($_POST['auto_email']) : 0;
    
    if ($userId <= 0) {
        $response = ['success' => false, 'error' => 'Invalid user ID'];
    } else {
        $autoEmail = ($autoEmail == 1) ? 1 : 0;        $result = $db->updateUserAutoEmail($userId, $autoEmail);
        
        if ($result) {
            $response = ['success' => true, 'message' => 'Updated successfully', 'auto_email' => $autoEmail];
        } else {
            $response = ['success' => false, 'error' => 'Database error: ' . $conn->error];
        }
    }
}

echo json_encode($response);
exit;
?>