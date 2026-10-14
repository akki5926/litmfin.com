<?php
/**
 * section_pms.php — PMS Holdings
 *
 * $HoldingInfoPms keyed by issuer → pms groups → pms data
 * PMS data keys: pmsName, assetClass, totalPurchaseCost, totalSellValue, currValue, XIRR
 *
 * $TotalHoldingInfoPms[0] keys:
 *   Total_Pms_Cost, Total_Pms_Sold, Total_Pms_Current_Value, Total_Pms_Return
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
<div class="section-header">Portfolio Management Service (PMS) Holdings</div>

<table>
    <thead>
        <tr>
            <th style="width:35%">PMS Name</th>
            <th style="width:12%">Asset Class</th>
            <th class="text-right" style="width:16%">Invested</th>
            <th class="text-right" style="width:15%">Sold</th>
            <th class="text-right" style="width:15%">Current Value</th>
            <th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($HoldingInfoPms as $providerName => $pmsGroups):
        foreach ($pmsGroups as $pmsHoldings):
            foreach ($pmsHoldings as $p):
                $xirr    = $p['XIRR'] ?? '';
                $xirrNum = (float)str_replace(',', '', $xirr);
                $xirrCls = $xirrNum >= 0 ? 'positive' : 'negative';
    ?>
        <tr>
            <td>
                <?= htmlspecialchars($p['pmsName'] ?? '') ?>
            </td>
            <td class="text-center"><?= htmlspecialchars($p['assetClass'] ?? '') ?></td>
            <td class="text-right"><?= fn2($p['totalPurchaseCost'] ?? 0) ?></td>
            <td class="text-right"><?= fn2($p['totalSellValue']    ?? 0) ?></td>
            <td class="text-right"><?= fn2($p['currValue']         ?? 0) ?></td>
            <td class="text-right <?= $xirrCls ?>">
                <?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?>
            </td>
        </tr>
    <?php endforeach; endforeach; endforeach; ?>
    </tbody>
    <?php if (!empty($TotalHoldingInfoPms[0])): $pt = $TotalHoldingInfoPms[0];
        $ptXirr = $pt['Total_Pms_Return'] ?? ''; $ptXirrNum = (float)str_replace(',','',$ptXirr);
    ?>
    <tfoot>
        <tr class="total-row">
            <td><strong>Total PMS</strong></td>
            <td></td>
            <td class="text-right"><strong><?= fn2($pt['Total_Pms_Cost']          ?? 0) ?></strong></td>
            <td class="text-right"><strong><?= fn2($pt['Total_Pms_Sold']          ?? 0) ?></strong></td>
            <td class="text-right"><strong><?= fn2($pt['Total_Pms_Current_Value'] ?? 0) ?></strong></td>
            <td class="text-right <?= $ptXirrNum >= 0 ? 'positive' : 'negative' ?>">
                <strong><?= is_numeric(str_replace(',','',$ptXirr)) ? number_format($ptXirrNum,2).'%' : '—' ?></strong>
            </td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<?php if (!empty($SoldPmsInfo)): ?>
<div class="sub-header mt-8">Exited PMS</div>
<table>
    <thead>
        <tr>
            <th style="width:40%">PMS Issuer</th>
            <th style="width:35%">PMS Name</th>
            <th style="width:15%">Asset Class</th>
            <th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($SoldPmsInfo as $sp):
        $xirr = $sp['XIRR'] ?? ''; $xirrNum = (float)str_replace(',','',$xirr); ?>
        <tr>
            <td><?= htmlspecialchars($sp['Pms_Issuer_Name'] ?? '') ?></td>
            <td><?= htmlspecialchars($sp['Pms_Name'] ?? '') ?></td>
            <td class="text-center"><?= htmlspecialchars($sp['Asset_Class'] ?? '') ?></td>
            <td class="text-right <?= $xirrNum >= 0 ? 'positive' : 'negative' ?>">
                <?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
