
<?php
// cancelled-cheque-upload.php

require_once __DIR__ . '/includes/cors.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Start debugging log
$debugLog = [];
$startTime = microtime(true);

function addDebugLog($message, $data = null) {
    global $debugLog;
    $timestamp = date('Y-m-d H:i:s.v');
    $logEntry = [
        'timestamp' => $timestamp,
        'message' => $message
    ];
    if ($data !== null) {
        $logEntry['data'] = $data;
    }
    $debugLog[] = $logEntry;
    //error_log("DEBUG [$timestamp]: $message");
}

addDebugLog('Script started - cancelled-cheque-upload.php');

/**
 * Determine Upload path - Modified to use custom path
 */
function getUploadPath() {
    addDebugLog('Getting upload path');
    
    // Use your custom path
    $uploadPath = __DIR__ . '/../uploads/cancelled_cheque/';
    addDebugLog('Using custom upload path', $uploadPath);
    
    // Try to create the directory if it doesn't exist
    if (!is_dir($uploadPath)) {
        addDebugLog('Creating upload directory', $uploadPath);
        if (!mkdir($uploadPath, 0755, true)) {
            $error = error_get_last();
            addDebugLog('Failed to create upload directory', $error);
            throw new Exception('Failed to create upload directory: ' . $uploadPath . ' - Error: ' . ($error['message'] ?? 'Unknown error'));
        }
        addDebugLog('Directory created successfully');
    } else {
        addDebugLog('Upload directory already exists');
    }
    
    // Check if directory is writable
    if (!is_writable($uploadPath)) {
        addDebugLog('Directory is not writable', $uploadPath);
        // Try to change permissions
        if (!chmod($uploadPath, 0755)) {
            throw new Exception('Directory is not writable and cannot change permissions: ' . $uploadPath);
        }
        addDebugLog('Directory permissions updated to 0755');
    }
    
    addDebugLog('Directory is writable');
    
    $finalPath = rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR;
    addDebugLog('Final upload path determined', $finalPath);
    
    return $finalPath;
}

// Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['tif', 'tiff']);
define('ALLOWED_MIME_TYPES', [
    'image/tiff',
    'image/tif',
    'application/tiff',
    'application/tif'
]);

addDebugLog('Configuration loaded', [
    'MAX_FILE_SIZE' => MAX_FILE_SIZE,
    'ALLOWED_EXTENSIONS' => ALLOWED_EXTENSIONS,
    'ALLOWED_MIME_TYPES' => ALLOWED_MIME_TYPES
]);

// Define storage path
$uploadPath = getUploadPath();
$storagePath = $uploadPath; // Directly use the upload path

// Log the storage path for debugging
addDebugLog('Storage path constructed', [
    'uploadPath' => $uploadPath,
    'storagePath' => $storagePath,
    'directory_exists' => is_dir($storagePath) ? 'Yes' : 'No',
    'directory_writable' => is_writable($storagePath) ? 'Yes' : 'No',
    'absolute_path' => realpath($storagePath)
]);

/**
 * Get user ID from JWT token
 */
function getUserIdFromToken() {
    addDebugLog('Getting user ID from JWT token');
    $headers = getallheaders();
    addDebugLog('HTTP Headers received', $headers);
    
    $authHeader = $headers['Authorization'] ?? '';
    addDebugLog('Authorization header', $authHeader ? 'Present' : 'Missing');
    
    if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
        addDebugLog('Authorization header validation failed', [
            'empty' => empty($authHeader),
            'starts_with_bearer' => str_starts_with($authHeader, 'Bearer ')
        ]);
        return null;
    }
    
    $token = str_replace('Bearer ', '', $authHeader);
    addDebugLog('Token extracted', substr($token, 0, 20) . '...');
    
    try {
        // Decode JWT to get user ID
        $parts = explode('.', $token);
        addDebugLog('Token parts count', count($parts));
        
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $parts[1]))), true);
            addDebugLog('JWT payload decoded', $payload);
            
            $userId = $payload['user_id'] ?? null;
            addDebugLog('User ID extracted from token', $userId);
            
            return $userId;
        } else {
            addDebugLog('Invalid JWT format - expected 3 parts');
        }
        return null;
    } catch (Exception $e) {
        addDebugLog('Token validation error', $e->getMessage());
        return null;
    }
}

/**
 * Validate input according to NSE requirements
 */
