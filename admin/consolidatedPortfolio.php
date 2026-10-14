<?php
require_once('auth_guard.php');

/**
 * consolidatedPortfolio.php
 * AJAX fragment — no <!DOCTYPE>, no CDN scripts, no window.parent.$ hacks.
 * All libraries already loaded by parent index.php.
 */

if (!isset($_SESSION)) { session_start(); }
if (!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)) {
    echo '<div class="alert alert-danger m-3">Session expired. Please <a href="login.php">log in</a>.</div>';
    exit;
}

// ── AJAX Endpoint: Inline Quick Summary & Holdings ─────────────────────────────
if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['get_summary', 'get_holdings'])) {
    require_once('database.php');
    require_once('portfolio.php');
    require_once('xirr_engine.php');
    
    $db = new MYSQLDB();
    $iin = $_REQUEST['iin'] ?? '';
    $family = (int)($_REQUEST['family'] ?? 0);
    $action = $_REQUEST['action'];

    // Subadmin check
    $isSubAdmin = isset($_SESSION["superuser"]) && $_SESSION["superuser"] == 0;
    if ($isSubAdmin) {
        $subAdminId = $_SESSION["userid"] ?? 0;
        if (!$db->isClientAssignedToSubadmin($iin, $subAdminId)) {
            echo '<div class="alert alert-danger py-2 px-3 mt-2 mb-0" style="font-size:12px;"><i class="fas fa-times-circle me-1"></i>Access Denied: Not assigned to this subadmin.</div>';
            exit;
        }
    }
    
    ob_start();
    try {
        $members = [['iin' => $iin, 'relation' => 'Self']];
        if ($family) {
            $familyDetails = $db->getFamilyByUCC($iin);
            if ($familyDetails) {
                if (!empty($familyDetails['member1_iin'])) $members[] = ['iin' => $familyDetails['member1_iin'], 'relation' => $familyDetails['member1_rel']];
                if (!empty($familyDetails['member2_iin'])) $members[] = ['iin' => $familyDetails['member2_iin'], 'relation' => $familyDetails['member2_rel']];
                if (!empty($familyDetails['member3_iin'])) $members[] = ['iin' => $familyDetails['member3_iin'], 'relation' => $familyDetails['member3_rel']];
            }
        }
        
        if ($action === 'get_summary') {
            $summableFields = [
                'Total_Equity_Cost', 'Total_Debt_Cost', 'Total_Hybrid_Cost', 'Total_Gold_Cost',
                'Total_Equity_Curr_Value', 'Total_Debt_Curr_Value', 'Total_Hybrid_Curr_Value', 'Total_Gold_Curr_Value',
                'Total_Equity_Unrealized', 'Total_Debt_Unrealized', 'Total_Hybrid_Unrealized', 'Total_Gold_Unrealized'
            ];
            
            $ta = [];
            $totalCost = 0;
            $totalCurrent = 0;
            $totalUnrealized = 0;
            
            foreach ($members as $member) {
                if (empty($member['iin'])) continue;
                list($portfolioInfo, $ZeroHoldingFundInfo, $totalHoldingsMF, $totalAssetInfo) = calculateHoldings($member['iin']);
                if (empty($totalAssetInfo[0])) continue;
                
                $memberAssets = $totalAssetInfo[0];
                foreach ($summableFields as $field) {
                    if (!isset($ta[$field])) $ta[$field] = 0;
                    $ta[$field] += (float)str_replace(',', '', $memberAssets[$field] ?? 0);
                }
                
                $totalCost += (float)str_replace(',', '', $memberAssets['Total_Cost_Value'] ?? 0);
                $totalCurrent += (float)str_replace(',', '', $memberAssets['Total_Current_Value'] ?? 0);
                $totalUnrealized += (float)str_replace(',', '', $memberAssets['Total_Unrealized'] ?? 0);
                
                if (!$family && isset($memberAssets['Total_Holding_Return'])) {
                    $ta['Total_Holding_Return'] = $memberAssets['Total_Holding_Return'];
                }
            }
            
            if ($totalCost > 0 || $totalCurrent > 0) {
                echo '<div class="mt-2 p-3 bg-white border rounded shadow-sm text-start" style="font-size:12px;">';
                echo '<div class="fw-bold text-primary border-bottom pb-2 mb-2"><i class="fas fa-chart-pie me-1"></i>'.($family ? 'Family' : 'Individual').' Assets &amp; Returns</div>';
                echo '<table class="table table-sm table-bordered mb-0">';
                echo '<thead class="table-light"><tr><th>Asset</th><th class="text-end">Cost</th><th class="text-end">Current Value</th><th class="text-end">P&amp;L</th></tr></thead>';
                echo '<tbody>';
                $assetClasses = ['Equity', 'Debt', 'Hybrid', 'Gold'];
                foreach ($assetClasses as $ac) {
                    $c = $ta["Total_{$ac}_Cost"] ?? 0;
                    $v = $ta["Total_{$ac}_Curr_Value"] ?? 0;
                    $p = $ta["Total_{$ac}_Unrealized"] ?? 0;
                    if ($c > 0 || $v > 0) {
                        echo '<tr>';
                        echo '<td>'.$ac.'</td>';
                        echo '<td class="text-end">₹'.number_format($c, 2).'</td>';
                        echo '<td class="text-end">₹'.number_format($v, 2).'</td>';
                        $pClass = $p >= 0 ? 'text-success' : 'text-danger';
                        echo '<td class="text-end '.$pClass.' fw-bold">₹'.number_format($p, 2).'</td>';
                        echo '</tr>';
                    }
                }
                echo '<tr class="fw-bold table-secondary">';
                echo '<td>TOTAL</td>';
                echo '<td class="text-end">₹'.number_format($totalCost, 2).'</td>';
                echo '<td class="text-end text-primary">₹'.number_format($totalCurrent, 2).'</td>';
                $tplClass = $totalUnrealized >= 0 ? 'text-success' : 'text-danger';
                echo '<td class="text-end '.$tplClass.'">₹'.number_format($totalUnrealized, 2).'</td>';
                echo '</tr>';
                echo '</tbody></table>';
                
                if (!$family && isset($ta['Total_Holding_Return'])) {
                    echo '<div class="mt-2 text-end text-muted" style="font-size:12px;">Overall XIRR: <span class="fw-bold text-dark bg-light px-2 py-1 border rounded">'.number_format((float)$ta['Total_Holding_Return'], 2).'%</span></div>';
                }
                echo '</div>';
            } else {
                echo '<div class="alert alert-warning py-2 px-3 mt-2 mb-0" style="font-size:12px;"><i class="fas fa-exclamation-circle me-1"></i>No active investments found.</div>';
            }
        } elseif ($action === 'get_holdings') {
            $allHoldings = [];
            foreach ($members as $member) {
                if (empty($member['iin'])) continue;
                $casData = calculateHoldings($member['iin']);
                $portfolioInfo = $casData[0] ?? [];
                $HoldingInfoBonds = $casData[4] ?? [];
                $HoldingInfoPms = $casData[7] ?? [];
                $HoldingInfoAif = $casData[10] ?? [];
                $HoldingInfoSif = $casData[24] ?? [];

                // Extract MF
                foreach ($portfolioInfo as $amc => $funds) {
                    foreach ($funds as $fWrapper) {
                        foreach ($fWrapper as $isin => $f) {
                            if ((float)str_replace(',', '', $f['Unit_Holdings'] ?? 0) > 0) {
                                $allHoldings[] = [
                                    'name' => $f['Fund_Name'],
                                    'type' => 'MF',
                                    'units' => $f['Unit_Holdings'],
                                    'cost' => $f['Cost_Value'],
                                    'nav' => $f['Current_NAV'],
                                    'value' => $f['Curr_Value']
                                ];
                            }
                        }
                    }
                }
                
                // Extract Bonds
                foreach ($HoldingInfoBonds as $amc => $funds) {
                    foreach ($funds as $fWrapper) {
                        foreach ($fWrapper as $f) {
                            if ((float)str_replace(',', '', $f['unitHoldings'] ?? 0) > 0) {
                                $allHoldings[] = [
                                    'name' => $f['bondName'],
                                    'type' => 'Bond',
                                    'units' => $f['unitHoldings'],
                                    'cost' => $f['costValue'],
                                    'nav' => $f['currentNAV'],
                                    'value' => $f['currValue']
                                ];
                            }
                        }
                    }
                }

                // Extract PMS
                foreach ($HoldingInfoPms as $amc => $funds) {
                    foreach ($funds as $fWrapper) {
                        foreach ($fWrapper as $f) {
                            if ((float)str_replace(',', '', $f['currValue'] ?? 0) > 0) {
                                $allHoldings[] = [
                                    'name' => $f['pmsName'],
                                    'type' => 'PMS',
                                    'units' => '-',
                                    'cost' => $f['totalPurchaseCost'],
                                    'nav' => '-',
                                    'value' => $f['currValue']
                                ];
                            }
                        }
                    }
                }

                // Extract AIF
                foreach ($HoldingInfoAif as $amc => $funds) {
                    foreach ($funds as $fWrapper) {
                        foreach ($fWrapper as $f) {
                            if (isset($f['aifName']) && (float)str_replace(',', '', $f['units'] ?? 0) > 0) {
                                $allHoldings[] = [
                                    'name' => $f['aifName'],
                                    'type' => 'AIF',
                                    'units' => $f['units'],
                                    'cost' => $f['costValue'],
                                    'nav' => $f['currNAV'],
                                    'value' => $f['currValue']
                                ];
                            }
                        }
                    }
                }
                
                // Extract SIF
                foreach ($HoldingInfoSif as $amc => $funds) {
                    foreach ($funds as $fWrapper) {
                        foreach ($fWrapper as $isin => $f) {
                            if ((float)str_replace(',', '', $f['Unit_Holdings'] ?? 0) > 0) {
                                $allHoldings[] = [
                                    'name' => $f['Fund_Name'],
                                    'type' => 'SIF',
                                    'units' => $f['Unit_Holdings'],
                                    'cost' => $f['Cost_Value'],
                                    'nav' => $f['Current_NAV'],
                                    'value' => $f['Curr_Value']
                                ];
                            }
                        }
                    }
                }
            }

            if (!empty($allHoldings)) {
                usort($allHoldings, function($a, $b) {
                    return (float)str_replace(',', '', $b['value']) <=> (float)str_replace(',', '', $a['value']);
                });

                echo '<div class="mt-2 p-3 bg-white border rounded shadow-sm text-start" style="font-size:12px; max-height: 400px; overflow-y: auto;">';
                echo '<div class="fw-bold text-primary border-bottom pb-2 mb-2"><i class="fas fa-list me-1"></i>'.($family ? 'Family' : 'Individual').' Active Holdings</div>';
                echo '<table class="table table-sm table-bordered table-striped mb-0">';
                echo '<thead class="table-light"><tr><th>Asset Name</th><th>Type</th><th class="text-end">Units</th><th class="text-end">Cost (₹)</th><th class="text-end">NAV (₹)</th><th class="text-end">Value (₹)</th></tr></thead>';
                echo '<tbody>';
                foreach ($allHoldings as $h) {
                    echo '<tr>';
                    echo '<td><div style="max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="'.htmlspecialchars($h['name']).'">' . htmlspecialchars($h['name']) . '</div></td>';
                    echo '<td class="text-center"><span class="badge bg-secondary">' . htmlspecialchars($h['type']) . '</span></td>';
                    echo '<td class="text-end">' . htmlspecialchars($h['units']) . '</td>';
                    echo '<td class="text-end">' . htmlspecialchars($h['cost']) . '</td>';
                    echo '<td class="text-end">' . htmlspecialchars($h['nav']) . '</td>';
                    echo '<td class="text-end fw-bold text-primary">' . htmlspecialchars($h['value']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            } else {
                echo '<div class="alert alert-warning py-2 px-3 mt-2 mb-0" style="font-size:12px;"><i class="fas fa-exclamation-circle me-1"></i>No active holdings found.</div>';
            }
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger py-2 px-3 mt-2 mb-0" style="font-size:12px;"><i class="fas fa-times-circle me-1"></i>Error calculating requested data.</div>';
    }
    
    ob_end_flush();
    exit;
}

$superUser = $_SESSION["superuser"] ?? 0;
$isSubAdmin = ($superUser == 0);
$subAdminId = $_SESSION["userid"] ?? 0;

require_once('database.php');
$db = new MYSQLDB();

// ── Fetch data ────────────────────────────────────────────────────────────────
if ($isSubAdmin) {
    $result = $db->getActivatedPortfolio4subAdmin($subAdminId);
} else {
    $result = $db->getActivatedPortfolio();
}

if (empty($result)) {
    echo '<div class="alert alert-info m-3"><i class="fas fa-info-circle me-2"></i>No portfolio data available.</div>';
    exit;
}
?>

<style>
    .client-code-link { color: #0d6efd; text-decoration: underline; cursor: pointer; font-weight: 500; }
    .client-code-link:hover { color: #0a58ca; }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-layer-group me-2"></i>Portfolio Reports</h5>
                    <div class="d-flex align-items-center gap-3">
                        <span id="cp-auto-email-stats" style="font-size:12px;opacity:.85;"><i class="fas fa-users me-1"></i>1 auto-email enabled</span>
                        <div>
                            <button id="sendAllBtn" class="btn btn-primary btn-sm fw-semibold" style="white-space:nowrap;">
                                <i class="fas fa-paper-plane me-1"></i>Send Email to All Auto Email Recipients
                            </button>
                            <div class="text-center mt-1">
                                <a href="#" class="cp-nav-link" data-url="assignsubadmin.php?ref=cp" data-title="Auto-Email Settings" style="font-size: 11px;">Edit auto-email settings</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div id="cpReportStatus"></div>
                    
                    <div class="table-responsive">
                        <table id="portfolioTable" class="table table-striped table-hover border data-table" style="width:100%; font-size:13px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%;">Client Code</th>
                                    <th style="width: 15%;">Client Name</th>
                                    <th class="text-center" style="width: 20%;">Holdings</th>
                                    <th class="text-center" style="width: 20%;">Assets &amp; Returns</th>
                                    <th class="text-center" style="width: 8%;">Include Family</th>
                                    <th class="text-center" style="width: 15%;">Complete Report</th>
                                    <th class="text-center" style="width: 12%;">Debug Excel</th>
                                </tr>
                            </thead>
                            <tbody>
                <?php foreach ($result as $row):
                    $code         = $row['CLIENT_CODE']          ?? '';
                    $holderName   = $row['PRIMARY_HOLDER_NAME']   ?? '-';
                    $autoEmail    = $row['AUTO_EMAIL']            ?? 0;
                ?>
                <tr data-auto-email="<?php echo htmlspecialchars($autoEmail); ?>">
                    <td>
                        <a href="#"
                           class="client-code-link ucc-nav-link"
                           data-url="getUccDetail.php?iin=<?php echo urlencode($code); ?>&ref=consolidatedPortfolio.php"
                           data-title="<?php echo htmlspecialchars($code); ?>">
                            <?php echo htmlspecialchars($code); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($holderName); ?></td>
                    <td class="text-center holdings-cell">
                        <button class="btn btn-sm btn-outline-primary viewHoldingsLink" data-client-code="<?php echo htmlspecialchars($code); ?>" title="View Holdings">
                            <i class="fas fa-list"></i> View Holdings
                        </button>
                        <div class="holdings-content" style="display:none;"></div>
                    </td>
                    <td class="text-center summary-cell">
                        <button class="btn btn-sm btn-outline-info viewSummaryLink" data-client-code="<?php echo htmlspecialchars($code); ?>" title="View Assets &amp; Returns">
                            <i class="fas fa-eye"></i> View Assets &amp; Returns
                        </button>
                        <div class="summary-content" style="display:none;"></div>
                    </td>
                    <td class="text-center">
                        <input class="form-check-input" type="checkbox"
                               name="family<?php echo htmlspecialchars($code); ?>"
                               id="family<?php echo htmlspecialchars($code); ?>"
                               value="1">
                    </td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-info text-white viewLink" data-client-code="<?php echo htmlspecialchars($code); ?>" title="View PDF">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-sm btn-primary downloadLink" data-client-code="<?php echo htmlspecialchars($code); ?>" title="Download PDF">
                                <i class="fas fa-download"></i> Download
                            </button>
                            <button class="btn btn-sm btn-success sendRequestLink" data-client-code="<?php echo htmlspecialchars($code); ?>" data-name="<?php echo htmlspecialchars($holderName); ?>" title="Send Email">
                                <i class="fas fa-envelope"></i> Email
                            </button>
                        </div>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-success downloadExcelLink" data-client-code="<?php echo htmlspecialchars($code); ?>" title="Download Excel">
                            <i class="fas fa-file-excel"></i> Download
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sendAllModal" tabindex="-1" aria-labelledby="sendAllModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sendAllModalLabel">
                    <i class="fas fa-paper-plane me-2 text-primary"></i>Sending Reports
                </h5>
            </div>
            <div class="modal-body">
                <div id="sa-summary" class="mb-3" style="font-size:13px;color:#555;"></div>
                <div class="progress mb-2" style="height:10px;border-radius:6px;">
                    <div id="sa-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                         role="progressbar" style="width:0%"></div>
                </div>
                <div class="d-flex justify-content-between" style="font-size:12px;color:#888;">
                    <span id="sa-progress-text">0 / 0</span>
                    <span id="sa-percent">0%</span>
                </div>
                <div id="sa-log" style="max-height:220px;overflow-y:auto;margin-top:14px;font-size:12px;background:#f8f9fa;border-radius:6px;padding:10px;border:1px solid #e9ecef;"></div>
            </div>
            <div class="modal-footer">
                <span id="sa-final-msg" class="me-auto fw-semibold" style="font-size:13px;"></span>
                <a id="sa-download-log-btn" class="btn btn-outline-primary btn-sm" style="display:none;">
                    <i class="fas fa-download me-1"></i>Download Log
                </a>
                <button id="sa-close-btn" class="btn btn-secondary btn-sm" style="display:none;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function ($) {

    function initCPTable() {
    // ── DataTable — destroy first if already initialised ─────────────────────
    if ($.fn.DataTable.isDataTable('#portfolioTable')) {
        $('#portfolioTable').DataTable().destroy();
    }

        var cpTable = $('#portfolioTable').DataTable({
            destroy: true,
            pageLength : 25,
            lengthMenu : [[10, 25, 50, -1], [10, 25, 50, 'All']],
            order      : [[0, 'asc']],
            language   : {
                search      : 'Search:',
                lengthMenu  : 'Show _MENU_ entries',
                info        : 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty   : 'Showing 0 to 0 of 0 entries',
                infoFiltered: '(filtered from _MAX_ total entries)',
                zeroRecords : 'No matching records found',
                paginate    : { first:'First', last:'Last', next:'Next', previous:'Previous' }
            },
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-6"i><"col-sm-6"p>>'
        });
    }

    // Function to initialize the page-specific scripts
    function initializeConsolidatedPortfolioPage() {
        initCPTable();
        // Any other page-specific initializations for this page can go here
    }

    // Register the initialization function to be called by the parent frame
    window.reinitPageScripts = initializeConsolidatedPortfolioPage;

    // ── Navigate to UCC detail page ──────────────────────────────────────────
    $(document).off('click.cpUcc').on('click.cpUcc', '#portfolioTable .ucc-nav-link', function (e) {
        e.preventDefault();
        var url   = $(this).data('url');
        var title = $(this).data('title') || $(this).text().trim();

        $('#page-title').text('UCC Details: ' + title);
        $('#main_content').hide();
        $('#loader-text').text('Loading UCC details for ' + title + '...');
        $('#loader').show();

        $('#main_content').load(url, function (response, status, xhr) {
            $('#loader').hide();
            if (status === 'error') {
                $('#main_content').html(
                    '<div class="alert alert-danger">Error ' + xhr.status + ': ' + xhr.statusText + '</div>'
                );
            }
            $('#main_content').fadeIn();
        });
    });

    // ── View inline ───────────────────────────────────────────────────────────
    $(document).off('click.cpView').on('click.cpView', '#portfolioTable .viewLink', function (e) {
        e.preventDefault();
        var code   = $(this).data('client-code');
        var family = $('#family' + code).is(':checked') ? 1 : 0;
        var url    = 'generatereport.php?i=' + encodeURIComponent(code) + '&family=' + family + '&action=view';

        $('#page-title').text('View Portfolio Report: ' + code);
        
        $('#main_content').children(':not(style, script, .modal)').hide();
        $('#pdfViewerContainer').remove();
        $('#main_content').append(`
            <div id="pdfViewerContainer" class="container-fluid p-0">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-file-pdf me-2"></i>Portfolio Report: ${code}</h5>
                        <button class="btn btn-outline-primary btn-sm shadow-sm" id="closePdfViewerBtn"><i class="fas fa-arrow-left me-1"></i> Back to List</button>
                    </div>
                    <div class="card-body p-0 position-relative" style="height: 75vh;">
                        <div id="pdf-loader" class="position-absolute top-50 start-50 translate-middle text-center" style="z-index: 10;">
                            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                            <p class="mt-2 fw-bold text-primary">Generating Report...</p>
                        </div>
                        <iframe src="${url}" width="100%" height="100%" style="border:none; position: relative; z-index: 20;" onload="document.getElementById('pdf-loader').style.display='none';"></iframe>
                    </div>
                </div>
            </div>
        `);
    });

    // ── View Holdings inline ──────────────────────────────────────────────────
    $(document).off('click.cpHoldings').on('click.cpHoldings', '#portfolioTable .viewHoldingsLink', function (e) {
        e.preventDefault();
        var btn = $(this);
        var container = btn.siblings('.holdings-content');
        var code = btn.data('client-code');
        var family = $('#family' + code).is(':checked') ? 1 : 0;

        if (container.is(':visible')) {
            container.slideUp();
            btn.html('<i class="fas fa-list"></i> View Holdings').removeClass('btn-primary text-white').addClass('btn-outline-primary');
            return;
        }
        
        btn.html('<i class="fas fa-spinner fa-spin"></i> Loading...').prop('disabled', true);
        
        $.ajax({
            url: 'consolidatedPortfolio.php',
            type: 'GET',
            data: { action: 'get_holdings', iin: code, family: family },
            success: function(resp) {
                container.html(resp).slideDown();
                btn.html('<i class="fas fa-chevron-up"></i> Hide Holdings').removeClass('btn-outline-primary').addClass('btn-primary text-white');
                btn.prop('disabled', false);
            },
            error: function() {
                container.html('<div class="text-danger mt-1">Failed to load holdings.</div>').slideDown();
                btn.html('<i class="fas fa-list"></i> Retry Holdings');
                btn.prop('disabled', false);
            }
        });
    });

    // ── View Assets & Returns inline ──────────────────────────────────────────
    $(document).off('click.cpSummary').on('click.cpSummary', '#portfolioTable .viewSummaryLink', function (e) {
        e.preventDefault();
        var btn = $(this);
        var container = btn.siblings('.summary-content');
        var code = btn.data('client-code');
        var family = $('#family' + code).is(':checked') ? 1 : 0;

        if (container.is(':visible')) {
            container.slideUp();
            btn.html('<i class="fas fa-eye"></i> View Assets &amp; Returns').removeClass('btn-info text-white').addClass('btn-outline-info');
            return;
        }
        
        btn.html('<i class="fas fa-spinner fa-spin"></i> Loading...').prop('disabled', true);
        
        $.ajax({
            url: 'consolidatedPortfolio.php',
            type: 'GET',
            data: { action: 'get_summary', iin: code, family: family },
            success: function(resp) {
                container.html(resp).slideDown();
                btn.html('<i class="fas fa-chevron-up"></i> Hide Assets &amp; Returns').removeClass('btn-outline-info').addClass('btn-info text-white');
                btn.prop('disabled', false);
            },
            error: function() {
                container.html('<div class="text-danger mt-1">Failed to load summary.</div>').slideDown();
                btn.html('<i class="fas fa-eye"></i> Retry Assets &amp; Returns');
                btn.prop('disabled', false);
            }
        });
    });

    // ── Close inline viewer ───────────────────────────────────────────────────
    $(document).off('click.cpClose').on('click.cpClose', '#closePdfViewerBtn', function (e) {
        e.preventDefault();
        $('#pdfViewerContainer').remove();
        $('#main_content').children(':not(style, script, .modal)').fadeIn();
        $('#page-title').text('Portfolio Reports');
    });

    // ── Sidebar link fallback ─────────────────────────────────────────────────
    $(document).off('sameUrlClicked.cpFallback').on('sameUrlClicked.cpFallback', function(e, url) {
        if (url === 'consolidatedPortfolio.php' && $('#pdfViewerContainer').length) {
            $('#closePdfViewerBtn').click();
        }
    });

    // ── Download with family checkbox ────────────────────────────────────────
    $(document).off('click.cpDownload').on('click.cpDownload', '#portfolioTable .downloadLink', function (e) {
        e.preventDefault();
        var btn      = $(this);
        var origHtml = btn.html();
        var code    = $(this).data('client-code');
        var family  = $('#family' + code).is(':checked') ? 1 : 0;
        var url     = 'generatereport.php?i=' + encodeURIComponent(code) + '&family=' + family + '&action=download';
        
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true).addClass('disabled');
        
        setTimeout(function() {
            btn.html(origHtml).prop('disabled', false).removeClass('disabled');
        }, 6000);
        
        window.location.href = url;
    });

    // ── Download Excel with family checkbox ──────────────────────────────────
    $(document).off('click.cpExcel').on('click.cpExcel', '#portfolioTable .downloadExcelLink', function (e) {
        e.preventDefault();
        var btn      = $(this);
        var origHtml = btn.html();
        var code    = $(this).data('client-code');
        var family  = $('#family' + code).is(':checked') ? 1 : 0;
        var url     = 'generatereport.php?i=' + encodeURIComponent(code) + '&family=' + family + '&action=download_excel';
        
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true).addClass('disabled');
        
        setTimeout(function() {
            btn.html(origHtml).prop('disabled', false).removeClass('disabled');
        }, 3000);
        
        window.location.href = url;
    });

    // ── Send email ────────────────────────────────────────────────────────────
    $(document).off('click.cpEmail').on('click.cpEmail', '#portfolioTable .sendRequestLink', function (e) {
        e.preventDefault();
        var code   = $(this).data('client-code');
        var name   = $(this).data('name');
        var family = $('#family' + code).is(':checked') ? 1 : 0;
        var btn    = $(this);
        var origHtml = btn.html();

        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

        $.ajax({
            url     : 'send_report_email.php',
            type    : 'GET',
            data    : { action: 'send', i: code, family: family },
            success : function (resp) {
                btn.html(origHtml).prop('disabled', false);
                var isError = resp.toLowerCase().includes('error');
                if(!isError) {
                    $('#cpReportStatus').html(`<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>${resp}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                } else {
                    $('#cpReportStatus').html(`<div class="alert alert-danger alert-dismissible"><i class="fas fa-exclamation-triangle me-2"></i>${resp}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                }
            },
            error   : function (xhr) {
                btn.html(origHtml).prop('disabled', false);
                $('#cpReportStatus').html(`<div class="alert alert-danger alert-dismissible"><i class="fas fa-exclamation-triangle me-2"></i>Error sending email (${xhr.status}). Please retry.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
            }
        });
    });

    // Handler for links to other pages like auto-email settings
    $(document).off('click', '.cp-nav-link').on('click', '.cp-nav-link', function (e) {
        e.preventDefault();
        var url   = $(this).data('url');
        var title = $(this).data('title') || $(this).text().trim();

        // Update active state in sidebar
        $('#sidebar a').removeClass('active');
        var cleanUrl = url.split('?')[0];
        var $sidebarLink = $('#sidebar a[data-url="' + cleanUrl + '"]');
        if ($sidebarLink.length) {
            $sidebarLink.addClass('active');
            title = $sidebarLink.text().trim(); // Use clean title from sidebar
            var $subMenu = $sidebarLink.closest('.sub-menu');
            if ($subMenu.length && !$subMenu.hasClass('show')) {
                $subMenu.addClass('show');
                $('.dropdown-toggle[data-dropdown="' + $subMenu.attr('id') + '"]').addClass('open');
            }
        }

        if (typeof window.loadContent === 'function') {
            window.loadContent(url, title, false);
        } else {
            // Fallback just in case
            $('#main_content').load(url);
        }
    });

    // ── Send Email to All (AUTO_EMAIL = 1) ───────────────────────────────────
    // Load stats badge on init
    $.get('send_report_email.php', { action: 'auto_email_stats' }, function (resp) {
        try {
            var d = typeof resp === 'object' ? resp : JSON.parse(resp);
            if (d.enabled_count !== undefined) {
                $('#cp-auto-email-stats').html(
                    '<i class="fas fa-users me-1"></i>' + d.enabled_count + ' auto-email enabled'
                );
            }
        } catch (e) {}
    });

    $('#sendAllBtn').on('click', function () {
        // Fetch the active DataTable instance dynamically
        var tableApi = $('#portfolioTable').DataTable();
        if (!tableApi) return;
        
        var allRows  = tableApi.rows().nodes();
        var clients  = [];

        $(allRows).each(function () {
            var $row     = $(this);
            var autoEmail = $row.data('auto-email');
            
            // Only include clients with AUTO_EMAIL = 1
            if (autoEmail == 1 || autoEmail === '1') {
                var code     = $row.find('.sendRequestLink').data('client-code');
                var name     = $row.find('.sendRequestLink').data('name');
                var family   = $row.find('input[type=checkbox]').is(':checked') ? 1 : 0;
                if (code) clients.push({ code: code, name: name, family: family });
            }
        });

        if (!clients.length) {
            alert('No clients with Auto Email enabled found in the current view.');
            return;
        }

        // Reset modal
        $('#sa-log').empty();
        $('#sa-progress-bar').css('width', '0%');
        $('#sa-progress-text').text('0 / ' + clients.length);
        $('#sa-percent').text('0%');
        $('#sa-final-msg').text('');
        $('#sa-close-btn').hide();
        $('#sa-download-log-btn').hide();
        $('#sa-summary').html(
            'Sending to <strong>' + clients.length + '</strong> client(s) with Auto Email enabled…'
        );
        $('#sendAllModal').modal('show');

        var sent = 0, succeeded = 0, failed = 0, skipped = 0;
        var total = clients.length;

        var logText = "================================================================================\n";
        logText += "                     BULK EMAIL REPORT LOG\n";
        logText += "                     Date: " + new Date().toLocaleString() + "\n";
        logText += "================================================================================\n\n";

        function sendNext(idx) {
            if (idx >= total) {
                // All done
                $('#sa-progress-bar')
                    .removeClass('progress-bar-animated progress-bar-striped')
                    .addClass(failed === 0 && skipped === 0 ? 'bg-success' : (succeeded === 0 ? 'bg-danger' : 'bg-warning'));
                $('#sa-final-msg').html(
                    (succeeded > 0 ? '<i class="fas fa-check-circle text-success me-1"></i>' : '') +
                    succeeded + ' sent, ' +
                    (failed > 0 ? '<i class="fas fa-times-circle text-danger ms-2 me-1"></i>' : '') +
                    failed + ' failed, ' +
                    (skipped > 0 ? '<i class="fas fa-exclamation-triangle text-warning ms-2 me-1"></i>' : '') +
                    skipped + ' skipped'
                );
                $('#sa-close-btn').show();
                $('#sendAllBtn').prop('disabled', false).html(
                    '<i class="fas fa-paper-plane me-1"></i>Send Email to All Auto Email Recipients'
                );

                logText += "\n================================================================================\n";
                logText += "SUMMARY: " + succeeded + " Sent, " + failed + " Failed, " + skipped + " Skipped\n";
                logText += "================================================================================\n";
                
                var blob = new Blob([logText], { type: 'text/plain;charset=utf-8' });
                var dlUrl = window.URL.createObjectURL(blob);
                $('#sa-download-log-btn').attr('href', dlUrl).attr('download', 'Email_Report_Log_' + new Date().toISOString().slice(0, 10) + '.txt').show();

                return;
            }

            var c = clients[idx];
            $.ajax({
                url    : 'send_report_email.php',
                type   : 'GET',
                data   : { action: 'send', i: c.code, family: c.family, is_bulk: 1 },
                success: function (resp) {
                    sent++;
                    var isErr = resp.toLowerCase().includes('error') || // Check for "error"
                                resp.toLowerCase().includes('could not');
                    var isSkipped = resp.toLowerCase().includes('skipped'); // Check for "skipped"

                    if (isErr) {
                        failed++;
                    } else if (isSkipped) {
                        skipped++;
                    } else {
                        succeeded++;
                    }

                    // Format raw HTML response into clean text for the log file
                    var cleanResp = resp.replace(/<br\s*[\/]?>/gi, ' - ').replace(/<[^>]*>?/gm, '').trim(); 
                    var statusText = isErr ? "FAILED " : (isSkipped ? "SKIPPED" : "SUCCESS");
                    logText += "[" + statusText + "] " + c.name + " (" + c.code + ") - " + cleanResp + "\n";

                    var pct = Math.round((sent / total) * 100);
                    $('#sa-progress-bar').css('width', pct + '%');
                    $('#sa-progress-text').text(sent + ' / ' + total);
                    $('#sa-percent').text(pct + '%');

                    var icon, color;
                    if (isErr) { icon = '&#10060;'; color = '#dc3545'; } // Red X
                    else if (isSkipped) { icon = '&#9888;'; color = '#ffc107'; } // Yellow warning ⚠️
                    else { icon = '&#9989;'; color = '#28a745'; } // Green check ✅

                    $('#sa-log').append(
                        '<div style="color:' + color + ';padding:2px 0;border-bottom:1px solid #eee;">' +
                        icon + ' ' + $('<span>').text(c.name).html() +
                        ' <span style="color:#888;font-size:11px;">(' + $('<span>').text(c.code).html() + ')</span> — ' +
                        $('<span>').text(resp).html() +
                        '</div>'
                    );
                    // Auto-scroll log
                    var log = document.getElementById('sa-log');
                    log.scrollTop = log.scrollHeight;
                },
                error: function (xhr) {
                    sent++; failed++;
                    var pct = Math.round((sent / total) * 100);
                    $('#sa-progress-bar').css('width', pct + '%');
                    $('#sa-progress-text').text(sent + ' / ' + total);
                    $('#sa-percent').text(pct + '%');

                    logText += "[ERROR  ] " + c.name + " (" + c.code + ") - HTTP Status " + xhr.status + "\n";

                    $('#sa-log').append(
                        '<div style="color:#dc3545;padding:2px 0;border-bottom:1px solid #eee;">' +
                        '&#10060; ' + $('<span>').text(c.name).html() +
                        ' — HTTP ' + xhr.status + '</div>'
                    );
                },
                complete: function () {
                    // Sequential: send next only after current completes
                    sendNext(idx + 1);
                }
            });
        }

        $('#sendAllBtn').prop('disabled', true).html(
            '<i class="fas fa-spinner fa-spin me-1"></i>Sending…'
        );
        sendNext(0);
    });

})(jQuery);
</script>
