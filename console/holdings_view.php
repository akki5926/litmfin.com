<?php
if(!isset($_SESSION)){ session_start(); }
if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
    http_response_code(403);
    exit("Unauthorized access.");
}

require_once('../admin/database.php');
$db = new MYSQLDB();

// Handle AJAX Requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_holdings') {
        $ucc = isset($_GET['ucc']) ? preg_replace('/[^a-zA-Z0-9_.-]/', '', $_GET['ucc']) : '';
        $holdings = [];
        
        if ($ucc) {
            // Get PAN and basic details from vw_client_profiles
            $query_pan = "SELECT PRIMARY_HOLDER_PAN, PRIMARY_HOLDER_NAME, EMAIL, INDIAN_MOBILE_NO FROM vw_client_profiles WHERE CLIENT_CODE = '$ucc' LIMIT 1";
            $res_pan = $db->query($query_pan);
            $pan = '';
            $client_details = null;
            if ($row_pan = $res_pan->fetch_assoc()) {
                $pan = $row_pan['PRIMARY_HOLDER_PAN'];
                $client_details = [
                    'name' => $row_pan['PRIMARY_HOLDER_NAME'],
                    'email' => $row_pan['EMAIL'],
                    'mobile' => $row_pan['INDIAN_MOBILE_NO']
                ];
            } else {
                // Fallback to tb_users if not found
                $query_pan2 = "SELECT PRIMARY_HOLDER_PAN FROM tb_users WHERE CLIENT_CODE = '$ucc' LIMIT 1";
                $res_pan2 = $db->query($query_pan2);
                if ($row_pan2 = $res_pan2->fetch_assoc()) {
                    $pan = $row_pan2['PRIMARY_HOLDER_PAN'];
                }
            }
            
            if ($pan) {
                // Fetch holdings from tb_transaction_mailback to include both CAMS and KFintech
                $query_holdings = "
                    SELECT 
                        folio_number as folio,
                        amc_code as AMC_CODE,
                        isin,
                        rta as kra_name,
                        SUM(CASE WHEN transaction_flag IN ('P', '+') THEN units ELSE -units END) as units,
                        SUM(CASE WHEN transaction_flag IN ('P', '+') THEN amount ELSE 0 END) as invested_amt_total,
                        SUM(CASE WHEN transaction_flag IN ('P', '+') THEN units ELSE 0 END) as invested_units_total
                    FROM tb_transaction_mailback
                    WHERE pan1 = '$pan'
                    GROUP BY folio_number, amc_code, isin, rta
                    HAVING units > 0.1
                ";
                
                $res_holdings = $db->query($query_holdings);
                while($row = $res_holdings->fetch_assoc()) {
                    // Get proper scheme name
                    $isin = $row['isin'];
                    $row['holding_name'] = $db->getFundNamebyISIN($isin);
                    if (empty($row['holding_name'])) {
                        $row['holding_name'] = "Unknown Scheme (ISIN: $isin)";
                    }

                    // Fallback for KRA name if not found in tb_transaction_mailback
                    if (empty($row['kra_name'])) {
                        if (is_numeric(trim($row['AMC_CODE']))) {
                            $row['kra_name'] = 'KFINTECH';
                        } else {
                            $row['kra_name'] = 'CAMS';
                        }
                    }
                    
                    // Latest NAV
                    $navData = $db->getFundNAVByISIN($isin);
                    $latest_nav = ($navData && isset($navData['nav'])) ? floatval($navData['nav']) : 0;
                    $row['latest_nav'] = $latest_nav;
                    $row['nav_date'] = ($navData && isset($navData['nav_date'])) ? $navData['nav_date'] : '';
                    
                    $units = floatval($row['units']);
                    $row['amount'] = $units * $latest_nav; // Current Value
                    
                    // Purchased Avg NAV
                    $invested_amt_total = floatval($row['invested_amt_total']);
                    $invested_units_total = floatval($row['invested_units_total']);
                    
                    $row['avg_nav'] = 0;
                    if ($invested_units_total > 0) {
                        $row['avg_nav'] = round($invested_amt_total / $invested_units_total, 4);
                    }
                    
                    $holdings[] = $row;
                }
                
                // Sort holdings alphabetically by scheme name
                usort($holdings, function($a, $b) {
                    return strcmp($a['holding_name'], $b['holding_name']);
                });
            }
        }
        
        echo json_encode(['holdings' => $holdings, 'pan' => $pan, 'client_details' => $client_details]);
        exit;
    }
}
?>

