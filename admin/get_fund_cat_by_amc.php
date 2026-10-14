<?php
require_once('auth_guard.php');
 
	require_once('database.php');
	$db = new MYSQLDB();

	$amc = isset($_REQUEST['amc']) ? $_REQUEST['amc'] : '';
	if(empty($amc)){
		echo "<option value='0'>Select Fund Category</option>";
		exit;
	}

	$result = $db->getFundCategoriesByAmc($amc);
	echo "<option value='0'>Select Fund Category</option>";
	if($result){
		foreach($result as $row){
			echo "<option value='".$row["fund_search"]."'> ".$row['fund_cat_name']." </option>";
		}
	}
?>
