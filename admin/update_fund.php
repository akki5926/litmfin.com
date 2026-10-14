<?php
require_once('auth_guard.php');
 

    require_once('database.php');
    $db = new MYSQLDB();






    $ID = $_REQUEST['Id'];
    $allocation = $_REQUEST['allocation'];
    $rational = $_REQUEST['rational'];

    $res = $db->updateRec($ID,$allocation,$rational);
    if($res >=1){
        echo "Data Updated Successfully";
    }



    // $result = $db ->getallinvestType($_REQUEST['invest']);
    // //var_dump($result);
    // echo "<option value='0'>select</option>";
    //  foreach($result as $row){
        
    //      echo "<option value='".$row["fund_search"]."'> ".$row['fund_cat_name']." </option>";        
        
    //  // echo "<option value='".$row['web_id']."'> ".$row['web_portal_name']." </option>";
    // }
 
?>