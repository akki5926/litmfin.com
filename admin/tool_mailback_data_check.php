<?php
if(!isset($_SESSION)) { session_start(); }
require_once('database.php');

$db = new MYSQLDB();
$pdo = $db->getPDO();

// Handle AJAX request for Client Investigation Block
if (isset($_POST['action']) && $_POST['action'] === 'fetch_client_trx') {
    header('Content-Type: application/json');
    $ucc = trim($_POST['ucc'] ?? '');
    if (!$ucc) {
        echo json_encode(['success' => false, 'message' => 'UCC is required']);
        exit;
    }
    
    $user = $db->getUserByClientCode($ucc);
    $pan = $user['PRIMARY_HOLDER_PAN'] ?? '';
    $name = $user['PRIMARY_HOLDER_NAME'] ?? '';
    
    if (!$pan && !$name) {
        echo json_encode(['success' => false, 'message' => 'User not found in system or missing identifiers.']);
        exit;
    }
    
    // Fetch normal financial transactions ('P' or 'R') and exclude Rejected ('R') mode
    $sql = "SELECT * FROM tb_transaction_mailback 
            WHERE transaction_mode != 'R' 
            AND transaction_flag IN ('P', 'R') 
            AND (pan1 = ? OR investor_name = ?)
            ORDER BY transaction_date DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pan, $name]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enrich with AMC and Fund names
    foreach ($transactions as &$t) {
        $isin = $t['isin'] ?? '';
        $t['amc_name'] = $isin ? $db->getAMCNamebyISIN($isin) : 'Unmapped';
        $t['fund_name'] = $isin ? $db->getFundNamebyISIN($isin) : 'Unmapped';
    }
    
    echo json_encode(['success' => true, 'data' => $transactions]);
    exit;
}

// Fetch summary metrics for anomalies
$metrics = [
    'missing_isin' => $pdo->query("SELECT COUNT(*) FROM tb_transaction_mailback WHERE isin IS NULL OR isin = ''")->fetchColumn(),
    'missing_pan'  => $pdo->query("SELECT COUNT(*) FROM tb_transaction_mailback WHERE transaction_flag IN ('P', 'R') AND (pan1 IS NULL OR pan1 = '')")->fetchColumn(),
    'rejected'     => $pdo->query("SELECT COUNT(*) FROM tb_transaction_mailback WHERE transaction_mode = 'R'")->fetchColumn(),
    'anomalies'    => $pdo->query("SELECT COUNT(*) FROM tb_transaction_mailback WHERE transaction_flag IN ('P', 'R') AND transaction_mode != 'R' AND (transaction_date IS NULL OR transaction_id IS NULL OR transaction_id = '' OR amount IS NULL OR amount <= 0 OR nav IS NULL OR nav <= 0 OR price IS NULL OR price <= 0 OR units IS NULL OR units <= 0)")->fetchColumn()
];

