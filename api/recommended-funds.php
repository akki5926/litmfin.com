<?php
require_once __DIR__ . '/../../db/mfddb.php';
require_once __DIR__ . '/../../cors.php';

header('Content-Type: application/json');

try {
    $db = new MfdDb();
    
    // Get all recommended funds with details
    $funds = $db->getRecommendedFundsWithDetails();
    
    // Transform data to match frontend format
    $transformedFunds = [];
    
    foreach ($funds as $fund) {
        // Map category names based on your table structure
        $categoryNames = [
            1 => 'Aggressive Growth Portfolio',
            2 => 'Balanced Growth Portfolio',
            3 => 'Conservative Income Portfolio',
            4 => 'Stable Income Portfolio'
        ];
        
        // Determine risk level based on invest_type and fund_cat
        $riskLevel = $this->calculateRiskLevel($fund['invest_type'], $fund['fund_cat']);
        
        // Calculate random returns for demo (in real app, fetch from performance API)
        $oneYearReturn = $this->generateReturn($fund['invest_type'], '1Y');
        $threeYearReturn = $this->generateReturn($fund['invest_type'], '3Y');
        $fiveYearReturn = $this->generateReturn($fund['invest_type'], '5Y');
        
        // Determine sub-category based on fund_cat
        $subCategoryMap = [
            'Small-cap' => 'Small Cap',
            'Mid-cap' => 'Mid Cap',
            'Multi-cap' => 'Multi Cap',
            'Large-cap' => 'Large Cap',
            'Focussed' => 'Focused Fund',
            'Other Equities' => 'Equity',
            'Hybrid' => 'Dynamic Asset Allocation',
            'Credit Risk' => 'Corporate Bond',
            'Bond Fund' => 'Corporate Bond',
            'Other Debt Funds' => 'Debt'
        ];
        
        $transformedFund = [
            'id' => (int)$fund['recmd_id'],
            'name' => $fund['fund_name'] ?? 'Unknown Fund',
            'category' => ucfirst($fund['invest_type']),
            'subCategory' => $subCategoryMap[$fund['fund_cat']] ?? $fund['fund_cat'],
            'riskLevel' => $riskLevel,
            'oneYearReturn' => $oneYearReturn,
            'threeYearReturn' => $threeYearReturn,
            'fiveYearReturn' => $fiveYearReturn,
            'expenseRatio' => $this->generateExpenseRatio($fund['invest_type']),
            'rating' => round(4 + (rand(0, 10) / 10), 1), // Random rating 4.0-5.0
            'minInvestment' => 1000,
            'fundSize' => $this->generateFundSize(),
            'fundManager' => $fund['amc_name'] ?? 'Unknown AMC',
            'description' => $fund['recmd_rational'] ?? 'Expert recommended fund.',
            'tags' => $this->generateTags($fund['invest_type'], $fund['fund_cat']),
            'fundCode' => $fund['product_id'] ?? '',
            'allocation' => (int)$fund['allocation'],
            'categoryId' => (int)$fund['category_id'],
            'categoryName' => $categoryNames[$fund['category_id']] ?? 'Portfolio ' . $fund['category_id'],
            'investType' => $fund['invest_type'],
            'fundCat' => $fund['fund_cat'],
            'amcCode' => $fund['amc'],
            'amcName' => $fund['amc_name'] ?? '',
            'isin' => $fund['isin'] ?? '',
            'assetClass' => $fund['asset_class'] ?? '',
            'schemeType' => $fund['scheme_type'] ?? ''
        ];
        
        $transformedFunds[] = $transformedFund;
    }
    
    // Get categories summary
    $categories = $db->getRecommendedFundCategories();
    $categorySummary = [];
    
    foreach ($categories as $cat) {
        $categoryFunds = array_filter($transformedFunds, function($f) use ($cat) {
            return $f['categoryId'] == $cat['category_id'];
        });
        
        $categorySummary[] = [
            'id' => (int)$cat['category_id'],
            'name' => $categoryNames[$cat['category_id']] ?? 'Portfolio ' . $cat['category_id'],
            'investType' => $cat['invest_type'],
            'count' => count($categoryFunds),
            'totalAllocation' => array_sum(array_column($categoryFunds, 'allocation'))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'funds' => $transformedFunds,
            'categories' => $categorySummary,
            'stats' => $this->calculateStats($transformedFunds)
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch recommended funds',
        'message' => $e->getMessage()
    ]);
}

