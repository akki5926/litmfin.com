<?php
require_once('auth_guard.php');

	require_once('database.php');
	$db = new MYSQLDB();
	
	
	$output='<div class="container-fluid row">
				<div class="col-lg-12 col-md-12 col-sm-12">
					<center>
						<h3 style="margin-top:10px">Upload MF Scheme Master file </h3>
						(Downloaded from NSENMF SchemeMaster) <a href="SchemeMaster-Template.xlsx" download>Download Template File</a>
					  <form action="" method="POST" id="my-awesome-dropzone" enctype="multipart/form-data">
						<table style="margin-top:10px" id="tableAddFaq">
							<tr>
								<td>
                                    <label for="fileToUpload">Upload MF Scheme Master file </label>
                                    <input type="hidden" value="UploadMfList" name="hiddenmethod" id="hiddenmethod" />
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
										<button type="button" name="uploadMfBtn" id="uploadMfBtn" onclick="uploadMfList()" >Upload </button>
										
									</center>
								 </td>

									


							</tr>
						</table>
                     </form>
					 
					 
                     <div id="loader" style="display:none;">
                							<img src="../img/loader.svg" width="50px" height="50px">
										</div>
					</center><div id="ResultDisplay"></div>
					
				</div>	
			</div>';
echo $output;
	
?>





