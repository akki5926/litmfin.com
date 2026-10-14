<?php
require_once('auth_guard.php');

//require_once('financial_class.php');
require_once(__DIR__ . '/xirr_engine.php');

class TotalHoldings 
{
	private 	$m_cashFlowEquity				= array();
	private 	$m_cashFlowDebt					= array();
	private 	$m_cashFlowHybrid				= array();
	private 	$m_cashFlowGold					= array();

	private 	$m_cashFlowTotal				= array();
	
	private 	$m_timePeriodEquity 			= array();
	private 	$m_timePeriodDebt 				= array();
	private 	$m_timePeriodHybrid 			= array();
	private 	$m_timePeriodGold 				= array();

	private 	$m_timePeriodTotal 				= array();
	
	private 	$m_totalCostValueEquity			=	0;
	private 	$m_totalCostValueDebt			=	0;
	private 	$m_totalCostValueHybrid			=	0;
	private 	$m_totalCostValueGold 			= 	0; 
	
	private 	$m_totalCostValue				=	0;

	private 	$m_totalCurrentValueEquity		=	0;
	private 	$m_totalCurrentValueDebt		=	0;
	private 	$m_totalCurrentValueHybrid		=	0;
	private 	$m_totalCurrentValueGold 		= 	0; 

	private 	$m_totalCurrentValue			=	0;
	
	private 	$m_totalUnRealizedProfitEquity	=	0;
	private 	$m_totalUnRealizedProfitDebt	=	0;
	private 	$m_totalUnRealizedProfitHybrid	=	0;	
    private 	$m_totalUnRealizedProfitGold 	= 	0; 
	
	private 	$m_totalUnRealizedProfit		=	0;

	private 	$m_totalPayoutRecvdEquity		=	0;
	private 	$m_totalPayoutRecvdDebt			=	0;
	private 	$m_totalPayoutRecvdHybrid		=	0;
	private 	$m_totalPayoutRecvdGold 		= 	0; 
	
	private 	$m_totalPayoutRecvd				=	0;
	
	private 	$m_totalBookedProfitEquity		=	0;
	private 	$m_totalBookedProfitDebt		=	0;
	private 	$m_totalBookedProfitHybrid		=	0;
	private 	$m_totalBookedProfitGold 		= 	0; 
	
	private 	$m_totalBookedProfit			=	0;

	private 	$m_totalCostValueMF 			=	0;
	private 	$m_totalCurrentValueMF 			=	0;
	private 	$m_totalUnRealizedProfitMF 		=	0;
	private 	$m_totalBookedProfitMF 			=	0;
    
	//
   	private 	$m_totalEquiyCostValueMF 			=	0;
	private 	$m_totalEquiyCurrentValueMF 		=	0;
	private 	$m_totalEquiyUnRealizedProfitMF 	=	0;
	private 	$m_totalEquiyBookedProfitMF 		=	0;
	private 	$m_totalEquityCashFlowMF			= array();
	private 	$m_totalEquityTimePeriodMF 			= array();
	
	private 	$m_totalDebtCostValueMF 			=	0;
	private 	$m_totalDebtCurrentValueMF 			=	0;
	private 	$m_totalDebtUnRealizedProfitMF 		=	0;
	private 	$m_totalDebtBookedProfitMF 			=	0;
	private 	$m_totalDebtCashFlowMF				= array();
	private 	$m_totalDebtTimePeriodMF 			= array();

	private 	$m_totalHybridCostValueMF 			=	0;
	private 	$m_totalHybridCurrentValueMF 		=	0;
	private 	$m_totalHybridUnRealizedProfitMF 	=	0;
	private 	$m_totalHybridBookedProfitMF 		=	0;
	private 	$m_totalHybridCashFlowMF			= array();
	private 	$m_totalHybridTimePeriodMF 			= array();
	
	private 	$m_totalGoldCostValueMF 			=	0;
	private 	$m_totalGoldCurrentValueMF 			=	0;
	private 	$m_totalGoldUnRealizedProfitMF 		=	0;
	private 	$m_totalGoldBookedProfitMF 			=	0;
	private 	$m_totalGoldCashFlowMF				= array();
	private 	$m_totalGoldTimePeriodMF 			= array();
	//
	
	private 	$m_totalCostValueBond 			=	0;
	private 	$m_totalCurrentValueBond 		=	0;
	private 	$m_totalUnRealizedProfitBond 	=	0;
	private 	$m_totalBookedProfitBond 		=	0;
	
	private 	$m_totalPayoutRecvdBond 		= 	0; 

	private 	$m_totalCostValuePms 			=	0;
	private 	$m_totalCurrentValuePms 		=	0;
	private 	$m_totalUnRealizedProfitPms 	=	0;
	private 	$m_totalBookedProfitPms			=	0;
	private 	$m_totalSellValuePms 			=	0;

	private 	$m_totalCostValueAIF 			=	0;
	private 	$m_totalCurrentValueAIF 		=	0;
	private 	$m_totalUnRealizedProfitAIF 	=	0;
	private 	$m_totalBookedProfitAIF 		=	0;
	private 	$m_totalPayoutRecvdAIF 		= 	0; 
	
	private 	$m_totalCostValueSIF 			=	0;
	private 	$m_totalCurrentValueSIF 		=	0;
	private 	$m_totalUnRealizedProfitSIF 	=	0;
	private 	$m_totalBookedProfitSIF 		=	0;
	private 	$m_totalPayoutRecvdSIF 		= 	0; 
	
	private 	$m_cashFlowMF					= array();
	private 	$m_cashFlowBond					= array();
	private 	$m_cashFlowPMS					= array();
	private 	$m_cashFlowAIF					= array();
	private 	$m_cashFlowSIF					= array();
	
