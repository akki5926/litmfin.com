<?php
require_once('auth_guard.php');

header('Content-Type: application/json');
require_once 'database.php';

$db = new MySQLDB();

// Assign a goal to a folio + fund
if (isset($_POST['assignGoal'])) {
    $ucc = $_POST['ucc'] ?? '';
    $folio = $_POST['folio'] ?? '';
    $fund_code = $_POST['fund_code'] ?? '';
    $goal_id = $_POST['goal_id'] ?? '';

    if (!$ucc || !$folio || !$fund_code || !$goal_id) {
        echo json_encode(["success" => false, "message" => "Missing parameters"]);
        exit;
    }

    // Upsert into assigned_goals
    $result = $db->upsertAssignedGoal($ucc, $folio, $fund_code, $goal_id);
    echo json_encode($result);
    exit;
}

// Fetch dropdown goals for an UCC
if (isset($_GET['fetchGoalsDropdown']) && $_GET['fetchGoalsDropdown'] == 1 && isset($_GET['ucc'])) {
    $ucc = $_GET['ucc'];
    $rows = $db->getGoalsByIIN($ucc);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// Fetch already assigned goals for an UCC
if (isset($_POST['fetchAssignedGoals']) && $_POST['fetchAssignedGoals'] == 1 && isset($_POST['ucc'])) {
    $ucc = $_POST['ucc'];
    $rows = $db->getAssignedGoals($ucc);
    echo json_encode($rows);
    exit;
}

// Fetch full goal status for frontend UI
if (isset($_POST['action']) && $_POST['action'] === 'fetchGoalStatus' && isset($_POST['ucc'])) {
    $ucc = $_POST['ucc'];
    $assigned = $db->getAssignedGoals($ucc);

    if (!$assigned || count($assigned) === 0) {
        echo json_encode(['success' => false, 'message' => 'No assigned goals found']);
        exit;
    }

    $groupedGoals = [];

    foreach ($assigned as $row) {
        $goalName = $row['goal_name'];

        if (!isset($groupedGoals[$goalName])) {
            $groupedGoals[$goalName] = [
                'goal_name' => $goalName,
                'amount_required' => (float)$row['amount_required'],
                'goal_date' => $row['goal_date'],
                'remarks' => $row['remarks'],
                'funds' => []
            ];
        }

        // Get scheme name
        $schemeName = $db->getschemename($row['fund_code'], ''); // if AMC code is not used

        $groupedGoals[$goalName]['funds'][] = [
            'folio' => $row['amc_code'],
            'fund_code' => $row['fund_code'],
            'scheme_name' => $schemeName
        ];
    }

    echo json_encode(['success' => true, 'data' => array_values($groupedGoals)]);
    exit;
}

// Default fallback
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;
