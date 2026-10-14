<?php
require_once('auth_guard.php');

// change_password.php - Only Change Password functionality
if(!isset($_SESSION)) { session_start(); }

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo '<div class="alert alert-danger">Session expired. Please login again.</div>';
    exit;
}
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-key me-2 text-primary"></i>Change Password</h5>
                    <p class="text-muted small mb-0 mt-1">Update your account password</p>
                </div>
                <div class="card-body">
                    <div id="changeAlert"></div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Old Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="oldPassword" 
                                   placeholder="Enter current password">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" id="newPassword" 
                                   placeholder="Enter new password">
                        </div>
                        <div id="passwordStrength" class="small mt-1"></div>
                        <small class="text-muted">Password must be at least 6 characters long</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                            <input type="password" class="form-control" id="confirmPassword" 
                                   placeholder="Confirm new password">
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-primary w-100" onclick="updatePassword()" id="updateBtn">
                        <i class="fas fa-save me-2"></i>Update Password
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// API URL - pointing to your api_change_password.php
var scriptPath = window.location.pathname;
var basePath = scriptPath.substring(0, scriptPath.lastIndexOf('/') + 1);
var API_URL = basePath + 'api_change_password.php';

// Password strength checker
var newPasswordInput = document.getElementById('newPassword');
if(newPasswordInput) {
    newPasswordInput.addEventListener('input', function() {
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
        
        var indicator = document.getElementById("passwordStrength");
        if (password.length > 0) {
            indicator.innerHTML = "Password strength: <strong class=\"" + strengthClass + "\">" + strengthText + "</strong>";
        } else {
            indicator.innerHTML = "";
        }
    });
}

// Update Password
function updatePassword() {
    var oldPassword = document.getElementById('oldPassword').value.trim();
    var newPassword = document.getElementById('newPassword').value.trim();
    var confirmPassword = document.getElementById('confirmPassword').value.trim();
    var updateBtn = document.getElementById('updateBtn');
    
    // Validation
    if (oldPassword === '') {
        showAlert('changeAlert', 'Please enter old password', 'danger');
        return;
    }
    if (newPassword === '') {
        showAlert('changeAlert', 'Please enter new password', 'danger');
        return;
    }
    if (newPassword.length < 6) {
        showAlert('changeAlert', 'Password must be at least 6 characters long', 'danger');
        return;
    }
    if (confirmPassword === '') {
        showAlert('changeAlert', 'Please confirm new password', 'danger');
        return;
    }
    if (newPassword !== confirmPassword) {
        showAlert('changeAlert', 'New password and confirm password do not match', 'danger');
        return;
    }
    if (oldPassword === newPassword) {
        showAlert('changeAlert', 'New password must be different from old password', 'danger');
        return;
    }
    
    // Show loading state
    var originalText = updateBtn.innerHTML;
    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    updateBtn.disabled = true;
    
    // Send AJAX request
    var formData = new FormData();
    formData.append('action', 'update_password');
    formData.append('oldPassword', oldPassword);
    formData.append('newPassword', newPassword);
    formData.append('confirmPassword', confirmPassword);
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('changeAlert', data.message, 'success');
            // Clear form on success
            document.getElementById('oldPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            document.getElementById('passwordStrength').innerHTML = '';
        } else {
            showAlert('changeAlert', data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('changeAlert', 'Network error. Please try again.', 'danger');
    })
    .finally(() => {
        updateBtn.innerHTML = originalText;
        updateBtn.disabled = false;
    });
}

// Show alert message
function showAlert(containerId, message, type) {
    var alertDiv = document.getElementById(containerId);
    var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    var icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
    
    alertDiv.innerHTML = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                         '<i class="fas fa-' + icon + ' me-2"></i>' +
                         message +
                         '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                         '</div>';
    
    // Auto hide after 5 seconds
    setTimeout(function() {
        alertDiv.innerHTML = '';
    }, 5000);
}

// Enter key support
document.getElementById('newPassword').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('confirmPassword').focus();
    }
});

document.getElementById('confirmPassword').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        updatePassword();
    }
});
</script>