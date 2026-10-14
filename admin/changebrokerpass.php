<?php
require_once('auth_guard.php');

if(!isset($_SESSION)) { session_start(); } 
if(!isset($_SESSION['user'])) { header("Location: index.php"); }
	require_once('database.php');
	$db =new MYSQLDB();
	
	$output ='';?>
	
	<div class="container-fluid row">
				<div class="col-lg-12 col-md-12 col-sm-12">
					<center>
						<h3 style="margin-top:50px">Change Broker Password</h3>
					  <form action="" method="POST">
						<table style="margin-top:50px" id="tableAddFaq">
							<tr>
								<td>
									<label for="oldPasswrod">Old Password </label>
								</td>
								<td>
									<input type="password" class="form-control form-rounded" name="oldPasswrod" id="oldPasswrod" placeholder="Enter old broker password" />
								</td>
							</tr>
							<tr>
								<td>
									<label for="newPassword">Enter New Password </label>
								</td>
								<td width="80%">
									  <input type="password" class="form-control form-rounded" name="newPassword" id="newPassword" placeholder="Enter new broker password"/>
								</td>
									
							</tr>
							<tr>
								<td>
									<label for="confirmPassword">Confirm New Password </label>
								</td>
								<td width="80%">
									 <input type="password" class="form-control form-rounded" name="confirmPassword" id="confirmPassword" placeholder="Confirm New password" >
								</td>
							</tr>
							
							<tr>
								<td colspan="4">
									<center>
										<button type="button" name="changeAdPassword" id="changeAdPassword" onclick="changeBrokerPassword()"> Save </button>
									</center>
								 </td>
							</tr>
						</table>
					 </form>
					</center>
				</div>	

				<div class="alert alert-danger" id = "errormessage" style="display: none; text-align: center;" ><b></b></div>
				<div class="alert alert-success" id = "successmessage" style="display: none; text-align: center;" ><b></b></div>
			</div>

			<?php
echo $output;
?>

<script type="text/javascript">
	
	function changeBrokerPassword(){
	
	var method = "ChangeBrokerPassword";
	var oldPassword = document.getElementById('oldPasswrod').value;
	var newPassword = document.getElementById('newPassword').value;
	var confirmPassword = document.getElementById('confirmPassword').value;

	if(newPassword == confirmPassword){
		

		var upfId =1;
		$.ajax({
			url:"data_manuplation.php",
			data: {method:method,oldPass:oldPassword,newpass:newPassword,confirmpass:confirmPassword,fId:upfId},
			success:function(responseText){
				alert(responseText);

				document.getElementById("errormessage").style.display="none";
				document.getElementById("successmessage").innerHTML = responseText;
				document.getElementById("successmessage").style.display="block";
				
				
			}
		});

	}else{
		//alert("Not matched");
		document.getElementById("successmessage").style.display="none";
		document.getElementById("errormessage").style.display="block";
		document.getElementById("errormessage").innerHTML = "New password and confirm password not matched";
	}
	
}
</script>