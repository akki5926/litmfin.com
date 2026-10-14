<?php
if (empty($goalsFundInfo)) return;

if (!function_exists('projectGoalValue')) {
    function projectGoalValue(float $corpus, float $monthlySip, int $n, float $rate = 0.12): float
    {
        if ($n <= 0) return $corpus;
        $r = pow(1.0 + $rate, 1.0 / 12.0) - 1.0;
        $years = $n / 12.0;
        $fvCorpus = $corpus * pow(1.0 + $rate, $years);
        $fvSip = ($r > 0 && $monthlySip > 0)
            ? $monthlySip * ((pow(1.0 + $r, $n) - 1.0) / $r) * (1.0 + $r)
            : $monthlySip * $n;
        return $fvCorpus + $fvSip;
    }
}

if (!function_exists('calculateAdditionalMonthlySipForTarget')) {
    function calculateAdditionalMonthlySipForTarget(float $target, float $currentCorpus, float $existingSip, int $months, float $rate = 0.12): float
    {
        if ($months <= 0) return 0.0;

        $projectedWithExisting = projectGoalValue($currentCorpus, $existingSip, $months, $rate);
        if ($projectedWithExisting >= $target) return 0.0;

        $additionalShortfall = $target - $projectedWithExisting;
        $r = pow(1.0 + $rate, 1.0 / 12.0) - 1.0;
        if ($r <= 0) return $additionalShortfall / $months;

        $annuityFactor = ((pow(1.0 + $r, $months) - 1.0) / $r) * (1.0 + $r);
        return $annuityFactor > 0 ? $additionalShortfall / $annuityFactor : 0.0;
    }
}

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

if (!function_exists('goalProjectionChart')) {
    function goalProjectionChart(array $goalSummaries): ?string
    {
        if (empty($goalSummaries)) return null;

        $labels = [];
        $currents = [];
        $projects = [];
        $targets = [];

        foreach ($goalSummaries as $goal) {
            $label = $goal['name'] ?? 'Goal';
            $labels[] = strlen($label) > 24 ? substr($label, 0, 22) . '…' : $label;
            $currents[] = (float)($goal['pct'] ?? 0);
            $projects[] = (float)($goal['projectedPct'] ?? 0);
            $targets[] = 100.0;
        }

        $suggestMax = 110;

        $payload = json_encode([
            'version' => '3',
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Current',
                            'data' => $currents,
                            'backgroundColor' => '#1D4F91',
                            'borderRadius' => 2,
                            'borderSkipped' => false,
                            'barThickness' => 16,
                        ],
                        [
                            'label' => 'Projected',
                            'data' => array_map(fn($c, $p) => max(0, $p - $c), $currents, $projects),
                            'backgroundColor' => '#8BB9F0',
                            'borderRadius' => 2,
                            'borderSkipped' => false,
                            'barThickness' => 16,
                        ],
                    ],
                ],
                'options' => [
                    'indexAxis' => 'y',
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'bottom',
                            'labels' => ['font' => ['size' => 8], 'color' => '#222'],
                        ],
                        'tooltip' => ['enabled' => false],
                        'datalabels' => [
                            'display' => true,
                            'anchor' => 'end',
                            'align' => 'right',
                            'color' => '#222',
                            'font' => ['size' => 7, 'weight' => 'bold'],
                            'formatter' => "function(v, ctx){ if(ctx.datasetIndex === 0) { return v.toFixed(1)+'%'; } return ''; }",
                            'offset' => 4,
                        ],
                    ],
                    'scales' => [
                        'x' => [
                            'stacked' => true,
                            'grid' => ['display' => false],
                            'ticks' => ['display' => false],
                            'suggestedMax' => $suggestMax,
                        ],
                        'y' => [
                            'stacked' => true,
                            'grid' => ['display' => false],
                            'ticks' => ['font' => ['size' => 8], 'color'=> '#222'],
                        ],
                    ],
                    'layout' => ['padding' => ['top' => 8, 'bottom' => 8, 'left' => 10, 'right' => 40]],
                ],
            ],
            'width' => 560,
            'height' => count($labels) * 40 + 60,
            'backgroundColor' => 'white',
            'format' => 'png',
        ]);

        return qcPost($payload);
    }
}

$today = new DateTime();
$goalSummaries = [];

foreach ($goalsFundInfo as $goalName => $fundList) {
    $goalTarget = 0.0;
    $goalCollected = 0.0;
    $goalSip = 0.0;
    $goalDate = '';

    foreach ($fundList as $f) {
        $goalCollected += (float)($f['current_value'] ?? 0);
        if (!$goalTarget && !empty($f['goal_amount'])) {
            $goalTarget = (float)$f['goal_amount'];
        }
        if (!$goalDate && !empty($f['goal_date'])) {
            $goalDate = $f['goal_date'];
        }
        foreach ($f['SIP_Info'] ?? [] as $s) {
            $goalSip += (float)($s['Amount'] ?? 0);
        }
    }

    $monthsLeft = 0;
    if ($goalDate) {
        try {
            $gdt = new DateTime($goalDate);
            $monthsLeft = max(0, (int)(($gdt->getTimestamp() - $today->getTimestamp()) / (86400 * 30.4375)));
        } catch (Exception $e) {}
    }

    $goalShortfall = max(0.0, $goalTarget - $goalCollected);
    $goalPct = $goalTarget > 0 ? round($goalCollected / $goalTarget * 100, 1) : 0;
    $goalProjectedAmt = $monthsLeft > 0 ? projectGoalValue($goalCollected, $goalSip, $monthsLeft, 0.12) : $goalCollected;
    $goalProjectedPct = $goalTarget > 0 ? round($goalProjectedAmt / $goalTarget * 100, 1) : $goalPct;
    $goalAdditionalSip = $monthsLeft > 0 ? calculateAdditionalMonthlySipForTarget($goalTarget, $goalCollected, $goalSip, $monthsLeft, 0.12) : 0.0;

    $goalSummaries[] = [
        'name' => $goalName,
        'target' => $goalTarget,
        'collected' => $goalCollected,
        'sip' => $goalSip,
        'months' => $monthsLeft,
        'shortfall' => $goalShortfall,
        'pct' => $goalPct,
        'projectedPct' => $goalProjectedPct,
        'extraSip' => $goalAdditionalSip,
    ];
}

usort($goalSummaries, static function ($a, $b) {
    return ($a['target'] - $a['collected']) <=> ($b['target'] - $b['collected']);
});

$goalChart = goalProjectionChart($goalSummaries);
?>

<div class="section-header">Goals Summary — Completion Chart</div>

<?php if ($goalChart): ?>
    <div style="border:0.5pt solid #d7e0ed;background:#f8fbff;padding:8px 10px;">
        <div style="font-size:8pt;font-weight:bold;color:#1D3557;margin-bottom:6px;text-align:center;">Goal Completion — Current vs Projected at 12% p.a.</div>
        <div style="text-align:center;">
            <img src="<?= $goalChart ?>" style="width:100%;max-width:560px;" />
        </div>
        <div style="font-size:7.5pt;color:#555;line-height:1.4;margin-top:8px;text-align:center;">
            <span style="display:inline-block;width:10px;height:10px;background:#1D4F91;border-radius:2px;margin-right:3px;"></span> Current Progress
            &nbsp;&nbsp;&nbsp;
            <span style="display:inline-block;width:10px;height:10px;background:#8BB9F0;border-radius:2px;margin-right:3px;"></span> Projected Growth
            <br/>
            Forecast assumes 12% p.a. growth on SIPs and current corpus. Detailed breakdowns are in the next section.
        </div>
    </div>
<?php endif; ?>