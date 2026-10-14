<?php
/**
 * Tax Saver Funds API Endpoint
 */
require_once __DIR__ . '/includes/cors.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Only GET method is allowed
if ($method !== 'GET') {
    sendResponse(false, 'Method not allowed. Only GET is supported.', null, 405);
}

switch ($action) {
    case 'get_recommended_tax_saver':
        handleGetRecommendedTaxSaver($db);
        break;
    
    case 'get_tax_saver_by_amc':
        $amc_code = $_GET['amc_code'] ?? '';
        if (empty($amc_code)) {
            sendResponse(false, 'amc_code parameter is required');
        }
        handleGetTaxSaverByAmc($db, $amc_code);
        break;
    
    case 'get_tax_saver_by_product':
        $product_code = $_GET['product_code'] ?? '';
        if (empty($product_code)) {
            sendResponse(false, 'product_code parameter is required');
        }
        handleGetTaxSaverByProduct($db, $product_code);
        break;
    
    case 'get_tax_saver_by_amc_product':
        $amc_code = $_GET['amc_code'] ?? '';
        $product_code = $_GET['product_code'] ?? '';
        if (empty($amc_code) || empty($product_code)) {
            sendResponse(false, 'Both amc_code and product_code parameters are required');
        }
        handleGetTaxSaverByAmcProduct($db, $amc_code, $product_code);
        break;
    
    case 'get_tax_saver_with_details':
        handleGetTaxSaverWithDetails($db);
        break;
    
    case 'get_amc_list':
        handleGetAmcList($db);
        break;
    
    case 'get_product_categories':
        handleGetProductCategories($db);
        break;
    
    default:
        sendResponse(false, 'Invalid action. Available actions: get_recommended_tax_saver, get_tax_saver_by_amc, get_tax_saver_by_product, get_tax_saver_by_amc_product, get_tax_saver_with_details, get_amc_list, get_product_categories');
}

/**
 * Get all recommended tax saver funds
 */
function handleGetRecommendedTaxSaver(MfdDb $db): void {
    try {
        $funds = $db->getTaxSaverFunds();
        
        if (empty($funds)) {
            sendResponse(true, 'No tax saver funds found', []);
            return;
        }
        
        // Enhance with additional details from mtb_scheme_master
        $enhancedFunds = [];
        foreach ($funds as $fund) {
            $enhancedFund = enhanceTaxSaverFundWithScheme($db, $fund);
            $enhancedFunds[] = $enhancedFund;
        }
        
        sendResponse(true, 'Recommended tax saver funds retrieved successfully', $enhancedFunds);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving tax saver funds: ' . $e->getMessage());
    }
}

/**
 * Get tax saver funds by AMC code
 */
function handleGetTaxSaverByAmc(MfdDb $db, string $amc_code): void {
    try {
        $funds = $db->getTaxSaverFundsByAmc($amc_code);
        
        if (empty($funds)) {
            sendResponse(true, "No tax saver funds found for AMC code: {$amc_code}", []);
            return;
        }
        
        // Enhance with additional details from mtb_scheme_master
        $enhancedFunds = [];
        foreach ($funds as $fund) {
            $enhancedFund = enhanceTaxSaverFundWithScheme($db, $fund);
            $enhancedFunds[] = $enhancedFund;
        }
        
        sendResponse(true, "Tax saver funds for AMC code {$amc_code} retrieved successfully", $enhancedFunds);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving tax saver funds: ' . $e->getMessage());
    }
}

/**
 * Get tax saver fund by product code (scheme_code)
 */
function handleGetTaxSaverByProduct(MfdDb $db, string $product_code): void {
    try {
        // Get all tax saver funds and filter by scheme_code
        $allFunds = $db->getTaxSaverFunds();
        $filteredFunds = [];
        
        foreach ($allFunds as $fund) {
            if ($fund['scheme_code'] === $product_code) {
                $filteredFunds[] = $fund;
            }
        }
        
        if (empty($filteredFunds)) {
            sendResponse(true, "No tax saver funds found for product code: {$product_code}", []);
            return;
        }
        
        // Enhance with additional details from mtb_scheme_master
        $enhancedFunds = [];
        foreach ($filteredFunds as $fund) {
            $enhancedFund = enhanceTaxSaverFundWithScheme($db, $fund);
            $enhancedFunds[] = $enhancedFund;
        }
        
        sendResponse(true, "Tax saver funds for product code {$product_code} retrieved successfully", $enhancedFunds);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving tax saver funds: ' . $e->getMessage());
    }
}

