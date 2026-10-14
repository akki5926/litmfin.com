<?php
require_once('auth_guard.php');

/**
 * getsch-from-Nse.php  — optimised
 *
 * PERFORMANCE & BUG FIXES
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. BATCH DB INSERTS (biggest fix)
 *    OLD: one INSERT/UPDATE per scheme inside the loop → 10,000+ individual
 *         DB round-trips for a typical NSE master file. Each one pays TCP +
 *         query-parse + lock overhead. On a local MySQL server this alone
 *         takes 30–120 seconds.
 *    FIX: rows are collected into batches of BATCH_SIZE (500). One
 *         INSERT … ON DUPLICATE KEY UPDATE statement per batch.
 *         10,000 rows → 20 queries instead of 10,000. Typical speedup: 10–50×.
 *
 * 2. STREAMING / CHUNKED JSON PROGRESS
 *    OLD: PHP processed everything silently then returned one giant JSON blob.
 *         The browser showed nothing for 60-120 s then either succeeded or
 *         timed out. No way to know if it was working.
 *    FIX: action=fetch_progress streams newline-delimited JSON (NDJSON)
 *         via chunked transfer. The JS reads the stream incrementally and
 *         updates a live progress bar + running counts in real time.
 *
 * 3. REMOVED per-row error_log() CALLS
 *    OLD: error_log("Inserted scheme: ...") called inside the foreach —
 *         one synchronous disk write per scheme. 10,000 schemes = 10,000
 *         fwrite() calls. Adds seconds of pure I/O overhead.
 *    FIX: only summary totals are logged at the end.
 *
 * 4. REMOVED REDUNDANT error_log() for every line (lines 1–5 sample logging)
 *    These were left-over debug lines hitting disk on every request.
 *
 * 5. deriveFundCategory() / deriveCatTag() called strtoupper(trim()) on
 *    every single row independently. Pre-uppercased once at parse time.
 *
 * 6. convertDateFormat() used strtotime() as a fallback — very slow on
 *    malformed strings (tries 30+ format patterns internally). Added an
 *    early-return for the only two real formats in the NSE file.
 *
 * 7. AMC FILTER applied in PHP before DB, not after — avoids inserting/
 *    updating records the user didn't ask for.
 *
 * 8. ini_set('display_errors',1) was left on AFTER the AJAX header block
 *    that turned it off — any PHP notice would corrupt the JSON response.
 *    Fixed: display_errors always off in AJAX path.
 *
 * 9. PAGE SHELL now matches the parent's AJAX-fragment pattern
 *    (no full <!DOCTYPE> page with its own jQuery). The fetch button wires
 *    into the same loader/progress infrastructure as the rest of the admin.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Constants ─────────────────────────────────────────────────────────────────
const BATCH_SIZE = 500;   // rows per INSERT batch — tune to your MySQL max_allowed_packet

// ── Determine request type ────────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$isAjax = in_array($action, ['fetch_schemes', 'fetch_progress', 'diagnostics'], true);

if ($isAjax) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

set_time_limit(0);
ini_set('memory_limit', '512M');   // 1024M was excessive; 512M is enough for text parsing

require_once('database.php');
$db = new MySQLDB();

/* ═══════════════════════════════════════════════════════════════════════════
   HELPER FUNCTIONS
═══════════════════════════════════════════════════════════════════════════ */

/**
 * FIX 6: fast date converter — only two real formats exist in NSE files.
 */
