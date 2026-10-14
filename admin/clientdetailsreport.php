<?php
require_once('auth_guard.php');

/**
 * clientdetailsreport.php  —  Client Detail Report (View Layer)
 *
 * Search scenarios (all handled in clientdetailsreportajax.php):
 *  1. Client Code only  OR  Client Code + dates  →  NSE API (live)
 *  2. Date Range only (no client code) → NSE API (live)
 *  3. Both provided → Client Code takes priority (NSE API)
 *  4. Neither → Error message
 * 
 * Client Master Data section:
 *  - New "Client Master Data" card for fetching complete client data from NSE
 *  - Uses clientmasterreportajax.php for API calls
 */

if (!isset($_SESSION)) { session_start(); }
require_once('database.php');

$db       = new MYSQLDB();
$siteName = $db->getSiteName();

if (!(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true)) {
    header('location: login.php');
    exit;
}
$superUser = $_SESSION['superuser'] ?? 0;
?>

<style>
/* ── Base ───────────────────────────────────────────────────────────────── */
.cdr-page { font-family: 'Inter','Segoe UI',sans-serif; }

/* ── Autocomplete styles ─────────────────────────────────────────────────── */
.autocomplete-container {
    position: relative;
}
#autocomplete-list, #master-autocomplete-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e2e6f0;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    margin-top: 4px;
}
#autocomplete-list div, #master-autocomplete-list div {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 12px;
    border-bottom: 1px solid #f0f2f9;
    transition: background 0.15s;
}
#autocomplete-list div:last-child, #master-autocomplete-list div:last-child {
    border-bottom: none;
}
#autocomplete-list div:hover, #master-autocomplete-list div:hover {
    background-color: #f8f9fc;
}
#autocomplete-list .no-results, #master-autocomplete-list .no-results {
    color: #8892b0;
    text-align: center;
    cursor: default;
}
#autocomplete-list .no-results:hover, #master-autocomplete-list .no-results:hover {
    background: transparent;
}

