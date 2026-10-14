<?php
if(!isset($_SESSION)) { session_start(); }
if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
    http_response_code(403);
    exit("Unauthorized access.");
}

$scanDirectories = [
    'Admin Folder' => __DIR__ . '/../admin',
    'Admin Logs' => __DIR__ . '/../admin/logs',
    'Admin Debug' => __DIR__ . '/../admin/debugg',
    'API Folder' => __DIR__ . '/../api',
    'API Debug Logs' => __DIR__ . '/../api/debugg'
];

// Helper to get all log files
function getLogFiles($directories) {
    $files = [];
    foreach ($directories as $groupName => $dirPath) {
        $files[$groupName] = []; // Always initialize the group
        
        if (is_dir($dirPath)) {
            $txtFiles = glob($dirPath . '/*.txt');
            $logFiles = glob($dirPath . '/*.log');
            $noExtLogs = glob($dirPath . '/*_log');
            $merged = array_merge($txtFiles ?: [], $logFiles ?: [], $noExtLogs ?: []);
            
            if (!empty($merged)) {
                foreach ($merged as $file) {
                    if (is_readable($file)) {
                        $files[$groupName][] = [
                            'name' => basename($file),
                            'path' => realpath($file),
                            'size' => filesize($file),
                            'mtime' => filemtime($file)
                        ];
                    }
                }
            }
        }
    }
    return $files;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    $filePath = $_POST['file_path'] ?? '';
    
    // Security check: ensure path is within allowed directories
    $isAllowed = false;
    $realPath = realpath($filePath);
    if ($realPath) {
        foreach ($scanDirectories as $dir) {
            $allowedDir = realpath($dir);
            if ($allowedDir && strpos($realPath, $allowedDir) === 0) {
                $isAllowed = true;
                break;
            }
        }
    }
    
    if (!$isAllowed) {
        echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized file path.']);
        exit;
    }

    if ($action === 'load_log') {
        if (file_exists($realPath)) {
            // Read last 2MB if file is too large to prevent memory exhaustion
            $filesize = filesize($realPath);
            $limit = 2 * 1024 * 1024; // 2MB
            
            if ($filesize > $limit) {
                $content = "... [File too large, showing last 2MB] ...\n\n" . file_get_contents($realPath, false, null, $filesize - $limit, $limit);
            } else {
                $content = file_get_contents($realPath);
            }
            
            if ($content === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to read file.']);
            } else {
                echo json_encode(['success' => true, 'content' => empty($content) ? '[Empty File]' : $content]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found.']);
        }
        exit;
    }
    
    if ($action === 'clear_log') {
        if (file_exists($realPath)) {
            if (@file_put_contents($realPath, '') !== false) {
                echo json_encode(['success' => true, 'message' => 'Log cleared successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to clear log (check permissions).']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found.']);
        }
        exit;
    }
    
    if ($action === 'delete_log') {
        if (file_exists($realPath)) {
            if (@unlink($realPath)) {
                echo json_encode(['success' => true, 'message' => 'Log file deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete log file (check permissions).']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found.']);
        }
        exit;
    }
}

$logGroups = getLogFiles($scanDirectories);
?>

<style>
    .log-file-item {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .log-file-item:hover {
        background-color: #f8f9fa;
    }
    .log-file-item.active {
        background-color: #e9ecef;
        border-left: 4px solid #C94F1A;
        font-weight: 500;
    }
    #logViewer {
        height: 600px;
        overflow-y: auto;
        font-family: 'Courier New', Courier, monospace;
        font-size: 13px;
        white-space: pre-wrap;
        word-wrap: break-word;
        background-color: #212529;
        color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
    }
    .empty-viewer {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #6c757d;
        background-color: #f8f9fa !important;
        border: 1px dashed #dee2e6;
        font-family: inherit !important;
    }
</style>

<div class="card shadow-sm border-0 h-100">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>System Error Logs</h5>
    </div>
    
    <div class="card-body p-0">
        <div class="row g-0">
            <!-- Sidebar / File List -->
            <div class="col-md-4 col-lg-3 border-end" style="height: 670px; overflow-y: auto; background: #fff;">
                <div class="p-3 border-bottom bg-light">
                    <h6 class="m-0 fw-bold text-secondary">Available Logs</h6>
                </div>
                
                <div class="accordion accordion-flush" id="logFolderAccordion">
                    <?php 
                    $index = 0;
                    foreach ($logGroups as $groupName => $files): 
                        $collapseId = "collapse_" . md5($groupName);
                    ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?> py-2 bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" style="font-size: 14px; font-weight: 600;">
                                <i class="fas fa-folder-open text-warning me-2"></i> <?php echo htmlspecialchars($groupName); ?>
                                <span class="badge bg-secondary ms-auto rounded-pill" style="font-size: 10px;"><?php echo count($files); ?></span>
                            </button>
                        </h2>
                        <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#logFolderAccordion">
                            <div class="accordion-body p-0">
                                <?php if (empty($files)): ?>
                                    <div class="p-3 text-muted text-center" style="font-size: 13px;">
                                        <em>No logs currently present</em>
                                    </div>
                                <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($files as $file): ?>
                                    <li class="list-group-item log-file-item py-2 px-3 border-0" 
                                        data-path="<?php echo htmlspecialchars($file['path']); ?>"
                                        data-name="<?php echo htmlspecialchars($file['name']); ?>">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <div class="text-truncate" style="max-width: 180px; font-size: 13px;" title="<?php echo htmlspecialchars($file['name']); ?>">
                                                <i class="far fa-file-alt text-secondary me-2"></i><?php echo htmlspecialchars($file['name']); ?>
                                            </div>
                                            <small class="text-muted" style="font-size: 11px;">
                                                <?php echo round($file['size'] / 1024, 1); ?> KB
                                            </small>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php $index++; endforeach; ?>
                    
                    <?php if (empty($logGroups)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-search mb-2 fa-2x opacity-50"></i>
                            <p class="small m-0">No log files found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Main Content / Viewer -->
            <div class="col-md-8 col-lg-9 p-4 bg-light">
                <div id="logStatusMessage"></div>
                
                <div id="viewerContainer" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="m-0 fw-bold" id="currentLogName"><i class="fas fa-file-code me-2"></i>Select a log</h6>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary me-2" id="refreshLogBtn" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-warning me-2" id="clearLogBtn" title="Empty File Contents">
                                <i class="fas fa-eraser me-1"></i> Clear
                            </button>
                            <button class="btn btn-sm btn-danger" id="deleteLogBtn" title="Delete File Completely">
                                <i class="fas fa-trash-alt me-1"></i> Delete
                            </button>
                        </div>
                    </div>
                    
                    <div id="logViewer"></div>
                </div>
                
                <div id="emptyState" class="h-100 w-100" style="min-height: 600px;">
                    <div class="empty-viewer rounded">
                        <div class="text-center">
                            <i class="fas fa-mouse-pointer fa-3x mb-3 opacity-50"></i>
                            <h5>Select a log file</h5>
                            <p>Choose a file from the left sidebar to view its contents.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentLogPath = '';
    
    // Handle log file selection
    $('.log-file-item').on('click', function() {
        $('.log-file-item').removeClass('active');
        $(this).addClass('active');
        
        currentLogPath = $(this).data('path');
        const fileName = $(this).data('name');
        
        $('#currentLogName').html(`<i class="fas fa-file-code me-2 text-primary"></i>${fileName}`);
        $('#emptyState').hide();
        $('#viewerContainer').show();
        
        loadLog(currentLogPath);
    });
    
    // Refresh button
    $('#refreshLogBtn').on('click', function() {
        if (currentLogPath) loadLog(currentLogPath);
    });
    
    // Clear button
    $('#clearLogBtn').on('click', function() {
        if (!currentLogPath) return;
        
        if (confirm('Are you sure you want to clear this log file? This cannot be undone.')) {
            $.ajax({
                url: 'tool_view_error_logs.php',
                type: 'POST',
                data: { ajax_action: 'clear_log', file_path: currentLogPath },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#logStatusMessage').html(`<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                        loadLog(currentLogPath); // Reload empty file
                    } else {
                        $('#logStatusMessage').html(`<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-1"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                    }
                },
                error: function() {
                    $('#logStatusMessage').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-1"></i> Network error occurred.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                }
            });
        }
    });
    
    // Delete button
    $('#deleteLogBtn').on('click', function() {
        if (!currentLogPath) return;
        
        if (confirm('Are you sure you want to permanently delete this log file? This action cannot be undone.')) {
            $.ajax({
                url: 'tool_view_error_logs.php',
                type: 'POST',
                data: { ajax_action: 'delete_log', file_path: currentLogPath },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // We need to reload the whole interface to update the sidebar file list
                        $('#main_content').html('<div class="text-center mt-5"><i class="fas fa-spinner fa-spin fa-2x text-secondary"></i><p class="mt-2 text-secondary">Loading...</p></div>');
                        $.get('tool_view_error_logs.php', function(html) {
                            $('#main_content').html(html);
                            $('#logStatusMessage').html(`<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                        });
                    } else {
                        $('#logStatusMessage').html(`<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-1"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                    }
                },
                error: function() {
                    $('#logStatusMessage').html('<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-1"></i> Network error occurred.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                }
            });
        }
    });
    
    // Function to load log via AJAX
    function loadLog(path) {
        const viewer = $('#logViewer');
        viewer.html('<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading...</p></div>');
        
        $.ajax({
            url: 'tool_view_error_logs.php',
            type: 'POST',
            data: { ajax_action: 'load_log', file_path: path },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    viewer.text(response.content);
                    // Scroll to bottom
                    viewer.scrollTop(viewer[0].scrollHeight);
                } else {
                    viewer.html(`<div class="text-danger p-3"><i class="fas fa-exclamation-triangle me-2"></i>${response.message}</div>`);
                }
            },
            error: function() {
                viewer.html('<div class="text-danger p-3"><i class="fas fa-exclamation-triangle me-2"></i>Error loading file. It may be too large or inaccessible.</div>');
            }
        });
    }
});
</script>