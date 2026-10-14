<?php
require_once('auth_guard.php');

if(!isset($_SESSION)) { session_start(); } 
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit; }

require_once('database.php');
$db = new MySQLDB();

// Handle AJAX request for deleting FAQ
if(isset($_POST['action']) && $_POST['action'] == 'delete') {
    header('Content-Type: application/json');
    
    $faqId = intval($_POST['faqId'] ?? 0);
    
    if($faqId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid FAQ ID']);
        exit;
    }
    
    $result = $db->deleteFaq($faqId);
    
    if($result) {
        echo json_encode(['status' => 'success', 'message' => 'FAQ deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete FAQ']);
    }
    exit;
}

// If it's not an AJAX request, display the list
$result = $db->displayAllFAQs();
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="row mb-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="myInput" onkeyup="myFunction()" placeholder="Search by FAQ Page...">
                </div>
                <div class="col-md-8 text-right">
                    <button class="btn btn-primary" id="addFaqBtn">Add New FAQ</button>
                </div>
            </div>
        </div>
    </div>
    
    <div id="deleteMessage" style="display:none;" class="alert"></div>
    
    <div class="row">
        <div class="col-md-12">
            <table class="table table-bordered table-striped" id="myTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Sr. No.</th>
                        <th>FAQ Page</th>
                        <th>FAQ Question</th>
                        <th>FAQ Answer</th>
                        <th width="100">Edit</th>
                        <th width="100">Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if($result !== false && !empty($result)) {
                        $sr_no = 1;
                        foreach($result as $row){
                            // Strip HTML tags for preview
                            $question = strip_tags($row['faq_question']);
                            $answer = strip_tags(substr($row['faq_ans'], 0, 100));
                            if(strlen($row['faq_ans']) > 100) $answer .= '...';
                            
                            echo '<tr id="row_'.$row['faq_id'].'">
                                    <td>'.$sr_no.'</td>
                                    <td>'.htmlspecialchars($row['faq_page']).'</td>
                                    <td><div class="faq-question-preview" title="'.htmlspecialchars($question).'">'.htmlspecialchars($question).'</div></td>
                                    <td><div class="faq-question-preview" title="'.htmlspecialchars(strip_tags($row['faq_ans'])).'">'.htmlspecialchars($answer).'</div></td>
                                    <td><button class="btn btn-sm btn-warning editFaq" data-id="'.$row['faq_id'].'">Edit</button></td>
                                    <td><button class="btn btn-sm btn-danger deleteFaq" data-id="'.$row['faq_id'].'" data-page="'.htmlspecialchars($row['faq_page']).'">Delete</button></td>
                                </tr>';
                            $sr_no++;
                        }
                    } else {
                        echo '<tr><td colspan="6" class="text-center">No FAQs found</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this FAQ from page: <strong id="deleteFaqPage"></strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    $("#addFaqBtn").click(function(){
        $.ajax({
            url: "add_faq.php",
            success: function(response) {
                $("#main_content").html(response);
            },
            error: function(xhr, status, error) {
                alert("Error loading add FAQ form: " + error);
            }
        });
    });
    
    // Edit button click handler
    $(document).on('click', '.editFaq', function(){
        var getFaqId = $(this).data("id");
        $.ajax({
            url: "edit_faq.php",
            data: { "faqId": getFaqId },
            success: function(response) {
                $("#main_content").html(response);
            },
            error: function(xhr, status, error) {
                alert("Error loading edit form: " + error);
            }
        });
    });
    
    // Delete functionality
    var deleteId = null;
    
    $(document).on('click', '.deleteFaq', function(){
        deleteId = $(this).data("id");
        var faqPage = $(this).data("page");
        $("#deleteFaqPage").text(faqPage);
        $("#deleteConfirmModal").modal("show");
    });
    
    $("#confirmDeleteBtn").click(function(){
        if(deleteId) {
            $.ajax({
                url: "display_faq.php",
                type: "POST",
                data: {
                    action: "delete",
                    faqId: deleteId
                },
                dataType: "json",
                beforeSend: function() {
                    $("#confirmDeleteBtn").prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
                },
                success: function(response) {
                    $("#deleteConfirmModal").modal("hide");
                    
                    if(response.status == "success") {
                        $("#row_" + deleteId).fadeOut(500, function() {
                            $(this).remove();
                            
                            // Check if table is empty
                            if($("#myTable tbody tr").length == 0) {
                                $("#myTable tbody").html('<tr><td colspan="6" class="text-center">No FAQs found</td></tr>');
                            } else {
                                // Renumber serial numbers
                                $("#myTable tbody tr").each(function(index) {
                                    $(this).find('td:first').text(index + 1);
                                });
                            }
                            
                            // Show success message
                            showDeleteMessage(response.message, "success");
                        });
                    } else {
                        showDeleteMessage(response.message, "danger");
                    }
                    $("#confirmDeleteBtn").prop('disabled', false).html('Delete');
                    deleteId = null;
                },
                error: function(xhr, status, error) {
                    $("#deleteConfirmModal").modal("hide");
                    showDeleteMessage("Error occurred while deleting FAQ: " + error, "danger");
                    $("#confirmDeleteBtn").prop('disabled', false).html('Delete');
                    deleteId = null;
                    console.log(xhr.responseText);
                }
            });
        }
    });
});

function showDeleteMessage(message, type) {
    $("#deleteMessage").removeClass("alert-success alert-danger").addClass("alert-" + type).html(message).fadeIn();
    setTimeout(function() {
        $("#deleteMessage").fadeOut();
    }, 3000);
}

// Search functionality
function myFunction() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("myInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("myTable");
    tr = table.getElementsByTagName("tr");
    
    for (i = 1; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td")[1]; // FAQ Page column
        if (td) {
            txtValue = td.textContent || td.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }       
    }
}
</script>