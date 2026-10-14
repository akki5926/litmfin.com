<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

// Prevent direct access to errors for AJAX
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// ---------- Helper ----------
function toFloat($val) {
    if (is_numeric($val)) return (float)$val;

    if (is_string($val)) {
        $val = trim($val);
        $val = str_replace(['%', ','], '', $val);
        return is_numeric($val) ? (float)$val : 0;
    }
    return 0;
}

// ---------- Fallback Map ----------
$fallbackMcapMap = [
    // Replace 'ISIN_HERE' with the actual ISIN for each corresponding fund.
    'INF179KC1BJ4' => [70,20,10], // HDFC Banking Financial Services Fund
    'INF179K01BB8' => [70,20,10], // HDFC Elss Tax Saver Fund
    'INF179KC1JL3' => [65,25,10], // HDFC Innovation Fund
    'INF179KC1HL7' => [65,25,10], // HDFC Pharma and Health Care Fund
    'INF179K01426' => [70,20,10], // HDFC Value Fund

    'INF109KC15W9' => [70,20,10], // ICICI Prudential Energy Opportunities Fund
    'INF109KC1Q80' => [75,15,10], // ICICI Prudential Flexi Cap Fund
    'INF109K01415' => [70,20,10], // ICICI Prudential FMCG fund
    'INF109K01BZ4' => [70,20,10], // ICICI Prudential Focused Equity Fund
    'INF109K01BL4' => [85,10,5],  // ICICI Prudential Large Cap Fund
    'INF109KC1LG4' => [70,20,10], // ICICI Prudential Manufacturing Fund
    'INF109KC18C5' => [70,30,0],  // ICICI Prudential Nifty 200 Quality 30 Index fund  // ICICI PRUDENTIAL NIFTY 200 MOMENTUM 30 INDEX FUND - GROWTH
    'INF109K01AF8' => [70,20,10], // ICICI Prudential Value Fund

    'INF769K01GU5' => [70,20,10], // Mirae Asset Banking and Financial Services Fund
    'INF769K01DK3' => [80,10,5],  // Mirae Asset ELSS Tax Saver Fund
    'INF769K01ES4' => [85,10,5],  // Mirae Asset Focused Fund
    'INF769K01135' => [70,25,5],  // Mirae Asset Great Consumer Fund
    'INF769K01101' => [65,30,5],  // Mirae Asset Large and Mid-Cap Fund
    'INF769K01010' => [85,10,5],  // Mirae Asset Large Cap Fund
    'INF769K01EY2' => [20,70,10], // Mirae Asset Mid Cap Fund

    'INF247L01411' => [20,65,10], // Motilal Oswal Mid Cap Fund
    'INF247L01700' => [100,0,0],  // Motilal Oswal Nasdaq 100 FOF
    'INF247L01AH0' => [100,0,0],  // Motilal Oswal S&P 500 Index Fund

    'INF204K01323' => [25,70,5],  // Nippon India Growth Mid Cap Fund
    'INF204K01AE0' => [70,20,10], // Nippon India Power and Infra Fund
    'INF204K01HY3' => [10,20,70], // Nippon India Small Cap Fund
    'INF204K01GB3' => [70,20,10], // Nippon India Value Fund

    'INF879O01019' => [80,15,5],  // Parag Parikh Flexi Cap Fund
    'INF754K30052' => [80,20],    // Altiva Hybrid Long Short Fund
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_mcap') {
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

        $sql = "INSERT INTO tb_funds_mcap_data 
                (isin, month_year, large_cap, mid_cap, small_cap, cash, debt)
                VALUES (:isin, :dt, :l, :m, :s, :c, :d)
                ON DUPLICATE KEY UPDATE 
                large_cap = VALUES(large_cap), 
                mid_cap = VALUES(mid_cap), 
                small_cap = VALUES(small_cap), 
                cash = VALUES(cash), 
                debt = VALUES(debt)";

        $stmt = $pdo->prepare($sql);

        foreach ($allSheets as $sheet) {

            if ($sheet->getTitle() == 'Summary') continue;

            $sheetName = $sheet->getTitle();
            $highestRow = $sheet->getHighestRow();
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

            for ($row = 1; $row <= $highestRow; $row++) {

                $cellA = $sheet->getCell([1, $row])->getValue();

                // ✅ FIXED ID detection
                if ($cellA && strpos($cellA, 'ID:') === 0) {

                    preg_match('/ID:\s*([^|]+)/', $cellA, $matches);
                    $isin = trim($matches[1] ?? '');

                    // Fund Name
                    $fundHeader = $sheet->getCell([1, $row - 1])->getValue();
                    $fundName = '';
                    if ($fundHeader && str_contains($fundHeader, 'FUND:')) {
                        preg_match('/FUND:\s*(.*?)\s*\|/', $fundHeader, $m);
                        $fundName = $m[1] ?? '';
                    }

                    if (!$isin) continue;

                    $totalFundsProcessed[$isin] = true;

                    for ($col = 2; $col <= $highestColIndex; $col++) {

                        $monthValue = $sheet->getCell([$col, $row])->getValue();

                        // ✅ FIXED empty check
                        if ($monthValue === null || $monthValue === '') continue;

                        // ✅ FIXED date parsing
                        try {
                            if (is_numeric($monthValue)) {
                                $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($monthValue);
                                $formattedDate = $dateObj->format('Y-m-d');
                            } else {
                                $dateObj = DateTime::createFromFormat('M-y', $monthValue) ?: new DateTime($monthValue);
                                $formattedDate = $dateObj->format('Y-m-01');
                            }
                        } catch (Exception $e) {
                            continue;
                        }

                        // Values
                        $large = toFloat($sheet->getCell([$col, $row+1])->getCalculatedValue());
                        $mid   = toFloat($sheet->getCell([$col, $row+2])->getCalculatedValue());
                        $small = toFloat($sheet->getCell([$col, $row+3])->getCalculatedValue());
                        $cash  = toFloat($sheet->getCell([$col, $row+4])->getCalculatedValue());
                        $debt  = toFloat($sheet->getCell([$col, $row+5])->getCalculatedValue());

                        // Fix decimal %
                        foreach (['large','mid','small','cash','debt'] as $k) {
                            if ($$k > 0 && $$k <= 1) $$k *= 100;
                        }

                        $total = $large + $mid + $small + $cash + $debt;

                        // Fallback
                        if ($total == 0) {
                            if ($isin && isset($fallbackMcapMap[$isin])) {
                                $vals = $fallbackMcapMap[$isin];

                                $large = $vals[0] ?? 0;
                                $mid   = $vals[1] ?? 0;
                                $small = $vals[2] ?? 0;
                                $cash = 0;
                                $debt = 0;

                                $total = $large + $mid + $small;

                                $fallbackUsed[$isin]['sheet'] = $sheetName;
                                $fallbackUsed[$isin]['fund_name'] = $fundName;
                                $fallbackUsed[$isin]['isin'] = $isin;
                                $fallbackUsed[$isin]['months'][] = $formattedDate;
                            }
                            if ($total == 0) {
                                $missingData[$isin]['sheet'] = $sheetName;
                                $missingData[$isin]['fund_name'] = $fundName;
                                $missingData[$isin]['isin'] = $isin;
                                $missingData[$isin]['months'][] = $formattedDate;
                                continue;
                            }
                        }

                        if ($total > 100.5) {
                            $errorRows[] = ['sheet'=>$sheetName,'isin'=>$isin,'fund_name'=>$fundName,'month'=>$formattedDate,'reason'=>"Invalid total ($total)"];
                            continue;
                        }

                        // Insert
                        $stmt->execute([
                            ':isin'=>$isin,
                            ':dt'=>$formattedDate,
                            ':l'=>$large,
                            ':m'=>$mid,
                            ':s'=>$small,
                            ':c'=>$cash,
                            ':d'=>$debt
                        ]);

                        $recordsProcessed++;

                        // Success
                        $successSummary[$isin]['sheet'] = $sheetName;
                        $successSummary[$isin]['fund_name'] = $fundName;
                        $successSummary[$isin]['months'][] = $formattedDate;
                        $successSummary[$isin]['count'] = ($successSummary[$isin]['count'] ?? 0) + 1;
                    }

                    $row += 8;
                }
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
            foreach ($successSummary as $isin=>$d) {
                sort($d['months']);
                echo "<tr>
                <td>{$d['sheet']}</td>
                <td>$isin</td>
                <td style='max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='" . htmlspecialchars($d['fund_name']) . "'>{$d['fund_name']}</td>
                <td>{$d['months'][0]}</td>
                <td>".end($d['months'])."</td>
                <td>{$d['count']}</td>
                </tr>";
            }
            echo "</tbody></table></div>";
        }

        if ($fallbackUsed) {
            echo "<h6 class='text-warning mt-3'>Fallback Used</h6>";
            echo "<div class='table-responsive' style='max-height:300px;'><table class='table table-sm table-bordered table-striped mb-0'>";
            echo "<thead class='table-light'><tr><th>Sheet</th><th>ISIN</th><th>Fund Name</th><th>From Month</th><th>To Month</th><th>Records</th></tr></thead><tbody>";
            foreach ($fallbackUsed as $d) {
                sort($d['months']);
                echo "<tr>
                <td>{$d['sheet']}</td>
                <td>{$d['isin']}</td>
                <td style='max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='" . htmlspecialchars($d['fund_name']) . "'>{$d['fund_name']}</td>
                <td>{$d['months'][0]}</td>
                <td>".end($d['months'])."</td>
                <td>".count($d['months'])."</td>
                </tr>";
            }
            echo "</tbody></table></div>";
        }

        if ($missingData) {
            echo "<h6 class='text-secondary mt-3'>Missing Data (No fallback used)</h6>";
            echo "<div class='table-responsive' style='max-height:300px;'><table class='table table-sm table-bordered table-striped mb-0'>";
            echo "<thead class='table-light'><tr><th>Sheet</th><th>ISIN</th><th>Fund Name</th><th>From Month</th><th>To Month</th><th>Missing Records</th></tr></thead><tbody>";
            foreach ($missingData as $isin => $d) {
                sort($d['months']);
                echo "<tr>
                <td>{$d['sheet']}</td>
                <td>$isin</td>
                <td style='max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='" . htmlspecialchars($d['fund_name']) . "'>" . htmlspecialchars($d['fund_name']) . "</td>
                <td>{$d['months'][0]}</td>
                <td>".end($d['months'])."</td>
                <td>".count($d['months'])."</td>
                </tr>";
            }
            echo "</tbody></table></div>";
        }

        if ($errorRows) {
            echo "<h6 class='text-danger mt-3'>Errors Found: " . count($errorRows) . "</h6>";
            echo "<div class='table-responsive' style='max-height:300px;'><table class='table table-sm table-bordered table-striped mb-0'>";
            echo "<thead class='table-light'><tr><th>Sheet</th><th>ISIN</th><th>Fund Name</th><th>Month</th><th>Reason</th></tr></thead><tbody>";
            foreach ($errorRows as $err) {
                echo "<tr>
                        <td>{$err['sheet']}</td>
                        <td>{$err['isin']}</td>
                        <td style='max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='" . htmlspecialchars($err['fund_name']) . "'>" . htmlspecialchars($err['fund_name']) . "</td>
                        <td>{$err['month']}</td>
                        <td>{$err['reason']}</td>
                      </tr>";
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
        
        <!-- Generate Tool Section -->
        <div class="alert alert-secondary">
            <i class="fas fa-info-circle me-1"></i> Generate the MCAP Allocation Tool (Excel) containing active funds for data entry.
        </div>
        <div class="d-flex gap-2 mb-4">
            <button id="btnGenerateTool" class="btn btn-primary px-4 py-2">
                <i class="fas fa-file-excel me-2"></i> Generate Filled MCAP Tool
            </button>
            <button id="btnGenerateBlankTool" class="btn btn-outline-primary px-4 py-2">
                <i class="fas fa-file-alt me-2"></i> Generate Blank MCAP Tool
            </button>
        </div>
        <div id="generate_status_msg"></div>

        <hr class="my-4">

        <!-- Upload Section -->
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
$(document).ready(function() {
    $("#btnGenerateTool").click(function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Generating...');
        $("#generate_status_msg").html('');

        // Redirect to trigger file download
        window.location.href = "generate_mcap_excel_tool.php";
        
        // Reset button state after a short delay
        setTimeout(function() {
            $btn.prop('disabled', false).html('<i class="fas fa-file-excel me-2"></i> Generate Filled MCAP Tool');
        }, 3000);
    });

    $("#btnGenerateBlankTool").click(function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Generating...');
        $("#generate_status_msg").html('');

        // Redirect to trigger file download with blank flag
        window.location.href = "generate_mcap_excel_tool.php?blank=1";
        
        // Reset button state after a short delay
        setTimeout(function() {
            $btn.prop('disabled', false).html('<i class="fas fa-file-alt me-2"></i> Generate Blank MCAP Tool');
        }, 3000);
    });

    $("#btnUploadMcap").click(function() {
        var $btn = $(this);
        var fileInput = document.getElementById('mcap_file');
        
        if (fileInput.files.length === 0) {
            alert("Please select a file to upload.");
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Processing...');
        $("#mcap_status_msg").html('');

        var formData = new FormData($("#mcapUploadForm")[0]);

        $.ajax({
            url: "upload_mcap_data.php",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    $("#mcap_status_msg").html('<div class="alert alert-success mt-2 border-success">' + response.html + '</div>');
                } else {
                    $("#mcap_status_msg").html('<div class="alert alert-danger mt-2 border-danger"><strong>Error!</strong> ' + response.message + '</div>');
                }
                $btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt me-2"></i> Upload & Process');
                $('#mcapUploadForm')[0].reset();
            },
            error: function(xhr, status, error) {
                $("#mcap_status_msg").html('<div class="alert alert-danger mt-2 border-danger"><strong>Error!</strong> A system error occurred during upload.</div>');
                $btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt me-2"></i> Upload & Process');
            }
        });
    });
});
</script>