	private 	$m_timePeriodMF 				= array();
	private 	$m_timePeriodBond 				= array();
	private 	$m_timePeriodPMS 				= array();
	private 	$m_timePeriodAIF				= array();
	private 	$m_timePeriodSIF				= array();
	
	private 	$m_holdingDataMF 		= 	[];
	private 	$m_holdingDataBonds 	= 	[];
	private 	$m_holdingDataPMS 		= 	[];
	private 	$m_holdingDataAIF 		= 	[];

	private		$m_xirrObj ;				
    private 	$m_unitData = []; // Two-dimensional array to store unit data

	public function __construct()
    {
        //$this->m_xirrObj	=	new Financial;
		//require_once __DIR__ . '/xirr_calculator.php';
		//$this->m_xirrObj = new XirrCalculator();
		$this->m_xirrObj = new XirrEngine();
    }
	
    public function addInvestments(	$assetType, $investmentType, $costValue, $sellValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod ) 
	{
		/*
		echo "<br>"." Inside addInvestments call ". "<br>";
		echo "<br>"." Asset Type:= ".$assetType;
		echo "<br>"." Investment Type:= ".$investmentType;
		echo "<br>"." Cost Value:= ".$costValue;
		echo "<br>"." Sell Value:= ".$sellValue;
		echo "<br>"." Current Value:= ".$currentValue;
		echo "<br>"." Unrealized profit:= ".$unRealizedProfit;
		echo "<br>"." Booked Profit:= ".$bookedProfit;
		*/
		$assetType	=	strtoupper ($assetType);
		switch( $assetType )
		{
			
			case	"EQUITY": 	$this->addEquityData($investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);	
								break;			
			case	"DEBT": 	$this->addDebtData($investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);	
								break;		
			case	"HYBRID": 	$this->addHybridData($investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);	
								break;	
			case	"GOLD": 	$this->addGoldData($investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);	
								break;									
			default:			$this->addDebtData($investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);	
								break;	
		}
		
		$this->addDataByInvestmentType( $investmentType, $assetType, $costValue, $sellValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod); 
	} 
	
	
	public function addDataByInvestmentType( $investmentType, $assetType, $costValue, $sellValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod) 
	{
		$investmentType	=	strtoupper ($investmentType);
		switch($investmentType)
		{
			case	"MF": 	
							$this->addMFDataByAssetType( $assetType, $costValue, $sellValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod);
							/* When total MF data needed, can be used. For now it is not needed.
							$this->m_totalCostValueMF 			+= $costValue; 
							$this->m_totalCurrentValueMF 		+= $currentValue;
							$this->m_totalUnRealizedProfitMF 	+= $unRealizedProfit;
							$this->m_totalBookedProfitMF 		+= $bookedProfit;
							$this->m_cashFlowMF					= array_merge($this->m_cashFlowMF,  $cashFlow);
							$this->m_timePeriodMF				= array_merge($this->m_timePeriodMF, $timePeriod);
							*/
							break;			
							
			case	"BOND": 	
							$this->m_totalCostValueBond 		+= $costValue; 
							$this->m_totalCurrentValueBond 		+= $currentValue;
							$this->m_totalUnRealizedProfitBond 	+= $unRealizedProfit;
							$this->m_totalBookedProfitBond 		+= $bookedProfit;
							$this->m_totalPayoutRecvdBond 		+= $payoutRecvd;
							
							$this->m_cashFlowBond				= array_merge($this->m_cashFlowBond,  $cashFlow);
							$this->m_timePeriodBond				= array_merge($this->m_timePeriodBond, $timePeriod);
							break;			
							
			case	"PMS": 	
							//echo "<br>"."Adding PMS Data";
							$this->m_totalCostValuePms 			+= $costValue; 
							$this->m_totalSellValuePms 			+= $sellValue; 
							$this->m_totalCurrentValuePms 		+= $currentValue;
							$this->m_totalUnRealizedProfitPms 	+= $unRealizedProfit;
							$this->m_totalBookedProfitPms 		+= $bookedProfit;
							
							//echo "<br>"."Cashflow array total for PMS before merging : "."<br>";
							//print_r($this->m_cashFlowPMS);
							
							$this->m_cashFlowPMS				= array_merge($this->m_cashFlowPMS,  $cashFlow);
							$this->m_timePeriodPMS				= array_merge($this->m_timePeriodPMS, $timePeriod);
							
							//echo "<br>"."Cashflow array tootal for PMS after merging : "."<br>";
							//print_r($this->m_cashFlowPMS);
							break;			
							
			case	"AIF": 	
							$this->m_totalCostValueAIF 			+= $costValue; 
							$this->m_totalCurrentValueAIF 		+= $currentValue;
							$this->m_totalUnRealizedProfitAIF 	+= $unRealizedProfit;
							$this->m_totalBookedProfitAIF 		+= $bookedProfit;
							$this->m_totalPayoutRecvdAIF 		+= $payoutRecvd;
							$this->m_cashFlowAIF				= array_merge($this->m_cashFlowAIF,  $cashFlow);
							$this->m_timePeriodAIF				= array_merge($this->m_timePeriodAIF, $timePeriod);
							break;			
							
			case	"SIF": 	
							$this->m_totalCostValueSIF 			+= $costValue; 
							$this->m_totalCurrentValueSIF 		+= $currentValue;
							$this->m_totalUnRealizedProfitSIF 	+= $unRealizedProfit;
							$this->m_totalBookedProfitSIF 		+= $bookedProfit;
							$this->m_totalPayoutRecvdSIF 		+= $payoutRecvd;
							$this->m_cashFlowSIF				= array_merge($this->m_cashFlowSIF,  $cashFlow);
							$this->m_timePeriodSIF				= array_merge($this->m_timePeriodSIF, $timePeriod);
							break;	
			//default:
			//					break;	
		}

    }

