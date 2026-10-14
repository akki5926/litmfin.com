<?php
/**
 * SIF Exits Section
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
<div class="sub-header mt-8">SIF Exits</div>
<table>
    <thead>
        <tr>
            <th style="width:40%">SIF Name</th>
            <th style="width:12%">Asset Class</th>
            <th class="text-right" style="width:12%">Units</th>
            <th class="text-right" style="width:14%">Booked P&amp;L</th>
            <th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($SoldSifInfo as $sa):
            $xirr = $sa['XIRR'] ?? ''; $xirrNum = (float)str_replace(',','',$xirr); ?>
            <tr>
                <td><?= htmlspecialchars($sa['Fund_Name'] ?? '') ?></td>
                <td class="text-center"><?= htmlspecialchars($sa['Asset_Class'] ?? '') ?></td>
                <td class="text-right"><span style="white-space:nowrap"><?= $sa['Unit_Holdings'] ?? '0' ?></span></td>
                <td class="text-right"><?= fn2($sa['RealizedProfit'] ?? 0, true) ?></td>
                <td class="text-right <?= $xirrNum >= 0 ? 'positive' : 'negative' ?>"><?= is_numeric(str_replace(',','',$xirr)) ? number_format($xirrNum,2).'%' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>