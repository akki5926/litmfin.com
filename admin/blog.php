<?php
require_once('auth_guard.php');

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

// Handle CRUD operations via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // Save (Create/Update)
    if ($action == 'save') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = trim($_POST['title'] ?? '');
        $topic = trim($_POST['topic'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $read_time = trim($_POST['read_time'] ?? '');
        
        if (empty($title) || empty($topic) || empty($description)) {
            echo json_encode(['success' => false, 'message' => 'Title, Topic and Description are required!']);
            exit;
        }
        
        $conn = $db->getConnection();
        $image_name = '';
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_dir = dirname(__DIR__) . '/uploads/blog/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['image']['name']);
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name)) {
                    if ($id > 0) {
                        // Get old image to delete it
                        $old_image = $db->getBlogImage($id);
                        if ($old_image && file_exists($upload_dir . $old_image)) {
                            unlink($upload_dir . $old_image);
                        }
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload image!']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid image format! Allowed: jpg, jpeg, png, gif, webp']);
                exit;
            }
        }
        
        if ($id > 0) {
            // Update
            $updateData = [
                'title' => $title,
                'topic' => $topic,
                'description' => $description,
                'url' => $url,
                'date' => $date,
                'read_time' => $read_time
            ];
            
            if ($image_name) {
                $updateData['image'] = $image_name;
            }
            
            if ($db->updateBlog($id, $updateData)) {
                echo json_encode(['success' => true, 'message' => 'Blog updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update blog!']);
            }
        } else {
            // Insert
            $insertData = [
                'title' => $title,
                'topic' => $topic,
                'description' => $description,
                'url' => $url,
                'date' => $date,
                'read_time' => $read_time,
                'image' => $image_name
            ];
            
            $new_id = $db->createBlog($insertData);
            if ($new_id) {
                echo json_encode(['success' => true, 'message' => 'Blog added successfully!', 'id' => $new_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add blog!']);
            }
        }
        exit;
    }
    
    // Delete
    if ($action == 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid blog ID']);
            exit;
        }
        
        if ($db->deleteBlog($id)) {
            echo json_encode(['success' => true, 'message' => 'Blog deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete blog!']);
        }
        exit;
    }
}

// Get single blog for editing
if (isset($_GET['get_blog']) && is_numeric($_GET['get_blog'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['get_blog']);
    $blog = $db->getBlogById($id);
    echo json_encode($blog ?: null);
    exit;
}

// Fetch all blogs for listing
$blogs = $db->getAllBlogs();
if ($blogs === false) $blogs = [];

// Calculate stats using database method
$stats = $db->getBlogStatistics();
$totalBlogs = $stats ? $stats['total_blogs'] : 0;
$publishedBlogs = $stats ? $stats['published_blogs'] : 0;
$draftBlogs = $stats ? $stats['draft_blogs'] : 0;
$blogsWithImages = $stats ? $stats['blogs_with_images'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management</title>
    
    <!-- Only include CSS, not JS - JS is already loaded in parent page -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
        }
        
        /* Modal z-index - higher than sidebar */
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
        
        .topic-badge {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
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
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79,70,229,0.3);
            color: white;
        }
        
        .table-img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
        }
        
        .wrap-text {
            max-width: 200px;
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
            border-color: #4f46e5;
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
        
        /* DataTables wrapper styling */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: white !important;
        }
        
        /* Action buttons */
        .btn-sm {
            padding: 5px 10px;
        }
    </style>
</head>
<body>

