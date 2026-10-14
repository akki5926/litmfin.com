<?php
// resetpassword.php
// Only reachable after OTP is verified (session flag set by sendmail.php VerifyOTP handler).

if (!isset($_SESSION)) { session_start(); }

// Guard: OTP not yet verified → back to OTP entry
if (empty($_SESSION['otp_verified']) || empty($_SESSION['reset_admin_id'])) {
    header('Location: otp_verification.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .reset-card {
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
        .success-badge {
            display: flex; align-items: center; gap: 10px;
            background: #d4edda; border: 1px solid #c3e6cb;
            color: #155724; border-radius: 8px; padding: 10px 14px;
            font-size: 13px; margin-bottom: 22px;
        }
        .form-group { margin-bottom: 20px; }
        .form-label { font-weight: 500; color: #495057; margin-bottom: 8px; display: block; font-size: 14px; }
        .input-group { display: flex; align-items: stretch; }
        .input-group-text {
            background: #f8f9fa; border: 1px solid #ced4da; border-right: none;
            padding: 10px 12px; border-radius: 8px 0 0 8px; color: #6c757d; cursor: default;
        }
        .input-group-text.toggle { cursor: pointer; border-left: none; border-right: 1px solid #ced4da; border-radius: 0 8px 8px 0; }
        .form-control {
            padding: 10px 12px; border: 1px solid #ced4da;
            flex: 1; font-size: 14px; transition: border-color .2s;
            border-radius: 0; border-left: none; border-right: none;
        }
        .form-control:focus { outline: none; border-color: #667eea; box-shadow: none; }
        .input-group:focus-within .input-group-text { border-color: #667eea; }
        .btn {
            padding: 12px; font-weight: 600; transition: all .3s;
            border: none; border-radius: 8px; cursor: pointer; font-size: 14px; width: 100%;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,.4); }
        .btn-primary:disabled { opacity: .7; cursor: not-allowed; transform: none; }
        .alert {
            border-radius: 10px; margin-bottom: 20px; padding: 12px 16px; font-size: 14px;
        }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger  { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        /* strength bar */
        .strength-bar-wrap { margin-top: 8px; height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden; }
        .strength-bar { height: 100%; border-radius: 2px; transition: width .3s, background .3s; width: 0; }
        .strength-label { font-size: 12px; margin-top: 4px; }
        .text-muted-small { color: #6c757d; font-size: 12px; margin-top: 5px; display: block; }
        .text-center { text-align: center; }
        .mt-3 { margin-top: 15px; }
        .back-link { color: #667eea; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="reset-card">
    <div class="card-header">
        <h3><i class="fas fa-lock me-2"></i>Reset Password</h3>
        <p>Create a new secure password</p>
    </div>
    <div class="card-body">
        <div id="alertMessage"></div>

        <div class="success-badge">
            <i class="fas fa-check-circle" style="font-size:18px;flex-shrink:0"></i>
            OTP verified successfully. Please set your new password below.
        </div>

        <!-- New Password -->
        <div class="form-group">
            <label class="form-label"><i class="fas fa-key me-2"></i>New Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-key"></i></span>
                <input type="password" class="form-control" id="newPassword"
                       placeholder="Enter new password" autocomplete="new-password">
                <span class="input-group-text toggle" onclick="togglePwd('newPassword', this)">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
            <div class="strength-label" id="strengthLabel"></div>
            <small class="text-muted-small">Minimum 6 characters.</small>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
            <label class="form-label"><i class="fas fa-check-circle me-2"></i>Confirm Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="confirmPassword"
                       placeholder="Re-enter new password" autocomplete="new-password">
                <span class="input-group-text toggle" onclick="togglePwd('confirmPassword', this)">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <div id="matchHint" style="font-size:12px;margin-top:4px"></div>
        </div>

        <button type="button" class="btn btn-primary" id="resetBtn" onclick="resetPassword()">
            <i class="fas fa-save me-2"></i>Reset Password
        </button>

        <div class="text-center mt-3">
            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left me-1"></i>Back to Login
            </a>
        </div>
    </div>
</div>

<script>
// ── Show / hide password ──────────────────────────────────────────────────
function togglePwd(fieldId, iconWrap) {
    var inp  = document.getElementById(fieldId);
    var icon = iconWrap.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ── Password strength ─────────────────────────────────────────────────────
document.getElementById('newPassword').addEventListener('input', function () {
    var pw  = this.value;
    var bar = document.getElementById('strengthBar');
    var lbl = document.getElementById('strengthLabel');

    if (pw.length === 0) { bar.style.width = '0'; lbl.textContent = ''; return; }

    var score = 0;
    if (pw.length >= 6)             score++;
    if (pw.length >= 10)            score++;
    if (/[A-Z]/.test(pw))           score++;
    if (/[0-9]/.test(pw))           score++;
    if (/[^A-Za-z0-9]/.test(pw))    score++;

    var pct   = (score / 5) * 100;
    var color, text;
    if      (score <= 1) { color = '#dc3545'; text = 'Weak'; }
    else if (score <= 3) { color = '#ffc107'; text = 'Medium'; }
    else                 { color = '#28a745'; text = 'Strong'; }

    bar.style.width      = pct + '%';
    bar.style.background = color;
    lbl.innerHTML = 'Strength: <strong style="color:' + color + '">' + text + '</strong>';
});

// ── Match hint ─────────────────────────────────────────────────────────────
document.getElementById('confirmPassword').addEventListener('input', function () {
    var hint = document.getElementById('matchHint');
    var np   = document.getElementById('newPassword').value;
    if (this.value === '') { hint.textContent = ''; return; }
    if (this.value === np) {
        hint.innerHTML = '<span style="color:#28a745"><i class="fas fa-check me-1"></i>Passwords match</span>';
    } else {
        hint.innerHTML = '<span style="color:#dc3545"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
    }
});

// ── Reset password ────────────────────────────────────────────────────────
function resetPassword() {
    var np   = document.getElementById('newPassword').value;
    var cp   = document.getElementById('confirmPassword').value;
    var btn  = document.getElementById('resetBtn');

    if (np === '') {
        showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Please enter a new password.', 'danger');
        return;
    }
    if (np.length < 6) {
        showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Password must be at least 6 characters.', 'danger');
        return;
    }
    if (cp === '') {
        showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Please confirm your password.', 'danger');
        return;
    }
    if (np !== cp) {
        showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Passwords do not match.', 'danger');
        return;
    }

    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting…';
    btn.disabled  = true;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'sendmail.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    showAlert('<i class="fas fa-check-circle me-2"></i>' + res.message, 'success');
                    setTimeout(function () {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    btn.innerHTML = orig;
                    btn.disabled  = false;
                    showAlert('<i class="fas fa-exclamation-triangle me-2"></i>' + res.message, 'danger');
                    // If session expired, redirect to start over
                    if (res.message && res.message.toLowerCase().includes('session')) {
                        setTimeout(function () {
                            window.location.href = 'forgot_password.php';
                        }, 2500);
                    }
                }
            } catch (e) {
                btn.innerHTML = orig;
                btn.disabled  = false;
                showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Unexpected server response. Please try again.', 'danger');
            }
        } else {
            btn.innerHTML = orig;
            btn.disabled  = false;
            showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Network error. Please try again.', 'danger');
        }
    };

    xhr.send('method=ResetPassword'
        + '&newPassword='     + encodeURIComponent(np)
        + '&confirmPassword=' + encodeURIComponent(cp));
}

// Enter key on confirm field → submit
document.getElementById('confirmPassword').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') resetPassword();
});

function showAlert(msg, type) {
    var d = document.getElementById('alertMessage');
    d.innerHTML = '<div class="alert alert-' + type + '">' + msg + '</div>';
    if (type !== 'success') {
        setTimeout(function () { d.innerHTML = ''; }, 7000);
    }
}
</script>
</body>
</html>