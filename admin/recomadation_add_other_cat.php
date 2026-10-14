<?php
require_once('auth_guard.php');

	require_once('database.php');
	$db = new MYSQLDB();
	$cat_id = isset($_REQUEST['cat_id']) ? (int)$_REQUEST['cat_id'] : 0;

	$catNames = array(1 => 'Very Aggressive', 2 => 'Aggressive', 3 => 'Conservative', 4 => 'Ultra Conservative');
	$catName = isset($catNames[$cat_id]) ? $catNames[$cat_id] : 'Unknown';
?>

<div class="container-fluid row">
	<div class="col-lg-12">
		<button type="button" class="btn btn-default" onclick="$.ajax({url:'Recommendation-list.php',success:function(r){$('#main_content').html(r);}});"><i class="fa fa-arrow-left"></i> Back</button>
		<h3 style="text-align: center;">Add Other Recommendation - <?php echo htmlspecialchars($catName); ?></h3>
	</div>

	<div class="col-lg-12">
		<form id="add_other_rec" method="POST" onsubmit="addOtherRec(); return false;">
			<center>
				<table class="table" style="width: 100%">

					<tr>
						<td>Investment Type</td>
						<td>
							<select id="oi_invest_type" name="invest_type">
								<option value="0" selected="">Select Investment Type</option>
								<option value="AIF">AIF</option>
								<option value="Bond">Bond</option>
								<option value="PMS">PMS</option>
							</select>
						</td>
					</tr>

					<tr>
						<td>Asset Class</td>
						<td>
							<select id="oi_asset_class" name="asset_class">
								<option value="0">Select Asset Class</option>
								<option value="Debt">Debt</option>
								<option value="Equity">Equity</option>
								<option value="Gold">Gold</option>
								<option value="Hybrid">Hybrid</option>
							</select>
						</td>
					</tr>

					<tr>
						<td>Name</td>
						<td>
							<input type="text" id="oi_invName" name="invName" placeholder="Please enter Name of Investment" style="width:80%;">
						</td>
					</tr>

					<tr>
						<td>Issuer</td>
						<td>
							<input type="text" id="oi_issuer" name="issuer" placeholder="Please enter Name of Issuer" style="width:80%;">
						</td>
					</tr>

					<tr>
						<td>Rational<br><font style="font-size: 25px;color: red;">*</font><font style="color: red">(Don't exceed 100 characters)</font></td>
						<td>
							<textarea id="oi_rational" cols="40" rows="5" name="rational"></textarea>
						</td>
					</tr>

					<tr>
						<td colspan="2" style="text-align: center;">
							<input type="hidden" id="oi_action" value="add">
							<input type="hidden" id="oi_slno" value="0">
							<input type="hidden" id="oi_category_id" value="<?php echo $cat_id; ?>">
							<button type="submit" class="btn btn-primary">Submit</button>
						</td>
					</tr>

				</table>
			</center>
		</form>
	</div>

	<div id="oi_status" class="alert alert-success" style="display: none;"></div>

	<div class="col-lg-12">
		<h4>Other Recommendations for <?php echo htmlspecialchars($catName); ?></h4>
		<table class="table" id="otherRecTable" style="border-collapse: collapse;">
			<thead>
				<tr style="background-color: #e0e0ff;">
					<th>Investment Type</th>
					<th>Asset Class</th>
					<th>Name</th>
					<th>Issuer</th>
					<th>Rational</th>
					<th>Edit</th>
					<th>Delete</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>

</div>

<script type="text/javascript">
	var otherRecCatId = <?php echo $cat_id; ?>;

	fetchOtherRecData();

	function addOtherRec() {
		var invest_type = document.getElementById("oi_invest_type").value;
		var asset_class = document.getElementById("oi_asset_class").value;
		var invName = document.getElementById("oi_invName").value;
		var issuer = document.getElementById("oi_issuer").value;
		var rational = document.getElementById("oi_rational").value;
		var slno = document.getElementById("oi_slno").value;
		var action = document.getElementById("oi_action").value;
		var category_id = document.getElementById("oi_category_id").value;

		if(invest_type == '' || invest_type == '0'){ alert('Please select Investment Type'); return; }
		if(invName == ''){ alert('Please enter Name'); return; }

		$.ajax({
			url: "process_rec_other.php",
			method: "POST",
			data: {
				method: "addOtherInv",
				invest_type: invest_type,
				asset_class: asset_class,
				invName: invName,
				issuer: issuer,
				rational: rational,
				slno: slno,
				action: action,
				category_id: category_id
			},
			success: function(data) {
				document.getElementById("oi_status").style.display = "block";
				$('#oi_status').html(data);
				fetchOtherRecData();
				resetOtherRecForm();
			}
		});
	}

	function fetchOtherRecData() {
		$.ajax({
			url: "process_rec_other.php",
			method: "POST",
			data: { method: "getRecData", category_id: otherRecCatId },
			dataType: "json",
			success: function(data) {
				populateOtherRecTable(data);
			}
		});
	}

	function populateOtherRecTable(investedData) {
		var tableBody = document.querySelector('#otherRecTable tbody');
		tableBody.innerHTML = '';
		if(!investedData || investedData.length == 0) return;

		var rowStyle = '';
		investedData.forEach(function(invested) {
			var row = document.createElement('tr');
			row.setAttribute('style', rowStyle);
			var tr = '';
			tr += "<td>" + invested['investment_type'] + "</td>";
			tr += "<td>" + invested['asset_class'] + "</td>";
			tr += "<td>" + invested['name'] + "</td>";
			tr += "<td>" + invested['issuer'] + "</td>";
			tr += "<td>" + invested['rational'] + "</td>";
			tr += "<td><button class='btn btn-sm' onclick='editOtherRec(\"" + invested['slno'] + "\")'>Edit</button></td>";
			tr += "<td><button class='btn btn-sm' onclick='deleteOtherRec(\"" + invested['slno'] + "\",\"" + invested['name'] + "\")'>Delete</button></td>";
			row.innerHTML = tr;
			tableBody.appendChild(row);
			rowStyle = rowStyle === '' ? 'background-color: #F2F2F2;' : '';
		});
	}

	function editOtherRec(slno) {
		$.ajax({
			url: "process_rec_other.php",
			method: "POST",
			data: { method: "getInvDataBySlno", slno: slno },
			dataType: "json",
			success: function(oiData) {
				document.getElementById("oi_invest_type").value = oiData.investment_type;
				document.getElementById("oi_asset_class").value = oiData.asset_class;
				document.getElementById("oi_invName").value = oiData.name;
				document.getElementById("oi_issuer").value = oiData.issuer;
				document.getElementById("oi_rational").value = oiData.rational;
				document.getElementById("oi_action").value = "edit";
				document.getElementById("oi_slno").value = slno;
			}
		});
	}

	function deleteOtherRec(slno, iname) {
		if(!confirm('Are you sure you want to delete ' + iname + '?')) return;
		$.ajax({
			url: "process_rec_other.php",
			method: "POST",
			data: { method: "del_rec", slno: slno, iname: iname },
			success: function(data) {
				document.getElementById("oi_status").style.display = "block";
				$('#oi_status').html(data);
				fetchOtherRecData();
			}
		});
	}

	function resetOtherRecForm() {
		document.getElementById("oi_invest_type").value = "0";
		document.getElementById("oi_asset_class").value = "0";
		document.getElementById("oi_invName").value = "";
		document.getElementById("oi_issuer").value = "";
		document.getElementById("oi_rational").value = "";
		document.getElementById("oi_action").value = "add";
		document.getElementById("oi_slno").value = "0";
	}
</script>
