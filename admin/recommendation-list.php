<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">

<?php
require_once('auth_guard.php');

    require_once('database.php');
    $db = new MYSQLDB();
    
    $output='';
?>
    <div class="container-fluid row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <center><h3>Recommendation</h3></center>

            <!-- Risk Category A -->
            <div class="row" style="background-color: #f0f0f0;">
                <div class="col-lg-9"> 
                    <p style="font-size: 20px;"><b><i style="font-size:30px;color: red" class="fa">&#xf0a4;</i>&nbsp;&nbsp;Risk Category A (Very Aggressive)</b> </p>
                </div>
                
                <div class="col-lg-3" style="float: right;"> 
                    <button class="btn btn-primary"  id="Add_aggressive">Add New Fund</button> 
                    <button class="btn btn-info"  onclick="loadOtherInvestments()">Other Fund</button> 
                </div>
            
                <div class="col-lg-12">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Fund Type</th>
                                <th>Fund Category</th>
                                <th>Fund Name (AMC)</th>
                                <th>Scheme</th>
                                <th>Allocation (%)</th>
                                <th>Rationale</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                            $result_A = $db->getRecommendationData(1);
                            if (!empty($result_A)) {
                                foreach($result_A as $row){
                                    $invest = $row["invest_type"] ?? '';
                                    $fund_cat = $row["fund_cat"] ?? '';
                                    $fund_name = $row["fund_name"] ?? $row["amc"] ?? '';
                                    $scheme = $row["scheme_name"] ?? $row["product_id"] ?? '';  // This will now show the scheme name
                                    $allocation = $row["allocation"] ?? '';
                                    $rational = $row["recmd_rational"] ?? '';
                                    $recmd_id = $row["recmd_id"] ?? 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invest); ?></td>
                                <td><?php echo htmlspecialchars($fund_cat); ?></td>
                                <td><?php echo htmlspecialchars($fund_name); ?></td>
                                <td><?php echo htmlspecialchars($scheme); ?></td>
                                <td><?php echo htmlspecialchars($allocation); ?>%</td>
                                <td><?php echo htmlspecialchars($rational); ?></td>
                                <td><button class="btn btn-sm btn-primary" onclick="editdata(<?php echo $recmd_id; ?>)">Edit</button></td>
                                <td><button class="btn btn-sm btn-danger" onclick="deletedata(<?php echo $recmd_id; ?>)" class="btn">Delete</button></td>
                            </tr>
                        <?php 
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>No records found</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>
                </div> 
            </div>

            <br>

            <!-- Risk Category B -->
            <div class="row">
                <div class="col-lg-9"> 
                    <p style="font-size: 20px;"><b><i style="font-size:30px;color: orange" class="fa">&#xf0a4;</i>&nbsp;&nbsp;Risk Category B (Aggressive)</b> </p>
                </div>

                <div class="col-lg-3" style="float: right;"> 
                    <button class="btn btn-primary" id="addaggr" >Add New Fund</button> 
                    <button class="btn btn-info"  onclick="loadOtherInvestments()">Other Fund</button>
                </div>

                <div class="col-lg-12">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Fund Type</th>
                                <th>Fund Category</th>
                                <th>Fund Name (AMC)</th>
                                <th>Scheme</th>
                                <th>Allocation (%)</th>
                                <th>Rationale</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                            $result_B = $db->getRecommendationData(2);
                            if (!empty($result_B)) {
                                foreach($result_B as $row){
                                    $invest = $row["invest_type"] ?? '';
                                    $fund_cat = $row["fund_cat"] ?? '';
                                    $fund_name = $row["fund_name"] ?? $row["amc"] ?? '';
                                    $scheme = $row["scheme_name"] ?? $row["product_id"] ?? '';
                                    $allocation = $row["allocation"] ?? '';
                                    $rational = $row["recmd_rational"] ?? '';
                                    $recmd_id = $row["recmd_id"] ?? 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invest); ?></td>
                                <td><?php echo htmlspecialchars($fund_cat); ?></td>
                                <td><?php echo htmlspecialchars($fund_name); ?></td>
                                <td><?php echo htmlspecialchars($scheme); ?></td>
                                <td><?php echo htmlspecialchars($allocation); ?>%</td>
                                <td><?php echo htmlspecialchars($rational); ?></td>
                                <td><button class="btn btn-sm btn-primary" onclick="editdata(<?php echo $recmd_id; ?>)">Edit</button></td>
                                <td><button class="btn btn-sm btn-danger" onclick="deletedata(<?php echo $recmd_id; ?>)" class="btn">Delete</button></td>
                            </tr>
                        <?php 
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>No records found</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>
                </div> 
            </div>

            <br>

            <!-- Risk Category C -->
            <div style="background-color: #f0f0f0;" class="row">
                <div class="col-lg-9"> 
                    <p style="font-size: 20px;"><b><i style="font-size:30px;color: blue" class="fa">&#xf0a4;</i>&nbsp;&nbsp;Risk Category C (Conservative)</b></p>
                </div>

                <div class="col-lg-3" style="float: right;"> 
                    <button class="btn btn-primary" id="categore_c" >Add New Fund</button> 
                    <button class="btn btn-info"  onclick="loadOtherInvestments()">Other Fund</button>
                </div>

                <div class="col-lg-12">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Fund Type</th>
                                <th>Fund Category</th>
                                <th>Fund Name (AMC)</th>
                                <th>Scheme</th>
                                <th>Allocation (%)</th>
                                <th>Rationale</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                            $result_C = $db->getRecommendationData(3);
                            if (!empty($result_C)) {
                                foreach($result_C as $row){
                                    $invest = $row["invest_type"] ?? '';
                                    $fund_cat = $row["fund_cat"] ?? '';
                                    $fund_name = $row["fund_name"] ?? $row["amc"] ?? '';
                                    $scheme = $row["scheme_name"] ?? $row["product_id"] ?? '';
                                    $allocation = $row["allocation"] ?? '';
                                    $rational = $row["recmd_rational"] ?? '';
                                    $recmd_id = $row["recmd_id"] ?? 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invest); ?></td>
                                <td><?php echo htmlspecialchars($fund_cat); ?></td>
                                <td><?php echo htmlspecialchars($fund_name); ?></td>
                                <td><?php echo htmlspecialchars($scheme); ?></td>
                                <td><?php echo htmlspecialchars($allocation); ?>%</td>
                                <td><?php echo htmlspecialchars($rational); ?></td>
                                <td><button class="btn btn-sm btn-primary" onclick="editdata(<?php echo $recmd_id; ?>)">Edit</button></td>
                                <td><button class="btn btn-sm btn-danger" onclick="deletedata(<?php echo $recmd_id; ?>)" class="btn">Delete</button></td>
                            </tr>
                        <?php 
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>No records found</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>
                </div> 
            </div>

            <br>

            <!-- Risk Category D -->
            <div class="row">
                <div class="col-lg-9"> 
                    <p style="font-size: 20px;"><b><i style="font-size:30px;color: green" class="fa">&#xf0a4;</i>&nbsp;&nbsp;Risk Category D (Ultra Conservative)</b></p>
                </div>

                <div class="col-lg-3" style="float: right;"> 
                    <button class="btn btn-primary" id="categore_d" >Add New Fund</button> 
                    <button class="btn btn-info"  onclick="loadOtherInvestments()">Other Fund</button>
                </div>

                <div class="col-lg-12">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Fund Type</th>
                                <th>Fund Category</th>
                                <th>Fund Name (AMC)</th>
                                <th>Scheme</th>
                                <th>Allocation (%)</th>
                                <th>Rationale</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                            $result_D = $db->getRecommendationData(4);
                            if (!empty($result_D)) {
                                foreach($result_D as $row){
                                    $invest = $row["invest_type"] ?? '';
                                    $fund_cat = $row["fund_cat"] ?? '';
                                    $fund_name = $row["fund_name"] ?? $row["amc"] ?? '';
                                    $scheme = $row["scheme_name"] ?? $row["product_id"] ?? '';
                                    $allocation = $row["allocation"] ?? '';
                                    $rational = $row["recmd_rational"] ?? '';
                                    $recmd_id = $row["recmd_id"] ?? 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invest); ?></td>
                                <td><?php echo htmlspecialchars($fund_cat); ?></td>
                                <td><?php echo htmlspecialchars($fund_name); ?></td>
                                <td><?php echo htmlspecialchars($scheme); ?></td>
                                <td><?php echo htmlspecialchars($allocation); ?>%</td>
                                <td><?php echo htmlspecialchars($rational); ?></td>
                                <td><button class="btn btn-sm btn-primary" onclick="editdata(<?php echo $recmd_id; ?>)">Edit</button></td>
                                <td><button class="btn btn-sm btn-danger" onclick="deletedata(<?php echo $recmd_id; ?>)" class="btn">Delete</button></td>
                            </tr>
                        <?php 
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>No records found</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>
                </div> 
            </div>
        </div>  
    </div>

    <style>
        .table-bordered th, .table-bordered td {
            border: 1px solid #dee2e6;
            padding: 8px;
            vertical-align: middle;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>

    <script type="text/javascript">
        $(document).ready(function(){
            // Removed automatic loading of other investments
        });

        window.loadOtherInvestments = function() {
            if (typeof loadContent === 'function') {
                loadContent('recommendation-other.php', 'Other Fund', true);
            } else {
                $.ajax({
                    url:"recommendation-other.php",
                    success:function(responce){
                        $("#main_content").html(responce);
                    }
                });
            }
        };

        $(document).off('click', '#Add_aggressive').on('click', '#Add_aggressive', function(e){
            e.preventDefault();
            if (typeof loadContent === 'function') {
                loadContent("recommendation_add_new.php?risk=1", "Add New Fund", true);
            } else {
                $.ajax({
                    url:"recommendation_add_new.php?risk=1",
                    success:function(responce){
                        $("#main_content").html(responce);
                    }
                });
            }
        });

        $(document).off('click', '#addaggr').on('click', '#addaggr', function(e){
            e.preventDefault();
            if (typeof loadContent === 'function') {
                loadContent("recommendation_add_new.php?risk=2", "Add New Fund", true);
            } else {
                $.ajax({
                    url:"recommendation_add_new.php?risk=2",
                    success:function(responce){
                        $("#main_content").html(responce);
                    }
                });
            }
        });

        $(document).off('click', '#categore_c').on('click', '#categore_c', function(e){
            e.preventDefault();
            if (typeof loadContent === 'function') {
                loadContent("recommendation_add_new.php?risk=3", "Add New Fund", true);
            } else {
                $.ajax({
                    url:"recommendation_add_new.php?risk=3",
                    success:function(responce){
                        $("#main_content").html(responce);
                    }
                });
            }
        });

        $(document).off('click', '#categore_d').on('click', '#categore_d', function(e){
            e.preventDefault();
            if (typeof loadContent === 'function') {
                loadContent("recommendation_add_new.php?risk=4", "Add New Fund", true);
            } else {
                $.ajax({
                    url:"recommendation_add_new.php?risk=4",
                    success:function(responce){
                        $("#main_content").html(responce);
                    }
                });
            }
        });

        $(document).off('click', '#add_new_fund_unified').on('click', '#add_new_fund_unified', function(e){
            e.preventDefault();
            if (typeof loadContent === 'function') {
                loadContent("recommendation_add_new.php?risk=1", "Add New Fund", true);
            } else {
                $.ajax({
                    url:"recommendation_add_new.php?risk=1",
                    success:function(responce){
                        $("#main_content").html(responce);
                    }
                });
            }
        });

        $(document).off('click', '#add_other_fund_unified').on('click', '#add_other_fund_unified', function(){
            loadOtherInvestments();
        });
    </script>

    <script>
    function deletedata(id) {
        if (confirm('Are you sure you want to delete this record?')) {
            $.ajax({
                url:"rec_del.php",
                method:"POST",
                data:{id:id},
                success:function(data){
                    $.ajax({
                        url:"Recommendation-list.php",
                        success:function(responce){
                            $("#main_content").html(responce);
                        }
                    });
                }
            });
        }
    }

    function editdata(id){
        $.ajax({
            url:"editrecommedation.php",
            data:{id:id},
            success:function(responce){
                $("#main_content").html(responce);
            }
        });
    } 
    </script>