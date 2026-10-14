<?php
require_once('auth_guard.php');

    require_once('database.php');
    $db = new MYSQLDB();
    $Id = $_REQUEST['id'] ?? 0;
    
    //error_log("editrecommedation.php called with ID = " . $Id);
    
    $res = $db->getRecData($Id);
    
    // Initialize variables with default values
    $invest_type = '';
    $amc = '';
    $fund_cat = '';
    $scheme = '';
    $allocation = '';
    $relational = '';
    $recmd_id = 0;
    
    // Check if we have results
    if (!empty($res) && count($res) > 0) {
        $row = $res[0]; // Get the first row
        
        //error_log("Available columns: " . implode(", ", array_keys($row)));
        
        // Get values with null coalescing
        $invest_type = $row["invest_type"] ?? '';
        $fund_cat = $row["fund_cat"] ?? '';
        $amc = $row["amc_name"] ?? $row["amc"] ?? '';
        $scheme = $row["scheme_name"] ?? $row["product_id"] ?? '';  // Updated this line
        $allocation = $row["allocation"] ?? '';
        $relational = $row["recmd_rational"] ?? '';
        $recmd_id = $row["recmd_id"] ?? $Id;
    } else {
        echo '<div class="alert alert-danger">No record found with ID: ' . htmlspecialchars($Id) . '</div>';
    }

    
    $output='';
?>

<div class="container-fluid row">
    <div class="col-lg-12"><h3 style="text-align: center;">Update Fund</h3></div>
    <div class="col-lg-12">
        <form action="" method="post">
            <center>
                <table class="table" style="width: 100%">
                    
                    <tr>
                        <td>Select Invest Type</td>
                        <td>
                            <p><?php echo htmlspecialchars($invest_type); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <td>Select Fund Category</td>
                        <td>
                            <p><?php echo htmlspecialchars($fund_cat); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <td>Select Fund House</td>
                        <td>
                            <p><?php echo htmlspecialchars($amc); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <td>Select Scheme</td>
                        <td>
                            <p><?php echo htmlspecialchars($scheme); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <td>Allocation</td>
                        <td>
                            <input type="text" name="allocation" id="allocation" 
                                   placeholder="Please enter allocation" 
                                   value="<?php echo htmlspecialchars($allocation); ?>">%
                        </td>
                    </tr>

                    <tr>
                        <td>
                            Rationale<br><font style="color: gray">(Optional, max 100 chars)</font>
                        </td>
                        <td>
                            <textarea id="rational" cols="40" rows="5" name="rational"><?php echo htmlspecialchars($relational); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <td colspan="2" style="text-align: center;">
                            <button id="save" name="save" type="button" class="btn btn-info" 
                                    onclick="senddata(<?php echo htmlspecialchars($recmd_id); ?>)">Update</button>
                        </td>
                    </tr>
                    
                </table>
            </center>
        </form>
    </div>
    <div id="success_data" class="alert alert-success" style="display: none;"></div>
</div>

<?php 
    echo $output;
?>

<script type="text/javascript">
    $(document).ready(function(){
        $('#invest_type').change(function(){
            var invest_type = $(this).val();
            $.ajax({
                url:"invest.php",
                method:"POST",
                data:{invest:invest_type},
                success:function(data){
                    $('#fund_cat').html(data);
                }
            });
        });
    });

    function checkdata(){
        var amc = document.getElementById("fund_house").value;
        var cat = document.getElementById("fund_cat").value;

        if(amc == "" || amc == "0"){
            alert("Please select fund house");
        }
        else if(cat == "" || cat == "0"){
            alert("Please select fund category");
        } else{
            $.ajax({
                url:"scheme.php",
                method:"POST",
                data:{amc:amc, category:cat},
                success:function(data){
                    $('#scheme').html(data);
                }
            });
        }
    }

    function senddata(id){
        document.getElementById("success_data").style.display = "none";

        var allocation = document.getElementById("allocation").value;
        var rational = document.getElementById("rational").value;

        if (!allocation || allocation.trim() === '') {
            alert("Please enter allocation value");
            return;
        }

        $.ajax({
            url:"update_fund.php",
            method:"POST",
            data:{Id:id, allocation:allocation, rational:rational},
            success:function(data){
                document.getElementById("success_data").style.display = "block";
                $('#success_data').html(data);

                $.ajax({
                    url:"Recommendation-list.php",
                    success:function(responce){
                        $("#main_content").html(responce);
                    }
                });
            },
            error: function(xhr, status, error) {
                alert("Error updating data: " + error);
            }
        });
    }
</script>