<?php	
	if(!isset($_SESSION)){session_start();}
?>
<html>
	<title>Settings Panel</title>
	<head>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
		 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css"> 
		<style>
			body{
				background-color:lightgrey;
			}
			.input-icons i { position: absolute; } 
			.input-icons { width: 100%; margin-bottom: 10px; } 
			.icon { padding: 10px; color: blue; min-width: 50px; text-align: center; } 
			.input-field { padding: 10px; text-align: center; } 
			h2 { color: blue; } 
		</style>
	</head>
	<body>
		<div class="row">
			<div class="col-md-12" style="height:100px;width:100%;background-color:#f5f2ec">
					<img src="../img/logo.png" style="height:80px;width:200px">
			</div>
		</div>
		<div class="row" style="padding-left:40%;padding-top:10%;padding-bottom:10%">
				<center>
					<div style="padding:10px;background:white;box-shadow:0px 2px 6px 2px white;height:auto;min-height:250px">
		<?php 
			if(isset($_SESSION["logincheck"]))
				echo '<div class="alert alert-danger" style="text-align: center;" >wrong User/Password</div>'; 
			
			unset($_SESSION['logincheck']);
		?>
						<form action="index.php" method="POST">
							<h2>Settings Login</h2> 
							
								<div class="input-icons"> 
									<i class="fa fa-user icon"> 
								  </i> 
									<input class="input-field" type="text" placeholder="Username" name="admin_user" id="login_username"> 
								</div> 

								<div class="input-icons"> 
									<i class="fa fa-key icon"> 
								  </i> 
									<input class="input-field"  type="Password" name="admin_pass"  id="Password1" placeholder="Password"> 
								</div> 
								
								 <button type="submit" class="btn btn-primary">Login</button><br>
								 <a href="../admin/login.php" style="color:#667eea;font-size:13px;text-decoration:none;">Forgot Password? (Go to Admin)</a>
						</form>
					</div>
				</center>
		</div>
	</body>
</html>
