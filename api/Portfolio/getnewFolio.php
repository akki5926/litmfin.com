<?php
    session_start();

    require_once('database.php');
    $db = new MYSQLDB();

    $folioStatus = $db->getFolioStatus();
    
   
    if($folioStatus != ''){
        echo JSON_ENCODE(array("ID"=>'0',"Message"=>'All tax Status Data',"Success"=>true,"FolioData"=>$folioStatus));
    }else{
        echo JSON_ENCODE(array("ID"=>'1',"Message"=>'please Reload The Page',"Success"=>false));
    }
    
?>

