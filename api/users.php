<?php
require_once __DIR__ . '/includes/cors.php';

// Get database connection (cors.php already created $db)
// If $db is not set, create it
if (!isset($db)) {
    require_once __DIR__ . '/db/mfddb.php';
    $db = new MfdDb();
}

// Get request method and data
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Get request data
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

// Route based on action
switch($action) {
    case 'signup':
        handleSignup($db, $input);
        break;
        
    case 'login':
        handleLogin($db, $input);
        break;
        
    case 'forgot-password':
        handleForgotPassword($db, $input);
        break;
        
    case 'verify-token':
        handleVerifyResetToken($db, $input);
        break;
        
    case 'reset-password':
        handleResetPassword($db, $input);
        break;
        
    case 'send_verification':
        $emailResult = handleSendVerificationEmail($db, $input['email']);
        sendResponse(true, 'Signup successful', [
            'emailVerification' => $emailResult
        ]);
        break;
        
    case 'check_email_verification':
        if (empty($input['email'])) {
            sendResponse(false, 'Email is required', null, 400);
        }
        $result = checkEmailVerificationStatus($db, $input['email']);
        sendResponse(
            $result['success'],
            $result['message'],
            [
                'verified' => $result['verified'] ?? false
            ]
        );
        break;

    case 'verify':
        handleVerifyEmail($db, $input);
        break;
        
    case 'get_user_profile':
    case 'get_user_details':
        handleGetUserProfile($db, $input);
        break;
        
    case 'check_session':
        handleCheckSession($db);
        break;
        
    case 'logout':
        handleLogout($db);
        break;
        
    default:
        sendResponse(false, 'Invalid API endpoint', null, 404);
        break;
}

/**
 * Handle user registration
 */
function handleSignup($db, $data) {
    // Validate required fields
    $required = ['fullName', 'email', 'mobile', 'pan', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "$field is required");
        }
    }
    
    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format');
    }
    
    // Validate mobile (10 digits, starting with 6-9)
    if (!preg_match('/^[6-9]\d{9}$/', $data['mobile'])) {
        sendResponse(false, 'Invalid mobile number. Must be 10 digits starting with 6-9');
    }
    
    // Validate PAN (format: ABCDE1234F)
    $pan = strtoupper($data['pan']);
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
        sendResponse(false, 'Invalid PAN format. Must be like ABCDE1234F');
    }
    
    // Validate password
    if (strlen($data['password']) < 8) {
        sendResponse(false, 'Password must be at least 8 characters');
    }
    
    // Check if user already exists
    if ($db->checkUserExists($data['email'], $data['mobile'], $pan)) {
        sendResponse(false, 'User with this email, mobile or PAN already exists');
    }
    
    // Generate client code
    $clientCode = $db->generateClientCode();
    
    // Prepare user data
    $userData = [
        'client_code' => $clientCode,
        'primary_holder_name' => $data['fullName'],
        'primary_holder_pan' => $pan,
        'email' => $data['email'],
        'indian_mobile_no' => $data['mobile'],
        'address_1' => $data['address'] ?? '',
        'city' => $data['city'] ?? '',
        'state' => $data['state'] ?? '',
        'pincode' => $data['pincode'] ?? '',
        'country' => 'IN',
        'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
        'email_verified' => 0
    ];
    
    // Save to database
    try {
        if ($db->createUser($userData)) {
            // Send verification email
            handleSendVerificationEmail($db, $data['email']);
        
            sendResponse(true, 'Registration successful! Please verify your email.', [
                'clientCode' => $clientCode,
                'message' => 'Verification email sent'
            ]);
        } else {
            sendResponse(false, 'Registration failed. Please try again.');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage());
    }
}

/**
 * Handle user login with session management
 */
