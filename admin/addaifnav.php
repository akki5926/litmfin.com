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
    <title>AIF NAV Management</title>
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

        .investment-section { background:white; border-radius:20px; padding:28px; margin-bottom:25px; box-shadow:0 10px 40px rgba(0,0,0,0.1); }
        .section-header { border-bottom:3px solid #667eea; padding-bottom:14px; margin-bottom:24px; }
        .section-header h3 { color:#333; font-size:18px; font-weight:700; }

        .form-inline { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end; margin-bottom:20px; }
        .input-field { display:flex; flex-direction:column; flex:1; min-width:180px; }
        .input-field label { font-size:12px; font-weight:600; color:#555; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.5px; }
        .input-field input, .input-field select { padding:10px 13px; border:2px solid #e0e0e0; border-radius:11px; font-size:14px; transition:all 0.3s; background:#fafafa; color:#333; }
        .input-field input:focus, .input-field select:focus { outline:none; border-color:#667eea; background:white; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }
        .field-action { display:flex; align-items:flex-end; }

        /* NEW TABLE CONTROLS */
        .data-controls { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin:20px 0; }
        .entries-selector { display:flex; align-items:center; gap:8px; font-size:13px; color:#555; }
        .entries-selector select { padding:6px 10px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px; background:#fafafa; cursor:pointer; }
        .search-box { display:flex; align-items:center; gap:8px; font-size:13px; color:#555; }
        .search-box input { padding:7px 12px; border:2px solid #e0e0e0; border-radius:8px; font-size:13px; width:220px; background:#fafafa; }

        /* NEW TABLE STYLES */
        .data-table-container { overflow-x:auto; border-radius:14px; border:1px solid #e0e0e0; background:white; }
        .nav-table { width:100%; border-collapse:collapse; font-size:13px; }
        .nav-table thead { background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .nav-table th { padding:14px 16px; text-align:left; font-weight:600; color:white; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        .nav-table td { padding:12px 16px; border-bottom:1px solid #f0f0f0; color:#444; }
        .nav-table tbody tr:hover { background:#faf5ff; }
        .nav-table tbody tr:nth-child(even) { background:#fafafa; }

        /* NEW PAGINATION */
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

        @media(max-width:768px){ body{padding:10px;} .form-row,.form-inline{flex-direction:column;} .form-group,.input-field{width:100%;} .data-controls,.pagination-footer{flex-direction:column;align-items:flex-start;} }
    </style>
</head>
<body>
<div class="container">

    <div class="header-card">
        <h1>AIF NAV Management</h1>
        <p>Update and track Alternative Investment Fund NAV values</p>
    </div>

    <div class="selection-panel">
        <form onsubmit="showDiv(); return false;">
            <div class="form-row">
                <div class="form-group">
                    <label>Customer UCC</label>
                    <input type="text" name="custUCC" id="custUCC" placeholder="Enter UCC or Name" autocomplete="off">
                    <div id="autocomplete-list"></div>
                </div>
                <div class="form-group" style="flex:0;">
                    <label>&nbsp;</label>
                    <button type="submit" name="investment_type_btn" class="btn btn-orange">Select UCC</button>
                </div>
                <div class="form-group" style="flex:0; min-width:160px;">
                    <label>&nbsp;</label>
                    <div id="status"></div>
                </div>
            </div>
        </form>
    </div>

    <div id="aif_div" style="display:none;">
        <div class="investment-section">
            <div class="section-header">
                <h3 id="formhead">Update AIF NAVs</h3>
            </div>
            
            <form id="add_aif" onsubmit="addAif(); return false;">
                <div class="form-inline">
                    <div class="input-field">
                        <label>AIF Name</label>
                        <select id="aif_name" name="aif_name" required></select>
                    </div>
                    <div class="input-field">
                        <label>Date</label>
                        <input type="date" id="aif_current_date" name="aif_current_date">
                    </div>
                    <div class="input-field">
                        <label>Current NAV</label>
                        <input type="text" id="aif_current_value" name="aif_current_value" required placeholder="Enter NAV" oninput="this.value=this.value.replace(/[^0-9.]/g,'');">
                    </div>
                    <div class="field-action">
                        <input type="hidden" id="aif_action" name="aif_action" value="add">
                        <input type="hidden" id="aif_slno" name="aif_slno" value="0">
                        <button type="submit" name="add_aif" class="btn btn-orange">Update NAV</button>
                    </div>
                </div>
            </form>

            <!-- NEW TABLE CONTROLS -->
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
                    Search: <input type="text" id="search_input" placeholder="Search by name, date or NAV..." onkeyup="searchData()">
                </div>
            </div>

            <!-- NEW TABLE -->
            <div class="data-table-container">
                <table class="nav-table" id="nav_table">
                    <thead>
                            <th>AIF Name</th>
                            <th>Date</th>
                            <th>NAV Value</th>
                            <th width="80">Edit</th>
                            <th width="80">Delete</th>
                        </thead>
                    <tbody id="table_body">
                        <tr><td colspan="5" class="no-data">No records found</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- NEW PAGINATION -->
            <div class="pagination-footer">
                <div class="entries-info" id="entries_info">Showing 0 to 0 of 0 entries</div>
                <div class="pagination-buttons" id="pagination_buttons"></div>
            </div>
        </div>
    </div>

</div>

<script>
// Data storage
var allNavData = [];
var filteredData = [];
var currentPage = 1;
var pageSize = 10;

// Search function
function searchData() {
    var searchTerm = document.getElementById('search_input').value.toLowerCase();
    
    if (searchTerm === '') {
        filteredData = [...allNavData];
    } else {
        filteredData = allNavData.filter(function(item) {
            return (item.aif_name && item.aif_name.toLowerCase().indexOf(searchTerm) !== -1) ||
                   (item.nav_date && item.nav_date.toLowerCase().indexOf(searchTerm) !== -1) ||
                   (item.cur_value && String(item.cur_value).toLowerCase().indexOf(searchTerm) !== -1);
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

// Render table with pagination
function renderTable() {
    var tbody = document.getElementById('table_body');
    var totalRecords = filteredData.length;
    var totalPages = Math.ceil(totalRecords / pageSize);
    var startIndex = (currentPage - 1) * pageSize;
    var endIndex = Math.min(startIndex + pageSize, totalRecords);
    var pageData = filteredData.slice(startIndex, endIndex);
    
    // Clear table body
    tbody.innerHTML = '';
    
    // Populate table
    if (pageData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="no-data">No records found</td></tr>';
    } else {
        for (var i = 0; i < pageData.length; i++) {
            var item = pageData[i];
            var row = tbody.insertRow();
            
            row.insertCell(0).innerHTML = item.aif_name || '';
            row.insertCell(1).innerHTML = item.nav_date || '';
            row.insertCell(2).innerHTML = item.cur_value || '';
            row.insertCell(3).innerHTML = '<a href="#" class="action-btn" onclick="editRecord(' + item.slno + '); return false;">Edit</a>';
            row.insertCell(4).innerHTML = '<a href="#" class="action-btn delete-btn" onclick="deleteRecord(' + item.slno + ', \'' + (item.aif_name || '') + '\'); return false;">Delete</a>';
        }
    }
    
    // Update entries info
    var infoEl = document.getElementById('entries_info');
    if (totalRecords === 0) {
        infoEl.innerHTML = 'Showing 0 to 0 of 0 entries';
    } else {
        var info = 'Showing ' + (startIndex + 1) + ' to ' + endIndex + ' of ' + totalRecords + ' entries';
        if (allNavData.length !== totalRecords) {
            info += ' (filtered from ' + allNavData.length + ' total entries)';
        }
        infoEl.innerHTML = info;
    }
    
    // Render pagination buttons
    renderPagination(totalPages);
}

// Render pagination buttons
function renderPagination(totalPages) {
    var paginationDiv = document.getElementById('pagination_buttons');
    paginationDiv.innerHTML = '';
    
    if (totalPages <= 1) return;
    
    // Previous button
    var prevBtn = document.createElement('button');
    prevBtn.className = 'page-button';
    prevBtn.innerHTML = 'Previous';
    prevBtn.disabled = (currentPage === 1);
    prevBtn.onclick = function() {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    };
    paginationDiv.appendChild(prevBtn);
    
    // Page numbers
    var maxButtons = 5;
    var startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    var endPage = Math.min(totalPages, startPage + maxButtons - 1);
    startPage = Math.max(1, endPage - maxButtons + 1);
    
    for (var i = startPage; i <= endPage; i++) {
        var pageBtn = document.createElement('button');
        pageBtn.className = 'page-button';
        if (i === currentPage) pageBtn.classList.add('active');
        pageBtn.innerHTML = i;
        pageBtn.onclick = (function(page) {
            return function() {
                currentPage = page;
                renderTable();
            };
        })(i);
        paginationDiv.appendChild(pageBtn);
    }
    
    // Next button
    var nextBtn = document.createElement('button');
    nextBtn.className = 'page-button';
    nextBtn.innerHTML = 'Next';
    nextBtn.disabled = (currentPage >= totalPages);
    nextBtn.onclick = function() {
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    };
    paginationDiv.appendChild(nextBtn);
}

// Show div and load data
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
    var rc = document.getElementById('status');
    if (response == false) {
        rc.style.color = 'red';
        rc.innerHTML = "Error: UCC " + UCC + " does not exist!";
        return;
    }
    rc.style.color = 'blue';
    rc.innerHTML = response;

    var aifDiv = document.getElementById("aif_div");
    aifDiv.style.display = "none";
    resetForm();
    aifDiv.style.display = "block";
    fetchNavData();
    updateAifNames(UCC);
}

// Add/Update AIF NAV
function addAif() {
    var UCC = document.getElementById("custUCC").value;
    var slno = document.getElementById("aif_slno").value || 0;
    var name = document.getElementById("aif_name").value;
    var currentValue = document.getElementById("aif_current_value").value || 0;
    var currentDate = document.getElementById("aif_current_date").value || '';
    var action = document.getElementById("aif_action").value;
    
    if(!name) {
        document.getElementById('status').innerHTML = "Please select AIF Name";
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
            fetchNavData();
            if(action !== "edit") {
                document.getElementById("aif_current_value").value = '';
                document.getElementById("aif_current_date").value = '';
            }
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

// Edit record
function editRecord(slno) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'update_nav.php?method=getAifNavDataBySlno&nav_slno=' + slno);
    xhr.onload = function() {
        var data = JSON.parse(xhr.responseText);
        document.getElementById("aif_name").value = data.aif_name;
        document.getElementById("aif_current_value").value = data.cur_value;
        document.getElementById("aif_current_date").value = data.nav_date;
        document.querySelector('button[name="add_aif"]').innerHTML = 'Update AIF';
        document.getElementById("aif_action").value = "edit";
        document.getElementById("aif_slno").value = slno;
        document.getElementById("formhead").innerHTML = "Edit AIF Current NAV";
    };
    xhr.send();
}

// Delete record
function deleteRecord(investmentId, iName) {
    if (!confirm("Are you sure you want to delete " + iName + "?")) return;
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_nav.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = xhr.responseText;
            var rc = document.getElementById('status');
            rc.style.color = response.includes("Error") ? 'red' : 'green';
            rc.innerHTML = response;
            fetchNavData();
            var actionInput = document.getElementById("aif_action");
            if (actionInput.value === "edit") {
                actionInput.value = "add";
                document.querySelector('button[name="add_aif"]').innerHTML = 'Update NAV';
                document.getElementById("aif_slno").value = 0;
                document.getElementById("formhead").innerHTML = "Update AIF Current NAV";
                document.getElementById("aif_name").value = "";
                document.getElementById("aif_current_value").value = "";
                document.getElementById("aif_current_date").value = "";
            }
        }
    };
    xhr.send("method=del_aif_nav&investmentId=" + investmentId + "&iname=" + encodeURIComponent(iName));
}

// Fetch NAV data
function fetchNavData() {
    var UCC = document.getElementById("custUCC").value;
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "update_nav.php", true);
    xhr.responseType = 'json';
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            allNavData = xhr.response || [];
            filteredData = [...allNavData];
            currentPage = 1;
            document.getElementById('search_input').value = '';
            renderTable();
        }
    };
    xhr.send("method=getAifNavData&UCC=" + encodeURIComponent(UCC));
}

// Reset form
function resetForm() {
    document.getElementById("formhead").innerHTML = "Update AIF Current NAV";
    document.getElementById("aif_name").value = "";
    document.getElementById("aif_slno").value = "";
    document.getElementById("aif_current_value").value = "";
    document.getElementById("aif_current_date").value = "";
    document.getElementById("search_input").value = "";
    document.querySelector('button[name="add_aif"]').innerHTML = 'Update NAV';
    document.getElementById("aif_action").value = "add";
    allNavData = [];
    filteredData = [];
    currentPage = 1;
    renderTable();
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
    if (val === "") { 
        list.style.display = "none"; 
        return; 
    }
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

// Update AIF names dropdown
function updateAifNames(UCC) {
    var sel = document.getElementById("aif_name");
    sel.innerHTML = '<option value="">Loading...</option>';
    if (UCC !== "") {
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                sel.innerHTML = xhr.responseText;
            }
        };
        xhr.open("GET", "getInvesteddata.php?method=getAifNames&UCC=" + encodeURIComponent(UCC), true);
        xhr.send();
    }
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