<?php
require_once('auth_guard.php');

// citypincodeapi.php - DEBUG VERSION
if(!isset($_SESSION)) { session_start(); } 

// Set JSON content type
header('Content-Type: application/json');

// Enable ALL error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display to not break JSON
ini_set('log_errors', 0);

// Create debug log file
$logsDir = __DIR__ . '/logs';
if (!file_exists($logsDir)) {
    @mkdir($logsDir, 0777, true);
}
$debugLogFile = $logsDir . '/debug_api.log';
function debugLog($message, $data = null) {
    global $debugLogFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= "\n" . print_r($data, true);
    }
    $logEntry .= "\n";
    @file_put_contents($debugLogFile, $logEntry, FILE_APPEND);
}

debugLog("=== API Request Started ===");
//debugLog("POST Data", $_POST);
//debugLog("FILES Data", $_FILES);
//debugLog("GET Data", $_GET);

require_once('database.php');

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request',
    'data' => null
];

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] != true) {
    http_response_code(401);
    $response['message'] = 'Unauthorized. Please login.';
    //debugLog("Unauthorized access attempt");
    echo json_encode($response);
    exit;
}

//debugLog("User is authenticated");

// Get action from request
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
//debugLog("Action: " . $action);

if(empty($action)) {
    $response['message'] = 'Action parameter is required';
    debugLog("No action provided");
    echo json_encode($response);
    exit;
}

