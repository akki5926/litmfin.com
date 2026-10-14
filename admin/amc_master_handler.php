<?php
require_once('auth_guard.php');
require_once('database.php');

header('Content-Type: application/json');

$db = new MYSQLDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_amc_data':
            // Fetch all distinct amc_code from scheme master
            $scheme_data_result = $db->select("SELECT amc_code, scheme_name, rta_agent_code FROM mtb_scheme_master WHERE amc_code IS NOT NULL AND amc_code != '' ORDER BY amc_code, scheme_name");

            // Fetch existing data from amc master
            $amc_master_result = $db->select("SELECT AMC_CODE, LONG_NAME, KRA FROM mtb_amc");
            $amc_master_map = [];
            foreach ($amc_master_result as $row) {
                if (!empty($row['AMC_CODE'])) {
                    $amc_master_map[$row['AMC_CODE']] = [
                        'long_name' => $row['LONG_NAME'],
                        'kra' => $row['KRA'] ?? '',
                        'sif_code' => '',
                        'is_suggestion' => false
                    ];
                }
            }

            // Group scheme names by amc_code to suggest a name
            $amc_suggestions = [];
            $kra_suggestions = [];
            foreach ($scheme_data_result as $row) {
                $amc_code = $row['amc_code'];
                $scheme_name = $row['scheme_name'];
                $rta_agent = $row['rta_agent_code'] ?? '';
                
                if (!isset($amc_suggestions[$amc_code])) {
                    $amc_suggestions[$amc_code] = [];
                    $kra_suggestions[$amc_code] = [];
                }
                // Get the first word of the scheme name
                $first_word = strtok($scheme_name, " ");
                if ($first_word) {
                    $amc_suggestions[$amc_code][] = $first_word;
                }
                // Track RTA agent for KRA suggestion
                if (!empty($rta_agent)) {
                    $kra_suggestions[$amc_code][] = strtoupper(trim($rta_agent));
                }
            }

            // Combine the data
            $data = [];
            $processed_amcs = [];
            foreach ($scheme_data_result as $row) {
                $code = $row['amc_code'];
                if (isset($processed_amcs[$code])) continue;
                $processed_amcs[$code] = true;

                if (isset($amc_master_map[$code])) {
                    $data[] = [
                        'amc_code' => $code,
                        'long_name' => $amc_master_map[$code]['long_name'],
                        'kra' => $amc_master_map[$code]['kra'],
                        'sif_code' => $amc_master_map[$code]['sif_code'],
                        'is_suggestion' => false
                    ];
                } else {
                    $counts = isset($amc_suggestions[$code]) ? array_count_values($amc_suggestions[$code]) : [];
                    arsort($counts);
                    $base_name = !empty($counts) ? key($counts) : '';
                    $suggested_name = '';
                    if ($base_name) {
                        if (substr($code, -4) === '_SIF') {
                            $suggested_name = $base_name . ' SIF';
                        } else {
                            $suggested_name = $base_name . ' Mutual Fund';
                        }
                    }
                    $kra_counts = isset($kra_suggestions[$code]) ? array_count_values($kra_suggestions[$code]) : [];
                    arsort($kra_counts);
                    $suggested_kra = !empty($kra_counts) ? key($kra_counts) : '';
                    if ($suggested_kra === 'KARVY') {
                        $suggested_kra = 'KFINTECH';
                    }
                    
                    $data[] = [
                        'amc_code' => $code,
                        'long_name' => $suggested_name,
                        'kra' => $suggested_kra,
                        'sif_code' => '',
                        'is_suggestion' => true
                    ];
                }
            }

            // Append any AMC codes that exist in mtb_amc but not in scheme master
            foreach ($amc_master_map as $code => $info) {
                if (!isset($processed_amcs[$code])) {
                    $processed_amcs[$code] = true;
                    $data[] = [
                        'amc_code' => $code,
                        'long_name' => $info['long_name'],
                        'kra' => $info['kra'],
                        'sif_code' => $info['sif_code'],
                        'is_suggestion' => false
                    ];
                }
            }

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'save_amc_name':
            $amc_code = $_POST['amc_code'] ?? '';
            $sif_code = $_POST['sif_code'] ?? null;
            $long_name = $_POST['long_name'] ?? '';
            $kra = $_POST['kra'] ?? '';

            if (empty($amc_code) || empty($long_name)) {
                throw new Exception("AMC Code and Long Name are required.");
            }

            $conn = $db->getConnection();
            $stmt = $conn->prepare("INSERT INTO mtb_amc (AMC_CODE, LONG_NAME, KRA, BANNED_COUNTRY) VALUES (?, ?, ?, 'No') ON DUPLICATE KEY UPDATE LONG_NAME = VALUES(LONG_NAME), KRA = VALUES(KRA)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sss", $amc_code, $long_name, $kra);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => "Successfully saved '{$long_name}' for code '{$amc_code}'."]);
            break;

        case 'delete_amc':
            $amc_code = $_POST['amc_code'] ?? '';

            if (empty($amc_code)) {
                throw new Exception("AMC Code is required for deletion.");
            }

            $conn = $db->getConnection();
            $stmt = $conn->prepare("DELETE FROM mtb_amc WHERE AMC_CODE = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $amc_code);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => "Successfully deleted AMC code '{$amc_code}' from database."]);
            break;

        case 'update_all_kra':
            // Fetch all distinct rta_agent_code for each amc_code from mtb_scheme_master
            $scheme_data_result = $db->select("SELECT amc_code, rta_agent_code FROM mtb_scheme_master WHERE amc_code IS NOT NULL AND amc_code != '' AND rta_agent_code IS NOT NULL AND rta_agent_code != ''");
            
            $kra_map = [];
            foreach ($scheme_data_result as $row) {
                $amc_code = $row['amc_code'];
                $rta_agent = strtoupper(trim($row['rta_agent_code']));
                if (!isset($kra_map[$amc_code])) {
                    $kra_map[$amc_code] = [];
                }
                $kra_map[$amc_code][] = $rta_agent;
            }
            
            $conn = $db->getConnection();
            $updated_count = 0;
            
            foreach ($kra_map as $amc_code => $agents) {
                $counts = array_count_values($agents);
                arsort($counts);
                $suggested_kra = key($counts);
                if ($suggested_kra === 'KARVY') {
                    $suggested_kra = 'KFINTECH';
                }
                
                $stmt = $conn->prepare("UPDATE mtb_amc SET KRA = ? WHERE AMC_CODE = ?");
                $stmt->bind_param("ss", $suggested_kra, $amc_code);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $updated_count++;
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Successfully updated KRA for {$updated_count} AMCs from scheme master."]);
            break;

        default:
            throw new Exception("Invalid action specified.");
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>