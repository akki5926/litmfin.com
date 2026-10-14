<?php
require_once('auth_guard.php');
 

	require_once('database.php');
	$db = new MYSQLDB();






	$Risk_id = $_REQUEST['Risk_id'];
	$invest_type = $_REQUEST['invest_type'];
	$fund_house = $_REQUEST['fund_house'];
	$fund_cat = $_REQUEST['fund_cat'];
	$scheme = $_REQUEST['scheme'];
	$allocation = $_REQUEST['allocation'];
	$rational = $_REQUEST['rational'];

	$res = $db->insertRec($Risk_id,$invest_type,$fund_house,$fund_cat,$scheme,$allocation,$rational);
	if($res >=1){
		echo "Data Inserted Successfully";
	}



	// $result = $db ->getallinvestType($_REQUEST['invest']);
	// //var_dump($result);
	// echo "<option value='0'>select</option>";
	// 	foreach($result as $row){
		
	// 		echo "<option value='".$row["fund_search"]."'> ".$row['fund_cat_name']." </option>";		
		
	// 	// echo "<option value='".$row['web_id']."'> ".$row['web_portal_name']." </option>";
	// }
 
?>