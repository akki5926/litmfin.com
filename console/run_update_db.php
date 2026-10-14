<?php
    if(!isset($_SESSION)){ session_start(); }
    if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
        http_response_code(403);
        exit("Unauthorized access.");
    }
    
    // If the request is an AJAX post to run the update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_update') {
        // Capture the output of the script instead of printing it directly
        ob_start();
        
        try {
            require_once '../admin/database.php';
            
            $db = new MySQLDB();
            $conn = $db->getConnection();
            
            echo "Starting Database Updates...\n";
            
            // ---------------------------------------------------------
            // 1. Database and Table Collation Fixes
            // ---------------------------------------------------------
            $dbNameResult = $conn->query("SELECT DATABASE() AS db");
            $row = $dbNameResult->fetch_assoc();
            $dbName = $row['db'];
            
            $charset = 'utf8mb4';
            $collation = 'utf8mb4_unicode_ci';
            
            echo "\n[1] Changing database $dbName to $charset COLLATE $collation...\n";
            $conn->query("ALTER DATABASE `$dbName` CHARACTER SET $charset COLLATE $collation");
            
            echo "Updating all base tables to $charset COLLATE $collation...\n";
            $tablesResult = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            while ($tableRow = $tablesResult->fetch_array()) {
                $table = $tableRow[0];
                $conn->query("ALTER TABLE `$table` CONVERT TO CHARACTER SET $charset COLLATE $collation");
            }
            echo "Collation updates completed.\n";
            
            // ---------------------------------------------------------
            // 2. Schema Alterations (Add future queries here)
            // ---------------------------------------------------------
            echo "\n[2] Executing specific schema alterations...\n";
            
            $queries = [
                // Add KRA mapping column to AMCs
                "ALTER TABLE `mtb_amc` ADD COLUMN `KRA` VARCHAR(50) DEFAULT NULL",
                
                // Increase size for nominee minor flags
                "ALTER TABLE `tb_users` MODIFY COLUMN `NOMINEE_1_MINOR_FLAG` VARCHAR(10) DEFAULT NULL",
                "ALTER TABLE `tb_users` MODIFY COLUMN `NOMINEE_2_MINOR_FLAG` VARCHAR(10) DEFAULT NULL",
                "ALTER TABLE `tb_users` MODIFY COLUMN `NOMINEE_3_MINOR_FLAG` VARCHAR(10) DEFAULT NULL",
                
                // Increase size for nominee 1 fields to match nominee 2
                "ALTER TABLE `tb_users` MODIFY COLUMN `NOMINEE_1_ADDRESS1` VARCHAR(255) DEFAULT NULL",
                "ALTER TABLE `tb_users` MODIFY COLUMN `NOMINEE_1_NAME` VARCHAR(100) DEFAULT NULL",
                "ALTER TABLE `tb_users` MODIFY COLUMN `NOMINEE_1_CITY` VARCHAR(100) DEFAULT NULL",
                
                // Increase size for scheme name in tb_nav
                "ALTER TABLE `tb_nav` MODIFY COLUMN `scheme_name` VARCHAR(500) DEFAULT NULL",
            ];
            
            foreach ($queries as $sql) {
                if ($conn->query($sql)) {
                    echo "Successfully executed: $sql\n";
                } else {
                    echo "Error executing $sql: " . $conn->error . "\n";
                }
            }
            
            // ---------------------------------------------------------
            // 3. Recreate Stored Procedures with New Collation
            // ---------------------------------------------------------
            echo "\n[3] Recreating all stored procedures with $charset COLLATE $collation...\n";
            
            // We must set the connection charset before recreating so the SPs inherit it
            $conn->query("SET NAMES $charset COLLATE $collation");
            
            $stmt = $conn->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
            if ($stmt) {
                while ($proc = $stmt->fetch_assoc()) {
                    $name = $proc['Name'];
                    echo "Recreating procedure: $name...\n";
                    
                    $createStmt = $conn->query("SHOW CREATE PROCEDURE `$name`");
                    if ($createStmt) {
                        $createRes = $createStmt->fetch_assoc();
                        $sql = $createRes['Create Procedure'] ?? null;
                        
                        if (empty($sql)) {
                            echo " - Error: Could not read definition for procedure $name. Skipping.\n";
                            continue;
                        }
                        
                        // Drop the old one
                        $conn->query("DROP PROCEDURE IF EXISTS `$name`");
                        
                        // Create the new one
                        if ($conn->query($sql)) {
                            echo " - Successfully recreated $name\n";
                        } else {
                            echo " - Error recreating $name: " . $conn->error . "\n";
                        }
                    }
                }
            }
            
            echo "\nDatabase update complete!\n";
        } catch (Exception $e) {
            echo "Exception caught during execution:\n" . $e->getMessage();
        }
        
        $output = ob_get_clean();
        echo "<pre class='m-0 text-light' style='white-space: pre-wrap; word-break: break-word; font-family: monospace;'>" . htmlspecialchars($output) . "</pre>";
        exit;
    }
