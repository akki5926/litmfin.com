<?php
require_once('auth_guard.php');

    $output ='';

    require_once('database.php');
    $db = new MYSQLDB();

    $result = $db->getallRiskcategories();
    $recmndFundRes = $db->getAllRecmdFunds();

    
    if($recmndFundRes){
        foreach($result as $row){
            $output .='
                <div class="container-fluid row" style="padding-top:20px">
                    <div class="col-md-12">
                        <table style="width:100%" class="table table-striped">
                            
                                <center>
                                    <h5 style="margin-top:30px;margin-bottom:30px">Add Recommanded Funds in Risk Category '.$row['risk_category'].' ('.$row['category_name'].')</h5>
                                    <button type="submit" class="btn btn-primary" style="float:right;margin-bottom:10px" name="btnNewFund" id="btnNewFund" data-toggle="modal" data-target="#exampleModal_'.$row['risk_category'].'">Add New Fund</button>
                                </center>
                            <tr>
                                    <th>AMC Code</th>
                                    <th>Fund Type</th>
                                    <th>Fund Name</th>
                                    <th>Allocation(%)</th>
                                    <th>Rational</th>
                                    <th>Operations</th>
                            </tr>
            ';
            foreach($recmndFundRes as $rcmdFund){
                if($row['risk_category_id']===$rcmdFund['category_id']){
                    $output .='
                            <tr>
                                    <td>25</td>
                                    <td>'.$rcmdFund['asset_class'].'</td>
                                    <td>'.$rcmdFund['recmd_fund_name'].'</td>
                                    <td>'.$rcmdFund['recmd_fund_allocation'].'</td>
                                    <td>'.$rcmdFund['recmd_rational'].'</td>
                                    <td>
                                        <button style="float:left;padding:0px;margin-right:10px"> Edit </button>
                                        <button style="float:left;padding:0px;margin-right:10px"> Delete </button>
                                    </td>
                             </tr>
                    ';
                }
            }
                $output .='</table>
                        </div>
                    </div>
                    
                    <!-- Modal -->
                        <div class="modal fade" id="exampleModal_'.$row['category_id'].'" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <div class="modal-body">
                                    <form action="" method="">
                                        <table>
                                            '.$row['category_id'].';
                                        </table>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary">Save changes</button>
                                </div>
                            </div>
                        </div>';
        }
    }else{
            foreach($result as $row){
                $output .='
                <div class="container-fluid row" style="padding-top:20px">
                    <div class="col-md-12">
                        <center>
                            <h5 style="margin-top:30px;margin-bottom:30px">Add Recommanded Funds in Risk Category '.$row['risk_category'].' ('.$row['risk_category_name'].')</h5>
                            <button type="submit" class="btn btn-primary" style="float:right;margin-bottom:10px" name="btnNewFund" id="btnNewFund" data-toggle="modal" data-target="#exampleModal_'.$row['risk_category'].'">Add New Fund</button>
                        </center>
                    </div>
                </div>
                <!-- Modal -->
                        <div class="modal fade" id="exampleModal_'.$row['risk_category'].'" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <div class="modal-body">
                                    <form action="" method="">
                                        <table>
                                            <tr>
                                                <td>
                                                    <input type="text" value="'.$row['risk_category_name'].'" name="riskCategory" id="riskCategory" disabled>
                                                    <input type="hidden" value="'.$row['risk_category_id'].'" name="riskCategoryId" id="riskCategoryId" />
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                        Amc Code 
                                                </td>
                                                <td>
                                                    <select name="selectAmcCode" id="selectAmcCode" onChange="getALlFund()">
                                                        <option value="0" disabled selected>
                                                            Select Amc Code
                                                        </option>
                                                        ';
                                                        $AmcCodeRes = $db->getAllUniqueAmcCode();
                                                        foreach($AmcCodeRes as $AmcCode){
                                                            $output .='
                                                                <option value="'.$AmcCode['amc_code'].'">'.$AmcCode['LONG_NAME'].'</option>
                                                            ';
                                                        } 
                                                    $output .='</select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                        Fund Type 
                                                </td>
                                                <td>
                                                    <select name="selectFundType" id="selectFundType">
                                                        <option  value="0" disabled selected>
                                                            Select Fund Type
                                                        </option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                        Fund Name 
                                                </td>
                                                <td>
                                                    <select name="selectFundName" id="selectFundName">
                                                        <option  value="0" disabled selected>
                                                            Select Fund Name
                                                        </option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                        Allocation 
                                                </td>
                                                <td>
                                                    <input type="text" placeholder="Enter Allocation percentage" name="txtAllocation" id="txtAllocation" />
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                        Rational 
                                                </td>
                                                <td>
                                                    <input type="text" placeholder="Enter Rational" name="txtRational" id="txtRational" />
                                                </td>
                                            </tr>
                                            
                                        </table>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" name="saveFund" id="saveFund">Save changes</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="fundtype"></div>
                    ';
            }

            

    }

    $output .= '
        <script>
            $(document).ready(function(){
                $("#selectAmcCode").change(function(){
                   var amcCodeName = $(this).val();
                   
                   $.ajax({
                    url:"get_fund_type.php",
                    data:{"amcName" : "127"},
                    success:function(responce){
                        $("#fundtype").html(responce);
                    }
                });
                });
            });
        </script>
    ';
    echo $output;
?>
