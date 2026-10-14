<?php
/**
 * section_fund_metrics.php — Trailing Returns & Risk Statistics
 *
 * Fund name truncation: keep only the part before the last hyphen-separated
 * plan/option suffix, e.g. "KOTAK AGGRESSIVE HYBRID FUND -REGULAR PLAN-GROWTH"
 * → "KOTAK AGGRESSIVE HYBRID FUND"
 * ISIN shown on the same line in small grey text to keep row height compact.
 */

if (empty($fundMetrics)) return;

/**
 * Truncate fund name: strip common trailing plan/option/growth suffixes.
 * Keeps only the fund's actual name portion.
 */
if (!function_exists('truncateFundName')) {
    function truncateFundName(string $name): string {
        // Remove everything after known trailing suffixes (case-insensitive)
        $patterns = [
            '/\s*[-–]\s*(REGULAR|DIRECT)\s*(PLAN\s*)?([-–]\s*(GROWTH|DIVIDEND|IDCW|REINVEST\w*))?$/i',
            '/\s*[-–]\s*(GROWTH|DIVIDEND|IDCW|REINVESTMENT)\s*$/i',
            '/\s*[-–]\s*PLAN\s*[-–]?\s*(GROWTH|DIVIDEND|IDCW)?\s*$/i',
        ];
        foreach ($patterns as $p) {
            $trimmed = preg_replace($p, '', $name);
            if ($trimmed && strlen($trimmed) < strlen($name)) {
                $name = $trimmed;
            }
        }
        // Final trim and max 40 chars
        $name = trim($name);
        if (strlen($name) > 42) {
            $name = substr($name, 0, 40) . '…';
        }
        return $name;
    }
}

if (!function_exists('fmtRisk')) {
    function fmtRisk(?float $v, int $dp = 2): string {
        return $v !== null ? number_format($v, $dp) : '—';
    }
}
if (!function_exists('retColor')) {
    function retColor(?float $v): string {
        if ($v === null) return '#999';
        return $v >= 0 ? '#0D7C5F' : '#C0392B';
    }
}
if (!function_exists('alphaColor')) {
    function alphaColor(?float $v): string {
        if ($v === null) return '#999';
        return $v > 0 ? '#0D7C5F' : '#C0392B';
    }
}
if (!function_exists('betaColor')) {
    function betaColor(?float $v): string {
        if ($v === null) return '#999';
        if ($v > 1.1)  return '#E87722';
        if ($v < 0.9)  return '#0D7C5F';
        return '#444';
    }
}
?>

<div class="section-header">Mutual Fund — Trailing Returns &amp; Risk Statistics</div>

