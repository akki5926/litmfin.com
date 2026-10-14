<?php
require_once('auth_guard.php');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/portfolio.php';
require_once __DIR__ . '/xirr_engine.php';

function calculateCasPortfolio($ucc, $advFilter = 'exclude', $advArn = '') {
    return calculateCasHoldings($ucc, $advFilter, $advArn);
}

function calculateCasHoldings($userUCC, $advFilter = 'exclude', $advArn = '') {
    $db = new MYSQLDB();
    $pdo = $db->getPDO();

    $netFundHolding = new MutualFundHolding();
    $totalHoldingObj = new TotalHoldings();
    $xirrEngine = new XirrEngine();

    $HoldingInfo = [];
    $SoldFundInfo = [];
    $rawportfolio = [];
    $totalFundsInfo = [];

    $totalCashFlows = [];
    $totalTimePeriod = [];
    $fundCashFlowRegistry = [];

    // Fetch valid historical transactions
    $sql = "SELECT * FROM tb_cas_transactions WHERE client_code = ? AND validation_status NOT LIKE '%Mismatch%'";
    if ($advFilter === 'exclude' && !empty($advArn)) {
        $sql .= " AND (advisor_arn IS NULL OR advisor_arn != ?)";
    }
    $sql .= " ORDER BY date ASC";

    $stmt = $db->getConnection()->prepare($sql);
    if ($advFilter === 'exclude' && !empty($advArn)) {
        $stmt->bind_param("ss", $userUCC, $advArn);
    } else {
        $stmt->bind_param("s", $userUCC);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    error_log("CAS_CALC: Fetched " . $res->num_rows . " valid transactions for UCC: " . $userUCC);

    $buyTypes = ['PURCHASE', 'SIP_PURCHASE', 'STP_PURCHASE', 'TRANSFER_IN', 'DIVIDEND_REINVESTMENT', 'DIVIDEND_TRANSFER_FROM'];
    $sellTypes = ['REDEMPTION', 'SIP_REDEMPTION', 'STP_REDEMPTION', 'SWP', 'TRANSFER_OUT', 'DIVIDEND_PAID', 'DIVIDEND_TRANSFER_TO'];

    while ($row = $res->fetch_assoc()) {
        $amcName = trim($row['amc'] ?? '');
        $amcCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($amcName, 0, 10)));
        
        $fundName = trim($row['scheme_name'] ?? '');
        $isinCode = trim($row['isin'] ?? '');
        $fundCode = !empty($isinCode) ? $isinCode : md5($fundName);
        
        $type = strtoupper($row['transaction_type'] ?? '');
        $amount = (float)$row['amount'];
        $units = (float)$row['units'];
        
        $dateStr = $row['date'] ?? '';
        $ts = !empty($dateStr) ? strtotime($dateStr) : false;
        if ($ts === false) {
            error_log("CAS_CALC: Skipped transaction due to missing/invalid date: " . json_encode($row));
            continue; // Skip transactions with missing or invalid dates
        }
        $date = $dateStr;

        $isBuy = in_array($type, $buyTypes);
        $isSell = in_array($type, $sellTypes);

        if ($isBuy) {
            $transactionSign = 'P';
        } elseif ($isSell) {
            $transactionSign = 'R';
        } else {
            //error_log("CAS_CALC: Skipped non-financial transaction type: " . $type);
            continue; // Skip non-financial
        }

        if (!isset($rawportfolio[$amcCode])) {
            $rawportfolio[$amcCode] = [];
        }
        if (!isset($totalFundsInfo[$amcCode])) {
            $totalFundsInfo[$amcCode] = [];
        }

        $rawportfolio[$amcCode][] = [
            'fundAmcCode' => $amcCode,
            'fundHouseName' => $amcName,
            'isinCode' => $isinCode,
            'fundName' => $fundName,
            'unitsTransacted' => $units,
            'transactionSign' => $transactionSign,
            'transactionAmount' => $amount,
            'transactionDate' => $date
        ];

        if (!in_array($fundCode, $totalFundsInfo[$amcCode])) {
            $totalFundsInfo[$amcCode][] = $fundCode;
        }
    }
    $stmt->close();

    // Now compute holding for each fund
    foreach ($totalFundsInfo as $amcCode => $fundsInfo) {
        if (!isset($HoldingInfo[$amcCode])) {
            $HoldingInfo[$amcCode] = [];
        }

        foreach ($fundsInfo as $fundNumber) {
            $fundName = '';
            $fundHouseName = '';
            $isinCode = '';
            $cashFlow = [];
            $timePeriod = [];
            $payoutRecvd = 0;
            
            foreach ($rawportfolio[$amcCode] as $fund) {
                $checkCode = !empty($fund['isinCode']) ? $fund['isinCode'] : md5($fund['fundName']);
                if ($checkCode === $fundNumber) {
                    $fundHouseName = $fund['fundHouseName'];
                    $fundName = $fund['fundName'];
                    $isinCode = $fund['isinCode'];
                    $unitsTransacted = $fund['unitsTransacted'];
                    $transactionSign = $fund['transactionSign'];
                    $transactionAmount = $fund['transactionAmount'];
                    $transactionDate = $fund['transactionDate'];

                    if ($transactionSign == 'P') {
                        $netFundHolding->addTransaction('buy', $unitsTransacted, $transactionAmount, $transactionDate);
                        $cashFlow[] = 0 - $transactionAmount;
                        $timePeriod[] = strtotime($transactionDate);
                    } elseif ($transactionSign == 'R') {
                        $netFundHolding->addTransaction('sell', $unitsTransacted, $transactionAmount, $transactionDate);
                        $cashFlow[] = $transactionAmount;
                        $timePeriod[] = strtotime($transactionDate);
                        if ($unitsTransacted <= 0.0001) {
                            $payoutRecvd += $transactionAmount;
                        }
                    }
                }
            }

            $netUnitsHolding = $netFundHolding->getNetHoldingUnits();
            $bookedProfit = $netFundHolding->getrealizedprofits();
            $costNAV = (abs($netUnitsHolding) > 0.0001) ? $netFundHolding->getCostPerUnitOfRemainingUnits() : 0;
            $costOfUnitsHolding = $netFundHolding->getCostOfRemainingUnits();
            
            $assetClass = !empty($isinCode) ? $db->getFundAssetClassByISIN($isinCode) : 'EQUITY';
            if (empty($assetClass)) {
                $assetClass = 'EQUITY';
            }

            $currentNAV = 0;
            $currentNAVDate = date('Y-m-d');

            if (!empty($isinCode)) {
                $navData = $db->getFundNAVByISIN($isinCode);
                if ($navData) {
                    $currentNAV = (float)$navData['nav'];
                    $currentNAVDate = !empty($navData['nav_date']) ? $navData['nav_date'] : date('Y-m-d');
                }
            }

            $unRealizedProfit = 0;

            if ($netUnitsHolding >= 0.01) {
                if ($currentNAV == 0) {
                    error_log("CAS_CALC: Missing NAV for HELD fund: " . $fundName . " (ISIN: " . $isinCode . ")");
                }

                $currentFundValue = $netUnitsHolding * $currentNAV;
                $unRealizedProfit = $currentFundValue - $costOfUnitsHolding;

                $cashFlow[] = $currentFundValue;
                
                $_navTs = strtotime($currentNAVDate) ?: time();
                $_lastBuyTs = !empty($timePeriod) ? max($timePeriod) : 0;
                if ($_navTs < $_lastBuyTs) { $_navTs = $_lastBuyTs; }
                $timePeriod[] = $_navTs;

                $fundCashFlowRegistry[] = [
                    'investment_type' => 'MF',
                    'fund_name'       => $fundName,
                    'amc_code'        => $amcCode,
                    'fund_code'       => $fundNumber,
                    'asset_class'     => $assetClass,
                    'cashflows'       => $cashFlow,
                    'timeperiods'     => $timePeriod,
                    'status'          => 'HELD',
                ];

                $totalCashFlows = array_merge($totalCashFlows, $cashFlow);
                $totalTimePeriod = array_merge($totalTimePeriod, $timePeriod);

                $totalHoldingObj->addInvestments($assetClass, "MF", $costOfUnitsHolding, 0, $currentFundValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);

                $xirrReturn = 0;
                if ($currentNAV > 0 && count($cashFlow) >= 2) {
                    $xirrReturn = $xirrEngine->XIRRAdpro($cashFlow, $timePeriod, 0.1) * 100;
                    if (abs($xirrReturn) > 10000) $xirrReturn = 0;
                }

                $HoldingInfo[$amcCode][] = [
                    $fundNumber => [
                        "Fund_House_Name" => $fundHouseName,
                        "Fund_Name" => $fundName,
                        "Asset_Class" => $assetClass,
                        "Unit_Holdings" => number_format($netUnitsHolding, 4, '.', ','),
                        "Cost_NAV" => number_format($costNAV, 2, '.', ','),
                        "Cost_Value" => number_format($costOfUnitsHolding, 2, '.', ','),
                        "Current_NAV" => number_format($currentNAV, 2, '.', ','),
                        "NAV_Date" => $currentNAVDate,
                        "Curr_Value" => number_format($currentFundValue, 2, '.', ','),
                        "PayoutRecvd" => number_format($payoutRecvd, 2, '.', ','),
                        "RealizedProfit" => number_format($bookedProfit, 2, '.', ','),
                        "UnRealizedProfit" => number_format($unRealizedProfit, 2, '.', ','),
                        "XIRR" => number_format($xirrReturn, 2, '.', ',')
                    ]
                ];
                
                //error_log("CAS_CALC: Added HELD fund {$fundName}, Units: {$netUnitsHolding}, Value: {$currentFundValue}");
            } elseif ($netUnitsHolding < 0.01) {
                // SOLD
                $xirrReturn = 0;
                if (count($cashFlow) >= 2) {
                    $xirrReturn = $xirrEngine->XIRRAdpro($cashFlow, $timePeriod, 0.1) * 100;
                    if (abs($xirrReturn) > 10000) $xirrReturn = 0;
                }

                $SoldFundInfo[] = [
                    "Fund_House_Name" => $fundHouseName,
                    "Fund_Name" => $fundName,
                    "Asset_Class" => $assetClass,
                    "Unit_Holdings" => number_format(0, 4, '.', ','),
                    "PayoutRecvd" => number_format($payoutRecvd, 2, '.', ','),
                    "RealizedProfit" => number_format($bookedProfit, 2, '.', ','),
                    "XIRR" => number_format($xirrReturn, 2, '.', ','),
                    "cashflows" => $cashFlow,
                    "timeperiods" => $timePeriod
                ];

                $fundCashFlowRegistry[] = [
                    'investment_type' => 'MF',
                    'fund_name'       => $fundName,
                    'amc_code'        => $amcCode,
                    'fund_code'       => $fundNumber,
                    'asset_class'     => $assetClass,
                    'cashflows'       => $cashFlow,
                    'timeperiods'     => $timePeriod,
                    'status'          => 'SOLD',
                ];
                
                //error_log("CAS_CALC: Added SOLD fund {$fundName}");
            }

            $netFundHolding->reInitializeHolding();
        }
    }

    $totalHoldingsMF = $totalHoldingObj->getMFHoldingDataTotal();
    $allFundHoldingInfo = $totalHoldingObj->getHoldingDataTotal();

    // MCap logic
    $portfolioMcap = calculatePortfolioMcapAllocation($HoldingInfo);
    $mcapHistory = calculateMcapHistory($HoldingInfo, $pdo);
    
    $result = calculateMFTopHoldings($HoldingInfo) ?? [];
    $companyAgg = $result['top_companies'] ?? [];
    $sectorAgg = $result['top_sectors'] ?? [];
    $fundMetrics = $result['fund_metrics'] ?? [];
    $totalMFValue = $result['total_mf_value'] ?? 0;

    $totalCashFlows = $totalHoldingObj->getTotalCashFlows();
    $totalTimePeriods = $totalHoldingObj->getTotalTimePeriods();

    return [
        $HoldingInfo, $SoldFundInfo, 
        $totalHoldingsMF, $allFundHoldingInfo, 
        [], [], [], // Bonds
        [], [], [], // PMS
        [], [], [], // AIF
        [], // Goals
        $totalCashFlows, 
        $totalTimePeriods,
        $fundCashFlowRegistry,
        $companyAgg,
        $sectorAgg,
        $fundMetrics,
        $totalMFValue,
        $portfolioMcap,
        [], // SIP Info
        [], // SIP Txns
        $mcapHistory
    ];
}

