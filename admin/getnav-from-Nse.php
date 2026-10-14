
<?php
require_once('auth_guard.php');

// getnav-from-Nse.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/database-nseinvest-Api.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
	$mfdb = new DB_NSEINVEST_API();  
    $apiResult = $mfdb->callNseApi('/v2/reports/MASTER_DOWNLOAD', [
        "file_type" => "NAV"
    ]);
    $httpCode = $apiResult['http_code'] ?? 0;
    $response = $apiResult['response'] ?? '';
} catch (Throwable $e) {
    echo "<h2>API Error</h2><pre>" . h($e->getMessage()) . "</pre>";
    exit;
}


// Save raw response for inspection
//$responseFile = __DIR__ . '/nse_api_response.json';
//@file_put_contents($responseFile, $response);

/**
 * NAV parser: pipe separated fallback
 */
function parse_pipe_nav(string $text): array {
    $text = trim($text);
    if ($text === '') return ['headers'=>[], 'rows'=>[]];
    $lines = preg_split('/\r\n|\n|\r/', $text);
    $rows = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 8) {
            $parts = array_pad($parts, 8, '');
        } elseif (count($parts) > 8) {
            $first = array_slice($parts, 0, 7);
            $last  = array_slice($parts, 7);
            $parts = array_merge($first, [implode('|', $last)]);
        }
        $rows[] = [
            'nav_date_raw'       => $parts[0],
            'scheme_code'        => $parts[1],
            'scheme_name'        => $parts[2],
            'rta_scheme_code'    => $parts[3],
            'div_reinvest_flag'  => $parts[4],
            'isin'               => $parts[5],
            'nav_value_raw'      => $parts[6],
            'rta_code'           => $parts[7],
        ];
    }
    $headers = [
        'NAV Date','SCHEME CODE','SCHEME NAME','RTA SCHEME CODE',
        'DIV. REINVEST FLAG','ISIN','NAV VALUE','RTA CODE'
    ];
    return ['headers'=>$headers, 'rows'=>$rows];
}

function toYmdDate(?string $s): ?string {
    if (!$s) return null;
    $ts = strtotime($s);
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}

// ------ Try JSON first; else treat as pipe ------
$decoded = json_decode($response, true);
$clientHeaders = [];
$clientRows    = [];

if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
    if (isset($decoded[0]) && is_array($decoded[0])) {
        $clientHeaders = array_keys($decoded[0]);
        $clientRows = $decoded;
    } else {
        $clientHeaders = ['value'];
        $clientRows = [['value' => json_encode($decoded)]];
    }
} else {
    $pipe = parse_pipe_nav($response);
    $clientHeaders = $pipe['headers'];
    $clientRows    = $pipe['rows'];
}

// Filter rows
$skipTokens = ['DIRECT'];//['DIRECT','IDCW','DIVIDEND','REINVESTMENT'];
$rowsToSave = [];
$rowsToDisplay = [];

foreach ($clientRows as $r) {
    if (!isset($r['scheme_name'])) continue;
    $nameU = strtoupper((string)$r['scheme_name']);
    $skip = false;
    foreach ($skipTokens as $t) {
        if (strpos($nameU, $t) !== false) { $skip = true; break; }
    }
    if ($skip) continue;

    // Build DB row
    $dbRow = [
        'nav_date'          => toYmdDate($r['nav_date_raw'] ?? null),
        'scheme_code'       => $r['scheme_code'] ?? null,
        'scheme_name'       => $r['scheme_name'] ?? null,
        'rta_scheme_code'   => $r['rta_scheme_code'] ?? null,
        'div_reinvest_flag' => $r['div_reinvest_flag'] ?? null,
        'isin'              => $r['isin'] ?? null,
        'nav'         => isset($r['nav_value_raw']) && is_numeric($r['nav_value_raw']) ? (float)$r['nav_value_raw'] : null,
        'rta_code'          => $r['rta_code'] ?? null,
    ];

    if (!empty($dbRow['scheme_code'])) {
        $rowsToSave[] = $dbRow;
    }

    $rowsToDisplay[] = $r;
}

// Save to DB
$saved = 0;
$errors = [];
if (!empty($rowsToSave)) {
    try {
        $mfdb->bulkUpsertNav($rowsToSave);
        $saved = count($rowsToSave);
    } catch (Throwable $t) {
        $errors[] = $t->getMessage();
    }
}

