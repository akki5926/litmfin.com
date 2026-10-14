<?php
require_once('auth_guard.php');

/**
 * AJAX endpoint for NSE SIP Data
 * File: getsipdataajax.php
 */

ob_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

$logsDir = __DIR__ . '/logs';
if (!file_exists($logsDir)) {
    @mkdir($logsDir, 0777, true);
}
ini_set('error_log', $logsDir . '/sip_api_errors.log');

require_once __DIR__ . '/../api/nseapiconnect.php';
require_once __DIR__ . '/database.php';

ob_clean();

// database.php sets display_errors to 1, we must turn it back off to prevent HTML bleeding into JSON
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_PARSE])) {
        while (ob_get_level()) { ob_end_clean(); }
        if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode([
            'success' => false,
            'error'   => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'],
            'html'    => '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <strong>Fatal PHP Error:</strong> ' . htmlspecialchars($error['message']) . '</div>'
        ]);
    }
});

header('Content-Type: application/json');

function sendJsonResponse($response) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode($response);
    exit;
}

$response = [
    'success'         => false,
    'error'           => '',
    'http_code'       => '',
    'response_status' => '',
    'data'            => [],
    'html'            => '',
    'db_save_result'  => null
];

try {
    $fromDate   = $_POST['fromDate']   ?? '';
    $toDate     = $_POST['toDate']     ?? '';
    $clientCode = $_POST['clientCode'] ?? '';
    $reportType = $_POST['reportType'] ?? 'BOTH';

    error_log("SIP API Request - fromDate: $fromDate, toDate: $toDate, clientCode: $clientCode");

    if (empty($clientCode) && (empty($fromDate) || empty($toDate))) {
        throw new Exception("Please either enter a Client Code OR select both From Date and To Date");
    }

    set_time_limit(300); // Allow up to 5 minutes for chunked API calls
    
    $startDt = null;
    $endDt = null;

    if (!empty($clientCode) && !empty($fromDate) && !empty($toDate)) {
        $startDt = new DateTime($fromDate);
        $endDt   = new DateTime($toDate);
        error_log("Case: Client Code + Date Range");
    } elseif (!empty($clientCode)) {
        $startDt = new DateTime('2019-01-01');
        $endDt   = new DateTime();
        error_log("Case: Only Client Code - fetching all records");
    } else {
        $startDt = new DateTime($fromDate);
        $endDt   = new DateTime($toDate);
        $clientCode = '';
        error_log("Case: Only Date Range - fetching all clients");
    }

    $nseApi    = new NseApiConnect();
    $allRawData = [];
    $lastHttpCode = 200;
    $sipApiFoundData = false;
    $swpApiFoundData = false;

    $currentStart = clone $startDt;
    while ($currentStart <= $endDt) {
        $currentEnd = clone $currentStart;
        $currentEnd->modify('+30 days');
        if ($currentEnd > $endDt) {
            $currentEnd = clone $endDt;
        }
        
        // Fetch SIP data if requested
        if ($reportType === 'SIP' || $reportType === 'BOTH') {
            $payloadChunk = [
                'xsip_reg_id'      => '',
                'client_code'      => $clientCode,
                'member_unique_ids' => '',
                'from_date'        => $currentStart->format('d-m-Y'),
                'to_date'          => $currentEnd->format('d-m-Y')
            ];
            
            error_log("SIP API Chunk Payload: " . json_encode($payloadChunk));
            $apiResult = $nseApi->getSipTransactionsFromNse($payloadChunk);
            
            $lastHttpCode = $apiResult['http_code'] ?? 'N/A';
            
            if (isset($apiResult['json'])) {
                $chunkStatus = $apiResult['json']['response_status'] ?? 'N/A';
                if ($chunkStatus !== 'S') {
                    $remark = strtolower($apiResult['json']['error_remark'] ?? '');
                    if (strpos($remark, 'no data') === false && strpos($remark, 'no record') === false && strpos($remark, 'not found') === false) {
                        throw new Exception($apiResult['json']['error_remark'] ?? 'Unknown API error');
                    }
                } else {
                    $chunkData = $apiResult['json']['report_data'] ?? [];
                    if (!empty($chunkData)) {
                        $allRawData = array_merge($allRawData, $chunkData);
                        $sipApiFoundData = true;
                    }
                }
            }
        }
        
        // Fetch SWP data if requested
        if ($reportType === 'SWP' || $reportType === 'BOTH') {
            if (method_exists($nseApi, 'getSwpTransactionsFromNse')) {
                $payloadChunkSwp = [
                    'swp_reg_id'       => '',
                    'client_code'      => $clientCode,
                    'member_unique_ids'=> '',
                    'from_date'        => $currentStart->format('d-m-Y'),
                    'to_date'          => $currentEnd->format('d-m-Y')
                ];
                
                error_log("SWP API Chunk Payload: " . json_encode($payloadChunkSwp));
                $apiResultSwp = $nseApi->getSwpTransactionsFromNse($payloadChunkSwp);
                
                $lastHttpCode = $apiResultSwp['http_code'] ?? $lastHttpCode;
                
                if (isset($apiResultSwp['json'])) {
                    $chunkStatusSwp = $apiResultSwp['json']['response_status'] ?? 'N/A';
                    if ($chunkStatusSwp === 'S') {
                        $chunkDataSwp = $apiResultSwp['json']['report_data'] ?? [];
                        if (!empty($chunkDataSwp)) {
                            $mappedSwpData = [];
                            foreach ($chunkDataSwp as $row) {
                                $mapped = $row;
                                $mapped['amc_name'] = $row['amc'] ?? null;
                                $mapped['folio_number'] = $row['folio_no'] ?? null;
                                $mapped['remarks'] = $row['remark'] ?? null;
                                $mapped['euin_no'] = $row['euin_number'] ?? null;
                                $mapped['sub_br_code'] = $row['subbrcode'] ?? null;
                                $mapped['internal_ref_no'] = $row['int_ref_no'] ?? null;
                                $mapped['primary_holder_email'] = $row['email_id'] ?? null;
                                $mapped['primary_holder_mobile'] = $row['mobile_no'] ?? null;
                                $mapped['first_order_today'] = $row['first_order_flag'] ?? null;
                                $mapped['sub_broker_arn'] = $row['sub_broker_arn_code'] ?? null;
                                
                                // Map to allow DB date filters to work natively
                                $mapped['xsip_registration_date'] = $row['swp_registration_date'] ?? null;
                                $mappedSwpData[] = $mapped;
                                $swpApiFoundData = true;
                            }
                            $allRawData = array_merge($allRawData, $mappedSwpData);
                        }
                    }
                }
            } else {
                error_log("NseApiConnect::getSwpTransactionsFromNse() is not defined. Skipping SWP data fetch.");
            }
        }
        
        $currentStart = clone $currentEnd;
        $currentStart->modify('+1 day'); // Step into the next chunk
    }

    $response['http_code'] = $lastHttpCode;
    $response['response_status'] = 'S';
    $response['success'] = true;
    $rawData = $allRawData;

    // -------------------------------------------------------
    // STEP 1: Save ALL raw API data to DB (no filtering first)
    // -------------------------------------------------------
    if (!empty($rawData)) {
        try {
            $db         = new MySQLDB();
            $saveResult = $db->saveNseSipDataToTable($rawData);
            $response['db_save_result'] = $saveResult;
            error_log("NSE SIP Save Result: " . json_encode($saveResult));
        } catch (Exception $dbException) {
            error_log("Database save error: " . $dbException->getMessage());
            $response['db_save_result'] = [
                'success' => false,
                'message' => 'Database save error: ' . $dbException->getMessage()
            ];
        }
    } else {
        $response['db_save_result'] = [
            'success' => true,
            'message' => 'No ' . ($reportType === 'BOTH' ? 'SIP or SWP' : $reportType) . ' data returned from API to save'
        ];
    }

    // -------------------------------------------------------
    // STEP 2: Read filtered + sorted data FROM DB for display
    // -------------------------------------------------------
    try {
        $db = $db ?? new MySQLDB();
        $response['data'] = $db->getNseSipDataFromDB($clientCode, $fromDate, $toDate);
        error_log("DB query returned " . count($response['data']) . " record(s)");
    } catch (Exception $dbException) {
        error_log("Database read error: " . $dbException->getMessage());
        $response['data'] = [];
    }

    // Split the data for rendering
    $sipData = [];
    $swpData = [];
    if (!empty($response['data'])) {
        foreach ($response['data'] as $r) {
            if (!empty($r['swp_registration_no'])) {
                if ($reportType === 'SWP' || $reportType === 'BOTH') $swpData[] = $r;
            } else {
                if ($reportType === 'SIP' || $reportType === 'BOTH') $sipData[] = $r;
            }
        }
    }

    // -------------------------------------------------------
    // STEP 3: Build HTML from DB results
    // -------------------------------------------------------
    ob_start();
    ?>

    <?php if (!empty($response['db_save_result'])): ?>
        <?php 
        $isNoDataToSaveMsg = strpos($response['db_save_result']['message'], 'No data') !== false;
        $willShowNoDataFound = empty($sipData) && empty($swpData);
        
        if ($isNoDataToSaveMsg && $willShowNoDataFound) {
            // Hide this redundant message, as the one below is more specific.
        } elseif ($response['db_save_result']['success']) {
        ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fa fa-database"></i>
                <strong>Database:</strong> <?php echo htmlspecialchars($response['db_save_result']['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php } else { ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-triangle"></i>
                <strong>Database Warning:</strong> <?php echo htmlspecialchars($response['db_save_result']['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php } ?>
    <?php endif; ?>

    <?php if (!empty($sipData) || !empty($swpData)): ?>

        <?php if (!empty($sipData)): ?>
        <h5 class="mb-3">SIP Registrations</h5>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Client Code</th>
                        <th>Client Name</th>
                        <th>Folio No</th>
                        <th>Registration No</th>
                        <th>Reg Date</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Scheme Name</th>
                        <th>Frequency</th>
                        <th>Amount (₹)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                $sipCounter = 1;
                foreach ($sipData as $r):
                        $status = trim($r['status'] ?? '-');
                        $badgeClass = 'secondary';
                        if (in_array(strtoupper($status), ['ACTIVE'])) {
                            $badgeClass = 'success';
                        } elseif (in_array(strtoupper($status), ['CXL', 'CEASED', 'CANCELLED'])) {
                            $badgeClass = 'danger';
                        } elseif (in_array(strtoupper($status), ['PENDING'])) {
                            $badgeClass = 'warning';
                        }

                        // Format dates from Y-m-d (DB) to d-M-Y for display
                        $formatDate = function($dateStr) {
                            if (empty($dateStr) || $dateStr === '0000-00-00') return '-';
                            $d = DateTime::createFromFormat('Y-m-d', $dateStr);
                            return $d ? $d->format('d M Y') : $dateStr;
                        };
                    ?>
                        <tr>
                        <td><?php echo $sipCounter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['client_code'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['client_name'] ?? '-'); ?></td>
                            <td><small><?php echo htmlspecialchars($r['folio_number'] ?? '-'); ?></small></td>
                            <td><small><?php echo htmlspecialchars($r['xsip_registration_no'] ?? '-'); ?></small></td>
                            <td><?php echo $formatDate($r['xsip_registration_date'] ?? ''); ?></td>
                            <td><?php echo $formatDate($r['start_date'] ?? ''); ?></td>
                            <td><?php echo $formatDate($r['end_date'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(substr($r['scheme_name'] ?? '-', 0, 50)); ?></td>
                            <td><?php echo htmlspecialchars($r['frequency_type'] ?? '-'); ?></td>
                            <td class="text-end">₹ <?php echo number_format(floatval($r['installments_amount'] ?? 0), 2); ?></td>
                            <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary">
                    <tr>
                    <th colspan="10" class="text-end"><strong>Total SIP Records:</strong></th>
                    <th colspan="2"><strong><?php echo count($sipData); ?></strong></th>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php elseif ($reportType === 'SIP' || $reportType === 'BOTH'): ?>
        <div class="alert alert-warning mb-4"><i class="fa fa-info-circle"></i> No SIP Registrations found.</div>
        <?php endif; ?>

        <?php if (!empty($swpData)): ?>
        <h5 class="mt-4 mb-3">SWP Registrations</h5>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>#</th>
                        <th>Client Code</th>
                        <th>Client Name</th>
                        <th>Folio No</th>
                        <th>SWP Reg No</th>
                        <th>Reg Date</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Scheme Name</th>
                        <th>Frequency</th>
                        <th>Withdrawal Amount (₹)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $swpCounter = 1;
                    foreach ($swpData as $r):
                        $status = trim($r['status'] ?? '-');
                        $badgeClass = 'secondary';
                        if (in_array(strtoupper($status), ['ACTIVE'])) {
                            $badgeClass = 'success';
                        } elseif (in_array(strtoupper($status), ['CXL', 'CEASED', 'CANCELLED'])) {
                            $badgeClass = 'danger';
                        } elseif (in_array(strtoupper($status), ['PENDING'])) {
                            $badgeClass = 'warning';
                        }

                        $formatDate = function($dateStr) {
                            if (empty($dateStr) || $dateStr === '0000-00-00') return '-';
                            $d = DateTime::createFromFormat('Y-m-d', $dateStr);
                            return $d ? $d->format('d M Y') : $dateStr;
                        };
                    ?>
                        <tr>
                            <td><?php echo $swpCounter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['client_code'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['client_name'] ?? '-'); ?></td>
                            <td><small><?php echo htmlspecialchars($r['folio_number'] ?? '-'); ?></small></td>
                            <td><small><?php echo htmlspecialchars($r['swp_registration_no'] ?? '-'); ?></small></td>
                            <td><?php echo $formatDate($r['swp_registration_date'] ?? ''); ?></td>
                            <td><?php echo $formatDate($r['swp_start_date'] ?? ''); ?></td>
                            <td><?php echo $formatDate($r['swp_end_date'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(substr($r['scheme_name'] ?? '-', 0, 50)); ?></td>
                            <td><?php echo htmlspecialchars($r['frequency_type'] ?? '-'); ?></td>
                            <td class="text-end">₹ <?php echo number_format(floatval($r['withdrawl_amount'] ?? 0), 2); ?></td>
                            <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-primary">
                    <tr>
                        <th colspan="10" class="text-end"><strong>Total SWP Records:</strong></th>
                        <th colspan="2"><strong><?php echo count($swpData); ?></strong></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php elseif ($reportType === 'SWP' || $reportType === 'BOTH'): ?>
        <div class="alert alert-warning mb-4"><i class="fa fa-info-circle"></i> No SWP Registrations found.</div>
        <?php endif; ?>

        <div class="alert alert-info mt-3">
            <small>
                <i class="fa fa-info-circle"></i>
            Showing <?php echo count($sipData) + count($swpData); ?> record(s) from database |
            Filter Type: <?php echo htmlspecialchars($reportType); ?> |
                Sorted by Registration Date (Newest first)
                <?php if (!empty($clientCode) && !empty($fromDate) && !empty($toDate)): ?>
                    <br><strong>Filter:</strong>
                    Client Code: <?php echo htmlspecialchars($clientCode); ?> |
                    Date Range: <?php echo htmlspecialchars($fromDate); ?> to <?php echo htmlspecialchars($toDate); ?>
                <?php elseif (!empty($clientCode)): ?>
                    <br><strong>Filter:</strong>
                    Client Code: <?php echo htmlspecialchars($clientCode); ?> | Showing all available records
                <?php elseif (!empty($fromDate) && !empty($toDate)): ?>
                    <br><strong>Filter:</strong>
                    Date Range: <?php echo htmlspecialchars($fromDate); ?> to <?php echo htmlspecialchars($toDate); ?>
                <?php endif; ?>
            </small>
        </div>

    <?php else: ?>

        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fa fa-info-circle"></i> <strong>No <?php 
                if ($reportType === 'BOTH') echo 'SIP or SWP';
                else echo htmlspecialchars($reportType);
            ?> Data Found</strong><br>
            <?php if (!empty($clientCode)): ?>
                Client Code: <strong><?php echo htmlspecialchars($clientCode); ?></strong><br>
            <?php endif; ?>
            <?php if (!empty($fromDate) && !empty($toDate)): ?>
                Date Range: <strong><?php echo htmlspecialchars($fromDate); ?> to <?php echo htmlspecialchars($toDate); ?></strong>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

    <?php endif; ?>

    <?php
    $response['html'] = ob_get_clean();

    sendJsonResponse($response);

} catch (Exception $e) {
} catch (Throwable $e) {
    error_log("SIP API Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    ob_end_clean();

    $errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle"></i>
                    <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';

    sendJsonResponse([
        'success' => false,
        'error'   => $e->getMessage(),
        'html'    => $errorHtml
    ]);
}
?>