function handleLogin($db, $data) {
    $identifier = $data['identifier'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        sendResponse(false, 'Identifier and password are required');
    }
    
    // Get user by identifier (email, mobile, or PAN)
    $users = $db->getUserByIdentifier($identifier);
    
    if (!$users || count($users) === 0) {
        sendResponse(false, 'User not found');
        return;
    }
    
    if (count($users) > 1) {
        $selectedUser = $data['selected_user'] ?? $data['user_name'] ?? '';
        if (!empty($selectedUser)) {
            $matched = array_values(array_filter($users, function($u) use ($selectedUser) {
                return (
                    ($u['PRIMARY_HOLDER_NAME'] ?? '') === $selectedUser ||
                    ($u['id'] ?? '') == $selectedUser ||
                    ($u['CLIENT_CODE'] ?? '') === $selectedUser
                );
            }));
            if (!empty($matched)) {
                $users = $matched;
            }
        }
    }
    
    if (count($users) > 1) {
        $names = array_column($users, 'PRIMARY_HOLDER_NAME');
        sendResponse(false, 'multiple user found', ['users' => $names]);
        return;
    }
    
    $user = $users[0];
    
    
    // Verify password
    if (!password_verify($password, $user['PASSWORD_HASH'])) {
        sendResponse(false, 'Invalid password');
        return;
    }
    
    // Check account status
    if ($user['EMAIL_VERIFIED'] == 0) {
        sendResponse(false, 'Account is not active. Please complete verification.');
        return;
    }
    
    // Start session and store user data
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['EMAIL'];
    $_SESSION['client_code'] = $user['CLIENT_CODE'];
    $_SESSION['user_name'] = $user['PRIMARY_HOLDER_NAME'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    
    // Update last login
    $db->updateLoginTimestamp($user['id']);
    
    // Generate JWT token
    try {
        $token = generateJWT([
            'user_id' => $user['id'],
            'email'   => $user['EMAIL'],
            'client_code' => $user['CLIENT_CODE']
        ]);
    } catch (Exception $e) {
        // Fallback token if JWT fails
        $token = bin2hex(random_bytes(32));
    }

    sendResponse(true, 'Login successful', [
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'clientCode' => $user['CLIENT_CODE'],
            'name' => $user['PRIMARY_HOLDER_NAME'],
            'email' => $user['EMAIL'],
            'mobile' => $user['INDIAN_MOBILE_NO']
        ]
    ]);
}

/**
 * Handle session check - validates if user session is still active
 */
function handleCheckSession($db) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId    = null;
    $clientCode = null;

    // ── Strategy 1: PHP session ──────────────────────────────────────────────
    if (isset($_SESSION['user_id'])) {
        $lastActivity = $_SESSION['last_activity'] ?? time();
        $timeout      = 60 * 60; // 60 minutes

        if ((time() - $lastActivity) > $timeout) {
            // Session timed out – destroy and fall through to JWT check
            session_destroy();
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        } else {
            // Valid session — do NOT update last_activity here.
            // check_session is called every 60 s by the periodic background check;
            // if we refreshed last_activity on every call, the backend timeout
            // would never fire and inactivity logout would be impossible.
            // last_activity is only updated by the 'ping' action (real user activity).
            $userId     = $_SESSION['user_id'];
            $clientCode = $_SESSION['client_code'] ?? '';
        }
    }

    // ── Strategy 2: JWT Bearer token (fallback when session is missing) ──────
    if (!$userId) {
        $token = getBearerToken();
        if ($token) {
            $jwtUserId = verifyJwtToken($token);
            if ($jwtUserId) {
                $userId = $jwtUserId;
                // Re-hydrate the session so subsequent PHP-session checks pass.
                // IMPORTANT: do NOT set last_activity to time() here.
                // check_session is called every 60 s by the periodic background check;
                // resetting last_activity on every call would keep idle sessions alive
                // forever and prevent the 1-hour inactivity logout from working.
                // last_activity is only stamped at login time and updated via 'ping'.
                $_SESSION['user_id']    = $userId;
                $_SESSION['login_time'] = $_SESSION['login_time'] ?? time();
                // Preserve existing last_activity if already set; otherwise use
                // the JWT issued-at time so the inactivity clock starts from login.
                if (!isset($_SESSION['last_activity'])) {
                    // iat is when the token was issued — safest reference point
                    $parts = explode('.', $token);
                    $jwtPayload = json_decode(base64_decode(
                        str_replace(['-', '_'], ['+', '/'], $parts[1])
                    ), true);
                    $_SESSION['last_activity'] = $jwtPayload['iat'] ?? time();
                }
            }
        }
    }

    // ── No valid auth found ───────────────────────────────────────────────────
    if (!$userId) {
        sendResponse(false, 'No active session', null, 401);
        return;
    }

    sendResponse(true, 'Session valid', [
        'valid'       => true,          // <-- JS checks result.data.valid or result.success
        'user_id'     => $userId,
        'client_code' => $clientCode ?? ''
    ]);
}