function validateChequeInput($data, $db, $userId) {
    addDebugLog('Validating cheque input');
    $errors = [];
    
    // Get user bank details from database
    addDebugLog('Getting bank details for user ID', $userId);
    $bankDetails = $db->getBankAccountByUserId($userId);
    addDebugLog('Bank details retrieved', $bankDetails ? 'Found' : 'Not found');
    
    if (!$bankDetails) {
        $errors[] = 'Bank account details not found. Please complete bank account setup first.';
        addDebugLog('Validation error - Bank details not found');
        return $errors;
    }
    
    // Validate file_name (max 40 chars)
    $fileName = $data['file_name'] ?? '';
    addDebugLog('Validating file name', $fileName);
    
    if (empty($fileName)) {
        $errors[] = 'File name is required';
        addDebugLog('Validation error - File name empty');
    } elseif (strlen($fileName) > 40) {
        $errors[] = 'File name must be 40 characters or less';
        addDebugLog('Validation error - File name too long', strlen($fileName));
    } else {
        // Validate file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        addDebugLog('File extension detected', $fileExtension);
        
        if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
            $errors[] = 'Only TIF/TIFF files are allowed. Found: ' . $fileExtension;
            addDebugLog('Validation error - Invalid file extension');
        }
    }
    
    // Validate file_data
    $fileData = $data['file_data'] ?? '';
    addDebugLog('Validating file data', [
        'has_data' => !empty($fileData),
        'data_length' => strlen($fileData),
        'first_50_chars' => substr($fileData, 0, 50) . '...'
    ]);
    
    if (empty($fileData)) {
        $errors[] = 'File data is required';
        addDebugLog('Validation error - File data empty');
    } elseif (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $fileData)) {
        $errors[] = 'Invalid Base64 file data';
        addDebugLog('Validation error - Invalid Base64 format');
    }
    
    // Validate bank details from database
    addDebugLog('Validating bank details from database', [
        'accountNo' => $bankDetails['accountNo'] ?? 'Empty',
        'ifscCode' => $bankDetails['ifscCode'] ?? 'Empty',
        'accountType' => $bankDetails['accountType'] ?? 'Empty'
    ]);
    
    if (empty($bankDetails['accountNo'])) {
        $errors[] = 'Bank account number not found in your profile';
        addDebugLog('Validation error - Account number missing');
    }
    
    if (empty($bankDetails['ifscCode'])) {
        $errors[] = 'IFSC code not found in your profile';
        addDebugLog('Validation error - IFSC code missing');
    }
    
    if (empty($bankDetails['accountType'])) {
        $errors[] = 'Account type not found in your profile';
        addDebugLog('Validation error - Account type missing');
    }
    
    addDebugLog('Validation completed', [
        'total_errors' => count($errors),
        'errors' => $errors
    ]);
    
    return $errors;
}

/**
 * Get user bank details for cheque upload
 */
function getUserBankDetailsForCheque($db, $userId) {
    addDebugLog('Getting user bank details for cheque upload', ['user_id' => $userId]);
    
    // Get user's client code
    addDebugLog('Fetching user UCC details');
    $userUcc = $db->getUserUccById($userId);
    addDebugLog('User UCC details retrieved', $userUcc);
    
    if (empty($userUcc)) {
        addDebugLog('Error - Client code not found for user');
        throw new Exception('Client code not found for user');
    }
    
    // Get bank account details
    addDebugLog('Fetching bank account details');
    $bankDetails = $db->getBankAccountByUserId($userId);
    addDebugLog('Bank account details retrieved', $bankDetails);
    
    if (!$bankDetails) {
        addDebugLog('Error - Bank account details not found');
        throw new Exception('Bank account details not found. Please complete bank account setup.');
    }
    
    $result = [
        'client_code' => $userUcc,
        'account_no' => $bankDetails['accountNo'] ?? '',
        'ifsc' => $bankDetails['ifscCode'] ?? '',
        'account_type' => $bankDetails['accountType'] ?? '',
        'account_holder_name' => $bankDetails['accountHolderName'] ?? ''
    ];
    
    addDebugLog('Bank details prepared for cheque upload', $result);
    
    return $result;
}

/**
 * Save file locally to uploads directory
 */
