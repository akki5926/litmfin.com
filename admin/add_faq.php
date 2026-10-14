<?php
require_once('auth_guard.php');

if(!isset($_SESSION)) { session_start(); } 
if(!isset($_SESSION['user'])) { header("Location: index.php"); exit; }

require_once('database.php');
$db = new MySQLDB();

// Handle AJAX request for inserting FAQ
if(isset($_POST['action']) && $_POST['action'] == 'insert') {
    header('Content-Type: application/json');
    
    $faqPage = preg_replace('/[^a-zA-Z0-9\s.,_\-@]/', '', $_POST['faqPage'] ?? '');
    $faqQuestion = preg_replace('/[^a-zA-Z0-9\s.,_\-@]/', '', $_POST['faqQuestion'] ?? '');
    $faqAnswer = preg_replace('/[^a-zA-Z0-9\s.,_\-@]/', '', $_POST['faqAnswer'] ?? '');
    
    if(empty($faqPage) || empty($faqQuestion) || empty($faqAnswer)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }
    
    $result = $db->addFaq($faqPage, $faqQuestion, $faqAnswer);
    
    if($result) {
        echo json_encode(['status' => 'success', 'message' => 'FAQ added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add FAQ']);
    }
    exit;
}

// If it's not an AJAX request, display the form
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10 col-sm-12">
            <center>
                <h3 style="margin-top:100px">Adding New Faq</h3>
                <div id="message" style="display:none;" class="alert"></div>
                <form id="faqForm" method="POST">
                    <table style="margin-top:50px" id="tableAddFaq" class="table table-bordered">
                        <tr>
                            <td style="width:20%">
                                <label for="faqPage">Enter FAQ Page</label>
                            </td>
                            <td style="width:80%">
                                <input type="text" class="form-control form-rounded" name="faqPage" id="faqPage" placeholder="Enter FAQ Page" required />
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="faqQuestion">Enter FAQ Heading</label>
                            </td>
                            <td>
                                <textarea class="form-control form-rounded" name="faqQuestion" id="faqQuestion" rows="3" placeholder="Enter FAQ Heading"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="faqAnswer">Enter FAQ Details</label>
                            </td>
                            <td>
                                <textarea class="form-control form-rounded" name="faqAnswer" id="faqAnswer" placeholder="Enter FAQ Details" rows="5"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <center>
                                    <button type="button" class="btn btn-primary" name="insertFaqBtn" id="insertFaqBtn">Add Faq</button>
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
    $('#faqPage, #faqQuestion, #faqAnswer').on('input', function() {
        var cleaned = cleanHackingChars($(this).val());
        if (cleaned !== $(this).val()) {
            $(this).val(cleaned);
        }
    });
    
    $("#insertFaqBtn").click(function() {
        
        var faqPage = cleanHackingChars($("#faqPage").val().trim());
        var faqQuestion = cleanHackingChars($("#faqQuestion").val().trim());
        var faqAnswer = cleanHackingChars($("#faqAnswer").val().trim());
        
        if(faqPage == "") {
            showMessage("Please enter FAQ Page", "danger");
            $("#faqPage").focus();
            return false;
        }
        if(faqQuestion == "") {
            showMessage("Please enter FAQ Heading", "danger");
            $("#faqQuestion").focus();
            return false;
        }
        if(faqAnswer == "") {
            showMessage("Please enter FAQ Details", "danger");
            $("#faqAnswer").focus();
            return false;
        }
        
        $.ajax({
            url: "add_faq.php",
            type: "POST",
            data: {
                action: "insert",
                faqPage: faqPage,
                faqQuestion: faqQuestion,
                faqAnswer: faqAnswer
            },
            dataType: "json",
            beforeSend: function() {
                $("#insertFaqBtn").prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
            },
            success: function(response) {
                if(response.status == "success") {
                    showMessage(response.message, "success");
                    setTimeout(function() {
                        loadFaqList();
                    }, 1500);
                } else {
                    showMessage(response.message, "danger");
                    $("#insertFaqBtn").prop('disabled', false).html('Add Faq');
                }
            },
            error: function(xhr, status, error) {
                showMessage("Error occurred while adding FAQ: " + error, "danger");
                $("#insertFaqBtn").prop('disabled', false).html('Add Faq');
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