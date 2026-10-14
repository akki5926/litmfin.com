<?php
require_once __DIR__ . '/includes/cors.php';

function formatRiskName(string $riskId): string {
    $names = [
        'high' => 'High Risk',
        'medium' => 'Medium Risk', 
        'low' => 'Low Risk',
        'conservative' => 'Conservative',
        '1' => 'High Risk',
        '2' => 'Medium Risk',
        '3' => 'Low Risk', 
        '4' => 'Conservative'
    ];
    return $names[$riskId] ?? ucfirst($riskId);
}

// === CONFIGURATION ===
$DEVELOPMENT_MODE = true; // Set to false in production

// Get user ID from various sources
$userId = null;

// 1. From query parameter (easiest for testing)
if (isset($_GET['userId'])) {
    $userId = trim($_GET['userId']);
}

// 2. From header
if (!$userId && isset($_SERVER['HTTP_X_USER_ID'])) {
    $userId = trim($_SERVER['HTTP_X_USER_ID']);
}

// 3. From POST JSON data
if (!$userId && ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT')) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['userId'])) {
        $userId = trim($input['userId']);
    }
}

// Development mode fallback
if (empty($userId) && $DEVELOPMENT_MODE) {
    $userId = 'test_user_' . time();
}

// Final validation
if (empty($userId)) {
    sendResponse(false, 'User ID is required. Provide it via: ?userId=your_id, X-User-ID header, or in POST JSON.', [
        'example_requests' => [
            'GET' => '/api/risk_profile_api.php?userId=john_doe',
            'POST' => '{"userId": "john_doe", "riskProfileId": "medium"}',
            'Header' => 'X-User-ID: john_doe'
        ]
    ], 401);
    exit;
}

// Validate database connection
if (!isset($db) || !is_object($db)) {
    sendResponse(false, 'Database connection not available', null, 500);
    exit;
}

// Handle the request
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($db, $userId);
            break;
            
        case 'POST':
            handlePostRequest($db, $userId);
            break;
            
        case 'OPTIONS':
            sendResponse(true, 'OK', null, 200);
            break;
            
        default:
            sendResponse(false, 'Method not allowed. Use GET or POST.', null, 405);
    }
} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}

/**
 * Handle GET request - Retrieve risk profile
 */
function handleGetRequest($db, string $userId): void {
    // Check if method exists
    if (!method_exists($db, 'getUserRiskProfile')) {
        sendResponse(false, 'Database method getUserRiskProfile not available', null, 500);
        return;
    }
    
    $riskProfile = $db->getUserRiskProfile($userId);
    
    if ($riskProfile === false) {
        sendResponse(false, 'Error fetching risk profile from database', null, 500);
        return;
    }
    
    // If no profile exists
    if (empty($riskProfile) || empty($riskProfile['RISK_CATEGORY_ID'])) {
        sendResponse(true, 'No risk profile found', [
            'hasRiskProfile' => false,
            'userId' => $userId,
            'availableRiskProfiles' => [
                ['id' => 'high', 'name' => 'High Risk'],
                ['id' => 'medium', 'name' => 'Medium Risk'],
                ['id' => 'low', 'name' => 'Low Risk'],
                ['id' => 'conservative', 'name' => 'Conservative']
            ]
        ]);
        return;
    }
    
    // Format the response for existing profile
    $riskCode = $riskProfile['RISK_CATEGORY_ID'];
    
    // Try to map to frontend ID if method exists
    $frontendRiskId = $riskCode;
    if (method_exists($db, 'mapRiskCodeToId')) {
        $mapped = $db->mapRiskCodeToId($riskCode);
        if (!empty($mapped)) {
            $frontendRiskId = $mapped;
        }
    }
    
    $formattedProfile = [
        'riskId' => $frontendRiskId,
        'riskCode' => $riskCode,
        'riskName' => $riskProfile['risk_name'] ?? formatRiskName($riskCode),
        'userId' => $userId,
        'originalRiskCode' => $riskCode
    ];
    
    // Include any additional fields from database
    foreach ($riskProfile as $key => $value) {
        if (!in_array($key, ['RISK_CATEGORY_ID', 'risk_name', 'risk_code'])) {
            $formattedProfile[$key] = $value;
        }
    }
    
    sendResponse(true, 'Risk profile retrieved successfully', [
        'hasRiskProfile' => true,
        'riskProfile' => $formattedProfile
    ]);
}

/**
 * Handle POST request - Save risk profile
 */
function handlePostRequest($db, string $userId): void {
    // Get and validate JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null) {
        sendResponse(false, 'Invalid JSON data. Please check your request body.', null, 400);
        return;
    }
    
    if (!isset($input['riskProfileId'])) {
        sendResponse(false, 'riskProfileId is required in request body', null, 400);
        return;
    }
    
    $riskProfileId = trim($input['riskProfileId']);
    
    // Validate risk profile ID
    $validRiskIds = ['high', 'medium', 'low', 'conservative'];
    if (!in_array($riskProfileId, $validRiskIds, true)) {
        sendResponse(false, 'Invalid riskProfileId. Must be one of: ' . implode(', ', $validRiskIds), [
            'validOptions' => $validRiskIds,
            'received' => $riskProfileId
        ], 400);
        return;
    }
    
    // Check if save method exists
    if (!method_exists($db, 'saveUserRiskProfile')) {
        sendResponse(false, 'Database method saveUserRiskProfile not available', null, 500);
        return;
    }
    
    // Check if user already has a risk profile
    if (method_exists($db, 'getUserRiskProfile')) {
        $existingProfile = $db->getUserRiskProfile($userId);
        if ($existingProfile && !empty($existingProfile['RISK_CATEGORY_ID'])) {
            $existingId = $existingProfile['RISK_CATEGORY_ID'];
            $frontendId = $existingId;
            
            if (method_exists($db, 'mapRiskCodeToId')) {
                $mapped = $db->mapRiskCodeToId($existingId);
                if (!empty($mapped)) {
                    $frontendId = $mapped;
                }
            }
            
            sendResponse(false, 'Risk profile already exists and cannot be modified', [
                'existingRiskProfile' => [
                    'riskId' => $frontendId,
                    'riskCode' => $existingId,
                    'riskName' => $existingProfile['risk_name'] ?? formatRiskName($existingId)
                ],
                'userId' => $userId,
                'message' => 'Contact support to modify your risk profile'
            ], 409);
            return;
        }
    }
    
    // Save the risk profile
    $success = $db->saveUserRiskProfile($userId, $riskProfileId);
    
    if ($success) {
        // Fetch the saved profile to return complete data
        $savedProfile = [];
        if (method_exists($db, 'getUserRiskProfile')) {
            $savedProfile = $db->getUserRiskProfile($userId) ?? [];
        }
        
        $riskCode = $savedProfile['RISK_CATEGORY_ID'] ?? $riskProfileId;
        $frontendRiskId = $riskProfileId; // Use the one we saved
        
        $resData = [
            'riskProfile' => [
                'riskId' => $frontendRiskId,
                'riskCode' => $riskCode,
                'riskName' => $savedProfile['risk_name'] ?? formatRiskName($riskProfileId),
                'userId' => $userId
            ]
        ];

        sendResponse(true, 'Risk profile saved successfully', $resData, 201);
    } else {
        sendResponse(false, 'Failed to save risk profile. Please try again.', [
            'userId' => $userId,
            'attemptedProfileId' => $riskProfileId
        ]);
    }
}