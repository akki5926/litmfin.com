<?php
require_once('auth_guard.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if(!isset($_SESSION)){session_start();}
require_once('database.php');
$db = new MySQLDB();

// ─────────────────────────────────────────────────────────────
// AJAX handler — only fires on real POST requests with XHR header.
// ─────────────────────────────────────────────────────────────
if(
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    // Support both form-encoded ($_POST) and raw JSON bodies
    $postData = $_POST;
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonInput = json_decode($rawInput, true);
        if (is_array($jsonInput)) {
            $postData = array_merge($postData, $jsonInput);
        }
    }

    $action = isset($postData['action']) ? trim($postData['action']) : '';

    // ── Add Sub Admin ─────────────────────────────────────────
    if($action === 'add_sub_admin') {
        $username = trim($postData['username'] ?? '');
        $password = trim($postData['password'] ?? '');

        if(!empty($username) && !empty($password)) {
            $result = $db->addSubAdminToDb($username, $password);
            if($result !== false) {
                $response['success'] = true;
                $response['message'] = "Sub-admin '$username' added successfully!";
                $response['data']    = ['id' => $result, 'username' => $username];
            } else {
                $response['message'] = "Username '$username' already exists!";
            }
        } else {
            $response['message'] = "Please enter both username and password!";
        }

    // ── Update Username ───────────────────────────────────────
    } elseif($action === 'update_username') {
        $id          = intval($postData['id'] ?? 0);
        $newUsername = trim($postData['username'] ?? '');

        if(!empty($newUsername)) {
            $result = $db->updateSubAdmin($id, $newUsername);
            if($result !== false) {
                $response['success'] = true;
                $response['message'] = "Username updated successfully!";
                $response['data']    = ['id' => $id, 'username' => $newUsername];
            } else {
                $response['message'] = "Username already exists or update failed!";
            }
        } else {
            $response['message'] = "Please enter a username!";
        }

    // ── Reset Password ────────────────────────────────────────
    } elseif($action === 'reset_password') {
        $id          = intval($postData['id'] ?? 0);
        $newPassword = trim($postData['new_password'] ?? '');

        if(!empty($newPassword)) {
            $result = $db->updateSubAdminPass($id, $newPassword);
            if($result !== false) {
                $response['success'] = true;
                $response['message'] = "Password reset successfully!";
            } else {
                $response['message'] = "Failed to reset password!";
            }
        } else {
            $response['message'] = "Please enter a new password!";
        }

    // ── Delete Sub Admin ──────────────────────────────────────
    } elseif($action === 'delete_sub_admin') {
        $id       = intval($postData['id'] ?? 0);
        $username = trim($postData['username'] ?? '');

        $result = $db->deleteSubAdmin($id);
        if($result !== false) {
            $response['success'] = true;
            $response['message'] = "Sub-admin '$username' deleted successfully!";
            $response['data']    = ['id' => $id];
        } else {
            $response['message'] = "Failed to delete sub-admin!";
        }

    // ── Unknown action ────────────────────────────────────────
    } else {
        error_log("subAdmin.php — unknown action=[{$action}]");
        $response['message'] = "Invalid action: '{$action}'";
    }

    echo json_encode($response);
    exit;
}

