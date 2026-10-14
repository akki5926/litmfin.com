<?php
require_once('auth_guard.php');

/**
 * xirr_helpers.php
 *
 * Shared XIRR debug export functions.
 * Used by xirr_debug_export.php and generatereport.php.
 *
 * ── HOW THE NIFTY MIRROR IS BUILT ──────────────────────────────────────────
 *
 * Stage A — Collapse per-installment registry entries into ONE entry per fund.
 *   portfolio.php stores one registry entry per SIP installment, each with its
 *   own small 'Current Value' terminal row. Stage A merges them: all buys, all
 *   sells, ONE terminal row (sum of all installment current values).
 *
 * Stage B — streamlineCashflows(): for each fund, sum all cashflows on the same
 *   date into a SINGLE row. 49 Canara Robeco redemptions on 2026-02-10 (each
 *   ₹15k–₹40k) become ONE row totalling ₹12.99L.  This is the key fix: instead
 *   of 49 sequential mirror-sells where the first empties the unit counter and
 *   leaves 48 rows blank, we do one sell of the aggregate, correctly using all
 *   available units.
 *
 * Stage C — Per-fund unit counter in buildXirrRows().
 *   Nifty mirror units are tracked independently per fund_code. A fund's
 *   terminal 'Current Value' row resets ONLY that fund's counter.
 *
 * ── REDEMPTION LOGIC ───────────────────────────────────────────────────────
 *   On each (aggregated) sell row:
 *     mirror_value_now = hypoUnits[fund] × nifty_price_on_date
 *     pct_sold         = min(redemption_amount / mirror_value_now, 1.0)
 *     units_sold       = hypoUnits[fund] × pct_sold
 *   Mirrors the exact fraction of the fund liquidated in market-value terms.
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// ─────────────────────────────────────────────────────────────────────────────
function xirr_getNiftyPrice(array $prices, string $date): float
{
    if (!empty($prices[$date])) return (float)$prices[$date];
    
    // $prices is sorted chronologically. Search backwards to find the nearest prior date.
    $availableDates = array_keys($prices);
    for ($i = count($availableDates) - 1; $i >= 0; $i--) {
        if ($availableDates[$i] <= $date) {
            return (float)$prices[$availableDates[$i]];
        }
    }
    
    // Fallback: If date is older than our earliest database record,
    // use the earliest available price to prevent dropping the cashflow.
    if (!empty($prices)) {
        reset($prices);
        return (float)current($prices);
    }
    return 0.0;
}

// ─────────────────────────────────────────────────────────────────────────────
// streamlineCashflows()
//
// PUBLIC utility.  Given a flat array of transaction rows (each having at least
// 'date', 'type', 'fund_code', 'cashflow', 'is_terminal'), aggregate all rows
// that share the same fund_code + date + sign (P/R/CV) into one row.
//
// Returns the condensed array sorted by date (terminals last on same date).
// ─────────────────────────────────────────────────────────────────────────────
/**
 * streamlineCashflows()
 *
 * Aggregates rows with the same fund_name + date + sign (P/R/CV) into one row.
 * Preserves input order — does NOT sort by date.
 *
 * Input order from buildXirrRawTxns is already fund-by-fund, with each fund's
 * transactions chronologically sorted and the terminal row last. This produces
 * the desired Excel layout: all rows for Fund A together, then Fund B, etc.
 *
 * The bucket key uses fund_name (not fund_code) to correctly merge rows from
 * held-bond and sold-bond registry entries that share the same name.
 */
