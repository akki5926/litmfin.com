<?php
require_once('auth_guard.php');


class MutualFundHolding 
{
    
	private 	$units 				= 	array(); 
    private 	$cost 				= 	0;
	private 	$realizedprofits	=	0;
	private 	$unrealizedprofits	=	0;
	public 		$amcCode 			=	 "";
	public 		$fundCode 			= 	"";
	public 		$holdingData 		= 	[];
	private 	$costPerUnit		= 0;
	/*
    public function setFundHouse($amc) 
	{
		this->amcCode	=	$amc;
    }
	*/
	
    public function addTransaction($type, $units, $amount, $date) 
	{
        $transaction = [
            'type' => $type,
            'units' => $units,
            'amount' => $amount,
            'date' => $date
        ];

        $this->calculateHolding($transaction);
		//echo "<br>"."Txn Type = ".$type;
		//echo "<br>"."Txn Units = ".$units;
       // $this->printHolding();
    }

    private function calculateHolding($transaction) 
	{
        if ($transaction['type'] === 'buy') 
		{
            $this->addBuyTransaction($transaction);
        } 
		elseif ($transaction['type'] === 'sell') 
		{
            $this->addSellTransaction($transaction);
        }
    }

    private function addBuyTransaction($transaction) 
	{
		/*
		echo " Total units before adding buy transactione =   ".$this->getNetHoldingUnits() ;
		echo "<br>";
		*/	
			
        $this->units[] = [
            'units' => $transaction['units'],
            'cost' 	=> (abs($transaction['units']) > 0.000001) ? ($transaction['amount'] / $transaction['units']) : 0
        ];

        $this->cost += $transaction['amount'];
		
		/*
		echo "<br>";
		echo " Adding buy transaction: Buy units =   ". $transaction['units'];
		echo "<br>";
		echo " Adding buy transaction: buy date  =   ".$transaction['date'];
		echo "<br>";
		echo " Total units after adding buy transactione =   ".$this->getNetHoldingUnits() ;
		echo "<br>";
		*/
		
    }
	
	private function addSellTransaction($transaction) 
	{
		$unitsToSell 		= 	$transaction['units'];
        
        if ($unitsToSell <= 0.000001) {
            // Dividend payouts are collected by the main engine as $payoutRecvd. Do not mix into realized profit.
            return;
        }
        
		$perUnitSellPrice	=  	$transaction['amount'] / $unitsToSell;
		$saledate			=  	$transaction['date'];
		
		/*
		echo "<br>";
		echo " Adding sell transaction: Sell units =   ". $unitsToSell;
		echo "<br>";
		echo " Adding sell transaction: Sell date =   ".$saledate;
		echo "<br>";
		echo " Total units before adding sell transactione =   ".$this->getNetHoldingUnits() ;
		echo "<br>";
		*/
		
		while ($unitsToSell > 0 && !empty($this->units)) 
		{
			//$oldestUnit 		= reset($this->units);
			//$oldestUnitQuantity = $oldestUnit['units'];
			
			reset($this->units); // Reset array pointer to the first unit
			$oldestUnitIndex 	= key($this->units);
			$oldestUnit 		= &$this->units[$oldestUnitIndex];

			if (!isset($oldestUnit['units'])) {
				// If the 'units' key is not set, that means the unit was fully sold in a previous transaction
				unset($this->units[$oldestUnitIndex]);
				// 28-07-2023
				//$this->units = array_values($this->units);
				//
				continue;
			}

			$oldestUnitQuantity = $oldestUnit['units'];
			
			//echo "oldestUnitQuantity: " . $oldestUnitQuantity; echo "<br>";
			//	echo "unitsToSell: " . $unitsToSell; echo "<br>";
			
			
			$perUnitBuyPrice	=	$oldestUnit['cost'] ;  /// $oldestUnit['units'] ;
			
			if ($unitsToSell >= $oldestUnitQuantity) 
			{
				$trxnProfit				= 0;
				$trxnProfit				=	$oldestUnitQuantity * ($perUnitSellPrice - $perUnitBuyPrice) ;
				$this->realizedprofits	+=	$trxnProfit;
				
				//$this->realizedprofits	+=	($oldestUnitQuantity * $perUnitSellPrice) - ($oldestUnitQuantity * $perUnitBuyPrice);
				
				
				$this->cost 	-= $oldestUnit['cost'] * $oldestUnitQuantity;
				$unitsToSell 	-= $oldestUnitQuantity;
				
			//	echo "this->cost: " . $this->cost; echo "<br>";
			//	echo "unitsToSell: " . $unitsToSell; echo "<br>";
				
				unset($this->units[$oldestUnitIndex]);
				/*
				echo "<br>";
				echo " Per unit Buying price = ".$perUnitBuyPrice."  Per unit Selling Price = ".$perUnitSellPrice;
				echo "<br>";
				echo " Units being sold now = ".$oldestUnitQuantity;
				echo "<br>";
				echo "  Profit in this trxn-If loop =   ".$trxnProfit."  Total Realized profit till date= ";
				echo $this->realizedprofits;
				echo "<br>";
				*/
			   // next($this->units); // Move the array pointer to the next unit
			}
			else 
			{
				// Partial sell
				//	echo "Partial sell"; echo "<br>";
				//	echo "oldestUnitQuantity: " . $oldestUnitQuantity; echo "<br>";
				
				$perUnitBuyPrice	=	$oldestUnit['cost'] ;  /// $oldestUnit['units'] ;
				
				/*
				echo "<br>";
				echo "Partial Units to sell: ".$unitsToSell; echo "<br>";
				echo "oldestUnit: " . $oldestUnit['units']; echo "<br>";
				*/
				
				$trxnProfit				= 0;
				$trxnProfit				=	$unitsToSell * ($perUnitSellPrice - $perUnitBuyPrice) ;
				$this->realizedprofits	+=	$trxnProfit;
				
				$oldestUnit['units'] -= $unitsToSell;
				
				/*
				echo "oldestUnit after subtracting: " . $oldestUnit['units']; echo "<br>";
				echo "oldestUnit cost  : " . $oldestUnit['cost']; echo "<br>";
				
			//	echo "oldestUnit after subtracting: " . $oldestUnit['units']; echo "<br>";
				
				echo "<br>";
				echo " Per unit Buying price = ".$perUnitBuyPrice."  Per unit Selling Price = ".$perUnitSellPrice;
				echo "<br>";
				echo " Units being sold now = ".$unitsToSell;
				echo "<br>";
				echo "  Profit in this trxn =   ".$trxnProfit."  Total Realized profit till date= ";
				echo $this->realizedprofits;
				echo "<br>";
				*/
				$this->cost -= $oldestUnit['cost'] * $unitsToSell;
				$unitsToSell = 0;
				
				// No need to remove the unit as it still has some remaining quantity
			}
		}
		
		//if (empty($this->units))
		//if ($this->units)	
		//{
		//	echo " this->units is not an empty array ";
		//	echo "<br>";
		//}
			
			// Update cost per unit after sell
			if ($this->getNetHoldingUnits() > 0.000001) 
			{
				$this->costPerUnit = $this->cost / $this->getNetHoldingUnits();
			} else 
			{
				$this->costPerUnit = 0; // All units were sold, so cost per unit is 0
			}
		
		// 30-07-2023
		/*
		echo "<br>";	
		echo "Net Holding Units After sell : "; 
		$this->printHolding();
		*/
		// 30-07-2023
	}

	
	
