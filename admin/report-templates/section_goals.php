<?php
/**
 * section_goals.php — Financial Goals Status
 *
 * For each goal:
 *   1. Current accumulated value vs target + accurate mPDF progress bar.
 *   2. Projection: corpus at 12% XIRR + remaining SIPs → projected value at goal date.
 *        FV = corpus × (1.12)^years  +  SIP × [((1+r)^n − 1)/r] × (1+r)
 *        r = monthly rate = (1.12)^(1/12) − 1,  n = months remaining
 *   3. Projected shortfall/surplus vs target.
 *   4. Progress bar uses linear-gradient in single TD (mPDF-safe).
 *
 * $goalsFundInfo keyed by goal name → array of fund rows:
 *   fund_name, investment_type, asset_class, current_value, profit_loss, xirr,
 *   goal_amount, goal_date, SIP_Info[]
 */

if (!function_exists('n')) { function n($v): float { return (float)str_replace(',', '', $v ?? 0); } }
if (empty($goalsFundInfo)) return;

// ── Projection at 12% XIRR ────────────────────────────────────────────────────
if (!function_exists('projectGoalValue')) {
    function projectGoalValue(float $corpus, float $monthlySip, int $n, float $rate = 0.12): float
    {
        if ($n <= 0) return $corpus;
        $r        = pow(1.0 + $rate, 1.0 / 12.0) - 1.0;
        $years    = $n / 12.0;
        $fvCorpus = $corpus * pow(1.0 + $rate, $years);
        $fvSip    = ($r > 0 && $monthlySip > 0)
            ? $monthlySip * ((pow(1.0 + $r, $n) - 1.0) / $r) * (1.0 + $r)
            : $monthlySip * $n;
        return $fvCorpus + $fvSip;
    }
}

// ── Calculate ADDITIONAL monthly SIP needed to reach target at given XIRR ──────
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

// ── mPDF-safe progress bar using table with pixel widths ───────────────────
if (!function_exists('goalProgressBar')) {
    function goalProgressBar(float $pct, string $color): string
    {
        $p = max(0.0, min(100.0, $pct));
        $filledWidth = round(2.0 * $p, 1); // 200px total width
        $emptyWidth = 200 - $filledWidth;
        $pctText = number_format($p, 1) . '%';

        $bar = '<table style="width:200px;border-collapse:collapse;"><tr>';
        if ($filledWidth > 0) {
            $bar .= '<td style="width:'.$filledWidth.'px;height:10px;background-color:'.$color.';padding:0;border:0;"></td>';
        }
        if ($emptyWidth > 0) {
            $bar .= '<td style="width:'.$emptyWidth.'px;height:10px;background-color:#e0e8f4;padding:0;border:0;"></td>';
        }
        $bar .= '</tr></table>';

        return '<table style="border-collapse:collapse;"><tr>'
             . '<td style="padding:0;vertical-align:middle;">'.$bar.'</td>'
             . '<td style="padding:0 0 0 6px;vertical-align:middle;font-size:7pt;font-weight:bold;color:'.$color.';">'.$pctText.'</td>'
             . '</tr></table>';
    }
}

$today = new DateTime();

// ── Calculate totals across all goals ──────────────────────────────────────
$totalGoalCount = 0;
$totalGoalTarget = 0.0;
$totalCollected = 0.0;
$totalExistingSip = 0.0;
$totalAdditionalSip = 0.0;
$totalShortfall = 0.0;
$goalSummaries = [];

