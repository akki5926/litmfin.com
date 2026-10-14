<?php
require_once('auth_guard.php');

	require_once('database.php');

	$userId 			= 0;
	$db 		 	= new MYSQLDB();
	$result 		= $db->getMergedTransactionDetails('' , '' , '' , '');   
	
	//$profiledata	= $db->getProfileData($userIIN);  	
		
	$portfolioFunds[] 		= 	array();
	$portfolioAmcs[] 		= 	array();
	$wrongTrxnTypes[] 		= 	array();
	$wrongAssetTypes[] 		= 	array();
	$navDataMissing[] 		= 	array();
	$wrongTrxnProcessDate	=	array();
	$wrongTrxnUnitsAmt		=	array();
	
	//============================================================+
	// Functions for Diagnostic Purpose for MFD Admin
	// #1. Verify the asset class for all the funds present in mailbox or/and in database.
	// #2. Verify nav data is present for all the funds present in mailbox or/and in database.
	// #3. Verify transaction type for all trxn data present in mailbox or/and in database.
	// #3. Verify nav data is present for all the funds present in mailbox or/and in database.
	// #3. Verify nav data is present for all the funds present in mailbox or/and in database.
	
	//============================================================+
	foreach($result as $row)	
	{
		
		global	$portfolioFunds;
		global	$portfolioAmcs;
		global	$wrongTrxnTypes;
		global	$wrongAssetTypes;
		
		$unitsTransacted 	= 	$row['mb_units'];
		$transactionAmount 	= 	$row['mb_amount'];
		//$transactionNAV 	= 	$row['price'];
		//$transactionDate 	= 	$row['trxn_date'];
		$transactionDate 	= 	$row['process_date'];
		//$transactionNAV 	=   $row['price'];
		$currentNAV			=	0;
		
		$amcCodeName 		= 	$row['amc_name'];
		$tempCode 			= 	explode("/",$amcCodeName);
		$amcCode			= 	$tempCode[0];
		$fundHouseName 		= 	$tempCode[1];
		
		//$portfolioAmcs[]	=	$amcCode;
		$portfolioAmcs[]	=	array	('AMC_Code'=> $amcCode,
										 'AMC_Name'=> $amcCode);
		
		$fundCodeName 		= 	$row['scheme_name'];
		$tempCode 			= 	explode("/",$fundCodeName);
		$fundCode			= 	$tempCode[0];
		$fundName 			= 	$tempCode[1];
	
		//$portfolioFunds[] 	= $fundCode;
		$portfolioFunds[]	=	array	('Fund_Code'=> $fundCode,
										 'Fund_Name'=> $fundName);
		/*
		echo "<br>";
		echo "Fund Code:  ". $fundCode;
		echo "  Fund Name:  ". $fundName;
		*/
		/*
		$transactionType	= 	$row['trxn_head']; // P, R, N
		if( strtolower($transactionType) != 	strtolower("P") && strtolower($transactionType) != 	strtolower("R"))
		{
			// #Error: This is invalid txn type. 
			//$wrongTrxnTypes[]	=	$transactionType;
			$wrongTrxnTypes[]	=	array	(	'SL_No'=> $row['slno'],
												'Cust_IIN'=> $row['customer_id'],
												'Cust_Name'=> $row['investor_name'],
												'Trxn_Type'=> $row['trxn_head']
												);
		}
		*/
		
		$assetType		= $db->getFundAssetClass($amcCode, $fundCode );		
		if( strtolower($assetType) == 	strtolower("Not_Defined"))
		{
			$wrongAssetTypes[]	=	array	(	'SL_No'=> $row['slno'],
												'Cust_IIN'=> $row['customer_id'],
												'Cust_Name'=> $row['investor_name'],
												'Trxn_Type'=> $row['trxn_head'],
												'Asset_Type'=> $assetType
												);
		}
		
		/*
		if(($unitsTransacted <= 0) || ($transactionAmount <= 0))
		{
			$wrongTrxnUnitsAmt[]	=	array	(	'SL_No'=> $row['slno'],
												'Cust_IIN'=> $row['customer_id'],
												'Cust_Name'=> $row['investor_name'],
												'Trxn_Type'=> $row['trxn_head'],
												'Trxn_Amount'=>$transactionAmount,
												'Units_Transacted'=>$unitsTransacted
												);
		}
		
		if(!(date_parse($transactionDate)))
		{
			
				$wrongTrxnProcessDate[]	=	array	(	'SL_No'=> $row['slno'],
												'Cust_IIN'=> $row['customer_id'],
												'Cust_Name'=> $row['investor_name'],
												'Trxn_Type'=> $row['trxn_head'],
												'Process_Date'=>$transactionDate
												);
		}			
		*/
		$navData			= $db->getFundNAV($amcCode,$fundCode );
		$assetClass			= $db->getFundAssetClass($amcCode, $fundCode );
		//$unRealizedProfit 	= 0;
		
		if (!$navData)
		{
			//echo "<br>";
			//echo "NAV data not found for : ". "AMC:  ". $fundHouseName." Fund Name: ".$fundName."*  Fund Code:  ".$fundCode;
			//echo "<br>";
			// Data Not found for: 
			$navDataMissing[]	=	array	(	'AMC_Code'=> $amcCode,
												'AMC_Name'=> $fundHouseName,
												'Fund_Name'=> $fundName 
											);
		}
		
		
	}
	
	
	function verifyTransactions()
	{
		global	$wrongTrxnTypes;
		global	$wrongAssetTypes;
		global	$wrongTrxnProcessDate;
		global	$wrongTrxnUnitsAmt;
		
		/*
		echo " <br>"." Number of Wrong Trxn types found in all transactions = ".count($wrongTrxnTypes)."<br>"."<br>"; 		
		print_r($wrongTrxnTypes);
		
		echo " <br>"." Number of Wrong Asset Class/Types found in all transactions = ".count($wrongAssetTypes)."<br>"."<br>"; 		
		print_r($wrongAssetTypes);
		echo " <br>";
		*/			
		
		echo " <br>"." Number of Wrong Trxn Process date found in all transactions = ".count($wrongTrxnProcessDate)."<br>"."<br>"; 			
		echo "<pre>";
		print_r($wrongTrxnProcessDate);
		echo "</pre>";

		echo " <br>";
		
		echo " <br>"." Number of Wrong Trxn Units or Amount found in all transactions = ".count($wrongTrxnUnitsAmt)."<br>"."<br>"; 	
		print_r($wrongTrxnUnitsAmt);
		echo " <br>";
		
		/*
		echo " <br>"."List of Wrong Trxn Process date found in all transactions "."<br>"; 
		print_r($wrongTrxnProcessDate);
		echo " <br>";
		*/
		
	}

	function verifyNAV()
	{
		global	$navDataMissing;
		
		echo " <br>"." Number of Funds for which NAV data not found = ".count($navDataMissing)."<br>"."<br>"; 	
		print_r($navDataMissing);
		echo " <br>";
	}

	//verifyTransactions();
	verifyNAV();
	

	
	//============================================================+
	// END OF FILE
	//============================================================+
	
	
		/*
		echo "   Name: ";
		echo 	$name; echo "    ";
		echo "  PAN:  ";echo	$userpan;echo "    ";
		echo  "  Address: ".$address;echo "    ";
		*/
		
?>
