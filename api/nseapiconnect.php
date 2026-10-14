<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Required dependencies
 */
require_once __DIR__ . '/db/mfddb.php';
require_once __DIR__ . '/nseapi.php';

class NseApiConnect
{
    private $db;
    private $nseApi;
    private $creds;

    public function __construct()
    {
        $this->db = new MfdDb();
        $this->nseApi = new NseAPI();
        $this->initCredentials();
    }

    /**
     * Load NSE Invest credentials from DB
     */
    private function initCredentials(): void
    {
        try {
            $nseInvestCredential = $this->db->getNseInvestApiCredential();
            
            if (!$nseInvestCredential) {
                throw new Exception('NSE Invest credentials not found in database');
            }
            
            // Validate required fields
            $requiredFields = ['secret_key', 'member_key', 'tm_code', 'login_user_id', 'api_url'];
            foreach ($requiredFields as $field) {
                if (empty($nseInvestCredential[$field])) {
                    throw new Exception("Missing required credential field: $field");
                }
            }

            $this->creds = [
                'secret_key'    => $nseInvestCredential['secret_key'],
                'member_key'    => $nseInvestCredential['member_key'],
                'tm_code'       => $nseInvestCredential['tm_code'],
                'login_user_id' => $nseInvestCredential['login_user_id'],
                'api_url'       => $nseInvestCredential['api_url'],
            ];
            
        } catch (Exception $e) {
            error_log("NseApiConnect credential error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Call NSE UCC API for client registration
     */
    private function callNseUccApi($payload): array
    {
        try {
            $urlPostFix = "/nsemfdesk/api/v2/registration/CLIENTCOMMON183";
            
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            
            error_log("CLIENTCOMMON183 API Response: " . json_encode($apiResult));
            
            return $apiResult;
            
        } catch (Exception $e) {
            error_log("callNseUccApi error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'UCC API call failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create UCC for a user
     */
    public function createUcc(int $userId): array
    {
        try {
            $payload = $this->db->getUserAllData($userId);
            $apiResult = $this->callNseUccApi($payload);
            return $apiResult;
        } catch (Exception $e) {
            error_log("createUcc error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Create UCC failed: ' . $e->getMessage()
            ];
        }
    }
   
    /**
     * Create FATCA on NSE for a user
     */
    // public function createFatcaOnNse(int $userId): array
    // {
    //     try {
    //         $payload = $this->db->getUserFatcaDataPayload($userId);
            
    //         $urlPostFix = "/nsemfdesk/api/v2/registration/FATCA";
    //         $apiResult = $this->nseApi->callNseApi(
    //             $this->creds,
    //             $urlPostFix,
    //             $payload
    //         );
            
    //         return $apiResult;
    //     } catch (Exception $e) {
    //         error_log("createFatcaOnNse error: " . $e->getMessage());
    //         return [
    //             'success' => false,
    //             'error' => 'FATCA API call failed: ' . $e->getMessage()
    //         ];
    //     }
    // }
    
    public function createFatcaOnNseCommon(int $userId): array
    {
        try {
            $payload = $this->db->getUserFatcaDataPayload($userId);
            
            $urlPostFix = "/nsemfdesk/api/v2/registration/FATCA_COMMON";
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            
            return $apiResult;
        } catch (Exception $e) {
            error_log("createFatcaOnNseCommon error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'FATCA API call failed: ' . $e->getMessage()
            ];
        }
    }
    /**
     * Call NSE KYC API
     */
    private function callNseKycApi($payload, int $userId = 0): array
    {
        try {
            $urlPostFix = "/nsemfdesk/api/v2/utility/KYC_CHECK";
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            return $apiResult;
        } catch (Exception $e) {
            error_log("callNseKycApi error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'KYC API call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Perform KYC check for a user
     */
    public function KycCheck(int $userId): array
    {
        try {
            $user = $this->db->getUserById($userId);
            if (!$user || empty($user['PRIMARY_HOLDER_PAN'])) {
                return [
                    'success' => false,
                    'error' => 'User or PAN not found'
                ];
            }
            
            $payload = [
                'pan_no' => $user['PRIMARY_HOLDER_PAN']
            ];
            
            $apiResult = $this->callNseKycApi($payload);
            return $apiResult;
        } catch (Exception $e) {
            error_log("KycCheck error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'KYC Check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Call NSE Buy API for transactions
     */
    public function callNseBuyApi($payload): array
    { 
        try {
            $urlPostFix = "/nsemfdesk/api/v2/transaction/NORMAL";
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            
            return $apiResult;
        } catch (Exception $e) {
            error_log("callNseBuyApi error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Buy API call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Call NSE redemption API for sell transactions.
     */
    public function callNseRedemptionApi($payload): array
    {
        try {
            $urlPostFix = "/nsemfdesk/api/v2/transaction/NORMAL";
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );

            return $apiResult;
        } catch (Exception $e) {
            error_log("callNseRedemptionApi error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Redemption API call failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get transaction details from NSE
     */
    public function getTransactionDetailFromNse($payload, array $options = []): array
    {
        try {
            @set_time_limit(180);
            $urlPostFix = "/nsemfdesk/api/v2/reports/TRANSACTION_DETAIL_REPORT";
            $callOptions = array_merge(['timeout' => 180], $options);
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload,
                $callOptions
            );
            
            return $apiResult;
        } catch (Exception $e) {
            error_log("getTransactionDetailFromNse error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Transaction detail API call failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get SIP Transactions from NSE API
     */
    public function getSipTransactionsFromNse($payload): array
    {
        try {
            if (empty($payload['from_date']) || empty($payload['to_date'])) {
                return [
                    'http_code' => 400,
                    'json' => [
                        'response_status' => 'E',
                        'error_remark' => 'from_date and to_date are required fields',
                        'report_data' => []
                    ],
                    'error' => 'from_date and to_date are required fields'
                ];
            }

            if (!isset($payload['xsip_reg_id'])) $payload['xsip_reg_id'] = '';
            if (!isset($payload['client_code'])) $payload['client_code'] = '';
            if (!isset($payload['member_unique_ids'])) $payload['member_unique_ids'] = '';
            
            $urlPostFix = "/nsemfdesk/api/v2/reports/XSIP_REG_REPORT";
            
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            
            return [
                'http_code' => $apiResult['http_code'] ?? 0,
                'json' => $apiResult['json'] ?? null,
                'response' => $apiResult['response'] ?? null,
                'error' => $apiResult['error'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("getSipTransactionsFromNse error: " . $e->getMessage());
            return [
                'http_code' => 0,
                'json' => null,
                'response' => null,
                'error' => 'SIP transactions API call failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Alternative method name for backward compatibility
     */
    public function callNseSipApi($payload): array
    {
        return $this->getSipTransactionsFromNse($payload);
    }
    
    /**
     * Upload cancelled cheque to NSE API
     */
    public function uploadCancelledCheque($payload): array
    {
        try {
            $logPayload = $payload;
            if (isset($logPayload['file_data'])) {
                $logPayload['file_data'] = 'BASE64_DATA...[' . strlen($payload['file_data']) . ' bytes]';
            }
            
            $urlPostFix = "/nsemfdesk/api/v2/fileupload/CANCELCHEQUE";
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            
            return $apiResult;
        } catch (Exception $e) {
            error_log("uploadCancelledCheque error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Cheque upload API call failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get master data from NSE
     */
    public function getMasterFromNse($payload): array
    {
        try {
            $urlPostFix = "/nsemfdesk/api/v2/reports/MASTER_DOWNLOAD";
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            return $apiResult;
        } catch (Exception $e) {
            error_log("getMasterFromNse error: " . $e->getMessage());
            return [
                'success' => false,
                'error'   => 'MASTER_DOWNLOAD API failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Register SIP with NSE XSIP API
     */
    public function callNseXsipApi($payload): array
    {
        try {
            error_log("in callNseXsipApi payload = " . print_r($payload, true));
            
            $urlPostFix = "/nsemfdesk/api/v2/registration/product/XSIP";
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            
            error_log("in callNseXsipApi apiResult = " . print_r($apiResult, true));
            return $apiResult;
        } catch (Exception $e) {
            error_log("callNseXsipApi error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'XSIP registration API call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Register Mandate with NSE MANDATE API (UPDATED with all fields)
     */
    public function registerMandateOnNse(int $userId, int $mandateDbId): array
    {
        try {
            $user = $this->db->getUserById($userId);

            if (!$user || empty($user['CLIENT_CODE'])) {
                $this->db->updateMandateFromNse($mandateDbId, [
                    'client_code'       => '',
                    'client_name'       => '',
                    'member_code'       => '',
                    'bank_name'         => '',
                    'bank_branch'       => '',
                    'mandate_type'      => 'E',
                    'mandate_collection_type' => 'ENACH',
                    'account_no'        => '',
                    'ac_type'           => 'SB',
                    'ifsc_code'         => '',
                    'micr_no'           => '',
                    'registration_id'   => '',
                    'umrn_no'           => '',
                    'reg_status'        => 'FAILED',
                    'reg_remark'        => 'User or CLIENT_CODE not found. Complete KYC/UCC registration first.',
                    'member_mandate_no' => null,
                    'registration_date' => date('Y-m-d'),
                ]);
                return [
                    'success' => false,
                    'error'   => 'User or CLIENT_CODE not found',
                    'json'    => ['reg_data' => [['reg_status' => 'FAILED', 'reg_remark' => 'User or CLIENT_CODE not found']]]
                ];
            }

            $mandate = $this->db->getMandateById($mandateDbId);

            if (!$mandate) {
                return [
                    'success' => false,
                    'error'   => 'Mandate record not found'
                ];
            }

            $startDate = date('d/m/Y', strtotime($mandate['start_date']));
            $endDate   = date('d/m/Y', strtotime($mandate['end_date']));

            $payload = [
                'reg_data' => [
                    [
                        'client_code'        => $user['CLIENT_CODE'],
                        'amount'             => (string) $mandate['amount'],
                        'mandate_type'       => 'E',
                        'account_no'         => $user['ACCOUNT_NO_1'] ?? '',
                        'ac_type'            => $user['ACCOUNT_TYPE_1'] ?? 'SB',
                        'ifsc_code'          => $user['IFSC_CODE_1'] ?? '',
                        'micr_code'          => '',
                        'start_date'         => $startDate,
                        'end_date'           => $endDate,
                        'member_mandate_no'  => $mandate['member_mandate_no'] ?? '',
                    ]
                ]
            ];

            $urlPostFix = "/nsemfdesk/api/v2/registration/product/MANDATE";
            $apiResult  = null;
            $apiError   = null;

            try {
                $apiResult = $this->nseApi->callNseApi(
                    $this->creds,
                    $urlPostFix,
                    $payload
                );
            } catch (\RuntimeException $httpEx) {
                $apiError = $httpEx->getMessage();
                error_log("registerMandateOnNse HTTP error: " . $apiError);

                $jsonFromError = null;
                if (preg_match('/\{.*\}/s', $apiError, $m)) {
                    $jsonFromError = json_decode($m[0], true);
                }
                $apiResult = [
                    'http_code' => 0,
                    'response'  => $apiError,
                    'json'      => $jsonFromError,
                    'error'     => $apiError,
                ];
            }

            $json    = $apiResult['json'] ?? null;
            $regData = [];

            if (is_array($json)) {
                if (!empty($json['reg_data'][0]) && is_array($json['reg_data'][0])) {
                    $regData = $json['reg_data'][0];
                } elseif (!empty($json['reg_details'][0]) && is_array($json['reg_details'][0])) {
                    $regData = $json['reg_details'][0];
                } elseif (isset($json[0]) && is_array($json[0])) {
                    $regData = $json[0];
                } elseif (isset($json['reg_status'])) {
                    $regData = $json;
                }
            }

            foreach ($regData as $k => $v) {
                if (is_string($v)) {
                    $regData[$k] = trim($v);
                }
            }

            $memberMandateNo = $regData['member_mandate_no'] ?? '';
            if ($memberMandateNo === '') {
                $memberMandateNo = null;
            }

            $updateData = [
                'mandate_id'             => $regData['mandate_id'] ?? null,
                'client_code'            => $regData['client_code'] ?? $user['CLIENT_CODE'],
                'client_name'            => $user['PRIMARY_HOLDER_NAME'] ?? '',
                'member_code'            => $this->creds['tm_code'] ?? '',
                'bank_name'              => $regData['bank_name'] ?? '',
                'bank_branch'            => $regData['bank_branch'] ?? '',
                'mandate_type'           => $regData['mandate_type'] ?? 'E',
                'mandate_collection_type'=> 'ENACH',
                'account_no'             => $regData['account_no'] ?? ($user['ACCOUNT_NO_1'] ?? ''),
                'ac_type'                => $regData['ac_type'] ?? ($user['ACCOUNT_TYPE_1'] ?? 'SB'),
                'ifsc_code'              => $regData['ifsc_code'] ?? ($user['IFSC_CODE_1'] ?? ''),
                'micr_no'                => $regData['micr_code'] ?? '',
                'registration_id'        => $regData['reg_id'] ?? '',
                'umrn_no'                => $regData['umrn_no'] ?? '',
                'reg_status'             => $regData['reg_status'] ?? ($apiError ? 'API_ERROR' : 'PENDING'),
                'reg_remark'             => trim($regData['reg_remark'] ?? ($apiError ?? '')),
                'member_mandate_no'      => $memberMandateNo,
                'registration_date'      => date('Y-m-d'),
            ];

            $updated = $this->db->updateMandateFromNse($mandateDbId, $updateData);

            if (!is_array($apiResult['json'] ?? null)) {
                $apiResult['json'] = [];
            }
            if (empty($apiResult['json']['reg_data']) && !empty($regData)) {
                $apiResult['json']['reg_data'] = [$regData];
            }

            return $apiResult;

        } catch (Exception $e) {
            error_log("registerMandateOnNse error: " . $e->getMessage());
            return [
                'success' => false,
                'error'   => 'Mandate NSE registration failed: ' . $e->getMessage()
            ];
        }
    }
    
    // ==================== MANDATE STATUS API METHODS ====================
    
    /**
     * Get Mandate Status from NSE API
     * Fetches mandate registration status report
     * 
     * @param array $payload Request payload with parameters:
     *                       - from_date: string (DD-MM-YYYY) required
     *                       - to_date: string (DD-MM-YYYY) required
     *                       - client_code: string (optional)
     *                       - mandate_id: string (optional)
     *                       - status: string (optional)
     * @return array API response with status and data
     */
    public function getMandateStatusFromNse($payload): array
    {
        try {
            if (empty($payload['from_date']) || empty($payload['to_date'])) {
                return [
                    'http_code' => 400,
                    'json' => [
                        'response_status' => 'E',
                        'error_remark' => 'from_date and to_date are required fields',
                        'report_data' => []
                    ],
                    'error' => 'from_date and to_date are required fields'
                ];
            }

            $payload['client_code'] = $payload['client_code'] ?? '';
            $payload['mandate_id'] = $payload['mandate_id'] ?? '';
            $payload['status'] = $payload['status'] ?? '';
            
            $urlPostFix = "/nsemfdesk/api/v2/reports/MANDATE_STATUS";
            
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );

            error_log("Mandate Status API Response: " . print_r($apiResult, true));
            
            return [
                'http_code' => $apiResult['http_code'] ?? 0,
                'json' => $apiResult['json'] ?? null,
                'response' => $apiResult['response'] ?? null,
                'error' => $apiResult['error'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("getMandateStatusFromNse error: " . $e->getMessage());
            return [
                'http_code' => 0,
                'json' => null,
                'response' => null,
                'error' => 'Mandate status API call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format NSE date (DD/MM/YYYY) to MySQL date (Y-m-d)
     */
    private function formatNseDate($nseDate): ?string
    {
        if (empty($nseDate) || trim($nseDate) === '' || trim($nseDate) === ' ') {
            return null;
        }
        
        $date = DateTime::createFromFormat('d/m/Y', trim($nseDate));
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
        
        return null;
    }

    /**
     * Map NSE mandate status to local status
     */
    private function mapMandateStatus($nseStatus): string
    {
        $statusMap = [
            'APPROVED' => 'APPROVED',
            'ACTIVE' => 'APPROVED',
            'PENDING' => 'PENDING',
            'REJECTED' => 'REJECTED',
            'CANCELLED' => 'CANCELLED',
            'EXPIRED' => 'EXPIRED',
            'FAILED' => 'REJECTED'
        ];
        
        return $statusMap[$nseStatus] ?? 'PENDING';
    }

    /**
     * Sync Mandate Status from NSE to local database
     * Fetches mandate status for a date range and updates local records
     * 
     * @param string $fromDate Start date (DD-MM-YYYY)
     * @param string $toDate End date (DD-MM-YYYY)
     * @param string|null $clientCode Optional client code filter
     * @return array Sync result with counts
     */
    public function syncMandateStatus($fromDate, $toDate, $clientCode = null): array
    {
        $result = [
            'success' => false,
            'total_fetched' => 0,
            'updated' => 0,
            'new_records' => 0,
            'errors' => [],
            'data' => []
        ];
        
        try {
            $payload = [
                'from_date' => $fromDate,
                'to_date' => $toDate
            ];
            
            if ($clientCode) {
                $payload['client_code'] = $clientCode;
            }
            
            $apiResponse = $this->getMandateStatusFromNse($payload);
            
            if ($apiResponse['http_code'] != 200 || empty($apiResponse['json'])) {
                $result['errors'][] = "API call failed: " . ($apiResponse['error'] ?? 'Unknown error');
                return $result;
            }
            
            $jsonData = $apiResponse['json'];
            
            if (!empty($jsonData['error_remark']) && $jsonData['error_remark'] != '') {
                $result['errors'][] = "API returned error: " . $jsonData['error_remark'];
            }
            
            if (($jsonData['response_status'] ?? '') !== 'S' && empty($jsonData['report_data'])) {
                if (empty($result['errors'])) {
                    $result['errors'][] = "API returned non-success status: " . ($jsonData['error_remark'] ?? 'Unknown error');
                }
                return $result;
            }
            
            $reportData = $jsonData['report_data'] ?? [];
            $result['total_fetched'] = count($reportData);
            $result['data'] = $reportData;
            
            foreach ($reportData as $mandateData) {
                try {
                    $existingMandate = null;
                    
                    if (!empty($mandateData['memberMandateId'])) {
                        $existingMandate = $this->db->getMandateByMemberMandateId($mandateData['memberMandateId']);
                    }
                    
                    if (!$existingMandate && !empty($mandateData['mandateId'])) {
                        $existingMandate = $this->db->getMandateByMandateId($mandateData['mandateId']);
                    }
                    
                    $registrationDate = $this->formatNseDate($mandateData['registrationDate'] ?? '');
                    $startDate = $this->formatNseDate($mandateData['startDate'] ?? '');
                    $endDate = $this->formatNseDate($mandateData['endDate'] ?? '');
                    $approvedDate = $this->formatNseDate($mandateData['approvedDate'] ?? '');
                    $dateOfUpload = $this->formatNseDate($mandateData['dateOfUpload'] ?? '');
                    $dateOfReupload = $this->formatNseDate($mandateData['dateOfReupload'] ?? '');
                    
                    $localStatus = $this->mapMandateStatus($mandateData['status'] ?? '');
                    
                    $mandateRecord = [
                        'mandate_id' => $mandateData['mandateId'] ?? null,
                        'client_code' => $mandateData['clientCode'] ?? '',
                        'client_name' => $mandateData['clientName'] ?? '',
                        'member_code' => $mandateData['memberCode'] ?? '',
                        'bank_name' => $mandateData['bankName'] ?? '',
                        'bank_branch' => $mandateData['bankBranch'] ?? '',
                        'amount' => $mandateData['amount'] ?? 0,
                        'mandate_type' => $mandateData['mandateType'] ?? 'X',
                        'mandate_collection_type' => $mandateData['mandateCollectionType'] ?? 'ENACH',
                        'account_no' => $mandateData['bankAccountNumber'] ?? '',
                        'ac_type' => 'SB',
                        'ifsc_code' => '',
                        'micr_no' => '',
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'approved_date' => $approvedDate,
                        'date_of_upload' => $dateOfUpload,
                        'date_of_reupload' => $dateOfReupload,
                        'registration_date' => $registrationDate,
                        'registration_id' => $mandateData['umrnNo'] ?? '',
                        'umrn_no' => $mandateData['umrnNo'] ?? '',
                        'reg_status' => $localStatus,
                        'reg_remark' => trim($mandateData['remarks'] ?? ''),
                        'approved_remark' => trim($mandateData['remarks'] ?? ''),
                        'error_remark' => $jsonData['error_remark'] ?? '',
                        'member_mandate_no' => $mandateData['memberMandateId'] ?? null,
                    ];
                    
                    if ($existingMandate) {
                        $updated = $this->db->updateMandateFromNse($existingMandate['id'], $mandateRecord);
                        if ($updated) {
                            $result['updated']++;
                            error_log("Updated mandate ID: {$existingMandate['id']}");
                        }
                    } else {
                        $newId = $this->db->createMandateFromNseData($mandateRecord);
                        if ($newId) {
                            $result['new_records']++;
                            error_log("Created new mandate ID: {$newId}");
                        }
                    }
                    
                } catch (Exception $e) {
                    $result['errors'][] = "Error processing mandate {$mandateData['mandateId']}: " . $e->getMessage();
                    error_log("syncMandateStatus error: " . $e->getMessage());
                }
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['errors'][] = "Sync failed: " . $e->getMessage();
            error_log("syncMandateStatus error: " . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Get approved mandate for a user by client code
     * Checks local database first, then syncs from NSE if needed
     * 
     * @param int $userId User ID
     * @return array|null Approved mandate details or null
     */
    public function getApprovedMandateForUser($userId)
    {
        try {
            $user = $this->db->getUserById($userId);
            if (!$user || empty($user['CLIENT_CODE'])) {
                return null;
            }
            
            $approvedMandate = $this->db->getApprovedMandateByUserId($userId);
            
            if ($approvedMandate) {
                return $approvedMandate;
            }
            
            $syncResult = $this->syncMandateStatus(
                date('d/m/Y', strtotime('-30 days')),
                date('d/m/Y'),
                $user['CLIENT_CODE']
            );
            
            if ($syncResult['success'] && $syncResult['total_fetched'] > 0) {
                $approvedMandate = $this->db->getApprovedMandateByUserId($userId);
            }
            
            return $approvedMandate;
            
        } catch (Exception $e) {
            error_log("getApprovedMandateForUser error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update single mandate status by checking NSE
     * 
     * @param int $mandateId Local mandate ID
     * @return array Result with status
     */
    public function updateSingleMandateStatus($mandateId): array
    {
        try {
            $mandate = $this->db->getMandateById($mandateId);
            
            if (!$mandate) {
                return [
                    'success' => false,
                    'error' => 'Mandate not found'
                ];
            }
            
            $payload = [
                'from_date' => date('d/m/Y', strtotime('-30 days')),
                'to_date' => date('d/m/Y'),
                'client_code' => $mandate['client_code'] ?? ''
            ];
            
            if (!empty($mandate['mandate_id'])) {
                $payload['mandate_id'] = $mandate['mandate_id'];
            }
            
            $apiResponse = $this->getMandateStatusFromNse($payload);
            
            if ($apiResponse['http_code'] == 200 && isset($apiResponse['json']['report_data'])) {
                $reportData = $apiResponse['json']['report_data'];
                if (!empty($reportData)) {
                    $latestData = $reportData[0];
                    
                    $updateData = [
                        'reg_status' => $this->mapMandateStatus($latestData['status'] ?? ''),
                        'reg_remark' => trim($latestData['remarks'] ?? ''),
                        'umrn_no' => $latestData['umrnNo'] ?? '',
                        'approved_date' => $this->formatNseDate($latestData['approvedDate'] ?? ''),
                        'registration_id' => $latestData['umrnNo'] ?? '',
                    ];
                    
                    $this->db->updateMandateFromNse($mandateId, $updateData);
                    
                    return [
                        'success' => true,
                        'status' => $updateData['reg_status'],
                        'data' => $latestData
                    ];
                }
            }
            
            return [
                'success' => false,
                'error' => 'Could not fetch latest status'
            ];
            
        } catch (Exception $e) {
            error_log("updateSingleMandateStatus error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Status update failed: ' . $e->getMessage()
            ];
        }
    }
	
	/**
 * Get Client Detail Report from NSE API
 */
public function getClientDetailReport($payload): array
{
    try {
        $urlPostFix = "/nsemfdesk/api/v2/reports/client_detail_report";
        
        $apiResult = $this->nseApi->callNseApi(
            $this->creds,
            $urlPostFix,
            $payload
        );

        return [
            'http_code' => $apiResult['http_code'] ?? 0,
            'json' => $apiResult['json'] ?? null,
            'response' => $apiResult['response'] ?? null,
            'error' => $apiResult['error'] ?? null
        ];
        
    } catch (Exception $e) {
        error_log("getClientDetailReport error: " . $e->getMessage());
        return [
            'http_code' => 0,
            'json' => null,
            'response' => null,
            'error' => 'Client detail report API call failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Sync Client Status from NSE to local database
 */
public function syncClientStatus($clientCode): array
{
    $result = [
        'success' => false,
        'updated' => false,
        'data' => null,
        'errors' => []
    ];
    
    try {
        if (empty($clientCode)) {
            $result['errors'][] = "client_code is required";
            return $result;
        }
        
        $payload = [
            'client_code' => $clientCode
        ];
        
        $apiResponse = $this->getClientDetailReport($payload);
        
        if ($apiResponse['http_code'] != 200 || empty($apiResponse['json'])) {
            $result['errors'][] = "API call failed: " . ($apiResponse['error'] ?? 'Unknown error');
            return $result;
        }
        
        $jsonData = $apiResponse['json'];
        
        if (!empty($jsonData['error_remark']) && $jsonData['error_remark'] != '') {
            $result['errors'][] = "API returned error: " . $jsonData['error_remark'];
        }
        
        if (($jsonData['response_status'] ?? '') !== 'S' && empty($jsonData['report_data'])) {
            if (empty($result['errors'])) {
                $result['errors'][] = "API returned non-success status: " . ($jsonData['error_remark'] ?? 'Unknown error');
            }
            return $result;
        }
        
        $reportData = $jsonData['report_data'] ?? [];
        
        if (empty($reportData)) {
            $result['errors'][] = "No client data found for client_code: {$clientCode}";
            return $result;
        }
        
        $clientData = $reportData[0];
        $result['data'] = $clientData;
        
        // Get user by client code
        $user = $this->db->getUserByClientCode($clientCode);
        
        if (!$user) {
            $result['errors'][] = "User not found for client_code: {$clientCode}";
            return $result;
        }
        
        $userId = $user['id'];
        
        // Map NSE status to database values
        $fatcaDone = ($clientData['first_holder_fatca_exists'] ?? 'N') === 'Y' ? 'Y' : 'N';
        
        $deactivationMessage = trim($clientData['ucc_remediation'] ?? '');

        // Fallback: If NSE shows FATCA as missing ('N'), but local DB has filled FATCA details, submit to NSE
        if ($fatcaDone === 'N') {
            $localFatca = $this->db->getFatcaDetailsByUserId($userId);
            if (!empty($localFatca)) {
                $fatcaRes = $this->createFatcaOnNseCommon($userId);
                if (isset($fatcaRes['json']['reg_details'][0]['reg_status']) && 
                    $fatcaRes['json']['reg_details'][0]['reg_status'] === 'REG_SUCCESS') {
                    $fatcaDone = 'Y';
                    if ($deactivationMessage === 'Fatca Registration is pending') {
                        $deactivationMessage = 'Client authorization / AOF / eLog is pending';
                    }
                }
            }
        }
        
        $uccStatus = trim($clientData['ucc_status'] ?? '');
        $activationStatus = ($uccStatus === 'Active' || $uccStatus === 'ACTIVE') ? 'ACTIVE' : 'INACTIVE';
        
        error_log("Syncing client status for user {$userId}: fatca_done={$fatcaDone}, activation_status={$activationStatus}, deactivation_message={$deactivationMessage}");
        
        // Update the mtb_user_status table
        $this->db->updateClientStatus($userId, $fatcaDone, $activationStatus, $deactivationMessage);
        
        // Update user COUNTRY column in tb_users using country_name from mtb_country
        $countryRaw = $clientData['country'] ?? $clientData['res_country'] ?? $clientData['c_country'] ?? $clientData['country_code'] ?? '';
        if (!empty($countryRaw)) {
            $this->db->updateUserCountry($userId, $countryRaw);
        }
        
        $result['success'] = true;
        $result['updated'] = true;
        
    } catch (Exception $e) {
        $result['errors'][] = "Sync failed: " . $e->getMessage();
        error_log("syncClientStatus error: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * Sync all clients or specific client status
 */
public function syncAllClientsStatus($specificClientCode = null): array
{
    $result = [
        'success' => false,
        'total' => 0,
        'updated' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    try {
        $users = $this->db->getAllUsersWithClientCode();
        
        if (empty($users)) {
            $result['errors'][] = "No users with client codes found";
            return $result;
        }
        
        $result['total'] = count($users);
        
        foreach ($users as $user) {
            $clientCode = $user['CLIENT_CODE'];
            
            if ($specificClientCode !== null && $clientCode !== $specificClientCode) {
                continue;
            }
            
            $syncResult = $this->syncClientStatus($clientCode);
            
            if ($syncResult['success'] && $syncResult['updated']) {
                $result['updated']++;
            } else {
                $result['failed']++;
                $result['errors'][] = "Failed for {$clientCode}: " . implode(', ', $syncResult['errors']);
            }
            
            usleep(100000);
        }
        
        $result['success'] = $result['updated'] > 0;
        
    } catch (Exception $e) {
        $result['errors'][] = "Sync all failed: " . $e->getMessage();
        error_log("syncAllClientsStatus error: " . $e->getMessage());
    }
    
    return $result;
}
   /**
     * Get SWP Transactions from NSE API
     * Fetches SWP registration report
     * 
     * @param array $payload
     * @return array API response with status and data
     */
    public function getSwpTransactionsFromNse($payload): array
    {
        try {
            if (empty($payload['from_date']) || empty($payload['to_date'])) {
                return [
                    'http_code' => 400,
                    'json' => [
                        'response_status' => 'E',
                        'error_remark' => 'from_date and to_date are required fields',
                        'report_data' => []
                    ],
                    'error' => 'from_date and to_date are required fields'
                ];
            }

            if (!isset($payload['swp_reg_id'])) $payload['swp_reg_id'] = '';
            if (!isset($payload['client_code'])) $payload['client_code'] = '';
            if (!isset($payload['member_unique_ids'])) $payload['member_unique_ids'] = '';
            
            $urlPostFix = "/nsemfdesk/api/v2/reports/SWP_REG_REPORT";
            
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );

            return [
                'http_code' => $apiResult['http_code'] ?? 0,
                'json' => $apiResult['json'] ?? null,
                'response' => $apiResult['response'] ?? null,
                'error' => $apiResult['error'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("getSwpTransactionsFromNse error: " . $e->getMessage());
            return [
                'http_code' => 0,
                'json' => null,
                'response' => null,
                'error' => 'SWP transactions API call failed: ' . $e->getMessage()
            ];
        }
    }

     /**
     * Get Client Master Report from NSE API
     * This fetches complete client master data including all details
     * 
     * @param array $payload Request payload with client_code parameter
     * @return array API response with status and data
     */
    public function getClientMasterReport($payload): array
    {
        try {
            if (empty($payload['client_code'])) {
                return [
                    'http_code' => 400,
                    'json' => [
                        'response_status' => 'E',
                        'error_remark' => 'client_code is required',
                        'report_data' => []
                    ],
                    'error' => 'client_code is required'
                ];
            }

            $urlPostFix = "/nsemfdesk/api/v2/reports/client_master_report";
            
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );

            error_log("Client Master Report API Response: " . print_r($apiResult, true));

            return [
                'http_code' => $apiResult['http_code'] ?? 0,
                'json' => $apiResult['json'] ?? null,
                'response' => $apiResult['response'] ?? null,
                'error' => $apiResult['error'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("getClientMasterReport error: " . $e->getMessage());
            return [
                'http_code' => 0,
                'json' => null,
                'response' => null,
                'error' => 'Client master report API call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get FATCA report from NSE
     * 
     * @param array $payload Payload with optional 'pan_pkern_no', 'from_date', 'to_date'
     * @return array Response array containing http_code, json, response, error
     */
    public function getFatcaReportFromNse(array $payload): array
    {
        try {
            $urlPostFix = "/nsemfdesk/api/v2/reports/FATCA_REPORT";
            
            $apiResult = $this->nseApi->callNseApi(
                $this->creds,
                $urlPostFix,
                $payload
            );
            
            return [
                'http_code' => $apiResult['http_code'] ?? 0,
                'json' => $apiResult['json'] ?? null,
                'response' => $apiResult['response'] ?? null,
                'error' => $apiResult['error'] ?? null
            ];
        } catch (Exception $e) {
            error_log("getFatcaReportFromNse error: " . $e->getMessage());
            return [
                'http_code' => 0,
                'json' => null,
                'response' => null,
                'error' => 'FATCA report API call failed: ' . $e->getMessage()
            ];
        }
    }
}
?>