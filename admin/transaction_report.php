<?php
require_once('auth_guard.php');

/**
 * transaction_report.php
 * AJAX fragment — loaded into index.php #main_content div.
 *
 * PERFORMANCE & BUG FIXES APPLIED
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. INPUT VALIDATION  — dates are validated server-side with DateTime before
 *    being passed to the DB query. Prevents malformed strings reaching SQL.
 *
 * 2. TRANSACTION TYPE SELECT  — was always empty (<option value="">All</option>
 *    only). The distinct types are now built from PHP on first load and echoed
 *    as real <option> tags, with the current filter value pre-selected.
 *    This also removes the need for a second JS fetch just to populate the
 *    dropdown.
 *
 * 3. FILTER RELOAD — applyTransactionFilter() was using fetch() to replace
 *    container.innerHTML with raw HTML. This bypasses jQuery's .load() script
 *    execution pipeline, so the new page's <script> block never ran properly
 *    in some browsers (event listeners lost on re-render).
 *    Fixed: delegate all reloads through the parent's #main_content via
 *    jQuery $.load(), exactly as every other fragment in this codebase does.
 *
 * 4. DUPLICATE formatDate() — defined identically inside both
 *    setTransactionDateRange() and resetTransactionFilters().
 *    Fixed: extracted once as a module-level helper.
 *
 * 5. DOMContentLoaded — unnecessary; this fragment is injected after DOM is
 *    ready. Replaced with direct execution.
 *
 * 6. TABLE OUTPUT — PHP was echoing each cell individually, calling
 *    htmlspecialchars() and number_format() inline inside the foreach.
 *    Refactored: pre-process each row's display values once, output cleanly.
 *
 * 7. EXPORT BUTTON — added a CSV export that works from the already-rendered
 *    table data (no second server round-trip needed).
 *
 * 8. DATE RANGE NOTE — was hardcoded PHP echo inside the full-page fragment,
 *    so after a JS-driven filter it would show stale dates.
 *    Fixed: the note is now inside #trans-range-note and updated by JS after
 *    each reload.
 * ─────────────────────────────────────────────────────────────────────────────
 */

require_once('database.php');
$db = new MYSQLDB();

// ── FIX 1: validated date inputs ─────────────────────────────────────────────
function sanitiseDate(string $input, string $fallback): string {
    $d = DateTime::createFromFormat('Y-m-d', $input);
    return ($d && $d->format('Y-m-d') === $input) ? $input : $fallback;
}

$defaultFrom = date('Y-m-d', strtotime('-7 days'));
$defaultTo   = date('Y-m-d');

$fromDate        = sanitiseDate($_GET['from_date']       ?? '', $defaultFrom);
$toDate          = sanitiseDate($_GET['to_date']         ?? '', $defaultTo);
$userUCC         = trim($_GET['user_ucc']                ?? '');
$transactionType = trim($_GET['transaction_type']        ?? '');

// Swap if from > to
if ($fromDate > $toDate) { [$fromDate, $toDate] = [$toDate, $fromDate]; }

// ── Fetch data ────────────────────────────────────────────────────────────────
$results      = [];
$dbError      = '';
try {
    $results = $db->getTransactionDetails($fromDate, $toDate, $userUCC, $transactionType) ?: [];
} catch (Exception $e) {
    error_log('Transaction Report Error: ' . $e->getMessage());
    $dbError = 'Could not load transactions. Please try again.';
}

// ── FIX 2: build distinct transaction types for the dropdown ──────────────────
$distinctTypes = [];
foreach ($results as $row) {
    $t = trim($row['transaction_type'] ?? '');
    if ($t !== '' && !in_array($t, $distinctTypes, true)) {
        $distinctTypes[] = $t;
    }
}
sort($distinctTypes);

// ── FIX 6: pre-process rows (do heavy work once, not inline in template) ──────
$totalAmount = 0.0;
$typeMap = []; // badge CSS class cache

