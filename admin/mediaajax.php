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
        listMedia($db);
        break;
    case 'get':
        getMedia($db);
        break;
    case 'create':
        createMedia($db);
        break;
    case 'update':
        updateMedia($db);
        break;
    case 'delete':
        deleteMedia($db);
        break;
    case 'bulk_delete':
        bulkDeleteMedia($db);
        break;
    case 'get_categories':
        getCategories($db);
        break;
    case 'search':
        searchMedia($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * List all media items
 */
function listMedia($db) {
    $media = $db->getAllMedia();
    echo json_encode(['success' => true, 'data' => $media, 'total' => count($media)]);
}

/**
 * Get single media by ID
 */
function getMedia($db) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid media ID']);
        return;
    }
    
    $media = $db->getMediaById($id);
    
    if ($media) {
        echo json_encode(['success' => true, 'data' => $media]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Media not found']);
    }
}

/**
 * Create new media entry
 */
function createMedia($db) {
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        return;
    }
    
    // Handle icon/image upload
    $icon_name = '';
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
        $icon_name = uploadIcon($_FILES['icon']);
        if ($icon_name === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload icon. Allowed formats: jpg, jpeg, png, gif, webp, svg, ico']);
            return;
        }
    }
    
    $data = [
        'title' => $title,
        'url' => $url,
        'description' => $description,
        'source' => $source,
        'date' => $date,
        'category' => $category,
        'icon' => $icon_name
    ];
    
    $id = $db->createMedia($data);
    
    if ($id) {
        echo json_encode(['success' => true, 'message' => 'Media created successfully', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create media']);
    }
}

/**
 * Update existing media entry
 */
function updateMedia($db) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if ($id <= 0 || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    
    $data = [
        'title' => $title,
        'url' => $url,
        'description' => $description,
        'source' => $source,
        'date' => $date,
        'category' => $category
    ];
    
    // Handle icon/image upload
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
        // Delete old icon
        $old_icon = $db->getMediaIcon($id);
        if ($old_icon) {
            $old_path = dirname(__DIR__) . '/uploads/media/' . $old_icon;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }
        
        $icon_name = uploadIcon($_FILES['icon']);
        if ($icon_name === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload icon. Allowed formats: jpg, jpeg, png, gif, webp, svg, ico']);
            return;
        }
        $data['icon'] = $icon_name;
    }
    
    $result = $db->updateMedia($id, $data);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Media updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update media']);
    }
}

/**
 * Delete media entry
 */
function deleteMedia($db) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid media ID']);
        return;
    }
    
    $result = $db->deleteMedia($id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Media deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete media']);
    }
}

/**
 * Bulk delete multiple media entries
 */
function bulkDeleteMedia($db) {
    $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
    
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No media IDs provided']);
        return;
    }
    
    // Convert to integers
    $ids = array_map('intval', $ids);
    
    $result = $db->bulkDeleteMedia($ids);
    
    if ($result['success'] > 0) {
        echo json_encode([
            'success' => true, 
            'message' => $result['success'] . ' media deleted successfully',
            'deleted' => $result['success'],
            'failed' => $result['failed']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete media']);
    }
}

/**
 * Get all unique categories
 */
function getCategories($db) {
    $categories = $db->getMediaCategories();
    echo json_encode(['success' => true, 'data' => $categories]);
}

/**
 * Search media by keyword
 */
function searchMedia($db) {
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    
    if (empty($keyword)) {
        listMedia($db);
        return;
    }
    
    $results = $db->searchMedia($keyword);
    echo json_encode(['success' => true, 'data' => $results, 'total' => count($results), 'keyword' => $keyword]);
}

/**
 * Upload icon/image file
 * @param array $file The uploaded file from $_FILES
 * @return string|false Filename on success, false on failure
 */
function uploadIcon($file) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return false;
    }
    
    // Upload directory - goes up one level from admin folder, then into uploads/media/
    $upload_dir = dirname(__DIR__) . '/uploads/media/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $upload_path = $upload_dir . $new_filename;
    
    // For SVG files, we need to handle differently
    if ($ext == 'svg') {
        // Read SVG content and save
        $svg_content = file_get_contents($file['tmp_name']);
        if ($svg_content !== false) {
            file_put_contents($upload_path, $svg_content);
            return $new_filename;
        }
        return false;
    }
    
    // For regular images
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Optimize image if it's not SVG
        if ($ext != 'svg' && function_exists('imagecreatefromjpeg')) {
            optimizeImage($upload_path, $ext);
        }
        return $new_filename;
    }
    
    return false;
}

/**
 * Optimize uploaded image (reduce file size)
 * @param string $path Image file path
 * @param string $ext File extension
 */
function optimizeImage($path, $ext) {
    // Only optimize if GD is available
    if (!function_exists('imagecreatefromjpeg')) {
        return;
    }
    
    try {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($path);
                if ($image) {
                    imagejpeg($image, $path, 85);
                    imagedestroy($image);
                }
                break;
            case 'png':
                $image = imagecreatefrompng($path);
                if ($image) {
                    imagepng($image, $path, 8);
                    imagedestroy($image);
                }
                break;
            case 'gif':
                $image = imagecreatefromgif($path);
                if ($image) {
                    imagegif($image, $path);
                    imagedestroy($image);
                }
                break;
            case 'webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = imagecreatefromwebp($path);
                    if ($image) {
                        imagewebp($image, $path, 85);
                        imagedestroy($image);
                    }
                }
                break;
        }
    } catch (Exception $e) {
        // Silently fail - keep original file
        error_log("Image optimization failed: " . $e->getMessage());
    }
}
?>