<?php
require_once('database.php');
$db = new MYSQLDB();
require_once('auth_guard.php');

ini_set('MAX_EXECUTION_TIME', '-1');
ini_set('memory_limit', '-1');
$target_file = "";
$conn = "";

function uploadfile()
{
	global $target_file;
	$target_dir = "uploads/";
	$imageFileType = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));
	$safeName = uniqid('upload_') . '.' . $imageFileType;
	$target_file = $target_dir . $safeName;
	$uploadOk = 1;

	// Check if file already exists
/*	if (file_exists($target_file)) {
		echo "Sorry, file already exists.";
		$uploadOk = 0;
	}
	*/
	// Check file size
	if ($_FILES["fileToUpload"]["size"] > 7000000) {
		echo "Sorry, your file is too large.";
		$uploadOk = 0;
	}
	// Allow certain file formats
	if($imageFileType != "xlsx" && $imageFileType != "xls" && $imageFileType != "csv" ) {
		echo "Sorry, only xlsx, xls & csv files are allowed.";
		$uploadOk = 0;
	}
	// Check if $uploadOk is set to 0 by an error
	if ($uploadOk == 0) {
		echo "Sorry, your file was not uploaded.";
	// if everything is ok, try to upload file
	} else {
		if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
			echo "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded.";
		} else {
			echo "Sorry, there was an error uploading your file.";
		}
		
		
	}

}
function Export2DB($dbtable,$fieldname,$data)
{
	global $db;
	$target_file = $data;
	$databasetable = $dbtable;
	$fieldCount = $fieldname;
	
	set_include_path(get_include_path() . PATH_SEPARATOR . 'Classes/');
	include 'PHPExcel/IOFactory.php';

	$inputFileName = $target_file;
	try {
		$objPHPExcel = PHPExcel_IOFactory::load($inputFileName);
	} catch(Exception $e) {
		die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
	}

	$allDataInSheet = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
	$arrayCount = count($allDataInSheet);
	$alpha="ABCDEFGHIGKLMNOPQRSTUVWXYZ";
	for($k=0;$k<$fieldCount;$k++){
		$fieldList[$k] = trim($allDataInSheet[1]["$alpha[$k]"]);
	}
	$fieldCount = count($fieldList);
	for($i=2;$i<=$arrayCount;$i++){
		for($k=0;$k<$fieldCount;$k++){
			$fieldValue[$k] = trim($allDataInSheet[$i]["$alpha[$k]"]);
		}

		$success = $db->insertDynamicRecord($databasetable, $fieldList, $fieldValue);
		if ($success){
			$msg = 'Record has been added. <div style="Padding:20px 0 0 0;"><a href="index.php">Go Back to Upload</a></div>';
		} 
		else {
			$msg = 'Record already exist. <div style="Padding:20px 0 0 0;"><a href="index.php">Go Back to Upload</a></div>';
		}
	}
	echo "<div style='font: bold 18px arial,verdana;padding: 45px 0 0 500px;'>".$msg."</div>";

}
function getId($dbtable,$fieldname,$data,$fieldid)
{
	global $db;
	return $db->getDynamicId($dbtable, $fieldname, $data, $fieldid);
}
function getIdFromAlias($dbtable,$fieldname,$data,$fieldid)
{
	global $db;
	return $db->getDynamicIdFromAlias($dbtable, $fieldname, $data, $fieldid);
}