foreach ($goalsFundInfo as $goalName => $fundList) {
    $totalGoalCount++;
    $goalTotal = 0.0; $goalTarget = 0.0;
    $goalDate = ''; $totalSip = 0.0;

    foreach ($fundList as $f) {
        $goalTotal += (float)($f['current_value'] ?? 0);
        if (!$goalTarget && !empty($f['goal_amount'])) $goalTarget = (float)$f['goal_amount'];
        if (!$goalDate && !empty($f['goal_date'])) $goalDate = $f['goal_date'];
        foreach ($f['SIP_Info'] ?? [] as $s) $totalSip += (float)($s['Amount'] ?? 0);
    }

    $shortfallNow = max(0.0, $goalTarget - $goalTotal);
    $pct = $goalTarget > 0 ? round($goalTotal / $goalTarget * 100, 1) : 0;
    $color = $pct >= 100 ? '#1a7a4a' : ($pct >= 60 ? '#2196f3' : ($pct >= 30 ? '#e67e22' : '#e74c3c'));

    $monthsLeft = 0;
    if ($goalDate) {
        try {
            $gdt = new DateTime($goalDate);
            $monthsLeft = max(0, (int)(($gdt->getTimestamp() - $today->getTimestamp()) / (86400 * 30.4375)));
        } catch (\Exception $e) {}
    }

    $projVal = ($goalTarget > 0 && $monthsLeft > 0) ? projectGoalValue($goalTotal, $totalSip, $monthsLeft, 0.12) : 0.0;
    $projPct = $goalTarget > 0 ? round($projVal / $goalTarget * 100, 1) : $pct;
    $additionalSip = ($goalTarget > 0 && $monthsLeft > 0) ? calculateAdditionalMonthlySipForTarget($goalTarget, $goalTotal, $totalSip, $monthsLeft, 0.12) : 0.0;

    $goalSummaries[] = [
        'name' => $goalName,
        'target' => $goalTarget,
        'collected' => $goalTotal,
        'sip' => $totalSip,
        'shortfall' => $shortfallNow,
        'extraSip' => $additionalSip,
        'color' => $color,
        'pct' => $pct,
        'projectedPct' => $projPct,
    ];

    $totalGoalTarget += $goalTarget;
    $totalCollected += $goalTotal;
    $totalExistingSip += $totalSip;
    $totalAdditionalSip += $additionalSip;
    $totalShortfall += $shortfallNow;
}

usort($goalSummaries, static function ($a, $b) {
    return $b['shortfall'] <=> $a['shortfall'];
});

$overallPct = $totalGoalTarget > 0 ? round($totalCollected / $totalGoalTarget * 100, 1) : 0;
?>

<div class="section-header">Financial Goals — Status</div>

<!-- ── Goals Summary Tables ──────────────────────────────────────────────────── -->
<table style="width:100%;border-collapse:collapse;font-size:8pt;margin-bottom:12px;">
    <tr>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;background:#f7fbff;width:20%;font-weight:bold;text-align:center;">Total Goals</td>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;background:#f7fbff;width:20%;font-weight:bold;text-align:right;">Target Amount</td>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;background:#f7fbff;width:20%;font-weight:bold;text-align:right;">Accumulated</td>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;background:#f7fbff;width:20%;font-weight:bold;text-align:right;">Shortfall</td>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;background:#f7fbff;width:20%;font-weight:bold;text-align:right;">Add'l SIP Reqd/mo</td>
    </tr>
    <tr>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;text-align:center;"><?= $totalGoalCount ?></td>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;text-align:right;font-weight:bold;"><?= number_format($totalGoalTarget, 0) ?></td>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;text-align:right;font-weight:bold;color:#1a7a4a;"><?= number_format($totalCollected, 0) ?> <span style="font-weight:normal;color:#666;font-size:7pt;">(<?= $overallPct ?>%)</span></td>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;text-align:right;font-weight:bold;color:#c0392b;"><?= number_format($totalShortfall, 0) ?></td>
        <td style="padding:8px;border:0.5pt solid #d7e0ed;text-align:right;font-weight:bold;color:#e67e22;"><?= number_format($totalAdditionalSip, 0) ?></td>
    </tr>
</table>