// Prepare JSON for frontend table
if (!empty($rowsToDisplay) && is_array($rowsToDisplay[0])) {
    $dataKeys = array_keys($rowsToDisplay[0]);
    $displayLabels = !empty($clientHeaders) ? $clientHeaders : $dataKeys;
} else {
    $dataKeys = $clientHeaders;
    $displayLabels = $clientHeaders;
}
$jsHeaderLabels = json_encode($displayLabels, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
$jsHeaderKeys   = json_encode($dataKeys,     JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
$jsRows         = json_encode($rowsToDisplay, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>NSEInvest - NAV Data</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body { font-family: Arial, sans-serif; margin: 18px; color: #222; }
        h1 { margin-bottom: 6px; font-size: 20px; }
        .meta { margin-bottom: 10px; color: #555; }
        .msg { padding:10px 12px; border-radius:6px; border:1px solid #e8e8e8; background:#fafafa; margin:10px 0; }
        .ok { color:#0a7a2f; }
        .err { color:#b00020; }
        .progress-wrap { width:100%; background:#eef3f7; border:1px solid #dde6ef; padding:4px; border-radius:6px; margin:8px 0; display:flex; align-items:center; gap:8px; }
        .progress-bar { height:12px; background:linear-gradient(90deg, #4aa3ff, #2b74d0); width:0%; border-radius:4px; transition: width 200ms linear; }
        .progress-text { min-width:90px; font-size:13px; color:#333; text-align:right; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; vertical-align: middle; }
        th { background-color: #f6f6f6; font-weight:600; position: sticky; top:0; z-index:1; }
        tr:nth-child(even) { background-color: #fbfbfb; }
        tr:hover { background-color: #f1f9ff; }
        #navTableContainer { overflow:auto; max-height:60vh; border:1px solid #f0f0f0; border-radius:6px; padding:6px; }
    </style>
</head>
<body>
    <h1>NSEInvest - NAV Data</h1>
    <div class="meta">
        <!--<strong>HTTP Status:</strong> <?php echo h((string)$httpCode); ?> |-->
        <span>Row Saved : <span class="ok"><?php echo (int)$saved; ?></span></span>
        <?php if ($errors): ?>
            | <span class="err">DB Error: <?php echo h(implode('; ', $errors)); ?></span>
        <?php endif; ?>
        <!--<br><small>Raw response saved to <code><?php echo h($responseFile); ?></code></small>-->
    </div>

<?php if (!empty($rowsToDisplay) && !empty($displayLabels)): ?>
    <div class="progress-wrap">
        <div style="flex:1; min-width:0;">
            <div style="background:transparent; border-radius:6px; overflow:hidden;">
                <div id="progressBar" class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
            </div>
        </div>
        <div class="progress-text" id="progressText">0%</div>
    </div>
    <div id="navTableContainer">
        <table id="navTable">
            <thead><tr id="navTableHead"></tr></thead>
            <tbody id="navTableBody"></tbody>
        </table>
    </div>
<script>
/*
  NSE NAV table renderer
  - Safe to call multiple times (no const re-declarations)
  - Cancels previous render when called again
  - Shows minutes + seconds when complete
  - Call pattern: window.NSENavRenderer.render(labelsArray, keysArray, rowsArray)
  - This script auto-runs with PHP-provided data below.
*/
(function (window, document) {
    const DEFAULT_DELAY = 60;

    // Build (or replace) a single renderer object on window
    const renderer = {
        renderSessionId: 0,

        // main entry
        render: function (labels, keys, rows) {
            // increment session id to cancel previous runs
            this.renderSessionId++;
            const sessionId = this.renderSessionId;
            const self = this;

            // ensure arrays
            labels = Array.isArray(labels) ? labels : [];
            keys = Array.isArray(keys) ? keys : [];
            rows = Array.isArray(rows) ? rows : [];

            // DOM refs (safe-guard)
            const headRow = document.getElementById('navTableHead');
            const body = document.getElementById('navTableBody');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const container = document.getElementById('navTableContainer');

            if (!headRow || !body || !progressBar || !progressText) {
                console.warn('NSENavRenderer: Missing required DOM elements (navTableHead/navTableBody/progressBar/progressText). Aborting render.');
                return;
            }

            // Reset UI
            headRow.innerHTML = '';
            body.innerHTML = '';
            progressBar.style.width = '0%';
            progressBar.style.background = ''; // revert to CSS gradient
            progressBar.setAttribute('aria-valuenow', 0);
            progressText.textContent = '0%';

            // Create header
            const thSl = document.createElement('th');
            thSl.textContent = 'Sl. No.';
            headRow.appendChild(thSl);
            labels.forEach(label => {
                const th = document.createElement('th');
                th.textContent = label ?? '';
                headRow.appendChild(th);
            });

            // If keys empty but rows have data, infer keys from first row
            if ((!keys || keys.length === 0) && rows.length > 0) {
                const first = rows[0];
                if (first && typeof first === 'object') {
                    keys = Object.keys(first);
                }
            }

            // chunk config based on size
            function chunkCfg(n) {
                if (n <= 100) return { chunk: 10, delay: 20 };
                if (n <= 500) return { chunk: 25, delay: 25 };
                if (n <= 2000) return { chunk: 80, delay: 20 };
                return { chunk: 200, delay: 10 };
            }
            const cfg = chunkCfg(rows.length);

            let rendered = 0;
            const total = rows.length;
            const startTime = (performance && performance.now) ? performance.now() : Date.now();

            // update progress display
            function updateProgress() {
                // stop if another render started
                if (sessionId !== self.renderSessionId) return;
                const pct = total === 0 ? 100 : Math.round((rendered / total) * 100);
                progressBar.style.width = pct + '%';
                progressBar.setAttribute('aria-valuenow', pct);

                if (pct >= 100) {
                    progressBar.style.background = 'green';

                    const now = (performance && performance.now) ? performance.now() : Date.now();
                    const elapsedMs = now - startTime;
                    const minutes = Math.floor(elapsedMs / 60000);
                    const seconds = Math.floor((elapsedMs % 60000) / 1000);
                    const secsStr = seconds.toString().padStart(2, '0');

                    progressText.textContent = `${pct}% (Completed in ${minutes} min ${secsStr} sec)`;
                } else {
                    progressText.textContent = pct + '%';
                }
            }

            // chunk rendering loop
            function renderChunk() {
                // cancel if this session is stale
                if (sessionId !== self.renderSessionId) return;

                if (rendered >= total) {
                    updateProgress();
                    return;
                }

                const end = Math.min(rendered + cfg.chunk, total);
                const frag = document.createDocumentFragment();

                for (let i = rendered; i < end; i++) {
                    const tr = document.createElement('tr');

                    // Sl. No.
                    const tdSl = document.createElement('td');
                    tdSl.textContent = String(i + 1);
                    tr.appendChild(tdSl);

                    // other columns by keys
                    for (const key of keys) {
                        const td = document.createElement('td');
                        let v = (rows[i] && rows[i][key] !== undefined) ? rows[i][key] : '';
                        if (v === null || v === undefined) v = '';
                        if (typeof v === 'object') {
                            try { td.textContent = JSON.stringify(v); } catch (e) { td.textContent = String(v); }
                        } else {
                            td.textContent = String(v);
                        }
                        tr.appendChild(td);
                    }

                    frag.appendChild(tr);
                }

                body.appendChild(frag);
                rendered = end;
                updateProgress();

                // scroll to bottom to show progress (non-blocking)
                try { container.scrollTop = container.scrollHeight; } catch (e) { /* ignore */ }

                // schedule next chunk
                setTimeout(renderChunk, cfg.delay ?? DEFAULT_DELAY);
            }

            // start the chunked rendering shortly
            setTimeout(renderChunk, 60);
        }
    };

    // assign (replace any previous)
    window.NSENavRenderer = renderer;

    // Auto-run the render with server-provided arrays (safe: not declaring consts)
    try {
        window.NSENavRenderer.render(
            <?php echo $jsHeaderLabels ?: '[]'; ?>,
            <?php echo $jsHeaderKeys   ?: '[]'; ?>,
            <?php echo $jsRows         ?: '[]'; ?>
        );
    } catch (e) {
        console.error('NSENavRenderer: render failed', e);
    }
})(window, document);
</script>

<?php else: ?>
    <div class="msg">No structured table data found (or all records filtered).</div>
    <pre class="msg"><?php echo h(substr($response,0,20000)); ?></pre>
<?php endif; ?>
</body>
</html>
