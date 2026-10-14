<?php
require_once('auth_guard.php');

/**
 * benchmark_xirr.php
 * 
 * Benchmark replication engine + chart builder.
 * 
 * Strategy: given the client's actual cashflow arrays (parallel arrays of
 * amounts + Unix timestamps), simulate what would have happened if those
 * same rupee amounts were invested in Nifty 500 on the same dates.
 * 
 * For each buy cashflow (negative amount, Unix timestamp):
 *   - Look up Nifty 500 closing price on that date (or nearest prior trading day)
 *   - Compute hypothetical units purchased = |amount| / nifty_price
 * 
 * At the valuation date (today / report date):
 *   - Value hypothetical units at today's Nifty 500 price
 *   - Append as positive cashflow
 * 
 * Run XIRRAdpro on this synthetic cashflow series → benchmark XIRR
 * 
 * Requirements:
 *   - nifty_historical_data DB table: trade_date (DATE), nifty50, nifty500
 *   - Same Financial / XIRRAdpro engine used by totalholdings.php
 *   - QuickChart.io for chart PNG generation (free, no install)
 * 
 * Usage in generatereport.php / section_charts.php:
 *   require_once 'benchmark_xirr.php';
 *   $bm = new BenchmarkXirr($pdo);
 *   $result = $bm->computeBenchmark($cashFlows, $timePeriods, 'nifty500');
 *   $png    = $bm->buildComparisonChartPng($result, $clientName);
 */

require_once __DIR__ . '/xirr_engine.php';
if (file_exists(__DIR__ . '/index_xirr.php')) {
    require_once __DIR__ . '/index_xirr.php';
}

class BenchmarkXirr
{
    private PDO    $pdo;
    private array  $priceCache = [];   // date-string → nifty500 price
    private object $xirrObj;
    private string $cacheDir;

