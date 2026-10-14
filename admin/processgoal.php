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
	// Handle Add or Edit request
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		$name = trim($_POST['name']);
		$description = trim($_POST['description']);
		$edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

		if (!empty($name) && !empty($description)) {
			// Check if category name already exists (EXCLUDE current record when updating)
			$existing = $db->getGoalCategoryByName($name);
				//echo json_encode(["success" => false, "message" => $existing['id'], "edit_id" => $edit_id]);
				//exit;
			if ($existing && ($edit_id == 0 || $existing['id'] != $edit_id)) {
				echo json_encode(["success" => false, "message" => "Goal Category already exists!"]);
				exit;
			} 
			
 

			if ($edit_id) {
				$success = $db->updateGoalCategory($edit_id, $name, $description);
				echo json_encode(["success" => $success, "message" => $success ? "Goal Category updated successfully!" : "Error updating Goal Category."]);
			} else {
				$success = $db->addGoalCategory($name, $description);
				echo json_encode(["success" => $success, "message" => $success ? "Goal Category added successfully!" : "Error adding Goal Category."]);
			}
		} else {
			echo json_encode(["success" => false, "message" => "Please fill all fields."]);
		}
		exit;
	}




    // Handle Delete request
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        error_log("Deleting ID: $delete_id");
        $success = $db->deleteGoalCategory($delete_id);
        echo json_encode(["success" => $success, "message" => $success ? "Goal Category deleted successfully!" : "Error deleting Goal Category."]);
        exit;
    }

    // Fetch Goal Categories with Pagination
    if (isset($_GET['fetch'])) {
		$limit = 5;
		$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
		$offset = ($page - 1) * $limit;

		error_log("Fetching page: $page with limit: $limit, offset: $offset");

		$totalRows = $db->getGoalCategoryCount(); // Function to count total rows
		$totalPages = ceil($totalRows / $limit);

		$goalCategories = $db->getGoalCategoriesPaginated($limit, $offset);

		echo json_encode([
			"data" => $goalCategories,
			"totalPages" => $totalPages,
			"currentPage" => $page
		]);
		exit;
	}

} catch (Exception $e) {
    error_log("Exception caught: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Goal Categories: Error processing request."]);
}
