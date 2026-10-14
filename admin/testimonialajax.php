<?php
require_once('auth_guard.php');

if (!isset($_SESSION)) { 
    session_start(); 
}

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

require_once 'database.php';
// Prevent HTML PHP warnings from corrupting the JSON response
ini_set('display_errors', 0);

$db = new MySQLDB();
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        listTestimonials($db);
        break;
    case 'get':
        getTestimonial($db);
        break;
    case 'create':
        createTestimonial($db);
        break;
    case 'update':
        updateTestimonial($db);
        break;
    case 'delete':
        deleteTestimonial($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listTestimonials($db) {
    $testis = $db->getAllTestimonials();
    echo json_encode(['success' => true, 'data' => $testis ? $testis : []]);
}

function getTestimonial($db) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid testimonial ID']);
        return;
    }
    
    $testi = $db->getTestimonialById($id);
    
    if ($testi) {
        echo json_encode(['success' => true, 'data' => $testi]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Testimonial not found']);
    }
}

function createTestimonial($db) {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $text = trim($_POST['text'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $status = intval($_POST['status'] ?? 1);
    
    if (empty($name) || empty($text)) {
        echo json_encode(['success' => false, 'message' => 'Name and Text are required']);
        return;
    }
    
    // Handle image upload
    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_result = uploadImage($_FILES['image']);
        if (is_array($upload_result) && !$upload_result['success']) {
            echo json_encode(['success' => false, 'message' => $upload_result['message']]);
            return;
        }
        $image_name = $upload_result['filename'];
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $_FILES['image']['error']]);
        return;
    }
    
    $data = [
        'name' => $name,
        'role' => $role,
        'text' => $text,
        'image' => $image_name,
        'sort_order' => $sort_order,
        'status' => $status
    ];
    
    $id = $db->createTestimonial($data);
    
    if ($id) {
        echo json_encode(['success' => true, 'message' => 'Testimonial created successfully', 'id' => $id]);
    } else {
        $error = $db->getError();
        $msg = 'Failed to create testimonial.';
        if (strpos($error, "Unknown column 'image'") !== false) {
            $msg .= ' Please run the SQL command to add the "image" column to your database.';
        }
        echo json_encode(['success' => false, 'message' => $msg, 'db_error' => $error]);
    }
}

function updateTestimonial($db) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $text = trim($_POST['text'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $status = intval($_POST['status'] ?? 1);
    
    if ($id <= 0 || empty($name) || empty($text)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    
    $data = [
        'name' => $name,
        'role' => $role,
        'text' => $text,
        'sort_order' => $sort_order,
        'status' => $status
    ];
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_result = uploadImage($_FILES['image']);
        if (is_array($upload_result) && !$upload_result['success']) {
            echo json_encode(['success' => false, 'message' => $upload_result['message']]);
            return;
        }
        $data['image'] = $upload_result['filename'];
        
        // Delete old image
        $old_image = $db->getTestimonialImage($id);
        if ($old_image) {
            $old_path = dirname(__DIR__) . '/uploads/testimonials/' . $old_image;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $_FILES['image']['error']]);
        return;
    }
    
    $result = $db->updateTestimonial($id, $data);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Testimonial updated successfully']);
    } else {
        $error = $db->getError();
        $msg = 'Failed to update testimonial.';
        if (strpos($error, "Unknown column 'image'") !== false) {
            $msg .= ' Please run the SQL command to add the "image" column to your database.';
        }
        echo json_encode(['success' => false, 'message' => $msg, 'db_error' => $error]);
    }
}

function deleteTestimonial($db) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid testimonial ID']);
        return;
    }
    
    $result = $db->deleteTestimonial($id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Testimonial deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete testimonial']);
    }
}

function uploadImage($file) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP'];
    }
    
    // Upload directory
    $upload_dir = dirname(__DIR__) . '/uploads/testimonials/';
    
    if (!file_exists($upload_dir)) {
        // Use @ to suppress PHP warnings if permission is denied
        if (!@mkdir($upload_dir, 0777, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory. Check folder permissions.'];
        }
    }
    
    $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $upload_path = $upload_dir . $new_filename;
    
    // Use @ to suppress PHP warnings if permission is denied
    if (@move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $new_filename];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file. Check folder permissions.'];
}
?>