try {
    $db = new MySQLDB();
    //debugLog("Database connection established");
    
    switch($action) {
        // ==================== CITY MANAGEMENT ====================
        case 'list_cities':
            $search = isset($_POST['search']) ? trim($_POST['search']) : '';
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            
            debugLog("list_cities called", [
                'search' => $search,
                'limit' => $limit,
                'offset' => $offset
            ]);
            
            $limit = min($limit, 100);
            $offset = max($offset, 0);
            
            // Check if method exists
            if(!method_exists($db, 'getCitiesList')) {
                debugLog("ERROR: Method getCitiesList does not exist in database class");
                $response['message'] = 'Method getCitiesList not found';
                break;
            }
            
            $result = $db->getCitiesList($search, $limit, $offset);
            //debugLog("getCitiesList result", $result);
            
            if($result === false) {
                $response['message'] = 'Database query failed';
                debugLog("Database query returned false");
            } else {
                $response['success'] = true;
                $response['data'] = isset($result['data']) ? $result['data'] : [];
                $response['total'] = isset($result['total']) ? $result['total'] : 0;
                //debugLog("Successfully retrieved " . count($response['data']) . " records out of " . $response['total']);
            }
            break;
            
        case 'save_city':
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $city_name = isset($_POST['city_name']) ? trim($_POST['city_name']) : '';
            $state_code = isset($_POST['state_code']) ? trim($_POST['state_code']) : '';
            $country_code = isset($_POST['country_code']) ? trim($_POST['country_code']) : '';
            $pincode = isset($_POST['pincode']) ? trim($_POST['pincode']) : '';
            
            // debugLog("save_city called", [
            //     'id' => $id,
            //     'city_name' => $city_name,
            //     'state_code' => $state_code,
            //     'country_code' => $country_code,
            //     'pincode' => $pincode
            // ]);
            
            $city_name = ucwords(strtolower($city_name));
            $state_code = strtoupper($state_code);
            $country_code = strtoupper($country_code);
            
            if(empty($city_name) || empty($state_code) || empty($country_code) || empty($pincode)) {
                $response['message'] = 'All fields are required';
                debugLog("Validation failed: missing fields");
            } elseif(!preg_match('/^\d{6}$/', $pincode)) {
                $response['message'] = 'Pincode must be 6 digits';
                debugLog("Validation failed: invalid pincode format");
            } elseif($db->saveCity($id, $city_name, $state_code, $country_code, $pincode)) {
                $response['success'] = true;
                $response['message'] = $id > 0 ? 'City updated successfully' : 'City added successfully';
                //debugLog("City saved successfully", ['id' => $id]);
            } else {
                $response['message'] = 'Failed to save city';
                debugLog("Failed to save city");
            }
            break;
            
        case 'get_city':
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            //debugLog("get_city called for ID: " . $id);
            
            if($id > 0) {
                $city = $db->getCityById($id);
                if($city) {
                    $response['success'] = true;
                    $response['data'] = $city;
                    //debugLog("City found", $city);
                } else {
                    $response['message'] = 'City not found';
                    debugLog("City not found for ID: " . $id);
                }
            } else {
                $response['message'] = 'Invalid city ID';
                debugLog("Invalid city ID: " . $id);
            }
            break;
            
        case 'delete_city':
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            //debugLog("delete_city called for ID: " . $id);
            
            if($id > 0) {
                if($db->deleteCity($id)) {
                    $response['success'] = true;
                    $response['message'] = 'City deleted successfully';
                    //debugLog("City deleted successfully");
                } else {
                    $response['message'] = 'Failed to delete city';
                    debugLog("Failed to delete city");
                }
            } else {
                $response['message'] = 'Invalid city ID';
                debugLog("Invalid city ID: " . $id);
            }
            break;
            
        case 'upload_cities':
            //debugLog("upload_cities called");
            
            if(!isset($_FILES['city_file']) || $_FILES['city_file']['error'] != UPLOAD_ERR_OK) {
                $errorMsg = 'File upload failed. Error code: ' . ($_FILES['city_file']['error'] ?? 'No file');
                $response['message'] = $errorMsg;
                debugLog($errorMsg);
                break;
            }
            
            $file = $_FILES['city_file']['tmp_name'];
            $filename = $_FILES['city_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // debugLog("File uploaded", [
            //     'filename' => $filename,
            //     'size' => $_FILES['city_file']['size'],
            //     'extension' => $ext
            // ]);
            
            if($ext != 'csv' && $ext != 'txt') {
                $response['message'] = 'Only CSV or TXT files are allowed';
                debugLog("Invalid file type");
                break;
            }
            
            if($_FILES['city_file']['size'] > 10 * 1024 * 1024) {
                $response['message'] = 'File size should not exceed 10MB';
                debugLog("File too large");
                break;
            }
            
            $handle = fopen($file, 'r');
            if(!$handle) {
                $response['message'] = 'Cannot read file';
                debugLog("Cannot read file");
                break;
            }
            
            $data = [];
            while(($row = fgetcsv($handle)) !== false) {
                if(empty(array_filter($row))) continue;
                $data[] = $row;
            }
            fclose($handle);
            
            // debugLog("File parsed", [
            //     'total_rows' => count($data),
            //     'first_row' => isset($data[0]) ? $data[0] : null
            // ]);
            
            if(count($data) < 2) {
                $response['message'] = 'File contains no data rows';
                debugLog("No data rows found");
                break;
            }
            
            $header = array_map('strtoupper', array_map('trim', $data[0]));
            $expected = ['CITY', 'STATE_CODE', 'COUNTRY_CODE', 'PINCODE'];
            $missing = array_diff($expected, $header);
            
            // debugLog("Header validation", [
            //     'header' => $header,
            //     'expected' => $expected,
            //     'missing' => $missing
            // ]);
            
            if(!empty($missing)) {
                $response['message'] = 'Missing columns: ' . implode(', ', $missing);
                debugLog("Missing columns");
                break;
            }
            
            $result = $db->bulkUploadCities($data, $header);
            $response['success'] = true;
            $response['message'] = "Upload complete: {$result['inserted']} inserted, {$result['skipped']} skipped";
            //debugLog("Bulk upload result", $result);
            break;
            
        default:
            $response['message'] = "Unknown action: $action";
            debugLog("Unknown action: " . $action);
            http_response_code(400);
            break;
    }
    
} catch(Exception $e) {
    http_response_code(200);
    $response['success'] = false;
    $response['message'] = 'Server error occurred: ' . $e->getMessage();
    debugLog("Exception caught", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

//debugLog("Response", $response);
debugLog("=== API Request Ended ===\n");

echo json_encode($response);
?>