    public function __construct(PDO $pdo)
    {
        $this->pdo      = $pdo;
        // Use XirrCalculator (PhpSpreadsheet engine) if available, else fallback
        $this->xirrObj = new XirrEngine();
        $this->cacheDir = __DIR__ . '/cache/charts/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 1 — CORE BENCHMARK COMPUTATION
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Main entry point.
     *
     * @param  array  $cashFlows    Parallel array of amounts. Negatives = investments,
     *                              positives = redemptions. Last element should be
     *                              +current_value appended by totalholdings.php.
     * @param  array  $timePeriods  Parallel array of Unix timestamps.
     * @param  string $index        'nifty500' or 'nifty50'
     * @return array  [
     *                  'portfolio_xirr'    => float (already *100, e.g. 14.23),
     *                  'benchmark_xirr'    => float,
     *                  'first_date'        => string Y-m-d,
     *                  'last_date'         => string Y-m-d,
     *                  'holding_days'      => int,
     *                  'cashflow_count'    => int,
     *                  'monthly_portfolio' => [...],   // for chart
     *                  'monthly_nifty'     => [...],   // for chart
     *                  'monthly_labels'    => [...],   // month labels
     *                  'growth_portfolio'  => [...],   // ₹1L growth series
     *                  'growth_nifty'      => [...],
     *                ]
     */
    public function computeBenchmark(
        array  $cashFlows,
        array  $timePeriods,
        string $index = 'nifty500'
    ): array {

        if (empty($cashFlows) || count($cashFlows) !== count($timePeriods)) {
            return $this->emptyResult();
        }

        // Sort both arrays together by date ascending
        array_multisort($timePeriods, SORT_ASC, $cashFlows);

        $firstTs  = $timePeriods[0];
        $lastTs   = $timePeriods[count($timePeriods) - 1];
        $firstDate = date('Y-m-d', $firstTs);
        $lastDate  = date('Y-m-d', $lastTs);
        $today     = date('Y-m-d');

        // Pre-load all Nifty prices between first investment and today in one query
        $this->loadPriceRange($firstDate, $today, $index);

        $portfolioXirr = 0.0;
        if (count($cashFlows) >= 2) {
            $raw = $this->xirrObj->XIRRAdpro($cashFlows, $timePeriods, 0.1);
            $portfolioXirr = (is_numeric($raw) && is_finite((float)$raw)) ? max(-0.9999, (float)$raw) : 0.0;
        }

        // ── Build synthetic benchmark cashflows ──
        $bmCashFlows       = [];
        $bmTimePeriods     = [];
        $hypotheticalUnits = 0.0;

        // All but the last element — last is the +current_value terminal cashflow
        $txnCount = count($cashFlows) - 1;

        for ($i = 0; $i < $txnCount; $i++) {
            $amount = $cashFlows[$i];
            $ts     = $timePeriods[$i];
            $date   = date('Y-m-d', $ts);

            if ($amount < 0) {
                // Investment: buy hypothetical Nifty units
                $price = $this->getNiftyPrice($date, $index);
                if ($price > 0) {
                    $unitsBought        = abs($amount) / $price;
                    $hypotheticalUnits += $unitsBought;
                    $bmCashFlows[]      = $amount;   // negative, same as actual
                    $bmTimePeriods[]    = $ts;
                }
            } elseif ($amount > 0) {
                // Redemption: sell the same PERCENTAGE of hypothetical units
                // as the redemption is of the portfolio's compounded value.
                $price = $this->getNiftyPrice($date, $index);
                if ($price > 0 && $hypotheticalUnits > 0) {
                    $portValue = 0.0;
                    for ($j = 0; $j < $i; $j++) {
                        $pastCf = $cashFlows[$j];
                        $pastTs = $timePeriods[$j];
                        $years = ($ts - $pastTs) / 365.0 / 86400.0;
                        $portValue += $pastCf * pow(1 + $portfolioXirr, $years);
                    }
                    $portValue = -$portValue;
                    
                    $pctRedeemed = $portValue > 0 ? min($amount / $portValue, 1.0) : 1.0;
                    
                    $unitsToSell        = $hypotheticalUnits * $pctRedeemed;
                    $hypotheticalUnits -= $unitsToSell;
                    if ($hypotheticalUnits < 0.0001) $hypotheticalUnits = 0.0;
                    $bmCashFlows[]      = round($unitsToSell * $price, 2);  // positive
                    $bmTimePeriods[]    = $ts;
                }
            }
        }

        // Terminal value: current Nifty price × hypothetical units
        $todayPrice = $this->getNiftyPrice($today, $index);
        if ($hypotheticalUnits > 0 && $todayPrice > 0) {
            $bmCashFlows[]  = $hypotheticalUnits * $todayPrice;
            $bmTimePeriods[] = time();
        }

        // ── Compute XIRRs ──
        $benchmarkXirr = 0.0;

        if (count($cashFlows) >= 2) {
            $portfolioXirr = round($portfolioXirr * 100, 2);
        }

        if (count($bmCashFlows) >= 2) {
            $raw = $this->xirrObj->XIRRAdpro($bmCashFlows, $bmTimePeriods, 0.1);
            $benchmarkXirr = (is_numeric($raw) && is_finite((float)$raw)) ? round($raw * 100, 2) : 0.0;
        }

        // ── Build monthly series for charts ──
        $monthly = $this->buildMonthlyGrowthSeries(
            $cashFlows, $timePeriods, $index, $today
        );

        return [
            'portfolio_xirr'    => $portfolioXirr,
            'benchmark_xirr'    => $benchmarkXirr,
            'first_date'        => $firstDate,
            'last_date'         => $lastDate,
            'holding_days'      => (int)(($lastTs - $firstTs) / 86400),
            'cashflow_count'    => $txnCount,
            'monthly_labels'    => $monthly['labels'],
            'monthly_portfolio' => $monthly['portfolioValues'],
            'monthly_nifty'     => $monthly['niftyValues'],
            'growth_portfolio'  => $monthly['growthPortfolio'],
            'growth_nifty'      => $monthly['growthNifty'],
        ];
    }

    /**
     * Build month-end portfolio value series and Nifty 500 index series
     * from first investment date to today.
     * 
     * For portfolio: we use the actual current value at report date and
     * back-calculate a NAV-weighted series. Since we don't have daily
     * portfolio NAV history, we use the Nifty series as the time axis
     * and plot portfolio cost + gain attribution.
     * 
     * For ₹1L growth chart: simpler — invest ₹1L in Nifty on first date,
     * track its value month by month.
     */
    private function buildMonthlyGrowthSeries(
        array  $cashFlows,
        array  $timePeriods,
        string $index,
        string $today
    ): array {

        $labels            = [];
        $portfolioValues   = [];
        $niftyValues       = [];
        $growthPortfolio   = [];
        $growthNifty       = [];

        $firstTs    = $timePeriods[0];
        $firstDate  = date('Y-m-d', $firstTs);
        $firstPrice = $this->getNiftyPrice($firstDate, $index);

        if ($firstPrice <= 0) {
            return compact('labels','portfolioValues','niftyValues','growthPortfolio','growthNifty');
        }

        // ₹1L growth in Nifty
        $growthBase = 100000.0;

        // Iterate month by month from first investment to today
        $cursor = mktime(0, 0, 0, (int)date('m', $firstTs), 1, (int)date('Y', $firstTs));
        $endTs  = strtotime($today);

        // Running totals for portfolio cost invested up to each month
        $cumulativeCost = 0.0;

        while ($cursor <= $endTs) {
            $monthLabel = date('M y', $cursor);
            $monthEnd   = date('Y-m-d', $cursor);

            // Nifty price at this month
            $niftyPrice = $this->getNiftyPrice($monthEnd, $index);
            if ($niftyPrice <= 0) {
                $cursor = strtotime('+1 month', $cursor);
                continue;
            }

            // Cumulative investment up to this month
            $monthCost = 0.0;
            foreach ($timePeriods as $k => $ts) {
                if ($ts <= $cursor && $cashFlows[$k] < 0) {
                    $monthCost += abs($cashFlows[$k]);
                }
            }

            if ($monthCost > 0) {
                $labels[]          = $monthLabel;
                $niftyValues[]     = round($niftyPrice, 2);
                $portfolioValues[] = round($monthCost, 2);

                // ₹1L growth: how much would ₹1L invested on first date be worth now
                $growthNifty[]     = round(($niftyPrice / $firstPrice) * $growthBase, 0);
                $growthPortfolio[] = round($monthCost, 0); // replaced below with actual current value ratio
            }

            $cursor = strtotime('+1 month', $cursor);
        }

        // Both growth series use XIRR-based compounding so they are directly comparable.
        //
        // WHY: Nifty actual monthly prices are non-linear (market volatility).
        // Portfolio month-by-month values are unavailable (no per-fund NAV history).
        // Mixing actual Nifty prices with a smooth portfolio curve creates a misleading
        // chart where a smooth line appears below a volatile one even when portfolio wins.
        //
        // FIX: derive the annualised XIRR for both portfolio and Nifty mirror,
        // then plot both as smooth compounding curves from the same ₹1L base.
        //   portfolio_growth[m] = 100000 × (1 + portXirr)^(days/365)
        //   niftyGrowth[m]      = 100000 × (1 + niftyXirr)^(days/365)
        // When portXirr > niftyXirr, the portfolio line is ALWAYS above the Nifty line.
        if (!empty($labels) && $firstTs > 0) {
            // Compute portfolio XIRR from cashflows
            $portXirr  = 0.0;
            $niftyXirr = 0.0;
            if (count($cashFlows) >= 2) {
                $xirrEngine = new XirrEngine();
                $raw = $xirrEngine->XIRRAdpro($cashFlows, $timePeriods, 0.1);
                if (is_numeric($raw) && is_finite((float)$raw) && abs($raw) < 50) {
                    $portXirr = (float)$raw;
                }
                // Build Nifty mirror cashflows: same buy amounts but discounted by actual Nifty prices
                $bmCF = []; $bmTP = []; $bmUnits = 0.0; $lastDate = date('Y-m-d');
                foreach ($cashFlows as $i => $cf) {
                    $ts = $timePeriods[$i];
                    $d  = date('Y-m-d', $ts);
                    $np = $this->getNiftyPrice($d, $index);
                    if ($cf < 0 && $np > 0) {
                        $bmUnits += abs($cf) / $np;
                        $bmCF[]   = $cf;
                        $bmTP[]   = $ts;
                    } elseif ($cf > 0.01 && $np > 0 && $bmUnits > 0.0001) {
                        $mv  = $bmUnits * $np;
                        $pct = min($cf / $mv, 1.0);
                        $bmUnits -= $bmUnits * $pct;
                        $bmCF[]   = round($bmUnits > 0.0001 ? $mv * $pct : $mv, 2);
                        $bmTP[]   = $ts;
                    }
                }
                // Terminal: value remaining Nifty units today
                $todayP = $this->getNiftyPrice($lastDate, $index);
                if ($bmUnits > 0.0001 && $todayP > 0) {
                    $bmCF[] = round($bmUnits * $todayP, 2);
                    $bmTP[] = time();
                }
                if (count($bmCF) >= 2) {
                    $rawN = $xirrEngine->XIRRAdpro($bmCF, $bmTP, 0.1);
                    if (is_numeric($rawN) && is_finite((float)$rawN) && abs($rawN) < 50) {
                        $niftyXirr = (float)$rawN;
                    }
                }
            }

            // Build both growth curves from the same base using their respective XIRRs
            $growthPortfolio = [];
            $growthNifty     = [];
            $cursor2 = mktime(0, 0, 0, (int)date('m', $firstTs), 1, (int)date('Y', $firstTs));
            foreach ($labels as $lbl) {
                $daysPassed = max(0, ($cursor2 - $firstTs) / 86400.0);
                $years = $daysPassed / 365.0;
                $growthPortfolio[] = round($growthBase * pow(1.0 + $portXirr,  $years), 0);
                $growthNifty[]     = round($growthBase * pow(1.0 + $niftyXirr, $years), 0);
                $cursor2 = strtotime('+1 month', $cursor2);
            }
        }

        // --- Down-sample if > 120 points to prevent QuickChart API limits ---
        $count = count($labels);
        if ($count > 120) {
            $step = (int)ceil($count / 120);
            $sampledLabels = [];
            $sampledPort = [];
            $sampledNifty = [];
            $sampledGPort = [];
            $sampledGNifty = [];
            
            for ($i = 0; $i < $count; $i += $step) {
                $sampledLabels[] = $labels[$i];
                if (isset($portfolioValues[$i])) $sampledPort[] = $portfolioValues[$i];
                if (isset($niftyValues[$i])) $sampledNifty[] = $niftyValues[$i];
                if (isset($growthPortfolio[$i])) $sampledGPort[] = $growthPortfolio[$i];
                if (isset($growthNifty[$i])) $sampledGNifty[] = $growthNifty[$i];
            }
            // Ensure the very last data point is always included
            $lastIdx = $count - 1;
            if ($lastIdx % $step !== 0) {
                $sampledLabels[] = $labels[$lastIdx];
                if (isset($portfolioValues[$lastIdx])) $sampledPort[] = $portfolioValues[$lastIdx];
                if (isset($niftyValues[$lastIdx])) $sampledNifty[] = $niftyValues[$lastIdx];
                if (isset($growthPortfolio[$lastIdx])) $sampledGPort[] = $growthPortfolio[$lastIdx];
                if (isset($growthNifty[$lastIdx])) $sampledGNifty[] = $growthNifty[$lastIdx];
            }
            
            $labels = $sampledLabels;
            $portfolioValues = $sampledPort;
            $niftyValues = $sampledNifty;
            $growthPortfolio = $sampledGPort;
            $growthNifty = $sampledGNifty;
        }

        return compact('labels','portfolioValues','niftyValues','growthPortfolio','growthNifty');
    }


    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 2 — ROLLING XIRR (3yr and 5yr windows)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Compute rolling XIRR for a fixed window (e.g. 3 years or 5 years).
     * 
     * Simulates: "If a client had invested in this portfolio vs Nifty 500
     * for exactly N years ending today, what XIRR would they have got?"
     * 
     * Method: filter cashflows to only those within the window,
     * append today's current-value cashflow, compute XIRR.
     * 
     * @param  array  $cashFlows
     * @param  array  $timePeriods
     * @param  float  $currentValue  Portfolio current value (positive)
     * @param  int    $years         3 or 5
     * @param  string $index
     * @return array  ['portfolio_xirr' => float, 'benchmark_xirr' => float, 'years' => int]
     */
    public function computeRollingXirr(
        array  $cashFlows,
        array  $timePeriods,
        float  $currentValue,
        int    $years = 3,
        string $index = 'nifty500'
    ): array {

        $windowStart = strtotime("-{$years} years");
        $today       = date('Y-m-d');
        $nowTs       = time();

        // Filter to cashflows within window
        $windowCF  = [];
        $windowTP  = [];
        $bmCF      = [];
        $bmTP      = [];
        $bmUnits   = 0.0;

        $this->loadPriceRange(date('Y-m-d', $windowStart), $today, $index);

        foreach ($timePeriods as $k => $ts) {
            if ($ts >= $windowStart) {
                $amount = $cashFlows[$k];
                $date   = date('Y-m-d', $ts);

                // Only count investment cashflows (skip the terminal current-value entry)
                if ($amount < 0) {
                    $windowCF[] = $amount;
                    $windowTP[] = $ts;

                    $price = $this->getNiftyPrice($date, $index);
                    if ($price > 0) {
                        $bmUnits += abs($amount) / $price;
                        $bmCF[]   = $amount;
                        $bmTP[]   = $ts;
                    }
                }
            }
        }

        if (empty($windowCF)) {
            return ['portfolio_xirr' => 0.0, 'benchmark_xirr' => 0.0, 'years' => $years];
        }

        // Append current values as terminal cashflows
        $windowCF[] = $currentValue;
        $windowTP[] = $nowTs;

        $todayPrice = $this->getNiftyPrice($today, $index);
        $bmCurrentValue = $bmUnits * $todayPrice;
        $bmCF[]  = $bmCurrentValue;
        $bmTP[]  = $nowTs;

        // Compute XIRRs
        $rawPortfolio = $this->xirrObj->XIRRAdpro($windowCF, $windowTP, 0.1);
        $rawBenchmark = $this->xirrObj->XIRRAdpro($bmCF, $bmTP, 0.1);

        return [
            'portfolio_xirr' => is_numeric($rawPortfolio) ? round($rawPortfolio * 100, 2) : 0.0,
            'benchmark_xirr' => is_numeric($rawBenchmark) ? round($rawBenchmark * 100, 2) : 0.0,
            'years'          => $years,
        ];
    }


    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 2B — REGISTRY-BASED BENCHMARK (correct, matches Excel XIRR)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Compute portfolio XIRR AND Nifty mirror XIRR from $fundCashFlowRegistry.
     *
     * Fixes the $totalCashFlows problem: that array is a raw merge of per-fund
     * arrays each ending with a per-fund current_value, so XIRR sees dozens of
     * intermediate "redemptions" and underestimates the return (e.g. 5% not 10.8%).
     *
     * This method processes each fund independently, then:
     *  - Portfolio XIRR = XIRR on [all buys, all sells, single combined terminal]
     *  - Nifty mirror XIRR = XIRR on [mirrored buys/sells, Nifty value of
     *    remaining units at terminal date]
     */
    public function computeBenchmarkFromRegistry(
        array  $fundCashFlowRegistry,
        string $index = 'nifty500',
        string $statusFilter = 'ALL'
    ): array {
        if (empty($fundCashFlowRegistry)) return $this->emptyResult();

        // Step 1: collapse per-installment entries into per-fund groups
        $fundGroups = [];
        $fundOrder  = [];

        foreach ($fundCashFlowRegistry as $entry) {
            // Skip entries that do not match the desired status
            if ($statusFilter !== 'ALL') {
                $status = $entry['status'] ?? 'HELD';
                if (strcasecmp($status, $statusFilter) !== 0) {
                    continue;
                }
            }

            $fname = $entry['fund_name'] ?? ($entry['fund_code'] ?? 'unknown');
            $itype = $entry['investment_type'] ?? 'MF';
            $gk    = $itype . '||' . $fname;

            if (!isset($fundGroups[$gk])) {
                $fundGroups[$gk] = [
                    'fund_name'   => $fname,
                    'asset_class' => $entry['asset_class'] ?? '',
                    'txns'        => [],
                    'terminal_cf' => 0.0,
                    'terminal_ts' => 0,
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
                    $fundGroups[$gk]['terminal_cf'] += $amount;
                    if ($ts > $fundGroups[$gk]['terminal_ts']) {
                        $fundGroups[$gk]['terminal_ts'] = $ts;
                    }
                } else {
                    if (abs($amount) < 0.001) continue;
                    $fundGroups[$gk]['txns'][] = ['ts' => $ts, 'cf' => $amount];
                }
            }
        }

        // Step 1.5: Streamline transactions to prevent timeouts on massive identical records (e.g. bonds)
        foreach ($fundOrder as $gk) {
            $txns = $fundGroups[$gk]['txns'];
            if (count($txns) > 1) {
                usort($txns, fn($a, $b) => $a['ts'] - $b['ts']);
                $streamlined = [];
                $prevKey = null;
                foreach ($txns as $t) {
                    $dateStr = date('Y-m-d', $t['ts']);
                    $sign = ($t['cf'] < 0) ? 'P' : 'R';
                    $key = $dateStr . '|' . $sign;

                    if ($key === $prevKey) {
                        $streamlined[count($streamlined) - 1]['cf'] += $t['cf'];
                    } else {
                        $streamlined[] = $t;
                        $prevKey = $key;
                    }
                }
                $fundGroups[$gk]['txns'] = $streamlined;
            }
        }

        // Step 2: load Nifty prices
        $allDates = [];
        foreach ($fundGroups as $g) {
            foreach ($g['txns'] as $t) $allDates[] = date('Y-m-d', $t['ts']);
            if ($g['terminal_ts']) $allDates[] = date('Y-m-d', $g['terminal_ts']);
        }
        sort($allDates);
        if (empty($allDates)) return $this->emptyResult();
        $today = date('Y-m-d');
        $this->loadPriceRange($allDates[0], $today, $index);

        // Calculate XIRR for each fund
        $fundXirrs = [];
        foreach ($fundOrder as $gk) {
            $g = $fundGroups[$gk];
            $fn = $g['fund_name'];
            $cfs = [];
            $tps = [];
            foreach ($g['txns'] as $t) {
                $cfs[] = $t['cf'];
                $tps[] = $t['ts'];
            }
            if ($g['terminal_cf'] > 0.01 && $g['terminal_ts'] > 0) {
                $cfs[] = $g['terminal_cf'];
                $tps[] = $g['terminal_ts'];
            }
            if (count($cfs) >= 2) {
                $xirr = $this->xirrObj->XIRRAdpro($cfs, $tps, 0.1);
                $fundXirrs[$fn] = is_numeric($xirr) && is_finite($xirr) ? max(-0.9999, (float)$xirr) : 0.0;
            } else {
                $fundXirrs[$fn] = 0.0;
            }
        }

        // Step 3: process per-fund — collect buys/sells only (NO per-fund terminals).
        //
        // KEY INSIGHT: Adding 25 separate per-fund terminal values to portCF causes
        // NonPeriodic::XIRR to fail convergence (too many sign changes in a complex
        // cashflow series). It falls back to CAGR which gives the wrong answer (5.07%).
        //
        // CORRECT APPROACH: collect ALL buys and intermediate sells, then append
        // ONE SINGLE combined terminal at the end:
        //   portCF terminal = totalCurrentValue (actual portfolio value today)
        //   bmCF terminal   = sum(all fund hypoUnits) × todayNiftyPrice
        //
        // This gives XIRR a clean [buys, sells, +single_terminal] structure
        // that converges correctly to the expected ~10.8%.
        $portCF  = []; $portTP  = [];   // buys + intermediate sells only
        $bmCF    = []; $bmTP    = [];   // mirror buys + mirror sells only
        $firstTs = PHP_INT_MAX;
        $lastTs  = 0;
        $hypoUnitsPerFund = [];
        $pastCfsPerFund = [];

        foreach ($fundOrder as $gk) {
            $g  = $fundGroups[$gk];
            $fn = $g['fund_name'];
            if (!isset($hypoUnitsPerFund[$fn])) $hypoUnitsPerFund[$fn] = 0.0;

            $txns = $g['txns'];
            usort($txns, fn($a, $b) => $a['ts'] - $b['ts']);

            foreach ($txns as $t) {
                $ts    = $t['ts'];
                $cf    = $t['cf'];
                $date  = date('Y-m-d', $ts);
                $price = $this->getNiftyPrice($date, $index);

                if ($ts < $firstTs) $firstTs = $ts;
                if ($ts > $lastTs)  $lastTs  = $ts;

                $portCF[] = $cf;
                $portTP[] = $ts;

                if ($cf < 0 && $price > 0) {
                    // Buy: acquire mirror units
                    $hypoUnitsPerFund[$fn] += abs($cf) / $price;
                    $bmCF[] = $cf;
                    $bmTP[] = $ts;
                } elseif ($cf > 0.0001 && $price > 0 && $hypoUnitsPerFund[$fn] > 0.0001) {
                    // Intermediate sell (coupon / partial redemption): mirror proportionally using fund XIRR
                    $xirr = $fundXirrs[$fn] ?? 0.0;
                    $portValue = 0.0;
                    
                    if (!empty($pastCfsPerFund[$fn]['cfs'])) {
                        foreach ($pastCfsPerFund[$fn]['cfs'] as $i => $pastCf) {
                            $pastTs = $pastCfsPerFund[$fn]['tps'][$i];
                            $years = ($ts - $pastTs) / 365.0 / 86400.0;
                            $portValue += $pastCf * pow(1 + $xirr, $years);
                        }
                    }
                    $portValue = -$portValue;
                    
                    $pct = $portValue > 0 ? min($cf / $portValue, 1.0) : 1.0;
                    
                    $sold      = $hypoUnitsPerFund[$fn] * $pct;
                    $hypoUnitsPerFund[$fn] -= $sold;
                    if ($hypoUnitsPerFund[$fn] < 0.0001) $hypoUnitsPerFund[$fn] = 0.0;
                    $bmCF[] = round($sold * $price, 2);
                    $bmTP[] = $ts;
                }
                
                $pastCfsPerFund[$fn]['cfs'][] = $cf;
                $pastCfsPerFund[$fn]['tps'][] = $ts;
            }

            // Track terminal date for $lastTs — but do NOT add per-fund terminals to portCF/bmCF
            if ($g['terminal_ts'] > 0) {
                if ($g['terminal_ts'] > $lastTs) $lastTs = $g['terminal_ts'];
                if ($g['terminal_ts'] < $firstTs) $firstTs = $g['terminal_ts'];
            }
        }

        if (empty($portCF) || $firstTs === PHP_INT_MAX) return $this->emptyResult();

        // Pre-scan: find each fund's last terminal date (needed for isTrueFinal check below)
        $lastTerminalDate = [];
        foreach ($fundGroups as $g) {
            if ($g['terminal_ts'] > 0) {
                $fn = $g['fund_name'];
                $d  = date('Y-m-d', $g['terminal_ts']);
                if (!isset($lastTerminalDate[$fn]) || $d > $lastTerminalDate[$fn]) {
                    $lastTerminalDate[$fn] = $d;
                }
            }
        }

        // Add terminals per fund at each fund's actual terminal timestamp.
        // Using a single combined terminal at time() caused 15.10% vs 15.34% discrepancy
        // because bonds that matured earlier got their terminal dated to today instead
        // of their actual maturity date. Per-fund terminals match xirr_helpers.php logic.
        $todayPrice = $this->getNiftyPrice($today, $index);
        $totalPortVal = 0.0;
        $totalBmVal = 0.0;

        foreach ($fundOrder as $gk) {
            $g  = $fundGroups[$gk];
            $fn = $g['fund_name'];
            if ($g['terminal_cf'] <= 0.01 || $g['terminal_ts'] <= 0) continue;

            $ts    = $g['terminal_ts'];
            $date  = date('Y-m-d', $ts);
            $price = $this->getNiftyPrice($date, $index);

            // Portfolio: add this fund's terminal at its actual terminal date
            $portCF[] = $g['terminal_cf'];
            $portTP[] = $ts;
            $totalPortVal += $g['terminal_cf'];

            // Nifty mirror: check if this is the fund's last terminal (two-pass)
            $isTrueFinal = ($lastTerminalDate[$fn] ?? '') === $date;
            if ($isTrueFinal && $price > 0 && ($hypoUnitsPerFund[$fn] ?? 0) > 0.0001) {
                $bmTerm = round($hypoUnitsPerFund[$fn] * $price, 2);
                $bmCF[] = $bmTerm;
                $bmTP[] = $ts;
                $totalBmVal += $bmTerm;
                $hypoUnitsPerFund[$fn] = 0.0;
            }
        }

        if (count($portCF) < 2 || count($bmCF) < 2) return $this->emptyResult();

        // Step 4: compute XIRRs
        // With the single-terminal structure, NonPeriodic::XIRR converges correctly
        $portfolioXirr = 0.0;
        $benchmarkXirr = 0.0;

        if (count($portCF) >= 2) {
            $raw = $this->xirrObj->XIRRAdpro($portCF, $portTP, 0.1);
            $portfolioXirr = (is_numeric($raw) && is_finite((float)$raw))
                ? round((float)$raw * 100, 2) : 0.0;
        }
        if (count($bmCF) >= 2) {
            $raw = $this->xirrObj->XIRRAdpro($bmCF, $bmTP, 0.1);
            $benchmarkXirr = (is_numeric($raw) && is_finite((float)$raw))
                ? round((float)$raw * 100, 2) : 0.0;
        }

        // Debug log to help verify convergence
        $totalHypoUnits = array_sum($hypoUnitsPerFund);
        error_log(sprintf(
            'LitmFin XIRR: portCF=%d entries, portXirr=%.2f%%, bmCF=%d entries, bmXirr=%.2f%%, hypoUnits=%.4f',
            count($portCF), $portfolioXirr, count($bmCF), $benchmarkXirr, $totalHypoUnits
        ));

        // Step 5: monthly growth series for charts
        $monthly = $this->buildMonthlyGrowthSeries($portCF, $portTP, $index, $today);

        return [
            'portfolio_xirr'    => $portfolioXirr,
            'benchmark_xirr'    => $benchmarkXirr,
            'first_date'        => date('Y-m-d', $firstTs),
            'last_date'         => date('Y-m-d', $lastTs),
            'holding_days'      => (int)(($lastTs - $firstTs) / 86400),
            'cashflow_count'    => count($portCF) - 1,
            'monthly_labels'    => $monthly['labels'],
            'monthly_portfolio' => $monthly['portfolioValues'],
            'monthly_nifty'     => $monthly['niftyValues'],
            'growth_portfolio'  => $monthly['growthPortfolio'],
            'growth_nifty'      => $monthly['growthNifty'],
            'portfolio_value'   => $totalPortVal,
            'benchmark_value'   => $totalBmVal,
        ];
    }

    /**
     * Rolling XIRR from registry for a fixed time window.
     *
     * Filters cashflows to those within the window, uses actual portfolio
     * current value as terminal, and builds the Nifty mirror from accumulated
     * units still held at end-of-window. Both portfolio XIRR and Nifty mirror
     * XIRR are returned (answers Issue 2 — yes, Nifty mirror is always computed).
     */
    public function computeRollingXirrFromRegistry(
        array  $fundCashFlowRegistry,
        float  $currentValue,
        int    $years,
        string $index = 'nifty500',
        string $statusFilter = 'ALL'
    ): array {
        if (empty($fundCashFlowRegistry)) {
            return ['portfolio_xirr' => 0.0, 'benchmark_xirr' => 0.0, 'years' => $years];
        }

        $windowStart = strtotime("-{$years} years");
        $nowTs       = time();
        $today       = date('Y-m-d');

        // Filter the registry by status
        $filteredRegistry = [];
        foreach ($fundCashFlowRegistry as $entry) {
            if ($statusFilter !== 'ALL') {
                $status = $entry['status'] ?? 'HELD';
                if (strcasecmp($status, $statusFilter) !== 0) {
                    continue;
                }
            }
            $filteredRegistry[] = $entry;
        }

        if (empty($filteredRegistry)) {
            return ['portfolio_xirr' => 0.0, 'benchmark_xirr' => 0.0, 'years' => $years];
        }

        if (!function_exists('buildXirrRows')) {
            require_once __DIR__ . '/xirr_helpers.php';
        }

        $rows = buildXirrRows($filteredRegistry, $this->pdo, $windowStart);

        $portCF = []; $portTP = [];
        $bmCF   = []; $bmTP   = [];

        foreach ($rows as $r) {
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

        if (count($portCF) < 2) {
            return ['portfolio_xirr' => 0.0, 'benchmark_xirr' => 0.0, 'years' => $years];
        }

        $portXirr = 0.0;
        $bmXirr   = 0.0;

        if (count($portCF) >= 2) {
            $raw = $this->xirrObj->XIRRAdpro($portCF, $portTP, 0.1);
            $portXirr = (is_numeric($raw) && is_finite((float)$raw))
                ? round((float)$raw * 100, 2) : 0.0;
        }
        if (count($bmCF) >= 2) {
            $raw = $this->xirrObj->XIRRAdpro($bmCF, $bmTP, 0.1);
            $bmXirr = (is_numeric($raw) && is_finite((float)$raw))
                ? round((float)$raw * 100, 2) : 0.0;
        }

        return [
            'portfolio_xirr' => $portXirr,
            'benchmark_xirr' => $bmXirr,
            'years'          => $years,
            'first_date'     => date('Y-m-d', $windowStart),
            'last_date'      => $today,
            'holding_days'   => $years * 365,
        ];
    }


    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 3 — CHART BUILDERS (QuickChart.io PNGs)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Chart 1: XIRR comparison bar chart
     * Portfolio XIRR vs Nifty 500 XIRR — since inception + rolling 3yr + 5yr
     */
    /**
     * Vertical grouped bar chart: Portfolio vs Nifty 500 Mirror for all three periods.
     * Styled like the sample report — navy bars, orange bars, values on top, no gridlines.
     */
    public function buildXirrComparisonChart(
        array  $sinceInception,
        array  $rolling3yr,
        array  $rolling5yr,
        string $clientName = ''
    ): ?string {

        $labels = [];
        $tickColors = [];
        $portfolioData = [];
        $benchmarkData = [];

        // Since Inception is always available
        $alphaInception = ($sinceInception['portfolio_xirr'] ?? 0) - ($sinceInception['benchmark_xirr'] ?? 0);
        $labels[] = ['Since Inception', '(α ' . ($alphaInception >= 0 ? '+' : '') . number_format($alphaInception, 2) . '%)'];
        $tickColors[] = $alphaInception >= 0 ? '#0D7C5F' : '#C0392B';
        $portfolioData[] = round($sinceInception['portfolio_xirr'] ?? 0, 2);
        $benchmarkData[] = round($sinceInception['benchmark_xirr'] ?? 0, 2);

        if (!empty($rolling3yr)) {
            $alpha3yr = ($rolling3yr['portfolio_xirr'] ?? 0) - ($rolling3yr['benchmark_xirr'] ?? 0);
            $labels[] = ['Last 3 Years', '(α ' . ($alpha3yr >= 0 ? '+' : '') . number_format($alpha3yr, 2) . '%)'];
            $tickColors[] = $alpha3yr >= 0 ? '#0D7C5F' : '#C0392B';
            $portfolioData[] = round($rolling3yr['portfolio_xirr'] ?? 0, 2);
            $benchmarkData[] = round($rolling3yr['benchmark_xirr'] ?? 0, 2);
        }

        if (!empty($rolling5yr)) {
            $alpha5yr = ($rolling5yr['portfolio_xirr'] ?? 0) - ($rolling5yr['benchmark_xirr'] ?? 0);
            $labels[] = ['Last 5 Years', '(α ' . ($alpha5yr >= 0 ? '+' : '') . number_format($alpha5yr, 2) . '%)'];
            $tickColors[] = $alpha5yr >= 0 ? '#0D7C5F' : '#C0392B';
            $portfolioData[] = round($rolling5yr['portfolio_xirr'] ?? 0, 2);
            $benchmarkData[] = round($rolling5yr['benchmark_xirr'] ?? 0, 2);
        }

        // suggestMax so values written on top of bars don't clip
        $allVals    = array_merge($portfolioData, $benchmarkData);
        $maxVal     = max(array_map('abs', $allVals));
        $minVal     = empty($allVals) ? 0 : min($allVals);
        $suggestMax = round($maxVal * 1.30 + 1, 0);
        $suggestMin = round($minVal * 1.40 - 2, 0);
        if ($suggestMin > 0) $suggestMin = 0;

        $config = [
            'type' => 'bar',
            'data' => [
                'labels'   => $labels,
                'datasets' => [
                    [
                        'label'           => 'Portfolio',
                        'data'            => $portfolioData,
                        'backgroundColor' => '#3A639B',   // lighter navy
                        'borderRadius'    => 4,
                        'borderSkipped'   => false,
                        'barThickness'    => 32,
                    ],
                    [
                        'label'           => 'Nifty 500 Mirror',
                        'data'            => $benchmarkData,
                        'backgroundColor' => '#F09045',   // lighter orange
                        'borderRadius'    => 4,
                        'borderSkipped'   => false,
                        'barThickness'    => 32,
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'annotation' => [
                        'annotations' => [
                            'zeroLine' => [
                                'type'        => 'line',
                                'yMin'        => 0,
                                'yMax'        => 0,
                                'borderColor' => '#333',
                                'borderWidth' => 2
                            ]
                        ]
                    ],
                    'title'  => ['display' => false],
                    'legend' => [
                        'display'  => true,
                        'position' => 'bottom',
                        'labels'   => [
                            'pointStyle'    => 'rectRounded',
                            'usePointStyle' => true,
                            'font'          => ['size' => 10, 'weight' => 'bold'],
                            'padding'       => 14,
                            'boxWidth'      => 12,
                            'color'         => '#222',
                        ],
                    ],
                    'datalabels' => [
                        'display'   => true,
                        'anchor'    => 'end',
                        'align'     => 'top',
                        'color'     => '#222',
                        'font'      => ['size' => 9, 'weight' => 'bold'],
                        'formatter' => "function(v){return v.toFixed(2)+'%'}",
                        'padding'   => ['bottom' => 3],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'display'    => true,          // must be visible for border/zero line to show
                        'suggestMin' => $suggestMin,
                        'suggestMax' => $suggestMax,
                        'grid'       => ['display' => false],
                        'border'     => ['display' => false],
                        'ticks'      => [
                            'display'  => true,
                            'callback' => "function(v){return v+'%'}",
                            'font'     => ['size' => 10],
                            'color'    => '#666',
                        ],
                    ],
                    'x' => [
                        'grid'   => ['display' => false],
                        'border' => ['color' => '#333', 'width' => 2],
                        'ticks'  => ['font' => ['size' => 11, 'weight' => 'bold'], 'color' => $tickColors],
                    ],
                ],
                'layout' => ['padding' => ['top' => 24, 'right' => 10, 'bottom' => 10, 'left' => 35]],
            ],
        ];

        $cacheKey = 'xirr_comparison_v20_' . md5($clientName . json_encode($portfolioData));
        return $this->fetchChartPng($config, 560, 260, $cacheKey);
    }

    /**
     * Chart 1.5: Held vs All XIRR comparison bar chart
     * Portfolio XIRR of currently Held assets vs All Assets (Including Sold).
     */
    public function buildHeldVsAllChart(
        array  $sinceInceptionHeld,
        array  $rolling3yrHeld,
        array  $rolling5yrHeld,
        array  $sinceInceptionAll,
        array  $rolling3yrAll,
        array  $rolling5yrAll,
        string $clientName = ''
    ): ?string {

        $labels   = ['Since Inception', 'Last 3 Years', 'Last 5 Years'];
        $heldData = [
            round($sinceInceptionHeld['portfolio_xirr'] ?? 0, 2),
            round($rolling3yrHeld['portfolio_xirr']     ?? 0, 2),
            round($rolling5yrHeld['portfolio_xirr']     ?? 0, 2),
        ];
        $allData  = [
            round($sinceInceptionAll['portfolio_xirr'] ?? 0, 2),
            round($rolling3yrAll['portfolio_xirr']     ?? 0, 2),
            round($rolling5yrAll['portfolio_xirr']     ?? 0, 2),
        ];

        // suggestMax so values written on top of bars don't clip
        $allVals    = array_merge($heldData, $allData);
        $maxVal     = max(array_map('abs', $allVals));
        $minVal     = empty($allVals) ? 0 : min($allVals);
        $suggestMax = round($maxVal * 1.30 + 1, 0);
        $suggestMin = round($minVal * 1.30 - 2, 0);
        if ($suggestMin > 0) $suggestMin = 0;

        $config = [
            'type' => 'bar',
            'data' => [
                'labels'   => $labels,
                'datasets' => [
                    [
                        'label'           => 'Currently Held',
                        'data'            => $heldData,
                        'backgroundColor' => '#1E3A5F',   // dark navy
                        'borderRadius'    => 4,
                        'borderSkipped'   => false,
                        'barThickness'    => 32,
                    ],
                    [
                        'label'           => 'All Assets (Incl. Sold)',
                        'data'            => $allData,
                        'backgroundColor' => '#0D7C5F',   // teal
                        'borderRadius'    => 4,
                        'borderSkipped'   => false,
                        'barThickness'    => 32,
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'annotation' => [
                        'annotations' => [
                            'zeroLine' => [
                                'type'        => 'line',
                                'yMin'        => 0,
                                'yMax'        => 0,
                                'borderColor' => '#333',
                                'borderWidth' => 2
                            ]
                        ]
                    ],
                    'title'  => ['display' => false],
                    'legend' => [
                        'display'  => true,
                        'position' => 'bottom',
                        'labels'   => [
                            'pointStyle'    => 'rectRounded',
                            'usePointStyle' => true,
                            'font'          => ['size' => 10, 'weight' => 'bold'],
                            'padding'       => 14,
                            'boxWidth'      => 12,
                            'color'         => '#222',
                        ],
                    ],
                    'datalabels' => [
                        'display'   => true,
                        'anchor'    => 'end',
                        'align'     => 'top',
                        'color'     => '#222',
                        'font'      => ['size' => 9, 'weight' => 'bold'],
                        'formatter' => "function(v){return v.toFixed(2)+'%'}",
                        'padding'   => ['bottom' => 3],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'display'    => true,
                        'suggestMin' => $suggestMin,
                        'suggestMax' => $suggestMax,
                        'grid'       => ['display' => false],
                        'border'     => ['display' => false],
                        'ticks'      => ['display' => false],
                    ],
                    'x' => [
                        'grid'  => ['display' => false],
                        'ticks' => ['font' => ['size' => 11, 'weight' => 'bold'], 'color' => '#222'],
                    ],
                ],
                'layout' => ['padding' => ['top' => 24, 'right' => 10, 'bottom' => 4, 'left' => 10]],
            ],
        ];

        $cacheKey = 'held_vs_all_v20_' . md5($clientName . json_encode($heldData) . json_encode($allData));
        return $this->fetchChartPng($config, 560, 260, $cacheKey);
    }

    /**
     * Chart 2: ₹1 Lakh growth — portfolio vs Nifty 500 (since inception)
     * Line chart showing how ₹1L invested on client's first investment date
     * would have grown in portfolio vs Nifty 500.
     */
    public function buildGrowthChart(array $benchmarkResult, string $clientName = ''): ?string
    {
        $labels    = $benchmarkResult['monthly_labels']    ?? [];
        $portfolio = $benchmarkResult['growth_portfolio']  ?? [];
        $nifty     = $benchmarkResult['growth_nifty']      ?? [];

        if (empty($labels)) return null;

        // Final values for annotation on the chart
        $pFinal = !empty($portfolio) ? end($portfolio) : 0;
        $nFinal = !empty($nifty)     ? end($nifty)     : 0;

        $config = [
            'type' => 'line',
            'data' => [
                'labels'   => $labels,
                'datasets' => [
                    [
                        'label'           => 'Portfolio',
                        'data'            => $portfolio,
                        'borderColor'     => '#1E3A5F',
                        'backgroundColor' => 'rgba(30,58,95,0.07)',
                        'fill'            => true,
                        'tension'         => 0.3,
                        'pointRadius'     => 0,
                        'borderWidth'     => 2.5,
                    ],
                    [
                        'label'           => 'Nifty 500 Mirror',
                        'data'            => $nifty,
                        'borderColor'     => '#E87722',
                        'backgroundColor' => 'rgba(232,119,34,0.05)',
                        'fill'            => true,
                        'tension'         => 0.3,
                        'pointRadius'     => 0,
                        'borderWidth'     => 2,
                        'borderDash'      => [6, 3],
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => false,
                        'text'    => 'Growth of ₹1,00,000 since inception',
                        'font'    => ['size' => 11, 'weight' => 'bold'],
                        'color'   => '#1E3A5F',
                        'padding' => ['bottom' => 6],
                    ],
                    'legend' => [
                        'display'  => true,
                        'position' => 'bottom',
                        'labels'   => [
                            'pointStyle' => 'line',         // show as line, not rectangle
                            'usePointStyle' => true,
                            'font'       => ['size' => 10],
                            'padding'    => 10,
                            'color'      => '#222',
                        ],
                    ],
                    'datalabels' => ['display' => false],
                ],
                'scales' => [
                    'y' => [
                        'display' => true,
                        'ticks' => [
                            'display'       => true,
                            'font'          => ['size' => 10],
                            'maxTicksLimit' => 10,
                            'color'         => '#000',
                        ],
                        'grid' => ['display' => false],
                    ],
                    'x' => [
                        'ticks' => ['maxTicksLimit' => 10, 'font' => ['size' => 8], 'color' => '#666'],
                        'grid'  => ['display' => false],
                    ],
                ],
                'layout' => ['padding' => ['top' => 4, 'right' => 20, 'bottom' => 0, 'left' => 75]],
            ],
        ];

        $cacheKey = 'growth_chart_v8_' . md5($clientName . implode(',', array_slice($nifty, -3)));
        return $this->fetchChartPng($config, 580, 280, $cacheKey);
    }

    /**
     * Chart 3: Rolling XIRR grouped bar — 3yr and 5yr side by side
     * with portfolio vs benchmark for each window
     */
    public function buildRollingXirrChart(
        array  $rolling3yr,
        array  $rolling5yr,
        string $clientName = ''
    ): ?string {

        $config = [
            'type' => 'bar',
            'data' => [
                'labels'   => ['Portfolio', 'Nifty 500 Mirror'],
                'datasets' => [
                    [
                        'label'           => '3-year XIRR',
                        'data'            => [$rolling3yr['portfolio_xirr'], $rolling3yr['benchmark_xirr']],
                        'backgroundColor' => '#1D9E75',
                        'borderRadius'    => 5,
                        'barThickness'    => 32,
                    ],
                    [
                        'label'           => '5-year XIRR',
                        'data'            => [$rolling5yr['portfolio_xirr'], $rolling5yr['benchmark_xirr']],
                        'backgroundColor' => '#378ADD',
                        'borderRadius'    => 5,
                        'barThickness'    => 32,
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'annotation' => [
                        'annotations' => [
                            'zeroLine' => [
                                'type'        => 'line',
                                'yMin'        => 0,
                                'yMax'        => 0,
                                'borderColor' => '#333',
                                'borderWidth' => 2
                            ]
                        ]
                    ],
                    'title' => [
                        'display' => true,
                        'text'    => 'Rolling XIRR vs Nifty 500 Mirror — 3yr vs 5yr (%)',
                        'font'    => ['size' => 14],
                    ],
                    'legend' => ['position' => 'top'],
                ],
                'scales' => [
                    'y' => [
                        'grid'       => ['display' => false],
                        'border'     => ['display' => false],
                        'ticks' => ['callback' => "function(v){return v+'%'}"],
                    ],
                    'x' => ['grid' => ['display' => false]],
                ],
            ],
        ];

        $cacheKey = 'rolling_xirr_v20_' . md5($clientName . $rolling3yr['portfolio_xirr'] . $rolling5yr['portfolio_xirr']);
        return $this->fetchChartPng($config, 440, 250, $cacheKey);
    }
    /**
     * Single-period XIRR bar: Portfolio vs Nifty 500 Mirror for ONE time window.
     * Used in section_charts.php to show Since Inception / 3yr / 5yr as separate blocks.
     *
     * @param  array  $result     From computeBenchmark() or computeRollingXirr()
     * @param  string $periodLabel e.g. 'Since Inception', 'Last 3 Years', 'Last 5 Years'
     * @param  string $clientName
     * @return ?string  PNG data URI or null
     */
    public function buildSinglePeriodChart(
        array  $result,
        string $periodLabel,
        string $clientName = '',
        int    $width = 380,
        int    $height = 120
    ): ?string {
        $pXirr = $result['portfolio_xirr'] ?? 0;
        $bXirr = $result['benchmark_xirr'] ?? 0;

        // Teal when portfolio leads, orange when it trails
        $pColor = '#1E3A5F';   // navy — portfolio
        $bColor = '#E87722';   // orange — Nifty 500 Mirror

        // suggestMax = 130% of the larger value, minimum 5.
        // This ensures bars never span full width so datalabels always fit outside.
        $maxVal    = max(abs($pXirr), abs($bXirr), 1);
        $suggestMax = round($maxVal * 1.45 + 1, 1);
        
        $minVal    = min($pXirr, $bXirr);
        $suggestMin = round($minVal * 1.45 - 1, 1);
        if ($suggestMin > 0) $suggestMin = 0;

        $config = [
            'type' => 'bar',
            'data' => [
                'labels'   => ['Portfolio', 'Nifty 500 Mirror'],
                'datasets' => [[
                    'data'            => [$pXirr, $bXirr],
                    'backgroundColor' => [$pColor, $bColor],
                    'borderWidth'     => 0,
                    'borderRadius'    => 4,
                    'borderSkipped'   => false,
                    'minBarLength'    => 6,
                    'barThickness'    => 32,
                ]],
            ],
            'options' => [
                // Vertical bars — two adjacent bars, clean labels on top
                'plugins' => [
                    'annotation' => [
                        'annotations' => [
                            'zeroLine' => [
                                'type'        => 'line',
                                'yMin'        => 0,
                                'yMax'        => 0,
                                'borderColor' => '#333',
                                'borderWidth' => 2
                            ]
                        ]
                    ],
                    'title'  => ['display' => false],
                    'legend' => ['display' => false],
                    'datalabels' => [
                        'display'   => true,
                        'anchor'    => 'end',
                        'align'     => 'top',
                        'color'     => '#111',
                        'font'      => ['size' => 11, 'weight' => 'bold'],
                        'formatter' => "function(v){return v.toFixed(2)+'%'}",
                    ],
                ],
                'scales' => [
                    'x' => [
                        'grid'   => ['display' => false],
                        'ticks'  => [
                            'font'  => ['size' => 10, 'weight' => 'bold'],
                            'color' => '#222',
                        ],
                        'border' => ['color' => '#333', 'width' => 2],
                    ],
                    'y' => [
                        'display'    => true,           // visible so zero line border shows
                        'suggestMin' => $suggestMin,
                        'suggestMax' => $suggestMax,
                        'grid'       => ['display' => false],
                        'border'     => ['display' => false],
                        'ticks'      => ['display' => false],
                    ],
                ],
                'layout' => ['padding' => ['top' => 24, 'right' => 10, 'bottom' => 0, 'left' => 0]],
            ],
        ];

        $cacheKey = 'single_period_v20_' . md5($periodLabel . $clientName . $pXirr . $bXirr);
        return $this->fetchChartPng($config, $width, $height, $cacheKey);
    }

    /**
     * Chart 3: Asset Class XIRR returns bar chart
     */
    public function buildAssetClassXirrChart(array $totalAssetInfo, string $clientName = ''): ?string
    {
        $info = $totalAssetInfo[0] ?? [];
        $equity = (float)($info['Total_Equity_Return'] ?? 0);
        $debt   = (float)($info['Total_Debt_Return'] ?? 0);
        $hybrid = (float)($info['Total_Hybrid_Return'] ?? 0);
        $gold   = (float)($info['Total_Gold_Return'] ?? 0);

        if ($equity == 0 && $debt == 0 && $hybrid == 0 && $gold == 0) return null;

        $labels = [];
        $data   = [];
        $colors = [];

        if ($equity != 0) { $labels[] = 'Equity'; $data[] = round($equity, 2); $colors[] = '#1E3A5F'; }
        if ($debt != 0)   { $labels[] = 'Debt';   $data[] = round($debt, 2);   $colors[] = '#0D7C5F'; }
        if ($hybrid != 0) { $labels[] = 'Hybrid'; $data[] = round($hybrid, 2); $colors[] = '#E87722'; }
        if ($gold != 0)   { $labels[] = 'Gold';   $data[] = round($gold, 2);   $colors[] = '#F1C40F'; }

        $maxVal     = max(array_map('abs', $data));
        $suggestMax = round($maxVal * 1.35 + 2, 0);
        $minVal     = empty($data) ? 0 : min($data);
        $suggestMin = round($minVal * 1.30 - 1, 0);
        if ($suggestMin > 0) $suggestMin = 0;

        $config = [
            'type' => 'bar',
            'data' => [
                'labels'   => $labels,
                'datasets' => [
                    [
                        'data'            => $data,
                        'backgroundColor' => $colors,
                        'borderRadius'    => 4,
                        'borderSkipped'   => false,
                        'barThickness'    => 48,
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'annotation' => [
                        'annotations' => [
                            'zeroLine' => [
                                'type'        => 'line',
                                'yMin'        => 0,
                                'yMax'        => 0,
                                'borderColor' => '#333',
                                'borderWidth' => 2
                            ]
                        ]
                    ],
                    'title'  => ['display' => false],
                    'legend' => ['display' => false],
                    'datalabels' => [
                        'display'   => true,
                        'anchor'    => 'end',
                        'align'     => 'top',
                        'color'     => '#222',
                        'font'      => ['size' => 15, 'weight' => 'bold'],
                        'formatter' => "function(v){return v.toFixed(2)+'%'}",
                        'padding'   => ['bottom' => 3],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'display'    => true,
                        'suggestMin' => $suggestMin,
                        'suggestMax' => $suggestMax,
                        'grid'       => ['display' => false],
                        'border'     => ['display' => false],
                        'ticks'      => [
                            'display'  => true,
                            'callback' => "function(v){return v+'%'}",
                            'font'     => ['size' => 11],
                            'color'    => '#666',
                        ],
                    ],
                    'x' => [
                        'grid'   => ['display' => false],
                        'border' => ['color' => '#333', 'width' => 2],
                        'ticks'  => ['font' => ['size' => 13, 'weight' => 'bold'], 'color' => '#222'],
                    ],
                ],
                'layout' => ['padding' => ['top' => 24, 'right' => 10, 'bottom' => 10, 'left' => 10]],
            ],
        ];

        $cacheKey = 'asset_class_xirr_v20_' . md5($clientName . json_encode($data));
        return $this->fetchChartPng($config, 560, 260, $cacheKey);
    }

    /**
     * Chart 4: Current Value Comparison Bar
     */
    public function buildValueComparisonChart(
        float  $portValue,
        float  $bmValue,
        string $label,
        string $clientName = '',
        int    $width = 380,
        int    $height = 120
    ): ?string {
        $pColor = '#1E3A5F';
        $bColor = '#E87722';

        $maxVal     = max(abs($portValue), abs($bmValue), 1);
        $suggestMax = round($maxVal * 1.35, 0);

        $config = [
            'type' => 'bar',
            'data' => [
                'labels'   => ['Portfolio', 'Nifty 500 Mirror'],
                'datasets' => [[
                    'data'            => [round($portValue, 0), round($bmValue, 0)],
                    'backgroundColor' => [$pColor, $bColor],
                    'borderWidth'     => 0,
                    'borderRadius'    => 4,
                    'borderSkipped'   => false,
                    'minBarLength'    => 6,
                    'barThickness'    => 50,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title'  => ['display' => false],
                    'legend' => ['display' => false],
                    'datalabels' => [
                        'display'   => true,
                        'anchor'    => 'end',
                        'align'     => 'top',
                        'color'     => '#111',
                        'font'      => ['size' => 11, 'weight' => 'bold'],
                        'formatter' => "function(v){if(v==0)return '';var isNeg=v<0;var x=Math.abs(Math.round(v)).toString();var l=x.substring(x.length-3);var o=x.substring(0,x.length-3);if(o!=''){l=','+l;var r='';var c=0;for(var i=o.length-1;i>=0;i--){r=o.charAt(i)+r;c++;if(c%2==0&&i!=0)r=','+r;}var res=r+l;}else{var res=l;}return (isNeg?'-':'')+'₹'+res;}",
                    ],
                ],
                'scales' => [
                    'x' => ['grid' => ['display' => false], 'ticks' => ['font' => ['size' => 10, 'weight' => 'bold'], 'color' => '#222'], 'border' => ['color' => '#333', 'width' => 2]],
                    'y' => ['display' => true, 'suggestMax' => $suggestMax, 'grid' => ['display' => false], 'border' => ['color' => '#333', 'width' => 2], 'ticks' => ['display' => false]],
                ],
                'layout' => ['padding' => ['top' => 24, 'right' => 10, 'bottom' => 0, 'left' => 0]],
            ],
        ];

        $cacheKey = 'val_comp_v20_' . md5($label . $clientName . $portValue . $bmValue);
        return $this->fetchChartPng($config, $width, $height, $cacheKey);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 3B — MARKET CAP ALLOCATION HISTORY CHART
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * buildMcapAllocationChart()
     * Stacked bar: Large / Mid / Small Cap / Other % per month.
     * Pure stacked bar — no dual-axis, no mixed types — guaranteed to render.
     */
    public function buildSwpFlowChart(
        float  $costValue,
        float  $withdrawnValue,
        float  $currentValue,
        string $clientName = '',
        int    $width = 380,
        int    $height = 120
    ): ?string {
        $maxVal = max(abs($costValue), abs($withdrawnValue), abs($currentValue), 1);
        $suggestMax = round($maxVal * 1.35, 0);

        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => ['Cost Value', 'Withdrawn', 'Current Value'],
                'datasets' => [[
                    'data' => [round($costValue, 0), round($withdrawnValue, 0), round($currentValue, 0)],
                    'backgroundColor' => ['#0D7C5F', '#E87722', '#1E3A5F'],
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                    'minBarLength' => 6,
                    'barThickness' => 40,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title' => ['display' => false],
                    'legend' => ['display' => false],
                    'datalabels' => [
                        'display' => true,
                        'anchor' => 'end',
                        'align' => 'top',
                        'color' => '#111',
                        'font' => ['size' => 11, 'weight' => 'bold'],
                        'formatter' => "function(v){if(v==0)return '';var isNeg=v<0;var x=Math.abs(Math.round(v)).toString();var l=x.substring(x.length-3);var o=x.substring(0,x.length-3);if(o!=''){l=','+l;var r='';var c=0;for(var i=o.length-1;i>=0;i--){r=o.charAt(i)+r;c++;if(c%2==0&&i!=0)r=','+r;}var res=r+l;}else{var res=l;}return (isNeg?'-':'')+'₹'+res;}",
                    ],
                ],
                'scales' => [
                    'x' => ['grid' => ['display' => false], 'ticks' => ['font' => ['size' => 10, 'weight' => 'bold'], 'color' => '#222'], 'border' => ['color' => '#333', 'width' => 2]],
                    'y' => ['display' => true, 'suggestMax' => $suggestMax, 'grid' => ['display' => false], 'border' => ['color' => '#333', 'width' => 2], 'ticks' => ['display' => false]],
                ],
                'layout' => ['padding' => ['top' => 24, 'right' => 10, 'bottom' => 0, 'left' => 0]],
            ],
        ];

        $cacheKey = 'swp_flow_v3_' . md5($clientName . $costValue . $withdrawnValue . $currentValue);
        return $this->fetchChartPng($config, $width, $height, $cacheKey);
    }

    public function buildMcapAllocationChart(array $mcapHistory, string $clientName = ''): ?string
    {
        $labels  = $mcapHistory['labels']            ?? [];
        $large   = $mcapHistory['large_cap_pct']     ?? [];
        $mid     = $mcapHistory['mid_cap_pct']       ?? [];
        $small   = $mcapHistory['small_cap_pct']     ?? [];
        $remaining = array_map(fn($l, $m, $s) => max(0, 100 - ($l + $m + $s)), $large, $mid, $small);
        $n500raw = $mcapHistory['nifty500_raw']      ?? $mcapHistory['nifty500'] ?? [];
        $months  = count($labels);
        if ($months < 2) return null;

        // Nifty 500 actual monthly values (e.g. 8500, 12000, 24000)
        $n500 = array_map(fn($v) => $v === null ? null : (float)$v, $n500raw);

        // The chart uses TWO y-axes both declared as independent scales:
        // LEFT  (id='y')      : stacked bars 0–100% (mcap allocation)
        // RIGHT (id='yNifty') : Nifty 500 actual value (e.g. 5000–25000)
        //
        // KEY LESSON: In Chart.js v3 stacked bar + line combo, the line
        // dataset MUST have 'type':'line' explicitly, AND its yAxisID must
        // be a scale that is NOT stacked. The right-axis scale must NOT have
        // 'stacked':true.  This is the correct v3 combo syntax.
        
        $n500Vals = array_filter($n500, fn($v) => $v !== null && $v > 0);
        $minNifty = empty($n500Vals) ? 0 : min($n500Vals);
        $maxNifty = empty($n500Vals) ? 100 : max($n500Vals);
        
        $range    = $maxNifty - $minNifty;
        if ($range == 0) $range = max($minNifty * 0.1, 100);
        $minY     = max(0, floor(($minNifty - $range * 0.15) / 100) * 100);
        $maxY     = ceil(($maxNifty + $range * 0.15) / 100) * 100;

        $config = [
            'type' => 'bar',
            'data' => [
                'labels'   => $labels,
                'datasets' => [
                    [
                        'type'            => 'bar',
                        'label'           => 'Large Cap',
                        'data'            => $large,
                        'backgroundColor' => 'rgba(30,58,95,0.6)',
                        'stack'           => 'mcap',
                        'yAxisID'         => 'y',
                        'borderWidth'     => 0,
                        'barPercentage'   => 1.0,
                        'categoryPercentage' => 1.0,
                    ],
                    [
                        'type'            => 'bar',
                        'label'           => 'Mid Cap',
                        'data'            => $mid,
                        'backgroundColor' => 'rgba(232,119,34,0.6)',
                        'stack'           => 'mcap',
                        'yAxisID'         => 'y',
                        'borderWidth'     => 0,
                        'order'           => 2,
                        'barPercentage'   => 1.0,
                        'categoryPercentage' => 1.0,
                    ],
                    [
                        'type'            => 'bar',
                        'label'           => 'Small Cap',
                        'data'            => $small,
                        'backgroundColor' => 'rgba(13,124,95,0.6)',
                        'stack'           => 'mcap',
                        'yAxisID'         => 'y',
                        'borderWidth'     => 0,
                        'order'           => 2,
                        'barPercentage'   => 1.0,
                        'categoryPercentage' => 1.0,
                    ],
                    [
                        'type'            => 'bar',
                        'label'           => 'Cash/Debt',
                        'data'            => $remaining,
                        'backgroundColor' => 'rgba(200,200,200,0.5)',
                        'stack'           => 'mcap',
                        'yAxisID'         => 'y',
                        'borderWidth'     => 0,
                        'order'           => 3,
                        'barPercentage'   => 1.0,
                        'categoryPercentage' => 1.0,
                    ],
                    [
                        'type'            => 'line',
                        'label'           => 'Nifty 500',
                        'data'            => $n500,
                        'borderColor'     => '#C0392B',
                        'backgroundColor' => 'transparent',
                        'borderWidth'     => 1.0,
                        'pointRadius'     => 0,
                        'tension'         => 0.3,
                        'fill'            => false,
                        'yAxisID'         => 'yNifty',
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'title'  => ['display' => false],
                    'legend' => ['display' => false],
                    'datalabels' => ['display' => false],
                ],
                'scales' => [
                    'x' => [
                        'stacked'            => true,
                        'grid'               => ['display' => false],
                        'ticks'              => [
                            'maxTicksLimit' => min($months, 18),
                            'maxRotation'   => 45,
                            'font'          => ['size' => 9],
                            'color'         => '#444',
                        ],
                    ],
                    // LEFT: stacked bar 0–100%
                    'y' => [
                        'type'     => 'linear',
                        'position' => 'left',
                        'display'  => true,
                        'min'      => 0,
                        'max'      => 100,
                        'ticks'    => [
                            'display'       => true,
                            'stepSize'      => 20,
                            'font'          => ['size' => 10],
                            'color'         => '#000',
                            'maxTicksLimit' => 10,
                        ],
                        'grid' => ['color' => 'rgba(0,0,0,0.04)'],
                    ],
                    // RIGHT: Nifty 500 actual values — NOT stacked
                    'yNifty' => [
                        'type'     => 'linear',
                        'position' => 'right',
                        'display'  => true,
                        'stacked'  => false,
                        'beginAtZero' => false,
                        'min'      => $minY,
                        'max'      => $maxY,
                        'grid'     => ['display' => false],
                        'ticks'    => [
                            'display'       => true,
                            'font'          => ['size' => 10],
                            'color'         => '#C0392B',
                            'maxTicksLimit' => 10,
                        ],
                    ],
                ],
                'layout' => ['padding' => ['top' => 8, 'right' => 50, 'bottom' => 6, 'left' => 55]],
            ],
        ];
        $cacheKey = 'mcap_alloc_v14_' . md5($clientName . $months . implode(',', $labels) . '|' . implode(',', array_slice($large, -3)) . '|' . implode(',', array_slice($mid, -3)) . '|' . implode(',', array_slice($small, -3)) . '|' . implode(',', array_slice($remaining, -3)));
        return $this->fetchChartPng($config, 650, 340, $cacheKey);
    }

    public function buildMcapSplitChartTop(array $mcapHistory, string $clientName = ''): ?string
    {
        $labels = $mcapHistory['labels']        ?? [];
        $large  = $mcapHistory['large_cap_pct'] ?? [];
        $mid    = $mcapHistory['mid_cap_pct']   ?? [];
        $small  = $mcapHistory['small_cap_pct'] ?? [];
        $remaining = array_map(fn($l, $m, $s) => max(0, 100 - ($l + $m + $s)), $large, $mid, $small);
        $months = count($labels);
        if ($months < 2) return null;

        $config = [
            'type' => 'bar',
            'data' => [
                'labels'   => $labels,
                'datasets' => [
                    [
                        'type'            => 'bar',
                        'label'           => 'Large Cap',
                        'data'            => $large,
                        'backgroundColor' => 'rgba(30,58,95,0.6)',
                        'stack'           => 'mcap',
                        'borderWidth'     => 0,
                        'barPercentage'   => 1.0,
                        'categoryPercentage' => 1.0,
                    ],
                    [
                        'type'            => 'bar',
                        'label'           => 'Mid Cap',
                        'data'            => $mid,
                        'backgroundColor' => 'rgba(232,119,34,0.6)',
                        'stack'           => 'mcap',
                        'borderWidth'     => 0,
                        'barPercentage'   => 1.0,
                        'categoryPercentage' => 1.0,
                    ],
                    [
                        'type'            => 'bar',
                        'label'           => 'Small Cap',
                        'data'            => $small,
                        'backgroundColor' => 'rgba(13,124,95,0.6)',
                        'stack'           => 'mcap',
                        'borderWidth'     => 0,
                        'barPercentage'   => 1.0,
                        'categoryPercentage' => 1.0,
                    ],
                    [
                        'type'            => 'bar',
                        'label'           => 'Cash/Debt',
                        'data'            => $remaining,
                        'backgroundColor' => 'rgba(200,200,200,0.5)',
                        'stack'           => 'mcap',
                        'borderWidth'     => 0,
                        'barPercentage'   => 1.0,
                        'categoryPercentage' => 1.0,
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'title'  => ['display' => false],
                    'legend' => [
                        'display'  => true,
                        'position' => 'top',
                        'labels'   => [
                            'usePointStyle' => true,
                            'pointStyle'    => 'circle',
                            'font'          => ['size' => 9],
                            'padding'       => 10,
                            'color'         => '#222',
                        ],
                    ],
                    'datalabels' => ['display' => false],
                ],
                'scales' => [
                    'x' => [
                        'stacked'            => true,
                        'grid'               => ['display' => false],
                        'ticks'              => ['display' => false],
                    ],
                    'y' => [
                        'type'     => 'linear',
                        'position' => 'left',
                        'display'  => true,
                        'min'      => 0,
                        'max'      => 100,
                        'ticks'    => [
                            'display'       => true,
                            'stepSize'      => 20,
                            'font'          => ['size' => 10],
                            'color'         => '#000',
                            'maxTicksLimit' => 10,
                        ],
                        'grid' => ['color' => 'rgba(0,0,0,0.04)'],
                    ],
                ],
                'layout' => ['padding' => ['top' => 6, 'right' => 50, 'bottom' => 0, 'left' => 55]],
            ],
        ];

        $cacheKey = 'mcap_split_top_v3_' . md5($clientName . $months . implode(',', $labels) . '|' . implode(',', array_slice($large, -3)) . '|' . implode(',', array_slice($mid, -3)) . '|' . implode(',', array_slice($small, -3)) . '|' . implode(',', array_slice($remaining, -3)));
        return $this->fetchChartPng($config, 650, 240, $cacheKey);
    }

    public function buildMcapSplitChartBottom(array $mcapHistory, string $clientName = ''): ?string
    {
        $labels  = $mcapHistory['labels']      ?? [];
        $n500raw = $mcapHistory['nifty500_raw'] ?? $mcapHistory['nifty500'] ?? [];
        $months  = count($labels);
        if ($months < 2) return null;

        $n500 = array_map(fn($v) => $v === null ? null : (float)$v, $n500raw);
        $n500Vals = array_filter($n500, fn($v) => $v !== null && $v > 0);
        $minNifty = empty($n500Vals) ? 0 : min($n500Vals);
        $maxNifty = empty($n500Vals) ? 100 : max($n500Vals);
        
        $range    = $maxNifty - $minNifty;
        if ($range == 0) $range = max($minNifty * 0.1, 100);
        $minY     = max(0, floor(($minNifty - $range * 0.15) / 100) * 100);
        $maxY     = ceil(($maxNifty + $range * 0.15) / 100) * 100;

        $config = [
            'type' => 'line',
            'data' => [
                'labels'   => $labels,
                'datasets' => [
                    [
                        'label'           => 'Nifty 500',
                        'data'            => $n500,
                        'borderColor'     => '#C0392B',
                        'backgroundColor' => 'rgba(192,57,43,0.1)',
                        'borderWidth'     => 1.5,
                        'pointRadius'     => 0,
                        'tension'         => 0.3,
                        'fill'            => true,
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'title'  => ['display' => false],
                    'legend' => [
                        'display'  => true,
                        'position' => 'top',
                        'labels'   => [
                            'usePointStyle' => true,
                            'pointStyle'    => 'line',
                            'font'          => ['size' => 9],
                            'padding'       => 10,
                            'color'         => '#222',
                        ],
                    ],
                    'datalabels' => ['display' => false],
                ],
                'scales' => [
                    'x' => [
                        'grid'               => ['display' => false],
                        'ticks'              => [
                            'maxTicksLimit' => min($months, 18),
                            'maxRotation'   => 45,
                            'font'          => ['size' => 9],
                            'color'         => '#444',
                        ],
                    ],
                    'y' => [
                        'type'        => 'linear',
                        'position'    => 'left',
                        'display'     => true,
                        'beginAtZero' => false,
                        'min'         => $minY,
                        'max'         => $maxY,
                        'grid'        => ['display' => false],
                        'ticks'       => [
                            'display'       => true,
                            'font'          => ['size' => 10],
                            'color'         => '#C0392B',
                            'maxTicksLimit' => 10,
                        ],
                    ],
                ],
                'layout' => ['padding' => ['top' => 0, 'right' => 50, 'bottom' => 4, 'left' => 55]],
            ],
        ];

        $cacheKey = 'mcap_split_bottom_v3_' . md5($clientName . $months . implode(',', $labels) . '|' . implode(',', array_slice($n500, -3)));
        return $this->fetchChartPng($config, 650, 240, $cacheKey);
    }


    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 4 — NIFTY PRICE LOOKUP (with nearest-day fallback)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Load all Nifty prices between two dates in a single query.
     * Stored in $this->priceCache keyed by date string.
     */
    private function loadPriceRange(string $fromDate, string $toDate, string $index): void
    {
        $col = ($index === 'nifty50') ? 'nifty50' : 'nifty500';
        $sql = "SELECT trade_date, `{$col}` AS price
                FROM nifty_historical_data
                WHERE trade_date BETWEEN :from AND :to
                  AND `{$col}` IS NOT NULL AND `{$col}` > 0
                ORDER BY trade_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->priceCache[$row['trade_date']] = (float)$row['price'];
        }
    }

    /**
     * Get Nifty price for a date. If exact date is a holiday/weekend,
     * walk backwards up to 5 trading days to find the nearest prior close.
     */
    private function getNiftyPrice(string $date, string $index): float
    {
        // Try exact date first (already in cache)
        if (isset($this->priceCache[$date])) {
            return $this->priceCache[$date];
        }

        // Walk backwards up to 5 days
        $ts = strtotime($date);
        for ($i = 1; $i <= 15; $i++) {
            $tryDate = date('Y-m-d', $ts - ($i * 86400));
            if (isset($this->priceCache[$tryDate])) {
                return $this->priceCache[$tryDate];
            }
        }

        // If still not found (date before our data starts), query directly
        $col  = ($index === 'nifty50') ? 'nifty50' : 'nifty500';
        $stmt = $this->pdo->prepare(
            "SELECT `{$col}` AS price FROM nifty_historical_data
             WHERE trade_date <= :date AND `{$col}` > 0
             ORDER BY trade_date DESC LIMIT 1"
        );
        $stmt->execute([':date' => $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $price = (float)$row['price'];
            $this->priceCache[$date] = $price;
            return $price;
        }

        // Fallback: use the earliest known price if date is before database records start
        $stmt = $this->pdo->prepare(
            "SELECT `{$col}` AS price FROM nifty_historical_data
             WHERE `{$col}` > 0
             ORDER BY trade_date ASC LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $price = $row ? (float)$row['price'] : 0.0;

        $this->priceCache[$date] = $price;
        return $price;
    }


    // ──────────────────────────────────────────────────────────────────────────
    // SECTION 5 — QUICKCHART HTTP HELPER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetch a chart PNG from QuickChart.io.
     * Uses local file cache (24hr TTL) to avoid repeat API calls per report run.
     * 
     * @return string|null  Raw PNG bytes, or null on failure
     */
    private function fetchChartPng(array $config, int $w, int $h, string $cacheKey): ?string
    {
        $cachePath = $this->cacheDir . $cacheKey . '.png';

        // Return cached PNG if fresh (< 24 hours)
        if (file_exists($cachePath) && (time() - filemtime($cachePath) < 86400)) {
            return file_get_contents($cachePath);
        }

        $payload = json_encode([
            'version'         => '3',    // Chart.js v3 — required for indexAxis, named scales, mixed types
            'chart'           => $config,
            'width'           => $w,
            'height'          => $h,
            'backgroundColor' => 'white',
            'format'          => 'png',
        ]);

        // Try cURL first, fall back to file_get_contents
        $png = $this->curlPost('https://quickchart.io/chart', $payload);

        if ($png && strlen($png) > 1000) {  // sanity check: real PNG is > 1KB
            file_put_contents($cachePath, $png);
            return $png;
        }

        return null;
    }

    private function curlPost(string $url, string $payload): ?string
    {
        if (!function_exists('curl_init')) {
            // Fall back to file_get_contents if cURL not available
            $ctx = stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 15,
            ]]);
            $result = @file_get_contents($url, false, $ctx);
            return $result ?: null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,  // some shared hosts need this
        ]);
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        curl_close($ch);

        return ($result && !$err) ? $result : null;
    }

    /**
     * Convert raw PNG bytes to a base64 data URI for embedding in mPDF HTML
     */
    public static function pngToDataUri(?string $png): string
    {
        if (!$png) return '';
        return 'data:image/png;base64,' . base64_encode($png);
    }

    private function emptyResult(): array
    {
        return [
            'portfolio_xirr'    => 0.0,
            'benchmark_xirr'    => 0.0,
            'first_date'        => '',
            'last_date'         => '',
            'holding_days'      => 0,
            'cashflow_count'    => 0,
            'monthly_labels'    => [],
            'monthly_portfolio' => [],
            'monthly_nifty'     => [],
            'growth_portfolio'  => [],
            'growth_nifty'      => [],
        ];
    }
}
