<?php
require_once('auth_guard.php');

$method = "";
if (isset($_REQUEST['method'])) $method = $_REQUEST['method'];
//echo "method=$method";
switch ($method) {
  case "isUCCExist": 
    if (isset($_REQUEST['UCC'])){
      $UCC = $_REQUEST['UCC'];
      require_once('database.php');
      $db = new MYSQLDB();
      $response = $db->isUCCExists($UCC);
      // Send the response as a JSON object
      echo json_encode($response);
    }
    break;
  case "getUCCName": 
    if (isset($_REQUEST['UCC'])){
      $UCC = $_REQUEST['UCC'];
      require_once('database.php');
      $db = new MYSQLDB();
      $response = $db->getUCCName($UCC);
      // Send the response as a JSON object
      echo json_encode($response);
    }
    break;

  case "getInvestData":
    if (isset($_REQUEST['UCC'])) {
      $UCC = $_REQUEST["UCC"];
      $tp = $_REQUEST["tp"];
      
      require_once('database.php');
      $db = new MYSQLDB();
      $bond_data = $db->getInvestmentData($tp, $UCC);
      echo json_encode($bond_data);
    }
    break;
  case "addInvest":
		addInvest();
		break;
  case "getInvestmentData":
		getInvestmentData();
		break;
  case "del_invest":
		del_invest();
		break;
  case "updatePayout":
		updatePayout();
		break;
  case "getBondNames":
		getBondNames();
		break;
  case "getBondDetails":
		getBondDetails();
		break;
  case "getPmsNames":
		getPmsNames();
		break;
  case "getAifNames":
		getAifNames();
		break;
}

function addInvest()
{
	if(isset($_REQUEST["tp"]))
	{
		$investmentType = $_REQUEST["tp"];

		$UCC 			= "";
		$name 			= "";
		$issuer 			= "";
		$assetClass 	= "";
		$transactionType= "";
		$transactionAmount = 0;
		$faceValue 		= 0;
		$interestRate	= 0;
		$units 			= 0;
		$transactionRate 	= 0;
		$bondPayoutDate = "";
		$transactionDate 	= "";
		$currentRate 	= 0;
		$currentValue 	= 0;
		$currentDate 	= "";
		$payoutFrequency = "";
		$cagr 			= 0;
		$Mdate			= "";
		$payoutAmt 		= 0;
		$action 		= "add";
		$slno 			= 0;

		if(isset($_REQUEST["UCC"])) 			$UCC 			= $_REQUEST["UCC"];
		if(isset($_REQUEST["name"]))			$name 			= $_REQUEST["name"];
		if(isset($_REQUEST["issuer"]))			$issuer 			= $_REQUEST["issuer"];
		if(isset($_REQUEST["assetclass"]))      $assetClass 	= $_REQUEST["assetclass"];
		if(isset($_REQUEST["tt"]))      		$transactionType = $_REQUEST["tt"];
		if(isset($_REQUEST["transaction_amount"]))	$transactionAmount 		= $_REQUEST["transaction_amount"];
		if(isset($_REQUEST["face_value"]))      $faceValue 		= $_REQUEST["face_value"];
		if(isset($_REQUEST["interest_rate"]))   $interestRate	= $_REQUEST["interest_rate"];
		if(isset($_REQUEST["units"]))           $units 			= $_REQUEST["units"];
		if(isset($_REQUEST["transaction_rate"]))  $transactionRate 	= $_REQUEST["transaction_rate"];
		if(isset($_REQUEST["transaction_date"]))  $transactionDate 	= $_REQUEST["transaction_date"];
		if(isset($_REQUEST["payout_date"]))  $bondPayoutDate 	= $_REQUEST["payout_date"];
		if(isset($_REQUEST["current_rate"]))    $currentRate 	= $_REQUEST["current_rate"];
		if(isset($_REQUEST["current_value"]))   $currentValue 	= $_REQUEST["current_value"];
		if(isset($_REQUEST["current_date"]))  $currentDate 	= $_REQUEST["current_date"];
		if(isset($_REQUEST["payoutFrequency"]))	$payoutFrequency = $_REQUEST["payoutFrequency"];
		if(isset($_REQUEST["cagr"]))            $cagr 			= $_REQUEST["cagr"];
		if(isset($_REQUEST["Mdate"]))       	$Mdate			= $_REQUEST["Mdate"];
		if(isset($_REQUEST["payoutamt"]))       $payoutAmt 		= $_REQUEST["payoutamt"];
		if(isset($_REQUEST["action"]))          $action 		= $_REQUEST["action"];
		if(isset($_REQUEST["slno"]))            $slno 			= $_REQUEST["slno"];

		require_once('database.php');
		$db = new MYSQLDB();

		//print "in add_bond.php : UCC:" . $UCC." , ".$investmentType." , ".$name." , ".$costValue." , ".$interestRate." , ".$units." , ".$purchasedRate." , ".$purchasedDate." , ".$currentRate." , ".$currentValue." , ".$interestPayout." , ".$cagr ;
		//echo "<br><br>in add_bond.php : currentDate:" . $currentDate. " purchasedDate:".$purchasedDate.", bondPayoutDate:".$bondPayoutDate.", Mdate:".$Mdate."|";

		$result = $db->addInvestment($UCC,$investmentType,$name,$issuer,$assetClass,$faceValue,$transactionType,$transactionAmount,$interestRate,$units,$transactionRate,$bondPayoutDate,$transactionDate,$currentDate,$currentRate,$currentValue,$payoutFrequency,$cagr,$Mdate,$payoutAmt,$action,$slno);

		echo json_encode($result);
	}
}

