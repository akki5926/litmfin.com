<?php
// email_verification_api.php
require_once __DIR__ . '/includes/cors.php';
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    // Get the action
    $action = $_GET['action'] ?? '';
    
    if ($action === 'send_verification') {
        handleSendVerificationEmail();
    } else {
        sendResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage());
}

function handleSendVerificationEmail() {
    // Get JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        sendResponse(false, 'Invalid JSON data');
    }
    
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    $clientCode = $data['clientCode'] ?? '';
    $baseUrl = $data['baseUrl'] ?? ''; // Get app base URL from frontend
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Valid email address is required');
    }
    
    // Use defaults if missing
    $userName = !empty($name) ? $name : '';
    $userClientCode = !empty($clientCode) ? $clientCode : '';
    
    try {
        // Initialize database
        $db = new MfdDb();
        
        // Check if user exists and get user data
        $user = $db->getUserByEmail($email);
        
        if (!$user) {
            sendResponse(false, 'User not found with this email');
        }
        
        // Generate verification token (64-character hex)
        $verificationToken = bin2hex(random_bytes(32));
        
        // Set expiration (24 hours from now)
        $expiryTime = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Save token to database using user ID
        $tokenSaved = $db->saveVerificationToken($user['id'], $verificationToken, $expiryTime);
        
        if (!$tokenSaved) {
            sendResponse(false, 'Failed to generate verification token');
        }
        
        // Use user's actual name from database if available
        $userName = $user['PRIMARY_HOLDER_NAME'] ?? $name;
        
        // Send verification email with baseUrl
        $emailSent = sendVerificationEmail($db, $email, $userName, $userClientCode, $verificationToken, $baseUrl);
        
        if ($emailSent) {
            sendResponse(true, 'Verification email sent successfully', [
                'email' => $email,
                'expires_at' => $expiryTime
            ]);
        } else {
            sendResponse(false, 'Failed to send verification email');
        }
        
    } catch (Exception $e) {
        error_log("Send verification email error: " . $e->getMessage());
        sendResponse(false, 'An error occurred while sending verification email');
    }
}

function sendVerificationEmail(MfdDb $db, string $email, string $name, string $clientCode, string $token, string $frontendBaseUrl = ''): bool
{
    $company = $db->getCompanyConfig();
    $smtp    = $db->getSmtpConfig();

    // Use frontend base URL if provided, otherwise calculate from server
    if (!empty($frontendBaseUrl)) {
        $baseUrl = rtrim($frontendBaseUrl, '/');
    } else {
        // Base URL fallback (server-based calculation)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $host     = $_SERVER['HTTP_HOST'];

        // Dynamically determine the base path (e.g., /v4) from the current script path
        $baseDir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); // e.g., /v4/api
        $basePath = preg_replace('/\/api\/?$/i', '', $baseDir); // remove /api
        $baseUrl  = rtrim($protocol . $host . $basePath, '/');
    }

    // Verification link
    $verificationLink = $baseUrl . '/verify-email?token=' . urlencode($token)
        . '&email=' . urlencode($email);

    // Subject (company-driven)
	$companyName = $company['name'];
    $subject = 'Verify Your ' . $company['name'] . ' Account';

    // HTML body
    $htmlContent = generateEmailHtml($name, $companyName,$clientCode, $verificationLink);

    // Headers (config-driven)
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: "' . $company['name'] . '" <' . $smtp['from_email'] . '>',
        'Reply-To: ' . ($company['support_email'] ?? $smtp['from_email']),
        'X-Mailer: PHP/' . phpversion()
    ];

    // Try PHP mail first
    $mailSent = @mail(
        $email,
        $subject,
        $htmlContent,
        implode("\r\n", $headers)
    );

    // SMTP fallback
    if (!$mailSent) {
        $mailSent = $db->sendMailViaSMTP($email, $subject, $htmlContent);
    }

    return $mailSent;
}

function generateEmailHtml($name, $companyName,$clientCode, $verificationLink) {
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
                
                " . (!empty($clientCode) ? "
                <div style=\"background: #e0f2fe; padding: 15px; border-radius: 5px; margin: 20px 0;\">
                    <p><strong>Your Client Code:</strong> <span style=\"font-family: monospace; font-weight: bold;\">$clientCode</span></p>
                    <p>Keep this code safe as you'll need it for future transactions and account-related communications.</p>
                </div>
                " : "") . "
                
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
                    <p>$companyName</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}

?>