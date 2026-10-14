<?php
// forgot_password.php
// Handles password reset requests for superadmin users only
// Regular users are redirected to contact superadmin

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// If OTP already verified in this session, go straight to reset
if (!empty($_SESSION['otp_verified']) && !empty($_SESSION['reset_admin_id'])) {
    header('Location: resetpassword.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
        }
        .forgot-card {
            max-width: 450px; width: 100%; border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,.15); overflow: hidden; background: #fff;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; text-align: center; padding: 30px;
        }
        .card-header h3 { margin: 0; font-weight: 600; font-size: 24px; }
        .card-header p  { margin: 10px 0 0; opacity: .9; font-size: 14px; }
        .card-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-label { 
            font-weight: 500; color: #495057; margin-bottom: 8px; 
            display: block; font-size: 14px; 
        }
        .input-group { display: flex; align-items: stretch; }
        .input-group-text {
            background: #f8f9fa; border: 1px solid #ced4da; border-right: none;
            padding: 10px 12px; border-radius: 8px 0 0 8px; color: #6c757d;
        }
        .form-control {
            border-left: none; padding: 10px 12px; border-radius: 0 8px 8px 0;
            border: 1px solid #ced4da; flex: 1; font-size: 14px; transition: border-color .2s;
        }
        .form-control:focus { outline: none; border-color: #667eea; box-shadow: none; }
        .input-group:focus-within .input-group-text { border-color: #667eea; }
        .btn {
            padding: 12px; font-weight: 600; transition: all .3s;
            border: none; border-radius: 8px; cursor: pointer; font-size: 14px; width: 100%;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: #fff; 
        }
        .btn-primary:hover:not(:disabled) { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(102,126,234,.4); 
        }
        .btn-primary:disabled { opacity: .7; cursor: not-allowed; transform: none; }
        .btn-secondary {
            background: #6c757d;
            color: #fff;
            margin-top: 10px;
        }
        .btn-secondary:hover:not(:disabled) {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 10px; margin-bottom: 20px; padding: 12px 16px;
            font-size: 14px; display: flex; align-items: flex-start; gap: 8px;
        }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger  { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .info-box {
            background: #e7f3ff; border-left: 4px solid #667eea;
            padding: 12px 14px; margin-bottom: 22px; border-radius: 6px; font-size: 13px; color: #333;
        }
        .text-muted-small { color: #6c757d; font-size: 12px; margin-top: 5px; display: block; }
        .text-center { text-align: center; }
        .mt-3 { margin-top: 15px; }
        .back-link { color: #667eea; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e0e0;
        }
        .divider span {
            background: #fff;
            padding: 0 15px;
            position: relative;
            color: #999;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="forgot-card">
    <div class="card-header">
        <h3><i class="fas fa-key me-2"></i>Forgot Password</h3>
        <p>Reset your admin account password</p>
    </div>
    <div class="card-body">
        <div id="alertMessage"></div>

        <div class="info-box">
            <i class="fas fa-info-circle" style="color:#667eea;margin-right:6px"></i>
            <strong>Password Reset Instructions:</strong><br>
            • Enter your username below<br>
            • If you are a <strong>Super Admin</strong>, an OTP will be sent to your registered email<br>
            • If you are a <strong>Regular Admin</strong>, please contact your Super Admin to reset your password<br>
            • OTP is valid for 10 minutes
        </div>

        <div class="form-group">
            <label class="form-label">
                <i class="fas fa-user me-2"></i>Username
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" id="username" 
                       placeholder="Enter your admin username" autocomplete="username">
            </div>
        </div>

        <button type="button" class="btn btn-primary" onclick="sendOTP()" id="sendBtn">
            <i class="fas fa-paper-plane me-2"></i>Send OTP
        </button>

        <div class="divider">
            <span>OR</span>
        </div>

        <button type="button" class="btn btn-secondary" onclick="goToLogin()">
            <i class="fas fa-arrow-left me-2"></i>Back to Login
        </button>

        <div class="text-center mt-3">
            <small class="text-muted">
                <i class="fas fa-lock me-1"></i>
                Only Super Admins can reset their password via OTP
            </small>
        </div>
    </div>
</div>

<script>
// Allow Enter key to submit
document.getElementById('username').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendOTP();
    }
});

function goToLogin() {
    window.location.href = 'login.php';
}

function sendOTP() {
    var username = document.getElementById('username').value.trim();
    
    if (username === '') {
        showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Please enter your username', 'danger');
        document.getElementById('username').focus();
        return;
    }

    var sendBtn = document.getElementById('sendBtn');
    var orig = sendBtn.innerHTML;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
    sendBtn.disabled = true;

    var formData = new FormData();
    formData.append('method', 'CheckUserAndSendOTP');
    formData.append('username', username);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'sendmail.php', true);

    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        
        sendBtn.innerHTML = orig;
        sendBtn.disabled = false;

        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    showAlert('<i class="fas fa-check-circle me-2"></i>' + res.message, 'success');
                    setTimeout(function () {
                        window.location.href = 'otp_verification.php';
                    }, 2000);
                } else {
                    if (res.is_superadmin === false) {
                        showAlert(
                            '<i class="fas fa-exclamation-triangle me-2"></i>' + res.message + 
                            '<br><small>Please contact your Super Admin to reset your password.</small>', 
                            'warning'
                        );
                        // Clear username field for regular users
                        document.getElementById('username').value = '';
                    } else {
                        showAlert('<i class="fas fa-exclamation-triangle me-2"></i>' + res.message, 'danger');
                    }
                }
            } catch (e) {
                console.error('Parse error:', e);
                showAlert('Unexpected server response. Please try again.', 'danger');
            }
        } else {
            showAlert('Network error. Please check your connection and try again.', 'danger');
        }
    };

    xhr.onerror = function() {
        sendBtn.innerHTML = orig;
        sendBtn.disabled = false;
        showAlert('Network error. Please check your connection.', 'danger');
    };

    xhr.send(formData);
}

function showAlert(msg, type) {
    var alertDiv = document.getElementById('alertMessage');
    var alertClass = '';
    
    switch(type) {
        case 'success':
            alertClass = 'alert-success';
            break;
        case 'danger':
            alertClass = 'alert-danger';
            break;
        case 'warning':
            alertClass = 'alert-warning';
            break;
        case 'info':
            alertClass = 'alert-info';
            break;
        default:
            alertClass = 'alert-info';
    }
    
    alertDiv.innerHTML = '<div class="alert ' + alertClass + '">' + msg + '</div>';
    
    // Auto-hide after 7 seconds for non-success messages
    if (type !== 'success') {
        setTimeout(function () { 
            if (alertDiv.innerHTML !== '') {
                alertDiv.innerHTML = ''; 
            }
        }, 8000);
    }
}

// Focus on username field when page loads
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('username').focus();
});
</script>
</body>
</html>