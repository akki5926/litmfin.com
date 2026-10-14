<?php
require_once('auth_guard.php');

/**
 * NSE SIP Registration Report - Form Only
 * 
 * File: sipreportnse.php
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<style>
    #autocomplete-list {
        position: absolute;
        border: 1px solid #ccc;
        background-color: #fff;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    #autocomplete-list div {
        cursor: pointer;
        padding: 8px 12px;
        border-bottom: 1px solid #eee;
        font-size: 12px;
    }

    #autocomplete-list div:hover {
        background-color: #e9e9e9;
    }
    
    .sip-summary {
        margin-top: 15px;
        padding: 10px;
        background-color: #e8f5e9;
        border-left: 4px solid #4caf50;
        border-radius: 4px;
        font-size: 13px;
    }
</style>

<div class="container-fluid p-3">
    <h3>NSE SIP Registration Report</h3>
    <p class="text-muted">Fetch SIP registration data directly from NSE API</p>
    
    <div class="card mb-3">
        <div class="card-body">
            <form id="sipForm" onsubmit="return false;">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" id="fromDate" 
                               value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                               max="<?php echo date('Y-m-d'); ?>">
                        <small class="text-muted">Optional if Client Code is entered</small>
                    </div>
                    
                    <div class="col-md-3 mb-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" id="toDate" 
                               value="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d'); ?>">
                        <small class="text-muted">Optional if Client Code is entered</small>
                    </div>
                    
                    <div class="col-md-3 mb-2" style="position: relative;">
                        <label class="form-label">Client Code</label>
                        <input type="text" class="form-control" id="clientCode" 
                               placeholder="e.g., 5100004 or client name" autocomplete="off">
                        <div id="autocomplete-list" style="position: absolute; background: white; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; width: calc(100% - 2px); display: none; z-index: 1000;"></div>
                        <small class="text-muted">Enter client code or name to search (min 2 characters)</small>
                    </div>
                    
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" id="reportType">
                            <option value="SIP">SIP Registration</option>
                            <option value="SWP">SWP Registration</option>
                            <option value="BOTH" selected>Both (SIP & SWP)</option>
                        </select>
                    </div>

                    <div class="col-md-12 mb-2 d-flex align-items-end justify-content-end gap-2">
                        <button type="button" class="btn btn-info px-4 text-white" onclick="fetchAllSipData()">
                            <i class="fas fa-history"></i> Fetch All (Since 2019)
                        </button>
                        <button type="button" class="btn btn-secondary px-4" onclick="clearForm()">
                            <i class="fas fa-eraser"></i> Clear
                        </button>
                        <button type="button" class="btn btn-primary px-4" onclick="fetchSipData()">
                            <i class="fas fa-search"></i> Fetch Data
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div id="results"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
var autocompleteTimeout;

function escapeHtml(text) {
    if (!text) return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function fetchSipData() {
    // Get form values
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const clientCode = document.getElementById('clientCode').value.trim();
    const reportType = document.getElementById('reportType') ? document.getElementById('reportType').value : 'BOTH';
    
    // Validation logic
    if (!clientCode && (!fromDate || !toDate)) {
        alert('Please either:\n- Enter a Client Code, OR\n- Select both From Date and To Date');
        return;
    }
    
    // If dates are provided, validate them
    if (fromDate && toDate) {
        const from = new Date(fromDate);
        const to = new Date(toDate);
        const diffDays = Math.ceil((to - from) / (1000 * 60 * 60 * 24));
        
        if (diffDays < 0) {
            alert('To Date must be after From Date');
            return;
        }
    }
    
    // Show loading
    document.getElementById('results').innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Fetching data from NSE... This may take a while for large date ranges.</p></div>';
    
    // Prepare data for AJAX
    const postData = {
        clientCode: clientCode,
        reportType: reportType
    };
    
    // Only add dates if they are provided
    if (fromDate) postData.fromDate = fromDate;
    if (toDate) postData.toDate = toDate;
    
    // Make AJAX request to the separate endpoint
    $.ajax({
        url: 'getsipdataajax.php',
        type: 'POST',
        data: postData,
        dataType: 'json',
        success: function(response) {
            if (response.html) {
                document.getElementById('results').innerHTML = response.html;
            } else {
                document.getElementById('results').innerHTML = '<div class="alert alert-danger">Invalid response from server</div>';
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            document.getElementById('results').innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        }
    });
}

function fetchAllSipData() {
    const clientCode = document.getElementById('clientCode').value.trim();
    const reportType = document.getElementById('reportType') ? document.getElementById('reportType').value : 'BOTH';
    
    // Internally set from 1st Jan 2019 till today
    const fromDate = '2019-01-01';
    const today = new Date();
    const toDate = today.toISOString().split('T')[0];
    
    // Show loading
    document.getElementById('results').innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Fetching all data since Jan 1, 2019 from NSE... This may take a while.</p></div>';
    
    // Prepare data for AJAX
    const postData = {
        clientCode: clientCode,
        fromDate: fromDate,
        toDate: toDate,
        reportType: reportType
    };
    
    $.ajax({
        url: 'getsipdataajax.php',
        type: 'POST',
        data: postData,
        dataType: 'json',
        success: function(response) {
            if (response.html) {
                document.getElementById('results').innerHTML = response.html;
            } else {
                document.getElementById('results').innerHTML = '<div class="alert alert-danger">Invalid response from server</div>';
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            document.getElementById('results').innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        }
    });
}

function clearForm() {
    // Clear all input fields
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    document.getElementById('clientCode').value = '';
    
    // Clear autocomplete list
    const autocompleteList = document.getElementById('autocomplete-list');
    if (autocompleteList) {
        autocompleteList.innerHTML = '';
        autocompleteList.style.display = 'none';
    }
    
    // Clear results
    document.getElementById('results').innerHTML = '';
    
    // Optional: Show a brief message
    // alert('Form cleared');
}

// Autocomplete functionality for Client Code
function fetchClientCodes(input) {
    var val = input.value.trim();
    var autocompleteList = document.getElementById('autocomplete-list');
    
    // Clear previous timeout
    if (autocompleteTimeout) {
        clearTimeout(autocompleteTimeout);
    }
    
    // Don't search if less than 2 characters
    if (val.length < 2) {
        autocompleteList.style.display = 'none';
        autocompleteList.innerHTML = '';
        return;
    }
    
    // Debounce the search
    autocompleteTimeout = setTimeout(function() {
        console.log('Searching for client:', val);
        
        fetch("getUCClistwithname.php")
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Received client data:', data);
                autocompleteList.innerHTML = "";
                
                var matchingClients = data.filter(function(entry) {
                    return entry.UCC.toLowerCase().indexOf(val.toLowerCase()) !== -1 || 
                           entry.name.toLowerCase().indexOf(val.toLowerCase()) !== -1;
                });
                
                console.log('Matching clients:', matchingClients.length);
                
                if (matchingClients.length > 0 && matchingClients.length <= 30) {
                    matchingClients.forEach(function(entry) {
                        var option = document.createElement("div");
                        option.style.padding = "8px 12px";
                        option.style.cursor = "pointer";
                        option.style.borderBottom = "1px solid #eee";
                        option.style.fontSize = "12px";
                        option.innerHTML = `<strong>${escapeHtml(entry.UCC)}</strong> - ${escapeHtml(entry.name)}`;
                        option.onclick = function() {
                            input.value = entry.UCC;
                            autocompleteList.innerHTML = "";
                            autocompleteList.style.display = "none";
                            // Optionally auto-fetch after selection
                            // fetchSipData();
                        };
                        autocompleteList.appendChild(option);
                    });
                    autocompleteList.style.display = "block";
                    autocompleteList.style.width = input.offsetWidth + "px";
                } else if (matchingClients.length > 30) {
                    autocompleteList.innerHTML = '<div style="padding: 8px; color: #666;">Too many results, please refine your search</div>';
                    autocompleteList.style.display = "block";
                } else {
                    autocompleteList.style.display = "none";
                }
            })
            .catch(error => {
                console.error('Error fetching client list:', error);
                autocompleteList.innerHTML = '<div style="padding: 8px; color: red;">Error loading client data</div>';
                autocompleteList.style.display = "block";
            });
    }, 300); // 300ms debounce
}


// Set max date for toDate on page load
(function() {
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    const fromDate = document.getElementById('fromDate');
    const toDate = document.getElementById('toDate');
    
    if (toDate) toDate.max = todayStr;
    if (fromDate) fromDate.max = todayStr;
    
    // Set default dates if not set
    if (fromDate && !fromDate.value) {
        fromDate.value = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    }
    if (toDate && !toDate.value) {
        toDate.value = todayStr;
    }
})();

// Autocomplete setup for client code input (using event delegation for AJAX compatibility)
var jq = window.jQuery || window.$;
if (jq) {
    jq(document).off("input.autoSip").on("input.autoSip", "#clientCode", function() {
        fetchClientCodes(this);
    });
    
    jq(document).off("click.autoSip").on("click.autoSip", function(e) {
        if (e.target.id !== "clientCode" && e.target.id !== "autocomplete-list") {
            var list = document.getElementById('autocomplete-list');
            if (list) list.style.display = 'none';
        }
    });
    
    jq(document).off("keydown.autoSip").on("keydown.autoSip", "#clientCode", function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var list = document.getElementById('autocomplete-list');
            if (list) list.style.display = 'none';
            fetchSipData();
        }
    });
}

// Optional: Add keyboard shortcut (Ctrl+Shift+C) to clear form
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'C') {
        e.preventDefault();
        clearForm();
    }
});
</script>