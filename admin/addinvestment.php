<?php
require_once('auth_guard.php');

if(!isset($_SESSION)) { session_start(); } 
if(!isset($_SESSION['user'])) { header("Location: index.php"); }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment Management System</title>
    <style>
        .container { max-width: 1400px; margin: 0 auto; }

        .header-card { background:white; border-radius:20px; padding:25px 30px; margin-bottom:25px; box-shadow:0 10px 40px rgba(0,0,0,0.1); }
        .header-card h1 { color:#333; font-size:24px; margin-bottom:5px; }
        .header-card p { color:#666; font-size:14px; }

        .selection-panel { background:white; border-radius:20px; padding:25px; margin-bottom:25px; box-shadow:0 10px 40px rgba(0,0,0,0.1); }
        .form-row { display:flex; flex-wrap:wrap; gap:20px; align-items:flex-end; }
        .form-group { flex:1; min-width:200px; position:relative; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px; }
        .form-group input, .form-group select { width:100%; padding:12px 15px; border:2px solid #e0e0e0; border-radius:12px; font-size:14px; transition:all 0.3s; background:#fafafa; color:#333; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:#667eea; background:white; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }

        .autocomplete-wrapper { position:relative; }
        #autocomplete-list { position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #e0e0e0; border-radius:12px; max-height:250px; overflow-y:auto; z-index:1000; box-shadow:0 10px 25px rgba(0,0,0,0.1); }
        #autocomplete-list div { padding:12px 15px; cursor:pointer; transition:background 0.2s; font-size:14px; }
        #autocomplete-list div:hover { background:#f5f5f5; }

        .btn { padding:12px 28px; border:none; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.3s; background:#667eea; color:white; }
        .btn:hover { background:#5a67d8; transform:translateY(-2px); box-shadow:0 5px 15px rgba(102,126,234,0.3); }
        .btn-orange { background:#fd7101; }
        .btn-orange:hover { background:#e66500; }
        .status { padding:10px 20px; border-radius:12px; font-size:13px; font-weight:500; background:#f8f9fa; }

        .investment-section { background:white; border-radius:20px; padding:25px; margin-bottom:25px; box-shadow:0 10px 40px rgba(0,0,0,0.1); display:none; }
        .section-header { border-bottom:3px solid #667eea; padding-bottom:15px; margin-bottom:25px; }
        .section-header h3 { color:#333; font-size:20px; }

        .form-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px; margin-bottom:25px; }
        .input-field { display:flex; flex-direction:column; }
        .input-field label { font-size:13px; font-weight:600; color:#555; margin-bottom:8px; }
        .input-field input, .input-field select { padding:10px 12px; border:2px solid #e0e0e0; border-radius:10px; font-size:14px; transition:all 0.3s; background:#fafafa; }
        .input-field input:focus, .input-field select:focus { outline:none; border-color:#667eea; background:white; }

        .data-controls { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin:20px 0; }
        .entries-selector { display:flex; align-items:center; gap:8px; font-size:13px; color:#555; }
        .entries-selector select { padding:6px 10px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px; background:#fafafa; cursor:pointer; }
        .search-box { display:flex; align-items:center; gap:8px; font-size:13px; color:#555; }
        .search-box input { padding:7px 12px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px; width:220px; background:#fafafa; }

        .data-table-container { overflow-x:auto; border-radius:14px; border:1px solid #e0e0e0; background:white; }
        .investment-table { width:100%; border-collapse:collapse; font-size:13px; }
        .investment-table thead { background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .investment-table th { padding:14px 16px; text-align:left; font-weight:600; color:white; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        .investment-table td { padding:12px 16px; border-bottom:1px solid #f0f0f0; color:#444; }
        .investment-table tbody tr:hover { background:#faf5ff; }
        .investment-table tbody tr:nth-child(even) { background:#fafafa; }

        .pagination-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:16px; }
        .entries-info { font-size:13px; color:#666; }
        .pagination-buttons { display:flex; gap:6px; align-items:center; }
        .page-button { padding:6px 12px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; background:white; color:#555; transition:all 0.2s; }
        .page-button:hover:not(:disabled) { border-color:#667eea; color:#667eea; }
        .page-button.active { background:#667eea; border-color:#667eea; color:white; }
        .page-button:disabled { opacity:0.4; cursor:not-allowed; }

        .action-btn { color:#667eea; text-decoration:none; font-weight:600; font-size:12px; padding:5px 12px; border-radius:6px; background:#f0f0ff; display:inline-block; transition:all 0.2s; cursor:pointer; margin-right:5px; }
        .action-btn:hover { background:#667eea; color:white; }
        .delete-btn { color:#e53e3e; background:#fff0f0; }
        .delete-btn:hover { background:#e53e3e; color:white; }
        .no-data { text-align:center; padding:40px; color:#999; font-size:14px; }

        @media (max-width:768px) { body{padding:10px;} .form-row{flex-direction:column;} .form-group{width:100%;} .form-grid{grid-template-columns:1fr;} .data-controls,.pagination-footer{flex-direction:column;align-items:flex-start;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header-card">
        <h1>Investment Management System</h1>
        <p>Add, edit, and manage customer investments</p>
    </div>

    <div class="selection-panel">
        <form onsubmit="showDiv(); return false;">
            <div class="form-row">
                <div class="form-group">
                    <label>Customer UCC</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" name="custUCC" id="custUCC" placeholder="Enter UCC or Name" autocomplete="off">
                        <div id="autocomplete-list"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Investment Type</label>
                    <select id="investment_type" name="investment_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="bond_div">Bond Investment</option>
                        <option value="pms_div">PMS Investment</option>
                        <option value="aif_div">AIF Investment</option>
                        <option value="fd_div">FD Investment</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-orange">Select Investment</button>
                </div>
                <div class="form-group">
                    <div id="status" class="status"></div>
                </div>
            </div>
        </form>
    </div>

    <!-- BOND Investment Section -->
    <div id="bond_div" class="investment-section">
        <div class="section-header"><h3 id="bond_formhead">Bond Investment</h3></div>
        <form id="add_bond" onsubmit="addBond(); return false;">
            <div class="form-grid">
                <div class="input-field"><label>Asset Class</label><select id="bond_asset_class" onchange="updateBondNames()"><option value="">Select</option><option value="Debt">Debt</option><option value="Equity">Equity</option><option value="Gold">Gold</option><option value="Hybrid">Hybrid</option></select></div>
                <div class="input-field"><label>Bond Name</label><select id="bond_name" onchange="updateBondDetails()"></select></div>
                <div class="input-field"><label>Face Value</label><input type="text" id="bond_face_value" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Interest Rate (%)</label><input type="text" id="bond_interest_rate" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Payout Frequency</label><select id="bond_payout_frequency"><option value="">Select</option><option value="Monthly">Monthly</option><option value="Quarterly">Quarterly</option><option value="Semi-annually">Semi-annually</option><option value="Annually">Annually</option><option value="On-maturity">On-maturity</option></select></div>
                <div class="input-field"><label>Maturity Date</label><input type="date" id="bond_maturity_date"></div>
                <div class="input-field"><label>Transaction Type</label><select id="bond_transaction_type"><option value="">Select</option><option value="P">Buy</option><option value="R">Sell</option></select></div>
                <div class="input-field"><label>Transaction Date</label><input type="date" id="bond_transaction_date" required></div>
                <div class="input-field"><label>Transaction Amount</label><input type="text" id="bond_transaction_amount" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Units</label><input type="text" id="bond_units" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>First Payout Date</label><input type="date" id="bond_payout_date"></div>
                <div class="input-field"><label>Current NAV</label><input type="text" id="bond_current_rate" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Current NAV Date</label><input type="date" id="bond_current_date"></div>
            </div>
            <input type="hidden" id="bond_action" value="add"><input type="hidden" id="bond_slno" value="0">
            <button type="submit" class="btn btn-orange" name="add_bond">Add Bond</button>
        </form>

        <div class="data-controls">
            <div class="entries-selector">Show <select id="bond_page_size" onchange="bondChangePageSize()"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select> entries</div>
            <div class="search-box">Search: <input type="text" id="bond_search" placeholder="Search..." onkeyup="bondSearch()"></div>
        </div>
        <div class="data-table-container">
            <table class="investment-table"><thead><tr><th>Bond Name</th><th>Asset Class</th><th>Face Value</th><th>Interest %</th><th>Payout</th><th>Txn Type</th><th>Txn Date</th><th>Txn Amt</th><th>Units</th><th>First Payout</th><th>Current NAV</th><th>NAV Date</th><th>Maturity</th><th>Actions</th></tr></thead><tbody id="bondTableBody"></tbody></table>
        </div>
        <div class="pagination-footer"><div class="entries-info" id="bond_entry_info">Showing 0 to 0 of 0 entries</div><div class="pagination-buttons" id="bond_pagination"></div></div>
    </div>

    <!-- AIF Investment Section -->
    <div id="aif_div" class="investment-section">
        <div class="section-header"><h3 id="aif_formhead">AIF Investment</h3></div>
        <form id="add_aif" onsubmit="addAif(); return false;">
            <div class="form-grid">
                <div class="input-field"><label>Asset Class</label><select id="aif_asset_class"><option value="">Select</option><option value="Debt">Debt</option><option value="Equity">Equity</option><option value="Gold">Gold</option><option value="Hybrid">Hybrid</option></select></div>
                <div class="input-field"><label>AIF Name</label><input type="text" id="aif_name" required></div>
                <div class="input-field"><label>Issuer Name</label><input type="text" id="aif_issuer" required></div>
                <div class="input-field"><label>Transaction Type</label><select id="aif_transaction_type"><option value="">Select</option><option value="P">Buy</option><option value="R">Sell</option></select></div>
                <div class="input-field"><label>Transaction Date</label><input type="date" id="aif_transaction_date" required></div>
                <div class="input-field"><label>Transaction Amount</label><input type="text" id="aif_transaction_amount" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Units</label><input type="text" id="aif_units" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
            </div>
            <input type="hidden" id="aif_action" value="add"><input type="hidden" id="aif_slno" value="0">
            <button type="submit" class="btn btn-orange" name="add_aif">Add AIF</button>
        </form>
        <div class="data-controls">
            <div class="entries-selector">Show <select id="aif_page_size" onchange="aifChangePageSize()"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select> entries</div>
            <div class="search-box">Search: <input type="text" id="aif_search" placeholder="Search..." onkeyup="aifSearch()"></div>
        </div>
        <div class="data-table-container">
            <table class="investment-table"><thead><tr><th>AIF Name</th><th>Asset Class</th><th>Issuer</th><th>Txn Type</th><th>Txn Date</th><th>Txn Amt</th><th>Units</th><th>Actions</th></tr></thead><tbody id="aifTableBody"></tbody></table>
        </div>
        <div class="pagination-footer"><div class="entries-info" id="aif_entry_info">Showing 0 to 0 of 0 entries</div><div class="pagination-buttons" id="aif_pagination"></div></div>
    </div>

    <!-- PMS Investment Section -->
    <div id="pms_div" class="investment-section">
        <div class="section-header"><h3 id="pms_formhead">PMS Investment</h3></div>
        <form id="add_pms" onsubmit="addPms(); return false;">
            <div class="form-grid">
                <div class="input-field"><label>Asset Class</label><select id="pms_asset_class"><option value="">Select</option><option value="Debt">Debt</option><option value="Equity">Equity</option><option value="Gold">Gold</option><option value="Hybrid">Hybrid</option></select></div>
                <div class="input-field"><label>PMS Name</label><input type="text" id="pms_name" required></div>
                <div class="input-field"><label>Issuer Name</label><input type="text" id="pms_issuer" required></div>
                <div class="input-field"><label>Transaction Type</label><select id="pms_transaction_type" required><option value="">Select</option><option value="P">New Purchase</option><option value="TOPUP">Top Up Purchase</option><option value="FULL">Full Redemption</option><option value="PARTIAL">Part Sell</option></select></div>
                <div class="input-field"><label>Transaction Date</label><input type="date" id="pms_transaction_date" required></div>
                <div class="input-field"><label>Transaction Amount</label><input type="text" id="pms_transaction_amount" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
            </div>
            <input type="hidden" id="pms_action" value="add"><input type="hidden" id="pms_slno" value="0">
            <button type="submit" class="btn btn-orange" name="add_pms">Add PMS</button>
        </form>
        <div class="data-controls">
            <div class="entries-selector">Show <select id="pms_page_size" onchange="pmsChangePageSize()"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select> entries</div>
            <div class="search-box">Search: <input type="text" id="pms_search" placeholder="Search..." onkeyup="pmsSearch()"></div>
        </div>
        <div class="data-table-container">
            <table class="investment-table"><thead><tr><th>PMS Name</th><th>Asset Class</th><th>Issuer</th><th>Txn Type</th><th>Txn Date</th><th>Txn Amt</th><th>Actions</th></tr></thead><tbody id="pmsTableBody"></tbody></table>
        </div>
        <div class="pagination-footer"><div class="entries-info" id="pms_entry_info">Showing 0 to 0 of 0 entries</div><div class="pagination-buttons" id="pms_pagination"></div></div>
    </div>

    <!-- FD Investment Section -->
    <div id="fd_div" class="investment-section">
        <div class="section-header"><h3 id="fd_formhead">FD Investment</h3></div>
        <form id="add_fd" onsubmit="addFd(); return false;">
            <div class="form-grid">
                <div class="input-field"><label>FD Name</label><input type="text" id="fd_name" required></div>
                <div class="input-field"><label>Asset Class</label><select id="fd_asset_class"><option value="">Select</option><option value="Debt">Debt</option><option value="Equity">Equity</option><option value="Gold">Gold</option><option value="Hybrid">Hybrid</option></select></div>
                <div class="input-field"><label>Cost Value</label><input type="text" id="fd_transaction_amount" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Transaction Date</label><input type="date" id="fd_transaction_date" required></div>
                <div class="input-field"><label>Interest Payout</label><select id="fd_payout_frequency"><option value="">Select</option><option value="Monthly">Monthly</option><option value="Quarterly">Quarterly</option><option value="Semi-annually">Semi-annually</option><option value="Annually">Annually</option><option value="On-maturity">On-maturity</option></select></div>
                <div class="input-field"><label>Payout Received</label><input type="text" id="fd_payout_recd" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Current Value</label><input type="text" id="fd_current_value" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Current Date</label><input type="date" id="fd_current_date"></div>
                <div class="input-field"><label>CAGR (%)</label><input type="text" id="fd_cagr" oninput="this.value=this.value.replace(/[^0-9.]/g,'');"></div>
                <div class="input-field"><label>Maturity Date</label><input type="date" id="fd_maturity_date" required></div>
            </div>
            <input type="hidden" id="fd_action" value="add"><input type="hidden" id="fd_slno" value="0">
            <button type="submit" class="btn btn-orange" name="add_fd">Add FD</button>
        </form>
        <div class="data-controls">
            <div class="entries-selector">Show <select id="fd_page_size" onchange="fdChangePageSize()"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select> entries</div>
            <div class="search-box">Search: <input type="text" id="fd_search" placeholder="Search..." onkeyup="fdSearch()"></div>
        </div>
        <div class="data-table-container">
            <table class="investment-table"><thead><tr><th>FD Name</th><th>Asset Class</th><th>Cost Value</th><th>Txn Date</th><th>Interest Payout</th><th>Payout Received</th><th>Current Value</th><th>Current Date</th><th>CAGR</th><th>Maturity</th><th>Actions</th></tr></thead><tbody id="fdTableBody"></tbody></table>
        </div>
        <div class="pagination-footer"><div class="entries-info" id="fd_entry_info">Showing 0 to 0 of 0 entries</div><div class="pagination-buttons" id="fd_pagination"></div></div>
    </div>
</div>

<script>
// ==================== BOND SECTION ====================
var bondAllData = [], bondFiltered = [], bondCurrentPage = 1, bondPageSize = 10;

function bondSearch() {
    var q = document.getElementById('bond_search').value.toLowerCase();
    bondFiltered = bondAllData.filter(function(r) {
        return (r.investment_name || '').toLowerCase().indexOf(q) !== -1 ||
               (r.asset_class || '').toLowerCase().indexOf(q) !== -1 ||
               String(r.face_value || '').indexOf(q) !== -1 ||
               String(r.interest_rate || '').indexOf(q) !== -1;
    });
    bondCurrentPage = 1;
    bondRender();
}

function bondChangePageSize() {
    bondPageSize = parseInt(document.getElementById('bond_page_size').value);
    bondCurrentPage = 1;
    bondRender();
}

function bondRender() {
    var tbody = document.getElementById('bondTableBody');
    var total = bondFiltered.length;
    var start = (bondCurrentPage - 1) * bondPageSize;
    var end = Math.min(start + bondPageSize, total);
    var pageData = bondFiltered.slice(start, end);
    
    tbody.innerHTML = '';
    if (pageData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="14" class="no-data">No records found</td></tr>';
    } else {
        for (var i = 0; i < pageData.length; i++) {
            var item = pageData[i];
            var row = '<tr>' +
                '<td>' + (item.investment_name || '') + '</td>' +
                '<td>' + (item.asset_class || '') + '</td>' +
                '<td>' + (item.face_value || '') + '</td>' +
                '<td>' + (item.interest_rate || '') + '</td>' +
                '<td>' + (item.payout_frequency || '') + '</td>' +
                '<td>' + (item.transaction_type || '') + '</td>' +
                '<td>' + (item.transaction_date || '') + '</td>' +
                '<td>' + (item.transaction_amount || '') + '</td>' +
                '<td>' + (item.units || '') + '</td>' +
                '<td>' + (item.payout_date || '') + '</td>' +
                '<td>' + (item.current_rate || '') + '</td>' +
                '<td>' + (item.current_date || '') + '</td>' +
                '<td>' + (item.maturity_date || '') + '</td>' +
                '<td><a href="#" class="action-btn" onclick="showEditForm(\'edit_bond\',' + item.slno + '); return false;">Edit</a><a href="#" class="action-btn delete-btn" onclick="deleteRow(\'bond\',' + item.slno + ',\'' + (item.investment_name || '') + '\'); return false;">Delete</a></td>' +
                '</tr>';
            tbody.innerHTML += row;
        }
    }
    
    var infoEl = document.getElementById('bond_entry_info');
    if (total === 0) infoEl.innerHTML = 'Showing 0 to 0 of 0 entries';
    else {
        var info = 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries';
        if (bondAllData.length !== total) info += ' (filtered from ' + bondAllData.length + ' total entries)';
        infoEl.innerHTML = info;
    }
    
    var totalPages = Math.max(1, Math.ceil(total / bondPageSize));
    var pagEl = document.getElementById('bond_pagination');
    pagEl.innerHTML = '';
    
    var prev = document.createElement('button');
    prev.className = 'page-button';
    prev.innerHTML = 'Previous';
    prev.disabled = (bondCurrentPage === 1);
    prev.onclick = function() { bondCurrentPage--; bondRender(); };
    pagEl.appendChild(prev);
    
    var maxBtns = 5;
    var startPage = Math.max(1, bondCurrentPage - Math.floor(maxBtns / 2));
    var endPage = Math.min(totalPages, startPage + maxBtns - 1);
    startPage = Math.max(1, endPage - maxBtns + 1);
    for (var p = startPage; p <= endPage; p++) {
        var btn = document.createElement('button');
        btn.className = 'page-button' + (p === bondCurrentPage ? ' active' : '');
        btn.innerHTML = p;
        btn.onclick = (function(pg) { return function() { bondCurrentPage = pg; bondRender(); }; })(p);
        pagEl.appendChild(btn);
    }
    var nxt = document.createElement('button');
    nxt.className = 'page-button';
    nxt.innerHTML = 'Next';
    nxt.disabled = (bondCurrentPage >= totalPages);
    nxt.onclick = function() { bondCurrentPage++; bondRender(); };
    pagEl.appendChild(nxt);
}

// ==================== AIF SECTION ====================
var aifAllData = [], aifFiltered = [], aifCurrentPage = 1, aifPageSize = 10;

function aifSearch() {
    var q = document.getElementById('aif_search').value.toLowerCase();
    aifFiltered = aifAllData.filter(function(r) {
        return (r.investment_name || '').toLowerCase().indexOf(q) !== -1 ||
               (r.asset_class || '').toLowerCase().indexOf(q) !== -1 ||
               (r.issuer_name || '').toLowerCase().indexOf(q) !== -1;
    });
    aifCurrentPage = 1;
    aifRender();
}

function aifChangePageSize() {
    aifPageSize = parseInt(document.getElementById('aif_page_size').value);
    aifCurrentPage = 1;
    aifRender();
}

function aifRender() {
    var tbody = document.getElementById('aifTableBody');
    var total = aifFiltered.length;
    var start = (aifCurrentPage - 1) * aifPageSize;
    var end = Math.min(start + aifPageSize, total);
    var pageData = aifFiltered.slice(start, end);
    
    tbody.innerHTML = '';
    if (pageData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="no-data">No records found</td></tr>';
    } else {
        for (var i = 0; i < pageData.length; i++) {
            var item = pageData[i];
            var row = '<tr>' +
                '<td>' + (item.investment_name || '') + '</td>' +
                '<td>' + (item.asset_class || '') + '</td>' +
                '<td>' + (item.issuer_name || '') + '</td>' +
                '<td>' + (item.transaction_type || '') + '</td>' +
                '<td>' + (item.transaction_date || '') + '</td>' +
                '<td>' + (item.transaction_amount || '') + '</td>' +
                '<td>' + (item.units || '') + '</td>' +
                '<td><a href="#" class="action-btn" onclick="showEditForm(\'edit_aif\',' + item.slno + '); return false;">Edit</a><a href="#" class="action-btn delete-btn" onclick="deleteRow(\'aif\',' + item.slno + ',\'' + (item.investment_name || '') + '\'); return false;">Delete</a></td>' +
                '</tr>';
            tbody.innerHTML += row;
        }
    }
    
    var infoEl = document.getElementById('aif_entry_info');
    if (total === 0) infoEl.innerHTML = 'Showing 0 to 0 of 0 entries';
    else {
        var info = 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries';
        if (aifAllData.length !== total) info += ' (filtered from ' + aifAllData.length + ' total entries)';
        infoEl.innerHTML = info;
    }
    
    var totalPages = Math.max(1, Math.ceil(total / aifPageSize));
    var pagEl = document.getElementById('aif_pagination');
    pagEl.innerHTML = '';
    
    var prev = document.createElement('button');
    prev.className = 'page-button';
    prev.innerHTML = 'Previous';
    prev.disabled = (aifCurrentPage === 1);
    prev.onclick = function() { aifCurrentPage--; aifRender(); };
    pagEl.appendChild(prev);
    
    var maxBtns = 5;
    var startPage = Math.max(1, aifCurrentPage - Math.floor(maxBtns / 2));
    var endPage = Math.min(totalPages, startPage + maxBtns - 1);
    startPage = Math.max(1, endPage - maxBtns + 1);
    for (var p = startPage; p <= endPage; p++) {
        var btn = document.createElement('button');
        btn.className = 'page-button' + (p === aifCurrentPage ? ' active' : '');
        btn.innerHTML = p;
        btn.onclick = (function(pg) { return function() { aifCurrentPage = pg; aifRender(); }; })(p);
        pagEl.appendChild(btn);
    }
    var nxt = document.createElement('button');
    nxt.className = 'page-button';
    nxt.innerHTML = 'Next';
    nxt.disabled = (aifCurrentPage >= totalPages);
    nxt.onclick = function() { aifCurrentPage++; aifRender(); };
    pagEl.appendChild(nxt);
}

// ==================== PMS SECTION ====================
var pmsAllData = [], pmsFiltered = [], pmsCurrentPage = 1, pmsPageSize = 10;

function pmsSearch() {
    var q = document.getElementById('pms_search').value.toLowerCase();
    pmsFiltered = pmsAllData.filter(function(r) {
        return (r.investment_name || '').toLowerCase().indexOf(q) !== -1 ||
               (r.asset_class || '').toLowerCase().indexOf(q) !== -1 ||
               (r.issuer_name || '').toLowerCase().indexOf(q) !== -1;
    });
    pmsCurrentPage = 1;
    pmsRender();
}

function pmsChangePageSize() {
    pmsPageSize = parseInt(document.getElementById('pms_page_size').value);
    pmsCurrentPage = 1;
    pmsRender();
}

function pmsRender() {
    var tbody = document.getElementById('pmsTableBody');
    var total = pmsFiltered.length;
    var start = (pmsCurrentPage - 1) * pmsPageSize;
    var end = Math.min(start + pmsPageSize, total);
    var pageData = pmsFiltered.slice(start, end);
    
    tbody.innerHTML = '';
    if (pageData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="no-data">No records found</td></tr>';
    } else {
        for (var i = 0; i < pageData.length; i++) {
            var item = pageData[i];
            var ttDisplay = item.transaction_type || '';
            if (ttDisplay === 'P' || ttDisplay === 'NEW') ttDisplay = 'New Purchase';
            else if (ttDisplay === 'TOPUP') ttDisplay = 'Top Up';
            else if (ttDisplay === 'FULL' || ttDisplay === 'R') ttDisplay = 'Full Redemption';
            else if (ttDisplay === 'PARTIAL' || ttDisplay === 'PART') ttDisplay = 'Part Sell';

            var row = '<tr>' +
                '<td>' + (item.investment_name || '') + '</td>' +
                '<td>' + (item.asset_class || '') + '</td>' +
                '<td>' + (item.issuer_name || '') + '</td>' +
                '<td>' + ttDisplay + '</td>' +
                '<td>' + (item.transaction_date || '') + '</td>' +
                '<td>' + (item.transaction_amount || '') + '</td>' +
                '<td><a href="#" class="action-btn" onclick="showEditForm(\'edit_pms\',' + item.slno + '); return false;">Edit</a><a href="#" class="action-btn delete-btn" onclick="deleteRow(\'pms\',' + item.slno + ',\'' + (item.investment_name || '') + '\'); return false;">Delete</a></td>' +
                '</tr>';
            tbody.innerHTML += row;
        }
    }
    
    var infoEl = document.getElementById('pms_entry_info');
    if (total === 0) infoEl.innerHTML = 'Showing 0 to 0 of 0 entries';
    else {
        var info = 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries';
        if (pmsAllData.length !== total) info += ' (filtered from ' + pmsAllData.length + ' total entries)';
        infoEl.innerHTML = info;
    }
    
    var totalPages = Math.max(1, Math.ceil(total / pmsPageSize));
    var pagEl = document.getElementById('pms_pagination');
    pagEl.innerHTML = '';
    
    var prev = document.createElement('button');
    prev.className = 'page-button';
    prev.innerHTML = 'Previous';
    prev.disabled = (pmsCurrentPage === 1);
    prev.onclick = function() { pmsCurrentPage--; pmsRender(); };
    pagEl.appendChild(prev);
    
    var maxBtns = 5;
    var startPage = Math.max(1, pmsCurrentPage - Math.floor(maxBtns / 2));
    var endPage = Math.min(totalPages, startPage + maxBtns - 1);
    startPage = Math.max(1, endPage - maxBtns + 1);
    for (var p = startPage; p <= endPage; p++) {
        var btn = document.createElement('button');
        btn.className = 'page-button' + (p === pmsCurrentPage ? ' active' : '');
        btn.innerHTML = p;
        btn.onclick = (function(pg) { return function() { pmsCurrentPage = pg; pmsRender(); }; })(p);
        pagEl.appendChild(btn);
    }
    var nxt = document.createElement('button');
    nxt.className = 'page-button';
    nxt.innerHTML = 'Next';
    nxt.disabled = (pmsCurrentPage >= totalPages);
    nxt.onclick = function() { pmsCurrentPage++; pmsRender(); };
    pagEl.appendChild(nxt);
}

// ==================== FD SECTION ====================
var fdAllData = [], fdFiltered = [], fdCurrentPage = 1, fdPageSize = 10;

function fdSearch() {
    var q = document.getElementById('fd_search').value.toLowerCase();
    fdFiltered = fdAllData.filter(function(r) {
        return (r.investment_name || '').toLowerCase().indexOf(q) !== -1 ||
               (r.asset_class || '').toLowerCase().indexOf(q) !== -1 ||
               String(r.transaction_amount || '').indexOf(q) !== -1;
    });
    fdCurrentPage = 1;
    fdRender();
}

function fdChangePageSize() {
    fdPageSize = parseInt(document.getElementById('fd_page_size').value);
    fdCurrentPage = 1;
    fdRender();
}

function fdRender() {
    var tbody = document.getElementById('fdTableBody');
    var total = fdFiltered.length;
    var start = (fdCurrentPage - 1) * fdPageSize;
    var end = Math.min(start + fdPageSize, total);
    var pageData = fdFiltered.slice(start, end);
    
    tbody.innerHTML = '';
    if (pageData.length === 0) {
        tbody.innerHTML = '<td colspan="11" class="no-data">No records found</td></tr>';
    } else {
        for (var i = 0; i < pageData.length; i++) {
            var item = pageData[i];
            var row = '<tr>' +
                '<td>' + (item.investment_name || '') + '</td>' +
                '<td>' + (item.asset_class || '') + '</td>' +
                '<td>' + (item.transaction_amount || '') + '</td>' +
                '<td>' + (item.transaction_date || '') + '</td>' +
                '<td>' + (item.payout_frequency || '') + '</td>' +
                '<td>' + (item.payout_amt || '') + '</td>' +
                '<td>' + (item.current_value || '') + '</td>' +
                '<td>' + (item.current_date || '') + '</td>' +
                '<td>' + (item.cagr || '') + '</td>' +
                '<td>' + (item.maturity_date || '') + '</td>' +
                '<td><a href="#" class="action-btn" onclick="showEditForm(\'edit_fd\',' + item.slno + '); return false;">Edit</a><a href="#" class="action-btn delete-btn" onclick="deleteRow(\'fd\',' + item.slno + ',\'' + (item.investment_name || '') + '\'); return false;">Delete</a></td>' +
                '</tr>';
            tbody.innerHTML += row;
        }
    }
    
    var infoEl = document.getElementById('fd_entry_info');
    if (total === 0) infoEl.innerHTML = 'Showing 0 to 0 of 0 entries';
    else {
        var info = 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries';
        if (fdAllData.length !== total) info += ' (filtered from ' + fdAllData.length + ' total entries)';
        infoEl.innerHTML = info;
    }
    
    var totalPages = Math.max(1, Math.ceil(total / fdPageSize));
    var pagEl = document.getElementById('fd_pagination');
    pagEl.innerHTML = '';
    
    var prev = document.createElement('button');
    prev.className = 'page-button';
    prev.innerHTML = 'Previous';
    prev.disabled = (fdCurrentPage === 1);
    prev.onclick = function() { fdCurrentPage--; fdRender(); };
    pagEl.appendChild(prev);
    
    var maxBtns = 5;
    var startPage = Math.max(1, fdCurrentPage - Math.floor(maxBtns / 2));
    var endPage = Math.min(totalPages, startPage + maxBtns - 1);
    startPage = Math.max(1, endPage - maxBtns + 1);
    for (var p = startPage; p <= endPage; p++) {
        var btn = document.createElement('button');
        btn.className = 'page-button' + (p === fdCurrentPage ? ' active' : '');
        btn.innerHTML = p;
        btn.onclick = (function(pg) { return function() { fdCurrentPage = pg; fdRender(); }; })(p);
        pagEl.appendChild(btn);
    }
    var nxt = document.createElement('button');
    nxt.className = 'page-button';
    nxt.innerHTML = 'Next';
    nxt.disabled = (fdCurrentPage >= totalPages);
    nxt.onclick = function() { fdCurrentPage++; fdRender(); };
    pagEl.appendChild(nxt);
}

// ==================== COMMON FUNCTIONS ====================
function showDiv() {
    var UCC = document.getElementById("custUCC").value;
    if(UCC.trim() === "") {
        document.getElementById('status').innerHTML = "Please enter Customer UCC";
        document.getElementById('status').style.color = 'red';
        return;
    }
    getUCCName(UCC, showDiv2);
}

function showDiv2(response) {
    var UCC = document.getElementById("custUCC").value;
    if(response == false) {
        document.getElementById('status').style.color = 'red';
        document.getElementById('status').innerHTML = "Error: UCC " + UCC + " does not exist!";
        return;
    }
    document.getElementById('status').style.color = 'green';
    document.getElementById('status').innerHTML = "Customer: " + response;
    
    var investmentType = document.getElementById("investment_type").value;
    if(investmentType === "") {
        document.getElementById('status').innerHTML = "Please select an investment type";
        return;
    }
    
    document.getElementById("bond_div").style.display = "none";
    document.getElementById("aif_div").style.display = "none";
    document.getElementById("pms_div").style.display = "none";
    document.getElementById("fd_div").style.display = "none";

    if (investmentType == "bond_div") {
        resetBondForm();
        document.getElementById("bond_div").style.display = "block";
        fetchInvestData("bond");
    } else if (investmentType == "aif_div") {
        resetAifForm();
        document.getElementById("aif_div").style.display = "block";
        fetchInvestData("aif");
    } else if (investmentType == "pms_div") {
        resetPmsForm();
        document.getElementById("pms_div").style.display = "block";
        fetchInvestData("pms");
    } else if (investmentType == "fd_div") {
        resetFdForm();
        document.getElementById("fd_div").style.display = "block";
        fetchInvestData("fd");
    }
}

function fetchInvestData(type) {
    var UCC = document.getElementById("custUCC").value;
    if(UCC.trim() === "") return;
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "getInvesteddata.php", true);
    xhr.responseType = 'json';
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200 && xhr.response) {
            var data = xhr.response;
            if (type === "bond") { bondAllData = data; bondFiltered = [...bondAllData]; bondCurrentPage = 1; bondRender(); }
            else if (type === "aif") { aifAllData = data; aifFiltered = [...aifAllData]; aifCurrentPage = 1; aifRender(); }
            else if (type === "pms") { pmsAllData = data; pmsFiltered = [...pmsAllData]; pmsCurrentPage = 1; pmsRender(); }
            else if (type === "fd") { fdAllData = data; fdFiltered = [...fdAllData]; fdCurrentPage = 1; fdRender(); }
        }
    };
    xhr.send("method=getInvestData&UCC=" + encodeURIComponent(UCC) + "&tp=" + type);
}

function getUCCName(UCC, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "getInvesteddata.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) callback(JSON.parse(xhr.responseText));
    };
    xhr.send("method=getUCCName&UCC=" + encodeURIComponent(UCC));
}

function fetchCustomerUCCs(input) {
    var val = input.value.toLowerCase();
    var list = document.getElementById("autocomplete-list");
    list.innerHTML = "";
    if (val === "") { list.style.display = "none"; return; }
    fetch("getUCClistwithname.php").then(function(r) { return r.json(); }).then(function(data) {
        var matching = data.filter(function(e) { return e.UCC.toLowerCase().indexOf(val) !== -1 || e.name.toLowerCase().indexOf(val) !== -1; });
        for (var i = 0; i < matching.length; i++) {
            var e = matching[i];
            var opt = document.createElement("div");
            opt.innerHTML = e.UCC + " - " + e.name;
            opt.addEventListener("click", function(ucc) { return function() { input.value = ucc; list.innerHTML = ""; list.style.display = "none"; }; }(e.UCC));
            list.appendChild(opt);
        }
        list.style.display = matching.length > 0 ? "block" : "none";
    }).catch(function(err) { console.error(err); });
}

var jq = window.jQuery || window.$;
if (jq) {
    jq(document).off("input", "#custUCC").on("input", "#custUCC", function() { fetchCustomerUCCs(this); });
    jq(document).off("click.autoAddInv").on("click.autoAddInv", function(e) {
        if (e.target.id !== "custUCC") {
            var list = document.getElementById("autocomplete-list");
            if (list) list.style.display = "none";
        }
    });
} else {
    // Fallback if jQuery is somehow not available
    var input = document.getElementById("custUCC");
    if(input) {
        input.addEventListener("input", function() { fetchCustomerUCCs(this); });
        document.addEventListener("click", function(e) { if (e.target !== input) { var list = document.getElementById("autocomplete-list"); if (list) list.style.display = "none"; } });
    }
}

// Bond specific functions
function updateBondNames(bondData) {
    var assetClass = document.getElementById("bond_asset_class").value;
    var bondNameSelect = document.getElementById("bond_name");
    
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            bondNameSelect.innerHTML = xhr.responseText;
            if(bondData && bondData !== "") selectBondName(bondData);
        }
    };
    xhr.open("GET", "getInvesteddata.php?method=getBondNames&asset_class=" + encodeURIComponent(assetClass), true);
    xhr.send();
}

function updateBondDetails() {
    var selectedBondName = document.getElementById("bond_name").value;
    if (selectedBondName !== "") {
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response) {
                        document.getElementById("bond_face_value").value = response.face_value || '';
                        document.getElementById("bond_interest_rate").value = response.coupon_rate || response.interest_rate || '';
                        document.getElementById("bond_payout_frequency").value = response.payout_frequency || '';
                        if (response.redemption_date) {
                            var dateStr = String(response.redemption_date).trim();
                            if (dateStr.indexOf('/') !== -1) {
                                var parts = dateStr.split("/");
                                if (parts.length === 3) document.getElementById("bond_maturity_date").value = parts[2] + "-" + parts[1] + "-" + parts[0];
                            } else if (dateStr.indexOf('-') !== -1) {
                                var parts = dateStr.split("-");
                                if (parts.length === 3) {
                                    if (parts[0].length === 4) { // YYYY-MM-DD
                                        document.getElementById("bond_maturity_date").value = dateStr;
                                    } else { // DD-MM-YYYY
                                        document.getElementById("bond_maturity_date").value = parts[2] + "-" + parts[1] + "-" + parts[0];
                                    }
                                }
                            }
                        }
                    }
                } catch(e) {
                    console.error("Error parsing bond details response:", e);
                }
            }
        };
        xhr.open("GET", "getInvesteddata.php?method=getBondDetails&bond_name=" + encodeURIComponent(selectedBondName), true);
        xhr.send();
    }
}

function selectBondName(bondData) {
    var bondNameSelect = document.getElementById("bond_name");
    for (var i = 0; i < bondNameSelect.options.length; i++) {
        if (bondNameSelect.options[i].value === bondData) {
            bondNameSelect.selectedIndex = i;
            updateBondDetails();
            break;
        }
    }
}

// Reset functions
function resetBondForm() {
    document.getElementById("bond_formhead").innerHTML = "Bond Investment";
    document.getElementById("bond_slno").value = ""; document.getElementById("bond_asset_class").value = "";
    document.getElementById("bond_transaction_amount").value = ""; document.getElementById("bond_face_value").value = "";
    document.getElementById("bond_interest_rate").value = ""; document.getElementById("bond_payout_date").value = "";
    document.getElementById("bond_payout_frequency").value = "Annually"; document.getElementById("bond_units").value = "";
    document.getElementById("bond_transaction_date").value = ""; document.getElementById("bond_current_rate").value = "";
    document.getElementById("bond_current_date").value = new Date().toISOString().slice(0, 10);
    document.getElementById("bond_maturity_date").value = ""; document.getElementById("bond_transaction_type").value = "";
    document.getElementById("bond_search").value = "";
    document.querySelector('button[name="add_bond"]').innerHTML = 'Add Bond';
    document.getElementById("bond_action").value = "add";
    bondAllData = []; bondFiltered = []; bondCurrentPage = 1; bondRender();
    updateBondNames();
}

function resetAifForm() {
    document.getElementById("aif_formhead").innerHTML = "AIF Investment";
    document.getElementById("aif_name").value = ""; document.getElementById("aif_issuer").value = "";
    document.getElementById("aif_slno").value = ""; document.getElementById("aif_asset_class").value = "";
    document.getElementById("aif_transaction_amount").value = ""; document.getElementById("aif_transaction_date").value = "";
    document.getElementById("aif_units").value = ""; document.getElementById("aif_transaction_type").value = "";
    document.getElementById("aif_search").value = "";
    document.querySelector('button[name="add_aif"]').innerHTML = 'Add AIF';
    document.getElementById("aif_action").value = "add";
    aifAllData = []; aifFiltered = []; aifCurrentPage = 1; aifRender();
}

function resetPmsForm() {
    document.getElementById("pms_formhead").innerHTML = "PMS Investment";
    document.getElementById("pms_name").value = ""; document.getElementById("pms_issuer").value = "";
    document.getElementById("pms_slno").value = ""; document.getElementById("pms_asset_class").value = "";
    document.getElementById("pms_transaction_amount").value = ""; document.getElementById("pms_transaction_date").value = "";
    document.getElementById("pms_transaction_type").value = ""; document.getElementById("pms_search").value = "";
    document.querySelector('button[name="add_pms"]').innerHTML = 'Add PMS';
    document.getElementById("pms_action").value = "add";
    pmsAllData = []; pmsFiltered = []; pmsCurrentPage = 1; pmsRender();
}

function resetFdForm() {
    document.getElementById("fd_formhead").innerHTML = "FD Investment";
    document.getElementById("fd_name").value = ""; document.getElementById("fd_slno").value = "";
    document.getElementById("fd_asset_class").value = ""; document.getElementById("fd_transaction_amount").value = "";
    document.getElementById("fd_transaction_date").value = ""; document.getElementById("fd_payout_frequency").value = "";
    document.getElementById("fd_payout_recd").value = ""; document.getElementById("fd_current_value").value = "";
    document.getElementById("fd_cagr").value = ""; document.getElementById("fd_maturity_date").value = "";
    document.getElementById("fd_current_date").value = ""; document.getElementById("fd_search").value = "";
    document.querySelector('button[name="add_fd"]').innerHTML = 'Add FD';
    document.getElementById("fd_action").value = "add";
    fdAllData = []; fdFiltered = []; fdCurrentPage = 1; fdRender();
}

// Add/Edit/Delete functions
function addBond() {
    var UCC = document.getElementById("custUCC").value;
    if(UCC.trim() === "") { document.getElementById('status').innerHTML = "Please select a customer first"; return; }
    
    var slno = document.getElementById("bond_slno").value || 0;
    var bondAssetClass = document.getElementById("bond_asset_class").value;
    var bondName = document.getElementById("bond_name").value;
    var faceValue = document.getElementById("bond_face_value").value || 0;
    var bondTransactionType = document.getElementById("bond_transaction_type").value || "";
    var bondTransactionAmount = document.getElementById("bond_transaction_amount").value || 0;
    var bondIntRate = document.getElementById("bond_interest_rate").value || 0;
    var bondUnits = document.getElementById("bond_units").value || 0;
    var bondTransactionDate = document.getElementById("bond_transaction_date").value || "";
    var bondPayoutDate = document.getElementById("bond_payout_date").value || "";
    var bondCurrentRate = document.getElementById("bond_current_rate").value || 0;
    var currentDate = document.getElementById("bond_current_date").value || "";
    var payoutFrequency = document.getElementById("bond_payout_frequency").value;
    var bondMdate = document.getElementById("bond_maturity_date").value || "";
    var action = document.getElementById("bond_action").value || 0;

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "getInvesteddata.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchInvestData("bond");
            if(action !== "edit") resetBondForm();
        }
    };
    var data = "method=addInvest&tp=bond&UCC=" + encodeURIComponent(UCC) + "&name=" + encodeURIComponent(bondName) + "&assetclass=" + encodeURIComponent(bondAssetClass) + "&face_value=" + faceValue + "&tt=" + bondTransactionType + "&transaction_amount=" + bondTransactionAmount + "&interest_rate=" + bondIntRate + "&units=" + bondUnits + "&payout_date=" + bondPayoutDate + "&transaction_date=" + bondTransactionDate + "&current_rate=" + bondCurrentRate + "&current_date=" + currentDate + "&payoutFrequency=" + encodeURIComponent(payoutFrequency) + "&Mdate=" + bondMdate + "&action=" + action + "&slno=" + slno;
    xhr.send(data);
}

function addAif() {
    var UCC = document.getElementById("custUCC").value;
    if(UCC.trim() === "") { document.getElementById('status').innerHTML = "Please select a customer first"; return; }
    
    var slno = document.getElementById("aif_slno").value || 0;
    var name = document.getElementById("aif_name").value;
    var aif_issuer = document.getElementById("aif_issuer").value;
    var assetClass = document.getElementById("aif_asset_class").value;
    var transactionType = document.getElementById("aif_transaction_type").value;
    var TransactionAmount = document.getElementById("aif_transaction_amount").value;
    var transactionDate = document.getElementById("aif_transaction_date").value;
    var aifUnits = document.getElementById("aif_units").value || 0;
    var action = document.getElementById("aif_action").value;

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "getInvesteddata.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchInvestData("aif");
            if(action !== "edit") resetAifForm();
        }
    };
    var data = "method=addInvest&tp=aif&UCC=" + encodeURIComponent(UCC) + "&name=" + encodeURIComponent(name) + "&issuer=" + encodeURIComponent(aif_issuer) + "&assetclass=" + encodeURIComponent(assetClass) + "&transaction_amount=" + TransactionAmount + "&transaction_date=" + transactionDate + "&tt=" + transactionType + "&units=" + aifUnits + "&action=" + action + "&slno=" + slno;
    xhr.send(data);
}

function addPms() {
    var UCC = document.getElementById("custUCC").value;
    if(UCC.trim() === "") { document.getElementById('status').innerHTML = "Please select a customer first"; return; }
    
    var slno = document.getElementById("pms_slno").value || 0;
    var name = document.getElementById("pms_name").value;
    var pms_issuer = document.getElementById("pms_issuer").value;
    var assetClass = document.getElementById("pms_asset_class").value;
    var transactionType = document.getElementById("pms_transaction_type").value;
    var TransactionAmount = document.getElementById("pms_transaction_amount").value;
    var transactionDate = document.getElementById("pms_transaction_date").value;
    var action = document.getElementById("pms_action").value;

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "getInvesteddata.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchInvestData("pms");
            if(action !== "edit") resetPmsForm();
        }
    };
    var data = "method=addInvest&tp=pms&UCC=" + encodeURIComponent(UCC) + "&name=" + encodeURIComponent(name) + "&issuer=" + encodeURIComponent(pms_issuer) + "&assetclass=" + encodeURIComponent(assetClass) + "&transaction_amount=" + TransactionAmount + "&transaction_date=" + transactionDate + "&tt=" + transactionType + "&action=" + action + "&slno=" + slno;
    xhr.send(data);
}

function addFd() {
    var UCC = document.getElementById("custUCC").value;
    if(UCC.trim() === "") { document.getElementById('status').innerHTML = "Please select a customer first"; return; }
    
    var slno = document.getElementById("fd_slno").value || 0;
    var name = document.getElementById("fd_name").value;
    var assetClass = document.getElementById("fd_asset_class").value;
    var TransactionAmount = document.getElementById("fd_transaction_amount").value || 0;
    var transactionDate = document.getElementById("fd_transaction_date").value;
    var currentValue = document.getElementById("fd_current_value").value || 0;
    var CurrentDate = document.getElementById("fd_current_date").value || "";
    var interestPayout = document.getElementById("fd_payout_frequency").value;
    var payoutAmt = document.getElementById("fd_payout_recd").value || 0;
    var cagr = document.getElementById("fd_cagr").value || 0;
    var Mdate = document.getElementById("fd_maturity_date").value || "";
    var action = document.getElementById("fd_action").value || 0;

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "getInvesteddata.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchInvestData("fd");
            if(action !== "edit") resetFdForm();
        }
    };
    var data = "method=addInvest&tp=fd&UCC=" + encodeURIComponent(UCC) + "&name=" + encodeURIComponent(name) + "&assetclass=" + encodeURIComponent(assetClass) + "&transaction_amount=" + TransactionAmount + "&transaction_date=" + transactionDate + "&current_value=" + currentValue + "&current_date=" + CurrentDate + "&payoutFrequency=" + encodeURIComponent(interestPayout) + "&cagr=" + cagr + "&Mdate=" + Mdate + "&payoutamt=" + payoutAmt + "&action=" + action + "&slno=" + slno;
    xhr.send(data);
}

function showEditForm(formType, investmentId) {
    var investmentType = "";
    if (formType === "edit_bond") investmentType = "Bond";
    else if (formType === "edit_aif") investmentType = "AIF";
    else if (formType === "edit_pms") investmentType = "PMS";
    else if (formType === "edit_fd") investmentType = "FD";
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'getInvesteddata.php?method=getInvestmentData&investmentType=' + investmentType + '&investmentId=' + investmentId);
    xhr.onload = function() {
        var data = JSON.parse(xhr.responseText);
        
        if (formType === "edit_bond") {
            document.getElementById("bond_asset_class").value = data.asset_class;
            // Populate bond name dropdown for this asset class, then select the saved bond name
            updateBondNames(data.investment_name);
            document.getElementById("bond_transaction_amount").value = data.transaction_amount;
            document.getElementById("bond_face_value").value = data.face_value;
            document.getElementById("bond_interest_rate").value = data.interest_rate;
            document.getElementById("bond_payout_frequency").value = data.payout_frequency;
            document.getElementById("bond_units").value = data.units;
            document.getElementById("bond_transaction_date").value = data.transaction_date;
            document.getElementById("bond_payout_date").value = data.payout_date;
            document.getElementById("bond_current_rate").value = data.current_rate;
            document.getElementById("bond_current_date").value = data.current_date;
            document.getElementById("bond_maturity_date").value = data.maturity_date;
            document.getElementById("bond_transaction_type").value = data.transaction_type;
            document.querySelector('button[name="add_bond"]').innerHTML = 'Update Bond';
            document.getElementById("bond_action").value = "edit";
            document.getElementById("bond_slno").value = investmentId;
            document.getElementById("bond_formhead").innerHTML = "Edit Bond Investment";
        } else if (formType === "edit_aif") {
            document.getElementById("aif_name").value = data.investment_name;
            document.getElementById("aif_issuer").value = data.issuer_name;
            document.getElementById("aif_asset_class").value = data.asset_class;
            document.getElementById("aif_transaction_type").value = data.transaction_type;
            document.getElementById("aif_transaction_amount").value = data.transaction_amount;
            document.getElementById("aif_transaction_date").value = data.transaction_date;
            document.getElementById("aif_units").value = data.units;
            document.querySelector('button[name="add_aif"]').innerHTML = 'Update AIF';
            document.getElementById("aif_action").value = "edit";
            document.getElementById("aif_slno").value = investmentId;
            document.getElementById("aif_formhead").innerHTML = "Edit AIF Investment";
        } else if (formType === "edit_pms") {
            document.getElementById("pms_name").value = data.investment_name;
            document.getElementById("pms_issuer").value = data.issuer_name;
            document.getElementById("pms_asset_class").value = data.asset_class;
            document.getElementById("pms_transaction_type").value = data.transaction_type;
            document.getElementById("pms_transaction_amount").value = data.transaction_amount;
            document.getElementById("pms_transaction_date").value = data.transaction_date;
            document.querySelector('button[name="add_pms"]').innerHTML = 'Update PMS';
            document.getElementById("pms_action").value = "edit";
            document.getElementById("pms_slno").value = investmentId;
            document.getElementById("pms_formhead").innerHTML = "Edit PMS Investment";
        } else if (formType === "edit_fd") {
            document.getElementById("fd_name").value = data.investment_name;
            document.getElementById("fd_asset_class").value = data.asset_class;
            document.getElementById("fd_transaction_amount").value = data.transaction_amount;
            document.getElementById("fd_transaction_date").value = data.transaction_date;
            document.getElementById("fd_payout_frequency").value = data.payout_frequency;
            document.getElementById("fd_payout_recd").value = data.payout_amt;
            document.getElementById("fd_current_value").value = data.current_value;
            document.getElementById("fd_current_date").value = data.current_date;
            document.getElementById("fd_cagr").value = data.cagr;
            document.getElementById("fd_maturity_date").value = data.maturity_date;
            document.querySelector('button[name="add_fd"]').innerHTML = 'Update FD';
            document.getElementById("fd_action").value = "edit";
            document.getElementById("fd_slno").value = investmentId;
            document.getElementById("fd_formhead").innerHTML = "Edit FD Investment";
        }
    };
    xhr.send();
}

function deleteRow(type, investmentId, iName) {
    if (!confirm("Are you sure you want to delete " + iName + "?")) return;
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "getInvesteddata.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchInvestData(type);
            var actionInput = document.getElementById(type + "_action");
            if(actionInput && actionInput.value == "edit") {
                actionInput.value = "add";
                var addButton = document.querySelector('button[name="add_' + type + '"]');
                if(addButton) addButton.innerHTML = 'Add ' + type.toUpperCase();
                document.getElementById(type + "_slno").value = 0;
                document.getElementById(type + "_formhead").innerHTML = type.toUpperCase() + " Investment";
            }
        }
    };
    xhr.send("method=del_invest&investmentId=" + investmentId + "&iname=" + encodeURIComponent(iName));
}
</script>
</body>
</html>