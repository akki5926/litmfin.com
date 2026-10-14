<?php
require_once('auth_guard.php');

require_once('database.php');
$db = new MYSQLDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_user') {
    $userId = $_POST['user_id'] ?? 0;
    if ($userId > 0) {
        $db->activateUser($userId);
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
}

$result = $db->displayAllUsers();
?>

<style>
    .badge-success { 
        background-color: #28a745; 
        color: white; 
        padding: 3px 8px; 
        border-radius: 4px; 
    }
    .badge-warning { 
        background-color: #ffc107; 
        color: black; 
        padding: 3px 8px; 
        border-radius: 4px; 
    }
    table { font-size: 13px; }
    
    .dataTables_length select {
        padding: 5px 25px 5px 10px !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        width: auto !important;
        min-width: 70px !important;
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
    }
    .ucc-link {
        cursor: pointer;
        color: #0d6efd;
        text-decoration: underline;
    }
    .ucc-link:hover { color: #0a58ca; }
    .page-header {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: white;
        padding: 20px 25px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .page-header h2 { margin: 0; font-weight: 600; }
    .table-container {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
</style>

<div style="padding: 20px;">
    <div class="page-header">
        <h2><i class="fas fa-users mr-2"></i> User Management</h2>
        <p class="mb-0 mt-2">View and manage all registered users</p>
    </div>
    
    <div class="table-container">
        <table id="userListTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Mobile No.</th>
                    <th>Email Id</th>
                    <th>UCC</th>
                    <th>Client Name</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($result && !empty($result)): ?>
                    <?php foreach($result as $row): ?>
                        <?php
                            $user_id     = $row['id'] ?? '-';
                            $client_code = $row['CLIENT_CODE'] ?? '';
                            $client_name = $row['PRIMARY_HOLDER_NAME'] ?? '-';
                            $mobile      = $row['INDIAN_MOBILE_NO'] ?? '-';
                            $email       = $row['EMAIL'] ?? '-';
                            $activationVal = $row['UCC_ACTIVATION'] ?? null;

                            if($activationVal == "0" || $activationVal === null) {
                                $status = "Pending";
                                $statusClass = "badge-warning";
                            } else {
                                $status = "Activated";
                                $statusClass = "badge-success";
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user_id); ?></td>
                            <td><?php echo htmlspecialchars($mobile); ?></td>
                            <td><?php echo htmlspecialchars($email); ?></td>
                            <td>
                                <?php if(!empty($client_code)): ?>
                                    <a href="#"
                                       class="ucc-link"
                                       data-url="getuccdetail.php?UCC=<?php echo urlencode($client_code); ?>&ref=user_list.php"
                                       data-title="<?php echo htmlspecialchars($client_code); ?>">
                                        <?php echo htmlspecialchars($client_code); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($client_name); ?></td>
                            <td><span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                            <td>
                                <?php if($activationVal == "0" || $activationVal === null): ?>
                                    <button class="btn btn-sm btn-primary activate-user-btn" data-id="<?php echo htmlspecialchars($user_id); ?>">Activate</button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding: 20px;">No users found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    // Destroy existing instance if table was previously initialized
    if ($.fn.DataTable.isDataTable('#userListTable')) {
        $('#userListTable').DataTable().destroy();
    }

    $('#userListTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[0, "desc"]],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            zeroRecords: "No matching records found",
            paginate: {
                first: "First", last: "Last", next: "Next", previous: "Previous"
            }
        },
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-6"i><"col-sm-6"p>>'
    });

    // Handle UCC link clicks — navigate within the parent's #main_content
    $(document).on('click', '.ucc-link', function(e) {
        e.preventDefault();

        const url   = $(this).data('url');
        const title = $(this).data('title') || $(this).text().trim();

        $('#page-title').text('UCC Details: ' + title);
        $('#main_content').hide();
        $('#loader-text').text('Loading UCC details for ' + title + '...');
        $('#loader').show();

        $('#main_content').load(url, function(response, status, xhr) {
            $('#loader').hide();
            if (status === 'error') {
                $('#main_content').html('<div class="alert alert-danger">Error ' + xhr.status + ': ' + xhr.statusText + '</div>');
            }
            $('#main_content').fadeIn();
        });
    });

    // ============================================================
    // FIXED: Activate User Button Handler
    // The issue was that when user clicked "Cancel" on the confirm
    // dialog, the code was still executing the activation request.
    // Now properly handles both Confirm and Cancel actions.
    // ============================================================
    $(document).on('click', '.activate-user-btn', function() {
        var btn = $(this);
        var userId = btn.data('id');
        
        // Show confirmation dialog - only proceed if user clicks "OK"
        if (confirm('Are you sure you want to activate this user?')) {
            // User clicked OK - proceed with activation
            btn.prop('disabled', true).text('Activating...');
            
            $.post('user_list.php', { 
                action: 'activate_user', 
                user_id: userId 
            }, function(response) {
                try {
                    var res = JSON.parse(response);
                    if (res.success) {
                        // Reload the user list to reflect the changes
                        $('#main_content').load('user_list.php');
                    } else {
                        alert(res.message || 'Failed to activate user');
                        btn.prop('disabled', false).text('Activate');
                    }
                } catch(e) {
                    alert('Error processing request.');
                    btn.prop('disabled', false).text('Activate');
                }
            });
        }
        // If user clicks Cancel, nothing happens - the function simply returns
        // No alert or error message should appear on Cancel
    });
});
</script>