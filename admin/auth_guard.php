<?php
// admin/auth_guard.php
// Centralized authentication check to prevent Broken Access Control

if (defined('API_CALL') && API_CALL === true) {
    return;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)) {
    // If it's an AJAX request (either via Fetch or jQuery XHR)
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
           || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
           
    if ($isAjax) {
        header("HTTP/1.1 401 Unauthorized");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login.']);
        exit;
    } else {
        header("Location: login.php");
        exit;
    }
}

// Check role permission constraints for Admin role (value 2)
$superUser = $_SESSION["superuser"] ?? 0;
if ($superUser == 2) {
    $currentPage = basename($_SERVER['SCRIPT_NAME']);
    
    // List of restricted files for Admin role (lowercase)
    $restrictedPages = [
        'uploadmailback.php',
        'uploadtxtmailback.php',
        'mailbackcsvfiletodb.php',
        'mailbackfiletodb.php',
        'mailback_upload_form.php',
        'mailback_upload_process.php',
        'tool_mailback_data_check.php',
        
        'consolidatedportfolio.php',
        'generatereport.php',
        
        'cas_portfolio_list.php',
        'cas_generate_report.php',
        'cas_portfolio_calc.php',
        
        'utility_tools.php',
        'tool_asset_class_modifier.php',
        'tool_manage_navs.php',
        'tool_view_error_logs.php',
        
        'goal.php',
        'assign-goals.php',
        'goals-management.php',
        'goal-gategory.php',
        'goal-status.php',
        'processgoal.php',
        'processgoalmngt.php',
        'processassigngoal.php',
        
        'recommendation-list.php',
        'recommendation-stocks.php',
        'recommendation-other.php',
        'recommanded-funds.php',
        'recommendation_add_new.php',
        'recomadation_add_aggressive.php',
        'recomadation_add_catc_aggressive.php',
        'recomadation_add_catd_aggressive.php',
        'recomadation_add_other_cat.php',
        'recomadation_add_other_inv_del.php',
        'recomadation_add_very_aggressive.php',
        'editrecommedation.php',
        'rec_del.php',
        'process_rec_other.php',
        
        'dbbackup.php',
        'change_password.php',
        'admin_nse_credentials.php',
        'subadmin.php',
        'adminmanagement.php',
        'assignsubadmin.php'
    ];
    
    if (in_array(strtolower($currentPage), $restrictedPages)) {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
               
        if ($isAjax) {
            header("HTTP/1.1 403 Forbidden");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Access forbidden. You do not have permission to access this module.']);
            exit;
        } else {
            echo "<div class='container mt-4'><div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> <strong>Access Denied:</strong> You do not have permission to access this module.</div></div>";
            exit;
        }
    }
}
?>
