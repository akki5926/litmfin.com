<?php
/**
 * section_sips.php — Active SIPs
 */

error_log("--- DEBUG: \$runningSIPInfo in section_sips.php ---");
error_log(print_r($runningSIPInfo ?? 'NOT SET OR NULL', true));

if (empty($runningSIPInfo)) return;

$activeSips = [];
$activeSwps = [];
$activeStps = [];

foreach ($runningSIPInfo as $plan) {
    $type = strtoupper($plan['registration_type'] ?? $plan['Sip_Type'] ?? 'SIP');
    if ($type === 'SWP') {
        $activeSwps[] = $plan;
    } elseif ($type === 'STP') {
        $activeStps[] = $plan;
    } else {
        $activeSips[] = $plan;
    }
}

// Sort alphabetically by Fund_Name
usort($activeSips, function($a, $b) {
    return strcasecmp($a['Fund_Name'] ?? $a['scheme_name'] ?? '', $b['Fund_Name'] ?? $b['scheme_name'] ?? '');
});
usort($activeSwps, function($a, $b) {
    return strcasecmp($a['Fund_Name'] ?? $a['scheme_name'] ?? '', $b['Fund_Name'] ?? $b['scheme_name'] ?? '');
});
usort($activeStps, function($a, $b) {
    return strcasecmp($a['Fund_Name'] ?? $a['scheme_name'] ?? '', $b['Fund_Name'] ?? $b['scheme_name'] ?? '');
});
?>

<?php if (!empty($activeSips)): ?>
<div class="sub-header mt-8" style="margin-top:15px;">Active Systematic Investment Plans (SIPs)</div>

<table>
    <thead>
        <tr>
            <th style="width:26%">Fund Name</th>
            <th class="text-right" style="width:10%">SIP Amt (₹)</th>
            <th class="text-center" style="width:10%">Frequency</th>
            <th class="text-center" style="width:11%">Start Date</th>
            <th class="text-center" style="width:11%">End Date</th>
            <th class="text-right" style="width:11%">Cost Value (₹)</th>
            <th class="text-right" style="width:11%">Curr. Value (₹)</th>
			<th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $totalSipAmount = 0;
    $totalSipCurrentValue = 0;
    $totalSipCostValue = 0;
    $seenSipFunds = [];
    foreach ($activeSips as $sip):
        $amount = (float)str_replace(',', '', $sip['Amount'] ?? 0);
        $totalSipAmount += $amount;
        $currentValue = (float)($sip['current_value'] ?? 0);
        $costValue = (float)($sip['cost_value'] ?? 0);
        
        $fundKey = (!empty($sip['ISIN_Code'])) ? $sip['ISIN_Code'] : (cleanFundName($sip['Fund_Name'] ?? $sip['scheme_name'] ?? ''));
        if (!isset($seenSipFunds[$fundKey])) {
            $totalSipCurrentValue += $currentValue;
            $totalSipCostValue += $costValue;
            $seenSipFunds[$fundKey] = true;
        }
    ?>
        <tr>
            <td><?= htmlspecialchars(cleanFundName($sip['Fund_Name'] ?? '')) ?></td>
            <td class="text-right"><?= number_format($amount, 0) ?></td>
            <td class="text-center"><?= htmlspecialchars($sip['Frequency'] ?? '') ?></td>
            <td class="text-center"><?= !empty($sip['From_Date']) && $sip['From_Date'] != '0000-00-00' ? date('d-M-Y', strtotime($sip['From_Date'])) : '—' ?></td>
            <td class="text-center"><?= !empty($sip['To_Date']) && $sip['To_Date'] != '0000-00-00' && $sip['To_Date'] != '2099-12-31' && $sip['To_Date'] != '2999-12-31' ? date('d-M-Y', strtotime($sip['To_Date'])) : 'Until Cancelled' ?></td>
            <td class="text-right"><?= number_format($costValue, 0) ?></td>
            <td class="text-right"><?= number_format($currentValue, 0) ?></td>
			<td class="text-right"><?= isset($sip['xirr']) ? number_format($sip['xirr'], 2) . '%' : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td><strong>Total Active SIPs (<?= count($activeSips) ?>)</strong></td>
            <td class="text-right"><strong><?= number_format($totalSipAmount, 0) ?></strong></td>
            <td colspan="3"></td>
            <td class="text-right"><strong><?= number_format($totalSipCostValue, 0) ?></strong></td>
            <td class="text-right"><strong><?= number_format($totalSipCurrentValue, 0) ?></strong></td>
            <td class="text-right"><strong><?= isset($sipXirrResult['portfolio_xirr']) ? number_format($sipXirrResult['portfolio_xirr'], 2) . '%' : '—' ?></strong></td>
        </tr>
    </tfoot>
