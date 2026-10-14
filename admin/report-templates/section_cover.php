<?php
/**
 * section_cover.php — Cover page
 * Chart 1: Since Inception XIRR only — two horizontal bars (Portfolio vs Nifty 500 Mirror)
 * Chart 2: Donut + legend table
 */

$info  = $totalAssetInfo[0] ?? [];
$total = (float)str_replace(',', '', $info['Total_Current_Value'] ?? 0);

if (!function_exists('sc_strip')) {
    function sc_strip($v): float { return (float)str_replace(',', '', $v ?? 0); }
}

$allocData = array_values(array_filter([
    ['label'=>'Equity', 'value'=>sc_strip($info['Total_Equity_Curr_Value'] ?? 0),
     'color'=>'#1E3A5F', 'xirr'=>sc_strip($info['Total_Equity_Return'] ?? 0)],
    ['label'=>'Debt',   'value'=>sc_strip($info['Total_Debt_Curr_Value']   ?? 0),
     'color'=>'#E87722', 'xirr'=>sc_strip($info['Total_Debt_Return']   ?? 0)],
    ['label'=>'Hybrid', 'value'=>sc_strip($info['Total_Hybrid_Curr_Value'] ?? 0),
     'color'=>'#0D7C5F', 'xirr'=>sc_strip($info['Total_Hybrid_Return'] ?? 0)],
    ['label'=>'Gold',   'value'=>sc_strip($info['Total_Gold_Curr_Value']   ?? 0),
     'color'=>'#D4AC0D', 'xirr'=>sc_strip($info['Total_Gold_Return']   ?? 0)],
], fn($r) => $r['value'] > 0));

if (!function_exists('qcPost')) {
    function qcPost(string $payload): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init('https://quickchart.io/chart');
            curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
                CURLOPT_POSTFIELDS=>$payload]);
            $r = curl_exec($ch); curl_close($ch);
        } else {
            $r = @file_get_contents('https://quickchart.io/chart', false,
                stream_context_create(['http'=>['method'=>'POST',
                    'header'=>"Content-Type: application/json\r\n",
                    'content'=>$payload,'timeout'=>15]]));
        }
        return ($r && strlen($r) > 500) ? 'data:image/png;base64,'.base64_encode($r) : null;
    }
}

$portXirr = $sinceInceptionHeld['portfolio_xirr'] ?? 0;
$bmXirr   = $sinceInceptionHeld['benchmark_xirr'] ?? 0;
$alpha    = round($portXirr - $bmXirr, 2);
$alphaCss  = $alpha >= 0 ? '#1a7a4a' : '#c0392b';
$alphaSign = $alpha >= 0 ? '+' : '';

$firstDateVal = !empty($sinceInceptionHeld['first_date']) ? date('d-m-Y', strtotime($sinceInceptionHeld['first_date'])) : '';

$assetClassPng      = $bm->buildAssetClassXirrChart($totalAssetInfo, $headName);
$chartInceptionAll  = $bm->buildSinglePeriodChart($sinceInception ?? [], 'Since Inception', $headName . '_all', 320, 180);
$chartInceptionHeld = $bm->buildSinglePeriodChart($sinceInceptionHeld ?? [], 'Since Inception', $headName . '_held', 320, 180);

$growthPng = $bm->buildGrowthChart($sinceInception ?? [], $headName);

$alphaAll = ($sinceInception['portfolio_xirr'] ?? 0) - ($sinceInception['benchmark_xirr'] ?? 0);
$alphaHeld = ($sinceInceptionHeld['portfolio_xirr'] ?? 0) - ($sinceInceptionHeld['benchmark_xirr'] ?? 0);

$alphaAllStr = 'Alpha: ' . ($alphaAll >= 0 ? '+' : '') . number_format($alphaAll, 2) . '%';
$alphaAllColor = $alphaAll >= 0 ? '#1a7a4a' : '#c0392b';
$alphaHeldStr = 'Alpha: ' . ($alphaHeld >= 0 ? '+' : '') . number_format($alphaHeld, 2) . '%';
$alphaHeldColor = $alphaHeld >= 0 ? '#1a7a4a' : '#c0392b';

