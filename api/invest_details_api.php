<?php
/**
 * Investment Details API Endpoints
 * Handles investment-specific operations like portfolio, transactions, etc.
 */
// ---------------- CORS HEADERS ----------------
require_once __DIR__ . '/includes/cors.php';

function verifyJWT() {
    $headers = getallheaders();
    $token = null;
    
    // Get token from Authorization header
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    // Fallback to GET/POST parameter
    if (!$token && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    if (!$token && isset($_POST['token'])) {
        $token = $_POST['token'];
    }
    
    if (!$token) {
        sendResponse(false, 'Authentication token required', null, 401);
    }
    
    // Simple JWT verification (you should implement proper JWT validation)
    try {
        // Decode JWT
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            sendResponse(false, 'Invalid token format', null, 401);
        }
        
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        if (!$payload || !isset($payload['user_id'])) {
            sendResponse(false, 'Invalid token payload', null, 401);
        }
        
        // Verify expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            sendResponse(false, 'Token has expired', null, 401);
        }
        
        return $payload['user_id'];
        
    } catch (Exception $e) {
        sendResponse(false, 'Token verification failed: ' . $e->getMessage(), null, 401);
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Central routing
switch ($action) {
    
    // Investment Portfolio Endpoints
    case 'get_portfolio':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetPortfolio($db, $userId);
        break;
        
    case 'get_portfolio_summary':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetPortfolioSummary($db, $userId);
        break;
        
    case 'get_holdings':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetHoldings($db, $userId);
        break;
        
    // Transaction History Endpoints
    case 'get_transactions':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $type = $_GET['type'] ?? 'all'; // all, purchase, redemption, dividend
        $fromDate = $_GET['from_date'] ?? '';
        $toDate = $_GET['to_date'] ?? '';
        handleGetTransactions($db, $userId, $type, $fromDate, $toDate);
        break;
        
    case 'get_transaction_by_id':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $transactionId = $_GET['transaction_id'] ?? '';
        handleGetTransactionById($db, $userId, $transactionId);
        break;
        
    // Fund Details Endpoints
    case 'get_fund_details':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $fundCode = $_GET['fund_code'] ?? '';
        handleGetFundDetails($db, $fundCode);
        break;
        
    case 'search_funds':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $query = $_GET['query'] ?? '';
        $category = $_GET['category'] ?? '';
        handleSearchFunds($db, $query, $category);
        break;
        
    case 'get_fund_performance':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $fundCode = $_GET['fund_code'] ?? '';
        $period = $_GET['period'] ?? '1y'; // 1m, 3m, 6m, 1y, 3y, 5y
        handleGetFundPerformance($db, $fundCode, $period);
        break;
        
    // Investment Actions Endpoints
    case 'create_investment':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $input = json_decode(file_get_contents('php://input'), true);
        handleCreateInvestment($db, $userId, $input);
        break;
        
    case 'create_sip':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $input = json_decode(file_get_contents('php://input'), true);
        handleCreateSIP($db, $userId, $input);
        break;
        
    case 'cancel_sip':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $sipId = $_GET['sip_id'] ?? '';
        handleCancelSIP($db, $userId, $sipId);
        break;
        
    case 'redeem_investment':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $input = json_decode(file_get_contents('php://input'), true);
        handleRedeemInvestment($db, $userId, $input);
        break;
        
    case 'switch_investment':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $input = json_decode(file_get_contents('php://input'), true);
        handleSwitchInvestment($db, $userId, $input);
        break;
        
    // SIP Management Endpoints
    case 'get_active_sips':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetActiveSIPs($db, $userId);
        break;
        
    case 'get_sip_history':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetSIPHistory($db, $userId);
        break;
        
    // Watchlist Endpoints
    case 'add_to_watchlist':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $input = json_decode(file_get_contents('php://input'), true);
        handleAddToWatchlist($db, $userId, $input);
        break;
        
    case 'remove_from_watchlist':
        if ($method !== 'DELETE') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $fundCode = $_GET['fund_code'] ?? '';
        handleRemoveFromWatchlist($db, $userId, $fundCode);
        break;
        
    case 'get_watchlist':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetWatchlist($db, $userId);
        break;
        
    // Dashboard & Analytics
    case 'get_investment_summary':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetInvestmentSummary($db, $userId);
        break;
        
    case 'get_asset_allocation':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetAssetAllocation($db, $userId);
        break;
        
    case 'get_performance_chart':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $period = $_GET['period'] ?? '1y';
        handleGetPerformanceChart($db, $userId, $period);
        break;
        
    // Fund Category Navigation
    case 'get_funds_by_category':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $category = $_GET['category'] ?? '';
        handleGetFundsByCategory($db, $category);
        break;
        
    case 'get_top_performing_funds':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $category = $_GET['category'] ?? '';
        $limit = $_GET['limit'] ?? 10;
        handleGetTopPerformingFunds($db, $category, $limit);
        break;
        
    // Reports & Statements
    case 'generate_portfolio_statement':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $input = json_decode(file_get_contents('php://input'), true);
        handleGeneratePortfolioStatement($db, $userId, $input);
        break;
        
    case 'generate_tax_statement':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $financialYear = $_GET['financial_year'] ?? date('Y');
        handleGenerateTaxStatement($db, $userId, $financialYear);
        break;
        
    // Investment Goals
    case 'create_investment_goal':
        if ($method !== 'POST') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $input = json_decode(file_get_contents('php://input'), true);
        handleCreateInvestmentGoal($db, $userId, $input);
        break;
        
    case 'update_investment_goal':
        if ($method !== 'PUT') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $input = json_decode(file_get_contents('php://input'), true);
        handleUpdateInvestmentGoal($db, $userId, $input);
        break;
        
    case 'get_investment_goals':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        handleGetInvestmentGoals($db, $userId);
        break;
        
    case 'delete_investment_goal':
        if ($method !== 'DELETE') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $userId = verifyJWT();
        $goalId = $_GET['goal_id'] ?? '';
        handleDeleteInvestmentGoal($db, $userId, $goalId);
        break;
        
    // Market Data
    case 'get_market_update':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        handleGetMarketUpdate($db);
        break;
        
    case 'get_nav_updates':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        $fundCodes = $_GET['fund_codes'] ?? '';
        handleGetNAVUpdates($db, $fundCodes);
        break;
        
    // Test endpoint (no auth required)
    case 'test':
        if ($method !== 'GET') {
            sendResponse(false, 'Method not allowed', null, 405);
        }
        sendResponse(true, 'Investment API is running', [
            'version' => '1.0.0',
            'endpoints' => [
                'portfolio' => ['get_portfolio', 'get_portfolio_summary', 'get_holdings'],
                'transactions' => ['get_transactions', 'get_transaction_by_id'],
                'funds' => ['get_fund_details', 'search_funds', 'get_fund_performance'],
                'investments' => ['create_investment', 'create_sip', 'redeem_investment', 'switch_investment'],
                'sip' => ['get_active_sips', 'get_sip_history', 'cancel_sip'],
                'watchlist' => ['add_to_watchlist', 'remove_from_watchlist', 'get_watchlist'],
                'analytics' => ['get_investment_summary', 'get_asset_allocation', 'get_performance_chart'],
                'reports' => ['generate_portfolio_statement', 'generate_tax_statement'],
                'goals' => ['create_investment_goal', 'get_investment_goals', 'update_investment_goal', 'delete_investment_goal'],
                'market' => ['get_market_update', 'get_nav_updates']
            ]
        ]);
        break;
        
    default:
        sendResponse(
            false,
            'Invalid action. Available actions: ' . 
            'get_portfolio, get_portfolio_summary, get_holdings, ' .
            'get_transactions, get_transaction_by_id, ' .
            'get_fund_details, search_funds, get_fund_performance, ' .
            'create_investment, create_sip, redeem_investment, switch_investment, ' .
            'get_active_sips, get_sip_history, cancel_sip, ' .
            'add_to_watchlist, remove_from_watchlist, get_watchlist, ' .
            'get_investment_summary, get_asset_allocation, get_performance_chart, ' .
            'get_funds_by_category, get_top_performing_funds, ' .
            'generate_portfolio_statement, generate_tax_statement, ' .
            'create_investment_goal, get_investment_goals, update_investment_goal, delete_investment_goal, ' .
            'get_market_update, get_nav_updates, test',
            null,
            404
        );
}

/**
 * Get user's investment portfolio
 */
function handleGetPortfolio(MfdDb $db, int $userId): void {
    try {
        // Get user details first
        $userSql = "SELECT CLIENT_CODE, PRIMARY_HOLDER_PAN FROM users WHERE id = ?";
        $userStmt = $db->getPdo()->prepare($userSql);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        if (!$user) {
            sendResponse(false, 'User not found');
            return;
        }
        
        // Get portfolio holdings (this is a placeholder - you'll need to implement actual portfolio logic)
        $portfolio = [
            'client_code' => $user['CLIENT_CODE'],
            'pan' => $user['PRIMARY_HOLDER_PAN'],
            'total_investment' => 0,
            'current_value' => 0,
            'total_gain_loss' => 0,
            'total_gain_loss_percentage' => 0,
            'holdings' => []
        ];
        
        // You would typically join with your investment tables here
        // Example: SELECT * FROM investments WHERE user_id = ? ORDER BY purchase_date DESC
        
        sendResponse(true, 'Portfolio retrieved successfully', $portfolio);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving portfolio: ' . $e->getMessage());
    }
}

/**
 * Get portfolio summary
 */
function handleGetPortfolioSummary(MfdDb $db, int $userId): void {
    try {
        // Placeholder implementation - you'll need to implement actual logic
        $summary = [
            'total_invested' => 150000.00,
            'current_value' => 165000.00,
            'total_gain_loss' => 15000.00,
            'total_gain_loss_percentage' => 10.00,
            'today_gain_loss' => 1250.00,
            'today_gain_loss_percentage' => 0.76,
            'sip_active' => 3,
            'sip_monthly_amount' => 15000.00,
            'holdings_count' => 8
        ];
        
        sendResponse(true, 'Portfolio summary retrieved successfully', $summary);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving portfolio summary: ' . $e->getMessage());
    }
}

/**
 * Get detailed holdings
 */
function handleGetHoldings(MfdDb $db, int $userId): void {
    try {
        // Placeholder implementation
        $holdings = [
            [
                'id' => 1,
                'fund_code' => 'MF001',
                'fund_name' => 'Large Cap Equity Fund',
                'units' => 100.50,
                'average_nav' => 45.50,
                'total_invested' => 4572.75,
                'current_nav' => 50.25,
                'current_value' => 5045.13,
                'gain_loss' => 472.38,
                'gain_loss_percentage' => 10.33,
                'category' => 'Equity',
                'sub_category' => 'Large Cap'
            ],
            [
                'id' => 2,
                'fund_code' => 'MF002',
                'fund_name' => 'Debt Fund',
                'units' => 200.00,
                'average_nav' => 25.00,
                'total_invested' => 5000.00,
                'current_nav' => 26.50,
                'current_value' => 5300.00,
                'gain_loss' => 300.00,
                'gain_loss_percentage' => 6.00,
                'category' => 'Debt',
                'sub_category' => 'Liquid'
            ]
        ];
        
        sendResponse(true, 'Holdings retrieved successfully', $holdings);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving holdings: ' . $e->getMessage());
    }
}

/**
 * Get transaction history
 */
function handleGetTransactions(MfdDb $db, int $userId, string $type, string $fromDate, string $toDate): void {
    try {
        // Build query based on parameters
        $queryParams = [$userId];
        $whereClause = "user_id = ?";
        
        if ($type !== 'all') {
            $whereClause .= " AND transaction_type = ?";
            $queryParams[] = $type;
        }
        
        if ($fromDate) {
            $whereClause .= " AND transaction_date >= ?";
            $queryParams[] = $fromDate;
        }
        
        if ($toDate) {
            $whereClause .= " AND transaction_date <= ?";
            $queryParams[] = $toDate;
        }
        
        // Placeholder - implement actual transaction query
        $transactions = [
            [
                'id' => 1,
                'transaction_id' => 'TXN20231215001',
                'date' => '2023-12-15',
                'type' => 'purchase',
                'fund_code' => 'MF001',
                'fund_name' => 'Large Cap Equity Fund',
                'units' => 100.50,
                'nav' => 45.50,
                'amount' => 4572.75,
                'status' => 'completed'
            ],
            [
                'id' => 2,
                'transaction_id' => 'TXN20231210001',
                'date' => '2023-12-10',
                'type' => 'sip',
                'fund_code' => 'MF002',
                'fund_name' => 'Debt Fund',
                'units' => 200.00,
                'nav' => 25.00,
                'amount' => 5000.00,
                'status' => 'completed'
            ]
        ];
        
        sendResponse(true, 'Transactions retrieved successfully', $transactions);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving transactions: ' . $e->getMessage());
    }
}

/**
 * Get fund details
 */
function handleGetFundDetails(MfdDb $db, string $fundCode): void {
    try {
        if (!$fundCode) {
            sendResponse(false, 'Fund code is required');
            return;
        }
        
        $fund = $db->getMfMasterByCode($fundCode);
        
        if (!$fund) {
            sendResponse(false, 'Fund not found');
            return;
        }
        
        // Format the response
        $fundDetails = [
            'fund_code' => $fund['product_code'] ?? '',
            'isin' => $fund['isin'] ?? '',
            'fund_name' => $fund['product_long_name'] ?? '',
            'short_name' => $fund['sub_fund_name'] ?? '',
            'amc_code' => $fund['amc_code'] ?? '',
            'category' => $fund['product_category'] ?? '',
            'asset_class' => $fund['asset_class'] ?? '',
            'sebi_asset_class' => $fund['sebi_asset_class'] ?? '',
            'scheme_type' => $fund['scheme_type'] ?? '',
            'plan_type' => $fund['plan_type'] ?? '',
            'option' => $fund['scheme_option'] ?? '',
            'sip_allowed' => $fund['sip_allowed'] ?? 'N',
            'swp_allowed' => $fund['swp_allowed'] ?? 'N',
            'stp_allowed' => $fund['stp_allowed'] ?? 'N',
            'reinvest_tag' => $fund['reinvest_tag'] ?? 'N',
            'purchase_allowed' => $fund['purchase_allowed'] ?? 'N',
            'redemption_allowed' => $fund['redemption_allowed'] ?? 'N',
            'switch_allowed' => $fund['switch_allowed'] ?? 'N',
            'face_value' => $fund['face_value'] ?? 10,
            'open_date' => $fund['open_date'] ?? '',
            'active_flag' => $fund['active_flag'] ?? 'N',
            'nfo_enabled' => $fund['nfo_enabled'] ?? 'N',
            'nfo_close_date' => $fund['nfo_close_date'] ?? '',
            'amfi_code' => $fund['amfi_code'] ?? '',
            'last_modified' => $fund['last_modified_date'] ?? ''
        ];
        
        sendResponse(true, 'Fund details retrieved successfully', $fundDetails);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving fund details: ' . $e->getMessage());
    }
}

/**
 * Search funds
 */
function handleSearchFunds(MfdDb $db, string $query, string $category): void {
    try {
        $sql = "SELECT * FROM tb_mf_master WHERE 1=1";
        $params = [];
        
        if ($query) {
            $sql .= " AND (product_long_name LIKE ? OR sub_fund_name LIKE ? OR product_code LIKE ? OR isin LIKE ?)";
            $searchTerm = "%{$query}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if ($category) {
            $sql .= " AND product_category = ?";
            $params[] = $category;
        }
        
        $sql .= " AND active_flag = 'Y' ORDER BY product_long_name LIMIT 50";
        
        $stmt = $db->getPdo()->prepare($sql);
        $stmt->execute($params);
        $funds = $stmt->fetchAll();
        
        // Format results
        $formattedFunds = [];
        foreach ($funds as $fund) {
            $formattedFunds[] = [
                'fund_code' => $fund['product_code'],
                'fund_name' => $fund['product_long_name'],
                'short_name' => $fund['sub_fund_name'],
                'category' => $fund['product_category'],
                'isin' => $fund['isin'],
                'sip_allowed' => $fund['sip_allowed'],
                'active_flag' => $fund['active_flag']
            ];
        }
        
        sendResponse(true, 'Fund search completed', [
            'count' => count($formattedFunds),
            'results' => $formattedFunds
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error searching funds: ' . $e->getMessage());
    }
}

/**
 * Get funds by category
 */
function handleGetFundsByCategory(MfdDb $db, string $category): void {
    try {
        // Get all categories if none specified
        if (!$category) {
            $categories = $db->getFundCategory();
            sendResponse(true, 'Fund categories retrieved', $categories);
            return;
        }
        
        // Get funds for specific category
        $sql = "SELECT * FROM tb_mf_master WHERE product_category = ? AND active_flag = 'Y' ORDER BY product_long_name";
        $stmt = $db->getPdo()->prepare($sql);
        $stmt->execute([$category]);
        $funds = $stmt->fetchAll();
        
        $formattedFunds = [];
        foreach ($funds as $fund) {
            $formattedFunds[] = [
                'fund_code' => $fund['product_code'],
                'fund_name' => $fund['product_long_name'],
                'short_name' => $fund['sub_fund_name'],
                'category' => $fund['product_category'],
                'isin' => $fund['isin'],
                'sip_allowed' => $fund['sip_allowed']
            ];
        }
        
        sendResponse(true, 'Funds retrieved by category', [
            'category' => $category,
            'count' => count($formattedFunds),
            'funds' => $formattedFunds
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error getting funds by category: ' . $e->getMessage());
    }
}

/**
 * Add fund to watchlist
 */
function handleAddToWatchlist(MfdDb $db, int $userId, array $data): void {
    try {
        if (!isset($data['fund_code']) || empty($data['fund_code'])) {
            sendResponse(false, 'Fund code is required');
            return;
        }
        
        // Check if fund exists
        $fund = $db->getMfMasterByCode($data['fund_code']);
        if (!$fund) {
            sendResponse(false, 'Fund not found');
            return;
        }
        
        // Check if already in watchlist
        $checkSql = "SELECT id FROM user_watchlist WHERE user_id = ? AND fund_code = ?";
        $checkStmt = $db->getPdo()->prepare($checkSql);
        $checkStmt->execute([$userId, $data['fund_code']]);
        
        if ($checkStmt->fetch()) {
            sendResponse(false, 'Fund already in watchlist');
            return;
        }
        
        // Add to watchlist
        $insertSql = "INSERT INTO user_watchlist (user_id, fund_code, created_at) VALUES (?, ?, NOW())";
        $insertStmt = $db->getPdo()->prepare($insertSql);
        $insertStmt->execute([$userId, $data['fund_code']]);
        
        sendResponse(true, 'Fund added to watchlist', [
            'watchlist_id' => $db->getPdo()->lastInsertId(),
            'fund_code' => $data['fund_code']
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error adding to watchlist: ' . $e->getMessage());
    }
}

/**
 * Get user's watchlist
 */
function handleGetWatchlist(MfdDb $db, int $userId): void {
    try {
        // You need to create the user_watchlist table first
        $sql = "SELECT w.*, m.product_long_name, m.sub_fund_name, m.product_category 
                FROM user_watchlist w 
                JOIN tb_mf_master m ON w.fund_code = m.product_code 
                WHERE w.user_id = ? 
                ORDER BY w.created_at DESC";
        
        $stmt = $db->getPdo()->prepare($sql);
        $stmt->execute([$userId]);
        $watchlist = $stmt->fetchAll();
        
        sendResponse(true, 'Watchlist retrieved', $watchlist);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving watchlist: ' . $e->getMessage());
    }
}

/**
 * Create a new investment
 */
function handleCreateInvestment(MfdDb $db, int $userId, array $data): void {
    try {
        // Validate required fields
        $required = ['fund_code', 'amount', 'transaction_type'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                sendResponse(false, "Field '$field' is required");
                return;
            }
        }
        
        // Get user details
        $userSql = "SELECT CLIENT_CODE, PRIMARY_HOLDER_PAN FROM users WHERE id = ?";
        $userStmt = $db->getPdo()->prepare($userSql);
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        if (!$user) {
            sendResponse(false, 'User not found');
            return;
        }
        
        // Get fund details
        $fund = $db->getMfMasterByCode($data['fund_code']);
        if (!$fund) {
            sendResponse(false, 'Fund not found');
            return;
        }
        
        // Check if fund allows purchase
        if ($data['transaction_type'] === 'purchase' && $fund['purchase_allowed'] !== 'Y') {
            sendResponse(false, 'Purchase not allowed for this fund');
            return;
        }
        
        // Generate transaction ID
        $transactionId = 'TXN' . date('YmdHis') . rand(100, 999);
        
        // Calculate units (this would normally use current NAV)
        $nav = $data['nav'] ?? 10.00; // Default NAV, should fetch from NAV table
        $units = $data['amount'] / $nav;
        
        // Placeholder for actual investment creation
        // You would insert into your investments table here
        
        $investment = [
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'client_code' => $user['CLIENT_CODE'],
            'fund_code' => $data['fund_code'],
            'fund_name' => $fund['product_long_name'],
            'transaction_type' => $data['transaction_type'],
            'amount' => $data['amount'],
            'nav' => $nav,
            'units' => $units,
            'transaction_date' => date('Y-m-d H:i:s'),
            'status' => 'pending', // Would move to 'completed' after payment confirmation
            'reference_number' => $data['reference_number'] ?? ''
        ];
        
        sendResponse(true, 'Investment created successfully', $investment);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error creating investment: ' . $e->getMessage());
    }
}

// Placeholder implementations for other handlers
function handleGetTransactionById(MfdDb $db, int $userId, string $transactionId): void {
    sendResponse(true, 'Transaction details placeholder', ['transaction_id' => $transactionId]);
}

function handleGetFundPerformance(MfdDb $db, string $fundCode, string $period): void {
    sendResponse(true, 'Fund performance placeholder', ['fund_code' => $fundCode, 'period' => $period]);
}

function handleCreateSIP(MfdDb $db, int $userId, array $data): void {
    sendResponse(true, 'SIP creation placeholder', $data);
}

function handleCancelSIP(MfdDb $db, int $userId, string $sipId): void {
    sendResponse(true, 'SIP cancellation placeholder', ['sip_id' => $sipId]);
}

function handleRedeemInvestment(MfdDb $db, int $userId, array $data): void {
    sendResponse(true, 'Redemption placeholder', $data);
}

function handleSwitchInvestment(MfdDb $db, int $userId, array $data): void {
    sendResponse(true, 'Switch placeholder', $data);
}

function handleGetActiveSIPs(MfdDb $db, int $userId): void {
    sendResponse(true, 'Active SIPs placeholder', []);
}

function handleGetSIPHistory(MfdDb $db, int $userId): void {
    sendResponse(true, 'SIP history placeholder', []);
}

function handleRemoveFromWatchlist(MfdDb $db, int $userId, string $fundCode): void {
    sendResponse(true, 'Remove from watchlist placeholder', ['fund_code' => $fundCode]);
}

function handleGetInvestmentSummary(MfdDb $db, int $userId): void {
    sendResponse(true, 'Investment summary placeholder', []);
}

function handleGetAssetAllocation(MfdDb $db, int $userId): void {
    sendResponse(true, 'Asset allocation placeholder', []);
}

function handleGetPerformanceChart(MfdDb $db, int $userId, string $period): void {
    sendResponse(true, 'Performance chart placeholder', ['period' => $period]);
}

function handleGetTopPerformingFunds(MfdDb $db, string $category, int $limit): void {
    sendResponse(true, 'Top performing funds placeholder', ['category' => $category, 'limit' => $limit]);
}

function handleGeneratePortfolioStatement(MfdDb $db, int $userId, array $data): void {
    sendResponse(true, 'Portfolio statement placeholder', $data);
}

function handleGenerateTaxStatement(MfdDb $db, int $userId, string $financialYear): void {
    sendResponse(true, 'Tax statement placeholder', ['financial_year' => $financialYear]);
}

function handleCreateInvestmentGoal(MfdDb $db, int $userId, array $data): void {
    sendResponse(true, 'Investment goal creation placeholder', $data);
}

function handleUpdateInvestmentGoal(MfdDb $db, int $userId, array $data): void {
    sendResponse(true, 'Investment goal update placeholder', $data);
}

function handleGetInvestmentGoals(MfdDb $db, int $userId): void {
    sendResponse(true, 'Investment goals placeholder', []);
}

function handleDeleteInvestmentGoal(MfdDb $db, int $userId, string $goalId): void {
    sendResponse(true, 'Investment goal deletion placeholder', ['goal_id' => $goalId]);
}

function handleGetMarketUpdate(MfdDb $db): void {
    sendResponse(true, 'Market update placeholder', []);
}

function handleGetNAVUpdates(MfdDb $db, string $fundCodes): void {
    sendResponse(true, 'NAV updates placeholder', ['fund_codes' => $fundCodes]);
}

?>
