<?php
if(!isset($_SESSION)) { session_start(); }
require_once('database.php');

$db = new MYSQLDB();
$pdo = $db->getPDO();

// AJAX Handler for updating NAV data
if (isset($_POST['action']) && $_POST['action'] === 'update_nav') {
    header('Content-Type: application/json');
    $type = $_POST['type'] ?? '';
    $name = $_POST['name'] ?? '';
    $clientCode = $_POST['client'] ?? '';
    $navValue = (float)($_POST['nav_value'] ?? 0);
    $navDate = $_POST['nav_date'] ?? '';

    if (empty($name) || empty($navDate)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        if ($type === 'Bonds') {
            $check = $pdo->prepare("SELECT * FROM tb_other_investment_nav WHERE name = ? AND nav_date = ?");
            $check->execute([$name, $navDate]);
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE tb_other_investment_nav SET current_nav = ? WHERE name = ? AND nav_date = ?");
                $stmt->execute([$navValue, $name, $navDate]);
            } else {
                $stmtNse = $pdo->prepare("SELECT nse_code FROM tb_other_investment_nav WHERE name = ? ORDER BY nav_date DESC LIMIT 1");
                $stmtNse->execute([$name]);
                $nseCode = $stmtNse->fetchColumn() ?: '';
                
                $stmt = $pdo->prepare("INSERT INTO tb_other_investment_nav (name, nse_code, current_nav, nav_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $nseCode, $navValue, $navDate]);
            }
        } elseif ($type === 'Pms' || $type === 'Aif') {
            $table = ($type === 'Pms') ? 'tb_pms_nav' : 'tb_aif_nav';
            $nameCol = ($type === 'Pms') ? 'pms_name' : 'aif_name';
            
            $stmt = $pdo->prepare("INSERT INTO {$table} (CLIENT_CODE, {$nameCol}, cur_value, nav_date) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE cur_value = VALUES(cur_value)");
            $stmt->execute([$clientCode, $name, $navValue, $navDate]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Helper function for NAV date status
function getNavCutoffDate() {
    $today = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $dayOfWeek = (int)$today->format('N'); // 1 for Monday, 7 for Sunday

    if ($dayOfWeek === 1) { // Monday, last working day was Friday
        return $today->modify('-3 days')->format('Y-m-d');
    } elseif ($dayOfWeek === 7) { // Sunday, last working day was Friday
        return $today->modify('-2 days')->format('Y-m-d');
    } elseif ($dayOfWeek === 6) { // Saturday, last working day was Friday
        return $today->modify('-1 day')->format('Y-m-d');
    } else { // Tuesday to Friday
        return $today->modify('-1 day')->format('Y-m-d');
    }
}
$navCutoffDate = getNavCutoffDate();

// 1. Get Active ISINs and identify SIF funds
$activeIsins = $db->getActiveFundISINs();
$sifIsins = [];
if (!empty($activeIsins)) {
    $inClauseForSif = implode(',', array_map(fn($isin) => "'" . $db->safeEscape($isin) . "'", $activeIsins));
    
    // Fetch ISINs directly from scheme master where amc_code indicates a SIF fund
    $sifSql = "SELECT isin FROM mtb_scheme_master WHERE isin IN ($inClauseForSif) AND UPPER(amc_code) LIKE '%\_SIF%'";
    $sifResult = $db->select($sifSql);
    if ($sifResult) {
        $sifIsins = array_column($sifResult, 'isin');
    }
}
$sifIsinsMap = array_flip($sifIsins);

// 2. Fetch all active Mutual Fund NAVs (including SIFs)
$allMfNavs = [];
if (!empty($activeIsins)) {
    $inClause = implode(',', array_map(fn($isin) => "'" . $db->safeEscape($isin) . "'", $activeIsins));
    $sql = "SELECT t1.isin, t1.scheme_name, t1.nav, t1.nav_date AS last_nav_date
            FROM tb_nav t1
            INNER JOIN (
                SELECT isin, MAX(nav_date) AS max_date
                FROM tb_nav
                WHERE isin IN ($inClause)
                GROUP BY isin
            ) t2 ON t1.isin = t2.isin AND t1.nav_date = t2.max_date
            ORDER BY t1.nav_date ASC, t1.scheme_name ASC";
    try {
        $stmt = $pdo->query($sql);
        if ($stmt) $allMfNavs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// 3. Separate MF and SIF funds and calculate MF summary
$mfNavs = [];
$sifNavs = [];
$mfSummary = ['uptodate' => 0, 'old7' => 0, 'old30' => 0];
$now_ts = time();

foreach ($allMfNavs as $nav) {
    if (isset($sifIsinsMap[$nav['isin']])) {
        $sifNavs[] = $nav;
    } else {
        $mfNavs[] = $nav;
        // Calculate summary for regular MFs
        if (!empty($nav['last_nav_date'])) {
            $daysOld = ($now_ts - strtotime($nav['last_nav_date'])) / 86400;
            if ($nav['last_nav_date'] >= $navCutoffDate) {
                $mfSummary['uptodate']++;
            }
            if ($daysOld > 30) {
                $mfSummary['old30']++;
            } elseif ($daysOld > 7) {
                $mfSummary['old7']++;
            }
        }
    }
}

// 4. Bond NAVs and Summary
$bondNavs = [];
$bondSummary = ['uptodate' => 0, 'old7' => 0, 'old30' => 0];
try {
    $sql = "SELECT t1.name, t1.nse_code, t1.current_nav, t1.nav_date as last_nav_date
            FROM tb_other_investment_nav t1
            INNER JOIN (
                SELECT name, MAX(nav_date) as max_date
                FROM tb_other_investment_nav
                GROUP BY name
            ) t2 ON t1.name = t2.name AND t1.nav_date = t2.max_date
            ORDER BY t1.current_nav DESC";
    $stmt = $pdo->query($sql);
    if ($stmt) {
        $bondNavs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($bondNavs as $nav) {
        if (!empty($nav['last_nav_date'])) {
            $daysOld = ($now_ts - strtotime($nav['last_nav_date'])) / 86400;
            if ($nav['last_nav_date'] >= $navCutoffDate) {
                $bondSummary['uptodate']++;
            }
            if ($daysOld > 30) {
                $bondSummary['old30']++;
            } elseif ($daysOld > 7) {
                $bondSummary['old7']++;
            }
        }
    }
} catch(Exception $e) {}

// 5. PMS NAVs (Client-specific)
$pmsNavs = [];
$pmsSummary = ['uptodate' => 0, 'old7' => 0, 'old30' => 0];
try {
    $sql = "SELECT t1.slno, t1.CLIENT_CODE, u.PRIMARY_HOLDER_NAME as client_name, t1.pms_name, t1.cur_value as curr_value, t1.nav_date as last_nav_date
            FROM tb_pms_nav t1
            LEFT JOIN vw_client_profiles u ON t1.CLIENT_CODE = u.CLIENT_CODE
            INNER JOIN (
                SELECT CLIENT_CODE, pms_name, MAX(nav_date) as max_date
                FROM tb_pms_nav
                GROUP BY CLIENT_CODE, pms_name
            ) t2 ON t1.CLIENT_CODE = t2.CLIENT_CODE AND t1.pms_name = t2.pms_name AND t1.nav_date = t2.max_date
            ORDER BY t1.cur_value DESC";
    $stmt = $pdo->query($sql);
    if ($stmt) $pmsNavs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pmsNavs as $nav) {
        if (!empty($nav['last_nav_date'])) {
            $daysOld = ($now_ts - strtotime($nav['last_nav_date'])) / 86400;
            if ($nav['last_nav_date'] >= $navCutoffDate) {
                $pmsSummary['uptodate']++;
            }
            if ($daysOld > 30) {
                $pmsSummary['old30']++;
            } elseif ($daysOld > 7) {
                $pmsSummary['old7']++;
            }
        }
    }
} catch(Exception $e) {}

// 6. AIF NAVs (Client-specific)
$aifNavs = [];
$aifSummary = ['uptodate' => 0, 'old7' => 0, 'old30' => 0];
try {
    $sql = "SELECT t1.slno, t1.CLIENT_CODE, u.PRIMARY_HOLDER_NAME as client_name, t1.aif_name, t1.cur_value as curr_value, t1.nav_date as last_nav_date
            FROM tb_aif_nav t1
            LEFT JOIN vw_client_profiles u ON t1.CLIENT_CODE = u.CLIENT_CODE
            INNER JOIN (
                SELECT CLIENT_CODE, aif_name, MAX(nav_date) as max_date
                FROM tb_aif_nav
                GROUP BY CLIENT_CODE, aif_name
            ) t2 ON t1.CLIENT_CODE = t2.CLIENT_CODE AND t1.aif_name = t2.aif_name AND t1.nav_date = t2.max_date
            ORDER BY t1.cur_value DESC";
    $stmt = $pdo->query($sql);
    if ($stmt) $aifNavs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($aifNavs as $nav) {
        if (!empty($nav['last_nav_date'])) {
            $daysOld = ($now_ts - strtotime($nav['last_nav_date'])) / 86400;
            if ($nav['last_nav_date'] >= $navCutoffDate) {
                $aifSummary['uptodate']++;
            }
            if ($daysOld > 30) {
                $aifSummary['old30']++;
            } elseif ($daysOld > 7) {
                $aifSummary['old7']++;
            }
        }
    }
} catch(Exception $e) {}
?>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0 text-success fw-bold"><i class="fas fa-edit me-2"></i>View & Manage NAVs</h5>
                <small class="text-muted">View latest NAVs and manually update values of some asset types.</small>
            </div>
            <a href="#" class="btn btn-outline-secondary btn-sm ajax-link" data-url="utility_tools.php">
                <i class="fas fa-arrow-left me-1"></i> Back to Tools
            </a>
        </div>
        <div class="card-body p-4">
            <div class="accordion" id="navAccordion">
                
                <!-- Mutual Funds -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingMf">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMf" aria-expanded="false" aria-controls="collapseMf">
                            <strong>Mutual Funds (<?php echo count($mfNavs); ?> Active Funds)</strong>
                        </button>
                    </h2>
                    <div id="collapseMf" class="accordion-collapse collapse" aria-labelledby="headingMf" data-bs-parent="#navAccordion">
                        <div class="accordion-body">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Up-to-date: <?php echo $mfSummary['uptodate']; ?></span>
                                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> &gt; 7 Days Old: <?php echo $mfSummary['old7']; ?></span>
                                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> &gt; 30 Days Old: <?php echo $mfSummary['old30']; ?></span>
                            </div>
                            <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover align-middle nav-datatable" style="width:100%; font-size: 13px;">
                                <thead class="table-light">
                                    <tr>
                                        <th width="10%">ISIN</th>
                                        <th width="45%">Scheme Name</th>
                                        <th width="15%">Latest NAV</th>
                                        <th width="15%">Latest NAV Date</th>
                                        <th width="15%">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mfNavs as $nav): 
                                        $isOutdated = empty($nav['last_nav_date']) || $nav['last_nav_date'] < $navCutoffDate;
                                        $statusClass = $isOutdated ? 'text-danger' : 'text-success';
                                        $statusText = $isOutdated ? 'Outdated' : 'Up-to-date';
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($nav['isin']); ?></span></td>
                                        <td><?php echo htmlspecialchars($nav['scheme_name'] ?? 'Unknown'); ?></td>
                                        <td class="fw-bold text-primary"><?php echo number_format((float)($nav['nav'] ?? 0), 2); ?></td>
                                        <td class="fw-bold <?php echo $statusClass; ?>"><?php echo htmlspecialchars($nav['last_nav_date'] ?? 'None'); ?></td>
                                        <td><span class="<?php echo $statusClass; ?> fw-semibold"><i class="fas fa-circle me-1"></i><?php echo $statusText; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- SIF Funds -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSif">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSif" aria-expanded="false" aria-controls="collapseSif">
                            <strong>Special Investment Funds (SIF) (<?php echo count($sifNavs); ?> Active Funds)</strong>
                        </button>
                    </h2>
                    <div id="collapseSif" class="accordion-collapse collapse" aria-labelledby="headingSif" data-bs-parent="#navAccordion">
                        <div class="accordion-body table-responsive">
                            <table class="table table-striped table-bordered table-hover align-middle nav-datatable" style="width:100%; font-size: 13px;">
                                <thead class="table-light">
                                    <tr>
                                        <th width="10%">ISIN</th>
                                        <th width="45%">Scheme Name</th>
                                        <th width="15%">Latest NAV</th>
                                        <th width="15%">Latest NAV Date</th>
                                        <th width="15%">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sifNavs as $nav): 
                                        $isOutdated = empty($nav['last_nav_date']) || $nav['last_nav_date'] < $navCutoffDate;
                                        $statusClass = $isOutdated ? 'text-danger' : 'text-success';
                                        $statusText = $isOutdated ? 'Outdated' : 'Up-to-date';
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($nav['isin']); ?></span></td>
                                        <td><?php echo htmlspecialchars($nav['scheme_name'] ?? 'Unknown'); ?></td>
                                        <td class="fw-bold text-primary"><?php echo number_format((float)($nav['nav'] ?? 0), 2); ?></td>
                                        <td class="fw-bold <?php echo $statusClass; ?>"><?php echo htmlspecialchars($nav['last_nav_date'] ?? 'None'); ?></td>
                                        <td><span class="<?php echo $statusClass; ?> fw-semibold"><i class="fas fa-circle me-1"></i><?php echo $statusText; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Other Alternative Investments (Bonds, PMS, AIF) -->
                <?php 
                $altSections = [
                    ['id' => 'Bonds', 'title' => 'Bonds', 'data' => $bondNavs, 'col1' => 'Bond Name', 'col2' => 'NSE Code', 'key1' => 'name', 'key2' => 'nse_code', 'nav_key' => 'current_nav', 'summary' => $bondSummary, 'is_client_wise' => false, 'status_rule' => '30_days'],
                    ['id' => 'Pms', 'title' => 'PMS', 'data' => $pmsNavs, 'col1' => 'PMS Name', 'col2' => 'Client', 'key1' => 'pms_name', 'key2' => 'client_name', 'nav_key' => 'curr_value', 'summary' => $pmsSummary, 'is_client_wise' => true, 'status_rule' => '30_days'],
                    ['id' => 'Aif', 'title' => 'AIF', 'data' => $aifNavs, 'col1' => 'AIF Name', 'col2' => 'Client', 'key1' => 'aif_name', 'key2' => 'client_name', 'nav_key' => 'curr_value', 'summary' => $aifSummary, 'is_client_wise' => true, 'status_rule' => '30_days']
                ];
                foreach ($altSections as $sec): 
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php echo $sec['id']; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $sec['id']; ?>" aria-expanded="false" aria-controls="collapse<?php echo $sec['id']; ?>">
                            <strong><?php echo $sec['title']; ?> (<?php echo count($sec['data']); ?> Entries)</strong>
                        </button>
                    </h2>
                    <div id="collapse<?php echo $sec['id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $sec['id']; ?>" data-bs-parent="#navAccordion">
                        <div class="accordion-body">
                            <?php if ($sec['summary']): ?>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Up-to-date: <?php echo $sec['summary']['uptodate']; ?></span>
                                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> &gt; 7 Days Old: <?php echo $sec['summary']['old7']; ?></span>
                                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> &gt; 30 Days Old: <?php echo $sec['summary']['old30']; ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover align-middle nav-datatable" style="width:100%; font-size: 13px;">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40%"><?php echo $sec['col1']; ?></th>
                                        <th width="15%"><?php echo $sec['col2']; ?></th>
                                        <th width="15%">Latest NAV</th>
                                        <th width="10%">Latest NAV Date</th>
                                        <th width="10%">Status</th>
                                        <th width="10%" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sec['data'] as $row): 
                                        $navDateRaw = $row['last_nav_date'] ?? '';
                                        $navDateSafe = empty($navDateRaw) ? date('Y-m-d') : $navDateRaw;

                                        if ($sec['status_rule'] === '30_days') {
                                            $daysOld = empty($navDateRaw) ? 999 : ($now_ts - strtotime($navDateRaw)) / 86400;
                                            $isOutdated = $daysOld > 30;
                                            $statusClass = $isOutdated ? 'text-danger' : 'text-success';
                                            $statusText = $isOutdated ? 'Outdated' : 'Up-to-date';
                                        }
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($row[$sec['key1']] ?? 'Unknown'); ?></td>
                                        <td>
                                            <?php if($sec['is_client_wise']): ?>
                                                <?php echo htmlspecialchars($row[$sec['key2']] ?? 'Unknown'); ?><br><small class="text-muted"><?php echo htmlspecialchars($row['CLIENT_CODE'] ?? ''); ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row[$sec['key2']] ?? '-'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-info"><?php echo number_format((float)($row[$sec['nav_key']] ?? 0), 2); ?></td>
                                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($navDateRaw ?: 'None'); ?></td>
                                        <td>
                                            <span class="<?php echo $statusClass; ?> fw-semibold"><i class="fas fa-circle fa-xs me-1"></i><?php echo $statusText; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary edit-nav-btn" data-type="<?php echo $sec['id']; ?>" data-name="<?php echo htmlspecialchars($row[$sec['key1']]); ?>" data-client="<?php echo htmlspecialchars($row['CLIENT_CODE'] ?? ''); ?>" data-nav="<?php echo (float)($row[$sec['nav_key']] ?? 0); ?>" data-date="<?php echo htmlspecialchars($navDateSafe); ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>

<!-- Edit NAV Modal -->
<div class="modal fade" id="editNavModal" tabindex="-1" aria-labelledby="editNavModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editNavModalLabel">Edit NAV Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editNavForm">
                    <input type="hidden" id="editNavType" name="type">
                    <input type="hidden" id="editNavName" name="name">
                    <input type="hidden" id="editNavClient" name="client">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" class="form-control bg-light" id="editNavNameDisplay" readonly>
                    </div>
                    <div class="mb-3" id="editNavClientWrapper">
                        <label class="form-label fw-bold">Client</label>
                        <input type="text" class="form-control bg-light" id="editNavClientDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="editNavValue" class="form-label fw-bold">NAV Value</label>
                        <input type="number" step="0.0001" class="form-control" id="editNavValue" name="nav_value" required>
                    </div>
                    <div class="mb-3">
                        <label for="editNavDate" class="form-label fw-bold">NAV Date</label>
                        <input type="date" class="form-control" id="editNavDate" name="nav_date" required>
                        <div class="form-text">Please confirm the date for which this new NAV is announced.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveNavBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.nav-datatable').each(function() {
        if ($.fn.DataTable.isDataTable(this)) { $(this).DataTable().destroy(); }
        $(this).DataTable({ pageLength: 25, lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]] });
    });

    $(document).on('click', '.edit-nav-btn', function() {
        const type = $(this).attr('data-type');
        const name = $(this).attr('data-name');
        const client = $(this).attr('data-client');
        
        $('#editNavType').val(type);
        $('#editNavName').val(name);
        $('#editNavClient').val(client);
        $('#editNavNameDisplay').val(name);
        
        if (type === 'Pms' || type === 'Aif') {
            $('#editNavClientWrapper').show();
            $('#editNavClientDisplay').val(client);
        } else {
            $('#editNavClientWrapper').hide();
        }
        
        $('#editNavValue').val($(this).attr('data-nav'));
        $('#editNavDate').val($(this).attr('data-date'));
        
        const editModal = new bootstrap.Modal(document.getElementById('editNavModal'));
        editModal.show();
    });

    $('#saveNavBtn').click(function() {
        const form = document.getElementById('editNavForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const formData = new FormData(form);
        formData.append('action', 'update_nav');
        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'tool_analyze_nav_dates.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var myModalEl = document.getElementById('editNavModal');
                    var modal = bootstrap.Modal.getInstance(myModalEl);
                    modal.hide();
                    myModalEl.addEventListener('hidden.bs.modal', function (event) {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');
                        $('#main_content').load('tool_manage_navs.php');
                    }, { once: true });
                } else {
                    alert('Error updating NAV: ' + (response.message || 'Unknown error'));
                    $btn.prop('disabled', false).html('Save Changes');
                }
            },
            error: function() {
                alert('Network error occurred while saving.');
                $btn.prop('disabled', false).html('Save Changes');
            }
        });
    });
});
</script>