<?php
require_once('auth_guard.php');

require_once('database.php');
$db = new MYSQLDB();
$conn = $db->getConnection();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'get_amc') {
    $sql = "SELECT DISTINCT amc_code FROM mtb_scheme_master ORDER BY amc_code ASC";
    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action == 'get_scheme_type') {
    $amc_code = $_GET['amc_code'] ?? '';
    if (!$amc_code) {
        echo json_encode(['success' => false, 'message' => 'AMC Code required']);
        exit;
    }
    $sql = "SELECT DISTINCT scheme_type FROM mtb_scheme_master WHERE amc_code = ? ORDER BY scheme_type ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $amc_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action == 'get_fund_cat') {
    $amc_code = $_GET['amc_code'] ?? '';
    $scheme_type = $_GET['scheme_type'] ?? '';
    
    if (!$amc_code || !$scheme_type) {
        echo json_encode(['success' => false, 'message' => 'AMC Code and Scheme Type required']);
        exit;
    }
    
    $sql = "SELECT DISTINCT fund_cat FROM mtb_scheme_master WHERE amc_code = ? AND scheme_type = ? ORDER BY fund_cat ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $amc_code, $scheme_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

if ($action == 'get_schemes') {
    $amc_code = $_GET['amc_code'] ?? '';
    $scheme_type = $_GET['scheme_type'] ?? '';
    $fund_cat = $_GET['fund_cat'] ?? '';
    
    if (!$amc_code || !$scheme_type || !$fund_cat) {
        echo json_encode(['success' => false, 'message' => 'AMC Code, Scheme Type, and Fund Category required']);
        exit;
    }
    
    $sql = "SELECT scheme_code, scheme_name FROM mtb_scheme_master WHERE amc_code = ? AND scheme_type = ? AND fund_cat = ? ORDER BY scheme_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $amc_code, $scheme_type, $fund_cat);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