function streamlineCashflows(array $txns): array
{
    $buckets = [];
    $order   = [];   // insertion order = fund-by-fund chronological

    foreach ($txns as $t) {
        $fn      = $t['fund_name']  ?? ($t['fund_code'] ?? '');
        $date    = $t['date']       ?? '';
        $isCV    = ($t['is_terminal'] ?? false) || ($t['type'] === 'Current Value');
        $sign    = $isCV ? 'CV' : ($t['cashflow'] < 0 ? 'P' : 'R');

        // Key: fund_name + date + sign — aggregates same-fund same-date rows
        $bKey = $fn . '||' . $date . '||' . $sign;

        if (!isset($buckets[$bKey])) {
            $buckets[$bKey] = [
                'date'            => $date,
                'type'            => $isCV ? 'Current Value' : $sign,
                'investment_type' => $t['investment_type'] ?? '',
                'fund_name'       => $fn,
                'amc_code'        => $t['amc_code']    ?? '',
                'fund_code'       => $t['fund_code']   ?? '',
                'asset_class'     => $t['asset_class'] ?? '',
                'cashflow'        => 0.0,
                'amount'          => 0.0,
                'is_terminal'     => $isCV,
            ];
            if (isset($t['status'])) $buckets[$bKey]['status'] = $t['status'];
            $order[] = $bKey;
        }
        $buckets[$bKey]['cashflow'] += (float)$t['cashflow'];
        $buckets[$bKey]['amount']   += abs((float)$t['cashflow']);
    }

    // Emit in insertion order (= fund-by-fund, each fund chronological)
    // NO global date sort — that would destroy fund grouping.
    $result = [];
    foreach ($order as $k) {
        if (abs($buckets[$k]['cashflow']) < 0.01) continue;
        $result[] = $buckets[$k];
    }
    return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// buildXirrRawTxns()  — Stage A + B
//
// Stage A: Collapse per-installment registry entries into per-fund groups.
//          Merges all entries sharing the same fund_name (not fund_code, because
//          held-bond and sold-bond entries may differ in fund_code).
//          Identifies the TRUE terminal: the last significant positive cashflow
//          across ALL entries for the fund (skipping trailing zeros).
//
// Stage B: streamlineCashflows() — aggregates same fund + same date into one row.
//
// Issue 3 — Fund-wise ordering:
//   Output is sorted FUND BY FUND (all of fund A's rows, then fund B's, etc.)
//   within each fund sorted chronologically. This makes it easy to read/debug.
// ─────────────────────────────────────────────────────────────────────────────
function buildXirrRawTxns(array $fundCashFlowRegistry): array
{
    // ── Stage A: Collapse entries per fund ────────────────────────────────────
    // Key by fund_name only (not fund_code) — held-bond and sold-bond entries
    // use the same bondName but may differ in fund_code or investment_type.
    $fundGroups = [];
    $fundOrder  = [];  // track insertion order for fund-by-fund output

    foreach ($fundCashFlowRegistry as $entry) {
        $fname = $entry['fund_name'] ?? ($entry['fund_code'] ?? 'unknown');
        $itype = $entry['investment_type'] ?? 'MF';
        $gk    = $itype . '||' . $fname;

        if (!isset($fundGroups[$gk])) {
            $fundGroups[$gk] = [
                'investment_type' => $itype,
                'fund_name'       => $fname,
                'amc_code'        => $entry['amc_code']    ?? '',
                'fund_code'       => $entry['fund_code']   ?? '',
                'asset_class'     => $entry['asset_class'] ?? '',
                'status'          => $entry['status']      ?? 'HELD',
                'transactions'    => [],   // all non-terminal cashflows
                'all_terminals'   => [],   // ALL terminal candidates, date-sorted later
            ];
            $fundOrder[] = $gk;
        }

        $cflows   = $entry['cashflows'];
        $tperiods = $entry['timeperiods'];

        $lastIdx = count($cflows) - 1;

        foreach ($cflows as $k => $amount) {
            $ts     = (int)$tperiods[$k];
            $amount = (float)$amount;

            if ($k === $lastIdx) {
                // Store as a terminal CANDIDATE — we'll pick the LATEST one
                $fundGroups[$gk]['all_terminals'][] = [
                    'ts'     => $ts,
                    'amount' => $amount,
                ];
            } else {
                if (abs($amount) < 0.001) continue;  // skip dust
                $fundGroups[$gk]['transactions'][] = [
                    'ts'     => $ts,
                    'amount' => $amount,
                ];
            }
        }
    }

    // Build flat pre-streamline list, FUND BY FUND
    $preTxns = [];

    foreach ($fundOrder as $gk) {
        $g = $fundGroups[$gk];

        // Emit non-terminal transactions sorted by date within this fund
        $txns = $g['transactions'];
        usort($txns, fn($a, $b) => $a['ts'] - $b['ts']);

        foreach ($txns as $txn) {
            if (abs($txn['amount']) < 0.01) continue;
            $preTxns[] = [
                'date'            => date('Y-m-d', $txn['ts']),
                'type'            => $txn['amount'] < 0 ? 'P' : 'R',
                'investment_type' => $g['investment_type'],
                'fund_name'       => $g['fund_name'],
                'amc_code'        => $g['amc_code'],
                'fund_code'       => $g['fund_code'],
                'asset_class'     => $g['asset_class'],
                'amount'          => abs($txn['amount']),
                'cashflow'        => $txn['amount'],
                'is_terminal'     => false,
                'status'          => $g['status'] ?? 'HELD',
            ];
        }

        // Pick the SINGLE true terminal: the one with the LATEST timestamp.
        // If tied on date, pick the one with the LARGEST amount.
        $terminals = $g['all_terminals'];
        if (!empty($terminals)) {
            $sumAmount    = array_sum(array_column($terminals, 'amount'));

            if ($sumAmount > 0.01) {
                usort($terminals, fn($a, $b) =>
                    $b['ts'] - $a['ts'] ?: $b['amount'] - $a['amount']
                );
                $trueTerminal = $terminals[0];

                // Use the true terminal's timestamp but SUM all terminal values
                // (because held-bond adds current_value and sold-bond adds maturity —
                // together they represent the complete cashflow picture)
                $preTxns[] = [
                    'date'            => date('Y-m-d', $trueTerminal['ts']),
                    'type'            => 'Current Value',
                    'investment_type' => $g['investment_type'],
                    'fund_name'       => $g['fund_name'],
                    'amc_code'        => $g['amc_code'],
                    'fund_code'       => $g['fund_code'],
                    'asset_class'     => $g['asset_class'],
                    'amount'          => $sumAmount,
                    'cashflow'        => $sumAmount,
                    'is_terminal'     => true,
                    'status'          => $g['status'] ?? 'HELD',
                ];
            }
        }
    }

    // ── Stage B: aggregate same fund+date+sign → one row ──────────────────────
    return streamlineCashflows($preTxns);
}

/**
 * Build combined portfolio + Nifty mirror rows.
 *
 * Two-pass approach to avoid premature resets:
 *
 * Pass 1 — Pre-scan to find each fund's LAST terminal date.
 *   An intermediate Current Value row (not the fund's last) does NOT reset
 *   hypoUnits. Only the fund's chronologically last Current Value resets.
 *
 * Pass 2 — Process all rows: buys add units, sells remove units,
 *   intermediate CVs display current value without resetting,
 *   final CV values remaining units and resets.
 */
function buildXirrRows(array $fundCashFlowRegistry, PDO $pdo, int $windowStart = 0): array
{
    $rawTxns = buildXirrRawTxns($fundCashFlowRegistry);
    if (empty($rawTxns)) return [];

    $dates = array_unique(array_column($rawTxns, 'date'));
    sort($dates);

    $niftyPrices = [];
    $stmt = $pdo->prepare(
        "SELECT trade_date, nifty500 FROM nifty_historical_data
         WHERE trade_date BETWEEN :from AND :to ORDER BY trade_date ASC"
    );
    $loadFrom = date('Y-m-d', strtotime($dates[0] . ' -10 days'));
    $stmt->execute([':from' => $loadFrom, ':to' => date('Y-m-d')]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $nr) {
        $niftyPrices[$nr['trade_date']] = (float)$nr['nifty500'];
    }

    // Pass 1: find last terminal date per fund_name
    $lastTerminalDate = [];   // [fund_name => 'Y-m-d']
    foreach ($rawTxns as $t) {
        if ($t['is_terminal'] ?? ($t['type'] === 'Current Value')) {
            $fn = $t['fund_name'];
            if (!isset($lastTerminalDate[$fn]) || $t['date'] > $lastTerminalDate[$fn]) {
                $lastTerminalDate[$fn] = $t['date'];
            }
        }
    }

    // Pass 1.5: Compute XIRR per fund to enable proportional redemptions
    $fundCfs = [];
    $fundMeta = [];
    foreach ($rawTxns as $t) {
        $fn = $t['fund_name'];
        if (!isset($fundCfs[$fn])) {
            $fundCfs[$fn] = ['cfs' => [], 'tps' => []];
            $fundMeta[$fn] = ['ac' => $t['asset_class'], 'it' => $t['investment_type']];
        }
        $fundCfs[$fn]['cfs'][] = $t['cashflow'];
        $fundCfs[$fn]['tps'][] = strtotime($t['date']);
    }

    $fundXirrs = [];
    if (!class_exists('XirrEngine')) {
        require_once __DIR__ . '/xirr_engine.php';
    }
    $xirrEngine = new XirrEngine();
    foreach ($fundCfs as $fn => $data) {
        $xirr = $xirrEngine->XIRRAdpro($data['cfs'], $data['tps'], 0.1);
        $fundXirrs[$fn] = is_numeric($xirr) && is_finite($xirr) ? max(-0.9999, (float)$xirr) : 0.0;
    }

    // Pass 2: process all rows
    $rows             = [];
    $hypoUnitsPerFund = [];   // [fund_name => float]  per-fund counters
    $pastCfsPerFund   = [];   // [fund_name => ['cfs' => [], 'tps' => []]]
    
    $fundStartPortVal     = [];
    $fundStartMirrorUnits = [];
    $npStart = 0.0;
    if ($windowStart > 0) {
        $npStart = xirr_getNiftyPrice($niftyPrices, date('Y-m-d', $windowStart));
    }

    foreach ($rawTxns as $t) {
        $cf      = $t['cashflow'];
        $ts      = strtotime($t['date']);
        $isBuy   = ($t['type'] === 'P');
        $isSell  = ($t['type'] === 'R');
        $isCV    = ($t['type'] === 'Current Value');
        $isSold  = ($t['status'] ?? 'HELD') === 'SOLD';
        $np      = xirr_getNiftyPrice($niftyPrices, $t['date']);
        $fn      = $t['fund_name'];   // use fund_name as key (consistent across entries)

        if (!isset($hypoUnitsPerFund[$fn])) $hypoUnitsPerFund[$fn] = 0.0;
        
        // CHECK WINDOW BOUNDARY: Record starting value when crossing window threshold
        if ($windowStart > 0 && $ts >= $windowStart && !isset($fundStartPortVal[$fn])) {
            $xirr = $fundXirrs[$fn] ?? 0.0;
            $portValue = 0.0;
            if (!empty($pastCfsPerFund[$fn]['cfs'])) {
                foreach ($pastCfsPerFund[$fn]['cfs'] as $i => $pastCf) {
                    $pastTs = $pastCfsPerFund[$fn]['tps'][$i];
                    $years = ($windowStart - $pastTs) / 365.0 / 86400.0;
                    $portValue += $pastCf * pow(1 + $xirr, $years);
                }
            }
            $fundStartPortVal[$fn] = -$portValue;
            $fundStartMirrorUnits[$fn] = $hypoUnitsPerFund[$fn];
        }

        $nUnits = 0.0;
        $nCf    = null;
        $nRun   = 0.0;

        if ($isBuy && $np > 0) {
            $nUnits                = abs($cf) / $np;
            $hypoUnitsPerFund[$fn] += $nUnits;
            $nCf                   = $cf;
            $nRun                  = $hypoUnitsPerFund[$fn];

        } elseif ($isSell && $np > 0 && $hypoUnitsPerFund[$fn] > 0.0001) {
            // PROPORTIONAL REDEMPTION: Compute portfolio value just before this sell using fund XIRR
            $xirr = $fundXirrs[$fn] ?? 0.0;
            $portValue = 0.0;
            if (!empty($pastCfsPerFund[$fn]['cfs'])) {
                foreach ($pastCfsPerFund[$fn]['cfs'] as $i => $pastCf) {
                    $pastTs = $pastCfsPerFund[$fn]['tps'][$i];
                    $years = ($ts - $pastTs) / 365.0 / 86400.0;
                    $portValue += $pastCf * pow(1 + $xirr, $years);
                }
            }
            $portValue = -$portValue; // Invert because investments are negative
            
            if ($portValue > 0) {
                $pct = min(abs($cf) / $portValue, 1.0);
            } else {
                $pct = 1.0;
            }

            $nUnits                = $hypoUnitsPerFund[$fn] * $pct;
            $hypoUnitsPerFund[$fn] -= $nUnits;
            if ($hypoUnitsPerFund[$fn] < 0.0001) $hypoUnitsPerFund[$fn] = 0.0;
            $nCf  = round($nUnits * $np, 2);
            $nRun = $hypoUnitsPerFund[$fn];

        } elseif ($isSell && ($hypoUnitsPerFund[$fn] <= 0.0001)) {
            // Post-exit sell — show price with 0 cf so row is not blank
            $nCf  = 0.0;
            $nRun = 0.0;

        } elseif ($isCV) {
            $isTrueFinal = ($lastTerminalDate[$fn] ?? '') === $t['date'];

            if ($isTrueFinal) {
                // Final terminal: value remaining units then reset
                $nUnits                = $hypoUnitsPerFund[$fn];
                $nCf                   = ($np > 0 && $nUnits > 0)
                                          ? round($nUnits * $np, 2) : 0.0;
                $nRun                  = $nUnits;
                $hypoUnitsPerFund[$fn] = 0.0;   // reset only now
            } else {
                // Intermediate CV (snapshot, not final): show value, DO NOT reset
                $nUnits = $hypoUnitsPerFund[$fn];
                $nCf    = ($np > 0 && $nUnits > 0)
                           ? round($nUnits * $np, 2) : 0.0;
                $nRun   = $nUnits;
                // hypoUnitsPerFund[$fn] intentionally NOT reset
            }
        }

        // Record past cashflows for future portValue calculation (exclude CVs)
        if (!$isCV) {
            $pastCfsPerFund[$fn]['cfs'][] = $cf;
            $pastCfsPerFund[$fn]['tps'][] = $ts;
        }

        // Filter by the historical boundary window
        if (strtotime($t['date']) >= $windowStart) {
            $rows[] = [
                'date'            => $t['date'],
                'type'            => $isBuy ? 'Purchase' : ($isSell ? 'Redemption' : 'Current Value'),
                'investment_type' => $t['investment_type'],
                'fund_name'       => $t['fund_name'],
                'asset_class'     => $t['asset_class'],
                'port_amount'     => $t['amount'],
                'cashflow'        => $cf,
                'is_sold'         => $isSold,
                'nifty_price'     => $np     > 0   ? $np     : null,
                'nifty_units'     => $nUnits > 0   ? round($nUnits, 6) : ($nUnits === 0.0 && ($isSell || $isCV) ? 0.0 : null),
                'nifty_running'   => $nRun   >= 0  ? round($nRun,   6) : null,
                'nifty_cashflow'  => $nCf,
            ];
        }
    }
    
    // Prepend Starting Values for any rolling window
    if ($windowStart > 0) {
        $startRows = [];
        foreach ($hypoUnitsPerFund as $fn => $units) {
            if (!isset($fundStartPortVal[$fn])) {
                $xirr = $fundXirrs[$fn] ?? 0.0;
                $portValue = 0.0;
                if (!empty($pastCfsPerFund[$fn]['cfs'])) {
                    foreach ($pastCfsPerFund[$fn]['cfs'] as $i => $pastCf) {
                        $pastTs = $pastCfsPerFund[$fn]['tps'][$i];
                        $years = ($windowStart - $pastTs) / 365.0 / 86400.0;
                        $portValue += $pastCf * pow(1 + $xirr, $years);
                    }
                }
                $fundStartPortVal[$fn] = -$portValue;
                $fundStartMirrorUnits[$fn] = $units;
            }
            
            $startVal = $fundStartPortVal[$fn];
            $mirrorUnits = $fundStartMirrorUnits[$fn];
            
            // If mirrorUnits is 0, the fund was fully sold prior to the window.
            // Any residual startVal is just an XIRR precision artifact and must be scrubbed.
            if ($mirrorUnits < 0.0001) {
                $startVal = 0.0;
            }

            $mirrorVal = $mirrorUnits * $npStart;
            
            if (abs($startVal) > 0.01 || abs($mirrorVal) > 0.01) {
                $ac = $fundMeta[$fn]['ac'] ?? '';
                $it = $fundMeta[$fn]['it'] ?? '';
                
                $startRows[] = [
                    'date'            => date('Y-m-d', $windowStart),
                    'type'            => 'Starting Value',
                    'investment_type' => $it,
                    'fund_name'       => $fn,
                    'asset_class'     => $ac,
                    'port_amount'     => round(abs($startVal), 2),
                    'cashflow'        => -round(abs($startVal), 2), // Treated as an initial investment into the window
                    'is_sold'         => false,
                    'nifty_price'     => $npStart > 0 ? $npStart : null,
                    'nifty_units'     => $mirrorUnits > 0 ? round($mirrorUnits, 6) : null,
                    'nifty_running'   => $mirrorUnits > 0 ? round($mirrorUnits, 6) : null,
                    'nifty_cashflow'  => $mirrorUnits > 0 ? -round(abs($mirrorVal), 2) : null,
                ];
            }
        }
        $rows = array_merge($startRows, $rows);
    }

    return $rows;
}


// ─────────────────────────────────────────────────────────────────────────────
// buildXirrSummaryTable()
// ─────────────────────────────────────────────────────────────────────────────
    function buildXirrSummaryTable(array $fundCashFlowRegistry, PDO $pdo): array
    {
        if (empty($fundCashFlowRegistry)) return [];
    
        if (!class_exists('XirrEngine')) {
            require_once __DIR__ . '/xirr_engine.php';
        }
        $xirrFn = fn($cf, $ts) => (new XirrEngine())->XIRRAdpro($cf, $ts, 0.1);
    
        $windows = [
            'Since Inception' => 0,
            'Last 3 Years'    => strtotime('-3 years'),
            'Last 5 Years'    => strtotime('-5 years'),
        ];
    
        $result = [];
    
        foreach ($windows as $wLabel => $wStart) {
            $rows = buildXirrRows($fundCashFlowRegistry, $pdo, $wStart);
    
            $groups = ['TOTAL' => $rows];
            foreach ($rows as $r) {
                $groups[strtoupper($r['asset_class'] ?? 'OTHER')][] = $r;
            }
    
            foreach ($groups as $ac => $gRows) {
                $portCF  = []; $portTP  = [];
                $bmCF    = []; $bmTP    = [];
    
                foreach ($gRows as $r) {
                    $ts = strtotime($r['date']);
                    if (abs($r['cashflow']) > 0.001) {
                        $portCF[] = $r['cashflow'];
                        $portTP[] = $ts;
                    }
                    if (isset($r['nifty_cashflow']) && abs($r['nifty_cashflow']) > 0.001) {
                        $bmCF[] = $r['nifty_cashflow'];
                        $bmTP[] = $ts;
                    }
                }
    
                $rP = count($portCF) >= 2 ? $xirrFn($portCF, $portTP) : null;
                $rB = count($bmCF)   >= 2 ? $xirrFn($bmCF,   $bmTP)   : null;

				$result[$wLabel][$ac] = [
					'portfolio_xirr' => (is_numeric($rP) && is_finite((float)$rP))
						? round((float)$rP * 100, 2) : null,
					'benchmark_xirr' => (is_numeric($rB) && is_finite((float)$rB))
						? round((float)$rB * 100, 2) : null,
				];
        }
    }
    return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// populateXirrWorksheet()
// ─────────────────────────────────────────────────────────────────────────────
function populateXirrWorksheet(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
    array  $rows,
    string $sheetTitle,
    string $userIIN,
    string $headName
): array {

    $hP = ['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
           'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'2C5F8A']],
           'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,
                         'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
           'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0C4DE']]]];
    $hN = ['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
           'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'185F5A']],
           'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,
                         'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
           'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'9FDED9']]]];
    $tS = ['font'=>['bold'=>true,'size'=>9],
           'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'DDEEFF']],
           'borders'=>['top'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'2C5F8A']]]];
    $xS = ['font'=>['bold'=>true,'size'=>10,'color'=>['rgb'=>'1a4a1a']],
           'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E8F5E9']],
           'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'2e7d32']]]];
    $dS = ['font'=>['size'=>9],
           'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'D0D8E8']]]];
    $gF = ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F0F0F0']];
    $soldFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']]; // Light yellow for sold

    $ws->setTitle(substr($sheetTitle, 0, 31)); // Max excel tab length is 31

    $ws->mergeCells('A1:M1');
    $ws->setCellValue('A1',
        'XIRR Verification — ' . $userIIN .
        ($headName ? '  (' . $headName . ')' : '') . '  —  ' . date('d M Y'));
    $ws->getStyle('A1')->applyFromArray([
        'font'=>['bold'=>true,'size'=>11,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1D3557']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
    $ws->getRowDimension(1)->setRowHeight(20);

    $ws->mergeCells('A2:H2');
    $ws->setCellValue('A2', 'PORTFOLIO CASHFLOWS  (SOLD funds are highlighted)');
    $ws->getStyle('A2')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'2C5F8A']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
    $ws->getStyle('I2')->getFill()->applyFromArray($gF);
    $ws->mergeCells('J2:M2');
    $ws->setCellValue('J2', 'NIFTY 500 MIRROR  (per-fund units; sells use market-value % of mirror)');
    $ws->getStyle('J2')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'185F5A']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);

    foreach (['Date','Type','Inv.Type','Fund Name','Status','Asset Class',
              'Amount (₹)','Portfolio CF (₹)'] as $ci => $h) {
        $ws->setCellValue([$ci + 1, 3], $h);
    }
    $ws->getStyle('A3:H3')->applyFromArray($hP);
    $ws->getStyle('I3')->getFill()->applyFromArray($gF);
    foreach (['Nifty 500 Price','Units Bought/Sold','Running Units','Nifty Mirror CF (₹)'] as $ci => $h) {
        $ws->setCellValue([$ci + 10, 3], $h);
    }
    $ws->getStyle('J3:M3')->applyFromArray($hN);
    $ws->getRowDimension(3)->setRowHeight(28);

    $dataStart = 4;
    $row = $dataStart;
    $matrix = [];
    $soldRows = [];
    $portCF = []; $portTP = [];
    $bmCF = []; $bmTP = [];
    
    foreach ($rows as $i => $r) {
        if (abs($r['cashflow']) > 0.001) {
            $portCF[] = $r['cashflow'];
            $portTP[] = strtotime($r['date']);
        }
        if (isset($r['nifty_cashflow']) && abs($r['nifty_cashflow']) > 0.001) {
            $bmCF[] = $r['nifty_cashflow'];
            $bmTP[] = strtotime($r['date']);
        }
        $matrix[] = [
            $r['date'],
            $r['type'],
            $r['investment_type'] ?? '',
            $r['fund_name'],
            ($r['is_sold'] ?? false) ? 'Sold' : 'Held',
            $r['asset_class'],
            (float)$r['port_amount'],
            (float)$r['cashflow'],
            null, // Spacer
            $r['nifty_price'] !== null ? (float)$r['nifty_price'] : null,
            $r['nifty_units'] !== null ? (float)$r['nifty_units'] : null,
            $r['nifty_running'] !== null ? (float)$r['nifty_running'] : null,
            $r['nifty_cashflow'] !== null ? (float)$r['nifty_cashflow'] : null,
        ];
        if ($r['is_sold'] ?? false) {
            $soldRows[] = $row;
        }
        $row++;
    }
    
    if (!empty($matrix)) {
        $ws->fromArray($matrix, null, "A{$dataStart}");
    }
    $dataEnd = $row - 1;

    $pXirr = 0.0;
    $nXirr = 0.0;
    if (count($portCF) >= 2) {
        $raw = (new XirrEngine())->XIRRAdpro($portCF, $portTP, 0.1);
        $pXirr = is_numeric($raw) && is_finite((float)$raw) ? max(-0.9999, (float)$raw) : 0.0;
    }
    if (count($bmCF) >= 2) {
        $raw = (new XirrEngine())->XIRRAdpro($bmCF, $bmTP, 0.1);
        $nXirr = is_numeric($raw) && is_finite((float)$raw) ? max(-0.9999, (float)$raw) : 0.0;
    }

    if ($dataEnd >= $dataStart) {
        $ws->getStyle("A{$dataStart}:A{$dataEnd}")->getNumberFormat()->setFormatCode('DD-MMM-YYYY');
        $ws->getStyle("G{$dataStart}:H{$dataEnd}")->getNumberFormat()->setFormatCode('[Color 10]#,##0.00;[Red](#,##0.00);-');
        $ws->getStyle("J{$dataStart}:J{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle("K{$dataStart}:L{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0.000000');
        $ws->getStyle("M{$dataStart}:M{$dataEnd}")->getNumberFormat()->setFormatCode('[Color 10]#,##0.00;[Red](#,##0.00);-');
        $ws->getStyle("A{$dataStart}:H{$dataEnd}")->applyFromArray($dS);
        $ws->getStyle("J{$dataStart}:M{$dataEnd}")->applyFromArray($dS);
        $ws->getStyle("I{$dataStart}:I{$dataEnd}")->getFill()->applyFromArray($gF);
        
        // Bulk solid background for speed
        $ws->getStyle("A{$dataStart}:H{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
        $ws->getStyle("J{$dataStart}:M{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5FFFE');
        
        // Overlay sold highlights
        foreach ($soldRows as $sr) {
            $ws->getStyle("A{$sr}:H{$sr}")->getFill()->applyFromArray($soldFill);
            $ws->getStyle("J{$sr}:M{$sr}")->getFill()->applyFromArray($soldFill);
        }
    }

    if ($dataEnd >= $dataStart) {
        $ws->setCellValue("A{$row}", 'TOTAL');
        $ws->setCellValue("G{$row}", "=SUM(G{$dataStart}:G{$dataEnd})");
        $ws->setCellValue("H{$row}", "=SUM(H{$dataStart}:H{$dataEnd})");
        $ws->setCellValue("M{$row}", "=SUM(M{$dataStart}:M{$dataEnd})");
        $ws->getStyle("G{$row}:H{$row}")->getNumberFormat()->setFormatCode('#,##0.00;(#,##0.00);-');
        $ws->getStyle("M{$row}")->getNumberFormat()->setFormatCode('#,##0.00;(#,##0.00);-');
        $ws->getStyle("A{$row}:H{$row}")->applyFromArray($tS);
        $ws->getStyle("J{$row}:M{$row}")->applyFromArray($tS);
    }
    $row += 2;

    $pXR = $row;
    $ws->setCellValue("A{$row}", 'Total Portfolio XIRR');
    if ($dataEnd >= $dataStart) {
        // Use PHP calculated XIRR instead of Excel formula to prevent #NUM! errors due to fund-by-fund sorting
        $ws->setCellValue("B{$row}", $pXirr);
    }
    $ws->getStyle("B{$row}")->getNumberFormat()->setFormatCode('0.00%');
    $ws->getStyle("A{$row}:B{$row}")->applyFromArray($xS);
    $ws->setCellValue("C{$row}", '← All asset classes combined — compare with PDF');
    $ws->getStyle("C{$row}")->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB('555');
    $row++;

    $nXR = $row;
    $ws->setCellValue("A{$row}", 'Total Nifty 500 Mirror XIRR');
    if ($dataEnd >= $dataStart) {
        // Use PHP calculated XIRR
        $ws->setCellValue("B{$row}", $nXirr);
    }
    $ws->getStyle("B{$row}")->getNumberFormat()->setFormatCode('0.00%');
    $ws->getStyle("A{$row}:B{$row}")->applyFromArray($xS);
    $ws->setCellValue("C{$row}", '← Per-fund unit tracking, aggregated cashflows');
    $ws->getStyle("C{$row}")->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB('555');

    foreach (['A'=>14,'B'=>13,'C'=>11,'D'=>38,'E'=>8,'F'=>10,'G'=>14,'H'=>17,
              'I'=>3,'J'=>16,'K'=>15,'L'=>15,'M'=>20] as $c => $w) {
        $ws->getColumnDimension($c)->setWidth($w);
    }
    $ws->freezePane('A4');

    return ['pXR' => $pXR, 'nXR' => $nXR];
}

// ─────────────────────────────────────────────────────────────────────────────
// populateXirrSummaryWorksheet()
// ─────────────────────────────────────────────────────────────────────────────
function populateXirrSummaryWorksheet(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws2,
    array  $xirrSummary,
    string $sheetTitle,
    string $userIIN,
    string $headName,
    string $txnSheetName,
    int    $pXR,
    int    $nXR
): void {
    $dS = ['font'=>['size'=>9],'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'D0D8E8']]]];
    $xS = ['font'=>['bold'=>true,'size'=>10,'color'=>['rgb'=>'1a4a1a']],
           'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E8F5E9']],
           'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'2e7d32']]]];

    $ws2->setTitle(substr($sheetTitle, 0, 31));
    $ws2->mergeCells('A1:H1');
    $ws2->setCellValue('A1', 'XIRR Summary — ' . $userIIN . ($headName ? '  (' . $headName . ')' : ''));
    $ws2->getStyle('A1')->applyFromArray(['font'=>['bold'=>true,'size'=>12,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1D3557']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
    $ws2->getRowDimension(1)->setRowHeight(22);
    $ws2->mergeCells('A2:H2');
    $ws2->setCellValue('A2', 'Aggregated cashflows | Per-fund Nifty units | Generated: ' . date('d M Y H:i'));
    $ws2->getStyle('A2')->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB('555555');

    foreach (['Asset Class','Since Inc. Portfolio','Since Inc. Nifty Mirror','Alpha',
              '3yr Portfolio','3yr Nifty','5yr Portfolio','5yr Nifty'] as $ci => $h) {
        $ws2->setCellValue([$ci + 1, 3], $h);
    }
    $ws2->getStyle('A3:H3')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'2C5F8A']],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'wrapText'=>true],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0C4DE']]]]);
    $ws2->getRowDimension(3)->setRowHeight(32);

    $r2 = 4;
    foreach (['EQUITY','DEBT','HYBRID','GOLD','TOTAL'] as $ac) {
        $si = $xirrSummary['Since Inception'][$ac] ?? null;
        $y3 = $xirrSummary['Last 3 Years'][$ac]    ?? null;
        $y5 = $xirrSummary['Last 5 Years'][$ac]    ?? null;
        if (!$si && !$y3 && !$y5) continue;

        $pSI   = $si['portfolio_xirr'] ?? null;
        $bSI   = $si['benchmark_xirr'] ?? null;
        $alpha = (is_numeric($pSI) && is_numeric($bSI)) ? round($pSI - $bSI, 2) : null;
        $fill  = ($ac === 'TOTAL') ? 'DDEEFF' : (($r2 % 2) ? 'FFFFFF' : 'F5F8FF');

        $ws2->setCellValue("A{$r2}", ucfirst(strtolower($ac)));
        foreach ([
            'B' => $pSI, 'C' => $bSI, 'D' => $alpha,
            'E' => $y3['portfolio_xirr'] ?? null, 'F' => $y3['benchmark_xirr'] ?? null,
            'G' => $y5['portfolio_xirr'] ?? null, 'H' => $y5['benchmark_xirr'] ?? null,
        ] as $col => $val) {
            if ($val !== null) {
                $ws2->setCellValue("{$col}{$r2}", $val / 100);
                $ws2->getStyle("{$col}{$r2}")->getNumberFormat()
                    ->setFormatCode($col === 'D' ? '+0.00%;-0.00%;0.00%' : '0.00%');
                $ws2->getStyle("{$col}{$r2}")->getFont()->getColor()
                    ->setRGB($val >= 0 ? '1a7a4a' : 'C0392B');
            } else {
                $ws2->setCellValue("{$col}{$r2}", '—');
            }
        }
        $ws2->getStyle("A{$r2}:H{$r2}")->applyFromArray($dS);
        $ws2->getStyle("A{$r2}:H{$r2}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
        if ($ac === 'TOTAL') $ws2->getStyle("A{$r2}:H{$r2}")->getFont()->setBold(true);
        $r2++;
    }
    foreach (['A'=>15,'B'=>18,'C'=>18,'D'=>12,'E'=>16,'F'=>16,'G'=>16,'H'=>16] as $c => $w) {
        $ws2->getColumnDimension($c)->setWidth($w);
    }
    $r2 += 2;
    
    $safeSheetName = str_replace("'", "''", $txnSheetName);
    
    $ws2->setCellValue("A{$r2}", 'Total Portfolio XIRR');
    $ws2->setCellValue("B{$r2}", "='{$safeSheetName}'!B{$pXR}");
    $ws2->getStyle("B{$r2}")->getNumberFormat()->setFormatCode('0.00%');
    $ws2->getStyle("A{$r2}:B{$r2}")->applyFromArray($xS);
    $r2++;
    $ws2->setCellValue("A{$r2}", 'Total Nifty 500 Mirror XIRR');
    $ws2->setCellValue("B{$r2}", "='{$safeSheetName}'!B{$nXR}");
    $ws2->getStyle("B{$r2}")->getNumberFormat()->setFormatCode('0.00%');
    $ws2->getStyle("A{$r2}:B{$r2}")->applyFromArray($xS);
}

// ─────────────────────────────────────────────────────────────────────────────
function saveXirrDebugExcel(
    array  $xirrRowsAll,
    array  $xirrSummaryAll,
    array  $xirrRowsHeld,
    array  $xirrSummaryHeld,
    string $userIIN,
    string $headName,
    string $dir,
    array  $xirrRowsAll3yr = [],
    array  $xirrRowsAll5yr = [],
    array  $xirrRowsHeld3yr = [],
    array  $xirrRowsHeld5yr = []
): string {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $headName);
    $file = $dir . $safe . '_XIRR_Debug_' . date('dMY') . '.xlsx';
    
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setTitle('XIRR Verification — ' . $userIIN)
        ->setCreator('LitmFin');
    
    $spreadsheet->removeSheetByIndex(0);

    // Sheet 1: Held Assets - CF
    $sheet1Name = 'Held Assets (CF)';
    $sheet1 = $spreadsheet->createSheet();
    $sheet1->getTabColor()->setRGB('1E3A5F'); // Navy color code for tab
    $refsHeld = populateXirrWorksheet($sheet1, $xirrRowsHeld, $sheet1Name, $userIIN, $headName . ' - Held Assets');

    if (!empty($xirrRowsHeld3yr)) {
        $sHeld3 = $spreadsheet->createSheet();
        $sHeld3->getTabColor()->setRGB('3A5F8B');
        populateXirrWorksheet($sHeld3, $xirrRowsHeld3yr, 'Held Assets (3 Yr)', $userIIN, $headName . ' - Held (3 Yr)');
    }

    if (!empty($xirrRowsHeld5yr)) {
        $sHeld5 = $spreadsheet->createSheet();
        $sHeld5->getTabColor()->setRGB('3A5F8B');
        populateXirrWorksheet($sHeld5, $xirrRowsHeld5yr, 'Held Assets (5 Yr)', $userIIN, $headName . ' - Held (5 Yr)');
    }

    // Held Assets - Sum
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->getTabColor()->setRGB('1E3A5F');
    populateXirrSummaryWorksheet($sheet2, $xirrSummaryHeld, 'Held Assets (Sum)', $userIIN, $headName . ' - Held Assets', $sheet1Name, $refsHeld['pXR'], $refsHeld['nXR']);

    // All Assets - CF
    $sheet3Name = 'All Assets (CF)';
    $sheet3 = $spreadsheet->createSheet();
    $sheet3->getTabColor()->setRGB('E87722'); // Orange color code for tab
    $refsAll = populateXirrWorksheet($sheet3, $xirrRowsAll, $sheet3Name, $userIIN, $headName . ' - All Assets');

    if (!empty($xirrRowsAll3yr)) {
        $sAll3 = $spreadsheet->createSheet();
        $sAll3->getTabColor()->setRGB('F09045');
        populateXirrWorksheet($sAll3, $xirrRowsAll3yr, 'All Assets (3 Yr)', $userIIN, $headName . ' - All (3 Yr)');
    }

    if (!empty($xirrRowsAll5yr)) {
        $sAll5 = $spreadsheet->createSheet();
        $sAll5->getTabColor()->setRGB('F09045');
        populateXirrWorksheet($sAll5, $xirrRowsAll5yr, 'All Assets (5 Yr)', $userIIN, $headName . ' - All (5 Yr)');
    }

    // All Assets - Sum
    $sheet4 = $spreadsheet->createSheet();
    $sheet4->getTabColor()->setRGB('E87722');
    populateXirrSummaryWorksheet($sheet4, $xirrSummaryAll, 'All Assets (Sum)', $userIIN, $headName . ' - All Assets', $sheet3Name, $refsAll['pXR'], $refsAll['nXR']);

    $spreadsheet->setActiveSheetIndex(0);
    
    (new Xlsx($spreadsheet))->save($file);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    
    return $file;
}
