<?php
require_once('auth_guard.php');

require_once('database.php');
$db = new MYSQLDB();

$risk_id_get = isset($_GET['risk']) ? $_GET['risk'] : '';
?>
<div class="container-fluid row">
    <div class="col-lg-12">
        <center><h3>Add New Recommended Fund</h3></center>
    </div>
    <div class="col-lg-12">
        <form id="add_fund_form" onsubmit="submitNewFund(event)">
            <table class="table" style="width: 100%">
                <input type="hidden" id="risk_category" name="risk_category" value="<?php echo htmlspecialchars($risk_id_get); ?>">
                <tr>
                    <td>Fund House (AMC Code)</td>
                    <td>
                        <select id="fund_house" name="fund_house" class="form-control" onchange="loadSchemeTypes()" required>
                            <option value="">Select Fund House</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Fund Type (Scheme Type)</td>
                    <td>
                        <select id="invest_type" name="invest_type" class="form-control" onchange="loadFundCategories()" required>
                            <option value="">Select Fund Type</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Fund Category</td>
                    <td>
                        <select id="fund_cat" name="fund_cat" class="form-control" onchange="loadSchemes()" required>
                            <option value="">Select Fund Category</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Scheme</td>
                    <td>
                        <select id="scheme" name="scheme" class="form-control" required>
                            <option value="">Select Scheme</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Allocation (%)</td>
                    <td>
                        <input type="number" name="allocation" id="allocation" class="form-control" placeholder="Enter allocation percentage" required step="0.01" min="0" max="100">
                    </td>
                </tr>
                <tr>
                    <td>Rationale (Optional)</td>
                    <td>
                        <textarea id="rational" name="rational" class="form-control" rows="4" placeholder="Enter rationale (Optional, max 100 chars)" maxlength="100"></textarea>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <button type="submit" class="btn btn-primary">Save Fund Recommendation</button>
                        <button type="button" class="btn btn-secondary" onclick="backToList()">Cancel / Back to List</button>
                    </td>
                </tr>
            </table>
        </form>
        <div id="status_message"></div>
    </div>
</div>

