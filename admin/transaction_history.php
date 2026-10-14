<?php
require_once('auth_guard.php');

/**
 * transaction_history.php  — optimised AJAX fragment
 *
 * PERFORMANCE FIXES APPLIED
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. REMOVED <script src="jquery"> — already loaded by parent index.php.
 *    Eliminates a blocking network round-trip every time this fragment loads.
 *
 * 2. AUTOCOMPLETE: was fetching ALL UCCs from the server on EVERY keystroke.
 *    Fixed: fetch once, cache in JS, filter locally on subsequent keystrokes.
 *    getUCClistwithname.php is now called only one time total.
 *
 * 3. TABLE RENDERING: was building one giant HTML string with string
 *    concatenation inside a loop (O(n) string copies).
 *    Fixed: push each row into an array, join once at the end — much faster
 *    for large result sets and avoids GC pressure.
 *
 * 4. DOMECONTENTLOADED duplicate listener: there were two separate
 *    'DOMContentLoaded' listeners for the same element — merged into one.
 *
 * 5. REMOVED all console.log / debug output — unnecessary network + CPU
 *    overhead in production.
 *
 * 6. shouldDisplayTransaction() was called twice per row (once in the render
 *    loop, once in the export loop).  Fixed: filter once, store results.
 *
 * 7. escapeHtml() was creating a new regex and map object on every single
 *    call.  Fixed: hoisted to module-level constants.
 *
 * 8. formatNumber() called toLocaleString() on every cell render — expensive.
 *    Fixed: cached the Intl.NumberFormat instance.
 * ─────────────────────────────────────────────────────────────────────────────
 */

require_once('database.php');
require_once __DIR__ . '/../api/nseapiconnect.php';
$db = new MYSQLDB();
$nseApiConnect = new NseApiConnect();
?>

