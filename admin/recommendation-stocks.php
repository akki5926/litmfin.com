<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">

<?php
require_once('auth_guard.php');

	$output='';?>
	<div class="container-fluid row">
				<div class="col-lg-12 col-md-12 col-sm-12">
					
<div id="rec_div_form" style="margin-right: 20px;">
		<form id="add_rec" method="POST" onsubmit="addRec(); return false;">
			<div class="row">
				<div class="col-md-4" style="text-align: right;">	
					<label for="invest_type">Investment Type:</label>				
					<select id="invest_type" name = "invest_type">
						<option value="0" selected=""> Select Invest Type </option>
						<option value="AIF">AIF</option>
						<option value="Bond">Bond</option>
						<option value="PMS">PMS</option>
					</select>
				</div>
				<div class="col-md-4" style="text-align: left;">	
					<label for="asset_class">Asset Class:</label>				
					<select id="asset_class" name = "asset_class" >
						<option value="0">Select Asset Class</option>
						<option value="Debt">Debt</option>
						<option value="Equity">Equity</option>
						<option value="Gold">Gold</option>
						<option value="Hybrid">Hybrid</option>
					</select>
				</div>
				<div class="col-md-4" style="text-align: right;">
					<label for="invName">Name:</label>
					<input type="text" name="invName" id="invName" placeholder="Please enter Name of Investment" >
				</div>
				<div class="col-md-4" style="text-align: right;">
					<label for="issuer">Issuer:</label>
					<input type="text" name="issuer" id="issuer" placeholder="Please enter Name of issuer" >
				</div>
				<div class="col-md-5" style="text-align: left;">
					<label for="rational">Rational:</label>
					<input type="text" name="rational"   size="30" id="rational" placeholder="Please enter rational" >
				</div>
				<div class="col-md-3" style="text-align: right;">
					<input type="hidden" id="oi_action" name="oi_action" value="add" >
					<input type="hidden" id="oi_slno" name="oi_slno" value="0" >
					<label for="add_oi"></label>
					<button type="submit" name="add_oi" value="add oi" style="background-color: #FD7101; color: white; padding: 10px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">Add Recommendation</button>
				</div>
			</div>
		</form>
		<div id="status" style="margin-left: 10px;"></div>
		</div>
					<div class="row" >
						<!--<div class="col-lg-3" style="float: right;"> <button class="btn btn-primary" href="javascripit:void(0)" id="Add_aggressive">Add New Investment</button> </div> -->
					
						<div class="col-lg-12">
							<table class="table" id="recTable" style="border-collapse: collapse;font-size: 12px;">
							<thead>
								<tr style='background-color: #e0e0ff;'>
								<th>Investment Type</th>
								<th>Asset Class</th>
								<th>Name</th>
								<th>Issuer</th>
								<th>Rational</th>
								<th>Edit</th>
								<th>Delete</th>
								</tr>
							</thead>
							<tbody>
							</tbody>
							</table>
						
						</div> 
					</div>
				</div>	
			</div>
			<?php 
				echo $output;
			?>

