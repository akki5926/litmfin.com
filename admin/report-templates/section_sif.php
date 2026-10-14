<?php
/**
 * SIF Holdings Section
 */
if (!function_exists('n')) { function n($v): float { return (float)str_replace(',', '', $v ?? 0); } }
if (!function_exists('fn2')) {
    function fn2($v, bool $color = false): string {
        $f = n($v); $s = number_format($f, 2);
        if ($color) { $c = $f >= 0 ? 'positive' : 'negative'; return '<span class="'.$c.'" style="white-space:nowrap">'.$s.'</span>'; }
        return '<span style="white-space:nowrap">'.$s.'</span>';
    }
}
?>
<div class="sub-header mt-8">SIF Holdings</div>

<table>
    <thead>
        <tr>
            <th style="width:30%">SIF Name</th>
            <th class="text-right" style="width:10%">Units</th>
            <th class="text-right" style="width:10%">Avg Cost NAV</th>
            <th class="text-right" style="width:12%">Cost Value</th>
            <th class="text-right" style="width:10%">Curr NAV</th>
            <th class="text-right" style="width:10%">Booked P&amp;L</th>
            <th class="text-right" style="width:10%">Unrealized P&amp;L</th>
            <th class="text-right" style="width:12%">Curr Value</th>
            <th class="text-right" style="width:8%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($HoldingInfoSif as $sifIssuer => $sifHoldingInfo): ?>
            <?php foreach ($sifHoldingInfo as $sifHoldings): ?>
                <?php foreach ($sifHoldings as $s): 
                    $xirr    = $s['XIRR'] ?? '';
                    $xirrNum = (float)str_replace(',', '', $xirr);
                    $xirrCls = $xirrNum >= 0 ? 'positive' : 'negative';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($s['Fund_Name'] ?? '') ?></td>
                        <td class="text-right"><span style="white-space:nowrap"><?= $s['Unit_Holdings'] ?? '0' ?></span></td>
                        <td class="text-right"><span style="white-space:nowrap"><?= $s['Cost_NAV'] ?? '—' ?></span></td>
                        <td class="text-right"><?= fn2($s['Cost_Value'] ?? 0) ?></td>
                        <td class="text-right"><span style="white-space:nowrap"><?= $s['Current_NAV'] ?? '—' ?></span></td>
                        <td class="text-right"><?= fn2($s['RealizedProfit'] ?? 0, true) ?></td>
                        <td class="text-right"><?= fn2($s['UnRealizedProfit'] ?? 0, true) ?></td>
                        <td class="text-right"><?= fn2($s['Curr_Value'] ?? 0) ?></td>
                        <td class="text-right <?= $xirrCls ?>"><?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
    <?php if (!empty($TotalHoldingInfoSif[0])): $st = $TotalHoldingInfoSif[0];
        $stXirr = $st['Total_Sif_Return'] ?? ''; $stXirrNum = (float)str_replace(',','',$stXirr);
    ?>
    <tfoot>
        <tr class="total-row">
            <td><strong>Total SIF</strong></td>
            <td></td>
            <td></td>
            <td class="text-right"><strong><?= fn2($st['Total_Sif_Cost'] ?? 0) ?></strong></td>
            <td></td>
            <td class="text-right"><strong><?= fn2($st['Total_Sif_Profit_Booked'] ?? 0, true) ?></strong></td>
            <td class="text-right"><strong><?= fn2($st['Total_Sif_Unrealized_Profit'] ?? 0, true) ?></strong></td>
            <td class="text-right"><strong><?= fn2($st['Total_Sif_Current_Value'] ?? 0) ?></strong></td>
            <td class="text-right <?= $stXirrNum >= 0 ? 'positive' : 'negative' ?>">
                <strong><?= is_numeric(str_replace(',','',$stXirr)) ? number_format($stXirrNum,2).'%' : '—' ?></strong>
            </td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>