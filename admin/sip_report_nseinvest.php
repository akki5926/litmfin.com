<?php
require_once('auth_guard.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/database-nseinvest-Api.php';

$output = '';
$mfdb = new DB_NSEINVEST_API();
?>
<style>
    .sip-form-card {
        background: #ffffff;
        border: 1px solid #dcdcdc;
        border-radius: 6px;
        padding: 16px 18px;
        margin-bottom: 15px;
        font-size: 13px;
    }

    .sip-form-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 12px;
    }

    .sip-form-row {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .sip-form-group {
        display: flex;
        flex-direction: column;
        min-width: 200px;
        position: relative;
    }

    .sip-form-group label {
        font-size: 12px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 4px;
    }

    .sip-form-control {
        padding: 7px 8px;
        font-size: 13px;
        border-radius: 4px;
        border: 1px solid #cfd6de;
    }

    .sip-btn {
        background-color: #FD7101;
        color: #ffffff;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
    }

    .sip-btn:hover {
        background-color: #e86400;
    }

    #autocomplete-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 999;
        border: 1px solid #ccc;
        background-color: #ffffff;
        max-height: 200px;
        overflow-y: auto;
    }

    #autocomplete-list div {
        padding: 6px 8px;
        cursor: pointer;
    }

    #autocomplete-list div:hover {
        background-color: #f0f2f5;
    }

    .sip-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin-top: 15px;
    }

    .sip-table th {
        background: #2c3e50;
        color: white;
        padding: 8px;
        text-align: left;
    }

    .sip-table td {
        padding: 6px 8px;
        border-bottom: 1px solid #ddd;
    }

    .sip-table tr:nth-child(even) {
        background: #f9f9f9;
    }
</style>

<form method="post">
    <div class="sip-form-card">
        <div class="sip-form-title">
            SIP History (NSE API) — Maximum Period: 30 Days
        </div>

        <div class="sip-form-row">
            <div class="sip-form-group">
                <label for="fromDate">From Date</label>
                <input type="date" class="sip-form-control" name="fromDate" id="fromDate" 
                       onclick="disablefuturedate()" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
            </div>

            <div class="sip-form-group">
                <label for="toDate">To Date</label>
                <input type="date" class="sip-form-control" name="toDate" id="toDate" 
                       onclick="disablefuturedate()" value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="sip-form-group">
                <label for="custIIN">Customer IIN / UCC</label>
                <input type="text" class="sip-form-control" name="custIIN" id="custIIN" autocomplete="off">
                <div id="autocomplete-list"></div>
            </div>

            <div class="sip-form-group">
                <button type="button" class="sip-btn" onclick="fetchSIPReport()">
                    Fetch SIP Report
                </button>
            </div>
        </div>
    </div>
</form>

<div id="result" style="margin-top:15px;"></div>

<?php echo $output; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function fetchSIPReport() {
    var fromDate = document.getElementById('fromDate').value;
    var toDate = document.getElementById('toDate').value;
    var custIIN = document.getElementById("custIIN").value;

    if (!fromDate || !toDate) {
        alert("Please select both From Date and To Date");
        return;
    }

    var from = new Date(fromDate);
    var to = new Date(toDate);
    var diffDays = (to - from) / (1000 * 60 * 60 * 24);

    if (diffDays > 30) {
        alert("Date range cannot exceed 30 days");
        return;
    }

    if (diffDays < 0) {
        alert("To Date must be after From Date");
        return;
    }

    document.getElementById("result").innerHTML = "<p>Loading...</p>";

    $.ajax({
        url: "data_manuplation.php",
        type: "GET",
        dataType: "json",
        data: {
            method: "sipreportsNseInvest",
            fromDate: fromDate,
            toDate: toDate,
            custIIN: custIIN
        },
        success: function(response) {
            console.log("Response:", response); // Debug: see what we get
            if (response && response.status === 'success') {
                displaySIPData(response.records);
            } else {
                document.getElementById("result").innerHTML = `<p style="color:red">Error: ${response ? response.message : 'Unknown error'}</p>`;
            }
        },
        error: function(xhr, status, error) {
            console.log("Error:", xhr.responseText); // Debug: see error details
            document.getElementById("result").innerHTML = `<p style="color:red">Error: ${error}</p>`;
        }
    });
}