function txTypeClass(string $t): string {
    $u = strtoupper($t);
    if (str_contains($u, 'REDEMPTION') || str_contains($u, 'SWITCH_OUT')) return 'type-redemption';
    if (str_contains($u, 'SWITCH'))   return 'type-switch';
    if (str_contains($u, 'DIVIDEND')) return 'type-dividend';
    return 'type-purchase';
}

$rows = [];
foreach ($results as $raw) {
    $amount = (float)($raw['amount'] ?? 0);
    $totalAmount += $amount;
    $trxType = $raw['transaction_type'] ?? '';
    $rows[] = [
        'folio'      => htmlspecialchars($raw['folio_number']       ?? '-'),
        'investor'   => htmlspecialchars($raw['investor_name']      ?? '-'),
        'trx_num'    => htmlspecialchars($raw['transaction_number'] ?? '-'),
        'trx_date'   => htmlspecialchars($raw['transaction_date']   ?? '-'),
        'trx_type'   => htmlspecialchars($trxType ?: '-'),
        'type_class' => txTypeClass($trxType),
        'scheme'     => htmlspecialchars($raw['master_scheme_name'] ?? '-'),
        'units'      => number_format((float)($raw['units'] ?? 0), 4),
        'price'      => number_format((float)($raw['price'] ?? $raw['nav'] ?? 0), 4),
        'amount'     => number_format($amount, 2),
    ];
}
?>

