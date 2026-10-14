<?php
require_once('auth_guard.php');

header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private");
header("Pragma: no-cache");
header("Expires: 0");

require_once('database.php');
$db = new MYSQLDB();

// Handle AJAX requests for assignments
if (isset($_GET['action']) && $_GET['action'] === 'get_assignments') {
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private");
    header("Pragma: no-cache");
    header("Expires: 0");
    try {
        $users = $db->displayAllUsers();
        $subadmins = $db->getSubAdmins();
        
        usort($users, function($a, $b) {
            return $b['id'] - $a['id'];
        });
        
        $result = [];
        foreach ($users as $user) {
            $assignedId = $user["assigned_subadmin_id"];
            $assignedName = '';
            foreach ($subadmins as $sa) {
                if ($sa['ad_id'] == $assignedId) {
                    $assignedName = $sa['ad_user_name'];
                    break;
                }
            }
            
            // Get activation status
            $activationVal = isset($user['UCC_ACTIVATION']) ? $user['UCC_ACTIVATION'] : null;
            $status = ($activationVal == "0" || $activationVal === null) ? "Pending" : "Activated";
            
            $result[] = [
                'id' => $user['id'],
                'name' => $user['PRIMARY_HOLDER_NAME'],
                'mobile' => $user['INDIAN_MOBILE_NO'],
                'email' => $user['EMAIL'],
                'client_code' => $user['CLIENT_CODE'],
                'assigned_id' => $assignedId ?: '',
                'assigned_name' => $assignedName,
                'auto_email' => isset($user['AUTO_EMAIL']) ? intval($user['AUTO_EMAIL']) : 0,
                'status' => $status
            ];
        }
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Fetch all users
$users = $db->displayAllUsers();
usort($users, function($a, $b) {
    return $b['id'] - $a['id'];
});

$subadmins = $db->getSubAdmins();

$subadminOptions = [];
foreach ($subadmins as $sa) {
    $subadminOptions[] = ['id' => $sa['ad_id'], 'name' => $sa['ad_user_name']];
}
$subadminJson = json_encode($subadminOptions);

$usersData = [];
foreach ($users as $user) {
    $assignedId = $user["assigned_subadmin_id"];
    $assignedName = '';
    foreach ($subadmins as $sa) {
        if ($sa['ad_id'] == $assignedId) {
            $assignedName = $sa['ad_user_name'];
            break;
        }
    }
    
    // Get activation status
    $activationVal = isset($user['UCC_ACTIVATION']) ? $user['UCC_ACTIVATION'] : null;
    $status = ($activationVal == "0" || $activationVal === null) ? "Pending" : "Activated";
    
    $usersData[] = [
        'id' => $user['id'],
        'mobile' => $user['INDIAN_MOBILE_NO'],
        'email' => $user['EMAIL'],
        'client_code' => $user['CLIENT_CODE'],
        'assigned_id' => $assignedId ?: '',
        'assigned_name' => $assignedName,
        'auto_email' => isset($user['AUTO_EMAIL']) ? intval($user['AUTO_EMAIL']) : 0,
        'status' => $status
    ];
}
$usersJson = json_encode($usersData);
// Add timestamp to force refresh
$cacheBuster = time();
?>

<style>
/* Page Layout */
.page-wrapper {
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    background: #f8f9fc;
    min-height: 100vh;
}
.page-header {
    margin-bottom: 25px;
}
.page-header h1 {
    font-size: 24px;
    color: #2c3e50;
    margin-bottom: 5px;
}
.page-header p {
    color: #6c757d;
    margin: 0;
}

/* Controls Section */
.controls-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.search-box input {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    width: 300px;
    font-size: 14px;
    transition: border-color 0.3s;
}
.search-box input:focus {
    outline: none;
    border-color: #4a90e2;
    box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
}
.btn-refresh {
    padding: 10px 20px;
    background: #4a90e2;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s;
}
.btn-refresh:hover {
    background: #357abd;
}
.btn-refresh:disabled {
    background: #a0c4e8;
    cursor: not-allowed;
}

/* Table Styles */
.table-wrapper {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 20px;
}
.table-responsive {
    overflow-x: auto;
}
#usersTable {
    width: 100%;
    border-collapse: collapse;
}
#usersTable th {
    background: #f1f4f8;
    color: #2c3e50;
    font-weight: 600;
    padding: 15px;
    text-align: left;
    border-bottom: 2px solid #e1e5eb;
    white-space: nowrap;
}
#usersTable td {
    padding: 15px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}
