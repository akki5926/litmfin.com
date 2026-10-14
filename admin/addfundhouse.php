<?php
require_once('auth_guard.php');

	require_once('database.php');
	$db = new MYSQLDB();
	
	
	$output='<div class="container-fluid row">
				<div class="col-lg-12 col-md-12 col-sm-12">
					<center>
						<h3 style="margin-top:100px">Upload Fund House Master file </h3>
						(Downloaded from NSENMF Download Master-AMC) <a href="Amc - ARN-xxxxxx.xls" download>Download Template File</a>
					  <form action="" method="POST" id="my-awesome-dropzone" enctype="multipart/form-data">
						<table style="margin-top:50px" id="tableAddFaq">
							<tr>
								<td>
                                    <label for="fileToUpload">Upload Fund House file </label>
                                    <input type="hidden" value="UploadAMC" name="hiddenmethod" id="hiddenmethod" />
								</td>
								<td>
                                <input type="file" class="form-control" name="fileToUpload" id="fileToUpload"/>
								</td>
							</tr>
							<tr>
								<td>
									<label for="remarks">Remarks </label>
								</td>
								<td width="80%">
                                     <textarea class="form-control" name="remarks" id="remarks" rows="3" cols="10"> </textarea>
								</td>
									
							</tr>
							<tr>
								<td colspan="2">
									<center>
										<button type="button" name="uploadAmcBtn" id="uploadAmcBtn" onclick="UploadAMC()" >Upload </button>
										
									</center>
								 </td>

									


							</tr>
						</table>=-097654321`
                     </form><br>
					 
					 
                     <div id="loader" style="display:none;">
                							<img src="../img/loader.svg" width="50px" height="50px">
										</div>
					<div id="ResultDisplay">
					</div>
					</center>
				</div>	
			</div>';
echo $output;
	
?>





