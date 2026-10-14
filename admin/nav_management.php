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
    <title>NAV Management Dashboard</title>
    <style>
        .container { max-width: 1200px; margin: 0 auto; }

        .header-card { background:white; border-radius:20px; padding:25px 30px; margin-bottom:25px; box-shadow:0 10px 40px rgba(0,0,0,0.1); }
        .header-card h1 { color:#333; font-size:22px; margin-bottom:4px; }
        .header-card p  { color:#888; font-size:13px; }

        .selection-panel { background:white; border-radius:20px; padding:22px 28px; margin-bottom:25px; box-shadow:0 10px 40px rgba(0,0,0,0.1); }
        .form-row { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; }
        .form-group { flex:1; min-width:200px; position:relative; }
        .form-group label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.5px; }
        .form-group input, .form-group select { width:100%; padding:11px 14px; border:2px solid #e0e0e0; border-radius:12px; font-size:14px; transition:all 0.3s; background:#fafafa; color:#333; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:#667eea; background:white; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }

        #autocomplete-list { position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #e0e0e0; border-radius:12px; max-height:220px; overflow-y:auto; z-index:1000; box-shadow:0 10px 25px rgba(0,0,0,0.1); }
        #autocomplete-list div { padding:11px 14px; cursor:pointer; font-size:13px; color:#333; transition:background 0.2s; }
        #autocomplete-list div:hover { background:#f5f0ff; color:#667eea; }

        .btn { padding:11px 26px; border:none; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.3s; white-space:nowrap; }
        .btn-orange { background:#fd7101; color:white; }
        .btn-orange:hover { background:#e66500; transform:translateY(-2px); box-shadow:0 5px 15px rgba(253,113,1,0.35); }

        #status { font-size:13px; font-weight:500; padding:8px 14px; border-radius:10px; background:#f8f9fa; min-width:160px; }

        .nav-type-selector { 
            background: #f8f9fa; 
            border-radius: 12px; 
            padding: 8px;
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .nav-type-btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            background: white;
            border: 2px solid #e0e0e0;
            color: #666;
        }
        .nav-type-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
        }
        .nav-type-btn:hover:not(.active) {
            border-color: #667eea;
            background: #f5f0ff;
        }

        .investment-section { background:white; border-radius:20px; padding:28px; margin-bottom:25px; box-shadow:0 10px 40px rgba(0,0,0,0.1); }
        .section-header { border-bottom:3px solid #667eea; padding-bottom:14px; margin-bottom:24px; }
        .section-header h3 { color:#333; font-size:18px; font-weight:700; }

        .form-inline { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; margin-bottom:20px; }
        .input-field { display:flex; flex-direction:column; flex:1; min-width:180px; }
        .input-field label { font-size:12px; font-weight:600; color:#555; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.5px; }
        .input-field input, .input-field select { padding:10px 13px; border:2px solid #e0e0e0; border-radius:11px; font-size:14px; transition:all 0.3s; background:#fafafa; color:#333; }
        .input-field input:focus, .input-field select:focus { outline:none; border-color:#667eea; background:white; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }
        .field-action { display:flex; align-items:flex-end; }

        .data-controls { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin:20px 0; }
        .entries-selector { display:flex; align-items:center; gap:8px; font-size:13px; color:#555; }
        .entries-selector select { padding:6px 10px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px; background:#fafafa; cursor:pointer; }
        .search-box { display:flex; align-items:center; gap:8px; font-size:13px; color:#555; }
        .search-box input { padding:7px 12px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px; width:220px; background:#fafafa; }

        .data-table-container { overflow-x:auto; border-radius:14px; border:1px solid #e0e0e0; background:white; }
        .nav-table { width:100%; border-collapse:collapse; font-size:13px; }
        .nav-table thead { background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .nav-table th { padding:14px 16px; text-align:left; font-weight:600; color:white; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        .nav-table td { padding:12px 16px; border-bottom:1px solid #f0f0f0; color:#444; }
        .nav-table tbody tr:hover { background:#faf5ff; }
        .nav-table tbody tr:nth-child(even) { background:#fafafa; }

        .pagination-footer { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:16px; }
        .entries-info { font-size:13px; color:#666; }
        .pagination-buttons { display:flex; gap:6px; align-items:center; }
        .page-button { padding:6px 12px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; background:white; color:#555; transition:all 0.2s; }
        .page-button:hover:not(:disabled) { border-color:#667eea; color:#667eea; }
        .page-button.active { background:#667eea; border-color:#667eea; color:white; }
        .page-button:disabled { opacity:0.4; cursor:not-allowed; }

        .action-btn { color:#667eea; text-decoration:none; font-weight:600; font-size:12px; padding:5px 12px; border-radius:6px; background:#f0f0ff; display:inline-block; transition:all 0.2s; cursor:pointer; }
        .action-btn:hover { background:#667eea; color:white; }
        .delete-btn { color:#e53e3e; background:#fff0f0; }
        .delete-btn:hover { background:#e53e3e; color:white; }
        .no-data { text-align:center; padding:40px; color:#999; font-size:14px; }

        @media(max-width:768px){ body{padding:10px;} .form-row,.form-inline{flex-direction:column;} .form-group,.input-field{width:100%;} .data-controls,.pagination-footer{flex-direction:column;align-items:flex-start;} .nav-type-selector{flex-direction:column;} }
    </style>
</head>
<body>
<div class="container">

    <div class="header-card">
        <h1>NAV Management Dashboard</h1>
        <p>Manage PMS NAV, AIF NAV, and AIF Payout records</p>
    </div>

    <div class="selection-panel">
        <form onsubmit="selectCustomer(); return false;">
            <div class="form-row">
                <div class="form-group">
                    <label>Customer UCC</label>
                    <input type="text" name="custUCC" id="custUCC" placeholder="Enter UCC or Name" autocomplete="off">
                    <div id="autocomplete-list"></div>
                </div>
                <div class="form-group" style="flex:0;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-orange">Select Customer</button>
                </div>
                <div class="form-group" style="flex:0; min-width:160px;">
                    <label>&nbsp;</label>
                    <div id="status"></div>
                </div>
            </div>
        </form>
    </div>

    <div id="management_div" style="display:none;">
        <div class="investment-section">
            <!-- NAV Type Selector -->
            <div class="nav-type-selector">
                <div class="nav-type-btn" data-type="pms" onclick="switchNavType('pms')">
                    <i class="fas fa-chart-line"></i> PMS NAV
                </div>
                <div class="nav-type-btn" data-type="aif_nav" onclick="switchNavType('aif_nav')">
                    <i class="fas fa-hdd"></i> AIF NAV
                </div>
                <div class="nav-type-btn" data-type="aif_payout" onclick="switchNavType('aif_payout')">
                    <i class="fas fa-coins"></i> AIF Payout
                </div>
            </div>

            <div class="section-header">
                <h3 id="formhead">Add PMS Current Value</h3>
            </div>
            
            <!-- PMS Form -->
            <form id="pms_form" onsubmit="addPmsRecord(); return false;" style="display:none;">
                <div class="form-inline">
                    <div class="input-field">
                        <label>PMS Name</label>
                        <select id="pms_name" name="pms_name" required></select>
                    </div>
                    <div class="input-field">
                        <label>Date</label>
                        <input type="date" id="pms_current_date" name="pms_current_date">
                    </div>
                    <div class="input-field">
                        <label>Current Value</label>
                        <input type="text" id="pms_current_value" name="pms_current_value" required placeholder="Enter amount" oninput="this.value=this.value.replace(/[^0-9.]/g,'');">
                    </div>
                    <div class="field-action">
                        <input type="hidden" id="pms_action" name="pms_action" value="add">
                        <input type="hidden" id="pms_slno" name="pms_slno" value="0">
                        <button type="submit" class="btn btn-orange" id="pms_submit_btn">Add PMS</button>
                    </div>
                </div>
            </form>

            <!-- AIF NAV Form -->
            <form id="aif_nav_form" onsubmit="addAifNavRecord(); return false;" style="display:none;">
                <div class="form-inline">
                    <div class="input-field">
                        <label>AIF Name</label>
                        <select id="aif_nav_name" name="aif_nav_name" required></select>
                    </div>
                    <div class="input-field">
                        <label>Date</label>
                        <input type="date" id="aif_nav_current_date" name="aif_nav_current_date">
                    </div>
                    <div class="input-field">
                        <label>Current NAV</label>
                        <input type="text" id="aif_nav_current_value" name="aif_nav_current_value" required placeholder="Enter NAV" oninput="this.value=this.value.replace(/[^0-9.]/g,'');">
                    </div>
                    <div class="field-action">
                        <input type="hidden" id="aif_nav_action" name="aif_nav_action" value="add">
                        <input type="hidden" id="aif_nav_slno" name="aif_nav_slno" value="0">
                        <button type="submit" class="btn btn-orange" id="aif_nav_submit_btn">Update NAV</button>
                    </div>
                </div>
            </form>

            <!-- AIF Payout Form - Using same structure as working addaifpayout.php -->
            <form id="aif_payout_form" onsubmit="addAifPayoutRecord(); return false;" style="display:none;">
                <div class="form-inline">
                    <div class="input-field">
                        <label>AIF Name</label>
                        <select id="aif_payout_name" name="aif_payout_name" required></select>
                    </div>
                    <div class="input-field">
                        <label>Date</label>
                        <input type="date" id="aif_payout_date" name="aif_payout_date">
                    </div>
                    <div class="input-field">
                        <label>Amount</label>
                        <input type="text" id="aif_payout_amount" name="aif_payout_amount" required placeholder="Enter amount" oninput="this.value=this.value.replace(/[^0-9.]/g,'');">
                    </div>
                    <div class="input-field">
                        <label>Remarks</label>
                        <input type="text" id="aif_payout_remarks" name="aif_payout_remarks" placeholder="Optional">
                    </div>
                    <div class="field-action">
                        <input type="hidden" id="aif_payout_action" name="aif_payout_action" value="add">
                        <input type="hidden" id="aif_payout_slno" name="aif_payout_slno" value="0">
                        <button type="submit" name="add_aif_payout" class="btn btn-orange" id="aif_payout_submit_btn">Add AIF Payout</button>
                    </div>
                </div>
            </form>

            <!-- Data Table Controls -->
            <div class="data-controls">
                <div class="entries-selector">
                    Show
                    <select id="entries_per_page" onchange="changePageSize()">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    entries
                </div>
                <div class="search-box">
                    Search: <input type="text" id="search_input" placeholder="Search records..." onkeyup="searchData()">
                </div>
            </div>

            <!-- Data Table -->
            <div class="data-table-container">
                <table class="nav-table">
                    <thead id="table_header">
                        <tr><th>Name</th><th>Date</th><th>Value</th><th>Remarks</th><th width="80">Edit</th><th width="80">Delete</th> </>
                    </thead>
                    <tbody id="table_body">
                        <tr><td colspan="6" class="no-data">No records found</td> </>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-footer">
                <div class="entries-info" id="entries_info">Showing 0 to 0 of 0 entries</div>
                <div class="pagination-buttons" id="pagination_buttons"></div>
            </div>
        </div>
    </div>

</div>

<script>
// Add Font Awesome
var link = document.createElement('link');
link.rel = 'stylesheet';
link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
document.head.appendChild(link);

// Global variables
var currentNavType = 'pms';
var currentUCC = '';
var allData = [];
var filteredData = [];
var currentPage = 1;
var pageSize = 10;

// Switch between NAV types
function switchNavType(type) {
    currentNavType = type;
    
    // Update active button style
    document.querySelectorAll('.nav-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.nav-type-btn[data-type="${type}"]`).classList.add('active');
    
    // Update form header
    var headers = {
        'pms': 'Add PMS Current Value',
        'aif_nav': 'Update AIF Current NAV',
        'aif_payout': 'Add AIF Payout'
    };
    document.getElementById('formhead').innerHTML = headers[type];
    
    // Hide all forms, show selected
    document.getElementById('pms_form').style.display = 'none';
    document.getElementById('aif_nav_form').style.display = 'none';
    document.getElementById('aif_payout_form').style.display = 'none';
    
    if (type === 'pms') {
        document.getElementById('pms_form').style.display = 'flex';
        updateNamesDropdown('pms');
        updateTableHeaders('pms');
    } else if (type === 'aif_nav') {
        document.getElementById('aif_nav_form').style.display = 'flex';
        updateNamesDropdown('aif_nav');
        updateTableHeaders('aif_nav');
    } else if (type === 'aif_payout') {
        document.getElementById('aif_payout_form').style.display = 'flex';
        updateNamesDropdown('aif_payout');
        updateTableHeaders('aif_payout');
    }
    
    // Reset form and fetch data
    resetFormByType(type);
    fetchData();
}

// Update table headers based on type
function updateTableHeaders(type) {
    var thead = document.getElementById('table_header');
    if (type === 'pms') {
        thead.innerHTML = '<th>PMS Name</th><th>Date</th><th>Current Value</th><th width="80">Edit</th><th width="80">Delete</th>';
    } else if (type === 'aif_nav') {
        thead.innerHTML = '<tr><th>AIF Name</th><th>Date</th><th>NAV Value</th><th width="80">Edit</th><th width="80">Delete</th></tr>';
    } else if (type === 'aif_payout') {
        thead.innerHTML = '<th>AIF Name</th><th>Date</th><th>Amount</th><th>Remarks</th><th width="80">Edit</th><th width="80">Delete</th>';
    }
}

// Update names dropdown based on type
function updateNamesDropdown(type) {
    if (!currentUCC) return;

    
    if (type === 'pms') {
        var sel = document.getElementById('pms_name');
        sel.innerHTML = '<option value="">Loading...</option>';
        fetch('getInvesteddata.php?method=getPmsNames&UCC=' + encodeURIComponent(currentUCC))
            .then(r => r.text())
            .then(html => { sel.innerHTML = html; });
    } else if (type === 'aif_nav' || type === 'aif_payout') {
        var selId = (type === 'aif_nav') ? 'aif_nav_name' : 'aif_payout_name';
        var sel = document.getElementById(selId);
        sel.innerHTML = '<option value="">Loading...</option>';
        fetch('getInvesteddata.php?method=getAifNames&UCC=' + encodeURIComponent(currentUCC))
            .then(r => r.text())
            .then(html => { sel.innerHTML = html; });
    }
}

// Reset form by type
function resetFormByType(type) {
    if (type === 'pms') {
        document.getElementById('pms_slno').value = '';
        document.getElementById('pms_current_value').value = '';
        document.getElementById('pms_current_date').value = '';
        document.getElementById('pms_action').value = 'add';
        document.getElementById('pms_submit_btn').innerHTML = 'Add PMS';
        document.getElementById('formhead').innerHTML = 'Add PMS Current Value';
    } else if (type === 'aif_nav') {
        document.getElementById('aif_nav_slno').value = '';
        document.getElementById('aif_nav_current_value').value = '';
        document.getElementById('aif_nav_current_date').value = '';
        document.getElementById('aif_nav_action').value = 'add';
        document.getElementById('aif_nav_submit_btn').innerHTML = 'Update NAV';
        document.getElementById('formhead').innerHTML = 'Update AIF Current NAV';
    } else if (type === 'aif_payout') {
        document.getElementById('aif_payout_slno').value = '';
        document.getElementById('aif_payout_amount').value = '';
        document.getElementById('aif_payout_date').value = '';
        document.getElementById('aif_payout_remarks').value = '';
        document.getElementById('aif_payout_action').value = 'add';
        document.getElementById('aif_payout_submit_btn').innerHTML = 'Add AIF Payout';
        document.getElementById('formhead').innerHTML = 'Add AIF Payout';
    }
}

// PMS Record Function
function addPmsRecord() {
    var UCC = currentUCC;
    var slno = document.getElementById('pms_slno').value || 0;
    var name = document.getElementById('pms_name').value;
    var currentValue = document.getElementById('pms_current_value').value || 0;
    var currentDate = document.getElementById('pms_current_date').value || '';
    var action = document.getElementById('pms_action').value;
    
    if(!name) {
        document.getElementById('status').innerHTML = "Please select PMS Name";
        document.getElementById('status').style.color = 'red';
        return;
    }
    
    if(!currentValue || currentValue == 0) {
        document.getElementById('status').innerHTML = "Please enter current value";
        document.getElementById('status').style.color = 'red';
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_nav.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchData();
            resetFormByType('pms');
        }
    };
    var data = "method=updatePmsNav&tp=pms&UCC=" + encodeURIComponent(UCC) + 
               "&name=" + encodeURIComponent(name) +
               "&current_value=" + currentValue + 
               "&current_date=" + currentDate +
               "&action=" + action + 
               "&slno=" + slno;
    xhr.send(data);
}

// AIF NAV Record Function
function addAifNavRecord() {
    var UCC = currentUCC;
    var slno = document.getElementById('aif_nav_slno').value || 0;
    var name = document.getElementById('aif_nav_name').value;
    var currentValue = document.getElementById('aif_nav_current_value').value || 0;
    var currentDate = document.getElementById('aif_nav_current_date').value || '';
    var action = document.getElementById('aif_nav_action').value;
    
    if(!name) {
        document.getElementById('status').innerHTML = "Please select AIF Name";
        document.getElementById('status').style.color = 'red';
        return;
    }
    
    if(!currentValue || currentValue == 0) {
        document.getElementById('status').innerHTML = "Please enter NAV value";
        document.getElementById('status').style.color = 'red';
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_nav.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchData();
            resetFormByType('aif_nav');
        }
    };
    var data = "method=updateAifNav&tp=aif&UCC=" + encodeURIComponent(UCC) + 
               "&name=" + encodeURIComponent(name) +
               "&current_value=" + currentValue + 
               "&current_date=" + currentDate +
               "&action=" + action + 
               "&slno=" + slno;
    xhr.send(data);
}

// AIF Payout Record Function - EXACTLY matching working addaifpayout.php
function addAifPayoutRecord() {
    var UCC = currentUCC;
    var slno = document.getElementById('aif_payout_slno').value || 0;
    var name = document.getElementById('aif_payout_name').value;
    var payoutValue = document.getElementById('aif_payout_amount').value || 0;
    var payoutDate = document.getElementById('aif_payout_date').value || '';
    var remarks = document.getElementById('aif_payout_remarks').value || '';
    var action = document.getElementById('aif_payout_action').value;
    
    if(!name) {
        document.getElementById('status').innerHTML = "Please select AIF Name";
        document.getElementById('status').style.color = 'red';
        return;
    }
    
    if(!payoutValue || payoutValue == 0) {
        document.getElementById('status').innerHTML = "Please enter payout amount";
        document.getElementById('status').style.color = 'red';
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_nav.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchData();
            if(action !== "edit") {
                document.getElementById("aif_payout_amount").value = '';
                document.getElementById("aif_payout_date").value = '';
                document.getElementById("aif_payout_remarks").value = '';
            } else {
                resetFormByType('aif_payout');
            }
        }
    };
    var data = "method=updateAifPayout&tp=aif&UCC=" + encodeURIComponent(UCC) + 
               "&name=" + encodeURIComponent(name) +
               "&payout_amount=" + payoutValue + 
               "&payout_date=" + payoutDate +
               "&remarks=" + encodeURIComponent(remarks) +
               "&action=" + action + 
               "&slno=" + slno;
    xhr.send(data);
}

// Edit record - FIXED to match working addaifpayout.php
function editRecord(slno, type) {
    if (type === 'pms') {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'update_nav.php?method=getPmsNavDataBySlno&nav_slno=' + slno);
        xhr.onload = function() {
            var data = JSON.parse(xhr.responseText);
            document.getElementById("pms_name").value = data.pms_name;
            document.getElementById("pms_current_value").value = data.cur_value;
            document.getElementById("pms_current_date").value = data.nav_date;
            document.getElementById("pms_action").value = "edit";
            document.getElementById("pms_slno").value = slno;
            document.getElementById("pms_submit_btn").innerHTML = 'Update PMS';
            document.getElementById("formhead").innerHTML = "Edit PMS Current Value";
        };
        xhr.send();
    } 
    else if (type === 'aif_nav') {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'update_nav.php?method=getAifNavDataBySlno&nav_slno=' + slno);
        xhr.onload = function() {
            var data = JSON.parse(xhr.responseText);
            document.getElementById("aif_nav_name").value = data.aif_name;
            document.getElementById("aif_nav_current_value").value = data.cur_value;
            document.getElementById("aif_nav_current_date").value = data.nav_date;
            document.getElementById("aif_nav_action").value = "edit";
            document.getElementById("aif_nav_slno").value = slno;
            document.getElementById("aif_nav_submit_btn").innerHTML = 'Update AIF';
            document.getElementById("formhead").innerHTML = "Edit AIF Current NAV";
        };
        xhr.send();
    }
    else if (type === 'aif_payout') {
        // EXACTLY as in working addaifpayout.php - using 'slno' parameter, not 'nav_slno'
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'update_nav.php?method=getAifPayoutDataBySlno&slno=' + slno);
        xhr.onload = function() {
            var data = JSON.parse(xhr.responseText);
            document.getElementById("aif_payout_name").value = data.aif_name;
            document.getElementById("aif_payout_amount").value = data.payout_amount;
            document.getElementById("aif_payout_date").value = data.payout_date;
            document.getElementById("aif_payout_remarks").value = data.remarks || '';
            document.getElementById("aif_payout_action").value = "edit";
            document.getElementById("aif_payout_slno").value = slno;
            document.getElementById("aif_payout_submit_btn").innerHTML = 'Update AIF Payout';
            document.getElementById("formhead").innerHTML = "Edit AIF Payout";
        };
        xhr.send();
    }
}

// Delete record
function deleteRecord(investmentId, iName, type) {
    if (!confirm("Are you sure you want to delete " + iName + "?")) return;
    
    var method = '';
    if (type === 'pms') method = 'del_pms_nav';
    else if (type === 'aif_nav') method = 'del_aif_nav';
    else if (type === 'aif_payout') method = 'del_aif_payout';
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_nav.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchData();
            resetFormByType(currentNavType);
        }
    };
    xhr.send("method=" + method + "&investmentId=" + investmentId + "&iname=" + encodeURIComponent(iName));
}

// Fetch data based on current type
function fetchData() {
    var method = '';
    if (currentNavType === 'pms') method = 'getPmsNavData';
    else if (currentNavType === 'aif_nav') method = 'getAifNavData';
    else if (currentNavType === 'aif_payout') method = 'getAifPayoutData';
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_nav.php", true);
    xhr.responseType = 'json';
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            allData = xhr.response || [];
            filteredData = [...allData];
            currentPage = 1;
            document.getElementById('search_input').value = '';
            renderTable();
        }
    };
    xhr.send("method=" + method + "&UCC=" + encodeURIComponent(currentUCC));
}

// Search function
function searchData() {
    var searchTerm = document.getElementById('search_input').value.toLowerCase();
    
    if (searchTerm === '') {
        filteredData = [...allData];
    } else {
        filteredData = allData.filter(function(item) {
            var name = item.pms_name || item.aif_name || '';
            var date = item.nav_date || item.payout_date || '';
            var value = String(item.cur_value || item.payout_amount || '');
            var remarks = item.remarks || '';
            
            return name.toLowerCase().indexOf(searchTerm) !== -1 ||
                   date.toLowerCase().indexOf(searchTerm) !== -1 ||
                   value.toLowerCase().indexOf(searchTerm) !== -1 ||
                   remarks.toLowerCase().indexOf(searchTerm) !== -1;
        });
    }
    currentPage = 1;
    renderTable();
}

// Change page size
function changePageSize() {
    pageSize = parseInt(document.getElementById('entries_per_page').value);
    currentPage = 1;
    renderTable();
}

// Render table
function renderTable() {
    var tbody = document.getElementById('table_body');
    var totalRecords = filteredData.length;
    var totalPages = Math.ceil(totalRecords / pageSize);
    var startIndex = (currentPage - 1) * pageSize;
    var endIndex = Math.min(startIndex + pageSize, totalRecords);
    var pageData = filteredData.slice(startIndex, endIndex);
    
    tbody.innerHTML = '';
    if (pageData.length === 0) {
        var colspan = (currentNavType === 'aif_payout') ? 6 : 5;
        tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="no-data">No records found</td>' + (currentNavType === 'aif_payout' ? '<td style="display:none;"></td>' : '') + '</tr>';
        if (currentNavType === 'aif_payout') {
            tbody.innerHTML = '<td colspan="6" class="no-data">No records found</tr>';
        } else {
            tbody.innerHTML = '<td colspan="5" class="no-data">No records found</tr>';
        }
    } else {
        for (var i = 0; i < pageData.length; i++) {
            var item = pageData[i];
            var row = '<tr>';
            
            if (currentNavType === 'pms') {
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.pms_name || '') + '</td>';
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.nav_date || '') + '</td>';
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.cur_value || '') + '</td>';
                row += '<td style="padding:12px 16px;"><a href="#" class="action-btn" onclick="editRecord(' + item.slno + ', \'pms\'); return false;">Edit</a></td>';
                row += '<td style="padding:12px 16px;"><a href="#" class="action-btn delete-btn" onclick="deleteRecord(' + item.slno + ', \'' + escapeHtml(item.pms_name || '') + '\', \'pms\'); return false;">Delete</a></td>';
            } else if (currentNavType === 'aif_nav') {
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.aif_name || '') + '</td>';
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.nav_date || '') + '</td>';
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.cur_value || '') + '</td>';
                row += '<td style="padding:12px 16px;"><a href="#" class="action-btn" onclick="editRecord(' + item.slno + ', \'aif_nav\'); return false;">Edit</a></td>';
                row += '<td style="padding:12px 16px;"><a href="#" class="action-btn delete-btn" onclick="deleteRecord(' + item.slno + ', \'' + escapeHtml(item.aif_name || '') + '\', \'aif_nav\'); return false;">Delete</a></td>';
            } else if (currentNavType === 'aif_payout') {
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.aif_name || '') + '</td>';
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.payout_date || '') + '</td>';
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.payout_amount || '') + '</td>';
                row += '<td style="padding:12px 16px;">' + escapeHtml(item.remarks || '') + '</td>';
                row += '<td style="padding:12px 16px;"><a href="#" class="action-btn" onclick="editRecord(' + item.slno + ', \'aif_payout\'); return false;">Edit</a></td>';
                row += '<td style="padding:12px 16px;"><a href="#" class="action-btn delete-btn" onclick="deleteRecord(' + item.slno + ', \'' + escapeHtml(item.aif_name || '') + '\', \'aif_payout\'); return false;">Delete</a></td>';
            }
            
            row += '</tr>';
            tbody.innerHTML += row;
        }
    }
    
    var infoEl = document.getElementById('entries_info');
    if (totalRecords === 0) {
        infoEl.innerHTML = 'Showing 0 to 0 of 0 entries';
    } else {
        var info = 'Showing ' + (startIndex + 1) + ' to ' + endIndex + ' of ' + totalRecords + ' entries';
        if (allData.length !== totalRecords) info += ' (filtered from ' + allData.length + ' total entries)';
        infoEl.innerHTML = info;
    }
    
    renderPagination(totalPages);
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

// Render pagination
function renderPagination(totalPages) {
    var paginationDiv = document.getElementById('pagination_buttons');
    paginationDiv.innerHTML = '';
    if (totalPages <= 1) return;
    
    var prevBtn = document.createElement('button');
    prevBtn.className = 'page-button';
    prevBtn.innerHTML = 'Previous';
    prevBtn.disabled = (currentPage === 1);
    prevBtn.onclick = function() { if (currentPage > 1) { currentPage--; renderTable(); } };
    paginationDiv.appendChild(prevBtn);
    
    var maxButtons = 5;
    var startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    var endPage = Math.min(totalPages, startPage + maxButtons - 1);
    startPage = Math.max(1, endPage - maxButtons + 1);
    
    for (var i = startPage; i <= endPage; i++) {
        var pageBtn = document.createElement('button');
        pageBtn.className = 'page-button' + (i === currentPage ? ' active' : '');
        pageBtn.innerHTML = i;
        pageBtn.onclick = (function(page) { return function() { currentPage = page; renderTable(); }; })(i);
        paginationDiv.appendChild(pageBtn);
    }
    
    var nextBtn = document.createElement('button');
    nextBtn.className = 'page-button';
    nextBtn.innerHTML = 'Next';
    nextBtn.disabled = (currentPage >= totalPages);
    nextBtn.onclick = function() { if (currentPage < totalPages) { currentPage++; renderTable(); } };
    paginationDiv.appendChild(nextBtn);
}

// Select customer
function selectCustomer() {
    var UCC = document.getElementById("custUCC").value;
    if(UCC.trim() === "") {
        document.getElementById('status').innerHTML = "Please enter Customer UCC";
        document.getElementById('status').style.color = 'red';
        return;
    }
    getUCCName(UCC, function(response) {
        var rc = document.getElementById('status');
        if (response == false) {
            rc.style.color = 'red';
            rc.innerHTML = "Error: UCC " + UCC + " does not exist!";
            return;
        }
        rc.style.color = 'blue';
        rc.innerHTML = response;
        
        currentUCC = UCC;
        var managementDiv = document.getElementById("management_div");
        managementDiv.style.display = "none";
        switchNavType('pms');
        managementDiv.style.display = "block";
    });
}

// Get UCC name
function getUCCName(UCC, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "getInvesteddata.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            callback(JSON.parse(xhr.responseText));
        }
    };
    xhr.send("method=getUCCName&UCC=" + encodeURIComponent(UCC));
}

// Autocomplete for UCC
function fetchCustomerUCCs(input) {
    var val = input.value.toLowerCase();
    var list = document.getElementById("autocomplete-list");
    list.innerHTML = "";
    if (val === "") { list.style.display = "none"; return; }
    fetch("getUCClistwithname.php")
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var matching = data.filter(function(e) {
                return e.UCC.toLowerCase().indexOf(val) !== -1 || e.name.toLowerCase().indexOf(val) !== -1;
            });
            for (var i = 0; i < matching.length; i++) {
                var e = matching[i];
                var opt = document.createElement("div");
                opt.innerHTML = e.UCC + " - " + e.name;
                opt.addEventListener("click", function(ucc) { 
                    return function() { 
                        input.value = ucc; 
                        list.innerHTML = ""; 
                        list.style.display = "none"; 
                    };
                }(e.UCC));
                list.appendChild(opt);
            }
            list.style.display = matching.length > 0 ? "block" : "none";
        })
        .catch(function(err) { console.error(err); });
}

// Event listeners
var input = document.getElementById("custUCC");
input.addEventListener("input", function() { fetchCustomerUCCs(this); });
document.addEventListener("click", function(e) {
    if (e.target !== input) document.getElementById("autocomplete-list").style.display = "none";
});
</script>
</body>
</html>