<!-- ── Individual Goal Summary ──────────────────────────────────────────────────── -->
<table style="width:100%;border-collapse:collapse;font-size:8pt;margin-bottom:12px;">
    <thead>
        <tr style="background:#f1f7ff;border-bottom:0.5pt solid #d7e0ed;">
            <th style="width:28%;padding:6px;text-align:left;">Goal Name</th>
            <th style="width:32%;padding:6px;text-align:left;">Progress</th>
            <th style="width:20%;padding:6px;text-align:right;">Shortfall</th>
            <th style="width:20%;padding:6px;text-align:right;">Add'l SIP Reqd/mo</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($goalSummaries as $goal): ?>
            <tr style="border-top:0.3pt solid #e1e8f2;">
                <td style="padding:6px;vertical-align:middle;">
                    <strong><?= htmlspecialchars($goal['name']) ?></strong><br>
                    <span style="color:#666;font-size:7pt;"><?= number_format($goal['collected'], 0) ?> / <?= number_format($goal['target'], 0) ?></span>
                </td>
                <td style="padding:6px;vertical-align:middle;font-size:7.5pt;">
                    <?php
                        $p = max(0.0, min(100.0, $goal['pct']));
                        $filledW = round(1.2 * $p, 1);
                        $emptyW = 120 - $filledW;
                        $bar = '<table style="width:120px;border-collapse:collapse;height:10px;"><tr>';
                        if($filledW > 0) $bar .= '<td style="width:'.$filledW.'px;background:'.$goal['color'].';padding:0;border:0;"></td>';
                        if($emptyW > 0) $bar .= '<td style="width:'.$emptyW.'px;background:#e8eff7;padding:0;border:0;"></td>';
                        $bar .= '</tr></table>';
                        echo '<table style="border-collapse:collapse;"><tr><td style="padding:0;vertical-align:middle;">'.$bar.'</td><td style="padding:0 0 0 6px;font-weight:bold;font-size:7pt;vertical-align:middle;color:'.$goal['color'].';">'.number_format($p,1).'%</td></tr></table>';
                    ?>
                </td>
                <td style="padding:6px;text-align:right;vertical-align:middle;color:#c0392b;font-weight:bold;"><?= $goal['shortfall'] > 0 ? number_format($goal['shortfall'], 0) : '—' ?></td>
                <td style="padding:6px;text-align:right;vertical-align:middle;font-weight:bold;color:#e67e22;"><?= $goal['extraSip'] > 0 ? number_format($goal['extraSip'], 0) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div style="font-size:7.5pt;color:#555;line-height:1.4;margin-bottom:16px;padding:6px;background:#f9fafb;border-left:2px solid #2196f3;">
    Forecast assumes 12% p.a. growth on SIPs and current corpus. Add'l SIP Reqd/mo is the estimated monthly top-up required to meet the goal by its target date.
</div>

<!-- ── Individual Goal Details ──────────────────────────────────────────────────── -->
<?php
foreach ($goalsFundInfo as $goalName => $fundList):
    $goalTotal  = 0.0; $goalTarget = 0.0;
    $goalDate   = '';  $totalSip   = 0.0;

    foreach ($fundList as $f) {
        $goalTotal += (float)($f['current_value'] ?? 0);
        if (!$goalTarget && !empty($f['goal_amount'])) $goalTarget = (float)$f['goal_amount'];
        if (!$goalDate  && !empty($f['goal_date']))    $goalDate   = $f['goal_date'];
        foreach ($f['SIP_Info'] ?? [] as $s) $totalSip += (float)($s['Amount'] ?? 0);
    }

    // Months remaining
    $monthsLeft = 0;
    if ($goalDate) {
        try {
            $gdt = new DateTime($goalDate);
            $monthsLeft = max(0, (int)(($gdt->getTimestamp() - $today->getTimestamp()) / (86400 * 30.4375)));
        } catch (\Exception $e) {}
    }

    // Current status
    $achievedPct  = ($goalTarget > 0) ? min(round($goalTotal / $goalTarget * 100, 1), 100) : 0;
    $shortfallNow = max(0.0, $goalTarget - $goalTotal);
    $barColor     = $achievedPct >= 100 ? '#1a7a4a' : ($achievedPct >= 60 ? '#2196f3' : ($achievedPct >= 30 ? '#e67e22' : '#e74c3c'));

    // Projection
    $hasProj  = ($goalTarget > 0 && $monthsLeft > 0);
    $projVal  = $hasProj ? projectGoalValue($goalTotal, $totalSip, $monthsLeft, 0.12) : 0.0;
    $projShort = max(0.0, $goalTarget - $projVal);
    $projSurp  = max(0.0, $projVal - $goalTarget);
    $additionalMonthlySipNeeded = $hasProj ? calculateAdditionalMonthlySipForTarget($goalTarget, $goalTotal, $totalSip, $monthsLeft, 0.12) : 0.0;
?>