// ── Chart 2: Clean donut ──────────────────────────────────────────────────────
$chart2 = null;
if ($total > 0 && !empty($allocData)) {
    $donutData = []; $donutColors = [];
    foreach ($allocData as $a) {
        $donutData[]   = round($a['value'] / $total * 100, 2);
        $donutColors[] = $a['color'];
    }
    $chart2 = qcPost(json_encode([
        'version' => '3',
        'chart' => [
            'type' => 'doughnut',
            'data' => ['datasets'=>[[
                'data'=>$donutData,'backgroundColor'=>$donutColors,
                'borderColor'=>'#ffffff','borderWidth'=>3,'hoverOffset'=>0,
            ]]],
            'options' => [
                'cutout'  => '58%',
                'layout'  => ['padding'=>6],
                'plugins' => [
                    'title'=>['display'=>false],'legend'=>['display'=>false],
                    'datalabels'=>['display'=>false],
                ],
                'animation'=>['animateRotate'=>false,'animateScale'=>false],
            ],
        ],
        'width'=>180,'height'=>180,'backgroundColor'=>'transparent','format'=>'png',
    ]));
}

// ── Chart 3: Family Distribution ──────────────────────────────────────────────
$familyChartPng = null;
if (!empty($familyDistribution) && count($familyDistribution) > 1) {
    $famLabels = [];
    $famValues = [];
    $famColors = ['#1D3557', '#E87722', '#0D7C5F', '#D4AC0D', '#8E44AD', '#3498DB', '#1ABC9C'];
    foreach ($familyDistribution as $i => $fd) {
        $famLabels[] = $fd['name'];
        $famValues[] = round($fd['value'], 0);
    }
    $payload = json_encode([
        'version' => '3',
        'chart' => [
            'type' => 'bar',
            'data' => [
                'labels' => $famLabels,
                'datasets' => [[
                    'data' => $famValues,
                    'backgroundColor' => array_slice($famColors, 0, count($famValues)),
                    'borderRadius' => 4,
                    'borderSkipped' => false
                ]]
            ],
            'options' => [
                'indexAxis' => 'y',
                'plugins' => [
                    'title' => ['display' => true, 'text' => 'Family Wealth Distribution', 'color' => '#1D3557', 'font' => ['size' => 12, 'weight' => 'bold'], 'align' => 'start'],
                    'legend' => ['display' => false],
                    'datalabels' => [
                        'display' => true, 'anchor' => 'end', 'align' => 'right',
                        'formatter' => "function(v){return '₹' + (v/100000).toFixed(1) + 'L';}",
                        'font' => ['size' => 10, 'weight' => 'bold']
                    ]
                ],
                'scales' => [
                    'x' => ['grid' => ['display' => false], 'ticks' => ['display' => false]],
                    'y' => ['grid' => ['display' => false], 'ticks' => ['font' => ['size' => 10, 'weight' => 'bold']]]
                ],
                'layout' => ['padding' => ['right' => 50, 'left' => 0]]
            ]
        ],
        'width' => 450,
        'height' => 180,
        'backgroundColor' => 'transparent',
        'format' => 'png'
    ]);
    $familyChartPng = qcPost($payload);
}
?>