function displaySIPData(records) {
    if (!records || records.length === 0) {
        document.getElementById("result").innerHTML = "<p class='text-center text-muted p-4'>No SIP records found</p>";
        return;
    }

    // Group records by client code for summary
    let clientsWithData = {};
    records.forEach(record => {
        let client = record.client_code || 'Unknown';
        if (!clientsWithData[client]) {
            clientsWithData[client] = 0;
        }
        clientsWithData[client]++;
    });

    // Create summary HTML
    let summaryHtml = `
        <div class="alert alert-info mb-3">
            <strong>Summary:</strong> Found ${records.length} SIP records 
            across ${Object.keys(clientsWithData).length} clients
        </div>
    `;

    let html = summaryHtml + '<table class="sip-table">';
    html += '<thead><tr>';
    html += '<th>#</th>';
    html += '<th>Client Code</th>';
    html += '<th>Client Name</th>';
    html += '<th>Reg Date</th>';
    html += '<th>Reg No</th>';
    html += '<th>Scheme</th>';
    html += '<th>Amount</th>';
    html += '<th>Frequency</th>';
    html += '<th>Start Date</th>';
    html += '<th>End Date</th>';
    html += '<th>Status</th>';
    html += '</tr></thead><tbody>';

    records.forEach(function(row, i) {
        let status = row.status || 'ACTIVE';
        let statusColor = status === 'ACTIVE' ? 'green' : (status === 'SUSPENDED' ? 'orange' : 'red');
        
        html += '<tr>';
        html += `<td>${i+1}</td>`;
        html += `<td>${row.client_code || '-'}</td>`;
        html += `<td>${row.client_name || '-'}</td>`;
        html += `<td>${row.xsip_registration_date || '-'}</td>`;
        html += `<td>${row.xsip_registration_no || '-'}</td>`;
        html += `<td>${row.scheme_name || '-'}</td>`;
        html += `<td style="text-align:right">₹ ${parseFloat(row.installments_amount || 0).toFixed(2)}</td>`;
        html += `<td>${row.frequency_type || '-'}</td>`;
        html += `<td>${row.start_date || '-'}</td>`;
        html += `<td>${row.end_date || '-'}</td>`;
        html += `<td style="color:${statusColor}">${status}</td>`;
        html += '</tr>';
    });

    html += '</tbody></table>';
    document.getElementById("result").innerHTML = html;
}

function disablefuturedate() {
    var today = new Date().toISOString().split('T')[0];
    document.getElementsByName("fromDate")[0].setAttribute('max', today);
    document.getElementsByName("toDate")[0].setAttribute('max', today);
}

function fetchCustomerIINs(input) {
    var val = input.value.toLowerCase();
    var list = document.getElementById("autocomplete-list");
    list.innerHTML = "";

    if (val === "") {
        list.style.display = "none";
        return;
    }

    fetch("getUCClistwithname.php")
        .then(r => r.json())
        .then(data => {
            let matches = data.filter(e => e.iin.indexOf(val) !== -1 || e.name.toLowerCase().indexOf(val) !== -1);
            matches.slice(0, 10).forEach(e => {
                let div = document.createElement("div");
                div.innerHTML = `${e.iin} - ${e.name}`;
                div.onclick = function() {
                    input.value = e.iin;
                    list.innerHTML = "";
                };
                list.appendChild(div);
            });
            list.style.display = matches.length ? "block" : "none";
        });
}

document.getElementById("custIIN").addEventListener("input", function() {
    fetchCustomerIINs(this);
});

(function () {
    const fromDate = document.getElementById('fromDate');
    const toDate = document.getElementById('toDate');
    if (!fromDate || !toDate) return;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const formatDate = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    };

    toDate.max = formatDate(today);
    fromDate.max = formatDate(today);

    fromDate.addEventListener('change', function() {
        if (!this.value) return;
        const from = new Date(this.value);
        let calculatedTo = new Date(from);
        calculatedTo.setDate(calculatedTo.getDate() + 30);
        if (calculatedTo > today) calculatedTo = new Date(today);
        toDate.value = formatDate(calculatedTo);
    });
})();
</script>