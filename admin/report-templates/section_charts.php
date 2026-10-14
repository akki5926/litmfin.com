<?php
/**
 * section_charts.php
 *
 * Chart 1: "Portfolio vs Nifty 500 Mirror — XIRR (%)"
 *   Vertical grouped bar chart — all three periods side by side on X-axis.
 *   Portfolio = dark navy, Nifty 500 Mirror = orange.
 *   Values displayed on top of each bar. No gridlines. Legend at bottom.
 *   Styled like the "Fund v/s Benchmark Return (%)" chart in the sample report.
 *
 * Chart 2: Growth of 1,00,000 — both curves XIRR-compounded.
 */
?>
<!--
<div class="section-header">Portfolio vs Nifty 500 Mirror — Benchmark Analysis</div>
-->
<div class="section-header">Portfolio vs Nifty 500 Mirror</div>
<?php
// ── Chart 1: All Assets (Incl. Sold) vs Nifty 500 Mirror ────────────────────
$compAllPng = $bm->buildXirrComparisonChart($sinceInception, $rolling3yr, $rolling5yr, $headName . '_all');

// Compute alpha for each period for the caption table (All)
$periodsAll = [
    'Since Inception' => $sinceInception,
];
if (!empty($rolling3yr)) {
    $periodsAll['Last 3 Years'] = $rolling3yr;
}
if (!empty($rolling5yr)) {
    $periodsAll['Last 5 Years'] = $rolling5yr;
}
?>

<!-- Chart box -->
<div style="border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px 10px 6px 10px;margin-bottom:8px;">

    <!-- Section subtitle -->
    <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:4px;text-align:center;">
        XIRR — Lifetime <small style="color: #777; font-size: 80%;">(All Assets)</small>
    </div>

    <!-- Chart image -->
    <?php if ($compAllPng): ?>
        <div style="text-align:center;">
            <img src="<?= BenchmarkXirr::pngToDataUri($compAllPng) ?>"
                 style="width:100%;max-width:560px;"/>
        </div>
    <?php else: ?>
        <!-- HTML fallback if QuickChart unavailable -->
        <table style="margin:6px 0;">
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="text-right">Portfolio</th>
                    <th class="text-right">Nifty 500 Mirror</th>
                    <th class="text-right">Alpha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periodsAll as $lbl => $r):
                $p = $r['portfolio_xirr'] ?? 0;
                $n = $r['benchmark_xirr'] ?? 0;
                $a = round($p - $n, 2);
            ?>
                <tr>
                    <td><?= $lbl ?></td>
                    <td class="text-right <?= $p>=0?'positive':'negative' ?>"><?= number_format($p,2) ?>%</td>
                    <td class="text-right <?= $n>=0?'positive':'negative' ?>"><?= number_format($n,2) ?>%</td>
                    <td class="text-right <?= $a>=0?'positive':'negative' ?>"><?= ($a>=0?'+':'').number_format($a,2) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

 
	
</div>

<?php
// ── Chart 2: Currently Held Assets vs Nifty 500 Mirror ────────────────────
$compHeldPng = $bm->buildXirrComparisonChart($sinceInceptionHeld, $rolling3yrHeld, $rolling5yrHeld, $headName . '_held');

// Compute alpha for each period for the caption table (Held)
$periodsHeld = [
    'Since Inception' => $sinceInceptionHeld,
];
if (!empty($rolling3yrHeld)) {
    $periodsHeld['Last 3 Years'] = $rolling3yrHeld;
}
if (!empty($rolling5yrHeld)) {
    $periodsHeld['Last 5 Years'] = $rolling5yrHeld;
}
?>

<!-- Chart box -->
    <div style="border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px 10px 6px 10px;margin-bottom:8px;">

        <!-- Section subtitle -->
        <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:4px;text-align:center;">
            XIRR — Active <small style="color: #777; font-size: 80%;">(All Assets)</small>
        </div>

        <!-- Chart image -->
        <?php if ($compHeldPng): ?>
            <div style="text-align:center;">
                <img src="<?= BenchmarkXirr::pngToDataUri($compHeldPng) ?>"
                     style="width:100%;max-width:560px;"/>
            </div>
        <?php else: ?>
            <!-- HTML fallback if QuickChart unavailable -->
            <table style="margin:6px 0;">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th class="text-right">Portfolio</th>
                        <th class="text-right">Nifty 500 Mirror</th>
                        <th class="text-right">Alpha</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($periodsHeld as $lbl => $r):
                    $p = $r['portfolio_xirr'] ?? 0;
                    $n = $r['benchmark_xirr'] ?? 0;
                    $a = round($p - $n, 2);
                ?>
                    <tr>
                        <td><?= $lbl ?></td>
                        <td class="text-right <?= $p>=0?'positive':'negative' ?>"><?= number_format($p,2) ?>%</td>
                        <td class="text-right <?= $n>=0?'positive':'negative' ?>"><?= number_format($n,2) ?>%</td>
                        <td class="text-right <?= $a>=0?'positive':'negative' ?>"><?= ($a>=0?'+':'').number_format($a,2) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
