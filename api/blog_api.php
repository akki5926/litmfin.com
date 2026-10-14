<?php
/**
 * Blog API Endpoint
 */

// Include CORS configuration first
require_once __DIR__ . '/includes/cors.php';

// Now include database
require_once __DIR__ . '/db/mfddb.php';

try {
    $db = new MfdDb();
    
    // Get base URL for images - construct from server variables
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the base path from the current script location
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    // Remove /api from the path to get the root
    $base_path = str_replace('/api', '', $script_path);
    
    // Construct full base URL for images
    $image_base_url = $protocol . $host . $base_path . '/uploads/blog/';
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'all';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $topic = isset($_GET['topic']) ? $_GET['topic'] : '';
    $url = isset($_GET['url']) ? $_GET['url'] : '';
    
    $response = ['success' => true];
    
    // Helper function to add full image URL and ensure URL field is included
    $addImageAndUrl = function(&$item) use ($image_base_url) {
        // Add full image URL
        if (!empty($item['image'])) {
            // Check if image already has http/https, if not, add base URL
            if (preg_match('/^https?:\/\//', $item['image'])) {
                $item['image_url'] = $item['image'];
            } else {
                $item['image_url'] = $image_base_url . rawurlencode($item['image']);
            }
        } else {
            $item['image_url'] = null;
        }
        
        // Ensure URL field is included for read more link
        // if (!isset($item['url']) || empty($item['url'])) {
        //     // If no URL in DB, create one from ID or title
        //     $item['url'] = '/blog/' . ($item['id'] ?? '');
        // }
        if (!isset($item['url']) || empty($item['url'])) {
            // If no URL in DB, create one from ID or title
            $item['url'] = '/blog/' . ($item['id'] ?? '');
            
            // Check if the file doesn't exist, then create it
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $item['url'] . '.php';
            
            if (!file_exists($file_path)) {
                try {
                    // Create directory if it doesn't exist
                    $directory = dirname($file_path);
                    if (!is_dir($directory)) {
                        if (!mkdir($directory, 0777, true)) {
                            throw new Exception("Failed to create directory: " . $directory);
                        }
                    }
                    
                    // Content to write to the file with blog template
                    $content = "<?php\n /* file created from api\blog_api.php */\n";
                    $content .= "\$id = " . ($item['id'] ?? 'null') . ";\n";
                    $content .= "include_once \"blog-template.php\";\n";
                    $content .= "?>";
                    
                    // Write content to file
                    $bytes_written = file_put_contents($file_path, $content);
                    if ($bytes_written === false) {
                        throw new Exception("Failed to write content to file: " . $file_path);
                    }
                    
                } catch (Exception $e) {
                    // Handle error appropriately (log, display, etc.)
                    error_log("Blog file creation error: " . $e->getMessage());
                }
            }
}
        
        return $item;
    };
    
    switch ($action) {
        case 'by_url':
            if (empty($url)) {
                sendResponse(false, 'URL parameter is required', null, 400);
                exit;
            }
            $post = $db->getBlogPostByUrl($url);
            if (!$post) {
                sendResponse(false, 'Blog post not found', null, 404);
                exit;
            }
            $addImageAndUrl($post);
            $response['data'] = $post;
            break;
            
        case 'featured_external':
            $response['data'] = $db->getFeaturedExternalBlogs();
            if (is_array($response['data'])) {
                array_walk($response['data'], $addImageAndUrl);
                $response['count'] = count($response['data']);
            } else {
                $response['data'] = [];
                $response['count'] = 0;
            }
            break;
            
        case 'internal':
            $response['data'] = $db->getInternalBlogPosts($limit, $featured);
            if (is_array($response['data'])) {
                array_walk($response['data'], $addImageAndUrl);
                $response['count'] = count($response['data']);
            } else {
                $response['data'] = [];
                $response['count'] = 0;
            }
            break;
            
        case 'recent':
            $limit = $limit > 0 ? $limit : 6;
            $response['data'] = $db->getRecentBlogPosts($limit);
            if (is_array($response['data'])) {
                array_walk($response['data'], $addImageAndUrl);
                $response['count'] = count($response['data']);
            } else {
                $response['data'] = [];
                $response['count'] = 0;
            }
            break;
            
        case 'by_topic':
            if (empty($topic)) {
                sendResponse(false, 'Topic parameter is required', null, 400);
                exit;
            }
            $response['data'] = $db->getBlogPostsByTopic($topic, $limit);
            if (is_array($response['data'])) {
                array_walk($response['data'], $addImageAndUrl);
                $response['count'] = count($response['data']);
            } else {
                $response['data'] = [];
                $response['count'] = 0;
            }
            $response['topic'] = $topic;
            break;
            
        case 'single':
            if ($id <= 0) {
                sendResponse(false, 'Valid ID parameter is required', null, 400);
                exit;
            }
            $post = $db->getBlogPostById($id);
            if (!$post) {
                sendResponse(false, 'Blog post not found', null, 404);
                exit;
            }
            $addImageAndUrl($post);
            $response['data'] = $post;
            break;
            
        case 'all':
        default:
            // Get ALL blog posts
            $response['data'] = $db->getAllBlogPosts();
            if (is_array($response['data'])) {
                array_walk($response['data'], $addImageAndUrl);
                $response['count'] = count($response['data']);
            } else {
                $response['data'] = [];
                $response['count'] = 0;
            }
            
            // Also get featured external for the featured section
            $response['featured_external'] = $db->getFeaturedExternalBlogs();
            if (is_array($response['featured_external'])) {
                array_walk($response['featured_external'], $addImageAndUrl);
            } else {
                $response['featured_external'] = [];
            }
            
            // Get all internal posts (including non-featured)
            $response['internal'] = $db->getInternalBlogPosts(0, null);
            if (is_array($response['internal'])) {
                array_walk($response['internal'], $addImageAndUrl);
            } else {
                $response['internal'] = [];
            }
            break;
    }
    
    // CORS headers are already set by cors.php
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>