<?php
require_once('auth_guard.php');

if(!isset($_SESSION)) { session_start(); } 
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit; }

require_once('database.php');
$db = new MySQLDB();

// Handle AJAX request for updating FAQ
if(isset($_POST['action']) && $_POST['action'] == 'update') {
    header('Content-Type: application/json');
    
    $faqId = intval($_POST['faqId'] ?? 0);
    $faqPage = preg_replace('/[^a-zA-Z0-9\s.,_\-@]/', '', $_POST['faqPage'] ?? '');
    $faqQuestion = preg_replace('/[^a-zA-Z0-9\s.,_\-@]/', '', $_POST['faqQuestion'] ?? '');
    $faqAnswer = preg_replace('/[^a-zA-Z0-9\s.,_\-@]/', '', $_POST['faqAnswer'] ?? '');
    
    if($faqId <= 0 || empty($faqPage) || empty($faqQuestion) || empty($faqAnswer)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }
    
    $result = $db->ModifyingFaq($faqPage, $faqQuestion, $faqAnswer, $faqId);
    
    if($result) {
        echo json_encode(['status' => 'success', 'message' => 'FAQ updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update FAQ']);
    }
    exit;
}

$getFaqId = isset($_REQUEST['faqId']) ? intval($_REQUEST['faqId']) : 0;

// If it's not an AJAX request and has valid ID, display the form
if($getFaqId > 0) {
    $result = $db->getSingleFaqDetail($getFaqId);
    
    if($result !== false && !empty($result)) {
        $row = $result[0]; // Get the first row
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10 col-sm-12">
            <center>
                <h3 style="margin-top:100px">Edit Faq Details</h3>
                <div id="message" style="display:none;" class="alert"></div>
                <form id="editFaqForm" method="POST">
                    <table id="tableAddFaq" class="table table-bordered">
                        <tr>
                            <td colspan="2">
                                <input type="hidden" name="upFaqId" id="upFaqId" value="<?php echo $getFaqId; ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td style="width:20%">
                                <label for="upFaqPage">Enter FAQ Page</label>
                            </td>
                            <td style="width:80%">
                                <input class="form-control form-rounded" type="text" name="upFaqPage" id="upFaqPage" value="<?php echo htmlspecialchars($row['faq_page']); ?>" placeholder="Enter FAQ Page" required />
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="upFaqQuestion">Enter FAQ Heading</label>
                            </td>
                            <td>
                                <textarea class="form-control form-rounded" name="upFaqQuestion" id="upFaqQuestion" placeholder="Enter FAQ Heading" rows="4"><?php echo htmlspecialchars($row['faq_question']); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="upFaqAnswer">Enter FAQ Details</label>
                            </td>
                            <td>
                                <textarea class="form-control form-rounded" name="upFaqAnswer" id="upFaqAnswer" placeholder="Enter FAQ Details" rows="5"><?php echo htmlspecialchars($row['faq_ans']); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <center>
                                    <button type="button" class="btn btn-primary" name="updateFaqBtn" id="updateFaqBtn">Update Faq</button>
                                    <button type="button" class="btn btn-secondary" name="cancelBtn" id="cancelBtn">Cancel</button>
                                </center>
                            </td>
                        </tr>
                    </table>
                </form>
            </center>
        </div>  
    </div>
</div>

<script type="text/javascript">
function cleanHackingChars(text) {
    // Only allow letters, numbers, spaces, newlines, and specific punctuation (., - _ @)
    return text.replace(/[^a-zA-Z0-9\s.,_\-@]/g, '');
}

$(document).ready(function() {
    // Clean input on the fly
    $('#upFaqPage, #upFaqQuestion, #upFaqAnswer').on('input', function() {
        var cleaned = cleanHackingChars($(this).val());
        if (cleaned !== $(this).val()) {
            $(this).val(cleaned);
        }
    });
    
    $("#updateFaqBtn").click(function() {
        
        var faqId = $("#upFaqId").val();
        var faqPage = cleanHackingChars($("#upFaqPage").val().trim());
        var faqQuestion = cleanHackingChars($("#upFaqQuestion").val().trim());
        var faqAnswer = cleanHackingChars($("#upFaqAnswer").val().trim());
        
        if(faqPage == "") {
            showMessage("Please enter FAQ Page", "danger");
            $("#upFaqPage").focus();
            return false;
        }
        if(faqQuestion == "") {
            showMessage("Please enter FAQ Heading", "danger");
            $("#upFaqQuestion").focus();
            return false;
        }
        if(faqAnswer == "") {
            showMessage("Please enter FAQ Details", "danger");
            $("#upFaqAnswer").focus();
            return false;
        }
        
        $.ajax({
            url: "edit_faq.php",
            type: "POST",
            data: {
                action: "update",
                faqId: faqId,
                faqPage: faqPage,
                faqQuestion: faqQuestion,
                faqAnswer: faqAnswer
            },
            dataType: "json",
            beforeSend: function() {
                $("#updateFaqBtn").prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');
            },
            success: function(response) {
                if(response.status == "success") {
                    showMessage(response.message, "success");
                    setTimeout(function() {
                        loadFaqList();
                    }, 1500);
                } else {
                    showMessage(response.message, "danger");
                    $("#updateFaqBtn").prop('disabled', false).html('Update Faq');
                }
            },
            error: function(xhr, status, error) {
                showMessage("Error occurred while updating FAQ: " + error, "danger");
                $("#updateFaqBtn").prop('disabled', false).html('Update Faq');
                console.log(xhr.responseText);
            }
        });
    });
    
    $("#cancelBtn").click(function() {
        loadFaqList();
    });
});

function showMessage(message, type) {
    $("#message").removeClass("alert-success alert-danger").addClass("alert-" + type).html(message).fadeIn();
    setTimeout(function() {
        $("#message").fadeOut();
    }, 3000);
}

function loadFaqList() {
    $.ajax({
        url: "display_faq.php",
        success: function(response) {
            $("#main_content").html(response);
        },
        error: function(xhr, status, error) {
            alert("Error loading FAQ list: " + error);
        }
    });
}
</script>
<?php
    } else {
        echo '<div class="alert alert-danger">FAQ not found</div>';
    }
} else {
    echo '<div class="alert alert-warning">Invalid FAQ ID</div>';
}
?>