/**
 * Collects SIP transactions from the raw portfolio data based on active SIP details.
 *
 * @param array $runningSIP   Array containing the user's active SIPs.
 * @param array $rawportfolio Array of all raw transactions grouped by AMC.
 * @return array The matched SIP transactions.
 */
function collectSipTransactions_CAS($runningSIP, $rawportfolio) {
    $sipTransactions = [];
    $matchedTxnKeys = []; // To prevent duplicating the same transaction across overlapping SIPs

    if (empty($runningSIP) || empty($rawportfolio)) {
        return $sipTransactions;
    }

    foreach ($runningSIP as $sip) {
        // Identify ISIN
        $sipIsin = $sip['ISIN_Code'] ?? ($sip['isin'] ?? '');
        if (empty($sipIsin)) {
            continue;
        }

        // Identify SIP Amount
        $sipAmount = (float)str_replace(',', '', $sip['Amount'] ?? ($sip['installments_amount'] ?? 0));
        if ($sipAmount <= 0) {
            continue;
        }

        // Identify SIP Day (day of the month)
        $sipDay = null;
        if (!empty($sip['instdate'])) {
            $sipDay = is_numeric($sip['instdate']) ? (int)$sip['instdate'] : (int)date('d', strtotime($sip['instdate']));
        } elseif (!empty($sip['From_Date'])) {
            $sipDay = (int)date('d', strtotime($sip['From_Date']));
        } elseif (!empty($sip['start_date'])) {
            $sipDay = (int)date('d', strtotime($sip['start_date']));
        }

        // Search transactions in $rawportfolio
        foreach ($rawportfolio as $amcCode => $transactions) {
            foreach ($transactions as $txn) {
                $txnIsin = $txn['isinCode'] ?? '';
                
                // Rule 1: Match ISIN
                if (empty($txnIsin) || $txnIsin !== $sipIsin) {
                    continue; 
                }

                // Rule 2: Amount within +/- 10 Rs
                $txnAmount = abs((float)($txn['transactionAmount'] ?? 0));
                if (abs($txnAmount - $sipAmount) <= 10.0) {
                    
                    $isDateMatch = false;
                    if ($sipDay !== null && !empty($txn['transactionDate'])) {
                        $txnTimestamp = strtotime($txn['transactionDate']);
                        $txnMonth = (int)date('m', $txnTimestamp);
                        $txnYear = (int)date('Y', $txnTimestamp);
                        
                        // Verify proximity safely against month crossovers (e.g., Jan 31 -> Feb 2)
                        $targetDateCurrent = mktime(0, 0, 0, $txnMonth, $sipDay, $txnYear);
                        $targetDatePrev = mktime(0, 0, 0, $txnMonth - 1, $sipDay, $txnYear);
                        $targetDateNext = mktime(0, 0, 0, $txnMonth + 1, $sipDay, $txnYear);
                        
                        $diffCurrent = abs($txnTimestamp - $targetDateCurrent) / 86400;
                        $diffPrev = abs($txnTimestamp - $targetDatePrev) / 86400;
                        $diffNext = abs($txnTimestamp - $targetDateNext) / 86400;
                        
                        // Rule 3: Date within +/- 2 days
                        if (min($diffCurrent, $diffPrev, $diffNext) <= 2) {
                            $isDateMatch = true;
                        }
                    } else {
                        // If SIP day is not specified in the database, assume matched based purely on Amount/ISIN
                        $isDateMatch = true;
                    }

                    if ($isDateMatch) {
                        // Create a unique key for the transaction to prevent duplicates
                        $txnKey = $txnIsin . '_' . $txn['transactionDate'] . '_' . $txn['transactionAmount'] . '_' . $txn['unitsTransacted'];
                        
                        if (!isset($matchedTxnKeys[$txnKey])) {
                            $matchedTxnKeys[$txnKey] = true;
                            $sipTransactions[] = [
                                'fundHouseName'     => $txn['fundHouseName'] ?? '',
                                'isinCode'          => $txnIsin,
                                'fundName'          => $txn['fundName'] ?? '',
                                'unitsTransacted'   => $txn['unitsTransacted'] ?? 0,
                                'transactionSign'   => $txn['transactionSign'] ?? '',
                                'transactionAmount' => $txn['transactionAmount'] ?? 0,
                                'transactionDate'   => $txn['transactionDate'] ?? ''
                            ];
                        }
                    }
                }
            }
        }
    }

    return $sipTransactions;
}