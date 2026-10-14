<?php
require_once('auth_guard.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (!isset($_SESSION)) { 
    session_start(); 
}

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    header('Location: login.php');
    exit;
}

require_once 'database.php';
$db = new MySQLDB();

// Log the request for debugging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    error_log("Media AJAX Request: " . print_r($_POST, true));
}

// Handle CRUD operations via AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($isAjax || isset($_POST['action']))) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    error_log("Media Action: " . $action);
    
    // Save (Create/Update)
    if ($action == 'save') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $source = trim($_POST['source'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Title is required!']);
            exit;
        }
        
        $icon_name = '';
        
        // Handle icon/image upload
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
            $ext = strtolower(pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_dir = dirname(__DIR__) . '/uploads/media/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $icon_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['icon']['name']);
                
                if (move_uploaded_file($_FILES['icon']['tmp_name'], $upload_dir . $icon_name)) {
                    // Delete old icon if updating
                    if ($id > 0) {
                        $oldIcon = $db->getMediaIcon($id);
                        if ($oldIcon && file_exists($upload_dir . $oldIcon)) {
                            unlink($upload_dir . $oldIcon);
                        }
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload icon!']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid file format! Allowed: jpg, jpeg, png, gif, webp, svg, ico']);
                exit;
            }
        }
        
        if ($id > 0) {
            // Update
            if ($icon_name) {
                $res = $db->updateMediaWithIcon($id, $title, $url, $description, $source, $date, $category, $icon_name);
            } else {
                $res = $db->updateMedia($id, $title, $url, $description, $source, $date, $category);
            }
            
            if ($res['success']) {
                echo json_encode(['success' => true, 'message' => 'Media updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update media: ' . $res['error']]);
            }
        } else {
            // Insert
            $res = $db->insertMedia($title, $url, $description, $source, $date, $category, $icon_name);
            
            if ($res['success']) {
                echo json_encode(['success' => true, 'message' => 'Media added successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add media: ' . $res['error']]);
            }
        }
        exit;
    }
    
    // Delete
    if ($action == 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid media ID']);
            exit;
        }
        
        $upload_dir = dirname(__DIR__) . '/uploads/media/';
        
        $oldIcon = $db->getMediaIcon($id);
        
        if ($oldIcon && file_exists($upload_dir . $oldIcon)) {
            unlink($upload_dir . $oldIcon);
        }
        
        $res = $db->deleteMedia($id);
        
        if ($res['success']) {
            echo json_encode(['success' => true, 'message' => 'Media deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete media: ' . $res['error']]);
        }
        exit;
    }
}

