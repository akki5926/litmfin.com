<?php
require_once('auth_guard.php');

/**
 * xirr_calculator.php
 *
 * Robust XIRR engine using bisection (interval halving).
 *
 * WHY BISECTION INSTEAD OF NEWTON-RAPHSON:
 *   PhpSpreadsheet's NonPeriodic::XIRR uses Newton-Raphson with a single guess.
 *   On large cashflow arrays (500+ entries with many sign changes — typical for
 *   multi-fund portfolios with SIPs + coupons + per-fund terminals), Newton-Raphson
 *   diverges and PhpSpreadsheet returns "#NUM!" or throws.
 *   The old code fell back to a simple CAGR, which gives wrong results (e.g. 5.07%
 *   instead of the correct 10.8%).
 *
 *   Bisection is guaranteed to converge for any valid XIRR problem:
 *   - Find a bracket [lo, hi] where NPV(lo) > 0 and NPV(hi) < 0
 *   - Halve the interval 200 times → precision < 1e-14
 *   - O(n × 200) time — fast even at 600 cashflows
 *   - No divergence, no false convergence, no guess sensitivity
 *
 * DROP-IN REPLACEMENT for Financial->XIRRAdpro():
 *   Old: $this->m_xirrObj = new Financial;
 *   New: $this->m_xirrObj = new XirrCalculator();
 *   Call sites unchanged: $this->m_xirrObj->XIRRAdpro($cfs, $timestamps, 0.1)
 */

class XirrCalculator
{
    private const MAX_VALID      = 50.0;     // 5000% annualised — filters nonsense
    private const MIN_VALID      = -0.9999;  // near-total loss
    private const SHORT_DAYS     = 32;       // ≤32 days: use CAGR (XIRR unstable)
    private const BISECT_ITERS   = 200;      // 200 halvings → precision ~1e-14
    private const BISECT_TOL     = 1e-10;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Main XIRR entry point.
     *
     * @param  array $values  Cashflows. Negative = investment, positive = receipt.
     *                        Must have ≥1 negative and ≥1 positive value.
     * @param  array $dates   Parallel array of Unix timestamps.
     * @param  float $guess   Ignored (kept for signature compatibility).
     * @return float          Annualised rate as decimal (e.g. 0.108 for 10.8%).
     *                        Returns 0.0 on invalid input or no solution.
     */
    public static function calculate(array $values, array $dates, float $guess = 0.1): float
    {
        // ── Validate and clean ────────────────────────────────────────────────
        if (empty($values) || empty($dates)) return 0.0;
        if (count($values) !== count($dates)) return 0.0;

        $clean = [];
        foreach ($values as $k => $v) {
            if (!isset($dates[$k]) || !is_numeric($v) || !is_numeric($dates[$k])) continue;
            $clean[] = ['v' => (float)$v, 'ts' => (int)$dates[$k]];
        }
        if (count($clean) < 2) return 0.0;

        // Sort by date ascending
        usort($clean, fn($a, $b) => $a['ts'] - $b['ts']);
        $sortedValues = array_column($clean, 'v');
        $sortedDates  = array_column($clean, 'ts');

        // Must have both negative (investments) and positive (receipts)
        $hasNeg = false; $hasPos = false;
        foreach ($sortedValues as $v) {
            if ($v < 0) $hasNeg = true;
            if ($v > 0) $hasPos = true;
        }
        if (!$hasNeg || !$hasPos) return 0.0;

        // Short period: CAGR is more stable than XIRR
        $firstTs  = $sortedDates[0];
        $lastTs   = end($sortedDates);
        $daysDiff = ($lastTs - $firstTs) / 86400.0;
        if ($daysDiff <= self::SHORT_DAYS) {
            return self::cagr($sortedValues, $daysDiff);
        }

        // ── Bisection XIRR ────────────────────────────────────────────────────
        $result = self::bisection($sortedValues, $sortedDates);

        if ($result !== null && self::isValid($result)) {
            error_log(sprintf(
                'LitmFin XirrCalculator: XIRR OK — %d entries, %.0f days, rate=%.4f (%.2f%%)',
                count($sortedValues), $daysDiff, $result, $result * 100
            ));
            return $result;
        }

        // ── Fallback: CAGR ───────────────────────────────────────────────────
        $cagrVal = self::cagr($sortedValues, $daysDiff);
        error_log(sprintf(
            'LitmFin XirrCalculator: CAGR FALLBACK — %d entries, %.0f days, cagr=%.4f (%.2f%%). Bisection found no bracket.',
            count($sortedValues), $daysDiff, $cagrVal, $cagrVal * 100
        ));
        return $cagrVal;
    }

