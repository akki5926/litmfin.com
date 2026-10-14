<?php
// sendmail.php

// ── Session MUST start before any output buffering so cookie header is sent cleanly ──
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ── Now suppress errors before database.php (which sets display_errors=1) ──
ini_set('display_errors', 0);
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
ini_set('max_execution_time', 60);

ob_start(); // Buffer stray output from database.php include
require_once('database.php');
ob_end_clean(); // Discard any stray output from the include

header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// ─── OTP expiry: 10 minutes ────────────────────────────────────────────────
define('OTP_EXPIRY_SECONDS', 600);

try {
    $db = new MySQLDB();
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
$method = isset($_REQUEST['method']) ? trim($_REQUEST['method']) : '';

// ═══════════════════════════════════════════════════════════════════════════
// METHOD: CheckUserAndSendOTP
// Checks if user exists and if they are superadmin, then sends OTP
// ═══════════════════════════════════════════════════════════════════════════
if ($method === 'CheckUserAndSendOTP') {

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';

    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a username']);
        exit;
    }

    $conn = $db->getConnection();
    
    // Fetch user by username
    $stmt = $conn->prepare(
        "SELECT ad_id, ad_user_name, email_id, admin_role
         FROM tb_admin_user
         WHERE ad_user_name = ?
         LIMIT 1"
    );
    
    if (!$stmt) {
        error_log("sendmail.php prepare error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        exit;
    }
    
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin  = $result->fetch_assoc();
    $stmt->close();

    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Username not found. Please check and try again.']);
        exit;
    }

    // Check if user is superadmin
    if ($admin['admin_role'] != 1) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please ask a superadmin to change your password. Regular users cannot reset their own passwords.',
            'is_superadmin' => false
        ]);
        exit;
    }

    // Validate that the superadmin has an email on record
    if (empty($admin['email_id']) || !filter_var($admin['email_id'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Your account email is not configured. Please contact your system administrator.'
        ]);
        exit;
    }

    // ── Generate 6-digit numeric OTP ─────────────────────────────────────
    $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

    // ── Store OTP + timestamp in DB ──────────────────────────────────────
    $otpTime = date('Y-m-d H:i:s');
    $upd = $conn->prepare(
        "UPDATE tb_admin_user SET random_number = ?, otp_created_at = ? WHERE ad_id = ?"
    );
    if (!$upd) {
        error_log("sendmail.php update prepare error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        exit;
    }
    $upd->bind_param('ssi', $otp, $otpTime, $admin['ad_id']);

    if (!$upd->execute()) {
        error_log("sendmail.php update execute error: " . $upd->error);
        echo json_encode(['success' => false, 'message' => 'Could not save OTP. Please try again.']);
        $upd->close();
        exit;
    }
    $upd->close();

    // ── Save metadata in session ──
    $_SESSION['otp_admin_id']  = (int)$admin['ad_id'];
    $_SESSION['otp_issued_at'] = time();
    $_SESSION['otp_email']     = $admin['email_id'];
    unset($_SESSION['otp_verified'], $_SESSION['reset_admin_id']);

    // ── Send OTP via PHPMailer ───────────────────────────────────────────
    $smtpCfg    = $db->getSmtpConfig();
    $companyCfg = $db->getCompanyConfig();
    $siteName   = $db->getSiteName() ?: 'Admin Panel';

    $mail = new PHPMailer(true);
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = $smtpCfg['email_host'] ?? 'mail.litmfin.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpCfg['from_email'] ?? 'admin@litmfin.com';
        $mail->Password   = trim($smtpCfg['from_email_password'] ?? '', '"');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL for port 465
        $mail->Port       = (int)($smtpCfg['email_port'] ?? 465);
        
        // Bypass SSL certificate verification for localhost/WAMP
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Increase timeout for slow connections
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = true;

        $mail->setFrom(
            $smtpCfg['from_email'] ?? 'admin@litmfin.com',
            $smtpCfg['from_name'] ?? $siteName
        );
        $mail->addAddress($admin['email_id'], $admin['ad_user_name']);

        if (!empty($smtpCfg['cc_email']) && $smtpCfg['cc_email'] !== $admin['email_id']) {
            $mail->addCC($smtpCfg['cc_email']);
        }
        
        if (!empty($smtpCfg['bcc_email'])) {
            $mail->addBCC($smtpCfg['bcc_email']);
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $siteName . ' - Password Reset OTP';
        $mail->Body    = buildOtpEmailHtml($otp, $admin['ad_user_name'], $siteName, OTP_EXPIRY_SECONDS / 60);
        $mail->AltBody = "Hello {$admin['ad_user_name']},\n\n"
                       . "Your password reset OTP is: {$otp}\n"
                       . "This OTP is valid for " . (OTP_EXPIRY_SECONDS / 60) . " minutes.\n\n"
                       . "If you did not request this, please ignore this email.\n\n"
                       . "Regards,\n{$siteName}";

        $mail->send();

        echo json_encode([
            'success' => true,
            'message' => 'OTP sent to your registered email.'
        ]);

    } catch (MailException $e) {
        $smtpError = $mail->ErrorInfo;
        error_log("PHPMailer error: " . $smtpError);
        echo json_encode([
            'success' => false,
            'message' => 'Email delivery failed: ' . $smtpError
        ]);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// METHOD: ResetPassword  (called by resetpassword.php)
// ═══════════════════════════════════════════════════════════════════════════
if ($method === 'ResetPassword') {

    $newPassword     = isset($_POST['newPassword'])     ? $_POST['newPassword']     : '';
    $confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';

    // ── Session gate: must have passed OTP verification ───────────────────
    if (empty($_SESSION['otp_verified']) || empty($_SESSION['reset_admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please request a new OTP.']);
        exit;
    }

    // ── Validation ────────────────────────────────────────────────────────
    if ($newPassword === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter a new password.']);
        exit;
    }
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $adminId      = (int)$_SESSION['reset_admin_id'];
    $hashedPass   = password_hash($newPassword, PASSWORD_BCRYPT);
    $conn         = $db->getConnection();

    $stmt = $conn->prepare(
        "UPDATE tb_admin_user
         SET ad_user_pass = ?, random_number = NULL, otp_created_at = NULL
         WHERE ad_id = ?"
    );
    if (!$stmt) {
        error_log("ResetPassword prepare error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        exit;
    }
    $stmt->bind_param('si', $hashedPass, $adminId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        unset(
            $_SESSION['otp_admin_id'],
            $_SESSION['otp_issued_at'],
            $_SESSION['otp_email'],
            $_SESSION['otp_verified'],
            $_SESSION['reset_admin_id']
        );
        echo json_encode(['success' => true, 'message' => 'Password reset successfully! Redirecting to login…']);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// METHOD: VerifyOTP  (called by otp_verification.php)
// ═══════════════════════════════════════════════════════════════════════════
if ($method === 'VerifyOTP') {

    $enteredOtp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

    if ($enteredOtp === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter the OTP.']);
        exit;
    }

    if (empty($_SESSION['otp_admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please request a new OTP.', 'expired' => true]);
        exit;
    }

    $elapsed = time() - (int)($_SESSION['otp_issued_at'] ?? 0);
    if ($elapsed > OTP_EXPIRY_SECONDS) {
        $conn = $db->getConnection();
        $clr  = $conn->prepare("UPDATE tb_admin_user SET random_number = NULL WHERE ad_id = ?");
        if ($clr) { $clr->bind_param('i', $_SESSION['otp_admin_id']); $clr->execute(); $clr->close(); }
        unset($_SESSION['otp_admin_id'], $_SESSION['otp_issued_at'], $_SESSION['otp_email']);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.', 'expired' => true]);
        exit;
    }

    $adminId = (int)$_SESSION['otp_admin_id'];
    $conn    = $db->getConnection();
    $stmt    = $conn->prepare(
        "SELECT random_number FROM tb_admin_user WHERE ad_id = ? LIMIT 1"
    );
    if (!$stmt) {
        error_log("VerifyOTP prepare error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        exit;
    }
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['random_number'] === null || $row['random_number'] === '') {
        echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.', 'expired' => true]);
        exit;
    }

    if (!hash_equals((string)$row['random_number'], (string)$enteredOtp)) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please check and try again.']);
        exit;
    }

    $clr = $conn->prepare("UPDATE tb_admin_user SET random_number = NULL WHERE ad_id = ?");
    if ($clr) { $clr->bind_param('i', $adminId); $clr->execute(); $clr->close(); }

    $_SESSION['otp_verified']   = true;
    $_SESSION['reset_admin_id'] = $adminId;
    unset($_SESSION['otp_issued_at']);

    echo json_encode(['success' => true, 'message' => 'OTP verified! Redirecting…']);
    exit;
}

// ── Unknown method ─────────────────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;

// ═══════════════════════════════════════════════════════════════════════════
// Helper: build HTML email body
// ═══════════════════════════════════════════════════════════════════════════
function buildOtpEmailHtml(string $otp, string $name, string $siteName, int $expiryMin): string
{
    $safeOtp  = htmlspecialchars($otp,      ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($name,     ENT_QUOTES, 'UTF-8');
    $safeSite = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0">
    <tr><td align="center">
      <table width="500" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">
        <!-- header -->
        <tr>
          <td style="background:linear-gradient(135deg,#667eea,#764ba2);padding:30px;text-align:center">
            <h2 style="margin:0;color:#fff;font-size:22px">🔐 Password Reset OTP</h2>
          </td>
        </tr>
        <!-- body -->
        <tr>
          <td style="padding:30px">
            <p style="color:#333;font-size:15px">Hello <strong>{$safeName}</strong>,</p>
            <p style="color:#555;font-size:14px;line-height:1.6">
              You requested a password reset for your <strong>{$safeSite}</strong> admin account.
              Use the OTP below to proceed:
            </p>
            <!-- OTP box -->
            <div style="text-align:center;margin:28px 0">
              <div style="display:inline-block;background:#f0f4ff;border:2px dashed #667eea;
                          border-radius:12px;padding:18px 40px">
                <span style="font-size:36px;font-weight:700;letter-spacing:10px;color:#667eea">
                  {$safeOtp}
                </span>
              </div>
            </div>
            <p style="color:#e74c3c;font-size:13px;text-align:center">
              ⏱ This OTP is valid for <strong>{$expiryMin} minutes</strong>.
            </p>
            <p style="color:#777;font-size:13px;margin-top:24px">
              If you did not request this, please ignore this email. Your password will remain unchanged.
            </p>
          </td>
        </tr>
        <!-- footer -->
        <tr>
          <td style="background:#f8f8f8;padding:16px;text-align:center">
            <p style="margin:0;color:#aaa;font-size:12px">&copy; {$safeSite}. Do not reply to this email.</p>
          </td>
        </tr>
      </table>
    </td>
  </table>
</body>
</html>
HTML;
}
?>