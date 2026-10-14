//goal-status.js
 selectedCustomerName = ""; // Add this at the top in your <script>
function showDiv(event) {
    console.log("showDiv() triggered");  
    event.preventDefault();

    var ucc = document.getElementById("custUCC").value;
    console.log("UCC entered:", ucc);

    if (!ucc) {
        console.warn("No UCC entered, skipping getUCCName call");
        return;
    }

    console.log("Calling getUCCName with UCC:", ucc);
    getUCCName(ucc, showDiv2);
}

function showDiv2(response) {
    console.log("showDiv2() triggered with response:", response);

    var ucc = document.getElementById("custUCC").value;
    console.log("UCC from input field:", ucc);

    var tableBody = document.getElementById("goalStatusContainer");
    tableBody.innerHTML = "Loading Goal Status... <img src='images/loader.svg' alt='Loading...' width='100px;'>";

    var goalDiv = document.getElementById("goal_div");
    var responseContainer = document.getElementById('status1');

    if (response === false) {
        console.error("getUCCName returned false for UCC:", ucc);
        responseContainer.style.color = 'red';
        responseContainer.innerHTML = "Error: UCC " + ucc + " does not exist!";
        goalDiv.style.display = "none";
    } else {
        console.log("Valid response received:", response);
        responseContainer.style.color = 'blue';
        responseContainer.innerHTML = response;

        // Track selected customer
        selectedCustomerName = response; 
        console.log("selectedCustomerName set to:", selectedCustomerName);

        goalDiv.style.display = "block";

        console.log("Calling fetchGoalStatus with UCC:", ucc);
        fetchGoalStatus(ucc);
    }
}

