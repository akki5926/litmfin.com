<?php
require_once('auth_guard.php');

// No need to start session again as it's already started in database.php
require_once 'database.php';

// Check if user is logged in and is admin (admin_role)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['userid'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
    exit;
}

// Check if user is super admin
$isSuperAdmin = isset($_SESSION['superuser']) && $_SESSION['superuser'] == 1;
if (!$isSuperAdmin) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Admin only.']);
    exit;
}

$db = new MySQLDB();
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

header('Content-Type: application/json');

switch ($action) {
    case 'get_all':
        $credentials = $db->getAllNSECredentials();
        echo json_encode(['success' => true, 'data' => $credentials]);
        break;
        
    case 'get_by_id':
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            break;
        }
        $credential = $db->getNSECredentialById($id);
        if ($credential) {
            echo json_encode(['success' => true, 'data' => $credential]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Credential not found']);
        }
        break;
        
    case 'add':
        // For add, secret_key is required
        if (empty($_POST['secret_key'])) {
            echo json_encode(['success' => false, 'message' => 'Secret Key is required']);
            break;
        }
        
        $data = [
            'name' => $_POST['name'] ?? '',
            'tm_code' => $_POST['tm_code'] ?? '',
            'secret_key' => $_POST['secret_key'] ?? '',
            'member_key' => $_POST['member_key'] ?? '',
            'login_user_id' => $_POST['login_user_id'] ?? '',
            'arn_number' => $_POST['arn_number'] ?? '',
            'euin_number' => $_POST['euin_number'] ?? '',
            'environment' => $_POST['environment'] ?? 'Live',
            'api_url' => $_POST['api_url'] ?? '',
            'status' => $_POST['status'] ?? 'inactive',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        // Validate required fields
        if (empty($data['name']) || empty($data['tm_code'])) {
            echo json_encode(['success' => false, 'message' => 'Name and TM Code are required']);
            break;
        }
        
        $result = $db->addNSECredential($data);
        echo json_encode($result);
        break;
        
    case 'update':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            break;
        }
        
        $data = [
            'name' => $_POST['name'] ?? '',
            'tm_code' => $_POST['tm_code'] ?? '',
            'secret_key' => $_POST['secret_key'] ?? null,
            'member_key' => $_POST['member_key'] ?? '',
            'login_user_id' => $_POST['login_user_id'] ?? '',
            'arn_number' => $_POST['arn_number'] ?? '',
            'euin_number' => $_POST['euin_number'] ?? '',
            'environment' => $_POST['environment'] ?? 'Live',
            'api_url' => $_POST['api_url'] ?? '',
            'status' => $_POST['status'] ?? 'inactive',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        // Validate required fields
        if (empty($data['name']) || empty($data['tm_code'])) {
            echo json_encode(['success' => false, 'message' => 'Name and TM Code are required']);
            break;
        }
        
        $result = $db->updateNSECredential($id, $data);
        echo json_encode($result);
        break;
        
    case 'activate':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            break;
        }
        
        // First get the credential to check if it exists
        $credential = $db->getNSECredentialById($id);
        if (!$credential) {
            echo json_encode(['success' => false, 'message' => 'Credential not found']);
            break;
        }
        
        // Activate this credential and deactivate all others
        $result = $db->activateNSECredential($id);
        echo json_encode($result);
        break;
        
    case 'delete':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            break;
        }
        $result = $db->deleteNSECredential($id);
        echo json_encode($result);
        break;
        
    case 'get_active':
        $active = $db->getActiveNSECredentials();
        if ($active) {
            echo json_encode(['success' => true, 'data' => $active]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No active credentials found']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