?>
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 text-primary"><i class="fas fa-database me-2"></i>Database Update Utility</h5>
    </div>
    <div class="card-body bg-light">
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <h6 class="fw-bold mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Important Warning</h6>
            <p class="mb-0">Running the database update script will execute structural alterations, update collations across all tables, and recreate stored procedures. Please ensure you have a recent backup before proceeding.</p>
        </div>
        
        <div class="text-center mb-4">
            <button class="btn btn-primary btn-lg" id="btnRunUpdate">
                <i class="fas fa-play-circle me-2"></i>Run Database Update
            </button>
            <a href="/sql_dump/update_procedures.sql" download class="btn btn-outline-secondary btn-lg ms-2" title="Download SQL script to manually update procedures in phpMyAdmin">
                <i class="fas fa-download me-2"></i>Download Procedures SQL
            </a>
        </div>
        
        <div id="logContainer" style="display: none;">
            <p class="text-muted fw-bold mb-2">Execution Log:</p>
            <div id="executionLog" class="bg-dark text-light p-3 rounded" style="font-family: monospace; font-size: 13px; max-height: 500px; overflow-y: auto;">
                <!-- Output will appear here -->
            </div>
        </div>
        
    </div>
</div>

<script>
    $('#btnRunUpdate').on('click', function() {
        if (confirm('Are you absolutely sure you want to run the database update script? This action cannot be undone.')) {
            
            const btn = $(this);
            const logBox = $('#executionLog');
            const logContainer = $('#logContainer');
            
            // Update UI state
            btn.prop('disabled', true);
            btn.html('<i class="fas fa-spinner fa-spin me-2"></i>Running Updates...');
            logBox.html('<div class="text-center text-secondary py-3"><i class="fas fa-circle-notch fa-spin fa-2x mb-2"></i><br>Executing queries, please wait...</div>');
            logContainer.slideDown();
            
            // Execute AJAX POST
            $.ajax({
                url: 'run_update_db.php',
                type: 'POST',
                data: { action: 'run_update' },
                success: function(response) {
                    logBox.html(response);
                    btn.html('<i class="fas fa-check-circle me-2"></i>Update Complete');
                    btn.removeClass('btn-primary').addClass('btn-success');
                    
                    // Allow running again after a few seconds if they really want to
                    setTimeout(() => {
                        btn.prop('disabled', false);
                        btn.removeClass('btn-success').addClass('btn-primary');
                        btn.html('<i class="fas fa-play-circle me-2"></i>Run Database Update Again');
                    }, 5000);
                },
                error: function() {
                    logBox.html('<div class="text-danger"><i class="fas fa-times-circle me-2"></i>A network or server error occurred while executing the script.</div>');
                    btn.prop('disabled', false);
                    btn.html('<i class="fas fa-play-circle me-2"></i>Try Again');
                }
            });
        }
    });
</script>