<table style="font-size:7pt;">
    <thead>
        <tr>
            <th rowspan="2" style="width:24%;vertical-align:bottom;font-size:7pt;">
                Fund Name / ISIN
            </th>
            <th colspan="3" style="text-align:center;background:#2C5282;
                                    border-bottom:0.3pt solid #3D6A9C;font-size:7pt;">
                Trailing Returns (%)
            </th>
            <th colspan="3" style="text-align:center;background:#0D5C3A;
                                    border-bottom:0.3pt solid #1A7A4E;font-size:7pt;">
                3Y Risk Stats
            </th>
            <th colspan="3" style="text-align:center;background:#0D5C3A;
                                    border-bottom:0.3pt solid #1A7A4E;font-size:7pt;">
                5Y Risk Stats
            </th>
        </tr>
        <tr>
            <th class="text-right" style="background:#2C5282;font-size:6.5pt;width:7%;">1Y</th>
            <th class="text-right" style="background:#2C5282;font-size:6.5pt;width:7%;">3Y</th>
            <th class="text-right" style="background:#2C5282;font-size:6.5pt;width:7%;">5Y</th>
            <th class="text-right" style="background:#0D5C3A;font-size:6.5pt;width:8%;">Alpha</th>
            <th class="text-right" style="background:#0D5C3A;font-size:6.5pt;width:7%;">Beta</th>
            <th class="text-right" style="background:#0D5C3A;font-size:6.5pt;width:8%;">StdDev</th>
            <th class="text-right" style="background:#0D5C3A;font-size:6.5pt;width:8%;">Alpha</th>
            <th class="text-right" style="background:#0D5C3A;font-size:6.5pt;width:7%;">Beta</th>
            <th class="text-right" style="background:#0D5C3A;font-size:6.5pt;width:8%;">StdDev</th>
        </tr>
    </thead>
    <tbody>
    <?php $rowIdx = 0; foreach ($fundMetrics as $isin => $fm): $rowIdx++;
        $bg  = ($rowIdx % 2 === 0) ? '#EEF3F8' : '#FFFFFF';
        $r1y = $fm['returns']['1y'] ?? null;
        $r3y = $fm['returns']['3y'] ?? null;
        $r5y = $fm['returns']['5y'] ?? null;
        $a3y = $fm['risk']['3y']['alpha']  ?? null;
        $b3y = $fm['risk']['3y']['beta']   ?? null;
        $s3y = $fm['risk']['3y']['stddev'] ?? null;
        $a5y = $fm['risk']['5y']['alpha']  ?? null;
        $b5y = $fm['risk']['5y']['beta']   ?? null;
        $s5y = $fm['risk']['5y']['stddev'] ?? null;
        $displayName = truncateFundName($fm['scheme_name'] ?? '');
    ?>
        <tr style="background:<?= $bg ?>;">
            <!-- Fund name + ISIN on one line -->
            <td style="font-size:7pt;line-height:1.3;white-space:nowrap;overflow:hidden;">
                <strong><?= htmlspecialchars($displayName) ?></strong>
                <span style="color:#7A8FA6;font-size:6pt;margin-left:4px;"><?= htmlspecialchars($isin) ?></span>
            </td>
            <!-- Returns -->
            <td class="text-right" style="font-weight:bold;color:<?= retColor($r1y) ?>;font-size:7.5pt;"><?= fmtRisk($r1y) ?></td>
            <td class="text-right" style="font-weight:bold;color:<?= retColor($r3y) ?>;font-size:7.5pt;"><?= fmtRisk($r3y) ?></td>
            <td class="text-right" style="font-weight:bold;color:<?= retColor($r5y) ?>;font-size:7.5pt;"><?= fmtRisk($r5y) ?></td>
            <!-- 3Y Risk -->
            <td class="text-right" style="font-weight:bold;color:<?= alphaColor($a3y) ?>;font-size:7.5pt;"><?= fmtRisk($a3y) ?></td>
            <td class="text-right" style="font-weight:bold;color:<?= betaColor($b3y) ?>;font-size:7.5pt;"><?= fmtRisk($b3y) ?></td>
            <td class="text-right" style="font-size:7.5pt;"><?= fmtRisk($s3y) ?></td>
            <!-- 5Y Risk -->
            <td class="text-right" style="font-weight:bold;color:<?= alphaColor($a5y) ?>;font-size:7.5pt;"><?= fmtRisk($a5y) ?></td>
            <td class="text-right" style="font-weight:bold;color:<?= betaColor($b5y) ?>;font-size:7.5pt;"><?= fmtRisk($b5y) ?></td>
            <td class="text-right" style="font-size:7.5pt;"><?= fmtRisk($s5y) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p style="font-size:6pt;color:#7A8FA6;margin-top:4px;line-height:1.6;">
    <strong>Trailing Returns:</strong> Annualised CAGR. &nbsp;|&nbsp;
    <strong>Alpha</strong> <span style="color:#0D7C5F;">▲&gt;0 outperforms</span> /
    <span style="color:#C0392B;">▼&lt;0 underperforms</span>. &nbsp;|&nbsp;
    <strong>Beta</strong> <span style="color:#E87722;">&gt;1.1 more volatile</span>,
    <span style="color:#0D7C5F;">&lt;0.9 defensive</span>. &nbsp;|&nbsp;
    <strong>Std Dev:</strong> Lower = more stable. &nbsp;|&nbsp;
    Data sourced from fund_yahoo_data table.
</p>
