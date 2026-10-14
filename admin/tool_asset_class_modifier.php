<?php
if(!isset($_SESSION)) { session_start(); }
require_once('database.php');

$db = new MYSQLDB();
$pdo = $db->getPDO();

// Handle AJAX update
if (isset($_POST['action']) && $_POST['action'] === 'update_asset_type') {
    header('Content-Type: application/json');
    $isin = $_POST['isin'] ?? '';
    $asset_type = $_POST['asset_type'] ?? '';
    $fund_name = $_POST['fund_name'] ?? '';

    if (in_array($asset_type, ['EQUITY', 'DEBT', 'HYBRID', 'GOLD']) && !empty($isin)) {
        try {
            // Update mtb_scheme_master
            $sql1 = "UPDATE mtb_scheme_master SET asset_type = ? WHERE isin = ?";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([$asset_type, $isin]);

            // Upsert tb_custom_fund_category
            $sql2 = "INSERT INTO tb_custom_fund_category (isin, fund_name, asset_type) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE asset_type = VALUES(asset_type), fund_name = VALUES(fund_name)";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$isin, $fund_name, $asset_type]);

            echo json_encode(['success' => true, 'message' => 'Asset type updated successfully.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    }
    exit;
}

try {
    // 1. Get unique ISINs from transactions using the database function
    $uniqueIsins = $db->getActiveFundISINs();

    // 2. Get scheme details from scheme master
    $smSql = "SELECT isin, MAX(scheme_name) as fund_name, MAX(asset_type) as asset_type 
              FROM mtb_scheme_master 
              WHERE isin IS NOT NULL AND isin != ''
              GROUP BY isin";
    $smStmt = $pdo->query($smSql);
    $smData = [];
    while ($row = $smStmt->fetch(PDO::FETCH_ASSOC)) {
        $smData[$row['isin']] = $row;
    }

    // 3. Merge data in PHP to avoid SQL collation conflicts
    $funds = [];
    foreach ($uniqueIsins as $isin) {
        $funds[] = [
            'isin' => $isin,
            'fund_name' => $smData[$isin]['fund_name'] ?? 'Unknown Fund / Unmapped',
            'asset_type' => $smData[$isin]['asset_type'] ?? 'EQUITY'
        ];
    }

    // 4. Sort by fund name
    usort($funds, function($a, $b) {
        return strcasecmp($a['fund_name'], $b['fund_name']);
    });
} catch (Exception $e) {
    $error = $e->getMessage();
    $funds = [];
}
?>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-layer-group me-2"></i>Asset Class Types</h5>
                <small class="text-muted">View and modify asset classifications for active portfolio funds</small>
            </div>
            <!-- <button class="btn btn-outline-secondary btn-sm" onclick="loadUtilityTool('utility_tools.php')"> -->
			<button class="btn btn-outline-secondary btn-sm" onclick="$('.ajax-link[data-url=\'utility_tools.php\']').click()">
                <i class="fas fa-arrow-left me-1"></i> Back to Tools
            </button>
        </div>
        <div class="card-body p-4">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="assetClassTable" class="table table-striped table-bordered table-hover align-middle" style="width:100%; font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="15%">ISIN</th>
                                <th width="55%">Fund Name</th>
                                <th width="15%" class="text-center">Asset Class</th>
                                <th width="10%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($funds as $index => $fund): ?>
                                <tr>
                                    <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($fund['isin']); ?></span></td>
                                    <td class="fw-semibold text-secondary"><?php echo htmlspecialchars($fund['fund_name'] ?? 'Unknown Fund / Unmapped'); ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $ac = htmlspecialchars(strtoupper($fund['asset_type'] ?? 'EQUITY')); 
                                        $badgeClass = 'bg-secondary';
                                        
                                        if ($ac === 'EQUITY') $badgeClass = 'bg-primary';
                                        elseif ($ac === 'DEBT') $badgeClass = 'bg-success';
                                        elseif ($ac === 'HYBRID') $badgeClass = 'bg-warning text-dark';
                                        elseif ($ac === 'GOLD') $badgeClass = 'bg-info text-dark';
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?> px-2 py-1" id="badge_<?php echo htmlspecialchars($fund['isin']); ?>"><?php echo $ac; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editAssetClass('<?php echo htmlspecialchars($fund['isin']); ?>', '<?php echo htmlspecialchars(addslashes($fund['fund_name'])); ?>', '<?php echo $ac; ?>')" title="Modify Asset Class">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Asset Class Modal -->
<div class="modal fade" id="editAssetModal" tabindex="-1" aria-labelledby="editAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAssetModalLabel">Modify Asset Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editAssetForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ISIN</label>
                        <input type="text" class="form-control bg-light" id="edit_isin" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fund Name</label>
                        <input type="text" class="form-control bg-light" id="edit_fund_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Asset Type</label>
                        <select class="form-select" id="edit_asset_type">
                            <option value="EQUITY">EQUITY</option>
                            <option value="DEBT">DEBT</option>
                            <option value="HYBRID">HYBRID</option>
                            <option value="GOLD">GOLD</option>
                        </select>
                    </div>
                </form>
                <div id="editAssetMessage"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveAssetBtn" onclick="saveAssetClass()">Save changes</button>
            </div>
        </div>
    </div>
</div>

<script>
function editAssetClass(isin, fundName, currentType) {
    $('#edit_isin').val(isin);
    $('#edit_fund_name').val(fundName);
    
    if (['EQUITY', 'DEBT', 'HYBRID', 'GOLD'].includes(currentType)) {
        $('#edit_asset_type').val(currentType);
    } else {
        $('#edit_asset_type').val('EQUITY');
    }
    
    $('#editAssetMessage').html('');
    $('#editAssetModal').modal('show');
}

function saveAssetClass() {
    var isin = $('#edit_isin').val();
    var fundName = $('#edit_fund_name').val();
    var assetType = $('#edit_asset_type').val();
    
    var btn = $('#saveAssetBtn');
    var origText = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'tool_asset_class_modifier.php',
        type: 'POST',
        data: {
            action: 'update_asset_type',
            isin: isin,
            fund_name: fundName,
            asset_type: assetType
        },
        dataType: 'json',
        success: function(res) {
            btn.html(origText).prop('disabled', false);
            if(res.success) {
                $('#editAssetMessage').html('<div class="alert alert-success mt-2"><i class="fas fa-check-circle me-2"></i>' + res.message + '</div>');
                
                // Update UI badge directly to avoid full page reload
                var badge = $('#badge_' + isin);
                badge.text(assetType);
                badge.removeClass('bg-primary bg-success bg-warning text-dark bg-info');
                if(assetType === 'EQUITY') badge.addClass('bg-primary');
                else if(assetType === 'DEBT') badge.addClass('bg-success');
                else if(assetType === 'HYBRID') badge.addClass('bg-warning text-dark');
                else if(assetType === 'GOLD') badge.addClass('bg-info text-dark');
                
                setTimeout(function() {
                    $('#editAssetModal').modal('hide');
                }, 1000);
            } else {
                $('#editAssetMessage').html('<div class="alert alert-danger mt-2"><i class="fas fa-exclamation-triangle me-2"></i>' + res.message + '</div>');
            }
        },
        error: function() {
            btn.html(origText).prop('disabled', false);
            $('#editAssetMessage').html('<div class="alert alert-danger mt-2"><i class="fas fa-exclamation-triangle me-2"></i>Error updating record.</div>');
        }
    });
}

$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#assetClassTable')) {
        $('#assetClassTable').DataTable().destroy();
    }
    $('#assetClassTable').DataTable({
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        language: { search: "Search Funds:" }
    });
});
</script>