/**
 * Handle logout - destroys session and clears cookies
 */
function handleLogout($db) {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    sendResponse(true, 'Logged out successfully');
}

/**
 * Handle forgot password request
 */
/**
 * Mask email address for privacy (e.g. rajesh@gmail.com -> r***h@gmail.com)
 */
function maskEmailAddress($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } else {
        $maskedName = substr($name, 0, 1) . str_repeat('*', max(3, $len - 2)) . substr($name, -1);
    }
    return $maskedName . '@' . $domain;
}

/**
 * Handle forgot password request
 */
function handleForgotPassword($db, $data) {
    $pan = trim($data['pan'] ?? '');
    $emailInput = trim($data['email'] ?? '');

    // ── Flow 1: PAN-based Forgot Password (Preferred) ──────────────────────────
    if (!empty($pan)) {
        $pan = strtoupper($pan);
        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
            sendResponse(false, 'Please enter a valid 10-character PAN number (e.g. ABCDE1234F)');
            return;
        }

        // Fetch exact user profile tied to this PAN
        $user = $db->getUserByPan($pan);
        if (!$user || empty($user['EMAIL'])) {
            sendResponse(true, 'If the PAN provided is registered, password reset instructions have been sent to the registered email address.');
            return;
        }

        $email = $user['EMAIL'];
        $name = $user['PRIMARY_HOLDER_NAME'] ?? 'User';
        $token = bin2hex(random_bytes(32));

        if ($db->createPasswordResetTokenByPan($pan, $token)) {
            $emailSent = sendPasswordResetEmail($db, $email, $name, $token, $pan);
            if ($emailSent) {
                $db->updateEmailVerified($email);
                $maskedEmail = maskEmailAddress($email);
                sendResponse(true, "Password reset instructions sent to registered email ($maskedEmail). Please check your inbox.", [
                    'maskedEmail' => $maskedEmail,
                    'pan' => $pan
                ]);
            } else {
                sendResponse(false, 'Failed to send email. Please try again later.');
            }
        } else {
            sendResponse(false, 'Failed to generate reset token');
        }
        return;
    }

    // ── Flow 2: Email-based Fallback ───────────────────────────────────────────
    if (empty($emailInput) || !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Valid PAN number or Email address is required');
        return;
    }

    $user = $db->getUserByIdentifier($emailInput);
    if (!$user) {
        sendResponse(true, 'If your email exists, you will receive reset instructions.');
        return;
    }

    $token = bin2hex(random_bytes(32));

    if ($db->createPasswordResetToken($emailInput, $token)) {
        $emailSent = sendPasswordResetEmail($db, $emailInput, $user['PRIMARY_HOLDER_NAME'] ?? 'User', $token);
        
        if ($emailSent) {
            $db->updateEmailVerified($emailInput);
            sendResponse(true, 'Password reset instructions sent to your email. Please check your inbox.');
        } else {
            sendResponse(false, 'Failed to send email. Please try again later.');
        }
    } else {
        sendResponse(false, 'Failed to generate reset token');
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($db, $email, $name, $token, $pan = null) {
    // Get base URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // If running on localhost
    if ($host === 'localhost' || str_starts_with($host, 'localhost:')) {
        $baseUrl = 'http://localhost:3000';
    } else {
        // Dynamically determine the base path
        $baseDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $basePath = preg_replace('/\/api\/?$/i', '', $baseDir);
        $baseUrl = rtrim($protocol . $host . $basePath, '/');
    }
    
    // Create reset link
    $resetLink = $baseUrl . "/forgot-password?token=" . urlencode($token) . "&email=" . urlencode($email);
    if (!empty($pan)) {
        $resetLink .= "&pan=" . urlencode($pan);
    }

    $company = $db->getCompanyConfig();
    $companyName = $company['name'];
    $support_email = $company['support_email'];
    
    // Email configuration
    $subject = $companyName . " Password Reset Request";
    
    // Email HTML content
    $htmlContent = generatePasswordResetEmailHtml($name, $resetLink, $token, $companyName, $support_email);
    
    return $db->sendMailViaSMTP($email, $subject, $htmlContent);
}

/**
 * Generate password reset email HTML
 */
function generatePasswordResetEmailHtml($name, $resetLink, $token, $companyName, $support_email) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\"utf-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <title>Reset Your $companyName Password</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #1e40af, #1e3a8a);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .content {
                padding: 30px;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #1e40af, #1e3a8a);
                color: white;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
                text-align: center;
            }
            .code-box {
                background: #f3f4f6;
                padding: 15px;
                border-radius: 5px;
                font-family: monospace;
                font-size: 14px;
                word-break: break-all;
                margin: 20px 0;
                border-left: 4px solid #1e40af;
            }
            .warning {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e5e7eb;
                color: #6b7280;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class=\"container\">
            <div class=\"header\">
                <h1>Password Reset Request</h1>
                <p>$companyName - Smart Investment Platform</p>
            </div>
            
            <div class=\"content\">
                <h2>Hello $name,</h2>
                
                <p>We received a request to reset your password for your $companyName account. If you didn't make this request, you can safely ignore this email.</p>
                
                <p>To reset your password, click the button below:</p>
                
                <div style=\"text-align: center; margin: 30px 0;\">
                    <a href=\"$resetLink\" style=\"display: inline-block; background-color: #1e40af; color: white; padding: 14px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; mso-padding-alt: 14px 40px; text-align: center; border: 2px solid #1e40af;\">Reset Password</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <div class=\"code-box\">$resetLink</div>
                
                <div class=\"warning\">
                    <strong>Important:</strong> This reset link will expire in 1 hour. For security reasons, do not share this link with anyone.
                </div>
                
                <p>Alternatively, you can use this verification code on the password reset page:</p>
                <div class=\"code-box\">$token</div>
                
                <p>If you have any questions or need assistance, our support team is here to help.</p>
                
                <p>Best regards,<br>
                <strong>The $companyName Team</strong></p>
                
                <div class=\"footer\">
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>$companyName</p>
                    <p>Need help? Contact us at <a href=\"mailto:$support_email\" style=\"color: #1e40af;\">$support_email</a></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Handle reset token verification
 */
function handleVerifyResetToken($db, $data) {
    $email = trim($data['email'] ?? '');
    $pan = trim($data['pan'] ?? '');
    $token = trim($data['token'] ?? '');
    
    if (empty($token) || (empty($email) && empty($pan))) {
        sendResponse(false, 'Token and PAN or Email are required');
    }
    
    // Validate token format (64 hex characters)
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        sendResponse(false, 'Invalid token format');
    }
    
    // Check if token is valid and not expired
    $user = null;
    if (!empty($pan)) {
        $user = $db->validateResetTokenByPan($pan, $token);
    } elseif (!empty($email)) {
        $user = $db->validateResetToken($email, $token);
    }
    
    if (!$user) {
        sendResponse(false, 'This reset token has expired or is invalid');
    }
    
    sendResponse(true, 'Token is valid and ready for password reset', [
        'pan' => $user['PRIMARY_HOLDER_PAN'] ?? $pan,
        'email' => $user['EMAIL'] ?? $email
    ]);
}