// Helper methods for data transformation
function calculateRiskLevel($investType, $fundCat) {
    $riskMap = [
        'equity' => [
            'Small-cap' => 5,
            'Mid-cap' => 4,
            'Other Equities' => 4,
            'Focussed' => 4,
            'Multi-cap' => 3,
            'Large-cap' => 3
        ],
        'debt' => [
            'Credit Risk' => 4,
            'Other Debt Funds' => 2,
            'Bond Fund' => 3
        ],
        'hybrid' => [
            'Hybrid' => 3
        ]
    ];
    
    return $riskMap[$investType][$fundCat] ?? 3;
}

function generateReturn($investType, $period) {
    $baseReturns = [
        'equity' => ['1Y' => 25, '3Y' => 18, '5Y' => 16],
        'debt' => ['1Y' => 8, '3Y' => 7, '5Y' => 6.5],
        'hybrid' => ['1Y' => 15, '3Y' => 12, '5Y' => 10]
    ];
    
    $base = $baseReturns[$investType][$period] ?? 10;
    $variation = rand(-500, 500) / 100; // +/- 5%
    
    return round($base + $variation, 1);
}

function generateExpenseRatio($investType) {
    $ratios = [
        'equity' => [0.78, 0.95],
        'debt' => [0.20, 0.45],
        'hybrid' => [0.85, 0.89]
    ];
    
    $range = $ratios[$investType] ?? [0.5, 1.0];
    return round($range[0] + (rand(0, 100) / 100) * ($range[1] - $range[0]), 2);
}

function generateFundSize() {
    $sizes = ['?5,245 Cr', '?12,780 Cr', '?28,450 Cr', '?42,150 Cr', '?33,245 Cr', '?71,250 Cr'];
    return $sizes[array_rand($sizes)];
}

function generateTags($investType, $fundCat) {
    $tags = [];
    
    if ($investType === 'equity') {
        $tags = ['Equity', 'Growth Focus'];
        if (strpos($fundCat, 'Small') !== false) $tags[] = 'High Growth';
        if (strpos($fundCat, 'Mid') !== false) $tags[] = 'Growth Potential';
        if ($fundCat === 'Focussed') $tags[] = 'Concentrated Portfolio';
    } elseif ($investType === 'debt') {
        $tags = ['Debt', 'Income Focus'];
        if ($fundCat === 'Credit Risk') $tags[] = 'High Yield';
        if ($fundCat === 'Bond Fund') $tags[] = 'Yield Carry';
    } elseif ($investType === 'hybrid') {
        $tags = ['Balanced', 'Auto Rebalancing'];
    }
    
    return array_slice($tags, 0, 3);
}

function calculateStats($funds) {
    if (empty($funds)) {
        return [
            'avg1YReturn' => 0,
            'avgRiskLevel' => 0,
            'avgExpenseRatio' => 0,
            'avgRating' => 0
        ];
    }
    
    $totalFunds = count($funds);
    
    return [
        'avg1YReturn' => round(array_sum(array_column($funds, 'oneYearReturn')) / $totalFunds, 1),
        'avgRiskLevel' => round(array_sum(array_column($funds, 'riskLevel')) / $totalFunds, 1),
        'avgExpenseRatio' => round(array_sum(array_column($funds, 'expenseRatio')) / $totalFunds, 2),
        'avgRating' => round(array_sum(array_column($funds, 'rating')) / $totalFunds, 1)
    ];
}
?>