function saveFileLocally($base64Data, $fileName, $clientCode, $accountNo) {
    global $storagePath;
    
    addDebugLog('Starting local file save', [
        'file_name' => $fileName,
        'client_code' => $clientCode,
        'account_no' => $accountNo,
        'storage_path' => $storagePath
    ]);
    
    // Decode base64 data
    addDebugLog('Decoding Base64 file data');
    $fileData = base64_decode($base64Data, true);
    if ($fileData === false) {
        addDebugLog('Base64 decode failed');
        throw new Exception('Invalid base64 file data');
    }
    addDebugLog('Base64 decode successful');
    
    // Check file size
    $fileSize = strlen($fileData);
    addDebugLog('File size calculated', [
        'file_size' => $fileSize,
        'max_allowed' => MAX_FILE_SIZE,
        'within_limit' => $fileSize <= MAX_FILE_SIZE
    ]);
    
    if ($fileSize > MAX_FILE_SIZE) {
        addDebugLog('File size exceeds limit');
        throw new Exception('File size exceeds maximum allowed size of 5MB. Actual size: ' . formatBytes($fileSize));
    }
    
    // Validate file extension
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    addDebugLog('File extension validation', [
        'extension' => $fileExtension,
        'allowed_extensions' => ALLOWED_EXTENSIONS,
        'is_allowed' => in_array($fileExtension, ALLOWED_EXTENSIONS)
    ]);
    
    if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
        addDebugLog('Invalid file extension');
        throw new Exception('File must have .tif or .tiff extension. Found: ' . $fileExtension);
    }
    
    // Generate unique filename
    $timestamp = date('Ymd_His');
    $safeClientCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $clientCode);
    $safeAccountNo = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($accountNo, -4));
    
    // Ensure proper extension
    $finalExtension = $fileExtension === 'tiff' ? 'tiff' : 'tif';
    
    $uniqueFileName = "cheque_{$safeClientCode}_{$safeAccountNo}_{$timestamp}.{$finalExtension}";
    $filePath = $storagePath . $uniqueFileName;
    
    addDebugLog('Generated unique filename', [
        'timestamp' => $timestamp,
        'safe_client_code' => $safeClientCode,
        'safe_account_no' => $safeAccountNo,
        'final_extension' => $finalExtension,
        'unique_file_name' => $uniqueFileName,
        'file_path' => $filePath
    ]);
    
    // Save file
    addDebugLog('Writing file to disk');
    $bytesWritten = file_put_contents($filePath, $fileData);
    addDebugLog('File write operation completed', [
        'bytes_written' => $bytesWritten,
        'success' => $bytesWritten !== false && $bytesWritten > 0
    ]);
    
    if ($bytesWritten === false || $bytesWritten === 0) {
        $error = error_get_last();
        addDebugLog('File write failed', $error);
        throw new Exception('Failed to save file to: ' . $filePath . ' - Error: ' . ($error['message'] ?? 'Unknown error'));
    }
    
    addDebugLog('File saved successfully');
    
    // Verify file was saved
    if (!file_exists($filePath)) {
        addDebugLog('File verification failed - file does not exist');
        throw new Exception('File was not saved. File does not exist at: ' . $filePath);
    }
    addDebugLog('File exists at path');
    
    $actualFileSize = filesize($filePath);
    addDebugLog('Actual file size on disk', $actualFileSize);
    
    if ($actualFileSize === 0) {
        addDebugLog('File verification failed - file is empty (0 bytes)');
        throw new Exception('File was saved but is empty (0 bytes). Path: ' . $filePath);
    }
    
    addDebugLog('File verification successful');
    
    // Set permissions
    chmod($filePath, 0644);
    addDebugLog('File permissions set to 0644');
    
    $result = [
        'file_path' => $filePath,
        'file_name' => $uniqueFileName,
        'original_name' => $fileName,
        'file_size' => $fileSize,
        'actual_file_size' => $actualFileSize,
        'upload_time' => $timestamp,
        'client_code' => $clientCode,
        'account_no' => $accountNo,
        'storage_path' => $storagePath
    ];
    
    addDebugLog('Local file save completed successfully', $result);
    
    return $result;
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Log upload activity
 */
