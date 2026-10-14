<?php
if(!isset($_SESSION)) { session_start(); }
require_once('database.php'); // Assuming database.php is needed for session or other common functions

$phpErrorLogPath = __DIR__ . '/php_error.log';
$applicationErrorLogPath = __DIR__ . '/debugg/aplication_error_log.txt';

$phpErrorLogContent = '';
$applicationErrorLogContent = '';

// Read PHP error log
if (file_exists($phpErrorLogPath)) {
    $phpErrorLogContent = file_get_contents($phpErrorLogPath);
} else {
    $phpErrorLogContent = "PHP error log file not found at: " . htmlspecialchars($phpErrorLogPath);
}

// Read application error log
if (file_exists($applicationErrorLogPath)) {
    $applicationErrorLogContent = file_get_contents($applicationErrorLogPath);
} else {
    $applicationErrorLogContent = "Application error log file not found at: " . htmlspecialchars($applicationErrorLogPath);
}

// Handle log clearing action
if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    header('Content-Type: application/json');
    $logType = $_POST['log_type'] ?? '';
    $success = false;
    $message = '';

    if ($logType === 'php_error') {
        if (file_exists($phpErrorLogPath) && @file_put_contents($phpErrorLogPath, '') !== false) {
            $success = true;
            $message = 'PHP error log cleared successfully.';
        } else {
            $message = 'Failed to clear PHP error log.';
        }
    } elseif ($logType === 'application_error') {
        if (file_exists($applicationErrorLogPath) && @file_put_contents($applicationErrorLogPath, '') !== false) {
            $success = true;
            $message = 'Application error log cleared successfully.';
        } else {
            $message = 'Failed to clear application error log.';
        }
    } elseif ($logType === 'all') {
        $phpCleared = (file_exists($phpErrorLogPath) && @file_put_contents($phpErrorLogPath, '') !== false);
        $appCleared = (file_exists($applicationErrorLogPath) && @file_put_contents($applicationErrorLogPath, '') !== false);
        if ($phpCleared && $appCleared) {
            $success = true;
            $message = 'All logs cleared successfully.';
        } else {
            $message = 'Failed to clear all logs. Check file permissions.';
        }
    } else {
        $message = 'Invalid log type specified.';
    }
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}
?>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0 text-danger fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>View Error Logs</h5>
                <small class="text-muted">Review system and application error logs.</small>
            </div>
            <a href="#" class="btn btn-outline-secondary btn-sm ajax-link" data-url="utility_tools.php">
                <i class="fas fa-arrow-left me-1"></i> Back to Tools
            </a>
        </div>
        <div class="card-body p-4">
            <div id="logStatusMessage" class="mb-3"></div>

            <ul class="nav nav-tabs" id="logTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="php-error-tab" data-bs-toggle="tab" data-bs-target="#php-error-log" type="button" role="tab" aria-controls="php-error-log" aria-selected="true">PHP Error Log</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="application-error-tab" data-bs-toggle="tab" data-bs-target="#application-error-log" type="button" role="tab" aria-controls="application-error-log" aria-selected="false">Application Error Log</button>
                </li>
            </ul>
            <div class="tab-content" id="logTabsContent">
                <div class="tab-pane fade show active" id="php-error-log" role="tabpanel" aria-labelledby="php-error-tab">
                    <div class="d-flex justify-content-end my-3">
                        <button class="btn btn-sm btn-outline-danger" onclick="clearLog('php_error')"><i class="fas fa-trash me-1"></i> Clear PHP Log</button>
                    </div>
                    <pre class="bg-light p-3 border rounded" style="max-height: 500px; overflow-y: scroll; font-size: 0.8em;"><?php echo htmlspecialchars($phpErrorLogContent); ?></pre>
                </div>
                <div class="tab-pane fade" id="application-error-log" role="tabpanel" aria-labelledby="application-error-tab">
                    <div class="d-flex justify-content-end my-3">
                        <button class="btn btn-sm btn-outline-danger" onclick="clearLog('application_error')"><i class="fas fa-trash me-1"></i> Clear Application Log</button>
                    </div>
                    <pre class="bg-light p-3 border rounded" style="max-height: 500px; overflow-y: scroll; font-size: 0.8em;"><?php echo htmlspecialchars($applicationErrorLogContent); ?></pre>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button class="btn btn-danger" onclick="clearLog('all')"><i class="fas fa-trash-alt me-1"></i> Clear All Logs</button>
            </div>
        </div>
    </div>
</div>

<script>
function clearLog(logType) {
    if (!confirm(`Are you sure you want to clear the ${logType === 'all' ? 'all' : logType.replace('_', ' ')} log(s)? This action cannot be undone.`)) {
        return;
    }

    $.ajax({
        url: 'tool_view_error_logs.php', // This file itself handles the clear action
        type: 'POST',
        data: { action: 'clear_logs', log_type: logType },
        dataType: 'json',
        success: function(response) {
            const statusDiv = $('#logStatusMessage');
            if (response.success) {
                statusDiv.html(`<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-1"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                // Reload the content to show cleared logs
                $('#main_content').load('tool_view_error_logs.php');
            } else {
                statusDiv.html(`<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-circle me-1"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
            }
        },
        error: function() {
            $('#logStatusMessage').html('<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-circle me-1"></i> An error occurred while clearing logs.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        }
    });
}
</script>