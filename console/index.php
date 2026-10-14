<?php
    if(!isset($_SESSION)){ session_start(); }
    try {
        require_once('../admin/database.php');
        $db = new MYSQLDB();
        $siteName = $db->getSiteName() ?: 'Settings Panel';
    } catch (\Throwable $e) {
        die("<div style='padding: 20px;'><strong>Fatal Initialization Error:</strong><br>" . $e->getMessage() . "</div>");
    }

    /**
     * -------------------------------------------------
     * LOGIN PROCESSING LOGIC
     * -------------------------------------------------
     */
    if (isset($_POST['admin_user']) && isset($_POST['admin_pass'])) {
        $username = $_POST['admin_user'];
        $password = $_POST['admin_pass'];

        $loginResult = $db->checkAdminUserLogin($username, $password);

        if ($loginResult && count($loginResult) > 0) {
            $_SESSION["loggedin"] = true;
            $_SESSION["user"] = $username;
            $_SESSION["superuser"] = $loginResult[0]['admin_role'] ?? 0;
            $_SESSION["userid"] = $loginResult[0]['ad_id'];
            
            header("location: index.php");
            exit;
        } else {
            $_SESSION["logincheck"] = "fail";
            header("location: login.php");
            exit;
        }
    }

    if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
        header("location: login.php");
        exit;
    }
    
    $superUser  = $_SESSION["superuser"] ?? 0;
    $isSubAdmin = ($superUser == 0); // subAdmin: not a super admin
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations | Console</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --sidebar-width: 240px;
            --sidebar-bg: #ffffff;
            --sidebar-surface: #f8f9fb;
            --sidebar-border: #e8eaef;
            --accent: #C94F1A;
            --text-primary: #1e2340;
            --page-bg: #f0f2f9;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--page-bg);
            margin: 0;
            display: flex;
        }

        #sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            height: 100vh;
            border-right: 1px solid var(--sidebar-border);
            padding: 20px 0;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--sidebar-border);
            margin-bottom: 20px;
        }

        .sidebar-header img {
            width: 150px;
        }

        #sidebar ul {
            list-style: none;
            padding: 0;
        }

        #sidebar ul li a {
            display: block;
            padding: 10px 20px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
        }

        #sidebar ul li a:hover, #sidebar ul li a.active {
            background: #f3f4f6;
            color: var(--accent);
            border-left: 3px solid var(--accent);
        }
        
        #sidebar ul li a.text-danger {
            color: red;
        }

        #content {
            flex: 1;
            padding: 30px;
            height: 100vh;
            overflow-y: auto;
        }
        
        .top-bar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>

<div id="sidebar">
    <div class="sidebar-header">
        <img src="../img/logo.png" alt="Logo">
        <div style="font-size: 12px; margin-top: 5px; color: #666;">Operations Console</div>
    </div>
    <ul>
        <li>
            <a href="#" class="ajax-link active" data-url="dashboard.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
        </li>
        <?php if (!$isSubAdmin): ?>
        <li>
            <a href="#" class="ajax-link" data-url="amc_aum.php">
                <i class="fas fa-chart-pie me-2"></i> AMC AUM
            </a>
        </li>
        <li>
            <a href="#" class="ajax-link" data-url="amc_kra_list.php">
                <i class="fas fa-list me-2"></i> AMC RTA List
            </a>
        </li>
        <li>
            <a href="#" class="ajax-link" data-url="holdings_view.php">
                <i class="fas fa-wallet me-2"></i> Client Holdings
            </a>
        </li>
        <li>
            <a href="#" class="ajax-link" data-url="sitesettings.php">
                <i class="fas fa-building me-2"></i> Global Configs
            </a>
        </li>
        <li>
            <a href="#" class="ajax-link" data-url="tool_view_error_logs.php">
                <i class="fas fa-exclamation-triangle me-2 text-danger"></i> System Error Logs
            </a>
        </li>
        <li>
            <a href="#" class="ajax-link" data-url="run_update_db.php">
                <i class="fas fa-database me-2"></i> Update DB
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="../admin/logout.php" class="text-danger">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </li>
    </ul>
</div>

<div id="content">
    <!-- Top bar removed for more vertical space -->
    
    <div id="main_content">
        <!-- Content gets loaded here -->
        <h4>Welcome to the Console</h4>
        <p>Select an option from the menu to get started.</p>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.ajax-link').on('click', function(e) {
        e.preventDefault();
        
        // Update active state
        $('.ajax-link').removeClass('active');
        $(this).addClass('active');
        
        const url = $(this).data('url');
        if (!url) return;
        
        $('#main_content').html('<div class="text-center mt-5"><i class="fas fa-spinner fa-spin fa-2x text-secondary"></i><p class="mt-2 text-secondary">Loading...</p></div>');
        
        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                $('#main_content').html(response);
            },
            error: function(xhr) {
                if(xhr.status === 401 || xhr.status === 403) {
                    window.location.href = 'login.php';
                } else {
                    $('#main_content').html('<div class="alert alert-danger">Error loading content. Please try again.</div>');
                }
            }
        });
    });

    // Load the active tab (Dashboard) by default
    $('.ajax-link.active').trigger('click');
});
</script>

</body>
</html>