    public function printHolding() 
	{
		echo "<br>"."-------------------------" . PHP_EOL;echo "<br>";
        echo "Net Holding Units: " . $this->getNetHoldingUnits(); echo "<br>";
        echo "Cost of Remaining Holding Units: $" . number_format($this->getCostOfRemainingUnits(), 2) . PHP_EOL;echo "<br>";
        echo "Cost per Unit of Remaining Holding: $" . number_format($this->getCostPerUnitOfRemainingUnits(), 2) . PHP_EOL;echo "<br>";
        echo "-------------------------" . PHP_EOL;echo "<br>";
    }
	
	public function reInitializeHolding() 
	{
		//$units = [];
		unset($this->units);
		$this->cost = 0;
		$this->realizedprofits = 0;
		
		//foreach ($this->units as $unit) 
		/*
		foreach ($this->units as $unit ) 
		{
            unset($this->units); // = $unit['units'];
			//$netHolding = $unit['units'];
			$this->cost = 0;
			
        }
		*/
		
		//unset($units);
		//$cost = 0;
	
	}
	

	public function getNetHoldingUnits() 
	{
        $netHolding = 0;

        // Check if the property $this->units is set and is an array.
        if (isset($this->units) && is_array($this->units)) 
		{
            foreach ($this->units as $unit) 
			{
                $netHolding += $unit['units'];
            }
        }
		/*
		else
		{
			// Error. No matching buy data found for current sell order.
			// Return -1 to show as error.
				
			$netHolding		=	-1;
		}
		*/
        return $netHolding;
    }
	
    public function getCostOfRemainingUnits() {
        $totalCost = 0;
        foreach ($this->units as $unit) 
		{
            $totalCost += $unit['cost'] * $unit['units'];
        }
        return $totalCost;
    }
	
	public function getCostPerUnitOfRemainingUnits() {
        $netHoldingUnits = $this->getNetHoldingUnits();
        if (abs($netHoldingUnits) <= 0.000001) {
            return 0;
        }
        return $this->getCostOfRemainingUnits() / $netHoldingUnits;
    }

	public function getrealizedprofits() 
	{
        return $this->realizedprofits;
    }

}

/*
// Example usage:
$holdingInfo = new MutualFundHolding();

$holdingInfo->addTransaction('buy', 100, 2000, '2023-06-01');
$holdingInfo->addTransaction('buy', 50, 1200, '2023-06-15');
$holdingInfo->addTransaction('sell', 120, 2400, '2023-06-20');
$holdingInfo->addTransaction('buy', 80, 1800, '2023-06-25');
$holdingInfo->addTransaction('sell', 50, 1400, '2023-06-28');

*/
//echo "<br>";
/*
		echo "<br>";
		echo $net_holding;
		echo "<br>";
		echo $net_effective_price;echo "<br>";
		echo $realized_profit;echo "<br>";
		echo $unrealized_profit;echo "<br>";
*/		

?>