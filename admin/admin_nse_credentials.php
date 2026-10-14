<?php
require_once('auth_guard.php');

// No need to start session here - it's already started in database.php
require_once 'database.php';

// Check if user is logged in and is admin (admin_role)
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['superuser']) || $_SESSION['superuser'] != 1) {
    header('Location: login.php');
    exit;
}

$db = new MySQLDB();
?>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-key me-2"></i>NSE Credentials Management
                        <button type="button" class="btn btn-light btn-sm float-end" id="refreshCredentialsBtn">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-success btn-sm float-end me-2" id="addCredentialBtn">
                            <i class="fas fa-plus"></i> Add New Credentials
                        </button>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> Only one set of credentials can be active at a time. 
                        When you activate a new set, all others will be automatically deactivated.
                    </div>
                    
                    <div class="table-responsive">
                        <table id="credentialsTable" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>TM Code</th>
                                    <th>Login User ID</th>
                                    <th>ARN Number</th>
                                    <th>Environment</th>
                                    <th>Activation Status</th>
                                    <th>API URL</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="credentialsTableBody">
                                <tr>
                                    <td colspan="10" class="text-center">Loading credentials...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="credentialModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Add NSE Credentials</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="credentialForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="credentialId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" class="form-control" name="name" id="name" required>
                            <small class="text-muted">A descriptive name for this credential set</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">TM Code</label>
                            <input type="text" class="form-control" name="tm_code" id="tm_code" required>
                            <small class="text-muted">Trading Member Code</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" id="secretKeyLabel">Secret Key <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="secret_key" id="secret_key">
                            <small class="text-muted" id="secretKeyHelp">API Secret Key</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Member Key</label>
                            <input type="text" class="form-control" name="member_key" id="member_key">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Login User ID</label>
                            <input type="text" class="form-control" name="login_user_id" id="login_user_id">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">EUIN Number</label>
                            <input type="text" class="form-control" name="euin_number" id="euin_number">
                            <small class="text-muted">Employee Unique Identification Number</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ARN Number</label>
                            <input type="text" class="form-control" name="arn_number" id="arn_number" maxlength="20">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Environment</label>
                            <select class="form-select" name="environment" id="environment">
                                <option value="Live">Live</option>
                                <option value="UAT">UAT</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Activation Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="inactive">Inactive</option>
                                <option value="active">Active</option>
                            </select>
                            <small class="text-muted text-warning">Setting to Active will deactivate all other credentials</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API URL</label>
                        <input type="url" class="form-control" name="api_url" id="api_url" 
                               placeholder="https://api.nseindia.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveCredentialBtn">
                        <span id="saveBtnText">Save Credentials</span>
                        <span id="saveBtnSpinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        display: inline-block;
        text-align: center;
        min-width: 70px;
    }
    .status-active {
        background-color: #28a745;
        color: white;
    }
    .status-inactive {
        background-color: #dc3545;
        color: white;
    }
    .required:after {
        content: " *";
        color: red;
    }
    .toast-message {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);   opacity: 1; }
    }
    .btn-action {
        margin: 0 2px;
    }
    .refresh-spin {
        animation: spin 0.5s ease-in-out;
    }
    @keyframes spin {
        0%   { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .activate-btn {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 4px 10px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .activate-btn:hover {
        background-color: #218838;
        color: white;
        transform: scale(1.05);
    }
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .table-responsive {
        overflow-x: auto;
    }
    /* ── Action column ── */
    .action-cell {
        min-width: 130px;
        white-space: nowrap;
        vertical-align: middle;
    }
    .action-icon-row {
        display: flex;
        gap: 4px;
        align-items: center;
        margin-bottom: 5px;
    }
    .action-status-row {
        display: flex;
        justify-content: flex-start;
    }
    .action-status-row .badge {
        font-size: 0.75rem;
        padding: 5px 10px;
        white-space: nowrap;
    }
    .action-status-row .activate-btn {
        font-size: 0.75rem;
        padding: 4px 10px;
        white-space: nowrap;
    }
    .fade-out {
        animation: fadeOut 0.3s ease-out forwards;
    }
    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(0); }
        to   { opacity: 0; transform: translateX(-20px); }
    }
    .fade-in {
        animation: fadeIn 0.4s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateX(20px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    /* Highlight newly added / updated row */
    .row-highlight {
        animation: rowHighlight 2s ease-out;
    }
    @keyframes rowHighlight {
        0%   { background-color: #d4edda; }
        100% { background-color: transparent; }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    'use strict';

    let dataTable      = null;
    let isLoading      = false;
    let isSaving       = false;
    let credentialsData = [];

    /* ------------------------------------------------------------------ */
    /*  Bootstrap                                                           */
    /* ------------------------------------------------------------------ */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        loadCredentials();
        bindEvents();
    }

    /* ------------------------------------------------------------------ */
    /*  Event bindings                                                      */
    /* ------------------------------------------------------------------ */
    function bindEvents() {
        // Refresh button
        const refreshBtn = document.getElementById('refreshCredentialsBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function (e) {
                e.preventDefault();
                refreshData();
            });
        }

        // Add button
        const addBtn = document.getElementById('addCredentialBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showAddModal();
            });
        }

        // Form submit
        const form = document.getElementById('credentialForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!isSaving) saveCredentials();
            });
        }

        // Reset form when modal closes
        const modal = document.getElementById('credentialModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', resetForm);
        }
    }

    function resetForm() {
        const form = document.getElementById('credentialForm');
        if (form) form.reset();
        document.getElementById('credentialId').value    = '';
        document.getElementById('secret_key').value     = '';
        document.getElementById('secret_key').required  = true;
        document.getElementById('secretKeyLabel').innerHTML = 'Secret Key <span class="text-danger">*</span>';
        document.getElementById('secretKeyHelp').innerHTML  = 'API Secret Key';
        setSaveButtonState(false);
    }

    /* ------------------------------------------------------------------ */
    /*  Loading / message helpers                                           */
    /* ------------------------------------------------------------------ */
    function showLoading() {
        if (document.getElementById('tempLoadingOverlay')) return;
        const overlay = document.createElement('div');
        overlay.id = 'tempLoadingOverlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;justify-content:center;align-items:center;';
        overlay.innerHTML = '<div class="spinner-border text-light" style="width:3rem;height:3rem;" role="status"><span class="visually-hidden">Loading…</span></div>';
        document.body.appendChild(overlay);
    }

    function hideLoading() {
        const overlay = document.getElementById('tempLoadingOverlay');
        if (overlay) overlay.remove();
    }

    function showMessage(message, type) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show toast-message`;
        const icon = type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-circle' : 'info-circle');
        toast.innerHTML = `<i class="fas fa-${icon} me-2"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(toast);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3500);
    }

    function setSaveButtonState(saving) {
        isSaving = saving;
        const btn     = document.getElementById('saveCredentialBtn');
        const text    = document.getElementById('saveBtnText');
        const spinner = document.getElementById('saveBtnSpinner');
        if (!btn) return;
        btn.disabled = saving;
        if (text)    text.textContent = saving ? 'Saving…' : 'Save Credentials';
        if (spinner) spinner.classList.toggle('d-none', !saving);
    }

    /* ------------------------------------------------------------------ */
    /*  Status normaliser                                                   */
    /* ------------------------------------------------------------------ */
    function normalizeStatus(status) {
        if (status === 1 || status === '1' || (typeof status === 'string' && status.toLowerCase() === 'active')) {
            return 'active';
        }
        return 'inactive';
    }

    /* ------------------------------------------------------------------ */
    /*  Data loading                                                        */
    /* ------------------------------------------------------------------ */
    function refreshData() {
        if (isLoading) return;
        const icon = document.querySelector('#refreshCredentialsBtn i');
        if (icon) {
            icon.classList.add('refresh-spin');
            setTimeout(() => icon.classList.remove('refresh-spin'), 1000);
        }
        loadCredentials(true);
    }

    /**
     * Fetch all credentials from the server and re-render the table.
     * This is the single source of truth – called after every mutation.
     *
     * @param {boolean} forceRefresh  Add cache-busting timestamp (default false)
     * @param {number|null} highlightId  Optional row ID to highlight after render
     */
    function loadCredentials(forceRefresh = false, highlightId = null) {
        if (isLoading) return;
        isLoading = true;
        showLoading();

        const url = `nse_credentials_handler.php?action=get_all&t=${Date.now()}`;

        fetch(url, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            isLoading = false;
            hideLoading();

            if (data.success) {
                credentialsData = (data.data || []).map(cred => {
                    cred.status = normalizeStatus(cred.status);
                    return cred;
                });
                credentialsData.sort((a, b) => a.id - b.id);
                renderTable(credentialsData, highlightId);
            } else {
                showMessage(data.message || 'Failed to load credentials', 'danger');
                renderTable([]);
            }
        })
        .catch(error => {
            isLoading = false;
            hideLoading();
            console.error('loadCredentials error:', error);
            showMessage('Error loading credentials: ' + error.message, 'danger');
            renderTable([]);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable helpers                                                   */
    /* ------------------------------------------------------------------ */
    function destroyDataTable() {
        if (dataTable) {
            try { dataTable.destroy(); } catch (e) { /* ignore */ }
            dataTable = null;
        }
        if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
            try {
                if ($.fn.DataTable.isDataTable('#credentialsTable')) {
                    $('#credentialsTable').DataTable().destroy();
                }
            } catch (e) { /* ignore */ }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Render                                                              */
    /* ------------------------------------------------------------------ */
    function renderTable(credentials, highlightId = null) {
        const tbody = document.getElementById('credentialsTableBody');
        if (!tbody) return;

        destroyDataTable();

        if (!credentials || credentials.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center">No NSE credentials found</td></tr>';
            return;
        }

        let html = '';
        credentials.forEach(cred => {
            const statusClass   = cred.status === 'active' ? 'status-active' : 'status-inactive';
            const statusText    = cred.status === 'active' ? 'Active' : 'Inactive';
            const environment   = cred.environment ? cred.environment.toUpperCase() : 'N/A';
            const created       = cred.created_at ? new Date(cred.created_at).toLocaleDateString() : 'N/A';
            const activationBtn = cred.status === 'inactive'
                ? `<button class="btn btn-sm activate-btn btn-action" onclick="window.activateCredential(${cred.id})" title="Activate this credential"><i class="fas fa-power-off"></i> Activate</button>`
                : `<span class="badge bg-success" style="padding:5px 10px;"><i class="fas fa-check-circle"></i> Active</span>`;

            html += `
                <tr data-cred-id="${cred.id}">
                    <td>${escapeHtml(String(cred.id))}</td>
                    <td><strong>${escapeHtml(cred.name)}</strong></td>
                    <td>${escapeHtml(cred.tm_code)}</td>
                    <td>${escapeHtml(cred.login_user_id || '-')}</td>
                    <td>${escapeHtml(cred.arn_number || '-')}</td>
                    <td><span class="badge bg-info">${escapeHtml(environment)}</span></td>
                    <td><span class="status-badge ${statusClass}" data-status="${cred.status}">${statusText}</span></td>
                    <td><small>${escapeHtml(cred.api_url ? cred.api_url.substring(0, 30) : '-')}</small></td>
                    <td>${created}</td>
                    <td class="action-cell">
                        <div class="action-icon-row">
                            <button class="btn btn-sm btn-info btn-action"    onclick="window.viewCredential(${cred.id})"                               title="View Details"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-warning btn-action" onclick="window.editCredential(${cred.id})"                               title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger btn-action"  onclick="window.deleteCredential(${cred.id}, '${escapeHtml(cred.name)}')" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                        <div class="action-status-row">${activationBtn}</div>
                    </td>
                </tr>`;
        });

        tbody.innerHTML = html;

        // Highlight a specific row if requested
        if (highlightId) {
            const targetRow = tbody.querySelector(`tr[data-cred-id="${highlightId}"]`);
            if (targetRow) targetRow.classList.add('row-highlight');
        }

        // Re-initialise DataTable
        setTimeout(() => {
            if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
                try {
                    dataTable = $('#credentialsTable').DataTable({
                        pageLength : 10,
                        order      : [[0, 'asc']],
                        destroy    : true,
                        retrieve   : false,
                        responsive : true,
                        language   : { emptyTable: 'No NSE credentials found' },
                        columnDefs : [
                            { orderable: true,  targets: [0, 1] },
                            { orderable: false, targets: [8], width: '130px' }
                        ]
                    });
                } catch (e) {
                    console.error('DataTable init error:', e);
                }
            }
        }, 50);
    }

    /* ------------------------------------------------------------------ */
    /*  Add modal                                                           */
    /* ------------------------------------------------------------------ */
    function showAddModal() {
        resetForm();
        document.getElementById('modalTitle').innerText = 'Add NSE Credentials';
        document.getElementById('environment').value = 'Live';
        document.getElementById('status').value      = 'inactive';
        new bootstrap.Modal(document.getElementById('credentialModal')).show();
    }

    /* ------------------------------------------------------------------ */
    /*  Save (add & update)                                                 */
    /* ------------------------------------------------------------------ */
    function saveCredentials() {
        const name      = document.getElementById('name').value.trim();
        const tmCode    = document.getElementById('tm_code').value.trim();
        const secretKey = document.getElementById('secret_key').value;
        const id        = document.getElementById('credentialId').value;

        if (!name || !tmCode) {
            showMessage('Please fill in all required fields (Name, TM Code)', 'warning');
            return;
        }
        if (!id && !secretKey) {
            showMessage('Secret Key is required for new credentials', 'warning');
            return;
        }

        const formData = new FormData(document.getElementById('credentialForm'));
        formData.set('action', id ? 'update' : 'add');
        if (id) formData.set('id', id);

        setSaveButtonState(true);
        showLoading();

        fetch('nse_credentials_handler.php', {
            method : 'POST',
            body   : formData,
            headers: { 'Cache-Control': 'no-cache' }
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            setSaveButtonState(false);

            if (data.success) {
                // Close modal first
                const modalEl = document.getElementById('credentialModal');
                const modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) modalInst.hide();

                const savedId = data.data && data.data.id ? parseInt(data.data.id) : (id ? parseInt(id) : null);
                showMessage(id ? 'Credential updated successfully' : 'Credential added successfully', 'success');

                // *** KEY FIX: always reload from server so table reflects DB truth instantly ***
                loadCredentials(true, savedId);
            } else {
                showMessage(data.message || 'Failed to save credentials', 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            setSaveButtonState(false);
            console.error('saveCredentials error:', error);
            showMessage('Error saving credentials: ' + error.message, 'danger');
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Activate                                                            */
    /* ------------------------------------------------------------------ */
    window.activateCredential = function (id) {
        if (!confirm('⚠️ Are you sure you want to activate this credential?\n\nThis will deactivate all other active credentials.')) return;

        showLoading();

        const formData = new FormData();
        formData.append('action', 'activate');
        formData.append('id', id);

        fetch('nse_credentials_handler.php', {
            method : 'POST',
            body   : formData,
            headers: { 'Cache-Control': 'no-cache' }
        })
        .then(r => r.json())
        .then(result => {
            hideLoading();
            if (result.success) {
                showMessage(result.message || 'Credential activated successfully', 'success');
                // *** KEY FIX: reload from server ***
                loadCredentials(true, parseInt(id));
            } else {
                showMessage(result.message || 'Failed to activate credentials', 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('activateCredential error:', error);
            showMessage('Error activating credentials: ' + error.message, 'danger');
        });
    };

    /* ------------------------------------------------------------------ */
    /*  Edit                                                                */
    /* ------------------------------------------------------------------ */
    window.editCredential = function (id) {
        showLoading();

        fetch(`nse_credentials_handler.php?action=get_by_id&id=${id}&t=${Date.now()}`, {
            method : 'GET',
            headers: { 'Cache-Control': 'no-cache' }
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const cred = data.data;
                cred.status = normalizeStatus(cred.status);

                document.getElementById('modalTitle').innerText     = 'Edit NSE Credentials';
                document.getElementById('credentialId').value       = cred.id;
                document.getElementById('name').value               = cred.name;
                document.getElementById('tm_code').value            = cred.tm_code;
                document.getElementById('secret_key').value         = '';
                document.getElementById('secret_key').required      = false;
                document.getElementById('secretKeyLabel').innerHTML = 'Secret Key <small class="text-muted">(Leave blank to keep unchanged)</small>';
                document.getElementById('secretKeyHelp').innerHTML  = 'API Secret Key <span class="text-muted">(Leave blank to keep current secret key)</span>';
                document.getElementById('member_key').value         = cred.member_key    || '';
                document.getElementById('login_user_id').value      = cred.login_user_id || '';
                document.getElementById('arn_number').value         = cred.arn_number    || '';
                document.getElementById('euin_number').value        = cred.euin_number   || '';
                document.getElementById('environment').value        = cred.environment   || 'Live';
                document.getElementById('status').value             = cred.status        || 'inactive';
                document.getElementById('api_url').value            = cred.api_url       || '';
                document.getElementById('notes').value              = cred.notes         || '';

                new bootstrap.Modal(document.getElementById('credentialModal')).show();
            } else {
                showMessage(data.message || 'Failed to load credential', 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('editCredential error:', error);
            showMessage('Error loading credential', 'danger');
        });
    };

    /* ------------------------------------------------------------------ */
    /*  View                                                                */
    /* ------------------------------------------------------------------ */
    window.viewCredential = function (id) {
        showLoading();

        fetch(`nse_credentials_handler.php?action=get_by_id&id=${id}&t=${Date.now()}`, {
            method : 'GET',
            headers: { 'Cache-Control': 'no-cache' }
        })
        .then(r => r.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                const cred        = data.data;
                cred.status       = normalizeStatus(cred.status);
                const statusText  = cred.status === 'active' ? 'Active' : 'Inactive';
                const statusClass = cred.status === 'active' ? 'status-active' : 'status-inactive';

                const details = `
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">${escapeHtml(cred.name)}</h5>
                            <hr>
                            <p><strong>TM Code:</strong> ${escapeHtml(cred.tm_code)}</p>
                            <p><strong>Secret Key:</strong> ********</p>
                            <p><strong>Member Key:</strong> ${escapeHtml(cred.member_key || '-')}</p>
                            <p><strong>Login User ID:</strong> ${escapeHtml(cred.login_user_id || '-')}</p>
                            <p><strong>ARN Number:</strong> ${escapeHtml(cred.arn_number || '-')}</p>
                            <p><strong>EUIN Number:</strong> ${escapeHtml(cred.euin_number || '-')}</p>
                            <p><strong>Environment:</strong> ${escapeHtml(cred.environment || 'Live')}</p>
                            <p><strong>Activation Status:</strong> <span class="status-badge ${statusClass}">${statusText}</span></p>
                            <p><strong>API URL:</strong> ${escapeHtml(cred.api_url || '-')}</p>
                            <p><strong>Notes:</strong> ${escapeHtml(cred.notes || '-')}</p>
                            <p><strong>Created:</strong> ${cred.created_at ? new Date(cred.created_at).toLocaleString() : 'N/A'}</p>
                            <p><strong>Updated:</strong> ${cred.updated_at ? new Date(cred.updated_at).toLocaleString() : 'N/A'}</p>
                        </div>
                    </div>`;

                const modalDiv = document.createElement('div');
                modalDiv.className = 'modal fade';
                modalDiv.innerHTML = `
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">Credential Details – ${escapeHtml(cred.name)}</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">${details}</div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>`;
                document.body.appendChild(modalDiv);
                const viewModal = new bootstrap.Modal(modalDiv);
                viewModal.show();
                modalDiv.addEventListener('hidden.bs.modal', () => modalDiv.remove());
            } else {
                showMessage(data.message || 'Failed to load credential', 'danger');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('viewCredential error:', error);
            showMessage('Error loading credential', 'danger');
        });
    };

    /* ------------------------------------------------------------------ */
    /*  Delete                                                              */
    /* ------------------------------------------------------------------ */
    window.deleteCredential = function (id, name) {
        if (!confirm(`⚠️ Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) return;

        // Animate the row out before removing
        const row = document.querySelector(`tr[data-cred-id="${id}"]`);
        if (row) row.classList.add('fade-out');

        setTimeout(() => {
            showLoading();

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch('nse_credentials_handler.php', {
                method : 'POST',
                body   : formData,
                headers: { 'Cache-Control': 'no-cache' }
            })
            .then(r => r.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showMessage(data.message || 'Credential deleted successfully', 'success');
                    // *** KEY FIX: reload from server ***
                    loadCredentials(true);
                } else {
                    showMessage(data.message || 'Failed to delete credential', 'danger');
                    // Undo the fade-out if delete failed
                    if (row) row.classList.remove('fade-out');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('deleteCredential error:', error);
                showMessage('Error deleting credentials', 'danger');
                if (row) row.classList.remove('fade-out');
            });
        }, 320); // Wait for fade-out animation to finish
    };

    /* ------------------------------------------------------------------ */
    /*  Utility                                                             */
    /* ------------------------------------------------------------------ */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
</script>