</table>

<?php if (!empty($sipXirrResult) && !empty($sipChartPng) && !empty($sipValChartPng)): 
    $sXirr = $sipXirrResult['portfolio_xirr'] ?? 0;
    $bXirr = $sipXirrResult['benchmark_xirr'] ?? 0;
    $alpha = $sXirr - $bXirr;
    $alphaStr = 'Alpha: ' . ($alpha >= 0 ? '+' : '') . number_format($alpha, 2) . '%';
    $alphaColor = $alpha >= 0 ? '#1a7a4a' : '#c0392b';
?>
<div class="sub-header mt-8" style="margin-top:15px;">Active SIPs — Performance Analysis</div>
<table style="width:100%;margin-top:15px;border-collapse:separate;border-spacing:8px 0;">
    <tr>
        <td style="width:50%;vertical-align:top;text-align:center;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:4px;">
                XIRR - SIP <small style="color: #777; font-size: 80%;">(All Active SIPs)</small>
            </div>
            <img src="<?= BenchmarkXirr::pngToDataUri($sipChartPng) ?>" style="width:100%;max-width:340px;"/>
            <div style="font-size:10pt;font-weight:bold;color:<?= $alphaColor ?>;margin-top:3px;">
                <?= $alphaStr ?>
            </div>
			<!--
            <div style="font-size:7pt;color:#444;margin-top:3px;line-height:1.8;">
                <span style="display:inline-block;width:20px;height:3px;background:#1E3A5F;vertical-align:middle;margin-right:3px;"></span><strong>Portfolio</strong>
                &nbsp;&nbsp;
                <span style="display:inline-block;width:20px;height:3px;background:#E87722;vertical-align:middle;margin-right:3px;"></span><strong>Nifty 500 Mirror</strong>
            </div>
			-->
        </td>
        <td style="width:50%;vertical-align:top;text-align:center;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:4px;">
                Current Value (₹)
            </div>
            <img src="<?= BenchmarkXirr::pngToDataUri($sipValChartPng) ?>" style="width:100%;max-width:340px;"/>
            <div style="font-size:9pt;font-weight:bold;color:transparent;margin-top:3px;">-</div>
		<!--
		  <div style="font-size:7pt;color:#444;margin-top:3px;line-height:1.8;">
                <span style="display:inline-block;width:20px;height:3px;background:#1E3A5F;vertical-align:middle;margin-right:3px;"></span><strong>Portfolio</strong>
                &nbsp;&nbsp;
                <span style="display:inline-block;width:20px;height:3px;background:#E87722;vertical-align:middle;margin-right:3px;"></span><strong>Nifty 500 Mirror</strong>
            </div>
		-->	
        </td>
    </tr>
</table>
<?php endif; ?>

<?php if (!empty($activeStps)): ?>
<div class="sub-header mt-8" style="margin-top:15px;">Active Systematic Transfer Plans (STPs)</div>