	public function addMFDataByAssetType( $assetType, $costValue, $sellValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod) 
	{
		switch( $assetType )
		{
			
			
			case	"EQUITY": 	        
									$this->m_totalEquiyCostValueMF 			+= $costValue;
									$this->m_totalEquiyCurrentValueMF 		+= $currentValue;
									$this->m_totalEquiyUnRealizedProfitMF 	+= $unRealizedProfit;
									$this->m_totalEquiyBookedProfitMF 		+= $bookedProfit;
									$this->m_totalEquityCashFlowMF			= array_merge($this->m_totalEquityCashFlowMF,  $cashFlow);
									$this->m_totalEquityTimePeriodMF		= array_merge($this->m_totalEquityTimePeriodMF, $timePeriod);

								break;			

			case	"DEBT": 	
									$this->m_totalDebtCostValueMF 			+= $costValue;
									$this->m_totalDebtCurrentValueMF 		+= $currentValue;
									$this->m_totalDebtUnRealizedProfitMF 	+= $unRealizedProfit;
									$this->m_totalDebtBookedProfitMF 		+= $bookedProfit;
									$this->m_totalDebtCashFlowMF			= array_merge($this->m_totalDebtCashFlowMF,  $cashFlow);
									$this->m_totalDebtTimePeriodMF			= array_merge($this->m_totalDebtTimePeriodMF, $timePeriod);

								break;		
			case	"HYBRID": 	
									$this->m_totalHybridCostValueMF 			+= $costValue;
									$this->m_totalHybridCurrentValueMF 		+= $currentValue;
									$this->m_totalHybridUnRealizedProfitMF 	+= $unRealizedProfit;
									$this->m_totalHybridBookedProfitMF 		+= $bookedProfit;
									$this->m_totalHybridCashFlowMF			= array_merge($this->m_totalHybridCashFlowMF,  $cashFlow);
									$this->m_totalHybridTimePeriodMF		= array_merge($this->m_totalHybridTimePeriodMF, $timePeriod);

								break;	
			case	"GOLD": 	
									$this->m_totalGoldCostValueMF 			+= $costValue;
									$this->m_totalGoldCurrentValueMF 		+= $currentValue;
									$this->m_totalGoldUnRealizedProfitMF 	+= $unRealizedProfit;
									$this->m_totalGoldBookedProfitMF 		+= $bookedProfit;
									$this->m_totalGoldCashFlowMF			= array_merge($this->m_totalGoldCashFlowMF,  $cashFlow);
									$this->m_totalGoldTimePeriodMF			= array_merge($this->m_totalGoldTimePeriodMF, $timePeriod);

								break;									
			default:			
								break;	
		}
	}

	// Method to store Equity data with additional inputs
    public function addEquityData( $investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod) 
	{
		//echo "<br>"."Adding Equity Cost data: ".$costValue;
        $this->m_totalCostValueEquity 			+= $costValue;
        $this->m_totalCurrentValueEquity 		+= $currentValue;
        $this->m_totalUnRealizedProfitEquity 	+= $unRealizedProfit;
        $this->m_totalBookedProfitEquity 		+= $bookedProfit;
		$this->m_totalPayoutRecvdEquity 		+= $payoutRecvd;
		
		$this->m_cashFlowEquity				= array_merge($this->m_cashFlowEquity,  $cashFlow);
		$this->m_timePeriodEquity			= array_merge($this->m_timePeriodEquity, $timePeriod);
		 
		//addDataByInvestmentType( $investmentType, $costValue, $currentValue, $unRealizedProfit, $bookedProfit, $cashFlow, $timePeriod); 
    }

	// Method to store Equity data with additional inputs
    public function addDebtData( $investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod) 
	{
		//echo "<br>".$investmentType."  Adding Debt Cost data: ".$costValue;
        $this->m_totalCostValueDebt 			+= $costValue;
		//echo "<br>"."Total Debt Cost data after addition: ".$this->m_totalCostValueDebt;
		
		//echo "<br>".$investmentType." Adding Debt Current Value: ".$currentValue;
        $this->m_totalCurrentValueDebt 			+= $currentValue;
		//echo "<br>"."Total Debt Current Value after addition: ".$this->m_totalCurrentValueDebt;
		
        $this->m_totalUnRealizedProfitDebt 		+= $unRealizedProfit;
        $this->m_totalBookedProfitDebt 			+= $bookedProfit;
		$this->m_totalPayoutRecvdDebt	 		+= $payoutRecvd;
		
		$this->m_cashFlowDebt				= array_merge($this->m_cashFlowDebt,  $cashFlow);
		$this->m_timePeriodDebt				= array_merge($this->m_timePeriodDebt, $timePeriod);
    }
	
	// Method to store Hybrid data with additional inputs
    public function addHybridData( $investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod) 
	{
        $this->m_totalCostValueHybrid 			+= $costValue;
        $this->m_totalCurrentValueHybrid 		+= $currentValue;
        $this->m_totalUnRealizedProfitHybrid 	+= $unRealizedProfit;
        $this->m_totalBookedProfitHybrid 		+= $bookedProfit;
		$this->m_totalPayoutRecvdHybrid	 		+= $payoutRecvd;
		
		$this->m_cashFlowHybrid				= array_merge($this->m_cashFlowHybrid,  $cashFlow);
		$this->m_timePeriodHybrid			= array_merge($this->m_timePeriodHybrid, $timePeriod);
    }

