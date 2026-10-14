<?php
require_once('auth_guard.php');

/**
 * Unified Goal Management System
 * 
 * This file handles all goal-related operations:
 * - Goal Categories (CRUD)
 * - Goal Management (CRUD)  
 * - Goal Assignment to Folios
 * - Goal Status Tracking
 */

if(!isset($_SESSION)) { session_start(); } 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

require_once 'database.php';
require_once 'portfolio.php';

// Initialize database connection
$db = new MYSQLDB();

// ==================== API HANDLER ====================
// Check if this is an API request
$isApiRequest = ($_SERVER["REQUEST_METHOD"] == "POST" || isset($_GET['action']) || isset($_GET['deleteCategory']));

if ($isApiRequest) {
    header('Content-Type: application/json');
    try {
        $response = handleApiRequest($db);
        echo json_encode($response);
    } catch (Exception $e) {
        error_log("Exception caught: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Error processing request: " . $e->getMessage()]);
    }
    exit;
}

function handleApiRequest($db) {
    // ==================== GOAL CATEGORY APIs ====================
    
    // Fetch all goal categories (GET)
    if (isset($_GET['action']) && $_GET['action'] === 'fetchCategories') {
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $categories = $db->getGoalCategoriesPaginated($limit, $offset);
        $totalRows = $db->getGoalCategoryCount();
        
        return [
            "success" => true,
            "data" => $categories,
            "totalPages" => ceil($totalRows / $limit),
            "currentPage" => $page
        ];
    }
    
    // Add/Edit goal category (POST)
    if (isset($_POST['saveCategory'])) {
        $id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || empty($description)) {
            return ["success" => false, "message" => "Category name and description are required"];
        }
        
        if ($id > 0) {
            $success = $db->updateGoalCategory($id, $name, $description);
            return ["success" => $success, "message" => $success ? "Category updated successfully" : "Failed to update category"];
        } else {
            $success = $db->addGoalCategory($name, $description);
            return ["success" => $success, "message" => $success ? "Category added successfully" : "Failed to add category"];
        }
    }
    
    // Delete goal category (GET)
    if (isset($_GET['deleteCategory'])) {
        $id = intval($_GET['deleteCategory']);
        if ($id > 0) {
            $success = $db->deleteGoalCategory($id);
            return ["success" => $success, "message" => $success ? "Category deleted successfully" : "Failed to delete category"];
        }
        return ["success" => false, "message" => "Invalid category ID"];
    }
    
    // ==================== GOAL APIs ====================
    
    // Fetch goals with pagination (GET)
    if (isset($_GET['action']) && $_GET['action'] === 'fetchGoals') {
        $limit = 10;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $limit;
        $ucc = isset($_GET['ucc']) ? trim($_GET['ucc']) : '';
        
        if (empty($ucc)) {
            return ["success" => false, "message" => "UCC is required", "data" => []];
        }
        
        $totalRows = $db->getGoalsCount($ucc);
        $goals = $db->getGoalsPaginated($ucc, $limit, $offset);
        
        return [
            "success" => true,
            "data" => $goals,
            "totalPages" => ceil($totalRows / $limit),
            "currentPage" => $page,
            "totalRows" => $totalRows
        ];
    }
    
    // Fetch goals for dropdown (GET)
    if (isset($_GET['action']) && $_GET['action'] === 'fetchGoalsDropdown') {
        $ucc = isset($_GET['ucc']) ? trim($_GET['ucc']) : '';
        
        if (empty($ucc)) {
            return ["success" => false, "message" => "UCC is required", "data" => []];
        }
        
        $goals = $db->getGoalsByUCC($ucc);
        return ["success" => true, "data" => $goals];
    }
    
    // Add/Edit goal (POST)
    if (isset($_POST['saveGoal'])) {
        $goal_name = trim($_POST['goal_name'] ?? '');
        $category_id = intval($_POST['goal_category'] ?? 0);
        $amount_required = floatval($_POST['amount_required'] ?? 0);
        $on_date = $_POST['on_date'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $edit_id = intval($_POST['edit_id'] ?? 0);
        $ucc = trim($_POST['ucc'] ?? '');
        
        if (empty($goal_name) || $category_id <= 0 || $amount_required <= 0 || empty($on_date) || empty($ucc)) {
            return ["success" => false, "message" => "All required fields must be filled"];
        }
        
        if ($edit_id > 0) {
            $success = $db->updateGoal($edit_id, $goal_name, $category_id, $amount_required, $on_date, $remarks, $ucc);
            return ["success" => $success, "message" => $success ? "Goal updated successfully" : "Failed to update goal"];
        } else {
            $success = $db->addGoal($goal_name, $category_id, $amount_required, $on_date, $remarks, $ucc);
            return ["success" => $success, "message" => $success ? "Goal added successfully" : "Failed to add goal"];
        }
    }
    
    // Delete goal (POST)
    if (isset($_POST['deleteGoal'])) {
        $goal_id = intval($_POST['goal_id'] ?? 0);
        $ucc = trim($_POST['ucc'] ?? '');
        
        if ($goal_id > 0 && !empty($ucc)) {
            $success = $db->deleteGoal($goal_id, $ucc);
            return ["success" => $success, "message" => $success ? "Goal deleted successfully" : "Failed to delete goal"];
        }
        return ["success" => false, "message" => "Invalid goal ID or UCC"];
    }
    
    // ==================== GOAL ASSIGNMENT APIs ====================
    
    // Fetch assigned goals for a customer (POST)
    if (isset($_POST['fetchAssignedGoals'])) {
        $ucc = trim($_POST['ucc'] ?? '');
        
        if (empty($ucc)) {
            return ["success" => false, "message" => "UCC is required"];
        }
        
        $assignedGoals = $db->getAssignedGoals($ucc);
        return ["success" => true, "data" => $assignedGoals];
    }
    
    // Assign goal to folio (POST)
    if (isset($_POST['assignGoal'])) {
        $ucc = trim($_POST['ucc'] ?? '');
        $investment_type = trim($_POST['investment_type'] ?? '');
        $amc_code = trim($_POST['amc_code'] ?? '');
        $fundCode = trim($_POST['fundCode'] ?? '');
        $goalId = intval($_POST['goal_id'] ?? 0);
        $asset_class = trim($_POST['asset_class'] ?? '');
        $fund_name = trim($_POST['fund_name'] ?? '');
        
        if (empty($ucc) || empty($amc_code) || empty($fundCode) || $goalId <= 0) {
            return ["success" => false, "message" => "All fields are required"];
        }
        
        $result = $db->assignGoalToFolio($ucc, $investment_type, $amc_code, $fundCode, $asset_class, $fund_name, $goalId);
        return [
            "success" => $result,
            "message" => $result ? "Goal assigned successfully" : "Failed to assign goal"
        ];
    }
    
    // Unassign goal from folio (POST)
    if (isset($_POST['unassignGoal'])) {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        
        if ($assignment_id > 0) {
            $success = $db->unassignGoal($assignment_id);
            return ["success" => $success, "message" => $success ? "Goal unassigned successfully" : "Failed to unassign goal"];
        }
        return ["success" => false, "message" => "Invalid assignment ID"];
    }
    
    // ==================== GOAL STATUS API ====================
    
    // Fetch goal status with portfolio data (POST)
    if (isset($_POST['action']) && $_POST['action'] === 'fetchGoalStatus') {
        $ucc = trim($_POST['ucc'] ?? '');
        
        if (empty($ucc)) {
            return ["success" => false, "message" => "UCC is required"];
        }
        
        // Get assigned goals with details
        $assignedGoals = $db->getAssignedGoalsWithDetails($ucc);
        
        if (!$assignedGoals || count($assignedGoals) === 0) {
            return ["success" => false, "message" => "No assigned goals found"];
        }
        
        // Get portfolio data
        $portfolioData = getPortfolioData($ucc);
        
        // Group goals and calculate status
        $groupedGoals = [];
        foreach ($assignedGoals as $row) {
            $goalName = $row['goal_name'];
            
            if (!isset($groupedGoals[$goalName])) {
                $groupedGoals[$goalName] = [
                    'goal_id' => $row['goal_id'],
                    'goal_name' => $goalName,
                    'category_name' => $row['category_name'] ?? '',
                    'amount_required' => (float)$row['amount_required'],
                    'goal_date' => $row['goal_date'],
                    'remarks' => $row['remarks'] ?? '',
                    'funds' => []
                ];
            }
            
            // Get current value from portfolio data
            $currentValue = 0;
            $xirr = 'N/A';
            $units = 'N/A';
            $costValue = 0;
            $nav = 'N/A';
            $fundName = $row['fund_name'];
            
            if ($portfolioData && isset($portfolioData[$row['amc_code']])) {
                foreach ($portfolioData[$row['amc_code']] as $fund) {
                    if (isset($fund[$row['fund_code']])) {
                        $details = $fund[$row['fund_code']];
                        $currentValue = floatval(str_replace(',', '', $details['Curr_Value'] ?? 0));
                        $xirr = $details['XIRR'] ?? 'N/A';
                        $units = $details['Unit_Holdings'] ?? 'N/A';
                        $costValue = floatval(str_replace(',', '', $details['Cost_Value'] ?? 0));
                        $nav = $details['Current_NAV'] ?? 'N/A';
                        $fundName = $details['Fund_Name'] ?? $row['fund_name'];
                        break;
                    }
                }
            }
            
            $groupedGoals[$goalName]['funds'][] = [
                'amc_code' => $row['amc_code'],
                'fund_code' => $row['fund_code'],
                'fund_name' => $fundName,
                'investment_type' => $row['investment_type'],
                'asset_class' => $row['asset_class'],
                'units' => $units,
                'cost_value' => $costValue,
                'nav' => $nav,
                'current_value' => $currentValue,
                'xirr' => $xirr
            ];
        }
        
        // Calculate summary for each goal
        foreach ($groupedGoals as &$goal) {
            $totalCurrentValue = array_sum(array_column($goal['funds'], 'current_value'));
            $goal['total_current_value'] = $totalCurrentValue;
            $goal['percent_achieved'] = $goal['amount_required'] > 0 ? round(($totalCurrentValue / $goal['amount_required']) * 100) : 0;
            
            $yearsLeft = calculateYearsLeft($goal['goal_date']);
            $goal['years_left'] = $yearsLeft;
            
            if ($yearsLeft > 0) {
                $futureValue = calculateFutureValue($totalCurrentValue, 0.12, $yearsLeft);
                $goal['future_value'] = $futureValue;
                $goal['future_percent'] = $goal['amount_required'] > 0 ? round(($futureValue / $goal['amount_required']) * 100) : 0;
                $goal['shortfall'] = max($goal['amount_required'] - $futureValue, 0);
                $goal['required_sip'] = $goal['shortfall'] > 0 ? calculateSIP($goal['amount_required'], 0.12, $yearsLeft) : 0;
            } else {
                $goal['future_value'] = $totalCurrentValue;
                $goal['future_percent'] = $goal['percent_achieved'];
                $goal['shortfall'] = max($goal['amount_required'] - $totalCurrentValue, 0);
                $goal['required_sip'] = 0;
            }
        }
        
        return [
            "success" => true,
            "data" => array_values($groupedGoals),
            "customer_name" => getCustomerName($ucc)
        ];
    }
    
    return ["success" => false, "message" => "Invalid action"];
}

