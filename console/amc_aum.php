<?php
if(!isset($_SESSION)){ session_start(); }
if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
    http_response_code(403);
    exit("Unauthorized access.");
}

function formatINR($amount) {
    $amount_str = number_format(round(abs($amount)), 0, '.', '');
    $whole = $amount_str;
    
    if (strlen($whole) > 3) {
        $last_three = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $rest = preg_replace("/\B(?=(\d{2})+(?!\d))/", ",", $rest) . ',';
        $formatted = '₹' . $rest . $last_three;
    } else {
        $formatted = '₹' . $whole;
    }
    return $amount < 0 ? '-' . $formatted : $formatted;
}

require_once('../admin/database.php');
$db = new MYSQLDB();

$isin_to_amc = [];
$res_map = $db->query("
    SELECT m.isin, a.LONG_NAME 
    FROM mtb_scheme_master m 
    JOIN mtb_amc a ON m.amc_code = a.AMC_CODE 
    WHERE m.isin != '' AND m.isin IS NOT NULL
    GROUP BY m.isin
");
if ($res_map) {
    while($row = $res_map->fetch_assoc()) {
        $isin_to_amc[$row['isin']] = $row['LONG_NAME'];
    }
}

$aum_data = [];
$total_aum = 0;

try {
    $q = "
        SELECT 
            isin, 
            SUM(CASE WHEN transaction_flag IN ('P', '+', 'N') THEN units ELSE -units END) as total_units 
        FROM tb_transaction_mailback 
        GROUP BY isin 
        HAVING total_units > 0.1
    ";
    $res = $db->query($q);
    
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $isin = $row['isin'];
            $amc_name = isset($isin_to_amc[$isin]) ? $isin_to_amc[$isin] : 'Unknown AMC';
            $units = floatval($row['total_units']);
            
            $navData = $db->getFundNAVByISIN($isin);
            $latest_nav = ($navData && isset($navData['nav'])) ? floatval($navData['nav']) : 0;
            
            $current_value = $units * $latest_nav;
            
            if (!isset($aum_data[$amc_name])) {
                $aum_data[$amc_name] = [
                    'amc_name' => $amc_name,
                    'aum' => 0
                ];
            }
            $aum_data[$amc_name]['aum'] += $current_value;
            $total_aum += $current_value;
        }
    }
    
    // Sort by AUM descending
    usort($aum_data, function($a, $b) {
        return $b['aum'] <=> $a['aum'];
    });

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold mb-1" style="color: #1e2340;">AMC Assets Under Management (AUM)</h4>
            <p class="text-muted small mb-0">Total investments held across different Asset Management Companies.</p>
        </div>
    </div>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold" style="color: #1e2340;">AUM Breakdown</h6>
                <div class="badge bg-primary fs-6 p-2 px-3 rounded-pill">Total AUM: <?php echo formatINR($total_aum); ?></div>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center text-muted" style="width: 60px;">Sl.</th>
                                <th>AMC Name</th>
                                <th class="text-end">AUM (₹)</th>
                                <th class="text-end" style="width: 150px;">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($aum_data)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No data available.</td>
                                </tr>
                            <?php else: ?>
                                <?php $index = 1; foreach ($aum_data as $data): 
                                    $percent = $total_aum > 0 ? ($data['aum'] / $total_aum) * 100 : 0;
                                ?>
                                <tr>
                                    <td class="text-center text-muted small"><?php echo $index++; ?></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($data['amc_name']); ?></td>
                                    <td class="text-end fw-semibold text-primary"><?php echo formatINR($data['aum']); ?></td>
                                    <td class="text-end">
                                        <div class="d-flex align-items-center justify-content-end">
                                            <span class="me-2 small fw-bold text-muted"><?php echo number_format($percent, 2); ?>%</span>
                                            <div class="progress" style="width: 60px; height: 6px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($aum_data)): ?>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2" class="text-end">Total</td>
                                <td class="text-end fs-6 text-primary"><?php echo formatINR($total_aum); ?></td>
                                <td class="text-end">100.00%</td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
