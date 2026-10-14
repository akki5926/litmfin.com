<?php
	require_once('auth_guard.php');						   
	require_once('financial_class.php');
	require_once('portfolio-calculations.php');
	require_once('database.php');
	require_once('totalholdings.php');
	require_once('error_logger.php'); 
	require_once(__DIR__ . '/xirr_engine.php');
	
	/*******************************************************************************************************
		//	Calculate XIRR returns of each asset class: Total Equity, Total Debt, Total Hybrid, Total Gold 
		//  and Total current Holdings
	***********************************************************************************************************/

	$netFundHolding 	= new MutualFundHolding();
	
	function getRunningSIPDetails($userUCC)
	{
		$sipdb          = new MYSQLDB();
		$userSIPData    = $sipdb->getSipResponseFromDb($userUCC);

		$userSIPInfo = array();
		
		if (!empty($userSIPData)) {
			foreach ($userSIPData as $row) {
								
				// SIP Types: SIP, SWP, STP
				$sipType        = strtoupper(trim($row['Sip_Type'] ?? $row['registration_type'] ?? 'SIP'));
				$fundName       = $row['scheme_name'];
				$amcName        = $row['amc_name'];
				$fundCode    	= $row['rta_scheme_code'];  
				$frequency      = $row['frequency_type'];
				$amount         = $row['installments_amount'];
				$fromDate       = $row['start_date'];
				$toDate         = $row['end_date'];
				$sipStatus      = $row['status']; // ACTIVE , PAUSE, MATURED, CXL, AUTHREJECT,  

				if ($sipType === 'SWP') {
					$amount   = $row['withdrawl_amount'] ?? $amount;
					$fromDate = $row['swp_start_date'] ?? $fromDate;
					$toDate   = $row['swp_end_date'] ?? $toDate;
				} elseif ($sipType === 'STP') {
					$amount   = $row['transfer_amount'] ?? $amount;
					$fromDate = $row['stp_start_date'] ?? $fromDate;
					$toDate   = $row['stp_end_date'] ?? $toDate;
				}
				
				$isinCode        = 	$sipdb->getIsinByFundNameAndCode($fundName, $fundCode, $amcName);
				// DEBUG: Log the outcome of SIP/SWP ISIN Resolution
				error_log("DEBUG getRunningSIPDetails: Processed {$sipType} - FundName='{$fundName}', Code='{$fundCode}', ISIN='{$isinCode}', Status='{$sipStatus}'");
				if (strcasecmp($sipStatus, "ACTIVE") == 0) 
				{
					$userSIPInfo[] = array(
						"Sip_Type"        => $sipType,
						"Fund_Name"       => $fundName,
						"Fund_Code"       => $fundCode,
						"ISIN_Code"       => $isinCode,
						"AMC_Name"        => $amcName,
						"Frequency"       => $frequency,
						"Amount"          => $amount,
						"From_Date"       => $fromDate,
						"To_Date"         => $toDate,
						"registration_type" => $sipType,
						"start_date"      => $row['start_date'] ?? null,
						"end_date"        => $row['end_date'] ?? null,
						"swp_start_date"  => $row['swp_start_date'] ?? null,
						"swp_end_date"    => $row['swp_end_date'] ?? null,
						"withdrawl_amount" => $row['withdrawl_amount'] ?? null,
						"stp_start_date"  => $row['stp_start_date'] ?? null,
						"stp_end_date"    => $row['stp_end_date'] ?? null,
						"transfer_amount" => $row['transfer_amount'] ?? null
					);
				}
			}
		}

		return $userSIPInfo; // Always return array (possibly empty)
	}

	/**
	 * Removes matching pairs of rejected and reversal transactions.
	 *
	 * A rejected transaction is identified by a negative transaction amount.
	 * A reversal is a normal transaction on the same day, for the same fund,
	 * with the same absolute amount.
	 *
	 * @param array $transactions The raw transaction array.
	 * @param string $userUCC The user's UCC for logging purposes.
	 * @return array The transaction array with matched pairs removed.
	 */
	function removeRejectionReversalPairs(array $transactions, string $userUCC): array
	{
		$grouped = [];
		// Group transactions by Date, ISIN, and absolute Amount
		foreach ($transactions as $index => $txn) {
			$date = $txn['transaction_date'] ?? null;
			$isin = $txn['isin'] ?? null;
			// round to 4 decimal places to avoid floating point precision mismatches in key grouping
			$amount = isset($txn['amount']) ? round(abs((float)$txn['amount']), 4) : null;

			if (!$date || !$isin || $amount === null) {
				continue;
			}
			$key = "{$date}|{$isin}|{$amount}";
			// Store the original index to modify the main array later
			$grouped[$key][] = ['index' => $index, 'txn' => $txn];
		}

		$indices_to_delete = [];

		foreach ($grouped as $key => $group) {
			$rejected = [];
			$normal = [];

			// Separate into rejected and normal transactions
			foreach ($group as $item) {
				// A transaction is considered rejected if amount is negative
				if (isset($item['txn']['amount']) && (float)$item['txn']['amount'] < 0) {
					$rejected[] = $item['index'];
				} else {
					$normal[] = $item['index'];
				}
			}

			$rejected_count = count($rejected);
			$normal_count = count($normal);

			if ($rejected_count > 0 && $normal_count > 0) {
				$pairs_to_remove = min($rejected_count, $normal_count);

				// Mark pairs for deletion
				$indices_to_delete = array_merge($indices_to_delete, array_slice($rejected, 0, $pairs_to_remove));
				$indices_to_delete = array_merge($indices_to_delete, array_slice($normal, 0, $pairs_to_remove));

				// Log an error if there's a mismatch
				if ($rejected_count > $normal_count) {
					$unmatched_count = $rejected_count - $normal_count;
					list($date, $isin, $amount) = explode('|', $key);
					log_error("Unmatched rejected transaction for UCC {$userUCC}: {$unmatched_count} rejection(s) without reversal on {$date} for ISIN {$isin}, Amount {$amount}.");
				}
			} elseif ($rejected_count > 0 && $normal_count == 0) {
				list($date, $isin, $amount) = explode('|', $key);
				log_error("Unmatched rejected transaction for UCC {$userUCC}: {$rejected_count} rejection(s) without reversal on {$date} for ISIN {$isin}, Amount {$amount}.");
			}
		}

		$indices_to_delete_map = array_flip($indices_to_delete);
		// Return a new array with the specified indices removed
		return array_values(array_filter($transactions, function($index) use ($indices_to_delete_map) {
			return !isset($indices_to_delete_map[$index]);
		}, ARRAY_FILTER_USE_KEY));
	}


	function	sortOtherInvestmentTrxns($otherInvtTrxns)
	{
		$AllBondNames			=	array();
		$AllPmsNames			=	array();
		$AllAifNames			=	array();
		$rawportfolioBonds		=	array();
		$rawportfolioPms		=	array();
		$rawportfolioAif		=	array();
		
		foreach($otherInvtTrxns as $row)	
		{
				if( strcasecmp ($row['invest_type'], "bond") == 0)
				{
					$bondIssuer				= 	!empty(trim($row['issuer_name'] ?? '')) ? $row['issuer_name'] : 'Corporate Bonds';
					$bondName				= 	$row['investment_name'];
					
					// Create a new entry in $rawportfolioOthers for the current fund house if it doesn't exist
					if (!isset($rawportfolioBonds[$bondIssuer])) 
					{
						$rawportfolioBonds[$bondIssuer] = array();
					}
					if (!isset($AllBondNames[$bondIssuer])) 
					{
						$AllBondNames[$bondIssuer] 	= array();
					}
					// Create a new entry in $rawportfolio for the current fund name within the fund house
					$rawportfolioBonds[$bondIssuer][] =	array(
																	'bondType'			=>	$row['invest_type'], 				
																	'bondName'			=>	$row['investment_name'],					
																	'bondIssuer'		=>	$bondIssuer,				
																	'bondAssetClass'	=>	$row['asset_class'],			
																	'bondFaceValue'		=>	$row['face_value'],			
																	'bondTxAmount'		=>	$row['transaction_amount'],			
																	'bondIntRate'		=>	$row['interest_rate'],				
																	'bondUnits'			=>	$row['units'],				
																	'bondTxRate'		=>	$row['transaction_rate'],				
																	'bondTxType'		=>	$row['transaction_type'],				
																	'bondTxDate'		=>	$row['transaction_date'],				
																	'bondPayOutDate'	=>	$row['payout_date'],			
																	'bondPayOutFreq'	=>	$row['payout_frequency'],			
																	'bondCurrNAV'		=>	$row['current_rate'],				
																	'bondCurrNavDate'	=>	$row['current_date'],			
																	'bondMaturityDate'	=>	$row['maturity_date']	
																);			

					$tecmpArray	=	$AllBondNames[$bondIssuer];
					
					if(!(in_array($bondName, $tecmpArray, TRUE)))
					{
						// store bond names to the respective bond issuer array
					  $AllBondNames[$bondIssuer][] = $bondName ;
					}	
					
				}// If (bond)
				else if( strcasecmp ($row['invest_type'], "pms") == 0)
				{
					$pmsIssuer				= 	$row['issuer_name'];
					$pmsName				= 	$row['investment_name'];
					// Create a new entry in $rawportfolioOthers for the current fund house if it doesn't exist
					if (!isset($rawportfolioPms[$pmsIssuer])) 
					{
						$rawportfolioPms[$pmsIssuer] = array();
					}
					if (!isset($AllPmsNames[$pmsIssuer])) 
					{
						$AllPmsNames[$pmsIssuer] 	= array();
					}
					// Create a new entry in $rawportfolio for the current fund name within the fund house
					$rawportfolioPms[$pmsIssuer][] =	array(
																	'investType'		=>	$row['invest_type'], 				
																	'pmsName'			=>	$row['investment_name'],					
																	'pmsIssuer'			=>	$row['issuer_name'],				
																	'pmsAssetClass'		=>	$row['asset_class'],			
																	'pmsTxAmount'		=>	$row['transaction_amount'],			
																	'pmsTxType'			=>	$row['transaction_type'],				
																	'pmsTxDate'			=>	$row['transaction_date'],				
																	'pmsCurrValue'		=>	$row['current_value'],				
																	'pmsCurrValueDate'	=>	$row['current_date']			
																);			

					$tecmpArray	=	$AllPmsNames[$pmsIssuer];
					
					if(!(in_array($pmsName, $tecmpArray, TRUE)))
					{
						// store pms names to the respective pms issuer array
					  $AllPmsNames[$pmsIssuer][] = $pmsName ;
					}	
					

				}
				else if( strcasecmp ($row['invest_type'], "aif") == 0)
				{
					$aifIssuer				= 	$row['issuer_name'];
					$aifName				= 	$row['investment_name'];
					
					// Create a new entry in $rawportfolioOthers for the current fund house if it doesn't exist
					if (!isset($rawportfolioAif[$aifIssuer])) 
					{
						$rawportfolioAif[$aifIssuer] = array();
					}
					if (!isset($AllAifNames[$aifIssuer])) 
					{
						$AllAifNames[$aifIssuer] 	= array();
					}
					// Create a new entry in $rawportfolio for the current fund name within the fund house
					$rawportfolioAif[$aifIssuer][] =	array(
																	'aifType'			=>	$row['invest_type'], 				
																	'aifName'			=>	$row['investment_name'],					
																	'aifIssuer'			=>	$row['issuer_name'],				
																	'aifAssetClass'		=>	$row['asset_class'],			
																	'aifFaceValue'		=>	$row['face_value'],			
																	'aifTxAmount'		=>	$row['transaction_amount'],			
																	'aifIntRate'		=>	$row['interest_rate'],				
																	'aifUnits'			=>	$row['units'],				
																	'aifTxRate'			=>	$row['transaction_rate'],				
																	'aifTxType'			=>	$row['transaction_type'],				
																	'aifTxDate'			=>	$row['transaction_date'],				
																	'aifPayOutDate'		=>	$row['payout_date'],			
																	'aifPayOutFreq'		=>	$row['payout_frequency'],			
																	'aifCurrNAV'		=>	$row['current_rate'],				
																	'aifCurrNavDate'	=>	$row['current_date'],			
																	'aifMaturityDate'	=>	$row['maturity_date']	
																);			

					$tecmpArray	=	$AllAifNames[$aifIssuer];
					
					if(!(in_array($aifName, $tecmpArray, TRUE)))
					{
						// store aif names to the respective aif issuer array
					  $AllAifNames[$aifIssuer][] = $aifName ;
					}	
					
					
				} //else if( strcasecmp ($row['invest_type'], "aif") == 0)
		}				
		
		return array($rawportfolioBonds, $AllBondNames, $rawportfolioPms, $AllPmsNames, $rawportfolioAif, $AllAifNames);
	}
	
	function calculateAifHoldings($userUCC, $rawportfolioAif, $allAifNames, $totalHoldingObj)
	{
		$dbAif 				= new MYSQLDB();
		$xirrObj 			= new XirrEngine();
		$HoldingInfoAifs	=	array();	
		$SoldAifInfo		=	array();	
		$cashFlowAifs		= 	array();
		$timePeriodAifs		= 	array();
		$xirrReturnAif		=	0;
		$aifCashFlowRegistry = [];
		
		foreach ($allAifNames as $aifIssuer => $aifInfo) 
		{
			
			if (!isset($HoldingInfoAifs[$aifIssuer])) 
			{
				$HoldingInfoAifs[$aifIssuer] = array();
		
			}	
			foreach ($aifInfo as $aifName) 
			{
				$netFundHolding 	= new MutualFundHolding();
				$payoutDates	=	array();
				$startIndex		=	0;
				
				$schemeTxns = [];
				foreach ($rawportfolioAif as $rawDataAifs => $aifTrxns) 
				{
					foreach ($aifTrxns as $investment) 
					{			
						if(($investment['aifName'] == $aifName) && ($investment['aifIssuer'] == $aifIssuer))
						{
							$schemeTxns[] = $investment;
						}
					}
				}

				// Sort transactions in ascending order of transaction date
				usort($schemeTxns, function($a, $b) {
					return strtotime($a['aifTxDate']) - strtotime($b['aifTxDate']);
				});

				foreach ($schemeTxns as $investment) 
				{
					$unitsTxed					=	$investment['aifUnits'];
					$amountTxed					=	$investment['aifTxAmount'];
					$dateTxed					=	$investment['aifTxDate'];
					$typeTxed					=	$investment['aifTxType'];
					$navOtherInvt				=   $investment['aifCurrNAV'];
					$navDateOtherInvt			=   $investment['aifCurrNavDate'];
					$maturityDate				=	$investment['aifMaturityDate'];
					$aifAssetClass				=	$investment['aifAssetClass'];
					$firstPayoutDate			=	$investment['aifPayOutDate'];
					$payoutFrequency			=	$investment['aifPayOutFreq'];
					$lastPayOutDate				=   date("Y-m-d");
					
					if ($typeTxed == "P" )
					{
						$netFundHolding->addTransaction('buy',  $unitsTxed, $amountTxed, $dateTxed);
						$cashFlowAifs[]   	= 	0 - $amountTxed;  // Buy is a negative cashflow
						$timePeriodAifs[] 	= 	strtotime($dateTxed);
					}
					else if ($typeTxed == "R") 
					{
						$netFundHolding->addTransaction('sell',  $unitsTxed, $amountTxed, $dateTxed);
						$cashFlowAifs[]   		= $amountTxed;  // Sell is a positive cashflow
						$timePeriodAifs[] 		= strtotime($dateTxed);
					}
					else 
					{
						// #Error : Record the error. Wrong txn type 
					}
				}
			
				$netUnitsHoldingAif 		= 	$netFundHolding->getNetHoldingUnits();
				$bookedProfitAif	 		= 	$netFundHolding->getrealizedprofits();
				$costNAVAif 				= 	(abs($netUnitsHoldingAif) > 0.0001) ? $netFundHolding->getCostPerUnitOfRemainingUnits() : 0;
				$costOfUnitsHoldingAif 		= 	$netFundHolding->getCostOfRemainingUnits();
				$totalPayoutRecvd			= 	0;
				$aifNavResult				= 	$dbAif->getAifNavDatabyName( $userUCC, $aifName );
				
				if($aifNavResult)
				{
					
					$currentNAVAif				=	$aifNavResult['cur_value'];
					$currNAVDateAif				=	$aifNavResult['nav_date'];
				}
				else
				{
					$currentDate			=	date("Y-m-d");
					$currentNAVAif			=	$costNAVAif;
					$currNAVDateAif			=	$currentDate;
					
					$error_message = " Error :NAV not found for AIF: ".$aifName." Assuming Cost NAV ";
					log_error($error_message);
				}
				
				$currentFundValueAif		= 	$netUnitsHoldingAif * $currentNAVAif;
				$unRealizedProfitAif 		= 	$netUnitsHoldingAif * ( $currentNAVAif - $costNAVAif);
				
				$cashFlowAifs[]				= 	$currentFundValueAif;
				$timePeriodAifs[] 			= 	strtotime($currNAVDateAif);
				
				//Calculate the payouts till this sell date for all units held.
				if($netUnitsHoldingAif > 0)
				{
					$resultPayoutDetails 	= $dbAif->calculateAIFPayouts($userUCC, $aifName);
					$cashFlowPayout			=	$resultPayoutDetails['cashflows'];
					$cashFlowPayoutTime		=	$resultPayoutDetails['timeperiods'];
					$totalPayoutRecvd		=	$resultPayoutDetails['total_payout'];
					
					$cashFlowAifs			= array_merge($cashFlowAifs, $cashFlowPayout);
					$timePeriodAifs		= array_merge($timePeriodAifs, $cashFlowPayoutTime);
					
					$totalHoldingObj->addInvestments($aifAssetClass, "Aif", $costOfUnitsHoldingAif, 0, $currentFundValueAif, $unRealizedProfitAif, $totalPayoutRecvd, $bookedProfitAif, $cashFlowAifs, $timePeriodAifs);
					
					$xirrReturnAif				= 	$xirrObj->XIRRAdpro($cashFlowAifs, $timePeriodAifs, 0.1) * 100;
					$xirrReturnAif				=	number_format($xirrReturnAif, 2, '.', ',');
				
					$costNAVAif					=	number_format($costNAVAif, 4, '.', ',');
					$costOfUnitsHoldingAif		=	number_format($costOfUnitsHoldingAif, 2, '.', ',');
					$currentNAVAif				=	number_format($currentNAVAif, 4, '.', ',');
					$currentFundValueAif		=	number_format($currentFundValueAif, 2, '.', ',');
					$bookedProfitAif			=	number_format($bookedProfitAif, 2, '.', ',');
					$unRealizedProfitAif		=	number_format($unRealizedProfitAif, 2, '.', ',');
					$netUnitsHoldingAif			=	number_format($netUnitsHoldingAif, 4, '.', ',');
					$totalPayoutRecvd			=	number_format($totalPayoutRecvd, 2, '.', ',');

					
					$HoldingInfoAifs[$aifIssuer][] =	array  ( 
																$aifName  => array(
																					"aifName"=>$aifName, 
																					"assetClass"=>$aifAssetClass, 
																					"units"=>$netUnitsHoldingAif,  
																					"costNAV"=>$costNAVAif, 
																					"costValue"=>$costOfUnitsHoldingAif, 
																					"currNAV"=>$currentNAVAif, 
																					"payoutRecvd"=>$totalPayoutRecvd,
																					"realizedProfit"=>$bookedProfitAif,  
																					"unRealizedProfit"=>$unRealizedProfitAif, 
																					"currValue"=>$currentFundValueAif, 
																					"XIRR"=>$xirrReturnAif,
																					"navDate"=>$currNAVDateAif, 
																					$aifIssuer
																					));					
				}
				else if($netUnitsHoldingAif == 0)
				{
				
					$xirrReturnAif		= $xirrObj->XIRRAdpro($cashFlowAifs, $timePeriodAifs, 0.1) * 100;

					$xirrReturnAif			=	number_format($xirrReturnAif, 2, '.', ',');
					$netUnitsHoldingAif	=	number_format($netUnitsHoldingAif, 2, '.', ',');
					$bookedProfit			=	number_format($bookedProfitAif, 2, '.', ',');
					$payoutRecvdAif		= 0;
					
					$SoldAifInfo[] 	= 	array(	"Aif_Issuer_Name"=>$aifIssuer, 
													"Aif_Name"=>$aifName, 
													"Asset_Class"=>$aifAssetClass, 
													"Unit_Holdings"=>$netUnitsHoldingAif, 
													"PayoutRecvd"=>$payoutRecvdAif,											
													"RealizedProfit"=>$bookedProfitAif, 
													"XIRR"=>$xirrReturnAif,
													"cashflows"=>$cashFlowAifs,
													"timeperiods"=>$timePeriodAifs  );
					
				}
				else
				{
					$error_message = " Error :Net unit holdings are negative for AIF holding : ".$aifName;
					log_error($error_message);
				}	
				$unRealizedProfitAif 			= 	0;
				$bookedProfitAif				=	0;
				$totalPayoutRecvd				= 	0;
				$aifCashFlowRegistry[] 			= [
													'investment_type' => 'AIF',
													'fund_name'       => $aifName,
													'amc_code'        => $aifIssuer,
													'fund_code'       => $aifName,
													'asset_class'     => $aifAssetClass,
													'cashflows'       => $cashFlowAifs,
													'timeperiods'     => $timePeriodAifs,
													'status'          => ((float)str_replace(',', '', $netUnitsHoldingAif) > 0) ? 'HELD' : 'SOLD',
												];
				$cashFlowAifs					= 	array();
				$timePeriodAifs					= 	array();
				$netFundHolding->reInitializeHolding();
				
			}
		}			
		

		$totalHoldingInfoAifs		=	$totalHoldingObj->getAifDataTotal();
		// If $totalHoldingInfoAifs is empty, means total value of all AIF funds to be written in 
		// last row as "Total" with returns etc is not available. That means all AIF funds have been Sold
		// Reset the $HoldingInfoAifs array, so that nothing is printed in Report. Only All AIF exited info will be available
		if(!$totalHoldingInfoAifs)
		{
			$HoldingInfoAifs	= array();
		}
		return array($HoldingInfoAifs, $totalHoldingInfoAifs, $SoldAifInfo, $aifCashFlowRegistry);
	}

	
	
	function calculatePmsHoldings($userUCC, $rawportfolioPms, $allPmsNames, $totalHoldingObj)
	{
		$dbPms 					= 	new MYSQLDB();
		$netFundHolding 		= 	new MutualFundHolding();
		$xirrObj 				= new XirrEngine();
		
		$HoldingInfoPms			=	array();	
		$SoldPmsInfo			=	array();	
		$cashFlowPms			= 	array();
		$timePeriodPms			= 	array();
		$xirrReturnPms			=	0;
		$totalPurchaseCostPms	=	0;
		$totalSellValuePms		=	0;
		
		$totalPurchaseCostPms	=	0;
		$currentFundValuePms	=	0;
		$pmsCashFlowRegistry 	= [];
		
		foreach ($allPmsNames as $pmsIssuer => $pmsInfo) 
		{
			if (!isset($HoldingInfoPms[$pmsIssuer])) 
			{
				$HoldingInfoPms[$pmsIssuer] = array();
			}	
			foreach ($pmsInfo as $pmsName) 
			{
				$schemeTxns = [];
				foreach ($rawportfolioPms as $rawDataPms => $pmsTrxns) 
				{
					foreach ($pmsTrxns as $investment) 
					{			
						if(($investment['pmsName'] == $pmsName) && ($investment['pmsIssuer'] == $pmsIssuer))
						{
							$schemeTxns[] = $investment;
						}			
					}
				}

				// Sort transactions chronologically by transaction date
				usort($schemeTxns, function($a, $b) {
					return strtotime($a['pmsTxDate']) - strtotime($b['pmsTxDate']);
				});

				$isFullExit           = false;
				$totalPurchaseCostPms = 0;
				$totalSellValuePms    = 0;
				$cashFlowPms          = array();
				$timePeriodPms        = array();
				$pmsAssetClass        = '';

				foreach ($schemeTxns as $investment)
				{
					$amountTxed    = (float)$investment['pmsTxAmount'];
					$dateTxed      = $investment['pmsTxDate'];
					$typeTxed      = strtoupper(trim($investment['pmsTxType']));
					$pmsAssetClass = $investment['pmsAssetClass'];

					if (in_array($typeTxed, ['P', 'NEW', 'TOPUP']))
					{
						$cashFlowPms[]        = 0 - $amountTxed;  // Buy / Top Up is a negative cashflow
						$timePeriodPms[]      = strtotime($dateTxed);
						$totalPurchaseCostPms += $amountTxed;
					}
					else if (in_array($typeTxed, ['R', 'FULL', 'PARTIAL', 'PART'])) 
					{
						$cashFlowPms[]       = $amountTxed;  // Sell is a positive cashflow
						$timePeriodPms[]     = strtotime($dateTxed);
						$totalSellValuePms   += $amountTxed;

						if ($typeTxed === 'R' || $typeTxed === 'FULL') {
							$isFullExit = true;
						}
					}
					else 
					{
						$error_message = "Error: Wrong txn type for PMS: ".$typeTxed;
						log_error($error_message);
					}
				}

				if (!$isFullExit)
				{
					$pmsNAVResult = $dbPms->getPmsNavDatabyName($userUCC, $pmsName);
					if($pmsNAVResult)
					{
						$currentFundValuePms = (float)$pmsNAVResult['cur_value'];
						$currNAVDatePms      = $pmsNAVResult['nav_date'];
					}
					else
					{					
						$currentDate          = date("Y-m-d");
						$currentFundValuePms  = 0;
						$currNAVDatePms       = $currentDate;
						$error_message = " Error: Current Value(NAV) not found for PMS: ".$pmsName." Assuming Current Value = 0 ";
						log_error($error_message);
					}
				}
				else
				{
					$currentFundValuePms = 0;
					$currNAVDatePms      = date("Y-m-d");
				}					
				
				if ($currentFundValuePms > 0 && !$isFullExit)
				{
					$cashFlowPms[]   = $currentFundValuePms;
					$timePeriodPms[] = strtotime($currNAVDatePms);

					$totalHoldingObj->addInvestments($pmsAssetClass, "Pms", $totalPurchaseCostPms, $totalSellValuePms, $currentFundValuePms, 0, 0, 0, $cashFlowPms, $timePeriodPms);

					$xirrReturnPms             = $xirrObj->XIRRAdpro($cashFlowPms, $timePeriodPms, 0.1) * 100;
					$xirrReturnPms             = number_format($xirrReturnPms, 2, '.', ',');
					$formattedCurrValuePms     = number_format($currentFundValuePms, 2, '.', ',');
					$HoldingInfoPms[$pmsIssuer][] = array( 
						$pmsName => array(
							"pmsName"           => $pmsName, 
							"assetClass"        => $pmsAssetClass, 
							"totalPurchaseCost" => $totalPurchaseCostPms, 
							"totalSellValue"    => $totalSellValuePms, 
							"navDate"           => $currNAVDatePms, 
							"currValue"         => $formattedCurrValuePms, 
							"XIRR"              => $xirrReturnPms  
						)
					);					
				}
				else
				{
					// All units/capital are sold out -> PMS Exit
					$xirrReturnPms = $xirrObj->XIRRAdpro($cashFlowPms, $timePeriodPms, 0.1) * 100;
					$xirrReturnPms = number_format($xirrReturnPms, 2, '.', ',');
					
					$SoldPmsInfo[] = array(
						"Pms_Issuer_Name" => $pmsIssuer, 
						"Pms_Name"        => $pmsName, 
						"Asset_Class"     => $pmsAssetClass, 
						"XIRR"            => $xirrReturnPms,
						"cashflows"       => $cashFlowPms,
						"timeperiods"     => $timePeriodPms
					);
				}
			
				$pmsCashFlowRegistry[] = [
					'investment_type' => 'PMS',
					'fund_name'       => $pmsName,
					'amc_code'        => $pmsIssuer,
					'fund_code'       => $pmsName,
					'asset_class'     => $pmsAssetClass,
					'cashflows'       => $cashFlowPms,
					'timeperiods'     => $timePeriodPms,
					'status'          => ($currentFundValuePms > 0 && !$isFullExit) ? 'HELD' : 'SOLD',
				];
	
				$cashFlowPms          = array();
				$timePeriodPms        = array();
				$totalSellValuePms    = 0;
				$totalPurchaseCostPms = 0;
				$currentFundValuePms  = 0;
				$xirrReturnPms        = 0;
			} // Calculation of net holding of one PMS fund is completed
		}			
		
		$totalHoldingInfoPms = $totalHoldingObj->getPmsDataTotal();
		if(!$totalHoldingInfoPms)
		{
			$HoldingInfoPms = array();
		}
		return array($HoldingInfoPms, $totalHoldingInfoPms, $SoldPmsInfo, $pmsCashFlowRegistry);
	}


	function calculateBondHoldings($rawportfolioBonds, $allBondNames, $totalHoldingObj)
	{
		$dbBond 			= new MYSQLDB();
		$xirrObj 			= new XirrEngine();
		
		$HoldingInfoBonds	=	array();	
		$SoldBondInfo		=	array();	
		$cashFlowBonds		= 	array();
		$timePeriodbonds	= 	array();
		$xirrReturnBond			=	0;
		$bondCashFlowRegistry = [];
		
		foreach ($allBondNames as $bondIssuer => $bondInfo) 
		{
			if (!isset($HoldingInfoBonds[$bondIssuer])) 
			{
				$HoldingInfoBonds[$bondIssuer] = array();
		
			}	
			foreach ($bondInfo as $bondName) 
			{
				$netFundHolding 	= new MutualFundHolding();
				$payoutDates	=	array();
				$startIndex		=	0;
				
				$bondTxns = [];
				foreach ($rawportfolioBonds as $rawDataBonds => $bondTrxns) 
				{
					foreach ($bondTrxns as $investment) 
					{			
						if(($investment['bondName'] == $bondName) && ($investment['bondIssuer'] == $bondIssuer))
						{
							$bondTxns[] = $investment;
						}
					}
				}

				// Sort transactions in ascending order of transaction date
				usort($bondTxns, function($a, $b) {
					return strtotime($a['bondTxDate']) - strtotime($b['bondTxDate']);
				});

				foreach ($bondTxns as $investment) 
				{
					$unitsTxed					=	$investment['bondUnits'];
					$amountTxed					=	$investment['bondTxAmount'];
					$dateTxed					=	$investment['bondTxDate'];
					$typeTxed					=	$investment['bondTxType'];
					$navOtherInvt				=   $investment['bondCurrNAV'];
					$navDateOtherInvt			=   $investment['bondCurrNavDate'];
					$maturityDate				=	$investment['bondMaturityDate'];
					$bondAssetClass				=	$investment['bondAssetClass'];
					$firstPayoutDate			=	$investment['bondPayOutDate'];
					$payoutFrequency			=	$investment['bondPayOutFreq'];
					$lastPayOutDate				=   date("Y-m-d");
					
					if ($typeTxed == "P" )
					{
						$netFundHolding->addTransaction('buy',  $unitsTxed, $amountTxed, $dateTxed);
						$cashFlowBonds[]   	= 	0 - $amountTxed;  // Buy is a negative cashflow
						$timePeriodbonds[] 	= 	strtotime($dateTxed);
						$startIndex				=	sizeof($payoutDates);
						$endIndex				=	$startIndex + $unitsTxed;
						for ($i = $startIndex; $i < $endIndex ; $i++) 
						{
							// Add a new row with the first and last pay dates
							$payoutDates[$i] = array(
													'purchase_date' 	=> $dateTxed,
													'first_pay_date' 	=> $firstPayoutDate,
													'last_pay_date' 	=> $lastPayOutDate,
													'holding' 			=> "Yes"
							);
						}
					}
					else if ($typeTxed == "R") 
					{
						$endIndex		=	sizeof($payoutDates); //$unitsTxed;
						$loopCounter	=	0;
						for ($i = 0; $i < $endIndex ; $i++) 
						{
								while ($loopCounter < $unitsTxed)
								{
									if(	$payoutDates[$i]['holding'] == "Yes")
									{
										$payoutDates[$i]['last_pay_date'] 		= $dateTxed;
										$payoutDates[$i]['holding'] 			= "No";
										$loopCounter++;
									}
								}
						}

						for ($i = 0; $i < $endIndex; $i++) 
						{
							if ($loopCounter >= $unitsTxed) 
							{
								break; // Exit the loop if we've updated enough elements
							}

							if ($payoutDates[$i]['holding'] == "Yes") 
							{
								$payoutDates[$i]['last_pay_date'] 	= $dateTxed;
								$payoutDates[$i]['holding'] 		= "No";
								$loopCounter++;
							}
						}
					
						$netFundHolding->addTransaction('sell',  $unitsTxed, $amountTxed, $dateTxed);
						$cashFlowBonds[]   		= $amountTxed;  // Sell is a positive cashflow
						$timePeriodbonds[] 	= strtotime($dateTxed);
					}
					else 
					{
						// #Error : Record the error. Wrong txn type 
					}
				}
			
				$netUnitsHoldingBond 		= 	$netFundHolding->getNetHoldingUnits();
				$bookedProfitBond	 		= 	$netFundHolding->getrealizedprofits();
				$costNAVBond 				= 	(abs($netUnitsHoldingBond) > 0.0001) ? $netFundHolding->getCostPerUnitOfRemainingUnits() : 0;
				$costOfUnitsHoldingBond 	= 	$netFundHolding->getCostOfRemainingUnits();
				$totalPayoutRecvd			= 	0;
				$navResult					= 	$dbBond->getOtherInvestmentsNAVByName( $bondName );
				$currentNAVBond				=	0;
				$currNAVDateBond			=	date("Y-m-d");

				if (!empty($navResult) && isset($navResult['current_nav']) && (float)$navResult['current_nav'] > 0) {
					$currentNAVBond			=	(float)$navResult['current_nav'];
					$currNAVDateBond		=	!empty($navResult['nav_date']) ? $navResult['nav_date'] : date("Y-m-d");
				} elseif (isset($navOtherInvt) && (float)$navOtherInvt > 0) {
					$currentNAVBond			=	(float)$navOtherInvt;
					$currNAVDateBond		=	!empty($navDateOtherInvt) ? $navDateOtherInvt : date("Y-m-d");
				} else {
					$currentNAVBond			=	($costNAVBond > 0) ? $costNAVBond : (float)($investment['bondFaceValue'] ?? 0);
					$currNAVDateBond		=	!empty($navDateOtherInvt) ? $navDateOtherInvt : date("Y-m-d");
				}

				// If rate is entered as percentage of face value (e.g. 103.52 for 100,000 face value), convert to unit NAV
				$faceVal = (float)($investment['bondFaceValue'] ?? 0);
				if ($faceVal >= 1000 && $currentNAVBond > 0 && $currentNAVBond < ($faceVal / 10)) {
					$currentNAVBond = ($currentNAVBond / 100) * $faceVal;
				}
				
				$currentDate				=	date("Y-m-d"); 
				$currentFundValueBond		= 	$netUnitsHoldingBond * $currentNAVBond;
				$unRealizedProfitBond 		= 	$netUnitsHoldingBond * ( $currentNAVBond - $costNAVBond);
				
				$cashFlowBonds[]			= 	$currentFundValueBond;
				$timePeriodbonds[] 			= 	strtotime($currNAVDateBond);

				//Calculate the payouts till this sell date for all units held.
				if($netUnitsHoldingBond > 0)
				{
					$resultPayoutDetails 	= $dbBond->calculatePayouts($bondName, $payoutDates); 

					$cashFlowPayout			=	$resultPayoutDetails['cashflows'];
					$cashFlowPayoutTime		=	$resultPayoutDetails['timeperiods'];
					$totalPayoutRecvd		=	$resultPayoutDetails['total_payout'];
					
					$cashFlowBonds			= array_merge($cashFlowBonds, $cashFlowPayout);
					$timePeriodbonds		= array_merge($timePeriodbonds, $cashFlowPayoutTime);

					$totalHoldingObj->addInvestments($bondAssetClass, "Bond", $costOfUnitsHoldingBond, 0, $currentFundValueBond, $unRealizedProfitBond, $totalPayoutRecvd, $bookedProfitBond, $cashFlowBonds, $timePeriodbonds);
					
					$xirrReturnBond				= $xirrObj->XIRRAdpro($cashFlowBonds, $timePeriodbonds, 0.1) * 100;
					$xirrReturnBond				=	number_format($xirrReturnBond, 2, '.', ',');
				
					$costNAVBond				=	number_format($costNAVBond, 2, '.', ',');
					$costOfUnitsHoldingBond		=	number_format($costOfUnitsHoldingBond, 2, '.', ',');
					$currentNAVBond				=	number_format($currentNAVBond, 2, '.', ',');
					$currentFundValueBond		=	number_format($currentFundValueBond, 2, '.', ',');
					$bookedProfitBond			=	number_format($bookedProfitBond, 2, '.', ',');
					$unRealizedProfitBond		=	number_format($unRealizedProfitBond, 2, '.', ',');
					$netUnitsHoldingBond		=	number_format($netUnitsHoldingBond, 2, '.', ',');
					$totalPayoutRecvd			=	number_format($totalPayoutRecvd, 2, '.', ',');

					$HoldingInfoBonds[$bondIssuer][] =	array  ( 
																$bondName  => array(
																					"bondName"=>$bondName, 
																					"assetClass"=>$bondAssetClass, 
																					"unitHoldings"=>$netUnitsHoldingBond,  
																					"costNAV"=>$costNAVBond, 
																					"costValue"=>$costOfUnitsHoldingBond, 
																					"currentNAV"=>$currentNAVBond, 
																					"navDate"=>$currNAVDateBond, 
																					"currValue"=>$currentFundValueBond, 
																					"payoutRecvd"=>$totalPayoutRecvd,
																					"realizedProfit"=>$bookedProfitBond,  
																					"unRealizedProfit"=>$unRealizedProfitBond, 
																					"XIRR"=>$xirrReturnBond  ));					
				}
				else if($netUnitsHoldingBond == 0)
				{
				
					// All units are sold.
					$xirrReturnBond		= $xirrObj->XIRRAdpro($cashFlowBonds, $timePeriodbonds, 0.1) * 100;

					$xirrReturnBond			=	number_format($xirrReturnBond, 2, '.', ',');
					$netUnitsHoldingBond	=	number_format($netUnitsHoldingBond, 2, '.', ',');
					$bookedProfit			=	number_format($bookedProfitBond, 2, '.', ',');
					$payoutRecvdBond		= 0;
					
					$SoldBondInfo[] 	= 	array(	"Bond_Issuer_Name"=>$bondIssuer, 
													"Bond_Name"=>$bondName, 
													"Asset_Class"=>$bondAssetClass, 
													"Unit_Holdings"=>$netUnitsHoldingBond, 
													"PayoutRecvd"=>$payoutRecvdBond,											
													"RealizedProfit"=>$bookedProfitBond, 
													"XIRR"=>$xirrReturnBond,
													"cashflows"=>$cashFlowBonds,
													"timeperiods"=>$timePeriodbonds  );
					
				}

				$unRealizedProfitBond 				= 	0;
				$bookedProfitBond					=	0;
				$totalPayoutRecvd					= 	0;
				
				$bondCashFlowRegistry[] 			= [
																'investment_type' => 'Bond',
																'fund_name'       => $bondName,
																'amc_code'        => $bondIssuer,
																'fund_code'       => $bondName,
																'asset_class'     => $bondAssetClass,
																'cashflows'       => $cashFlowBonds,
																'timeperiods'     => $timePeriodbonds,
																'status'          => ((float)str_replace(',', '', $netUnitsHoldingBond) > 0) ? 'HELD' : 'SOLD',
														];
							
				$cashFlowBonds						= 	array();
				$timePeriodbonds					= 	array();
				$netFundHolding->reInitializeHolding();
				
			}
		}			
		
		$totalHoldingInfoBonds		=	$totalHoldingObj->getBondDataTotal();
		
		if(!$totalHoldingInfoBonds)
		{
			$HoldingInfoBonds			= array();
		}
		
		return array($HoldingInfoBonds, $totalHoldingInfoBonds, $SoldBondInfo, $bondCashFlowRegistry);
		
	}
	
	function calculateHoldings($userUCC)
	{
		
		$netFundHolding 	= new MutualFundHolding();
		$totalHoldingObj 	= new TotalHoldings();
		
		
		$xirrCalcObj 	= new XirrEngine();
		$db 				= new MYSQLDB();

		$profiledata	= $db->getProfileData($userUCC);  	
		
		$HoldingInfo	 			= array();
		$SoldFundInfo	 			= array();
		
		$HoldingInfoBonds 			= array();
		$TotalHoldingInfoBonds 		= array();
		$SoldBondInfo				= array();
		
		$HoldingInfoPms 			= array();
		$TotalHoldingInfoPms 		= array();
		$SoldPmsInfo				= array();
		
		$HoldingInfoAif 			= array();
		$TotalHoldingInfoAif 		= array();
		$SoldAifInfo				= array();
		
		$HoldingInfoSif 			= array();
		$TotalHoldingInfoSif 		= array();
		$SoldSifInfo				= array();
		
		$rawportfolio				= array();
		$totalFundsInfo 			= array();

		$rawportfolioSif            = array();
		$totalFundsInfoSif          = array();

		$equityCashFlows 			= array();
		$debtCashFlows 				= array();
		$hybridCashFlows 			= array();
		$goldCashFlows 				= array();
		$totalCashFlows 			= array();
		
		$equityTimePeriod 			= array();
		$debtTimePeriod 			= array();
		$hybridTimePeriod 			= array();
		$goldTimePeriod 			= array();
		$totalTimePeriod 			= array();
		
		
		$fundCashFlowRegistry 		= [];   // per-fund cashflow registry for XIRR debug + benchmark
		$sipTransactions            = [];   // SIP transaction registry
		$runningSIPInfo             = getRunningSIPDetails($userUCC);
		$systematicPlanIndex        = buildSystematicPlanIndex($runningSIPInfo);
		$matchedSystematicTxnKeys   = [];
		$matchedSystematicMonths    = [];
		
		// Top Holdings
		$companyAgg 				= [];
		$sectorAgg  				= [];

		$name		= 	"NameNotFound";
		$address 	= 	"Not Available";
		$userpan    =	"NA";
		$useTaxStatus 	=	"01";
		
		
		if(!empty($profiledata))
		{
			foreach($profiledata as $row)
			{
				$name 		= $row['PRIMARY_HOLDER_NAME'];
				$address 	= $row['ADDRESS_1'];
				$userpan	= $row['PRIMARY_HOLDER_PAN'];
				if ($name !== null) 
					break;
			}
		}
		else
		{
				$address 	= "NA";
		}
		
		// Calculate holdings of other investment data such as Bonds, AIF, Gold, PMS etc..
		$resultOthers 	= $db->getOtherTransactions($userUCC , ''); 
		
		// Bonds
		if(is_iterable($resultOthers))
		{
			list($rawportfolioBonds, $allBondNames, $rawportfolioPms, $allPmsNames, $rawportfolioAif, $allAifNames)	=	sortOtherInvestmentTrxns($resultOthers);
			
			if($rawportfolioBonds)
			{
				list($HoldingInfoBonds, $TotalHoldingInfoBonds, $SoldBondInfo, $bondRegistry)	=	calculateBondHoldings($rawportfolioBonds, $allBondNames, $totalHoldingObj);
			}
			
			// PMS 
			if($rawportfolioPms)
			{
				list($HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo, $pmsRegistry)	=	calculatePmsHoldings($userUCC, $rawportfolioPms, $allPmsNames, $totalHoldingObj);
			}
			
			// AIF 
			if($rawportfolioAif)
			{
				list($HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo, $aifRegistry)	=	calculateAifHoldings($userUCC, $rawportfolioAif, $allAifNames, $totalHoldingObj);
			}
		}
		
		$result 	= $db->getAllTransactions_old($userUCC, '');
		
		// Step 2: Filter valid rows only and reindex the array
		$result = array_values(array_filter($result, function($row) {
			$date = trim($row['transaction_date'] ?? '');
			return $date !== '' && strtotime($date) !== false;
		}));

		// Step 3: Sort by transaction_date
		usort($result, function($a, $b) {
			return strtotime($a['transaction_date']) - strtotime($b['transaction_date']);
		});

		// Log initial row count for diagnostics
		$initialRowCount = count($result);
		error_log("Portfolio UCC {$userUCC}: Initial transaction count = {$initialRowCount}");
		
		// Remove rejected/reversal pairs before main processing
		$result = removeRejectionReversalPairs($result, $userUCC);
		$nettedRowCount = count($result);
		error_log("Portfolio UCC {$userUCC}: Transaction count after removing reversals = {$nettedRowCount}");
		
		$loopcount			=	0;
		foreach($result as $row)	
		{
			// Now amc_code and amc_name are same, according to new mailback
			$amcCode			= 	$row['amc_code'];  
			$fundCode			= 	$row['scheme_code']; 
			$isinCode			= 	$row['isin'];
			// New codes: EDELWEISSMUTUALFUND_MF same as amc code and amc name
			$fundHouseName 		= 	$db->getAMCNamebyISIN($isinCode); 
			$fundName			= 	$db->getFundNamebyISIN($isinCode); 
			
			// Identify if the transaction belongs to an SIF (AMC code/name ending in _SIF)
			$isSif = false;
			if ($fundHouseName && strpos(strtoupper($fundHouseName), '_SIF') !== false) {
			    $isSif = true;
			}

			if(!$fundHouseName)
			{
				// #Error : Record the error. Wrong txn type 
				$error_message = "Client: ".$name."Err: AMC name not found for ISIN: ". $isinCode." Fund Name: ". $fundName;
				log_error($error_message);
				continue;
			}
			if (!$fundName)
			{
				$error_message = "Client: ".$name." Error: "."Fund not found in MF master for AMC: ". $amcCode." ISIN Code: ". $fundCode." Fund House: ".$fundHouseName;
				log_error($error_message);
				continue;
			}
			/*
			KARVY: 
					P - Purchase
					SI - Switch In
					TI - Tranfer In
					DR - Dividend Reinvestment
					DP - Dividend payout
					R/SO/TO indicates subtractions
			CAMS: 
				First letter of the code is used for finding the transaction type.
				Starting with	    	 Description	                        Effect
					P	                                 Purchases             Addition
					R	                                Redemptions	    	   Subtraction
					SI	                                 Switch In		       Addition
					SO	                                 Switch Out		       Subtraction
					TI	                                Transfer In		       Addition
					TO	                                Transfer Out		   Subtraction
					DR	                                Dividend Reinvested	   Addition
					J	                                Rejected Transaction	No Effect
					All others	               Non Financial	                No Effect
			*/
			// 'TRXNMODE' in CAMS : R : Rejected Transactions
			//$transactionType	= 	$row['trxn_type']; // Not used for now.
			//Trxn Mode: (CAMS: N -Normal, M- Predated, R- Reversal // KARVY: "N for Normal / Transfer In // R for rejections / Transfer Out"

			$transactionMode	= 	$row['transaction_mode']; 
			/********** Transaction Sign:
			KARVY: 
				P indicates all Additions
				R Indicates all Subtractions
				D indicates dividend trxns
			CAMS:
				+  Additions
				-  Subtractions
				N  Non financial trxn.  Ignore
			**********/
			$transactionSign	= 	$row['transaction_flag']; // (CAMS: +, -, N  , KARVY: P, R, D (D-> Dividend)
			$unitsTransacted 	= 	abs((float)$row['units']);
			$transactionAmount = 	abs((float)$row['amount']);
			//$transactionDate 	= 	$row['trxn_process_date'];
			$transactionDate 	= 	$row['transaction_date'];
			
			$branchTrxnNumRejected		= 	$row['transaction_number']; // available only in case of "Purchase Rejection" Karvy.	
			//$branchTrxnNum				= 	$row['branch_trxn_num'];	// available in all trxns
			
			// In some cases like Re-KYC pending, unit value comes as NULL along with NAV as as NULL
			if( $unitsTransacted == NULL)
			{
				// #Error: This is invalid txn type. Ignore
				continue;
			}
			
			if( !(strtolower($transactionSign) == strtolower("P") || (strtolower($transactionSign) == strtolower("R"))))
			{
				// #Error: This is non Financial txn type. Ignore
				continue;
			}
			
			// Check the condition for $transactionSign
			if ($transactionSign == "P" || $transactionSign == "R") 
			{
			    if ($isSif) {
                    if (!isset($rawportfolioSif[$amcCode])) {
                        $rawportfolioSif[$amcCode] = array();
                    }
                    
                    if (!isset($totalFundsInfoSif[$amcCode])) {
                        $totalFundsInfoSif[$amcCode] = array();
                    }

					$rawportfolioSif[$amcCode][] = array(
                        'fundAmcCode'=> $amcCode,
                        'fundHouseName'=> $fundHouseName,
                        'isinCode' => $isinCode,
                        'fundName' => $fundName,
                        'unitsTransacted' => $unitsTransacted,
                        'transactionSign' => $transactionSign,
                        'transactionAmount' => $transactionAmount,
                        'transactionDate' => $transactionDate
                    );
                    
                    $tecmpArraySif = $totalFundsInfoSif[$amcCode];
                    if(!in_array($isinCode, $tecmpArraySif, TRUE)) {
                      $totalFundsInfoSif[$amcCode][] = $isinCode;
                    }
			    } else {
					$normalizedTxn = array(
						'fundAmcCode'=> $amcCode,
						'fundHouseName'=> $fundHouseName,
						'isinCode' => $isinCode,
						'fundName' => $fundName,
						'unitsTransacted' => $unitsTransacted,
						'transactionSign' => $transactionSign,
						'transactionAmount' => $transactionAmount,
						'transactionDate' => $transactionDate
					);
					$matchedPlanTxns = matchSystematicPlansForTransaction(
						$normalizedTxn,
						$systematicPlanIndex,
						$matchedSystematicTxnKeys,
						$matchedSystematicMonths
					);
					if (!empty($matchedPlanTxns)) {
						$sipTransactions = array_merge($sipTransactions, $matchedPlanTxns);
					}

    				// Create a new entry in $rawportfolio for the current fund house if it doesn't exist
    				if (!isset($rawportfolio[$amcCode])) 
    				{
    					$rawportfolio[$amcCode] = array();
    				}
    				
    				if (!isset($totalFundsInfo[$amcCode])) 
    				{
    					$totalFundsInfo[$amcCode] = array();
    				}
    
    				// Create a new entry in $rawportfolio for the current fund name within the fund house
    				$rawportfolio[$amcCode][] = $normalizedTxn;
				
    				$tecmpArray	=	$totalFundsInfo[$amcCode];
    				if(!in_array($isinCode, $tecmpArray, TRUE))
    				{
    				  $totalFundsInfo[$amcCode][] = $isinCode ;
    				}
			    }
			}
	
		} // foreach($result as $row)	
		
		
		/**************************************
		 All transaction data is now sorted and stored 
		*********************************************/ 

		$cashFlow					= [];
		$timePeriod 				= [];
		$currentNAV					= 0;
		$currentNAVDate				= "";
		$TotalTxnCount	=	0;
		$totalAmcCount	=	0;
		
		foreach ($totalFundsInfo as $amcCode => $fundsInfo) 
		{
			if (!isset($HoldingInfo[$amcCode])) 
			{
				$HoldingInfo[$amcCode] = array();
			}
			
			$totalAmcCount++;
			$counter	=	0;
			foreach ($fundsInfo as $fundNumber) 
			{
                $payoutRecvd = 0;
				
				foreach ($rawportfolio as $rawData => $funds) 
				{
					foreach ($funds as $fund) 
					{	
						
						if(($fund['isinCode'] == $fundNumber) && ($fund['fundAmcCode'] == $amcCode))
						{	
							$counter++;
							$TotalTxnCount++;
							
							$fundHouseName		=	$fund['fundHouseName'];
							$fundName			=	$fund['fundName'];
							$isinCode			=	$fund['isinCode'];
							$unitsTransacted	=	$fund['unitsTransacted'];
							$transactionSign	=	$fund['transactionSign'];
							$transactionAmount	=	$fund['transactionAmount'];
							$transactionDate	=	$fund['transactionDate'];
							if ($transactionSign == "P")
							{					
								$netFundHolding->addTransaction('buy',  $unitsTransacted, $transactionAmount, $transactionDate);
								$cashFlow[]   = 0 - $transactionAmount;  // Buy is a negative cashflow
								$timePeriod[] = strtotime($transactionDate);
							}							
							else if ($transactionSign == "R") 
							{
								
								$netFundHolding->addTransaction('sell', $unitsTransacted, $transactionAmount, $transactionDate);
								$cashFlow[]   = $transactionAmount;  // Sell is a positive cashflow
								$timePeriod[] = strtotime($transactionDate);
                                if ($unitsTransacted <= 0.0001) {
                                    $payoutRecvd += $transactionAmount;
                                }
							}
							else 
							{
								//It is Switch transaction.							
								// #Error : Record the error. Wrong txn type 
								$error_message = " Error :  Wrong txn type for MF, might be a switch Trxn: ".$transactionSign;
								log_error($error_message);
							}
						}			
					}
				
				}// Calculation of net holding of one fund is completed.
				
				$counter	=	0;

				// Save the AMC name, Fund Code, Fund name, Net Units, Net Cost per unit, Current NAV,Current Value, XIRR, Dividend Received 
				$netUnitsHolding 		= $netFundHolding->getNetHoldingUnits();
				$bookedProfit	 		= $netFundHolding->getrealizedprofits();
				$costNAV 				= (abs($netUnitsHolding) > 0.0001) ? $netFundHolding->getCostPerUnitOfRemainingUnits() : 0;
				$costOfUnitsHolding 	= $netFundHolding->getCostOfRemainingUnits();
				$navData				= $db->getFundNAVByISIN($fundNumber );
				$assetClass				= $db->getFundAssetClassByISIN($fundNumber );
				$unRealizedProfit 		= 0;
			
				if ($navData)
				{
					$currentNAV				= $navData['nav'];
					$currentNAVDate			= $navData['nav_date'];		
				}
				else
				{
					// #Error
					$currentNAV			=  0; 	//$costNAV;
					if($netUnitsHolding > 0)
					{
						$error_message 		= " Error: NAV not found for Client: ".$name;
						log_error($error_message);
						$error_message 		= " ISIN :  ".$fundNumber." Fund Name: ".$fundName;
						log_error($error_message);
					}
				}
				
						
					if(count($cashFlow))	
					{			
						
					if(($netUnitsHolding >= 0.1) && ( $currentNAV != 0))	 
					{
						$cashFlow[]   			= $netUnitsHolding * $currentNAV;  // terminal: current value as positive cashflow

						// Guard against stale NAV date being EARLIER than the last purchase.
						// e.g. Edelweiss Gold bought 2026-03-06 but NAV db only updated to 2026-01-29.
						// If terminal ts < last buy ts, XIRR sees inverted cashflows → wrong negative result.
						// Fix: terminal ts = max(nav_date, last_transaction_date).
						$_navTs      = strtotime($currentNAVDate);
						$_lastBuyTs  = !empty($timePeriod) ? max($timePeriod) : 0;
						if ($_navTs === false || $_navTs <= 0) { $_navTs = time(); }
						if ($_navTs < $_lastBuyTs)            { $_navTs = $_lastBuyTs; }
						$timePeriod[] 			= $_navTs;
						
						// ────────────────────────────────────────────────────────────────
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

						$totalCashFlows			= array_merge($totalCashFlows, $cashFlow);
						$totalTimePeriod		= array_merge($totalTimePeriod, $timePeriod);
						
						$unRealizedProfit 		= 	$netUnitsHolding * ( $currentNAV - $costNAV);
						$currentFundValue		=	$netUnitsHolding * $currentNAV;
						
						$totalHoldingObj->addInvestments($assetClass, "MF", $costOfUnitsHolding, 0, $currentFundValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);
						$xirrReturn			=	0;
						$xirrReturn	= $xirrCalcObj->XIRRAdpro($cashFlow, $timePeriod, 0.1);
						$xirrReturn	= $xirrReturn * 100;
						
						if ((abs($xirrReturn) > 10000 ))
						{
							$error_message 		= " XIRR seems wrong for : ".$name." Fund: ".$fundName; " XIRR= ".$xirrReturn;
							log_error($error_message);
							$xirrReturn	=	0;
						}
						
						$xirrReturn			=	number_format($xirrReturn, 2, '.', ',');
						$netUnitsHolding	=	number_format($netUnitsHolding, 2, '.', ',');
						$costNAV			=	number_format($costNAV, 2, '.', ',');
						$costOfUnitsHolding	=	number_format($costOfUnitsHolding, 2, '.', ',');
						$currentNAV			=	number_format($currentNAV, 2, '.', ',');
						$currentFundValue	=	number_format($currentFundValue, 2, '.', ',');
						$bookedProfit		=	number_format($bookedProfit, 2, '.', ',');
						$unRealizedProfit	=	number_format($unRealizedProfit, 2, '.', ',');
						$payoutRecvd		=	number_format($payoutRecvd, 2, '.', ',');
						
						$HoldingInfo[$amcCode][] = array  ( $fundNumber=> array("Fund_House_Name"=>$fundHouseName, "Fund_Name"=>$fundName, "Asset_Class"=>$assetClass, "Unit_Holdings"=>$netUnitsHolding,  
															"Cost_NAV"=>$costNAV, "Cost_Value"=>$costOfUnitsHolding, "Current_NAV"=>$currentNAV, "NAV_Date"=>$currentNAVDate, 
															"Curr_Value"=>$currentFundValue, "PayoutRecvd"=>$payoutRecvd, "RealizedProfit"=>$bookedProfit,  "UnRealizedProfit"=>$unRealizedProfit, "XIRR"=>$xirrReturn  ));
						$netFundHolding->reInitializeHolding();
					}
					// #ChangeDate: 24-05-2024
					// For some funds where entire holding is sold, $netUnitsHolding does not come exactly zero but a ver small fraction
					// e.g. Mirae Asset Focused Fund - Growth: Fund Code: FFRGGGR Balance Units: 3.6379788070917E-12
					// Forcing in such cases, unit holdings to Zero
					//else if($netUnitsHolding == 0)
					else 
					{
						
						$netUnitsHoldingTemp	=	$netUnitsHolding;
						$netUnitsHolding	=	0;
						// All units are sold.
						$xirrReturn	= $xirrCalcObj->XIRRAdpro($cashFlow, $timePeriod, 0.1);
						$xirrReturn	= $xirrReturn * 100;

						$xirrReturn			=	number_format($xirrReturn, 2, '.', ',');
						$netUnitsHolding	=	number_format($netUnitsHolding, 2, '.', ',');
						$bookedProfit		=	number_format($bookedProfit, 2, '.', ',');
						$xirrReturn			=	number_format((float)$xirrReturn, 2, '.', ',');
						$SoldFundInfo[] 	= 	array(	"Fund_House_Name"=>$fundHouseName, 
														"Fund_Name"=>$fundName, "Asset_Class"=>$assetClass, 
														"Unit_Holdings"=>$netUnitsHolding, 
														"PayoutRecvd"=>$payoutRecvd,											
														"RealizedProfit"=>$bookedProfit, 
														"XIRR"=>$xirrReturn,
														"cashflows"=>$cashFlow,
														"timeperiods"=>$timePeriod  );
						//echo "Exit Fund_Name : ".$fundName. ": Fund Code:  ".$fundNumber." Balance Units: ".$netUnitsHoldingTemp;
						
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

					}
				}
				//}
				else
				{
					$error_message 		= "Error: Client: ".$name."Cashflow Array not set ".$fundName;
					log_error($error_message);
				}
				$cashFlow					= array();
				$timePeriod					= array();
				$netFundHolding->reInitializeHolding();
				$bookedProfit				=	0;
				
			} //All Fund holdings for one fund house is sorted and stored.
			
		}  //	foreach ($totalFundsInfo as $amcCode => $fundsInfo) 

        // ---------------- SIF CALCULATIONS ----------------
		// Independent calculation block for SIF to avoid mixing with MF returns
		foreach ($totalFundsInfoSif as $amcCode => $fundsInfo) 
		{
			//echo "<BR> SIF calculations";
			if (!isset($HoldingInfoSif[$amcCode])) 
			{
				$HoldingInfoSif[$amcCode] = array();
			}
			
			$counter	=	0;
			
			foreach ($fundsInfo as $fundNumber) 
			{
                $payoutRecvd = 0;
				foreach ($rawportfolioSif as $rawData => $funds) 
				{
					foreach ($funds as $fund) 
					{	
						if(($fund['isinCode'] == $fundNumber) && ($fund['fundAmcCode'] == $amcCode))
						{	
							$counter++;
							$TotalTxnCount++;
							
							$fundHouseName		=	$fund['fundHouseName'];
							$fundName			=	$fund['fundName'];
							$isinCode			=	$fund['isinCode'];
							$unitsTransacted	=	$fund['unitsTransacted'];
							$transactionSign	=	$fund['transactionSign'];
							$transactionAmount	=	$fund['transactionAmount'];
							$transactionDate	=	$fund['transactionDate'];
							
							if ($transactionSign == "P" )
							{
								$netFundHolding->addTransaction('buy',  $unitsTransacted, $transactionAmount, $transactionDate);
								$cashFlow[]   = 0 - $transactionAmount;
								$timePeriod[] = strtotime($transactionDate);
							}
							else if ($transactionSign == "R") 
							{
								$netFundHolding->addTransaction('sell', $unitsTransacted, $transactionAmount, $transactionDate);
								$cashFlow[]   = $transactionAmount;
								$timePeriod[] = strtotime($transactionDate);
                                if ($unitsTransacted <= 0.0001) {
                                    $payoutRecvd += $transactionAmount;
                                }
							}
							else 
							{
								$error_message = " Error :  Wrong txn type for SIF, might be a switch Trxn: ".$transactionSign;
								log_error($error_message);
							}
						}			
					}
				}
				
				$counter	=	0;

				$netUnitsHolding 		= $netFundHolding->getNetHoldingUnits();
				$bookedProfit	 		= $netFundHolding->getrealizedprofits();
				$costNAV 				= (abs($netUnitsHolding) > 0.0001) ? $netFundHolding->getCostPerUnitOfRemainingUnits() : 0;
				$costOfUnitsHolding 	= $netFundHolding->getCostOfRemainingUnits();
				$navData				= $db->getFundNAVByISIN($fundNumber );
				$assetClass				= $db->getFundAssetClassByISIN($fundNumber );
				$unRealizedProfit 		= 0;
			
				if ($navData)
				{
					$currentNAV				= $navData['nav'];
					$currentNAVDate			= $navData['nav_date'];		
				}
				else
				{
					$currentNAV			=  0; 	//$costNAV;
					if($netUnitsHolding > 0)
					{
						$error_message 		= " Error: NAV not found for SIF Client: ".$name;
						log_error($error_message);
						$error_message 		= " AMC: ". $amcCode. " Fund Code:  ".$fundNumber." Fund Name: ".$fundName;
						log_error($error_message);
					}
				}
						
				if(count($cashFlow))	
				{			
					if(($netUnitsHolding >= 0.1) && ( $currentNAV != 0))	 
					{
						$cashFlow[]   			= $netUnitsHolding * $currentNAV;

						$_navTs      = strtotime($currentNAVDate);
						$_lastBuyTs  = !empty($timePeriod) ? max($timePeriod) : 0;
						if ($_navTs === false || $_navTs <= 0) { $_navTs = time(); }
						if ($_navTs < $_lastBuyTs)            { $_navTs = $_lastBuyTs; }
						$timePeriod[] 			= $_navTs;
						
						$fundCashFlowRegistry[] = [
								'investment_type' => 'SIF',
								'fund_name'       => $fundName,
								'amc_code'        => $amcCode,
								'fund_code'       => $fundNumber,
								'asset_class'     => $assetClass,
								'cashflows'       => $cashFlow,
								'timeperiods'     => $timePeriod,
								'status'          => 'HELD',
							];

						$totalCashFlows			= array_merge($totalCashFlows, $cashFlow);
						$totalTimePeriod		= array_merge($totalTimePeriod, $timePeriod);
                        
						$unRealizedProfit 		= 	$netUnitsHolding * ( $currentNAV - $costNAV);
						$currentFundValue		=	$netUnitsHolding * $currentNAV;
						
						$totalHoldingObj->addInvestments($assetClass, "SIF", $costOfUnitsHolding, 0, $currentFundValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);
						
						$xirrReturn			=	0;
						$xirrReturn	= $xirrCalcObj->XIRRAdpro($cashFlow, $timePeriod, 0.1);
						$xirrReturn	= $xirrReturn * 100;
						
						if ((abs($xirrReturn) > 10000 ))
						{
							$error_message 		= " XIRR seems wrong for SIF : ".$name." Fund: ".$fundName; " XIRR= ".$xirrReturn;
							log_error($error_message);
							$xirrReturn	=	0;
						}
						
						$xirrReturn			=	number_format($xirrReturn, 2, '.', ',');
						$netUnitsHolding	=	number_format($netUnitsHolding, 2, '.', ',');
						$costNAV			=	number_format($costNAV, 2, '.', ',');
						$costOfUnitsHolding	=	number_format($costOfUnitsHolding, 2, '.', ',');
						$currentNAV			=	number_format($currentNAV, 2, '.', ',');
						$currentFundValue	=	number_format($currentFundValue, 2, '.', ',');
						$bookedProfit		=	number_format($bookedProfit, 2, '.', ',');
						$unRealizedProfit	=	number_format($unRealizedProfit, 2, '.', ',');
						$payoutRecvd		=	number_format($payoutRecvd, 2, '.', ',');
						
						$HoldingInfoSif[$amcCode][] = array  ( $fundNumber=> array("Fund_House_Name"=>$fundHouseName, "Fund_Name"=>$fundName, "Asset_Class"=>$assetClass, "Unit_Holdings"=>$netUnitsHolding,  
															"Cost_NAV"=>$costNAV, "Cost_Value"=>$costOfUnitsHolding, "Current_NAV"=>$currentNAV, "NAV_Date"=>$currentNAVDate, 
															"Curr_Value"=>$currentFundValue, "PayoutRecvd"=>$payoutRecvd, "RealizedProfit"=>$bookedProfit,  "UnRealizedProfit"=>$unRealizedProfit, "XIRR"=>$xirrReturn  ));
						$netFundHolding->reInitializeHolding();
					}
					else 
					{
						$netUnitsHoldingTemp	=	$netUnitsHolding;
						$netUnitsHolding	=	0;
						$xirrReturn	= $xirrCalcObj->XIRRAdpro($cashFlow, $timePeriod, 0.1);
						$xirrReturn	= $xirrReturn * 100;

						$xirrReturn			=	number_format($xirrReturn, 2, '.', ',');
						$netUnitsHolding	=	number_format($netUnitsHolding, 2, '.', ',');
						$bookedProfit		=	number_format($bookedProfit, 2, '.', ',');
						$xirrReturn			=	number_format((float)$xirrReturn, 2, '.', ',');
						$SoldSifInfo[] 	= 	array(	"Fund_House_Name"=>$fundHouseName, 
														"Fund_Name"=>$fundName, "Asset_Class"=>$assetClass, 
														"Unit_Holdings"=>$netUnitsHolding, 
														"PayoutRecvd"=>$payoutRecvd,											
														"RealizedProfit"=>$bookedProfit, 
														"XIRR"=>$xirrReturn,
														"cashflows"=>$cashFlow,
														"timeperiods"=>$timePeriod  );
						
						$fundCashFlowRegistry[] = [
								'investment_type' => 'SIF',
								'fund_name'       => $fundName,
								'amc_code'        => $amcCode,
								'fund_code'       => $fundNumber,
								'asset_class'     => $assetClass,
								'cashflows'       => $cashFlow,
								'timeperiods'     => $timePeriod,
								'status'          => 'SOLD',
							];
					}
				}
				$cashFlow					= array();
				$timePeriod					= array();
				$netFundHolding->reInitializeHolding();
				$bookedProfit				=	0;
			}
		}
		
		/*******************************
		// All funds trxn for the customer has been sorted. 
		//Now calcualte and populate the returns and total data.
		******************************************/
		
		$totalHoldingsMF 		= 	array();
		$totalHoldingsMF		=	$totalHoldingObj->getMFHoldingDataTotal();
	
		$allFundHoldingInfo 	= 	array();
		$allFundHoldingInfo		=	$totalHoldingObj->getHoldingDataTotal();
		
		// Goals Handling
		//Gather goal names associated with this customer
		$clientGoals		= 	$db->getAssignedGoals($userUCC);
		$clientGoalCount	=	count($clientGoals, COUNT_RECURSIVE);
		
		$goalsFundInfo		= array();
		if($clientGoalCount)
		{
			// Group data by goal_name
			$groupedGoals 		= [];
			foreach ($clientGoals as $row) 
			{
				$goalName 					= $row['goal_name'];
				$groupedGoals[$goalName][] 	= $row;
			}
			
			// Step 1: create goals fund info
			$goalsFundInfo = createGoalFundsTable($userUCC, $groupedGoals, $HoldingInfo, $HoldingInfoBonds, $HoldingInfoPms, $HoldingInfoAif);

			// Step 3: attach SIPs to the relevant MF holdings
			$goalsFundInfo = attachSIPInfoToGoals($goalsFundInfo, $runningSIPInfo);
			// Now $goalsFundInfo has MF + Bond + PMS + AIF + SIP info
		}
		
		$portfolioMcap	=	calculatePortfolioMcapAllocation($HoldingInfo);
		$result 		= 	calculateMFTopHoldings($HoldingInfo);

		$companyAgg     = $result['top_companies'];
		$sectorAgg      = $result['top_sectors'];
		$fundMetrics    = $result['fund_metrics'];
		$totalMFValue   = $result['total_mf_value'];
		
		// Add these two lines just before the return statement
		$totalCashFlows   = $totalHoldingObj->getTotalCashFlows();
		$totalTimePeriods = $totalHoldingObj->getTotalTimePeriods();
		
		// Merge all asset class registries into one
		$bondRegistry = $bondRegistry ?? [];
		$pmsRegistry  = $pmsRegistry  ?? [];
		$aifRegistry  = $aifRegistry  ?? [];
		
		$fundCashFlowRegistry = array_merge(
											$fundCashFlowRegistry,
											$bondRegistry,
											$pmsRegistry,
											$aifRegistry
											);

		$TotalHoldingInfoSif = method_exists($totalHoldingObj, 'getSifDataTotal') ? $totalHoldingObj->getSifDataTotal() : [];
		
		return array(	$HoldingInfo, $SoldFundInfo, 
						$totalHoldingsMF, $allFundHoldingInfo, 
						$HoldingInfoBonds, $TotalHoldingInfoBonds, $SoldBondInfo , 
						$HoldingInfoPms, $TotalHoldingInfoPms, $SoldPmsInfo, 
						$HoldingInfoAif, $TotalHoldingInfoAif, $SoldAifInfo, 
						$goalsFundInfo, 
						$totalCashFlows, 
						$totalTimePeriods,
						$fundCashFlowRegistry,
						$companyAgg,
						$sectorAgg,
						$fundMetrics,
						$totalMFValue,
						$portfolioMcap,
						$runningSIPInfo,
						$sipTransactions,
						$HoldingInfoSif,
						$TotalHoldingInfoSif,
						$SoldSifInfo
						);

	}	// function calculateHoldings() ends


	function calculateMFTopHoldings($HoldingInfoMF)
	{
		$db = new MYSQLDB();
		$pdo = $db->getPDO();

		$companyAgg = [];
		$sectorAgg  = [];
		$fundMetrics = [];

		$totalMFValue = 0;

		foreach ($HoldingInfoMF as $amc => $funds) {

			foreach ($funds as $fundWrapper) {

				foreach ($fundWrapper as $isin => $fund) {

					// =========================
					// CURRENT VALUE
					// =========================
					$currentValue = floatval(str_replace(',', '', $fund['Curr_Value'] ?? 0));

					if ($currentValue <= 0) continue;

					$totalMFValue += $currentValue;

					// =========================
					// FETCH YAHOO DATA
					// =========================
					$stmt = $pdo->prepare("
						SELECT top_holdings, sector_allocation, trailing_returns, risk_statistics
						FROM fund_yahoo_data
						WHERE isin = ?
						LIMIT 1
					");
					$stmt->execute([$isin]);
					$yahoo = $stmt->fetch(PDO::FETCH_ASSOC);

					if (!$yahoo) continue;

					$topHoldings = json_decode($yahoo['top_holdings'], true);
					$sectors     = json_decode($yahoo['sector_allocation'], true);
					$returns     = json_decode($yahoo['trailing_returns'], true);
					$risk        = json_decode($yahoo['risk_statistics'], true);

					// =========================
					// COMPANY AGGREGATION
					// =========================
					if (!empty($topHoldings)) {
						foreach ($topHoldings as $h) {

							$symbol  = $h['symbol'] ?? '';
							$name    = $h['name'] ?? '';
							$percent = floatval($h['percent'] ?? 0);

							if (!$symbol || $percent <= 0) continue;

							$amount = ($currentValue * $percent) / 100;

							if (!isset($companyAgg[$symbol])) {
								$companyAgg[$symbol] = [
									'symbol' => $symbol,
									'name'   => $name,
									'amount' => 0
								];
							}

							$companyAgg[$symbol]['amount'] += $amount;
						}
					}

					// =========================
					// SECTOR AGGREGATION
					// =========================
					if (!empty($sectors)) {
						foreach ($sectors as $sector => $percent) {

							$percent = floatval($percent);

							if (!$sector || $percent <= 0) continue;

							$amount = ($currentValue * $percent) / 100;

							if (!isset($sectorAgg[$sector])) {
								$sectorAgg[$sector] = [
									'sector' => $sector,
									'amount' => 0
								];
							}

							$sectorAgg[$sector]['amount'] += $amount;
						}
					}

					// =========================
					// FUND METRICS
					// =========================

					$returns = is_array($returns) ? $returns : [];
					$risk    = is_array($risk) ? $risk : [];
					
					$fundMetrics[$isin] = [
						'scheme_name' => $fund['Fund_Name'] ?? '',

						// ===== RETURNS =====
						'returns' => [
							'1y' => isset($returns['1Y']) ? floatval($returns['1Y']) : null,
							'3y' => isset($returns['3Y']) ? floatval($returns['3Y']) : null,
							'5y' => isset($returns['5Y']) ? floatval($returns['5Y']) : null
						],

						// ===== RISK =====
						'risk' => [
							'3y' => [
								'alpha'  => isset($risk['3Y']['alpha']) ? floatval($risk['3Y']['alpha']) : null,
								'beta'   => isset($risk['3Y']['beta']) ? floatval($risk['3Y']['beta']) : null,
								'stddev' => isset($risk['3Y']['stdDev']) ? floatval($risk['3Y']['stdDev']) : null
							],
							'5y' => [
								'alpha'  => isset($risk['5Y']['alpha']) ? floatval($risk['5Y']['alpha']) : null,
								'beta'   => isset($risk['5Y']['beta']) ? floatval($risk['5Y']['beta']) : null,
								'stddev' => isset($risk['5Y']['stdDev']) ? floatval($risk['5Y']['stdDev']) : null
							]
						]
					];

				}
			}
		}

		// =========================
		// SORT COMPANIES
		// =========================
		$companyAgg = array_values($companyAgg);
		usort($companyAgg, fn($a, $b) => $b['amount'] <=> $a['amount']);
		$topCompanies = array_slice($companyAgg, 0, 10);

		// =========================
		// SORT SECTORS
		// =========================
		$sectorAgg = array_values($sectorAgg);
		usort($sectorAgg, fn($a, $b) => $b['amount'] <=> $a['amount']);
		$topSectors = array_slice($sectorAgg, 0, 10);

		// =========================
		// ADD PERCENTAGE
		// =========================
		foreach ($topCompanies as &$c) {
			$c['percent'] = $totalMFValue > 0 
				? round(($c['amount'] / $totalMFValue) * 100, 2)
				: 0;
		}

		foreach ($topSectors as &$s) {
			$s['percent'] = $totalMFValue > 0 
				? round(($s['amount'] / $totalMFValue) * 100, 2)
				: 0;
		}

		// =========================
		// FINAL RETURN
		// =========================
		return [
			'top_companies' => $topCompanies,
			'top_sectors'   => $topSectors,
			'fund_metrics'  => $fundMetrics,
			'total_mf_value'=> $totalMFValue
		];
	}


	function calculatePortfolioMcapAllocation($HoldingInfoMF)
	{
		$db = new MYSQLDB();

		// Step 1: Initialize result structure
		$portfolioMcap = [
			'Large Cap' => 0,
			'Mid Cap'   => 0,
			'Small Cap' => 0,
			'Cash'      => 0,
			'Debt'      => 0,
			'Total'     => 0
		];

		// Step 2: Collect all ISINs (bulk)
		$allIsins = [];

		foreach ($HoldingInfoMF as $amc => $funds)
		{
			foreach ($funds as $fundWrapper)
			{
				foreach ($fundWrapper as $isin => $fund)
				{
					if (!empty($isin)) {
						$allIsins[] = $isin;
					}
				}
			}
		}

		$allIsins = array_values(array_unique(array_map('trim', $allIsins)));
		
		if (empty($allIsins)) {
			return $portfolioMcap;
		}

		// Step 3: Fetch latest mcap data in ONE query
		$placeholders = implode(',', array_fill(0, count($allIsins), '?'));

		$sql = "
			SELECT t.*
			FROM tb_funds_mcap_data t
			INNER JOIN (
				SELECT isin, MAX(month_year) AS max_date
				FROM tb_funds_mcap_data
				WHERE isin IN ($placeholders)
				GROUP BY isin
			) latest 
			ON t.isin = latest.isin 
			AND t.month_year = latest.max_date
		";

		$stmt = $db->getPDO()->prepare($sql);
		$stmt->execute($allIsins);
		
		// Map ISIN → mcap data
		$mcapDataMap = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$mcapDataMap[$row['isin']] = $row;
		}

		// Step 4: Loop again and calculate allocation
		foreach ($HoldingInfoMF as $amc => $funds)
		{
			foreach ($funds as $fundWrapper)
			{
				foreach ($fundWrapper as $isin => $fund)
				{
					//echo "<br> ISIN :  ".$isin;
					
					if (empty($isin)) continue;

					$fundValue = (float)str_replace(',', '', $fund['Curr_Value'] ?? 0);
					if ($fundValue <= 0) continue;

					$mcap = $mcapDataMap[$isin] ?? null;
					if (!$mcap) continue;

					$portfolioMcap['Large Cap'] += $fundValue * ($mcap['large_cap'] / 100);
					$portfolioMcap['Mid Cap']   += $fundValue * ($mcap['mid_cap'] / 100);
					$portfolioMcap['Small Cap'] += $fundValue * ($mcap['small_cap'] / 100);
					$portfolioMcap['Cash']      += $fundValue * ($mcap['cash'] / 100);
					$portfolioMcap['Debt']      += $fundValue * ($mcap['debt'] / 100);

					$portfolioMcap['Total'] += $fundValue;
				}
			}
		}

		// Step 5: Calculate percentage allocation
		if ($portfolioMcap['Total'] > 0)
		{
			foreach (['Large Cap','Mid Cap','Small Cap','Cash','Debt'] as $key)
			{
				$portfolioMcap[$key . '_pct'] = round(
					($portfolioMcap[$key] / $portfolioMcap['Total']) * 100,
					2
				);
			}
		}

	
	return $portfolioMcap;
	}

	/**
	 * calculateMcapHistory()
	 *
	 * Builds a monthly time-series of market-cap allocation percentages for
	 * the client's current MF holdings, covering up to 5 years (or since the
	 * earliest data available, whichever is shorter).
	 *
	 * APPROACH: Apply current fund market values to historical mcap percentages.
	 *   - Accurate enough for a trend chart: shows how the mcap CHARACTER
	 *     of the client's current funds has changed over time.
	 *   - Avoids needing per-fund historical NAV data.
	 *
	 * DATA EFFICIENCY: Two queries total regardless of portfolio size.
	 *   Query 1: all mcap rows for all client ISINs over 5 years (bulk).
	 *   Query 2: first-of-month Nifty 50 + Nifty 500 for the same range.
	 *
	 * @param  array $HoldingInfoMF  Same structure as passed to calculatePortfolioMcapAllocation
	 * @param  PDO   $pdo            PDO connection (passed in to avoid creating a new DB object)
	 * @return array {
	 *   labels         string[]   ['Jan 2020', 'Feb 2020', ...]
	 *   large_cap_pct  float[]    [45.2, 44.8, ...]
	 *   mid_cap_pct    float[]
	 *   small_cap_pct  float[]
	 *   other_pct      float[]    (Cash + Debt + unclassified)
	 *   nifty50        float[]    rebased to 100 at first month
	 *   nifty500       float[]    rebased to 100 at first month
	 *   months         int        count of months in series
	 * }
	 */
	function calculateMcapHistory(array $HoldingInfoMF, PDO $pdo): array
	{
		$empty = [
			'labels'        => [],
			'large_cap_pct' => [],
			'mid_cap_pct'   => [],
			'small_cap_pct' => [],
			'other_pct'     => [],
			'nifty50'       => [],
			'nifty500'      => [],
			'months'        => 0,
		];

		// ── Step 1: Collect unique ISINs from current holdings ────────────────
		// Supports two input structures:
		//   A) portfolioInfo: [amc => [fund_row...]] where fund_row has 'ISIN','Curr_Value'
		//   B) HoldingInfoMF: [amc => [fundWrapper => [isin => fund_data]]] (nested 3 levels)
		$fundValues = [];   // [isin => current_value_float]

		foreach ($HoldingInfoMF as $amc => $funds) {
			foreach ($funds as $keyOrWrapper => $fundOrFunds) {
				// Check if this is structure A: fund_row with 'ISIN' key
				if (is_array($fundOrFunds) && isset($fundOrFunds['ISIN'])) {
					$asset = strtoupper(trim($fundOrFunds['Asset_Class'] ?? $fundOrFunds['asset_class'] ?? ''));
					if (!empty($asset) && $asset !== 'EQUITY') continue;   // Skip non-equity (e.g. Gold, Debt)
					$isin = trim($fundOrFunds['ISIN'] ?? '');
					$val  = (float)str_replace(',', '', $fundOrFunds['Curr_Value'] ?? 0);
					if (!empty($isin) && $val > 0) {
						$fundValues[$isin] = ($fundValues[$isin] ?? 0) + $val;
					}
				} else {
					// Structure B: nested fundWrapper → [isin => fund_data]
					foreach ((array)$fundOrFunds as $isin => $fund) {
						if (!is_array($fund)) continue;
						$asset = strtoupper(trim($fund['Asset_Class'] ?? $fund['asset_class'] ?? ''));
						if (!empty($asset) && $asset !== 'EQUITY') continue;   // Skip non-equity (e.g. Gold, Debt)
						// Try capital ISIN key first, then isin key
						$isin = trim($fund['ISIN'] ?? $fund['isin'] ?? $isin ?? '');
						if (is_numeric($isin)) continue;   // skip numeric wrapper keys
						$val = (float)str_replace(',', '', $fund['Curr_Value'] ?? 0);
						if (!empty($isin) && $val > 0) {
							$fundValues[$isin] = ($fundValues[$isin] ?? 0) + $val;
						}
					}
				}
			}
		}

		$allIsins = array_keys($fundValues);   // unique ISINs with positive value
		if (empty($allIsins)) return $empty;

		$totalMFValue = array_sum($fundValues);
		if ($totalMFValue <= 0) return $empty;

		// ── Step 2: Date range — 5 years back or earliest available ──────────
		$today       = date('Y-m-d');
		$fiveYrsAgo  = date('Y-m-d', strtotime('-5 years'));

		// ── Step 3: Bulk fetch all mcap rows for all ISINs over the range ─────
		// One query, returns every month for every ISIN in the range.
		// month_year may be stored as DATE (YYYY-MM-01) or VARCHAR (YYYY-MM).
		// We normalise to YYYY-MM in PHP.
		$placeholders = implode(',', array_fill(0, count($allIsins), '?'));

		$mcapSql = "
			SELECT isin,
			       DATE_FORMAT(month_year, '%Y-%m') AS ym,
			       large_cap, mid_cap, small_cap, cash, debt
			FROM   tb_funds_mcap_data
			WHERE  isin IN ({$placeholders})
			  AND  month_year >= ?
			ORDER  BY month_year ASC
		";

		$params = array_values($allIsins);
		$params[] = $fiveYrsAgo;

		$stmt = $pdo->prepare($mcapSql);
		$stmt->execute($params);

		// Index: [ym][isin] → mcap row
		$mcapByMonth = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$mcapByMonth[$row['ym']][$row['isin']] = $row;
		}

		if (empty($mcapByMonth)) return $empty;

		// ── Step 4: Determine months available (months where ≥50% of total
		// MF value has mcap data — guards against sparse early months) ────────
		ksort($mcapByMonth);   // chronological
		$validMonths = [];
		foreach ($mcapByMonth as $ym => $isins) {
			$covered = 0.0;
			foreach ($isins as $isin => $row) {
				$covered += $fundValues[$isin] ?? 0;
			}
			if ($covered >= $totalMFValue * 0.25) {   // at least 25% covered
				$validMonths[] = $ym;
			}
		}

		if (empty($validMonths)) return $empty;

		// ── Step 5: Bulk fetch Nifty 50 + Nifty 500 — first trading day each month
		$firstYm    = reset($validMonths);
		$lastYm     = end($validMonths);
		$niftyFrom  = $firstYm . '-01';
		$niftyTo    = date('Y-m-t', strtotime($lastYm . '-01'));

		// Get first available trading day in each calendar month
		$niftySql = "
			SELECT DATE_FORMAT(trade_date, '%Y-%m') AS ym,
			       MIN(trade_date) AS first_day,
			       (SELECT n2.nifty50
			         FROM   nifty_historical_data n2
			         WHERE  DATE_FORMAT(n2.trade_date, '%Y-%m') = DATE_FORMAT(n1.trade_date, '%Y-%m')
			         ORDER  BY n2.trade_date ASC LIMIT 1) AS nifty50,
			       (SELECT n3.nifty500
			         FROM   nifty_historical_data n3
			         WHERE  DATE_FORMAT(n3.trade_date, '%Y-%m') = DATE_FORMAT(n1.trade_date, '%Y-%m')
			         ORDER  BY n3.trade_date ASC LIMIT 1) AS nifty500
			FROM   nifty_historical_data n1
			WHERE  trade_date BETWEEN :from AND :to
			  AND  (nifty50 > 0 OR nifty500 > 0)
			GROUP  BY DATE_FORMAT(trade_date, '%Y-%m')
			ORDER  BY ym ASC
		";

		$niftyStmt = $pdo->prepare($niftySql);
		$niftyStmt->execute([':from' => $niftyFrom, ':to' => $niftyTo]);

		$niftyByMonth = [];
		while ($nr = $niftyStmt->fetch(PDO::FETCH_ASSOC)) {
			$niftyByMonth[$nr['ym']] = [
				'nifty50'  => (float)$nr['nifty50'],
				'nifty500' => (float)$nr['nifty500'],
			];
		}

		// ── Step 6: Build the time series ────────────────────────────────────
		$labels          = [];
		$largePct        = [];
		$midPct          = [];
		$smallPct        = [];
		$otherPct        = [];
		$nifty50Arr      = [];
		$nifty500Arr     = [];
		$nifty50RawArr   = [];
		$nifty500RawArr  = [];

		// Rebase Nifty to 100 at the first valid month
		$nifty50Base  = null;
		$nifty500Base = null;

		foreach ($validMonths as $ym) {
			$isins = $mcapByMonth[$ym] ?? [];

			// Weighted mcap allocation for this month
			$wLarge = 0.0; $wMid = 0.0; $wSmall = 0.0; $wOther = 0.0;
			$wTotal = 0.0;

			foreach ($fundValues as $isin => $fval) {
				$row = $isins[$isin] ?? null;
				if (!$row) {
					// ISIN has no data for this month — treat as unknown, count in other
					continue;
				}
				$wLarge += $fval * ((float)$row['large_cap'] / 100);
				$wMid   += $fval * ((float)$row['mid_cap']   / 100);
				$wSmall += $fval * ((float)$row['small_cap'] / 100);
				$cash    = (float)($row['cash'] ?? 0);
				$debt    = (float)($row['debt'] ?? 0);
				$wOther += $fval * (($cash + $debt) / 100);
				$wTotal += $fval;
			}

			if ($wTotal < $totalMFValue * 0.25) continue;   // skip sparse months (< 25%)

			// Express as % of the covered value (normalised to 100%)
			$scale = $wTotal > 0 ? (100.0 / $wTotal) : 1.0;
			$lPct  = round($wLarge * $scale, 1);
			$mPct  = round($wMid   * $scale, 1);
			$sPct  = round($wSmall * $scale, 1);
			$oPct  = round(max(0, 100 - $lPct - $mPct - $sPct), 1);  // remainder

			// Month label: "Jan 2020"
			$dt     = DateTime::createFromFormat('!Y-m', $ym);
			$labels[]    = $dt ? $dt->format('M Y') : $ym;
			$largePct[]  = $lPct;
			$midPct[]    = $mPct;
			$smallPct[]  = $sPct;
			$otherPct[]  = $oPct;

			// Nifty rebased to 100
			$nd = $niftyByMonth[$ym] ?? null;
			if ($nd) {
				if ($nifty50Base  === null) $nifty50Base  = $nd['nifty50'];
				if ($nifty500Base === null) $nifty500Base = $nd['nifty500'];
				$nifty50Arr[]      = $nifty50Base  > 0 ? round($nd['nifty50']  / $nifty50Base  * 100, 2) : null;
				$nifty500Arr[]     = $nifty500Base > 0 ? round($nd['nifty500'] / $nifty500Base * 100, 2) : null;
				$nifty50RawArr[]   = (float)$nd['nifty50'];
				$nifty500RawArr[]  = (float)$nd['nifty500'];
			} else {
				$nifty50Arr[]      = null;
				$nifty500Arr[]     = null;
				$nifty50RawArr[]   = null;
				$nifty500RawArr[]  = null;
			}
		}

        // --- Down-sample if > 120 points to prevent QuickChart API limits ---
        $count = count($labels);
        if ($count > 120) {
            $step = (int)ceil($count / 120);
            $sampled = [
                'labels' => [], 'large_cap_pct' => [], 'mid_cap_pct' => [], 
                'small_cap_pct' => [], 'other_pct' => [], 'nifty50' => [], 
                'nifty500' => [], 'nifty50_raw' => [], 'nifty500_raw' => []
            ];
            
            for ($i = 0; $i < $count; $i += $step) {
                $sampled['labels'][] = $labels[$i];
                $sampled['large_cap_pct'][] = $largePct[$i];
                $sampled['mid_cap_pct'][] = $midPct[$i];
                $sampled['small_cap_pct'][] = $smallPct[$i];
                $sampled['other_pct'][] = $otherPct[$i];
                $sampled['nifty50'][] = $nifty50Arr[$i];
                $sampled['nifty500'][] = $nifty500Arr[$i];
                $sampled['nifty50_raw'][] = $nifty50RawArr[$i];
                $sampled['nifty500_raw'][] = $nifty500RawArr[$i];
            }
            // Ensure the very last data point is always included
            $lastIdx = $count - 1;
            if ($lastIdx % $step !== 0) {
                $sampled['labels'][] = $labels[$lastIdx];
                $sampled['large_cap_pct'][] = $largePct[$lastIdx];
                $sampled['mid_cap_pct'][] = $midPct[$lastIdx];
                $sampled['small_cap_pct'][] = $smallPct[$lastIdx];
                $sampled['other_pct'][] = $otherPct[$lastIdx];
                $sampled['nifty50'][] = $nifty50Arr[$lastIdx];
                $sampled['nifty500'][] = $nifty500Arr[$lastIdx];
                $sampled['nifty50_raw'][] = $nifty50RawArr[$lastIdx];
                $sampled['nifty500_raw'][] = $nifty500RawArr[$lastIdx];
            }
            
            $labels = $sampled['labels'];
            $largePct = $sampled['large_cap_pct'];
            $midPct = $sampled['mid_cap_pct'];
            $smallPct = $sampled['small_cap_pct'];
            $otherPct = $sampled['other_pct'];
            $nifty50Arr = $sampled['nifty50'];
            $nifty500Arr = $sampled['nifty500'];
            $nifty50RawArr = $sampled['nifty50_raw'];
            $nifty500RawArr = $sampled['nifty500_raw'];
        }

		return [
			'labels'           => $labels,
			'large_cap_pct'    => $largePct,
			'mid_cap_pct'      => $midPct,
			'small_cap_pct'    => $smallPct,
			'other_pct'        => $otherPct,
			'nifty50'          => $nifty50Arr,
			'nifty500'         => $nifty500Arr,
			'nifty50_raw'      => $nifty50RawArr,
			'nifty500_raw'     => $nifty500RawArr,
			'months'           => count($labels),
		];
	}

	/* Sept 15th working without SIP  */

	function createGoalFundsTable($userUCC, $groupedGoals, $portfolioInfo, $HoldingInfoBonds, $HoldingInfoPms, $HoldingInfoAif) 
	{
		$allHoldings = [];
		
		// --- Mutual Funds ---
		foreach ($groupedGoals as $goalName => $goalRows) {
			foreach ($goalRows as $goalRow) {
				if ($goalRow['investment_type'] === 'MF') {
					$normalizedList = normalizeMF($goalRow, $portfolioInfo);
					foreach ($normalizedList as $normalized) {
						$key = $normalized['investment_type'] . '|' . $normalized['fund_code'];
						$allHoldings[$key] = $normalized;
					}
				}
			}
		}

		// --- Bonds ---
		foreach (normalizeBond($HoldingInfoBonds) as $normalized) {
			$keyCode = $normalized['investment_type'] . '|' . $normalized['fund_code'];
			$keyName = $normalized['investment_type'] . '|' . $normalized['fund_name'];
			$allHoldings[$keyCode] = $normalized;
			$allHoldings[$keyName] = $normalized;
		}

		// --- PMS ---
		foreach (normalizePMS($HoldingInfoPms) as $normalized) {
			$keyCode = $normalized['investment_type'] . '|' . $normalized['fund_code'];
			$keyName = $normalized['investment_type'] . '|' . $normalized['fund_name'];
			$allHoldings[$keyCode] = $normalized;
			$allHoldings[$keyName] = $normalized;
		}

		// --- AIF ---
		foreach (normalizeAIF($HoldingInfoAif) as $normalized) {
			$keyCode = $normalized['investment_type'] . '|' . $normalized['fund_code'];
			$keyName = $normalized['investment_type'] . '|' . $normalized['fund_name'];
			$allHoldings[$keyCode] = $normalized;
			$allHoldings[$keyName] = $normalized;
		}

		// --- Match holdings with goals ---
		$goalsFundInfo = [];

		foreach ($groupedGoals as $goalName => $goalRows) {
			foreach ($goalRows as $goalRow) {
				$key = $goalRow['investment_type'] . '|' . ($goalRow['fund_code'] ?? $goalRow['fund_name']);

				if (isset($allHoldings[$key])) {
					$fundRow = array_merge($goalRow, $allHoldings[$key]);

					// Include goal info in each fund row
					$fundRow['goal_amount'] = $goalRow['amount_required'] ?? 0;
					$fundRow['goal_date']   = $goalRow['goal_date'] ?? '';

					$goalsFundInfo[$goalName][] = $fundRow;
				} else {
					$empty = [
						'cost_value'     => 0,
						'current_value'  => 0,
						'profit_loss'    => 0,
						'xirr'           => '',
						'goal_amount'    => $goalRow['amount_required'] ?? 0,
						'goal_date'      => $goalRow['goal_date'] ?? '',
						'sip_info'       => []
					];
					$goalsFundInfo[$goalName][] = array_merge($goalRow, $empty);
				}
			}
		}
		return $goalsFundInfo;
	}


	function attachSIPInfoToGoals(array $goalsFundInfo, array $runningSIPInfo): array
	{
		// If no SIPs, initialize empty SIP_Info for every fund and return
		if (empty($runningSIPInfo)) {
			foreach ($goalsFundInfo as &$goalFunds) {
				foreach ($goalFunds as &$fund) {
					if (!isset($fund['SIP_Info'])) {
						$fund['SIP_Info'] = [];
					}
				}
			}
			return $goalsFundInfo;
		}


		// If SIPs exist, try to match and attach them
		foreach ($goalsFundInfo as &$goalFunds) 
		{
			foreach ($goalFunds as &$fund) 
			{
				$fund['SIP_Info'] = []; // default
				
				foreach ($runningSIPInfo as $sip) 
				{
					// Match by AMC + Product Code (your existing matching logic)
					if ( $fund['isin'] === $sip['ISIN_Code'] ) 
					{
						$fund['SIP_Info'][] = $sip;
					}
				}
			}
		}

		return $goalsFundInfo;
	}


	function normalizeMF($goalFund, $portfolioInfo) {
		$amc      = $goalFund['amc_code'] ?? '';
		$fundCode = $goalFund['fund_code'] ?? '';
		$isin     = $goalFund['isin'] ?? $fundCode;
		
		$fund = [
			'investment_type' => 'MF',
			'asset_class'     => $goalFund['asset_class'] ?? 'EQUITY',
			'amc_code'        => $amc,
			'fund_code'       => $fundCode,
			'isin'            => $isin,
			'fund_name'       => $goalFund['fund_name'] ?? '',
			'goal_amount'     => $goalFund['amount_required'] ?? 0,
			'goal_date'       => $goalFund['goal_date'] ?? '',
			'sip_info'        => []
		];

		// default values
		$fund['units']           = 0;
		$fund['cost_nav']        = 0;
		$fund['cost_value']      = 0;
		$fund['current_nav']     = 0;
		$fund['current_value']   = 0;
		$fund['realized_profit'] = 0;
		$fund['unrealized_profit']= 0;
		$fund['payout_received'] = 0;
		$fund['profit_loss']     = 0;
		$fund['xirr']            = '';

		// now extract real MF data if available
		if (!empty($portfolioInfo[$amc])) {
			foreach ($portfolioInfo[$amc] as $entry) {
				
				foreach ($entry as $entryKey => $mfData) {
					$matchesByKey = !empty($isin) && $entryKey === $isin;
					$matchesByName = !empty($goalFund['fund_name']) && !empty($mfData['Fund_Name'])
					    && strcasecmp(trim($mfData['Fund_Name']), trim($goalFund['fund_name'])) === 0;
					
					if ($matchesByKey || $matchesByName) {
						$units         = (float)str_replace(',', '', $mfData['Unit_Holdings'] ?? 0);
						$costNav       = (float)($mfData['Cost_NAV'] ?? 0);
						$costValue     = (float)str_replace(',', '', $mfData['Cost_Value'] ?? 0);
						$currentNav    = (float)($mfData['Current_NAV'] ?? 0);
						$currentValue  = (float)str_replace(',', '', $mfData['Curr_Value'] ?? 0);
						$realized      = (float)($mfData['RealizedProfit'] ?? 0);
						$unrealized    = (float)($mfData['UnRealizedProfit'] ?? 0);
						$payout        = (float)($mfData['PayoutRecvd'] ?? 0);
						$xirr          = $mfData['XIRR'] ?? '';

						$fund['units']           = $units;
						$fund['cost_nav']        = $costNav;
						$fund['cost_value']      = $costValue;
						$fund['current_nav']     = $currentNav;
						$fund['current_value']   = $currentValue;
						$fund['realized_profit'] = $realized;
						$fund['unrealized_profit']= $unrealized;
						$fund['payout_received'] = $payout;
						$fund['profit_loss']     = $currentValue - $costValue;
						$fund['xirr']            = $xirr;
						$fund['isin']            = $entryKey;

						break 2; // stop after first matching fund
					}
				}
			}
		}
		return [$fund];
	}


	function normalizeBond($bondDataArray) {
		$normalizedList = [];
				
		foreach ($bondDataArray as $bondGroup) {              // e.g. "SGB-Bonds"
			foreach ($bondGroup as $bondEntry) {              // each [0], [1], [2]…
				foreach ($bondEntry as $fundCode => $row) {   // e.g. "2021-22, Series I"
					$normalizedList[] = [
						'investment_type'   => 'Bond',
						'fund_code'         => $fundCode,
						'fund_name'         => $row['bondName'] ?? '',
						'amc_code'          => 'SGB-Bonds',
						'asset_class'       => $row['assetClass'] ?? null,
						'units'             => (float) str_replace(',', '', $row['unitHoldings'] ?? 0),
						'cost_nav'          => (float) str_replace(',', '', $row['costNAV'] ?? 0),
						'cost_value'        => (float) str_replace(',', '', $row['costValue'] ?? 0),
						'current_nav'       => (float) str_replace(',', '', $row['currentNAV'] ?? 0),
						'current_value'     => (float) str_replace(',', '', $row['currValue'] ?? 0),
						'realized_profit'   => (float) str_replace(',', '', $row['realizedProfit'] ?? 0),
						'unrealized_profit' => (float) str_replace(',', '', $row['unRealizedProfit'] ?? 0),
						'payout_received'   => (float) str_replace(',', '', $row['payoutRecvd'] ?? 0),
						'xirr'              => isset($row['XIRR']) ? (float) str_replace(',', '', $row['XIRR']) : null,
					];
				}
			}
		}

		return $normalizedList;
	}

	function normalizePMS($pmsDataArray) {
		$normalizedList = [];

		foreach ($pmsDataArray as $providerName => $fundGroup) {
			foreach ($fundGroup as $fundEntry) {
				foreach ($fundEntry as $fundCode => $row) {
					$normalizedList[] = [
						'investment_type'   => 'PMS',
						'fund_code'         => $fundCode,
						'fund_name'         => $row['pmsName'] ?? $fundCode,
						'amc_code'          => $providerName,
						'asset_class'       => $row['assetClass'] ?? null,
						'units'             => null, // PMS typically doesn’t give units
						'cost_nav'          => null,
						'cost_value'        => (float) str_replace(',', '', $row['totalPurchaseCost'] ?? 0),
						'current_nav'       => null,
						'current_value'     => (float) str_replace(',', '', $row['currValue'] ?? 0),
						'realized_profit'   => (float) str_replace(',', '', $row['totalSellValue'] ?? 0),
						'unrealized_profit' => null,
						'payout_received'   => null,
						'xirr'              => isset($row['XIRR']) ? (float) str_replace(',', '', $row['XIRR']) : null,
					];
				}
			}
		}

		return $normalizedList;
	}

	function normalizeAIF($aifDataArray) {
		$normalizedList = [];

		foreach ($aifDataArray as $providerName => $fundGroup) {
			foreach ($fundGroup as $fundEntry) {
				foreach ($fundEntry as $fundCode => $row) {
					// skip numeric keys like [0] => 'AMCName'
					if (is_numeric($fundCode)) continue;

					$normalizedList[] = [
						'investment_type'   => 'AIF',
						'fund_code'         => $fundCode,
						'fund_name'         => $row['aifName'] ?? $fundCode,
						'amc_code'          => $providerName,
						'asset_class'       => $row['assetClass'] ?? null,
						'units'             => (float) str_replace(',', '', $row['units'] ?? 0),
						'cost_nav'          => (float) str_replace(',', '', $row['costNAV'] ?? 0),
						'cost_value'        => (float) str_replace(',', '', $row['costValue'] ?? 0),
						'current_nav'       => (float) str_replace(',', '', $row['currNAV'] ?? 0),
						'current_value'     => (float) str_replace(',', '', $row['currValue'] ?? 0),
						'realized_profit'   => (float) str_replace(',', '', $row['realizedProfit'] ?? 0),
						'unrealized_profit' => (float) str_replace(',', '', $row['unRealizedProfit'] ?? 0),
						'payout_received'   => (float) str_replace(',', '', $row['payoutRecvd'] ?? 0),
						'xirr'              => isset($row['XIRR']) ? (float) str_replace(',', '', $row['XIRR']) : null,
					];
				}
			}
		}

		return $normalizedList;
	}
	
	function isSipTransaction(string $transactionType): bool
	{
		$transactionType = strtoupper(trim($transactionType));

		/*
		|--------------------------------------------------------------------------
		| 1. Explicit SIP markers
		|--------------------------------------------------------------------------
		*/
		if (
			$transactionType === 'SIN' ||
			strpos($transactionType, 'SIP') !== false
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| 2. Explicit known SIP transaction types
		|--------------------------------------------------------------------------
		*/
		/*
		$knownSipTypes = [
			'P1FCS',
			'P43AS',
			'PORI46L',
			'P1PFS',
			'P50AS',
			'P55AS'
		];
		
		if (in_array($transactionType, $knownSipTypes, true)) {
			return true;
		}
		*/
		/*
		|--------------------------------------------------------------------------
		| 3. Pattern-based SIP detection
		|--------------------------------------------------------------------------
		|
		| Examples:
		| P43AS
		| P55AS
		| P1QFS
		| P1MAFS
		| P2LTVFS
		|
		| Strong signal:
		| - Starts with P
		| - Ends with S
		|--------------------------------------------------------------------------
		*/
		if (
			preg_match('/^P[A-Z0-9]+[SL]$/', $transactionType)
			
		) {
			return true;
		}

		/*
		|--------------------------------------------------------------------------
		| 4. Otherwise not SIP
		|--------------------------------------------------------------------------
		*/
		return false;
	}

	function normalizeSystematicPlanType(array $plan): string
	{
		return strtoupper(trim($plan['registration_type'] ?? $plan['Sip_Type'] ?? 'SIP'));
	}

	function isOpenEndedPlanDate($date): bool
	{
		return empty($date) || $date === '0000-00-00' || $date === '2099-12-31' || $date === '2999-12-31';
	}

	function planAmountForMatching(array $plan): float
	{
		$type = normalizeSystematicPlanType($plan);
		if ($type === 'SWP') {
			return (float)str_replace(',', '', $plan['withdrawl_amount'] ?? $plan['Amount'] ?? 0);
		}
		if ($type === 'STP') {
			return (float)str_replace(',', '', $plan['transfer_amount'] ?? $plan['Amount'] ?? 0);
		}
		return (float)str_replace(',', '', $plan['Amount'] ?? $plan['installments_amount'] ?? 0);
	}

	function planStartDateForMatching(array $plan): string
	{
		$type = normalizeSystematicPlanType($plan);
		if ($type === 'SWP') {
			return $plan['swp_start_date'] ?? $plan['From_Date'] ?? $plan['start_date'] ?? '';
		}
		if ($type === 'STP') {
			return $plan['stp_start_date'] ?? $plan['From_Date'] ?? $plan['start_date'] ?? '';
		}
		return $plan['From_Date'] ?? $plan['start_date'] ?? '';
	}

	function planEndDateForMatching(array $plan): string
	{
		$type = normalizeSystematicPlanType($plan);
		if ($type === 'SWP') {
			return $plan['swp_end_date'] ?? $plan['To_Date'] ?? $plan['end_date'] ?? '';
		}
		if ($type === 'STP') {
			return $plan['stp_end_date'] ?? $plan['To_Date'] ?? $plan['end_date'] ?? '';
		}
		return $plan['To_Date'] ?? $plan['end_date'] ?? '';
	}

	function planExpectsTransactionSign(array $plan, string $transactionSign): bool
	{
		$type = normalizeSystematicPlanType($plan);
		$transactionSign = strtoupper(trim($transactionSign));

		if ($type === 'SIP') {
			return $transactionSign === 'P';
		}
		if ($type === 'SWP') {
			return $transactionSign === 'R';
		}

		return $transactionSign === 'P' || $transactionSign === 'R';
	}

	function buildSystematicPlanIndex(array $runningPlans): array
	{
		$index = [];
		foreach ($runningPlans as $plan) {
			$isin = $plan['ISIN_Code'] ?? ($plan['isin'] ?? '');
			if (empty($isin)) {
				continue;
			}
			$amount = planAmountForMatching($plan);
			if ($amount <= 0) {
				continue;
			}
			$plan['__match_amount'] = $amount;
			$plan['__match_type'] = normalizeSystematicPlanType($plan);
			$plan['__match_start'] = planStartDateForMatching($plan);
			$plan['__match_end'] = planEndDateForMatching($plan);
			$plan['__match_key'] = implode('|', [
				$plan['__match_type'],
				$isin,
				number_format($amount, 2, '.', ''),
				$plan['__match_start']
			]);
			$index[$isin][] = $plan;
		}
		return $index;
	}

	function transactionMatchesPlanDate(array $plan, string $transactionDate): bool
	{
		$txnTs = !empty($transactionDate) ? strtotime($transactionDate) : false;
		if ($txnTs === false) {
			return false;
		}

		$start = $plan['__match_start'] ?? planStartDateForMatching($plan);
		if (!empty($start) && ($startTs = strtotime($start)) !== false && $txnTs < strtotime('-5 days', $startTs)) {
			return false;
		}

		$end = $plan['__match_end'] ?? planEndDateForMatching($plan);
		if (!isOpenEndedPlanDate($end) && ($endTs = strtotime($end)) !== false && $txnTs > strtotime('+5 days', $endTs)) {
			return false;
		}

		$sipDay = null;
		if (!empty($plan['instdate'])) {
			$sipDay = is_numeric($plan['instdate']) ? (int)$plan['instdate'] : (int)date('d', strtotime($plan['instdate']));
		} elseif (!empty($start) && strtotime($start) !== false) {
			$sipDay = (int)date('d', strtotime($start));
		}

		if ($sipDay === null) {
			return true;
		}

		$txnMonth = (int)date('m', $txnTs);
		$txnYear = (int)date('Y', $txnTs);
		$targetDateCurrent = mktime(0, 0, 0, $txnMonth, $sipDay, $txnYear);
		$targetDatePrev = mktime(0, 0, 0, $txnMonth - 1, $sipDay, $txnYear);
		$targetDateNext = mktime(0, 0, 0, $txnMonth + 1, $sipDay, $txnYear);

		$diffCurrent = abs($txnTs - $targetDateCurrent) / 86400;
		$diffPrev = abs($txnTs - $targetDatePrev) / 86400;
		$diffNext = abs($txnTs - $targetDateNext) / 86400;

		return min($diffCurrent, $diffPrev, $diffNext) <= 3;
	}

	function matchSystematicPlansForTransaction(array $txn, array $planIndex, array &$matchedTxnKeys, array &$matchedPlanMonths): array
	{
		$txnIsin = $txn['isinCode'] ?? '';
		if (empty($txnIsin) || empty($planIndex[$txnIsin])) {
			return [];
		}

		$matches = [];
		$txnAmount = abs((float)($txn['transactionAmount'] ?? 0));
		$txnDate = $txn['transactionDate'] ?? '';
		$txnMonthYear = !empty($txnDate) && strtotime($txnDate) !== false ? date('Y-m', strtotime($txnDate)) : '';

		foreach ($planIndex[$txnIsin] as $plan) {
			if (!planExpectsTransactionSign($plan, $txn['transactionSign'] ?? '')) {
				continue;
			}

			$planAmount = $plan['__match_amount'] ?? planAmountForMatching($plan);
			if (abs($txnAmount - $planAmount) > 10.0) {
				continue;
			}

			if (!transactionMatchesPlanDate($plan, $txnDate)) {
				continue;
			}

			$planType = $plan['__match_type'] ?? normalizeSystematicPlanType($plan);
			$isMonthly = strpos(strtoupper(trim($plan['Frequency'] ?? '')), 'MONTH') !== false || strtoupper(trim($plan['Frequency'] ?? '')) === 'M';
			$planKey = $plan['__match_key'] ?? ($planType . '|' . $txnIsin . '|' . $planAmount);
			if ($isMonthly && $txnMonthYear !== '') {
				$monthKey = $planKey . '|' . $txnMonthYear;
				if (isset($matchedPlanMonths[$monthKey])) {
					continue;
				}
			}

			$txnKey = $planKey . '|' . $txnIsin . '|' . $txnDate . '|' . $txnAmount . '|' . ($txn['unitsTransacted'] ?? 0) . '|' . ($txn['transactionSign'] ?? '');
			if (isset($matchedTxnKeys[$txnKey])) {
				continue;
			}

			$matchedTxnKeys[$txnKey] = true;
			if ($isMonthly && $txnMonthYear !== '') {
				$matchedPlanMonths[$planKey . '|' . $txnMonthYear] = true;
			}

			$matches[] = [
				'planType'          => $planType,
				'registration_type' => $planType,
				'fundHouseName'     => $txn['fundHouseName'] ?? '',
				'isinCode'          => $txnIsin,
				'fundName'          => $txn['fundName'] ?? '',
				'unitsTransacted'   => $txn['unitsTransacted'] ?? 0,
				'transactionSign'   => $txn['transactionSign'] ?? '',
				'transactionAmount' => $txn['transactionAmount'] ?? 0,
				'transactionDate'   => $txnDate
			];
		}

		return $matches;
	}
	
	/**
	 * Collects SIP transactions from the raw portfolio data based on active SIP details.
	 *
	 * @param array $runningSIP   Array containing the user's active SIPs.
	 * @param array $rawportfolio Array of all raw transactions grouped by AMC.
	 * @return array The matched SIP transactions.
	 */
	 if (!function_exists('collectSipTransactions')) {
	function collectSipTransactions($runningSIP, $rawportfolio) {
		$sipTransactions = [];
		$matchedTxnKeys = []; // To prevent duplicating the same transaction across overlapping SIPs

		if (empty($runningSIP) || empty($rawportfolio)) {
			return $sipTransactions;
		}

		$planIndex = buildSystematicPlanIndex($runningSIP);
		$matchedMonths = [];
		foreach ($rawportfolio as $transactions) {
			foreach ($transactions as $txn) {
				$matches = matchSystematicPlansForTransaction($txn, $planIndex, $matchedTxnKeys, $matchedMonths);
				if (!empty($matches)) {
					$sipTransactions = array_merge($sipTransactions, $matches);
				}
			}
		}

		return $sipTransactions;
	}
   }
	
?>