<div class="container-fluid px-4 py-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-5 fw-bold mb-1"><i class="fas fa-blog text-primary me-2"></i>Blog Management</h1>
            <p class="text-muted">Manage your blog posts - Create, Edit, Delete</p>
        </div>
        <button class="btn btn-primary-custom" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i>Create New Blog Post
        </button>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">Total Blogs</div>
                <div class="h2 fw-bold mb-0"><?php echo $totalBlogs; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">Published</div>
                <div class="h2 fw-bold mb-0"><?php echo $publishedBlogs; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">Drafts</div>
                <div class="h2 fw-bold mb-0"><?php echo $draftBlogs; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">With Images</div>
                <div class="h2 fw-bold mb-0"><?php echo $blogsWithImages; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Blog Table -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="blogsTable" class="table table-hover w-100">
                    <thead class="table-light">
                        <tr>
                            <th width="50">ID</th>
                            <th width="70">Image</th>
                            <th>Title</th>
                            <th width="130">Topic</th>
                            <th>Description</th>
                            <th width="130">Date</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="blogsTableBody">
                        <?php foreach ($blogs as $blog): ?>
                        <tr id="row_<?php echo $blog['id']; ?>">
                            <td class="fw-bold">#<?php echo $blog['id']; ?></td>
                            <td>
                                <?php if (!empty($blog['image']) && file_exists(dirname(__DIR__) . '/uploads/blog/' . $blog['image'])): ?>
                                    <img src="../uploads/blog/<?php echo htmlspecialchars($blog['image']); ?>" class="table-img" onclick="window.open(this.src)">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="fas fa-image text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="wrap-text"><?php echo htmlspecialchars(substr($blog['title'], 0, 40)); ?><?php echo strlen($blog['title']) > 40 ? '…' : ''; ?></td>
                            <td><span class="topic-badge"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($blog['topic']); ?></span></td>
                            <td class="wrap-text"><?php echo htmlspecialchars(substr(strip_tags($blog['description']), 0, 60)); ?><?php echo strlen(strip_tags($blog['description'])) > 60 ? '…' : ''; ?></td>
                            <td>
                                <?php echo !empty($blog['date']) ? '<div><i class="far fa-calendar-alt me-1"></i>'.date('d M Y', strtotime($blog['date'])).'</div>' : '<span class="badge bg-warning text-dark">Draft</span>'; ?>
                                <?php echo !empty($blog['read_time']) ? '<div class="small text-muted mt-1"><i class="far fa-clock me-1"></i>'.$blog['read_time'].'</div>' : ''; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editBlog(<?php echo $blog['id']; ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBlog(<?php echo $blog['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="blogModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary rounded p-2"><i class="fas fa-edit text-white"></i></div>
                    <h4 class="fw-bold mb-0" id="modalTitle">Add New Blog</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="blogForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="blogId" value="0">
                    
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="title" name="title" required placeholder="Enter blog title">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Topic/Category <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="topic" name="topic" required placeholder="e.g., Technology, Business">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="8" required placeholder="Write your blog content..."></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Blog Date</label>
                                    <input type="date" class="form-control" id="date" name="date">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Read Time</label>
                                    <input type="text" class="form-control" id="read_time" name="read_time" placeholder="5 min read">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Featured Image</label>
                            <div class="image-upload-box" onclick="$('#image').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                <p class="mb-0">Click to upload</p>
                                <small class="text-muted">PNG, JPG, GIF, WEBP</small>
                            </div>
                            <input type="file" id="image" name="image" accept="image/*" class="d-none" onchange="previewImage(this)">
                            <div id="imagePreview" class="mt-3 text-center"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveBtn" onclick="saveBlog()"><i class="fas fa-save me-2"></i>Save Blog</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    let dataTable;
    
    // Initialize DataTable when document is ready
    $(document).ready(function() {
        if ($.fn.DataTable && $('#blogsTable').length) {
            dataTable = $('#blogsTable').DataTable({
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
        $('#blogForm')[0].reset();
        $('#blogId').val(0);
        $('#modalTitle').text('Add New Blog');
        $('#imagePreview').html('');
        $('#image').val('');
        $('#blogModal').modal('show');
    }
    
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').html(`
                    <div class="position-relative d-inline-block">
                        <img src="${e.target.result}" class="rounded shadow-sm" style="width: 150px; height: 120px; object-fit: cover; border: 2px solid #4f46e5;">
                        <button type="button" class="position-absolute top-0 end-0 bg-danger text-white rounded-circle border-0" style="width: 25px; height: 25px;" onclick="$('#imagePreview').html(''); $('#image').val('')">
                            <i class="fas fa-times fa-xs"></i>
                        </button>
                    </div>
                `);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function editBlog(id) {
        $.ajax({
            url: 'blog.php',
            type: 'GET',
            data: { get_blog: id },
            dataType: 'json',
            success: function(data) {
                if (data) {
                    $('#blogId').val(data.id);
                    $('#title').val(data.title);
                    $('#topic').val(data.topic);
                    $('#description').val(data.description);
                    $('#date').val(data.date || '');
                    $('#read_time').val(data.read_time || '');
                    $('#modalTitle').text('Edit Blog');
                    
                    if (data.image) {
                        $('#imagePreview').html(`
                            <div class="text-center">
                                <img src="../uploads/blog/${data.image}" class="rounded shadow-sm" style="width: 150px; height: 120px; object-fit: cover; border: 2px solid #4f46e5;">
                                <p class="small text-muted mt-1">Current image. Upload new to replace.</p>
                            </div>
                        `);
                    } else {
                        $('#imagePreview').html('');
                    }
                    
                    $('#blogModal').modal('show');
                }
            },
            error: function() {
                showToast('Failed to load blog data!', 'danger');
            }
        });
    }
    
    function buildRowHtml(b) {
        const imgHtml = b.image
            ? `<img src="../uploads/blog/${b.image}" class="table-img" onclick="window.open(this.src)">`
            : `<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:50px;height:50px;"><i class="fas fa-image text-secondary"></i></div>`;

        const title = b.title.length > 40 ? b.title.substring(0, 40) + '…' : b.title;
        const desc  = b.description.replace(/<[^>]*>/g,'');
        const descShort = desc.length > 60 ? desc.substring(0, 60) + '…' : desc;

        let dateHtml = '<span class="badge bg-warning text-dark">Draft</span>';
        let readHtml = '';
        if (b.date) {
            const d = new Date(b.date);
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            dateHtml = `<div><i class="far fa-calendar-alt me-1"></i>${String(d.getDate()).padStart(2,'0')} ${months[d.getMonth()]} ${d.getFullYear()}</div>`;
        }
        if (b.read_time) {
            readHtml = `<div class="small text-muted mt-1"><i class="far fa-clock me-1"></i>${b.read_time}</div>`;
        }

        return `<td class="fw-bold">#${b.id}</td>
                <td>${imgHtml}</td>
                <td class="wrap-text">${title}</td>
                <td><span class="topic-badge"><i class="fas fa-tag me-1"></i>${b.topic}</span></td>
                <td class="wrap-text">${descShort}</td>
                <td>${dateHtml}${readHtml}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editBlog(${b.id})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteBlog(${b.id})" title="Delete"><i class="fas fa-trash"></i></button>
                </td>`;
    }
    
    function saveBlog() {
        const title = $('#title').val().trim();
        const topic = $('#topic').val().trim();
        const description = $('#description').val().trim();
        
        if (!title || !topic || !description) {
            showToast('Please fill all required fields!', 'danger');
            return;
        }
        
        const blogId = parseInt($('#blogId').val());
        const formData = new FormData($('#blogForm')[0]);
        
        $('#saveBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled', true);
        
        $.ajax({
            url: 'blog.php', 
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                $('#saveBtn').html('<i class="fas fa-save me-2"></i>Save Blog').prop('disabled', false);
                if (response.success) {
                    showToast(response.message, 'success');
                    $('#blogModal').modal('hide');

                    const targetId = blogId > 0 ? blogId : response.id;

                    // Fetch the saved blog and update the table instantly
                    $.getJSON('blog.php', { get_blog: targetId }, function(data) {
                        if (!data) return;

                        if (blogId > 0) {
                            // Update existing row in DataTable
                            const row = dataTable.row($('#row_' + blogId));
                            if (row.length) {
                                $('#row_' + blogId).html(buildRowHtml(data));
                                dataTable.row($('#row_' + blogId)).invalidate().draw(false);
                            }
                        } else {
                            // Add new row to DataTable
                            const $tr = $('<tr id="row_' + data.id + '">').html(buildRowHtml(data));
                            dataTable.row.add($tr[0]).draw(false);
                        }
                    }).fail(function() {
                        // Fallback reload if fetch fails
                        location.reload();
                    });
                } else {
                    showToast(response.message || 'Operation failed!', 'danger');
                }
            },
            error: function() {
                showToast('An error occurred! Please try again.', 'danger');
                $('#saveBtn').html('<i class="fas fa-save me-2"></i>Save Blog').prop('disabled', false);
            }
        });
    }
    
    function deleteBlog(id) {
        if (confirm('Are you sure you want to delete this blog? This action cannot be undone!')) {
            $.ajax({
                url: 'blog.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        // Remove row instantly from DataTable
                        dataTable.row($('#row_' + id)).remove().draw(false);
                    } else {
                        showToast(response.message || 'Delete failed!', 'danger');
                    }
                },
                error: function() {
                    showToast('Failed to delete blog!', 'danger');
                }
            });
        }
    }
</script>
</body>
</html>