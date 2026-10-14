<?php
require_once __DIR__ . '/../api/db/mfddb.php';
$db = new MfdDb();
$pdo = $db->getPdo();

// Get a user ID from client profiles
$stmt = $pdo->query("SELECT id, PRIMARY_HOLDER_PAN, email FROM vw_client_profiles WHERE PRIMARY_HOLDER_PAN IS NOT NULL LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$userId = $user['id'];
$userEmail = $user['email'] ?? 'test@example.com';
echo "Testing for user_id: {$userId}, PAN: {$user['PRIMARY_HOLDER_PAN']}\n";

$sampleInput = [
    'pan_rp' => $user['PRIMARY_HOLDER_PAN'],
    'inv_name' => 'TEST USER NAME',
    'dob' => '1990-01-01',
    'fr_name' => 'TEST FATHER NAME',
    'sp_name' => 'TEST SPOUSE NAME',
    'tax_status_us' => '1',
    'data_src' => 'P',
    'addr_type' => '2',
    'po_bir_inc' => 'MUMBAI',
    'co_bir_inc' => 'IN',
    'tax_res1' => 'IN',
    'tpin1' => 'ABCDE1234F',
    'id1_type' => 'C',
    'tax_res2' => 'US',
    'tpin2' => '987654321',
    'id2_type' => 'T',
    'tax_res3' => '',
    'tpin3' => '',
    'id3_type' => '',
    'tax_res4' => '',
    'tpin4' => '',
    'id4_type' => '',
    'srce_wealt' => '1',
    'corp_servs' => '',
    'inc_slab' => '31',
    'net_worth' => '0',
    'nw_date' => '',
    'pep_flag' => 'N',
    'occ_code' => '2',
    'occ_type' => 'Service',
    'exemp_code' => '',
    'ffi_drnfe' => '',
    'giin_no' => '',
    'spr_entity' => '',
    'giin_na' => '',
    'giin_exemc' => '',
    'nffe_catg' => '',
    'act_nfe_sc' => '',
    'nature_bus' => '',
    'rel_listed' => 'N',
    'exch_name' => 'O',
    'aadhaar_rp' => '123456789012',
    'ubo_appl' => 'N',
    'ubo_count' => '0',
    'ubo_name' => '',
    'ubo_pan' => '',
    'ubo_nation' => '',
    'ubo_add1' => '',
    'ubo_add2' => '',
    'ubo_add3' => '',
    'ubo_city' => '',
    'ubo_pin' => '',
    'ubo_state' => '',
    'ubo_cntry' => '',
    'ubo_add_type' => '',
    'ubo_ctr' => '',
    'ubo_tin' => '',
    'ubo_id_ty' => '',
    'ubo_cob' => '',
    'ubo_dob' => '',
    'ubo_gender' => '',
    'ubo_fr_nam' => '',
    'ubo_occ' => '',
    'ubo_occ_ty' => '',
    'ubo_tel' => '',
    'ubo_mobile' => '',
    'ubo_code' => '',
    'ubo_hol_pc' => '0',
    'sdf_flag' => 'Y',
    'ubo_df' => 'N',
    'new_cha' => 'N',
    'npo_form' => 'N',
    'npo_dcl' => '',
    'npo_rgno' => '',
    'log_name' => $userEmail
];

$padNumeric = function($val) {
    if ($val !== null && $val !== '' && is_numeric($val)) {
        return str_pad($val, 2, '0', STR_PAD_LEFT);
    }
    return $val;
};

// Replicate updated handleSaveFatcaProfile logic from api/fatca_details_api.php
$fatcaData = [
    'user_id' => $userId,
    'pan_rp' => $user['PRIMARY_HOLDER_PAN'],
    'inv_name' => $sampleInput['inv_name'],
    'dob' => $sampleInput['dob'],
    'pekrn' => $sampleInput['pekrn'] ?? '',
    'fr_name' => $sampleInput['fr_name'] ?? '',
    'sp_name' => $sampleInput['sp_name'] ?? '',
    'tax_status_us' => $padNumeric($sampleInput['tax_status_us'] ?? ''),
    'data_src' => 'P',
    'addr_type' => $sampleInput['addr_type'] ?? '',
    'po_bir_inc' => $sampleInput['po_bir_inc'] ?? '',
    'co_bir_inc' => $sampleInput['co_bir_inc'] ?? '',
    'log_name' => $userEmail,
    'tax_res1' => $sampleInput['tax_res1'] ?? '',
    'tpin1' => $sampleInput['tpin1'] ?? '',
    'id1_type' => $sampleInput['id1_type'] ?? '',
    'tax_res2' => $sampleInput['tax_res2'] ?? '',
    'tpin2' => $sampleInput['tpin2'] ?? '',
    'id2_type' => $sampleInput['id2_type'] ?? '',
    'tax_res3' => $sampleInput['tax_res3'] ?? '',
    'tpin3' => $sampleInput['tpin3'] ?? '',
    'id3_type' => $sampleInput['id3_type'] ?? '',
    'tax_res4' => $sampleInput['tax_res4'] ?? '',
    'tpin4' => $sampleInput['tpin4'] ?? '',
    'id4_type' => $sampleInput['id4_type'] ?? '',
    'srce_wealt' => $padNumeric($sampleInput['srce_wealt'] ?? ''),
    'corp_servs' => $sampleInput['corp_servs'] ?? '',
    'inc_slab' => $sampleInput['inc_slab'] ?? '',
    'net_worth' => isset($sampleInput['net_worth']) ? floatval($sampleInput['net_worth']) : 0,
    'nw_date' => null,
    'pep_flag' => $sampleInput['pep_flag'] ?? 'N',
    'occ_code' => $padNumeric($sampleInput['occ_code'] ?? ''),
    'occ_type' => $sampleInput['occ_type'] ?? '',
    'exemp_code' => $sampleInput['exemp_code'] ?? '',
    'ffi_drnfe' => $sampleInput['ffi_drnfe'] ?? '',
    'giin_no' => $sampleInput['giin_no'] ?? '',
    'spr_entity' => $sampleInput['spr_entity'] ?? '',
    'giin_na' => $sampleInput['giin_na'] ?? '',
    'giin_exemc' => $sampleInput['giin_exemc'] ?? '',
    'nffe_catg' => $sampleInput['nffe_catg'] ?? '',
    'act_nfe_sc' => $sampleInput['act_nfe_sc'] ?? '',
    'nature_bus' => $sampleInput['nature_bus'] ?? '',
    'rel_listed' => $sampleInput['rel_listed'] ?? 'N',
    'exch_name' => 'O',
    'aadhaar_rp' => $sampleInput['aadhaar_rp'] ?? '',
    'ubo_appl' => $sampleInput['ubo_appl'] ?? 'N',
    'ubo_count' => isset($sampleInput['ubo_count']) ? intval($sampleInput['ubo_count']) : 0,
    'ubo_name' => $sampleInput['ubo_name'] ?? '',
    'ubo_pan' => $sampleInput['ubo_pan'] ?? '',
    'ubo_nation' => $sampleInput['ubo_nation'] ?? '',
    'ubo_add1' => $sampleInput['ubo_add1'] ?? '',
    'ubo_add2' => $sampleInput['ubo_add2'] ?? '',
    'ubo_add3' => $sampleInput['ubo_add3'] ?? '',
    'ubo_city' => $sampleInput['ubo_city'] ?? '',
    'ubo_pin' => $sampleInput['ubo_pin'] ?? '',
    'ubo_state' => $sampleInput['ubo_state'] ?? '',
    'ubo_cntry' => $sampleInput['ubo_cntry'] ?? '',
    'ubo_add_type' => $sampleInput['ubo_add_type'] ?? '',
    'ubo_ctr' => $sampleInput['ubo_ctr'] ?? '',
    'ubo_tin' => $sampleInput['ubo_tin'] ?? '',
    'ubo_id_ty' => $sampleInput['ubo_id_ty'] ?? '',
    'ubo_cob' => $sampleInput['ubo_cob'] ?? '',
    'ubo_dob' => null,
    'ubo_gender' => $sampleInput['ubo_gender'] ?? '',
    'ubo_fr_nam' => $sampleInput['ubo_fr_nam'] ?? '',
    'ubo_occ' => $padNumeric($sampleInput['ubo_occ'] ?? ''),
    'ubo_occ_ty' => $sampleInput['ubo_occ_ty'] ?? '',
    'ubo_tel' => $sampleInput['ubo_tel'] ?? '',
    'ubo_mobile' => $sampleInput['ubo_mobile'] ?? '',
    'ubo_code' => $sampleInput['ubo_code'] ?? '',
    'ubo_hol_pc' => isset($sampleInput['ubo_hol_pc']) ? floatval($sampleInput['ubo_hol_pc']) : 0,
    'sdf_flag' => $sampleInput['sdf_flag'] ?? 'Y',
    'ubo_df' => $sampleInput['ubo_df'] ?? 'N',
    'new_cha' => $sampleInput['new_cha'] ?? 'N',
    'npo_form' => $sampleInput['npo_form'] ?? 'N',
    'npo_dcl' => $sampleInput['npo_dcl'] ?? '',
    'npo_rgno' => $sampleInput['npo_rgno'] ?? '',
    'ubo_exch' => $sampleInput['ubo_exch'] ?? '',
    'ubo_isin' => $sampleInput['ubo_isin'] ?? '',
    'ubo_rel_li' => $sampleInput['ubo_rel_li'] ?? '',
    'ubo_categ' => $sampleInput['ubo_categ'] ?? '',
    'ubo_pep_fl' => $sampleInput['ubo_pep_fl'] ?? '',
    'ubo_email' => $sampleInput['ubo_email'] ?? '',
    'ubo_smo_de' => $sampleInput['ubo_smo_de'] ?? '',
    'filler1' => $sampleInput['filler1'] ?? '',
    'filler2' => $sampleInput['filler2'] ?? '',
];

$saveSuccess = $db->saveFatcaDetails($userId, $fatcaData);
echo "Save result: " . ($saveSuccess ? "SUCCESS" : "FAILED") . "\n";

// Now retrieve it using handleGetFatcaProfile logic
$retrieved = $db->getFatcaDetailsByUserId($userId);
if ($retrieved) {
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
    // Frontend occ_type normalization
    if (isset($retrieved['occ_type'])) {
        $map = ['S' => 'Service', 'B' => 'Business', 'O' => 'Others'];
        if (isset($map[$retrieved['occ_type']])) {
            $retrieved['occ_type'] = $map[$retrieved['occ_type']];
        }
    }
}

echo "\n--- COMPARISON OF INPUT vs RETRIEVED ---\n";
$mismatchCount = 0;
foreach ($sampleInput as $key => $sentVal) {
    $dbVal = $retrieved[$key] ?? 'MISSING_IN_DB';
    $match = ($sentVal == $dbVal) ? "OK" : "MISMATCH";
    if ($match === "MISMATCH") {
        $mismatchCount++;
        echo sprintf("%-15s | Sent: %-15s | DB: %-15s | %s\n", $key, $sentVal, $dbVal, $match);
    }
}
if ($mismatchCount === 0) {
    echo "PERFECT! All fields matched 100% between frontend sent input and backend retrieved profile!\n";
}