// Build canonical self-URL once — JS uses this instead of
// window.location.href to avoid redirect-induced body loss.
$selfUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . $_SERVER['PHP_SELF'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sub Admin Management</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                         'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            font-size: 18px;
        }
        .card-body { padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: inline-block; width: 100px; font-weight: 500; }
        .form-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 250px;
            font-size: 14px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary  { background: #4CAF50; color: white; }
        .btn-primary:hover  { background: #45a049; }
        .btn-edit     { background: #2196F3; color: white; }
        .btn-edit:hover     { background: #0b7dda; }
        .btn-reset    { background: #ff9800; color: white; }
        .btn-reset:hover    { background: #e68a00; }
        .btn-delete   { background: #f44336; color: white; }
        .btn-delete:hover   { background: #da190b; }
        .btn-cancel   { background: #6c757d; color: white; }
        .btn-cancel:hover   { background: #5a6268; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
        .modal {
            display: none; position: fixed; z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white; margin: 10% auto;
            width: 450px; border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .modal-header {
            padding: 15px 20px; border-bottom: 1px solid #e9ecef;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h3 { margin: 0; }
        .modal-header .close { font-size: 24px; cursor: pointer; color: #999; }
        .modal-header .close:hover { color: #333; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: right; }
        .alert {
            position: fixed; top: 20px; right: 20px;
            padding: 12px 20px; border-radius: 4px; color: white;
            z-index: 2000; min-width: 300px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .alert-success { background: #4CAF50; }
        .alert-error   { background: #f44336; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .loading {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8); color: white;
            padding: 20px; border-radius: 8px;
            z-index: 2001; display: none;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 style="margin-bottom:20px;">Sub Admin Management</h1>

    <!-- Add Sub Admin Form -->
    <div class="card">
        <div class="card-header">Add New Sub Admin</div>
        <div class="card-body">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" placeholder="Enter username" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" placeholder="Enter password">
            </div>
            <div style="margin-left:100px;">
                <button class="btn btn-primary" id="addBtn">Add Sub Admin</button>
            </div>
        </div>
    </div>

    <!-- Sub Admins List -->
    <div class="card">
        <div class="card-header">Sub Admin List</div>
        <div class="card-body">
            <div id="subAdminList">
                <?php
                $subAdmins = $db->getSubAdmins();
                if($subAdmins && count($subAdmins) > 0):
                ?>
                <table id="adminTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th style="width:100px;">Edit</th>
                            <th style="width:130px;">Reset Password</th>
                            <th style="width:80px;">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($subAdmins as $admin):
                        $id    = $admin['ad_id'];
                        $uname = htmlspecialchars($admin['ad_user_name']);
                    ?>
                        <tr id="row_<?= $id ?>">
                            <td><?= $id ?></td>
                            <td id="username_<?= $id ?>"><?= $uname ?></td>
                            <td><button class="btn btn-edit"
                                onclick="openEditModal(<?= $id ?>, '<?= addslashes($uname) ?>')">Edit</button></td>
                            <td><button class="btn btn-reset"
                                onclick="openResetModal(<?= $id ?>, '<?= addslashes($uname) ?>')">Reset</button></td>
                            <td><button class="btn btn-delete"
                                onclick="openDeleteModal(<?= $id ?>, '<?= addslashes($uname) ?>')">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">No sub-admins found. Add your first sub-admin using the form above.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading" class="loading">Processing...</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Sub Admin</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit_id">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" id="edit_username" style="width:280px;">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel" onclick="closeEditModal()">Cancel</button>
            <button class="btn btn-edit" id="updateBtn">Update</button>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reset Password</h3>
            <span class="close" onclick="closeResetModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="reset_id">
            <div class="form-group">
                <label>Username:</label>
                <strong id="reset_username_display"></strong>
            </div>
            <div class="form-group">
                <label>New Password:</label>
                <input type="password" id="reset_password" style="width:280px;">
            </div>
            <div class="form-group">
                <label>Confirm:</label>
                <input type="password" id="confirm_password" style="width:280px;">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel" onclick="closeResetModal()">Cancel</button>
            <button class="btn btn-reset" id="resetBtn">Reset Password</button>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Sub Admin</h3>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="delete_id">
            <input type="hidden" id="delete_username">
            <p>Are you sure you want to delete: <strong id="delete_username_display"></strong>?</p>
            <p style="color:#f44336;font-size:12px;margin-top:10px;">
                ⚠️ This action cannot be undone.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn btn-delete" id="deleteBtn">Delete</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    // PHP-generated canonical URL
    var AJAX_URL = "<?php echo $selfUrl; ?>";

    // ── Toast notifications ───────────────────────────────────
    function showMessage(message, type) {
        var el = $('<div>').addClass('alert alert-' + type).text(message);
        $('body').append(el);
        setTimeout(function() { el.fadeOut(function() { $(this).remove(); }); }, 3000);
    }

    // ── Central AJAX POST helper ──────────────────────────────
    function ajaxPost(data, onSuccess) {
        $('#loading').show();
        $.ajax({
            url:         AJAX_URL,
            type:        'POST',
            data:        data,
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            dataType:    'json',
            headers:     { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                $('#loading').hide();
                showMessage(response.message, response.success ? 'success' : 'error');
                if(response.success && typeof onSuccess === 'function') {
                    onSuccess(response);
                }
            },
            error: function(xhr, status, error) {
                $('#loading').hide();
                showMessage('Request failed: ' + error, 'error');
                console.error('AJAX error:', status, error, xhr.responseText);
            }
        });
    }

    // ── Add Sub Admin ─────────────────────────────────────────
    $('#addBtn').click(function() {
        var username = $('#username').val().trim();
        var password = $('#password').val().trim();
        if(!username || !password) {
            showMessage('Please enter both username and password!', 'error');
            return;
        }
        ajaxPost(
            { action: 'add_sub_admin', username: username, password: password },
            function(response) {
                // Clear the form
                $('#username').val('');
                $('#password').val('');

                var id    = response.data.id;
                var uname = escapeHtml(response.data.username);

                var newRow =
                    '<tr id="row_' + id + '">' +
                    '<td>' + id + '</td>' +
                    '<td id="username_' + id + '">' + uname + '</td>' +
                    '<td><button class="btn btn-edit" onclick="openEditModal(' + id + ',\'' + escapeForJS(response.data.username) + '\')">Edit</button></td>' +
                    '<td><button class="btn btn-reset" onclick="openResetModal(' + id + ',\'' + escapeForJS(response.data.username) + '\')">Reset</button></td>' +
                    '<td><button class="btn btn-delete" onclick="openDeleteModal(' + id + ',\'' + escapeForJS(response.data.username) + '\')">Delete</button></td>' +
                    '</tr>';

                if ($('#adminTable').length) {
                    $('#adminTable tbody').append(newRow);
                } else {
                    var table =
                        '<table id="adminTable">' +
                        '<thead><tr>' +
                        '<th>ID</th><th>Username</th>' +
                        '<th style="width:100px;">Edit</th>' +
                        '<th style="width:130px;">Reset Password</th>' +
                        '<th style="width:80px;">Delete</th>' +
                        '</tr></thead>' +
                        '<tbody>' + newRow + '</tbody>' +
                        '</table>';
                    $('#subAdminList').html(table);
                }
                
                // Remove empty state if it exists
                $('.empty-state').remove();
            }
        );
    });

    // ── Update Username ───────────────────────────────────────
    $('#updateBtn').click(function() {
        var id       = $('#edit_id').val();
        var username = $('#edit_username').val().trim();
        if(!username) {
            showMessage('Please enter a username!', 'error');
            return;
        }
        ajaxPost(
            { action: 'update_username', id: id, username: username },
            function() {
                closeEditModal();
                $('#username_' + id).text(escapeHtml(username));
                // Update the onclick attributes
                var row = $('#row_' + id);
                row.find('.btn-edit').attr('onclick', "openEditModal(" + id + ",'" + escapeForJS(username) + "')");
                row.find('.btn-reset').attr('onclick', "openResetModal(" + id + ",'" + escapeForJS(username) + "')");
                row.find('.btn-delete').attr('onclick', "openDeleteModal(" + id + ",'" + escapeForJS(username) + "')");
            }
        );
    });

    // ── Reset Password ────────────────────────────────────────
    $('#resetBtn').click(function() {
        var id       = $('#reset_id').val();
        var password = $('#reset_password').val();
        var confirm  = $('#confirm_password').val();
        if(!password) {
            showMessage('Please enter a new password!', 'error');
            return;
        }
        if(password !== confirm) {
            showMessage('Passwords do not match!', 'error');
            return;
        }
        if(password.length < 4) {
            showMessage('Password must be at least 4 characters!', 'error');
            return;
        }
        ajaxPost(
            { action: 'reset_password', id: id, new_password: password },
            function() {
                closeResetModal();
                $('#reset_password').val('');
                $('#confirm_password').val('');
            }
        );
    });

    // ── Delete Sub Admin ──────────────────────────────────────
    $('#deleteBtn').click(function() {
        var id       = $('#delete_id').val();
        var username = $('#delete_username').val();
        ajaxPost(
            { action: 'delete_sub_admin', id: id, username: username },
            function() {
                closeDeleteModal();
                $('#row_' + id).remove();
                if($('#adminTable tbody tr').length === 0) {
                    $('#subAdminList').html(
                        '<div class="empty-state">No sub-admins found. ' +
                        'Add your first sub-admin using the form above.</div>'
                    );
                }
            }
        );
    });

    // ── Enter-key shortcut ────────────────────────────────────
    $('#username, #password').keypress(function(e) {
        if(e.which === 13) { $('#addBtn').click(); }
    });

    // ── Close modal on backdrop click ─────────────────────────
    $(window).click(function(e) {
        if($(e.target).hasClass('modal')) { $('.modal').hide(); }
    });
    
    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function escapeForJS(text) {
        if (!text) return '';
        return text.replace(/'/g, "\\'").replace(/"/g, '\\"');
    }
});

// ── Modal helpers (called by inline onclick) ──────────────────
function openEditModal(id, username) {
    $('#edit_id').val(id);
    $('#edit_username').val(username);
    $('#editModal').show();
}
function closeEditModal()  { $('#editModal').hide(); }

function openResetModal(id, username) {
    $('#reset_id').val(id);
    $('#reset_username_display').text(username);
    $('#reset_password').val('');
    $('#confirm_password').val('');
    $('#resetModal').show();
}
function closeResetModal() { $('#resetModal').hide(); }

function openDeleteModal(id, username) {
    $('#delete_id').val(id);
    $('#delete_username').val(username);
    $('#delete_username_display').text(username);
    $('#deleteModal').show();
}
function closeDeleteModal() { $('#deleteModal').hide(); }
</script>
</body>
</html>