<?php
/**
 * section_summary.php — Asset Allocation + Portfolio Performance Summary
 *
 * Uses $totalAssetInfo[0] which has these keys (from totalholdings.php getHoldingDataTotal):
 *   Total_Equity_Cost, Total_Equity_Curr_Value, Total_Equity_Unrealized, Total_Equity_Booked, Total_Equity_Return
 *   Total_Debt_Cost,   Total_Debt_Curr_Value,   Total_Debt_Unrealized,   Total_Debt_Booked,   Total_Debt_Return
 *   Total_Hybrid_Cost, Total_Hybrid_Curr_Value, Total_Hybrid_Unrealized, Total_Hybrid_Booked, Total_Hybrid_Return
 *   Total_Gold_Cost,   Total_Gold_Curr_Value,   Total_Gold_Unrealized,   Total_Gold_Booked,   Total_Gold_Return
 *   Total_Cost_Value,  Total_Current_Value,     Total_Unrealized,        Total_Booked,        Total_Holding_Return
 *   Equity_Asset_Share, Debt_Asset_Share, Hybrid_Asset_Share, Gold_Asset_Share
 */

// Helper: strip commas and return float
if (!function_exists('n')) {
    function n(mixed $v): float { return (float)str_replace(',', '', $v ?? 0); }
}

// Helper: format number with comma, 2dp, no-wrap
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

$info  = $totalAssetInfo[0] ?? [];
$total = n($info['Total_Current_Value'] ?? 0);
?>

<div class="section-header">Asset Allocation &amp; Portfolio Performance</div>

<table>
    <thead>
        <tr>
            <th style="width:14%">Asset Class</th>
            <th class="text-right" style="width:14%">Cost Value</th>
            <th class="text-right" style="width:13%">Booked P&amp;L</th>
            <th class="text-right" style="width:14%">Unrealized P&amp;L</th>
            <th class="text-right" style="width:14%">Current Value</th>
            <th class="text-right" style="width:9%">Alloc %</th>
            <th class="text-right" style="width:9%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $rows = [
        ['Equity', 'Total_Equity_Cost', 'Total_Equity_Booked', 'Total_Equity_Unrealized', 'Total_Equity_Curr_Value', 'Equity_Asset_Share', 'Total_Equity_Return'],
        ['Debt',   'Total_Debt_Cost',   'Total_Debt_Booked',   'Total_Debt_Unrealized',   'Total_Debt_Curr_Value',   'Debt_Asset_Share',   'Total_Debt_Return'],
        ['Hybrid', 'Total_Hybrid_Cost', 'Total_Hybrid_Booked', 'Total_Hybrid_Unrealized', 'Total_Hybrid_Curr_Value', 'Hybrid_Asset_Share', 'Total_Hybrid_Return'],
        ['Gold',   'Total_Gold_Cost',   'Total_Gold_Booked',   'Total_Gold_Unrealized',   'Total_Gold_Curr_Value',   'Gold_Asset_Share',   'Total_Gold_Return'],
    ];
    foreach ($rows as [$label, $kCost, $kBooked, $kUnrlz, $kCurr, $kShare, $kXirr]):
        $currVal = n($info[$kCurr] ?? 0);
        $share   = isset($info[$kShare]) ? round((float)$info[$kShare], 1) : 0;
        $xirr    = $info[$kXirr] ?? '';
        $xirrNum = (float)str_replace(',', '', $xirr);
        $xirrCls = $xirrNum >= 0 ? 'positive' : 'negative';
        if ($currVal == 0 && n($info[$kCost] ?? 0) == 0) continue;
    ?>
        <tr>
            <td><?= $label ?></td>
            <td class="text-right"><?= fn2($info[$kCost]   ?? 0) ?></td>
            <td class="text-right"><?= fn2($info[$kBooked] ?? 0, true) ?></td>
            <td class="text-right"><?= fn2($info[$kUnrlz]  ?? 0, true) ?></td>
            <td class="text-right"><?= fn2($info[$kCurr]   ?? 0) ?></td>
            <td class="text-center"><?= $share ?>%</td>
            <td class="text-right <?= $xirrCls ?>">
                <?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <?php
        $totalXirr    = $info['Total_Holding_Return'] ?? '';
        $totalXirrNum = (float)str_replace(',', '', $totalXirr);
        $totalXirrCls = $totalXirrNum >= 0 ? 'positive' : 'negative';
        ?>
        <tr class="total-row">
            <td><strong>Total</strong></td>
            <td class="text-right"><strong><?= fn2($info['Total_Cost_Value']    ?? 0) ?></strong></td>
            <td class="text-right"><strong><?= fn2($info['Total_Booked']        ?? 0, true) ?></strong></td>
            <td class="text-right"><strong><?= fn2($info['Total_Unrealized']    ?? 0, true) ?></strong></td>
            <td class="text-right"><strong><?= fn2($info['Total_Current_Value'] ?? 0) ?></strong></td>
            <td class="text-center"><strong>100%</strong></td>
            <td class="text-right <?= $totalXirrCls ?>">
                <strong><?= is_numeric(str_replace(',','',$totalXirr)) ? number_format($totalXirrNum,2).'%' : '—' ?></strong>
            </td>
        </tr>
    </tfoot>
</table>
