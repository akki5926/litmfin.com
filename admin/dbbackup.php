<?php
require_once('auth_guard.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

	require_once('database.php');
	$db = new MYSQLDB();
	
	
	$output='<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card">
                <div class="card-body">
                    <h3 class="text-center mb-4">Backup Database</h3>
                    <form action="" method="POST" id="backup_data" enctype="multipart/form-data">
                        <input type="hidden" value="backupFile" name="hiddenmethod" />
                        <div class="text-center">
                            <button type="button" class="btn btn-primary" name="backupDbTables" onclick="takeBackupDbTables()">Backup Database</button>
                        </div>
                    </form>
                    <hr>
                    <h3 class="text-center mb-4">Restore Database</h3>
                    <form action="" method="POST" id="restore_data" enctype="multipart/form-data">
                        <input type="hidden" value="restoreFile" name="hiddenmethod" />
                        <div class="form-group row">
                            <label for="fileToUpload" class="col-sm-4 col-form-label">Upload Backup File : </label>
                            <div class="col-sm-8">
                                <input type="file" class="form-control-file" name="fileToUpload" id="fileToUpload" accept=".sql" />
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="button" class="btn btn-primary" name="restoreDbTables" onclick="restoreFile()">Restore Database</button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="loader" class="text-center mt-4" style="display:none;">
                <img src="../img/loader.svg" width="50" height="50" alt="Loading...">
            </div>
            <div id="ResultDisplay" class="text-center mt-4"></div>
        </div>
    </div>
</div>
';
echo $output;
	
?>

<script>
    function takeBackupDbTables() {
        //var txt = document.getElementById("txtWord").value;
        var formData = new FormData(document.getElementById('backup_data'));// yourForm: form selector        
		$.ajax({
		    type: "POST",
		    url: "data_manuplation.php",// where you wanna post
		    data: formData,
		    processData: false,
		    contentType: false,
		    beforeSend: function(){
				// Show image container
				$("#loader").show();
			},
			success: function(data) {$("#ResultDisplay").html(data);},
		   complete:function(data){
		    // Hide image container
				$("#loader").hide();
				
		   }
		});
    }
    function restoreFile() {
		// Check if a file is selected
		var fileToUpload = document.getElementById('fileToUpload').files[0];
		if (!fileToUpload) {
			alert("Please select a file to restore the database.");
			return; // Exit function if no file is selected
		}
		if (confirm("Are you sure you want to restore the database? This action cannot be undone.")) {

			var formData = new FormData(document.getElementById('restore_data'));
			//console.log(formData);		
			formData.append('hiddenmethod', 'restoreFile'); // Set the hidden method		

			$.ajax({
				type: "POST",
				url: "data_manuplation.php",
				data: formData,
				processData: false,
				contentType: false,
				beforeSend: function() {
					$("#loader").show();
				},
				success: function(data) {
					$("#ResultDisplay").html(data);
				},
				error: function(xhr, status, error) {
					console.error("AJAX request failed:", status, error);
					$("#ResultDisplay").html("An error occurred while processing the request.");
				},
				complete: function(data) {
					$("#loader").hide();
				}
			});
		}
	}

</script>