/**
 * Get specific tax saver fund by AMC and product code (scheme_code)
 */
function handleGetTaxSaverByAmcProduct(MfdDb $db, string $amc_code, string $product_code): void {
    try {
        $fund = $db->getTaxSaverDetails($amc_code, $product_code);
        
        if (!$fund) {
            sendResponse(false, "No tax saver fund found for AMC code: {$amc_code} and product code: {$product_code}");
            return;
        }
        
        // Enhance with scheme details
        $enhancedFund = enhanceTaxSaverFundWithScheme($db, $fund);
        
        sendResponse(true, 'Tax saver fund details retrieved successfully', $enhancedFund);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving tax saver fund: ' . $e->getMessage());
    }
}

/**
 * Get tax saver funds with enhanced details (grouped by AMC)
 */
function handleGetTaxSaverWithDetails(MfdDb $db): void {
    try {
        $funds = $db->getTaxSaverFunds();
        
        if (empty($funds)) {
            sendResponse(true, 'No tax saver funds found', []);
            return;
        }
        
        // Group by AMC for better organization
        $groupedFunds = [];
        foreach ($funds as $fund) {
            $amc_code = $fund['amc_code'];
            if (!isset($groupedFunds[$amc_code])) {
                // Get AMC details
                $amcDetails = $db->getAmcMasterByCode($amc_code);
                $groupedFunds[$amc_code] = [
                    'amc_code' => $amc_code,
                    'amc_name' => $amcDetails['long_name'] ?? $amcDetails['LONG_NAME'] ?? 'Unknown AMC',
                    'funds' => []
                ];
            }
            
            $enhancedFund = enhanceTaxSaverFundWithScheme($db, $fund);
            $groupedFunds[$amc_code]['funds'][] = $enhancedFund;
        }
        
        // Convert to indexed array
        $result = array_values($groupedFunds);
        
        sendResponse(true, 'Tax saver funds with details retrieved successfully', $result);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving tax saver funds: ' . $e->getMessage());
    }
}

/**
 * Get list of AMCs with tax saver funds
 */
function handleGetAmcList(MfdDb $db): void {
    try {
        $funds = $db->getTaxSaverFunds();
        
        if (empty($funds)) {
            sendResponse(true, 'No tax saver funds found', []);
            return;
        }
        
        // Extract unique AMC codes
        $amcCodes = [];
        foreach ($funds as $fund) {
            if (!in_array($fund['amc_code'], $amcCodes)) {
                $amcCodes[] = $fund['amc_code'];
            }
        }
        
        // Get AMC details
        $amcList = [];
        foreach ($amcCodes as $amcCode) {
            $amcDetails = $db->getAmcMasterByCode($amcCode);
            if ($amcDetails) {
                $amcList[] = [
                    'code' => $amcCode,
                    'details' => $amcDetails['long_name'] ?? $amcDetails['LONG_NAME'] ?? 'Unknown AMC',
                    'amc_id' => $amcDetails['amc_id'] ?? '',
                    'banned_country' => $amcDetails['banned_country'] ?? ''
                ];
            }
        }
        
        sendResponse(true, 'AMC list retrieved successfully', $amcList);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving AMC list: ' . $e->getMessage());
    }
}

/**
 * Get product categories for tax saver funds
 */
function handleGetProductCategories(MfdDb $db): void {
    try {
        $funds = $db->getTaxSaverFunds();
        
        if (empty($funds)) {
            sendResponse(true, 'No tax saver funds found', []);
            return;
        }
        
        // Extract unique scheme codes and get their categories
        $categories = [];
        foreach ($funds as $fund) {
            $schemeCode = $fund['scheme_code'];
            $schemeDetails = $db->getSchemeMasterByCode($schemeCode);
            
            if ($schemeDetails && !empty($schemeDetails['fund_cat'])) {
                $category = $schemeDetails['fund_cat'];
                if (!isset($categories[$category])) {
                    $categories[$category] = [
                        'category' => $category,
                        'count' => 0,
                        'funds' => []
                    ];
                }
                $categories[$category]['count']++;
                $categories[$category]['funds'][] = $schemeCode;
            }
        }
        
        // Convert to indexed array
        $result = array_values($categories);
        
        sendResponse(true, 'Product categories retrieved successfully', $result);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving product categories: ' . $e->getMessage());
    }
}