function getInvestmentData()
{
	//echo "in fn getInvestmentData";
		// Check if the required parameters are provided
		if (isset($_REQUEST['investmentType']) && isset($_REQUEST['investmentId'])) {
			$investmentType = $_REQUEST['investmentType'];
			$investmentId = $_REQUEST['investmentId'];
			//echo "in fn getInvestmentData";
			require_once('database.php');
			$db = new MYSQLDB();
			$investmentData = $db ->getInvestmentDataById($investmentType,$investmentId);
			
			// Return the investment data as JSON
			header('Content-Type: application/json');
			echo json_encode($investmentData);
			exit;
		}
}

function del_invest()
{
	$investmentId 	= $_REQUEST["investmentId"];
	$iname 			= $_REQUEST["iname"];
	require_once('database.php');
	$db = new MYSQLDB();
	$result = $db->removeInvestment($investmentId,$iname);
	echo json_encode($result);
}
function updatePayout()
{
	$UCC 	= $_REQUEST["UCC"];
	require_once('database.php');
	$db = new MYSQLDB();
	$result = $db->updatePayout($UCC);
	echo json_encode($result);
}
function getBondNames()
{
	if (isset($_REQUEST['asset_class'])) {
		$assetClass = $_REQUEST['asset_class'];

		require_once('database.php');
		$db = new MYSQLDB();
		$result = $db->getOtherInvestmentsByAssetClass($assetClass);

		// Generate and return the HTML options
		$options = '<option value="">Select</option>';
		if($result != false) {
			foreach ($result as $row) {
				if (!empty($row['investment_name'])) {
					$name = htmlspecialchars($row['investment_name'], ENT_QUOTES);
					$options .= '<option value="' . $name . '">' . $name . '</option>';
				}
			}
		}
		
		echo $options;
	}
}
function getBondDetails()
{
	if (isset($_REQUEST['bond_name'])) {
		$bond_name = $_REQUEST['bond_name'];

		require_once('database.php');
		$db = new MYSQLDB();
		// Look up in Bond Master (mtb_other_investment) first
		$result = $db->getBondDetails($bond_name);
		// Fallback to customer transactions table (tb_investments) if not found in master
		if ($result == false) {
			$result = $db->getOtherInvestmentsByName($bond_name);
		}

		if ($result != false) {
			$result['coupon_rate'] = isset($result['interest_rate']) ? $result['interest_rate'] : '';
			$redDate = isset($result['redemption_date']) ? $result['redemption_date'] : (isset($result['maturity_date']) ? $result['maturity_date'] : '');
			if (!empty($redDate)) {
				$ts = strtotime(str_replace('/', '-', $redDate));
				if ($ts !== false) {
					$result['redemption_date'] = date('d/m/Y', $ts);
				} else {
					$result['redemption_date'] = $redDate;
				}
			}
		} else {
			$result = [];
		}

		echo json_encode($result);
	}
}
function getPmsNames()
{
	if (isset($_GET['UCC'])) {
		$UCC = $_GET['UCC'];
		require_once('database.php');
		$db = new MYSQLDB();
		$result = $db->getPmsByUCC($UCC);

		// Generate and return the HTML options
		$options = '';
		if($result != false)
		foreach ($result as $row) {
			$options .= '<option value="' . $row['investment_name'] . '">' . $row['investment_name'] . '</option>';
		}
		
		echo $options;
	}

}
function getAifNames()
{
	if (isset($_GET['UCC'])) {
		$UCC = $_GET['UCC'];
		require_once('database.php');
		$db = new MYSQLDB();
		$result = $db->getAifByUCC($UCC);

		// Generate and return the HTML options
		$options = '';
		if($result != false)
		foreach ($result as $row) {
			$options .= '<option value="' . $row['investment_name'] . '">' . $row['investment_name'] . '</option>';
		}
		
		echo $options;
	}

}
?>