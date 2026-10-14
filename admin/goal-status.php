<!-- goal-status.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Status</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"> 
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
		.goal-section {
			border-radius: 8px;
			padding: 15px;
			margin-bottom: 20px;
			color: #212529;
			transition: all 0.3s ease;
		}
		.goal-section .card-header {
			cursor: pointer;
			font-weight: bold;
		}
		.goal-gradient-0 { background: linear-gradient(to right, #f9f9f9, #e0e0e0); }
		.goal-gradient-1 { background: linear-gradient(to right, #e8f5e9, #c8e6c9); }
		.goal-gradient-2 { background: linear-gradient(to right, #e3f2fd, #bbdefb); }
		.goal-gradient-3 { background: linear-gradient(to right, #fff3e0, #ffe0b2); }
		.goal-gradient-4 { background: linear-gradient(to right, #fce4ec, #f8bbd0); }
		.goal-gradient-5 { background: linear-gradient(to right, #ede7f6, #d1c4e9); }

		.table th, .table td {
			text-align: center;
			vertical-align: middle;
		}
		.goal-header:hover {
			background-color: #eaeaea;
		}
		.goal-header .indicator {
			font-weight: bold;
			margin-right: 10px;
		}
		
		.progress {
			background-color: #f0f0f0;
			border-radius: 5px;
			overflow: hidden;
			height: 25px;
		}

		.progress-bar {
			font-weight: bold;
			font-size: 14px;
			line-height: 25px;
		}
		.goal-summary p {
			margin: 4px 0;
			font-size: 15px;
		}
		.goal-summary strong {
			color: #1d3557;
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
	</style>
</head>
<body class="container mt-5">
    <h2>Goal Status</h2>
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
        <div id="goalStatusContainer"></div>
    </div>
	<div id="exportButtons" class="text-end mb-3" style="display: none;">
		<button class="btn btn-success me-2" onclick="exportGoalsToExcel()">Export to Excel</button>
		<button class="btn btn-danger" onclick="exportGoalsToPDF()">Export to PDF</button>
	</div>

<!-- Include required libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="goal-status.js"></script>
</body>
</html>