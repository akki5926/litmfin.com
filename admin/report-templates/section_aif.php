<?php
/**
 * section_aif.php — AIF Holdings
 *
 * AIF data keys: aifName, assetClass, units, costNAV, costValue,
 *   currNAV, payoutRecvd, realizedProfit, unRealizedProfit, currValue, XIRR
 * $TotalHoldingInfoAif[0]: Total_Aif_Cost, Total_Aif_Payout_Recvd,
 *   Total_Aif_Profit_Booked, Total_Aif_Unrealized_Profit, Total_Aif_Current_Value, Total_Aif_Return
 * $SoldAifInfo keys: Aif_Name, Asset_Class, Unit_Holdings, PayoutRecvd, RealizedProfit, XIRR
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
<div class="section-header">Alternative Investment Fund (AIF) Holdings</div>

<table>
    <thead>
        <tr>
            <th style="width:18%">AIF Name</th>
            <th style="width:7%">Asset Class</th>
            <th class="text-right" style="width:8%">Units</th>
            <th class="text-right" style="width:7%">Avg Cost NAV</th>
            <th class="text-right" style="width:11%">Cost Value</th>
            <th class="text-right" style="width:7%">Curr NAV</th>
            <th class="text-right" style="width:8%">Payout Recv'd</th>
            <th class="text-right" style="width:8%">Booked P&L</th>
            <th class="text-right" style="width:9%">Unrealized P&L</th>
            <th class="text-right" style="width:11%">Curr Value</th>
            <th class="text-right" style="width:6%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($HoldingInfoAif as $issuerName => $aifGroups):
        foreach ($aifGroups as $aifHoldings):
            foreach ($aifHoldings as $a):
                $xirr = $a['XIRR'] ?? ''; $xirrNum = (float)str_replace(',','',$xirr); $xirrCls = $xirrNum >= 0 ? 'positive' : 'negative';
    ?>
        <tr>
            <td><?= htmlspecialchars($a['aifName'] ?? '') ?></td>
            <td class="text-center"><?= htmlspecialchars($a['assetClass'] ?? '') ?></td>
            <td class="text-right"><span style="white-space:nowrap"><?= $a['units'] ?? '0' ?></span></td>
            <td class="text-right"><span style="white-space:nowrap"><?= $a['costNAV'] ?? '—' ?></span></td>
            <td class="text-right"><?= fn2($a['costValue'] ?? 0) ?></td>
            <td class="text-right"><span style="white-space:nowrap"><?= $a['currNAV'] ?? '—' ?></span></td>
            <td class="text-right"><?= fn2($a['payoutRecvd'] ?? 0) ?></td>
            <td class="text-right"><?= fn2($a['realizedProfit'] ?? 0, true) ?></td>
            <td class="text-right"><?= fn2($a['unRealizedProfit'] ?? 0, true) ?></td>
            <td class="text-right"><?= fn2($a['currValue'] ?? 0) ?></td>
            <td class="text-right <?= $xirrCls ?>"><?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?></td>
        </tr>
    <?php endforeach; endforeach; endforeach; ?>
    </tbody>
    <?php if (!empty($TotalHoldingInfoAif[0])): $at = $TotalHoldingInfoAif[0];
        $atX = $at['Total_Aif_Return'] ?? ''; $atXN = (float)str_replace(',','',$atX); ?>
    <tfoot>
        <tr class="total-row">
            <td colspan="4"><strong>Total AIF</strong></td>
            <td class="text-right"><strong><?= fn2($at['Total_Aif_Cost'] ?? 0) ?></strong></td>
            <td></td>
            <td class="text-right"><strong><?= fn2($at['Total_Aif_Payout_Recvd'] ?? 0) ?></strong></td>
            <td class="text-right"><strong><?= fn2($at['Total_Aif_Profit_Booked'] ?? 0, true) ?></strong></td>
            <td class="text-right"><strong><?= fn2($at['Total_Aif_Unrealized_Profit'] ?? 0, true) ?></strong></td>
            <td class="text-right"><strong><?= fn2($at['Total_Aif_Current_Value'] ?? 0) ?></strong></td>
            <td class="text-right <?= $atXN >= 0 ? 'positive' : 'negative' ?>"><strong><?= is_numeric(str_replace(',','',$atX)) ? number_format($atXN,2).'%' : '—' ?></strong></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<?php if (!empty($SoldAifInfo)): ?>
<div class="sub-header mt-8">Exited AIF</div>
<table>
    <thead>
        <tr>
            <th style="width:40%">AIF Name</th>
            <th style="width:12%">Asset Class</th>
            <th class="text-right" style="width:12%">Units</th>
            <th class="text-right" style="width:12%">Payout Recv'd</th>
            <th class="text-right" style="width:14%">Booked P&L</th>
            <th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($SoldAifInfo as $sa):
        $xirr = $sa['XIRR'] ?? ''; $xirrNum = (float)str_replace(',','',$xirr); ?>
        <tr>
            <td><?= htmlspecialchars($sa['Aif_Name'] ?? '') ?></td>
            <td class="text-center"><?= htmlspecialchars($sa['Asset_Class'] ?? '') ?></td>
            <td class="text-right"><span style="white-space:nowrap"><?= $sa['Unit_Holdings'] ?? '0' ?></span></td>
            <td class="text-right"><?= fn2($sa['PayoutRecvd'] ?? 0) ?></td>
            <td class="text-right"><?= fn2($sa['RealizedProfit'] ?? 0, true) ?></td>
            <td class="text-right <?= $xirrNum >= 0 ? 'positive' : 'negative' ?>"><?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
