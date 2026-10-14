<?php
require_once('auth_guard.php');

	require_once('database.php');
	$db = new MYSQLDB();
	
	
	$output='';?>

		<div class="container-fluid row">
			<div class="col-lg-12"><h3 style="text-align: center;"> Add Recommanded Funds in Risk Category D (Ultra Conservative) </h3></div>
			<div class="col-lg-12">
				<form action="" method="post">
					<center>
						<table class="table" style="width: 100%">
							
							<tr>
								<td>
									Fund Type
								</td>
								<td>
									<select id="invest_type" name = "invest_type">
										<option value="0" selected=""> Select Invest Type </option>
										<option value="equity">Equity</option>
										<option value="gold">Gold</option>
										<option value="debt">Debt</option>
										<option value="fof">FOF</option>
										<option value="hybrid">Hybrid</option>
										<option value="taxsaving">Tax Saving</option>
										<option value="arbitrage">Arbitrage</option>
										<option value="etf">ETF</option>

										
									</select>
								</td>
							</tr>

							<tr>
								<td>
									Fund Category
								</td>
								<td>
									<select id="fund_cat" name = "fund_cat" onchange="checkdata()">
										<option value="0">Select Fund Category</option>
									</select>
								</td>
							</tr>

							<tr>
								<td>
									Fund House
								</td>
								<td>
									<select id="fund_house" name = "fund_house" onchange="checkdata()">
										<option value="0" selected=""> Select Invest Type </option>
										<?php

											$results = $db->getAllUniqueAmcCode();
						           			foreach ($results as $row)
											{ 
						                		echo "<option value='".$row['amc_code']."'> ".$row['LONG_NAME']." </option>";
						         			}
											 

										?>

										
									</select>
								</td>
							</tr>

							


							<tr>
								<td>
									Scheme
								</td>
								<td>
									<select id="scheme" name = "scheme">
										<option>Select Fund Type</option>
									</select>
								</td>
							</tr>

							<tr>
								<td>
									Allocation
								</td>
								<td>
									<input type="text" name="allocation" id="allocation" placeholder="Please enter allocation" value="25">%
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
			<div id="success_data" class="alert alert-success" style="display: none"></div>
		
		</div>



	<?php 
		echo $output;
	?>

	<script type="text/javascript">
		
		$(document).ready(function(){
			$('#invest_type').change(function(){
				//alert("hello");
				var invest_type=$(this).val();
				$.ajax({
					url:"invest.php",
					method:"POST",
					data:{invest:invest_type},
					
					success:function(data){
						//alert(data);
						$('#fund_cat').html(data);
					}

				});
			});

		});

		function checkdata(){
			//alert("hello");
			
			var amc = document.getElementById("fund_house").value;
			//alert("amc"+ amc);
			var cat = document.getElementById("fund_cat").value;

			if(amc == "" || amc == "0"){
				alert("Please select fund house");
			}
			else if(cat =="" || cat == "0"){
				alert("Please select fund category");
			} else{

				//alert(amc + cat);
				$.ajax({
					url:"scheme.php",
					method:"POST",
					data:{amc:amc,category:cat},
					
					success:function(data){
						//alert(data);
						
						$('#scheme').html(data);
					}

				});
				

			}


		}

		function senddata(){
			document.getElementById("success_data").style.display="none";

			var invest_type = document.getElementById("invest_type").value;

			var Risk_id = 4;
			var fund_house = document.getElementById("fund_house").value;
			var fund_cat = document.getElementById("fund_cat").value;

			var scheme = document.getElementById("scheme").value;
			var allocation = document.getElementById("allocation").value;

			//var fund_house = document.getElementById("fund_house").value;
			var rational = document.getElementById("rational").value;
			//alert("Risk_id=1 &invest_type="+invest_type+"&fund_house="+fund_house+"&fund_cat="+fund_cat+"&scheme="+scheme+"&allocation="+allocation+"&rational="+rational);



			$.ajax({
					url:"senddata.php",
					method:"POST",
					data:{Risk_id:Risk_id,invest_type:invest_type,fund_house:fund_house,fund_cat:fund_cat,scheme:scheme,allocation:allocation,rational:rational},
					
					success:function(data){
						document.getElementById("success_data").style.display="block";
						//alert(data);
						$('#success_data').html(data);
					}

			});

		}

	</script>
