<?php
require_once('auth_guard.php');

/**
 * xirr_engine.php
 *
 * Flag-controlled XIRR engine.
 *
 * FLAG (change in one place):
 *   XIRR_USE_OLD_FINANCIAL = true   → NPV computed via Financial->XNPV()
 *                                     (same formula as portfolio.php / totalholdings.php)
 *   XIRR_USE_OLD_FINANCIAL = false  → pure built-in NPV (no dependencies)
 *
 * In BOTH modes the outer solver is our own safe bisection loop.
 * We NEVER call Financial->XIRR() directly because it has:
 *   abs($f1)  where $f1 can be null when XNPV overflows → PHP 8.1 deprecated.
 * Instead we call only Financial->XNPV() and wrap it with a null guard.
 *
 * Drop-in replacement for Financial->XIRRAdpro():
 *   old: (new Financial())->XIRRAdpro($cashflows, $timestamps, 0.1)
 *   new: (new XirrEngine())->XIRRAdpro($cashflows, $timestamps, 0.1)
 */

if (!defined('XIRR_USE_OLD_FINANCIAL')) {
    define('XIRR_USE_OLD_FINANCIAL', true);   // ← change to false to use built-in engine
}

if (XIRR_USE_OLD_FINANCIAL) {
    if (!class_exists('Financial')) {
        $fc = __DIR__ . '/financial_class.php';
        if (!file_exists($fc)) {
            throw new RuntimeException(
                'financial_class.php not found — set XIRR_USE_OLD_FINANCIAL=false.'
            );
        }
        require_once $fc;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
class XirrEngine
{
    private ?object $fin = null;   // Financial instance (flag = true only)

    public function __construct()
    {
        if (XIRR_USE_OLD_FINANCIAL) {
            $this->fin = new Financial();
        }
    }

    /**
     * XIRRAdpro — drop-in for Financial->XIRRAdpro().
     *
     * @param  array $values  Cashflows. Negative = investment, positive = receipt.
     * @param  array $dates   Parallel Unix timestamps.
     * @param  float $guess   Ignored (bisection needs no guess).
     * @return float          Annualised rate as decimal (0.108 = 10.8%). 0.0 on failure.
     */
    public function XIRRAdpro(array $values, array $dates, float $guess = 0.1): float
    {
        // ── Sanitize: strip nulls, non-numeric, non-finite ───────────────────
        $cv = []; $cd = [];
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, $dates)) continue;
            $d = $dates[$k];
            if ($v === null || $d === null)   continue;
            if (!is_numeric($v) || !is_numeric($d)) continue;
            $fv = (float)$v; $fd = (int)$d;
            if (!is_finite($fv)) continue;
            $cv[] = $fv;
            $cd[] = $fd;
        }
        if (count($cv) < 2) return 0.0;

        // Must have at least one negative (buy) and one positive (sell/value)
        $hasNeg = false; $hasPos = false;
        foreach ($cv as $v) {
            if ($v < 0) $hasNeg = true;
            if ($v > 0) $hasPos = true;
        }
        if (!$hasNeg || !$hasPos) return 0.0;

        // Sort by date
        array_multisort($cd, SORT_ASC, SORT_NUMERIC, $cv);

        // Short period → CAGR is more stable than XIRR
        $days = ($cd[count($cd) - 1] - $cd[0]) / 86400.0;
        if ($days <= 32) return self::cagr($cv, $days);

        // ── Build NPV closure ─────────────────────────────────────────────────
        $fin = $this->fin;

        if ($fin !== null) {
            // Use Financial->XNPV() — identical formula to portfolio.php.
            // Wrap so null (XNPV overflow) never reaches abs(): return signed sentinel.
            $npvFn = static function (float $r) use ($fin, $cv, $cd): float {
                $v = $fin->XNPV($r, $cv, $cd);
                if ($v === null || !is_finite($v)) {
                    // Extreme rate: negative → NPV should be huge positive; positive → huge negative
                    return ($r < 0) ? PHP_FLOAT_MAX : -PHP_FLOAT_MAX;
                }
                return $v;
            };
        } else {
            // Built-in NPV: same formula as Financial::XNPV but no class dependency
            $t0 = $cd[0];
            $npvFn = static function (float $r) use ($cv, $cd, $t0): float {
                $sum = 0.0;
                foreach ($cv as $i => $v) {
                    $years = ($cd[$i] - $t0) / 86400.0 / 365.0;
                    $denom = ($years == 0.0) ? 1.0 : pow(1.0 + $r, $years);
                    if ($denom == 0.0 || !is_finite($denom)) {
                        return ($r < 0) ? PHP_FLOAT_MAX : -PHP_FLOAT_MAX;
                    }
                    $sum += $v / $denom;
                }
                return is_finite($sum) ? $sum : (($r < 0) ? PHP_FLOAT_MAX : -PHP_FLOAT_MAX);
            };
        }

        // ── Bisection solver ──────────────────────────────────────────────────
        $r = self::bisect($npvFn);

        if ($r !== null && is_finite($r) && abs($r) <= 50.0) {
            return $r;
        }

        // Fallback CAGR (only if no bracket found — should be very rare)
        return self::cagr($cv, $days);
    }

    // ── Bisection ─────────────────────────────────────────────────────────────

    /**
     * Find r where npvFn(r) = 0 by interval bisection.
     * Tries 6 bracket pairs. 200 halvings → precision < 1e-14.
     * Guaranteed convergence once a bracket is found.
     */
    private static function bisect(callable $npvFn): ?float
    {
        // Bracket candidates: [lo, hi] pairs covering typical investment returns
        $pairs = [
            [-0.999, 10.0],
            [-0.999,  2.0],
            [-0.5,   10.0],
            [ 0.0,   10.0],
            [-0.999, 50.0],
            [-0.9,    1.0],
        ];

        $lo = null; $hi = null; $fLo = null; $fHi = null;

        foreach ($pairs as [$a, $b]) {
            $fa = $npvFn($a);
            $fb = $npvFn($b);
            if (!is_finite($fa) || !is_finite($fb)) continue;
            if ($fa * $fb < 0.0) {
                $lo = $a; $hi = $b; $fLo = $fa; $fHi = $fb;
                break;
            }
        }

        if ($lo === null) return null;   // no bracket — caller uses CAGR fallback

        for ($i = 0; $i < 200; $i++) {
            $mid  = ($lo + $hi) / 2.0;
            $fMid = $npvFn($mid);

            // Guard against overflow at mid
            if (!is_finite($fMid)) {
                $fMid = ($fLo > 0) ? PHP_FLOAT_MAX : -PHP_FLOAT_MAX;
            }

            if (abs($fMid) < 1e-9 || ($hi - $lo) < 1e-10) {
                return $mid;
            }

            if ($fLo * $fMid <= 0.0) {
                $hi = $mid; $fHi = $fMid;
            } else {
                $lo = $mid; $fLo = $fMid;
            }
        }

        return ($lo + $hi) / 2.0;
    }

    // ── CAGR ─────────────────────────────────────────────────────────────────

    private static function cagr(array $cf, float $days): float
    {
        $inv = 0.0; $rcv = 0.0;
        foreach ($cf as $v) {
            if ($v < 0) $inv += abs($v);
            else        $rcv += $v;
        }
        if ($inv <= 0.0 || $rcv <= 0.0 || $days <= 0.0) return 0.0;
        $r = pow($rcv / $inv, 365.0 / $days) - 1.0;
        return (is_finite($r) && abs($r) <= 50.0) ? $r : 0.0;
    }
}