    // Method to store Gold data with additional inputs
    public function addGoldData( $investmentType, $costValue, $currentValue, $unRealizedProfit, $payoutRecvd, $bookedProfit, $cashFlow, $timePeriod) 
	{
        $this->m_totalCostValueGold 		+= $costValue;
        $this->m_totalCurrentValueGold 		+= $currentValue;
        $this->m_totalUnRealizedProfitGold 	+= $unRealizedProfit;
        $this->m_totalBookedProfitGold 		+= $bookedProfit;
		$this->m_totalPayoutRecvdGold 		+= $payoutRecvd;
		
		$this->m_cashFlowGold			= array_merge($this->m_cashFlowGold,  $cashFlow);
		$this->m_timePeriodGold			= array_merge($this->m_timePeriodGold, $timePeriod);
    }

  // Method to get Equity holding data with additional inputs
    public function getEquityDataTotal( ) 
	{
		$totalEquityHoldings 	= array();

		if($this->m_totalCurrentValueEquity > 0)
		{

			$xirrReturnEquity		=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowEquity, $this->m_timePeriodEquity, 0.1) * 100;
			
			$totalCost						=	number_format($this->m_totalCostValueEquity, 2, '.', ',');
			$totalBookedProfit				=	number_format($this->m_totalBookedProfitEquity, 2, '.', ',');
			$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfitEquity, 2, '.', ',');
			$totalCurrentValue				=	number_format($this->m_totalCurrentValueEquity, 2, '.', ',');
			$xirrReturnEquity				=	number_format($xirrReturnEquity, 2, '.', ',');
			
