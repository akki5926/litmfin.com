<?php
// otp_verification.php
// User lands here after forgot_password.php sends the OTP email.
// Session must contain otp_admin_id + otp_value set by sendmail.php.

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Guard: no active OTP request → back to forgot password
if (empty($_SESSION['otp_admin_id'])) {
    header('Location: forgot_password.php');
    exit;
}

// Already verified → skip straight to reset
if (!empty($_SESSION['otp_verified']) && !empty($_SESSION['reset_admin_id'])) {
    header('Location: resetpassword.php');
    exit;
}

// Mask the email so it's recognisable but not fully exposed  (e.g.  a***@gmail.com)
$rawEmail    = $_SESSION['otp_email'] ?? '';
$maskedEmail = '';
if ($rawEmail !== '') {
    $parts  = explode('@', $rawEmail, 2);
    $local  = $parts[0];
    $domain = $parts[1] ?? '';
    $masked = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1));
    $maskedEmail = $masked . '@' . $domain;
}

// Seconds remaining for the countdown
define('OTP_EXPIRY_SECONDS', 600);
$issuedAt  = (int)($_SESSION['otp_issued_at'] ?? 0);
$remaining = max(0, OTP_EXPIRY_SECONDS - (time() - $issuedAt));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
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
        .otp-card {
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
        .info-box {
            background: #e7f3ff; border-left: 4px solid #667eea;
            padding: 12px 14px; margin-bottom: 22px; border-radius: 6px; font-size: 13px; color: #333;
        }
        .form-group { margin-bottom: 20px; }
        .form-label { font-weight: 500; color: #495057; margin-bottom: 8px; display: block; font-size: 14px; }
        .input-group { display: flex; align-items: stretch; }
        .input-group-text {
            background: #f8f9fa; border: 1px solid #ced4da; border-right: none;
            padding: 10px 12px; border-radius: 8px 0 0 8px; color: #6c757d;
        }
        .form-control {
            border-left: none; padding: 10px 14px; border-radius: 0 8px 8px 0;
            border: 1px solid #ced4da; flex: 1; font-size: 18px; letter-spacing: 6px;
            font-weight: 600; text-align: center; transition: border-color .2s;
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
        .timer-row { text-align: center; margin-top: 14px; font-size: 14px; color: #666; }
        .timer-count { font-weight: 700; color: #dc3545; }
        .timer-expired { color: #dc3545; font-weight: 600; }
        .links-row { text-align: center; margin-top: 16px; font-size: 14px; }
        .links-row a { color: #667eea; text-decoration: none; }
        .links-row a:hover { text-decoration: underline; }
        .sep { color: #ccc; margin: 0 8px; }
    </style>
</head>
<body>
<div class="otp-card">
    <div class="card-header">
        <h3><i class="fas fa-shield-alt me-2"></i>OTP Verification</h3>
        <p>Enter the 6-digit code sent to your email</p>
    </div>
    <div class="card-body">
        <div id="alertMessage"></div>

        <?php if ($maskedEmail): ?>
        <div class="info-box">
            <i class="fas fa-envelope me-2" style="color:#667eea"></i>
            OTP sent to <strong><?= htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>
            <br><small style="color:#555">Please check your inbox (and spam folder).</small>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">
                <i class="fas fa-lock me-2"></i>Enter OTP
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-key"></i></span>
                <input type="text" class="form-control" id="otp"
                       placeholder="_ _ _ _ _ _" maxlength="6"
                       inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
            </div>
        </div>

        <button type="button" class="btn btn-primary" id="verifyBtn" onclick="verifyOTP()">
            <i class="fas fa-check-circle me-2"></i>Verify OTP
        </button>

        <div class="timer-row" id="timerRow">
            OTP expires in: <span class="timer-count" id="timerCount"></span>
        </div>

        <div class="links-row">
            <a href="forgot_password.php">
                <i class="fas fa-redo me-1"></i>Resend OTP
            </a>
            <span class="sep">|</span>
            <a href="login.php">
                <i class="fas fa-arrow-left me-1"></i>Back to Login
            </a>
        </div>
    </div>
</div>

<script>
// ── Countdown timer ──────────────────────────────────────────────────────
var timeLeft    = <?= (int)$remaining ?>;
var timerEl     = document.getElementById('timerCount');
var timerRow    = document.getElementById('timerRow');
var verifyBtn   = document.getElementById('verifyBtn');
var timerHandle = null;

function pad(n) { return n < 10 ? '0' + n : n; }

function tick() {
    if (timeLeft <= 0) {
        clearInterval(timerHandle);
        timerRow.innerHTML = '<span class="timer-expired">'
            + '<i class="fas fa-clock me-1"></i>OTP expired. '
            + '<a href="forgot_password.php">Request a new one</a>.'
            + '</span>';
        verifyBtn.disabled = true;
        return;
    }
    var m = Math.floor(timeLeft / 60);
    var s = timeLeft % 60;
    timerEl.textContent = m + ':' + pad(s);
    timeLeft--;
}

if (timeLeft > 0) {
    tick();
    timerHandle = setInterval(tick, 1000);
} else {
    timerRow.innerHTML = '<span class="timer-expired">'
        + '<i class="fas fa-clock me-1"></i>OTP expired. '
        + '<a href="forgot_password.php">Request a new one</a>.'
        + '</span>';
    verifyBtn.disabled = true;
}

// ── Only allow digits ────────────────────────────────────────────────────
document.getElementById('otp').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
});

// ── Verify OTP ───────────────────────────────────────────────────────────
function verifyOTP() {
    var otp = document.getElementById('otp').value.trim();

    if (otp === '') {
        showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Please enter the OTP.', 'danger');
        return;
    }
    if (otp.length !== 6) {
        showAlert('<i class="fas fa-exclamation-triangle me-2"></i>OTP must be 6 digits.', 'danger');
        return;
    }

    var orig = verifyBtn.innerHTML;
    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying…';
    verifyBtn.disabled  = true;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'sendmail.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    clearInterval(timerHandle);
                    showAlert('<i class="fas fa-check-circle me-2"></i>OTP verified! Redirecting…', 'success');
                    setTimeout(function () {
                        window.location.href = 'resetpassword.php';
                    }, 1500);
                } else {
                    verifyBtn.innerHTML = orig;
                    verifyBtn.disabled  = (res.expired === true);
                    showAlert('<i class="fas fa-exclamation-triangle me-2"></i>' + res.message, 'danger');
                    if (res.expired) {
                        // Redirect back after a moment so they can resend
                        setTimeout(function () {
                            window.location.href = 'forgot_password.php';
                        }, 3000);
                    }
                }
            } catch (e) {
                verifyBtn.innerHTML = orig;
                verifyBtn.disabled  = false;
                showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Unexpected server response. Please try again.', 'danger');
            }
        } else {
            verifyBtn.innerHTML = orig;
            verifyBtn.disabled  = false;
            showAlert('<i class="fas fa-exclamation-triangle me-2"></i>Network error. Please try again.', 'danger');
        }
    };

    xhr.send('method=VerifyOTP&otp=' + encodeURIComponent(otp));
}

document.getElementById('otp').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') verifyOTP();
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