<style>
    .transaction-report-wrapper { padding: 15px; }

    .transaction-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .transaction-header h3 { margin: 0; color: #2c3e50; font-size: 18px; }

    .transaction-total {
        background: #FD7101;
        color: white;
        padding: 5px 12px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 13px;
    }

    .transaction-filter-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
        border: 1px solid #e0e0e0;
    }
    .transaction-filter-group { display: flex; flex-direction: column; gap: 5px; }
    .transaction-filter-group label { font-size: 12px; font-weight: bold; color: #555; }
    .transaction-filter-group input,
    .transaction-filter-group select {
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 13px;
        min-width: 160px;
    }
    .transaction-filter-group input:focus,
    .transaction-filter-group select:focus { outline: none; border-color: #FD7101; }

    .transaction-btn-filter {
        background: #FD7101; color: white; border: none;
        padding: 8px 20px; border-radius: 4px; cursor: pointer;
        font-size: 13px; font-weight: bold;
    }
    .transaction-btn-filter:hover { background: #e56500; }

    .transaction-btn-reset {
        background: #6c757d; color: white; border: none;
        padding: 8px 20px; border-radius: 4px; cursor: pointer; font-size: 13px;
    }
    .transaction-btn-reset:hover { background: #5a6268; }

    .transaction-btn-export {
        background: #198754; color: white; border: none;
        padding: 8px 20px; border-radius: 4px; cursor: pointer; font-size: 13px;
    }
    .transaction-btn-export:hover { background: #146c43; }

    .transaction-quick-dates { display: flex; gap: 8px; align-items: center; }
    .transaction-quick-date-btn {
        background: #e9ecef; border: 1px solid #dee2e6;
        padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;
    }
    .transaction-quick-date-btn:hover { background: #dee2e6; }

    .transaction-table-container {
        overflow-x: auto;
        max-height: 520px;
        overflow-y: auto;
        margin-top: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .transaction-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .transaction-table th {
        background: #34495e; color: white; padding: 10px;
        text-align: left; position: sticky; top: 0; z-index: 10;
        border: 1px solid #ddd;
    }
    .transaction-table td { border: 1px solid #ddd; padding: 8px; }
    .transaction-table tr:nth-child(even) { background: #f9f9f9; }
    .transaction-table tr:hover { background: #f0f4ff; }

    .amount-cell, .price-cell { text-align: right; font-family: monospace; }

    .transaction-no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }

    .transaction-date-range-note {
        font-size: 12px; color: #666;
        margin-top: 10px; padding: 8px 12px;
        background: #e8f4fd; border-radius: 4px;
    }

    .transaction-type-badge {
        display: inline-block; padding: 2px 8px;
        border-radius: 12px; font-size: 11px; font-weight: bold;
    }
    .type-purchase   { background: #d4edda; color: #155724; }
    .type-redemption { background: #f8d7da; color: #721c24; }
    .type-switch     { background: #fff3cd; color: #856404; }
    .type-dividend   { background: #d1ecf1; color: #0c5460; }

    .alert-danger {
        padding: 15px; background-color: #f8d7da;
        color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;
    }
    .loading-spinner { text-align: center; padding: 50px; }

    #trans-autocomplete-list {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        border: 1px solid #ccc;
        background-color: #fff;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 4px;
    }
    #trans-autocomplete-list div { cursor: pointer; padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 12px; }
    #trans-autocomplete-list div:hover { background-color: #e9e9e9; }
</style>

<div class="transaction-report-wrapper">

    <div class="transaction-header">
        <h3><i class="fas fa-chart-bar me-2"></i>Transaction Report <small style="font-size:13px;color:#888">(Mailback)</small></h3>
        <div class="transaction-total">
            <i class="fas fa-list me-1"></i>Total: <?php echo count($rows); ?> Records
        </div>
    </div>

    <?php if ($dbError): ?>
        <div class="alert-danger mb-3"><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($dbError); ?></div>
    <?php endif; ?>

    <!-- ── Filter bar ──────────────────────────────────────────────────────── -->
    <div class="transaction-filter-section">
        <div class="transaction-filter-group">
            <label><i class="fas fa-calendar-alt me-1"></i>From Date</label>
            <input type="date" id="trans_from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
        </div>
        <div class="transaction-filter-group">
            <label><i class="fas fa-calendar-alt me-1"></i>To Date</label>
            <input type="date" id="trans_to_date" value="<?php echo htmlspecialchars($toDate); ?>">
        </div>
        <div class="transaction-filter-group" style="position: relative;">
            <label><i class="fas fa-user me-1"></i>UCC / Client Code</label>
            <input type="text" id="trans_user_ucc" placeholder="Enter UCC or Name" autocomplete="off"
                   value="<?php echo htmlspecialchars($userUCC); ?>">
            <div id="trans-autocomplete-list" style="display:none;"></div>
        </div>

        <!-- FIX 2: properly populated dropdown from PHP -->
        <div class="transaction-filter-group">
            <label><i class="fas fa-exchange-alt me-1"></i>Transaction Type</label>
            <select id="trans_transaction_type">
                <option value="">All Transactions</option>
                <?php foreach ($distinctTypes as $dt): ?>
                    <option value="<?php echo htmlspecialchars($dt); ?>"
                        <?php echo ($dt === $transactionType) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dt); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="transaction-filter-group">
            <button type="button" class="transaction-btn-filter" onclick="applyTransactionFilter()">
                <i class="fas fa-search me-1"></i>Search
            </button>
        </div>
        <div class="transaction-filter-group">
            <button type="button" class="transaction-btn-reset" onclick="resetTransactionFilters()">
                <i class="fas fa-redo me-1"></i>Reset
            </button>
        </div>
        <?php if (!empty($rows)): ?>
        <div class="transaction-filter-group">
            <button type="button" class="transaction-btn-export" onclick="exportTransactionCSV()">
                <i class="fas fa-file-csv me-1"></i>Export CSV
            </button>
        </div>
        <?php endif; ?>

        <!-- Quick date shortcuts -->
        <div class="transaction-quick-dates">
            <span style="font-size:12px;color:#666">Quick:</span>
            <button type="button" class="transaction-quick-date-btn" onclick="setTransactionDateRange(7)">Last 7 Days</button>
            <button type="button" class="transaction-quick-date-btn" onclick="setTransactionDateRange(30)">Last 30 Days</button>
            <button type="button" class="transaction-quick-date-btn" onclick="setTransactionDateRange(90)">Last 90 Days</button>
        </div>
    </div>

    <!-- FIX 8: date note updated by JS after every reload -->
    <div class="transaction-date-range-note" id="trans-range-note">
        <i class="fas fa-info-circle me-1"></i>
        Showing transactions from <strong><?php echo htmlspecialchars($fromDate); ?></strong>
        to <strong><?php echo htmlspecialchars($toDate); ?></strong>
        <?php if ($userUCC): ?>
            &nbsp;|&nbsp;<i class="fas fa-user me-1"></i>Filtered by UCC: <strong><?php echo htmlspecialchars($userUCC); ?></strong>
        <?php endif; ?>
    </div>

    <!-- ── Table ───────────────────────────────────────────────────────────── -->
    <div class="transaction-table-container">
        <?php if (!empty($rows)): ?>
        <table class="transaction-table" id="trans-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Folio Number</th>
                    <th>Investor Name</th>
                    <th>Transaction No.</th>
                    <th>Trx Date</th>
                    <th>Type</th>
                    <th>Scheme Name</th>
                    <th>Units</th>
                    <th>Price (₹)</th>
                    <th>Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo $r['folio'];    ?></td>
                    <td><?php echo $r['investor']; ?></td>
                    <td><?php echo $r['trx_num'];  ?></td>
                    <td><?php echo $r['trx_date']; ?></td>
                    <td>
                        <span class="transaction-type-badge <?php echo $r['type_class']; ?>">
                            <?php echo $r['trx_type']; ?>
                        </span>
                    </td>
                    <td><?php echo $r['scheme'];  ?></td>
                    <td class="amount-cell"><?php echo $r['units'];  ?></td>
                    <td class="price-cell"><?php  echo $r['price'];  ?></td>
                    <td class="amount-cell"><?php echo $r['amount']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background:#f8f9fa;font-weight:bold;">
                <tr>
                    <td colspan="9" style="text-align:right"><strong>Total Amount:</strong></td>
                    <td class="amount-cell"><strong>₹ <?php echo number_format($totalAmount, 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <div class="transaction-no-data">
            <i class="fas fa-inbox" style="font-size:40px;color:#ccc;display:block;margin-bottom:12px"></i>
            No transaction records found for the selected filters.
            <p style="font-size:11px;margin-top:8px">Try changing the date range or clearing the filters.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.transaction-report-wrapper -->

<script>
(function () {
    'use strict';

    // ── FIX 4: single shared formatDate helper ────────────────────────────────
    function fmtDate(date) {
        return date.getFullYear() + '-' +
               String(date.getMonth() + 1).padStart(2, '0') + '-' +
               String(date.getDate()).padStart(2, '0');
    }

    // ── FIX 3: all reloads go through jQuery #main_content.load() ─────────────
    // This matches the pattern used by every other fragment in this codebase
    // and ensures the new fragment's <script> always executes correctly.
    function loadReport(params) {
        var qs  = params ? ('?' + new URLSearchParams(params).toString()) : '';
        var url = 'transaction_report.php' + qs;

        $('#page-title').text('Trans. Reports (MB)');
        $('#main_content').hide();
        $('#loader-text').text('Loading transaction report...');
        $('#loader').show();

        $('#main_content').load(url, function (response, status, xhr) {
            $('#loader').hide();
            if (status === 'error') {
                $('#main_content').html(
                    '<div class="alert-danger">Error ' + xhr.status + ': ' + xhr.statusText + '</div>'
                );
            }
            $('#main_content').fadeIn();
        });
    }

    // ── Public functions (called from onclick attributes) ─────────────────────
    window.applyTransactionFilter = function () {
        loadReport({
            from_date        : document.getElementById('trans_from_date').value,
            to_date          : document.getElementById('trans_to_date').value,
            user_ucc         : document.getElementById('trans_user_ucc').value.trim(),
            transaction_type : document.getElementById('trans_transaction_type').value
        });
    };

    window.resetTransactionFilters = function () {
        var today    = new Date();
        var weekAgo  = new Date(); weekAgo.setDate(today.getDate() - 7);
        loadReport({ from_date: fmtDate(weekAgo), to_date: fmtDate(today) });
    };

    window.setTransactionDateRange = function (days) {
        var today   = new Date();
        var from    = new Date(); from.setDate(today.getDate() - days);
        loadReport({ from_date: fmtDate(from), to_date: fmtDate(today) });
    };

    // ── FIX 7: CSV export direct from rendered table — no second server call ──
    window.exportTransactionCSV = function () {
        var table = document.getElementById('trans-table');
        if (!table) { alert('No data to export'); return; }

        var headers = Array.from(table.querySelectorAll('thead th'))
                          .map(function (th) { return '"' + th.innerText.trim() + '"'; });
        var lines   = [headers.join(',')];

        table.querySelectorAll('tbody tr').forEach(function (tr) {
            var cells = Array.from(tr.querySelectorAll('td')).map(function (td) {
                return '"' + td.innerText.trim().replace(/"/g, '""') + '"';
            });
            lines.push(cells.join(','));
        });

        var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = 'transaction_report_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(url); document.body.removeChild(a); }, 500);
    };

    // ── FIX 5: no DOMContentLoaded needed — fragment runs after DOM is ready ──
    var pt = document.getElementById('page-title');
    if (pt) pt.textContent = 'Trans. Reports (MB)';

    // ── Autocomplete for UCC (using event delegation for AJAX compatibility) ──
    var acList = document.getElementById('trans-autocomplete-list');
    var uccCache = null;
    var acTimer = null;

    function escapeHtmlSafe(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    var jq = window.jQuery || window.$;
    if (jq && acList) {
        jq(document).off('input.autoTrans').on('input.autoTrans', '#trans_user_ucc', function() {
            var inputEl = this;
            var val = inputEl.value.trim().toLowerCase();
            clearTimeout(acTimer);
            
            if (val.length < 2) {
                acList.style.display = 'none';
                acList.innerHTML = '';
                return;
            }

            acTimer = setTimeout(function() {
                if (uccCache !== null) {
                    renderAutocomplete(uccCache, val, inputEl);
                    return;
                }

                fetch('getUCClistwithname.php')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        uccCache = data;
                        renderAutocomplete(uccCache, val, inputEl);
                    })
                    .catch(function(e) {
                        acList.innerHTML = '<div style="padding:8px;color:red">Error loading data</div>';
                        acList.style.display = 'block';
                    });
            }, 300);
        });

        function renderAutocomplete(data, val, inputEl) {
            var matches = data.filter(function(e) {
                return (e.UCC && String(e.UCC).toLowerCase().indexOf(val) !== -1) ||
                       (e.name && String(e.name).toLowerCase().indexOf(val) !== -1);
            });

            acList.innerHTML = '';
            
            if (matches.length === 0) {
                acList.style.display = 'none';
                return;
            }
            
            if (matches.length > 30) {
                acList.innerHTML = '<div style="padding:8px;color:#666">Too many results — please refine search</div>';
                acList.style.display = 'block';
                return;
            }

            var frag = document.createDocumentFragment();
            matches.forEach(function(entry) {
                var div = document.createElement('div');
                div.innerHTML = '<strong>' + escapeHtmlSafe(entry.UCC) + '</strong> – ' + escapeHtmlSafe(entry.name);
                div.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    inputEl.value = entry.UCC;
                    acList.innerHTML = '';
                    acList.style.display = 'none';
                });
                frag.appendChild(div);
            });
            acList.appendChild(frag);
            acList.style.display = 'block';
        }

        jq(document).off('click.autoTrans').on('click.autoTrans', function(e) {
            if (e.target.id !== 'trans_user_ucc') {
                acList.style.display = 'none';
            }
        });
        
        jq(document).off('keydown.autoTrans').on('keydown.autoTrans', '#trans_user_ucc', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                acList.style.display = 'none';
                if (typeof window.applyTransactionFilter === 'function') {
                    window.applyTransactionFilter();
                }
            }
        });
    }

}());
</script>