function insertIntoDB($str,$amcCode,$productCode,$fieldsName){
	global $db;
	return $db->insertOrUpdateMFMasterBulk($str, $amcCode, $productCode, $fieldsName);
}
function ExportPP2DB($dbtable,$tfile,$fCount)
{
	
	global $db;
	$productCode ='';
	$amcCode = '';
	$target_file = $tfile;
	$databasetable = $dbtable;
	$count1 = 0;
	$count2 = 0;
	$count3 = 0;
	$upCountS = 0;
	$upCountF = 0;
	set_include_path(get_include_path() . PATH_SEPARATOR . 'Classes/');
	include 'PHPExcel/IOFactory.php';

	// This is the file path to be uploaded.
	$inputFileName = $target_file;
	//echo "Selected file:".$inputFileName;
	try {
		$objPHPExcel = PHPExcel_IOFactory::load($inputFileName);
	} catch(Exception $e) {
		die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
	}


	$allDataInSheet = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
	$arrayCount = count($allDataInSheet);  // Here get total count of row in that Excel sheet

	//echo "<br><br> date: $date , fuelcompany=$fuelcompany";
	//$fuelcompanyid = getId("fuelcompany","fuelcompanyname",$fuelcompany,"fuelcompanyid");
	//echo "<br><br>Row1 Data : datefield = $datefield , date = $date,fuelcompanyfield = $fuelcompanyfield, fuelcompany = $fuelcompany, fuelcompanyid = $fuelcompanyid <br>";
	

	$alpha=array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","AB","AC","AD","AE","AF","AG","AH","AI","AJ","AK","AL","AM","AN","AO","AP","AQ","AR","AS","AT","AU","AV");
	
	$str ="";
	$alphaCount = sizeof($alpha);

	for($k=0;$k<$alphaCount;$k++){
		if($allDataInSheet[1]["$alpha[$k]"]!=''){
			$strField = strtolower($allDataInSheet[1]["$alpha[$k]"]);
			if (strpos($strField, '/') !== false) {
				$strField = str_replace("/","_",$strField);
				$fieldsName[$k] = $strField;
			}else if(strpos($strField, ' ') !== false){
				$strField = str_replace(" ","_",$strField);
				$fieldsName[$k] = $strField;
			}else if(strpos($strField, '-') !== false){
				$strField = str_replace("-","_",$strField);
				$fieldsName[$k] = $strField;
			}else{
				$fieldsName[$k] = $strField;
			}
			
		}else{
			continue;
		}
		
	}
	echo $arrayCount;
	for($row=2;$row<=$arrayCount;$row++){
	   if($allDataInSheet[$row]["A"]!=''){
		   $amcCode = $allDataInSheet[$row]["A"];
		   $productCode = $allDataInSheet[$row]["B"];
			for($col=0;$col<=$alphaCount;$col++){
				if (array_key_exists($col, $alpha)){
					if($allDataInSheet[$row]["$alpha[$col]"]==''){
						$allDataInSheet[$row]["$alpha[$col]"] = 'NULL';
						if($str==""){
							if($allDataInSheet[$row]["$alpha[$col]"] == 'NULL'){
								$str[$col]=$allDataInSheet[$row]["$alpha[$col]"];
							}else{
								$str[$col]='"'.$allDataInSheet[$row]["$alpha[$col]"].'"';
							}
								
						}else{
							if($allDataInSheet[$row]["$alpha[$col]"] == 'NULL'){
								$str[$col]= $allDataInSheet[$row]["$alpha[$col]"];
							}else{
								$str[$col]= '"'.$allDataInSheet[$row]["$alpha[$col]"].'"';
							}
							
						}
					}else{
						if($str==""){
							$str[$col]='"'.$allDataInSheet[$row]["$alpha[$col]"].'"';
						}else{
							$str[$col]= '"'.$allDataInSheet[$row]["$alpha[$col]"].'"';
						}
					}
					
				}
			}
	   }else{
		   $count3++;
		   continue;
	   }
		
		
		$msg = insertIntoDB($str,$amcCode,$productCode,$fieldsName);
		if($msg == "Inserted Successfully"){
			$count1++;
		}
		if($msg == "Insert Failed"){
			$count2++;
		}
		if($msg == "Updated Successfully"){
			$upCountS++;
		}
		if($msg == "Updation Failed"){
			$upCountF++;
		}
		 $str="";
		 
	}
	echo "<br> Total Record Inserted := " .$count1;
	echo "<br> Total Insertion Failed := " .$count2;
	echo "<br> Total Empty Record ingnored := " .$count3;
	echo "<br> Total Record Updated := " .$upCountS;
	echo "<br> Total Record Updation Failed := " .$upCountF;

	$logMsg = 'Total Record Inserted := '.$count1.', ';
	$logMsg .= 'Total Insertion Failed :='.$count2.', ';
	$logMsg .= 'Total Record Updated := '.$upCountS.', ';
	$logMsg .= 'Total Record Updation Failed := '.$upCountF.', ';
	
	
	
	return $logMsg;

	/*	for($k=0;$k<$fCount;$k++){
		$fieldList[$k] = strtolower(trim($allDataInSheet[2]["$alpha[$k]"]));
			//echo "<br><br> field name: $k=".$fieldList[$k];
	}
	//$fieldCount = count($fieldList);
	*/
	
}

function updateLog($msg1)
{
	global $db;
	$remarks = NULL;
	if(isset($_REQUEST['remarks'])) $remarks = $_REQUEST['remarks'];
	$db->insertAdminLog($msg1, $remarks);
}
uploadfile();
echo "<br>Uploaded file : $target_file";
//Export2DB("emp",$target_file,3);

$msg1 = ExportPP2DB("tb_mf_master",$target_file,10)." File = <a href=\"$target_file\" download>".basename($target_file)."</a>";
updateLog($msg1);
echo "<br><br><a href='index.php'>Back </a>";
?>