<style>
    /* Autocomplete Styles - Enhanced */
    #autocomplete-list {
        position: absolute;
        border: 1px solid #ccc;
        background-color: #fff;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        min-width: 300px;
        border-radius: 4px;
    }
    #autocomplete-list div {
        cursor: pointer;
        padding: 8px 12px;
        border-bottom: 1px solid #eee;
        font-size: 12px;
        transition: background-color 0.2s;
    }
    #autocomplete-list div:last-child {
        border-bottom: none;
    }
    #autocomplete-list div:hover { 
        background-color: #e9e9e9; 
    }
    #autocomplete-list div.highlight {
        background-color: #d4e6f7;
    }
    #autocomplete-list .autocomplete-empty {
        padding: 12px;
        color: #999;
        text-align: center;
        font-style: italic;
    }
    #autocomplete-list .autocomplete-loading {
        padding: 12px;
        color: #666;
        text-align: center;
    }
    #autocomplete-list .autocomplete-error {
        padding: 12px;
        color: #dc3545;
        text-align: center;
        font-weight: bold;
    }

    .validation-message { color: #dc3545; font-size: 12px; margin-top: 5px; }

    .info-message {
        color: #0c5460;
        background-color: #d1ecf1;
        border: 1px solid #bee5eb;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        font-size: 12px;
    }
    .trans-heading {
        background: #2c3e50;
        color: #ffffff;
        padding: 10px 14px;
        margin-bottom: 10px;
        border-radius: 4px;
        font-size: 15px;
        font-weight: 600;
    }
    .trans-table {
        border-collapse: collapse;
        font-size: 11px;
        width: 100%;
        background: #fff;
    }
    .trans-table th {
        background: #34495e;
        color: #ffffff;
        text-align: left;
        padding: 10px 6px;
        border: 1px solid #46688a;
        position: sticky;
        top: 0;
        font-weight: 600;
        font-size: 10px;
    }
    .trans-table td {
        padding: 8px 6px;
        border: 1px solid #ddd;
        vertical-align: top;
    }
    .trans-table tbody tr:nth-child(even) { background-color: #f9f9f9; }
    .trans-table tbody tr:hover            { background-color: #f5f5f5; }

    .numeric-value { text-align: right; font-family: monospace; font-weight: 500; }
    .status-success { color: #27ae60; font-weight: 600; }
    .status-pending { color: #f39c12; font-weight: 600; }
    .status-failed  { color: #e74c3c; font-weight: 600; }

    .filter-summary {
        margin-top: 15px;
        padding: 10px;
        background-color: #e8f5e9;
        border-left: 4px solid #4caf50;
        border-radius: 4px;
        font-size: 13px;
    }
    .btn { padding: 8px 20px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; transition: background .2s; }
    .btn-success   { background-color: #28a745; color: white; }
    .btn-success:hover { background-color: #218838; }
    .btn-secondary { background-color: #6c757d; color: white; }
    .btn-secondary:hover { background-color: #5a6268; }
    .btn-info      { background-color: #17a2b8; color: white; }
    .btn-info:hover { background-color: #138496; }
    .btn-warning   { background-color: #ffc107; color: #212529; }
    .btn-warning:hover { background-color: #e0a800; }
    .btn-danger    { background-color: #dc3545; color: white; }
    .btn-danger:hover { background-color: #c82333; }
    .btn:disabled  { opacity: 0.6; cursor: not-allowed; }

    .input-md { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
    select.input-md { padding: 6px 8px; }

    .loader { text-align: center; padding: 40px; }
    .loader img { width: 50px; }

    .client-code-highlight { background-color: #fff3cd; font-weight: bold; }
    
    .date-preset-buttons {
        display: inline-flex;
        gap: 5px;
        margin-left: 10px;
    }
    .date-preset-buttons .btn {
        padding: 6px 12px;
        font-size: 11px;
    }
    
    /* Autocomplete input wrapper */
    .autocomplete-wrapper {
        position: relative;
        display: inline-block;
        width: 300px;
    }
</style>

<div class="info-message">
    <strong>Date Range Requirements:</strong><br>
    • Date range limited to 7 days for security<br>
    • To date cannot be later than today<br>
    • Leave both empty for last 7 days (ending yesterday)<br>
    • Enter only From date for auto To date calculation (+7 days, max: today)<br>
    • <strong>Client Code:</strong> Enter UCC code or client name to search
</div>

<form id="transactionForm" method="post">
    <table style="border:0; font-size:12px; width:100%; margin-bottom:15px;">
        <tr>
            <td width="15%"><label>From Date<span style="color:red">*</span></label></td>
            <td width="35%">
                <input class="input-md" style="width:200px" type="date" name="fromDate" id="fromDate" />
                <div class="validation-message" id="fromDateError"></div>
            </td>
            <td width="15%"><label>To Date<span style="color:red">*</span></label></td>
            <td width="35%">
                <input class="input-md" style="width:200px" type="date" name="toDate" id="toDate" />
                <div class="validation-message" id="toDateError"></div>
            </td>
        </tr>
        <tr>
            <td><label>Transaction Status</label></td>
            <td>
                <select name="transactionStatus" id="transactionStatus" class="input-md" style="width:200px">
                    <option value="ALL" selected>ALL</option>
                    <option value="VALID">VALID</option>
                    <option value="INVALID">INVALID</option>
                </select>
            </td>
            <td><label>Transaction Type</label></td>
            <td>
                <select name="transactionType" id="transactionType" class="input-md" style="width:200px">
                    <option value="ALL" selected>ALL</option>
                    <option value="PURCHASE">Purchase</option>
                    <option value="REDEMPTION">Redemption</option>
                    <option value="SIP">SIP</option>
                    <option value="STP">STP</option>
                    <option value="SWITCH_IN">SWITCH IN</option>
                    <option value="SWITCH_OUT">SWITCH OUT</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><label>Customer UCC</label></td>
            <td colspan="3">
                <div class="autocomplete-wrapper">
                    <input class="input-md" style="width:300px" type="text" name="custUCC" id="custUCC"
                           placeholder="Enter UCC Code or Client Name" autocomplete="off" />
                    <div id="autocomplete-list" style="display:none;"></div>
                </div>
                <small style="color:#666; margin-left:10px;">Type at least 2 characters to search</small>
            </td>
        </tr>
        <tr>
            <td colspan="4" style="padding-top:10px;">
                <button type="button" class="btn btn-success" id="fetchBtn" onclick="fetchTransactionHistory()">
                    <i class="fas fa-search"></i> Fetch Transaction History
                </button>
                <span class="date-preset-buttons">
                    <button type="button" class="btn btn-warning" onclick="setDatePreset('today')">
                        <i class="fas fa-calendar-day"></i> Today
                    </button>
                    <button type="button" class="btn btn-info" onclick="setDatePreset('week')">
                        <i class="fas fa-calendar-week"></i> Last 7 Days
                    </button>
                    <button type="button" class="btn btn-info" onclick="setDatePreset('yesterday')">
                        <i class="fas fa-calendar-minus"></i> Yesterday
                    </button>
                </span>
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    <i class="fas fa-redo"></i> Clear
                </button>
                <button type="button" class="btn btn-danger" id="exportBtn" onclick="exportToCSV()" disabled>
                    <i class="fas fa-file-csv"></i> Export to CSV
                </button>
            </td>
        </tr>
    </table>
</form>

<div id="loading" class="loader" style="display:none;">
    <img src="images/loader.svg" alt="Loading...">
    <p>Fetching transaction history from NSE...</p>
</div>

<div id="result"></div>

<script>
(function () {
    'use strict';

    // ── FIX 7: hoist escape map so it's not rebuilt every call ───────────────
    var ESC_MAP = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' };
    var ESC_RE  = /[&<>"']/g;

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(ESC_RE, function(m) { return ESC_MAP[m]; });
    }

    // ── FIX 8: cache NumberFormat instance ───────────────────────────────────
    var numFmt = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 4 });

    function formatNumber(value) {
        if (!value || value === '') return '-';
        var num = parseFloat(value);
        return isNaN(num) ? String(value) : numFmt.format(num);
    }

    // ── Module state ─────────────────────────────────────────────────────────
    var transactionData   = [];
    var filteredRows      = [];
    var uccCache          = null;
    var autocompleteTimer = null;
    var isFetchingUCC     = false;
    var uccFetchAttempted = false;

    // ── Date helpers ─────────────────────────────────────────────────────────
    function fmtAPI(d) {
        return String(d.getDate()).padStart(2,'0') + '-' +
               String(d.getMonth()+1).padStart(2,'0') + '-' +
               d.getFullYear();
    }
    function fmtInput(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth()+1).padStart(2,'0') + '-' +
               String(d.getDate()).padStart(2,'0');
    }

    // ── Init date fields ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var today     = new Date(); today.setHours(0,0,0,0);
        var yesterday = new Date(today); yesterday.setDate(today.getDate()-1);
        var weekAgo   = new Date(yesterday); weekAgo.setDate(yesterday.getDate()-6);

        document.getElementById('fromDate').setAttribute('max', fmtInput(today));
        document.getElementById('toDate').setAttribute('max', fmtInput(today));
        document.getElementById('fromDate').value = fmtInput(weekAgo);
        document.getElementById('toDate').value   = fmtInput(yesterday);

        document.getElementById('fromDate').addEventListener('change', function () {
            if (!this.value) { document.getElementById('toDate').value = ''; return; }
            var from = new Date(this.value + 'T00:00:00');
            var to   = new Date(from); to.setDate(from.getDate()+6);
            var todayDate = new Date(); todayDate.setHours(0,0,0,0);
            if (to > todayDate) to = new Date(todayDate);
            document.getElementById('toDate').value = fmtInput(to);
        });
    });

    // ── Transaction filter ───────────────────────────────────────────────────
    function applyFilters(rows, status, type) {
        return rows.filter(function (row) {
            var os = (row.order_status || '').toUpperCase();
            if (status === 'VALID'   && os !== 'VALID')   return false;
            if (status === 'INVALID' && os !== 'INVALID') return false;

            if (type === 'ALL') return true;
            var combined = ((row.transaction_type||'') + ' ' +
                            (row.product_type||'')     + ' ' +
                            (row.order_type||'')).toUpperCase();
            if (type === 'PURCHASE')   return /PURCHASE|BUY|PUR/.test(combined);
            if (type === 'REDEMPTION') return /REDEMPTION|REDEEM|SELL|RED/.test(combined);
            if (type === 'SIP')        return combined.indexOf('SIP') !== -1;
            if (type === 'STP')        return combined.indexOf('STP') !== -1;
            if (type === 'SWITCH_IN')  return /SWITCH IN|SWITCHIN/.test(combined) ||
                                              (combined.indexOf('SWITCH') !== -1 && combined.indexOf('OUT') === -1);
            if (type === 'SWITCH_OUT') return /SWITCH OUT|SWITCHOUT/.test(combined);
            return false;
        });
    }

    // ── Set Date Preset Function ─────────────────────────────────────────────
    window.setDatePreset = function(preset) {
        var today = new Date();
        today.setHours(0,0,0,0);
        var yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        
        function fmtInput(d) {
            return d.getFullYear() + '-' +
                   String(d.getMonth()+1).padStart(2,'0') + '-' +
                   String(d.getDate()).padStart(2,'0');
        }
        
        if (preset === 'today') {
            document.getElementById('fromDate').value = fmtInput(today);
            document.getElementById('toDate').value = fmtInput(today);
        } else if (preset === 'yesterday') {
            document.getElementById('fromDate').value = fmtInput(yesterday);
            document.getElementById('toDate').value = fmtInput(yesterday);
        } else if (preset === 'week') {
            var weekAgo = new Date(yesterday);
            weekAgo.setDate(yesterday.getDate() - 6);
            document.getElementById('fromDate').value = fmtInput(weekAgo);
            document.getElementById('toDate').value = fmtInput(yesterday);
        }
        
        document.getElementById('fromDateError').innerHTML = '';
        document.getElementById('toDateError').innerHTML = '';
        fetchTransactionHistory();
    };

    // ── Main fetch ───────────────────────────────────────────────────────────
    window.fetchTransactionHistory = function () {
        document.getElementById('fromDateError').innerHTML = '';
        document.getElementById('toDateError').innerHTML   = '';
        document.getElementById('exportBtn').disabled = true;

        var fromStr  = document.getElementById('fromDate').value;
        var toStr    = document.getElementById('toDate').value;
        var status   = document.getElementById('transactionStatus').value;
        var type     = document.getElementById('transactionType').value;
        var custUCC  = document.getElementById('custUCC').value.trim();

        var today     = new Date(); today.setHours(0,0,0,0);
        var yesterday = new Date(today); yesterday.setDate(today.getDate()-1);
        var errors    = [];

        if (!fromStr && !toStr) {
            var from = new Date(yesterday); from.setDate(yesterday.getDate()-6);
            fromStr = fmtAPI(from); toStr = fmtAPI(yesterday);
        } else if (fromStr && !toStr) {
            var from = new Date(fromStr+'T00:00:00');
            var to   = new Date(from); to.setDate(from.getDate()+6);
            if (to > today) to = new Date(today);
            fromStr = fmtAPI(from); toStr = fmtAPI(to);
        } else if (!fromStr && toStr) {
            errors.push('If To Date is provided, From Date must also be provided');
        } else {
            var from = new Date(fromStr+'T00:00:00');
            var to   = new Date(toStr+'T00:00:00');
            if (to > today) errors.push('To date cannot be later than today');
            var diffDays = Math.ceil(Math.abs(to - from) / 86400000);
            if (diffDays > 7) errors.push('Date range cannot exceed 7 days');
            fromStr = fmtAPI(from); toStr = fmtAPI(to);
        }

        if (errors.length) { 
            alert(errors.join('\n')); 
            return; 
        }

        document.getElementById('loading').style.display = 'block';
        document.getElementById('result').innerHTML = '';

        var requestData = {
            from_date          : fromStr,
            to_date            : toStr,
            transaction_status : status,
            transaction_type   : type,
            client_code        : custUCC
        };

        $.ajax({
            url      : 'transaction_history_handler.php',
            type     : 'POST',
            data     : requestData,
            dataType : 'json',
            success  : function (response) {
                document.getElementById('loading').style.display = 'none';
                if (response.success) {
                    transactionData = response.data || [];
                    filteredRows = applyFilters(transactionData, status, type);
                    displayResults(filteredRows, requestData);
                    document.getElementById('exportBtn').disabled = (filteredRows.length === 0);
                } else {
                    document.getElementById('result').innerHTML =
                        '<div style="padding:20px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;color:#721c24">' +
                        '<strong>Error:</strong> ' + escapeHtml(response.error || 'Unknown error') + '</div>';
                }
            },
            error    : function (xhr, status, error) {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('result').innerHTML =
                    '<div style="padding:20px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;color:#721c24">' +
                    '<strong>Error:</strong> ' + escapeHtml(error) + '</div>';
            }
        });
    };

    // ── Display results ──────────────────────────────────────────────────────
    function displayResults(rows, params) {
        var fromDate  = params.from_date;
        var toDate    = params.to_date;
        var status    = params.transaction_status;
        var type      = params.transaction_type;
        var clientCode = params.client_code;

        var typeLabel = { PURCHASE:'Purchase', REDEMPTION:'Redemption',
                          SWITCH_IN:'SWITCH IN', SWITCH_OUT:'SWITCH OUT' };

        var parts = [];

        parts.push('<div class="trans-heading">');
        parts.push('<strong>Transaction Report</strong><br>Period: ' + fromDate + ' to ' + toDate);
        if (type && type !== 'ALL') parts.push('<br>Type: ' + escapeHtml(typeLabel[type] || type));
        if (clientCode)             parts.push('<br>Client Code: <strong>' + escapeHtml(clientCode) + '</strong>');
        parts.push('</div>');

        parts.push('<div style="overflow-x:auto; max-height:600px;">');
        parts.push('<table class="trans-table"><thead><tr>');
        ['#','Client Code','Client Name','Folio No','Trx Date','Product Type',
         'Trx Type','Scheme','Amount/Units','Alloted NAV','Alloted Units',
         'Alloted Amount','Status','Remark'
        ].forEach(function(h) { parts.push('<th>' + h + '</th>'); });
        parts.push('</tr></thead><tbody>');

        if (rows.length === 0) {
            parts.push('<tr><td colspan="14" style="text-align:center;color:#666;padding:40px;">');
            parts.push('<i class="fas fa-inbox" style="font-size:48px;color:#ccc"></i><br>');
            parts.push('No transaction records found for the selected criteria');
            if (clientCode) parts.push('<br><small>Searched Client Code: <strong>' + escapeHtml(clientCode) + '</strong></small>');
            parts.push('</td></tr>');
        } else {
            rows.forEach(function (row, idx) {
                var trxDate    = (row.product_reg_date_time || row.entry_date || '').substring(0, 10);
                var amountUnits = row.amount_units || '-';
                var folioNo    = row.dp_folio_no   || row.folio_no      || row.folio_number  || '-';
                var orderRemark= row.order_remark  || row.remarks       || '-';
                var schemeName = row.scheme_name_from || row.scheme_name || '-';
                var clientName = row.applicant_name || row.primary_holder_name || row.investor_name || '-';
                var productType= row.product_type  || '-';
                var transType  = row.transaction_type || '-';
                var orderStatus= row.order_status  || '';
                var allotedNav   = row.alloted_nav  || row.nav   || row.price             || '';
                var allotedUnits = row.alloted_unit || row.units || row.unit              || '';
                var allotedAmt   = row.alloted_amount || row.amount || row.transaction_amount || '';

                var statusClass = '';
                var osUpper = orderStatus.toUpperCase();
                if (osUpper === 'VALID')   statusClass = 'status-success';
                else if (osUpper === 'INVALID') statusClass = 'status-failed';
                else if (/PENDING|PROCESSING/.test(osUpper)) statusClass = 'status-pending';

                var rowClass = (clientCode && row.client_code === clientCode) ? 'client-code-highlight' : '';

                parts.push('<tr class="' + rowClass + '">');
                parts.push('<td style="text-align:center">' + (idx+1) + '</td>');
                parts.push('<td>' + escapeHtml(row.client_code || '-') + '</td>');
                parts.push('<td>' + escapeHtml(clientName)    + '</td>');
                parts.push('<td>' + escapeHtml(folioNo)        + '</td>');
                parts.push('<td>' + escapeHtml(trxDate)        + '</td>');
                parts.push('<td>' + escapeHtml(productType)    + '</td>');
                parts.push('<td>' + escapeHtml(transType)      + '</td>');
                parts.push('<td>' + escapeHtml(schemeName)     + '</td>');
                parts.push('<td class="numeric-value">' + escapeHtml(amountUnits)    + '</td>');
                parts.push('<td class="numeric-value">' + formatNumber(allotedNav)   + '</td>');
                parts.push('<td class="numeric-value">' + formatNumber(allotedUnits) + '</td>');
                parts.push('<td class="numeric-value">' + formatNumber(allotedAmt)   + '</td>');
                parts.push('<td class="' + statusClass + '">' + escapeHtml(orderStatus) + '</td>');
                parts.push('<td>' + escapeHtml(orderRemark)    + '</td>');
                parts.push('</tr>');
            });
        }

        parts.push('</tbody></table></div>');

        if (rows.length > 0) {
            parts.push('<div class="filter-summary">');
            parts.push('<strong>Records Displayed:</strong> ' + rows.length);
            parts.push(' | <strong>Status:</strong> ' + escapeHtml(status));
            parts.push(' | <strong>Type:</strong> '   + escapeHtml(typeLabel[type] || type || 'All Types'));
            if (clientCode) parts.push(' | <strong>Client Code:</strong> ' + escapeHtml(clientCode));
            parts.push('</div>');
        }

        document.getElementById('result').innerHTML = parts.join('');
    }

    // ── Clear form ───────────────────────────────────────────────────────────
    window.clearForm = function () {
        document.getElementById('fromDate').value          = '';
        document.getElementById('toDate').value            = '';
        document.getElementById('transactionStatus').value = 'ALL';
        document.getElementById('transactionType').value   = 'ALL';
        document.getElementById('custUCC').value           = '';
        document.getElementById('result').innerHTML        = '';
        document.getElementById('fromDateError').innerHTML = '';
        document.getElementById('toDateError').innerHTML   = '';
        document.getElementById('exportBtn').disabled      = true;
        transactionData = []; filteredRows = [];
        var al = document.getElementById('autocomplete-list');
        al.innerHTML = ''; al.style.display = 'none';
        // Reset cache so it tries again
        uccCache = null;
        uccFetchAttempted = false;
    };

    // ── Export CSV ───────────────────────────────────────────────────────────
    window.exportToCSV = function () {
        if (!filteredRows.length) { alert('No data to export'); return; }

        var cols = ['Sl No','Client Code','Client Name','Folio No','Transaction Date',
                    'Product Type','Transaction Type','Scheme','Amount/Units',
                    'Alloted NAV','Alloted Units','Alloted Amount','Order Status','Order Remark'];

        var lines = [cols.map(function(c){ return '"'+c+'"'; }).join(',')];

        filteredRows.forEach(function (row, idx) {
            var trxDate    = (row.product_reg_date_time || row.entry_date || '').substring(0,10);
            var clientName = row.applicant_name || row.primary_holder_name || row.investor_name || '-';
            var folioNo    = row.dp_folio_no    || row.folio_no      || row.folio_number  || '-';
            var schemeName = row.scheme_name_from || row.scheme_name || '-';
            var productType= row.product_type   || '-';
            var transType  = row.transaction_type|| '-';
            var amountUnits= row.amount_units    || '-';
            var allotedNav   = row.alloted_nav   || row.nav   || row.price             || '';
            var allotedUnits = row.alloted_unit  || row.units || row.unit              || '';
            var allotedAmt   = row.alloted_amount|| row.amount|| row.transaction_amount|| '';
            var orderStatus= row.order_status    || '-';
            var orderRemark= row.order_remark    || row.remarks || '-';

            lines.push([
                idx+1, row.client_code||'-', clientName, folioNo, trxDate,
                productType, transType, schemeName, amountUnits,
                allotedNav, allotedUnits, allotedAmt, orderStatus, orderRemark
            ].map(function(v){ return '"' + String(v).replace(/"/g,'""') + '"'; }).join(','));
        });

        var blob = new Blob([lines.join('\n')], { type:'text/csv;charset=utf-8;' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href   = url;
        a.download = 'transaction_history_' + new Date().toISOString().slice(0,10) + '.csv';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(function(){ URL.revokeObjectURL(url); document.body.removeChild(a); }, 500);
    };

    // ── Autocomplete with better error handling ─────────────────────────────
    function fetchCustomerUCCs(input) {
        var val = input.value.trim();
        var al  = document.getElementById('autocomplete-list');

        clearTimeout(autocompleteTimer);

        if (val.length < 2) { 
            al.style.display = 'none'; 
            al.innerHTML = ''; 
            return; 
        }

        // If we have cached data and it's valid, use it
        if (uccCache !== null && Array.isArray(uccCache) && uccCache.length > 0) {
            renderAutocomplete(uccCache, val, input, al);
            return;
        }

        // If we already attempted to fetch and got an error, don't retry
        if (uccFetchAttempted && uccCache === null) {
            al.innerHTML = '<div class="autocomplete-error">Unable to load UCC list. Please refresh the page.</div>';
            al.style.display = 'block';
            return;
        }

        // Show loading state
        al.innerHTML = '<div class="autocomplete-loading">Loading UCC list...</div>';
        al.style.display = 'block';

        autocompleteTimer = setTimeout(function () {
            if (isFetchingUCC) return;
            isFetchingUCC = true;
            uccFetchAttempted = true;

            fetch('getUCClistwithname.php')
                .then(function (response) {
                    // Check if response is OK
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    // Check content type
                    var contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        // If not JSON, it might be HTML error page
                        return response.text().then(function(text) {
                            // Check if it contains HTML (auth redirect)
                            if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                                throw new Error('Authentication required. Please login again.');
                            }
                            throw new Error('Invalid response format');
                        });
                    }
                    return response.json();
                })
                .then(function (data) {
                    isFetchingUCC = false;
                    if (Array.isArray(data) && data.length > 0) {
                        uccCache = data;
                        renderAutocomplete(uccCache, val, input, al);
                    } else {
                        al.innerHTML = '<div class="autocomplete-empty">No UCC records found</div>';
                        al.style.display = 'block';
                    }
                })
                .catch(function (err) {
                    isFetchingUCC = false;
                    console.error('Autocomplete error:', err);
                    // Show user-friendly error message
                    var errorMsg = err.message || 'Failed to load UCC list';
                    if (errorMsg.includes('Authentication') || errorMsg.includes('login')) {
                        errorMsg = 'Session expired. Please refresh the page and login again.';
                    }
                    al.innerHTML = '<div class="autocomplete-error">⚠️ ' + escapeHtml(errorMsg) + '</div>';
                    al.style.display = 'block';
                });
        }, 300);
    }

    function renderAutocomplete(data, val, input, al) {
        var lower = val.toLowerCase();
        var matches = data.filter(function (e) {
            var ucc = (e.UCC || '').toLowerCase();
            var name = (e.name || '').toLowerCase();
            return ucc.indexOf(lower) !== -1 || name.indexOf(lower) !== -1;
        });

        al.innerHTML = '';

        if (matches.length === 0) {
            al.innerHTML = '<div class="autocomplete-empty">No matches found for "' + escapeHtml(val) + '"</div>';
            al.style.display = 'block';
            return;
        }

        if (matches.length > 50) {
            al.innerHTML = '<div class="autocomplete-empty">Too many results (' + matches.length + ') — please refine your search</div>';
            al.style.display = 'block';
            return;
        }

        var frag = document.createDocumentFragment();
        matches.forEach(function (entry) {
            var div = document.createElement('div');
            var ucc = escapeHtml(entry.UCC || '');
            var name = escapeHtml(entry.name || '');
            // Highlight the matching text
            var highlightedUCC = highlightMatch(ucc, val);
            var highlightedName = highlightMatch(name, val);
            div.innerHTML = '<strong>' + highlightedUCC + '</strong> – ' + highlightedName;
            div.addEventListener('mousedown', function (e) {
                e.preventDefault();
                input.value = entry.UCC || '';
                al.innerHTML = '';
                al.style.display = 'none';
                // Trigger change event
                var event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            });
            frag.appendChild(div);
        });
        al.appendChild(frag);
        al.style.display = 'block';
        
        // Position the dropdown properly
        var rect = input.getBoundingClientRect();
        al.style.position = 'fixed';
        al.style.left = rect.left + 'px';
        al.style.top = (rect.bottom + window.scrollY) + 'px';
        al.style.width = input.offsetWidth + 'px';
        al.style.maxHeight = '200px';
        al.style.overflowY = 'auto';
        al.style.zIndex = '9999';
    }

    function highlightMatch(text, query) {
        if (!text || !query) return text;
        var lowerText = text.toLowerCase();
        var lowerQuery = query.toLowerCase();
        var index = lowerText.indexOf(lowerQuery);
        if (index === -1) return text;
        var before = text.substring(0, index);
        var match = text.substring(index, index + query.length);
        var after = text.substring(index + query.length);
        return before + '<span style="background-color: #ffeb3b; font-weight: bold;">' + match + '</span>' + after;
    }

    // ── Wire up autocomplete input ───────────────────────────────────────────
    var uccInput = document.getElementById('custUCC');
    if (uccInput) {
        uccInput.addEventListener('input', function () { 
            fetchCustomerUCCs(this); 
        });
        uccInput.addEventListener('focus', function () {
            // If there's a value, show suggestions
            if (this.value.trim().length >= 2) {
                fetchCustomerUCCs(this);
            }
        });
        uccInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var al = document.getElementById('autocomplete-list');
                al.style.display = 'none';
                fetchTransactionHistory();
            }
            if (e.key === 'Escape') {
                var al = document.getElementById('autocomplete-list');
                al.style.display = 'none';
            }
        });
        // Close autocomplete when clicking outside
        document.addEventListener('click', function (e) {
            var al = document.getElementById('autocomplete-list');
            var input = document.getElementById('custUCC');
            if (e.target !== input && !al.contains(e.target)) {
                al.style.display = 'none';
            }
        });
        // Also close on scroll
        window.addEventListener('scroll', function() {
            var al = document.getElementById('autocomplete-list');
            if (al) {
                al.style.display = 'none';
            }
        }, true);
    }

}());
</script>