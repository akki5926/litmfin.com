<?php
require_once('auth_guard.php');

require_once('database.php');
$db =new MYSQLDB();

$output ='';
?>
<style>
    #autocomplete-list {
        position: absolute;
        border: 1px solid #ccc;
        background-color: #f1f1f1;
        max-height: 200px;
        overflow-y: auto;
    }

    #autocomplete-list div {
        cursor: pointer;
    }

    #autocomplete-list div:hover {
        background-color: #e9e9e9;
    }
</style>
<form method="post" >
    <table style="border: 3px; font-size: 12px;">
        <tr>
            <td><label>From Date</label></td>
            <td> 
                <input class="input-md" style="width:200px" type="date" name="fromDate" onclick="disablefuturedate()" id="fromDate" />
            </td>
            <td><label>To Date</label></td>
            <td>
                <input class="input-md" onclick="disablefuturedate()" style="width:200px" type="date" name="toDate" id="toDate" />
            </td>
        </tr>
        <tr>
           <td><label>Customer IIN </label></td>
           <td>
                <input class="input-md" style="width:200px" type="text" name="custIIN" id="custIIN" />
                <div id="autocomplete-list"></div>
           </td>
           <td><label>Report Type</label></td>
           <td>
                <select id="reportType" name="reportType" class="input-md" style="width:200px">
                    <option value="SIP">SIP Registration</option>
                    <option value="SWP">SWP Registration</option>
                    <option value="BOTH" selected>Both (SIP & SWP)</option>
                </select>
           </td>
        </tr>
        <tr>
           <td colspan="4" style="text-align: right;">
                <button type="button" class="btn btn-success" id="send" name="send" style="background-color: #FD7101; color: white; padding: 5px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;margin-left: 10px;" onclick="adminCallSIPReports()">Show Transactions</button>
            </td>
        </tr>
    </table>
</form>

<div class='table' id="result"></div>

<?php echo $output; ?>

<script type="text/javascript">
function adminCallSIPReports(){
    var method = "sipreportsNse";
    var fromDate = document.getElementById('fromDate').value;
    var toDate = document.getElementById('toDate').value;
    var custIIN = document.getElementById("custIIN").value;
    var reportType = document.getElementById("reportType").value;

    // Show loading indicator
    document.getElementById("result").innerHTML = '<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>';
    
    $.ajax({
        url: "getsipdataajax.php",  // Make sure this path is correct
        type: "POST",
        data: {
            fromDate: fromDate,
        toDate: toDate,
        clientCode: custIIN,
        reportType: reportType
        },
        dataType: "json",
        success: function(response){
            console.log("AJAX Response:", response);
            if(response && response.html){
                document.getElementById("result").innerHTML = response.html;
            } else if(response && response.error){
                document.getElementById("result").innerHTML = '<div class="alert alert-danger">' + response.error + '</div>';
            } else {
                document.getElementById("result").innerHTML = '<div class="alert alert-danger">Invalid response from server</div>';
            }
        },
        error: function(xhr, status, error){
            console.error("AJAX Error:", status, error);
            console.error("Response Text:", xhr.responseText);
            document.getElementById("result").innerHTML = '<div class="alert alert-danger">AJAX Error: ' + error + '<br><pre>' + escapeHtml(xhr.responseText) + '</pre></div>';
        }
    });
}

function escapeHtml(text) {
    if(!text) return '';
    return text.replace(/[&<>]/g, function(m) {
        if(m === '&') return '&amp;';
        if(m === '<') return '&lt;';
        if(m === '>') return '&gt;';
        return m;
    });
}

function disablefuturedate(){
    var today = new Date().toISOString().split('T')[0];
    document.getElementsByName("fromDate")[0].setAttribute('max', today);
    document.getElementsByName("toDate")[0].setAttribute('max', today);
}

function fetchCustomerIINs(input) {
    var val = input.value.toLowerCase();
    var autocompleteList = document.getElementById("autocomplete-list");
    autocompleteList.innerHTML = "";

    if (val === "") {
        autocompleteList.style.display = "none";
        return;
    }

    fetch("getUCClistwithname.php")
        .then(response => response.json())
        .then(data => {
            var matchingIINs = data.filter(function (entry) {
                return entry.UCC.indexOf(val) !== -1 || entry.name.toLowerCase().indexOf(val) !== -1;
            });

            if (val.length < 10) {
                matchingIINs.forEach(function (entry) {
                    var option = document.createElement("div");
                    option.innerHTML = `${entry.UCC} - ${entry.name}`;
                    option.addEventListener("click", function () {
                        input.value = entry.UCC;
                        autocompleteList.innerHTML = "";
                    });
                    autocompleteList.appendChild(option);
                });
                autocompleteList.style.display = "block";
            }
        })
        .catch(error => console.error(error));
}

var input = document.getElementById("custIIN");
input.addEventListener("input", function () {
    fetchCustomerIINs(this);
});
</script>