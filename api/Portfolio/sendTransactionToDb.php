<?php
	 session_start();
	 header("Access-Control-Allow-Origin: *");
	 header("Access-Control-Allow-Credentials: true");
	 header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
	 header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
	 header('P3P: CP="CAO PSA OUR"'); // Makes IE to support cookies
	 header("Content-Type: application/json; charset=utf-8");
	 require_once('../database.php');
	$db = new MYSQLDB();

	if(isset($_SESSION['Thruesday27FebUID'])){

		$unit_Allo = 0;
		$nav_bought = 0;
		
		$postdata = file_get_contents("php://input");
		$request = json_decode($postdata);
		$userId = "";
		if(isset($_SESSION['Thruesday27FebUID'])){
			$userId = $_SESSION['Thruesday27FebUID'];
		}else{
			
		}

		$usr_iin = $db->getUserIIN($userId);

		$trxsFromDate = "24-Dec-2019";
		$convrtFromDate = date('d-M-Y', strtotime($trxsFromDate));
		$trxsToDate = date('d-M-Y');
		$convrtToDate = date('d-M-Y', strtotime($trxsToDate));

		 $apiDetails = $db->getApiDetails();

	     if($apiDetails){
	        $appId = "";
	        $appPass ="";
	        $brockerCode ="";
			$euin = "";
	        $app_url = "";

	        foreach($apiDetails as $row){
	            $appId = $row['appId'];
	            $appPass =$row['appPass'];
	            $brockerCode = $row['brockerCode'];
				$euin = $row['EUIN'];
	            $app_url = $row['apiDomain'];
	        }
	    }

	    // $userDetails = $db->checkIfUserDetailsExists($user_id);
	  
	    
	    // if($userDetails){
	    //     $userName ="";
	    //     $bankCode ="";
	    //     $userAccountHolderName ="";
	    //     $userBankIfscCode="";
	    //     $userBankAccNo="";
	    //     $userIIN = "";
	    //     $bankCode = "";
	    //     $bankBranch ="";
	    //     $accType ="";


	    //     foreach($userDetails as $row){
	    //         $userName = $row['usr_full_name'];
	    //         $bankCode = $row['usr_bank_name'];
	    //         $userAccountHolderName = $row['usr_accout_holder_name'];
	    //         $userBankIfscCode = $row['usr_bank_ifsc_code'];
	    //         $bankBranch = $row['usr_bank_branch_name'];
	    //         $userBankAccNo = $row['usr_bank_account_no'];
	    //         $userIIN = $row['iin'];
	    //         $accType = $row['usr_acct_type'];
	    //     }
		
						
		$url  = "https://www.nsenmf.com/NMFII-IINHoldingDetails/NMFIINHoldingDetails/IINTRXNINFO";
			
			$xml_data = '<?xml version="1.0" encoding="UTF-8"?>
					<NMFIIService>
						<service_request>
							<appln_id>'.$appId.'</appln_id>
		                    <password>'.$appPass.'</password>
		                    <broker_code>'.$brockerCode.'</broker_code>
							<iin>'.$usr_iin.'</iin>
						</service_request>
					</NMFIIService>';
		
			
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);



		$output = curl_exec($ch);


		  	$create_request = "../debugg/".$usr_iin."_holding_details_status_request_data_".date("d-m-y h-m-s").".xml";

	        $create_response = "../debugg/".$usr_iin."_holding_details_status_response_data_".date("d-m-y h-m-s").".xml";
			$create_request = str_replace(' ', '_', $create_request);
			$create_response = str_replace(' ', '_', $create_response);
	        file_put_contents($create_request,$xml_data);
	        file_put_contents($create_response, $output);

		// file_put_contents('trxn_detailed_status_request_data.xml',$xml_data);
		// file_put_contents('trxn_detailed_status_response_data.xml', $output);
		
		
			
		$data = new SimpleXMLElement($output);
	        


	        $dataSearch = $data->children('urn:schemas-microsoft-com:xml-diffgram-v1')->children()->NMFIISERVICES;

	        
	        foreach ($dataSearch->service_status as $status):
	            $service_code = $status->service_return_code;   
	        endforeach;

	        if($service_code == 0){
	        	$res = $db->deleteHoldingFunds($userId);
	        	//echo "test";
				
				$response_array = [];
				foreach ($dataSearch->service_response as $response):

						$unit_Allo = number_format((float)$response->BALANCE_UNITS, 4, '.', '');
						//$unit_Allo = (float) $response->BALANCE_UNITS;

						$folio_no = $response->FOLIO_NO;
						$amount = (float) $response->COST_VALUE;
					
						$amc_code = $response->AMC_CODE;

						//$bought_nav = $amount / $unit_Allo; // rks 21Mar22
						$bought_nav = 0;
						if($unit_Allo > 0 )
							$bought_nav = $amount / $unit_Allo;

						$current_value = $response->CURRENT_VALUE;
					  
						$product_code = $response->PRODUCT_CODE;
						
						$auth_date = $response->CREATED_DATE;

						//$unit_Alloc = number_format((float)$unit_Allo, 2, '.', '');


						$res1 = $db->INSERTDATATransaction($amc_code,$product_code,$folio_no,$unit_Allo,$bought_nav,$userId,$amount,$current_value);
				endforeach;
	           echo json_encode(array("Id"=>0,"Result"=>"Pass","Message"=>"All transation stored in array","Success"=>true));
	        }else{
			
				$retuen_message = [];
	            foreach ($dataSearch->service_response as $response):
	                $retuen_message[] = $response->return_msg;
	                if($response->return_msg == "No Data Found"){
	                	$res = $db->deleteHoldingFunds($userId);

	                }
	            endforeach;
	            echo json_encode(array("Id"=>1,"Result"=>"Fail","Message"=>"Somthing went wrong","Success"=>false));
	        }
	        curl_close($ch);
	}else{
		session_destroy();
		echo json_encode(array("Id"=>1,"Result"=>"Fail","Message"=>"Your session has timed out. Please Login Again.","Success"=>false));

	}


        // service status
    
    
    
?>