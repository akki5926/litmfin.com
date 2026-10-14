<?php
require_once('auth_guard.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log'); // Log errors in php_error.log

require 'database.php';
$db = new MYSQLDB();

header('Content-Type: application/json');

try {
    // Log incoming request data for debugging (uncomment if needed)
    // error_log("POST Data: " . print_r($_POST, true));
    // error_log("GET Data: " . print_r($_GET, true));

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
		
		// Fetch assigned goals for a customer (UCC)
		if (isset($_POST['fetchAssignedGoals'])) {
			$ucc = isset($_POST['ucc']) ? trim($_POST['ucc']) : '';
			
			if (empty($ucc)) {
				echo json_encode(["success" => false, "message" => "UCC is required"]);
				exit;
			}
			
			$assignedGoals = $db->getAssignedGoals($ucc);
			
			echo json_encode(["success" => true, "data" => $assignedGoals]);
			exit;
		}

		// Assign goal to folio
		if (isset($_POST['assignGoal'])) {
			$ucc = isset($_POST['ucc']) ? trim($_POST['ucc']) : '';
			$investment_type = isset($_POST['investment_type']) ? trim($_POST['investment_type']) : '';
			$amc_code = isset($_POST['amc_code']) ? trim($_POST['amc_code']) : '';
			$fundCode = isset($_POST['fundCode']) ? trim($_POST['fundCode']) : '';
			$goalId = isset($_POST['goal_id']) ? intval($_POST['goal_id']) : 0;
			$asset_class = isset($_POST['asset_class']) ? trim($_POST['asset_class']) : '';
			$fund_name = isset($_POST['fund_name']) ? trim($_POST['fund_name']) : '';

			if (empty($ucc) || empty($amc_code) || empty($fundCode) || empty($goalId)) {
				error_log("Validation failed: Missing required fields. UCC: $ucc, AMC: $amc_code, Fund: $fundCode, GoalID: $goalId");
				echo json_encode(["success" => false, "message" => "All fields are required."]);
				exit;
			}

			$result = $db->assignGoalToFolio($ucc, $investment_type, $amc_code, $fundCode, $asset_class, $fund_name, $goalId);

			echo json_encode([
				"success" => $result,
				"message" => $result ? "Goal assigned successfully." : "Failed to assign goal."
			]);
			exit;
		}

		// Delete goal
		if (isset($_POST['deleteGoal'])) { 
			$delete_id = isset($_POST['goal_id']) ? intval($_POST['goal_id']) : 0;
			$ucc = isset($_POST['ucc']) ? trim($_POST['ucc']) : '';

			if ($delete_id > 0 && !empty($ucc)) {
				$success = $db->deleteGoal($delete_id, $ucc);
				echo json_encode(["success" => $success, "message" => $success ? "Goal deleted successfully!" : "Error deleting Goal."]);
			} else {
				echo json_encode(["success" => false, "message" => "Invalid Goal ID or UCC for deletion."]);
			}
			exit;
		}

		// Handle Add/Edit Goal Request
		$goal_name = isset($_POST['goal_name']) ? trim($_POST['goal_name']) : '';
		$category_id = isset($_POST['goal_category']) ? intval($_POST['goal_category']) : 0;
		$amount_required = isset($_POST['amount_required']) ? floatval($_POST['amount_required']) : 0;
		$on_date = isset($_POST['on_date']) ? $_POST['on_date'] : '';
		$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
		$edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
		$ucc = isset($_POST['ucc']) ? trim($_POST['ucc']) : '';

		// Validate required fields
		if (!empty($goal_name) && $category_id > 0 && $amount_required > 0 && !empty($on_date) && !empty($ucc)) {
			if ($edit_id) {
				$success = $db->updateGoal($edit_id, $goal_name, $category_id, $amount_required, $on_date, $remarks, $ucc);
				echo json_encode(["success" => $success, "message" => $success ? "Goal updated successfully!" : "Error updating Goal."]);
			} else {
				$success = $db->addGoal($goal_name, $category_id, $amount_required, $on_date, $remarks, $ucc);
				echo json_encode(["success" => $success, "message" => $success ? "Goal added successfully!" : "Error adding Goal."]);
			}
		} else {
			$errorMsg = "Please fill all fields correctly. ";
			if (empty($goal_name)) $errorMsg .= "Goal name missing. ";
			if ($category_id <= 0) $errorMsg .= "Category missing. ";
			if ($amount_required <= 0) $errorMsg .= "Amount required missing. ";
			if (empty($on_date)) $errorMsg .= "Date missing. ";
			if (empty($ucc)) $errorMsg .= "UCC missing. ";
			echo json_encode(["success" => false, "message" => $errorMsg]);
		}
		exit;
	}

	// Fetch Goals with Pagination (GET request)
	if (isset($_GET['fetchGoals'])) {
		$limit = 5;
		$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
		$offset = ($page - 1) * $limit;
		$ucc = isset($_GET['ucc']) ? trim($_GET['ucc']) : '';

		if (empty($ucc)) {
			echo json_encode(["success" => false, "message" => "UCC is required to fetch goals.", "data" => []]);
			exit;
		}

		$totalRows = $db->getGoalsCount($ucc);
		$totalPages = ceil($totalRows / $limit);
		$goals = $db->getGoalsPaginated($ucc, $limit, $offset);
		
		echo json_encode([
			"data" => $goals,
			"totalPages" => $totalPages,
			"currentPage" => $page
		]);
		exit;
	}

	// Fetch Goals for Dropdown (GET request)
	if (isset($_GET['fetchGoalsDropdown'])) {
		$ucc = isset($_GET['ucc']) ? trim($_GET['ucc']) : '';

		if (empty($ucc)) {
			echo json_encode(["success" => false, "message" => "UCC is required to fetch goals.", "data" => []]);
			exit;
		}

		$goals = $db->getGoalsByUCC($ucc); // Note: This function name should be updated in database.php

		echo json_encode([
			"success" => true,
			"data" => $goals
		]);
		exit;
	}

	// Fetch Goal Categories (GET request)
	if (isset($_GET['fetchCategories'])) {
		$categories = $db->getAllGoalCategories();
		echo json_encode($categories);
		exit;
	}

	// Default response if no valid action is specified
	echo json_encode(["success" => false, "message" => "Invalid request. No valid action specified."]);

} catch (Exception $e) {
	error_log("Exception caught in processgoalmngt.php: " . $e->getMessage());
	error_log("Stack trace: " . $e->getTraceAsString());
	echo json_encode(["success" => false, "message" => "Goals: Error processing request. " . $e->getMessage()]);
}
?>