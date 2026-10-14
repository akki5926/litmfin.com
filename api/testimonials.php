<?php
require_once __DIR__ . '/db/mfddb.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = new MfdDb();
    $testimonials = $db->getTestimonials();
    echo json_encode(['success' => true, 'data' => $testimonials]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>