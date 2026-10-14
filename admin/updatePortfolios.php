<?php
require_once('auth_guard.php');

	require_once('database.php');
	$db = new MYSQLDB();
	
	$userList = $db->getAllUCC();

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
	$CurAUM = 0;
	$AUM = 0;
	echo "<table border='1' style='border-collapse:collapse'  >
				<tr style='background-color:lightgrey'>
						<th>IIN</th>
						<th>AMC code</th>
						<th>Product code</th>
						<th>Product_name</th>
						<th>Folio no</th>
						<th>Units</th>
						<th>Bought Nav</th>
						<th>Amount</th>
						<th>Current value</th>
						<th>Abs Return %</th>
				</tr>
			";
			$productData = array();
			
	foreach($userList as $user)
	{
		$usr_iin = $user['CLIENT_CODE'];
		$userId = $user['id'];
		$userName = $user['PRIMARY_HOLDER_NAME'];
		
		$rowStr = "";
		$userAUM = 0;
		$userCurAUM = 0;
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
		/*
		$create_request = "debugg/".$usr_iin."_trxn_detailed_status_request_data".date("d-m-y h-m-s").".xml";

		$create_response = "debugg/".$usr_iin."_trxn_detailed_status_response_data".date("d-m-y h-m-s").".xml";
		$create_request = str_replace(' ', '_', $create_request);
		$create_response = str_replace(' ', '_', $create_response);
		file_put_contents($create_request,$xml_data);
		file_put_contents($create_response, $output);
		*/
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
					$product_name = $response->PRODUCT_LONG_NAME;
					$bought_nav = 0;
					if($unit_Allo > 0 )
						$bought_nav = $amount / $unit_Allo;

					$current_value = $response->CURRENT_VALUE;
					$product_code = $response->PRODUCT_CODE;
					$auth_date = $response->CREATED_DATE;
					//$unit_Alloc = number_format((float)$unit_Allo, 2, '.', '');

					$res1 = $db->INSERTDATATransaction($amc_code,$product_code,$folio_no,$unit_Allo,$bought_nav,$userId,$amount,$current_value);
					if($current_value > 0)
					{
						$bought_nav_formatted = number_format(floatval($bought_nav), 3, '.', ',');
						$current_value_formatted = number_format(floatval($current_value), 2, '.', ',');
						$amount_formatted = number_format(floatval($amount), 2, '.', ',');
						$absUserRet = 0;
						if($amount > 0) $absUserRet = ($current_value - $amount) * 100 / $amount ; 
						$absUserRet_formatted = number_format(floatval($absUserRet), 2, '.', ',');
						$rowStr .= "<tr>
						<td>".$usr_iin."</td>
						<td>".$amc_code."</td>
						<td>".$product_code."</td>
						<td>".$product_name."</td>
						<td style='text-align:right'>".$folio_no."</td>
						<td style='text-align:right'>".$unit_Allo."</td>
						<td style='text-align:right'>".$bought_nav_formatted."</td>
						<td style='text-align:right'>".$amount_formatted."</td>
						<td style='text-align:right'>".$current_value_formatted."</td>
						<td style='text-align:right'>".$absUserRet_formatted."&nbsp;&nbsp;&nbsp;</td>
						</tr>";
						$userCurAUM = $userCurAUM + $current_value;
						$CurAUM = $CurAUM + $current_value;
						$userAUM = $userAUM + $amount;
						$AUM = $AUM + $amount;
						
						$product_name_str = (string) $product_name;
						  // If the product name is not in the $productData array, add it with the current_value as total
						 if (!isset($productData[$product_name_str])) {
							$productData[$product_name_str] = $current_value;
						} else {
							// If it exists, add the current_value to the existing total
							$productData[$product_name_str] += $current_value;
						}
					}
			endforeach;
			
		   //echo json_encode(array("Id"=>0,"Result"=>"Pass","Message"=>"All transation stored in array","Success"=>true));
		}else{
		
			$retuen_message = [];
			foreach ($dataSearch->service_response as $response):
				$retuen_message[] = $response->return_msg;
				if($response->return_msg == "No Data Found"){
					$res = $db->deleteHoldingFunds($userId);

				}
			endforeach;
			//echo json_encode(array("Id"=>1,"Result"=>"Fail","Message"=>"Somthing went wrong","Success"=>false));
		}
		curl_close($ch);
		$UserAUM_formated = number_format($userAUM, 2, '.', ',');
		$UserCurAUM_formated = number_format($userCurAUM, 2, '.', ',');
		if($userAUM > 0 )
		{
			$usrAbsReturn = ($userCurAUM - $userAUM) *100 / $userAUM;
			$usrAbsReturn_formated = number_format($usrAbsReturn, 2, '.', ',');
			echo "<tr style='background-color:#82E0AA'>
						<td>".$usr_iin."</td>
						<td>".$userId."</td>
						<td colspan='5'>".$userName."</td>
						<td style='text-align:right'>".$UserAUM_formated."</td>
						<td style='text-align:right'>".$UserCurAUM_formated."</td>
						<td style='text-align:right'>".$usrAbsReturn_formated."%</td>
						</tr>";
		}
		else
		{
			echo "<tr style='background-color:#FFA07A'>
						<td>".$usr_iin."</td>
						<td>".$userId."</td>
						<td colspan='5'>".$userName."</td>
						<td style='text-align:right'>".$UserAUM_formated."</td>
						<td style='text-align:right'>".$UserCurAUM_formated."</td>
						<td></td>
						</tr>";
		}
		echo $rowStr;
	}
	echo "</table>";

	$absReturn = ($CurAUM - $AUM) *100 / $AUM;
	$absReturn_formated = number_format($absReturn, 2, '.', ',');
	$AUM_formated = number_format($AUM, 2, '.', ',');
	$CurAUM_formated = number_format($CurAUM, 2, '.', ',');
	echo "Total AUM : <b>".$AUM_formated."</b> , Total Current AUM : <b>".$CurAUM_formated."</b> , Abs Return : <b>$absReturn_formated %</b><br><br>";