/**
 * Handle email verification
 */
function handleVerifyEmail($db, $data) {
    // Validate required parameters exist
    if (!isset($data['email']) || !isset($data['token'])) {
        sendResponse(false, 'Email and token are required');
    }
    
    $email = trim($data['email']);
    $token = trim($data['token']);
    
    // Validate email
    if (empty($email)) {
        sendResponse(false, 'Email address is required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Please provide a valid email address');
    }
    
    // Validate token
    if (empty($token)) {
        sendResponse(false, 'Verification token is required');
    }
    
    if (strlen($token) !== 64) {
        sendResponse(false, 'Invalid verification token');
    }
    
    if (!ctype_xdigit($token)) {
        sendResponse(false, 'Invalid verification token format');
    }
    
    try {
        // Check if token is valid and not expired
        $user = $db->validateResetToken($email, $token);
        
        if (!$user) {
            $emailExists = $db->checkUserExistsWithEmail($email);
            
            if ($emailExists) {
                sendResponse(false, 'This verification link has expired or is invalid. Please request a new verification email.');
            } else {
                sendResponse(false, 'Invalid verification link.');
            }
            return;
        }
        
        // Check if email is already verified
        if ($db->isEmailAlreadyVerified($email)) {
            sendResponse(true, 'Email is already verified. You can proceed to login.');
            return;
        }
        
        // Token is valid - verify the email
        $updateResult = $db->markTokenAsVerified($email, $token);
        
        if ($updateResult) {
            $userData = $db->getUserByEmail($email);
            
            sendResponse(true, 'Email verified successfully!', [
                'email' => $email,
                'user_id' => $userData['id'] ?? null,
                'redirect_to' => '/verify'
            ]);
        } else {
            sendResponse(false, 'Failed to verify email. Please try again or contact support.');
        }
        
    } catch (Exception $e) {
        error_log("Email verification error for {$email}: " . $e->getMessage());
        sendResponse(false, 'An unexpected error occurred. Please try again later.');
    }
}