<table>
    <thead>
        <tr>
            <th style="width:26%">Fund Name</th>
            <th class="text-right" style="width:10%">STP Amt (₹)</th>
            <th class="text-center" style="width:10%">Frequency</th>
            <th class="text-center" style="width:11%">Start Date</th>
            <th class="text-center" style="width:11%">End Date</th>
            <th class="text-right" style="width:11%">Cost Value (₹)</th>
            <th class="text-right" style="width:11%">Curr. Value (₹)</th>
            <th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $totalStpAmount = 0;
    $totalStpCurrentValue = 0;
    $totalStpCostValue = 0;
    $seenStpFunds = [];
    foreach ($activeStps as $stp):
        $amount = (float)str_replace(',', '', $stp['Amount'] ?? $stp['installments_amount'] ?? 0);
        $totalStpAmount += $amount;
        $currentValue = (float)($stp['current_value'] ?? 0);
        $costValue = (float)($stp['cost_value'] ?? 0);
        
        $fundKey = (!empty($stp['ISIN_Code'])) ? $stp['ISIN_Code'] : (cleanFundName($stp['scheme_name'] ?? $stp['Fund_Name'] ?? ''));
        if (!isset($seenStpFunds[$fundKey])) {
            $totalStpCurrentValue += $currentValue;
            $totalStpCostValue += $costValue;
            $seenStpFunds[$fundKey] = true;
        }
        
        $fromDate = $stp['From_Date'] ?? $stp['start_date'] ?? '';
        $toDate = $stp['To_Date'] ?? $stp['end_date'] ?? '';
    ?>
        <tr>
            <td><?= htmlspecialchars(cleanFundName($stp['scheme_name'] ?? $stp['Fund_Name'] ?? '')) ?></td>
            <td class="text-right"><?= number_format($amount, 0) ?></td>
            <td class="text-center"><?= htmlspecialchars($stp['frequency_type'] ?? $stp['Frequency'] ?? '') ?></td>
            <td class="text-center"><?= !empty($fromDate) && $fromDate != '0000-00-00' ? date('d-M-Y', strtotime($fromDate)) : '—' ?></td>
            <td class="text-center"><?= !empty($toDate) && $toDate != '0000-00-00' && $toDate != '2099-12-31' && $toDate != '2999-12-31' ? date('d-M-Y', strtotime($toDate)) : 'Until Cancelled' ?></td>
            <td class="text-right"><?= number_format($costValue, 0) ?></td>
            <td class="text-right"><?= number_format($currentValue, 0) ?></td>
            <td class="text-right"><?= isset($stp['xirr']) ? number_format($stp['xirr'], 2) . '%' : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td><strong>Total Active STPs (<?= count($activeStps) ?>)</strong></td>
            <td class="text-right"><strong><?= number_format($totalStpAmount, 0) ?></strong></td>
            <td colspan="3"></td>
            <td class="text-right"><strong><?= number_format($totalStpCostValue, 0) ?></strong></td>
            <td class="text-right"><strong><?= number_format($totalStpCurrentValue, 0) ?></strong></td>
            <td class="text-right"><strong>—</strong></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($activeSwps)): ?>
<div class="sub-header mt-8" style="margin-top:15px;">Active Systematic Withdrawal Plans (SWPs)</div>

