<?php
require_once('auth_guard.php');

/**
 * processfamily.php
 * Handles all AJAX requests for Family Settings.
 * FIXED: Now supports individual member deletion and real-time updates
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ─── Helpers ────────────────────────────────────────────────────────────────

function getParam(string $key, $default = ''): string {
    return isset($_REQUEST[$key]) ? trim($_REQUEST[$key]) : $default;
}

function getIntParam(string $key, int $default = 0): int {
    return isset($_REQUEST[$key]) ? intval($_REQUEST[$key]) : $default;
}

// ─── Business Logic Functions ────────────────────────────────────────────────

/**
 * Retrieve a single family record by slno
 */
function retrieveFamilies(int $slno): void {
    require_once('database.php');
    $db = new MYSQLDB();
    $result = $db->getFamiliesById($slno);
    echo json_encode($result !== false ? $result : null);
}

/**
 * Add OR Update a family record (supports partial updates)
 */
function addFamily(array $data): void {
    $UCC    = isset($data['UCC'])    ? trim($data['UCC'])    : '';
    $UCC1   = isset($data['UCC1'])   ? trim($data['UCC1'])   : '';
    $rel1   = isset($data['rel1'])   ? trim($data['rel1'])   : '';
    $UCC2   = isset($data['UCC2'])   ? trim($data['UCC2'])   : '';
    $rel2   = isset($data['rel2'])   ? trim($data['rel2'])   : '';
    $UCC3   = isset($data['UCC3'])   ? trim($data['UCC3'])   : '';
    $rel3   = isset($data['rel3'])   ? trim($data['rel3'])   : '';

    if ($UCC === '') {
        echo json_encode(['status' => 'error', 'message' => 'Head UCC is required.']);
        return;
    }

    require_once('database.php');
    $db = new MYSQLDB();
    
    // Check if a family record already exists for this head UCC
    $existingRecord = $db->getFamilyByUCC($UCC);
    
    if ($existingRecord !== false && $existingRecord !== null) {
        // RECORD EXISTS - UPDATE IT
        $existingSlno = $existingRecord['slno'];
        
        // Merge: Use new values if provided, otherwise keep existing
        $finalUCC1 = ($UCC1 !== '') ? $UCC1 : ($existingRecord['member1_UCC'] ?? '');
        $finalRel1 = ($rel1 !== '') ? $rel1 : ($existingRecord['member1_rel'] ?? '');
        $finalUCC2 = ($UCC2 !== '') ? $UCC2 : ($existingRecord['member2_UCC'] ?? '');
        $finalRel2 = ($rel2 !== '') ? $rel2 : ($existingRecord['member2_rel'] ?? '');
        $finalUCC3 = ($UCC3 !== '') ? $UCC3 : ($existingRecord['member3_UCC'] ?? '');
        $finalRel3 = ($rel3 !== '') ? $rel3 : ($existingRecord['member3_rel'] ?? '');
        
        $result = $db->updateFamily($existingSlno, $UCC, $finalUCC1, $finalRel1, $finalUCC2, $finalRel2, $finalUCC3, $finalRel3);
        
        $isError = (strpos($result, 'Error') !== false || strpos($result, 'Failed') !== false);
        echo json_encode([
            'status'  => $isError ? 'error' : 'success',
            'message' => $isError ? $result : 'Family record updated successfully',
        ]);
    } else {
        // NO EXISTING RECORD - INSERT NEW (only if at least one member provided)
        if ($UCC1 === '' && $UCC2 === '' && $UCC3 === '') {
            echo json_encode(['status' => 'error', 'message' => 'At least one family member is required.']);
            return;
        }
        
        $result = $db->addFamily($UCC, $UCC1, $rel1, $UCC2, $rel2, $UCC3, $rel3, 'add', 0);
        
        $isError = (strpos($result, 'Error') !== false || strpos($result, 'Failed') !== false);
        echo json_encode([
            'status'  => $isError ? 'error' : 'success',
            'message' => $isError ? $result : 'Family members added successfully',
        ]);
    }
}

/**
 * Delete a specific member (1, 2, or 3) from the family
 */
function deleteMember(array $data): void {
    $UCC = isset($data['UCC']) ? trim($data['UCC']) : '';
    $memberNum = isset($data['memberNum']) ? intval($data['memberNum']) : 0;
    
    if ($UCC === '') {
        echo json_encode(['status' => 'error', 'message' => 'Head UCC is required.']);
        return;
    }
    
    if ($memberNum < 1 || $memberNum > 3) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid member number.']);
        return;
    }
    
    require_once('database.php');
    $db = new MYSQLDB();
    
    // Get existing record
    $existingRecord = $db->getFamilyByUCC($UCC);
    
    if ($existingRecord === false || $existingRecord === null) {
        echo json_encode(['status' => 'error', 'message' => 'No family record found.']);
        return;
    }
    
    // Preserve other members, clear the one being deleted
    $finalUCC1 = ($memberNum == 1) ? '' : ($existingRecord['member1_UCC'] ?? '');
    $finalRel1 = ($memberNum == 1) ? '' : ($existingRecord['member1_rel'] ?? '');
    $finalUCC2 = ($memberNum == 2) ? '' : ($existingRecord['member2_UCC'] ?? '');
    $finalRel2 = ($memberNum == 2) ? '' : ($existingRecord['member2_rel'] ?? '');
    $finalUCC3 = ($memberNum == 3) ? '' : ($existingRecord['member3_UCC'] ?? '');
    $finalRel3 = ($memberNum == 3) ? '' : ($existingRecord['member3_rel'] ?? '');
    
    $result = $db->updateFamily($existingRecord['slno'], $UCC, $finalUCC1, $finalRel1, $finalUCC2, $finalRel2, $finalUCC3, $finalRel3);
    
    $isError = (strpos($result, 'Error') !== false || strpos($result, 'Failed') !== false);
    echo json_encode([
        'status'  => $isError ? 'error' : 'success',
        'message' => $isError ? $result : "Member {$memberNum} deleted successfully",
    ]);
}

/**
 * Delete entire family record by slno
 */
function deleteFamily(int $slno): void {
    require_once('database.php');
    $db = new MYSQLDB();
    $result = $db->deleteFamily($slno);
    $isError = (strpos($result, 'Error') !== false || strpos($result, 'Failed') !== false);
    echo json_encode([
        'status'  => $isError ? 'error' : 'success',
        'message' => $result,
    ]);
}

/**
 * Get all family records where UCC is the head
 */
function getFamilies(string $UCC): void {
    require_once('database.php');
    $db = new MYSQLDB();
    $result = $db->getFamilies($UCC);
    echo json_encode($result !== false ? $result : []);
}

// ─── Route the request ───────────────────────────────────────────────────────

$action = getParam('action');

switch ($action) {
    case 'add':
        addFamily($_REQUEST);
        break;
    
    case 'deleteMember':
        deleteMember($_REQUEST);
        break;

    case 'getMembersData':
        $slno = getIntParam('slno');
        retrieveFamilies($slno);
        break;

    case 'getData':
        $UCC = getParam('UCC');
        getFamilies($UCC);
        break;

    case 'delete':
        $slno = getIntParam('slno');
        if ($slno <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid record ID for delete.']);
        } else {
            deleteFamily($slno);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => "Unknown action: $action"]);
        break;
}
?>