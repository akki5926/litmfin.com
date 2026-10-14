<?php
/**
 * FAQ API Endpoint
 */
require_once __DIR__ . '/includes/cors.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Ensure $db exists (should be created in cors.php)
if (!isset($db) || !is_object($db)) {
    sendResponse(false, 'Database connection not available', null, 500);
}

switch ($action) {
    case 'get_all_faqs':
        try {
            $data = $db->getFaqs();
            if (empty($data)) {
                sendResponse(true, 'No FAQs found', []);
            } else {
                sendResponse(true, 'FAQs retrieved successfully', $data);
            }
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage());
        }
        break;
        
    case 'get_faqs_by_page':
        $page = $_GET['page'] ?? '';
        if (empty($page)) {
            sendResponse(false, 'Page parameter is required');
        }
        
        try {
            $data = $db->getFaqsByPage($page);
            if (empty($data)) {
                sendResponse(true, "No FAQs found for page: {$page}", []);
            } else {
                sendResponse(true, "FAQs retrieved successfully for page: {$page}", $data);
            }
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage());
        }
        break;
        
    case 'get_faq_categories':
        try {
            if (method_exists($db, 'getFaqs')) {
                $allFaqs = $db->getFaqs();
                $categories = [];
                
                $pageCounts = [];
                foreach ($allFaqs as $faq) {
                    $page = $faq['faq_page'] ?? 'general';
                    $pageCounts[$page] = ($pageCounts[$page] ?? 0) + 1;
                }
                
                foreach ($pageCounts as $page => $count) {
                    $categories[] = [
                        'id' => $page,
                        'name' => ucfirst(str_replace('_', ' ', $page)),
                        'page' => $page,
                        'count' => $count
                    ];
                }
                
                sendResponse(true, 'FAQ categories retrieved successfully', $categories);
            } else {
                sendResponse(true, 'FAQ categories retrieved', [
                    ['id' => 'general', 'name' => 'General', 'page' => 'general', 'count' => 0],
                    ['id' => 'investing', 'name' => 'Investing', 'page' => 'investing', 'count' => 0],
                    ['id' => 'accounts', 'name' => 'Accounts', 'page' => 'accounts', 'count' => 0],
                    ['id' => 'transactions', 'name' => 'Transactions', 'page' => 'transactions', 'count' => 0]
                ]);
            }
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage());
        }
        break;
        
    case 'get_faq_by_id':
        $faq_id = $_GET['id'] ?? '';
        if (empty($faq_id)) {
            sendResponse(false, 'ID parameter is required');
        }
        
        try {
            if (method_exists($db, 'getFaqsById')) {
                $data = $db->getFaqsById($faq_id);
                if (!$data) {
                    sendResponse(false, "FAQ not found with ID: {$faq_id}");
                } else {
                    sendResponse(true, "FAQ retrieved successfully", $data);
                }
            } else {
                sendResponse(false, 'Method getFaqsById not available');
            }
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage());
        }
        break;
        
    case 'test':
        sendResponse(true, 'FAQ API is working', [
            'method' => $method,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s'),
            'available_actions' => [
                'get_all_faqs',
                'get_faqs_by_page',
                'get_faq_categories',
                'get_faq_by_id'
            ]
        ]);
        break;
        
    default:
        sendResponse(
            false,
            'Invalid action: "' . $action . '". Available actions: get_all_faqs, get_faqs_by_page, get_faq_categories, get_faq_by_id, test',
            [
                'received_action' => $action,
                'available_actions' => ['get_all_faqs', 'get_faqs_by_page', 'get_faq_categories', 'get_faq_by_id', 'test']
            ],
            404
        );
}
?>