function fetchGoalStatus(ucc) {
    console.log("fetchGoalStatus called with ucc:", ucc);

    // Step 1: Fetch portfolio data (with NAV, XIRR etc.)
    $.ajax({
        url: "uccfoliodata.php",
        type: "POST",
        dataType: "json",
        data: { ucc: ucc },
        success: function (portfolioResponse) {
            console.log("portfolioResponse:", portfolioResponse);

            // Step 2: Fetch assigned goals
            $.ajax({
                url: "processassigngoal.php",
                type: "POST",
                dataType: "json",
                data: { ucc: ucc, action: 'fetchGoalStatus' },
                success: function (goalResponse) {
                    console.log("goalResponse:", goalResponse);

                    if (goalResponse.success) {
                        renderGoalStatus(goalResponse.data, portfolioResponse.portfolioInfo);
                    } else {
                        console.warn("No goal data found.");
                        document.getElementById("goalStatusContainer").innerHTML = "No goal data found.";
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching goalResponse:", status, error);
                    console.debug("Response Text:", xhr.responseText);
                }
            });
        },
        error: function (xhr, status, error) {
            console.error("Error fetching portfolioResponse:", status, error);
            console.debug("Response Text:", xhr.responseText);
        }
    });
}


function renderGoalStatus(goalsData, portfolioInfo) {
    const container = document.getElementById("goalStatusContainer");
    container.innerHTML = "";

    if (!Array.isArray(goalsData) || goalsData.length === 0) {
        container.innerHTML = "<p>No goals assigned.</p>";
		document.getElementById("exportButtons").style.display = "none";
        return;
    }

    goalsData.forEach((goal, index) => {
        const fundRows = [];
        let totalCurrentValue = 0;

        goal.funds.forEach(fund => {
            const folio = fund.folio;
            const fundCode = fund.fund_code;
            const folioDataArray = portfolioInfo[folio] || [];
            const fundData = folioDataArray.find(f => f[fundCode]);
            if (!fundData) return;

            const details = fundData[fundCode];
            const currValue = parseFloat((details.Curr_Value || "0").toString().replace(/,/g, '')) || 0;
            totalCurrentValue += currValue;

            fundRows.push(`
                <tr>
                    <td>${details.Fund_Name}</td>
                    <td>${details.Unit_Holdings}</td>
                    <td>₹${formatIndianNumber(Math.floor(parseFloat((details.Cost_Value || "0").toString().replace(/,/g, ""))))}</td>
                    <td>${details.Current_NAV}</td>
                    <td>₹${formatIndianNumber(Math.floor(currValue))}</td>
                    <td>${details.XIRR}</td>
                </tr>
            `);
        });

        const balanceYears = Math.max(Math.ceil((new Date(goal.goal_date) - new Date()) / (1000 * 60 * 60 * 24 * 365)), 0);
        const futureValue = balanceYears > 0 ? calculateFutureValue(totalCurrentValue, 0.12, balanceYears) : totalCurrentValue;
        const shortfall = Math.max(parseFloat(goal.amount_required) - futureValue, 0);
		const amtReq = goal.amount_required;
		//console.log("shortfall 1 =",shortfall,", futureValue=", futureValue,", amtReq= ", amtReq);

        const requiredSIP = shortfall > 0 ? calculateSIP(goal.amount_required, 0.12, balanceYears) : 0;

        const percentAchieved = Math.round((totalCurrentValue / goal.amount_required) * 100);
        const futureValuePercent = Math.round((futureValue / goal.amount_required) * 100);
		
		//console.log("shortfall=",shortfall,", futureValue=", futureValue,", amtReq= ", amtReq);
		const today = new Date();
		const goalDate = new Date(goal.goal_date);
		//const balanceYears = Math.max(Math.ceil((goalDate - today) / (1000 * 60 * 60 * 24 * 365)), 0);
		const isPastGoal = goalDate <= today;

        let goalSummaryStatement = '';
			if (isPastGoal) {
				// Goal already expired or due
				const shortfallAmount = Math.max(goal.amount_required - totalCurrentValue, 0);
				goalSummaryStatement = `
					<p>🛑 This goal's target date has already <strong>passed (${formatIndianDate(goal.goal_date)}).</strong></p>
					<p>💼 You’ve achieved <strong>₹${formatIndianNumber(Math.floor(totalCurrentValue))} (${percentAchieved}%)</strong> out of <strong>₹${formatIndianNumber(goal.amount_required)}</strong>.</p>
					${shortfallAmount > 0 
						? `<p><strong style="color: red;">❗You fell short by ₹${formatIndianNumber(Math.floor(shortfallAmount))}.</strong></p>` 
						: `<p>🎉 You met or exceeded your goal. Well done!</p>`}
					<p>🧾 Please review or update this goal if needed.</p>
				`;
			} else if (totalCurrentValue >= goal.amount_required) {

			// Fully achieved already
			goalSummaryStatement = `
				<p><strong>🟢 Great! You've already achieved ₹${formatIndianNumber(Math.floor(totalCurrentValue))}</strong>, which is more than your target of <strong>₹${formatIndianNumber(goal.amount_required)}</strong>.</p>
				<p>🎉 <strong>No additional SIP needed — you're all set for this goal!</strong></p>
			`;
		} else if (futureValue >= goal.amount_required) {
			// Will be achieved by compounding
			goalSummaryStatement = `
				<p><strong>🟢 You've already achieved ₹${formatIndianNumber(Math.floor(totalCurrentValue))} (${percentAchieved}%)</strong> towards your goal of ₹${formatIndianNumber(goal.amount_required)}.</p>
				<p>📈 If your investments grow at 12% annually, they may become <strong>₹${formatIndianNumber(Math.floor(futureValue))} (${futureValuePercent}%)</strong> by <strong>${formatIndianDate(goal.goal_date)}</strong>, which will be enough to reach your goal.</p>
				<p>✅ <strong>No additional SIP is needed — stay invested to meet the goal!</strong></p>
			`;
		} else {
			// Shortfall exists
			goalSummaryStatement = `
				<p><strong>🟢 You've already achieved ₹${formatIndianNumber(Math.floor(totalCurrentValue))} (${percentAchieved}%)</strong> towards your goal of ₹${formatIndianNumber(goal.amount_required)}.</p>
				<p>📈 If your investments grow at 12% annually, they may become <strong>₹${formatIndianNumber(Math.floor(futureValue))} (${futureValuePercent}%)</strong> by <strong>${formatIndianDate(goal.goal_date)}</strong>.</p>
				<p><strong style="color: red;">❗You still need ₹${formatIndianNumber(Math.floor(shortfall))} more to reach your goal.</strong></p>
				<p>💸 Consider adding or topping up your SIPs to meet the shortfall. Contact your advisor for guidance.</p>
				
			`;
		}
		// <p>💸 You should invest <strong>₹${formatIndianNumber(Math.floor(requiredSIP))}/month</strong> for the next <strong>${balanceYears} years</strong> to bridge the gap.</p>

        const goalId = `goalBody_${index}`;
        const tableId = `goalTable_${index}`;
        const exportExcelId = `exportExcel_${index}`;
        const exportPDFId = `exportPDF_${index}`;

        const tableHTML = `
            <div class="goal-section mb-4 border rounded shadow-sm">
                <div class="goal-header p-3 bg-light d-flex justify-content-between align-items-center" style="cursor:pointer;" onclick="toggleGoal('${goalId}', this)">
                    <h5 class="mb-0">
                        <span class="indicator">▶</span> ${index + 1}. ${goal.goal_name}
                    </h5>
                </div>
                <div id="${goalId}" class="goal-body p-3" style="display: none;">
                    <p><strong>🎯 Target:</strong> ₹${formatIndianNumber(goal.amount_required)} by ${formatIndianDate(goal.goal_date)}<br>
                    <strong>📝 Remarks:</strong> ${goal.remarks || '—'}</p>


                    <table id="${tableId}" class="table table-bordered">
                        <thead>
                            <tr><th>Scheme</th><th>Units</th><th>Cost</th><th>NAV</th><th>Value</th><th>XIRR</th></tr>
                        </thead>
                        <tbody>
                            ${fundRows.join('')}
                        </tbody>
                    </table>

                    <div class="goal-summary mt-3 mb-4">
                        ${goalSummaryStatement}
                    </div>
                </div>
            </div>
        `;

        container.innerHTML += tableHTML;
		});
		document.getElementById("exportButtons").style.display = "block";

	}
	function exportGoalsToPDF() {
		const element = document.createElement('div');
		element.style.fontSize = '12px';
		element.style.padding = '20px';
		element.style.margin = '0';
		element.style.textAlign = 'left';
		element.style.lineHeight = '1.4';
		// Don't force page break here

		// Add heading
		const header = document.createElement('h2');
		header.textContent = `Goal Status Report for ${selectedCustomerName}`;
		header.style.textAlign = 'center';
		header.style.margin = '0 0 20px 0';
		element.appendChild(header);

		// Clone and append visible goal sections
		document.querySelectorAll('.goal-section').forEach(section => {
			const clone = section.cloneNode(true);
			const body = clone.querySelector('.goal-body');
			if (body) body.style.display = 'block';
			element.appendChild(clone);
		});

		// Export to PDF
		html2pdf().from(element).set({
			margin: [0.5, 0.5, 0.5, 0.5], // inches
			filename: `Goal_Status_${selectedCustomerName.replace(/\s+/g, '_')}.pdf`,
			image: { type: 'jpeg', quality: 0.98 },
			html2canvas: {
				scale: 2,
				useCORS: true
			},
			jsPDF: {
				unit: 'in',
				format: 'a4',
				orientation: 'portrait'
			}
		}).save();
	}
	function exportGoalsToExcel() {
		const wb = XLSX.utils.book_new();

		document.querySelectorAll('.goal-section').forEach((section, index) => {
			const goalName = section.querySelector('.goal-header h5').innerText;
			const table = section.querySelector('table');
			const summaryText = section.querySelector('.goal-summary').innerText;

			const wsData = [
				[`Goal Status Report for ${selectedCustomerName}`],
				[goalName],
				["Summary"],
				[summaryText],
				[],
				...Array.from(table.rows).map(row => Array.from(row.cells).map(cell => cell.innerText))
			];

			const ws = XLSX.utils.aoa_to_sheet(wsData);
			XLSX.utils.book_append_sheet(wb, ws, `Goal ${index + 1}`);
		});

		XLSX.writeFile(wb, `Goal_Status_${selectedCustomerName.replace(/\s+/g, '_')}.xlsx`);
	}


		function formatIndianNumber(num) {
			const n = Number(num);
			if (isNaN(n)) return "0";
			return n.toLocaleString('en-IN', { maximumFractionDigits: 0 });
		}

		function formatIndianDate(dateString) {
			if (!dateString) return '';
			const date = new Date(dateString);
			const day = String(date.getDate()).padStart(2, '0');
			const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
			const year = date.getFullYear();
			return `${day}/${month}/${year}`;
		}

		function calculateYearsLeft(goalDate) {
			const end = new Date(goalDate);
			const today = new Date();
			const years = (end - today) / (1000 * 60 * 60 * 24 * 365);
			return Math.max(0, Math.floor(years));
		}

		function calculateFutureValue(presentValue, rate = 0.12, years) {
			return Math.round(presentValue * Math.pow(1 + rate, years));
		}

		function calculateSIP(futureValue, annualRate, years) {
			//console.log("DEBUG SIP Calculation Inputs", { futureValue, annualRate, years });

			if (years <= 0) {
				console.warn("⚠️ Years is 0 or negative");
				return 0;
			}

			const monthlyRate = annualRate / 12;
			const months = years * 12;

			if (monthlyRate === 0) {
				console.warn("⚠️ Monthly Rate is 0");
				return 0;
			}

			const factor = (Math.pow(1 + monthlyRate, months) - 1) / monthlyRate;

			//console.log("💡 SIP Factor:", factor);

			if (!isFinite(factor) || factor === 0) {
				console.warn("⚠️ Invalid SIP Factor:", factor);
				return 0;
			}

			const sip = futureValue / factor;

			//console.log("💰 Calculated SIP:", sip);

			return sip;
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
                        option.innerHTML = `${entry.UCC} - ${entry.name}`;
                        option.addEventListener("click", function () {
                            input.value = entry.UCC;
                            autocompleteList.innerHTML = "";
                            autocompleteList.style.display = "none";
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
			console.log("getUCCName() called with:", ucc);

			fetch("getInvesteddata.php", {
				method: "POST",
				headers: { "Content-Type": "application/x-www-form-urlencoded" },
				body: "method=getUCCName&UCC=" + encodeURIComponent(ucc)
			})
			.then(response => {
				if (!response.ok) {
					throw new Error("Network error: " + response.status);
				}
				return response.json();
			})
			.then(data => {
				console.log("getUCCName response:", data);
				callback(data);
			})
			.catch(error => {
				console.error("Error in getUCCName:", error);
				callback(false);
			});
		}

		function toggleGoal(bodyId, headerElem) {
			const body = document.getElementById(bodyId);
			const indicator = headerElem.querySelector(".indicator");

			if (body.style.display === "none") {
				body.style.display = "block";
				indicator.textContent = "▼";
			} else {
				body.style.display = "none";
				indicator.textContent = "▶";
			}
		}