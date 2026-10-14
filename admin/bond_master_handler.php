<?php
require_once('auth_guard.php');
require_once('database.php');

@header('Content-Type: application/json');

$db = new MYSQLDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_bond_data':
            $bonds = $db->getAllBonds();
            if (!$bonds) {
                $bonds = [];
            }
            echo json_encode(['success' => true, 'data' => $bonds]);
            break;

        case 'save_bond':
            $slno = $_POST['slno'] ?? '';
            $asset_class = $_POST['asset_class'] ?? '';
            $investment_name = $_POST['investment_name'] ?? '';
            $face_value = $_POST['face_value'] ?? '';
            $nse_code = $_POST['nse_code'] ?? '';
            $bse_code = $_POST['bse_code'] ?? '';
            $interest_rate = $_POST['interest_rate'] ?? '';
            $payout_frequency = $_POST['payout_frequency'] ?? '';
            $redemption_date = $_POST['redemption_date'] ?? '';
            if (!empty($redemption_date)) {
                $redemption_date = trim($redemption_date);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $redemption_date)) {
                    $parts = explode('-', $redemption_date);
                    $redemption_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                } elseif (preg_match('/^\d{2}[\/\-]\d{2}[\/\-]\d{4}$/', $redemption_date)) {
                    $parts = preg_split('/[\/\-]/', $redemption_date);
                    $redemption_date = str_pad($parts[0], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . $parts[2];
                } elseif (preg_match('/^\d{4}$/', $redemption_date)) {
                    $redemption_date = '01-01-' . $redemption_date;
                } else {
                    $ts = strtotime(str_replace('/', '-', $redemption_date));
                    if ($ts !== false) {
                        $redemption_date = date('d-m-Y', $ts);
                    }
                }
            }
            $int_amt_per_payout = $_POST['int_amt_per_payout'] ?? '';
            $interest_pay_date = $_POST['interest_pay_date'] ?? '';

            if (empty($investment_name)) {
                throw new Exception("Bond Name is required.");
            }

            // Convert empty strings to null for optional database fields
            $face_value_val = ($face_value === '') ? null : intval($face_value);
            $interest_rate_val = ($interest_rate === '') ? null : floatval($interest_rate);
            $int_amt_per_payout_val = ($int_amt_per_payout === '') ? null : floatval($int_amt_per_payout);

            if (!empty($slno)) {
                // Update existing record by slno
                $db->updateBond($slno, $asset_class, $investment_name, $face_value_val, $nse_code, $bse_code, $interest_rate_val, $payout_frequency, $redemption_date, $int_amt_per_payout_val, $interest_pay_date);
                echo json_encode(['success' => true, 'message' => "Successfully updated bond '{$investment_name}'."]);
            } else {
                // Check if duplicate investment_name exists to prevent duplicates
                $existing = $db->getBondByName($investment_name);
                if ($existing) {
                    $existing_slno = $existing['slno'];
                    $db->updateBond($existing_slno, $asset_class, $investment_name, $face_value_val, $nse_code, $bse_code, $interest_rate_val, $payout_frequency, $redemption_date, $int_amt_per_payout_val, $interest_pay_date);
                    echo json_encode(['success' => true, 'message' => "Successfully updated existing bond '{$investment_name}'."]);
                } else {
                    // Insert new record
                    $db->insertBond($asset_class, $investment_name, $face_value_val, $nse_code, $bse_code, $interest_rate_val, $payout_frequency, $redemption_date, $int_amt_per_payout_val, $interest_pay_date);
                    echo json_encode(['success' => true, 'message' => "Successfully added new bond '{$investment_name}'."]);
                }
            }
            break;

        case 'delete_bond':
            $slno = $_POST['slno'] ?? '';
            if (empty($slno)) {
                throw new Exception("Bond Serial Number (slno) is required for deletion.");
            }

            $db->deleteBond($slno);
            echo json_encode(['success' => true, 'message' => "Successfully deleted bond from database."]);
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