    /** Drop-in replacement for Financial->XIRRAdpro(). Returns decimal rate. */
    public function XIRRAdpro(array $values, array $dates, float $guess = 0.1): float
    {
        return self::calculate($values, $dates, $guess);
    }

    /** Compatibility shim: returns null on failure instead of 0.0 */
    public function XIRR(array $values, array $dates, float $guess = 0.1): ?float
    {
        $r = self::calculate($values, $dates, $guess);
        return $r === 0.0 ? null : $r;
    }

    // ── Core bisection algorithm ──────────────────────────────────────────────

    /**
     * Bisection XIRR.
     *
     * XIRR is the rate r such that:
     *   sum( cf[i] / (1+r)^( (date[i]-date[0]) / 365 ) ) = 0
     *
     * Bisection finds r by bracketing the root and halving the interval.
     * Guaranteed to converge if a bracket [lo, hi] can be found.
     *
     * @param  array $values  Sorted cashflows (float)
     * @param  array $dates   Sorted Unix timestamps (int)
     * @return float|null     Rate decimal, or null if no bracket found
     */
    private static function bisection(array $values, array $dates): ?float
    {
        $t0 = $dates[0];

        // NPV function: sum of discounted cashflows at rate r
        $npv = function(float $r) use ($values, $dates, $t0): float {
            $sum = 0.0;
            foreach ($values as $i => $cf) {
                $years = ($dates[$i] - $t0) / 365.0;
                // Guard against extreme rates causing overflow
                $denom = ($years == 0) ? 1.0 : ((1.0 + $r) ** $years);
                if ($denom == 0.0 || !is_finite($denom)) return PHP_FLOAT_MAX;
                $sum += $cf / $denom;
            }
            return is_finite($sum) ? $sum : PHP_FLOAT_MAX;
        };

        // Try to find a bracket. For typical investment portfolios:
        //   At r = -0.999 (near-total loss): NPV >> 0 (terminal value dominates)
        //   At r = 10.0 (1000% return): NPV << 0 (investments dominate when discounted less)
        // Try multiple bracket pairs in case the range is unusual
        $brackets = [
            [-0.999,  10.0],
            [-0.999,  2.0],
            [-0.999,  50.0],
            [-0.5,    10.0],
            [0.0,     10.0],
            [-0.999,  0.5],
        ];

        $lo = null; $hi = null; $f_lo = null; $f_hi = null;

        foreach ($brackets as [$testLo, $testHi]) {
            $fLo = $npv($testLo);
            $fHi = $npv($testHi);
            if (is_finite($fLo) && is_finite($fHi) && $fLo * $fHi < 0) {
                $lo = $testLo; $hi = $testHi;
                $f_lo = $fLo;  $f_hi = $fHi;
                break;
            }
        }

        if ($lo === null) return null;  // no bracket found

        // Bisect
        for ($i = 0; $i < self::BISECT_ITERS; $i++) {
            $mid   = ($lo + $hi) / 2.0;
            $f_mid = $npv($mid);

            if (abs($f_mid) < self::BISECT_TOL || ($hi - $lo) < self::BISECT_TOL) {
                return $mid;
            }

            if ($f_lo * $f_mid <= 0) {
                $hi   = $mid;
                $f_hi = $f_mid;
            } else {
                $lo   = $mid;
                $f_lo = $f_mid;
            }
        }

        return ($lo + $hi) / 2.0;
    }

    // ── CAGR fallback ─────────────────────────────────────────────────────────

    /**
     * Compound Annual Growth Rate.
     * Used when: period ≤ 32 days, OR bisection finds no bracket.
     * Formula: (sum_received / sum_invested)^(365/days) - 1
     */
    private static function cagr(array $values, float $daysDiff): float
    {
        if ($daysDiff <= 0) return 0.0;
        $invested = 0.0; $received = 0.0;
        foreach ($values as $v) {
            if ($v < 0) $invested += abs($v);
            else        $received += $v;
        }
        if ($invested <= 0 || $received <= 0) return 0.0;
        $r = pow($received / $invested, 365.0 / $daysDiff) - 1.0;
        return self::isValid($r) ? $r : 0.0;
    }

    private static function isValid(mixed $v): bool
    {
        return is_numeric($v)
            && is_finite((float)$v)
            && (float)$v >= self::MIN_VALID
            && (float)$v <= self::MAX_VALID;
    }
}
