<?php
require_once('auth_guard.php');

if(!isset($_SESSION)){session_start();}
require_once('database.php');
$db = new MYSQLDB();


$superUser = $_SESSION["superuser"] ?? 0;
$isSubAdmin = ($superUser == 0);
$subAdminId = $_SESSION["userid"] ?? 0;

if ($isSubAdmin) {
    $clients = $db->getCasPortfolioClients4subAdmin($subAdminId);
} else {
    $clients = $db->getCasPortfolioClients();
}

$advisorDetails = $db->getAdvisorDetails();
$advisorName = $advisorDetails['name'] ?? 'Current Advisor';
$advisorArn = $advisorDetails['arn_number'] ?? '';

// ── AJAX Endpoint: Inline Quick Summary & Holdings ─────────────────────────────
if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], ['get_summary', 'get_holdings'])) {
    require_once('cas_portfolio_calc.php');
    
    $ucc = $_REQUEST['ucc'] ?? '';
    $action = $_REQUEST['action'];
    $advFilter = $_REQUEST['adv_filter'] ?? 'exclude';
    $advArn = $_REQUEST['adv_arn'] ?? '';

    // Subadmin check
    $isSubAdmin = isset($_SESSION["superuser"]) && $_SESSION["superuser"] == 0;
    if ($isSubAdmin) {
        $subAdminId = $_SESSION["userid"] ?? 0;
        if (!$db->isClientAssignedToSubadmin($ucc, $subAdminId)) {
            echo '<div class="alert alert-danger py-2 px-3 mt-2 mb-0" style="font-size:12px;"><i class="fas fa-times-circle me-1"></i>Access Denied: Not assigned to this subadmin.</div>';
            exit;
        }
    }
    
    ob_start();
    try {
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
            
            $casData = calculateCasHoldings($ucc, $advFilter, $advArn);
            $totalAssetInfo = $casData[3] ?? [];
            if (!empty($totalAssetInfo[0])) {
                $memberAssets = $totalAssetInfo[0];
                foreach ($summableFields as $field) {
                    $ta[$field] = (float)str_replace(',', '', $memberAssets[$field] ?? 0);
                }
                
                $totalCost = (float)str_replace(',', '', $memberAssets['Total_Cost_Value'] ?? 0);
                $totalCurrent = (float)str_replace(',', '', $memberAssets['Total_Current_Value'] ?? 0);
                $totalUnrealized = (float)str_replace(',', '', $memberAssets['Total_Unrealized'] ?? 0);
                $ta['Total_Holding_Return'] = $memberAssets['Total_Holding_Return'] ?? 0;
            }
            
            if ($totalCost > 0 || $totalCurrent > 0) {
                echo '<div class="mt-2 p-3 bg-white border rounded shadow-sm text-start" style="font-size:12px;">';
                echo '<div class="fw-bold text-primary border-bottom pb-2 mb-2"><i class="fas fa-chart-pie me-1"></i>Assets &amp; Returns</div>';
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
                
                if (isset($ta['Total_Holding_Return'])) {
                    echo '<div class="mt-2 text-end text-muted" style="font-size:12px;">Overall XIRR: <span class="fw-bold text-dark bg-light px-2 py-1 border rounded">'.number_format((float)$ta['Total_Holding_Return'], 2).'%</span></div>';
                }
                echo '</div>';
            } else {
                echo '<div class="alert alert-warning py-2 px-3 mt-2 mb-0" style="font-size:12px;"><i class="fas fa-exclamation-circle me-1"></i>No active investments found.</div>';
            }
        } elseif ($action === 'get_holdings') {
            $allHoldings = [];
            $casData = calculateCasHoldings($ucc, $advFilter, $advArn);
            $portfolioInfo = $casData[0] ?? [];
            
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

            if (!empty($allHoldings)) {
                usort($allHoldings, function($a, $b) {
                    return (float)str_replace(',', '', $b['value']) <=> (float)str_replace(',', '', $a['value']);
                });

                echo '<div class="mt-2 p-3 bg-white border rounded shadow-sm text-start" style="font-size:12px; max-height: 400px; overflow-y: auto;">';
                echo '<div class="fw-bold text-primary border-bottom pb-2 mb-2"><i class="fas fa-list me-1"></i>Active Holdings</div>';
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
    } catch (Throwable $e) {
        error_log("CAS_PORTFOLIO_LIST_ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        echo '<div class="alert alert-danger py-2 px-3 mt-2 mb-0" style="font-size:12px;"><i class="fas fa-times-circle me-1"></i>Error calculating requested data.</div>';
    }
    
    ob_end_flush();
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
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-file-pdf me-2"></i>CAS Portfolio Reports</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4 p-3 bg-light rounded border">
                        <label class="me-3 fw-bold text-dark"><i class="fas fa-filter me-1"></i> Filter Transactions:</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="advisor_filter" id="filterExclude" value="exclude" checked>
                            <label class="form-check-label" for="filterExclude">Exclude "<?php echo htmlspecialchars($advisorName); ?>"</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="advisor_filter" id="filterInclude" value="include">
                            <label class="form-check-label" for="filterInclude">Include "<?php echo htmlspecialchars($advisorName); ?>"</label>
                        </div>
                        <input type="hidden" id="advisor_arn" value="<?php echo htmlspecialchars($advisorArn); ?>">
                    </div>
                    
                    <div id="casReportStatus"></div>
                    
                    <div class="table-responsive">
                        <table id="casReportsTable" class="table table-striped table-hover border" style="width:100%; font-size:13px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%;">Client Code</th>
                                    <th style="width: 15%;">Client Name</th>
                                    <th style="width: 10%;">Last CAS Upload</th>
                                    <th class="text-center" style="width: 25%;">Holdings</th>
                                    <th class="text-center" style="width: 20%;">Assets &amp; Returns</th>
                                    <th class="text-center" style="width: 10%;">Complete Report</th>
                                    <th class="text-center" style="width: 10%;">Debug Excel</th>
                                </tr>
                            </thead>
                            <tbody>
								<?php foreach($clients as $client): 
                                    $ucc = $client['CLIENT_CODE'];
                                ?>
								  <tr>	    
										<td>
                                            <a href="#"
                                               class="client-code-link ucc-nav-link"
                                               data-url="getUccDetail.php?iin=<?php echo urlencode($ucc); ?>&ref=cas_portfolio_list.php"
                                               data-title="<?php echo htmlspecialchars($ucc); ?>">
                                                <?php echo htmlspecialchars($ucc); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($client['client_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('d M Y', strtotime($client['last_updated'])); ?></td>
                                        <td class="text-center holdings-cell">
                                            <button class="btn btn-sm btn-outline-primary viewHoldingsLink" data-client-code="<?php echo htmlspecialchars($ucc); ?>" title="View Holdings">
                                                <i class="fas fa-list"></i> View Holdings
                                            </button>
                                            <div class="holdings-content" style="display:none;"></div>
                                        </td>
                                        <td class="text-center summary-cell">
                                            <button class="btn btn-sm btn-outline-info viewSummaryLink" data-client-code="<?php echo htmlspecialchars($ucc); ?>" title="View Assets &amp; Returns">
                                                <i class="fas fa-eye"></i> View Assets &amp; Returns
                                            </button>
                                            <div class="summary-content" style="display:none;"></div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-info text-white viewLink" data-client-code="<?php echo htmlspecialchars($ucc); ?>" title="View PDF">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-primary downloadLink" data-client-code="<?php echo htmlspecialchars($ucc); ?>" title="Download PDF">
                                                    <i class="fas fa-download"></i> Download
                                                </button>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-success downloadExcelLink" data-client-code="<?php echo htmlspecialchars($ucc); ?>" title="Download Excel">
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

<script>


(function ($) {

    function initCASTable() {
        // ── DataTable — destroy first if already initialised ─────────────────────
        if ($.fn.DataTable.isDataTable('#casReportsTable')) {
            $('#casReportsTable').DataTable().destroy();
        }

        var casTable = $('#casReportsTable').DataTable({
            destroy: true,
            pageLength : 25,
            lengthMenu : [[10, 25, 50, -1], [10, 25, 50, 'All']],
            order      : [[2, 'desc']],
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

    initCASTable();

    if (typeof window.registerPageReinit === 'function') {
        window.registerPageReinit(initCASTable);
    }

    // ── Navigate to UCC detail page ──────────────────────────────────────────
    $(document).off('click.casUcc').on('click.casUcc', '#casReportsTable .ucc-nav-link', function (e) {
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
    $(document).off('click.casView').on('click.casView', '#casReportsTable .viewLink', function (e) {
        e.preventDefault();
        var code = $(this).data('client-code');
        var advFilter = $('input[name="advisor_filter"]:checked').val();
        var advArn = $('#advisor_arn').val();
        var url  = 'cas_generate_report.php?ucc=' + encodeURIComponent(code) + '&action=view&adv_filter=' + encodeURIComponent(advFilter) + '&adv_arn=' + encodeURIComponent(advArn);

        $('#page-title').text('View CAS Report: ' + code);
        
        $('#main_content').children(':not(style, script, .modal)').hide();
        $('#pdfViewerContainer').remove();
        $('#main_content').append(`
            <div id="pdfViewerContainer" class="container-fluid p-0">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-file-pdf me-2"></i>CAS Report: ${code}</h5>
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
    $(document).off('click.casHoldings').on('click.casHoldings', '#casReportsTable .viewHoldingsLink', function (e) {
        e.preventDefault();
        var btn = $(this);
        var container = btn.siblings('.holdings-content');
        var code = btn.data('client-code');
        var advFilter = $('input[name="advisor_filter"]:checked').val();
        var advArn = $('#advisor_arn').val();

        if (container.is(':visible')) {
            container.slideUp();
            btn.html('<i class="fas fa-list"></i> View Holdings').removeClass('btn-primary text-white').addClass('btn-outline-primary');
            return;
        }
        
        btn.html('<i class="fas fa-spinner fa-spin"></i> Loading...').prop('disabled', true);
        
        $.ajax({
            url: 'cas_portfolio_list.php',
            type: 'GET',
            data: { action: 'get_holdings', ucc: code, adv_filter: advFilter, adv_arn: advArn },
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
    $(document).off('click.casSummary').on('click.casSummary', '#casReportsTable .viewSummaryLink', function (e) {
        e.preventDefault();
        var btn = $(this);
        var container = btn.siblings('.summary-content');
        var code = btn.data('client-code');
        var advFilter = $('input[name="advisor_filter"]:checked').val();
        var advArn = $('#advisor_arn').val();

        if (container.is(':visible')) {
            container.slideUp();
            btn.html('<i class="fas fa-eye"></i> View Assets &amp; Returns').removeClass('btn-info text-white').addClass('btn-outline-info');
            return;
        }
        
        btn.html('<i class="fas fa-spinner fa-spin"></i> Loading...').prop('disabled', true);
        
        $.ajax({
            url: 'cas_portfolio_list.php',
            type: 'GET',
            data: { action: 'get_summary', ucc: code, adv_filter: advFilter, adv_arn: advArn },
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
    $(document).off('click.casClose').on('click.casClose', '#closePdfViewerBtn', function (e) {
        e.preventDefault();
        $('#pdfViewerContainer').remove();
        $('#main_content').children(':not(style, script, .modal)').fadeIn();
        $('#page-title').text('CAS Portfolio Reports');
    });

    // ── Sidebar link fallback ─────────────────────────────────────────────────
    $(document).off('sameUrlClicked.casFallback').on('sameUrlClicked.casFallback', function(e, url) {
        if (url === 'cas_portfolio_list.php' && $('#pdfViewerContainer').length) {
            $('#closePdfViewerBtn').click();
        }
    });

    // ── Download ────────────────────────────────────────
    $(document).off('click.casDownload').on('click.casDownload', '#casReportsTable .downloadLink', function (e) {
        e.preventDefault();
        var btn      = $(this);
        var origHtml = btn.html();
        var code    = $(this).data('client-code');
        var advFilter = $('input[name="advisor_filter"]:checked').val();
        var advArn = $('#advisor_arn').val();
        var url     = 'cas_generate_report.php?ucc=' + encodeURIComponent(code) + '&action=download&adv_filter=' + encodeURIComponent(advFilter) + '&adv_arn=' + encodeURIComponent(advArn);
        
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true).addClass('disabled');
        
        setTimeout(function() {
            btn.html(origHtml).prop('disabled', false).removeClass('disabled');
        }, 6000);
        
        window.location.href = url;
    });

    // ── Download Excel ────────────────────────────────────────
    $(document).off('click.casExcel').on('click.casExcel', '#casReportsTable .downloadExcelLink', function (e) {
        e.preventDefault();
        var code    = $(this).data('client-code');
        var advFilter = $('input[name="advisor_filter"]:checked').val();
        var advArn = $('#advisor_arn').val();
        var url     = 'cas_generate_report.php?ucc=' + encodeURIComponent(code) + '&action=download_excel&adv_filter=' + encodeURIComponent(advFilter) + '&adv_arn=' + encodeURIComponent(advArn);
        
        window.location.href = url;
    });

})(jQuery);
</script>