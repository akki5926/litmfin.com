<?php
/**
 * section_analytics.php
 *
 * Three blocks on one page:
 *   1. Top 10 Company Holdings  — horizontal bar chart (QuickChart)
 *   2. Top 10 Sector Exposure   — horizontal bar chart (QuickChart)
 *   3. Fund Metrics             — table: trailing returns + risk stats per fund
 *
 * Variables available from generatereport.php scope:
 *   $companyAgg   array  [{symbol, name, amount, percent}, ...]  top 10, sorted by amount
 *   $sectorAgg    array  [{sector, amount, percent}, ...]        top 10, sorted by amount
 *   $fundMetrics  array  [isin => {scheme_name, returns:{1y,3y,5y}, risk:{3y,5y:{alpha,beta,stddev}}}]
 *   $totalMFValue float  total MF portfolio value (base for %)
 */

// ── QuickChart POST helper (reuse from section_cover.php if already defined) ──
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

// ── Horizontal bar chart builder ──────────────────────────────────────────────
// Produces a horizontal bar chart from labels+values.
// barColor: fill colour; maxItems: truncate label to N chars; width/height in px.
if (!function_exists('analyticsHBarChart')) {
    function analyticsHBarChart(
        array  $labels,
        array  $values,
        string $barColor,
        string $title,
        int    $width  = 360,
        int    $height = 260
    ): ?string {
        if (empty($labels)) return null;

        $maxVal     = max(array_map('abs', $values ?: [1]));
        $suggestMax = round($maxVal * 1.35 + 0.5, 1);

        $payload = json_encode([
            'version' => '3',    // Chart.js v3 — required for indexAxis:'y' syntax
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels'   => $labels,
                    'datasets' => [[
                        'data'            => $values,
                        'backgroundColor' => $barColor,
                        'borderRadius'    => 3,
                        'borderSkipped'   => false,
                        'barThickness'    => 12,
                    ]],
                ],
                'options' => [
                    'indexAxis' => 'y',
                    'plugins'   => [
                        'title'  => ['display' => false],
                        'legend' => ['display' => false],
                        'datalabels' => [
                            'display'   => true,
                            'anchor'    => 'end',
                            'align'     => 'end',     // 'end' aligns the label AFTER the anchor point
                            'clamp'     => false,     // allow label outside chart area
                            'color'     => '#222',
                            'font'      => ['size' => 9, 'weight' => 'bold'],
                            'formatter' => "function(v){return v.toFixed(2)+'%'}",
                        ],
                    ],
                    'scales' => [
                        'x' => [
                            'display'    => false,
                            'suggestMax' => $suggestMax,
                            'grid'       => ['display' => false],
                        ],
                        'y' => [
                            'grid'  => ['display' => false],
                            'ticks' => [
                                'font'  => ['size' => 9],
                                'color' => '#222',
                            ],
                        ],
                    ],
                    'layout' => ['padding' => ['right' => 50, 'top' => 4, 'bottom' => 4, 'left' => 4]],
                ],
            ],
            'width'           => $width,
            'height'          => $height,
            'backgroundColor' => 'white',
            'format'          => 'png',
        ]);

        return qcPost($payload);
    }
}

// ── Prepare chart data ────────────────────────────────────────────────────────

// Top 10 Companies — truncate long names to 28 chars
$compLabels = []; $compValues = [];
foreach (array_slice($companyAgg ?? [], 0, 10) as $c) {
    $name = $c['name'] ?? $c['symbol'] ?? '?';
    $compLabels[] = strlen($name) > 28 ? substr($name, 0, 26).'…' : $name;
    $compValues[] = round((float)($c['percent'] ?? 0), 2);
}

// Top 10 Sectors — truncate to 30 chars
$sectLabels = []; $sectValues = [];
foreach (array_slice($sectorAgg ?? [], 0, 10) as $s) {
    $name = $s['sector'] ?? '?';
    $sectLabels[] = strlen($name) > 30 ? substr($name, 0, 28).'…' : $name;
    $sectValues[] = round((float)($s['percent'] ?? 0), 2);
}

// ── Build charts ──────────────────────────────────────────────────────────────
// Adjust heights based on item count for proper spacing
$compHeight = max(140, count($compLabels) * 22 + 30);
$sectHeight = max(140, count($sectLabels) * 22 + 30);

$compChartPng = !empty($compLabels)
    ? analyticsHBarChart($compLabels, $compValues, '#1E3A5F', 'Top 10 Holdings', 360, $compHeight)
    : null;

$sectChartPng = !empty($sectLabels)
    ? analyticsHBarChart($sectLabels, $sectValues, '#E87722', 'Top 10 Sectors', 360, $sectHeight)
    : null;
?>

<div class="section-header">Portfolio Analytics — Holdings &amp; Sectors <small style="color: #777; font-size: 80%;">(Mutual Funds)</small></div>