			$totalEquityHoldings[] = array(	"Total_Equity_Cost"=>$totalCost, 				
											"Total_Equity_Profit_Booked"=>$totalBookedProfit,
											"Total_Equity_Unrealized_Profit"=>$totalUnRealizedProfit, 				
											"Total_Equity_Current_Value"=>$totalCurrentValue,
											"Total_Equity_Return"=>$xirrReturnEquity
											);
		}
		return $totalEquityHoldings;
	}

  // Method to get Debt holding data with additional inputs
    public function getDebtDataTotal( ) 
	{
		$totalDebtHoldings 	= array();
		
		if($this->m_totalCurrentValueDebt > 0)
		{
			$xirrReturnDebt					=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowDebt, $this->m_timePeriodDebt, 0.1) * 100;
			
			$totalCost						=	number_format($this->m_totalCostValueDebt, 2, '.', ',');
			$totalBookedProfit				=	number_format($this->m_totalBookedProfitDebt, 2, '.', ',');
			$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfitDebt, 2, '.', ',');
			$totalCurrentValue				=	number_format($this->m_totalCurrentValueDebt, 2, '.', ',');
			$xirrReturnDebt					=	number_format($xirrReturnDebt, 2, '.', ',');
			
			$totalDebtHoldings[] = array(	"Total_Debt_Cost"=>$totalCost, 				
											"Total_Debt_Profit_Booked"=>$totalBookedProfit,
											"Total_Debt_Unrealized_Profit"=>$totalUnRealizedProfit, 				
											"Total_Debt_Current_Value"=>$totalCurrentValue,
											"Total_Debt_Return"=>$xirrReturnDebt
											);
		}		
		return $totalDebtHoldings;
	}

  // Method to get Hybrid holding data with additional inputs
    public function getHybridDataTotal( ) 
	{
		if($this->m_totalCurrentValueHybrid > 0)
		{
			$totalHybridHoldings 	= array();
			$xirrReturnHybrid		=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowHybrid, $this->m_timePeriodHybrid, 0.1) * 100;
			
			$totalCost						=	number_format($this->m_totalCostValueHybrid, 2, '.', ',');
			$totalBookedProfit				=	number_format($this->m_totalBookedProfitHybrid, 2, '.', ',');
			$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfitHybrid, 2, '.', ',');
			$totalCurrentValue				=	number_format($this->m_totalCurrentValueHybrid, 2, '.', ',');
			$xirrReturnHybrid				=	number_format($xirrReturnHybrid, 2, '.', ',');
			
			$totalHybridHoldings[] = array(	"Total_Hybrid_Cost"=>$totalCost, 				
											"Total_Hybrid_Profit_Booked"=>$totalBookedProfit,
											"Total_Hybrid_Unrealized_Profit"=>$totalUnRealizedProfit, 				
											"Total_Hybrid_Current_Value"=>$totalCurrentValue,
											"Total_Hybrid_Return"=>$xirrReturnHybrid
											);
		}
		return $totalHybridHoldings;
	}

	// Method to get Gold holding data with additional inputs
    public function getGoldDataTotal( ) 
	{
		$totalGoldHoldings 	= array();
		if($this->m_totalCurrentValueGold > 0)
		{
			$xirrReturnGold			=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowGold, $this->m_timePeriodGold, 0.1) * 100;
				
			$totalCost					=	number_format($this->m_totalCostValueGold, 2, '.', ',');
			$totalBookedProfit			=	number_format($this->m_totalBookedProfitGold, 2, '.', ',');
			$totalUnRealizedProfit		=	number_format($this->m_totalUnRealizedProfitGold, 2, '.', ',');
			$totalCurrentValue			=	number_format($this->m_totalCurrentValueGold, 2, '.', ',');
			$xirrReturnGold				=	number_format($xirrReturnGold, 2, '.', ',');
			
			$totalGoldHoldings[] 		= array(	"Total_Gold_Cost"=>$totalCost, 				
													"Total_Gold_Profit_Booked"=>$totalBookedProfit,
													"Total_Gold_Unrealized_Profit"=>$totalUnRealizedProfit, 				
													"Total_Gold_Current_Value"=>$totalCurrentValue,
													"Total_Gold_Return"=>$xirrReturnGold
												);
		}
		return $totalGoldHoldings;
	}

  // Method to get Gold holding data with additional inputs
    public function getMFDataTotal( ) 
	{
		$totalMFHoldings 	= array();
		
		if($this->m_totalCurrentValueMF > 0)
		{
			$xirrReturnMF		=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowMF, $this->m_timePeriodMF, 0.1) * 100;
			
			$totalCost						=	number_format($this->m_totalCostValueMF, 2, '.', ',');
			$totalBookedProfit				=	number_format($this->m_totalBookedProfitMF, 2, '.', ',');
			$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfitMF, 2, '.', ',');
			$totalCurrentValue				=	number_format($this->m_totalCurrentValueMF, 2, '.', ',');
			$xirrReturnMF					=	number_format($xirrReturnMF, 2, '.', ',');
			
			$totalMFHoldings[] = array(	"Total_MF_Cost"=>$totalCost, 				
											"Total_MF_Profit_Booked"=>$totalBookedProfit,
											"Total_MF_Unrealized_Profit"=>$totalUnRealizedProfit, 				
											"Total_MF_Current_Value"=>$totalCurrentValue,
											"Total_MF_Return"=>$xirrReturnMF
										);
		}
		
		return $totalMFHoldings;
	}

   // Method to get Gold holding data with additional inputs
    public function getBondDataTotal( ) 
	{
		$totalBondHoldings 	= 	array();
		
		if($this->m_totalCurrentValueBond > 0)
		{
			$xirrReturnBond		=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowBond, $this->m_timePeriodBond, 0.1) * 100;
			
			$totalCost						=	number_format($this->m_totalCostValueBond, 2, '.', ',');
			$totalBookedProfit				=	number_format($this->m_totalBookedProfitBond, 2, '.', ',');
			$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfitBond, 2, '.', ',');
			$totalCurrentValue				=	number_format($this->m_totalCurrentValueBond, 2, '.', ',');
			
			$totalPayoutRecvd				=	number_format($this->m_totalPayoutRecvdBond, 2, '.', ',');
			
			$xirrReturnBond					=	number_format($xirrReturnBond, 2, '.', ',');
			
			$totalBondHoldings[] = array(	"Total_Bond_Cost"=>$totalCost, 				
											"Total_Bond_Profit_Booked"=>$totalBookedProfit,
											"Total_Bond_Payout_Recvd"=>$totalPayoutRecvd,
											"Total_Bond_Unrealized_Profit"=>$totalUnRealizedProfit, 				
											"Total_Bond_Current_Value"=>$totalCurrentValue,
											"Total_Bond_Return"=>$xirrReturnBond
										);
		}
		
		return $totalBondHoldings;
	}

   // Method to get PMS holding data 
    public function getPmsDataTotal( ) 
	{
		$totalPmsHoldings 	= 	array();
		$xirrReturnPms		=	0;
		if($this->m_totalCurrentValuePms > 0)
		{
			$xirrReturnPms					=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowPMS, $this->m_timePeriodPMS, 0.1) * 100;
			
			$totalCost						=	number_format($this->m_totalCostValuePms, 2, '.', ',');
			$totalSold						=	number_format($this->m_totalSellValuePms, 2, '.', ',');
			$totalBookedProfit				=	number_format($this->m_totalBookedProfitPms, 2, '.', ',');
			$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfitPms, 2, '.', ',');
			$totalCurrentValue				=	number_format($this->m_totalCurrentValuePms, 2, '.', ',');
			
			//$totalPayoutRecvd				=	number_format($this->m_totalPayoutRecvdPms, 2, '.', ',');
			
			$xirrReturnPms					=	number_format($xirrReturnPms, 2, '.', ',');
			
			$totalPmsHoldings[] = array(	"Total_Pms_Cost"=>$totalCost, 		
											"Total_Pms_Sold"=>$totalSold,			
											"Total_Pms_Profit_Booked"=>$totalBookedProfit,
											"Total_Pms_Unrealized_Profit"=>$totalUnRealizedProfit, 				
											"Total_Pms_Current_Value"=>$totalCurrentValue,
											"Total_Pms_Return"=>$xirrReturnPms
										);
		}
		
		return $totalPmsHoldings;
	}

   // Method to get AIF holding data 
    public function getAifDataTotal( ) 
	{
		$totalAIFHoldings 	= 	array();
		
		if($this->m_totalCurrentValueAIF > 0)
		{
			$xirrReturnAIF					=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowAIF, $this->m_timePeriodAIF, 0.1) * 100;
			
			$totalCost						=	number_format($this->m_totalCostValueAIF, 1, '.', ',');
			//$totalSold						=	number_format($this->m_totalSellValueAIF, 2, '.', ',');
			$totalBookedProfit				=	number_format($this->m_totalBookedProfitAIF, 1, '.', ',');
			$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfitAIF, 1, '.', ',');
			$totalCurrentValue				=	number_format($this->m_totalCurrentValueAIF, 1, '.', ',');
			
			$totalPayoutRecvd				=	number_format($this->m_totalPayoutRecvdAIF, 1, '.', ',');
			
			$xirrReturnAIF					=	number_format($xirrReturnAIF, 2, '.', ',');
			
			$totalAIFHoldings[] = array(	
											"Total_Aif_Cost"=>$totalCost, 	
											"Total_Aif_Payout_Recvd"=>	$totalPayoutRecvd,											
											"Total_Aif_Profit_Booked"=>$totalBookedProfit,
											"Total_Aif_Unrealized_Profit"=>$totalUnRealizedProfit, 				
											"Total_Aif_Current_Value"=>$totalCurrentValue,
											"Total_Aif_Return"=>$xirrReturnAIF
										);
		}
		
		return $totalAIFHoldings;
		
	}

   // Method to get SIF holding data 
    public function getSifDataTotal( ) 
	{
		$totalSIFHoldings 	= 	array();
		
		if($this->m_totalCurrentValueSIF > 0)
		{
			$xirrReturnSIF					=	 $this->m_xirrObj->XIRRAdpro($this->m_cashFlowSIF, $this->m_timePeriodSIF, 0.1) * 100;
			if (abs($xirrReturnSIF) > 10000) $xirrReturnSIF = 0;
			
			$totalCost						=	number_format($this->m_totalCostValueSIF, 2, '.', ',');
			$totalBookedProfit				=	number_format($this->m_totalBookedProfitSIF, 2, '.', ',');
			$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfitSIF, 2, '.', ',');
			$totalCurrentValue				=	number_format($this->m_totalCurrentValueSIF, 2, '.', ',');
			
			$totalPayoutRecvd				=	number_format($this->m_totalPayoutRecvdSIF, 2, '.', ',');
			
			$xirrReturnSIF					=	number_format($xirrReturnSIF, 2, '.', ',');
			
			$totalSIFHoldings[] = array(	
											"Total_Sif_Cost"=>$totalCost, 	
											"Total_Sif_Payout_Recvd"=>	$totalPayoutRecvd,											
											"Total_Sif_Profit_Booked"=>$totalBookedProfit,
											"Total_Sif_Unrealized_Profit"=>$totalUnRealizedProfit, 				
											"Total_Sif_Current_Value"=>$totalCurrentValue,
											"Total_Sif_Return"=>$xirrReturnSIF
										);
		}
		
		return $totalSIFHoldings;
	}

	public function getHoldingDataTotal( ) 
	{
		
		$totalHoldings 					= 	array();
		$totalXirrEquity				=	0;
		$totalXirrDebt					=	0;
		$totalXirrHybrid				=	0;
		$totalXirrGold					=	0;
		$xirrReturnTotal				=	0;
		
		
		$this->m_totalCostValue				=	$this->m_totalCostValueEquity + $this->m_totalCostValueDebt + $this->m_totalCostValueHybrid + $this->m_totalCostValueGold;
		$this->m_totalBookedProfit			=	$this->m_totalBookedProfitEquity + $this->m_totalBookedProfitDebt + $this->m_totalBookedProfitHybrid + $this->m_totalBookedProfitGold;
		$this->m_totalUnRealizedProfit		=	$this->m_totalUnRealizedProfitEquity + $this->m_totalUnRealizedProfitDebt + $this->m_totalUnRealizedProfitHybrid + $this->m_totalUnRealizedProfitGold;
		$this->m_totalCurrentValue			=	$this->m_totalCurrentValueEquity + $this->m_totalCurrentValueDebt + $this->m_totalCurrentValueHybrid + $this->m_totalCurrentValueGold;
		
		
		$this->m_cashFlowTotal				=	array_merge($this->m_cashFlowEquity, $this->m_cashFlowDebt, $this->m_cashFlowHybrid, $this->m_cashFlowGold );
		$this->m_timePeriodTotal			=	array_merge($this->m_timePeriodEquity, $this->m_timePeriodDebt, $this->m_timePeriodHybrid, $this->m_timePeriodGold );
		
		$totalCost						=	number_format($this->m_totalCostValue, 2, '.', ',');
		$totalBookedProfit				=	number_format($this->m_totalBookedProfit, 2, '.', ',');
		$totalUnRealizedProfit			=	number_format($this->m_totalUnRealizedProfit, 2, '.', ',');
		$totalCurrentValue				=	number_format($this->m_totalCurrentValue, 2, '.', ',');
		
		$totalEquityCostValue			=	number_format($this->m_totalCostValueEquity, 2, '.', ',');
		$totalDebtCostValue				=	number_format($this->m_totalCostValueDebt, 2, '.', ',');
		$totalHybridCostValue			=	number_format($this->m_totalCostValueHybrid, 2, '.', ',');
		$totalGoldCostValue				=	number_format($this->m_totalCostValueGold, 2, '.', ',');
		
		$totalHoldingCostValue			=	number_format($this->m_totalCostValue, 2, '.', ',');
		
		$totalEquityCurrentValue		=	number_format($this->m_totalCurrentValueEquity, 2, '.', ',');
		$totalDebtCurrentValue			=	number_format($this->m_totalCurrentValueDebt, 2, '.', ',');
		$totalHybridCurrentValue		=	number_format($this->m_totalCurrentValueHybrid, 2, '.', ',');
		$totalGoldCurrentValue			=	number_format($this->m_totalCurrentValueGold, 2, '.', ',');
		
		$totalHoldingCurrentValue		=	number_format($this->m_totalCurrentValue, 2, '.', ',');
		
		
		$totalUnRealizedProfitEquity	=	number_format($this->m_totalUnRealizedProfitEquity, 2, '.', ',');
		$totalUnRealizedProfitDebt		=	number_format($this->m_totalUnRealizedProfitDebt, 2, '.', ',');
		$totalUnRealizedProfitHybrid	=	number_format($this->m_totalUnRealizedProfitHybrid, 2, '.', ',');
		$totalUnRealizedProfitGold		=	number_format($this->m_totalUnRealizedProfitGold, 2, '.', ',');
		
		$totalBookedProfitEquity		=	number_format($this->m_totalBookedProfitEquity, 2, '.', ',');
		$totalBookedProfitDebt			=	number_format($this->m_totalBookedProfitDebt, 2, '.', ',');
		$totalBookedProfitHybrid		=	number_format($this->m_totalBookedProfitHybrid, 2, '.', ',');
		$totalBookedProfitGold			=	number_format($this->m_totalBookedProfitGold, 2, '.', ',');
		
	
		if($this->m_cashFlowEquity)
			$totalXirrEquity				=	$this->m_xirrObj->XIRRAdpro($this->m_cashFlowEquity, $this->m_timePeriodEquity, 0.1) * 100;
		if($this->m_cashFlowDebt)
			$totalXirrDebt					=	$this->m_xirrObj->XIRRAdpro($this->m_cashFlowDebt, $this->m_timePeriodDebt, 0.1) * 100;
		if($this->m_cashFlowHybrid)
			$totalXirrHybrid			=	$this->m_xirrObj->XIRRAdpro($this->m_cashFlowHybrid, $this->m_timePeriodHybrid, 0.1) * 100;
		if($this->m_cashFlowGold)
			$totalXirrGold				=	$this->m_xirrObj->XIRRAdpro($this->m_cashFlowGold, $this->m_timePeriodGold, 0.1) * 100;
		if($this->m_cashFlowTotal)
			$xirrReturnTotal				=	$this->m_xirrObj->XIRRAdpro($this->m_cashFlowTotal, $this->m_timePeriodTotal, 0.1) * 100;
		
		$totalXirrEquity				=	number_format($totalXirrEquity, 2, '.', ',');
		$totalXirrDebt					=	number_format($totalXirrDebt, 2, '.', ',');
		$totalXirrHybrid				=	number_format($totalXirrHybrid, 2, '.', ',');
		$totalXirrGold					=	number_format($totalXirrGold, 2, '.', ',');
		$xirrReturnTotal				=	number_format($xirrReturnTotal, 2, '.', ',');
		
		$allAssetShare					=	$this->getAssetShare();
		
		$equityAssetShare				=	$allAssetShare['Equity_Share'];
		$debtAssetShare					=	$allAssetShare['Debt_Share'];
		$goldAssetShare					=	$allAssetShare['Gold_Share'];
		$hybridAssetShare				=	$allAssetShare['Hybrid_Share'];

		$totalHoldings[] = 		array(  "Total_Equity_Cost"=>$totalEquityCostValue, 				"Total_Debt_Cost"=>$totalDebtCostValue, 
										"Total_Hybrid_Cost"=>$totalHybridCostValue,  				"Total_Gold_Cost"=>$totalGoldCostValue, 
										"Total_Equity_Curr_Value"=>$totalEquityCurrentValue, 		"Total_Debt_Curr_Value"=>$totalDebtCurrentValue, 
										"Total_Hybrid_Curr_Value"=>$totalHybridCurrentValue,  		"Total_Gold_Curr_Value"=>$totalGoldCurrentValue,
										"Total_Equity_Unrealized"=>$totalUnRealizedProfitEquity, 	"Total_Equity_Booked"=>$totalBookedProfitEquity, 
										"Total_Debt_Unrealized"=>$totalUnRealizedProfitDebt,  		"Total_Debt_Booked"=>$totalBookedProfitDebt,  	
										"Total_Hybrid_Unrealized"=>$totalUnRealizedProfitHybrid,  	"Total_Hybrid_Booked"=>$totalBookedProfitHybrid, 
										"Total_Gold_Unrealized"=>$totalUnRealizedProfitGold,  		"Total_Gold_Booked"=>$totalBookedProfitGold,
										"Total_Unrealized"=>$totalUnRealizedProfit, 				"Total_Booked"=>$totalBookedProfit,
										"Total_Equity_Return"=>$totalXirrEquity, 					"Total_Debt_Return"=>$totalXirrDebt, 
										"Total_Hybrid_Return"=>$totalXirrHybrid, 					"Total_Gold_Return"=>$totalXirrGold, 
										"Total_Holding_Return"=>$xirrReturnTotal,
										"Equity_Asset_Share"=>$equityAssetShare	, 					"Debt_Asset_Share"=>$debtAssetShare , 
										"Hybrid_Asset_Share"=>$hybridAssetShare	, 					"Gold_Asset_Share"=>$goldAssetShare ,
										"Total_Current_Value"=>$totalHoldingCurrentValue, 			"Total_Cost_Value"=>$totalHoldingCostValue
									);
	
		
		return $totalHoldings;
	}


	public function getMFHoldingDataTotal( ) 
	{
		
		$totalHoldingsMF 					= 	array();
		$totalXirrEquity				=	0;
		$totalXirrDebt					=	0;
		$totalXirrHybrid				=	0;
		$totalXirrGold					=	0;
		$xirrReturnTotal				=	0;
	
		$totalEquityCostValue			=	number_format($this->m_totalEquiyCostValueMF, 2, '.', ',');
		$totalDebtCostValue				=	number_format($this->m_totalDebtCostValueMF, 2, '.', ',');
		$totalHybridCostValue			=	number_format($this->m_totalHybridCostValueMF, 2, '.', ',');
		$totalGoldCostValue				=	number_format($this->m_totalGoldCostValueMF, 2, '.', ',');
		
		$totalEquityCurrentValue		=	number_format($this->m_totalEquiyCurrentValueMF, 2, '.', ',');
		$totalDebtCurrentValue			=	number_format($this->m_totalDebtCurrentValueMF, 2, '.', ',');
		$totalHybridCurrentValue		=	number_format($this->m_totalHybridCurrentValueMF, 2, '.', ',');
		$totalGoldCurrentValue			=	number_format($this->m_totalGoldCurrentValueMF, 2, '.', ',');
	
		$totalUnRealizedProfitEquity	=	number_format($this->m_totalEquiyUnRealizedProfitMF, 2, '.', ',');
		$totalUnRealizedProfitDebt		=	number_format($this->m_totalDebtUnRealizedProfitMF, 2, '.', ',');
		$totalUnRealizedProfitHybrid	=	number_format($this->m_totalHybridUnRealizedProfitMF, 2, '.', ',');
		$totalUnRealizedProfitGold		=	number_format($this->m_totalGoldUnRealizedProfitMF, 2, '.', ',');
		
		$totalBookedProfitEquity		=	number_format($this->m_totalEquiyBookedProfitMF, 2, '.', ',');
		$totalBookedProfitDebt			=	number_format($this->m_totalDebtBookedProfitMF, 2, '.', ',');
		$totalBookedProfitHybrid		=	number_format($this->m_totalHybridBookedProfitMF, 2, '.', ',');
		$totalBookedProfitGold			=	number_format($this->m_totalGoldBookedProfitMF, 2, '.', ',');
		
		
		if($this->m_totalEquityCashFlowMF)
			$totalXirrEquity				=	$this->m_xirrObj->XIRRAdpro($this->m_totalEquityCashFlowMF, $this->m_totalEquityTimePeriodMF, 0.1) * 100;
		
		if($this->m_totalDebtCashFlowMF)
			$totalXirrDebt					=	$this->m_xirrObj->XIRRAdpro($this->m_totalDebtCashFlowMF, $this->m_totalDebtTimePeriodMF, 0.1) * 100;
		if($this->m_totalHybridCashFlowMF)
			$totalXirrHybrid			=	$this->m_xirrObj->XIRRAdpro($this->m_totalHybridCashFlowMF, $this->m_totalHybridTimePeriodMF, 0.1) * 100;
		if($this->m_totalGoldCashFlowMF)
			$totalXirrGold				=	$this->m_xirrObj->XIRRAdpro($this->m_totalGoldCashFlowMF, $this->m_totalGoldTimePeriodMF, 0.1) * 100;
		
		
		$totalXirrEquity				=	number_format($totalXirrEquity, 2, '.', ',');
		$totalXirrDebt					=	number_format($totalXirrDebt, 2, '.', ',');
		$totalXirrHybrid				=	number_format($totalXirrHybrid, 2, '.', ',');
		$totalXirrGold					=	number_format($totalXirrGold, 2, '.', ',');
		
		$totalHoldingsMF[] = 		array(  "Total_Equity_Cost"=>$totalEquityCostValue, 				"Total_Debt_Cost"=>$totalDebtCostValue, 
										"Total_Hybrid_Cost"=>$totalHybridCostValue,  				"Total_Gold_Cost"=>$totalGoldCostValue, 
										"Total_Equity_Curr_Value"=>$totalEquityCurrentValue, 		"Total_Debt_Curr_Value"=>$totalDebtCurrentValue, 
										"Total_Hybrid_Curr_Value"=>$totalHybridCurrentValue,  		"Total_Gold_Curr_Value"=>$totalGoldCurrentValue,
										"Total_Equity_Unrealized"=>$totalUnRealizedProfitEquity, 	"Total_Equity_Booked"=>$totalBookedProfitEquity, 
										"Total_Debt_Unrealized"=>$totalUnRealizedProfitDebt,  		"Total_Debt_Booked"=>$totalBookedProfitDebt,  	
										"Total_Hybrid_Unrealized"=>$totalUnRealizedProfitHybrid,  	"Total_Hybrid_Booked"=>$totalBookedProfitHybrid, 
										"Total_Gold_Unrealized"=>$totalUnRealizedProfitGold,  		"Total_Gold_Booked"=>$totalBookedProfitGold,
										"Total_Equity_Return"=>$totalXirrEquity, 					"Total_Debt_Return"=>$totalXirrDebt, 
										"Total_Hybrid_Return"=>$totalXirrHybrid, 					"Total_Gold_Return"=>$totalXirrGold
									);
		return $totalHoldingsMF;
	}


	public function getAssetShare( ) 
	{
		$assetShare 				= array();
		
		$equityAssetShare	=	0;
		$debtAssetShare		=	0;
		$hybridAssetShare	=	0;
		$goldAssetShare		=	0;
		
		$this->m_totalCurrentValue			=	$this->m_totalCurrentValueEquity + $this->m_totalCurrentValueDebt + $this->m_totalCurrentValueHybrid + $this->m_totalCurrentValueGold;
		
		if ($this->m_totalCurrentValueEquity >0)
		{
			$equityAssetShare				=	($this->m_totalCurrentValueEquity * 100 ) / $this->m_totalCurrentValue ;
		}
		if ($this->m_totalCurrentValueDebt >0)
		{
			$debtAssetShare					=	($this->m_totalCurrentValueDebt * 100 ) / $this->m_totalCurrentValue ;
		}
		if ($this->m_totalCurrentValueHybrid >0)
		{
			$hybridAssetShare				=	($this->m_totalCurrentValueHybrid * 100 ) / $this->m_totalCurrentValue ;
		}
		if ($this->m_totalCurrentValueGold >0)
		{
			$goldAssetShare					=	($this->m_totalCurrentValueGold * 100 ) / $this->m_totalCurrentValue ;
		}
		
		$assetShare = array(	"Equity_Share"=>$equityAssetShare, 				
								"Debt_Share"=>$debtAssetShare,
								"Hybrid_Share"=>$hybridAssetShare, 				
								"Gold_Share"=>$goldAssetShare
								);

		
		return $assetShare;
	}

    public function getUnitData() 
	{
        return $this->m_unitData;
    }

    public function getTotalGoldCostValue() 
	{
        return $this->m_totalCostValueGold;
    }

    public function getTotalGoldCurrentValue() 
	{
        return $this->m_totalCurrentValueGold;
    }

    public function getTotalUnRealizedProfitGold() 
	{
	        return $this->m_totalUnRealizedProfitGold;
    }
    
    public function getTotalBookedProfitGold() 
	{
        return $this->m_totalBookedProfitGold;
    }
	
	public function getTotalCashFlows(): array {
    return array_merge(
        $this->m_cashFlowEquity, $this->m_cashFlowDebt,
        $this->m_cashFlowHybrid, $this->m_cashFlowGold
    );
	
	}
	public function getTotalTimePeriods(): array {
		return array_merge(
			$this->m_timePeriodEquity, $this->m_timePeriodDebt,
			$this->m_timePeriodHybrid, $this->m_timePeriodGold
		);
	}
	public function getEquityCashFlows(): array  { return $this->m_cashFlowEquity; }
	public function getEquityTimePeriods(): array { return $this->m_timePeriodEquity; }
	
}

?>