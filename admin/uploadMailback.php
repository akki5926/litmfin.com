<style>
.success {
    color: green;
    font-weight: bold;
}

.error {
    color: red;
    font-weight: bold;
}
td {
    min-width: 200px; /* Set the desired minimum width */
}
</style>
<?php
require_once('auth_guard.php');

$output = '<div class="container-fluid row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                <form enctype="multipart/form-data">
                    <table style="margin-top:10px" id="tableAddFaq">
                    <tr>
                        <td>
                        <label for="rta">RTA :</label>
                        </td>
                        <td>
                            <select id="rta" name="rta">
                              <option value="" selected disabled>Select</option>
                              <option value="cams">CAMS KRA</option>
                              <option value="karvy">Karvy KRA</option>
                              <option value="nse">NSE Report</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="document-type-row" style="display: none;">
                        <td>
                        <label for="document-type">Document Type:</label>
                        </td>
                        <td>
                        <select id="document-type" name="document-type"></select>
                        </td>
                    </tr>
                    <tr id="mailback-doc-row" style="display: none;">
                        <td>
                            <label for="mailback-doc">Upload Document (Excel):</label>
                        </td>
                        <td>
                        <input type="file" class="form-control" id="mailback-doc" name="mailback-doc" accept=".xls, .xlsx" >
                        </td>
                    </tr>
                    
                    <tr id="upload-button-row" style="display: none;">
                        <td colspan="2">
                            <center>
                                <button type="button" name="uploadFileBtn" id="uploadFileBtn" onclick="uploadFileToDb()" >Upload </button>
                            </center>
                         </td>
                    </tr>
                    </table>
                </form>
                
                <div id="loader" style="display:none;">
                    <img src="../img/loader.svg" width="50px" height="50px">
                </div>
                <div id="ResultDisplay"></div>
                </div>    
            </div>';
echo $output;
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
  $('#rta').change(function() {
    var selectedRta = $(this).val();
    var documentTypeRow = $('#document-type-row');
    var mailbackDocRow = $('#mailback-doc-row');
    var uploadButtonRow = $('#upload-button-row');
    var resultDisplay = $('#ResultDisplay');
    resultDisplay.empty();
    var selectedFile = $('#mailback-doc');
    selectedFile.val(''); // Clear the value by setting it to an empty string

    // Hide or show document type row based on RTA selection
    if (selectedRta === '') {
      documentTypeRow.hide();
      mailbackDocRow.hide();
      uploadButtonRow.hide();
    } else {
      documentTypeRow.show();
      mailbackDocRow.hide();
      uploadButtonRow.hide();
    }

    // Clear and hide document type options
    var documentTypeOptions = $('#document-type');
    documentTypeOptions.empty();

    // Update document type options based on RTA selection
    if (selectedRta === 'cams') {
      documentTypeOptions.append('<option value="" selected disabled>Select</option>');
      documentTypeOptions.append('<option value="wbr9">WBR9</option>');
      documentTypeOptions.append('<option value="wbr10">WBR10</option>');
      documentTypeOptions.append('<option value="wbr49">WBR49</option>');
    } else if(selectedRta === 'karvy') {
      documentTypeOptions.append('<option value="" selected disabled>Select</option>');
      documentTypeOptions.append('<option value="mfsd201">MFSD201</option>');
    } else if(selectedRta === 'nse') {
      documentTypeOptions.append('<option value="" selected disabled>Select</option>');
      documentTypeOptions.append('<option value="nse-report">Transaction_Detailed_Report</option>');
    }
  });

  $('#document-type').change(function() {
    var selectedDocumentType = $(this).val();
    var mailbackDocRow = $('#mailback-doc-row');
    var uploadButtonRow = $('#upload-button-row');
    var resultDisplay = $('#ResultDisplay');
    var selectedFile = $('#mailback-doc');
    selectedFile.val(''); // Clear the value by setting it to an empty string

    resultDisplay.empty();
    // Hide or show mailback document row and upload button based on document type selection
    if (selectedDocumentType === '') {
      mailbackDocRow.hide();
      uploadButtonRow.hide();
    } else {
      mailbackDocRow.show();
      uploadButtonRow.show();
    }
  });
});

function uploadFileToDb() {
  var selectedFile = $('#mailback-doc').prop('files')[0];
  var fileType = $('#document-type').val();

  var formData = new FormData();
  formData.append('file', selectedFile);
  formData.append('fileType', fileType);
  var resultDisplay = $('#ResultDisplay');
  resultDisplay.empty();
  $('#loader').show();

  $.ajax({
    url: 'mailbackfiletodb.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function (response) {
      console.log("response 1:" + response);
      $('#loader').hide();
      resultDisplay.empty();

      var responseObject = JSON.parse(response);
      var message = responseObject.message;

      var messageElement = $('<p>').html(message);

      if (response.includes('Success')) {
        messageElement.addClass('success');
      } else {
        messageElement.addClass('error');
      }

      resultDisplay.append(messageElement);
      if (response.includes('Success')) { setTimeout(() => { loadContent('uploadMailback.php', 'Mailback Files Upload'); }, 1500); }
    },
    error: function (xhr, status, error) {
      console.log("response 1: error");
      $('#loader').hide();
      var resultDisplay = $('#ResultDisplay');
      resultDisplay.empty();
      var messageElement = $('<p>').text('An error occurred during the file upload.');
      messageElement.addClass('error');
      resultDisplay.append(messageElement);
      if (response.includes('Success')) { setTimeout(() => { loadContent('uploadMailback.php', 'Mailback Files Upload'); }, 1500); }
    }
  });
}
</script>
