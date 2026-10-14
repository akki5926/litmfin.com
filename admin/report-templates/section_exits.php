<?php
/**
 * section_exits.php — Exited / Zero-holding investments
 *
 * Rows sorted alphabetically: primary = Asset Class, secondary = Fund/Bond Name.
 *
 * $ZeroHoldingFundInfo: Fund_Name, Asset_Class, Unit_Holdings, RealizedProfit, XIRR, Fund_House_Name
 * $ZeroHoldingBondInfo: Bond_Name, Asset_Class, Unit_Holdings, PayoutRecvd, RealizedProfit, XIRR
 *
 * PMS exits → section_pms.php, AIF exits → section_aif.php
 */
if (!function_exists('n')) { function n($v): float { return (float)str_replace(',', '', $v ?? 0); } }
if (!function_exists('fn2')) {
    function fn2($v, bool $color = false): string {
        $f = n($v); $s = number_format($f, 2);
        if ($color) { $c = $f >= 0 ? 'positive' : 'negative'; return '<span class="'.$c.'" style="white-space:nowrap">'.$s.'</span>'; }
        return '<span style="white-space:nowrap">'.$s.'</span>';
    }
}

$hasExits = !empty($ZeroHoldingFundInfo) || !empty($ZeroHoldingBondInfo);
if (!$hasExits) return;

// Sort helper: by Asset_Class asc, then name asc (case-insensitive)
if (!function_exists('exitSort')) {
    function exitSort(array &$arr, string $classKey, string $nameKey): void {
        usort($arr, function($a, $b) use ($classKey, $nameKey) {
            $ac = strtoupper($a[$classKey] ?? '');
            $bc = strtoupper($b[$classKey] ?? '');
            if ($ac !== $bc) return strcmp($ac, $bc);
            return strcasecmp($a[$nameKey] ?? '', $b[$nameKey] ?? '');
        });
    }
}

// Sort MF exits
if (!empty($ZeroHoldingFundInfo)) {
    $mfExits = $ZeroHoldingFundInfo;
    exitSort($mfExits, 'Asset_Class', 'Fund_Name');
}

// Sort Bond exits
if (!empty($ZeroHoldingBondInfo)) {
    $bondExits = $ZeroHoldingBondInfo;
    exitSort($bondExits, 'Asset_Class', 'Bond_Name');
}
?>

<div class="section-header">Exits — Funds Sold / Fully Redeemed</div>

<?php if (!empty($mfExits)): ?>
<div class="sub-header">Mutual Fund Exits
	<!--
    <span style="font-size:7.5pt; font-weight:normal; color:#888;">
        — sorted by Asset Class then Fund Name
    </span>
	-->
</div>
<table>
    <thead>
        <tr>
            <th style="width:10%">Asset Class</th>
            <th style="width:40%">Fund Name</th>
            <th class="text-right" style="width:15%">Units at Exit</th>
            <th class="text-right" style="width:18%">Booked P&amp;L</th>
            <th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $prevClass = null;
    foreach ($mfExits as $sf):
        $xirr    = $sf['XIRR'] ?? '';
        $xirrNum = (float)str_replace(',', '', $xirr);
        $curClass = strtoupper($sf['Asset_Class'] ?? '');
        // Subtle shading per asset class group
        $rowBg = ($curClass !== $prevClass) ? 'background:#f9f9f9;' : '';
        $prevClass = $curClass;
    ?>
        <tr style="<?= $rowBg ?>">
            <td class="text-center">
                <span style="font-size:7pt; font-weight:bold; color:#555;">
                    <?= htmlspecialchars($sf['Asset_Class'] ?? '') ?>
                </span>
            </td>
            <td>
                <?= htmlspecialchars(cleanFundName($sf['Fund_Name'] ?? '')) ?>
            </td>
            <td class="text-right"><span style="white-space:nowrap"><?= $sf['Unit_Holdings'] ?? '0' ?></span></td>
            <td class="text-right"><?= fn2($sf['RealizedProfit'] ?? 0, true) ?></td>
            <td class="text-right <?= $xirrNum >= 0 ? 'positive' : 'negative' ?>">
                <?= is_numeric(str_replace(',', '', $xirr)) ? number_format($xirrNum, 2).'%' : '—' ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($bondExits)): ?>
<div class="sub-header mt-8">Bond / SGB Exits
    <span style="font-size:7.5pt; font-weight:normal; color:#888;">
        — sorted by Asset Class then Bond Name
    </span>
</div>
<table>
    <thead>
        <tr>
            <th style="width:10%">Asset Class</th>
            <th style="width:33%">Bond Name</th>
            <th class="text-right" style="width:10%">Units</th>
            <th class="text-right" style="width:13%">Payout Recv'd</th>
            <th class="text-right" style="width:14%">Booked P&amp;L</th>
            <th class="text-right" style="width:10%">XIRR %</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $prevClass = null;
    foreach ($bondExits as $sb):
        $xirr    = $sb['XIRR'] ?? '';
        $xirrNum = (float)str_replace(',', '', $xirr);
        $curClass = strtoupper($sb['Asset_Class'] ?? '');
        $rowBg = ($curClass !== $prevClass) ? 'background:#f9f9f9;' : '';
        $prevClass = $curClass;
    ?>
        <tr style="<?= $rowBg ?>">
            <td class="text-center">
                <span style="font-size:7pt; font-weight:bold; color:#555;">
                    <?= htmlspecialchars($sb['Asset_Class'] ?? '') ?>
                </span>
            </td>
            <td><?= htmlspecialchars($sb['Bond_Name'] ?? '') ?></td>
            <td class="text-right"><span style="white-space:nowrap"><?= $sb['Unit_Holdings'] ?? '0' ?></span></td>
            <td class="text-right"><?= fn2($sb['PayoutRecvd']    ?? 0) ?></td>
            <td class="text-right"><?= fn2($sb['RealizedProfit'] ?? 0, true) ?></td>
            <td class="text-right <?= $xirrNum >= 0 ? 'positive' : 'negative' ?>">
                <?= is_numeric(str_replace(',', '', $xirr)) ? number_format($xirrNum, 2).'%' : '—' ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
