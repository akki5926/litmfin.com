<?php
require_once('auth_guard.php');

// uploadCsvMailback.php
if(!isset($_SESSION)){session_start();}
require_once('database.php');
$db = new MYSQLDB();

// Get recent 5 logs using the updated getUploadLogs method
$recentLogs = $db->getUploadLogs(5);
$missingPans = $db->getMissingPanRecords();
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-upload me-2"></i>Upload Mailback Document</h5>
                </div>
                <div class="card-body p-4">
                    <div id="statusDisplay"></div>

                    <form id="mailbackUploadForm" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="fas fa-building me-1"></i> Select RTA *</label>
                                <select class="form-select" id="rtaSelect" name="rta" required>
                                    <option value="">-- Select RTA --</option>
                                    <option value="CAMS">CAMS</option>
                                    <option value="KFINTECH">KFINTECH</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3" id="documentGroup" style="display: none;">
                                <label class="form-label fw-bold"><i class="fas fa-file-alt me-1"></i> Document Type *</label>
                                <select class="form-select" id="documentSelect" name="document_type" required>
                                    <option value="">-- Select Document Type --</option>
                                </select>
                                <div id="documentHelp" class="form-text text-info"></div>
                            </div>
                        </div>

                        <div id="fileUploadGroup" class="mb-4" style="display: none;">
                            <label class="form-label fw-bold"><i class="fas fa-file-upload me-1"></i> Select TXT File (.txt only) *</label>
                            <input type="file" class="form-control" id="fileUpload" name="mailback_file" accept=".txt" required>
                            
                            <div class="alert alert-light border mt-3 mb-0">
                                <small class="text-muted">
                                    <strong>Requirements:</strong> TXT format | Max 10MB | Proper RTA formatting.
                                </small>
                            </div>
                        </div>

                        <div class="text-center" id="submitGroup" style="display: none;">
                            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold" id="btnSubmit">
                                <i class="fas fa-cloud-upload-alt me-2"></i> Upload & Process
                            </button>
                        </div>
                    </form>

                    <div id="innerProcessing" style="display: none;" class="text-center mt-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 fw-bold text-primary">Processing RTA Document... Please wait.</p>
                    </div>
                </div>
            </div>

            <?php if(!empty($missingPans)): ?>
            <div class="card mt-4 shadow-sm border-0 border-danger" style="border-top: 3px solid #dc3545 !important;">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
                    <h5 class="mb-0 text-danger fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Missing PAN Records Detected</h5>
                </div>
                <div class="card-body p-3 bg-light">
                    <p class="small text-muted mb-2">The following records still lack PAN information after synchronization:</p>
                    <div style="max-height: 200px; overflow-y: auto;" class="bg-white border rounded">
                        <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th>Date</th>
                                    <th>Folio Number</th>
                                    <th>Investor Name</th>
                                    <th style="width: 150px;">Update PAN</th>
                                    <th>Fund Description</th>
                                    <th>Source File</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($missingPans as $mp): ?>
                                <tr>
                                    <td class="text-nowrap"><?php echo !empty($mp['transaction_date']) ? date('d M Y', strtotime($mp['transaction_date'])) : '—'; ?></td>
                                    <td><strong><?php echo htmlspecialchars($mp['folio_number'] ?? ''); ?></strong></td>
                                    <td><?php echo htmlspecialchars($mp['investor_name'] ?? ''); ?></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control manual-pan-input text-uppercase" data-folio="<?php echo htmlspecialchars($mp['folio_number'] ?? ''); ?>" placeholder="Enter PAN" maxlength="10" value="<?php echo htmlspecialchars($mp['suggested_pan'] ?? ''); ?>">
                                            <button class="btn btn-outline-primary save-manual-pan" type="button" title="Save PAN"><i class="fas fa-save"></i></button>
                                        </div>
                                        <?php if(!empty($mp['suggested_pan'])): ?>
                                            <div class="text-success mt-1" style="font-size: 10px; line-height: 1;"><i class="fas fa-lightbulb me-1"></i>Auto-suggested</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?php echo htmlspecialchars($mp['fund_desc'] ?? ''); ?></td>
                                    <td class="text-muted" title="<?php echo htmlspecialchars($mp['original_filename'] ?? ''); ?>">
                                        <?php 
                                        $fname = $mp['original_filename'] ?? 'Unknown';
                                        echo htmlspecialchars(strlen($fname) > 30 ? substr($fname, 0, 30) . '...' : $fname); 
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Uploads Section - Now below the upload form -->
            <div class="card mt-4 shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary fw-bold">
                        <i class="fas fa-history me-2"></i>Recent Mailback Uploads
                    </h5>
                    <div>
                        <button type="button" id="clearLogsBtn" class="btn btn-sm btn-outline-danger me-2" title="Clear all logs">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <button type="button" id="refreshLogsBtn" class="btn btn-sm btn-outline-secondary" title="Refresh logs">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0" id="logsContainer">
                    <?php if(!empty($recentLogs)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-secondary">
                                    <tr>
                                        <th class="ps-4">Date & Time</th>
                                        <th>RTA / Doc</th>
                                        <th>Filename</th>
                                        <th class="text-center">Inserted</th>
                                        <th class="text-center">Skipped</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentLogs as $log): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <small class="text-muted d-block"><?php echo date('d M Y', strtotime($log['created_at'])); ?></small>
                                                <strong><?php echo date('h:i A', strtotime($log['created_at'])); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($log['rta']); ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['document_type']); ?></small>
                                            </td>
                                            <td title="<?php echo htmlspecialchars($log['original_filename']); ?>">
                                                <?php 
                                                $logFname = $log['original_filename'];
                                                $downloadUrl = '#';
                                                if (is_dir('uploads/mailback/')) {
                                                    foreach (scandir('uploads/mailback/', SCANDIR_SORT_DESCENDING) as $f) {
                                                        if ($f !== '.' && $f !== '..' && substr($f, -strlen($logFname)) === $logFname) {
                                                            $downloadUrl = 'uploads/mailback/' . $f;
                                                            break;
                                                        }
                                                    }
                                                }
                                                ?>
                                                <a href="<?php echo htmlspecialchars($downloadUrl); ?>" download="<?php echo htmlspecialchars($logFname); ?>" class="text-decoration-none" <?php echo $downloadUrl === '#' ? 'onclick="alert(\'File not found on server.\'); return false;"' : ''; ?>>
                                                    <i class="fas fa-file-download me-1"></i><?php echo htmlspecialchars(strlen($logFname) > 40 ? substr($logFname, 0, 40) . '...' : $logFname); ?>
                                                </a>
                                            </td>
                                            <td class="text-center text-success fw-bold"><?php echo $log['inserted']; ?></td>
                                            <td class="text-center text-danger"><?php echo $log['skipped']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                             </table>
                        </div>

                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                            <p>No upload logs found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Guide Section -->
            <div class="card mt-4 shadow-sm border-0">
                <div class="card-body bg-light">
                    <h6 class="fw-bold"><i class="fas fa-info-circle me-1"></i> Quick Guide</h6>
                    <div class="row small">
                        <div class="col-md-6">
                            <strong>CAMS:</strong> Supports WBR9 (Investor Static), WBR10 (Transaction Feed), WBR49 (SIP).
                        </div>
                        <div class="col-md-6">
                            <strong>KFINTECH:</strong> Supports MFSD201 Consolidated files - Without Split, no Common Format.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#statusDisplay .alert {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