#usersTable tbody tr:hover {
    background: #f8fafc;
}

/* Status Badges */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-activated {
    background: #e3fcef;
    color: #0d8246;
}
.status-pending {
    background: #fff4e5;
    color: #b26a00;
}

/* Action Cell */
.action-cell {
    display: flex;
    align-items: center;
    gap: 10px;
    white-space: nowrap;
}
.subadmin-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    min-width: 180px;
}
.btn-assign {
    padding: 8px 15px;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s;
}
.btn-assign:hover {
    background: #218838;
}
.assigned-display {
    font-size: 13px;
    font-weight: 500;
}
.assigned {
    color: #28a745;
}
.unassigned {
    color: #dc3545;
}

/* Pagination */
.pagination-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.pagination-info {
    color: #6c757d;
    font-size: 14px;
}
.pagination-controls {
    display: flex;
    gap: 5px;
}
.page-btn {
    padding: 8px 12px;
    border: 1px solid #ddd;
    background: #fff;
    color: #4a90e2;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}
.page-btn:hover:not(:disabled) {
    background: #f1f4f8;
}
.page-btn.active {
    background: #4a90e2;
    color: #fff;
    border-color: #4a90e2;
}
.page-btn:disabled {
    color: #ccc;
    cursor: not-allowed;
}
.page-size-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

/* Modal Styles */
.ucc-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.ucc-modal-content {
    background: #fff;
    width: 90%;
    max-width: 800px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    overflow: hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}
.ucc-modal-header {
    background: #2c3e50;
    color: #fff;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.ucc-modal-header h3 {
    margin: 0;
    font-size: 20px;
}
.ucc-modal-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 28px;
    cursor: pointer;
    opacity: 0.8;
}
.ucc-modal-close:hover {
    opacity: 1;
}
.ucc-modal-body {
    padding: 20px;
    overflow-y: auto;
}
.ucc-link {
    color: #4a90e2;
    text-decoration: underline;
    font-weight: 600;
}
.ucc-link:hover {
    color: #357abd;
}

/* Loading Overlay */
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.8);
    z-index: 999;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.loading-spinner {
    font-size: 20px;
    color: #4a90e2;
}