function convertDateFormat(?string $d): ?string {
    if ($d === null || $d === '') return null;
    // Already YYYY-MM-DD
    if (isset($d[9]) && $d[4] === '-' && $d[7] === '-') return $d;
    // DD-MM-YYYY  (most common in NSE files)
    if (isset($d[9]) && $d[2] === '-' && $d[5] === '-') {
        return substr($d, 6, 4) . '-' . substr($d, 3, 2) . '-' . substr($d, 0, 2);
    }
    // Last resort — only for genuinely unusual strings
    $ts = strtotime($d);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

/**
 * FIX 5: accepts pre-uppercased strings — no strtoupper() inside.
 */
function deriveFundCategory(string $typeU, string $nameU): string {
    if ($typeU === 'LIQUID' || $typeU === 'OVERNIGHT') return 'Debt';
    if (strpos($nameU, 'HYBRID') !== false)  return 'Hybrid';
    if (strpos($nameU, 'GOLD') !== false || strpos($nameU, 'SILVER') !== false) return 'Commodity';
    if (strpos($nameU, 'SMALL CAP') !== false  || strpos($nameU, 'SMALLCAP') !== false)  return 'Small Cap';
    if (strpos($nameU, 'MID CAP') !== false    || strpos($nameU, 'MIDCAP') !== false)    return 'Mid Cap';
    if (strpos($nameU, 'LARGE CAP') !== false  || strpos($nameU, 'LARGECAP') !== false)  return 'Large Cap';
    if (strpos($nameU, 'FLEXI CAP') !== false  || strpos($nameU, 'FLEXICAP') !== false)  return 'Flexi Cap';
    if (strpos($nameU, 'MULTI CAP') !== false  || strpos($nameU, 'MULTICAP') !== false)  return 'Multi Cap';
    if ($typeU === 'DEBT')   return 'Debt';
    if ($typeU === 'EQUITY') return 'Other Equity';
    return 'Other';
}

/**
 * FIX 5: accepts pre-uppercased name.
 */
function deriveCatTag(string $nameU): string {
    $tags = [];
    if (strpos($nameU, 'AGGRESSIVE HYBRID') !== false)       $tags[] = 'Aggressive Hybrid';
    elseif (strpos($nameU, 'CONSERVATIVE HYBRID') !== false) $tags[] = 'Conservative Hybrid';
    elseif (strpos($nameU, 'BALANCED ADVANTAGE') !== false)  $tags[] = 'Balanced Advantage';

    if (strpos($nameU, 'ETF') !== false)                                                      $tags[] = 'ETF';
    if (strpos($nameU, 'INDEX') !== false)                                                    $tags[] = 'Index Fund';
    if (strpos($nameU, 'FUND OF FUND') !== false || strpos($nameU, ' FOF') !== false)        $tags[] = 'FOF';
    if (strpos($nameU, 'BOND') !== false)                                                     $tags[] = 'Bond';
    if (strpos($nameU, 'ELSS') !== false || strpos($nameU, 'TAX SAVER') !== false)           $tags[] = 'ELSS';
    if (strpos($nameU, 'RETIREMENT') !== false)                                               $tags[] = 'Retirement';
    if (strpos($nameU, 'CHILDREN') !== false)                                                 $tags[] = 'Children Fund';

    static $sectoral = ['BANKING','PHARMA','HEALTHCARE','TECHNOLOGY','INFRA','INFRASTRUCTURE',
        'PSU','CONSUMPTION','MANUFACTURING','ENERGY','FINANCIAL','DIGITAL','INNOVATION',
        'GLOBAL','INTERNATIONAL','POWER','DEFENCE','TRANSPORT','NIFTY','SENSEX','BSE',
        'DIVIDEND YIELD','FOCUSED','VALUE','CONTRA','SPECIAL','OPPORTUNITIES',
        'MOMENTUM','QUANT','EQUITY SAVINGS','ARBITRAGE'];
    foreach ($sectoral as $kw) {
        if (strpos($nameU, $kw) !== false) { $tags[] = 'Sectoral'; break; }
    }

    $tag = implode(',', $tags);
    return strlen($tag) > 20 ? substr($tag, 0, 20) : $tag;
}

/**
 * Build a header→index map from the NSE pipe-delimited header line.
 * e.g. "UNIQUE SR NO|SCHEME CODE|..." → ['UNIQUE SR NO' => 0, 'SCHEME CODE' => 1, ...]
 */
function buildHeaderMap(string $headerLine): array {
    $map = [];
    $headers = explode('|', $headerLine);
    foreach ($headers as $i => $h) {
        // Normalise: uppercase, collapse spaces, trim
        $key = strtoupper(trim(preg_replace('/\s+/', ' ', $h)));
        $map[$key] = $i;
    }
    return $map;
}

/**
 * Map NSE header names → DB column names.
 * Key   = header label exactly as it appears in the NSE file (uppercased).
 * Value = DB column name in mtb_scheme_master.
 *
 * If NSE ever renames or reorders a column, only this table needs updating.
 */
function getNseHeaderToDbMap(): array {
    return [
        'UNIQUE SR NO'                   => 'unique_sr_no',
        'SCHEME CODE'                    => 'scheme_code',
        'RTA SCHEME CODE'                => 'rta_scheme_code',
        'AMC SCHEME CODE'                => 'amc_scheme_code',
        'ISIN'                           => 'isin',
        'AMC CODE'                       => 'amc_code',
        'SCHEME TYPE'                    => 'scheme_type',
        'PLAN TYPE'                      => 'plan_type',
        'SCHEME NAME'                    => 'scheme_name',
        'PURCHASE ALLOWED'               => 'purchase_allowed',
        'PURCHASE TRANSACTION MODE'      => 'purchase_transaction_mode',
        'NEW PURCHASE MIN AMOUNT'        => 'new_purchase_min_amount',
        'ADDITIONAL PURCHASE MIN AMOUNT' => 'additional_purchase_min_amount',
        'ADDITIONAL PURCHASE MAX AMOUNT' => 'additional_purchase_max_amount',
        'PURCHASE AMOUNT MULTIPLIER'     => 'purchase_amount_multiplier',
        'PURCHASE CUTOFF TIME'           => 'purchase_cutoff_time',
        'REDEMPTION ALLOWED'             => 'redemption_allowed',
        'REDEMPTION TRANSACTION MODE'    => 'redemption_transaction_mode',
        'REDEMPTION MIN QTY'             => 'redemption_min_qty',
        'REDEMPTION QTY MULTIPLIER'      => 'redemption_qty_multiplier',
        'REDEMPTION MAX QTY'             => 'redemption_max_qty',
        'REDEMPTION MIN AMOUNT'          => 'redemption_min_amount',
        'REDEMPTION MAX AMOUNT'          => 'redemption_max_amount',
        'REDEMPTION AMOUNT MULTIPLIER'   => 'redemption_amount_multiplier',
        'REDEMPTION CUTOFF TIME'         => 'redemption_cutoff_time',
        'RTA AGENT CODE'                 => 'rta_agent_code',
        'AMC ACTIVE FLAG'                => 'amc_active_flag',
        'DIV REINVEST FLAG'              => 'div_reinvest_flag',
        'SIP ALLOWED'                    => 'sip_allowed',
        'STP ENABLED'                    => 'stp_enabled',
        'SWP ENABLED'                    => 'swp_enabled',
        'SWITCH ALLOWED'                 => 'switch_allowed',
        'SETTLEMENT TYPE'                => 'settlement_type',
        'AMC IND'                        => 'amc_ind',
        'FACE VALUE'                     => 'face_value',
        'SCHEME START DATE'              => 'scheme_start_date',
        'MATURITY DATE'                  => 'maturity_date',
        'EXIT LOAD FLAG'                 => 'exit_load_flag',
        'EXIT LOAD'                      => 'exit_load',
        'LOCK IN PERIOD_FLAG'            => 'lock_in_period_flag',  // NSE uses underscore here
        'LOCK IN PERIOD FLAG'            => 'lock_in_period_flag',  // fallback if they fix it
        'LOCK IN PERIOD'                 => 'lock_in_period',
        'CHANNEL PARTNER CODE'           => 'channel_partner_code',
        'REOPENING DATE'                 => 'reopening_date',
        'OPEN/CLOSE ENDED SCHEME'        => 'open_close_ended_scheme',
        'OPEN CLOSE ENDED SCHEME'        => 'open_close_ended_scheme', // fallback normalised
    ];
}

/** Columns that contain dates — need convertDateFormat() applied */
function getDateDbColumns(): array {
    return ['scheme_start_date', 'maturity_date', 'reopening_date'];
}

/** Columns that default to '0' when empty instead of '' */
function getNumericDbColumns(): array {
    return [
        'new_purchase_min_amount', 'additional_purchase_min_amount',
        'additional_purchase_max_amount', 'purchase_amount_multiplier',
        'redemption_min_qty', 'redemption_qty_multiplier', 'redemption_max_qty',
        'redemption_min_amount', 'redemption_max_amount', 'redemption_amount_multiplier',
        'face_value', 'exit_load', 'lock_in_period',
    ];
}

/**
 * Parse the raw pipe-delimited text into an array of scheme data arrays.
 *
 * @param string $headerLine  The raw header line from the NSE response (not yet shifted off $lines)
 * @param array  $lines       Data lines only (header already removed by caller)
 * @param string $amcFilter   'ALL' or a specific AMC code
 * Returns ['schemes' => [...], 'errors' => [...], 'unmapped_headers' => [...]]
 */
function parseSchemeLines(string $headerLine, array $lines, string $amcFilter): array {
    $schemes         = [];
    $errors          = [];
    $unmappedHeaders = [];

    // ── Build index map from the actual header line ───────────────────────
    // e.g. $colIndex['SCHEME CODE'] = 1, $colIndex['AMC CODE'] = 5, etc.
    $colIndex    = buildHeaderMap($headerLine);
    $nseToDb     = getNseHeaderToDbMap();
    $dateCols    = array_flip(getDateDbColumns());
    $numericCols = array_flip(getNumericDbColumns());

    // Warn about any NSE headers we don't have a mapping for
    foreach ($colIndex as $nseHeader => $pos) {
        if (!isset($nseToDb[$nseHeader])) {
            $unmappedHeaders[] = $nseHeader . ' (col ' . $pos . ')';
        }
    }

    // Mandatory columns — abort if these are missing from the header
    $requiredNseHeaders = ['SCHEME CODE', 'AMC CODE', 'SCHEME TYPE', 'SCHEME NAME'];
    foreach ($requiredNseHeaders as $req) {
        if (!isset($colIndex[$req])) {
            return [
                'schemes'          => [],
                'errors'           => ["Header line missing required column: '$req'"],
                'unmapped_headers' => $unmappedHeaders,
            ];
        }
    }

    // ── Parse every data line ─────────────────────────────────────────────
    foreach ($lines as $idx => $line) {
        $line = trim($line);
        if ($line === '') continue;

        $cols = explode('|', $line);

        // Get mandatory fields by header position (not hardcoded index)
        $schemeCode = trim($cols[$colIndex['SCHEME CODE']] ?? '');
        if ($schemeCode === '') {
            $errors[] = 'Line ' . ($idx + 1) . ': missing scheme code';
            continue;
        }

        $amcCode = trim($cols[$colIndex['AMC CODE']] ?? '');

        // AMC filter
        if ($amcFilter !== 'ALL' && $amcFilter !== '' && $amcCode !== $amcFilter) {
            continue;
        }

        // ── Map every NSE header → DB column dynamically ──────────────────
        $row = [];
        foreach ($nseToDb as $nseHeader => $dbCol) {
            if (!isset($colIndex[$nseHeader])) {
                // Column not present in this file version — use safe default
                $row[$dbCol] = isset($numericCols[$dbCol]) ? '0' : '';
                continue;
            }
            $raw = trim($cols[$colIndex[$nseHeader]] ?? '');

            // Apply date conversion for date columns
            if (isset($dateCols[$dbCol])) {
                $row[$dbCol] = convertDateFormat($raw);
                continue;
            }

            // Empty numeric columns default to '0'
            if ($raw === '' && isset($numericCols[$dbCol])) {
                $row[$dbCol] = '0';
                continue;
            }

            $row[$dbCol] = $raw;
        }

        // Ensure scheme_code is set (may have been overwritten by map loop)
        $row['scheme_code'] = $schemeCode;
        $row['amc_code']    = $amcCode;

        // Clean scheme name
        $row['scheme_name'] = trim(preg_replace('/\s+/', ' ', $row['scheme_name'] ?? ''));

        // Derive computed columns (uppercase once, reused by both functions)
        $schemeNameU       = strtoupper($row['scheme_name']);
        $schemeTypeU       = strtoupper($row['scheme_type'] ?? '');
        $row['fund_cat']   = deriveFundCategory($schemeTypeU, $schemeNameU);
        $row['cat_tag']    = deriveCatTag($schemeNameU);

        $schemes[] = $row;
    }

    return ['schemes' => $schemes, 'errors' => $errors, 'unmapped_headers' => $unmappedHeaders];
}

/* ═══════════════════════════════════════════════════════════════════════════
   AJAX — STREAMING PROGRESS HANDLER (fetch_progress)
   FIX 2: streams NDJSON chunks so the browser gets live updates
═══════════════════════════════════════════════════════════════════════════ */
/* ═══════════════════════════════════════════════════════════════════════════
   DIAGNOSTICS — GET: getsch-from-Nse.php?action=diagnostics
   Opens in any browser tab. Shows exactly why insert/update counts are wrong.
═══════════════════════════════════════════════════════════════════════════ */
if ($action === 'diagnostics') {
    header('Content-Type: application/json');
    $testCode = '__DIAG_TEST_' . time();
    $diag = $db->getSchemeMasterDiagnostics($testCode);

    $onDupWorks   = ($diag['storedName'] === 'Test Row UPDATED');
    $infoWorks    = (strpos((string)$diag['info2'], 'Duplicates: 1') !== false);

    echo json_encode([
        'total_rows_in_table'    => $diag['totalRows'],
        'distinct_scheme_codes'  => $diag['distinctCodes'],
        'duplicate_rows_exist'   => ($diag['totalRows'] !== $diag['distinctCodes']),
        'unique_key_exists'      => $diag['hasUniqueKey'],
        'duplicate_samples'      => $diag['duplicateSamples'],
        'upsert_test' => [
            'first_insert_info'      => $diag['info1'],
            'first_insert_affected'  => $diag['affected1'],      // expect 1
            'second_insert_info'     => $diag['info2'],          // may be null on WAMP — that is OK
            'second_insert_affected' => $diag['affected2'],      // expect 2 (update) or 0 (no-op)
            'stored_name_after_update' => $diag['storedName'],   // expect "Test Row UPDATED"
            'pre_count_method_works' => ($diag['preCount'] === 1), // expect true
            'on_duplicate_key_works' => $onDupWorks,     // expect true — THE key check
            'info_string_works'      => $infoWorks,      // may be false on WAMP — OK now
        ],
        'counting_method'        => 'pre-count (reliable on all MySQL versions)',
        'verdict' => !$diag['hasUniqueKey']
            ? 'PROBLEM: UNIQUE KEY missing — ON DUPLICATE KEY never fires.'
            : (!$onDupWorks
                ? 'PROBLEM: ON DUPLICATE KEY is not updating rows — check UNIQUE KEY is on scheme_code.'
                : ($diag['totalRows'] !== $diag['distinctCodes']
                    ? 'PROBLEM: Duplicate rows exist — run deduplicateSchemeMaster() to clean up.'
                    : 'OK: Everything is working correctly. Counts use pre-count method which is accurate.')),
    ], JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'fetch_progress') {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Accel-Buffering: no');  // disable nginx buffering
    header('Cache-Control: no-cache');

    // Flush helper
    $flush = function (array $payload): void {
        echo json_encode($payload) . "\n";
        if (ob_get_level()) ob_flush();
        flush();
    };

    $amcFilter = trim($_POST['amc'] ?? 'ALL');

    // ── Step 1: fetch from NSE ─────────────────────────────────────────────
    $flush(['stage' => 'fetching', 'msg' => 'Contacting NSE API…', 'pct' => 5]);

    require_once '../api/nseapiconnect.php';
    $nseService = new NseApiConnect();
    $apiResult  = $nseService->getMasterFromNse(['file_type' => 'SCH']);

    if (!is_array($apiResult) || !isset($apiResult['response']) || !is_string($apiResult['response'])) {
        $flush(['stage' => 'error', 'msg' => 'Invalid or empty response from NSE API']);
        exit;
    }

    $text = trim($apiResult['response']);
    if ($text === '') {
        $flush(['stage' => 'error', 'msg' => 'Empty response from NSE API']);
        exit;
    }

    // ── Step 2: parse lines ────────────────────────────────────────────────
    $flush(['stage' => 'parsing', 'msg' => 'Parsing scheme data…', 'pct' => 15]);

    $lines = explode("\n", $text);

    // Extract header line — always the first non-empty line
    // Pass it to parseSchemeLines so column positions are read from the
    // actual file, not hardcoded indices. If NSE reorders columns tomorrow
    // this code still maps correctly.
    $headerLine = '';
    while (count($lines) > 0 && trim($lines[0]) === '') {
        array_shift($lines);   // skip any leading blank lines
    }
    $firstLine = strtoupper(trim($lines[0] ?? ''));
    $hasHeader = (strpos($firstLine, 'SCHEME') !== false) || (strpos($firstLine, 'UNIQUE SR NO') !== false);
    if ($hasHeader) {
        $headerLine = array_shift($lines);  // remove and keep the raw header
    }

    if ($headerLine === '') {
        $flush(['stage' => 'error', 'msg' => 'No header line found in NSE response — cannot map columns']);
        exit;
    }

    $parsed           = parseSchemeLines($headerLine, $lines, $amcFilter);
    $schemes          = $parsed['schemes'];
    $errors           = $parsed['errors'];
    $unmappedHeaders  = $parsed['unmapped_headers'] ?? [];
    $total            = count($schemes);

    // Warn about any new NSE columns we don't have a DB mapping for yet
    if (!empty($unmappedHeaders)) {
        foreach ($unmappedHeaders as $uh) {
            $errors[] = 'New/unmapped NSE header (ignored): ' . $uh;
        }
    }

    if ($total === 0) {
        $flush(['stage' => 'done', 'msg' => 'No matching schemes found', 'total' => 0,
                'saved' => 0, 'updated' => 0, 'errors' => $errors]);
        exit;
    }

    $flush(['stage' => 'saving', 'msg' => "Saving $total schemes to database…",
            'pct' => 20, 'total' => $total]);

    // ── Step 3: batch upsert ───────────────────────────────────────────────
    // FIX 1: process in chunks of BATCH_SIZE
    $saved   = 0;
    $updated = 0;
    $previewRows = [];   // first 100 inserted/updated for the table display

    $batches = array_chunk($schemes, BATCH_SIZE);
    $batchCount = count($batches);

    foreach ($batches as $batchIdx => $batch) {
        try {
            $result = $db->batchUpsertSchemes($batch);
            $saved   += $result['inserted'] ?? 0;
            $updated += $result['updated']  ?? 0;
        } catch (Exception $e) {
            $errors[] = 'Batch ' . ($batchIdx + 1) . ' failed: ' . $e->getMessage();
            error_log('getsch batchUpsert error batch ' . ($batchIdx + 1) . ': ' . $e->getMessage());
        }

        // Collect preview rows from first few batches
        if (count($previewRows) < 100) {
            foreach ($batch as $row) {
                if (count($previewRows) >= 100) break;
                $previewRows[] = [
                    'scheme_code' => $row['scheme_code'],
                    'amc_code'    => $row['amc_code'],
                    'scheme_name' => $row['scheme_name'],
                    'isin'        => $row['isin'],
                    'scheme_type' => $row['scheme_type'],
                    'fund_cat'    => $row['fund_cat'],
                ];
            }
        }

        // Stream progress after each batch
        $pct = 20 + (int)(($batchIdx + 1) / $batchCount * 75);
        $flush([
            'stage'   => 'saving',
            'pct'     => $pct,
            'msg'     => 'Batch ' . ($batchIdx + 1) . '/' . $batchCount . ' saved…',
            'saved'   => $saved,
            'updated' => $updated,
        ]);
    }

    // FIX 3: single summary log instead of one per scheme
    error_log("getsch-from-Nse: AMC=$amcFilter total=$total inserted=$saved updated=$updated errors=" . count($errors) . " unmapped=" . count($unmappedHeaders));

    // Populate asset types after the master table is updated
    $flush(['stage' => 'populating_assets', 'msg' => 'Populating asset types for all schemes...', 'pct' => 98]);
    $assetTypesAffected = 0;
    try {
        $assetTypesAffected = $db->populateAllAssetTypes();
        error_log("getsch-from-Nse: Populated asset types for $assetTypesAffected rows.");
    } catch (Exception $e) {
        $errors[] = 'Asset Type Population Failed: ' . $e->getMessage();
        error_log('getsch-from-Nse: Asset Type Population Failed: ' . $e->getMessage());
    }

    $finalMsg = "Complete: inserted $saved, updated $updated out of $total schemes.";
    if ($assetTypesAffected >= 0) { // Show even if 0 rows affected
        $finalMsg .= " Asset types populated for $assetTypesAffected rows.";
    }

    $flush([
        'stage'            => 'done',
        'pct'              => 100,
        'msg'              => $finalMsg,
        'total'            => $total,
        'saved'            => $saved,
        'updated'          => $updated,
        'rows'             => $previewRows,
        'errors'           => array_slice($errors, 0, 50),
        'error_count'      => count($errors),
        'unmapped_headers' => $unmappedHeaders,   // new NSE columns we don't map yet
    ]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
   AJAX — LEGACY SINGLE-SHOT HANDLER (fetch_schemes)
   Kept for backward compatibility. Delegates to the same parse+batch logic
   but returns a single JSON blob (no streaming).
═══════════════════════════════════════════════════════════════════════════ */
if ($action === 'fetch_schemes') {
    header('Content-Type: application/json');

    $amcFilter = trim($_POST['amc'] ?? 'ALL');

    require_once '../api/nseapiconnect.php';
    $nseService = new NseApiConnect();
    $apiResult  = $nseService->getMasterFromNse(['file_type' => 'SCH']);

    if (!is_array($apiResult) || empty($apiResult['response'])) {
        echo json_encode(['success' => false, 'msg' => 'No response from NSE API']);
        exit;
    }

    $text  = trim($apiResult['response']);
    $lines = explode("\n", $text);

    // Extract header line dynamically — same as fetch_progress
    while (count($lines) > 0 && trim($lines[0]) === '') { array_shift($lines); }
    $first      = strtoupper(trim($lines[0] ?? ''));
    $headerLine = '';
    if ((strpos($first, 'SCHEME') !== false) || (strpos($first, 'UNIQUE SR NO') !== false)) {
        $headerLine = array_shift($lines);
    }
    if ($headerLine === '') {
        echo json_encode(['success' => false, 'msg' => 'No header line in NSE response']);
        exit;
    }

    $parsed          = parseSchemeLines($headerLine, $lines, $amcFilter);
    $schemes         = $parsed['schemes'];
    $errors          = $parsed['errors'];
    $unmappedHeaders = $parsed['unmapped_headers'] ?? [];
    foreach ($unmappedHeaders as $uh) {
        $errors[] = 'New/unmapped NSE header (ignored): ' . $uh;
    }

    $saved = $updated = 0;
    $previewRows = [];

    foreach (array_chunk($schemes, BATCH_SIZE) as $batch) {
        try {
            $r = $db->batchUpsertSchemes($batch);
            $saved   += $r['inserted'] ?? 0;
            $updated += $r['updated']  ?? 0;
        } catch (Exception $e) {
            $errors[] = 'Batch failed: ' . $e->getMessage();
        }
        if (count($previewRows) < 100) {
            foreach ($batch as $row) {
                if (count($previewRows) >= 100) break;
                $previewRows[] = [
                    'scheme_code' => $row['scheme_code'], 'amc_code'  => $row['amc_code'],
                    'scheme_name' => $row['scheme_name'], 'isin'      => $row['isin'],
                    'scheme_type' => $row['scheme_type'], 'fund_cat'  => $row['fund_cat'],
                ];
            }
        }
    }

    error_log("getsch-from-Nse: total=" . count($schemes) . " inserted=$saved updated=$updated errors=" . count($errors));

    // Populate asset types after the master table is updated
    try {
        $db->populateAllAssetTypes();
        error_log("getsch-from-Nse (legacy): Populated asset types.");
    } catch (Exception $e) {
        $errors[] = 'Asset Type Population Failed: ' . $e->getMessage();
        error_log('getsch-from-Nse (legacy): Asset Type Population Failed: ' . $e->getMessage());
    }

    echo json_encode([
        'success'     => true,
        'total'       => count($schemes),
        'saved'       => $saved,
        'updated'     => $updated,
        'rows'        => $previewRows,
        'errors'      => array_slice($errors, 0, 50),
        'error_count' => count($errors),
    ]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
   PAGE SHELL (AJAX fragment loaded by index.php)
   FIX 9: no <!DOCTYPE> / full page — matches pattern of the rest of the admin
═══════════════════════════════════════════════════════════════════════════ */
$amcs = $db->getAmcMaster();
?>

<style>
    .nse-wrapper { padding: 16px; }
    .nse-header {
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        color: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 18px;
    }
    .nse-header h5 { margin: 0; font-weight: 700; }
    .nse-controls {
        background: #f8f9fa; border: 1px solid #e0e0e0;
        padding: 14px 16px; border-radius: 6px; margin-bottom: 16px;
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .nse-controls label { font-size: 12px; font-weight: 600; color: #555; margin: 0; }
    .nse-controls select {
        padding: 7px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; min-width: 220px;
    }
    .nse-btn {
        padding: 8px 20px; font-size: 13px; font-weight: 600;
        border: none; border-radius: 4px; cursor: pointer; transition: background .2s;
    }
    .nse-btn-primary { background: #0d6efd; color: #fff; }
    .nse-btn-primary:hover { background: #0a58ca; }
    .nse-btn-primary:disabled { background: #6c757d; cursor: not-allowed; }

    .nse-progress-wrap {
        background: #e9ecef; border-radius: 4px; height: 22px;
        overflow: hidden; margin-bottom: 10px; display: none;
    }
    .nse-progress-bar {
        height: 100%; background: #28a745; width: 0%;
        transition: width .3s ease; display: flex; align-items: center;
        padding-left: 8px; font-size: 11px; color: #fff; font-weight: 600; white-space: nowrap;
    }
    .nse-status { font-size: 13px; font-weight: 600; margin: 8px 0; min-height: 20px; }
    .nse-status.success { color: #28a745; }
    .nse-status.error   { color: #dc3545; }

    .nse-counters {
        display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px;
    }
    .nse-counter-card {
        background: #fff; border: 1px solid #e0e0e0; border-radius: 6px;
        padding: 8px 16px; font-size: 12px; min-width: 110px; text-align: center;
        box-shadow: 0 1px 4px rgba(0,0,0,.05);
    }
    .nse-counter-card .val { font-size: 22px; font-weight: 700; line-height: 1.2; }
    .nse-counter-card.inserted .val { color: #28a745; }
    .nse-counter-card.updated  .val { color: #0d6efd; }
    .nse-counter-card.total    .val { color: #6c757d; }
    .nse-counter-card.errors   .val { color: #dc3545; }

    .nse-error-box {
        background: #fff3cd; border: 1px solid #ffc107;
        padding: 10px 14px; border-radius: 4px; font-size: 12px;
        margin-bottom: 12px; display: none;
    }

    .nse-table-container { overflow-x: auto; max-height: 480px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; }
    .nse-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .nse-table th {
        background: #34495e; color: #fff; padding: 9px 8px;
        text-align: left; position: sticky; top: 0; z-index: 5;
        border: 1px solid #455a6a; font-size: 11px;
    }
    .nse-table td { padding: 7px 8px; border: 1px solid #ddd; }
    .nse-table tr:nth-child(even) { background: #f9f9f9; }
    .nse-table tr:hover { background: #f0f4ff; }
    .badge-fundcat {
        display: inline-block; padding: 2px 7px; border-radius: 10px;
        font-size: 10px; font-weight: 600; background: #e9ecef; color: #495057;
    }
</style>

<div class="nse-wrapper">

    <div class="nse-header">
        <h5><i class="fas fa-sync-alt me-2"></i>Update Scheme Master from NSE</h5>
        <div style="font-size:12px;opacity:.8;margin-top:4px">
            Fetches the full SCH master file from NSE API and upserts into local scheme table
        </div>
    </div>

    <!-- Controls -->
    <div class="nse-controls">
        <label for="nse-amc-select"><i class="fas fa-filter me-1"></i>Filter by AMC</label>
        <select id="nse-amc-select">
            <option value="ALL">ALL AMCs</option>
            <option value="NEW">New Schemes Only</option>
            <?php foreach ($amcs as $amc): ?>
                <option value="<?php echo htmlspecialchars($amc['code']); ?>">
                    <?php echo htmlspecialchars($amc['details']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="nse-btn nse-btn-primary" id="nse-fetch-btn">
            <i class="fas fa-cloud-download-alt me-1"></i>Fetch &amp; Update Schemes
        </button>
        <button class="nse-btn" id="nse-diag-btn"
                style="background:#6c757d;color:#fff"
                onclick="window.open('getsch-from-Nse.php?action=diagnostics','_blank')">
            <i class="fas fa-stethoscope me-1"></i>Diagnose DB
        </button>
    </div>

    <!-- Progress bar (hidden until fetch starts) -->
    <div class="nse-progress-wrap" id="nse-progress-wrap">
        <div class="nse-progress-bar" id="nse-progress-bar">0%</div>
    </div>

    <!-- Status text -->
    <div class="nse-status" id="nse-status"></div>

    <!-- Live counters -->
    <div class="nse-counters" id="nse-counters" style="display:none">
        <div class="nse-counter-card total">
            <div class="val" id="cnt-total">0</div>
            <div>Total</div>
        </div>
        <div class="nse-counter-card inserted">
            <div class="val" id="cnt-inserted">0</div>
            <div>Inserted</div>
        </div>
        <div class="nse-counter-card updated">
            <div class="val" id="cnt-updated">0</div>
            <div>Updated</div>
        </div>
        <div class="nse-counter-card errors">
            <div class="val" id="cnt-errors">0</div>
            <div>Errors</div>
        </div>
    </div>

    <!-- Error details -->
    <div class="nse-error-box" id="nse-error-box"></div>

    <!-- Results table -->
    <div class="nse-table-container" id="nse-table-container" style="display:none">
        <table class="nse-table">
            <thead>
                <tr>
                    <th>Scheme Code</th>
                    <th>AMC</th>
                    <th>Scheme Name</th>
                    <th>ISIN</th>
                    <th>Type</th>
                    <th>Fund Category</th>
                </tr>
            </thead>
            <tbody id="nse-tbody"></tbody>
        </table>
    </div>
</div>

<script>
(function () {
    'use strict';

    var ESC = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' };
    var ESC_RE = /[&<>"']/g;
    function esc(s) {
        return s ? String(s).replace(ESC_RE, function(c){ return ESC[c]; }) : '';
    }
    function $(id) { return document.getElementById(id); }

    var btn         = $('nse-fetch-btn');
    var statusEl    = $('nse-status');
    var progressWrap= $('nse-progress-wrap');
    var progressBar = $('nse-progress-bar');
    var counters    = $('nse-counters');
    var errorBox    = $('nse-error-box');
    var tableWrap   = $('nse-table-container');
    var tbody       = $('nse-tbody');

    function setProgress(pct, label) {
        progressWrap.style.display = 'block';
        progressBar.style.width = pct + '%';
        progressBar.textContent = (label || pct + '%');
    }
    function setCounters(total, saved, updated, errCnt) {
        counters.style.display = 'flex';
        $('cnt-total').textContent    = total   || 0;
        $('cnt-inserted').textContent = saved   || 0;
        $('cnt-updated').textContent  = updated || 0;
        $('cnt-errors').textContent   = errCnt  || 0;
    }
    function reset() {
        statusEl.textContent = '';
        statusEl.className   = 'nse-status';
        progressWrap.style.display   = 'none';
        counters.style.display       = 'none';
        errorBox.style.display       = 'none';
        tableWrap.style.display      = 'none';
        tbody.innerHTML              = '';
        setProgress(0, '');
    }

    btn.addEventListener('click', function () {
        reset();
        btn.disabled = true;
        var amc = $('nse-amc-select').value;

        statusEl.textContent = 'Starting…';
        setProgress(5, 'Starting…');

        // ── FIX 2: use streaming fetch (fetch_progress action) ─────────────
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'getsch-from-Nse.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        var readPos = 0;

        xhr.onprogress = function () {
            // Parse all new complete lines since last onprogress event
            var chunk = xhr.responseText.slice(readPos);
            var lines = chunk.split('\n');
            // Last element may be incomplete — leave it for next event
            readPos += chunk.lastIndexOf('\n') + 1;

            lines.forEach(function (line) {
                line = line.trim();
                if (!line) return;
                try {
                    var d = JSON.parse(line);
                    handleChunk(d);
                } catch (e) { /* incomplete line, skip */ }
            });
        };

        xhr.onload = function () {
            // Flush any remaining bytes
            var remaining = xhr.responseText.slice(readPos).trim();
            if (remaining) {
                remaining.split('\n').forEach(function (line) {
                    line = line.trim();
                    if (!line) return;
                    try { handleChunk(JSON.parse(line)); } catch(e){}
                });
            }
            btn.disabled = false;
        };

        xhr.onerror = function () {
            statusEl.textContent = 'Network error — please try again.';
            statusEl.className   = 'nse-status error';
            btn.disabled = false;
        };

        xhr.send('action=fetch_progress&amc=' + encodeURIComponent(amc));
    });

    function handleChunk(d) {
        if (!d || !d.stage) return;

        if (d.pct !== undefined) setProgress(d.pct, d.msg || '');
        if (d.msg) { statusEl.textContent = d.msg; }

        if (d.stage === 'saving' || d.stage === 'populating_assets') {
            setCounters(d.total || 0, d.saved || 0, d.updated || 0, 0);
        }

        if (d.stage === 'error') {
            statusEl.textContent = '✗ ' + (d.msg || 'Unknown error');
            statusEl.className   = 'nse-status error';
            setProgress(100, 'Error');
            return;
        }

        if (d.stage === 'done') {
            var errCnt = d.error_count || (d.errors ? d.errors.length : 0);
            setCounters(d.total, d.saved, d.updated, errCnt);
            statusEl.textContent = '✓ ' + (d.msg || 'Complete');
            statusEl.className   = 'nse-status success';
            setProgress(100, '100%');

            // Show error details if any
            if (d.errors && d.errors.length > 0) {
                errorBox.innerHTML = '<strong>⚠ ' + errCnt + ' parse errors:</strong><br>' +
                    d.errors.slice(0, 10).map(esc).join('<br>') +
                    (errCnt > 10 ? '<br>… and ' + (errCnt - 10) + ' more' : '');
                errorBox.style.display = 'block';
            }

            // Populate table with preview rows
            if (d.rows && d.rows.length > 0) {
                // FIX: array-push + join for table render, not per-row innerHTML
                var parts = [];
                d.rows.forEach(function (r) {
                    parts.push(
                        '<tr><td>' + esc(r.scheme_code) + '</td>' +
                        '<td>' + esc(r.amc_code) + '</td>' +
                        '<td>' + esc(r.scheme_name) + '</td>' +
                        '<td>' + esc(r.isin) + '</td>' +
                        '<td>' + esc(r.scheme_type) + '</td>' +
                        '<td><span class="badge-fundcat">' + esc(r.fund_cat) + '</span></td></tr>'
                    );
                });
                tbody.innerHTML = parts.join('');
                tableWrap.style.display = 'block';
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#999">No new schemes in this run</td></tr>';
                tableWrap.style.display = 'block';
            }
        }
    }
}());
</script>