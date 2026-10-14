<?php
require_once('auth_guard.php');
 

	require_once('database.php');
	$db = new MYSQLDB();

	$Id = $_REQUEST['id'];
        $res =  $db->deletefund($Id);
        echo $res;
		
		//header("location:index.php");	
	
?>

