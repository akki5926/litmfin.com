<?php
require_once __DIR__ . '/../api/db/mfddb.php';
$db = new MfdDb();
$pdo = $db->getPdo();

$stmt = $pdo->query("SELECT id, PRIMARY_HOLDER_PAN, email FROM vw_client_profiles WHERE PRIMARY_HOLDER_PAN IS NOT NULL LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$userId = $user['id'];
$userEmail = $user['email'] ?? 'test@example.com';

echo "=====================================================\n";
echo "COMPREHENSIVE FATCA FIELD VERIFICATION TEST\n";
echo "User ID: {$userId} | PAN: {$user['PRIMARY_HOLDER_PAN']}\n";
echo "=====================================================\n\n";

// Helper function to test save and fetch flow
function testFatcaFields($db, $userId, $userEmail, $pan, $testName, $input) {
    echo ">>> TESTING SCENARIO: {$testName}\n";

    // Frontend -> API payload construction (replicating api/fatca_details_api.php)
    $padNumeric = function($val) {
        if ($val !== null && $val !== '' && is_numeric($val)) {
            return str_pad($val, 2, '0', STR_PAD_LEFT);
        }
        return $val;
    };

    $formattedDob = !empty($input['dob']) && $input['dob'] !== '-0001-11-30' ? date('Y-m-d', strtotime($input['dob'])) : null;
    $formattedNwDate = !empty($input['nw_date']) && $input['nw_date'] !== '-0001-11-30' ? date('Y-m-d', strtotime($input['nw_date'])) : null;
    $formattedUboDob = !empty($input['ubo_dob']) && $input['ubo_dob'] !== '-0001-11-30' ? date('Y-m-d', strtotime($input['ubo_dob'])) : null;

    $fatcaData = [
        'user_id' => $userId,
        'pan_rp' => $pan,
        'inv_name' => $input['inv_name'] ?? 'TEST NAME',
        'dob' => $formattedDob,
        'pekrn' => $input['pekrn'] ?? '',
        'fr_name' => $input['fr_name'] ?? '',
        'sp_name' => $input['sp_name'] ?? '',
        'tax_status_us' => $padNumeric($input['tax_status_us'] ?? ''),
        'data_src' => 'P',
        'addr_type' => $input['addr_type'] ?? '',
        'po_bir_inc' => $input['po_bir_inc'] ?? '',
        'co_bir_inc' => $input['co_bir_inc'] ?? '',
        'log_name' => $userEmail,
        'tax_res1' => $input['tax_res1'] ?? '',
        'tpin1' => $input['tpin1'] ?? '',
        'id1_type' => $input['id1_type'] ?? '',
        'tax_res2' => $input['tax_res2'] ?? '',
        'tpin2' => $input['tpin2'] ?? '',
        'id2_type' => $input['id2_type'] ?? '',
        'tax_res3' => $input['tax_res3'] ?? '',
        'tpin3' => $input['tpin3'] ?? '',
        'id3_type' => $input['id3_type'] ?? '',
        'tax_res4' => $input['tax_res4'] ?? '',
        'tpin4' => $input['tpin4'] ?? '',
        'id4_type' => $input['id4_type'] ?? '',
        'srce_wealt' => $padNumeric($input['srce_wealt'] ?? ''),
        'corp_servs' => $input['corp_servs'] ?? '',
        'inc_slab' => $input['inc_slab'] ?? '',
        'net_worth' => isset($input['net_worth']) ? floatval($input['net_worth']) : 0,
        'nw_date' => $formattedNwDate,
        'pep_flag' => $input['pep_flag'] ?? 'N',
        'occ_code' => $padNumeric($input['occ_code'] ?? ''),
        'occ_type' => $input['occ_type'] ?? '',
        'exemp_code' => $input['exemp_code'] ?? '',
        'ffi_drnfe' => $input['ffi_drnfe'] ?? '',
        'giin_no' => $input['giin_no'] ?? '',
        'spr_entity' => $input['spr_entity'] ?? '',
        'giin_na' => $input['giin_na'] ?? '',
        'giin_exemc' => $input['giin_exemc'] ?? '',
        'nffe_catg' => $input['nffe_catg'] ?? '',
        'act_nfe_sc' => $input['act_nfe_sc'] ?? '',
        'nature_bus' => $input['nature_bus'] ?? '',
        'rel_listed' => $input['rel_listed'] ?? 'N',
        'exch_name' => 'O',
        'aadhaar_rp' => $input['aadhaar_rp'] ?? '',
        'ubo_appl' => $input['ubo_appl'] ?? 'N',
        'ubo_count' => isset($input['ubo_count']) ? intval($input['ubo_count']) : 0,
        'ubo_name' => $input['ubo_name'] ?? '',
        'ubo_pan' => $input['ubo_pan'] ?? '',
        'ubo_nation' => $input['ubo_nation'] ?? '',
        'ubo_add1' => $input['ubo_add1'] ?? '',
        'ubo_add2' => $input['ubo_add2'] ?? '',
        'ubo_add3' => $input['ubo_add3'] ?? '',
        'ubo_city' => $input['ubo_city'] ?? '',
        'ubo_pin' => $input['ubo_pin'] ?? '',
        'ubo_state' => $input['ubo_state'] ?? '',
        'ubo_cntry' => $input['ubo_cntry'] ?? '',
        'ubo_add_type' => $input['ubo_add_type'] ?? '',
        'ubo_ctr' => $input['ubo_ctr'] ?? '',
        'ubo_tin' => $input['ubo_tin'] ?? '',
        'ubo_id_ty' => $input['ubo_id_ty'] ?? '',
        'ubo_cob' => $input['ubo_cob'] ?? '',
        'ubo_dob' => $formattedUboDob,
        'ubo_gender' => $input['ubo_gender'] ?? '',
        'ubo_fr_nam' => $input['ubo_fr_nam'] ?? '',
        'ubo_occ' => $padNumeric($input['ubo_occ'] ?? ''),
        'ubo_occ_ty' => $input['ubo_occ_ty'] ?? '',
        'ubo_tel' => $input['ubo_tel'] ?? '',
        'ubo_mobile' => $input['ubo_mobile'] ?? '',
        'ubo_code' => $input['ubo_code'] ?? '',
        'ubo_hol_pc' => isset($input['ubo_hol_pc']) ? floatval($input['ubo_hol_pc']) : 0,
        'sdf_flag' => $input['sdf_flag'] ?? 'Y',
        'ubo_df' => $input['ubo_df'] ?? 'N',
        'new_cha' => $input['new_cha'] ?? 'N',
        'npo_form' => $input['npo_form'] ?? 'N',
        'npo_dcl' => $input['npo_dcl'] ?? '',
        'npo_rgno' => $input['npo_rgno'] ?? '',
        'ubo_exch' => $input['ubo_exch'] ?? '',
        'ubo_isin' => $input['ubo_isin'] ?? '',
        'ubo_rel_li' => $input['ubo_rel_li'] ?? '',
        'ubo_categ' => $input['ubo_categ'] ?? '',
        'ubo_pep_fl' => $input['ubo_pep_fl'] ?? '',
        'ubo_email' => $input['ubo_email'] ?? '',
        'ubo_smo_de' => $input['ubo_smo_de'] ?? '',
        'filler1' => $input['filler1'] ?? '',
        'filler2' => $input['filler2'] ?? '',
    ];

    $saveOk = $db->saveFatcaDetails($userId, $fatcaData);
    if (!$saveOk) {
        echo "   ❌ Database Save FAILED!\n\n";
        return;
    }

    // Retrieve via API logic
    $retrieved = $db->getFatcaDetailsByUserId($userId);
    if (!$retrieved) {
        echo "   ❌ Database Retrieve FAILED!\n\n";
        return;
    }

    // Apply handleGetFatcaProfile normalization
    foreach ($retrieved as $k => $v) {
        if (is_string($v)) $retrieved[$k] = trim($v);
    }
    $fieldsToNormalize = ['tax_status_us', 'occ_code', 'srce_wealt', 'addr_type', 'ubo_occ'];
    foreach ($fieldsToNormalize as $field) {
        if (isset($retrieved[$field]) && $retrieved[$field] !== '') {
            $trimmed = ltrim($retrieved[$field], '0');
            $retrieved[$field] = ($trimmed === '') ? '0' : $trimmed;
        }
    }
    // Apply Frontend page.js normalization
    if (isset($retrieved['occ_type'])) {
        $map = ['S' => 'Service', 'B' => 'Business', 'O' => 'Others'];
        if (isset($map[$retrieved['occ_type']])) {
            $retrieved['occ_type'] = $map[$retrieved['occ_type']];
        }
    }
    if (isset($retrieved['ubo_occ_ty'])) {
        $map = ['S' => 'Service', 'B' => 'Business', 'O' => 'Others'];
        if (isset($map[$retrieved['ubo_occ_ty']])) {
            $retrieved['ubo_occ_ty'] = $map[$retrieved['ubo_occ_ty']];
        }
    }

    $mismatches = [];
    foreach ($input as $key => $expectedVal) {
        $actualVal = $retrieved[$key] ?? null;

        // Normalize date format comparisons
        if ($key === 'dob' || $key === 'nw_date' || $key === 'ubo_dob') {
            $expectedVal = !empty($expectedVal) ? date('Y-m-d', strtotime($expectedVal)) : null;
            $actualVal = !empty($actualVal) && $actualVal !== '0000-00-00' ? date('Y-m-d', strtotime($actualVal)) : null;
        }
        // Normalize float format comparisons
        if ($key === 'net_worth' || $key === 'ubo_hol_pc') {
            $expectedVal = floatval($expectedVal);
            $actualVal = floatval($actualVal);
        }

        if ($expectedVal != $actualVal) {
            $mismatches[] = sprintf("%-15s | Expected: %-20s | Actual DB: %-20s", $key, var_export($expectedVal, true), var_export($actualVal, true));
        }
    }

    if (empty($mismatches)) {
        echo "   ✅ PERFECT! All " . count($input) . " fields saved and retrieved with 100% accuracy!\n\n";
    } else {
        echo "   ⚠️ Found " . count($mismatches) . " field mismatch(es):\n";
        foreach ($mismatches as $m) {
            echo "      " . $m . "\n";
        }
        echo "\n";
    }
}

// TEST CASE 1: Individual Mode (All Individual fields)
$individualInput = [
    'pan_rp' => $user['PRIMARY_HOLDER_PAN'],
    'inv_name' => 'INDIVIDUAL TEST USER',
    'dob' => '1985-05-15',
    'fr_name' => 'RAMESH SHARMA',
    'sp_name' => 'SUNITA SHARMA',
    'tax_status_us' => '1', // 01 Individual
    'data_src' => 'P',
    'addr_type' => '2', // Residential
    'po_bir_inc' => 'MUMBAI',
    'co_bir_inc' => 'IN',
    'tax_res1' => 'IN',
    'tpin1' => 'AAACS1234F',
    'id1_type' => 'C', // PAN
    'tax_res2' => 'US',
    'tpin2' => 'US99887766',
    'id2_type' => 'T', // TIN
    'tax_res3' => '',
    'tpin3' => '',
    'id3_type' => '',
    'tax_res4' => '',
    'tpin4' => '',
    'id4_type' => '',
    'srce_wealt' => '1', // Salary
    'inc_slab' => '33', // >5 <=10 LACS
    'pep_flag' => 'N',
    'occ_code' => '2', // SERVICE
    'occ_type' => 'Service',
    'rel_listed' => 'N',
    'exch_name' => 'O',
    'aadhaar_rp' => '999988887777',
    'ubo_appl' => 'N',
    'sdf_flag' => 'Y',
    'ubo_df' => 'N',
    'npo_form' => 'N',
];
testFatcaFields($db, $userId, $userEmail, $user['PRIMARY_HOLDER_PAN'], "1. Individual Account Profile", $individualInput);

// TEST CASE 2: Entity / Corporate Profile with UBO Details
$entityInput = [
    'pan_rp' => $user['PRIMARY_HOLDER_PAN'],
    'inv_name' => 'GLOBAL TRADING LLP',
    'dob' => '2015-08-20',
    'tax_status_us' => '4', // Company
    'data_src' => 'P',
    'addr_type' => '3', // Business
    'po_bir_inc' => 'DELHI',
    'co_bir_inc' => 'IN',
    'tax_res1' => 'IN',
    'tpin1' => 'AAACG1234F',
    'id1_type' => 'C1', // CIN
    'srce_wealt' => '2', // Business Income
    'corp_servs' => '01',
    'inc_slab' => '35', // > 25 LACS <= 1 CRORE
    'net_worth' => '150.50',
    'nw_date' => '2026-03-31',
    'pep_flag' => 'N',
    'occ_code' => '1', // Business
    'occ_type' => 'Business',
    'exemp_code' => '01',
    'ffi_drnfe' => 'DRNFE',
    'giin_no' => '123456789.99999.999',
    'nffe_catg' => '01',
    'act_nfe_sc' => '01',
    'nature_bus' => 'MANUFACTURING',
    'rel_listed' => 'N',
    'exch_name' => 'O',
    'ubo_appl' => 'Y',
    'ubo_count' => '1',
    'ubo_name' => 'DIRECTOR KAPOOR',
    'ubo_pan' => 'KAPPR1234F',
    'ubo_nation' => 'IN',
    'ubo_add1' => 'FLAT 101 STAR APTS',
    'ubo_add2' => 'MAIN STREET',
    'ubo_city' => 'MUMBAI',
    'ubo_pin' => '400001',
    'ubo_state' => '27', // Maharashtra
    'ubo_cntry' => 'IN',
    'ubo_add_type' => '2',
    'ubo_ctr' => 'IN',
    'ubo_tin' => 'TIN123456',
    'ubo_id_ty' => 'C',
    'ubo_cob' => 'IN',
    'ubo_dob' => '1975-11-10',
    'ubo_gender' => 'M',
    'ubo_fr_nam' => 'LATE KAPOOR SR',
    'ubo_occ' => '1',
    'ubo_occ_ty' => 'Business',
    'ubo_code' => 'C01',
    'ubo_hol_pc' => '51.00',
    'sdf_flag' => 'Y',
    'ubo_df' => 'Y',
    'npo_form' => 'Y',
    'npo_dcl' => 'Y',
    'npo_rgno' => 'NPO-REG-998877',
];
testFatcaFields($db, $userId, $userEmail, $user['PRIMARY_HOLDER_PAN'], "2. Entity Account with UBO Details & NPO Form", $entityInput);
