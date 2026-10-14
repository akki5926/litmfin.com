<?php
require_once('auth_guard.php');
 
	require_once('database.php');
	$db = new MYSQLDB();
	$method = $_REQUEST['method'];
	switch ($method) {
	case "addOtherInv":
	
		//echo "in fn";
		$invest_type = $_REQUEST['invest_type'];
		$asset_class = $_REQUEST['asset_class'];
		$invName = $_REQUEST['invName'];
		$issuer = $_REQUEST['issuer'];
		$rational = $_REQUEST['rational'];
		$slno = $_REQUEST['slno'];
		$action = $_REQUEST['action'];

		$res = $db->insertRecomadation($invest_type,$asset_class,$invName,$issuer,$rational,$slno,$action);
		echo $res;
		//if($res >=1){
		//	echo "Data Inserted Successfully";
		//}
	break;
	case "getRecData":
	
		$result_A = $db->getRecommendationOtherInvData();
		echo json_encode($result_A);
		break;
	case "getInvDataBySlno" :
		$slno = $_REQUEST['slno'];
		$result_A = $db->getRecOtherInvDataByslno($slno);
		echo json_encode($result_A);
		break;
	case "del_rec" :
		$slno = $_REQUEST['slno'];
		$iname = $_REQUEST['iname'];
		$result_A = $db->delRecOtherInvDataByslno($slno,$iname);
		echo json_encode($result_A);
		break;
	}
?>