/* ── Filter card ─────────────────────────────────────────────────────────── */
.filter-card {
    background:#fff; border-radius:16px; padding:24px;
    margin-bottom:24px; box-shadow:0 1px 4px rgba(30,35,64,.06);
    border:1px solid #e8ebf4;
}
.filter-card .section-label {
    font-size:11px; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; color:#8892b0; margin-bottom:16px;
}
.filter-card label { font-size:12px; font-weight:600; color:#3d4a68; margin-bottom:5px; display:block; }
.filter-card .form-control {
    font-size:13px; border-radius:10px; border:1.5px solid #e2e6f0;
    padding:8px 12px; transition:border-color .15s,box-shadow .15s; height:38px;
}
.filter-card .form-control:focus {
    border-color:#5b6ef5; box-shadow:0 0 0 3px rgba(91,110,245,.12); outline:none;
}

/* ── Date picker styling ──────────────────────────────────────────── */
input[type="date"] {
    font-size:13px; border-radius:10px; border:1.5px solid #e2e6f0;
    padding:8px 12px; transition:border-color .15s,box-shadow .15s; height:38px;
    font-family: 'Inter','Segoe UI',sans-serif;
}
input[type="date"]:focus {
    border-color:#5b6ef5; box-shadow:0 0 0 3px rgba(91,110,245,.12); outline:none;
}
input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    padding: 4px;
}

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn-fetch {
    background:linear-gradient(135deg,#5b6ef5 0%,#7c3aed 100%); color:#fff;
    border:none; border-radius:10px; padding:9px 22px; font-size:13px;
    font-weight:600; transition:opacity .15s,transform .1s; white-space:nowrap; height:38px;
}
.btn-fetch:hover { opacity:.9; transform:translateY(-1px); color:#fff; }
.btn-fetch:active { transform:translateY(0); }
.btn-clear-cdr {
    background:#f3f4f6; border:1.5px solid #e2e6f0; border-radius:10px;
    padding:8px 18px; font-size:12px; font-weight:500; color:#6b7280;
    transition:background .15s; height:38px;
}
.btn-clear-cdr:hover { background:#e5e7eb; color:#374151; }

/* ── Info banner ─────────────────────────────────────────────────────────── */
.search-hint-banner {
    background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px;
    padding:10px 16px; font-size:12px; color:#1e40af; margin-bottom:18px;
}
.badge-pill { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.badge-nse { background:#dbeafe; color:#1d4ed8; }
.badge-sep { background:#e5e7eb; color:#374151; }
.badge-err { background:#fee2e2; color:#991b1b; }

/* ── Results stats bar ───────────────────────────────────────────────────── */
.result-stats-bar {
    background:linear-gradient(135deg,#5b6ef5 0%,#7c3aed 100%);
    border-radius:12px; padding:12px 20px; margin-bottom:18px;
    color:#fff; font-size:13px; display:flex; justify-content:space-between; align-items:center;
}
.result-stats-bar .total-num { font-size:22px; font-weight:700; margin-right:6px; }
.data-source-tag {
    background:rgba(255,255,255,.2); border-radius:20px; padding:3px 12px;
    font-size:11px; font-weight:600; letter-spacing:.04em;
}

/* ── Table ───────────────────────────────────────────────────────────────── */
.client-table-wrap {
    background:#fff; border-radius:16px; overflow:hidden;
    border:1px solid #e8ebf4; box-shadow:0 1px 4px rgba(30,35,64,.04);
}
.client-table-wrap thead th {
    background:#f8f9fc; font-size:11px; font-weight:700; text-transform:uppercase;
    letter-spacing:.06em; color:#8892b0; padding:13px 16px;
    border-bottom:1.5px solid #e8ebf4; white-space:nowrap;
}
.client-table-wrap tbody td {
    font-size:12.5px; padding:11px 16px; vertical-align:middle;
    border-bottom:1px solid #f0f2f9; color:#2d3656;
}
.client-table-wrap tbody tr:last-child td { border-bottom:none; }
.client-table-wrap tbody tr:hover td { background:#fafbff; }

/* ── Badges ──────────────────────────────────────────────────────────────── */
.sbadge { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
.sbadge-exists  { background:#d1fae5; color:#065f46; }
.sbadge-missing { background:#fee2e2; color:#991b1b; }

/* ── Action buttons ──────────────────────────────────────────────────────── */
.btn-tbl-view   { padding:5px 12px; font-size:11px; border-radius:8px; border:1.5px solid #5b6ef5; color:#5b6ef5; background:transparent; font-weight:600; transition:all .15s; cursor:pointer; }
.btn-tbl-view:hover { background:#5b6ef5; color:#fff; }
.btn-tbl-add    { padding:5px 12px; font-size:11px; border-radius:8px; border:1.5px solid #10b981; color:#10b981; background:transparent; font-weight:600; transition:all .15s; cursor:pointer; margin-left:5px; }
.btn-tbl-add:hover { background:#10b981; color:#fff; }
.btn-tbl-update { padding:5px 12px; font-size:11px; border-radius:8px; border:1.5px solid #f59e0b; color:#f59e0b; background:transparent; font-weight:600; transition:all .15s; cursor:pointer; margin-left:5px; }
.btn-tbl-update:hover { background:#f59e0b; color:#fff; }

/* ── Empty/error state ───────────────────────────────────────────────────── */
.empty-state { background:#f8faff; border:1.5px dashed #c7d2fe; border-radius:14px; padding:48px 24px; text-align:center; color:#4b5563; }
.empty-state .es-icon { font-size:40px; margin-bottom:14px; }
.empty-state h6 { font-size:15px; font-weight:700; color:#1e2340; margin-bottom:6px; }
.empty-state p  { font-size:13px; margin:0; }

/* ── Loader ──────────────────────────────────────────────────────────────── */
#cdrLoader { display:none; position:fixed; inset:0; background:rgba(255,255,255,.55); z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:14px; }
#cdrLoader.show { display:flex; }
.cdr-spinner { width:44px; height:44px; border:4px solid #e5e7eb; border-top-color:#5b6ef5; border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* ── Modals ──────────────────────────────────────────────────────────────── */
.modal-content { border-radius:16px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.15); }
.modal-header  { border-bottom:1px solid #e8ebf4; padding:18px 24px; }
.modal-body    { padding:0; max-height:72vh; overflow-y:auto; }
.modal-footer  { border-top:1px solid #e8ebf4; padding:14px 24px; }
.detail-row { display:flex; padding:10px 24px; border-bottom:1px solid #f0f2f9; font-size:12.5px; gap:12px; }
.detail-row:last-child { border-bottom:none; }
.detail-label { width:180px; min-width:180px; font-weight:700; color:#3d4a68; }
.detail-value { flex:1; color:#1e2340; word-break:break-word; }

/* Read-only display styles */
.readonly-display {
    background: #f8f9fc;
    border-radius: 8px;
    padding: 4px 12px;
    font-family: monospace;
    font-size: 13px;
    color: #1e2340;
    display: inline-block;
}

/* Bank details section in read-only modal */
.bank-details-section {
    background: #f8faff;
    border-radius: 12px;
    padding: 16px 20px;
    margin-top: 12px;
}

.section-title {
    font-size: 13px;
    font-weight: 700;
    color: #3d4a68;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e2e6f0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 11px;
    font-weight: 600;
    color: #8892b0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 13px;
    font-weight: 500;
    color: #1e2340;
    word-break: break-word;
}

/* ── Client Master Data Section ──────────────────────────────────────────── */
.master-data-card {
    background:#fff; border-radius:16px; padding:24px;
    margin-bottom:24px; box-shadow:0 1px 4px rgba(30,35,64,.06);
    border:1px solid #e8ebf4;
}
.master-data-card .section-label {
    font-size:11px; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; color:#8892b0; margin-bottom:16px;
}
.master-data-card label { font-size:12px; font-weight:600; color:#3d4a68; margin-bottom:5px; display:block; }
.master-data-card .form-control {
    font-size:13px; border-radius:10px; border:1.5px solid #e2e6f0;
    padding:8px 12px; transition:border-color .15s,box-shadow .15s; height:38px;
}
.master-data-card .form-control:focus {
    border-color:#5b6ef5; box-shadow:0 0 0 3px rgba(91,110,245,.12); outline:none;
}
.btn-fetch-master {
    background:linear-gradient(135deg,#10b981 0%,#059669 100%); color:#fff;
    border:none; border-radius:10px; padding:9px 22px; font-size:13px;
    font-weight:600; transition:opacity .15s,transform .1s; white-space:nowrap; height:38px;
}
.btn-fetch-master:hover { opacity:.9; transform:translateY(-1px); color:#fff; }
.btn-fetch-master:active { transform:translateY(0); }
.btn-fetch-master:disabled { opacity:.6; cursor:not-allowed; transform:none; }

.btn-reset-master {
    background:#f3f4f6; border:1.5px solid #e2e6f0; border-radius:10px;
    padding:8px 18px; font-size:12px; font-weight:500; color:#6b7280;
    transition:background .15s; height:38px;
}
.btn-reset-master:hover { background:#e5e7eb; color:#374151; }

.master-result-box {
    background:#f8faff; border-radius:12px; padding:16px 20px;
    margin-top:16px; border:1px solid #e2e6f0;
}
.master-result-box .field-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
    gap:10px 16px;
}
.master-result-box .field-item {
    display:flex; flex-direction:column; padding:6px 0;
    border-bottom:1px solid #f0f2f9;
}
.master-result-box .field-item:last-child { border-bottom:none; }
.master-result-box .field-label {
    font-size:10px; font-weight:700; text-transform:uppercase;
    letter-spacing:.05em; color:#8892b0;
}
.master-result-box .field-value {
    font-size:13px; font-weight:500; color:#1e2340;
    word-break:break-word;
}
.master-status-success { color:#10b981; }
.master-status-error { color:#ef4444; }
.master-status-info { color:#5b6ef5; }

#masterLoader {
    display: none;
}
#masterLoader.show {
    display: inline-block;
}
.spinner-sm {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #e5e7eb;
    border-top-color: #10b981;
    border-radius: 50%;
    animation: spin .6s linear infinite;
    vertical-align: middle;
    margin-right: 8px;
}

@media (max-width:768px) {
    .result-stats-bar { flex-direction:column; gap:10px; align-items:flex-start; }
    .detail-label { width:130px; min-width:130px; }
    .info-grid {
        grid-template-columns: 1fr;
    }
    .master-data-card .row .col-auto { width:100%; }
    .master-data-card .btn-fetch-master { width:100%; }
    .master-result-box .field-grid { grid-template-columns:1fr; }
}

/* ── FATCA Section Styles ───────────────────────────────────────────────── */
#fatca-autocomplete-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e8dbff;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(124, 58, 237, 0.08);
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    margin-top: 4px;
}
#fatca-autocomplete-list div {
    padding: 10px 14px;
    cursor: pointer;
    font-size: 12.5px;
    border-bottom: 1px solid #f5efff;
    transition: background 0.15s, color 0.15s;
    color: #2d3656;
}
#fatca-autocomplete-list div:last-child {
    border-bottom: none;
}
#fatca-autocomplete-list div:hover {
    background-color: #f6f0ff;
    color: #7c3aed;
}
#fatca-autocomplete-list .no-results {
    color: #8892b0;
    text-align: center;
    cursor: default;
    padding: 12px;
}
.btn-fetch-fatca {
    background: linear-gradient(135deg, #7c3aed 0%, #5b6ef5 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 9px 22px;
    font-size: 13px;
    font-weight: 600;
    transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
    white-space: nowrap;
    height: 38px;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.2);
}
.btn-fetch-fatca:hover {
    opacity: 0.95;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(124, 58, 237, 0.3);
    color: #fff;
}
.btn-fetch-fatca:active {
    transform: translateY(0);
}
.btn-fetch-fatca:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
.fatca-hint-banner {
    background: #fbf8ff;
    border: 1px solid #e8dbff;
    border-radius: 12px;
    padding: 14px 20px;
    font-size: 12.5px;
    color: #5c3db5;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    box-shadow: 0 2px 6px rgba(124, 58, 237, 0.02);
}
.fatca-hint-banner i {
    font-size: 16px;
    margin-top: 2px;
    color: #7c3aed;
}
.fatca-result-table th {
    background: #f6f0ff !important;
    color: #5c3db5 !important;
    border-color: #e8dbff !important;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
}
.fatca-result-table td {
    border-color: #f5efff !important;
    font-size: 12.5px;
}
</style>

<!-- LOADER -->
<div id="cdrLoader">
    <div class="cdr-spinner"></div>
    <span style="font-size:13px;color:#5b6ef5;font-weight:600;">Fetching data from NSE API…</span>
</div>

<!-- MAIN PAGE -->
<div class="cdr-page">

    <!-- ==================== CLIENT MASTER DATA SECTION - NEW ==================== -->
    <div class="master-data-card">
        <div class="section-label"><i class="fas fa-database me-2"></i>Client Master Data (Full Fetch)</div>

        <div class="row g-3 align-items-end">
            <!-- UCC Input with Autocomplete -->
            <div class="col-lg-6 col-md-8">
                <label for="master_client_code">
                    <i class="fas fa-user-tag me-1 text-success"></i>UCC / Client Code
                    <span class="text-muted fw-normal">(fetch complete master data)</span>
                </label>
                <div class="autocomplete-container">
                    <input type="text" class="form-control" id="master_client_code"
                           placeholder="Enter Client Code / UCC (e.g. 1000001234)" 
                           autocomplete="off" maxlength="30">
                    <div id="master-autocomplete-list" style="display:none;"></div>
                </div>
                <div class="form-text" style="font-size:11px;">
                    Enter a valid UCC to fetch complete client master data from NSE.
                </div>
            </div>

            <!-- Fetch Button -->
            <div class="col-lg-3 col-md-4">
                <label class="d-none d-lg-block" style="visibility:hidden;">.</label>
                <button class="btn btn-fetch-master" id="masterBtnFetch">
                    <i class="fas fa-cloud-download-alt me-2"></i>
                    <span id="masterBtnText">Fetch Master Data</span>
                    <span id="masterLoader" style="display:none;">
                        <span class="spinner-sm"></span> Loading...
                    </span>
                </button>
            </div>

            <!-- Reset Button -->
            <div class="col-lg-3 col-md-12">
                <label class="d-none d-lg-block" style="visibility:hidden;">.</label>
                <button class="btn btn-reset-master" id="masterBtnReset">
                    <i class="fas fa-undo me-2"></i>Reset
                </button>
            </div>
        </div>

        <!-- Master Data Result Display -->
        <div id="masterResultContainer" style="display:none;">
            <div class="master-result-box">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="fas fa-check-circle text-success me-2"></i>Client Master Data</h6>
                    <span id="masterStatusBadge" class="badge bg-success">Fetched</span>
                </div>
                <div class="field-grid" id="masterFieldsContainer">
                    <!-- Dynamic fields will be populated here -->
                </div>
                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        <span id="masterActionMessage">Data saved to database</span>
                    </small>
                </div>
            </div>
        </div>

        <!-- Error message container -->
        <div id="masterErrorContainer" class="mt-2" style="display:none;">
            <div class="alert alert-danger" role="alert" style="border-radius:10px;font-size:13px;">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span id="masterErrorMessage"></span>
            </div>
        </div>
    </div>
    <!-- ==================== END CLIENT MASTER DATA SECTION ==================== -->

    <!-- ==================== FATCA REPORT SECTION - NEW ==================== -->
    <div class="master-data-card" style="border-top: 4px solid #7c3aed; box-shadow: 0 10px 30px rgba(124, 58, 237, 0.05); margin-bottom: 24px;">
        <div class="section-label" style="color: #7c3aed; display: flex; align-items: center; justify-content: space-between;">
            <span><i class="fas fa-file-invoice me-2"></i>FATCA Report (Fetch & Sync)</span>
            <span class="badge-pill" style="background: rgba(124, 58, 237, 0.1); color: #7c3aed; text-transform: none; letter-spacing: 0; font-size: 10px;">NSE Live API</span>
        </div>

        <div class="fatca-hint-banner">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>How to fetch FATCA reports:</strong>&nbsp;
                Enter a <span class="badge-pill" style="background:#e8dbff; color:#7c3aed;">Client Code / PAN</span> to pull live records for specific users, 
                or select a <span class="badge-pill" style="background:#e8dbff; color:#7c3aed;">Date Range</span> to query all declarations submitted in that period (up to 31 days). 
                All data is fetched in real-time from <strong>NSE live servers</strong> and saved directly into the database.
            </div>
        </div>

        <div class="row g-3 align-items-end">
            <!-- Client Code with Autocomplete -->
            <div class="col-lg-4 col-md-12">
                <label for="fatca_client_code">
                    <i class="fas fa-user-tag me-1" style="color: #7c3aed;"></i>Client Code / PAN
                    <span class="text-muted fw-normal">(Optional, comma separated max 50)</span>
                </label>
                <div class="autocomplete-container">
                    <input type="text" class="form-control" id="fatca_client_code"
                           placeholder="Type client code or PAN (e.g. 5013667627)" autocomplete="off" maxlength="150">
                    <div id="fatca-autocomplete-list" style="display:none;"></div>
                </div>
                <div class="form-text" style="font-size:11px;">
                    If entered, date range inputs will be ignored.
                </div>
            </div>

            <!-- From Date -->
            <div class="col-lg-3 col-md-3">
                <label for="fatca_from_date">
                    <i class="fas fa-calendar-alt me-1" style="color: #7c3aed;"></i>From Date
                </label>
                <input type="date" class="form-control w-100" id="fatca_from_date">
            </div>

            <!-- To Date -->
            <div class="col-lg-3 col-md-3">
                <label for="fatca_to_date">
                    <i class="fas fa-calendar-alt me-1" style="color: #7c3aed;"></i>To Date
                </label>
                <input type="date" class="form-control w-100" id="fatca_to_date">
            </div>

            <!-- Fetch Button -->
            <div class="col-lg-2 col-md-12 d-flex gap-2">
                <button class="btn btn-fetch-fatca w-100" id="fatcaBtnFetch">
                    <i class="fas fa-sync me-2"></i>
                    <span id="fatcaBtnText">Sync FATCA</span>
                    <span id="fatcaLoader" style="display:none;">
                        <span class="spinner-sm" style="border-top-color: #ffffff; margin-right: 4px;"></span> Syncing...
                    </span>
                </button>
                <button class="btn btn-reset-master" id="fatcaBtnReset" title="Reset Fields">
                    <i class="fas fa-undo"></i>
                </button>
            </div>
        </div>

        <!-- FATCA Result Display -->
        <div id="fatcaResultContainer" style="display:none;">
            <div class="master-result-box" style="background: #fcfaff; border-color: #e8dbff;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0" style="color: #7c3aed; font-weight: 700;"><i class="fas fa-check-circle me-2"></i>FATCA Sync Result</h6>
                    <span id="fatcaStatusBadge" class="badge" style="background-color: #7c3aed; font-size: 11px; padding: 5px 12px; border-radius: 20px;">Synced</span>
                </div>
                
                <div style="max-height: 250px; overflow-y: auto; border: 1px solid #e8dbff; border-radius: 8px;">
                    <table class="table table-hover fatca-result-table mb-0" style="background: #fff;">
                        <thead>
                            <tr>
                                <th>PAN / PEKRN</th>
                                <th>Investor Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="fatcaResultTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted" style="font-size: 11.5px;">
                        <i class="fas fa-info-circle me-1" style="color: #7c3aed;"></i>
                        <span id="fatcaActionMessage">FATCA records saved/updated in database.</span>
                    </small>
                </div>
            </div>
        </div>

        <!-- Error message container -->
        <div id="fatcaErrorContainer" class="mt-2" style="display:none;">
            <div class="alert alert-danger" role="alert" style="border-radius:10px;font-size:13px; margin-bottom:0; background: #fff5f5; border-color: #ffcccc; color: #cc0000;">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span id="fatcaErrorMessage"></span>
            </div>
        </div>
    </div>
    <!-- ==================== END FATCA REPORT SECTION ==================== -->

    <!-- ==================== EXISTING FILTER CARD ==================== -->
    <div class="filter-card">
        <div class="section-label"><i class="fas fa-filter me-2"></i>Search Client</div>

        <div class="search-hint-banner">
            <i class="fas fa-info-circle me-1"></i>
            <strong>How to search:</strong>&nbsp;
            <span class="badge-pill badge-nse">Client Code</span>&nbsp;→ live <strong>NSE API</strong>&nbsp;&nbsp;
            <span class="badge-pill badge-sep">OR</span>&nbsp;&nbsp;
            <span class="badge-pill badge-nse">Date Range</span>&nbsp;→ live <strong>NSE API</strong>&nbsp;&nbsp;
            <span class="badge-pill badge-err">Neither</span>&nbsp;→ error
            <br><span class="ms-4 mt-1 d-inline-block" style="opacity:.8;">
                ⚡ All searches go directly to <strong>NSE API</strong> for live data. Local DB is only for storing client records.
            </span>
        </div>

        <div class="row g-3 align-items-end">

            <!-- Client Code with Autocomplete -->
            <div class="col-lg-4 col-md-12">
                <label for="cdr_client_code">
                    <i class="fas fa-user-tag me-1 text-primary"></i>Client Code
                    <span class="text-muted fw-normal">(NSE live lookup)</span>
                </label>
                <div class="autocomplete-container">
                    <input type="text" class="form-control" id="cdr_client_code"
                           placeholder="Type client code or name (min 2 chars)" autocomplete="off" maxlength="30">
                    <div id="autocomplete-list" style="display: none;"></div>
                </div>
                <div class="form-text" style="font-size:11px;">Enter client code or name, or leave empty to search by date range.</div>
            </div>

            <!-- OR separator -->
            <div class="col-auto d-none d-lg-flex align-items-end pb-3">
                <span class="badge-pill badge-sep" style="font-size:12px;">OR</span>
            </div>

            <!-- FROM DATE - Native HTML5 date picker -->
            <div class="col-lg-auto col-md-6">
                <label><i class="fas fa-calendar-alt me-1 text-success"></i>From Date</label>
                <input type="date" class="form-control" id="cdr_from_date">
                <div class="form-text" style="font-size:11px;">Required when no client code.</div>
            </div>

            <!-- TO DATE - Native HTML5 date picker -->
            <div class="col-lg-auto col-md-6">
                <label><i class="fas fa-calendar-check me-1 text-success"></i>To Date</label>
                <input type="date" class="form-control" id="cdr_to_date">
                <div class="form-text" style="font-size:11px;">Max 7-day range.</div>
            </div>

            <!-- Fetch Button -->
            <div class="col-lg-auto col-md-12">
                <label class="d-none d-lg-block" style="visibility:hidden;">.</label>
                <button class="btn btn-fetch" id="cdrBtnFetch">
                    <i class="fas fa-search me-2"></i>Fetch Report
                </button>
            </div>
        </div>

        <div class="row mt-3 align-items-center">
            <div class="col-auto">
                <button class="btn btn-clear-cdr" id="cdrBtnClear">
                    <i class="fas fa-eraser me-2"></i>Clear All
                </button>
            </div>
            <div class="col">
                <small class="text-muted">
                    <i class="fas fa-keyboard me-1"></i>Press <kbd>Enter</kbd> in Client Code to fetch.
                </small>
            </div>
        </div>
    </div>
    <!-- ==================== END FILTER CARD ==================== -->

    <!-- Results -->
    <div id="cdrResults" style="display:none;">
        <div class="result-stats-bar">
            <div>
                <i class="fas fa-list me-2"></i>
                Showing <span class="total-num" id="cdrTotalCount">0</span> record(s)
                <span id="cdrSearchInfo" style="font-size:12px;opacity:.85;margin-left:8px;"></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span id="cdrDataSource" class="data-source-tag">⚡ NSE Live API</span>
                <button class="btn btn-sm btn-light" id="cdrBtnRefresh">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>
        <div class="client-table-wrap">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>Client Code</th><th>Primary Holder Name</th>
                            <th>PAN</th><th>Email</th><th>Mobile</th><th>DOB</th>
                            <th>Tax Status</th><th>UCC Status</th><th>Holding Nature</th>
                            <th>Account No</th><th>IFSC Code</th>
                            <th>DB Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cdrTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Empty state -->
    <div class="empty-state" id="cdrEmptyState">
        <div class="es-icon text-primary"><i class="fas fa-search"></i></div>
        <h6>No Data to Display</h6>
        <p>Enter a <strong>Client Code</strong> (NSE live) <em>or</em> choose a
           <strong>Date Range</strong> (NSE live, max 7 days), then click
           <strong>"Fetch Report"</strong>.</p>
    </div>

</div><!-- /cdr-page -->

<!-- MODAL: View Details -->
<div class="modal fade" id="cdrViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-circle me-2 text-primary"></i>Client Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cdrModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Add Client (READ-ONLY DISPLAY) -->
<div class="modal fade" id="cdrAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2 text-success"></i>Add Client to Database
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="cdrAddClientContent">
                    <!-- Content will be populated dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="cdrConfirmAdd">
                    <i class="fas fa-save me-1"></i>Confirm Add to Database
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const AJAX_URL = 'clientdetailsreportajax.php';
    const MASTER_AJAX_URL = 'clientmasterreportajax.php';
    const FATCA_AJAX_URL = 'fatcareportajax.php';
    const MAX_DAYS = 6;

    let currentData = [];
    let lastParams  = {};
    let pendingAddClient = null;
    let autocompleteTimeout = null;
    let masterAutocompleteTimeout = null;

    const $loader     = document.getElementById('cdrLoader');
    const $results    = document.getElementById('cdrResults');
    const $emptyState = document.getElementById('cdrEmptyState');
    const $tableBody  = document.getElementById('cdrTableBody');
    const $totalCount = document.getElementById('cdrTotalCount');
    const $searchInfo = document.getElementById('cdrSearchInfo');
    const $dataSource = document.getElementById('cdrDataSource');
    const $inputCC    = document.getElementById('cdr_client_code');
    const $autocompleteList = document.getElementById('autocomplete-list');

    // Master data elements
    const $masterCC = document.getElementById('master_client_code');
    const $masterBtnFetch = document.getElementById('masterBtnFetch');
    const $masterBtnReset = document.getElementById('masterBtnReset');
    const $masterBtnText = document.getElementById('masterBtnText');
    const $masterLoader = document.getElementById('masterLoader');
    const $masterResultContainer = document.getElementById('masterResultContainer');
    const $masterFieldsContainer = document.getElementById('masterFieldsContainer');
    const $masterStatusBadge = document.getElementById('masterStatusBadge');
    const $masterActionMessage = document.getElementById('masterActionMessage');
    const $masterErrorContainer = document.getElementById('masterErrorContainer');
    const $masterErrorMessage = document.getElementById('masterErrorMessage');
    const $masterAutocompleteList = document.getElementById('master-autocomplete-list');

    // FATCA elements
    const $fatcaCC       = document.getElementById('fatca_client_code');
    const $fatcaFromDate = document.getElementById('fatca_from_date');
    const $fatcaToDate   = document.getElementById('fatca_to_date');
    const $fatcaBtnFetch = document.getElementById('fatcaBtnFetch');
    const $fatcaBtnReset = document.getElementById('fatcaBtnReset');
    const $fatcaBtnText  = document.getElementById('fatcaBtnText');
    const $fatcaLoader   = document.getElementById('fatcaLoader');
    const $fatcaResultContainer = document.getElementById('fatcaResultContainer');
    const $fatcaResultTableBody = document.getElementById('fatcaResultTableBody');
    const $fatcaStatusBadge = document.getElementById('fatcaStatusBadge');
    const $fatcaActionMessage = document.getElementById('fatcaActionMessage');
    const $fatcaErrorContainer = document.getElementById('fatcaErrorContainer');
    const $fatcaErrorMessage = document.getElementById('fatcaErrorMessage');
    const $fatcaAutocompleteList = document.getElementById('fatca-autocomplete-list');
    let fatcaAutocompleteTimeout = null;

    function formatDateForInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatDateForDisplay(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return `${parts[2]}-${parts[1]}-${parts[0]}`;
        }
        return dateStr;
    }

    function validateDateRange() {
        const fromDate = document.getElementById('cdr_from_date').value;
        const toDate = document.getElementById('cdr_to_date').value;
        
        if (fromDate && toDate) {
            const from = new Date(fromDate);
            const to = new Date(toDate);
            from.setHours(0, 0, 0, 0);
            to.setHours(0, 0, 0, 0);
            const diffDays = Math.round((to - from) / (1000 * 60 * 60 * 24));
            
            if (diffDays > MAX_DAYS) {
                showToast('warning', 'Date range cannot exceed ' + (MAX_DAYS + 1) + ' days. Adjusting to ' + (MAX_DAYS + 1) + ' days.');
                const newToDate = new Date(from);
                newToDate.setDate(from.getDate() + MAX_DAYS);
                document.getElementById('cdr_to_date').value = formatDateForInput(newToDate);
            }
            
            if (diffDays < 0) {
                showToast('warning', 'To Date must be after From Date.');
                document.getElementById('cdr_to_date').value = fromDate;
            }
        }
    }

    function getDateStrFromPicker(pickerId) {
        const dateValue = document.getElementById(pickerId).value;
        if (!dateValue) return '';
        return formatDateForDisplay(dateValue);
    }

    function initDatePickers() {
        const $fromDate = document.getElementById('cdr_from_date');
        const $toDate = document.getElementById('cdr_to_date');
        
        const today = new Date();
        const sevenDaysAgo = new Date();
        sevenDaysAgo.setDate(today.getDate() - MAX_DAYS);
        
        $fromDate.value = formatDateForInput(sevenDaysAgo);
        $toDate.value = formatDateForInput(today);
        
        $fromDate.addEventListener('change', validateDateRange);
        $toDate.addEventListener('change', validateDateRange);
    }

    function initFatcaDates() {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        $fatcaFromDate.value = formatDateForInput(thirtyDaysAgo);
        $fatcaToDate.value = formatDateForInput(today);
        
        $fatcaFromDate.addEventListener('change', validateFatcaDates);
        $fatcaToDate.addEventListener('change', validateFatcaDates);
    }
    
    function validateFatcaDates() {
        const fromDate = $fatcaFromDate.value;
        const toDate = $fatcaToDate.value;
        
        if (fromDate && toDate) {
            const from = new Date(fromDate);
            const to = new Date(toDate);
            from.setHours(0, 0, 0, 0);
            to.setHours(0, 0, 0, 0);
            const diffDays = Math.round((to - from) / (1000 * 60 * 60 * 24));
            
            if (diffDays > 30) {
                const newToDate = new Date(from);
                newToDate.setDate(from.getDate() + 30);
                $fatcaToDate.value = formatDateForInput(newToDate);
            }
            
            if (diffDays < 0) {
                showToast('warning', 'To Date must be after From Date.');
                $fatcaToDate.value = fromDate;
            }
        }
    }

    initDatePickers();
    initFatcaDates();

    // ==================== AUTOCOMPLETE FUNCTIONALITY ====================
    function escapeHtmlForAutocomplete(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fetchClientCodes(searchTerm) {
        if (autocompleteTimeout) {
            clearTimeout(autocompleteTimeout);
        }
        
        if (searchTerm.length < 2) {
            $autocompleteList.style.display = 'none';
            $autocompleteList.innerHTML = '';
            return;
        }
        
        autocompleteTimeout = setTimeout(function() {
            fetch("getUCClistwithname.php")
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    $autocompleteList.innerHTML = '';
                    
                    var matchingClients = data.filter(function(entry) {
                        return (entry.UCC && entry.UCC.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1) || 
                               (entry.name && entry.name.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1);
                    });
                    
                    if (matchingClients.length > 0 && matchingClients.length <= 40) {
                        matchingClients.forEach(function(entry) {
                            var option = document.createElement("div");
                            option.innerHTML = `<strong>${escapeHtmlForAutocomplete(entry.UCC)}</strong> - ${escapeHtmlForAutocomplete(entry.name)}`;
                            option.onclick = function() {
                                $inputCC.value = entry.UCC;
                                $autocompleteList.innerHTML = '';
                                $autocompleteList.style.display = 'none';
                            };
                            $autocompleteList.appendChild(option);
                        });
                        $autocompleteList.style.display = "block";
                        $autocompleteList.style.width = $inputCC.offsetWidth + "px";
                    } else if (matchingClients.length > 40) {
                        var tooMany = document.createElement("div");
                        tooMany.className = "no-results";
                        tooMany.innerHTML = "Too many results, please refine your search";
                        $autocompleteList.appendChild(tooMany);
                        $autocompleteList.style.display = "block";
                    } else {
                        var noResults = document.createElement("div");
                        noResults.className = "no-results";
                        noResults.innerHTML = "No matching clients found";
                        $autocompleteList.appendChild(noResults);
                        $autocompleteList.style.display = "block";
                    }
                })
                .catch(error => {
                    console.error('Error fetching client list:', error);
                    $autocompleteList.innerHTML = '<div class="no-results">Error loading client data</div>';
                    $autocompleteList.style.display = "block";
                });
        }, 300);
    }

    // ── Master autocomplete ──────────────────────────────────────────────
    function fetchMasterClientCodes(searchTerm) {
        if (masterAutocompleteTimeout) {
            clearTimeout(masterAutocompleteTimeout);
        }

        if (searchTerm.length < 2) {
            $masterAutocompleteList.style.display = 'none';
            $masterAutocompleteList.innerHTML = '';
            return;
        }

        masterAutocompleteTimeout = setTimeout(function() {
            fetch('getUCClistwithname.php')
                .then(function(response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(function(data) {
                    $masterAutocompleteList.innerHTML = '';
                    
                    var matchingClients = data.filter(function(entry) {
                        return (entry.UCC && entry.UCC.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1) || 
                               (entry.name && entry.name.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1);
                    });
                    
                    if (matchingClients.length > 0 && matchingClients.length <= 40) {
                        matchingClients.forEach(function(entry) {
                            var option = document.createElement("div");
                            option.innerHTML = `<strong>${escapeHtmlForAutocomplete(entry.UCC)}</strong> - ${escapeHtmlForAutocomplete(entry.name)}`;
                            option.onclick = function() {
                                $masterCC.value = entry.UCC;
                                $masterAutocompleteList.innerHTML = '';
                                $masterAutocompleteList.style.display = 'none';
                            };
                            $masterAutocompleteList.appendChild(option);
                        });
                        $masterAutocompleteList.style.display = "block";
                        $masterAutocompleteList.style.width = $masterCC.offsetWidth + "px";
                    } else if (matchingClients.length > 40) {
                        var tooMany = document.createElement("div");
                        tooMany.className = "no-results";
                        tooMany.innerHTML = "Too many results, please refine your search";
                        $masterAutocompleteList.appendChild(tooMany);
                        $masterAutocompleteList.style.display = "block";
                    } else {
                        var noResults = document.createElement("div");
                        noResults.className = "no-results";
                        noResults.innerHTML = "No matching clients found";
                        $masterAutocompleteList.appendChild(noResults);
                        $masterAutocompleteList.style.display = "block";
                    }
                })
                .catch(function(error) {
                    console.error('Error fetching client list:', error);
                    $masterAutocompleteList.innerHTML = '<div class="no-results">Error loading client data</div>';
                    $masterAutocompleteList.style.display = "block";
                });
        }, 300);
    }

    // ── FATCA autocomplete ───────────────────────────────────────────────
    function fetchFatcaClientCodes(searchTerm) {
        if (fatcaAutocompleteTimeout) {
            clearTimeout(fatcaAutocompleteTimeout);
        }

        if (searchTerm.length < 2) {
            $fatcaAutocompleteList.style.display = 'none';
            $fatcaAutocompleteList.innerHTML = '';
            return;
        }

        fatcaAutocompleteTimeout = setTimeout(function() {
            fetch('getUCClistwithname.php')
                .then(function(response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(function(data) {
                    $fatcaAutocompleteList.innerHTML = '';
                    
                    var matchingClients = data.filter(function(entry) {
                        return (entry.UCC && entry.UCC.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1) || 
                               (entry.name && entry.name.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1);
                    });
                    
                    if (matchingClients.length > 0 && matchingClients.length <= 40) {
                        matchingClients.forEach(function(entry) {
                            var option = document.createElement("div");
                            option.innerHTML = `<strong>${escapeHtmlForAutocomplete(entry.UCC)}</strong> - ${escapeHtmlForAutocomplete(entry.name)}`;
                            option.onclick = function() {
                                $fatcaCC.value = entry.UCC;
                                $fatcaAutocompleteList.innerHTML = '';
                                $fatcaAutocompleteList.style.display = 'none';
                            };
                            $fatcaAutocompleteList.appendChild(option);
                        });
                        $fatcaAutocompleteList.style.display = "block";
                        $fatcaAutocompleteList.style.width = $fatcaCC.offsetWidth + "px";
                    } else if (matchingClients.length > 40) {
                        var tooMany = document.createElement("div");
                        tooMany.className = "no-results";
                        tooMany.innerHTML = "Too many results, please refine your search";
                        $fatcaAutocompleteList.appendChild(tooMany);
                        $fatcaAutocompleteList.style.display = "block";
                    } else {
                        var noResults = document.createElement("div");
                        noResults.className = "no-results";
                        noResults.innerHTML = "No matching clients found";
                        $fatcaAutocompleteList.appendChild(noResults);
                        $fatcaAutocompleteList.style.display = "block";
                    }
                })
                .catch(function(error) {
                    console.error('Error fetching client list:', error);
                    $fatcaAutocompleteList.innerHTML = '<div class="no-results">Error loading client data</div>';
                    $fatcaAutocompleteList.style.display = "block";
                });
        }, 300);
    }

    // ── Event listeners ──────────────────────────────────────────────────
    document.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'cdr_client_code') {
            fetchClientCodes(e.target.value.trim());
        }
        if (e.target && e.target.id === 'master_client_code') {
            fetchMasterClientCodes(e.target.value.trim());
        }
        if (e.target && e.target.id === 'fatca_client_code') {
            fetchFatcaClientCodes(e.target.value.trim());
        }
    });
    
    document.addEventListener('click', function(e) {
        if ($autocompleteList) {
            if (e.target !== $inputCC && e.target !== $autocompleteList && !$autocompleteList.contains(e.target)) {
                $autocompleteList.style.display = 'none';
            }
        }
        if ($masterAutocompleteList) {
            if (e.target !== $masterCC && e.target !== $masterAutocompleteList && !$masterAutocompleteList.contains(e.target)) {
                $masterAutocompleteList.style.display = 'none';
            }
        }
        if ($fatcaAutocompleteList) {
            if (e.target !== $fatcaCC && e.target !== $fatcaAutocompleteList && !$fatcaAutocompleteList.contains(e.target)) {
                $fatcaAutocompleteList.style.display = 'none';
            }
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.target && e.target.id === 'cdr_client_code') {
            if (e.key === 'Enter') {
                e.preventDefault();
                if ($autocompleteList) $autocompleteList.style.display = 'none';
                onFetch();
            }
        }
        if (e.target && e.target.id === 'master_client_code') {
            if (e.key === 'Enter') {
                e.preventDefault();
                if ($masterAutocompleteList) $masterAutocompleteList.style.display = 'none';
                onFetchMaster();
            }
        }
        if (e.target && e.target.id === 'fatca_client_code') {
            if (e.key === 'Enter') {
                e.preventDefault();
                if ($fatcaAutocompleteList) $fatcaAutocompleteList.style.display = 'none';
                onFetchFatca();
            }
        }
    });

    document.getElementById('cdrBtnRefresh').addEventListener('click', function () {
        if (Object.keys(lastParams).length) doFetch(lastParams);
    });
    document.getElementById('cdrBtnClear').addEventListener('click', onClear);
    document.getElementById('cdrConfirmAdd').addEventListener('click', onConfirmAdd);
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'cdrBtnFetch') {
            onFetch();
        }
    });

    // ── Master button events ─────────────────────────────────────────────
    $masterBtnFetch.addEventListener('click', onFetchMaster);
    $masterBtnReset.addEventListener('click', onResetMaster);

    // ── FATCA button events ──────────────────────────────────────────────
    $fatcaBtnFetch.addEventListener('click', onFetchFatca);
    $fatcaBtnReset.addEventListener('click', onResetFatca);

    // ==================== EXISTING FUNCTIONS ====================
    function onFetch() {
        var cc   = $inputCC.value.trim();
        var from = getDateStrFromPicker('cdr_from_date');
        var to   = getDateStrFromPicker('cdr_to_date');

        if (!cc && (!from || !to)) {
            showError('Nothing to search',
                'Please enter a <strong>Client Code</strong> OR select a <strong>Date Range</strong>.');
            return;
        }

        if (!cc && from && to) {
            var fP = from.split('-'), tP = to.split('-');
            var fD = new Date(fP[2], fP[1]-1, fP[0]);
            var tD = new Date(tP[2], tP[1]-1, tP[0]);
            var diff = Math.round((tD - fD) / 86400000);
            
            if (isNaN(diff) || diff < 0) {
                showError('Invalid date range', '<em>From Date</em> must be before or equal to <em>To Date</em>.');
                return;
            }
            if (diff > MAX_DAYS) {
                showError('Date range too wide',
                    'Maximum allowed range is <strong>' + (MAX_DAYS + 1) + ' days</strong>. Current: ' + (diff + 1) + ' days.');
                return;
            }
        }

        var params = { client_code: cc, from_date: from, to_date: to };
        lastParams = params;
        doFetch(params);
    }

    function doFetch(params) {
        setLoading(true);
        showEmpty(false);
        showResults(false);

        var fd = new FormData();
        fd.append('client_code', params.client_code || '');
        fd.append('from_date',   params.from_date   || '');
        fd.append('to_date',     params.to_date     || '');

        fetch(AJAX_URL + '?action=fetch_report', { method: 'POST', body: fd })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (resp) {
                setLoading(false);
                if (resp.success) {
                    currentData = resp.data || [];
                    renderTable(currentData);
                    updateStatsBar(resp);
                    showResults(true);
                } else {
                    showError('No results', escHtml(resp.error || 'The request returned no data.'));
                }
            })
            .catch(function (err) {
                setLoading(false);
                showError('Request failed', 'Could not reach the server. ' + escHtml(err.message));
            });
    }

    function renderTable(data) {
        $tableBody.innerHTML = '';
        if (!data || !data.length) {
            $tableBody.innerHTML =
                '<tr><td colspan="13" class="text-center py-5 text-muted">' +
                '<i class="fas fa-inbox fa-2x mb-2 d-block"></i>No records found</td></tr>';
            return;
        }
        data.forEach(function (c, idx) {
            var row = document.createElement('tr');
            row.innerHTML =
                '<td class="text-muted" style="font-size:11px;">'+(idx+1)+'</td>' +
                '<td><code style="font-size:12px;">'+escHtml(c.client_code)+'</code></td>' +
                '<td><strong>'+escHtml(c.client_name)+'</strong></td>' +
                '<td>'+escHtml(c.pan)+'</td>' +
                '<td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+escAttr(c.email)+'">'+escHtml(c.email)+'</td>' +
                '<td>'+escHtml(c.mobile)+'</td>' +
                '<td>'+escHtml(c.dob)+'</td>' +
                '<td>'+escHtml(c.tax_status)+'</td>' +
                '<td>'+escHtml(c.ucc_status)+'</td>' +
                '<td>'+escHtml(c.holding_nature)+'</td>' +
                '<td>'+escHtml(c.account_no_1)+'</td>' +
                '<td>'+escHtml(c.ifsc_code_1)+'</td>' +
                '<td>'+(c.exists_in_db
                    ? '<span class="sbadge sbadge-exists"><i class="fas fa-check-circle"></i> Exists</span>'
                    : '<span class="sbadge sbadge-missing"><i class="fas fa-times-circle"></i> Not in DB</span>')+'</td>' +
                '<td>'+actionBtns(c)+'</td>';
            $tableBody.appendChild(row);
        });
    }

    function actionBtns(c) {
        var cc = escAttr(c.client_code);
        var html = '<button class="btn-tbl-view" onclick="cdrViewDetails(\''+cc+'\')"><i class="fas fa-eye"></i> View</button>';
        
        if (c.exists_in_db) {
            // For existing records - show Update button that fetches fresh data from NSE
            html += '<button class="btn-tbl-update" onclick="cdrFetchAndUpdate(\''+cc+'\')"><i class="fas fa-sync-alt"></i> Update from NSE</button>';
        } else {
            // For new records - show Add button
            html += '<button class="btn-tbl-add" onclick="cdrShowAdd(\''+cc+'\')"><i class="fas fa-plus"></i> Add to DB</button>';
        }
        return html;
    }

    function updateStatsBar(resp) {
        $totalCount.textContent = resp.total || 0;
        $dataSource.textContent = '⚡ NSE Live API';
        if (resp.search_type === 'client_code') {
            $searchInfo.innerHTML = '— <i class="fas fa-user me-1"></i>Client Code: <strong>'+escHtml(resp.search_value)+'</strong>';
        } else {
            $searchInfo.innerHTML = '— <i class="fas fa-calendar-alt me-1"></i>Date Range: <strong>'+escHtml(resp.search_value)+'</strong>';
        }
    }

    window.cdrViewDetails = function (cc) {
        var c = currentData.find(function (x) { return x.client_code === cc; });
        if (!c) { alert('Client record not found.'); return; }
        var rows = [
            ['Client Code',        '<code>'+escHtml(c.client_code)+'</code>'],
            ['Primary Holder Name','<strong>'+escHtml(c.client_name)+'</strong>'],
            ['PAN',                escHtml(c.pan)            || '—'],
            ['Date of Birth',      escHtml(c.dob)            || '—'],
            ['Tax Status',         escHtml(c.tax_status)     || '—'],
            ['Holding Nature',     escHtml(c.holding_nature) || '—'],
            ['Email',              escHtml(c.email)          || '—'],
            ['Mobile',             escHtml(c.mobile)         || '—'],
            ['Account Type',       escHtml(c.account_type_1) || '—'],
            ['Account Number',     escHtml(c.account_no_1)   || '—'],
            ['Bank Name',          escHtml(c.bank1_name || c.bd_bank_code_1) || '—'],
            ['IFSC Code',          escHtml(c.ifsc_code_1)    || '—'],
            ['Created At',         escHtml(c.created_at)     || '—'],
            ['In Database',        c.exists_in_db
                ? '<span class="sbadge sbadge-exists">Yes</span>'
                : '<span class="sbadge sbadge-missing">No</span>']
        ];
        document.getElementById('cdrModalBody').innerHTML = rows.map(function (r) {
            return '<div class="detail-row"><div class="detail-label">'+r[0]+'</div><div class="detail-value">'+r[1]+'</div></div>';
        }).join('');
        new bootstrap.Modal(document.getElementById('cdrViewModal')).show();
    };

    function generateClientDetailsHTML(c) {
        return `
            <div style="padding: 20px;">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user-tag"></i> Client Code</span>
                        <span class="info-value"><code>${escHtml(c.client_code)}</code></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user"></i> Primary Holder Name</span>
                        <span class="info-value"><strong>${escHtml(c.client_name)}</strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-id-card"></i> PAN</span>
                        <span class="info-value">${escHtml(c.pan) || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar"></i> Date of Birth</span>
                        <span class="info-value">${escHtml(c.dob) || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-tag"></i> Tax Status</span>
                        <span class="info-value">${escHtml(c.tax_status) || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-handshake"></i> Holding Nature</span>
                        <span class="info-value">${escHtml(c.holding_nature) || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                        <span class="info-value">${escHtml(c.email) || '—'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-phone"></i> Mobile</span>
                        <span class="info-value">${escHtml(c.mobile) || '—'}</span>
                    </div>
                </div>
                
                <div class="bank-details-section">
                    <div class="section-title">
                        <i class="fas fa-university"></i> Bank Details
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Account Type</span>
                            <span class="info-value">${escHtml(c.account_type_1) || '—'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Account Number</span>
                            <span class="info-value">${escHtml(c.account_no_1) || '—'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Bank Name</span>
                            <span class="info-value">${escHtml(c.bank1_name || c.bd_bank_code_1) || '—'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">IFSC Code</span>
                            <span class="info-value">${escHtml(c.ifsc_code_1) || '—'}</span>
                        </div>
                    </div>
                </div>
                
                <div class="bank-details-section" style="margin-top: 8px;">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i> Additional Information
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">NSE Created Date</span>
                            <span class="info-value">${escHtml(c.created_at) || '—'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">FATCA Exists</span>
                            <span class="info-value">${c.fatca_exists === 'Y' ? 'Yes' : 'No'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">UCC Status</span>
                            <span class="info-value">${escHtml(c.ucc_status) || '—'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    window.cdrShowAdd = function (cc) {
        var c = currentData.find(function (x) { return x.client_code === cc; });
        if (!c) { alert('Client data not found.'); return; }
        
        pendingAddClient = c;
        
        var html = generateClientDetailsHTML(c);
        
        if (!c.account_no_1 && !c.ifsc_code_1) {
            html += `
                <div class="alert alert-warning mx-3 mb-3" style="border-radius: 10px;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> Bank account details are not available from NSE. 
                    You can update them later after adding the client.
                </div>
            `;
        }
        
        document.getElementById('cdrAddClientContent').innerHTML = html;
        document.getElementById('cdrAddModal').querySelector('.modal-title').innerHTML = '<i class="fas fa-plus-circle me-2 text-success"></i>Add Client to Database';
        document.getElementById('cdrConfirmAdd').innerHTML = '<i class="fas fa-save me-1"></i>Confirm Add to Database';
        new bootstrap.Modal(document.getElementById('cdrAddModal')).show();
    };

    window.cdrShowUpdate = function (cc, userId) {
        var c = currentData.find(function (x) { return x.client_code === cc; });
        if (!c) { alert('Client data not found.'); return; }
        
        pendingAddClient = c;
        
        var formHtml = `
            <div style="padding: 20px;">
                <input type="hidden" id="update_user_id" value="${escAttr(userId)}">
                <input type="hidden" id="update_client_code" value="${escAttr(c.client_code)}">
                <input type="hidden" id="update_nse_created_at" value="${escAttr(c.created_at || '')}">
                
                <div class="info-grid">
                    <div class="info-item">
                        <label class="info-label">Primary Holder Name *</label>
                        <input type="text" class="form-control form-control-sm" id="update_primary_holder_name" value="${escAttr(c.client_name)}" required>
                    </div>
                    <div class="info-item">
                        <label class="info-label">Date of Birth *</label>
                        <input type="date" class="form-control form-control-sm" id="update_primary_holder_dob" value="${fmtDateInput(c.dob)}" required>
                    </div>
                    <div class="info-item">
                        <label class="info-label">PAN *</label>
                        <input type="text" class="form-control form-control-sm" id="update_primary_holder_pan" value="${escAttr(c.pan)}" required placeholder="ABCDE1234F">
                    </div>
                    <div class="info-item">
                        <label class="info-label">Tax Status *</label>
                        <select class="form-control form-control-sm" id="update_tax_status" required>
                            <option value="">Select Tax Status</option>
                            <option value="INDIVIDUAL" ${c.tax_status === 'INDIVIDUAL' ? 'selected' : ''}>INDIVIDUAL</option>
                            <option value="HUF" ${c.tax_status === 'HUF' ? 'selected' : ''}>HUF</option>
                            <option value="COMPANY" ${c.tax_status === 'COMPANY' ? 'selected' : ''}>COMPANY</option>
                            <option value="FOREIGN" ${c.tax_status === 'FOREIGN' ? 'selected' : ''}>FOREIGN</option>
                        </select>
                    </div>
                    <div class="info-item">
                        <label class="info-label">Holding Nature *</label>
                        <select class="form-control form-control-sm" id="update_holding_nature" required>
                            <option value="">Select Holding Nature</option>
                            <option value="SINGLE" ${c.holding_nature === 'SINGLE' ? 'selected' : ''}>SINGLE</option>
                            <option value="JOINT" ${c.holding_nature === 'JOINT' ? 'selected' : ''}>JOINT</option>
                            <option value="ANYONE" ${c.holding_nature === 'ANYONE' ? 'selected' : ''}>ANYONE</option>
                        </select>
                    </div>
                    <div class="info-item">
                        <label class="info-label">Email *</label>
                        <input type="email" class="form-control form-control-sm" id="update_email" value="${escAttr(c.email)}" required>
                    </div>
                    <div class="info-item">
                        <label class="info-label">Mobile No *</label>
                        <input type="text" class="form-control form-control-sm" id="update_mobile_no" value="${escAttr(c.mobile)}" required placeholder="10 digits">
                    </div>
                </div>
                
                <div class="bank-details-section">
                    <div class="section-title">Bank Details</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label class="info-label">Account Type</label>
                            <select class="form-control form-control-sm" id="update_account_type_1">
                                <option value="">Select</option>
                                <option value="SB" ${c.account_type_1 === 'SB' ? 'selected' : ''}>SB</option>
                                <option value="CA" ${c.account_type_1 === 'CA' ? 'selected' : ''}>CA</option>
                                <option value="NRE" ${c.account_type_1 === 'NRE' ? 'selected' : ''}>NRE</option>
                                <option value="NRO" ${c.account_type_1 === 'NRO' ? 'selected' : ''}>NRO</option>
                            </select>
                        </div>
                        <div class="info-item">
                            <label class="info-label">Account Number</label>
                            <input type="text" class="form-control form-control-sm" id="update_account_no_1" value="${escAttr(c.account_no_1)}">
                        </div>
                        <div class="info-item">
                            <label class="info-label">Bank Name</label>
                            <input type="text" class="form-control form-control-sm" id="update_bank_name_1" value="${escAttr(c.bank1_name || '')}">
                        </div>
                        <div class="info-item">
                            <label class="info-label">IFSC Code</label>
                            <input type="text" class="form-control form-control-sm" id="update_ifsc_code_1" value="${escAttr(c.ifsc_code_1)}" placeholder="e.g. SBIN0001234">
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('cdrAddClientContent').innerHTML = formHtml;
        document.getElementById('cdrAddModal').querySelector('.modal-title').innerHTML = '<i class="fas fa-edit me-2 text-warning"></i>Update Client in Database';
        document.getElementById('cdrConfirmAdd').innerHTML = '<i class="fas fa-save me-1"></i>Update Client';
        new bootstrap.Modal(document.getElementById('cdrAddModal')).show();
    };

    /**
     * Fetch fresh data from NSE and update the client in database
     */
    window.cdrFetchAndUpdate = function (cc) {
        if (!cc) {
            showToast('error', 'Client code is required');
            return;
        }

        if (!confirm('This will fetch the latest client master data from NSE and update the database. Continue?')) {
            return;
        }

        setLoading(true);

        var fd = new FormData();
        fd.append('client_code', cc);
        var c = currentData.find(function(x) { return x.client_code === cc; });
        if (c && c.ucc_status) {
            fd.append('ucc_status', c.ucc_status);
        }

        fetch(MASTER_AJAX_URL + '?action=fetch_master', { 
            method: 'POST', 
            body: fd 
        })
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(resp) {
            setLoading(false);
            if (resp.success) {
                showToast('success', 'Client <strong>' + escHtml(cc) + '</strong> updated successfully from NSE. (' + (resp.affected_rows || 0) + ' rows affected)');
                // Refresh the current data
                if (Object.keys(lastParams).length) {
                    doFetch(lastParams);
                } else {
                    // If no search params, just refresh the page
                    location.reload();
                }
            } else {
                showToast('error', 'Update failed: ' + escHtml(resp.error || 'Unknown error'));
            }
        })
        .catch(function(err) {
            setLoading(false);
            showToast('error', 'Request failed: ' + escHtml(err.message));
        });
    };

    function onConfirmAdd() {
        if (!pendingAddClient) {
            showToast('error', 'No client data to save');
            return;
        }
        
        var isUpdate = document.getElementById('update_user_id') !== null;
        var fd = new FormData();
        
        if (isUpdate) {
            fd.append('client_code',         document.getElementById('update_client_code').value);
            fd.append('user_id',             document.getElementById('update_user_id').value);
            fd.append('nse_created_at',      document.getElementById('update_nse_created_at').value);
            fd.append('primary_holder_name', document.getElementById('update_primary_holder_name').value);
            fd.append('primary_holder_dob',  document.getElementById('update_primary_holder_dob').value);
            fd.append('primary_holder_pan',  document.getElementById('update_primary_holder_pan').value);
            fd.append('tax_status',          document.getElementById('update_tax_status').value);
            fd.append('holding_nature',      document.getElementById('update_holding_nature').value);
            fd.append('email',               document.getElementById('update_email').value);
            fd.append('mobile_no',           document.getElementById('update_mobile_no').value);
            fd.append('account_type_1',      document.getElementById('update_account_type_1').value);
            fd.append('account_no_1',        document.getElementById('update_account_no_1').value);
            fd.append('bank_name_1',         document.getElementById('update_bank_name_1').value);
            fd.append('ifsc_code_1',         document.getElementById('update_ifsc_code_1').value);
        } else {
            fd.append('client_code',         pendingAddClient.client_code);
            fd.append('user_id',             '');
            fd.append('nse_created_at',      pendingAddClient.created_at || '');
            fd.append('primary_holder_name', pendingAddClient.client_name || '');
            fd.append('primary_holder_dob',  fmtDateInput(pendingAddClient.dob));
            fd.append('primary_holder_pan',  pendingAddClient.pan || '');
            fd.append('tax_status',          pendingAddClient.tax_status || '');
            fd.append('holding_nature',      pendingAddClient.holding_nature || '');
            fd.append('email',               pendingAddClient.email || '');
            fd.append('mobile_no',           pendingAddClient.mobile || '');
            fd.append('account_type_1',      pendingAddClient.account_type_1 || '');
            fd.append('account_no_1',        pendingAddClient.account_no_1 || '');
            fd.append('bank_name_1',         pendingAddClient.bank1_name || '');
            fd.append('ifsc_code_1',         pendingAddClient.ifsc_code_1 || '');
        }

        bootstrap.Modal.getInstance(document.getElementById('cdrAddModal')).hide();
        setLoading(true);

        fetch(AJAX_URL + '?action=add_client', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                setLoading(false);
                if (resp.success) {
                    showToast('success', 'Client <strong>'+escHtml(fd.get('client_code'))+'</strong> '
                        +(resp.action === 'update' ? 'updated' : 'added')+' successfully.');
                    if (Object.keys(lastParams).length) doFetch(lastParams);
                    pendingAddClient = null;
                } else {
                    showToast('error', 'Operation failed: '+escHtml(resp.error||'Unknown error'));
                }
            })
            .catch(function (err) {
                setLoading(false);
                showToast('error', 'Request failed: '+escHtml(err.message));
            });
    }

    function fmtDateInput(s) {
        if (!s) return '';
        if (/^\d{2}-\d{2}-\d{4}$/.test(s)) {
            var p = s.split('-'); return p[2]+'-'+p[1]+'-'+p[0];
        }
        return s;
    }

    function onClear() {
        $inputCC.value = '';
        var today = new Date();
        var ago = new Date();
        ago.setDate(today.getDate() - MAX_DAYS);
        
        document.getElementById('cdr_from_date').value = formatDateForInput(ago);
        document.getElementById('cdr_to_date').value = formatDateForInput(today);
        
        currentData = []; 
        lastParams = {};
        showResults(false); 
        showEmpty(true);
        $tableBody.innerHTML = '';
        pendingAddClient = null;
        $autocompleteList.style.display = 'none';
        $autocompleteList.innerHTML = '';
    }

    function setLoading(on)  { $loader.classList.toggle('show', on); }
    function showResults(on) { $results.style.display = on ? '' : 'none'; }

    function showEmpty(on) {
        if (!on) { $emptyState.style.display = 'none'; return; }
        $emptyState.innerHTML =
            '<div class="es-icon text-primary"><i class="fas fa-search"></i></div>' +
            '<h6>No Data to Display</h6>' +
            '<p>Enter a <strong>Client Code</strong> (NSE live) <em>or</em> choose a ' +
            '<strong>Date Range</strong> (NSE live, max 7 days), then click <strong>"Fetch Report"</strong>.</p>';
        $emptyState.style.display = '';
    }

    function showError(title, msg) {
        showResults(false);
        $emptyState.innerHTML =
            '<div class="es-icon text-danger"><i class="fas fa-exclamation-triangle"></i></div>' +
            '<h6>'+title+'</h6><p>'+msg+'</p>';
        $emptyState.style.display = '';
    }

    function showToast(type, html) {
        var bg = type==='success' ? '#10b981' : (type==='warning' ? '#f59e0b' : '#ef4444');
        var t  = document.createElement('div');
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:'+bg+
            ';color:#fff;padding:12px 20px;border-radius:12px;font-size:13px;max-width:360px;'+
            'box-shadow:0 8px 24px rgba(0,0,0,.18);transition:opacity .3s;opacity:1;';
        t.innerHTML = html;
        document.body.appendChild(t);
        setTimeout(function(){ t.style.opacity='0'; }, 3500);
        setTimeout(function(){ document.body.removeChild(t); }, 4000);
    }

    function escHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
    function escAttr(s) { return escHtml(s); }

    // ==================== MASTER DATA FUNCTIONS ====================

    function onFetchMaster() {
        var clientCode = $masterCC.value.trim();

        if (!clientCode) {
            showMasterError('Please enter a Client Code / UCC to fetch master data.');
            return;
        }

        // Validate UCC format
        if (!/^[A-Z0-9]{6,10}$/i.test(clientCode)) {
            showMasterError('Invalid Client Code format. Please enter a valid UCC (6-10 alphanumeric characters).');
            return;
        }

        // Hide previous results and errors
        $masterResultContainer.style.display = 'none';
        $masterErrorContainer.style.display = 'none';

        // Show loading state
        $masterBtnText.textContent = 'Fetching...';
        $masterLoader.style.display = 'inline-block';
        $masterBtnFetch.disabled = true;

        var fd = new FormData();
        fd.append('client_code', clientCode);

        fetch(MASTER_AJAX_URL + '?action=fetch_master', { method: 'POST', body: fd })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(resp) {
                // Reset button state
                $masterBtnText.textContent = 'Fetch Master Data';
                $masterLoader.style.display = 'none';
                $masterBtnFetch.disabled = false;

                if (resp.success) {
                    displayMasterData(resp);
                    if ($masterAutocompleteList) $masterAutocompleteList.style.display = 'none';
                } else {
                    showMasterError(resp.error || 'Failed to fetch client master data.');
                }
            })
            .catch(function(err) {
                $masterBtnText.textContent = 'Fetch Master Data';
                $masterLoader.style.display = 'none';
                $masterBtnFetch.disabled = false;
                showMasterError('Request failed: ' + err.message);
            });
    }

    function displayMasterData(resp) {
        var data = resp.client_data || {};
        var action = resp.action || 'insert';
        var affectedRows = resp.affected_rows || 0;

        // Build field list - show only populated fields
        var fields = [
            { label: 'Client Code', value: data.CLIENT_CODE || '-' },
            { label: 'Primary Holder Name', value: data.PRIMARY_HOLDER_NAME || '-' },
            { label: 'PAN', value: data.PRIMARY_HOLDER_PAN || '-' },
            { label: 'Date of Birth', value: data.PRIMARY_HOLDER_DOB || '-' },
            { label: 'Gender', value: data.GENDER || '-' },
            { label: 'Tax Status', value: data.TAX_STATUS || '-' },
            { label: 'Occupation Code', value: data.OCCUPATION_CODE || '-' },
            { label: 'Holding Nature', value: data.HOLDING_NATURE || '-' },
            { label: 'Email', value: data.EMAIL || '-' },
            { label: 'Mobile No', value: data.INDIAN_MOBILE_NO || '-' },
            { label: 'Address 1', value: data.ADDRESS_1 || '-' },
            { label: 'Address 2', value: data.ADDRESS_2 || '-' },
            { label: 'City', value: data.CITY || '-' },
            { label: 'State', value: data.STATE || '-' },
            { label: 'Pincode', value: data.PINCODE || '-' },
            { label: 'Country', value: data.COUNTRY || '-' },
            { label: 'Account Type', value: data.ACCOUNT_TYPE_1 || '-' },
            { label: 'Account No', value: data.ACCOUNT_NO_1 || '-' },
            { label: 'Bank Holder Name', value: data.BANK_HOLDER_NAME_1 || '-' },
            { label: 'IFSC Code', value: data.IFSC_CODE_1 || '-' },
            { label: 'Bank Branch', value: data.BANK_BRANCH_1 || '-' },
        ];

        // Add nominee fields if present
        if (data.NOMINEE_1_NAME) {
            fields.push({ label: 'Nominee 1 Name', value: data.NOMINEE_1_NAME || '-' });
            fields.push({ label: 'Nominee 1 Relationship', value: data.NOMINEE_1_RELATIONSHIP || '-' });
            fields.push({ label: 'Nominee 1 Applicable %', value: data.NOMINEE_1_APPLICABLE_PERCENT || '-' });
        }
        if (data.NOMINEE_2_NAME) {
            fields.push({ label: 'Nominee 2 Name', value: data.NOMINEE_2_NAME || '-' });
            fields.push({ label: 'Nominee 2 Relationship', value: data.NOMINEE_2_RELATIONSHIP || '-' });
            fields.push({ label: 'Nominee 2 Applicable %', value: data.NOMINEE_2_APPLICABLE_PERCENT || '-' });
        }
        if (data.NOMINEE_3_NAME) {
            fields.push({ label: 'Nominee 3 Name', value: data.NOMINEE_3_NAME || '-' });
            fields.push({ label: 'Nominee 3 Relationship', value: data.NOMINEE_3_RELATIONSHIP || '-' });
            fields.push({ label: 'Nominee 3 Applicable %', value: data.NOMINEE_3_APPLICABLE_PERCENT || '-' });
        }

        // Also display second and third holder data if present
        if (data.SECOND_HOLDER_NAME) {
            fields.push({ label: 'Second Holder Name', value: data.SECOND_HOLDER_NAME || '-' });
            fields.push({ label: 'Second Holder PAN', value: data.SECOND_HOLDER_PAN || '-' });
            fields.push({ label: 'Second Holder DOB', value: data.SECOND_HOLDER_DOB || '-' });
        }
        if (data.THIRD_HOLDER_NAME) {
            fields.push({ label: 'Third Holder Name', value: data.THIRD_HOLDER_NAME || '-' });
            fields.push({ label: 'Third Holder PAN', value: data.THIRD_HOLDER_PAN || '-' });
            fields.push({ label: 'Third Holder DOB', value: data.THIRD_HOLDER_DOB || '-' });
        }

        // Build HTML
        var html = fields.map(function(f) {
            return '<div class="field-item">' +
                        '<span class="field-label">' + escapeHtmlForAutocomplete(f.label) + '</span>' +
                        '<span class="field-value">' + escapeHtmlForAutocomplete(f.value) + '</span>' +
                    '</div>';
        }).join('');

        $masterFieldsContainer.innerHTML = html;

        // Update status badge
        var statusText = action === 'update' ? 'Updated' : 'Saved';
        var statusClass = action === 'update' ? 'bg-warning' : 'bg-success';
        $masterStatusBadge.textContent = statusText + ' (' + affectedRows + ' rows)';
        $masterStatusBadge.className = 'badge ' + statusClass;

        // Action message
        var actionMsg = action === 'update' 
            ? 'Client data updated successfully in database. (' + affectedRows + ' rows affected)' 
            : 'Client data saved to database successfully.';
        $masterActionMessage.textContent = actionMsg;

        // Show the result container
        $masterResultContainer.style.display = 'block';
        $masterErrorContainer.style.display = 'none';
    }

    function showMasterError(msg) {
        $masterErrorMessage.textContent = msg;
        $masterErrorContainer.style.display = 'block';
        $masterResultContainer.style.display = 'none';
    }

    function onResetMaster() {
        $masterCC.value = '';
        $masterResultContainer.style.display = 'none';
        $masterErrorContainer.style.display = 'none';
        if ($masterAutocompleteList) {
            $masterAutocompleteList.style.display = 'none';
            $masterAutocompleteList.innerHTML = '';
        }
        $masterBtnText.textContent = 'Fetch Master Data';
        $masterLoader.style.display = 'none';
        $masterBtnFetch.disabled = false;
    }

    // ==================== FATCA DATA FUNCTIONS ====================

    function onFetchFatca() {
        var clientCodeVal = $fatcaCC.value.trim();
        var from = $fatcaFromDate.value;
        var to = $fatcaToDate.value;

        if (!clientCodeVal && (!from || !to)) {
            showFatcaError('Please enter a Client Code / PAN OR select a Date Range.');
            return;
        }

        // Hide previous results and errors
        $fatcaResultContainer.style.display = 'none';
        $fatcaErrorContainer.style.display = 'none';

        // Show loading state
        $fatcaBtnText.textContent = 'Syncing...';
        $fatcaLoader.style.display = 'inline-block';
        $fatcaBtnFetch.disabled = true;

        var fd = new FormData();
        fd.append('client_code', clientCodeVal);
        fd.append('from_date', from);
        fd.append('to_date', to);

        fetch(FATCA_AJAX_URL + '?action=fetch_fatca', { method: 'POST', body: fd })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(resp) {
                $fatcaBtnText.textContent = 'Sync FATCA';
                $fatcaLoader.style.display = 'none';
                $fatcaBtnFetch.disabled = false;

                if (resp.success) {
                    displayFatcaData(resp);
                } else {
                    showFatcaError(resp.error || 'Please enter the correct detail');
                }
            })
            .catch(function(err) {
                $fatcaBtnText.textContent = 'Sync FATCA';
                $fatcaLoader.style.display = 'none';
                $fatcaBtnFetch.disabled = false;
                showFatcaError('Please enter the correct detail');
            });
    }

    function displayFatcaData(resp) {
        var count = resp.saved_count || 0;
        var reportData = resp.report_data || [];

        var html = '';
        if (reportData.length === 0) {
            html = '<tr><td colspan="3" class="text-center text-muted">No records updated.</td></tr>';
        } else {
            reportData.forEach(function(item) {
                var pekrnHtml = item.pekrn ? ' <span class="badge bg-light text-dark ms-2" style="font-size:10px; border:1px solid #e8dbff;">PEKRN: ' + escapeHtmlForAutocomplete(item.pekrn) + '</span>' : '';
                html += '<tr>' +
                            '<td><code>' + escapeHtmlForAutocomplete(item.pan_rp) + '</code>' + pekrnHtml + '</td>' +
                            '<td><strong>' + escapeHtmlForAutocomplete(item.inv_name) + '</strong></td>' +
                            '<td><span class="badge" style="background-color: #d1fae5; color: #065f46; font-size:11px; padding: 4px 8px; border-radius: 6px;"><i class="fas fa-check-circle me-1"></i>Synced</span></td>' +
                        '</tr>';
            });
        }

        $fatcaResultTableBody.innerHTML = html;
        $fatcaStatusBadge.textContent = 'Synced (' + count + ' records)';
        $fatcaActionMessage.textContent = resp.message || 'FATCA records saved/updated in database.';
        
        $fatcaResultContainer.style.display = 'block';
        $fatcaErrorContainer.style.display = 'none';
    }

    function showFatcaError(msg) {
        $fatcaErrorMessage.textContent = msg;
        $fatcaErrorContainer.style.display = 'block';
        $fatcaResultContainer.style.display = 'none';
    }

    function onResetFatca() {
        $fatcaCC.value = '';
        initFatcaDates();
        $fatcaResultContainer.style.display = 'none';
        $fatcaErrorContainer.style.display = 'none';
        $fatcaBtnText.textContent = 'Sync FATCA';
        $fatcaLoader.style.display = 'none';
        $fatcaBtnFetch.disabled = false;
        if ($fatcaAutocompleteList) {
            $fatcaAutocompleteList.style.display = 'none';
            $fatcaAutocompleteList.innerHTML = '';
        }
    }

})();
</script>