// ==================== HELPER FUNCTIONS ====================

function getPortfolioData($ucc) {
    // Call the calculation function directly instead of using cURL to hit the API endpoint
    list($portfolioInfo) = calculateHoldings($ucc);
    
    return $portfolioInfo ?: [];
}

function getCustomerName($ucc) {
    global $db;
    
    // Directly query the database instead of making a cURL request
    if ($db) {
        $name = $db->getUCCName($ucc);
        if ($name && $name !== 'false' && $name !== 'null') {
            return $name;
        }
    }
    
    return $ucc;
}

function calculateYearsLeft($goalDate) {
    $end = new DateTime($goalDate);
    $today = new DateTime();
    $diff = $today->diff($end);
    
    if ($end < $today) {
        return 0;
    }
    
    $years = $diff->y + ($diff->m / 12) + ($diff->d / 365);
    return round($years, 1);
}

function calculateFutureValue($presentValue, $rate, $years) {
    return round($presentValue * pow(1 + $rate, $years));
}

function calculateSIP($futureValue, $annualRate, $years) {
    if ($years <= 0) return 0;
    
    $monthlyRate = $annualRate / 12;
    $months = $years * 12;
    
    if ($monthlyRate <= 0) return 0;
    
    $factor = (pow(1 + $monthlyRate, $months) - 1) / $monthlyRate;
    
    if ($factor <= 0) return 0;
    
    return round($futureValue / $factor);
}