<!-- Cover header -->
<div class="cover-wrapper">
    <div class="cover-firm">LITM</div>
    <div class="cover-title">
        <?= (!empty($isFamilyCover)) ? 'Family ' : '' ?>Portfolio Statement
        <?php if (!empty($isCasReport)): ?>
            <br><span style="font-size: 9pt; color: #7A8FA6; font-weight: normal;">(based on CAS Statement)</span>
        <?php endif; ?>
    </div>
    <hr style="width:50%;border-color:#2c5f8a;margin:12px auto;"/>
    <div class="cover-client"><?= htmlspecialchars($headName) ?></div>
    <div class="cover-meta">PAN: <?= htmlspecialchars($userpan) ?></div>
    <?php if ($address && $address !== 'Not available'): ?>
        <div class="cover-meta" style="font-size:8pt;color:#666;"><?= nl2br(htmlspecialchars($address)) ?></div>
    <?php endif; ?>
    <div class="cover-date">Statement Date: <?= isset($db) ? date('d F Y', strtotime($db->getFundNAVDate())) : date('d F Y') ?></div>
</div>

<!-- Key metrics strip -->
<table style="margin:10px auto;width:95%;border:0.5pt solid #dde8f4;">
    <tr style="background:#f0f6ff;">
    <?php foreach ([
        ['Portfolio Value',   number_format($total,0),          '#1D3557'],
        ['Portfolio XIRR',    number_format($portXirr,2).'%',              $portXirr>=0?'#1a7a4a':'#c0392b'],
        ['Nifty 500 Mirror',  number_format($bmXirr,2).'%',               $bmXirr>=0?'#1a7a4a':'#c0392b'],
        ['Alpha',             $alphaSign.number_format($alpha,2).'%',     $alphaCss],
        ['Since',             $firstDateVal,                              '#555'],
    ] as [$lbl,$val,$col]): ?>
        <td style="text-align:center;padding:7px 5px;border-right:0.4pt solid #dde;">
            <div style="font-size:6.5pt;color:#888;margin-bottom:2px;"><?= $lbl ?></div>
            <div style="font-size:11pt;font-weight:bold;color:<?= $col ?>;white-space:nowrap;"><?= $val ?></div>
        </td>
    <?php endforeach; ?>
    </tr>
</table>

<?php if ($familyChartPng): ?>
<!-- Family Wealth Distribution -->
<table style="width:100%;margin-top:8px;border-collapse:separate;border-spacing:8px 0;">
    <tr>
        <td style="width:100%;vertical-align:top;text-align:left;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <img src="<?= $familyChartPng ?>" style="width:100%;max-width:450px;display:block;margin-left:0;"/>
        </td>
    </tr>
</table>
<?php endif; ?>

