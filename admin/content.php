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

// Clean up corrupted data on page load
$db->cleanUpCorruptedMediaAndBlogs();

// Get counts for stats
$totalMedia = $db->getMediaCount();
$totalBlogs = $db->getBlogCount();
$uniqueCategories = $db->getUniqueMediaCategoryCount();
$publishedBlogs = $db->getPublishedBlogCount();
$totalTestimonials = $db->getTestimonialCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        
        .content-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 28px;
            background: white;
            border-radius: 20px 20px 0 0;
            padding: 0 24px;
        }
        
        .content-tabs .nav-link {
            border: none;
            font-weight: 600;
            padding: 16px 28px;
            color: #64748b;
            transition: 0.2s;
            border-radius: 0;
            font-size: 1rem;
        }
        
        .content-tabs .nav-link i { margin-right: 10px; font-size: 1.2rem; }
        .content-tabs .nav-link:hover { color: #0f172a; background: transparent; }
        .content-tabs .nav-link.active { color: #10b981; background: transparent; border-bottom: 3px solid #10b981; }
        .content-tabs .nav-link.blog-active.active { color: #4f46e5; border-bottom-color: #4f46e5; }
        .content-tabs .nav-link.testi-active.active { color: #f59e0b; border-bottom-color: #f59e0b; }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        
        .btn-media-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
        }
        .btn-blog-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
        }
        .btn-media-primary:hover, .btn-blog-primary:hover { transform: translateY(-2px); color: white; }
        
        .table-img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            background: #f3f4f6;
            padding: 5px;
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
        
        .topic-badge {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .wrap-text { max-width: 250px; white-space: normal; word-break: break-word; }
        
        .modal { z-index: 100000 !important; background: rgba(0,0,0,0.5); }
        .modal-backdrop { z-index: 99999 !important; }
        .modal-content { border-radius: 16px; border: none; }
        
        .image-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .image-upload-box:hover { border-color: #10b981; background: #f8fafc; }
        
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100001;
            min-width: 300px;
            animation: slideIn 0.3s ease;
            z-index: 1000000;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .icon-preview { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; border: 2px solid #10b981; }
        .card-glass { background: white; border-radius: 28px; border: none; box-shadow: 0 8px 30px rgba(0,0,0,0.05); }
        .btn-sm { padding: 5px 10px; }
        
        .img-fallback {
            width: 50px;
            height: 50px;
            background: #f3f4f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .img-fallback i { font-size: 24px; color: #9ca3af; }
        
        .url-link {
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }
        .url-link a { color: #10b981; text-decoration: none; font-size: 12px; }
        .url-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container-fluid px-4 py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-5 fw-bold mb-1"><i class="fas fa-folder-open text-success me-2"></i>Content Management</h1>
            <p class="text-muted">Manage your Media Library and Blog Posts from one dashboard</p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row row-cols-1 row-cols-md-5 g-3 mb-4">
        <div class="col"><div class="stat-card"><div class="text-muted small">Total Media</div><div class="h2 fw-bold mb-0" id="statTotalMedia"><?php echo $totalMedia; ?></div></div></div>
        <div class="col"><div class="stat-card"><div class="text-muted small">Media Categories</div><div class="h2 fw-bold mb-0" id="statCategories"><?php echo $uniqueCategories; ?></div></div></div>
        <div class="col"><div class="stat-card"><div class="text-muted small">Total Blogs</div><div class="h2 fw-bold mb-0" id="statTotalBlogs"><?php echo $totalBlogs; ?></div></div></div>
        <div class="col"><div class="stat-card"><div class="text-muted small">Published Blogs</div><div class="h2 fw-bold mb-0" id="statPublishedBlogs"><?php echo $publishedBlogs; ?></div></div></div>
        <div class="col"><div class="stat-card"><div class="text-muted small">Total Testimonials</div><div class="h2 fw-bold mb-0" id="statTotalTestimonials"><?php echo $totalTestimonials; ?></div></div></div>
    </div>
    
    <!-- Tabs -->
    <ul class="nav content-tabs" id="contentTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="media-tab" data-bs-toggle="tab" data-bs-target="#mediaPanel" type="button" role="tab">
                <i class="fas fa-photo-video"></i> Media Library
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link blog-active" id="blog-tab" data-bs-toggle="tab" data-bs-target="#blogPanel" type="button" role="tab">
                <i class="fas fa-blog"></i> Blog Management
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link testi-active" id="testi-tab" data-bs-toggle="tab" data-bs-target="#testiPanel" type="button" role="tab">
                <i class="fas fa-quote-left"></i> Testimonials
            </button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="contentTabContent">
        
        <!-- MEDIA PANEL -->
        <div class="tab-pane fade show active" id="mediaPanel" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-semibold mb-0"><i class="fas fa-images text-success me-2"></i>Media Items</h3>
                <button class="btn btn-media-primary" onclick="openMediaModal()"><i class="fas fa-plus me-2"></i>Add New Media</button>
            </div>
            
            <div class="card card-glass">
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
                                    <th width="100">Source</th>
                                    <th width="100">Date</th>
                                    <th width="80">URL</th>
                                    <th width="80">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="mediaTableBody">
                                <tr><td colspan="9" class="text-center py-5"><div class="spinner-border text-success"></div> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- BLOG PANEL -->
        <div class="tab-pane fade" id="blogPanel" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-semibold mb-0"><i class="fas fa-edit text-primary me-2"></i>Blog Posts</h3>
                <button class="btn btn-blog-primary" onclick="openBlogModal()"><i class="fas fa-plus me-2"></i>Create New Post</button>
            </div>
            
            <div class="card card-glass">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table id="blogTable" class="table table-hover w-100">
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
                            <tbody id="blogTableBody">
                                <tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- TESTIMONIAL PANEL -->
        <div class="tab-pane fade" id="testiPanel" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-semibold mb-0"><i class="fas fa-quote-left text-warning me-2"></i>Testimonials</h3>
                <button class="btn btn-warning text-dark fw-bold" onclick="openTestiModal()"><i class="fas fa-plus me-2"></i>Add Testimonial</button>
            </div>
            
            <div class="card card-glass">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table id="testiTable" class="table table-hover w-100">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">ID</th>
                                    <th width="70">Image</th>
                                    <th>Name</th>
                                    <th>Role/Company</th>
                                    <th>Text</th>
                                    <th width="80">Order</th>
                                    <th width="80">Status</th>
                                    <th width="80">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="testiTableBody">
                                <tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-warning"></div> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MEDIA MODAL -->
<div class="modal fade" id="mediaModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h4 class="fw-bold mb-0" id="mediaModalTitle">Add New Media</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="mediaForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="mediaId" value="0">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="mb-3"><label class="fw-bold">Title <span class="text-danger">*</span></label><input type="text" class="form-control" id="mediaTitle" name="title" required></div>
                            <div class="row"><div class="col-md-6 mb-3"><label>Category</label><input type="text" class="form-control" id="mediaCategory" name="category"></div>
                            <div class="col-md-6 mb-3"><label>Source</label><input type="text" class="form-control" id="mediaSource" name="source"></div></div>
                            <div class="row"><div class="col-md-6 mb-3"><label>URL</label><input type="url" class="form-control" id="mediaUrl" name="url" placeholder="https://example.com"></div>
                            <div class="col-md-6 mb-3"><label>Date</label><input type="date" class="form-control" id="mediaDate" name="date"></div></div>
                            <div class="mb-3"><label>Description</label><textarea class="form-control" id="mediaDescription" name="description" rows="4"></textarea></div>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold">Icon/Image</label>
                            <div class="image-upload-box" onclick="$('#mediaFileInput').click()"><i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i><p>Click to upload</p><small>JPG, PNG, GIF, SVG, WEBP</small></div>
                            <input type="file" id="mediaFileInput" name="icon" accept="image/*" class="d-none" onchange="previewMediaImage(this)">
                            <div id="mediaPreview" class="mt-3 text-center"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="saveMediaBtn" onclick="saveMedia()">Save Media</button>
            </div>
        </div>
    </div>
</div>

<!-- BLOG MODAL -->
<div class="modal fade" id="blogModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h4 class="fw-bold mb-0" id="blogModalTitle">Add New Blog</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="blogForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="blogId" value="0">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="mb-3"><label class="fw-bold">Title <span class="text-danger">*</span></label><input type="text" class="form-control form-control-lg" id="blogTitle" name="title" required></div>
                            <div class="mb-3"><label class="fw-bold">Topic/Category <span class="text-danger">*</span></label><input type="text" class="form-control" id="blogTopic" name="topic" required></div>
                            <div class="mb-3"><label class="fw-bold">Description <span class="text-danger">*</span></label><textarea class="form-control" id="blogDescription" name="description" rows="6" required></textarea></div>
                            <div class="row"><div class="col-md-6 mb-3"><label>Date</label><input type="date" class="form-control" id="blogDate" name="date"></div>
                            <div class="col-md-6 mb-3"><label>Read Time</label><input type="text" class="form-control" id="blogReadTime" name="read_time" placeholder="5 min read"></div></div>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold">Featured Image</label>
                            <div class="image-upload-box" onclick="$('#blogFileInput').click()"><i class="fas fa-image fa-3x text-muted mb-2"></i><p>Upload image</p><small>JPG, PNG, WEBP</small></div>
                            <input type="file" id="blogFileInput" name="image" accept="image/*" class="d-none" onchange="previewBlogImage(this)">
                            <div id="blogPreview" class="mt-3 text-center"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveBlogBtn" onclick="saveBlog()">Save Post</button>
            </div>
        </div>
    </div>
</div>

<!-- TESTIMONIAL MODAL -->
<div class="modal fade" id="testiModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h4 class="fw-bold mb-0" id="testiModalTitle">Add New Testimonial</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="testiForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="testiId" value="0">
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="fw-bold">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="testiName" name="name" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Role / Company</label>
                                    <input type="text" class="form-control" id="testiRole" name="role">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="fw-bold">Sort Order</label>
                                    <input type="number" class="form-control" id="testiSort" name="sort_order" value="0">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="fw-bold">Status</label>
                                    <select class="form-control" id="testiStatus" name="status">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold">Testimonial Text <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="testiText" name="text" rows="5" required></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold">Image / Avatar</label>
                            <div class="image-upload-box" onclick="$('#testiFileInput').click()">
                                <i class="fas fa-user-circle fa-3x text-muted mb-2"></i>
                                <p>Upload avatar</p>
                                <small>JPG, PNG, WEBP</small>
                            </div>
                            <input type="file" id="testiFileInput" name="image" accept="image/*" class="d-none" onchange="previewTestiImage(this)">
                            <div id="testiPreview" class="mt-3 text-center"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-dark fw-bold" id="saveTestiBtn" onclick="saveTesti()">Save Testimonial</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

<script>
var mediaTable, blogTable, testiTable;

function isValidFilename(filename) {
    if (!filename) return false;
    return /^[a-zA-Z0-9_\-\.]+$/.test(filename);
}

$(document).ready(function() {
    loadMediaList();
    loadBlogList();
    loadTestiList();
    
    $('#media-tab').on('shown.bs.tab', function() {
        if (mediaTable) setTimeout(() => mediaTable.columns.adjust(), 100);
    });
    $('#blog-tab').on('shown.bs.tab', function() {
        if (blogTable) setTimeout(() => blogTable.columns.adjust(), 100);
    });
    $('#testi-tab').on('shown.bs.tab', function() {
        if (testiTable) setTimeout(() => testiTable.columns.adjust(), 100);
    });
});

// ==================== MEDIA FUNCTIONS ====================
function loadMediaList() {
    $.ajax({
        url: 'mediaajax.php',
        type: 'GET',
        data: { action: 'list' },
        dataType: 'json',
        cache: false,
        success: function(res) {
            if (res.success) {
                renderMediaTable(res.data);
                $('#statTotalMedia').text(res.total || res.data.length);
                let cats = [...new Set(res.data.filter(m => m.category && m.category.trim()).map(m => m.category))];
                $('#statCategories').text(cats.length);
            } else {
                $('#mediaTableBody').html('<tr><td colspan="9" class="text-center text-danger py-5">Failed to load media.</td></tr>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Media load error:', error);
            $('#mediaTableBody').html('<tr><td colspan="9" class="text-center text-danger py-5">Failed to load media. Error: ' + error + '</td></tr>');
        }
    });
}

function renderMediaTable(data) {
    let html = '';
    data.forEach(m => {
        let isValid = m.icon && isValidFilename(m.icon);
        let iconHtml = `<div class="img-fallback"><i class="fas fa-image"></i></div>`;
        if (isValid) {
            iconHtml = `<img src="../uploads/media/${escapeHtml(m.icon)}" class="table-img" onclick="window.open(this.src)" onerror="this.onerror=null; this.parentNode.innerHTML='<div class=\'img-fallback\'><i class=\'fas fa-image\'></i></div>'">`;
        }
        
        let urlHtml = '—';
        if (m.url && m.url.trim()) {
            let displayUrl = m.url.length > 30 ? m.url.substring(0, 30) + '...' : m.url;
            urlHtml = `<a href="${escapeHtml(m.url)}" target="_blank" title="${escapeHtml(m.url)}" style="color:#10b981;"><i class="fas fa-external-link-alt"></i> Link</a>`;
        }
        
        html += `<tr id="media_row_${m.id}">
            <td class="fw-bold">#${m.id}</td>
            <td><div class="d-flex justify-content-center align-items-center" style="width:50px;height:50px;">${iconHtml}</div></td>
            <td class="wrap-text">${escapeHtml(m.title).substring(0,50)}${escapeHtml(m.title).length > 50 ? '…' : ''}</td>
            <td>${m.category ? `<span class="category-badge">${escapeHtml(m.category)}</span>` : '—'}</td>
            <td class="wrap-text small">${escapeHtml((m.description||'').substring(0,70))}</td>
            <td>${m.source ? escapeHtml(m.source) : '—'}</td>
            <td>${m.date || '—'}</td>
            <td>${urlHtml}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick="editMedia(${m.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteMedia(${m.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    });
    if ($.fn.DataTable.isDataTable('#mediaTable')) {
        let dt = $('#mediaTable').DataTable();
        dt.clear();
        if (html !== '') {
            dt.rows.add($(html));
        }
        dt.draw();
    } else {
        if (html === '') {
            html = '<tr><td colspan="9" class="text-center text-muted py-5">No media found. Click "Add New Media" to get started.</td></tr>';
        }
        $('#mediaTableBody').html(html);
        mediaTable = $('#mediaTable').DataTable({ order: [[0, 'desc']], pageLength: 10, responsive: true });
    }
}

function openMediaModal() {
    $('#mediaForm')[0].reset();
    $('#mediaId').val(0);
    $('#mediaPreview').html('');
    $('#mediaFileInput').val('');
    $('#mediaModalTitle').text('Add New Media');
    $('#mediaModal').modal('show');
}

function editMedia(id) {
    $.ajax({
        url: 'mediaajax.php',
        type: 'GET',
        data: { action: 'get', id: id },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.data) {
                let d = res.data;
                $('#mediaId').val(d.id);
                $('#mediaTitle').val(d.title);
                $('#mediaCategory').val(d.category || '');
                $('#mediaSource').val(d.source || '');
                $('#mediaUrl').val(d.url || '');
                $('#mediaDate').val(d.date || '');
                $('#mediaDescription').val(d.description || '');
                $('#mediaModalTitle').text('Edit Media');
                if (d.icon && isValidFilename(d.icon)) {
                    $('#mediaPreview').html(`<div class="text-center"><img src="../uploads/media/${escapeHtml(d.icon)}" class="icon-preview" onerror="this.onerror=null;this.src=''"><p class="small text-muted mt-1">Current icon</p></div>`);
                } else {
                    $('#mediaPreview').html('');
                }
                $('#mediaModal').modal('show');
            } else {
                showToast('Media not found', 'danger');
            }
        },
        error: function() { showToast('Failed to load media', 'danger'); }
    });
}

function saveMedia() {
    if (!$('#mediaTitle').val().trim()) {
        showToast('Title is required', 'danger');
        return;
    }
    
    let formData = new FormData();
    let mediaId = $('#mediaId').val();
    let action = mediaId == 0 ? 'create' : 'update';
    
    formData.append('action', action);
    if (mediaId > 0) formData.append('id', mediaId);
    formData.append('title', $('#mediaTitle').val().trim());
    formData.append('category', $('#mediaCategory').val());
    formData.append('source', $('#mediaSource').val());
    formData.append('url', $('#mediaUrl').val());
    formData.append('date', $('#mediaDate').val());
    formData.append('description', $('#mediaDescription').val());
    
    let fileInput = document.getElementById('mediaFileInput');
    if (fileInput.files.length > 0) {
        formData.append('icon', fileInput.files[0]);
    }
    
    $('#saveMediaBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'mediaajax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 30000,
        success: function(res) {
            if (res.success) {
                showToast(res.message, 'success');
                $('#mediaModal').modal('hide');
                // Instantly rebuild the media table from server
                loadMediaList();
            } else {
                showToast(res.message || 'Error saving media', 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Save error:', error);
            showToast('Error saving media: ' + error, 'danger');
        },
        complete: function() {
            $('#saveMediaBtn').html('Save Media').prop('disabled', false);
        }
    });
}

function deleteMedia(id) {
    if (confirm('Delete this media permanently?')) {
        // Instantly fade out the row for immediate feedback
        $(`#media_row_${id}`).fadeOut(200);
        $.ajax({
            url: 'mediaajax.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showToast('Media deleted', 'success');
                    // Remove from DataTable and update count
                    if (mediaTable) {
                        try { mediaTable.row($(`#media_row_${id}`)).remove().draw(false); } catch(e) {}
                        loadMediaList();
                    } else {
                        $(`#media_row_${id}`).remove();
                    }
                    let cur = parseInt($('#statTotalMedia').text()) || 0;
                    if (cur > 0) $('#statTotalMedia').text(cur - 1);
                } else {
                    $(`#media_row_${id}`).fadeIn(200); // restore if failed
                    showToast(res.message, 'danger');
                }
            },
            error: function() {
                $(`#media_row_${id}`).fadeIn(200);
                showToast('Error deleting media', 'danger');
            }
        });
    }
}

function previewMediaImage(input) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = e => $('#mediaPreview').html(`<div><img src="${e.target.result}" class="icon-preview"><button type="button" class="btn btn-sm btn-danger mt-2" onclick="$('#mediaPreview').html('');$('#mediaFileInput').val('')">Remove</button></div>`);
        reader.readAsDataURL(input.files[0]);
    }
}

// ==================== BLOG FUNCTIONS ====================
function loadBlogList() {
    $.ajax({
        url: 'blogajax.php',
        type: 'GET',
        data: { action: 'list' },
        dataType: 'json',
        cache: false,
        success: function(res) {
            if (res.success) {
                renderBlogTable(res.data);
                $('#statTotalBlogs').text(res.data.length);
                let published = res.data.filter(b => b.date && new Date(b.date) <= new Date()).length;
                $('#statPublishedBlogs').text(published);
            } else {
                $('#blogTableBody').html('<tr><td colspan="7" class="text-center text-danger py-5">Failed to load blogs.</td></tr>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Blog load error:', error);
            $('#blogTableBody').html('<tr><td colspan="7" class="text-center text-danger py-5">Failed to load blogs.</td></tr>');
        }
    });
}

function renderBlogTable(data) {
    let html = '';
    data.forEach(b => {
        let isValid = b.image && isValidFilename(b.image);
        let imgHtml = `<div class="img-fallback"><i class="fas fa-image"></i></div>`;
        if (isValid) {
            imgHtml = `<img src="../uploads/blog/${escapeHtml(b.image)}" class="table-img" onclick="window.open(this.src)" onerror="this.onerror=null; this.parentNode.innerHTML='<div class=\'img-fallback\'><i class=\'fas fa-image\'></i></div>'">`;
        }
        
        html += `<tr id="blog_row_${b.id}">
            <td class="fw-bold">#${b.id}</td>
            <td><div class="d-flex justify-content-center align-items-center" style="width:50px;height:50px;">${imgHtml}</div></td>
            <td class="wrap-text fw-medium">${escapeHtml(b.title).substring(0,50)}${b.title.length > 50 ? '…' : ''}</td>
            <td><span class="topic-badge">${escapeHtml(b.topic)}</span></td>
            <td class="wrap-text small">${escapeHtml((b.description||'').substring(0,65))}</td>
            <td>${b.date || '<span class="badge bg-warning text-dark">Draft</span>'}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick="editBlog(${b.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteBlog(${b.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    });
    if ($.fn.DataTable.isDataTable('#blogTable')) {
        let dt = $('#blogTable').DataTable();
        dt.clear();
        if (html !== '') {
            dt.rows.add($(html));
        }
        dt.draw();
    } else {
        if (html === '') {
            html = '<tr><td colspan="7" class="text-center text-muted py-5">No blog posts found. Click "Create New Post" to get started.</td></tr>';
        }
        $('#blogTableBody').html(html);
        blogTable = $('#blogTable').DataTable({ order: [[0, 'desc']], pageLength: 10, responsive: true });
    }
}

function openBlogModal() {
    $('#blogForm')[0].reset();
    $('#blogId').val(0);
    $('#blogPreview').html('');
    $('#blogFileInput').val('');
    $('#blogModalTitle').text('Add New Blog');
    $('#blogModal').modal('show');
}

function editBlog(id) {
    $.ajax({
        url: 'blogajax.php',
        type: 'GET',
        data: { action: 'get', id: id },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.data) {
                let d = res.data;
                $('#blogId').val(d.id);
                $('#blogTitle').val(d.title);
                $('#blogTopic').val(d.topic);
                $('#blogDescription').val(d.description);
                $('#blogDate').val(d.date || '');
                $('#blogReadTime').val(d.read_time || '');
                $('#blogModalTitle').text('Edit Blog');
                if (d.image && isValidFilename(d.image)) {
                    $('#blogPreview').html(`<div class="text-center"><img src="../uploads/blog/${escapeHtml(d.image)}" class="icon-preview" onerror="this.onerror=null;this.src=''"><p class="small text-muted mt-1">Current image</p></div>`);
                } else {
                    $('#blogPreview').html('');
                }
                $('#blogModal').modal('show');
            } else {
                showToast('Blog not found', 'danger');
            }
        },
        error: function() { showToast('Failed to load blog', 'danger'); }
    });
}

function saveBlog() {
    if (!$('#blogTitle').val().trim() || !$('#blogTopic').val().trim() || !$('#blogDescription').val().trim()) {
        showToast('Title, Topic and Description are required', 'danger');
        return;
    }
    
    let formData = new FormData();
    let blogId = $('#blogId').val();
    let action = blogId == 0 ? 'create' : 'update';
    
    formData.append('action', action);
    if (blogId > 0) formData.append('id', blogId);
    formData.append('title', $('#blogTitle').val().trim());
    formData.append('topic', $('#blogTopic').val().trim());
    formData.append('description', $('#blogDescription').val().trim());
    formData.append('date', $('#blogDate').val());
    formData.append('read_time', $('#blogReadTime').val());
    
    let fileInput = document.getElementById('blogFileInput');
    if (fileInput.files.length > 0) {
        formData.append('image', fileInput.files[0]);
    }
    
    $('#saveBlogBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'blogajax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 30000,
        success: function(res) {
            if (res.success) {
                showToast(res.message, 'success');
                $('#blogModal').modal('hide');
                // Instantly rebuild the blog table from server
                loadBlogList();
            } else {
                showToast(res.message || 'Error saving blog', 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Save error:', error);
            showToast('Error saving blog: ' + error, 'danger');
        },
        complete: function() {
            $('#saveBlogBtn').html('Save Post').prop('disabled', false);
        }
    });
}

function deleteBlog(id) {
    if (confirm('Delete this blog post permanently?')) {
        // Instantly fade out the row for immediate feedback
        $(`#blog_row_${id}`).fadeOut(200);
        $.ajax({
            url: 'blogajax.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showToast('Blog deleted', 'success');
                    // Remove from DataTable and update count
                    if (blogTable) {
                        try { blogTable.row($(`#blog_row_${id}`)).remove().draw(false); } catch(e) {}
                        loadBlogList();
                    } else {
                        $(`#blog_row_${id}`).remove();
                    }
                    let cur = parseInt($('#statTotalBlogs').text()) || 0;
                    if (cur > 0) $('#statTotalBlogs').text(cur - 1);
                } else {
                    $(`#blog_row_${id}`).fadeIn(200); // restore if failed
                    showToast(res.message, 'danger');
                }
            },
            error: function() {
                $(`#blog_row_${id}`).fadeIn(200);
                showToast('Error deleting blog', 'danger');
            }
        });
    }
}

function previewBlogImage(input) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = e => $('#blogPreview').html(`<div><img src="${e.target.result}" class="icon-preview"><button type="button" class="btn btn-sm btn-danger mt-2" onclick="$('#blogPreview').html('');$('#blogFileInput').val('')">Remove</button></div>`);
        reader.readAsDataURL(input.files[0]);
    }
}

// ==================== TESTIMONIAL FUNCTIONS ====================
function loadTestiList() {
    $.ajax({
        url: 'testimonialajax.php',
        type: 'GET',
        data: { action: 'list' },
        dataType: 'json',
        cache: false,
        success: function(res) {
            if (res.success) {
                renderTestiTable(res.data);
                $('#statTotalTestimonials').text(res.data.length);
            } else {
                $('#testiTableBody').html('<tr><td colspan="8" class="text-center text-danger py-5">Failed to load testimonials.</td></tr>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Testi load error:', error);
            $('#testiTableBody').html('<tr><td colspan="8" class="text-center text-danger py-5">Failed to load testimonials.</td></tr>');
        }
    });
}

function renderTestiTable(data) {
    let html = '';
    data.forEach(t => {
        let isValid = t.image && isValidFilename(t.image);
        let imgHtml = `<div class="img-fallback"><i class="fas fa-user"></i></div>`;
        if (isValid) {
            imgHtml = `<img src="../uploads/testimonials/${escapeHtml(t.image)}" class="table-img rounded-circle" onclick="window.open(this.src)" onerror="this.onerror=null; this.parentNode.innerHTML='<div class=\\'img-fallback rounded-circle\\'><i class=\\'fas fa-user\\'></i></div>'">`;
        }
        let statusHtml = t.status == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
        
        html += `<tr id="testi_row_${t.id}">
            <td class="fw-bold">#${t.id}</td>
            <td><div class="d-flex justify-content-center align-items-center" style="width:50px;height:50px;">${imgHtml}</div></td>
            <td class="fw-medium">${escapeHtml(t.name)}</td>
            <td>${t.role ? escapeHtml(t.role) : '—'}</td>
            <td class="wrap-text small">${escapeHtml((t.text||'').substring(0,65))}${t.text && t.text.length > 65 ? '…' : ''}</td>
            <td>${t.sort_order}</td>
            <td>${statusHtml}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick="editTesti(${t.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteTesti(${t.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    });
    if ($.fn.DataTable.isDataTable('#testiTable')) {
        let dt = $('#testiTable').DataTable();
        dt.clear();
        if (html !== '') {
            dt.rows.add($(html));
        }
        dt.draw();
    } else {
        if (html === '') {
            html = '<tr><td colspan="8" class="text-center text-muted py-5">No testimonials found. Click "Add New Testimonial" to get started.</td></tr>';
        }
        $('#testiTableBody').html(html);
        testiTable = $('#testiTable').DataTable({ order: [[5, 'asc'], [0, 'desc']], pageLength: 10, responsive: true });
    }
}

function openTestiModal() {
    $('#testiForm')[0].reset();
    $('#testiId').val(0);
    $('#testiPreview').html('');
    $('#testiFileInput').val('');
    $('#testiModalTitle').text('Add New Testimonial');
    $('#testiModal').modal('show');
}

function editTesti(id) {
    $.ajax({
        url: 'testimonialajax.php',
        type: 'GET',
        data: { action: 'get', id: id },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.data) {
                let d = res.data;
                $('#testiId').val(d.id);
                $('#testiName').val(d.name);
                $('#testiRole').val(d.role || '');
                $('#testiText').val(d.text);
                $('#testiSort').val(d.sort_order || 0);
                $('#testiStatus').val(d.status || 0);
                $('#testiModalTitle').text('Edit Testimonial');
                
                if (d.image && isValidFilename(d.image)) {
                    $('#testiPreview').html(`<div class="text-center"><img src="../uploads/testimonials/${escapeHtml(d.image)}" class="icon-preview rounded-circle" onerror="this.onerror=null;this.src=''"><p class="small text-muted mt-1">Current image</p></div>`);
                } else {
                    $('#testiPreview').html('');
                }
                $('#testiModal').modal('show');
            } else {
                showToast('Testimonial not found', 'danger');
            }
        },
        error: function() { showToast('Failed to load testimonial', 'danger'); }
    });
}

function saveTesti() {
    if (!$('#testiName').val().trim() || !$('#testiText').val().trim()) {
        showToast('Name and Text are required', 'danger');
        return;
    }
    
    let formData = new FormData();
    let testiId = $('#testiId').val();
    let action = testiId == 0 ? 'create' : 'update';
    
    formData.append('action', action);
    if (testiId > 0) formData.append('id', testiId);
    formData.append('name', $('#testiName').val().trim());
    formData.append('role', $('#testiRole').val().trim());
    formData.append('text', $('#testiText').val().trim());
    formData.append('sort_order', $('#testiSort').val());
    formData.append('status', $('#testiStatus').val());
    
    let fileInput = document.getElementById('testiFileInput');
    if (fileInput.files.length > 0) {
        formData.append('image', fileInput.files[0]);
    }
    
    $('#saveTestiBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'testimonialajax.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 30000,
        success: function(res) {
            if (res.success) {
                showToast(res.message, 'success');
                $('#testiModal').modal('hide');
                loadTestiList();
            } else {
                showToast(res.message || 'Error saving testimonial', 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Save error:', error);
            showToast('Error saving testimonial: ' + error, 'danger');
        },
        complete: function() {
            $('#saveTestiBtn').html('Save Testimonial').prop('disabled', false);
        }
    });
}

function deleteTesti(id) {
    if (confirm('Delete this testimonial permanently?')) {
        $(`#testi_row_${id}`).fadeOut(200);
        $.ajax({
            url: 'testimonialajax.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showToast('Testimonial deleted', 'success');
                    if (testiTable) {
                        try { testiTable.row($(`#testi_row_${id}`)).remove().draw(false); } catch(e) {}
                        loadTestiList();
                    } else {
                        $(`#testi_row_${id}`).remove();
                    }
                    let cur = parseInt($('#statTotalTestimonials').text()) || 0;
                    if (cur > 0) $('#statTotalTestimonials').text(cur - 1);
                } else {
                    $(`#testi_row_${id}`).fadeIn(200);
                    showToast(res.message, 'danger');
                }
            },
            error: function() {
                $(`#testi_row_${id}`).fadeIn(200);
                showToast('Error deleting testimonial', 'danger');
            }
        });
    }
}

function previewTestiImage(input) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = e => $('#testiPreview').html(`<div><img src="${e.target.result}" class="icon-preview rounded-circle"><button type="button" class="btn btn-sm btn-danger mt-2" onclick="$('#testiPreview').html('');$('#testiFileInput').val('')">Remove</button></div>`);
        reader.readAsDataURL(input.files[0]);
    }
}

function showToast(msg, type) {
    let icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    let bgClass = type === 'success' ? 'alert-success' : (type === 'danger' ? 'alert-danger' : 'alert-info');
    let toast = $(`<div class="toast-notification alert ${bgClass} shadow"><i class="fas fa-${icon} me-2"></i> ${escapeHtml(msg)}<button type="button" class="btn-close float-end" onclick="$(this).parent().remove()"></button></div>`);
    $('body').append(toast);
    setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3500);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}
</script>
</body>
</html>