<?php
require_once('auth_guard.php');

if(!isset($_SESSION)) { session_start(); } 
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit; }

require_once('database.php');
$db = new MySQLDB();

$message = '';
$status = '';
$errorDetails = [];
$existDetails = [];

if (isset($_POST['action']) && $_POST['action'] == 'migrate') {
    $result = $db->migrateOldUsers();
    if ($result) {
        $message = "Migration completed. Total users: {$result['total']}, Migrated successfully: {$result['success']}, Already exist: {$result['exists']}, Errors: {$result['errors']}.";
        $status = 'success';
        if (!empty($result['error_details'])) {
            $errorDetails = $result['error_details'];
        }
        if (!empty($result['exist_details'])) {
            $existDetails = $result['exist_details'];
        }
    } else {
        $message = "Failed to fetch old users or no users to migrate.";
        $status = 'danger';
    }
}
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10 col-sm-12">
            <center>
                <h3 style="margin-top:100px">Migrate Users Data</h3>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $status; ?> mt-3">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($errorDetails)): ?>
                <div class="alert alert-danger mt-3 text-left">
                    <h5>Error Details:</h5>
                    <ul style="max-height: 200px; overflow-y: auto;">
                        <?php foreach($errorDetails as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($existDetails)): ?>
                <div class="alert alert-info mt-3 text-left">
                    <h5>Skipped (Already Exist):</h5>
                    <ul style="max-height: 200px; overflow-y: auto;">
                        <?php foreach($existDetails as $info): ?>
                            <li><?php echo htmlspecialchars($info); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="card mt-4 text-left">
                    <div class="card-body" style="text-align: left;">
                        <p>This process will safely migrate users from the legacy <code>tb_sign_up</code> and <code>tb_user_profile_detail</code> tables into the new <code>users</code> table.</p>
                        <form method="POST" class="text-center mt-4">
                            <input type="hidden" name="action" value="migrate">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to run the migration?');">Run Migration</button>
                        </form>
                    </div>
                </div>
            </center>
        </div>
    </div>
</div>