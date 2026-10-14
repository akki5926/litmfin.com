<?php
require_once('auth_guard.php');

$method = "";
if (isset($_REQUEST['method'])) $method = $_REQUEST['method'];
//echo "method=$method";
switch ($method) {
  case "getPmsNavData":
    if (isset($_REQUEST['UCC'])) {
      $UCC = $_REQUEST["UCC"];
      
      require_once('database.php');
      $db = new MYSQLDB();
      $pms_Nav_data = $db->getPmsNavData( $UCC);
      echo json_encode($pms_Nav_data);
    }
    break;
  case "updatePmsNav":
		updatePmsNav();
		break;
  case "getPmsNavDataBySlno":
		getPmsNavDataBySlno();
		break;
  case "del_pms_nav":
		del_pms_nav();
		break;
  case "getPmsNames":
		getPmsNames();
		break;
  case "getAifNavData":
    if (isset($_REQUEST['UCC'])) {
      $UCC = $_REQUEST["UCC"];
      
      require_once('database.php');
      $db = new MYSQLDB();
      $aif_Nav_data = $db->getAifNavData( $UCC);
      echo json_encode($aif_Nav_data);
    }
    break;
  case "updateAifNav":
		updateAifNav();
		break;
  case "getAifNavDataBySlno":
		getAifNavDataBySlno();
		break;
  case "del_aif_nav":
		del_aif_nav();
		break;
  case "getAifNames":
		getAifNames();
		break;
  case "getAifPayoutData":
    if (isset($_REQUEST['UCC'])) {
      $UCC = $_REQUEST["UCC"];
      
      require_once('database.php');
      $db = new MYSQLDB();
      $aif_payout_data = $db->getAifPayoutData( $UCC);
      echo json_encode($aif_payout_data);
    }
    break;
  case "updateAifPayout":
		updateAifPayout();
		break;
  case "getAifPayoutDataBySlno":
		getAifPayoutDataBySlno();
		break;
  case "del_aif_payout":
		del_aif_payout();
		break;
  case "searchMFNav":
        if (isset($_REQUEST['q'])) {
            require_once('database.php');
            $db = new MYSQLDB();
            $results = $db->searchNAVRecords($_REQUEST['q'], 100);
            
            // Format result properly depending on whether it's an array or mysqli_result
            $rows = array();
            if (is_array($results)) {
                $rows = $results;
            } elseif ($results && $results instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($results)) {
                    $rows[] = $row;
                }
                mysqli_free_result($results);
            }
            
            // Clean strings to prevent json_encode failure due to bad charset
            array_walk_recursive($rows, function(&$item){
                if (is_string($item)) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
                }
            });
            
            echo json_encode($rows);
        }
        break;

}

function updatePmsNav()
{
	if(isset($_REQUEST["tp"]))
	{
		$investmentType = $_REQUEST["tp"];
		$UCC 			= "";
		$name 			= "";
		$navAmount 		= 0;
		$navDate 		= "";
		$action 		= "add";
		$slno 			= 0;
		

		if(isset($_REQUEST["UCC"])) 			$UCC 		= $_REQUEST["UCC"];
		if(isset($_REQUEST["name"]))			$name 		= $_REQUEST["name"];
		if(isset($_REQUEST["current_value"]))   $navAmount 	= $_REQUEST["current_value"];
		if(isset($_REQUEST["current_date"]))  	$navDate 	= $_REQUEST["current_date"];
		if(isset($_REQUEST["action"]))          $action 	= $_REQUEST["action"];
		if(isset($_REQUEST["slno"]))            $slno 		= $_REQUEST["slno"];

		require_once('database.php');
		$db = new MYSQLDB();
		$result = $db->updatePmsNav($UCC,$name,$navDate,$navAmount,$action,$slno);

		echo json_encode($result);
	}
}

function getPmsNavDataBySlno()
{
	//echo "in fn getPmsNav";
		// Check if the required parameters are provided
		if ( isset($_REQUEST['nav_slno'])) {
			$nav_slno = $_REQUEST['nav_slno'];
			//echo "in fn getPmsNav";
			require_once('database.php');
			$db = new MYSQLDB();
			$navData = $db ->getPmsNavDataBySlno($nav_slno);
			
			// Return the investment data as JSON
			header('Content-Type: application/json');
			echo json_encode($navData);
			exit;
		}
}