function logUploadActivity($clientCode, $accountNo, $fileName, $status, $nseResponse = null, $localFileInfo = null) {
    global $storagePath;
    
    addDebugLog('Logging upload activity', [
        'client_code' => $clientCode,
        'account_no' => $accountNo,
        'file_name' => $fileName,
        'status' => $status,
        'nse_response' => $nseResponse !== null ? 'Present' : 'Null',
        'local_file_info' => $localFileInfo !== null ? 'Present' : 'Null'
    ]);
    
    $logFile = $storagePath . 'upload_log.csv';
    addDebugLog('Log file path', $logFile);
    
    // Create log file if it doesn't exist
    if (!file_exists($logFile)) {
        addDebugLog('Creating new log file');
        $header = "Timestamp,ClientCode,AccountNo,FileName,Status,NSE_Status,NSE_Message,LocalFilePath,FileSize,StoragePath\n";
        $writeResult = file_put_contents($logFile, $header);
        addDebugLog('Log file header written', ['success' => $writeResult !== false]);
    } else {
        addDebugLog('Log file already exists');
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $nseStatus = $nseResponse['status'] ?? 'N/A';
    $nseMessage = isset($nseResponse['message']) ? '"' . str_replace('"', '""', $nseResponse['message']) . '"' : 'N/A';
    $localPath = $localFileInfo['file_path'] ?? 'N/A';
    $fileSize = $localFileInfo['file_size'] ?? '0';
    
    $logEntry = "{$timestamp},{$clientCode},{$accountNo},\"{$fileName}\",{$status},{$nseStatus},{$nseMessage},{$localPath},{$fileSize},{$storagePath}\n";
    
    addDebugLog('Writing log entry', ['entry_length' => strlen($logEntry)]);
    $writeResult = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    addDebugLog('Log entry written', ['success' => $writeResult !== false]);
}

// Main execution
try {
    addDebugLog('=== MAIN EXECUTION START ===');
    addDebugLog('Request method', $_SERVER['REQUEST_METHOD']);
    addDebugLog('Request URI', $_SERVER['REQUEST_URI'] ?? 'N/A');
    addDebugLog('Request time', date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()));
    
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        addDebugLog('Invalid request method - rejecting');
        sendResponse(false, 'Method not allowed. Use POST.', null, 405);
    }
    addDebugLog('Request method validation passed');
    
    // Get user ID from token
    addDebugLog('Attempting to get user ID from token');
    $userId = getUserIdFromToken();
    addDebugLog('User ID retrieved', $userId);
    
    if (!$userId) {
        addDebugLog('User ID not found - authentication required');
        sendResponse(false, 'Authentication required. Please login first.', null, 401);
    }
    addDebugLog('User authentication successful', ['user_id' => $userId]);
    
    // Get JSON input
    addDebugLog('Reading JSON input from php://input');
    $rawInput = file_get_contents('php://input');
    addDebugLog('Raw input received', [
        'input_length' => strlen($rawInput),
        'first_200_chars' => substr($rawInput, 0, 200) . (strlen($rawInput) > 200 ? '...' : '')
    ]);
    
    $input = json_decode($rawInput, true);
    addDebugLog('JSON decode result', [
        'success' => $input !== null,
        'input_type' => gettype($input),
        'input_keys' => $input !== null ? array_keys($input) : 'N/A'
    ]);
    
    if (!$input || !is_array($input)) {
        addDebugLog('Invalid JSON input format');
        sendResponse(false, 'Invalid JSON input format', null, 400);
    }
    addDebugLog('JSON input validation passed');
    
    // Create database instance
    addDebugLog('Creating database instance');
    $db = new MfdDb();
    addDebugLog('Database instance created');
    
    // Get user bank details from database
    addDebugLog('Fetching user bank details from database');
    $userBankDetails = getUserBankDetailsForCheque($db, $userId);
    addDebugLog('User bank details retrieved', $userBankDetails);
    
    // Log received request
    //error_log('Cancelled Cheque Upload Request - Client: ' . $userBankDetails['client_code']);
    addDebugLog('Request logged for client', $userBankDetails['client_code']);
    
    // Validate input (only file-related validation, bank details come from DB)
    addDebugLog('Starting input validation');
    $validationErrors = validateChequeInput($input, $db, $userId);
    if (!empty($validationErrors)) {
        addDebugLog('Input validation failed', $validationErrors);
        sendResponse(false, 'Validation failed: ' . implode(', ', $validationErrors), null, 400);
    }
    addDebugLog('Input validation passed');
    
    // Clean and format data
    $clientCode = strtoupper(trim($userBankDetails['client_code']));
    $accountNo = trim($userBankDetails['account_no']);
    $ifsc = strtoupper(trim($userBankDetails['ifsc']));
    $accountType = $userBankDetails['account_type'];
    $accountHolderName = $userBankDetails['account_holder_name'] ?? '';
    $fileName = $input['file_name'];
    $fileBase64 = $input['file_data'];
    
    addDebugLog('Data prepared for processing', [
        'client_code' => $clientCode,
        'account_no' => $accountNo,
        'ifsc' => $ifsc,
        'account_type' => $accountType,
        'account_holder_name' => $accountHolderName,
        'file_name' => $fileName,
        'file_data_length' => strlen($fileBase64),
        'file_data_preview' => substr($fileBase64, 0, 50) . '...'
    ]);
    
    // Step 1: Save file locally to uploads directory
    addDebugLog('=== STEP 1: LOCAL FILE SAVE ===');
    $localFileInfo = null;
    try {
        addDebugLog('Attempting to save file locally');
        $localFileInfo = saveFileLocally($fileBase64, $fileName, $clientCode, $accountNo);
        addDebugLog('Local file save successful', [
            'file_path' => $localFileInfo['file_path'],
            'file_name' => $localFileInfo['file_name']
        ]);
    } catch (Exception $e) {
        addDebugLog('Local file save failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        //error_log('Local file save failed: ' . $e->getMessage());
        // Continue even if local save fails
    }
    
    // Step 2: Upload to NSE API
    addDebugLog('=== STEP 2: NSE API UPLOAD ===');
    $nseResponse = null;
    try {
        // Include NSE API connection
        addDebugLog('Checking for NSE API connection file');
        if (file_exists(__DIR__ . '/nseapiconnect.php')) {
            addDebugLog('nseapiconnect.php found - including file');
            require_once __DIR__ . '/nseapiconnect.php';
            $nseApiConnect = new NseApiConnect();
            addDebugLog('NseApiConnect instance created');
            
            // Prepare payload in exact NSE format
            $payload = [
                'file_name' => $fileName,
                'client_code' => $clientCode,
                'account_no' => $accountNo,
                'ifsc' => $ifsc,
                'account_type' => $accountType,
                'file_data' => $fileBase64
            ];
            
            addDebugLog('Preparing NSE API payload', [
                'payload_keys' => array_keys($payload),
                'file_name' => $payload['file_name'],
                'client_code' => $payload['client_code'],
                'account_no' => $payload['account_no'],
                'ifsc' => $payload['ifsc'],
                'account_type' => $payload['account_type'],
                'file_data_length' => strlen($payload['file_data'])
            ]);
            
            addDebugLog('Calling NSE API uploadCancelledCheque');
            $apiResult = $nseApiConnect->uploadCancelledCheque($payload);
            addDebugLog('NSE API call completed', [
                'api_result_type' => gettype($apiResult),
                'has_json' => isset($apiResult['json'])
            ]);
            
            $nseResponse = $apiResult['json'] ?? null;
            addDebugLog('NSE response received', $nseResponse);
            
            if ($nseResponse) {
                addDebugLog('NSE API status', [
                    'status' => $nseResponse['status'] ?? 'No status',
                    'message' => $nseResponse['message'] ?? 'No message'
                ]);
                
                // Log the exact NSE response format
                //error_log('NSE Response: ' . json_encode($nseResponse));
            }
        } else {
            addDebugLog('nseapiconnect.php not found, skipping NSE upload');
            //error_log('nseapiconnect.php not found, skipping NSE upload');
        }
    } catch (Exception $e) {
        addDebugLog('NSE API error occurred', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        error_log('NSE API error: ' . $e->getMessage());
        // Continue even if NSE API fails
    }
    
    // Step 3: Log the activity
    addDebugLog('=== STEP 3: ACTIVITY LOGGING ===');
    $uploadStatus = ($nseResponse['status'] ?? '') === '100' ? 'SUCCESS' : 'PARTIAL_SUCCESS';
    addDebugLog('Determined upload status', $uploadStatus);
    
    logUploadActivity($clientCode, $accountNo, $fileName, $uploadStatus, $nseResponse, $localFileInfo);
    addDebugLog('Activity logging completed');
    
    // Step 4: Prepare response
    addDebugLog('=== STEP 4: PREPARING RESPONSE ===');
    $responseData = [
        'upload_details' => [
            'client_code' => $clientCode,
            'account_no' => $accountNo,
            'account_holder_name' => $accountHolderName,
            'ifsc' => $ifsc,
            'account_type' => $accountType,
            'upload_time' => date('Y-m-d H:i:s')
        ],
        'local_storage' => $localFileInfo ? [
            'success' => true,
            'file_name' => $localFileInfo['file_name'],
            'original_name' => $localFileInfo['original_name'],
            'file_size' => formatBytes($localFileInfo['file_size']),
            'file_path' => $localFileInfo['file_path'],
            'storage_location' => 'uploads/cancelled_cheque/',
            'absolute_path' => realpath($localFileInfo['file_path']),
            'message' => 'File saved to uploads directory successfully'
        ] : [
            'success' => false,
            'message' => 'File not saved locally'
        ],
        'nse_upload' => $nseResponse ? [
            'success' => ($nseResponse['status'] ?? '') === '100',
            'status' => $nseResponse['status'] ?? 'N/A',
            'message' => $nseResponse['message'] ?? 'No response from NSE',
            'response_data' => $nseResponse
        ] : [
            'success' => false,
            'message' => 'NSE upload not attempted'
        ],
        'debug_info' => [
            'script_execution_time' => round(microtime(true) - $startTime, 3) . ' seconds',
            'memory_usage' => formatBytes(memory_get_usage()),
            'peak_memory_usage' => formatBytes(memory_get_peak_usage()),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'debug_log_count' => count($debugLog)
        ]
    ];
    
    addDebugLog('Response data prepared', [
        'upload_details_keys' => array_keys($responseData['upload_details']),
        'local_storage_success' => $responseData['local_storage']['success'],
        'nse_upload_success' => $responseData['nse_upload']['success']
    ]);
    
    // Determine overall success
    $overallSuccess = ($localFileInfo !== null) || ($nseResponse && ($nseResponse['status'] ?? '') === '100');
    addDebugLog('Overall success determined', [
        'overall_success' => $overallSuccess,
        'local_file_info_exists' => $localFileInfo !== null,
        'nse_response_exists' => $nseResponse !== null,
        'nse_status_100' => ($nseResponse['status'] ?? '') === '100'
    ]);
    
    $message = 'Upload processed. ';
    $message .= $localFileInfo ? 'File saved to uploads directory. ' : 'Local save failed. ';
    $message .= $nseResponse ? 'NSE: ' . ($nseResponse['message'] ?? 'No message') : 'NSE not attempted.';
    
    addDebugLog('Final message prepared', $message);
    
    // Add debug log to response in development environment
    if (isset($_SERVER['HTTP_DEBUG']) && $_SERVER['HTTP_DEBUG'] === 'true') {
        $responseData['debug_log'] = $debugLog;
    }
    
    addDebugLog('=== MAIN EXECUTION COMPLETED ===');
    sendResponse($overallSuccess, $message, $responseData, $overallSuccess ? 200 : 207);
    
} catch (Exception $e) {
    addDebugLog('=== UNHANDLED EXCEPTION ===', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    error_log('Cancelled cheque upload error: ' . $e->getMessage());
    
    // Log the error
    if (isset($clientCode, $accountNo, $fileName)) {
        logUploadActivity($clientCode ?? 'UNKNOWN', $accountNo ?? 'UNKNOWN', 
                         $fileName ?? 'UNKNOWN', 'ERROR', null, null);
    }
    
    // Add debug info to error response
    $errorData = [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    // Add debug log to error response in development environment
    if (isset($_SERVER['HTTP_DEBUG']) && $_SERVER['HTTP_DEBUG'] === 'true') {
        $errorData['debug_log'] = $debugLog;
    }
    
    sendResponse(false, 'Internal server error: ' . $e->getMessage(), $errorData, 500);
}

// Function to add final debug log at script end
register_shutdown_function(function() {
    global $debugLog, $startTime;
    $executionTime = round(microtime(true) - $startTime, 3);
    addDebugLog('Script shutdown - total execution time: ' . $executionTime . ' seconds');
    
    // Log all debug messages to a separate file if needed
    $debugLogFile = __DIR__ . '/../logs/cancelled_cheque_debug_' . date('Y-m-d') . '.log';
    foreach ($debugLog as $logEntry) {
        $logLine = "[" . $logEntry['timestamp'] . "] " . $logEntry['message'];
        if (isset($logEntry['data'])) {
            $logLine .= " | " . json_encode($logEntry['data']);
        }
        @file_put_contents($debugLogFile, $logLine . PHP_EOL, FILE_APPEND);
    }
});
?>