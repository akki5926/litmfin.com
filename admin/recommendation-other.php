<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">

<?php
require_once('auth_guard.php');

	$output='';?>
	<div class="container-fluid">
		<div class="row">
			<div class="col-lg-12">
				<div class="card mb-4 shadow-sm">
					<div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
						<h5 class="mb-0 fw-bold text-primary">Other Investments (AIF, PMS, Bonds)</h5>
						<button class="btn btn-outline-secondary btn-sm" onclick="backToList()">
							<i class="fa fa-arrow-left"></i> Back to List
						</button>
					</div>
					<div class="card-body">
						<div id="status" class="mb-3"></div>
						
						<form id="add_rec" method="POST" onsubmit="addRec(); return false;">
							<div class="row g-3">
								<div class="col-md-3">
									<label for="invest_type" class="form-label fw-bold small text-muted">Investment Type</label>
									<select id="invest_type" name="invest_type" class="form-select form-control">
										<option value="0" selected="">Select Invest Type</option>
										<option value="AIF">AIF</option>
										<option value="Bond">Bond</option>
										<option value="PMS">PMS</option>
									</select>
								</div>
								<div class="col-md-3">
									<label for="asset_class" class="form-label fw-bold small text-muted">Asset Class</label>
									<select id="asset_class" name="asset_class" class="form-select form-control">
										<option value="0">Select Asset Class</option>
										<option value="Debt">Debt</option>
										<option value="Equity">Equity</option>
										<option value="Gold">Gold</option>
										<option value="Hybrid">Hybrid</option>
									</select>
								</div>
								<div class="col-md-6">
									<label for="invName" class="form-label fw-bold small text-muted">Name</label>
									<input type="text" name="invName" id="invName" class="form-control" placeholder="Enter Name of Investment">
								</div>
								<div class="col-md-6">
									<label for="issuer" class="form-label fw-bold small text-muted">Issuer</label>
									<input type="text" name="issuer" id="issuer" class="form-control" placeholder="Enter Name of issuer">
								</div>
								<div class="col-md-6">
									<label for="rational" class="form-label fw-bold small text-muted">Rational (Optional)</label>
									<input type="text" name="rational" id="rational" class="form-control" placeholder="Enter rational (Optional)">
								</div>
								<div class="col-12 text-end mt-4">
									<input type="hidden" id="oi_action" name="oi_action" value="add">
									<input type="hidden" id="oi_slno" name="oi_slno" value="0">
									<button type="submit" name="add_oi" class="btn text-white px-4" style="background-color: #FD7101;">
										<i class="fa fa-plus-circle me-1"></i> Add Recommendation
									</button>
								</div>
							</div>
						</form>
					</div>
				</div>

				<div class="card shadow-sm">
					<div class="card-header bg-light py-2">
						<h6 class="mb-0 text-muted">Investment List</h6>
					</div>
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-hover table-striped mb-0 align-middle" id="recTable">
								<thead class="table-light text-muted">
									<tr>
										<th class="py-3 ps-3">Investment Type</th>
										<th class="py-3">Asset Class</th>
										<th class="py-3">Name</th>
										<th class="py-3">Issuer</th>
										<th class="py-3">Rational</th>
										<th class="py-3">Edit</th>
										<th class="py-3 pe-3">Delete</th>
									</tr>
								</thead>
								<tbody>
								</tbody>
							</table>
						</div>
					</div> 
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

	  // Clear the table body
	  tableBody.innerHTML = '';
      
      if (!investedData || investedData.length === 0) {
          var row = document.createElement('tr');
          row.innerHTML = "<td colspan='7' class='text-center py-4 text-muted'>No investments found.</td>";
          tableBody.appendChild(row);
          return;
      }

	  investedData.forEach(function(invested) {
		var row = document.createElement('tr');

		var tr = '';
		tr += "<td class='py-3 ps-3'>" + invested['investment_type'] + "</td>";
		tr += "<td class='py-3'>" + invested['asset_class'] + "</td>";
		tr += "<td class='py-3 fw-bold'>" + invested['name'] + "</td>";
		tr += "<td class='py-3'>" + invested['issuer'] + "</td>";
		tr += "<td class='py-3'>" + invested['rational'] + "</td>";

		tr += "<td class='py-3'><a href='#' class='btn btn-sm btn-outline-primary' onclick='showEditForm(\""+ invested['slno'] + "\")'><i class='fa fa-edit'></i> Edit</a></td>";
		tr += "<td class='py-3 pe-3'><a href='#' class='btn btn-sm btn-outline-danger' onclick='deleteRow(\""+ invested['slno']+"\",\""+ invested['name'] + "\")'><i class='fa fa-trash'></i> Delete</a></td>";
		row.innerHTML = tr;
		tableBody.appendChild(row);
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
		if(addButton) addButton.innerHTML = '<i class="fa fa-save me-1"></i> Update Recommendation';
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
		document.getElementById("invest_type").value = "0";
        document.getElementById("asset_class").value = "0"; 
		document.getElementById("invName").value = ""; 
		document.getElementById("issuer").value = ""; 
		document.getElementById("rational").value = ""; 
		document.getElementById("oi_slno").value = "0"; 
        
		var addButton = document.querySelector('button[name="add_oi"]');
		if(addButton) addButton.innerHTML = '<i class="fa fa-plus-circle me-1"></i> Add Recommendation';
		// Find the hidden input element
		var OiActionInput = document.getElementById("oi_action");
		if(OiActionInput) OiActionInput.value = "add";	
}

function backToList() {
	$.ajax({
		url:"recommendation-list.php",
		success:function(responce){
			$("#main_content").html(responce);
		}
	});
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

				if(addButton) addButton.innerHTML = '<i class="fa fa-plus-circle me-1"></i> Add Recommendation';
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







