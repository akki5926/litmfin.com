<?php
    session_start();
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header('P3P: CP="CAO PSA OUR"');  
    header("Content-Type: application/json; charset=utf-8");
    // require_once('../../admin/portfolio.php');
    require_once('../database.php');
    $db = new MySQLDB();

     if(isset($_SESSION['Thruesday27FebUID']))   
	{

     $user_id = $_SESSION['Thruesday27FebUID'];
       $statusAllData = [];
    //    $user_id =  1;  // for testing
        $userIIN = $db->getUserIIN($user_id); //"5011782684";
        if(isset($userIIN))
		 {
            $casDetails = $db->getCasDetails($userIIN);      
           echo json_encode(array("Id"=>0,"Result"=>"Pass","Message"=>"all Status displayed","casDetails"=>$casDetails,"Success"=>true));
            //echo json_encode(array("Id"=>0,"Result"=>"Pass","Message"=>"all Status displayed","portFolioData"=>$portfolioInfo,"Success"=>true));
       }       
    }
	else
	{
       echo json_encode(array("Id"=>1,"Result"=>'fail',"Message"=>'Session Expired, please login again!',"Success"=>false));
    }
?>   