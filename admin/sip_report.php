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
            padding: 10px;
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
                    <input class="form-control input-md" style="width:200px" type="date"  name="fromDate" onclick="disablefuturedate()" id="fromDate" />
            </td>

            <td><label>To Date</label></td>
            <td>
                    <input class="form-control input-md" onclick="disablefuturedate()" style="width:200px" type="date" name="toDate" id="toDate" />
            </td>
        </tr>
        <tr>
           <td>
               <label>Customer IIN </label>
           </td>
           <td>
                <input class="form-control input-md" style="width:200px" type="text" name="custIIN" id="custIIN" />
				<!--<input type="text" id="custIIN" placeholder="Enter a customer IIN">-->
				<div id="autocomplete-list"></div>

           </td>
           <td colspan="2">
                <button type="button" class="btn btn-success" id="send" name = "send" style="padding-right:20px;padding-left:20px" onclick="adminCallSIPReports()" >Show Transactions</button>
            </td>
        </tr>
        
    </table>
</form>


<div class='table' id="result">

	
</div>

<?php
	echo $output;
?>

<script>
    function fetchCustomerIINs(input) {
        var val = input.value.toLowerCase();
        var autocompleteList = document.getElementById("autocomplete-list");
        autocompleteList.innerHTML = "";

        // Check if the input is empty and return if it is
        if (val === "") {
            autocompleteList.style.display = "none";
            return;
        }

        fetch("getUCClistwithname.php") // Replace with your server-side script URL
            .then(response => response.json())
            .then(data => {
                var matchingIINs = data.filter(function (entry) {
                    return entry.iin.indexOf(val) !== -1 || entry.name.toLowerCase().indexOf(val) !== -1;
                });

                if (val.length < 10) {
                    matchingIINs.forEach(function (entry) {
                        var option = document.createElement("div");
                        option.innerHTML = `${entry.iin} - ${entry.name}`;
                        option.addEventListener("click", function () {
                            input.value = entry.iin; // Set only the IIN when clicked
                            autocompleteList.innerHTML = "";
                        });
                        autocompleteList.appendChild(option);
                    });

                    // Show the autocomplete list if less than 10 characters
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



<script type="text/javascript">
	
	function adminCallSIPReports(){

		var method = "sipreports";

		var fromDate = document.getElementById('fromDate').value;
		var toDate = document.getElementById('toDate').value;
		var custIIN = document.getElementById("custIIN").value;

		//alert("from date "+ fromDate + " | todate " + toDate + " | selectTransationType "+ selectTransationType + " | selectTransactionStatus " + selectTransactionStatus + " | iin" + custIIN );


		$.ajax({
			url:"data_manuplation.php",
			data: {method:method,fromDate:fromDate,toDate:toDate,custIIN:custIIN},
			success:function(responseText){
				//alert(responseText);

				if(responseText == ""){

				}else{
					document.getElementById("result").innerHTML = responseText;

				}
				
				
			}
		});

	}

    function disablefuturedate(){
       // alert("hello");
        var today = new Date().toISOString().split('T')[0];
        //var nextWeekDate = new Date(new Date().getTime() + 6 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
      //document.getElementsByName("date")[0].setAttribute('min', today);
      document.getElementsByName("fromDate")[0].setAttribute('max', today);
      document.getElementsByName("toDate")[0].setAttribute('max', today);
    }


</script>