<script>
    // Debugging
    console.log("recommendation_add_new.php loaded");

    // Initial Load - Call directly for AJAX loaded content
    if(typeof loadAmcCodes === 'function') {
        loadAmcCodes();
    } else {
        console.error("loadAmcCodes is not defined yet!");
        // Fallback: define it then call it, but here we are defining it below.
        // Function declarations are hoisted, so it should be fine.
        // Let's rely on hoisting.
        setTimeout(loadAmcCodes, 100); 
    }

    function backToList() {
        $.ajax({
            url: "recommendation-list.php",
            success: function(response) {
                $("#main_content").html(response);
            }
        });
    }

    function loadAmcCodes() {
        console.log("Fetching AMC codes...");
        fetch('api_get_scheme_master_data.php?action=get_amc')
            .then(response => {
                if (!response.ok) {
                    throw new Error("HTTP Status " + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log("AMC Data received:", data);
                if(data.success) {
                    let options = '<option value="">Select Fund House</option>';
                    if(data.data.length === 0) {
                         options += '<option value="">No AMC Found</option>';
                    }
                    data.data.forEach(item => {
                        options += `<option value="${item.amc_code}">${item.amc_code}</option>`;
                    });
                    const el = document.getElementById('fund_house');
                    if(el) {
                        el.innerHTML = options;
                    } else {
                        console.error("Element #fund_house not found!");
                    }
                } else {
                    console.error("API Error:", data.message);
                    alert("Error loading AMC codes: " + (data.message || "Unknown error"));
                }
            })
            .catch(error => {
                console.error("Fetch Error:", error);
                alert("Failed to load AMC codes. See console for details.");
            });
    }

    function loadSchemeTypes() {
        const amcCode = document.getElementById('fund_house').value;
        if(!amcCode) return;
        
        // Reset dependent dropdowns
        document.getElementById('invest_type').innerHTML = '<option value="">Select Fund Type</option>';
        document.getElementById('fund_cat').innerHTML = '<option value="">Select Fund Category</option>';
        document.getElementById('scheme').innerHTML = '<option value="">Select Scheme</option>';

        fetch(`api_get_scheme_master_data.php?action=get_scheme_type&amc_code=${encodeURIComponent(amcCode)}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    let options = '<option value="">Select Fund Type</option>';
                    data.data.forEach(item => {
                        options += `<option value="${item.scheme_type}">${item.scheme_type}</option>`;
                    });
                    document.getElementById('invest_type').innerHTML = options;
                }
            });
    }

    function loadFundCategories() {
        const amcCode = document.getElementById('fund_house').value;
        const schemeType = document.getElementById('invest_type').value;
        if(!amcCode || !schemeType) return;

        // Reset dependent dropdowns
        document.getElementById('fund_cat').innerHTML = '<option value="">Select Fund Category</option>';
        document.getElementById('scheme').innerHTML = '<option value="">Select Scheme</option>';

        fetch(`api_get_scheme_master_data.php?action=get_fund_cat&amc_code=${encodeURIComponent(amcCode)}&scheme_type=${encodeURIComponent(schemeType)}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    let options = '<option value="">Select Fund Category</option>';
                    data.data.forEach(item => {
                        options += `<option value="${item.fund_cat}">${item.fund_cat}</option>`;
                    });
                    document.getElementById('fund_cat').innerHTML = options;
                }
            });
    }

    function loadSchemes() {
        const amcCode = document.getElementById('fund_house').value;
        const schemeType = document.getElementById('invest_type').value;
        const fundCat = document.getElementById('fund_cat').value;
        if(!amcCode || !schemeType || !fundCat) return;

        document.getElementById('scheme').innerHTML = '<option value="">Select Scheme</option>';

        fetch(`api_get_scheme_master_data.php?action=get_schemes&amc_code=${encodeURIComponent(amcCode)}&scheme_type=${encodeURIComponent(schemeType)}&fund_cat=${encodeURIComponent(fundCat)}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    let options = '<option value="">Select Scheme</option>';
                    data.data.forEach(item => {
                        options += `<option value="${item.scheme_name}">${item.scheme_name}</option>`;
                    });
                    document.getElementById('scheme').innerHTML = options;
                }
            });
    }

    function submitNewFund(e) {
        e.preventDefault();
        
        // Validation
        const risk_category = document.getElementById('risk_category').value;
        const invest_type = document.getElementById('invest_type').value;
        const fund_house = document.getElementById('fund_house').value;
        const fund_cat = document.getElementById('fund_cat').value;
        const scheme = document.getElementById('scheme').value;
        const allocation = document.getElementById('allocation').value;
        const rational = document.getElementById('rational').value;

        if(!risk_category || !invest_type || !fund_house || !fund_cat || !scheme || !allocation) {
            alert("All fields except Rationale are required");
            return;
        }

        // Prepare data for senddata.php
        // senddata.php expects: Risk_id, invest_type, fund_house, fund_cat, scheme, allocation, rational
        const formData = new FormData();
        formData.append('Risk_id', risk_category);
        formData.append('invest_type', invest_type);
        formData.append('fund_house', fund_house);
        formData.append('fund_cat', fund_cat);
        formData.append('scheme', scheme);
        formData.append('allocation', allocation);
        formData.append('rational', rational);
        
        // Also add 'save' parameter if senddata checks for it, though senddata.php seems to check values directly
        formData.append('save', 'Submit');

        fetch('senddata.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('status_message').innerHTML = '<div class="alert alert-success">' + data + '</div>';
            if(data.includes('Successfully')) {
                // Clear form
                 document.getElementById('add_fund_form').reset();
                 // Optionally reload list
                 // window.location.reload(); 
            }
        })
        .catch(error => console.error('Error:', error));
    }
</script>