/**
 * Handle password reset
 */
function handleResetPassword($db, $data) {
    $email = trim($data['email'] ?? '');
    $pan = trim($data['pan'] ?? '');
    $token = trim($data['token'] ?? '');
    $newPassword = $data['newPassword'] ?? '';
    
    if (empty($token) || empty($newPassword) || (empty($email) && empty($pan))) {
        sendResponse(false, 'All fields are required');
    }
    
    if (strlen($newPassword) < 8) {
        sendResponse(false, 'Password must be at least 8 characters');
    }
    
    // Validate token using PAN or Email
    $user = null;
    if (!empty($pan)) {
        $user = $db->validateResetTokenByPan($pan, $token);
    } elseif (!empty($email)) {
        $user = $db->validateResetToken($email, $token);
    }

    if (!$user) {
        sendResponse(false, 'Invalid or expired reset token');
    }
    
    // Update password strictly for this user ID
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    if ($db->updatePassword($user['id'], $passwordHash)) {
        // Clear token for this specific user profile
        if (method_exists($db, 'clearResetTokenById')) {
            $db->clearResetTokenById($user['id']);
        }
        sendResponse(true, 'Password reset successful');
    } else {
        sendResponse(false, 'Failed to reset password');
    }
}

/**
 * Handle sending verification email
 */
function handleSendVerificationEmail($db, $email) {
    try {
        // Check if user exists
        $user = $db->getUserByEmail($email);

        if (!$user) {
            return [
                'success' => false,
                'code' => 'USER_NOT_FOUND',
                'message' => 'User does not exist'
            ];
        }

        // Already verified
        if ((int)$user['EMAIL_VERIFIED'] === 1) {
            return [
                'success' => false,
                'code' => 'ALREADY_VERIFIED',
                'message' => 'Email is already verified'
            ];
        }

        // Check existing token
        $tokenData = $db->getEmailVerificationToken($user['id']);
        $tokenValid = false;

        if ($tokenData && !empty($tokenData['RESET_TOKEN_EXPIRY'])) {
            $tokenValid = strtotime($tokenData['RESET_TOKEN_EXPIRY']) > time();
        }

        if ($tokenValid) {
            return [
                'success' => false,
                'code' => 'TOKEN_ALREADY_SENT',
                'message' => 'Verification email already sent. Please check your inbox.'
            ];
        }

        // Generate new token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours

        // Save token
        $db->saveVerificationToken($user['id'], $token, $expiresAt);
        $name = $user['PRIMARY_HOLDER_NAME'];
        
        // Send email
        $emailSent = sendVerificationEmail($db, $email, $name, $token);

        if (!$emailSent) {
            return [
                'success' => false,
                'code' => 'EMAIL_FAILED',
                'message' => 'Unable to send verification email. Try again later.'
            ];
        }

        return [
            'success' => true,
            'code' => 'EMAIL_SENT',
            'message' => 'Verification email sent successfully'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'code' => 'SERVER_ERROR',
            'message' => 'Something went wrong. Please try again.'
        ];
    }
}

/**
 * Check email verification status
 */
function checkEmailVerificationStatus($db, $email) {
    try {
        $isVerified = $db->isEmailAlreadyVerified($email);
        
        return [
            'success' => $isVerified,
            'message' => $isVerified ? 'Email is verified' : 'Email is not verified yet.',
            'verified' => $isVerified
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error checking verification status: ' . $e->getMessage(),
            'verified' => false
        ];
    }
}

/**
 * Send verification email
 */