// Helper function to render anomaly tables
function renderAnomalyTable($pdo, $db, $sqlQuery) {
    $records = $pdo->query($sqlQuery)->fetchAll(PDO::FETCH_ASSOC);
    if (empty($records)) {
        echo "<div class='p-4 text-success fw-bold text-center'><i class='fas fa-check-circle fa-2x mb-2 d-block'></i>No issues found in this category.</div>";
        return;
    }
    echo "<div class='table-responsive' style='max-height: 400px;'><table class='table table-sm table-striped table-bordered table-hover align-middle mb-0' style='font-size: 12px;'>";
    echo "<thead class='table-dark sticky-top'><tr><th>Investor Name</th><th>Folio</th><th>ISIN</th><th>Fund Name</th><th>AMC Name</th><th>Date</th><th>Type</th><th>Mode</th><th>Amount</th><th>Units</th><th>NAV/Price</th></tr></thead><tbody>";
    foreach ($records as $r) {
        $isin = $r['isin'] ?? '';
        $fundName = $isin ? $db->getFundNamebyISIN($isin) : 'N/A';
        $amcName = $isin ? $db->getAMCNamebyISIN($isin) : 'N/A';
        
        $truncFund = strlen($fundName) > 25 ? substr($fundName, 0, 25) . '...' : $fundName;
        $truncAmc = strlen($amcName) > 20 ? substr($amcName, 0, 20) . '...' : $amcName;

        $type = ($r['transaction_flag'] == 'P') ? 'Purchase' : (($r['transaction_flag'] == 'R') ? 'Redemption' : (($r['transaction_flag'] == 'D') ? 'Dividend' : 'Non-Financial'));
        $mode = ($r['transaction_mode'] == 'R') ? '<span class="badge bg-danger">Rejected</span>' : '<span class="badge bg-success">Normal</span>';

        echo "<tr>";
        echo "<td class='fw-semibold'>" . htmlspecialchars($r['investor_name'] ?? 'Unknown') . "</td>";
        echo "<td>" . htmlspecialchars($r['folio_number'] ?? '') . "</td>";
        echo "<td><span class='badge bg-light text-dark border'>" . htmlspecialchars($isin ?: 'MISSING') . "</span></td>";
        echo "<td title='".htmlspecialchars($fundName)."'>" . htmlspecialchars($truncFund) . "</td>";
        echo "<td title='".htmlspecialchars($amcName)."'>" . htmlspecialchars($truncAmc) . "</td>";
        echo "<td>" . htmlspecialchars($r['transaction_date'] ?? 'MISSING') . "</td>";
        echo "<td>" . $type . "</td>";
        echo "<td>" . $mode . "</td>";
        echo "<td class='text-end'>" . number_format((float)($r['amount'] ?? 0), 2) . "</td>";
        echo "<td class='text-end'>" . number_format((float)($r['units'] ?? 0), 4) . "</td>";
        echo "<td class='text-end'>" . number_format((float)($r['nav'] ?? $r['price'] ?? 0), 4) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
    echo "<div class='card-footer bg-white text-muted small text-end'>Showing up to top 100 records</div>";
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-secondary mb-0"><i class="fas fa-envelope-open-text me-2"></i>Mailback Data Check</h4>
            <p class="text-muted small">Analyze transaction tables, track missing allocations, and isolate financial anomalies.</p>
        </div>
        <button class="btn btn-outline-secondary btn-sm" onclick="$('.ajax-link[data-url=\'utility_tools.php\']').click()">
            <i class="fas fa-arrow-left me-1"></i> Back to Tools
        </button>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="mailbackTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Data Anomalies Overview</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="client-tab" data-bs-toggle="tab" data-bs-target="#client-investigate" type="button" role="tab">Client Transactions Investigation</button>
        </li>
    </ul>

    <div class="tab-content" id="mailbackTabsContent">
        <!-- TAB 1: System Anomalies -->
        <div class="tab-content tab-pane fade show active" id="overview" role="tabpanel">
            <div class="accordion" id="anomalyAccordion">
                <!-- Accordion Item 1 -->
                <div class="accordion-item shadow-sm mb-3 border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                            1. Missing ISIN Mappings (<?php echo $metrics['missing_isin']; ?> records)
                        </button>
                    </h2>
                    <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#anomalyAccordion">
                        <div class="accordion-body p-0">
                            <?php renderAnomalyTable($pdo, $db, "SELECT * FROM tb_transaction_mailback WHERE isin IS NULL OR isin = '' LIMIT 100"); ?>
                        </div>
                    </div>
                </div>

                <!-- Accordion Item 2 -->
                <div class="accordion-item shadow-sm mb-3 border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold text-warning text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                            2. Missing Primary PAN on Financial Txns (<?php echo $metrics['missing_pan']; ?> records)
                        </button>
                    </h2>
                    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#anomalyAccordion">
                        <div class="accordion-body p-0">
                            <?php renderAnomalyTable($pdo, $db, "SELECT * FROM tb_transaction_mailback WHERE transaction_flag IN ('P', 'R') AND (pan1 IS NULL OR pan1 = '') LIMIT 100"); ?>
                        </div>
                    </div>
                </div>

                <!-- Accordion Item 3 -->
                <div class="accordion-item shadow-sm mb-3 border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold text-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                            3. Rejected Transactions (<?php echo $metrics['rejected']; ?> records)
                        </button>
                    </h2>
                    <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#anomalyAccordion">
                        <div class="accordion-body p-0">
                            <?php renderAnomalyTable($pdo, $db, "SELECT * FROM tb_transaction_mailback WHERE transaction_mode = 'R' LIMIT 100"); ?>
                        </div>
                    </div>
                </div>

                <!-- Accordion Item 4 -->
                <div class="accordion-item shadow-sm mb-3 border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold text-info text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                            4. Financial Data Irregularities (Zero/Null Data) (<?php echo $metrics['anomalies']; ?> records)
                        </button>
                    </h2>
                    <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#anomalyAccordion">
                        <div class="accordion-body p-0">
                            <?php renderAnomalyTable($pdo, $db, "SELECT * FROM tb_transaction_mailback WHERE transaction_flag IN ('P', 'R') AND transaction_mode != 'R' AND (transaction_date IS NULL OR transaction_id IS NULL OR transaction_id = '' OR amount IS NULL OR amount <= 0 OR nav IS NULL OR nav <= 0 OR price IS NULL OR price <= 0 OR units IS NULL OR units <= 0) LIMIT 100"); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: Client Investigation -->
        <div class="tab-content tab-pane fade" id="client-investigate" role="tabpanel">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4 bg-light rounded">
                    <div class="row align-items-end g-3">
                        <div class="col-md-6 position-relative">
                            <label class="form-label fw-bold">Search Client (Name or UCC Code)</label>
                            <input type="text" id="clientCodeCheck" class="form-control" placeholder="Search by name or UCC..." autocomplete="off">
                            <div id="autocomplete-list-check" class="shadow-sm rounded border" style="display:none; position:absolute; z-index:1000; background:#fff; width:calc(100% - 10px); max-height:200px; overflow-y:auto;"></div>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100 fw-bold" onclick="fetchClientTransactions()"><i class="fas fa-search me-2"></i>Fetch Client History</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <!-- Filter Controls injected by JS -->
                    <div id="filterControls" class="mb-4 p-3 bg-white border rounded shadow-sm" style="display:none;">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Filter Displayed Transactions</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="small fw-semibold text-muted">Fund Name</label>
                                <select id="dtFundFilter" class="form-select form-select-sm"><option value="">All Funds</option></select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-semibold text-muted">Transaction Type</label>
                                <select id="dtTypeFilter" class="form-select form-select-sm">
                                    <option value="">All Types</option>
                                    <option value="Purchase">Purchase</option>
                                    <option value="Redemption">Redemption</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-semibold text-muted">From Date</label>
                                <input type="date" id="dtFromDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-semibold text-muted">To Date</label>
                                <input type="date" id="dtToDate" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>

                    <div id="clientTrxContainer">
                        <div class="text-center text-muted p-5"><i class="fas fa-search fa-3x mb-3 text-light"></i><br>Search a client to view their financial transactions history.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let clientTable;

// Autocomplete logic
$('#clientCodeCheck').on('input', function() {
    let val = $(this).val().toLowerCase();
    let list = $('#autocomplete-list-check');
    if (val.length < 2) { list.hide(); return; }

    $.get('getUCClistwithname.php', function(data) {
        if (typeof data === 'string') data = JSON.parse(data);
        let matches = data.filter(e => (e.UCC && e.UCC.toLowerCase().includes(val)) || (e.name && e.name.toLowerCase().includes(val)));
        list.empty();
        if (matches.length > 0) {
            matches.slice(0, 15).forEach(e => {
                list.append(`<div class="p-2 border-bottom text-dark" style="cursor:pointer;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'" onclick="$('#clientCodeCheck').val('${e.UCC}'); $('#autocomplete-list-check').hide();"><strong>${e.UCC}</strong> - ${e.name}</div>`);
            });
            list.show();
        } else { list.hide(); }
    });
});

$(document).on('click', function(e) { if (!$(e.target).closest('#clientCodeCheck, #autocomplete-list-check').length) $('#autocomplete-list-check').hide(); });

function fetchClientTransactions() {
    const ucc = $('#clientCodeCheck').val();
    if (!ucc) { alert('Please enter or select a UCC.'); return; }

    $('#clientTrxContainer').html('<div class="text-center p-4"><div class="spinner-border text-primary"></div><p class="mt-2">Fetching transactions...</p></div>');
    $('#filterControls').hide();

    $.ajax({ url: 'tool_mailback_data_check.php', type: 'POST', data: { action: 'fetch_client_trx', ucc: ucc }, dataType: 'json',
        success: function(res) {
            if (res.success && res.data.length > 0) {
                let html = '<div class="table-responsive"><table id="clientTrxTable" class="table table-striped table-bordered align-middle" style="width:100%; font-size:12px;">';
                html += '<thead class="table-dark"><tr><th>Date</th><th>Fund Name</th><th>AMC Name</th><th>ISIN</th><th>Folio</th><th>Type</th><th>Amount</th><th>Units</th><th>NAV/Price</th></tr></thead><tbody>';
                let uniqueFunds = new Set();
                res.data.forEach(t => {
                    let type = t.transaction_flag === 'P' ? '<span class="badge bg-success">Purchase</span>' : (t.transaction_flag === 'R' ? '<span class="badge bg-danger">Redemption</span>' : 'Other');
                    let fund = t.fund_name || 'N/A';
                    if (fund !== 'N/A') uniqueFunds.add(fund);
                    let truncFund = fund.length > 30 ? fund.substring(0, 30) + '...' : fund;
                    let amc = t.amc_name || 'N/A';
                    let truncAmc = amc.length > 20 ? amc.substring(0, 20) + '...' : amc;
                    html += `<tr><td>${t.transaction_date || ''}</td><td title="${fund}" class="fw-semibold">${truncFund}</td><td title="${amc}">${truncAmc}</td><td>${t.isin || ''}</td><td>${t.folio_number || ''}</td><td>${type}</td><td class="text-end fw-bold">${parseFloat(t.amount || 0).toFixed(2)}</td><td class="text-end">${parseFloat(t.units || 0).toFixed(4)}</td><td class="text-end">${parseFloat(t.nav || t.price || 0).toFixed(4)}</td></tr>`;
                });
                html += '</tbody></table></div>';
                $('#clientTrxContainer').html(html);
                let fundSelect = $('#dtFundFilter').empty().append('<option value="">All Funds</option>');
                Array.from(uniqueFunds).sort().forEach(f => fundSelect.append(`<option value="${f.substring(0, 30)}">${f}</option>`));
                $('#filterControls').fadeIn();
                if ($.fn.DataTable.isDataTable('#clientTrxTable')) $('#clientTrxTable').DataTable().destroy();
                clientTable = $('#clientTrxTable').DataTable({ pageLength: 25, order: [[0, 'desc']] });
            } else { $('#clientTrxContainer').html('<div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i>No valid financial transactions found for this client code.</div>'); }
        },
        error: function() { $('#clientTrxContainer').html('<div class="alert alert-danger">Network error occurred.</div>'); }
    });
}

// Custom DataTables Filter Registration
$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    if (settings.nTable.id !== 'clientTrxTable') return true;
    let fundFilter = $('#dtFundFilter').val(), typeFilter = $('#dtTypeFilter').val(), minDate = $('#dtFromDate').val(), maxDate = $('#dtToDate').val();
    let date = data[0], fund = data[1], type = data[5];
    if (fundFilter && fund.indexOf(fundFilter) === -1) return false;
    if (typeFilter && type.indexOf(typeFilter) === -1) return false;
    if (minDate && date < minDate) return false;
    if (maxDate && date > maxDate) return false;
    return true;
});
$(document).on('change', '#dtFundFilter, #dtTypeFilter, #dtFromDate, #dtToDate', function() { if (clientTable) clientTable.draw(); });
</script>