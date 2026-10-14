<?php
        $user_id = 1; 
        $statusAllData = [];
		$statusAllData[] = array("folioNumber"=>"17747708792","amcCode"=>"101","productCode"=>"SCGPGGR", "unitAllocated"=>"203.24","boughtNav"=>"24.6002","purchaseDate"=>"2022-12-01 16:56:10","SchemeName"=>"Canara Robeco Small Cap Fund - Growth","FundType" => "Equity","fund_name"=>"CANARA ROBECO ASSET MGMT","amount" =>"4999.7532","PL" =>"79.26","profit" =>"1","Refund"=>"5079.0132","Percentage" =>"1.59","Currentvalue"=>"5079.0176","curNav"=>"24.55","curNavDate"=>"06-Jan-2023"); 
		$statusAllData[] = array("folioNumber"=>"17747708792","amcCode"=>"101","productCode"=>"SCGPGGR", "unitAllocated"=>"203.24","boughtNav"=>"24.6002","purchaseDate"=>"2022-12-01 16:56:10","SchemeName"=>"Canara Robeco Small Cap Fund - Growth","FundType" => "Equity","fund_name"=>"CANARA ROBECO ASSET MGMT","amount" =>"4999.7532","PL" =>"79.26","profit" =>"1","Refund"=>"5079.0132","Percentage" =>"1.59","Currentvalue"=>"5079.0176","curNav"=>"24.55","curNavDate"=>"06-Jan-2023"); 
		echo json_encode(array("Id"=>0,"Result"=>"Pass","Message"=>"all Status displayed","statusData"=>$statusAllData,"Success"=>true));

?>