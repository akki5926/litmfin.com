<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<HTML xmlns="http://www.w3.org/1999/xhtml">
<HEAD>
	<META http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<META http-equiv="Content-Type" content="text/html; charset=EUC-JP">
	<TITLE>Get NAV</TITLE>
	<style>
table, th, td {
  border: 1px solid black;
  border-collapse: collapse;
}
</style>
</HEAD>
<BODY>
<DIV id="main">
 <?php
require_once('auth_guard.php');
 
	updateNav();
 ?>
</div>

</BODY>
</HTML>
<?php

function updateNav()
{
	$lines = file('https://www.amfiindia.com/spages/NAVAll.txt');
	echo "<hr>NAV Price from AMFI:<br>";
	$slno = 1;
	$slnoAll = 1;
	$insertedCount = 0;
	$updatedCount = 0;
	$alreadyExistCount = 0;
	$alreadyExistStr = "";
	$dateToday = new DateTime("now");
	$now = time(); // or your date as well
	echo "<table>";
	echo "<tr><th>Sl No.</th><th>ISIN</th><th>Net Asset Value</th><th>Net Asset Value Date</th><th>Scheme Code</th><th>Scheme Name</th><th>DB actions</th></tr>";
	require_once('database.php');
    $db = new MYSQLDB();
	foreach ($lines as $line_num => $line) 
	{
		//echo "line=$line <br>";
		if(!(strpos($line,";INF") === FALSE))
		if(!((stripos($line,"Direct") != false))){
			$strStartPos = strrpos($line, ";");
			$strEndPos = strlen($line);
			$dateStr = trim(substr($line,$strStartPos+1));
			
			$dateArray = explode("-",$dateStr);
			$date = $dateArray[0]."-".$dateArray[1]."-".$dateArray[2];
			$d = date_parse_from_format("d-M-Y", $date);
			$date = $dateArray[2]."-".$d["month"]."-".$dateArray[0];
			//echo "dateStr:".$dateStr."/$date";
			$date2 = date_create($date);

			$nav_date = strtotime($date);
			$datediff = ceil(($now - $nav_date)/86400);
			if($datediff < 30){
				$dataArray = explode(";",$line);
				$str = str_replace(";","</td><td>",$line);
				//echo "<tr><td>$slno </td><td>".$str."</td></tr>";
				//echo "Slno #<b>{$slno}</b> : " . htmlspecialchars($line) . "<br />\n";

				
				$isin = $dataArray[1];
				$nav = $dataArray[4];
				$nav_dateStr = $dataArray[5];
				$scheme_code = $dataArray[0];
				$scheme_name = $dataArray[3];
				if(strlen($isin) > 10){
					
					$retResult = $db->updateNAV($isin,$nav,$nav_dateStr,$scheme_code,$scheme_name);
					echo "<tr><td>$slno</td><td>$isin</td><td>$nav</td><td>$nav_dateStr</td><td>$scheme_code</td><td>$scheme_name</td><td>$retResult</td></tr>";
					if($retResult == "Inserted") $insertedCount++;
					if($retResult == "Updated") $updatedCount++;
					if($retResult == "already exist"){
						$alreadyExistStr .= "slno=$slno,isin=$isin<br>";
						$alreadyExistCount++;
					}
					$slno++;
				}
				
			}
		}
		$slnoAll++;
		//if($slnoAll > 20) break;
	}
	echo "</table>";
	echo "Total record = $slno, no of row inserted= $insertedCount, no of records updated= $updatedCount";
	if(strlen($alreadyExistStr) > 10){
		echo ", already Exist but no action =$alreadyExistCount";
		echo "<br>alreadyExist:$alreadyExistStr";
	}
	echo "<script>window.scrollTo(0, document.body.scrollHeight);</script>";
	//echo "Line : " . htmlspecialchars($lines) . "<br />\n";
}
?> 