<?php
    //require_once('../cors.php'); // Adjust path if needed
    
    require_once('../database.php');
    $db = new MYSQLDB();

    if(isset($_SESSION['Thruesday27FebUID'])){


        $user_id = $_SESSION['Thruesday27FebUID'];
        $statusAllData = [];

        if(isset($user_id)){
            $status = $db->getFolioData($user_id);
             
            if(isset($status)){
                foreach($status as $row){

                    $type_Fund = $db->getFundType($row['product_code1'],$row['amc_code']);
                    // $type_Fund = "";

                    $folioNumber = $row['folio_number'];
                    
                    $amcCode = $row['amc_code'];
                    
                    $productCode = $row['product_code1'];

                    $unitAllocated = $row['total_unit'];

                    $boughtNav = $row['bought_nav'];

                    $purchaseDate = $row['bought_date'];

                    $Scheme = $row['product_long_name'];

                    $fundName = $row['LONG_NAME'];

                    $curNav = $row['nav'];
                    $curNavDate = $row['nav_date'];

                    $amount = $row['purchase_amount'];
                    $amount = number_format((float)$amount, 2, '.', '');

                        $cur_amount = $row['current_value'];
                        $cur_amount = number_format((float)$cur_amount, 2, '.', '');

                        $pl = $cur_amount - $amount;
                        $pl = number_format((float)$pl, 2, '.', '');
                        $ref = 0;



                        if($pl > 0){
                            
                            $profit = "1";
                            $ref = $amount + $pl;

                            $per = (($pl * 100)/$amount);
                             $per = number_format((float)$per, 2, '.', '');

                            //echo "in if ".$ref;
                        }else if($pl == 0){
                           // echo "in elseif ".$pl;
                            
                            $profit = "2";
                            $ref = $amount;
                            $per = 0;
                            $per = number_format((float)$per, 2, '.', '');
                            //echo "in elseif ".$ref;
                        }else{
                            //echo "in else ".$pl;
                            
                            $profit = "3";
                            $ref = $amount + $pl;
                            $per = (($pl * 100)/$amount);
                            $per = number_format((float)$per, 2, '.', '');
                            //echo "in else ".$ref;
                        }
                        $currentAmount = $row['current_value'];

                        $currentAmount = number_format((float)$currentAmount, 2, '.', '');
                  if( $amount > 0)
                  $statusAllData[] = array("folioNumber"=>$folioNumber,"amcCode"=>$amcCode,"productCode"=>$productCode, "unitAllocated"=>$unitAllocated,"boughtNav"=>$boughtNav,"purchaseDate"=>$purchaseDate,"SchemeName"=>$Scheme,"FundType" => $type_Fund,"fund_name"=>$fundName,"amount" =>$amount,"PL" =>$pl,"profit" =>$profit,"Refund"=>$ref,"Percentage" =>$per,"Currentvalue"=>$currentAmount,"curNav"=>$curNav,"curNavDate"=>$curNavDate);  



                  
                }
                echo json_encode(array("Id"=>0,"Result"=>"Pass","Message"=>"all Status displayed","statusData"=>$statusAllData,"Success"=>true));
              

                
                
            }else{
                echo json_encode(array("Id"=>1,"Result"=>'fail',"Message"=>'No Data Found',"Success"=>false));
            }
        }
    }else{
        session_destroy();
        echo json_encode(array("Id"=>1,"Result"=>'fail',"Message"=>'We are sorry. Please Login Again.',"Success"=>false));

    }
?>