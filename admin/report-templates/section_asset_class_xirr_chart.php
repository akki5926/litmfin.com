<?php
/**
 * section_asset_class_xirr_chart.php
 *
 * Chart for Asset Class XIRR (%) since inception.
 */

$assetXirrData = array_filter([
    ['label' => 'Equity', 'xirr' => (float)str_replace(',', '', $totalAssetInfo[0]['Total_Equity_Return'] ?? 0), 'color' => '#1E3A5F'],
    ['label' => 'Debt',   'xirr' => (float)str_replace(',', '', $totalAssetInfo[0]['Total_Debt_Return']   ?? 0), 'color' => '#E87722'],
    ['label' => 'Hybrid', 'xirr' => (float)str_replace(',', '', $totalAssetInfo[0]['Total_Hybrid_Return'] ?? 0), 'color' => '#0D7C5F'],
    ['label' => 'Gold',   'xirr' => (float)str_replace(',', '', $totalAssetInfo[0]['Total_Gold_Return']   ?? 0), 'color' => '#D4AC0D'],
], fn($r) => isset($totalAssetInfo[0]['Total_' . $r['label'] . '_Curr_Value']) && (float)str_replace(',', '', $totalAssetInfo[0]['Total_' . $r['label'] . '_Curr_Value']) > 0);

if (empty($assetXirrData)) return;

$labels = array_column($assetXirrData, 'label');
$xirrs = array_column($assetXirrData, 'xirr');
$colors = array_column($assetXirrData, 'color');

$chartMax = round(max(array_map('abs', $xirrs)) * 1.3 + 2, 1);

$assetXirrPng = qcPost(json_encode([
    'version' => '3',
    'chart' => [
        'type' => 'bar',
        'data' => [
            'labels'   => $labels,
            'datasets' => [['data' => $xirrs, 'backgroundColor' => $colors]],
        ],
        'options' => [
            'plugins' => [
                'title'  => ['display'=>false],
                'legend' => ['display'=>false],
                'datalabels' => [
                    'display'   => true,
                    'anchor'    => 'end',
                    'align'     => 'top',
                    'color'     => '#222',
                    'font'      => ['size'=>10,'weight'=>'bold'],
                    'formatter' => 'function(v){return v.toFixed(2)+"%"}',
                ],
            ],
            'scales' => [
                'x' => ['grid'  => ['display'=>false], 'ticks' => ['font' => ['size' => 11, 'weight' => 'bold']]],
                'y' => ['display' => false, 'suggestMax' => $chartMax, 'grid' => ['display'=>false]],
            ],
            'layout' => ['padding'=>['top'=>25,'right'=>5,'bottom'=>5,'left'=>5]],
        ],
    ],
    'width'=>560, 'height'=>240, 'backgroundColor'=>'white', 'format'=>'png',
]));

?>

<?php if (!empty($assetXirrPng)): ?>
<pagebreak/>
<div class="section-header">Asset Class Performance</div>
<div style="border:0.5pt solid #BDC9D5;background:#F7FAFD;padding:8px 10px 6px 10px;margin-top:8px;margin-bottom:8px;">
    <div style="font-size:8.5pt;font-weight:bold;color:#1E3A5F;margin-bottom:5px;text-align:center;letter-spacing:0.3px;">
        Asset Class XIRR (%) — Since Inception
    </div>
    <div style="text-align:center;">
        <img src="<?= BenchmarkXirr::pngToDataUri($assetXirrPng) ?>" style="width:100%;max-width:560px;"/>
    </div>
</div>
<?php endif; ?>