function sendVerificationEmail($db, $email, $name, $token) {
    // Get base URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // If running on localhost
    if ($host === 'localhost' || str_starts_with($host, 'localhost:')) {
        $baseUrl = 'http://localhost:3000';
    } else {
        // Dynamically determine the base path
        $baseDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $basePath = preg_replace('/\/api\/?$/i', '', $baseDir);
        $baseUrl = rtrim($protocol . $host . $basePath, '/');
    }
    
    $company = $db->getCompanyConfig();
    $companyName = $company['name'];
    $support_email = $company['support_email'];
    
    // Create verification link
    $verificationLink = $baseUrl . "/verify-email?token=" . urlencode($token) . "&email=" . urlencode($email);
    
    // Email configuration
    $to = $email;
    $subject = "Verify Your $companyName Account";
    
    // Email HTML content
    $htmlContent = generateEmailHtml($name, $verificationLink, $companyName, $support_email);
    
    $mailSent = $db->sendMailViaSMTP($email, $subject, $htmlContent);
    
    return $mailSent;
}

/**
 * Generate verification email HTML
 */
function generateEmailHtml($name, $verificationLink, $companyName, $support_email) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset=\"utf-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <title>Verify Your $companyName Account</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #1e40af, #1e3a8a);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .content {
                padding: 30px;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #1e40af, #1e3a8a);
                color: white;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
                text-align: center;
            }
            .code-box {
                background: #f3f4f6;
                padding: 15px;
                border-radius: 5px;
                font-family: monospace;
                font-size: 14px;
                word-break: break-all;
                margin: 20px 0;
                border-left: 4px solid #1e40af;
            }
            .warning {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e5e7eb;
                color: #6b7280;
                font-size: 12px;
            }
            .steps {
                background: #f9fafb;
                padding: 20px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .steps ol {
                margin: 10px 0;
                padding-left: 20px;
            }
            .steps li {
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <div class=\"container\">
            <div class=\"header\">
                <h1>$companyName Account Verification</h1>
                <p>Welcome to India's Smart Investment Platform</p>
            </div>
            
            <div class=\"content\">
                <h2>Hello $name,</h2>
                
                <p>Thank you for registering with <strong>$companyName</strong> - your gateway to smart investments!</p>
                
                <p>To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                
                <div style=\"text-align: center;\">
                    <a href=\"$verificationLink\" class=\"button\">Verify Email Address</a>
                </div>
                
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <div class=\"code-box\">$verificationLink</div>
                
                <div class=\"warning\">
                    <strong>Important:</strong> This verification link will expire in 24 hours. If you didn't request this verification, please ignore this email.
                </div>
                
                <div class=\"steps\">
                    <h3>Next Steps:</h3>
                    <ol>
                        <li>Verify your email (using the link above)</li>
                        <li>Complete your KYC process</li>
                        <li>Set up your investment profile</li>
                        <li>Start your investment journey!</li>
                    </ol>
                </div>
                
                <p>If you have any questions or need assistance, our support team is here to help.</p>
                
                <p>Best regards,<br>
                <strong>The $companyName Team</strong></p>
                
                <div class=\"footer\">
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>$companyName<br>
                    
                    <p>Need help? Contact us at <a href=\"mailto:$support_email\" style=\"color: #1e40af;\">$support_email</a></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Get user profile details
 */
function handleGetUserProfile($db, $data) {
    // Get user from session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    
    if (empty($userId)) {
        sendResponse(false, 'Not authenticated');
        return;
    }
    
    $user = $db->getUserProfileDetails($userId);
    
    if (!$user) {
        sendResponse(false, 'User not found');
        return;
    }
    
    // Format the response
    $formattedUser = [
        'PRIMARY_HOLDER_PAN' => $user['PRIMARY_HOLDER_PAN'] ?? '',
        'PRIMARY_HOLDER_NAME' => $user['PRIMARY_HOLDER_NAME'] ?? '',
        'PRIMARY_HOLDER_DOB' => $user['PRIMARY_HOLDER_DOB'] ?? '',
        'EMAIL' => $user['EMAIL'] ?? '',
        'INDIAN_MOBILE_NO' => $user['INDIAN_MOBILE_NO'] ?? '',
        'CLIENT_CODE' => $user['CLIENT_CODE'] ?? ''
    ];
    
    sendResponse(true, 'User details retrieved successfully', $formattedUser);
}
?>