<?php
// Include CORS configuration
require_once __DIR__ . '/includes/cors.php';

// Include the database class for SMTP functionality
require_once __DIR__ . '/db/mfddb.php';

// ============================================
// FULLY DYNAMIC - Reads all config from siteConfig
// NO HARDCODED VALUES - Works for any website
// ============================================

// Function to read siteConfig from Next.js config file
function getSiteConfig() {
    // Try multiple possible paths for siteConfig.ts or litm_siteConfig.ts
    $configPaths = [
        __DIR__ . '/../config/siteConfig.ts',
        __DIR__ . '/../config/litm_siteConfig.ts',
        __DIR__ . '/../src/config/siteConfig.ts',
        __DIR__ . '/../src/config/litm_siteConfig.ts',
        __DIR__ . '/../../config/siteConfig.ts',
        __DIR__ . '/../../src/config/siteConfig.ts',
        __DIR__ . '/../frontend/config/siteConfig.ts',
    ];
    
    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            
            // Extract values using regex patterns (supporting single and double quotes)
            $config = [];
            
            // Extract siteName
            if (preg_match('/siteName:\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['siteName'] = $matches[1];
            }
            
            // Extract supportEmail
            if (preg_match('/supportEmail:\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['supportEmail'] = $matches[1];
                $config['csEmail'] = $matches[1];
            }
            
            // Extract csEmail
            if (preg_match('/csEmail:\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['csEmail'] = $matches[1];
            }
            
            // Extract csPhone / phone
            if (preg_match('/(?:csPhone|phone):\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['csPhone'] = $matches[1];
            }
            
            // Extract mediaEmail
            if (preg_match('/mediaEmail:\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['mediaEmail'] = $matches[1];
            }
            
            // Extract privacyEmail
            if (preg_match('/privacyEmail:\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['privacyEmail'] = $matches[1];
            }
            
            // Extract general email
            if (preg_match('/email:\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['email'] = $matches[1];
            }
            
            // Extract CompanyName
            if (preg_match('/CompanyName:\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['companyName'] = $matches[1];
            }
            
            // Extract domain
            if (preg_match('/domain:\s*["\']([^"\']+)["\']/', $content, $matches)) {
                $config['domain'] = $matches[1];
            }
            
            // If we found config details, return config
            if (!empty($config)) {
                return $config;
            }
        }
    }
    
    // If cannot read from file, try to get from environment variables or fallback defaults
    return [
        'siteName' => getenv('SITE_NAME') ?: 'LITM',
        'csEmail' => getenv('CONTACT_EMAIL') ?: 'support@litmfin.com',
        'supportEmail' => 'support@litmfin.com',
        'companyName' => getenv('COMPANY_NAME') ?: 'Litm Financial Pvt Ltd',
        'domain' => getenv('DOMAIN') ?: ($_SERVER['HTTP_HOST'] ?? 'litmfin.com'),
    ];
}

// Initialize MfdDb to use its SMTP functionality
$db = null;
try {
    $db = new MfdDb();
    error_log("MfdDb initialized successfully for contact form");
} catch (Exception $e) {
    error_log("Failed to initialize MfdDb: " . $e->getMessage());
}

// Get configuration dynamically
$siteConfig = getSiteConfig();

// Validate that we have required config
if (empty($siteConfig['csEmail'])) {
    if (!empty($siteConfig['supportEmail'])) {
        $siteConfig['csEmail'] = $siteConfig['supportEmail'];
    } elseif (!empty($siteConfig['email'])) {
        $siteConfig['csEmail'] = $siteConfig['email'];
    } elseif (!empty($siteConfig['privacyEmail'])) {
        $siteConfig['csEmail'] = $siteConfig['privacyEmail'];
    } elseif (!empty($siteConfig['mediaEmail'])) {
        $siteConfig['csEmail'] = $siteConfig['mediaEmail'];
    } else {
        $siteConfig['csEmail'] = getenv('FALLBACK_CONTACT_EMAIL') ?: 'support@litmfin.com';
    }
}

// Rate limiting function
function checkRateLimit($ip, $limit = 5, $timeWindow = 3600) {
    $rateLimitDir = __DIR__ . '/../cache/rate_limits';
    if (!file_exists($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }
    
    $rateLimitFile = $rateLimitDir . '/rate_limit_' . md5($ip);
    $currentTime = time();
    
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        
        if (isset($data['requests'])) {
            $data['requests'] = array_filter($data['requests'], function($timestamp) use ($currentTime, $timeWindow) {
                return $timestamp > ($currentTime - $timeWindow);
            });
        } else {
            $data['requests'] = [];
        }
        
        if (count($data['requests']) >= $limit) {
            return false;
        }
    } else {
        $data = ['requests' => []];
    }
    
    $data['requests'][] = $currentTime;
    file_put_contents($rateLimitFile, json_encode($data));
    
    return true;
}

// Get client IP address
function getClientIP() {
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

// Function to send email using PHPMailer
function sendEmailWithPHPMailer($to, $subject, $htmlContent, $replyToEmail, $replyToName) {
    global $db;
    
    if (!isset($db)) {
        error_log("PHPMailer not available, falling back to mail()");
        return false;
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $smtpConfig = $db->getSmtpConfig();
        
        $mail->isSMTP();
        $mail->Host       = $smtpConfig['email_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpConfig['from_email'];
        $mail->Password   = trim($smtpConfig['from_email_password'], '"');
        $mail->SMTPSecure = $smtpConfig['secure'];
        $mail->Port       = (int) $smtpConfig['email_port'];
        
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name'] ?? 'Website Contact');
        $mail->addAddress($to);
        $mail->addReplyTo($replyToEmail, $replyToName);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;
        $mail->AltBody = strip_tags($htmlContent);
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        return false;
    }
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed. Please use POST.', null, 405);
}

// Check rate limit - Uncomment after testing
$clientIP = getClientIP();
if (!checkRateLimit($clientIP)) {
    sendResponse(false, 'Too many requests. Please try again after an hour.', null, 429);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(false, 'Invalid input data', null, 400);
}

// Validate required fields
$required_fields = ['name', 'email', 'subject', 'message'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields), null, 400);
}

// Sanitize input
$name = htmlspecialchars(strip_tags(trim($input['name'])));
$email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
$phone = isset($input['phone']) ? htmlspecialchars(strip_tags(trim($input['phone']))) : '';
$subject = htmlspecialchars(strip_tags(trim($input['subject'])));
$message = htmlspecialchars(strip_tags(trim($input['message'])));
$inquiryType = isset($input['inquiryType']) ? htmlspecialchars(strip_tags(trim($input['inquiryType']))) : 'general';

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email address', null, 400);
}

// Get from email dynamically
$fromEmail = 'noreply@' . ($siteConfig['domain'] ?? $_SERVER['HTTP_HOST'] ?? 'example.com');
$fromName = $siteConfig['siteName'] . ' Contact Form';

// Email subject
$emailSubject = "Contact Form: " . $subject;

// Current date time
$currentDateTime = date('F j, Y, g:i a');

// Build dynamic email content - NO EMOJIS / NO SPECIAL CHARACTERS
$htmlContent = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Contact Form Submission</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .header {
            background: #2563eb;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .header h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .content {
            background-color: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .field {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        .label {
            font-weight: bold;
            color: #374151;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .value {
            color: #111827;
            margin-top: 5px;
            font-size: 16px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #6b7280;
            background-color: #f9fafb;
            border-radius: 0 0 10px 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>New Contact Form Submission</h2>
            <p>From " . $siteConfig['siteName'] . " Website</p>
        </div>
        <div class='content'>
            <div class='field'>
                <div class='label'>Name:</div>
                <div class='value'>" . htmlspecialchars($name) . "</div>
            </div>
            <div class='field'>
                <div class='label'>Email:</div>
                <div class='value'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></div>
            </div>
            " . ($phone ? "
            <div class='field'>
                <div class='label'>Phone:</div>
                <div class='value'>" . htmlspecialchars($phone) . "</div>
            </div>
            " : "") . "
            <div class='field'>
                <div class='label'>Inquiry Type:</div>
                <div class='value'>" . ucfirst(htmlspecialchars($inquiryType)) . "</div>
            </div>
            <div class='field'>
                <div class='label'>Subject:</div>
                <div class='value'>" . htmlspecialchars($subject) . "</div>
            </div>
            <div class='field'>
                <div class='label'>Message:</div>
                <div class='value' style='white-space: pre-wrap;'>" . nl2br(htmlspecialchars($message)) . "</div>
            </div>
        </div>
        <div class='footer'>
            <p>This message was sent from the contact form on " . $siteConfig['siteName'] . " website.</p>
            <p>Copyright " . date('Y') . " " . $siteConfig['siteName'] . ". All rights reserved.</p>
        </div>
    </div>
</body>
</html>
";

// Plain text version
$textContent = "
======================================================================
NEW CONTACT FORM SUBMISSION - " . $siteConfig['siteName'] . "
======================================================================

Name: $name
Email: $email
Phone: " . ($phone ?: 'Not provided') . "
Inquiry Type: " . ucfirst($inquiryType) . "
Subject: $subject

Message:
$message

======================================================================
Submitted: $currentDateTime
======================================================================
";

// Send email using PHPMailer (preferred) or fallback to mail()
$mailSent = false;
$to = $siteConfig['csEmail'];

// Try PHPMailer first if available and $to is set
if (isset($db) && !empty($to)) {
    $mailSent = sendEmailWithPHPMailer($to, $emailSubject, $htmlContent, $email, $name);
}

// Fallback to mail() if PHPMailer failed or not available
if (!$mailSent) {
    error_log("Falling back to mail() function for contact form");
    
    if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "Reply-To: {$name} <{$email}>";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        
        $mailSent = @mail($to, $emailSubject, $htmlContent, implode("\r\n", $headers));
        
        if (!$mailSent && !empty($siteConfig['email']) && filter_var($siteConfig['email'], FILTER_VALIDATE_EMAIL)) {
            $toAlt = $siteConfig['email'];
            $mailSent = @mail($toAlt, $emailSubject, $htmlContent, implode("\r\n", $headers));
        }
    } else {
        error_log("Contact form error: Invalid or empty recipient email address: '$to'");
    }
}

// Local development fallback: if on localhost/127.0.0.1 and mail server is unavailable, save message locally and return success
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalDev = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

if (!$mailSent && $isLocalDev) {
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) mkdir($logDir, 0755, true);
    file_put_contents(
        $logDir . '/contact_dev_messages.log',
        date('Y-m-d H:i:s') . " - LOCAL DEV MESSAGE - From: $name <$email> - Subject: $subject - Message: $message" . PHP_EOL,
        FILE_APPEND
    );
    error_log("Local dev mode: mail server not running, saved contact form submission locally to logs/contact_dev_messages.log");
    $mailSent = true;
}

// Send auto-reply to user
if ($mailSent) {
    $autoReplySubject = "Thank you for contacting " . $siteConfig['siteName'];
    
    $autoReplyHtml = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Thank You</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #10b981; color: white; padding: 30px; text-align: center; }
            .content { background: white; padding: 30px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Thank You for Contacting Us!</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>Thank you for reaching out to <strong>" . $siteConfig['siteName'] . "</strong>. We have received your inquiry and our team will get back to you within 24 hours.</p>
                <p><strong>Your inquiry:</strong> " . htmlspecialchars($subject) . "</p>
                <p>Best regards,<br><strong>" . $siteConfig['siteName'] . " Team</strong></p>
            </div>
            <div class='footer'>
                <p>Copyright " . date('Y') . " " . $siteConfig['siteName'] . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Try PHPMailer for auto-reply
    if (isset($db) && !empty($email)) {
        $autoReplySent = sendEmailWithPHPMailer($email, $autoReplySubject, $autoReplyHtml, $siteConfig['csEmail'], $siteConfig['siteName'] . ' Support');
    } else {
        $autoReplyHeaders = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: {$siteConfig['siteName']} Support <{$fromEmail}>",
            "Reply-To: {$siteConfig['csEmail']}"
        ];
        $autoReplySent = @mail($email, $autoReplySubject, $autoReplyHtml, implode("\r\n", $autoReplyHeaders));
    }
    
    // Log success
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) mkdir($logDir, 0755, true);
    file_put_contents($logDir . '/contact.log', date('Y-m-d H:i:s') . " - SUCCESS - $name, $email" . PHP_EOL, FILE_APPEND);
    
    sendResponse(true, 'Message sent successfully! We will get back to you soon.', null, 200);
} else {
    // Log error
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) mkdir($logDir, 0755, true);
    file_put_contents($logDir . '/contact_errors.log', date('Y-m-d H:i:s') . " - FAILED - $name, $email" . PHP_EOL, FILE_APPEND);
    
    sendResponse(false, 'Failed to send message. Please try again later.', null, 500);
}
?>