// ==================== HTML OUTPUT (when not an API request) ====================
?>
    <style>
        :root {
            --primary-color: #FD7101;
            --primary-dark: #e06300;
            --secondary-color: #1d3557;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: white !important;
        }
        
        .navbar-custom .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            border-radius: 5px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--secondary-color), #2c3e66);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .autocomplete-container {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            border: 1px solid #ddd;
            background-color: white;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .autocomplete-list div {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .autocomplete-list div:hover {
            background-color: #f0f0f0;
        }
        
        .goal-section {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .goal-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px 20px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .goal-header:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
        }
        
        .goal-header h5 {
            margin: 0;
            color: var(--secondary-color);
        }
        
        .indicator {
            display: inline-block;
            width: 24px;
            font-weight: bold;
        }
        
        .goal-body {
            padding: 20px;
            border-top: 1px solid #eee;
        }
        
        .progress {
            height: 30px;
            border-radius: 15px;
            background-color: #e9ecef;
        }
        
        .progress-bar {
            border-radius: 15px;
            font-weight: 600;
            line-height: 30px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .shortfall-card {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .success-card {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .nav-tabs .nav-link {
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--secondary-color), #2c3e66);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .badge-goal {
            background-color: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .add-goal-btn {
            background-color: var(--success-color);
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .add-goal-btn:hover {
            background-color: #218838;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 12px;
            }
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
    <div class="main-container">
        <!-- Navigation -->
        <nav class="navbar navbar-custom navbar-expand-lg">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <i class="fas fa-bullseye me-2"></i>Goal Management System
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
					     <li class="nav-item">
                            <a class="nav-link" href="#" data-tab="categories">
                                <i class="fas fa-tags me-1"></i>Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-tab="goals">
                                <i class="fas fa-tasks me-1"></i>My Goals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-tab="assign">
                                <i class="fas fa-link me-1"></i>Assign Goals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-tab="status">
                                <i class="fas fa-chart-line me-1"></i>Goal Status
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Customer Selection -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user me-2"></i>Select Customer
            </div>
            <div class="card-body">
                <form id="customerForm" onsubmit="selectCustomer(event); return false;">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="custUCC" class="form-label fw-bold">Customer UCC</label>
                            <div class="autocomplete-container">
                                <input type="text" class="form-control" id="custUCC" name="custUCC" 
                                       placeholder="Enter UCC or Name" autocomplete="off" required>
                                <div id="autocomplete-list-custUCC" class="autocomplete-list" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Selected Customer</label>
                            <div class="form-control bg-light" id="selectedCustomerName">Not selected</div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1" id="selectCustomerBtn">
                                    <i class="fas fa-check-circle me-1"></i>Select Customer
                                </button>
                                <button type="button" class="btn btn-secondary" id="clearCustomerBtn" onclick="clearCustomerSelection()">
                                    <i class="fas fa-eraser me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                <div id="status1" class="mt-3"></div>
            </div>
        </div>
        
        <!-- Tab Content -->
        <div id="goalsTab" class="tab-content" style="display:block;">
            <!-- Goals Management Tab -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-tasks me-2"></i>Manage Goals</span>
                    <button class="btn btn-light btn-sm" onclick="showAddGoalModal()">
                        <i class="fas fa-plus me-1"></i>Add New Goal
                    </button>
                </div>
                <div class="card-body">
                    <div id="goalsStatus"></div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Goal Name</th>
                                    <th>Category</th>
                                    <th>Amount Required</th>
                                    <th>Target Date</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="goalTableBody">
                                <tr><td colspan="7" class="text-center text-muted">Select a customer to view goals</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="goalsPagination" class="d-flex justify-content-center mt-3"></div>
                </div>
            </div>
        </div>
        
        <div id="assignTab" class="tab-content" style="display:none;">
            <!-- Assign Goals Tab -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-link me-2"></i>Assign Goals to Folios
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Select a goal from the dropdown next to each folio to assign it. Click "Change Goal" to modify existing assignments.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Fund/Scheme Name</th>
                                    <th>Asset Class</th>
                                    <th>Units</th>
                                    <th>Cost Value</th>
                                    <th>Current NAV</th>
                                    <th>Current Value</th>
                                    <th>XIRR (%)</th>
                                    <th>Assigned Goal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="folioTableBody">
                                <tr><td colspan="9" class="text-center text-muted">Select a customer to view folios</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="statusTab" class="tab-content" style="display:none;">
            <!-- Goal Status Tab -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fas fa-chart-line me-2"></i>Goal Status Dashboard</h4>
                <div id="exportButtons" style="display:none;">
                    <button class="btn btn-success me-2" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-1"></i>Export Excel
                    </button>
                    <button class="btn btn-danger" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-1"></i>Export PDF
                    </button>
                </div>
            </div>
            <div id="goalStatusContainer">
                <div class="text-center text-muted py-5">Select a customer to view goal status</div>
            </div>
        </div>
        
        <div id="categoriesTab" class="tab-content" style="display:none;">
            <!-- Categories Management Tab -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-tags me-2"></i>Goal Categories</span>
                    <button class="btn btn-light btn-sm" onclick="showAddCategoryModal()">
                        <i class="fas fa-plus me-1"></i>Add Category
                    </button>
                </div>
                <div class="card-body">
                    <div id="categoriesStatus"></div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="categoryTableBody">
                                <tr><td colspan="5" class="text-center text-muted">Loading categories...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="categoriesPagination" class="d-flex justify-content-center mt-3"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Goal Modal -->
    <div class="modal fade" id="goalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-bullseye me-2"></i><span id="goalModalTitle">Add Goal</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="goalForm">
                        <input type="hidden" id="goal_edit_id" name="edit_id" aria-label="Goal ID" autocomplete="off">
                        <div class="mb-3">
                            <label for="goal_name" class="form-label">Goal Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="goal_name" name="goal_name" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="goal_category" class="form-label">Goal Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="goal_category" name="goal_category" required autocomplete="off">
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="amount_required" class="form-label">Amount Required (₹) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="amount_required" name="amount_required" step="0.01" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="on_date" class="form-label">Target Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="on_date" name="on_date" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="goal_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="goal_remarks" name="remarks" rows="3" autocomplete="off"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveGoal()">Save Goal</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tag me-2"></i><span id="categoryModalTitle">Add Category</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="categoryForm">
                        <input type="hidden" id="category_edit_id" name="category_id" aria-label="Category ID" autocomplete="off">
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="category_name" name="name" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="category_description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="category_description" name="description" rows="3" required autocomplete="off"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveCategory()">Save Category</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        // Global variables
        var currentUCC = '';
        var currentCustomerName = '';
        var currentPage = 1;
        var currentCategoryPage = 1;
        var totalGoalPages = 1;
        var folioDataCache = null;
        var assignedGoalsCache = null;
        
        // ==================== UTILITY FUNCTIONS ====================
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function formatIndianNumber(num) {
            if (!num && num !== 0) return '0';
            return Number(num).toLocaleString('en-IN', { maximumFractionDigits: 0 });
        }
        
        function formatIndianDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            return date.toLocaleDateString('en-IN');
        }
        
        function showStatus(container, message, type) {
            const alertClass = type === 'success' ? 'alert-success' : (type === 'danger' ? 'alert-danger' : 'alert-warning');
            $(container).html(`<div class="alert ${alertClass} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
            setTimeout(() => $(container).find('.alert').fadeOut(), 5000);
        }
        
        // ==================== CLEAR CUSTOMER SELECTION ====================
        
        function clearCustomerSelection() {
            // Clear global variables
            currentUCC = '';
            currentCustomerName = '';
            currentPage = 1;
            folioDataCache = null;
            assignedGoalsCache = null;
            
            // Clear input fields
            $('#custUCC').val('');
            $('#selectedCustomerName').text('Not selected');
            
            // Clear all tab content
            $('#goalTableBody').html('<tr><td colspan="7" class="text-center text-muted">Select a customer to view goals</td></tr>');
            $('#goalsPagination').empty();
            $('#folioTableBody').html('<tr><td colspan="9" class="text-center text-muted">Select a customer to view folios</td></tr>');
            $('#goalStatusContainer').html('<div class="text-center text-muted py-5">Select a customer to view goal status</div>');
            $('#exportButtons').hide();
            
            // Clear status messages
            $('#status1').empty();
            $('#goalsStatus').empty();
            $('#categoriesStatus').empty();
            
            // Hide any visible dropdowns
            $('[id^="dropdown_"]').hide();
            
            // Show success message
            showStatus('status1', 'Customer selection cleared', 'success');
        }
        
        // ==================== CUSTOMER SELECTION ====================
        
        function selectCustomer(event) {
            if (event) event.preventDefault();
            const ucc = $('#custUCC').val().trim();
            if (!ucc) {
                showStatus('status1', 'Please enter a UCC', 'danger');
                return;
            }
            
            // Validate customer
            $.post('getInvesteddata.php', { method: 'getUCCName', UCC: ucc }, function(response) {
                if (response && response !== false && response !== 'false' && response !== 'null') {
                    currentUCC = ucc;
                    currentCustomerName = response;
                    $('#selectedCustomerName').text(response);
                    showStatus('status1', `Customer selected: ${response}`, 'success');
                    
                    // Load data for all tabs
                    loadGoals();
                    loadFolioData();
                    loadGoalStatus();
                } else {
                    showStatus('status1', `Error: UCC ${ucc} does not exist!`, 'danger');
                    currentUCC = '';
                    $('#selectedCustomerName').text('Not selected');
                }
            }, 'json').fail(function() {
                showStatus('status1', 'Error validating customer', 'danger');
            });
        }
        
        // ==================== TAB NAVIGATION ====================
        
        $('[data-tab]').click(function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            
            // Update active state
            $('[data-tab]').removeClass('active');
            $(this).addClass('active');
            
            // Hide all tab contents
            $('.tab-content').hide();
            
            // Show selected tab
            if (tab === 'goals') $('#goalsTab').show();
            else if (tab === 'assign') $('#assignTab').show();
            else if (tab === 'status') $('#statusTab').show();
            else if (tab === 'categories') $('#categoriesTab').show();
            
            // Refresh data if customer selected
            if (currentUCC) {
                if (tab === 'goals') loadGoals();
                else if (tab === 'assign') loadFolioData();
                else if (tab === 'status') loadGoalStatus();
                else if (tab === 'categories') loadCategories();
            } else if (tab !== 'categories') {
                showStatus('status1', 'Please select a customer first', 'warning');
            }
            
            if (tab === 'categories') loadCategories();
        });
        
        // ==================== GOALS MANAGEMENT ====================
        
        function loadGoals(page = 1) {
            if (!currentUCC) return;
            
            currentPage = page;
            $('#goalTableBody').html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading goals...</td></tr>');
            
            $.get(`goal.php?action=fetchGoals&ucc=${encodeURIComponent(currentUCC)}&page=${page}`, function(response) {
                if (response.success) {
                    const tbody = $('#goalTableBody');
                    tbody.empty();
                    
                    if (response.data && response.data.length > 0) {
                        response.data.forEach(goal => {
                            tbody.append(`
                                <tr>
                                    <td>${goal.id}</td>
                                    <td>${escapeHtml(goal.name)}</td>
                                    <td>${escapeHtml(goal.category_name)}</td>
                                    <td>₹${formatIndianNumber(goal.amount_required)}</td>
                                    <td>${formatIndianDate(goal.goal_date)}</td>
                                    <td>${escapeHtml(goal.remarks)}</td>
                                    <td class="action-buttons">
                                        <button class="btn btn-warning btn-sm" onclick="editGoal(${goal.id}, '${escapeHtml(goal.name)}', ${goal.category_id}, ${goal.amount_required}, '${goal.goal_date}', '${escapeHtml(goal.remarks)}')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteGoal(${goal.id})">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                        
                        // Pagination
                        totalGoalPages = response.totalPages;
                        let paginationHtml = '<nav><ul class="pagination">';
                        for (let i = 1; i <= totalGoalPages; i++) {
                            paginationHtml += `<li class="page-item ${i === page ? 'active' : ''}"><button class="page-link" onclick="loadGoals(${i})">${i}</button></li>`;
                        }
                        paginationHtml += '</ul></nav>';
                        $('#goalsPagination').html(paginationHtml);
                    } else {
                        tbody.html('<tr><td colspan="7" class="text-center text-muted">No goals found</td></tr>');
                        $('#goalsPagination').empty();
                    }
                } else {
                    $('#goalTableBody').html(`<tr><td colspan="7" class="text-center text-danger">${response.message || 'Error loading goals'}</td></tr>`);
                }
            }).fail(function() {
                $('#goalTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading goals</td></tr>');
            });
        }
        
        function loadCategoriesForDropdown() {
            // Use a large limit to get all categories for the dropdown
            $.get('goal.php?action=fetchCategories&page=1&all=1', function(response) {
                if (response.success && response.data) {
                    const dropdown = $('#goal_category');
                    dropdown.empty().append('<option value="">Select Category</option>');
                    response.data.forEach(cat => {
                        dropdown.append(`<option value="${cat.id}">${escapeHtml(cat.name)}</option>`);
                    });
                    // If there are more pages, load them
                    if (response.totalPages > 1) {
                        for (let p = 2; p <= response.totalPages; p++) {
                            $.get(`goal.php?action=fetchCategories&page=${p}`, function(r) {
                                if (r.success && r.data) {
                                    r.data.forEach(cat => {
                                        dropdown.append(`<option value="${cat.id}">${escapeHtml(cat.name)}</option>`);
                                    });
                                }
                            });
                        }
                    }
                }
            });
        }
        
        function showAddGoalModal() {
            if (!currentUCC) {
                showStatus('goalsStatus', 'Please select a customer first', 'warning');
                return;
            }
            $('#goalModalTitle').text('Add Goal');
            $('#goalForm')[0].reset();
            $('#goal_edit_id').val('');
            $('#goalModal').modal('show');
        }
        
        function editGoal(id, name, categoryId, amount, date, remarks) {
            $('#goalModalTitle').text('Edit Goal');
            $('#goal_edit_id').val(id);
            $('#goal_name').val(name);
            $('#goal_category').val(categoryId);
            $('#amount_required').val(amount);
            $('#on_date').val(date);
            $('#goal_remarks').val(remarks);
            $('#goalModal').modal('show');
        }
        
        function saveGoal() {
            const formData = $('#goalForm').serialize();
            $.post('goal.php', formData + `&saveGoal=1&ucc=${encodeURIComponent(currentUCC)}`, function(response) {
                if (response.success) {
                    $('#goalModal').modal('hide');
                    showStatus('goalsStatus', response.message, 'success');
                    loadGoals(currentPage);
                } else {
                    showStatus('goalsStatus', response.message, 'danger');
                }
            }, 'json').fail(function() {
                showStatus('goalsStatus', 'Error saving goal', 'danger');
            });
        }
        
        function deleteGoal(goalId) {
            if (!confirm('Are you sure you want to delete this goal?')) return;
            
            $.post('goal.php', { deleteGoal: 1, goal_id: goalId, ucc: currentUCC }, function(response) {
                if (response.success) {
                    showStatus('goalsStatus', response.message, 'success');
                    loadGoals(currentPage);
                } else {
                    showStatus('goalsStatus', response.message, 'danger');
                }
            }, 'json');
        }
        
        // ==================== GOAL ASSIGNMENT ====================
        
        function loadFolioData() {
            if (!currentUCC) return;
            
            $('#folioTableBody').html('<tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading portfolio data...</td></tr>');
            
            // Fetch portfolio data
            $.ajax({
                url: "uccfoliodata.php",
                type: "POST",
                dataType: "json",
                data: { ucc: currentUCC },
                success: function(response) {
                    folioDataCache = response;
                    fetchAssignedGoals();
                },
                error: function() {
                    $('#folioTableBody').html('<tr><td colspan="9" class="text-center text-danger">Failed to fetch portfolio data</td></tr>');
                }
            });
        }
        
        function fetchAssignedGoals() {
            $.ajax({
                url: "goal.php",
                type: "POST",
                dataType: "json",
                data: { fetchAssignedGoals: 1, ucc: currentUCC },
                success: function(response) {
                    assignedGoalsCache = response.success ? response.data : [];
                    renderFolioTable();
                },
                error: function() {
                    assignedGoalsCache = [];
                    renderFolioTable();
                }
            });
        }
        
        function renderFolioTable() {
            if (!folioDataCache) return;
            
            const tbody = $('#folioTableBody');
            tbody.empty();
            
            let hasData = false;
            
            // Helper to create goal cells
            function buildGoalCells(amcCode, fundCode, fundName, assetClass, investmentType) {
                const assigned = assignedGoalsCache ? assignedGoalsCache.find(g => g.amc_code === amcCode && g.fund_code === fundCode) : null;
                const assignedHtml = assigned ? `<div class="goal-details"><strong>${escapeHtml(assigned.goal_name)}</strong><br><small>₹${formatIndianNumber(assigned.amount_required)} by ${formatIndianDate(assigned.goal_date)}</small></div>` : '';
                const buttonText = assigned ? "Change Goal" : "Add Goal";
                const buttonClass = assigned ? "btn-warning" : "btn-success";
                
                return `
                    <td>${assignedHtml}</td>
                    <td>
                        <button class="btn ${buttonClass} btn-sm add-goal-btn" onclick="showGoalDropdown('${amcCode}', '${fundCode}', '${escapeHtml(fundName)}', '${escapeHtml(assetClass)}', '${investmentType}')">
                            ${buttonText}
                        </button>
                        <select id="dropdown_${amcCode}_${fundCode}" class="form-select form-select-sm mt-2" style="display:none; width:150px;" onchange="assignGoal('${investmentType}', '${amcCode}', '${fundCode}', '${escapeHtml(assetClass)}', '${escapeHtml(fundName)}', this.value)">
                            <option value="">Select Goal</option>
                        </select>
                    </td>
                `;
            }
            
            // Process Equity/MF Holdings
            if (folioDataCache.portfolioInfo && Object.keys(folioDataCache.portfolioInfo).length > 0) {
                tbody.append('<tr class="table-secondary"><td colspan="9"><strong><i class="fas fa-chart-line me-2"></i>Equity / Mutual Fund Holdings</strong></td></tr>');
                
                Object.keys(folioDataCache.portfolioInfo).forEach(amcCode => {
                    const folioData = folioDataCache.portfolioInfo[amcCode];
                    if (Array.isArray(folioData)) {
                        folioData.forEach(fundObject => {
                            const fundCode = Object.keys(fundObject)[0];
                            const item = fundObject[fundCode];
                            if (item) {
                                hasData = true;
                                tbody.append(`
                                    <tr>
                                        <td>${escapeHtml(item.Fund_Name || 'N/A')}</td>
                                        <td>${escapeHtml(item.Asset_Class || 'N/A')}</td>
                                        <td>${item.Unit_Holdings || 'N/A'}</td>
                                        <td>₹${formatIndianNumber(item.Cost_Value)}</td>
                                        <td>${item.Current_NAV || 'N/A'}</td>
                                        <td>₹${formatIndianNumber(item.Curr_Value)}</td>
                                        <td>${item.XIRR || 'N/A'}%</td>
                                        ${buildGoalCells(amcCode, fundCode, item.Fund_Name, item.Asset_Class, 'MF')}
                                    </tr>
                                `);
                            }
                        });
                    }
                });
            }
            
            // Process Bonds
            if (folioDataCache.HoldingInfoBonds && Object.keys(folioDataCache.HoldingInfoBonds).length > 0) {
                tbody.append('<tr class="table-secondary"><td colspan="9"><strong><i class="fas fa-chart-simple me-2"></i>Bonds Holdings</strong></td></tr>');
                
                Object.keys(folioDataCache.HoldingInfoBonds).forEach(amcCode => {
                    const bondData = folioDataCache.HoldingInfoBonds[amcCode];
                    if (Array.isArray(bondData)) {
                        bondData.forEach(bondObject => {
                            const fundCode = Object.keys(bondObject)[0];
                            const item = bondObject[fundCode];
                            if (item) {
                                hasData = true;
                                tbody.append(`
                                    <tr>
                                        <td>${escapeHtml(item.bondName || 'N/A')}</td>
                                        <td>${escapeHtml(item.assetClass || 'N/A')}</td>
                                        <td>${item.unitHoldings || 'N/A'}</td>
                                        <td>₹${formatIndianNumber(item.costValue)}</td>
                                        <td>${item.currentNAV || 'N/A'}</td>
                                        <td>₹${formatIndianNumber(item.currValue)}</td>
                                        <td>${item.XIRR || 'N/A'}%</td>
                                        ${buildGoalCells(amcCode, fundCode, item.bondName, item.assetClass, 'Bond')}
                                    </tr>
                                `);
                            }
                        });
                    }
                });
            }
            
            if (!hasData) {
                tbody.html('<tr><td colspan="9" class="text-center text-muted">No folio data available</td></tr>');
            }
        }
        
        function showGoalDropdown(amcCode, fundCode, fundName, assetClass, investmentType) {
            const dropdown = $(`#dropdown_${amcCode}_${fundCode}`);
            if (dropdown.is(':visible')) {
                dropdown.hide();
                return;
            }
            
            $.get(`goal.php?action=fetchGoalsDropdown&ucc=${encodeURIComponent(currentUCC)}`, function(response) {
                if (response.success && response.data) {
                    dropdown.empty().append('<option value="">Select Goal</option>');
                    response.data.forEach(goal => {
                        dropdown.append(`<option value="${goal.id}">${escapeHtml(goal.name)} (₹${formatIndianNumber(goal.amount_required)})</option>`);
                    });
                    dropdown.show();
                } else {
                    alert('No goals available. Please create a goal first.');
                }
            });
        }
        
        function assignGoal(investmentType, amcCode, fundCode, assetClass, fundName, goalId) {
            if (!goalId) return;
            
            $.post('goal.php', {
                assignGoal: 1,
                investment_type: investmentType,
                amc_code: amcCode,
                fundCode: fundCode,
                asset_class: assetClass,
                fund_name: fundName,
                goal_id: goalId,
                ucc: currentUCC
            }, function(response) {
                if (response.success) {
                    showStatus('assignTab .alert', 'Goal assigned successfully!', 'success');
                    fetchAssignedGoals();
                } else {
                    showStatus('assignTab .alert', response.message, 'danger');
                }
            }, 'json');
        }
        
        // ==================== GOAL STATUS ====================
        
        function loadGoalStatus() {
            if (!currentUCC) return;
            
            $('#goalStatusContainer').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i>Loading goal status...</div>');
            
            $.post('goal.php', { action: 'fetchGoalStatus', ucc: currentUCC }, function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    renderGoalStatus(response.data);
                    $('#exportButtons').show();
                } else {
                    $('#goalStatusContainer').html(`<div class="alert alert-info">${response.message || 'No goals assigned to this customer'}</div>`);
                    $('#exportButtons').hide();
                }
            }, 'json').fail(function() {
                $('#goalStatusContainer').html('<div class="alert alert-danger">Error loading goal status</div>');
                $('#exportButtons').hide();
            });
        }
        
        function renderGoalStatus(goals) {
            const container = $('#goalStatusContainer');
            container.empty();
            
            goals.forEach((goal, index) => {
                const isPastGoal = new Date(goal.goal_date) <= new Date();
                const percentAchieved = goal.percent_achieved;
                const percentFuture = goal.future_percent;
                
                let progressColor = 'bg-success';
                if (percentAchieved < 50) progressColor = 'bg-danger';
                else if (percentAchieved < 80) progressColor = 'bg-warning';
                
                let summaryHtml = '';
                if (isPastGoal) {
                    const shortfallAmount = Math.max(goal.amount_required - goal.total_current_value, 0);
                    if (shortfallAmount > 0) {
                        summaryHtml = `
                            <div class="shortfall-card">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Goal target date has passed (${formatIndianDate(goal.goal_date)}).</strong><br>
                                Achieved: ₹${formatIndianNumber(goal.total_current_value)} (${percentAchieved}%)<br>
                                Shortfall: <span class="text-danger">₹${formatIndianNumber(shortfallAmount)}</span>
                            </div>
                        `;
                    } else {
                        summaryHtml = `
                            <div class="success-card">
                                <i class="fas fa-trophy me-2"></i>
                                <strong>Goal achieved! 🎉</strong><br>
                                You met or exceeded your target of ₹${formatIndianNumber(goal.amount_required)}.
                            </div>
                        `;
                    }
                } else if (goal.total_current_value >= goal.amount_required) {
                    summaryHtml = `
                        <div class="success-card">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Goal already achieved!</strong><br>
                            Current value ₹${formatIndianNumber(goal.total_current_value)} exceeds target of ₹${formatIndianNumber(goal.amount_required)}.
                        </div>
                    `;
                } else if (goal.future_value >= goal.amount_required) {
                    summaryHtml = `
                        <div class="summary-card">
                            <i class="fas fa-chart-line me-2"></i>
                            <strong>On track to achieve goal!</strong><br>
                            Current: ₹${formatIndianNumber(goal.total_current_value)} (${percentAchieved}%)<br>
                            Projected value by ${formatIndianDate(goal.goal_date)}: ₹${formatIndianNumber(goal.future_value)} (${percentFuture}%)<br>
                            <span class="text-success">No additional SIP needed - stay invested!</span>
                        </div>
                    `;
                } else {
                    summaryHtml = `
                        <div class="shortfall-card">
                            <i class="fas fa-chart-line me-2"></i>
                            <strong>Need additional investments</strong><br>
                            Current: ₹${formatIndianNumber(goal.total_current_value)} (${percentAchieved}%)<br>
                            Projected: ₹${formatIndianNumber(goal.future_value)} (${percentFuture}%)<br>
                            Shortfall: <span class="text-danger">₹${formatIndianNumber(goal.shortfall)}</span><br>
                            ${goal.required_sip > 0 ? `<span class="text-primary">Suggested monthly SIP: ₹${formatIndianNumber(goal.required_sip)}</span>` : ''}
                        </div>
                    `;
                }
                
                // Build fund rows
                let fundRows = '';
                goal.funds.forEach(fund => {
                    fundRows += `
                        <tr>
                            <td>${escapeHtml(fund.fund_name)}</td>
                            <td>${fund.units}</td>
                            <td>₹${formatIndianNumber(fund.cost_value)}</td>
                            <td>${fund.nav}</td>
                            <td>₹${formatIndianNumber(fund.current_value)}</td>
                            <td>${fund.xirr}%</td>
                        </tr>
                    `;
                });
                
                const goalHtml = `
                    <div class="goal-section">
                        <div class="goal-header" onclick="toggleGoalBody(${index})">
                            <h5>
                                <span class="indicator" id="indicator_${index}">▶</span>
                                ${index + 1}. ${escapeHtml(goal.goal_name)}
                                <span class="badge-goal ms-2">${escapeHtml(goal.category_name)}</span>
                            </h5>
                        </div>
                        <div class="goal-body" id="goalBody_${index}" style="display:none;">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p><strong>🎯 Target:</strong> ₹${formatIndianNumber(goal.amount_required)} by ${formatIndianDate(goal.goal_date)}</p>
                                    <p><strong>📝 Remarks:</strong> ${escapeHtml(goal.remarks) || '—'}</p>
                                </div>
                                <div class="col-md-6">
                                    <div class="progress mb-2">
                                        <div class="progress-bar ${progressColor}" style="width: ${Math.min(percentAchieved, 100)}%">
                                            ${percentAchieved}% Achieved
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Scheme/Fund</th>
                                            <th>Units</th>
                                            <th>Cost Value</th>
                                            <th>NAV</th>
                                            <th>Current Value</th>
                                            <th>XIRR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${fundRows}
                                    </tbody>
                                </table>
                            </div>
                            
                            ${summaryHtml}
                        </div>
                    </div>
                `;
                
                container.append(goalHtml);
            });
        }
        
        function toggleGoalBody(index) {
            const body = $(`#goalBody_${index}`);
            const indicator = $(`#indicator_${index}`);
            
            if (body.is(':visible')) {
                body.slideUp();
                indicator.text('▶');
            } else {
                body.slideDown();
                indicator.text('▼');
            }
        }
        
        // ==================== CATEGORIES MANAGEMENT ====================
        
        function loadCategories(page = 1) {
            currentCategoryPage = page;
            $('#categoryTableBody').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading categories...</td></tr>');
            
            $.get(`goal.php?action=fetchCategories&page=${page}`, function(response) {
                if (response.success) {
                    const tbody = $('#categoryTableBody');
                    tbody.empty();
                    
                    if (response.data && response.data.length > 0) {
                        response.data.forEach(cat => {
                            tbody.append(`
                                <tr>
                                    <td>${cat.id}</td>
                                    <td>${escapeHtml(cat.name)}</td>
                                    <td>${escapeHtml(cat.description)}</td>
                                    <td>${formatIndianDate(cat.created_at)}</td>
                                    <td class="action-buttons">
                                        <button class="btn btn-warning btn-sm" onclick="editCategory(${cat.id}, '${escapeHtml(cat.name)}', '${escapeHtml(cat.description)}')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteCategory(${cat.id})">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            `);
                        });
                        
                        // Pagination
                        let paginationHtml = '<nav><ul class="pagination">';
                        for (let i = 1; i <= response.totalPages; i++) {
                            paginationHtml += `<li class="page-item ${i === page ? 'active' : ''}"><button class="page-link" onclick="loadCategories(${i})">${i}</button></li>`;
                        }
                        paginationHtml += '</ul></nav>';
                        $('#categoriesPagination').html(paginationHtml);
                    } else {
                        tbody.html('<tr><td colspan="5" class="text-center text-muted">No categories found</td></tr>');
                        $('#categoriesPagination').empty();
                    }
                }
            }).fail(function() {
                $('#categoryTableBody').html('<tr><td colspan="5" class="text-center text-danger">Error loading categories</td></tr>');
            });
        }
        
        function showAddCategoryModal() {
            $('#categoryModalTitle').text('Add Category');
            $('#categoryForm')[0].reset();
            $('#category_edit_id').val('');
            $('#categoryModal').modal('show');
        }
        
        function editCategory(id, name, description) {
            $('#categoryModalTitle').text('Edit Category');
            $('#category_edit_id').val(id);
            $('#category_name').val(name);
            $('#category_description').val(description);
            $('#categoryModal').modal('show');
        }
        
        function saveCategory() {
            const formData = $('#categoryForm').serialize();
            $.post('goal.php', formData + '&saveCategory=1', function(response) {
                if (response.success) {
                    $('#categoryModal').modal('hide');
                    showStatus('categoriesStatus', response.message, 'success');
                    loadCategories(currentCategoryPage);
                    loadCategoriesForDropdown();
                } else {
                    showStatus('categoriesStatus', response.message, 'danger');
                }
            }, 'json');
        }
        
        function deleteCategory(id) {
            if (!confirm('Are you sure you want to delete this category? This may affect existing goals.')) return;
            
            $.get(`goal.php?deleteCategory=${id}`, function(response) {
                if (response.success) {
                    showStatus('categoriesStatus', response.message, 'success');
                    loadCategories(currentCategoryPage);
                    loadCategoriesForDropdown();
                } else {
                    showStatus('categoriesStatus', response.message, 'danger');
                }
            });
        }
        
        // ==================== EXPORT FUNCTIONS ====================
        
        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            const container = $('#goalStatusContainer');
            
            container.find('.goal-section').each((index, section) => {
                const goalName = $(section).find('.goal-header h5').clone().children().remove().end().text().trim();
                const table = $(section).find('table');
                const summary = $(section).find('.summary-card, .shortfall-card, .success-card').text().trim();
                
                const wsData = [
                    [`Goal Status Report for ${currentCustomerName}`],
                    [goalName],
                    ['Summary'],
                    [summary],
                    [],
                    ...Array.from(table.find('tr')).map(row => Array.from($(row).find('th, td')).map(cell => $(cell).text().trim()))
                ];
                
                const ws = XLSX.utils.aoa_to_sheet(wsData);
                XLSX.utils.book_append_sheet(wb, ws, `Goal ${index + 1}`);
            });
            
            XLSX.writeFile(wb, `Goal_Status_${currentCustomerName.replace(/\s+/g, '_')}.xlsx`);
        }
        
        function exportToPDF() {
            const element = $('<div>').css({ fontSize: '12px', padding: '20px' });
            element.append(`<h2 style="text-align:center">Goal Status Report for ${currentCustomerName}</h2>`);
            
            $('#goalStatusContainer .goal-section').each(function() {
                const clone = $(this).clone();
                clone.find('.goal-body').show();
                element.append(clone);
            });
            
            html2pdf().from(element[0]).set({
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: `Goal_Status_${currentCustomerName.replace(/\s+/g, '_')}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            }).save();
        }
        
        // Autocomplete for customer UCC
        $(document).off('input', '#custUCC').on('input', '#custUCC', function() {
            const val = $(this).val().toLowerCase();
            const list = $('#autocomplete-list-custUCC');
            
            if (val.length < 2) {
                list.hide();
                return;
            }
            
            // Check if getUCClistwithname.php exists
            $.get('getUCClistwithname.php')
                .done(function(data) {
                    // Ensure data is parsed and is an array
                    if (typeof data === 'string') {
                        try { 
                            data = JSON.parse(data); 
                        } catch (e) { 
                            console.error('JSON Parse Error:', e); 
                            console.warn('Raw Server Response:', data);
                            data = []; 
                        }
                    }
                    if (!Array.isArray(data)) {
                        data = [];
                    }
                    
                    const matches = data.filter(entry => 
                        (entry.UCC && entry.UCC.toLowerCase().includes(val)) || 
                        (entry.name && entry.name.toLowerCase().includes(val))
                    );
                    
                    list.empty();
                    if (matches.length === 0) {
                        list.hide();
                        return;
                    }
                    
                    matches.forEach(entry => {
                        list.append(`<div onclick="selectAutocomplete('${entry.UCC}', '${escapeHtml(entry.name)}')">${entry.UCC} - ${escapeHtml(entry.name)}</div>`);
                    });
                    list.show();
                })
                .fail(function(err) {
                    console.error('Error loading customer list:', err);
                });
        });
        
        window.selectAutocomplete = function(ucc, name) {
            $('#custUCC').val(ucc);
            $('#autocomplete-list-custUCC').hide();
            selectCustomer();
        }
        
        // Close autocomplete on click outside
        $(document).off('click.custUCCAutocomplete').on('click.custUCCAutocomplete', function(e) {
            if (!$(e.target).closest('.autocomplete-container').length) {
                $('#autocomplete-list-custUCC').hide();
            }
        });
        
        // Initialize
        $(document).ready(function() {
            loadCategoriesForDropdown();
            loadCategories();
        });
    </script>