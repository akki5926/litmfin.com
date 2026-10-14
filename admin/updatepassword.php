<?php
require_once('auth_guard.php');

if(!isset($_SESSION)) { session_start(); }
require_once('database.php');
$db = new MYSQLDB();

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$output = '';
$message = '';
$messageType = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION["userid"] ?? 0;
    $oldPassword = $_POST['oldPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validation
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "All fields are required.";
        $messageType = "danger";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New password and confirm password do not match.";
        $messageType = "danger";
    } elseif (strlen($newPassword) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "danger";
    } else {
        // Verify old password
        if ($db->verifyOldPassword($userId, $oldPassword)) {
            // Update password
            if ($db->updateAdminPassword($userId, $newPassword)) {
                $message = "Password updated successfully! You can now login with your new password.";
                $messageType = "success";
                
                // Optional: Log the user out and ask to login again with new password
                // Uncomment the following lines if you want to force re-login
                /*
                session_destroy();
                echo '<script>
                    alert("Password changed successfully! Please login again with your new password.");
                    window.location.href = "login.php";
                </script>';
                exit;
                */
            } else {
                $message = "Something went wrong. Please try again.";
                $messageType = "danger";
            }
        } else {
            $message = "Old password is incorrect.";
            $messageType = "danger";
        }
    }
}

// Get user info for display (optional)
$userInfo = $db->getAdminUserById($_SESSION["userid"]);

$output = '<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8 col-sm-12">
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white py-3">
                    <h4 class="mb-0 text-center">Change Password</h4>
                </div>
                <div class="card-body p-4">';

if ($userInfo) {
    $output .= '<div class="alert alert-info mb-4">
        <i class="fas fa-user-circle me-2"></i> Changing password for: <strong>' . htmlspecialchars($userInfo['ad_user_name']) . '</strong>
    </div>';
}

// Display message if any
if (!empty($message)) {
    $output .= '<div class="alert alert-' . $messageType . ' alert-dismissible fade show" role="alert">
        ' . $message . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

$output .= '<form method="POST" action="" id="changePasswordForm" onsubmit="return validatePasswordForm()">
    <div class="mb-3">
        <label for="oldPassword" class="form-label fw-bold">Old Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" class="form-control" name="oldPassword" id="oldPassword" 
                   placeholder="Enter old password" required autocomplete="off">
        </div>
    </div>
    
    <div class="mb-3">
        <label for="newPassword" class="form-label fw-bold">New Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-key"></i></span>
            <input type="password" class="form-control" name="newPassword" id="newPassword" 
                   placeholder="Enter new password" required minlength="6" autocomplete="off">
        </div>
        <div class="form-text">Password must be at least 6 characters long.</div>
    </div>
    
    <div class="mb-4">
        <label for="confirmPassword" class="form-label fw-bold">Confirm New Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
            <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" 
                   placeholder="Confirm new password" required autocomplete="off">
        </div>
    </div>
    
    <div class="d-grid gap-2">
        <button type="submit" name="changePassword" id="changePassword" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-2"></i>Update Password
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
            <i class="fas fa-undo me-2"></i>Clear Form
        </button>
    </div>
</form>';

$output .= '</div></div></div></div></div>';

// Add JavaScript for client-side validation
$output .= '
<script>
function validatePasswordForm() {
    var oldPassword = document.getElementById("oldPassword").value;
    var newPassword = document.getElementById("newPassword").value;
    var confirmPassword = document.getElementById("confirmPassword").value;
    
    if (oldPassword.trim() === "") {
        alert("Please enter old password");
        return false;
    }
    
    if (newPassword.trim() === "") {
        alert("Please enter new password");
        return false;
    }
    
    if (newPassword.length < 6) {
        alert("New password must be at least 6 characters long");
        return false;
    }
    
    if (confirmPassword.trim() === "") {
        alert("Please confirm new password");
        return false;
    }
    
    if (newPassword !== confirmPassword) {
        alert("New password and confirm password do not match");
        return false;
    }
    
    if (oldPassword === newPassword) {
        alert("New password must be different from old password");
        return false;
    }
    
    return true;
}

function resetForm() {
    if (confirm("Are you sure you want to clear the form?")) {
        document.getElementById("changePasswordForm").reset();
    }
}

// Password strength indicator
document.getElementById("newPassword").addEventListener("input", function() {
    var password = this.value;
    var strength = 0;
    
    if (password.length >= 6) strength += 1;
    if (password.match(/[a-z]+/)) strength += 1;
    if (password.match(/[A-Z]+/)) strength += 1;
    if (password.match(/[0-9]+/)) strength += 1;
    if (password.match(/[$@#&!]+/)) strength += 1;
    
    var strengthText = "";
    var strengthClass = "";
    
    switch(strength) {
        case 0:
        case 1:
            strengthText = "Weak";
            strengthClass = "text-danger";
            break;
        case 2:
        case 3:
            strengthText = "Medium";
            strengthClass = "text-warning";
            break;
        case 4:
        case 5:
            strengthText = "Strong";
            strengthClass = "text-success";
            break;
    }
    
    var indicator = document.getElementById("password-strength");
    if (!indicator) {
        indicator = document.createElement("div");
        indicator.id = "password-strength";
        indicator.className = "mt-1 small";
        this.parentNode.parentNode.appendChild(indicator);
    }
    
    if (password.length > 0) {
        indicator.innerHTML = "Password strength: <span class=\"" + strengthClass + " fw-bold\">" + strengthText + "</span>";
    } else {
        indicator.innerHTML = "";
    }
});
</script>';

echo $output;
?>