#logsContainer .table tr:hover {
    background-color: #f8f9fa;
}

#logsContainer .table {
    font-size: 0.85rem;
}
</style>

<script>
$(document).ready(function() {
    // Hide the main index loader if it's visible
    $('#loader').hide();

    const documentOptions = {
        'CAMS': [
            {value: 'WBR9', text: 'WBR9 - Investor Static details'},
            {value: 'WBR10', text: 'WBR10 - Transaction feed'},
            {value: 'WBR49', text: 'WBR49 - SIP Statement'}
        ],
        'KFINTECH': [{value: 'MFSD201', text: 'MFSD201 - Without Split, no Common Format'}]
    };

    const documentHelpText = {
        'WBR9': 'Investor static details (Demographics, contact information, bank details)',
        'WBR10': 'Transaction feed (Purchase/Redemption/Switch transactions)',
        'WBR49': 'SIP registration and transaction details',
        'MFSD201': 'Without Split, no Common Format'
    };

    // UI Logic for chaining selects
    $('#rtaSelect').change(function() {
        const rta = $(this).val();
        const docSelect = $('#documentSelect');
        docSelect.empty().append('<option value="">-- Select Document Type --</option>');
        
        if (rta) {
            $('#documentGroup').fadeIn();
            documentOptions[rta].forEach(opt => {
                docSelect.append(`<option value="${opt.value}">${opt.text}</option>`);
            });
        } else {
            $('#documentGroup, #fileUploadGroup, #submitGroup').hide();
        }
    });

    $('#documentSelect').change(function() {
        if ($(this).val()) {
            $('#documentHelp').text(documentHelpText[$(this).val()]);
            $('#fileUploadGroup').fadeIn();
        } else {
            $('#fileUploadGroup, #submitGroup').hide();
        }
    });

    $('#fileUpload').change(function() {
        if ($(this).val()) $('#submitGroup').fadeIn();
    });
    
    // Refresh logs function using view_logs.php with recent=1 parameter
    function refreshLogs() {
        const $refreshBtn = $('#refreshLogsBtn');
        const originalHtml = $refreshBtn.html();
        $refreshBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: 'view_logs.php?recent=1',
            type: 'GET',
            dataType: 'html',
            success: function(response) {
                $('#logsContainer').html(response);
            },
            error: function() {
                showStatusMessage('Failed to refresh logs', 'danger');
            },
            complete: function() {
                setTimeout(function() {
                    $refreshBtn.prop('disabled', false).html(originalHtml);
                }, 500);
            }
        });
    }
    
    // Show status message function
    function showStatusMessage(message, type) {
        const statusDiv = $('#statusDisplay');
        statusDiv.html(`
            <div class="alert alert-${type} alert-dismissible fade show">
                <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-triangle' : 'info-circle')} me-2"></i> 
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        // Auto scroll to status
        $('html, body').animate({
            scrollTop: statusDiv.offset().top - 100
        }, 300);
        
        // Auto hide after 10 seconds for success/info
        if (type !== 'danger') {
            setTimeout(function() {
                statusDiv.fadeOut('slow', function() {
                    statusDiv.empty().show();
                });
            }, 10000);
        }
    }

    // AJAX SUBMISSION TO KEEP SIDEBAR
    $('#mailbackUploadForm').on('submit', function(e) {
        e.preventDefault(); // Prevents page reload and losing the menu
        
        const formData = new FormData(this);
        
        // Validate file size (10MB limit)
        const file = $('#fileUpload')[0].files[0];
        if (file && file.size > 10 * 1024 * 1024) {
            showStatusMessage('File size exceeds 10MB limit', 'danger');
            return;
        }
        
        $('#innerProcessing').show();
        $('#mailbackUploadForm').hide();
        $('#statusDisplay').empty();

        $.ajax({
            url: 'mailback_upload_process.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 0, // 0 means no timeout, wait indefinitely for large files
            success: function(response) {
                $('#innerProcessing').hide();
                $('#mailbackUploadForm').show();
                
                // Check if response contains success or error
                if (response.indexOf('Success') !== -1 || response.indexOf('success') !== -1) {
                    showStatusMessage(response, 'success');
                    // Refresh logs after successful upload
                    setTimeout(function() {
                        refreshLogs();
                    }, 1000);
                } else {
                    showStatusMessage(response, 'danger');
                }
                
                // Reset form after successful upload
                if (response.indexOf('Success') !== -1) {
                    $('#mailbackUploadForm')[0].reset();
                    $('#documentGroup, #fileUploadGroup, #submitGroup').hide();
                }
            },
            error: function(xhr, status, error) {
                $('#innerProcessing').hide();
                $('#mailbackUploadForm').show();
                
                let errorMsg = 'System error occurred during upload.';
                if (status === 'timeout') {
                    errorMsg = 'Upload timeout. The file may still be processing in the background.';
                } else if (xhr.responseText) {
                    errorMsg = xhr.responseText;
                }
                
                showStatusMessage(errorMsg, 'danger');
            }
        });
    });
    
    // Handle manual PAN save
    $(document).on('click', '.save-manual-pan', function() {
        const $btn = $(this);
        const $input = $btn.closest('.input-group').find('.manual-pan-input');
        const folio = $input.data('folio');
        const pan = $input.val().trim().toUpperCase();
        
        if (!pan) {
            alert('Please enter a PAN.');
            return;
        }
        
        const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
        if (!panRegex.test(pan)) {
            alert('Please enter a valid 10-character PAN.');
            return;
        }
        
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: 'view_logs.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update_manual_pan',
                folio: folio,
                pan: pan
            },
            success: function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(function() {
                        $(this).remove();
                        // If no more rows, refresh logs to clear the section
                        if ($('.manual-pan-input').length === 0) {
                            refreshLogs();
                        }
                    });
                } else {
                    alert('Error: ' + response.message);
                    $btn.prop('disabled', false).html('<i class="fas fa-save"></i>');
                }
            },
            error: function() {
                alert('System error occurred.');
                $btn.prop('disabled', false).html('<i class="fas fa-save"></i>');
            }
        });
    });
    
    // Refresh logs button click
    $('#refreshLogsBtn').on('click', function() {
        refreshLogs();
    });
    
    // Clear logs button click
    $('#clearLogsBtn').on('click', function() {
        if (confirm('Are you sure you want to clear all upload logs?')) {
            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: 'view_logs.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'clear_logs' },
                success: function(response) {
                    if (response.success) {
                        refreshLogs();
                    } else {
                        alert('Failed to clear logs.');
                    }
                },
                error: function() {
                    alert('System error occurred.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }
    });
});
</script>