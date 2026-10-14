<?php
require_once('auth_guard.php');
 

	require_once('database.php');
	$db = new MYSQLDB();


	$result = $db ->getAllFundHouseSchemeNames($_REQUEST['amc'],$_REQUEST['category']);
	if($result){

		echo "<option value='0'>select</option>";
			foreach($result as $row){
		
				echo "<option value='".$row["product_code"]."'> ".$row['product_long_name']." </option>";		
		
		// echo "<option value='".$row['web_id']."'> ".$row['web_portal_name']." </option>";
			}

	}else{
		echo "<option  value='0' disabled selected>There is no scheme for this selection</option>";

	}
	//var_dump($result);
	

?>