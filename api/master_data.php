<?php
/**
 * Master Data API Endpoints (Single 'action' parameter only)
 */
require_once __DIR__ . '/includes/cors.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Central routing – only 'action' parameter
switch ($action) {

    // Fetch all master data in one call
    case 'get_all_tables':
        handleGetAllTables($db);
        break;
    case 'get_fatca_tables':
        handleGetFatcaTables($db);
        break;


    // Individual master tables
    case 'tax_status':
        handleSingleTable($db, 'getTaxStatus', 'Tax Status');
        break;

    case 'occupation_code':
        handleSingleTable($db, 'getOccupationCode', 'Occupation Code');
        break;

    case 'client_holding':
        handleSingleTable($db, 'getClientHolding', 'Client Holding');
        break;

    case 'pan_exempt_cat':
        handleSingleTable($db, 'getPanExemptCat', 'PAN Exempt Category');
        break;

    case 'bank_acc_type':
        handleSingleTable($db, 'getBankAccType', 'Bank Account Type');
        break;

    case 'banks':
        handleSingleTable($db, 'getBank', 'Bank Names');
        break;

    case 'nominee_relation':
        handleSingleTable($db, 'getNomineeRelation', 'Nominee Relation');
        break;
        
    case 'nominee_identity':
        handleSingleTable($db, 'getNomineeIdentity', 'Nominee Identity');
        break;

    case 'nominee_auth':
        handleSingleTable($db, 'getNomineeAuth', 'Nominee Authentication');
        break;

    case 'email_mob_self_decl':
        handleSingleTable($db, 'getEmailMobSelfDecl', 'Email/Mobile Self Declaration');
        break;
        
    case 'country':
        handleSingleTable($db, 'getCountries', 'Country');
        break;
        
    case 'state':
        handleSingleTable($db, 'getStates', 'State');
        break;

    // FATCA Related Tables
    case 'fatca_tax_status':
        handleSingleTable($db, 'getFatcaTaxStatus', 'FATCA Tax Status');
        break;

    case 'fatca_wealth_sc':
        handleSingleTable($db, 'getFatcaWealthSc', 'FATCA Wealth Source');
        break;

    case 'fatca_occupation':
        handleSingleTable($db, 'getFatcaOccupation', 'FATCA Occupation');
        break;

    case 'fatca_income_range':
        handleSingleTable($db, 'getFatcaIncomeRange', 'FATCA Income Range');
        break;

    case 'fatca_ubo_code':
        handleSingleTable($db, 'getFatcaUboCode', 'FATCA UBO Code');
        break;

    case 'fatca_giin_exempt':
        handleSingleTable($db, 'getFatcaGiinExempt', 'FATCA GIIN Exempt');
        break;

    case 'fatca_exempt_code':
        handleSingleTable($db, 'getFatcaExemptCode', 'FATCA Exempt Code');
        break;

    case 'fatca_act_nfe_sub_cat':
        handleSingleTable($db, 'getFatcaActNfeSubCat', 'FATCA Active NFE Sub Category');
        break;

    case 'fatca_identity_type':
        handleSingleTable($db, 'getFatcaIdentityType', 'FATCA Identity Type');
        break;

    // New FATCA Tables
    case 'fatca_acc_type':
        handleSingleTable($db, 'getFatcaAccType', 'FATCA Account Type');
        break;

    case 'fatca_add_type':
        handleSingleTable($db, 'getFatcaAddType', 'FATCA Address Type');
        break;

    case 'fatca_identity_doc_type':
        handleSingleTable($db, 'getFatcaIdentityDocType', 'FATCA Identity Document Type');
        break;

    case 'fatca_corp_serv_sec':
        handleSingleTable($db, 'getFatcaCorpServSec', 'FATCA Corporate Service Sector');
        break;
        
    // Risk Profile Tables
     case 'risk_profile':
        handleSingleTable($db, 'getRiskProfile', 'Risk Profile');
        break;
        
    // AMC (Asset Management Company) Table
    case 'amc_master': 
        handleSingleTable($db, 'getMFAmcMaster', 'AMC Master');
        break;

    // SIF AMC Master (only SIF fund houses)
    case 'sif_amc_master':
        handleSingleTable($db, 'getSIFAmcMaster', 'SIF AMC Master');
        break;
		
     // Investment/Mutual Fund Tables
    case 'fund_category':
		$amc_code = $_GET['amc_code'] ?? '';
		$asset_class = $_GET['asset_class'] ?? '';
        handleSingleTableByArg($db, 'getFundCategoryByAmc',$amc_code, $asset_class,'Fund Category');
        break;
        
    case 'mf_master':
		$amc_code = $_GET['amc_code'] ?? '';
		$category_code = $_GET['category_code'] ?? '';
		$asset_class = $_GET['asset_class'] ?? '';
		$settlementType = $_GET['settlementType'] ?? 'NRM';
        getMfSchemes($db,$amc_code,$category_code,$asset_class,$settlementType );
        break;
    
    case 'scheme_master':
        $product_code = $_GET['product_code'] ?? '';
        if (!$product_code) {
            sendResponse(false, 'product_code parameter is required for scheme_master');
        } else {
            handleSchemeMaster($db, $product_code);
        }
        break;
        
    // SIF Fund Categories - NEW ENDPOINT
    case 'sif_fund_categories':
        $amc_code = $_GET['amc_code'] ?? '';
        if (!$amc_code) {
            sendResponse(false, 'amc_code parameter is required for sif_fund_categories');
        } else {
            handleSifFundCategories($db, $amc_code);
        }
        break;
        
    // Recommended Funds Table
    case 'recommended_funds':
        $category_id = $_GET['category_id'] ?? '';
        if ($category_id) {
            handleRecommendedFundsByCategory($db, $category_id);
        } else {
            handleSingleTable($db, 'getRecommendedFunds', 'Recommended Funds');
        }
        break;
    
    // Tax Saver Funds Table
    case 'taxsaver_funds':
        $amc_code = $_GET['amc_code'] ?? '';
        if ($amc_code) {
            handleTaxSaverFundsByAmc($db, $amc_code);
        } else {
            handleSingleTable($db, 'getTaxSaverFunds', 'Tax Saver Funds');
        }
        break;
    
    case 'taxsaver_details':
        $amc_code = $_GET['amc_code'] ?? '';
        $product_code = $_GET['product_code'] ?? '';
        if (!$amc_code || !$product_code) {
            sendResponse(false, 'Both "amc_code" and "product_code" parameters are required for taxsaver_details');
        }
        handleTaxSaverDetails($db, $amc_code, $product_code);
        break;
       
    case 'pincode_info':
        $pin = $_GET['pincode'] ?? '';
        if (strlen($pin) !== 6 || !ctype_digit($pin)) {
            sendResponse(false, 'Invalid pincode format');
        }
        $info = $db->getLocationByPincode($pin);
        if ($info) {
            sendResponse(true, 'Location found', $info);
        } else {
            sendResponse(false, 'Pincode not found');
        }
        break;

    // FAQ Table
    case 'faqs':
        $faq_page = $_GET['page'] ?? '';
        if ($faq_page) {
            handleFaqsByPage($db, $faq_page);
        } else {
            handleSingleTable($db, 'getFaqs', 'FAQs');
        }
        break;

    // Get schemes by fund house and category
    case 'schemes_by_house_category':
        $amc_code = $_GET['amc_code'] ?? '';
        $fund_category = $_GET['fund_category'] ?? '';
        $scheme_type = $_GET['scheme_type'] ?? ''; // optional: equity, hybrid, debt
        
        if (!$amc_code || !$fund_category) {
            sendResponse(false, 'Parameters "amc_code" and "fund_category" are required');
        } else {
            handleSchemesByHouseAndCategory($db, $amc_code, $fund_category, $scheme_type);
        }
        break;

    // Specific record by code (requires additional 'code' param)
    case 'get_by_code':
        $table = $_GET['table'] ?? '';
        $code  = $_GET['code']  ?? '';
        if (!$table || !$code) {
            sendResponse(false, 'Both "table" and "code" parameters are required for get_by_code');
        }
        handleGetByCode($db, $table, $code);
        break;
        
    // Risk Profile by risk_code
    case 'risk_profile_by_code':
        $risk_code = $_GET['risk_code'] ?? '';
        if (!$risk_code) {
            sendResponse(false, 'risk_code parameter is required');
        }
        handleRiskProfileByCode($db, $risk_code);
        break;

    default:
        sendResponse(
            false,
            'Invalid action. Available actions: ' . 
            'get_all_tables, tax_status, occupation_code, client_holding, pan_exempt_cat, bank_acc_type, ' .
            'nominee_relation, nominee_auth, email_mob_self_decl, country, state, risk_profile, ' .
            'fund_category, mf_master, amc_master, sif_amc_master, sif_fund_categories, ' .
            'fatca_tax_status, fatca_wealth_sc, fatca_occupation, fatca_income_range, ' .
            'fatca_ubo_code, fatca_giin_exempt, fatca_exempt_code, fatca_act_nfe_sub_cat, ' .
            'fatca_identity_type, fatca_acc_type, fatca_add_type, fatca_identity_doc_type, ' .
            'fatca_corp_serv_sec, get_by_code, risk_profile_by_code',
            null,
            404
        );
}

