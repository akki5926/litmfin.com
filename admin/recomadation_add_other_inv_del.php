<?php
require_once('auth_guard.php');

	require_once('database.php');
	$db = new MYSQLDB();
	$output='';?>
	<div class="container-fluid row">
		<div class="col-lg-12"><h3 style="text-align: center;"> Add New Investment </h3></div>
		<div class="col-lg-12">
			<form action="" method="post">
				<center>
					<table class="table" style="width: 100%">
						<tr>
							<td>Investment Type</td>
							<td>
								<select id="invest_type" name = "invest_type">
									<option value="0" selected=""> Select Invest Type </option>
									<option value="equity">AIF</option>
									<option value="gold">Bond</option>
									<option value="debt">PMS</option>
								</select>
							</td>
						
							<td>Asset Class</td>
							<td>
								<select id="asset_class" name = "asset_class" >
									<option value="0">Select Asset Class</option>
									<option value="equity">Debt</option>
									<option value="gold">Equity</option>
									<option value="debt">Gold</option>
									<option value="debt">Hybrid</option>
								</select>
							</td>
						
							<td>Name</td>
							<td>
								<input type="text" name="name" id="name" placeholder="Please enter Name of Investment" >
							</td>
						
							<td>Issuer</td>
							<td>
								<input type="text" name="issuer" id="issuer" placeholder="Please enter Name of issuer" >
							</td>
						</tr>
						<tr>
							<td>
								Rational<br><font style="font-size: 25px;color: red;">*</font><font style="color: red">(Don't exceed 100 characters)</font>
							</td>
							<td>
								<textarea id="rational" cols="40" rows="5" name = "rational"></textarea>
							</td>
						</tr>
						<tr>
							<td colspan="2" style="text-align: center;"><button id="save" name="save" type="button" class="btn btn-primary" onclick="senddata()">Submit</button></td>
						</tr>
					</table>
				</center>
			</form>
		</div>
		<div id="success_data" class="alert alert-success" style="display: none;"></div>
	</div>

	<?php echo $output; ?>

	<script type="text/javascript">
		function senddata(){
			document.getElementById("success_data").style.display="none";
			var invest_type = document.getElementById("invest_type").value;
			var asset_class = document.getElementById("asset_class").value;
			var name = document.getElementById("name").value;
			var issuer = document.getElementById("issuer").value;
			var rational = document.getElementById("rational").value;
			var method = "addOtherInv";
			$.ajax({
				url:"saveDataToDb.php",
				method:"POST",
				data:{method:method,invest_type:invest_type,asset_class:asset_class,name:name,issuer:issuer,rational:rational},
				success:function(data){
					//alert(data);
					document.getElementById("success_data").style.display="block";
					$('#success_data').html(data);
				}
			});
		}
	
	</script>
