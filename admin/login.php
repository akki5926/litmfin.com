<?php	
	if(!isset($_SESSION)){session_start();}
	//$_SESSION["loggedin"] = true;
?>
<html>
	<title>Admin Panel</title>
	<head>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
		 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css"> 
		<style>
			body{
				background-color:lightgrey;
			}
			
			.input-icons i { 
				position: absolute; 
			} 
			  
			.input-icons { 
				width: 100%; 
				margin-bottom: 10px; 
			} 
			  
			.icon { 
				padding: 10px; 
				color: blue; 
				min-width: 50px; 
				text-align: center; 
			} 
			  
			.input-field { 
				padding: 10px; 
				text-align: center; 
			} 
			  
			h2 { 
				color: blue; 
			} 
   
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
					<div style=";padding:10px;background:white;box-shadow:0px 2px 6px 2px white;height:auto;min-height:250px">
		<?php 
			if(isset($_SESSION["logincheck"]))
				echo '<div class="alert alert-danger " id = "successmessage"  text-align: "center;" >wrong User/Password</div>'; 
			
			unset($_SESSION['logincheck']);
		?>
		
						<form action="index.php" method="POST">
							<h2>Login</h2> 
							
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
								 <a href="#" onclick="openForgotModal()" style="color:#667eea;font-size:13px;text-decoration:none;">Forgot Password?</a>
						</form>
						

						<!-- <button type="button"  onclick="sendForgotMail()">Forgot Password</button> -->
						<div class="alert alert-success" id = "successmessage" style="display: none; text-align: "center;" ><b></b></div>

						<!-- <button type="button" class="" onclick="sendForgotMail()">Forgot Password</button> -->
					</div>

					
				</center>
		</div>
	</body>
</html>

<!-- Forgot Password Modal -->
<div id="forgotModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
     background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:420px;
                margin:auto;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.2);
                position:absolute;top:50%;left:50%;transform:translate(-50%,-50%)">
        <!-- Modal Header -->
        <div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:24px;text-align:center;">
            <h5 style="margin:0;color:#fff;font-size:18px;font-weight:600;">
                <i class="fas fa-key" style="margin-right:8px"></i>Forgot Password
            </h5>
            <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:13px;">
                Enter your username to reset password
            </p>
        </div>
        <!-- Modal Body -->
        <div style="padding:28px;">
            <div id="forgotAlert" style="display:none;border-radius:8px;padding:10px 14px;
                 font-size:13px;margin-bottom:16px;"></div>

            <div class="form-group" style="margin-bottom:20px">
                <label style="font-weight:500;color:#495057;margin-bottom:8px;display:block;font-size:14px">
                    <i class="fas fa-user" style="margin-right:6px"></i>Username
                </label>
                <input type="text" id="forgot_username" class="form-control" 
                       placeholder="Enter your username" style="padding:10px 12px;border-radius:8px;border:1px solid #ced4da;width:100%">
            </div>

            <div style="display:flex;gap:10px;margin-top:8px;">
                <button onclick="closeForgotModal()"
                        style="flex:1;padding:11px;border:1px solid #ced4da;background:#fff;
                               border-radius:8px;font-size:14px;cursor:pointer;color:#6c757d;">
                    Cancel
                </button>
                <button id="forgotSendBtn" onclick="checkUserAndSendOTP()"
                        style="flex:1;padding:11px;border:none;
                               background:linear-gradient(135deg,#667eea,#764ba2);
                               color:#fff;border-radius:8px;font-size:14px;
                               font-weight:600;cursor:pointer;">
                    <i class="fas fa-paper-plane" style="margin-right:6px"></i>Send OTP
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">

    function openForgotModal() {
        document.getElementById('forgotModal').style.display = 'flex';
        document.getElementById('forgotAlert').style.display = 'none';
        document.getElementById('forgot_username').value = '';
    }

    function closeForgotModal() {
        document.getElementById('forgotModal').style.display = 'none';
    }

    // Close modal if backdrop clicked
    document.getElementById('forgotModal').addEventListener('click', function(e) {
        if (e.target === this) closeForgotModal();
    });

    function showForgotAlert(msg, type) {
        var el = document.getElementById('forgotAlert');
        var isSuccess = type === 'success';
        el.style.display = 'block';
        el.style.background    = isSuccess ? '#d4edda' : (type === 'warning' ? '#fff3cd' : '#f8d7da');
        el.style.border        = '1px solid ' + (isSuccess ? '#c3e6cb' : (type === 'warning' ? '#ffeeba' : '#f5c6cb'));
        el.style.color         = isSuccess ? '#155724' : (type === 'warning' ? '#856404' : '#721c24');
        el.innerHTML = msg;
    }

    function checkUserAndSendOTP() {
        var username = document.getElementById('forgot_username').value.trim();
        
        if (username === '') {
            showForgotAlert('<i class="fas fa-exclamation-triangle" style="margin-right:6px"></i>Please enter your username', 'danger');
            return;
        }

        var sendBtn = document.getElementById('forgotSendBtn');
        var orig = sendBtn.innerHTML;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:6px"></i>Checking...';
        sendBtn.disabled  = true;

        var formData = new FormData();
        formData.append('method', 'CheckUserAndSendOTP');
        formData.append('username', username);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'sendmail.php', true);

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            sendBtn.innerHTML = orig;
            sendBtn.disabled  = false;

            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        showForgotAlert('<i class="fas fa-check-circle" style="margin-right:6px"></i>' + res.message, 'success');
                        setTimeout(function () {
                            window.location.href = 'otp_verification.php';
                        }, 1800);
                    } else {
                        showForgotAlert('<i class="fas fa-exclamation-triangle" style="margin-right:6px"></i>' + res.message, 
                                        res.is_superadmin === false ? 'warning' : 'danger');
                    }
                } catch (e) {
                    showForgotAlert('Unexpected server error. Please try again.', 'danger');
                }
            } else {
                showForgotAlert('Network error. Please try again.', 'danger');
            }
        };

        xhr.send(formData);
    }

    // Allow Enter key in username field
    document.getElementById('forgot_username').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            checkUserAndSendOTP();
        }
    });

</script>