?>
<?php
ksort($productData);
?>
<div style="overflow-x: auto;">
    <table id="sortable-table" border='1' style='border-collapse:collapse'>

    <thead>
        <tr>
            <th>Sl No.</th>
			<th>Product Name</th>
            <th>Total Current Value</th>
        </tr>
    </thead>
    <tbody>
    <?php 
		$slno = 1;
		$tot_AUM = 0;
		foreach ($productData as $product_name => $total_current_value):
		$tot_cur_val_formated = number_format(floatval($total_current_value), 2, '.', ',');
		$tot_AUM = $tot_AUM + floatval($total_current_value);
	?>
        <tr>
            <td><?php echo $slno; ?></td>
			<td><?php echo $product_name; ?></td>
            <td style='text-align:right'><?php echo $tot_cur_val_formated; ?></td>
        </tr>
    <?php 
	$slno++;
	endforeach; 
	$AUM_formated2 = number_format($tot_AUM, 2, '.', ',');

	?>
	</tbody>
        <tfoot>
	        <tr style='background-color:#FFA07A'>
            <td></td>
			<td><b>Total Current AUM</b></td>
            <td style='text-align:right'><b><?php echo $AUM_formated2; ?></b></td>
        </tr>

    </tfoot>
</table>
</div>

    <!-- ... other meta tags, styles, etc. ... -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.6/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.6/js/jquery.dataTables.js"></script>

<script>
$(document).ready(function() {
    $('#sortable-table').DataTable({
        "paging": false, // Disable pagination if you don't need it
        "ordering": true, // Enable column sorting
        "searching": false, // Disable searching if you don't need it
        "info": false // Disable showing info about the table
    });
});
</script>

