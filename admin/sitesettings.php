<?php
require_once('auth_guard.php');

require_once('database.php');
$db = new MYSQLDB();

// Check if form is submitted for updating information
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	echo "form submitted";
    $registeredAddress = $_POST["registered_address"];
    $corporateAddress = $_POST["corporate_address"];
    $supportEmail = $_POST["support_email"];
    $supportPhone = $_POST["support_phone"];

    // Update the information in the database
    $return = $db->updateCompanyInfo($registeredAddress, $corporateAddress, $supportEmail, $supportPhone);
	if( $return )
		echo "Company information updated successfully.";
	else
		echo "Company information update failed.";
}

// Fetch the company information from the database
$companyInfo = $db->getCompanyInfo();

$output = '';
?>

<div class="container-fluid row">
    <div class="col-lg-12 col-md-12 col-sm-12">
        <center>
            <h3 style="margin-top: 10px;">Change Company Information</h3>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                <table style="margin-top: 50px;" id="tableCompanyInfo">
                    <tr>
                        <td>
                            <label for="registered_address">Registered Office Address:</label>
                        </td>
                        <td>
                            <textarea class="form-control form-rounded" name="registered_address"
                                id="registered_address"><?php echo isset($companyInfo['registered_address']) ? $companyInfo['registered_address'] : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="corporate_address">Corporate Office Address:</label>
                        </td>
                        <td>
                            <textarea class="form-control form-rounded" name="corporate_address"
                                id="corporate_address"><?php echo isset($companyInfo['corporate_address']) ? $companyInfo['corporate_address'] : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="support_email">Customer Support Email ID:</label>
                        </td>
                        <td width="80%">
                            <input type="email" class="form-control form-rounded" name="support_email"
                                id="support_email"
                                value="<?php echo isset($companyInfo['support_email']) ? $companyInfo['support_email'] : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label for="support_phone">Customer Support Phone Number:</label>
                        </td>
                        <td width="80%">
                            <input type="tel" class="form-control form-rounded" name="support_phone"
                                id="support_phone"
                                value="<?php echo isset($companyInfo['support_phone']) ? $companyInfo['support_phone'] : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <center>
                                <button type="submit" name="updateInfo" id="updateInfo">Save</button>
                            </center>
                        </td>
                    </tr>
                </table>
            </form>
        </center>
    </div>

    <div class="alert alert-danger" id="errormessage" style=" text-align: center;"><b></b></div>
    <div class="alert alert-success" id="successmessage" style=" text-align: center;"><b></b></div>
</div>

<script type="text/javascript">
    function updateCompanyInfo() {
        var method = "UpdateCompanyInfo";
        var registeredAddress = document.getElementById('registered_address').value;
        var corporateAddress = document.getElementById('corporate_address').value;
        var supportEmail = document.getElementById('support_email').value;
        var supportPhone = document.getElementById('support_phone').value;

        // Add your AJAX code here to send the updated information to the server

        // For now, let's display a success message
        document.getElementById("errormessage").style.display = "none";
        document.getElementById("successmessage").innerHTML = "Company information updated successfully.";
        document.getElementById("successmessage").style.display = "block";
    }
</script>
