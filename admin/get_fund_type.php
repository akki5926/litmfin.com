<?php
require_once('auth_guard.php');
   
    require_once('database.php');
    $db = new MYSQLDB();
    $output ='';
    $amcCodeName = $_POST['amcName'];

    $output .='
        <option>'.$amcCodeName.'</option>
    ';
    echo $output;
?>  