<table>
    <thead>
        <tr>
            <th style="width:26%">Fund Name</th>
            <th class="text-right" style="width:10%">SWP Amt (₹)</th>
            <th class="text-center" style="width:10%">Frequency</th>
            <th class="text-center" style="width:11%">Start Date</th>
            <th class="text-center" style="width:11%">End Date</th>
            <th class="text-right" style="width:11%">Cost Value (₹)</th>
            <th class="text-right" style="width:11%">Curr. Value (₹)</th>
            <th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $totalSwpAmount = 0;
    $totalSwpCurrentValue = 0;
    $totalSwpCostValue = 0;
    $seenSwpFunds = [];
    foreach ($activeSwps as $swp):
        $amount = (float)str_replace(',', '', $swp['Amount'] ?? 0);
        $totalSwpAmount += $amount;
        $currentValue = (float)($swp['current_value'] ?? 0);
        $costValue = (float)($swp['cost_value'] ?? 0);
        
        $fundKey = (!empty($swp['ISIN_Code'])) ? $swp['ISIN_Code'] : (cleanFundName($swp['Fund_Name'] ?? $swp['scheme_name'] ?? ''));
        if (!isset($seenSwpFunds[$fundKey])) {
            $totalSwpCurrentValue += $currentValue;
            $totalSwpCostValue += $costValue;
            $seenSwpFunds[$fundKey] = true;
        }
    ?>
        <tr>
            <td><?= htmlspecialchars(cleanFundName($swp['Fund_Name'] ?? '')) ?></td>
            <td class="text-right"><?= number_format($amount, 0) ?></td>
            <td class="text-center"><?= htmlspecialchars($swp['Frequency'] ?? '') ?></td>
            <td class="text-center"><?= !empty($swp['From_Date']) && $swp['From_Date'] != '0000-00-00' ? date('d-M-Y', strtotime($swp['From_Date'])) : '—' ?></td>
            <td class="text-center"><?= !empty($swp['To_Date']) && $swp['To_Date'] != '0000-00-00' && $swp['To_Date'] != '2099-12-31' && $swp['To_Date'] != '2999-12-31' ? date('d-M-Y', strtotime($swp['To_Date'])) : 'Until Cancelled' ?></td>
            <td class="text-right"><?= number_format($costValue, 0) ?></td>
            <td class="text-right"><?= number_format($currentValue, 0) ?></td>
            <td class="text-right"><?= isset($swp['xirr']) ? number_format($swp['xirr'], 2) . '%' : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td><strong>Total Active SWPs (<?= count($activeSwps) ?>)</strong></td>
            <td class="text-right"><strong><?= number_format($totalSwpAmount, 0) ?></strong></td>
            <td colspan="3"></td>
            <td class="text-right"><strong><?= number_format($totalSwpCostValue, 0) ?></strong></td>
            <td class="text-right"><strong><?= number_format($totalSwpCurrentValue, 0) ?></strong></td>
            <td class="text-right"><strong><?= isset($swpXirrResult['portfolio_xirr']) ? number_format($swpXirrResult['portfolio_xirr'], 2) . '%' : '—' ?></strong></td>
        </tr>
    </tfoot>
</table>

<?php if (!empty($swpXirrResult) && (!empty($swpXirrChartPng) || !empty($swpBarChartPng))): 
    $sXirr = $swpXirrResult['portfolio_xirr'] ?? 0;
    $bXirr = $swpXirrResult['benchmark_xirr'] ?? 0;
    $alpha = $sXirr - $bXirr;
    $alphaStr = 'Alpha: ' . ($alpha >= 0 ? '+' : '') . number_format($alpha, 2) . '%';
    $alphaColor = $alpha >= 0 ? '#1a7a4a' : '#c0392b';
?>
<div class="sub-header mt-8" style="margin-top:15px;">Active SWPs — Performance Analysis</div>
<table style="width:100%;margin-top:15px;border-collapse:separate;border-spacing:8px 0;">
    <tr>
        <td style="width:50%;vertical-align:top;text-align:center;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:4px;">
                XIRR - SWP <small style="color: #777; font-size: 80%;">(All Active SWPs)</small>
            </div>
            <?php if (!empty($swpXirrChartPng)): ?>
                <img src="<?= BenchmarkXirr::pngToDataUri($swpXirrChartPng) ?>" style="width:100%;max-width:340px;"/>
            <?php else: ?>
                <div style="padding:40px 0;color:#999;font-size:8pt;">Performance Chart Unavailable</div>
            <?php endif; ?>
            <div style="font-size:10pt;font-weight:bold;color:<?= $alphaColor ?>;margin-top:3px;">
                <?= $alphaStr ?>
            </div>
        </td>
        <td style="width:50%;vertical-align:top;text-align:center;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:4px;">
                SWP Flow Analysis
            </div>
            <?php if (!empty($swpBarChartPng)): ?>
                <img src="<?= BenchmarkXirr::pngToDataUri($swpBarChartPng) ?>" style="width:100%;max-width:340px;"/>
            <?php else: ?>
                <div style="padding:40px 0;color:#999;font-size:8pt;">Analysis Chart Unavailable</div>
            <?php endif; ?>
            <div style="font-size:9pt;font-weight:bold;color:transparent;margin-top:3px;">-</div>
        </td>
    </tr>
</table>
<?php endif; ?>
<?php endif; ?>