/* Toast Notification */
.toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 8px;
    color: #fff;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 2000;
    animation: slideUp 0.3s ease-out;
}
.toast.success { background: #28a745; }
.toast.error { background: #dc3545; }

@keyframes slideUp {
    from { transform: translateY(100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.last-refresh {
    text-align: right;
    margin-top: 15px;
    color: #6c757d;
    font-size: 13px;
}
</style>
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading data...</div>
</div>

<div class="ucc-modal" id="uccModal">
    <div class="ucc-modal-content">
        <div class="ucc-modal-header">
            <h3><i class="fas fa-user-circle"></i> UCC Details</h3>
            <button class="ucc-modal-close" onclick="closeUccModal()">&times;</button>
        </div>
        <div class="ucc-modal-body" id="uccModalBody">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top: 10px;">Loading UCC details...</p>
            </div>
        </div>
    </div>
</div>
<div class="page-wrapper">
    <div class="page-header">
        <h1>👥 Sub-Admin Management - Auto Mail Configuration</h1>
        <p>Efficiently manage and assign sub-admins to users for better administration</p>
    </div>
    <div class="controls-section">
        <div class="search-box">
            <input type="text" id="userSearch" placeholder="🔍 Search by ID, email, or mobile...">
        </div>
        <button class="btn-refresh" onclick="manualRefresh()">🔄 Refresh Now</button>
    </div>
    
    <div class="table-wrapper">
        <div class="table-responsive">
            <table id="usersTable">
                <thead>
                    <tr>
                        <!-- <th>User ID</th> -->
                        <th>UCC</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Auto Email</th>

                        <th>Status</th>
                        <th>Sub-Admin Assignment</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="7" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="pagination-bottom" id="paginationBottom">
        <div class="pagination-info" id="paginationInfo"></div>
        <div class="pagination-controls" id="paginationControls"></div>
        <div>
            <select class="page-size-select" id="pageSizeSelect">
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>
    </div>
    
    <div class="last-refresh">Last updated: <span id="lastRefreshTime">just now</span></div>
</div>

<script>
var cacheBuster = <?php echo $cacheBuster; ?>;

var allUsers = <?php echo $usersJson; ?>;
var subadminsList = <?php echo $subadminJson; ?>;
var filteredUsers = [];
var asaCurrentPage = 1;
var pageSize = 10;
var currentSearchTerm = '';
var isInitialLoad = true;

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Function to initialize collapsible sections in the modal
function initModalCollapsibles() {
    $('.ucc-modal-body .section-toggle').off('click').on('click', function() {
        var targetId = $(this).data('ucc-target');
        var $target = $('#' + targetId);
        var isOpen = $target.is(':visible');
        $target.slideToggle(200);
        $(this).toggleClass('collapsed', !isOpen);
    });
}

function showUccDetails(clientCode, userName) {
    var modal = document.getElementById('uccModal');
    var modalBody = document.getElementById('uccModalBody');
    modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin-top: 10px;">Loading UCC details for ' + escapeHtml(clientCode) + '...</p></div>';
    modal.style.display = 'flex';
    
    $.ajax({
        url: 'getuccdetail.php',
        type: 'GET',
        data: { 
            UCC: clientCode, 
            ref: 'assignsubadmin.php',
            _: Date.now()
        },
        dataType: 'html',
        success: function(response) {
            modalBody.innerHTML = response;
            // Initialize collapsible sections after content is loaded
            initModalCollapsibles();
            
            // Fix any back button functionality
            $('.ucc-modal-body .btn-back-ucc').off('click').on('click', function(e) {
                e.preventDefault();
                closeUccModal();
            });
        },
        error: function(xhr, status, error) {
            modalBody.innerHTML = '<div class="alert alert-danger m-3">Error loading UCC details. Please try again.</div>';
        }
    });
}

function closeUccModal() {
    document.getElementById('uccModal').style.display = 'none';
    document.getElementById('uccModalBody').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin-top: 10px;">Loading UCC details...</p></div>';
}

document.addEventListener('click', function(e) {
    var modal = document.getElementById('uccModal');
    if (e.target === modal) {
        closeUccModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUccModal();
    }
});

function getPaginatedData() {
    var start = (asaCurrentPage - 1) * pageSize;
    var end = start + pageSize;
    return filteredUsers.slice(start, end);
}

function renderPagination() {
    var totalPages = Math.ceil(filteredUsers.length / pageSize);
    if (totalPages === 0) totalPages = 1;
    
    if (asaCurrentPage > totalPages) asaCurrentPage = totalPages;
    if (asaCurrentPage < 1) asaCurrentPage = 1;
    
    var startItem = (asaCurrentPage - 1) * pageSize + 1;
    var endItem = Math.min(asaCurrentPage * pageSize, filteredUsers.length);
    var infoText = filteredUsers.length > 0 
        ? `Showing ${startItem} to ${endItem} of ${filteredUsers.length} users`
        : 'No users found';
    document.getElementById('paginationInfo').innerText = infoText;
    
    var controls = document.getElementById('paginationControls');
    controls.innerHTML = '';
    
    var prevBtn = document.createElement('button');
    prevBtn.innerText = '← Previous';
    prevBtn.className = 'page-btn';
    prevBtn.disabled = asaCurrentPage === 1;
    prevBtn.onclick = function() {
        if (asaCurrentPage > 1) {
            asaCurrentPage--;
            renderTable();
            scrollToTop();
        }
    };
    controls.appendChild(prevBtn);
    
    var maxButtons = 5;
    var startPage = Math.max(1, asaCurrentPage - Math.floor(maxButtons / 2));
    var endPage = Math.min(totalPages, startPage + maxButtons - 1);
    if (endPage - startPage + 1 < maxButtons) {
        startPage = Math.max(1, endPage - maxButtons + 1);
    }
    
    if (startPage > 1) {
        var firstBtn = document.createElement('button');
        firstBtn.innerText = '1';
        firstBtn.className = 'page-btn';
        firstBtn.onclick = function() { goToPage(1); };
        controls.appendChild(firstBtn);
        if (startPage > 2) {
            var dots = document.createElement('span');
            dots.innerText = '...';
            dots.style.padding = '0 5px';
            controls.appendChild(dots);
        }
    }
    
    for (var i = startPage; i <= endPage; i++) {
        var pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = 'page-btn';
        if (i === asaCurrentPage) pageBtn.classList.add('active');
        pageBtn.onclick = (function(page) { 
            return function() { goToPage(page); }; 
        })(i);
        controls.appendChild(pageBtn);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            var dots2 = document.createElement('span');
            dots2.innerText = '...';
            dots2.style.padding = '0 5px';
            controls.appendChild(dots2);
        }
        var lastBtn = document.createElement('button');
        lastBtn.innerText = totalPages;
        lastBtn.className = 'page-btn';
        lastBtn.onclick = function() { goToPage(totalPages); };
        controls.appendChild(lastBtn);
    }
    
    var nextBtn = document.createElement('button');
    nextBtn.innerText = 'Next →';
    nextBtn.className = 'page-btn';
    nextBtn.disabled = asaCurrentPage === totalPages;
    nextBtn.onclick = function() {
        if (asaCurrentPage < totalPages) {
            asaCurrentPage++;
            renderTable();
            scrollToTop();
        }
    };
    controls.appendChild(nextBtn);
}

function goToPage(page) {
    asaCurrentPage = page;
    renderTable();
    scrollToTop();
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderTable() {
    var tbody = document.getElementById("tableBody");
    tbody.innerHTML = "";
    
    var paginatedUsers = getPaginatedData();
    
    if (paginatedUsers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px;">No users found</td></tr>';
        renderPagination();
        return;
    }
    
    for (var i = 0; i < paginatedUsers.length; i++) {
        var user = paginatedUsers[i];
        var row = tbody.insertRow();
        
        var isAutoEnabled = (user.auto_email === 1);
        var assignedDisplayClass = user.assigned_name ? "assigned" : "unassigned";
        var assignedDisplayText = user.assigned_name ? user.assigned_name : "Not assigned";
        
        var subadminOptions = '<option value="">-- No Sub Admin --</option>';
        for (var j = 0; j < subadminsList.length; j++) {
            var selected = (subadminsList[j].id == user.assigned_id) ? 'selected' : '';
            subadminOptions += '<option value="' + subadminsList[j].id + '" ' + selected + '>' + escapeHtml(subadminsList[j].name) + '</option>';
        }
        
        var statusClass = (user.status === 'Activated') ? 'status-activated' : 'status-pending';
        var statusHtml = '<span class="status-badge ' + statusClass + '">' + (user.status || 'Pending') + '</span>';
        
        var uccHtml = '';
        if (user.client_code && user.client_code.trim() !== '') {
            uccHtml = '<a href="javascript:void(0);" class="ucc-link" onclick="showUccDetails(\'' + escapeHtml(user.client_code).replace(/'/g, "\\'") + '\', \'' + escapeHtml(user.client_code).replace(/'/g, "\\'") + '\')">' + escapeHtml(user.client_code) + '</a>';
        } else {
            uccHtml = '-';
        }
        
        row.innerHTML = 
            
            '<td>' + uccHtml + '</td>' +
            '<td>' + escapeHtml(user.name) + '</td>' +
            '<td style="max-width: 200px; word-break: break-all;">' + escapeHtml(user.email) + '</td>' +
            '<td class="auto-email-cell" id="auto_email_cell_' + user.id + '"></td>' +
             '<td>' + statusHtml + '</td>' +
            '<td>' +
                '<div class="action-cell">' +
                    '<select class="subadmin-select" id="select_' + user.id + '">' + subadminOptions + '</select>' +
                    '<button class="btn-assign" onclick="assignSubAdmin(' + user.id + ')">✓ Assign</button>' +
                    '<span class="assigned-display ' + assignedDisplayClass + '" id="assigned_display_' + user.id + '">' + escapeHtml(assignedDisplayText) + '</span>' +
                '</div>' +
            '</td>';
        
        var cell = document.getElementById('auto_email_cell_' + user.id);
        if (cell) {
            cell.innerHTML = '';
            
            var label = document.createElement('label');
            label.style.cssText = 'display: inline-flex; align-items: center; gap: 5px; cursor: pointer;';
            
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'auto-email-checkbox';
            checkbox.id = 'chk_' + user.id;
            checkbox.checked = isAutoEnabled;
            checkbox.setAttribute('data-user-id', user.id);
            
            checkbox.onclick = function() {
                var uid = parseInt(this.getAttribute('data-user-id'));
                var newValue = this.checked ? 1 : 0;
                var chk = this;
                chk.disabled = true;
                
                $.ajax({
                    url: 'updateautoemailajax.php',
                    type: 'POST',
                    data: { action: 'update_auto_email', user_id: uid, auto_email: newValue },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            for (var x = 0; x < allUsers.length; x++) {
                                if (allUsers[x].id == uid) allUsers[x].auto_email = newValue;
                            }
                            for (var x = 0; x < filteredUsers.length; x++) {
                                if (filteredUsers[x].id == uid) filteredUsers[x].auto_email = newValue;
                            }
                            
                            if (newValue === 1) {
                                showToast('Auto email ENABLED for user #' + uid, 'success');
                            } else {
                                showToast('Auto email DISABLED for user #' + uid, 'success');
                            }
                        } else {
                            chk.checked = !chk.checked;
                            showToast(resp.error || 'Failed to update', 'error');
                        }
                        chk.disabled = false;
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        chk.checked = !chk.checked;
                        showToast('Network error. Please try again.', 'error');
                        chk.disabled = false;
                    }
                });
                return true;
            };
            
            var spanText = document.createElement('span');
            spanText.className = 'auto-email-label';
            spanText.textContent = 'Auto';
            
            label.appendChild(checkbox);
            label.appendChild(spanText);
            cell.appendChild(label);
        }
    }
    renderPagination();
}

function filterData() {
    currentSearchTerm = document.getElementById("userSearch").value.toLowerCase();
    filteredUsers = allUsers.filter(function(user) {
        return user.id.toString().includes(currentSearchTerm) ||
               user.email.toLowerCase().includes(currentSearchTerm) ||
               user.mobile.toLowerCase().includes(currentSearchTerm);
    });
    asaCurrentPage = 1;
    renderTable();
}

function assignSubAdmin(userId) {
    var subadminId = document.getElementById("select_" + userId).value;
    var subadminSelect = document.getElementById("select_" + userId);
    var subadminName = subadminSelect.options[subadminSelect.selectedIndex] ? subadminSelect.options[subadminSelect.selectedIndex].text : "";
    var assignedDisplay = document.getElementById("assigned_display_" + userId);
    
    $.ajax({
        url: "assign_subadmin_action.php",
        method: "POST",
        data: { user_id: userId, subadmin_id: subadminId || null },
        success: function(response) {
            showToast("Sub-admin assigned successfully", "success");
            for (var i = 0; i < allUsers.length; i++) {
                if (allUsers[i].id == userId) {
                    allUsers[i].assigned_id = subadminId;
                    allUsers[i].assigned_name = (subadminId && subadminName !== "-- No Sub Admin --") ? subadminName : "";
                    break;
                }
            }
            for (var i = 0; i < filteredUsers.length; i++) {
                if (filteredUsers[i].id == userId) {
                    filteredUsers[i].assigned_id = subadminId;
                    filteredUsers[i].assigned_name = (subadminId && subadminName !== "-- No Sub Admin --") ? subadminName : "";
                    break;
                }
            }
            if (assignedDisplay) {
                var newName = (subadminId && subadminName !== "-- No Sub Admin --") ? subadminName : "Not assigned";
                var newClass = (subadminId && subadminName !== "-- No Sub Admin --") ? "assigned-display assigned" : "assigned-display unassigned";
                assignedDisplay.className = newClass;
                assignedDisplay.textContent = newName;
            }
            updateLastRefreshTime();
        },
        error: function() {
            showToast("Error assigning sub-admin", "error");
        }
    });
}

function showToast(message, type) {
    var toast = document.createElement("div");
    toast.className = "toast " + type;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.animation = "slideUp 0.3s ease-out reverse";
        setTimeout(function() { if (toast && toast.parentNode) toast.remove(); }, 300);
    }, 3000);
}

function updateLastRefreshTime() {
    document.getElementById("lastRefreshTime").textContent = new Date().toLocaleTimeString();
}

function showLoading(show) {
    var overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = show ? 'flex' : 'none';
    }
}


function manualRefresh() {
    var btn = document.querySelector('.btn-refresh');
    if (btn) { btn.textContent = '⏳ Refreshing...'; btn.disabled = true; }
    showLoading(true);

    $.ajax({
        url: 'assignsubadmin.php?action=get_assignments&_=' + Date.now(),
        type: 'GET',
        dataType: 'json',
        cache: false,
        success: function(response) {
            if (response.success) {
                allUsers = response.data;
                var searchTerm = currentSearchTerm;
                if (searchTerm) {
                    filteredUsers = allUsers.filter(function(user) {
                        return user.id.toString().includes(searchTerm) ||
                               user.email.toLowerCase().includes(searchTerm) ||
                               user.mobile.toLowerCase().includes(searchTerm);
                    });
                } else {
                    filteredUsers = allUsers.slice();
                }
                asaCurrentPage = 1;
                renderTable();
                updateLastRefreshTime();
                showToast('Data refreshed successfully', 'success');
            } else {
                showToast('Failed to refresh data', 'error');
            }
        },
        error: function() {
            showToast('Network error. Could not refresh.', 'error');
        },
        complete: function() {
            if (btn) { btn.textContent = '🔄 Refresh Now'; btn.disabled = false; }
            showLoading(false);
        }
    });
}

function initPageSizeListener() {
    var pageSizeSelect = document.getElementById('pageSizeSelect');
    pageSizeSelect.addEventListener('change', function() {
        pageSize = parseInt(this.value);
        asaCurrentPage = 1;
        renderTable();
    });
}





document.getElementById("userSearch").addEventListener("input", filterData);
initPageSizeListener();

filteredUsers = allUsers.slice();
renderTable();
updateLastRefreshTime();

// Use namespaces for events to avoid duplicate listeners when reloaded
$(document).off('visibilitychange.assignSub').on('visibilitychange.assignSub', function() {
    if (!document.hidden) {
        manualRefresh();
    }
});

</script>
