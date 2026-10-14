<?php
/**
 * section_mf.php — Mutual Fund Holdings
 *
 * $portfolioInfo  : keyed by AMC code → fund groups → fund data
 * $totalHoldingsMF: from getMFHoldingDataTotal(), keys:
 *   Total_Equity_Cost, Total_Equity_Curr_Value, Total_Equity_Unrealized, Total_Equity_Booked, Total_Equity_Return
 *   Total_Debt_*,  Total_Hybrid_*, Total_Gold_*  (same pattern)
 *
 * Fund data keys (from $singleFundData):
 *   Fund_Name, Fund_House_Name, Asset_Class, Unit_Holdings, Cost_NAV,
 *   Cost_Value, Current_NAV, NAV_Date, Curr_Value, RealizedProfit,
 *   UnRealizedProfit, PayoutRecvd, XIRR
 */
if (!function_exists('n')) {
    function n(mixed $v): float { return (float)str_replace(',', '', $v ?? 0); }
}
if (!function_exists('fn2')) {
    function fn2(mixed $v, bool $color = false): string {
        $f = (float)str_replace(',', '', $v ?? 0);
        $s = number_format($f, 2);
        if ($color) {
            $cls = $f >= 0 ? 'positive' : 'negative';
            return '<span class="' . $cls . '" style="white-space:nowrap">' . $s . '</span>';
        }
        return '<span style="white-space:nowrap">' . $s . '</span>';
    }
}

// Sort all fund data into asset class buckets
$mfEquity = $mfDebt = $mfHybrid = $mfGold = [];

foreach ($portfolioInfo as $amcCode => $fundsHoldingInfo) {
    foreach ($fundsHoldingInfo as $fundsHoldings) {
        foreach ($fundsHoldings as $singleFundData) {
            $assetClass = strtolower($singleFundData['Asset_Class'] ?? '');
            switch ($assetClass) {
                case 'equity': $mfEquity[] = $singleFundData; break;
                case 'debt':   $mfDebt[]   = $singleFundData; break;
                case 'hybrid': $mfHybrid[] = $singleFundData; break;
                case 'gold':   $mfGold[]   = $singleFundData; break;
            }
        }
    }
}

// Totals from getMFHoldingDataTotal()
$mfTotals = $totalHoldingsMF[0] ?? [];

if (!function_exists('renderMFSection')) {
    function renderMFSection(string $title, array $funds, array $mfTotals, string $costKey, string $bookedKey, string $unrlzKey, string $currKey, string $xirrKey): void
    {
        if (empty($funds)) return;
        ?>
        <div class="sub-header"><?= htmlspecialchars($title) ?></div>
        <table>
            <thead>
                <tr>
                    <th style="width:24%">Fund Name</th>
                    <th class="text-right" style="width:10%">Units</th>
                    <th class="text-right" style="width:7%">Avg NAV</th>
                    <th class="text-right" style="width:12%">Cost Value</th>
                    <th class="text-right" style="width:7%">Curr NAV</th>
                    <th class="text-right" style="width:10%">Booked P&amp;L</th>
                    <th class="text-right" style="width:11%">Unrealized P&amp;L</th>
                    <th class="text-right" style="width:12%">Curr Value</th>
                    <th class="text-right" style="width:7%">XIRR %</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($funds as $i => $f):
                $xirr    = $f['XIRR'] ?? '';
                $xirrNum = (float)str_replace(',', '', $xirr);
                $xirrCls = $xirrNum >= 0 ? 'positive' : 'negative';
                $isZero  = (n($f['Unit_Holdings'] ?? 0) == 0);
            ?>
                <tr class="<?= $isZero ? 'zero-holding' : '' ?>">
                    <td>
                        <?= htmlspecialchars(cleanFundName($f['Fund_Name'] ?? '')) ?>
                        <?php if (!empty($f['SIP_Info'])): ?>
                            <span class="sip-badge">SIP</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><span style="white-space:nowrap"><?= $f['Unit_Holdings'] ?? '0' ?></span></td>
                    <td class="text-right"><span style="white-space:nowrap"><?= $f['Cost_NAV'] ?? '—' ?></span></td>
                    <td class="text-right"><?= fn2($f['Cost_Value']       ?? 0) ?></td>
                    <td class="text-right"><span style="white-space:nowrap"><?= $f['Current_NAV'] ?? '—' ?></span></td>
                    <td class="text-right"><?= fn2($f['RealizedProfit']   ?? 0, true) ?></td>
                    <td class="text-right"><?= fn2($f['UnRealizedProfit'] ?? 0, true) ?></td>
                    <td class="text-right"><?= fn2($f['Curr_Value']       ?? 0) ?></td>
                    <td class="text-right <?= $xirrCls ?>">
                        <?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if (!empty($mfTotals[$costKey])): ?>
            <tfoot>
                <?php
                $tXirr    = $mfTotals[$xirrKey] ?? '';
                $tXirrNum = (float)str_replace(',', '', $tXirr);
                $tXirrCls = $tXirrNum >= 0 ? 'positive' : 'negative';
                ?>
                <tr class="total-row">
                    <td colspan="3"><strong>Total</strong></td>
                    <td class="text-right"><strong><?= fn2($mfTotals[$costKey]   ?? 0) ?></strong></td>
                    <td></td>
                    <td class="text-right"><strong><?= fn2($mfTotals[$bookedKey] ?? 0, true) ?></strong></td>
                    <td class="text-right"><strong><?= fn2($mfTotals[$unrlzKey]  ?? 0, true) ?></strong></td>
                    <td class="text-right"><strong><?= fn2($mfTotals[$currKey]   ?? 0) ?></strong></td>
                    <td class="text-right <?= $tXirrCls ?>">
                        <strong><?= is_numeric(str_replace(',','',$tXirr)) ? number_format($tXirrNum,2).'%' : '—' ?></strong>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        <?php
    }
}
?>

<div class="section-header">Mutual Fund Holdings</div>

<?php
renderMFSection('Equity Fund Holdings', $mfEquity, $mfTotals,
    'Total_Equity_Cost', 'Total_Equity_Booked', 'Total_Equity_Unrealized', 'Total_Equity_Curr_Value', 'Total_Equity_Return');

renderMFSection('Debt Fund Holdings', $mfDebt, $mfTotals,
    'Total_Debt_Cost', 'Total_Debt_Booked', 'Total_Debt_Unrealized', 'Total_Debt_Curr_Value', 'Total_Debt_Return');

renderMFSection('Hybrid Fund Holdings', $mfHybrid, $mfTotals,
    'Total_Hybrid_Cost', 'Total_Hybrid_Booked', 'Total_Hybrid_Unrealized', 'Total_Hybrid_Curr_Value', 'Total_Hybrid_Return');

renderMFSection('Gold Fund Holdings', $mfGold, $mfTotals,
    'Total_Gold_Cost', 'Total_Gold_Booked', 'Total_Gold_Unrealized', 'Total_Gold_Curr_Value', 'Total_Gold_Return');
?>