function del_pms_nav()
{
	$investmentId 	= $_REQUEST["investmentId"];
	$iname 			= $_REQUEST["iname"];
	require_once('database.php');
	$db = new MYSQLDB();
	$result = $db->removePmsNav($investmentId,$iname);
	echo json_encode($result);
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



function getAifNavDataBySlno()
{
	//echo "in fn getAifNav";
		// Check if the required parameters are provided
		if ( isset($_REQUEST['nav_slno'])) {
			$nav_slno = $_REQUEST['nav_slno'];
			//echo "in fn getAifNav";
			require_once('database.php');
			$db = new MYSQLDB();
			$navData = $db ->getAifNavDataBySlno($nav_slno);
			
			// Return the investment data as JSON
			header('Content-Type: application/json');
			echo json_encode($navData);
			exit;
		}
}

function del_aif_nav()
{
	$investmentId 	= $_REQUEST["investmentId"];
	$iname 			= $_REQUEST["iname"];
	require_once('database.php');
	$db = new MYSQLDB();
	$result = $db->removeAifNav($investmentId,$iname);
	echo json_encode($result);
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
function updateAifPayout()
{
	if(isset($_REQUEST["tp"]))
	{
		$investmentType = $_REQUEST["tp"];
		$UCC 			= "";
		$name 			= "";
		$amount 		= 0;
		$date 		= "";
		$action 		= "add";
		$slno 			= 0;
		$remarks 		= "";
		

		if(isset($_REQUEST["UCC"])) 			$UCC 	= $_REQUEST["UCC"];
		if(isset($_REQUEST["name"]))			$name 	= $_REQUEST["name"];
		if(isset($_REQUEST["payout_amount"]))	$amount = $_REQUEST["payout_amount"];
		if(isset($_REQUEST["payout_date"]))		$date 	= $_REQUEST["payout_date"];
		if(isset($_REQUEST["action"]))          $action = $_REQUEST["action"];
		if(isset($_REQUEST["slno"]))            $slno 	= $_REQUEST["slno"];
		if(isset($_REQUEST["remarks"]))         $remarks= $_REQUEST["remarks"];

		require_once('database.php');
		$db = new MYSQLDB();
		$result = $db->updateAifPayout($UCC,$name,$date,$amount,$action,$slno,$remarks);

		echo json_encode($result);
	}
}
function getAifPayoutDataBySlno()
{
	//echo "in fn getAifNav";
		// Check if the required parameters are provided
		if ( isset($_REQUEST['slno'])) {
			$slno = $_REQUEST['slno'];
			//echo "in fn getAifNav";
			require_once('database.php');
			$db = new MYSQLDB();
			$navData = $db ->getAifPayoutDataBySlno($slno);
			
			// Return the investment data as JSON
			header('Content-Type: application/json');
			echo json_encode($navData);
			exit;
		}
}
function del_aif_payout()
{
	$investmentId 	= $_REQUEST["investmentId"];
	$iname 			= $_REQUEST["iname"];
	require_once('database.php');
	$db = new MYSQLDB();
	$result = $db->removeAifPayout($investmentId,$iname);
	echo json_encode($result);
}

function updateAifNav()
{
	//var data = "method=" + method + "&tp=aif" + "&UCC=" + UCC + "&name=" + name + "&current_value=" + currentValue + "&current_date=" + CurrentDate +  "&action=" + action + "&slno=" + slno;
	if(isset($_REQUEST["tp"]))
	{
		$investmentType = $_REQUEST["tp"];
		$UCC 			= "";
		$name 			= "";
		$navAmount 		= 0;
		$navDate 		= "";
		$action 		= "add";
		$slno 			= 0;
		

		if(isset($_REQUEST["UCC"])) 			$UCC 		= $_REQUEST["UCC"];
		if(isset($_REQUEST["name"]))			$name 		= $_REQUEST["name"];
		if(isset($_REQUEST["current_value"]))   $navAmount 	= $_REQUEST["current_value"];
		if(isset($_REQUEST["current_date"]))  	$navDate 	= $_REQUEST["current_date"];
		if(isset($_REQUEST["action"]))          $action 	= $_REQUEST["action"];
		if(isset($_REQUEST["slno"]))            $slno 		= $_REQUEST["slno"];

		require_once('database.php');
		$db = new MYSQLDB();
		$result = $db->updateAifNav($UCC,$name,$navDate,$navAmount,$action,$slno);

		echo json_encode($result);
	}
}

?>
