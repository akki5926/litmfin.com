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
$db = new MySQLDB();
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'list':
        listBlogs($db);
        break;
    case 'get':
        getBlog($db);
        break;
    case 'create':
        createBlog($db);
        break;
    case 'update':
        updateBlog($db);
        break;
    case 'delete':
        deleteBlog($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listBlogs($db) {
    $blogs = $db->getAllBlogs();
    echo json_encode(['success' => true, 'data' => $blogs]);
}

function getBlog($db) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid blog ID']);
        return;
    }
    
    $blog = $db->getBlogById($id);
    
    if ($blog) {
        echo json_encode(['success' => true, 'data' => $blog]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Blog not found']);
    }
}

function createBlog($db) {
    $title = trim($_POST['title'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $read_time = trim($_POST['read_time'] ?? '');
    
    if (empty($title) || empty($topic) || empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Title, Topic and Description are required']);
        return;
    }
    
    // Handle image upload
    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image_name = uploadImage($_FILES['image']);
        if ($image_name === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            return;
        }
    }
    
    $data = [
        'title' => $title,
        'topic' => $topic,
        'description' => $description,
        'url' => $url,
        'date' => $date,
        'image' => $image_name,
        'read_time' => $read_time
    ];
    
    $id = $db->createBlog($data);
    
    if ($id) {
        echo json_encode(['success' => true, 'message' => 'Blog created successfully', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create blog']);
    }
}

function updateBlog($db) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = trim($_POST['title'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $read_time = trim($_POST['read_time'] ?? '');
    
    if ($id <= 0 || empty($title) || empty($topic) || empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    
    $data = [
        'title' => $title,
        'topic' => $topic,
        'description' => $description,
        'url' => $url,
        'date' => $date,
        'read_time' => $read_time
    ];
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        // Delete old image
        $old_image = $db->getBlogImage($id);
        if ($old_image) {
            $old_path = dirname(__DIR__) . '/uploads/blog/' . $old_image;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }
        
        $image_name = uploadImage($_FILES['image']);
        if ($image_name === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            return;
        }
        $data['image'] = $image_name;
    }
    
    $result = $db->updateBlog($id, $data);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Blog updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update blog']);
    }
}

function deleteBlog($db) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid blog ID']);
        return;
    }
    
    $result = $db->deleteBlog($id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Blog deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete blog']);
    }
}

function uploadImage($file) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return false;
    }
    
    // Upload directory - goes up one level from admin folder, then into uploads/blog/
    $upload_dir = dirname(__DIR__) . '/uploads/blog/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $new_filename;
    }
    
    return false;
}
?>