<?php
/**
 * Media API Endpoint - Fetches from tb_media table using MfdDb
 */

// Include CORS configuration
require_once __DIR__ . '/includes/cors.php';

try {
    // cors.php already instantiated MfdDb into $db — reuse it to avoid a second DB connection.
    if (!isset($db) || !($db instanceof MfdDb)) {
        $db = new MfdDb();
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendResponse(false, 'Method not allowed. Use GET.', null, 405);
    }
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'all';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $source = isset($_GET['source']) ? $_GET['source'] : '';
    $year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
    
    // Helper function to get base URL (handles subdirectory and reverse-proxy HTTPS)
    function getBaseUrl() {
        // Bug fix: detect HTTPS even when behind a reverse proxy (Nginx/Apache SSL termination)
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL']   === 'on')
                || (!empty($_SERVER['SERVER_PORT'])             && (int)$_SERVER['SERVER_PORT']        === 443);
        $protocol = $isHttps ? 'https://' : 'http://';

        $host = $_SERVER['HTTP_HOST'];

        // Bug fix: include the subdirectory if the app is not at the web root.
        // SCRIPT_NAME e.g. "/v4/api/media_api.php" → go up 2 levels → "/v4"
        // At root: "/api/media_api.php" → dirname twice = "/" → trimmed to ""
        $scriptDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');

        return $protocol . $host . $scriptDir;
    }
    
    // Helper function to get full image URL from icon filename
    function getImageUrl($icon) {
        if (empty($icon)) {
            return null;
        }
        // If it's already a full URL
        if (filter_var($icon, FILTER_VALIDATE_URL)) {
            return $icon;
        }
        // Build full URL to the uploaded image
        $baseUrl = getBaseUrl();
        return $baseUrl . '/uploads/media/' . ltrim($icon, '/');
    }
    
    // Helper function to get emoji based on source/category
    function getDefaultIconEmoji($source, $category) {
        $source = strtolower($source);
        $category = strtolower($category);
        
        $sourceIcons = [
            'economic times' => '📰',
            'business standard' => '📊',
            'financial express' => '📈',
            'moneycontrol' => '💰',
            'livemint' => '🌿',
            'bloomberg' => '📺',
            'reuters' => '🌐',
            'cnbc' => '📺',
            'forbes' => '💼',
            'the hindu' => '📰',
            'times of india' => '📰',
            'press release' => '📢',
            'interview' => '🎤',
        ];
        
        foreach ($sourceIcons as $key => $icon) {
            if (strpos($source, $key) !== false) {
                return $icon;
            }
        }
        
        if (strpos($category, 'award') !== false) return '🏆';
        if (strpos($category, 'recognition') !== false) return '⭐';
        if (strpos($category, 'event') !== false) return '🎪';
        
        return '📰';
    }
    
    // Helper function to process a single media item
    function processMediaItem($item) {
        if (!$item) return null;
        
        return [
            'id' => $item['id'],
            'title' => $item['title'],
            'url' => $item['url'],
            'description' => $item['description'],
            'source' => $item['source'],
            'date' => $item['date'],
            'category' => $item['category'],
            'icon' => $item['icon'],
            'image_url' => getImageUrl($item['icon']),
            'default_icon' => getDefaultIconEmoji($item['source'] ?? '', $item['category'] ?? ''),
            'formatted_date' => !empty($item['date']) && $item['date'] != '0000-00-00' ? date('d M Y', strtotime($item['date'])) : null,
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at']
        ];
    }
    
    // Helper function to process multiple media items
    function processMediaItems($items) {
        if (empty($items) || !is_array($items)) {
            return [];
        }
        $processed = [];
        foreach ($items as $item) {
            $processed[] = processMediaItem($item);
        }
        return $processed;
    }
    
    $responseData = [];
    
    switch ($action) {
        case 'single':
            if ($id <= 0) {
                sendResponse(false, 'Valid ID parameter is required', null, 400);
            }
            $item = $db->getMediaItemById($id);
            if (!$item) {
                sendResponse(false, 'Media item not found', null, 404);
            }
            $responseData['data'] = processMediaItem($item);
            break;
            
        case 'by_category':
            if (empty($category)) {
                sendResponse(false, 'Category parameter is required', null, 400);
            }
            $items = $db->getMediaItemsByCategory($category, $limit);
            $responseData['data'] = processMediaItems($items);
            $responseData['count'] = count($responseData['data']);
            $responseData['category'] = $category;
            break;
            
        case 'by_source':
            if (empty($source)) {
                sendResponse(false, 'Source parameter is required', null, 400);
            }
            $items = $db->getMediaItemsBySource($source, $limit);
            $responseData['data'] = processMediaItems($items);
            $responseData['count'] = count($responseData['data']);
            $responseData['source'] = $source;
            break;
            
        case 'by_year':
            if ($year <= 0) {
                sendResponse(false, 'Valid year parameter is required', null, 400);
            }
            $items = $db->getMediaItemsByYear($year, $limit);
            $responseData['data'] = processMediaItems($items);
            $responseData['count'] = count($responseData['data']);
            $responseData['year'] = $year;
            break;
            
        case 'categories':
            $categories = $db->getMediaCategories();
            $responseData['data'] = $categories;
            $responseData['count'] = count($categories);
            break;
            
        case 'sources':
            $sources = $db->getMediaSources();
            $responseData['data'] = $sources;
            $responseData['count'] = count($sources);
            break;
            
        case 'recent':
            $limit = $limit > 0 ? $limit : 10;
            $items = $db->getRecentMediaItems($limit);
            $responseData['data'] = processMediaItems($items);
            $responseData['count'] = count($responseData['data']);
            break;
            
        case 'media_coverage':
            $items = $db->getMediaCoverage($limit);
            $responseData['data'] = processMediaItems($items);
            $responseData['count'] = count($responseData['data']);
            break;
            
        case 'press_releases':
            $items = $db->getPressReleases($limit);
            $responseData['data'] = processMediaItems($items);
            $responseData['count'] = count($responseData['data']);
            break;
            
        case 'all':
        default:
            $items = $db->getAllMediaItems();
            if ($limit > 0) {
                $items = array_slice($items, 0, $limit);
            }
            $responseData['data'] = processMediaItems($items);
            $responseData['count'] = count($responseData['data']);
            // Also include categories and sources for filtering
            $responseData['categories'] = $db->getMediaCategories();
            $responseData['sources'] = $db->getMediaSources();
            break;
    }
    
    sendResponse(true, 'Success', $responseData, 200);
    
} catch (Exception $e) {
    error_log("Media API Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, 500);
}

// sendResponse function if not defined in cors.php
if (!function_exists('sendResponse')) {
    function sendResponse($success, $message, $data = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}
?>