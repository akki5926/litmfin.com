<?php
require_once('auth_guard.php');

/**
 * index_xirr.php
 *
 * Standalone, reusable XIRR calculator for Nifty 50 and Nifty 500 indices.
 *
 * Calculates the XIRR return you would have achieved if you had invested
 * the same cashflows into an index instead of the actual portfolio.
 *
 * This is the "universally accepted" benchmark replication method:
 * - On each purchase date: buy as many index units as the rupee amount allows
 * - On each redemption date: sell index units worth the same rupee amount
 * - At valuation date: value remaining units at today's index level
 * - Run XIRR on the resulting synthetic cashflow series
 *
 * USAGE EXAMPLES:
 * ──────────────────────────────────────────────────────────────────────────
 *   require_once 'index_xirr.php';
 *   $calc = new IndexXirr($pdo);
 *
 *   // Since-inception XIRR for Nifty 500
 *   $result = $calc->compute($cashflows, $timestamps, 'nifty500');
 *   echo $result['xirr_pct'];        // e.g. 12.45
 *   echo $result['units_held'];      // hypothetical units at valuation date
 *   echo $result['mirror_value'];    // current rupee value of mirror portfolio
 *
 *   // Both indices at once
 *   $both = $calc->computeBoth($cashflows, $timestamps);
 *   echo $both['nifty500']['xirr_pct'];
 *   echo $both['nifty50']['xirr_pct'];
 *
 *   // Rolling window (e.g. last 3 years)
 *   $rolling = $calc->computeRolling($cashflows, $timestamps, $currentValue, 3, 'nifty500');
 *   echo $rolling['xirr_pct'];
 *
 *   // Per-asset-class breakdown
 *   $breakdown = $calc->computeByAssetClass($fundRegistry, 'nifty500');
 *   foreach ($breakdown as $assetClass => $result) {
 *       echo "$assetClass: " . $result['xirr_pct'] . "%\n";
 *   }
 *
 *   // Simple Nifty return for a fixed investment period (no cashflow matching)
 *   $simple = $calc->simpleReturn('2020-01-01', '2026-04-02', 'nifty500');
 *   echo $simple['cagr_pct'];        // annualised CAGR
 *   echo $simple['absolute_pct'];    // total absolute return
 * ──────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/xirr_calculator.php';

class IndexXirr
{
    private PDO   $pdo;
    private array $priceCache = [];   // ['nifty500' => ['2020-01-01' => 12345.6, ...], ...]

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ══════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Compute index XIRR that mirrors the given cashflows.
     *
     * @param  array  $cashflows   Signed cashflows (negative=investment, positive=redemption).
     *                             Last element should be +current_portfolio_value (terminal).
     * @param  array  $timestamps  Parallel Unix timestamps.
     * @param  string $index       'nifty500' or 'nifty50'
     * @return array {
     *   xirr_pct: float,         XIRR as percentage (e.g. 12.45 for 12.45%)
     *   xirr_decimal: float,     XIRR as decimal (e.g. 0.1245)
     *   units_held: float,       hypothetical units held at valuation date
     *   mirror_value: float,     current rupee value of mirror portfolio
     *   first_date: string,      Y-m-d of first cashflow
     *   last_date: string,       Y-m-d of valuation date
     *   holding_days: int,
     *   cashflow_count: int,
     *   mirror_cashflows: array, signed cashflows for the mirror (for verification)
     *   mirror_timestamps: array
     * }
     */
    public function compute(array $cashflows, array $timestamps, string $index = 'nifty500'): array
    {
        if (empty($cashflows) || count($cashflows) !== count($timestamps)) {
            return $this->emptyResult($index);
        }

        // Sort by timestamp ascending (CRITICAL)
        $pairs = [];
        foreach ($cashflows as $k => $cf) {
            $pairs[] = ['cf' => (float)$cf, 'ts' => (int)$timestamps[$k]];
        }
        usort($pairs, fn($a, $b) => $a['ts'] - $b['ts']);

        // Calculate portfolio XIRR first for proportional redemptions
        $portXirr = 0.0;
        if (count($pairs) >= 2) {
            $cfs = array_column($pairs, 'cf');
            $tps = array_column($pairs, 'ts');
            $xirrDecimal = XirrCalculator::calculate($cfs, $tps);
            $portXirr = is_numeric($xirrDecimal) && is_finite($xirrDecimal) ? max(-0.9999, (float)$xirrDecimal) : 0.0;
        }

        $firstDate = date('Y-m-d', $pairs[0]['ts']);
        $lastTs    = end($pairs)['ts'];
        $lastDate  = date('Y-m-d', $lastTs);
        $today     = date('Y-m-d');

        $this->loadPrices($firstDate, $today, $index);

        $mirrorCf  = [];
        $mirrorTs  = [];
        $hypoUnits = 0.0;

        // All but the last entry are transactions; last is terminal current value
        $lastIdx = count($pairs) - 1;

        foreach ($pairs as $k => $p) {
            $cf    = $p['cf'];
            $ts    = $p['ts'];
            $date  = date('Y-m-d', $ts);
            $price = $this->getPrice($date, $index);
            $isLast = ($k === $lastIdx);

            if ($cf < 0 && !$isLast) {
                // Investment: buy index units
                if ($price > 0) {
                    $units      = abs($cf) / $price;
                    $hypoUnits += $units;
                    $mirrorCf[] = $cf;
                    $mirrorTs[] = $ts;
                }
            } elseif ($cf > 0 && !$isLast) {
                // Redemption: sell same PERCENTAGE of mirror units based on portfolio value
                if ($price > 0 && $hypoUnits > 0.0001) {
                    $portValue = 0.0;
                    for ($j = 0; $j < $k; $j++) {
                        $pastCf = $pairs[$j]['cf'];
                        $pastTs = $pairs[$j]['ts'];
                        $years = ($ts - $pastTs) / 365.0 / 86400.0;
                        $portValue += $pastCf * pow(1 + $portXirr, $years);
                    }
                    $portValue = -$portValue;
                    
                    $pct       = $portValue > 0 ? min(abs($cf) / $portValue, 1.0) : 1.0;
                    $unitsSold = $hypoUnits * $pct;
                    $hypoUnits         -= $unitsSold;
                    if ($hypoUnits < 0.0001) $hypoUnits = 0.0;
                    $mirrorCf[] = round($unitsSold * $price, 2);
                    $mirrorTs[] = $ts;
                }
            } elseif ($isLast) {
                // Terminal: value remaining units
                $todayPrice  = $this->getPrice($today, $index);
                $mirrorValue = ($todayPrice > 0) ? round($hypoUnits * $todayPrice, 2) : 0.0;
                if ($mirrorValue > 0) {
                    $mirrorCf[] = $mirrorValue;
                    $mirrorTs[] = time();
                }
            }
        }

        // Compute XIRR
        $xirrDecimal = 0.0;
        if (count($mirrorCf) >= 2) {
            $xirrDecimal = XirrCalculator::calculate($mirrorCf, $mirrorTs);
        }

        $todayPrice  = $this->getPrice($today, $index);
        $mirrorValue = round($hypoUnits * $todayPrice, 2);

        return [
            'index'            => $index,
            'xirr_pct'         => round($xirrDecimal * 100, 2),
            'xirr_decimal'     => $xirrDecimal,
            'units_held'       => round($hypoUnits, 6),
            'mirror_value'     => $mirrorValue,
            'first_date'       => $firstDate,
            'last_date'        => $lastDate,
            'holding_days'     => (int)(($lastTs - $pairs[0]['ts']) / 86400),
            'cashflow_count'   => count($mirrorCf),
            'mirror_cashflows' => $mirrorCf,
            'mirror_timestamps'=> $mirrorTs,
        ];
    }

    /**
     * Compute XIRR for both Nifty 500 AND Nifty 50 in a single call.
     *
     * @return array ['nifty500' => result, 'nifty50' => result]
     */
    public function computeBoth(array $cashflows, array $timestamps): array
    {
        return [
            'nifty500' => $this->compute($cashflows, $timestamps, 'nifty500'),
            'nifty50'  => $this->compute($cashflows, $timestamps, 'nifty50'),
        ];
    }

    /**
     * Compute rolling XIRR for a fixed time window.
     * Simulates: "if invested for exactly N years to today, what XIRR?"
     *
     * @param  float $currentValue  Portfolio current value (used as terminal cashflow)
     * @param  int   $years         Window length (1, 3, 5, 10 etc.)
     */
    public function computeRolling(
        array  $cashflows,
        array  $timestamps,
        float  $currentValue,
        int    $years,
        string $index = 'nifty500'
    ): array {
        $windowStart = strtotime("-{$years} years");

        // Filter cashflows to window
        $filteredCf = [];
        $filteredTs = [];
        foreach ($timestamps as $k => $ts) {
            if ($ts >= $windowStart && $cashflows[$k] < 0) {
                $filteredCf[] = $cashflows[$k];
                $filteredTs[] = $ts;
            }
        }

        if (empty($filteredCf)) {
            return array_merge($this->emptyResult($index), ['years' => $years]);
        }

        // Append current value as terminal
        $filteredCf[] = $currentValue;
        $filteredTs[] = time();

        $result          = $this->compute($filteredCf, $filteredTs, $index);
        $result['years'] = $years;
        return $result;
    }

    /**
     * Compute index XIRR per asset class from the fundCashFlowRegistry.
     *
     * @param  array  $registry  $fundCashFlowRegistry from calculateHoldings()
     * @param  string $index
     * @return array  keyed by asset class: ['EQUITY' => result, 'DEBT' => result, ...]
     */
    public function computeByAssetClass(array $registry, string $index = 'nifty500'): array
    {
        // Group by asset class
        $groups = [];
        foreach ($registry as $entry) {
            $ac = strtoupper($entry['asset_class'] ?? 'OTHER');
            if (!isset($groups[$ac])) $groups[$ac] = ['cashflows' => [], 'timestamps' => []];

            foreach ($entry['cashflows'] as $k => $cf) {
                $groups[$ac]['cashflows'][]  = $cf;
                $groups[$ac]['timestamps'][] = $entry['timeperiods'][$k];
            }
        }

        // Add TOTAL
        $groups['TOTAL'] = ['cashflows' => [], 'timestamps' => []];
        foreach ($registry as $entry) {
            foreach ($entry['cashflows'] as $k => $cf) {
                $groups['TOTAL']['cashflows'][]  = $cf;
                $groups['TOTAL']['timestamps'][] = $entry['timeperiods'][$k];
            }
        }

        $results = [];
        foreach ($groups as $ac => $data) {
            if (empty($data['cashflows'])) continue;
            $results[$ac] = $this->compute($data['cashflows'], $data['timestamps'], $index);
        }
        return $results;
    }

    /**
     * Simple index return between two dates (no cashflow matching).
     * Just: (end_price - start_price) / start_price, annualised.
     *
     * @return array {
     *   start_date, end_date, start_price, end_price,
     *   absolute_pct, cagr_pct, holding_days
     * }
     */
    public function simpleReturn(string $startDate, string $endDate, string $index = 'nifty500'): array
    {
        $this->loadPrices($startDate, $endDate, $index);
        $startPrice = $this->getPrice($startDate, $index);
        $endPrice   = $this->getPrice($endDate,   $index);

        if ($startPrice <= 0 || $endPrice <= 0) {
            return ['error' => 'Price not found for date range', 'index' => $index];
        }

        $days        = (strtotime($endDate) - strtotime($startDate)) / 86400;
        $absolutePct = round(($endPrice - $startPrice) / $startPrice * 100, 2);
        $cagrPct     = ($days > 0)
            ? round((pow($endPrice / $startPrice, 365 / $days) - 1) * 100, 2)
            : 0.0;

        return [
            'index'        => $index,
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'start_price'  => $startPrice,
            'end_price'    => $endPrice,
            'absolute_pct' => $absolutePct,
            'cagr_pct'     => $cagrPct,
            'holding_days' => (int)$days,
        ];
    }

    /**
     * Get index price for a specific date (with nearest-day fallback).
     * Useful for display or one-off lookups.
     */
    public function getPriceOn(string $date, string $index = 'nifty500'): float
    {
        $this->loadPrices($date, $date, $index);
        return $this->getPrice($date, $index);
    }

    /**
     * Get both index prices for a date.
     * @return array ['nifty500' => float, 'nifty50' => float]
     */
    public function getBothPricesOn(string $date): array
    {
        return [
            'nifty500' => $this->getPriceOn($date, 'nifty500'),
            'nifty50'  => $this->getPriceOn($date, 'nifty50'),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════

    
    // ══════════════════════════════════════════════════════════════════════
    // REGULAR INDEX XIRR  (universally accepted standalone method)
    //
    // These methods calculate what XIRR an investor would have earned by
    // investing directly INTO the index (not as a mirror of a portfolio).
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Regular index XIRR for a lump-sum investment on a fixed date.
     *
     * "If I had invested ₹X into Nifty 500 on startDate, what XIRR would
     *  I be getting today?"
     *
     * @param  float  $amount    Amount invested (positive)
     * @param  string $startDate Y-m-d
     * @param  string $endDate   Y-m-d (default: today)
     * @param  string $index     'nifty500' or 'nifty50'
     * @return array {
     *   xirr_pct, xirr_decimal, cagr_pct,
     *   start_price, end_price, absolute_return_pct,
     *   units, current_value, invested_amount,
     *   start_date, end_date, holding_days
     * }
     */
    public function regularXirrLumpsum(
        float  $amount,
        string $startDate,
        string $endDate   = '',
        string $index     = 'nifty500'
    ): array {
        $endDate = $endDate ?: date('Y-m-d');
        $this->loadPrices($startDate, $endDate, $index);

        $startPrice = $this->getPrice($startDate, $index);
        $endPrice   = $this->getPrice($endDate,   $index);

        if ($startPrice <= 0 || $amount <= 0) {
            return array_merge($this->emptyResult($index), [
                'error' => 'Invalid start price or amount',
            ]);
        }

        $units        = $amount / $startPrice;
        $currentValue = round($units * $endPrice, 2);
        $days         = (strtotime($endDate) - strtotime($startDate)) / 86400;

        // XIRR: two cashflows — buy on startDate, sell on endDate
        $xirr = XirrCalculator::calculate(
            [-$amount, $currentValue],
            [strtotime($startDate), strtotime($endDate)]
        );

        $absolutePct = $startPrice > 0 ? round(($endPrice - $startPrice) / $startPrice * 100, 2) : 0;
        $cagrPct     = $days > 0 ? round((pow($endPrice / $startPrice, 365 / $days) - 1) * 100, 2) : 0;

        return [
            'index'               => $index,
            'start_date'          => $startDate,
            'end_date'            => $endDate,
            'holding_days'        => (int)$days,
            'invested_amount'     => $amount,
            'units'               => round($units, 6),
            'start_price'         => $startPrice,
            'end_price'           => $endPrice,
            'current_value'       => $currentValue,
            'absolute_return_pct' => $absolutePct,
            'cagr_pct'            => $cagrPct,
            'xirr_pct'            => round($xirr * 100, 2),
            'xirr_decimal'        => $xirr,
        ];
    }

    /**
     * Regular index XIRR for a monthly SIP (fixed installment, fixed day).
     *
     * "If I had started a ₹X SIP into Nifty 500 from startDate to endDate,
     *  what XIRR would I be getting today?"
     *
     * @param  float  $sipAmount    Monthly installment amount
     * @param  string $startDate    First SIP date (Y-m-d)
     * @param  string $endDate      Last SIP date OR 'today' (default: today)
     * @param  int    $dayOfMonth   Day of month for SIP (default: same as startDate)
     * @param  string $index        'nifty500' or 'nifty50'
     * @return array {
     *   xirr_pct, xirr_decimal, total_invested, current_value, units_held,
     *   installment_count, start_date, end_date, holding_months
     * }
     */
    public function regularXirrSip(
        float  $sipAmount,
        string $startDate,
        string $endDate     = '',
        int    $dayOfMonth  = 0,
        string $index       = 'nifty500'
    ): array {
        $endDate    = $endDate   ?: date('Y-m-d');
        $dayOfMonth = $dayOfMonth ?: (int)date('d', strtotime($startDate));

        $this->loadPrices($startDate, $endDate, $index);

        $cashflows  = [];
        $timestamps = [];
        $units      = 0.0;
        $totalInvested = 0.0;

        // Walk month by month from startDate to endDate
        $cursor = mktime(0, 0, 0,
            (int)date('m', strtotime($startDate)),
            $dayOfMonth,
            (int)date('Y', strtotime($startDate))
        );
        $endTs  = strtotime($endDate);

        while ($cursor <= $endTs) {
            $d     = date('Y-m-d', $cursor);
            $price = $this->getPrice($d, $index);
            if ($price > 0) {
                $units         += $sipAmount / $price;
                $totalInvested += $sipAmount;
                $cashflows[]    = -$sipAmount;
                $timestamps[]   = $cursor;
            }
            $cursor = strtotime('+1 month', $cursor);
        }

        if (empty($cashflows)) {
            return array_merge($this->emptyResult($index), ['error' => 'No SIP dates found']);
        }

        // Terminal: current value
        $todayPrice   = $this->getPrice($endDate, $index);
        $currentValue = round($units * $todayPrice, 2);
        $cashflows[]  = $currentValue;
        $timestamps[] = strtotime($endDate);

        $xirr = XirrCalculator::calculate($cashflows, $timestamps);

        return [
            'index'              => $index,
            'start_date'         => $startDate,
            'end_date'           => $endDate,
            'sip_amount'         => $sipAmount,
            'day_of_month'       => $dayOfMonth,
            'installment_count'  => count($cashflows) - 1,
            'holding_months'     => count($cashflows) - 1,
            'total_invested'     => $totalInvested,
            'units_held'         => round($units, 6),
            'current_price'      => $todayPrice,
            'current_value'      => $currentValue,
            'absolute_return'    => round($currentValue - $totalInvested, 2),
            'absolute_return_pct'=> $totalInvested > 0
                ? round(($currentValue - $totalInvested) / $totalInvested * 100, 2) : 0,
            'xirr_pct'           => round($xirr * 100, 2),
            'xirr_decimal'       => $xirr,
        ];
    }

    /**
     * Regular XIRR for BOTH Nifty 500 and Nifty 50, lump-sum — one call.
     *
     * @return array ['nifty500' => result, 'nifty50' => result]
     */
    public function regularXirrLumpsumBoth(
        float  $amount,
        string $startDate,
        string $endDate = ''
    ): array {
        return [
            'nifty500' => $this->regularXirrLumpsum($amount, $startDate, $endDate, 'nifty500'),
            'nifty50'  => $this->regularXirrLumpsum($amount, $startDate, $endDate, 'nifty50'),
        ];
    }

    /**
     * Regular XIRR for BOTH Nifty 500 and Nifty 50, SIP — one call.
     *
     * @return array ['nifty500' => result, 'nifty50' => result]
     */
    public function regularXirrSipBoth(
        float  $sipAmount,
        string $startDate,
        string $endDate    = '',
        int    $dayOfMonth = 0
    ): array {
        return [
            'nifty500' => $this->regularXirrSip($sipAmount, $startDate, $endDate, $dayOfMonth, 'nifty500'),
            'nifty50'  => $this->regularXirrSip($sipAmount, $startDate, $endDate, $dayOfMonth, 'nifty50'),
        ];
    }

    /**
     * Rolling regular XIRR for a fixed investment period — both indices.
     * "What XIRR would a lump-sum into Nifty have given over the last N years?"
     *
     * @param  int    $years   Look-back period (1, 3, 5, 10)
     * @param  float  $amount  Hypothetical lump-sum amount
     * @return array ['nifty500' => result, 'nifty50' => result]
     */
    public function regularXirrRollingBoth(int $years, float $amount = 100000): array
    {
        $startDate = date('Y-m-d', strtotime("-{$years} years"));
        $endDate   = date('Y-m-d');
        return [
            'nifty500' => $this->regularXirrLumpsum($amount, $startDate, $endDate, 'nifty500'),
            'nifty50'  => $this->regularXirrLumpsum($amount, $startDate, $endDate, 'nifty50'),
        ];
    }

    private function loadPrices(string $fromDate, string $toDate, string $index): void
    {
        $col = ($index === 'nifty50') ? 'nifty50' : 'nifty500';
        if (!isset($this->priceCache[$index])) {
            $this->priceCache[$index] = [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT trade_date, `{$col}` AS price
             FROM nifty_historical_data
             WHERE trade_date BETWEEN :from AND :to
               AND `{$col}` IS NOT NULL AND `{$col}` > 0
             ORDER BY trade_date ASC"
        );
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->priceCache[$index][$row['trade_date']] = (float)$row['price'];
        }
    }

    private function getPrice(string $date, string $index): float
    {
        $cache = $this->priceCache[$index] ?? [];
        if (!empty($cache[$date])) return $cache[$date];

        // Walk back up to 7 days for holidays/weekends
        $ts = strtotime($date);
        for ($i = 1; $i <= 15; $i++) {
            $d = date('Y-m-d', $ts - $i * 86400);
            if (!empty($cache[$d])) return $cache[$d];
        }

        // If not in cache, query directly (for edge dates)
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
            $this->priceCache[$index][$date] = $price;
            return $price;
        }

        // Fallback: earliest available price
        $stmt = $this->pdo->prepare(
            "SELECT `{$col}` AS price FROM nifty_historical_data
             WHERE `{$col}` > 0
             ORDER BY trade_date ASC LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $price = $row ? (float)$row['price'] : 0.0;
        if ($price > 0) $this->priceCache[$index][$date] = $price;
        return $price;
    }

    private function emptyResult(string $index): array
    {
        return [
            'index'            => $index,
            'xirr_pct'         => 0.0,
            'xirr_decimal'     => 0.0,
            'units_held'       => 0.0,
            'mirror_value'     => 0.0,
            'first_date'       => '',
            'last_date'        => '',
            'holding_days'     => 0,
            'cashflow_count'   => 0,
            'mirror_cashflows' => [],
            'mirror_timestamps'=> [],
        ];
    }
}