/**
 * Helper: return entire content of one master table
 */
function handleSingleTable(MfdDb $db, string $method, string $friendlyName): void {
    try {
        $data = $db->{$method}(); // dynamic call to MfdDb method
        if (empty($data)) {
            sendResponse(true, "No records found for {$friendlyName}", []);
        } else {
            sendResponse(true, "{$friendlyName} retrieved successfully", $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

function handleSingleTableByArg(MfdDb $db, string $method, $amc_code, $asset_class, string $friendlyName): void {
    try {
        $data = $db->{$method}($amc_code, $asset_class); // dynamic call to MfdDb method
        //error_log("in handleSingleTableByArg: " . print_r($data, true)); // ✅ fix

        if (empty($data)) {
            sendResponse(true, "No records found for {$friendlyName}", []);
        } else {
            sendResponse(true, "{$friendlyName} retrieved successfully", $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

function getMfSchemes(MfdDb $db,$amc_code,$category_code,$asset_class ,$settlementType): void {
    try {
        $data = $db->getMfSchemes($amc_code,$category_code,$asset_class,$settlementType ); 
        //error_log("in getMfSchemes: " . print_r($data, true)); // ✅ fix

        if (empty($data)) {
            sendResponse(true, "No records found for MF scheme", []);
        } else {
            sendResponse(true, "MF schemes retrieved successfully", $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Handle SIF fund categories by AMC code - NEW FUNCTION
 */
function handleSifFundCategories(MfdDb $db, string $amc_code): void {
    try {
        $data = $db->getSifFundCategories($amc_code);
        
        if (empty($data)) {
            sendResponse(true, "No SIF fund categories found for AMC: {$amc_code}", []);
        } else {
            sendResponse(true, "SIF fund categories retrieved successfully", $data);
        }
    } catch (Exception $e) {
        error_log("Error in handleSifFundCategories: " . $e->getMessage());
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Fetch all master tables at once
 */
function handleGetAllTables(MfdDb $db): void {
    try {
        $allData = [
            'tax_status'           => $db->getTaxStatus(),
            'occupation_code'      => $db->getOccupationCode(),
            'client_holding'       => $db->getClientHolding(),
            'pan_exempt_cat'       => $db->getPanExemptCat(),
            'bank_acc_type'        => $db->getBankAccType(),
            'nominee_relation'     => $db->getNomineeRelation(),
            'email_mob_self_decl'  => $db->getEmailMobSelfDecl(),
            'state'                => $db->getStates(),
            'country'              => $db->getCountries(),
            'nominee_identity'     => $db->getNomineeIdentity(),
            'nominee_auth'         => $db->getNomineeAuth(),
            'risk_profile'         => $db->getRiskProfile(),
            // Investment/Mutual Fund Tables
            //'fund_category'        => $db->getFundCategory(),
            //'mf_master'            => $db->getMfMaster(),
            //'amc_master'           => $db->getAmcMaster(),
            // FATCA Tables
            'fatca_tax_status'     => $db->getFatcaTaxStatus(),
            'fatca_wealth_sc'      => $db->getFatcaWealthSc(),
            'fatca_occupation'     => $db->getFatcaOccupation(),
            'fatca_income_range'   => $db->getFatcaIncomeRange(),
            'fatca_ubo_code'       => $db->getFatcaUboCode(),
            'fatca_giin_exempt'    => $db->getFatcaGiinExempt(),
            'fatca_exempt_code'    => $db->getFatcaExemptCode(),
            'fatca_act_nfe_sub_cat' => $db->getFatcaActNfeSubCat(),
            'fatca_identity_type'  => $db->getFatcaIdentityType(),
            // New FATCA Tables
            'fatca_acc_type'       => $db->getFatcaAccType(),
            'fatca_add_type'       => $db->getFatcaAddType(),
            'fatca_identity_doc_type' => $db->getFatcaIdentityDocType(),
            'fatca_corp_serv_sec'  => $db->getFatcaCorpServSec(),
        ];

        sendResponse(true, 'All master data retrieved successfully', $allData);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving master data: ' . $e->getMessage());
    }
}

function handleGetFatcaTables(MfdDb $db): void {
    try {
        $allData = [
            'state'                => $db->getStates(),
            'country'              => $db->getCountries(), 
            // FATCA Tables
            'fatca_tax_status'     => $db->getFatcaTaxStatus(),
            'fatca_wealth_sc'      => $db->getFatcaWealthSc(),
            'fatca_occupation'     => $db->getFatcaOccupation(),
            'fatca_income_range'   => $db->getFatcaIncomeRange(),
            'fatca_ubo_code'       => $db->getFatcaUboCode(),
            'fatca_giin_exempt'    => $db->getFatcaGiinExempt(),
            'fatca_exempt_code'    => $db->getFatcaExemptCode(),
            'fatca_act_nfe_sub_cat' => $db->getFatcaActNfeSubCat(),
            'fatca_identity_type'  => $db->getFatcaIdentityType(),
            // New FATCA Tables
            'fatca_acc_type'       => $db->getFatcaAccType(),
            'fatca_add_type'       => $db->getFatcaAddType(),
            'fatca_identity_doc_type' => $db->getFatcaIdentityDocType(),
            'fatca_corp_serv_sec'  => $db->getFatcaCorpServSec(),
        ];

        sendResponse(true, 'All master data retrieved successfully', $allData);
    } catch (Exception $e) {
        sendResponse(false, 'Error retrieving master data: ' . $e->getMessage());
    }
}

/**
 * Get single record by code from any master table
 */
function handleGetByCode(MfdDb $db, string $table, string $code): void {
    $table = strtolower($table);

    $map = [
        // FATCA Tables
        'fatca_tax_status'    => 'getFatcaTaxStatusByCode',
        'fatca_wealth_sc'     => 'getFatcaWealthScByCode',
        'fatca_occupation'    => 'getFatcaOccupationByCode',
        'fatca_income_range'  => 'getFatcaIncomeRangeByCode',
        'fatca_ubo_code'      => 'getFatcaUboCodeByCode',
        'fatca_giin_exempt'   => 'getFatcaGiinExemptByCode',
        'fatca_exempt_code'   => 'getFatcaExemptCodeByCode',
        'fatca_act_nfe_sub_cat' => 'getFatcaActNfeSubCatByCode',
        'fatca_identity_type' => 'getFatcaIdentityTypeByCode',
        // New FATCA Tables
        'fatca_acc_type'      => 'getFatcaAccTypeByCode',
        'fatca_add_type'      => 'getFatcaAddTypeByCode',
        'fatca_identity_doc_type' => 'getFatcaIdentityDocTypeByCode',
        'fatca_corp_serv_sec' => 'getFatcaCorpServSecByCode',
        // AMC Master
        'amc_master'          => 'getAmcMasterByCode',
    ];

    if (!isset($map[$table])) {
        sendResponse(false, 'Invalid table name for get_by_code. Allowed: ' . implode(', ', array_keys($map)));
        return;
    }

    try {
        $data = $db->{$map[$table]}($code);

        if (!$data) {
            sendResponse(false, "No record found for code '{$code}' in {$table}");
        } else {
            sendResponse(true, 'Record retrieved successfully', $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage());
    }
    
}

/**
 * Get risk profile by risk_code
 */
function handleRiskProfileByCode(MfdDb $db, string $risk_code): void {
    try {
        $data = $db->getRiskProfileByCode($risk_code);

        if (!$data) {
            sendResponse(false, "No risk profile found for code '{$risk_code}'");
        } else {
            sendResponse(true, 'Risk profile retrieved successfully', $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage());
    }
}

/**
 * Get FAQs by page
 */
function handleFaqsByPage(MfdDb $db, string $page): void {
    try {
        $data = $db->getFaqsByPage($page);
        if (empty($data)) {
            sendResponse(true, "No FAQs found for page: {$page}", []);
        } else {
            sendResponse(true, "FAQs retrieved successfully for page: {$page}", $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Get schemes by fund house and category
 */
function handleSchemesByHouseAndCategory(MfdDb $db, string $amc_code, string $fund_category, string $scheme_type = ''): void {
    try {
        if (!empty($scheme_type)) {
            // Fetch schemes of a specific type (equity, hybrid, or debt)
            $data = $db->getSchemesByHouseAndCategory($amc_code, $fund_category, $scheme_type);
            if (empty($data)) {
                sendResponse(true, "No {$scheme_type} schemes found for fund house '{$amc_code}' and category '{$fund_category}'", []);
            } else {
                sendResponse(true, "Schemes retrieved successfully", $data);
            }
        } else {
            // Fetch all schemes (equity, hybrid, debt) grouped by type
            $data = $db->getSchemesByHouseCategoryAndTypes($amc_code, $fund_category);
            if (empty($data)) {
                sendResponse(true, "No schemes found for fund house '{$amc_code}' and category '{$fund_category}'", []);
            } else {
                sendResponse(true, "Schemes retrieved successfully", $data);
            }
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Get scheme master data by product code
 * Returns minimum investment amounts and other scheme details
 */
function handleSchemeMaster(MfdDb $db, string $product_code): void {
    try {
        $data = $db->getSchemeMasterDetails($product_code);
        
        if (!$data) {
            sendResponse(false, "No scheme found for product code '{$product_code}'");
        } else {
            // Ensure amounts are integers
            $data['new_purchase_min_amount'] = intval($data['new_purchase_min_amount'] ?? 0);
            $data['additional_purchase_min_amount'] = intval($data['additional_purchase_min_amount'] ?? 0);
            
            sendResponse(true, 'Scheme details retrieved successfully', $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error: ' . $e->getMessage());
    }
}

/**
 * Handle recommended funds by category
 */
function handleRecommendedFundsByCategory(MfdDb $db, string $category_id): void {
    try {
        $data = $db->getRecommendedFundsByCategory($category_id);
        if (empty($data)) {
            sendResponse(true, "No recommended funds found for category ID: {$category_id}", []);
        } else {
            sendResponse(true, "Recommended funds retrieved successfully", $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Handle tax saver funds by AMC
 */
function handleTaxSaverFundsByAmc(MfdDb $db, string $amc_code): void {
    try {
        $data = $db->getTaxSaverFundsByAmc($amc_code);
        if (empty($data)) {
            sendResponse(true, "No tax saver funds found for AMC: {$amc_code}", []);
        } else {
            sendResponse(true, "Tax saver funds retrieved successfully", $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Handle tax saver details
 */
function handleTaxSaverDetails(MfdDb $db, string $amc_code, string $product_code): void {
    try {
        $data = $db->getTaxSaverDetails($amc_code, $product_code);
        if (!$data) {
            sendResponse(false, "No tax saver details found for AMC: {$amc_code} and product: {$product_code}");
        } else {
            sendResponse(true, "Tax saver details retrieved successfully", $data);
        }
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}
?>