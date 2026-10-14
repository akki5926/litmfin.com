<?php
/**
 * section_bonds.php — Bond / SGB Holdings
 *
 * $HoldingInfoBonds keyed by issuer → bond groups → bond data
 * Bond data keys: bondName, assetClass, unitHoldings, costNAV, costValue,
 *   currentNAV, payoutRecvd, realizedProfit, unRealizedProfit, currValue, XIRR
 *
 * $TotalHoldingInfoBonds[0] keys:
 *   Total_Bond_Cost, Total_Bond_Payout_Recvd, Total_Bond_Profit_Booked,
 *   Total_Bond_Unrealized_Profit, Total_Bond_Current_Value, Total_Bond_Return
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
<div class="section-header">Bond / SGB Holdings</div>

<table>
    <thead>
        <tr>
            <th style="width:20%">Bond Name</th>
            <th style="width:7%">Asset Class</th>
            <th class="text-right" style="width:8%">Units</th>
            <th class="text-right" style="width:7%">Avg Cost NAV</th>
            <th class="text-right" style="width:11%">Cost Value</th>
            <th class="text-right" style="width:7%">Curr NAV</th>
            <th class="text-right" style="width:8%">Payout Recv'd</th>
            <th class="text-right" style="width:8%">Booked P&amp;L</th>
            <th class="text-right" style="width:9%">Unrealized P&amp;L</th>
            <th class="text-right" style="width:11%">Curr Value</th>
            <th class="text-right" style="width:6%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($HoldingInfoBonds as $issuerName => $bondGroups):
        foreach ($bondGroups as $bondHoldings):
            foreach ($bondHoldings as $b):
                $xirr    = $b['XIRR'] ?? '';
                $xirrNum = (float)str_replace(',', '', $xirr);
                $xirrCls = $xirrNum >= 0 ? 'positive' : 'negative';
    ?>
        <tr>
            <td><?= htmlspecialchars($b['bondName'] ?? '') ?></td>
            <td class="text-center"><?= htmlspecialchars($b['assetClass'] ?? '') ?></td>
            <td class="text-right"><span style="white-space:nowrap"><?= $b['unitHoldings'] ?? '0' ?></span></td>
            <td class="text-right"><span style="white-space:nowrap"><?= $b['costNAV']      ?? '—' ?></span></td>
            <td class="text-right"><?= fn2($b['costValue']       ?? 0) ?></td>
            <td class="text-right"><span style="white-space:nowrap"><?= $b['currentNAV']   ?? '—' ?></span></td>
            <td class="text-right"><?= fn2($b['payoutRecvd']     ?? 0) ?></td>
            <td class="text-right"><?= fn2($b['realizedProfit']  ?? 0, true) ?></td>
            <td class="text-right"><?= fn2($b['unRealizedProfit']?? 0, true) ?></td>
            <td class="text-right"><?= fn2($b['currValue']       ?? 0) ?></td>
            <td class="text-right <?= $xirrCls ?>">
                <?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?>
            </td>
        </tr>
    <?php endforeach; endforeach; endforeach; ?>
    </tbody>
    <?php if (!empty($TotalHoldingInfoBonds[0])): $bt = $TotalHoldingInfoBonds[0];
        $btXirr = $bt['Total_Bond_Return'] ?? ''; $btXirrNum = (float)str_replace(',','',$btXirr);
    ?>
    <tfoot>
        <tr class="total-row">
            <td colspan="4"><strong>Total Bonds</strong></td>
            <td class="text-right"><strong><?= fn2($bt['Total_Bond_Cost']              ?? 0) ?></strong></td>
            <td></td>
            <td class="text-right"><strong><?= fn2($bt['Total_Bond_Payout_Recvd']      ?? 0) ?></strong></td>
            <td class="text-right"><strong><?= fn2($bt['Total_Bond_Profit_Booked']     ?? 0, true) ?></strong></td>
            <td class="text-right"><strong><?= fn2($bt['Total_Bond_Unrealized_Profit'] ?? 0, true) ?></strong></td>
            <td class="text-right"><strong><?= fn2($bt['Total_Bond_Current_Value']     ?? 0) ?></strong></td>
            <td class="text-right <?= $btXirrNum >= 0 ? 'positive' : 'negative' ?>">
                <strong><?= is_numeric(str_replace(',','',$btXirr)) ? number_format($btXirrNum,2).'%' : '—' ?></strong>
            </td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