/**
 * Enhance tax saver fund with details from mtb_scheme_master
 */
function enhanceTaxSaverFundWithScheme(MfdDb $db, array $fund): array {
    $schemeCode = $fund['scheme_code'];
    $amcCode = $fund['amc_code'];
    
    // Get scheme details from mtb_scheme_master
    $schemeDetails = $db->getSchemeMasterByCode($schemeCode);
    
    // Get AMC details
    $amcDetails = $db->getAmcMasterByCode($amcCode);
    
    // Determine fund type and risk level based on scheme details
    $fundType = 'ELSS';
    $riskLevel = 'High';
    $category = 'Equity';
    
    if ($schemeDetails) {
        // Determine fund type from scheme_name or fund_cat
        if (stripos($schemeDetails['scheme_name'] ?? '', 'ELSS') !== false) {
            $fundType = 'ELSS';
        } elseif ($schemeDetails['fund_cat']) {
            $fundType = $schemeDetails['fund_cat'];
        }
        
        // Determine risk level based on fund category
        $fundCat = strtolower($schemeDetails['fund_cat'] ?? '');
        if (strpos($fundCat, 'debt') !== false) {
            $riskLevel = 'Low to Moderate';
            $category = 'Debt';
        } elseif (strpos($fundCat, 'hybrid') !== false) {
            $riskLevel = 'Moderate';
            $category = 'Hybrid';
        } elseif (strpos($fundCat, 'equity') !== false || strpos($fundCat, 'large cap') !== false) {
            $riskLevel = 'High';
            $category = 'Equity';
        }
    }
    
    // Add tax benefits information
    $taxBenefits = [
        'section' => '80C',
        'deduction_limit' => '₹1.5 Lakh',
        'lock_in_period' => $schemeDetails['lock_in_period'] ?? '3 Years',
        'eligible_for' => 'Tax saving with equity exposure',
        'benefits' => [
            'Tax deduction up to ₹1.5 lakh under Section 80C',
            'Potential for capital appreciation',
            'Lower long-term capital gains tax (10% above ₹1 lakh)',
            'Wealth creation with tax efficiency'
        ]
    ];
    
    // Build the enhanced fund array
    $enhancedFund = [
        'id' => $fund['id'],
        'amc_code' => $amcCode,
        'amc_name' => $amcDetails['long_name'] ?? $amcDetails['LONG_NAME'] ?? $amcCode,
        'scheme_code' => $schemeCode,
        'scheme_name' => $schemeDetails['scheme_name'] ?? $schemeCode,
        'rational' => $fund['rational'],
        'fundType' => $fundType,
        'riskLevel' => $riskLevel,
        'category' => $category,
        'tax_benefits' => $taxBenefits,
        'fund_details' => $schemeDetails ?: [],
        'amc_details' => $amcDetails ?: []
    ];
    
    // Add additional fields from mtb_scheme_master if available
    if ($schemeDetails) {
        $enhancedFund = array_merge($enhancedFund, [
            'scheme_type' => $schemeDetails['scheme_type'] ?? '',
            'plan_type' => $schemeDetails['plan_type'] ?? '',
            'purchase_allowed' => $schemeDetails['purchase_allowed'] ?? '',
            'sip_allowed' => $schemeDetails['sip_allowed'] ?? '',
            'lock_in_period_flag' => $schemeDetails['lock_in_period_flag'] ?? '',
            'lock_in_period' => $schemeDetails['lock_in_period'] ?? '3 Years',
            'exit_load_flag' => $schemeDetails['exit_load_flag'] ?? '',
            'exit_load' => $schemeDetails['exit_load'] ?? 'Nil after lock-in period',
            'scheme_start_date' => $schemeDetails['scheme_start_date'] ?? '',
            'fund_cat' => $schemeDetails['fund_cat'] ?? '',
            'isin' => $schemeDetails['isin'] ?? '',
            'face_value' => $schemeDetails['face_value'] ?? '',
            'redemption_allowed' => $schemeDetails['redemption_allowed'] ?? '',
            'switch_allowed' => $schemeDetails['switch_allowed'] ?? '',
            'stp_enabled' => $schemeDetails['stp_enabled'] ?? '',
            'swp_enabled' => $schemeDetails['swp_enabled'] ?? ''
        ]);
    }
    
    return $enhancedFund;
}
?>