<!-- ROW 1: Allocation Donut (left) + Asset Class XIRR (right) -->
<table style="width:100%;margin-top:8px;border-collapse:separate;border-spacing:8px 0;">
    <tr>
        <td style="width:50%;vertical-align:top;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:8px;text-align:center;">
                Asset Allocation
            </div>
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:40%;vertical-align:middle;text-align:center;padding-right:4px;">
                        <?php if ($chart2): ?>
                            <img src="<?= $chart2 ?>" style="width:100%;max-width:180px;"/>
                        <?php else: ?>
                            <table style="margin:0 auto;">
                            <?php foreach ($allocData as $a):
                                $h = round($a['value']/$total*100,2); ?>
                                <tr><td style="background:<?= $a['color'] ?>;height:<?= max($h/2,4) ?>px;width:28px;"></td></tr>
                            <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </td>
                    <td style="width:60%;vertical-align:middle;">
                        <table style="width:100%;border-collapse:collapse;font-size:8pt;">
                            <tr style="border-bottom:0.3pt solid #ddd;">
                                <td style="font-size:6.5pt;color:#888;padding:1px 2px;">Asset</td>
                                <td style="font-size:6.5pt;color:#888;padding:1px 2px;text-align:right;">Alloc</td>
                                <td style="font-size:6.5pt;color:#888;padding:1px 2px;text-align:right;">Amount</td>
                            </tr>
                            <?php foreach ($allocData as $a):
                                $pct  = $total>0 ? round($a['value']/$total*100,2) : 0;
                            ?>
                            <tr>
                                <td style="padding:3px 2px 3px 0;white-space:nowrap;">
                                    <span style="display:inline-block;width:9px;height:9px;background:<?= $a['color'] ?>;border-radius:2px;margin-right:3px;vertical-align:middle;"></span>
                                    <strong style="color:<?= $a['color'] ?>;"><?= $a['label'] ?></strong>
                                </td>
                                <td style="text-align:right;padding:3px 2px;font-weight:bold;color:#333;"><?= number_format($pct,2) ?>%</td>
                                <td style="text-align:right;padding:3px 2px;font-weight:bold;color:#333;"><?= number_format($a['value'],0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="border-top:0.5pt solid #bbb;">
                                <td style="padding:3px 2px 1px 0;font-weight:bold;">Total</td>
                                <td style="text-align:right;padding:3px 2px;font-weight:bold;">100%</td>
                                <td style="text-align:right;padding:3px 2px;font-weight:bold;color:#1D3557;"><?= number_format($total,0) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
        
        <!-- Right: Asset Class XIRR -->
        <td style="width:50%;vertical-align:middle;text-align:center;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:8px;">
                XIRR — Asset Classes
            </div>
            <?php if ($assetClassPng): ?>
                <img src="<?= BenchmarkXirr::pngToDataUri($assetClassPng) ?>" style="width:100%;max-width:340px;"/>
            <?php else: ?>
                <p style="font-size:8pt;color:#888;margin-top:20px;">No asset class XIRR data available.</p>
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- ROW 2: XIRR Since Inception (All vs Held) -->
<table style="width:100%;margin-top:8px;border-collapse:separate;border-spacing:8px 0;">
    <tr>
        <td style="width:50%;vertical-align:top;text-align:center;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:4px;">
                XIRR — Lifetime <small style="color: #777; font-size: 80%;">(All Assets)</small>
            </div>
            <?php if ($chartInceptionAll): ?>
                <img src="<?= BenchmarkXirr::pngToDataUri($chartInceptionAll) ?>" style="width:100%;max-width:340px;"/>
            <?php endif; ?>
            <div style="font-size:9pt;font-weight:bold;color:<?= $alphaAllColor ?>;margin-top:3px;">
                <?= $alphaAllStr ?>
            </div>
        </td>
        <td style="width:50%;vertical-align:top;text-align:center;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px;">
            <div style="font-size:10pt;font-weight:bold;color:#1D3557;margin-bottom:4px;">
                XIRR — Active <small style="color: #777; font-size: 80%;">(All Assets)</small>
            </div>
            <?php if ($chartInceptionHeld): ?>
                <img src="<?= BenchmarkXirr::pngToDataUri($chartInceptionHeld) ?>" style="width:100%;max-width:340px;"/>
            <?php endif; ?>
            <div style="font-size:9pt;font-weight:bold;color:<?= $alphaHeldColor ?>;margin-top:3px;">
                <?= $alphaHeldStr ?>
            </div>
        </td>
    </tr>
</table>

<!-- ROW 3: Growth Chart -->
<?php if ($growthPng && empty($isFamilyCover)): ?>
<table style="width:100%;margin-top:8px;border-collapse:separate;border-spacing:8px 0;">
    <tr>
        <td style="width:100%;vertical-align:top;text-align:center;border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px 10px 2px 10px;">
            <div style="font-size:10pt;font-weight:bold;color:#1E3A5F;margin-bottom:4px;letter-spacing:0.3px;">
                Growth of 1,00,000 since inception
            </div>
            <div style="text-align:center;">
                <img src="<?= BenchmarkXirr::pngToDataUri($growthPng) ?>" style="width:100%;max-width:580px;"/>
            </div>
            <p style="font-size:6.5pt;color:#7A8FA6;margin:3px 0 0;line-height:1.4;">
                Both curves use XIRR-based compounding from a 1,00,000 base. The higher-XIRR series always lies above the lower.
            </p>
        </td>
    </tr>
</table>
<?php endif; ?>
