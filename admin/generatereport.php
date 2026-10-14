<?php
require_once('auth_guard.php'); 

/**
 * generatereport.php
 *
 * Can be called:
 *   A) From browser: generatereport.php?i=IIN[&family=1][&debug=1][&xirr=1][&save=1]
 *   B) From other PHP files: require_once 'generatereport.php'; then call generateReport()
 *
 * generateReport(string $userIIN, int $family, array $options): void
 *
 * $options keys:
 *   'debug'     bool  true = HTML preview in browser, false = PDF (default false)
 *   'xirr'      bool  true = also save XIRR debug Excel to reports/ (default false)
 *   'save'      bool  true = silently save PDF+Excel to reports/ folder, no browser output
 *                     false = stream PDF to browser (default false)
 *   'reports_dir' string  folder to save files (default __DIR__ . '/reports/')
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/portfolio.php';
require_once __DIR__ . '/benchmark_xirr.php';
require_once __DIR__ . '/xirr_helpers.php';
require_once __DIR__ . '/xirr_engine.php';

use Mpdf\Mpdf;

// ── Browser entry point ───────────────────────────────────────────────────────
// Only auto-trigger if called directly, not from send_report_email.php (action=send)
if (isset($_REQUEST['i']) && ($_REQUEST['action'] ?? 'view') !== 'send') {
    $action = $_REQUEST['action'] ?? 'view';
	
    // Subadmin check
    $isSubAdmin = isset($_SESSION["superuser"]) && $_SESSION["superuser"] == 0;
    if ($isSubAdmin) {
        $subAdminId = $_SESSION["userid"] ?? 0;
        $dbCheck = new MYSQLDB();
        if (!$dbCheck->isClientAssignedToSubadmin(trim($_REQUEST['i']), $subAdminId)) {
            die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-family: sans-serif; border: 1px solid #ef9a9a; border-radius: 5px;'><strong>Access Denied:</strong> This client portfolio is not assigned to your subadmin account.</div>");
        }
    }
	
    generateReport(
        trim($_REQUEST['i']),
        (int)($_REQUEST['family'] ?? 0),
        [
            'debug' => !empty($_REQUEST['debug']),
            'xirr'  => !empty($_REQUEST['xirr']) || $action === 'download_excel',
            'save'  => !empty($_REQUEST['save']) || $action === 'save',
            'action'=> $action
        ]
    );
}

// ═════════════════════════════════════════════════════════════════════════════
function generateReport(string $userIIN, int $family = 0, array $options = []): array
{
    $debug      = $options['debug']       ?? false;
    $genXirr    = $options['xirr']        ?? false;
    $saveMode   = $options['save']        ?? false;
    $action     = $options['action']      ?? 'view';
    $reportsDir = $options['reports_dir'] ?? (__DIR__ . '/reports/');

    $db  = new MYSQLDB();
    $pdo = $db->getPDO();

    // ── Client profile ────────────────────────────────────────────────────────
    $profiledata = $db->getProfileData($userIIN);
    $headName = 'Name Not Found';
    $userpan  = 'PAN not found';
    $address  = 'Not available';
    foreach ($profiledata as $row) {
        $headName = $row['PRIMARY_HOLDER_NAME'] ?? 'Name Not Found';
        $userpan  = $row['PRIMARY_HOLDER_PAN']    ?? 'PAN not found';
        $address  = $row['ADDRESS_1']   ?? 'Not available';
        break;
    }

    // ── Holdings calculation ──────────────────────────────────────────────────
    $membersToProcess = [['iin' => $userIIN, 'name' => $headName]];
    $familyDistribution = [];

    if ($family === 1) {
        $famDetails = $db->getFamilyByUCC($userIIN);
        if ($famDetails) {
            if (!empty($famDetails['member1_UCC'])) {
                $mName = $db->getUCCName($famDetails['member1_UCC']);
                $membersToProcess[] = ['iin' => $famDetails['member1_UCC'], 'name' => $mName ?: 'Family Member 1'];
            }
            if (!empty($famDetails['member2_UCC'])) {
                $mName = $db->getUCCName($famDetails['member2_UCC']);
                $membersToProcess[] = ['iin' => $famDetails['member2_UCC'], 'name' => $mName ?: 'Family Member 2'];
            }
            if (!empty($famDetails['member3_UCC'])) {
                $mName = $db->getUCCName($famDetails['member3_UCC']);
                $membersToProcess[] = ['iin' => $famDetails['member3_UCC'], 'name' => $mName ?: 'Family Member 3'];
            }
        }
    }

    $html = '';

    // ── FAMILY COMBINED COVER PAGE ───────────────────────────────────────────
    if ($family === 1 && count($membersToProcess) > 1) {
        $combinedTotalAssetInfo = [[
            'Total_Equity_Cost' => 0, 'Total_Equity_Curr_Value' => 0, 'Total_Equity_Unrealized' => 0, 'Total_Equity_Booked' => 0,
            'Total_Debt_Cost' => 0, 'Total_Debt_Curr_Value' => 0, 'Total_Debt_Unrealized' => 0, 'Total_Debt_Booked' => 0,
            'Total_Hybrid_Cost' => 0, 'Total_Hybrid_Curr_Value' => 0, 'Total_Hybrid_Unrealized' => 0, 'Total_Hybrid_Booked' => 0,
            'Total_Gold_Cost' => 0, 'Total_Gold_Curr_Value' => 0, 'Total_Gold_Unrealized' => 0, 'Total_Gold_Booked' => 0,
            'Total_Cost_Value' => 0, 'Total_Current_Value' => 0, 'Total_Unrealized' => 0, 'Total_Booked' => 0,
        ]];
        $familyDistribution = [];
        $allFundCashFlowRegistry = [];

        foreach ($membersToProcess as $member) {
            list($m_portfolioInfo, $m_ZeroHoldingFundInfo, $m_totalHoldingsMF, $m_totalAssetInfo,
                 $m_HoldingInfoBonds, $m_TotalHoldingInfoBonds, $m_ZeroHoldingBondInfo,
                 $m_HoldingInfoPms, $m_TotalHoldingInfoPms, $m_SoldPmsInfo,
                 $m_HoldingInfoAif, $m_TotalHoldingInfoAif, $m_SoldAifInfo,
                 $m_goalsFundInfo, $m_totalCashFlows, $m_totalTimePeriods, $m_fundCashFlowRegistry,
                 $m_companyAgg, $m_sectorAgg, $m_fundMetrics, $m_totalMFValue, $m_portfolioMcap,    
                 $m_runningSIPInfo, $m_sipTransactions,
                 $m_HoldingInfoSif, $m_TotalHoldingInfoSif, $m_SoldSifInfo
            ) = calculateHoldings($member['iin']);
            
            $memberTotalVal = (float)str_replace(',', '', $m_totalAssetInfo[0]['Total_Current_Value'] ?? 0);
            if ($memberTotalVal > 0) {
                $familyDistribution[] = [
                    'name' => $member['name'],
                    'value' => $memberTotalVal
                ];
            }
            
            $t = $m_totalAssetInfo[0] ?? [];
            foreach (['Total_Equity_Cost', 'Total_Equity_Curr_Value', 'Total_Equity_Unrealized', 'Total_Equity_Booked',
                      'Total_Debt_Cost', 'Total_Debt_Curr_Value', 'Total_Debt_Unrealized', 'Total_Debt_Booked',
                      'Total_Hybrid_Cost', 'Total_Hybrid_Curr_Value', 'Total_Hybrid_Unrealized', 'Total_Hybrid_Booked',
                      'Total_Gold_Cost', 'Total_Gold_Curr_Value', 'Total_Gold_Unrealized', 'Total_Gold_Booked',
                      'Total_Cost_Value', 'Total_Current_Value', 'Total_Unrealized', 'Total_Booked'] as $key) {
                $combinedTotalAssetInfo[0][$key] += (float)str_replace(',', '', $t[$key] ?? 0);
            }

            $allFundCashFlowRegistry = array_merge($allFundCashFlowRegistry, $m_fundCashFlowRegistry ?? []);
        }

        $cTot = $combinedTotalAssetInfo[0]['Total_Current_Value'];
        if ($cTot > 0) {
            $combinedTotalAssetInfo[0]['Equity_Asset_Share'] = ($combinedTotalAssetInfo[0]['Total_Equity_Curr_Value'] / $cTot) * 100;
            $combinedTotalAssetInfo[0]['Debt_Asset_Share'] = ($combinedTotalAssetInfo[0]['Total_Debt_Curr_Value'] / $cTot) * 100;
            $combinedTotalAssetInfo[0]['Hybrid_Asset_Share'] = ($combinedTotalAssetInfo[0]['Total_Hybrid_Curr_Value'] / $cTot) * 100;
            $combinedTotalAssetInfo[0]['Gold_Asset_Share'] = ($combinedTotalAssetInfo[0]['Total_Gold_Curr_Value'] / $cTot) * 100;
        } else {
            $combinedTotalAssetInfo[0]['Equity_Asset_Share'] = 0;
            $combinedTotalAssetInfo[0]['Debt_Asset_Share'] = 0;
            $combinedTotalAssetInfo[0]['Hybrid_Asset_Share'] = 0;
            $combinedTotalAssetInfo[0]['Gold_Asset_Share'] = 0;
        }

        $bm = new BenchmarkXirr($pdo);
        
        $registryEquity = [];
        $registryDebt   = [];
        $registryHybrid = [];
        $registryGold   = [];
        
        foreach ($allFundCashFlowRegistry as $entry) {
            $it = strtoupper($entry['investment_type'] ?? 'MF');
            $ac = strtoupper($entry['asset_class'] ?? '');
            if ($it === 'MF') {
                if ($ac === 'EQUITY') $registryEquity[] = $entry;
                elseif ($ac === 'DEBT') $registryDebt[] = $entry;
                elseif ($ac === 'HYBRID') $registryHybrid[] = $entry;
                elseif ($ac === 'GOLD') $registryGold[] = $entry;
            }
        }
        
        if (!empty($registryEquity)) { $res = $bm->computeBenchmarkFromRegistry($registryEquity); $combinedTotalAssetInfo[0]['Total_Equity_Return'] = $res['portfolio_xirr'] ?? 0; }
        if (!empty($registryDebt)) { $res = $bm->computeBenchmarkFromRegistry($registryDebt); $combinedTotalAssetInfo[0]['Total_Debt_Return'] = $res['portfolio_xirr'] ?? 0; }
        if (!empty($registryHybrid)) { $res = $bm->computeBenchmarkFromRegistry($registryHybrid); $combinedTotalAssetInfo[0]['Total_Hybrid_Return'] = $res['portfolio_xirr'] ?? 0; }
        if (!empty($registryGold)) { $res = $bm->computeBenchmarkFromRegistry($registryGold); $combinedTotalAssetInfo[0]['Total_Gold_Return'] = $res['portfolio_xirr'] ?? 0; }
        
        $resTot = $bm->computeBenchmarkFromRegistry($allFundCashFlowRegistry);
        $combinedTotalAssetInfo[0]['Total_Holding_Return'] = $resTot['portfolio_xirr'] ?? 0;

        $sinceInception = $bm->computeBenchmarkFromRegistry($allFundCashFlowRegistry, 'nifty500', 'ALL');
        $sinceInceptionHeld = $bm->computeBenchmarkFromRegistry($allFundCashFlowRegistry, 'nifty500', 'HELD');
        
        // Pass variables to cover page
        $totalAssetInfo = $combinedTotalAssetInfo;
        $isFamilyCover = true;

        ob_start();
        include __DIR__ . '/report-templates/section_cover.php';
        $html .= ob_get_clean();
        
        $html .= '<pagebreak/>';
    }

    // ── INDIVIDUAL REPORTS ───────────────────────────────────────────────────
    foreach ($membersToProcess as $memberIndex => $member) {
        if ($memberIndex > 0 && empty($html)) {
            // Should not happen if family=1 is handled above, but just in case
            $html .= '<pagebreak/>';
        } elseif ($memberIndex > 0) {
            $html .= '<pagebreak/>';
        }
        
        $isFamilyCover = false;
        $familyDistribution = []; // Hide family distribution on individual covers

        // Get member details
        $profiledata_m = $db->getProfileData($member['iin']);
        $headName = $member['name'];
        $userpan  = 'PAN not found';
        $address  = 'Not available';
        foreach ($profiledata_m as $row) {
            $headName = $row['PRIMARY_HOLDER_NAME'] ?? $headName;
            $userpan  = $row['PRIMARY_HOLDER_PAN']    ?? 'PAN not found';
            $address  = $row['ADDRESS_1']   ?? 'Not available';
            break;
        }

        list($portfolioInfo, $ZeroHoldingFundInfo, $totalHoldingsMF, $totalAssetInfo,
             $HoldingInfoBonds, $TotalHoldingInfoBonds, $ZeroHoldingBondInfo,
             $HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo,
             $HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo,
             $goalsFundInfo, $totalCashFlows, $totalTimePeriods, $fundCashFlowRegistry,
             $companyAgg, $sectorAgg, $fundMetrics, $totalMFValue, $portfolioMcap,    
             $runningSIPInfo, $sipTransactions,
             $HoldingInfoSif,
             $TotalHoldingInfoSif,
             $SoldSifInfo
        ) = calculateHoldings($member['iin']);
		
		// Create a holdings lookup map from portfolioInfo using ISIN as the key
        $holdingsLookupByIsin = [];
		
		//print_r($portfolioInfo);
		
        if (!empty($portfolioInfo)) {
            foreach ($portfolioInfo as $amcCode => $funds) {
                foreach ($funds as $fundWrapper) {
                    foreach ($fundWrapper as $isin => $fundData) {
                        if (is_string($isin) && !isset($holdingsLookupByIsin[$isin])) {
                            $holdingsLookupByIsin[$isin] = [
                                'units' => (float)str_replace(',', '', $fundData['Unit_Holdings'] ?? 0),
                                'value' => (float)str_replace(',', '', $fundData['Curr_Value'] ?? 0),
                                'cost'  => (float)str_replace(',', '', $fundData['Cost_Value'] ?? 0),
                                'xirr'  => isset($fundData['XIRR']) ? (float)str_replace(',', '', $fundData['XIRR']) : null,
                                'name'  => trim($fundData['Fund_Name'] ?? '')
                            ];
                        }
                    }
                }
            }
        }
        
		//print_r($holdingsLookupByIsin);
		
        $totalCurrentValue = (float)str_replace(',', '',
            $totalAssetInfo[0]['Total_Current_Value'] ?? 0
        );

        $bm = new BenchmarkXirr($pdo);
        
        // Calculate ALL assets (Includes HELD and SOLD)
        $sinceInception = $bm->computeBenchmarkFromRegistry($fundCashFlowRegistry, 'nifty500', 'ALL');
        $holdingDaysAll = $sinceInception['holding_days'] ?? 0;
        $rolling3yr     = [];
        $rolling5yr     = [];
        if ($holdingDaysAll >= 1095) {
            $rolling3yr = $bm->computeRollingXirrFromRegistry($fundCashFlowRegistry, $totalCurrentValue, 3, 'nifty500', 'ALL');
        }
        if ($holdingDaysAll >= 1825) {
            $rolling5yr = $bm->computeRollingXirrFromRegistry($fundCashFlowRegistry, $totalCurrentValue, 5, 'nifty500', 'ALL');
        }

        // Calculate ONLY HELD assets
        $sinceInceptionHeld = $bm->computeBenchmarkFromRegistry($fundCashFlowRegistry, 'nifty500', 'HELD');
        $holdingDaysHeld    = $sinceInceptionHeld['holding_days'] ?? 0;
        $rolling3yrHeld     = [];
        $rolling5yrHeld     = [];
        if ($holdingDaysHeld >= 1095) {
            $rolling3yrHeld = $bm->computeRollingXirrFromRegistry($fundCashFlowRegistry, $totalCurrentValue, 3, 'nifty500', 'HELD');
        }
        if ($holdingDaysHeld >= 1825) {
            $rolling5yrHeld = $bm->computeRollingXirrFromRegistry($fundCashFlowRegistry, $totalCurrentValue, 5, 'nifty500', 'HELD');
        }

        // ── Active SIPs XIRR ──────────────────────────────────────────────────────
        $sipRegistry = [];
        $swpRegistry = [];
        $activeSipIsins = [];
        $activeSwpIsins = [];
        $activeSipPlanKeys = [];
        $planTypeOf = static function (array $plan): string {
            return strtoupper(trim($plan['registration_type'] ?? $plan['Sip_Type'] ?? 'SIP'));
        };

        foreach ($runningSIPInfo ?? [] as $plan) {
            $isin = $plan['ISIN_Code'] ?? '';
            if (empty($isin)) continue;
            $type = $planTypeOf($plan);
            if ($type === 'SWP') {
                $activeSwpIsins[] = $isin;
            } elseif ($type === 'SIP') {
                $activeSipIsins[] = $isin;
                $amt = (float)str_replace(',', '', $plan['Amount'] ?? 0);
                $key = $isin . '|' . round($amt, 2);
                $activeSipPlanKeys[$key] = true;
            }
        }
        $activeSipIsins = array_values(array_unique($activeSipIsins));
        $activeSwpIsins = array_values(array_unique($activeSwpIsins));
		
        if (!empty($sipTransactions)) {
            foreach ($sipTransactions as $txn) {
                $isin = $txn['isinCode'];
                $planType = strtoupper(trim($txn['planType'] ?? $txn['registration_type'] ?? 'SIP'));
                if ($planType !== 'SIP') continue;

                if (!in_array($isin, $activeSipIsins)) continue;

                $txnAmt = abs((float)($txn['transactionAmount'] ?? 0));
                $matchedKey = null;
                foreach (array_keys($activeSipPlanKeys) as $k) {
                    list($kIsin, $kAmt) = explode('|', $k);
                    if ($kIsin === $isin && abs($txnAmt - (float)$kAmt) <= 10.0) {
                        $matchedKey = $k;
                        break;
                    }
                }
                if (!$matchedKey) {
                    $matchedKey = $isin;
                }
                
                $txnDateTs = strtotime($txn['transactionDate']);
                if ($txnDateTs === false || $txnDateTs <= 0) {
                    continue; // Skip invalid dates like 0000-00-00
                }

                if ($txn['transactionSign'] === 'P') {
                    if (!isset($sipRegistry[$matchedKey])) {
                        $sipRegistry[$matchedKey] = [
                            'investment_type' => 'MF',
                            'fund_name'       => $txn['fundName'],
                            'amc_code'        => $txn['fundHouseName'],
                            'fund_code'       => $isin,
                            'asset_class'     => 'EQUITY', 
                            'cashflows'       => [],
                            'timeperiods'     => [],
                            'units'           => 0.0,
                            'status'          => 'HELD'
                        ];
                    }
                    $sipRegistry[$matchedKey]['cashflows'][] = -abs($txn['transactionAmount']);
                    $sipRegistry[$matchedKey]['timeperiods'][] = $txnDateTs;
                    $sipRegistry[$matchedKey]['units'] += abs($txn['unitsTransacted']);
                } elseif ($txn['transactionSign'] === 'R' && isset($sipRegistry[$matchedKey])) {
                    $sipRegistry[$matchedKey]['cashflows'][] = abs($txn['transactionAmount']);
                    $sipRegistry[$matchedKey]['timeperiods'][] = $txnDateTs;
                    $sipRegistry[$matchedKey]['units'] -= abs($txn['unitsTransacted']);
                }
            }

            // Append terminal values
            foreach ($sipRegistry as $key => &$reg) {
                $isin = $reg['fund_code'];
                $currentNav = 0;
                $navDateTs = time();
                
                $navData = $db->getFundNAVByISIN($isin);
                if ($navData) {
                    $currentNav = (float)$navData['nav'];
                    if (!empty($navData['nav_date']) && strpos($navData['nav_date'], '0000-00-00') === false) {
                        $parsedDate = strtotime($navData['nav_date']);
                        if ($parsedDate !== false && $parsedDate > 0) {
                            $navDateTs = $parsedDate;
                        }
                    }
                }
                
                $assetClass = $db->getFundAssetClassByISIN($isin);
                if ($assetClass) {
                    $reg['asset_class'] = strtoupper($assetClass);
                }
                
                $reg['units'] = max(0, $reg['units']); // prevent negative
                
                // Guard against stale NAV dates being EARLIER than the last purchase
                $_lastTxnTs = !empty($reg['timeperiods']) ? max($reg['timeperiods']) : 0;
                if ($navDateTs < $_lastTxnTs) {
                    $navDateTs = $_lastTxnTs;
                }
                
                $terminalValue = $reg['units'] * $currentNav;
                $reg['cashflows'][] = $terminalValue;
                $reg['timeperiods'][] = $navDateTs;
            }
            unset($reg);
        }
        
        $sipXirrResult = [];
        $sipChartPng = null;
        $sipValChartPng = null;
        if (!empty($sipRegistry)) {
            $sipXirrResult = $bm->computeBenchmarkFromRegistry(array_values($sipRegistry), 'nifty500', 'ALL');
            if (!empty($sipXirrResult['cashflow_count'])) {
                $sipChartPng = $bm->buildSinglePeriodChart($sipXirrResult, 'Active SIPs', $headName . '_sip', 440, 160);
                
                $pVal = $sipXirrResult['portfolio_value'] ?? 0;
                $bVal = $sipXirrResult['benchmark_value'] ?? 0;
                $sipValChartPng = $bm->buildValueComparisonChart($pVal, $bVal, 'Active SIPs Val', $headName . '_sipval', 320, 180);
            }
        }

        if (!empty($activeSwpIsins) && !empty($fundCashFlowRegistry)) {
            foreach ($fundCashFlowRegistry as $entry) {
                $entryType = strtoupper($entry['investment_type'] ?? 'MF');
                $entryIsin = $entry['fund_code'] ?? '';
                if ($entryType === 'MF' && in_array($entryIsin, $activeSwpIsins, true)) {
                    $swpRegistry[$entryIsin] = $entry;
                }
            }
        }

        $swpXirrResult = [];
        $swpXirrChartPng = null;
        $swpBarChartPng = null;
        if (!empty($swpRegistry)) {
            $swpXirrResult = $bm->computeBenchmarkFromRegistry(array_values($swpRegistry), 'nifty500', 'ALL');
            if (!empty($swpXirrResult['cashflow_count'])) {
                $swpXirrChartPng = $bm->buildSinglePeriodChart($swpXirrResult, 'Active SWPs', $headName . '_swp', 440, 160);
            }

            // Calculate theoretical withdrawals based on Start and End dates
            $totalSwpWithdrawals = 0.0;
            $totalSwpCost = 0.0;
            $seenSwpFunds = [];
            $now_ts = time();
            foreach ($runningSIPInfo ?? [] as $swp) {
                $planType = strtoupper(trim($swp['Sip_Type'] ?? $swp['registration_type'] ?? 'SIP'));
                if ($planType !== 'SWP') continue;

                $isin = $swp['ISIN_Code'] ?? '';
                $fundKey = (!empty($isin)) ? $isin : trim($swp['scheme_name'] ?? $swp['Fund_Name'] ?? '');
                if (!isset($seenSwpFunds[$fundKey])) {
                    $totalSwpCost += isset($holdingsLookupByIsin[$isin]['cost']) ? (float)$holdingsLookupByIsin[$isin]['cost'] : 0.0;
                    $seenSwpFunds[$fundKey] = true;
                }

                $amount = (float)str_replace(',', '', $swp['Amount'] ?? $swp['installments_amount'] ?? 0);
                $startDateStr = $swp['From_Date'] ?? $swp['start_date'] ?? '';
                $endDateStr = $swp['To_Date'] ?? $swp['end_date'] ?? '';
                
                $start_ts = strtotime($startDateStr);
                $end_ts = strtotime($endDateStr);
                
                // If end date is missing, in the future, or something like "2099-12-31", cap at today
                if (!$end_ts || $end_ts > $now_ts || date('Y', $end_ts) > 2050) {
                    $end_ts = $now_ts;
                }
                
                if ($start_ts > 0 && $start_ts <= $end_ts) {
                    $start_y = (int)date('Y', $start_ts);
                    $start_m = (int)date('m', $start_ts);
                    $end_y = (int)date('Y', $end_ts);
                    $end_m = (int)date('m', $end_ts);
                    
                    $months_passed = (($end_y - $start_y) * 12) + ($end_m - $start_m);
                    
                    // Add 1 if the day of the month has been reached or passed
                    if ((int)date('d', $end_ts) >= (int)date('d', $start_ts)) {
                        $months_passed++;
                    }
                    
                    $freq = strtoupper(trim($swp['Frequency'] ?? $swp['frequency_type'] ?? 'MONTHLY'));
                    $withdrawals = 0;
                    
                    if (strpos($freq, 'QUARTER') !== false || $freq === 'Q') {
                        $withdrawals = floor($months_passed / 3);
                    } elseif (strpos($freq, 'HALF') !== false || $freq === 'H' || strpos($freq, 'SEMI') !== false) {
                        $withdrawals = floor($months_passed / 6);
                    } elseif (strpos($freq, 'YEAR') !== false || strpos($freq, 'ANNUAL') !== false || $freq === 'Y') {
                        $withdrawals = floor($months_passed / 12);
                    } elseif (strpos($freq, 'WEEK') !== false || $freq === 'W') {
                        $withdrawals = floor(max(0, $end_ts - $start_ts) / (86400 * 7));
                    } else { // Default to monthly
                        $withdrawals = $months_passed;
                    }
                    
                    $totalSwpWithdrawals += $withdrawals * $amount;
                }
            }
            $swpCurrentValue = (float)($swpXirrResult['portfolio_value'] ?? 0);
            if ($totalSwpCost > 0 || $totalSwpWithdrawals > 0 || $swpCurrentValue > 0) {
                $swpBarChartPng = method_exists($bm, 'buildSwpFlowChart')
                    ? $bm->buildSwpFlowChart($totalSwpCost, $totalSwpWithdrawals, $swpCurrentValue, $headName . '_swpflow', 320, 180)
                    : $bm->buildValueComparisonChart($totalSwpWithdrawals, $swpCurrentValue, 'SWP Flow', $headName . '_swpflow', 320, 180);
            }
        }

        // Enrich active SIP/SWP list with total units and current value
        if (!empty($runningSIPInfo)) {
            foreach ($runningSIPInfo as &$sip) {
                $isin = $sip['ISIN_Code'];
                $planType = $planTypeOf($sip);

                if ($planType === 'SIP') {
                    $amt = (float)str_replace(',', '', $sip['Amount'] ?? 0);
                    $key = $isin . '|' . round($amt, 2);
                    $reg = $sipRegistry[$key] ?? $sipRegistry[$isin] ?? null;

                    if ($reg) {
                        $sip['units'] = max(0.0, (float)($reg['units'] ?? 0.0));

                        // Sum all SIP purchase installment amounts to get total SIP cost
                        $sipCost = 0.0;
                        if (!empty($reg['cashflows'])) {
                            $cfs = array_slice($reg['cashflows'], 0, -1);
                            foreach ($cfs as $cf) {
                                if ($cf < 0) {
                                    $sipCost += abs((float)$cf);
                                }
                            }
                        }
                        $sip['cost_value'] = $sipCost;

                        // Terminal value (SIP units * current NAV)
                        $lastIdx = count($reg['cashflows']) - 1;
                        if ($lastIdx >= 0 && (float)$reg['cashflows'][$lastIdx] >= 0) {
                            $sip['current_value'] = (float)$reg['cashflows'][$lastIdx];
                        } else {
                            $sip['current_value'] = 0.0;
                        }
                    } else {
                        // Fallback to holdings lookup if no SIP transactions were matched
                        $sip['units'] = $holdingsLookupByIsin[$isin]['units'] ?? 0.0;
                        $sip['current_value'] = $holdingsLookupByIsin[$isin]['value'] ?? 0.0;
                        $sip['cost_value'] = $holdingsLookupByIsin[$isin]['cost'] ?? 0.0;
                    }
                    $sip['xirr'] = $holdingsLookupByIsin[$isin]['xirr'] ?? null;
                } else {
                    // SWP / STP: use overall fund holding lookup values
                    $sip['units'] = $holdingsLookupByIsin[$isin]['units'] ?? 0.0;
                    $sip['current_value'] = $holdingsLookupByIsin[$isin]['value'] ?? 0.0;
                    $sip['cost_value'] = $holdingsLookupByIsin[$isin]['cost'] ?? 0.0;
                    $sip['xirr'] = $holdingsLookupByIsin[$isin]['xirr'] ?? null;
                }
                
                $regLookup = ($planType === 'SIP') ? ($sipRegistry[$isin . '|' . round((float)str_replace(',', '', $sip['Amount'] ?? 0), 2)] ?? $sipRegistry[$isin] ?? null) : ($swpRegistry[$isin] ?? null);
                if ($regLookup) {
                    $cfs = $regLookup['cashflows'];
                    $tps = $regLookup['timeperiods'];

                    if (count($cfs) >= 2) {
                        try {
                            $raw = (new XirrEngine())->XIRRAdpro($cfs, $tps, 0.1);
                            if (is_numeric($raw) && is_finite((float)$raw)) {
                                $sip['xirr'] = round((float)$raw * 100, 2);
                            }
                        } catch (\Throwable $e) {
                            error_log('LitmFin: XIRR calculation error for ISIN ' . $isin . ' — ' . $e->getMessage());
                            $sip['xirr'] = null;
                        }
                    }
                }
            }
            unset($sip);
        }

        // ── Market cap allocation history (for analytics chart) ─────────────────
        $mcapHistory = [];
        if (!empty($portfolioInfo)) {
            try {
                $mcapHistory = calculateMcapHistory($portfolioInfo, $pdo);
            } catch (Throwable $e) {
                error_log('LitmFin: mcapHistory failed — ' . $e->getMessage());
            }
        }

        // Render Individual Report
        ob_start();
        include __DIR__ . '/report-templates/report_main.php';
        $html .= ob_get_clean();
    } // End of members loop

    // ── DEBUG mode: full HTML preview with XIRR diagnostics ──────────────────
    // Access: generatereport.php?i=IIN&debug=1
    if ($debug) {
        echo '<!DOCTYPE html><html><head>';
        echo '<link rel="stylesheet" href="report-templates/report_styles.css">';
        echo '<style>body{margin:20px;font-family:sans-serif} pre{background:#f0f4f8;padding:16px;border-radius:6px;font-size:10px;line-height:1.6;border:1px solid #ccd} .ok{color:#1a7a4a;font-weight:bold} .warn{color:#c0392b;font-weight:bold}</style>';
        echo '</head><body>';

        // XIRR diagnostic panel
        echo '<div style="background:#1D3557;color:#fff;padding:12px 16px;border-radius:6px;margin-bottom:16px;">';
        echo '<h3 style="margin:0 0 8px;font-size:13pt;">XIRR Debug Panel — IIN: ' . htmlspecialchars($userIIN) . '</h3>';
        echo '<pre style="background:transparent;border:none;padding:0;color:#e0e8f0;">';
        $xirrEngine = XIRR_USE_OLD_FINANCIAL ? '<span style="color:#90EE90">Financial class ✓</span>' : '<span style="color:#90EE90">Bisection engine ✓</span>';
        echo "XIRR Engine      : {$xirrEngine}
";
        echo "Registry entries : " . count($fundCashFlowRegistry) . " funds
";
        echo "
";

        $rows = [
            ['Period',          'Portfolio XIRR',                           'Nifty 500 Mirror'],
            ['Since Inception',  $sinceInception['portfolio_xirr'].'%',      $sinceInception['benchmark_xirr'].'%'],
            ['Last 3 Years',     $rolling3yr['portfolio_xirr'].'%',           $rolling3yr['benchmark_xirr'].'%'],
            ['Last 5 Years',     $rolling5yr['portfolio_xirr'].'%',           $rolling5yr['benchmark_xirr'].'%'],
        ];
        foreach ($rows as $i => [$p, $v1, $v2]) {
            echo str_pad($p, 18) . str_pad($v1, 20) . $v2 . "
";
        }
        echo "
";
        echo "First date       : " . ($sinceInception['first_date']   ?? 'NULL') . "
";
        echo "Holding days     : " . ($sinceInception['holding_days'] ?? 'NULL') . "
";
        echo "Total curr value : ₹" . number_format($totalCurrentValue, 2) . "
";
        echo "Total_Holding_Ret: " . ($totalAssetInfo[0]['Total_Holding_Return'] ?? 'NOT FOUND') . "
";
        echo '</pre></div>';

        // Registry sample
        echo '<details style="margin-bottom:16px;"><summary style="cursor:pointer;font-weight:bold;">Registry — first 3 fund entries</summary><pre>';
        foreach (array_slice($fundCashFlowRegistry, 0, 3) as $i => $entry) {
            echo "Entry {$i}: {$entry['fund_name']} ({$entry['investment_type']} / {$entry['asset_class']})
";
            echo "  cashflow count: " . count($entry['cashflows']) . "
";
            echo "  first cashflow: " . ($entry['cashflows'][0] ?? 'N/A') . " on " . date('Y-m-d', $entry['timeperiods'][0] ?? 0) . "
";
            $lastCf = end($entry['cashflows']);
            $lastTs = end($entry['timeperiods']);
            echo "  last cashflow:  {$lastCf} on " . date('Y-m-d', $lastTs) . "

";
        }
        echo '</pre></details>';

        echo $html;
        echo '</body></html>';
        return ['status' => 'debug'];
    }

    // ── Build mPDF ────────────────────────────────────────────────────────────
    if ($action !== 'download_excel') {
    //
    // ┌─ FONT & SIZE CONTROLS ────────────────────────────────────────────────┐
    // │  Global base font size → 'default_font_size' (pt)                    │
    // │    8  = compact (more content per page, small text)                  │
    // │    9  = current default — good balance                               │
    // │   10  = comfortable reading                                           │
    // │                                                                       │
    // │  Font face → 'default_font'  ('dejavusans' supports Unicode/₹)      │
    // │                                                                       │
    // │  Page margins (mm) — reduce for more content area:                   │
    // │    margin_left/right: 10  (min 8 for printers)                       │
    // │    margin_top:  22 (includes header height)                           │
    // │    margin_bottom: 15 (includes footer height)                         │
    // └───────────────────────────────────────────────────────────────────────┘
    $mpdf = new Mpdf([
        'mode'              => 'utf-8',
        'format'            => 'A4',
        'default_font_size' => 8,    // ← change this to resize ALL text globally (pt)
        'default_font'      => 'dejavusans',
        'margin_left'       => 10,
        'margin_right'      => 10,
        'margin_top'        => 22,
        'margin_bottom'     => 15,
        'margin_header'     => 5,
        'margin_footer'     => 5,
        'tempDir'           => __DIR__ . '/tmp'
    ]);

    // Performance optimizations for large reports
    $mpdf->simpleTables = true;
    $mpdf->useSubstitutions = false;

    // Set the browser tab title and PDF document title to the client's name
    $mpdf->SetTitle($headName . ' - Portfolio Report');

    // Header + Footer
    $logoPath = __DIR__ . '/../img/logo.png';
    $logoTag  = file_exists($logoPath)
        ? '<img src="data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) . '" style="height:18px;"/>'
        : '<strong style="font-size:9pt;color:#2C5F8A;">LitmFin</strong>';

    $mpdf->SetHTMLHeader('
        <table width="100%" style="border-bottom:1px solid #ddd;padding-bottom:3px;">
            <tr>
                <td style="width:40%">' . $logoTag . '</td>
                <td style="text-align:right;color:#888;font-size:8pt;font-family:sans-serif;">
                    Portfolio Statement &mdash; ' . htmlspecialchars($headName) . '
                </td>
            </tr>
        </table>
    ');
    $mpdf->SetHTMLFooter('
        <table width="100%">
            <tr>
                <td style="font-size:7pt;color:#aaa;font-family:sans-serif;">
                    LitmFin Pvt Ltd &bull; {DATE j M Y} &bull; Confidential
                </td>
                <td style="text-align:right;font-size:7pt;color:#aaa;font-family:sans-serif;">
                    Page {PAGENO} of {nbpg}
                </td>
            </tr>
        </table>
    ');

	
    $cssFile = __DIR__ . '/report-templates/report_styles.css';
    if (file_exists($cssFile)) {
        $mpdf->WriteHTML(file_get_contents($cssFile), \Mpdf\HTMLParserMode::HEADER_CSS);
    }
    
    // Split HTML into smaller chunks to prevent pcre.backtrack_limit exception
    $chunks = preg_split('/<pagebreak\s*\/>/i', $html);
    foreach ($chunks as $idx => $chunk) {
        if ($idx > 0) {
            $mpdf->WriteHTML('<pagebreak/>');
        }
        if (trim($chunk) !== '') {
            $mpdf->WriteHTML($chunk, \Mpdf\HTMLParserMode::HTML_BODY);
        }
    }
    }
	
	
    // ── XIRR debug Excel ──────────────────────────────────────────────────────
    $xirrFile = null;
    if ($genXirr && !empty($fundCashFlowRegistry)) {
        try {
            // Create an isolated registry containing only held assets
            $heldRegistry = array_filter($fundCashFlowRegistry, fn($e) => strtoupper($e['status'] ?? 'HELD') === 'HELD');

            // Set 3Yr and 5Yr window bounds
            $window3yr = strtotime('-3 years');
            $window5yr = strtotime('-5 years');

            $xirrRowsAll    = buildXirrRows($fundCashFlowRegistry, $pdo);
            $xirrRowsAll3yr = buildXirrRows($fundCashFlowRegistry, $pdo, $window3yr);
            $xirrRowsAll5yr = buildXirrRows($fundCashFlowRegistry, $pdo, $window5yr);
            $xirrSummaryAll = buildXirrSummaryTable($fundCashFlowRegistry, $pdo);

            $xirrRowsHeld    = buildXirrRows($heldRegistry, $pdo);
            $xirrRowsHeld3yr = buildXirrRows($heldRegistry, $pdo, $window3yr);
            $xirrRowsHeld5yr = buildXirrRows($heldRegistry, $pdo, $window5yr);
            $xirrSummaryHeld = buildXirrSummaryTable($heldRegistry, $pdo);

            if (!empty($xirrRowsAll) || !empty($xirrRowsHeld)) {
                $xirrHeadName = $membersToProcess[0]['name'] ?? $headName;
                $xirrDir  = $saveMode ? $reportsDir : (__DIR__ . '/xirr_reports/');
                $xirrFile = saveXirrDebugExcel(
                    $xirrRowsAll, $xirrSummaryAll, $xirrRowsHeld, $xirrSummaryHeld, 
                    $userIIN, $xirrHeadName, $xirrDir,
                    $xirrRowsAll3yr, $xirrRowsAll5yr, $xirrRowsHeld3yr, $xirrRowsHeld5yr
                );
                error_log('LitmFin: XIRR Excel saved — ' . $xirrFile);
            }
        } catch (Throwable $e) {
            error_log('LitmFin: XIRR Excel failed — ' . $e->getMessage());
        }
    }

    // ── Output ────────────────────────────────────────────────────────────────
    if ($action === 'download_excel') {
        if ($xirrFile && file_exists($xirrFile)) {
            ob_end_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($xirrFile) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($xirrFile));
            readfile($xirrFile);
            unlink($xirrFile);
            exit;
        } else {
            ob_end_clean();
            die('Error: Excel file could not be generated.');
        }
    }

    $reportHeadName = $membersToProcess[0]['name'] ?? $headName;
    $safe           = preg_replace('/[^A-Za-z0-9_\-]/', '_', $reportHeadName);
    $portfolioType  = ($family === 1) ? 'Family_Portfolio' : 'Portfolio';
    $filename       = $safe . '_' . $portfolioType . '_' . date('dmy') . '.pdf';

    if ($saveMode || $action === 'save') {
        // Silent save — no browser output
        if (!is_dir($reportsDir)) mkdir($reportsDir, 0755, true);
        $pdfPath = $reportsDir . $filename;
        $mpdf->Output($pdfPath, 'F');   // 'F' = save to file
        //error_log('LitmFin: PDF saved — ' . $pdfPath);
        return ['pdf' => $pdfPath, 'xirr' => $xirrFile, 'status' => 'saved'];
    } else {
        // Stream to browser
        ob_end_clean();
        if ($action === 'download') {
            $mpdf->Output($filename, 'D');  // Force Download: Prompts the user to save the file locally
        } else {
            $mpdf->Output($filename, 'I');  // Inline View (Preview): Displays the PDF inside the browser's PDF viewer.
        }
        return ['status' => 'streamed'];
    }

}
