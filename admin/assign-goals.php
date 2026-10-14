<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Goals</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .autocomplete-container {
            position: relative;
            display: inline-block;
            width: 250px;
        }
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            font-size: 12px;
            box-sizing: border-box;
            text-align: left;
        }
        .autocomplete-list div {
            padding: 6px 10px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: left;
        }
        .autocomplete-list div:hover {
            background-color: #e9e9e9;
        }
        .goal-details {
            display: block;
            margin-top: 5px;
        }
        .goal-details small {
            font-size: 0.9em;
            color: #6c757d;
        }
        .table {
            font-size: 0.875em;
        }
        .form-container {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        #custUCC {
            width: 100%;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .add-goal-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .add-goal-btn:hover {
            background-color: #218838;
        }
        .goal-dropdown {
            margin-left: 5px;
            padding: 4px;
            font-size: 12px;
        }
    </style>
</head>
<body class="container mt-5">
    <h2>Assign Goals</h2>
    <div style="max-width: 900px; margin: auto; border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <form id="customerForm" method="POST" onsubmit="showDiv(event); return false;">
            <div class="form-container">
                <label for="custUCC">Customer UCC:</label>
                <span class="autocomplete-container">
                    <input type="text" id="custUCC" name="custUCC" required autocomplete="off">
                    <div id="autocomplete-list-custUCC" class="autocomplete-list"></div>
                </span>
                <button type="submit" class="btn btn-primary" id="selectCustomerBtn" disabled>Select Customer</button>
            </div>
        </form>
        <div id="status1" style="margin-top: 10px;"></div>
    </div>
    <div id="goal_div" style="margin-top: 20px; display: none;">
        <div class="table-responsive">
            <table class="table table-bordered">
                <tbody id="folioTableBody">
                    <tr><td colspan="10" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function showDiv(event) {
            event.preventDefault();
            var ucc = document.getElementById("custUCC").value;
            if (!ucc) {
                document.getElementById('status1').innerHTML = '<span style="color: red;">Please enter a UCC</span>';
                return;
            }
            getUCCName(ucc, showDiv2);
        }

        function showDiv2(response) {
            var ucc = document.getElementById("custUCC").value;
            var tableBody = document.getElementById("folioTableBody");
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center">Loading Portfolio... <img src="images/loader.svg" alt="Loading..." width="30px;"></td></tr>';
            var goalDiv = document.getElementById("goal_div");
            var responseContainer = document.getElementById('status1');

            if (response == false) {
                responseContainer.style.color = 'red';
                responseContainer.innerHTML = "Error: UCC " + ucc + " does not exist!";
                goalDiv.style.display = "none";
            } else {
                responseContainer.style.color = 'blue';
                responseContainer.innerHTML = response;
                goalDiv.style.display = "block";
                fetchInvestData(ucc);
            }
        }

        function fetchInvestData(ucc) {
            $.ajax({
                url: "uccfoliodata.php",
                type: "POST",
                dataType: "json",
                data: { ucc: ucc },
                success: function (response) {
                    populateInvestedTable(response);
                    fetchAssignedGoals(ucc);
                },
                error: function (xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    var tableBody = document.getElementById("folioTableBody");
                    tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">Failed to fetch folio data</td></tr>';
                }
            });
        }

        function fetchAssignedGoals(ucc) {
            $.ajax({
                url: "processgoalmngt.php",
                type: "POST",
                dataType: "json",
                data: { fetchAssignedGoals: 1, ucc: ucc },
                success: function (response) {
                    if (response.success && response.data) {
                        populateAssignedGoals(response.data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching assigned goals:", status, error);
                }
            });
        }

        function formatIndianNumber(amount) {
            if (!amount) return '0';
            return new Intl.NumberFormat('en-IN').format(amount);
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'N/A';
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}-${month}-${year}`;
        }
		
		function escapeHtml(str) {
			if (!str) return '';
			return str.replace(/[&<>]/g, function(m) {
				if (m === '&') return '&amp;';
				if (m === '<') return '&lt;';
				if (m === '>') return '&gt;';
				return m;
			});
		}

        function populateAssignedGoals(data) {
            if (!data || !Array.isArray(data)) return;
            
            data.forEach(function (goal) {
                var elementId = `assignedGoal_${goal.amc_code}_${goal.fund_code}`;
                var goalElement = document.getElementById(elementId);
                if (goalElement) {
                    goalElement.innerHTML = `
                        <div class="goal-details">
                            <strong>${escapeHtml(goal.goal_name)}</strong><br>
                            <small>Rs ${formatIndianNumber(goal.amount_required)} by ${formatDate(goal.goal_date)}</small>
                            ${goal.remarks ? `<br><small>${escapeHtml(goal.remarks)}</small>` : ''}
                        </div>
                    `;

                    var buttonId = `addGoalBtn_${goal.amc_code}_${goal.fund_code}`;
                    var buttonElement = document.getElementById(buttonId);
                    if (buttonElement) {
                        buttonElement.innerText = "Change Goal";
                        buttonElement.style.backgroundColor = "#ffc107";
                        buttonElement.style.color = "#000";
                    }
                }
            });
        }
		
		function populateInvestedTable(data) {
			var tableBody = document.getElementById("folioTableBody");
			tableBody.innerHTML = "";

			if ((!data.portfolioInfo || Object.keys(data.portfolioInfo).length === 0) &&
				(!data.HoldingInfoBonds || data.HoldingInfoBonds.length === 0) &&
				(!data.HoldingInfoPms || data.HoldingInfoPms.length === 0) &&
				(!data.HoldingInfoAif || data.HoldingInfoAif.length === 0)) {
				tableBody.innerHTML = '<tr><td colspan="10" class="text-center">No data available</td></tr>';
				return;
			}

			// Equity Fund Holdings
			if (data.portfolioInfo && Object.keys(data.portfolioInfo).length > 0) {
				tableBody.innerHTML += `
					<tr><td colspan="10" style="font-weight:bold; background:#f0f0f0;">Equity Fund Holdings</td></tr>
					<tr style="font-weight:bold; background:#fafafa;">
						<td>Fund Name</td>
						<td>Asset Class</td>
						<td>Units</td>
						<td>Cost Value</td>
						<td>Current NAV</td>
						<td>NAV Date</td>
						<td>Current Value</td>
						<td>XIRR</td>
						<td>Assigned Goal</td>
						<td>Action</td>
					</tr>`;

				Object.keys(data.portfolioInfo).forEach(function (amc_code) {
					var folioData = data.portfolioInfo[amc_code];
					if (!Array.isArray(folioData)) return;

					folioData.forEach(function (fundObject) {
						var fundCode = Object.keys(fundObject)[0];
						var item = fundObject[fundCode];
						if (!item || typeof item !== "object") return;

						addEquityRow(tableBody, item, amc_code, fundCode);
					});
				});
			}

			// Bonds Holdings
			if (data.HoldingInfoBonds && Object.keys(data.HoldingInfoBonds).length > 0) {
				tableBody.innerHTML += `
					<tr><td colspan="10" style="font-weight:bold; background:#f0f0f0;">Bonds Holdings</td></tr>
					<tr style="font-weight:bold; background:#fafafa;">
						<td>Bond Name</td>
						<td>Asset Class</td>
						<td>Unit</td>
						<td>Cost Value</td>
						<td>Current NAV</td>
						<td>NAV Date</td>
						<td>Current Value</td>
						<td>XIRR (%)</td>
						<td>Assigned Goal</td>
						<td>Action</td>
					</tr>`;
					
				Object.keys(data.HoldingInfoBonds).forEach(function (amc_code) {
					var folioData = data.HoldingInfoBonds[amc_code];
					if (!Array.isArray(folioData)) return;

					folioData.forEach(function (fundObject) {
						var fundCode = Object.keys(fundObject)[0];
						var item = fundObject[fundCode];
						if (!item || typeof item !== "object") return;

						addBondRow(tableBody, item, amc_code, fundCode);
					});
				});
			}

			// PMS Holdings
			if (data.HoldingInfoPms && Object.keys(data.HoldingInfoPms).length > 0) {
				tableBody.innerHTML += `
					<tr><td colspan="10" style="font-weight:bold; background:#f0f0f0;">PMS Holdings</td>
					<tr style="font-weight:bold; background:#fafafa;">
						<td>PMS Name</td>
						<td>Asset Class</td>
						<td></td>
						<td>Cost Value</td>
						<td>Total Sell Value</td>
						<td>NAV Date</td>
						<td>Current Value</td>
						<td>XIRR</td>
						<td>Assigned Goal</td>
						<td>Action</td>
					</tr>`;

				Object.keys(data.HoldingInfoPms).forEach(function (amc_code) {
					var folioData = data.HoldingInfoPms[amc_code];
					if (!Array.isArray(folioData)) return;

					folioData.forEach(function (fundObject) {
						var fundCode = Object.keys(fundObject)[0];
						var item = fundObject[fundCode];
						if (!item || typeof item !== "object") return;

						addPmsRow(tableBody, item, amc_code, fundCode);
					});
				});
			}

			// AIF Holdings
			if (data.HoldingInfoAif && Object.keys(data.HoldingInfoAif).length > 0) {
				tableBody.innerHTML += `
					<tr><td colspan="10" style="font-weight:bold; background:#f0f0f0;">AIF Holdings</td>
					<tr style="font-weight:bold; background:#fafafa;">
						<td>AIF Name</td>
						<td>Asset Class</td>
						<td>Units</td>
						<td>Cost Value</td>
						<td>Current NAV</td>
						<td>NAV Date</td>
						<td>Current Value</td>
						<td>XIRR</td>
						<td>Assigned Goal</td>
						<td>Action</td>
					</tr>`;

				Object.keys(data.HoldingInfoAif).forEach(function (amc_code) {
					var folioData = data.HoldingInfoAif[amc_code];
					if (!Array.isArray(folioData)) return;

					folioData.forEach(function (fundObject) {
						var fundCode = Object.keys(fundObject)[0];
						var item = fundObject[fundCode];
						if (!item || typeof item !== "object") return;

						addAifRow(tableBody, item, amc_code, fundCode);
					});
				});
			}
		}

		function buildGoalCells(investment_type, amc_code, fundCode, asset_class, fund_name, assignedGoal = "") {
			var dropdownId = `goalDropdown_${amc_code}_${fundCode}`;
			var buttonText = assignedGoal ? "Change Goal" : "Add Goal";
			var buttonStyle = assignedGoal ? 'style="background-color:#ffc107; color:#000;"' : 'style="background-color:#28a745; color:#fff;"';

			return `
				<td id="assignedGoal_${amc_code}_${fundCode}">${assignedGoal || ''}</td>
				<td>
					<button class="add-goal-btn" id="addGoalBtn_${amc_code}_${fundCode}" ${buttonStyle}
						data-amc_code="${amc_code}" data-fund="${fundCode}"
						onclick="showGoalDropdown('${amc_code}', '${fundCode}', '${dropdownId}')">
						${buttonText}
					</button>
					<select id="${dropdownId}" class="goal-dropdown" style="display:none;"
						onchange="assignGoal('${investment_type}','${amc_code}', '${fundCode}','${asset_class}', '${escapeHtml(fund_name)}', this.value)">
						<option value="">Select Goal</option>
					</select>
				</td>`;
		}

		function addEquityRow(tableBody, item, amc_code, fundCode) {
			var asset_class = item.Asset_Class || "N/A";
			var fund_name = item.Fund_Name || "N/A";
			var row = `<tr>
				<td>${escapeHtml(item.Fund_Name || "N/A")}</td>
				<td>${escapeHtml(item.Asset_Class || "N/A")}</td>
				<td>${item.Unit_Holdings || "N/A"}</td>
				<td>${formatIndianNumber(item.Cost_Value) || "N/A"}</td>
				<td>${item.Current_NAV || "N/A"}</td>
				<td>${item.NAV_Date || "N/A"}</td>
				<td>${formatIndianNumber(item.Curr_Value) || "N/A"}</td>
				<td>${item.XIRR || "N/A"}</td>
				${buildGoalCells('MF', amc_code, fundCode, asset_class, fund_name)}
			</tr>`;
			tableBody.innerHTML += row;
		}

		function addBondRow(tableBody, item, amc_code, fundCode) {
			var asset_class = item.assetClass || "N/A";
			var fund_name = item.bondName || "N/A";
			var row = `<tr>
				<td>${escapeHtml(item.bondName || "N/A")}</td>
				<td>${escapeHtml(item.assetClass || "N/A")}</td>
				<td>${item.unitHoldings || "N/A"}</td>
				<td>${formatIndianNumber(item.costValue) || "N/A"}</td>
				<td>${item.currentNAV || "N/A"}</td>
				<td>${item.navDate || "N/A"}</td>
				<td>${formatIndianNumber(item.currValue) || "N/A"}</td>
				<td>${item.XIRR || "N/A"}</td>
				${buildGoalCells('Bond', amc_code, fundCode, asset_class, fund_name)}
			</tr>`;
			tableBody.innerHTML += row;
		}

		function addPmsRow(tableBody, item, amc_code, fundCode) {
			var asset_class = item.assetClass || "N/A";
			var fund_name = item.pmsName || "N/A";
			var row = `<tr>
				<td>${escapeHtml(item.pmsName || "N/A")}</td>
				<td>${escapeHtml(item.assetClass || "N/A")}</td>
				<td></td>
				<td>${formatIndianNumber(item.totalPurchaseCost) || "N/A"}</td>
				<td>${formatIndianNumber(item.totalSellValue) || "N/A"}</td>
				<td>${item.navDate || "N/A"}</td>
				<td>${formatIndianNumber(item.currValue) || "N/A"}</td>
				<td>${item.XIRR || "N/A"}</td>
				${buildGoalCells('PMS', amc_code, fundCode, asset_class, fund_name)}
			</tr>`;
			tableBody.innerHTML += row;
		}

		function addAifRow(tableBody, item, amc_code, fundCode) {
			var asset_class = item.assetClass || "N/A";
			var fund_name = item.aifName || "N/A";
			var row = `<tr>
				<td>${escapeHtml(item.aifName || "N/A")}</td>
				<td>${escapeHtml(item.assetClass || "N/A")}</td>
				<td>${item.units || "N/A"}</td>
				<td>${formatIndianNumber(item.costValue) || "N/A"}</td>
				<td>${item.currNAV || "N/A"}</td>
				<td>${item.navDate || "N/A"}</td>
				<td>${formatIndianNumber(item.currValue) || "N/A"}</td>
				<td>${item.XIRR || "N/A"}</td>
				${buildGoalCells('AIF', amc_code, fundCode, asset_class, fund_name)}
			</tr>`;
			tableBody.innerHTML += row;
		}
		
		function assignGoal(investment_type, amc_code, fundCode, asset_class, fund_name, goalId) {
			let custUCC = $('#custUCC').val();

			if (!goalId || !custUCC) {
				console.warn("Missing goalId or custUCC");
				alert("Goal or UCC is missing.");
				return;
			}

			let requestData = {
				assignGoal: 1,
				investment_type: investment_type,
				amc_code: amc_code,
				fundCode: fundCode,
				asset_class: asset_class,
				fund_name: fund_name,
				goal_id: goalId,
				ucc: custUCC
			};
			
			$.ajax({
				url: "processgoalmngt.php",
				type: "POST",
				dataType: "json",
				data: requestData,
				success: function (response) {
					if (response.success) {
						alert("Goal assigned successfully!");
						fetchInvestData(custUCC);
					} else {
						alert(response.message || "Error assigning goal");
					}
				},
				error: function (xhr, status, error) {
					console.error("AJAX error:", error);
					alert("Error assigning goal. Please try again.");
				}
			});
		}

        function showGoalDropdown(amc_code, fundCode, dropdownId) {
            let custUCC = $('#custUCC').val();

            $.get('processgoalmngt.php?fetchGoalsDropdown=1&ucc=' + custUCC, function (response) {
                let dropdown = document.getElementById(dropdownId);
                if (!dropdown) return;

                dropdown.innerHTML = '<option value="">Select Goal</option>';

                if (response.data && Array.isArray(response.data)) {
                    response.data.forEach(goal => {
                        dropdown.innerHTML += `<option value="${goal.id}">${escapeHtml(goal.name)}</option>`;
                    });
                }

                dropdown.style.display = "inline-block";
            }, 'json').fail(function (xhr, status, error) {
                console.error("AJAX error:", status, error);
            });
        }

        function fetchCustomerUCCs(input, listId) {
            var val = input.value.toLowerCase();
            var autocompleteList = document.getElementById(listId);
            autocompleteList.innerHTML = "";

            if (val === "") {
                autocompleteList.style.display = "none";
                return;
            }

            fetch("getUCClistwithname.php")
                .then(response => response.json())
                .then(data => {
                    var matchingUCCs = data.filter(entry =>
                        entry.UCC.indexOf(val) !== -1 || entry.name.toLowerCase().indexOf(val) !== -1
                    );

                    if (matchingUCCs.length === 0) {
                        autocompleteList.style.display = "none";
                        return;
                    }

                    matchingUCCs.forEach(function (entry) {
                        var option = document.createElement("div");
                        option.innerHTML = `${entry.UCC} - ${escapeHtml(entry.name)}`;
                        option.addEventListener("click", function () {
                            input.value = entry.UCC;
                            autocompleteList.innerHTML = "";
                            autocompleteList.style.display = "none";
                            $('#selectCustomerBtn').prop('disabled', false);
                        });
                        autocompleteList.appendChild(option);
                    });

                    autocompleteList.style.display = "block";
                })
                .catch(error => console.error(error));
        }

        document.getElementById("custUCC").addEventListener("input", function () {
            fetchCustomerUCCs(this, "autocomplete-list-custUCC");
            var button = document.getElementById("selectCustomerBtn");
            button.disabled = this.value.trim() === "";
        });

        function getUCCName(ucc, callback) {
            var xhr = new XMLHttpRequest();
            var url = "getInvesteddata.php";
            xhr.open("POST", url, true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        callback(response);
                    } catch(e) {
                        console.error("Error parsing response:", e);
                        callback(false);
                    }
                }
            };
            var method = "getUCCName";
            var data = "method=" + method + "&ucc=" + ucc;
            xhr.send(data);
        }
    </script>
</body>
</html>