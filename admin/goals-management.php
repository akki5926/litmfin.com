<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goals Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function fetchCategories() {
            $.get('processgoalmngt.php?fetchCategories=1', function(response) {
                let dropdown = $('#goal_category');
                dropdown.empty();
                dropdown.append('<option value="">Select Category</option>');
                if (response && Array.isArray(response)) {
                    response.forEach(category => {
                        dropdown.append(`<option value="${category.id}">${escapeHtml(category.name)}</option>`);
                    });
                }
            }, 'json').fail(function() {
                console.error("Failed to fetch categories");
            });
        }

        function formatIndianNumber(amount) {
            if (!amount) return '0';
            return new Intl.NumberFormat('en-IN').format(amount);
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

		function fetchGoals(page = 1) {
			let custUCC = $('#custUCC').val();
			
			if (!custUCC) {
				$('#goalTableBody').html('<tr><td colspan="7" class="text-center">Please select a customer first</td></tr>');
				return;
			}

			$.get('processgoalmngt.php?fetchGoals=1&page=' + page + '&ucc=' + custUCC, function(response) {
				let tableBody = $('#goalTableBody');
				tableBody.empty();
				
				if (response.data && response.data.length > 0) {
					response.data.forEach(goal => {
						const row = $(`
							<tr>
								<td>${goal.id}</td>
								<td>${escapeHtml(goal.name)}</td>
								<td>${escapeHtml(goal.category_name)}</td>
								<td>${formatIndianNumber(goal.amount_required)}</td>
								<td>${goal.goal_date}</td>
								<td>${escapeHtml(goal.remarks)}</td>
								<td>
									<button class='btn btn-warning btn-sm edit-goal-btn'>Edit</button>
									<button class='btn btn-danger btn-sm delete-goal-btn'>Delete</button>
								</td>
							</tr>
						`);

						row.find('.edit-goal-btn').data({
							id: goal.id,
							name: goal.name,
							category_id: goal.category_id,
							amount: goal.amount_required,
							date: goal.goal_date,
							remarks: goal.remarks
						});

						row.find('.edit-goal-btn').on('click', function () {
							const $btn = $(this);
							editGoal(
								$btn.data('id'),
								$btn.data('name'),
								$btn.data('category_id'),
								$btn.data('amount'),
								$btn.data('date'),
								$btn.data('remarks')
							);
						});

						row.find('.delete-goal-btn').on('click', function () {
							deleteGoal(goal.id);
						});

						tableBody.append(row);
					});
				} else {
					tableBody.html('<tr><td colspan="7" class="text-center">No goals found for this customer</td></tr>');
				}
			}, 'json').fail(function(jqXHR, textStatus, errorThrown) {
				console.error("AJAX Error:", textStatus, errorThrown);
				$('#goalTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading goals</td></tr>');
			});
		}

		$(document).ready(function() {
			fetchCategories();

			$('#goalsForm').submit(function(e) {
				e.preventDefault();
				let custUCC = $('#custUCC').val();
				
				if (!custUCC) {
					$('#status').html('<div class="alert alert-danger">Please select a customer first</div>');
					return;
				}
				
				let formData = $(this).serialize() + '&ucc=' + custUCC + '&ajax=1';

				$.post('processgoalmngt.php', formData, function(response) {
					$('#status').html(`<div class='alert ${response.success ? 'alert-success' : 'alert-danger'}'>${response.message}</div>`);
					if (response.success) {
						$('#goalsForm')[0].reset();
						$('#edit_id').val('');
						$('#submit-btn').text('Add Goal');
						fetchGoals();
						
						setTimeout(function() {
							$('#status').fadeOut();
						}, 3000);
					}
				}, 'json').fail(function(jqXHR, textStatus, errorThrown) {
					console.error("AJAX Error:", textStatus, errorThrown);
					$('#status').html('<div class="alert alert-danger">Error processing request</div>');
				});
			});

			$('#custUCC').change(function() {
				if ($(this).val()) {
					fetchGoals();
				}
			});
		});

		function editGoal(id, name, category_id, amount, date, remarks) {
			$('#edit_id').val(id);
			$('#goal_name').val(name);
			$('#goal_category').val(category_id);
			$('#amount_required').val(amount);
			$('#on_date').val(date);
			$('#remarks').val(remarks);
			$('#submit-btn').text('Update Goal');
			
			// Add cancel button if not exists
			if ($('#cancel-btn').length === 0) {
				$('#submit-btn').after('<button type="button" class="btn btn-secondary ms-2" id="cancel-btn" onclick="resetGoalForm()">Cancel</button>');
			}
		}
		
		function resetGoalForm() {
			$('#edit_id').val('');
			$('#goalsForm')[0].reset();
			$('#submit-btn').text('Add Goal');
			$('#cancel-btn').remove();
		}

		function deleteGoal(goal_id) {
			let custUCC = $('#custUCC').val();

			if (!confirm("Are you sure you want to delete this goal?")) return;

			$.post('processgoalmngt.php', { deleteGoal: 1, goal_id: parseInt(goal_id), ucc: custUCC }, function(response) {
				if (response.success) {
					fetchGoals();
					$('#status').html('<div class="alert alert-success">Goal deleted successfully</div>');
					setTimeout(function() {
						$('#status').fadeOut();
					}, 3000);
				} else {
					alert("Error deleting goal: " + response.message);
				}
			}, 'json').fail(function(xhr, status, error) {
				console.error("AJAX error:", status, error);
				alert("Error deleting goal");
			});
		}

		function showDiv(event) {
			event.preventDefault();
			var ucc = document.getElementById("custUCC").value;
			if (!ucc) {
				document.getElementById('status1').innerHTML = '<span style="color: red;">Please enter a UCC</span>';
				return;
			}
			getUCCName(ucc, showDiv2);
			fetchGoals();
		}

		function showDiv2(response) {
			var ucc = document.getElementById("custUCC").value;
			var goalDiv = document.getElementById("goal_div");
			var responseContainer = document.getElementById('status1');

			if(response == false) {
				responseContainer.style.color = 'red';
				responseContainer.innerHTML = "Error: UCC " + ucc + " does not exist!";
				goalDiv.style.display = "none";
				return;
			} else {
				responseContainer.style.color = 'blue';
				responseContainer.innerHTML = response;
			}
			goalDiv.style.display = "block";
		}

		document.getElementById("custUCC").addEventListener("input", function () {
			fetchCustomerUCCs(this, "autocomplete-list-custUCC");
		});

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
					var matchingUCCs = data.filter(function (entry) {
						return entry.UCC.indexOf(val) !== -1 || entry.name.toLowerCase().indexOf(val) !== -1;
					});

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
							// Trigger change event
							$(input).trigger('change');
						});
						autocompleteList.appendChild(option);
					});

					autocompleteList.style.display = "block";
				})
				.catch(error => console.error(error));
		}

		function getUCCName(ucc, callback) {
			var xhr = new XMLHttpRequest();
			var url = "getInvesteddata.php";
			xhr.open("POST", url, true);
			xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			xhr.onreadystatechange = function() {
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
	<style>
		.form-container {
			display: flex;
			align-items: center;
			gap: 10px;
			flex-wrap: wrap;
		}

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
		
		#custUCC {
			width: 100%;
			padding: 6px;
			border: 1px solid #ccc;
			border-radius: 4px;
		}
	</style>
</head>
<body class="container mt-5">
	<h2>Manage Goals</h2>
	<div style="max-width: 900px; margin: auto; border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
		<form id="customerForm" method="POST" onsubmit="showDiv(event); return false;">
			<div class="form-container">
				<label for="custUCC">Customer UCC:</label>
				<span class="autocomplete-container">
					<input type="text" id="custUCC" name="custUCC" required autocomplete="off">
					<div id="autocomplete-list-custUCC" class="autocomplete-list"></div>
				</span>
				<button type="submit" name="customer_btn" class="btn" style="background-color: #FD7101; color: white;">Select Customer</button>
			</div>
		</form>
		<div id="status1" style="margin-top: 10px;"></div>
	</div>
	<div id="goal_div" style="margin: 20px; display: none;">
		<div id="status"></div>
		<div class="card p-3 shadow-sm" style="max-width: 600px; margin: auto;">
			<form id="goalsForm" method="post">
				<input type="hidden" id="edit_id" name="edit_id">
				<div class="row mb-2">
					<label for="goal_name" class="col-sm-4 col-form-label">Goal Name</label>
					<div class="col-sm-8">
						<input type="text" class="form-control" id="goal_name" name="goal_name" required>
					</div>
				</div>
				<div class="row mb-2">
					<label for="goal_category" class="col-sm-4 col-form-label">Goal Category</label>
					<div class="col-sm-8">
						<select class="form-select" id="goal_category" name="goal_category" required>
							<option value="">Select Category</option>
						</select>
					</div>
				</div>
				<div class="row mb-2">
					<label for="amount_required" class="col-sm-4 col-form-label">Amount Required</label>
					<div class="col-sm-8">
						<input type="number" class="form-control" id="amount_required" name="amount_required" required>
					</div>
				</div>
				<div class="row mb-2">
					<label for="on_date" class="col-sm-4 col-form-label">Target Date</label>
					<div class="col-sm-8">
						<input type="date" class="form-control" id="on_date" name="on_date" required>
					</div>
				</div>
				<div class="row mb-2">
					<label for="remarks" class="col-sm-4 col-form-label">Remarks</label>
					<div class="col-sm-8">
						<textarea id="remarks" name="remarks" class="form-control" rows="3"></textarea>
					</div>
				</div>
				<div class="text-center mt-3">
					<button type="submit" class="btn btn-primary" id="submit-btn">Add Goal</button>
				</div>
			</form>
		</div>
		<h3 class="mt-4">Existing Goals</h3>
		<div class="table-responsive">
			<table class="table table-bordered">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Category</th>
						<th>Amount</th>
						<th>Target Date</th>
						<th>Remarks</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody id="goalTableBody">
					<tr><td colspan="7" class="text-center">Select a customer to view goals</td></tr>
				</tbody>
			</table>
		</div>
	</div>
</body>
</html>