<script>
	fetchRecData();
	// ,fund_cat,amcname,productname,allocation,recmd_rational
	function addRec() {
		//console.log("in addPms");
		var invest_type = document.getElementById("invest_type").value;
		var asset_class = document.getElementById("asset_class").value || 0;
		var invName 	= document.getElementById("invName").value;
		var issuer 		= document.getElementById("issuer").value || 0;
		var rational 	= document.getElementById("rational").value;
		var slno 		= document.getElementById("oi_slno").value || 0;
		var action 		= document.getElementById("oi_action").value;
		var xhr = new XMLHttpRequest();
		var url = "process_rec_other.php";
		xhr.open("POST", url, true);
		xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4 && xhr.status === 200) {
				var response = xhr.responseText;
				//console.log("response:"+response);
				 var responseContainer = document.getElementById('status');
				  if (response.includes("Error")) {
					  responseContainer.style.color = 'red';
					} else {
					  responseContainer.style.color = 'green';
					}  // Set the text color to red
				  responseContainer.innerHTML = response;
				  fetchRecData();
				  resetOiForm();
			}
		};
		var method = "addOtherInv";
		var data = "method=" + method + "&invest_type=" + invest_type + "&asset_class=" + asset_class + "&invName=" + invName + "&issuer=" + issuer + "&rational=" + rational+ "&action=" + action + "&slno=" + slno;;

		console.log("data: process_rec_other.php?"+data);
		xhr.send(data);
	}
	function fetchRecData() {
		console.log("data in fetchRecData");
		var xhr = new XMLHttpRequest();
		var url = "process_rec_other.php";
		xhr.open("POST", url, true);
		xhr.responseType = 'json';
		xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		xhr.onreadystatechange = function() {
		if (xhr.readyState === 4 && xhr.status === 200) {
			populateInvestedTable(xhr.response);
			console.log("data in xhr.response:"+xhr.response);
		  }
		};
		var method = "getRecData";//"getInvestData";
		var data = "method=" + method ;
		console.log("data in fetchRecData:"+data);
		xhr.send(data);
	}
	function populateInvestedTable(investedData) {
	  var tableBody = document.querySelector('#recTable tbody');
	  var rowStyle = '';

	  // Clear the table body
	  tableBody.innerHTML = '';

	  investedData.forEach(function(invested) {
		var row = document.createElement('tr');
		row.setAttribute('style', rowStyle);

		var tr = '';
		tr += "<td style='border: 1px solid black; padding: 5px;'>" + invested['investment_type'] + "</td>";

		tr += "<td style='border: 1px solid black; padding: 5px;'>" + invested['asset_class'] + "</td>";
		tr += "<td style='border: 1px solid black; padding: 5px;'>" + invested['name'] + "</td>";
		tr += "<td style='border: 1px solid black; padding: 5px;'>" + invested['issuer'] + "</td>";
		tr += "<td style='border: 1px solid black; padding: 5px;'>" + invested['rational'] + "</td>";

		tr += "<td style='border: 1px solid black; padding: 5px;'><a href='#' onclick='showEditForm(\""+ invested['slno'] + "\")'>Edit</a></td>";
		tr += "<td style='border: 1px solid black; padding: 5px;'><a href='#' onclick='deleteRow(\""+ invested['slno']+"\",\""+ invested['name'] + "\")'>Delete</a></td>";
		row.innerHTML = tr;
		tableBody.appendChild(row);

		rowStyle = rowStyle === '' ? 'background-color: #F2F2F2;' : '';
		//console.log("in populateinvestedTable name:" + invested['name']);
	  });
	}
	
	function showEditForm(slno) {

        // Get the investment data using the investmentId
        var oiDataPromise  = getInvestmentDataBySlno(slno); 
		oiDataPromise.then(oiData => {
        console.log("slno: "+slno +", oidata: "+oiData);
        // Populate the form fields with the retrieved data
        document.getElementById("invest_type").value = oiData.investment_type;
        document.getElementById("asset_class").value = oiData.asset_class;
		document.getElementById("invName").value = oiData.name;
		document.getElementById("issuer").value = oiData.issuer;
		document.getElementById("rational").value = oiData.rational;
        
        // Show the oi investment form
        //document.getElementById("oi_div").style.display = "block";
		// Find the button element
		var addButton = document.querySelector('button[name="add_oi"]');

		// Update the button text
		addButton.innerHTML = 'Update Recommendation';
		// Find the hidden input element
		var oiActionInput = document.getElementById("oi_action");
		// Update the value
		oiActionInput.value = "edit";
		var oiActionSlno = document.getElementById("oi_slno");
		// Update the value
		oiActionSlno.value = slno;
		//document.getElementById("formhead").innerHTML = "Edit oi Current Value";
		});
    
	}
	function getInvestmentDataBySlno(slno) {
		// Make an AJAX request to the server
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			xhr.open('POST', 'process_rec_other.php?method=getInvDataBySlno&slno=' + slno);
			xhr.onload = () => {
				if (xhr.status === 200) {
					// Parse the response JSON and resolve the promise with the investment data
					const investmentData = JSON.parse(xhr.responseText);
					resolve(investmentData);
				} else {
					// Reject the promise with an error message
					reject('Failed to retrieve investment data');
				}
			};
			xhr.onerror = () => {
				// Reject the promise with an error message
				reject('Failed to make the AJAX request');
			};
			xhr.send();
		});
	}
function resetOiForm()
{
		//document.getElementById("formhead").innerHTML = "Add Oi Transaction";
		document.getElementById("invest_type").value = "";
        document.getElementById("asset_class").value = ""; 
		document.getElementById("invName").value = ""; 
		document.getElementById("issuer").value = ""; 
		document.getElementById("rational").value = ""; 
		document.getElementById("oi_slno").value = ""; 
        
		var addButton = document.querySelector('button[name="add_Oi"]');
		addButton.innerHTML = 'Add Recommendation';
		// Find the hidden input element
		var OiActionInput = document.getElementById("oi_action");
		OiActionInput.value = "add";	
}
function deleteRow(investmentId,iName) {
	// Prompt the user for confirmation before proceeding with the deletion
	var confirmation = confirm("Are you sure you want to delete " + iName + "?");
	if (confirmation) {
		var xhr = new XMLHttpRequest();
		var url = "process_rec_other.php";
		xhr.open("POST", url, true);
		xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		xhr.onreadystatechange = function() {
		if (xhr.readyState === 4 && xhr.status === 200) {
			var response = xhr.responseText;
			//alert(response);
			//console.log("response:" + response);
			var responseContainer = document.getElementById('status');
			if (response.includes("Error")) {
			  responseContainer.style.color = 'red';
			} else {
			  responseContainer.style.color = 'green';
			}
			responseContainer.innerHTML = response;
			fetchRecData();
			var actionInput = document.getElementById("oi_action");
			var actionInputValue = actionInput.value;
			//console.log("actionInputValue = "+actionInputValue);
			if(actionInputValue == "edit")
			{
				actionInput.value = "add";
				var addButton = document.querySelector('button[name="add_oi"]');

				addButton.innerHTML = 'Add Recommendation';
				document.getElementById("oi_slno").value = 0;
				//document.getElementById("formhead").innerHTML = "Add Investment";
			}
		  }
		};
		
		//var data = "investmentId=" + investmentId + "&iname=" + iName;
		var method = "del_rec";
		var data = "method=" + method + "&slno=" + investmentId + "&iname=" + iName;
		//console.log("data:"+data);
		xhr.send(data);
	}
}
</script>