<!-- ── Row 1: Holdings chart (left) + Sectors chart (right) ──────────────── -->
<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
    <tr style="vertical-align:top;">

        <!-- Top 10 Company Holdings -->
        <td style="width:48%;border:0.5pt solid #BDC9D5;background:#F7FAFD;
                   padding:7px 8px 6px 8px;">
            <div style="font-size:12pt;font-weight:bold;color:#1E3A5F;
                        margin-bottom:4px;text-align:center;letter-spacing:0.3px;">
                Top 10 Holdings
                <span style="font-size:8.5pt;font-weight:normal;color:#7A8FA6;">
                    (% of MF portfolio)
                </span>
            </div>

            <?php if ($compChartPng): ?>
                <img src="<?= $compChartPng ?>" style="width:100%;display:block;"/>
            <?php elseif (!empty($companyAgg)): ?>
                <!-- HTML table fallback -->
                <table>
                    <thead><tr>
                        <th style="width:55%">Company</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">%</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach (array_slice($companyAgg, 0, 10) as $i => $co): ?>
                        <tr>
                            <td><?= htmlspecialchars($co['name'] ?? $co['symbol'] ?? '') ?></td>
                            <td class="text-right"><?= number_format($co['amount'], 0) ?></td>
                            <td class="text-right" style="color:#1E3A5F;font-weight:bold;"><?= number_format($co['percent'] ?? 0, 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#aaa;font-size:7.5pt;text-align:center;padding:20px;">
                    Holdings data not available
                </p>
            <?php endif; ?>
        </td>

        <!-- Spacer -->
        <td style="width:4%;border:none;background:transparent;padding:0;"></td>

        <!-- Top 10 Sectors -->
        <td style="width:48%;border:0.5pt solid #BDC9D5;background:#F7FAFD;
                   padding:7px 8px 6px 8px;">
            <div style="font-size:12pt;font-weight:bold;color:#1E3A5F;
                        margin-bottom:4px;text-align:center;letter-spacing:0.3px;">
                Top 10 Sector Exposure
                <span style="font-size:8.5pt;font-weight:normal;color:#7A8FA6;">
                    (% of MF portfolio)
                </span>
            </div>

            <?php if ($sectChartPng): ?>
                <img src="<?= $sectChartPng ?>" style="width:100%;display:block;"/>
            <?php elseif (!empty($sectorAgg)): ?>
                <table>
                    <thead><tr>
                        <th style="width:55%">Sector</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">%</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach (array_slice($sectorAgg, 0, 10) as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['sector'] ?? '') ?></td>
                            <td class="text-right"><?= number_format($s['amount'], 0) ?></td>
                            <td class="text-right" style="color:#E87722;font-weight:bold;"><?= number_format($s['percent'] ?? 0, 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#aaa;font-size:7.5pt;text-align:center;padding:20px;">
                    Sector data not available
                </p>
            <?php endif; ?>
        </td>

    </tr>
</table>

<!-- ── Market Cap Allocation History ─────────────────────────────────────── -->
<?php
$mcapAllocPng = null;
$mcapMonths = count($mcapHistory['labels'] ?? []);
if (!empty($mcapHistory) && $mcapMonths >= 2) {
    $mcapAllocPng = $bm->buildMcapAllocationChart($mcapHistory, $headName);
    $mcapSplitTop = $bm->buildMcapSplitChartTop($mcapHistory, $headName);
    $mcapSplitBottom = $bm->buildMcapSplitChartBottom($mcapHistory, $headName);
}
if ($mcapAllocPng): ?>
<div style="border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px 10px 6px 10px;">

    <div style="font-size:8.5pt;font-weight:bold;color:#1E3A5F;
                margin-bottom:16px;text-align:center;letter-spacing:0.3px;">
        Market Cap Allocation History
        <span style="font-size:7pt;font-weight:normal;color:#7A8FA6;">
            (<?= $mcapMonths ?> months &nbsp;&bull;&nbsp;
            bars = Large / Mid / Small Cap % &nbsp;&bull;&nbsp;
            <span style="color:#C0392B;">&#9644;</span> Nifty 500 actual value)
        </span>
    </div>

    <div style="text-align:center;">
        <img src="<?= BenchmarkXirr::pngToDataUri($mcapAllocPng) ?>"
             style="width:100%;max-width:650px;"/>
    </div>

    <p style="font-size:6pt;color:#7A8FA6;margin:3px 0 0;line-height:1.5;text-align:center;">
        <span style="color:rgba(30,58,95,1);font-weight:bold;">&#9632;</span> Large Cap &nbsp;
        <span style="color:rgba(232,119,34,1);font-weight:bold;">&#9632;</span> Mid Cap &nbsp;
        <span style="color:rgba(13,124,95,1);font-weight:bold;">&#9632;</span> Small Cap &nbsp;
        &nbsp;&bull;&nbsp;
        Nifty 500 (right axis, red line). &nbsp;
    </p>
</div>
<?php endif; ?>