<style>
    .form-group { position:relative; }
    .form-group input { width:100%; padding:11px 14px; border:2px solid #e0e0e0; border-radius:12px; font-size:14px; transition:all 0.3s; background:#fafafa; color:#333; }
    .form-group input:focus { outline:none; border-color:#667eea; background:white; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }
    #autocomplete-list { position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #e0e0e0; border-radius:12px; max-height:220px; overflow-y:auto; z-index:1000; box-shadow:0 10px 25px rgba(0,0,0,0.1); }
    #autocomplete-list div { padding:11px 14px; cursor:pointer; font-size:13px; color:#333; transition:background 0.2s; }
    #autocomplete-list div:hover { background:#f5f0ff; color:#667eea; }

    .holding-card {
        background: #fff;
        border: 1px solid #e8eaef;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        transition: transform 0.2s;
    }
    .holding-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    }
    .kra-badge-cams {
        background-color: #e3f2fd;
        color: #0d6efd;
    }
    .kra-badge-kfintech {
        background-color: #f8d7da;
        color: #dc3545;
    }
    
    .btn { padding:11px 26px; border:none; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.3s; white-space:nowrap; }
    .btn-orange { background:#fd7101; color:white; }
    .btn-orange:hover { background:#e66500; transform:translateY(-2px); box-shadow:0 5px 15px rgba(253,113,1,0.35); }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold mb-1" style="color: #1e2340;">Client Holdings</h4>
            <p class="text-muted small mb-0">Search by UCC or Name to view client holdings and KRA names.</p>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 8px;">
        <div class="card-body p-3">
            <form onsubmit="fetchHoldings(); return false;">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-grow-1" style="max-width: 600px;">
                        <label class="form-label text-muted fw-bold small me-2 mb-0" style="white-space: nowrap;">UCC / Name:</label>
                        <div class="form-group flex-grow-1 me-2 mb-0">
                            <input type="text" id="custUCC" placeholder="Enter UCC or Name" autocomplete="off" style="padding: 8px 12px; border-radius: 8px;">
                            <div id="autocomplete-list" style="display:none; top: 100%; border-radius: 8px;"></div>
                        </div>
                        <button type="submit" class="btn btn-orange btn-sm" style="padding: 8px 16px; border-radius: 8px;">Fetch</button>
                    </div>
                    
                    <div class="d-flex align-items-center justify-content-end mt-2 mt-md-0">
                        <div id="client-details-display" class="d-none me-3" style="font-size: 0.85rem; color: #555;"></div>
                        <div id="pan-display" class="badge bg-secondary p-1 px-2 fs-6 d-none">
                            PAN: <span id="pan-text"></span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="loading-spinner" class="text-center d-none py-5">
        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
        <p class="text-muted mt-2">Fetching holdings...</p>
    </div>

    <div id="holdings-container" class="row g-4">
        <!-- Holdings will be rendered here -->
    </div>
    
    <div id="no-data-msg" class="text-center py-5 d-none">
        <i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
        <h5 class="text-muted">No holdings found for this client.</h5>
    </div>
</div>

<script>
function fetchCustomerUCCs(input) {
    var val = input.value.toLowerCase();
    var list = document.getElementById("autocomplete-list");
    list.innerHTML = "";
    if (val === "") { list.style.display = "none"; return; }
    fetch("../admin/getUCClistwithname.php")
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var matching = data.filter(function(e) {
                return (e.UCC && e.UCC.toLowerCase().indexOf(val) !== -1) || (e.name && e.name.toLowerCase().indexOf(val) !== -1);
            });
            for (var i = 0; i < Math.min(matching.length, 15); i++) {
                var e = matching[i];
                var opt = document.createElement("div");
                opt.innerHTML = e.UCC + " - " + e.name;
                opt.addEventListener("click", function(ucc) { 
                    return function() { 
                        input.value = ucc; 
                        list.innerHTML = ""; 
                        list.style.display = "none";
                        // Optionally trigger fetch
                        fetchHoldings();
                    }; 
                }(e.UCC));
                list.appendChild(opt);
            }
            list.style.display = matching.length > 0 ? "block" : "none";
        })
        .catch(function(err) { console.error(err); });
}

var input = document.getElementById("custUCC");
input.addEventListener("input", function() { fetchCustomerUCCs(this); });
document.addEventListener("click", function(e) {
    if (e.target !== input) {
        var list = document.getElementById("autocomplete-list");
        if(list) list.style.display = "none";
    }
});

window.currentHoldingsData = [];
window.currentSort = { col: '', asc: true };

function fetchHoldings() {
    var ucc = document.getElementById('custUCC').value.trim();
    if (!ucc) return;

    $('#holdings-container').empty();
    $('#no-data-msg').addClass('d-none');
    $('#pan-display').addClass('d-none');
    $('#loading-spinner').removeClass('d-none');

    $.ajax({
        url: 'holdings_view.php',
        type: 'GET',
        data: { action: 'get_holdings', ucc: ucc },
        dataType: 'json',
        success: function(response) {
            $('#loading-spinner').addClass('d-none');
            
            if (response.pan) {
                $('#pan-text').text(response.pan);
                $('#pan-display').removeClass('d-none');
            }
            if (response.client_details) {
                let det = response.client_details;
                let emailStr = det.email ? ` | 📧 ${det.email}` : '';
                let mobStr = det.mobile ? ` | 📱 ${det.mobile}` : '';
                $('#client-details-display').html(`<strong>${det.name}</strong>${emailStr}${mobStr}`).removeClass('d-none');
            } else {
                $('#client-details-display').addClass('d-none');
            }

            if (response.holdings && response.holdings.length > 0) {
                window.currentHoldingsData = response.holdings.map(h => {
                    let units = parseFloat(h.units);
                    let avgNav = parseFloat(h.avg_nav);
                    let latestNav = parseFloat(h.latest_nav);
                    let amount = parseFloat(h.amount);
                    let investedAmt = (units * avgNav > 0) ? (units * avgNav) : 0;
                    let absReturn = amount - investedAmt;
                    let retPercent = investedAmt > 0 ? (absReturn / investedAmt) * 100 : 0;
                    
                    let navDateFormatted = h.nav_date || '';
                    if (navDateFormatted && navDateFormatted.includes('-')) {
                        let parts = navDateFormatted.split(' ')[0].split('-');
                        if (parts.length === 3) {
                            navDateFormatted = parts[2] + '-' + parts[1] + '-' + parts[0];
                        }
                    }
                    
                    return {
                        ...h,
                        units: units,
                        avg_nav: avgNav,
                        latest_nav: latestNav,
                        amount: amount,
                        investedAmt: investedAmt,
                        absReturn: absReturn,
                        retPercent: retPercent,
                        nav_date_fmt: navDateFormatted
                    };
                });
                renderHoldingsTable();
            } else {
                $('#no-data-msg').removeClass('d-none');
            }
        },
        error: function() {
            $('#loading-spinner').addClass('d-none');
            alert('An error occurred while fetching holdings.');
        }
    });
}

function sortHoldings(col) {
    if (window.currentSort.col === col) {
        window.currentSort.asc = !window.currentSort.asc;
    } else {
        window.currentSort.col = col;
        window.currentSort.asc = true;
    }
    
    window.currentHoldingsData.sort((a, b) => {
        let valA = a[col];
        let valB = b[col];
        
        if (typeof valA === 'string') valA = valA.toLowerCase();
        if (typeof valB === 'string') valB = valB.toLowerCase();
        
        if (valA < valB) return window.currentSort.asc ? -1 : 1;
        if (valA > valB) return window.currentSort.asc ? 1 : -1;
        return 0;
    });
    
    renderHoldingsTable();
}

function getSortIcon(col) {
    if (window.currentSort.col !== col) return '<span class="text-muted ms-1" style="opacity:0.3; font-size: 0.8rem;">↕</span>';
    return window.currentSort.asc ? '<span class="ms-1" style="font-size: 0.8rem;">↑</span>' : '<span class="ms-1" style="font-size: 0.8rem;">↓</span>';
}

function renderHoldingsTable() {
    $('#holdings-container').removeClass('row g-4');
    let html = `
    <div class="table-responsive">
        <style>
            th.sortable { cursor: pointer; user-select: none; transition: background 0.2s; white-space: nowrap; }
            th.sortable:hover { background-color: #f1f3f5 !important; }
        </style>
        <table class="table table-hover table-bordered align-middle" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); font-size: 0.85rem;">
            <thead class="table-light">
                <tr>
                    <th class="text-center text-muted" style="width: 50px;">Sl.</th>
                    <th class="sortable" onclick="sortHoldings('holding_name')">Scheme Name ${getSortIcon('holding_name')}</th>
                    <th class="sortable" onclick="sortHoldings('folio')">Folio & KRA ${getSortIcon('folio')}</th>
                    <th class="sortable text-end" onclick="sortHoldings('units')">Units ${getSortIcon('units')}</th>
                    <th class="sortable text-end" onclick="sortHoldings('avg_nav')">Avg NAV ${getSortIcon('avg_nav')}</th>
                    <th class="sortable text-end" onclick="sortHoldings('latest_nav')">Latest NAV ${getSortIcon('latest_nav')}</th>
                    <th class="sortable text-end" onclick="sortHoldings('investedAmt')">Invested Amt ${getSortIcon('investedAmt')}</th>
                    <th class="sortable text-end" onclick="sortHoldings('amount')">Current Value ${getSortIcon('amount')}</th>
                    <th class="sortable text-end" onclick="sortHoldings('absReturn')">Abs. Return ${getSortIcon('absReturn')}</th>
                </tr>
            </thead>
            <tbody>
    `;
    let totalInvested = 0;
    let totalCurrent = 0;

    window.currentHoldingsData.forEach(function(holding, index) {
        let kraClass = holding.kra_name.toUpperCase().includes('KFIN') ? 'kra-badge-kfintech' : 'kra-badge-cams';
        
        totalInvested += holding.investedAmt;
        totalCurrent += holding.amount;
        
        let retColor = holding.absReturn >= 0 ? 'text-success' : 'text-danger';
        let retSign = holding.absReturn >= 0 ? '+' : '';
        
        let retHtml = '-';
        if (holding.investedAmt > 0) {
            retHtml = `
                ${holding.absReturn.toLocaleString('en-IN', { style: 'currency', currency: 'INR' })}
                <div class="small">${retSign}${holding.retPercent.toFixed(2)}%</div>
            `;
        }
        
        html += `
            <tr>
                <td class="text-center text-muted small">${index + 1}</td>
                <td class="fw-bold" style="color: #1e2340; max-width: 300px; white-space: normal; font-size: 0.8rem;">${holding.holding_name}</td>
                <td>
                    <div><span class="text-muted small">Folio:</span> ${holding.folio}</div>
                    <span class="badge ${kraClass} rounded-pill px-2 py-0 mt-1" style="font-size:0.7rem;">${holding.kra_name}</span>
                </td>
                <td class="text-end">${holding.units.toFixed(3)}</td>
                <td class="text-end" style="color: #6a1b9a;">${holding.avg_nav > 0 ? holding.avg_nav.toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) : '-'}</td>
                <td class="text-end text-success">
                    ${holding.latest_nav > 0 ? holding.latest_nav.toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) : '-'}
                    ${holding.nav_date_fmt ? `<div class="small text-muted" style="font-size:0.7rem;">${holding.nav_date_fmt}</div>` : ''}
                </td>
                <td class="text-end fw-semibold">${holding.investedAmt > 0 ? holding.investedAmt.toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) : '-'}</td>
                <td class="text-end fw-bold">${holding.amount > 0 ? holding.amount.toLocaleString('en-IN', { style: 'currency', currency: 'INR' }) : '-'}</td>
                <td class="text-end fw-bold ${holding.investedAmt > 0 ? retColor : 'text-muted'}">
                    ${retHtml}
                </td>
            </tr>
        `;
    });
    
    let totalAbsReturn = totalCurrent - totalInvested;
    let totalRetPercent = totalInvested > 0 ? (totalAbsReturn / totalInvested) * 100 : 0;
    let totalRetColor = totalAbsReturn >= 0 ? 'text-success' : 'text-danger';
    let totalRetSign = totalAbsReturn >= 0 ? '+' : '';

    html += `
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="6" class="text-end">Total</td>
                    <td class="text-end fs-6">${totalInvested.toLocaleString('en-IN', { style: 'currency', currency: 'INR' })}</td>
                    <td class="text-end fs-6 text-primary">${totalCurrent.toLocaleString('en-IN', { style: 'currency', currency: 'INR' })}</td>
                    <td class="text-end fs-6 ${totalRetColor}">
                        ${totalAbsReturn.toLocaleString('en-IN', { style: 'currency', currency: 'INR' })}
                        <div class="small fw-semibold">${totalRetSign}${totalRetPercent.toFixed(2)}%</div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    `;
    $('#holdings-container').html(html);
}
</script>