<div style="margin-bottom:16px; border:0.5pt solid #b0c4de; page-break-inside: avoid;">
    <table style="width:100%; border-collapse:collapse; font-size:8pt;">
        <tr style="background:#e8f0fa;">
            <td style="padding:6px 8px; width:60%;">
                <strong style="font-size:9.5pt; color:#1D3557;"><?= htmlspecialchars($goalName) ?></strong>
                <?php if ($goalDate): ?>
                    <span style="color:#555; font-size:7.5pt; margin-left:8px;">
                        &bull; By <strong><?= htmlspecialchars($goalDate) ?></strong>
                        <?php if ($monthsLeft > 0): ?> (<?= $monthsLeft ?> mos left)<?php endif; ?>
                    </span>
                <?php endif; ?>
            </td>
            <td style="padding:6px 8px; text-align:right; width:40%;">
                <span style="color:#555; font-size:7.5pt;">Target:</span>
                <strong style="font-size:9.5pt; color:#1D3557;">₹<?= number_format($goalTarget, 0) ?></strong>
            </td>
        </tr>
        <tr style="background:#fdfdfe; border-bottom:0.5pt solid #d7e0ed;">
            <td style="padding:6px 8px; vertical-align:middle;">
                <?= goalProgressBar((float)$achievedPct, $barColor) ?>
                <div style="font-size:7pt; color:#666; margin-top:3px;">
                    Accumulated: <strong style="color:<?= $barColor ?>; font-size:8pt;">₹<?= number_format($goalTotal, 0) ?></strong>
                    <?php if ($shortfallNow > 0): ?>
                        | Gap: <strong style="color:#c0392b;">₹<?= number_format($shortfallNow, 0) ?></strong>
                    <?php else: ?>
                        | <strong style="color:#1a7a4a;">&#x2713; Reached</strong>
                    <?php endif; ?>
                </div>
            </td>
            <td style="padding:6px 8px; text-align:right; vertical-align:middle; font-size:7.5pt;">
                Current SIP: <strong>₹<?= $totalSip > 0 ? number_format($totalSip, 0) : '0' ?>/mo</strong><br>
                Add'l SIP Reqd: <strong style="color:#e67e22;">₹<?= $additionalMonthlySipNeeded > 0 ? number_format($additionalMonthlySipNeeded, 0) : '0' ?>/mo</strong>
            </td>
        </tr>
    </table>
    
    <table style="width:100%; border-collapse:collapse; font-size:7.5pt;">
        <thead>
            <tr style="background:#f7f9fc; border-bottom:0.5pt solid #e1e8f2;">
                <th style="padding:5px; text-align:left;">Fund Name</th>
                <th style="padding:5px; text-align:center;">Asset</th>
                <th style="padding:5px; text-align:right;">Curr Value</th>
                <th style="padding:5px; text-align:right;">P&amp;L</th>
                <th style="padding:5px; text-align:right;">XIRR %</th>
                <th style="padding:5px; text-align:right;">SIP/mo</th>
                <th style="padding:5px; text-align:right;">Alloc%</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fundList as $f):
            $xirr    = $f['xirr'] ?? '';
            $xirrNum = (float)str_replace(',', '', $xirr);
            $pl      = (float)($f['profit_loss'] ?? 0);
            $cv      = (float)($f['current_value'] ?? 0);
            $allocPct = $goalTotal > 0 ? round($cv / $goalTotal * 100, 1) : 0;
            $sipAmt  = 0;
            foreach ($f['SIP_Info'] ?? [] as $s) {
                $sipAmt += (float)($s['Amount'] ?? 0);
            }
        ?>
            <tr style="border-bottom:0.3pt solid #f0f2f9;">
                <td style="padding:5px;"><?= htmlspecialchars(cleanFundName($f['fund_name'] ?? '')) ?></td>
                <td style="padding:5px; text-align:center;"><?= strtoupper($f['asset_class'] ?? '') ?></td>
                <td style="padding:5px; text-align:right; font-weight:bold;"><?= number_format($cv, 0) ?></td>
                <td style="padding:5px; text-align:right;" class="<?= $pl >= 0 ? 'positive' : 'negative' ?>">
                    <?= ($pl >= 0 ? '+' : '') . number_format($pl, 0) ?>
                </td>
                <td style="padding:5px; text-align:right;" class="<?= $xirrNum >= 0 ? 'positive' : 'negative' ?>">
                    <?= is_numeric(str_replace(',', '', $xirr)) ? number_format($xirrNum, 2).'%' : '—' ?>
                </td>
                <td style="padding:5px; text-align:right;">
                    <?= $sipAmt > 0 ? number_format($sipAmt, 0) : '<span style="color:#aaa;">—</span>' ?>
                </td>
                <td style="padding:5px; text-align:right; color:#666;"><?= $allocPct ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<p style="font-size:6.5pt;color:#888;margin-top:6px;line-height:1.5;">
    * Projection assumes current SIP continues monthly for the remaining duration and earns 12% XIRR p.a.
    Formula: Projected = Corpus &times; (1.12)<sup>years</sup>
    + SIP &times; [((1+r)<sup>n</sup>&minus;1) / r] &times; (1+r),
    where r = monthly equivalent of 12% p.a., n = months remaining.
    Illustrative only — actual returns may differ.
</p>
