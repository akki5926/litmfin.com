<?php
require_once('auth_guard.php');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

// Prevent direct access to errors for AJAX
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

function toFloat($val) {
    if (is_numeric($val)) return (float)$val;
    if (is_string($val)) {
        $val = trim($val);
        $val = str_replace(['%', ','], '', $val);
        return is_numeric($val) ? (float)$val : 0;
    }
    return 0;
}

$fallbackMcapMap = [
    'INF179KC1BJ4' => [70,20,10],
    'INF179K01BB8' => [70,20,10],
    'INF179KC1JL3' => [65,25,10],
    'INF179KC1HL7' => [65,25,10],
    'INF179K01426' => [70,20,10],
    // (map shortened for brevity) - keep full map above in actual file
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_mcap') {
    if (!isset($_FILES['mcap_file']) || $_FILES['mcap_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid Excel file.']);
        exit;
    }

    $db = new MYSQLDB();
    $pdo = $db->getPDO();
    $file = $_FILES['mcap_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($file);
        $allSheets = $spreadsheet->getAllSheets();

        $recordsProcessed = 0;
        $errorRows = [];
        $successSummary = [];
        $fallbackUsed = [];
        $missingData = [];
        $totalFundsProcessed = [];

        $sql = "INSERT INTO tb_funds_mcap_data (isin, month_year, large_cap, mid_cap, small_cap, cash, debt)
                VALUES (:isin, :dt, :l, :m, :s, :c, :d)
                ON DUPLICATE KEY UPDATE large_cap = VALUES(large_cap), mid_cap = VALUES(mid_cap), small_cap = VALUES(small_cap), cash = VALUES(cash), debt = VALUES(debt)";

        $stmt = $pdo->prepare($sql);

        foreach ($allSheets as $sheet) {
            if ($sheet->getTitle() === 'Summary') continue;
            $sheetName = $sheet->getTitle();
            $highestRow = $sheet->getHighestRow();
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

            for ($row = 1; $row <= $highestRow; $row++) {
                $cellA = $sheet->getCell([1, $row])->getValue();
                if (!$cellA || strpos($cellA, 'ID:') !== 0) continue;

                preg_match('/ID:\s*([^|]+)/', $cellA, $matches);
                $isin = trim($matches[1] ?? '');
                if (!$isin) continue;

                $fundHeader = $sheet->getCell([1, max($row - 1, 1)])->getValue();
                $fundName = '';
                if ($fundHeader && str_contains($fundHeader, 'FUND:')) {
                    preg_match('/FUND:\s*(.*?)\s*\|/', $fundHeader, $m);
                    $fundName = $m[1] ?? '';
                }

                $totalFundsProcessed[$isin] = true;

                for ($col = 2; $col <= $highestColIndex; $col++) {
                    $monthValue = $sheet->getCell([$col, $row])->getValue();
                    if ($monthValue === null || $monthValue === '') continue;

                    try {
                        if (is_numeric($monthValue)) {
                            $dateObj = Date::excelToDateTimeObject($monthValue);
                        } else {
                            $dateObj = DateTime::createFromFormat('M-y', $monthValue) ?: new DateTime($monthValue);
                        }
                        if (!$dateObj) continue;
                        $formattedDate = $dateObj->format('Y-m-01');
                    } catch (Exception $e) {
                        continue;
                    }

                    $large = toFloat($sheet->getCell([$col, $row + 1])->getCalculatedValue());
                    $mid   = toFloat($sheet->getCell([$col, $row + 2])->getCalculatedValue());
                    $small = toFloat($sheet->getCell([$col, $row + 3])->getCalculatedValue());
                    $cash  = toFloat($sheet->getCell([$col, $row + 4])->getCalculatedValue());
                    $debt  = toFloat($sheet->getCell([$col, $row + 5])->getCalculatedValue());

                    $rawTotal = $large + $mid + $small + $cash + $debt;
                    if ($rawTotal > 0 && $rawTotal <= 1.01) {
                        $large *= 100; $mid *= 100; $small *= 100; $cash *= 100; $debt *= 100;
                    }

                    $total = $large + $mid + $small + $cash + $debt;
                    if ($total == 0) {
                        if (isset($fallbackMcapMap[$isin])) {
                            $vals = $fallbackMcapMap[$isin];
                            $large = $vals[0] ?? 0; $mid = $vals[1] ?? 0; $small = $vals[2] ?? 0; $cash = 0; $debt = 0;
                            $total = $large + $mid + $small;
                            if (!isset($fallbackUsed[$isin])) {
                                $fallbackUsed[$isin] = ['sheet' => $sheetName, 'fund_name' => $fundName, 'isin' => $isin, 'months' => []];
                            }
                            if (!in_array($formattedDate, $fallbackUsed[$isin]['months'], true)) {
                                $fallbackUsed[$isin]['months'][] = $formattedDate;
                            }
                        }
                        if ($total == 0) {
                            if (!isset($missingData[$isin])) {
                                $missingData[$isin] = ['sheet' => $sheetName, 'fund_name' => $fundName, 'isin' => $isin, 'months' => []];
                            }
                            if (!in_array($formattedDate, $missingData[$isin]['months'], true)) {
                                $missingData[$isin]['months'][] = $formattedDate;
                            }
                            continue;
                        }
                    }

                    if ($total > 100.5) {
                        $errorRows[] = ['sheet' => $sheetName, 'isin' => $isin, 'fund_name' => $fundName, 'month' => $formattedDate, 'reason' => "Invalid total ($total)"];
                        continue;
                    }

                    $stmt->execute([':isin' => $isin, ':dt' => $formattedDate, ':l' => $large, ':m' => $mid, ':s' => $small, ':c' => $cash, ':d' => $debt]);

                    $recordsProcessed++;
                    $successSummary[$isin]['sheet'] = $sheetName;
                    $successSummary[$isin]['fund_name'] = $fundName;
                    $successSummary[$isin]['months'][] = $formattedDate;
                    $successSummary[$isin]['count'] = ($successSummary[$isin]['count'] ?? 0) + 1;
                }
                $row += 8;
            }
        }

        ob_start();
        $totalFundsCount = count($totalFundsProcessed);
        $successCount = count($successSummary);
        $fallbackCount = count($fallbackUsed);
        $errorFundsCount = count(array_unique(array_column($errorRows, 'isin')));
        $missingCount = count($missingData);

        echo "<h5 class='text-success'><i class='fas fa-check-circle me-1'></i> Processed $recordsProcessed allocation points.</h5>";
        echo "<div class='d-flex flex-wrap gap-2 mb-3 mt-2'>";
        echo "<span class='badge bg-primary px-3 py-2 fs-6'>Total Funds: $totalFundsCount</span>";
        echo "<span class='badge bg-success px-3 py-2 fs-6'>Success: $successCount</span>";
        echo "<span class='badge bg-warning text-dark px-3 py-2 fs-6'>Fallback Used: $fallbackCount</span>";
        echo "<span class='badge bg-danger px-3 py-2 fs-6'>Error Funds: $errorFundsCount</span>";
        echo "<span class='badge bg-secondary px-3 py-2 fs-6'>Missing Data: $missingCount</span>";
        echo "</div>";

        if ($successSummary) {
            echo "<h6 class='text-primary mt-3'>Success Summary</h6>";
            echo "<div class='table-responsive' style='max-height:300px;'><table class='table table-sm table-bordered table-striped mb-0'>";
            echo "<thead class='table-light'><tr><th>Sheet</th><th>ISIN</th><th>Fund Name</th><th>From Month</th><th>To Month</th><th>Records</th></tr></thead><tbody>";
            foreach ($successSummary as $isin => $d) {
                sort($d['months']);
                $fromMonth = htmlspecialchars($d['months'][0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE);
                $toMonth = htmlspecialchars(end($d['months']) ?: '', ENT_QUOTES | ENT_SUBSTITUTE);
                $fundName = htmlspecialchars($d['fund_name'], ENT_QUOTES | ENT_SUBSTITUTE);
                echo "<tr><td>{$d['sheet']}</td><td>{$isin}</td><td style='max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='{$fundName}'>{$fundName}</td><td>{$fromMonth}</td><td>{$toMonth}</td><td>{$d['count']}</td></tr>";
            }
            echo "</tbody></table></div>";
        }

        if ($fallbackUsed) {
            echo "<h6 class='text-warning mt-3'>Fallback Used</h6>";
            echo "<div class='table-responsive' style='max-height:300px;'><table class='table table-sm table-bordered table-striped mb-0'>";
            echo "<thead class='table-light'><tr><th>Sheet</th><th>ISIN</th><th>Fund Name</th><th>From Month</th><th>To Month</th><th>Records</th></tr></thead><tbody>";
            foreach ($fallbackUsed as $d) {
                sort($d['months']);
                $fromMonth = htmlspecialchars($d['months'][0] ?? '', ENT_QUOTES | ENT_SUBSTITUTE);
                $toMonth = htmlspecialchars(end($d['months']) ?: '', ENT_QUOTES | ENT_SUBSTITUTE);
                $fundName = htmlspecialchars($d['fund_name'], ENT_QUOTES | ENT_SUBSTITUTE);
                echo "<tr><td>{$d['sheet']}</td><td>{$d['isin']}</td><td style='max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='{$fundName}'>{$fundName}</td><td>{$fromMonth}</td><td>{$toMonth}</td><td>" . count($d['months']) . "</td></tr>";
            }
            echo "</tbody></table></div>";
        }

        if ($errorRows) {
            echo "<h6 class='text-danger mt-3'>Errors Found: " . count($errorRows) . "</h6>";
            echo "<div class='table-responsive' style='max-height:300px;'><table class='table table-sm table-bordered table-striped mb-0'>";
            echo "<thead class='table-light'><tr><th>Sheet</th><th>ISIN</th><th>Month</th><th>Reason</th></tr></thead><tbody>";
            foreach ($errorRows as $err) {
                $reason = htmlspecialchars($err['reason'], ENT_QUOTES | ENT_SUBSTITUTE);
                echo "<tr><td>{$err['sheet']}</td><td>{$err['isin']}</td><td>{$err['month']}</td><td>{$reason}</td></tr>";
            }
            echo "</tbody></table></div>";
        }

        $html = ob_get_clean();
        echo json_encode(['success' => true, 'message' => 'Upload processed successfully.', 'html' => $html]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!-- HTML for the page loaded via AJAX -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Market Cap Data Management</h5>
    </div>
    <div class="card-body p-4">
        <div class="alert alert-secondary">
            <i class="fas fa-info-circle me-1"></i> Generate the MCAP Allocation Tool (Excel) containing active funds for data entry.
        </div>
        <div class="d-flex gap-2 mb-4">
            <button id="btnGenerateTool" class="btn btn-primary px-4 py-2" data-blank="0">
                <i class="fas fa-file-excel me-2"></i> Generate Filled MCAP Tool
            </button>
            <button id="btnGenerateBlankTool" class="btn btn-outline-primary px-4 py-2" data-blank="1">
                <i class="fas fa-file-alt me-2"></i> Generate Blank MCAP Tool
            </button>
        </div>
        <div id="generate_options" style="display:none;"></div>
        <div id="generate_status_msg"></div>

        <hr class="my-4">

        <h5 class="mb-3 text-success fw-bold"><i class="fas fa-upload me-2"></i> Upload Filled Market Cap Data</h5>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i> Select the filled <strong>Market_Cap_Allocation_Tool.xlsx</strong> file to update the database.
        </div>
        <form id="mcapUploadForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_mcap">
            <div class="input-group mb-3">
                <input type="file" name="mcap_file" id="mcap_file" class="form-control" accept=".xlsx" required>
                <button type="button" id="btnUploadMcap" class="btn btn-success px-4 fw-bold">
                    <i class="fas fa-cloud-upload-alt me-2"></i> Upload & Process
                </button>
            </div>
        </form>
        <div id="mcap_status_msg"></div>
    </div>
</div>

<script>
$(function() {
    function showGenerateOptions(isBlank) {
        $('#generate_status_msg').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Checking missing months...</div>');
        $('#generate_options').hide().empty();
        $.getJSON('generate_mcap_excel_tool.php?action=missing_months&blank=' + (isBlank ? '1' : '0'))
        .done(function(response) {
            if (!response.success) { $('#generate_status_msg').html('<div class="alert alert-danger">Unable to fetch missing months.</div>'); return; }
            var missingMonths = response.missing_months || [];
            var html = '<div class="card card-body bg-light mb-3"><h6>Select blank month columns to include</h6><form id="generate_tool_form">';
            html += '<input type="hidden" name="action" value="generate_tool">';
            html += '<input type="hidden" name="blank" value="' + (isBlank ? '1' : '0') + '">';
            if (missingMonths.length === 0) html += '<p class="mb-2">No missing months detected.</p>';
            else missingMonths.forEach(function(m){ html += '<div class="form-check"><input class="form-check-input" type="checkbox" name="selected_months[]" id="month_'+m.value+'" value="'+m.value+'" checked><label class="form-check-label" for="month_'+m.value+'">'+m.label+'</label></div>'; });
            html += '<button type="submit" class="btn btn-success mt-3"><i class="fas fa-file-excel me-2"></i> Generate Excel</button></form></div>';
            $('#generate_options').html(html).show(); $('#generate_status_msg').empty();
        }).fail(function(){ $('#generate_status_msg').html('<div class="alert alert-danger">Error fetching missing months.</div>'); });
    }

    $('#btnGenerateTool, #btnGenerateBlankTool').on('click', function(){ showGenerateOptions($(this).data('blank')==1); });

    $(document).on('submit', '#generate_tool_form', function(e){
        e.preventDefault();
        var $btn = $(this).find('button[type=submit]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Generating...');
        $.post('generate_mcap_excel_tool.php', $(this).serialize(), function(resp){
            if (!resp.success) { $('#generate_status_msg').html('<div class="alert alert-danger">'+(resp.message||'Generation failed.')+'</div>'); $btn.prop('disabled', false).html('<i class="fas fa-file-excel me-2"></i> Generate Excel'); return; }
            $('#generate_status_msg').html('<div class="alert alert-success">Excel generated, downloading...</div>');
            window.location = 'generate_mcap_excel_tool.php?action=download_file&file='+encodeURIComponent(resp.file);
            $btn.prop('disabled', false).html('<i class="fas fa-file-excel me-2"></i> Generate Excel');
        }, 'json').fail(function(){ $('#generate_status_msg').html('<div class="alert alert-danger">System error.</div>'); $btn.prop('disabled', false).html('<i class="fas fa-file-excel me-2"></i> Generate Excel'); });
    });

    $('#btnUploadMcap').on('click', function(){
        var $btn = $(this); var file = $('#mcap_file')[0].files[0]; if (!file) { alert('Please select a file to upload.'); return; }
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Processing...'); $('#mcap_status_msg').empty();
        var fd = new FormData($('#mcapUploadForm')[0]);
        $.ajax({ url: 'upload_mcap_data.php', type: 'POST', data: fd, processData:false, contentType:false, dataType:'json'
        }).done(function(res){ if (res.success) $('#mcap_status_msg').html('<div class="alert alert-success mt-2">'+res.html+'</div>'); else $('#mcap_status_msg').html('<div class="alert alert-danger mt-2">'+res.message+'</div>'); $btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt me-2"></i> Upload & Process'); $('#mcapUploadForm')[0].reset();
        }).fail(function(){ $('#mcap_status_msg').html('<div class="alert alert-danger mt-2">System error during upload.</div>'); $btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt me-2"></i> Upload & Process'); });
    });
});
</script>