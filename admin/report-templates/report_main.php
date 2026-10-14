<?php
/**
 * report_main.php — Master PDF template
 *
 * Variables available from generatereport.php scope:
 *   $headName, $userpan, $address
 *   $totalAssetInfo, $portfolioInfo, $totalHoldingsMF
 *   $HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo
 *   $HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo
 *   $HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo
 *   $ZeroHoldingFundInfo, $goalsFundInfo
 *   $bm, $sinceInception, $rolling3yr, $rolling5yr
 */

$tmplDir = __DIR__ . '/';

if (!function_exists('cleanFundName')) {
    function cleanFundName(string $name): string {
        // Trim any string after the first occurrence of "Growth"
        $name = preg_replace('/(\bGrowth\b).*/i', '$1', $name);
        
        $name = preg_replace('/\b(Regular Plan|Regular|Reg\.?)\b/i', '', $name);
        $name = preg_replace('/[-–]\s*[-–]/', '-', $name);
        $name = preg_replace('/\s*-\s*/', ' - ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name, " -–");
    }
}
?>

<!-- ── Cover page ───────────────────────────────────────────────────────── -->
<?php include $tmplDir . 'section_cover.php'; ?>

<?php if (($holdingDaysAll ?? 0) >= 1095 || ($holdingDaysHeld ?? 0) >= 1095): ?>
	<pagebreak/>
<!-- ── Benchmark charts ─────────────────────────────────────────────────── -->
<?php include $tmplDir . 'section_charts.php'; ?>
<?php endif; ?>

<!-- ── Top holdings / sectors ──────────────────────────────────────────── -->
<?php if (!empty($companyAgg) || !empty($sectorAgg) || !empty($mcapHistory)): ?>
    <pagebreak/>
    <?php include $tmplDir . 'section_analytics.php'; ?>
<?php endif; ?>

<pagebreak/>
<!-- ── Asset allocation summary ───────────────────────────────────────── -->
<?php include $tmplDir . 'section_summary.php'; ?>

<!-- ── Mutual fund holdings (equity, debt, hybrid, gold) ────────────────── -->
<?php include $tmplDir . 'section_mf.php'; ?>

<!-- ── Bond / SGB holdings ──────────────────────────────────────────────── -->
<?php if (!empty($HoldingInfoBonds)): ?>
   
    <?php include $tmplDir . 'section_bonds.php'; ?>
<?php endif; ?>

<!-- ── PMS holdings ─────────────────────────────────────────────────────── -->
<?php if (!empty($HoldingInfoPms)): ?>
     
    <?php include $tmplDir . 'section_pms.php'; ?>
<?php endif; ?>

<!-- ── AIF holdings ─────────────────────────────────────────────────────── -->
<?php if (!empty($HoldingInfoAif)): ?>
    
    <?php include $tmplDir . 'section_aif.php'; ?>
<?php endif; ?>

<!-- ── SIF holdings ─────────────────────────────────────────────────────── -->
<?php if (!empty($HoldingInfoSif)): ?>
    <?php include $tmplDir . 'section_sif.php'; ?>
<?php endif; ?>

<!-- ── Exits (fully redeemed MF + sold bonds) ───────────────────────────── -->
<?php if (!empty($ZeroHoldingFundInfo) || !empty($ZeroHoldingBondInfo)): ?>
    <?php include $tmplDir . 'section_exits.php'; ?>
<?php endif; ?>

<!-- ── SIF Exits ────────────────────────────────────────────────────────── -->
<?php if (!empty($SoldSifInfo)): ?>
    <?php include $tmplDir . 'section_sif_exits.php'; ?>
<?php endif; ?>


<!-- ── Active SIPs ──────────────────────────────────────────────────────── -->
<?php if (!empty($runningSIPInfo)): ?>
	<pagebreak/>
    <?php include $tmplDir . 'section_sips.php'; ?>
<?php endif; ?>

<!-- ── Financial goals ──────────────────────────────────────────────────── -->
<?php if (!empty($goalsFundInfo)): ?>
    <?php //include $tmplDir . 'section_goals_summary.php'; ?>
    <pagebreak/>
    <?php include $tmplDir . 'section_goals.php'; ?>
<?php endif; ?>
