<?php
require_once('auth_guard.php');

if(!isset($_SESSION)){session_start();}
require_once('database.php');
$db = new MYSQLDB();

// Handle manual PAN update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_manual_pan') {
    header('Content-Type: application/json');
    $folio = $_POST['folio'] ?? '';
    $pan = strtoupper(trim($_POST['pan'] ?? ''));
    
    if (empty($folio) || empty($pan)) {
        echo json_encode(['success' => false, 'message' => 'Folio and PAN are required.']);
        exit;
    }
    
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
        echo json_encode(['success' => false, 'message' => 'Invalid PAN format.']);
        exit;
    }
    
    $folioEscaped = $db->safeEscape($folio);
    $result = $db->updateMissingPanByFolio($folio, $pan);
    
    echo json_encode(['success' => (bool)$result, 'message' => $result ? 'PAN updated successfully.' : 'Database update failed.']);
    exit;
}

// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    header('Content-Type: application/json');
    $result = $db->clearUploadLogs();
    echo json_encode(['success' => (bool)$result]);
    exit;
}

// Check if we need only recent logs (for AJAX refresh)
$recent_only = isset($_GET['recent']) && $_GET['recent'] == 1;
$limit = $recent_only ? 5 : null;

// Get logs based on request
$logs = $db->getUploadLogs($limit);
$missingPans = $db->getMissingPanRecords();
?>

<?php if(!$recent_only): ?>
<!-- Full page view for standalone access -->
<div class="container-fluid py-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-history me-2"></i>Mailback Processing Logs</h5>
            <div>
                <button type="button" class="btn btn-sm btn-outline-danger me-2" id="clearLogsBtn">
                    <i class="fas fa-trash-alt"></i> Clear Logs
                </button>
                <a href="?recent=1" class="btn btn-sm btn-outline-secondary" id="viewRecentBtn">
                    <i class="fas fa-list"></i> Show Recent 5
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="logsTable">
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
                        <?php if(!empty($logs)): foreach($logs as $log): ?>
                        <tr>
                            <td class="ps-4">
                                <small class="text-muted d-block"><?php echo date('d M Y', strtotime($log['created_at'])); ?></small>
                                <strong><?php echo date('h:i A', strtotime($log['created_at'])); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($log['rta']); ?></span>
                                <br>
                                <small><?php echo htmlspecialchars($log['document_type']); ?></small>
                            </td>
                            <td class="text-truncate" style="max-width:200px;" title="<?php echo htmlspecialchars($log['original_filename']); ?>">
                                <?php echo htmlspecialchars($log['original_filename']); ?>
                            </td>
                            <td class="text-center text-success fw-bold"><?php echo $log['inserted']; ?></td>
                            <td class="text-center text-danger"><?php echo $log['skipped']; ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No logs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#loader').hide();
    
    // Initialize DataTable if it exists
    if ($.fn.DataTable) {
        $('#logsTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries"
            }
        });
    }
    
    $('#clearLogsBtn').on('click', function() {
        if (confirm('Are you sure you want to clear all processing logs?')) {
            $.post('view_logs.php', {action: 'clear_logs'}, function(response) {
                if (response.success) location.reload();
                else alert('Failed to clear logs.');
            }, 'json');
        }
    });
});
</script>

<?php else: ?>
<!-- Recent logs only view (for AJAX refresh on mailback upload page) -->
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light text-secondary">
            <tr>
                <th class="ps-4">Date & Time</th>
                <th>RTA / Doc</th>
                <th class="text-center">Inserted</th>
                <th class="text-center">Skipped</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($logs)): foreach($logs as $log): ?>
                <tr>
                    <td class="ps-4">
                        <small class="text-muted d-block"><?php echo date('d M Y', strtotime($log['created_at'])); ?></small>
                        <strong><?php echo date('h:i A', strtotime($log['created_at'])); ?></strong>
                    </td>
                    <td>
                        <span class="badge bg-info text-dark"><?php echo htmlspecialchars($log['rta']); ?></span>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($log['document_type']); ?></small>
                        <br>
                        <small class="text-truncate d-inline-block" style="max-width: 150px;" title="<?php echo htmlspecialchars($log['original_filename']); ?>">
                            <?php echo htmlspecialchars(substr($log['original_filename'], 0, 20)) . (strlen($log['original_filename']) > 20 ? '...' : ''); ?>
                        </small>
                    </td>
                    <td class="text-center text-success fw-bold"><?php echo $log['inserted']; ?></td>
                    <td class="text-center text-danger"><?php echo $log['skipped']; ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center py-4 text-muted">No logs found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if(!empty($missingPans)): ?>
<div class="p-3 bg-light border-top">
    <h6 class="text-danger fw-bold mb-2"><i class="fas fa-exclamation-triangle me-1"></i> Missing PAN Records Detected</h6>
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
<?php endif; ?>
<?php endif; ?>