// Get single media for editing
if (isset($_GET['get_media']) && is_numeric($_GET['get_media'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['get_media']);
    $media = $db->getMediaById($id);
    echo json_encode($media ?: null);
    exit;
}

// Check if table exists
$db->createMediaTableIfNotExists();

// Fetch all media for listing
$media_list = $db->getAllMedia();

// Calculate stats
$totalMedia = count($media_list);
$mediaWithIcons = 0;
$categories = [];

foreach ($media_list as $media) {
    if (!empty($media['icon'])) {
        $mediaWithIcons++;
    }
    if (!empty($media['category']) && !in_array($media['category'], $categories)) {
        $categories[] = $media['category'];
    }
}
$uniqueCategories = count($categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Management</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .modal {
            z-index: 100000 !important;
            background: rgba(0,0,0,0.5);
        }
        .modal-backdrop {
            z-index: 99999 !important;
        }
        .modal-dialog {
            margin-top: 50px;
        }
        .modal-content {
            border-radius: 16px;
            border: none;
        }
        
        .category-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16,185,129,0.3);
            color: white;
        }
        
        .table-img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            background: #f3f4f6;
            padding: 5px;
        }
        
        .wrap-text {
            max-width: 250px;
            white-space: normal;
            word-break: break-word;
        }
        
        .image-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .image-upload-box:hover {
            border-color: #10b981;
            background: #f8fafc;
        }
        
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100001;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #10b981 !important;
            border-color: #10b981 !important;
            color: white !important;
        }
        
        .btn-sm {
            padding: 5px 10px;
        }
        
        .icon-preview {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #10b981;
        }
        
        .source-link {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }
        
        .source-link a {
            color: #10b981;
            text-decoration: none;
        }
        
        .source-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container-fluid px-4 py-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-5 fw-bold mb-1"><i class="fas fa-photo-video text-success me-2"></i>Media Management</h1>
            <p class="text-muted">Manage your media files - Images, Videos, Documents</p>
        </div>
        <button class="btn btn-primary-custom" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i>Add New Media
        </button>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="text-muted small">Total Media</div>
                <div class="h2 fw-bold mb-0"><?php echo $totalMedia; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="text-muted small">With Icons/Images</div>
                <div class="h2 fw-bold mb-0"><?php echo $mediaWithIcons; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="text-muted small">Categories</div>
                <div class="h2 fw-bold mb-0"><?php echo $uniqueCategories; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Media Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="mediaTable" class="table table-hover w-100">
                    <thead class="table-light">
                        <tr>
                            <th width="50">ID</th>
                            <th width="70">Icon</th>
                            <th>Title</th>
                            <th width="100">Category</th>
                            <th>Description</th>
                            <th width="120">Source</th>
                            <th width="100">Date</th>
                            <th width="120">URL</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="mediaTableBody">
                        <?php if (empty($media_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fas fa-database fa-3x mb-3 d-block"></i>
                                No media found. Click "Add New Media" to get started.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($media_list as $media): ?>
                        <tr id="row_<?php echo $media['id']; ?>">
                            <td class="fw-bold">#<?php echo $media['id']; ?></td>
                            <td>
                                <?php if (!empty($media['icon']) && file_exists(dirname(__DIR__) . '/uploads/media/' . $media['icon'])): ?>
                                    <img src="../uploads/media/<?php echo htmlspecialchars($media['icon']); ?>" class="table-img" onclick="window.open(this.src)" alt="Icon">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="fas fa-image text-secondary fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="wrap-text fw-medium"><?php echo htmlspecialchars(substr($media['title'], 0, 50)); ?><?php echo strlen($media['title']) > 50 ? '…' : ''; ?></td>
                            <td>
                                <?php if (!empty($media['category'])): ?>
                                    <span class="category-badge"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($media['category']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="wrap-text text-muted small">
                                <?php echo htmlspecialchars(substr(strip_tags($media['description']), 0, 80)); ?>
                                <?php echo strlen(strip_tags($media['description'])) > 80 ? '…' : ''; ?>
                            </td>
                            <td>
                                <?php if (!empty($media['source'])): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info p-2">
                                        <i class="fas fa-globe me-1"></i><?php echo htmlspecialchars($media['source']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($media['date'])): ?>
                                    <div><i class="far fa-calendar-alt me-1"></i><?php echo date('d M Y', strtotime($media['date'])); ?></div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="source-link">
                                <?php if (!empty($media['url'])): ?>
                                    <a href="<?php echo htmlspecialchars($media['url']); ?>" target="_blank" title="<?php echo htmlspecialchars($media['url']); ?>">
                                        <i class="fas fa-external-link-alt"></i> Link
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editMedia(<?php echo $media['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteMedia(<?php echo $media['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="mediaModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-success rounded p-2"><i class="fas fa-photo-video text-white"></i></div>
                    <h4 class="fw-bold mb-0" id="modalTitle">Add New Media</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="mediaForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="mediaId" value="0">
                    
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="title" name="title" required placeholder="Enter media title">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" placeholder="e.g., Banner, Logo, Gallery">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Source</label>
                                    <input type="text" class="form-control" id="source" name="source" placeholder="e.g., Website, Social Media">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">URL/Link</label>
                                    <input type="url" class="form-control" id="url" name="url" placeholder="https://example.com/image.jpg">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Date</label>
                                    <input type="date" class="form-control" id="date" name="date">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5" placeholder="Enter media description..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Icon/Image</label>
                            <div class="image-upload-box" onclick="$('#icon').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                <p class="mb-0">Click to upload</p>
                                <small class="text-muted">PNG, JPG, GIF, SVG, WEBP, ICO</small>
                            </div>
                            <input type="file" id="icon" name="icon" accept="image/*" class="d-none" onchange="previewImage(this)">
                            <div id="imagePreview" class="mt-3 text-center"></div>
                            <small class="text-muted d-block mt-2">Recommended size: 300x250px or square</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveBtn" onclick="saveMedia()"><i class="fas fa-save me-2"></i>Save Media</button>
            </div>
        </div>
    </div>
</div>

<script>
// Check if jQuery is available
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded!');
} else {
    console.log('jQuery version:', jQuery.fn.jquery);
}

$(document).ready(function() {
    console.log('Document ready, initializing DataTable...');
    
    // Initialize DataTable
    if ($.fn.DataTable && $('#mediaTable').length) {
        // Destroy existing DataTable if any
        if ($.fn.DataTable.isDataTable('#mediaTable')) {
            $('#mediaTable').DataTable().destroy();
            console.log('Destroyed existing DataTable');
        }
        
        $('#mediaTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            responsive: true,
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
        console.log('DataTable initialized successfully');
    } else {
        console.log('DataTable not available or table not found');
    }
});

function showToast(message, type = 'success') {
    const toast = $(`
        <div class="toast-notification alert alert-${type} shadow">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close float-end" onclick="$(this).parent().remove()"></button>
        </div>
    `);
    $('body').append(toast);
    setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
}

function openAddModal() {
    $('#mediaForm')[0].reset();
    $('#mediaId').val(0);
    $('#modalTitle').text('Add New Media');
    $('#imagePreview').html('');
    $('#icon').val('');
    $('#mediaModal').modal('show');
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#imagePreview').html(`
                <div class="position-relative d-inline-block">
                    <img src="${e.target.result}" class="icon-preview">
                    <button type="button" class="position-absolute top-0 end-0 bg-danger text-white rounded-circle border-0" style="width: 25px; height: 25px;" onclick="$('#imagePreview').html(''); $('#icon').val('')">
                        <i class="fas fa-times fa-xs"></i>
                    </button>
                </div>
            `);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function editMedia(id) {
    console.log('Editing media ID:', id);
    $.ajax({
        url: 'media.php',
        type: 'GET',
        data: { get_media: id },
        dataType: 'json',
        success: function(data) {
            console.log('Edit response:', data);
            if (data) {
                $('#mediaId').val(data.id);
                $('#title').val(data.title);
                $('#url').val(data.url || '');
                $('#description').val(data.description || '');
                $('#source').val(data.source || '');
                $('#date').val(data.date || '');
                $('#category').val(data.category || '');
                $('#modalTitle').text('Edit Media');
                
                if (data.icon) {
                    $('#imagePreview').html(`
                        <div class="text-center">
                            <img src="../uploads/media/${data.icon}" class="icon-preview">
                            <p class="small text-muted mt-1">Current icon. Upload new to replace.</p>
                        </div>
                    `);
                } else {
                    $('#imagePreview').html('');
                }
                
                $('#mediaModal').modal('show');
            } else {
                showToast('Media not found!', 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Edit AJAX Error:', error);
            console.error('Response:', xhr.responseText);
            showToast('Failed to load media data! Check console for details.', 'danger');
        }
    });
}

function saveMedia() {
    const title = $('#title').val().trim();
    
    if (!title) {
        showToast('Please enter a title!', 'danger');
        return;
    }
    
    const formData = new FormData($('#mediaForm')[0]);
    
    $('#saveBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'media.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            console.log('Save response:', response);
            if (response.success) {
                showToast(response.message, 'success');
                $('#mediaModal').modal('hide');
                setTimeout(() => {
                    if (typeof window.refreshCurrentPage === 'function') {
                        window.refreshCurrentPage();
                    } else {
                        location.reload();
                    }
                }, 1000);
            } else {
                showToast(response.message || 'Operation failed!', 'danger');
                $('#saveBtn').html('<i class="fas fa-save me-2"></i>Save Media').prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('Save AJAX Error:', error);
            console.error('Response:', xhr.responseText);
            showToast('An error occurred! Check console for details.', 'danger');
            $('#saveBtn').html('<i class="fas fa-save me-2"></i>Save Media').prop('disabled', false);
        }
    });
}

function deleteMedia(id) {
    if (confirm('Are you sure you want to delete this media? This action cannot be undone!')) {
        $.ajax({
            url: 'media.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    setTimeout(() => {
                        if (typeof window.refreshCurrentPage === 'function') {
                            window.refreshCurrentPage();
                        } else {
                            location.reload();
                        }
                    }, 1000);
                } else {
                    showToast(response.message || 'Delete failed!', 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete AJAX Error:', error);
                showToast('Failed to delete media!', 'danger');
            }
        });
    }
}
</script>
</body>
</html>