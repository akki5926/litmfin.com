<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Categories</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
   <script>
	function fetchCategories(page = 1) {
		$.get('processgoal.php?fetch=1&page=' + page, function(response) {
			if (response.data && response.data.length > 0) {
				let tableBody = $('#goalCategoryTableBody');
				tableBody.empty();

				response.data.forEach(row => {
					let tableRow = $(`
						<tr>
							<td>${row.id}</td>
							<td>${escapeHtml(row.name)}</td>
							<td>${escapeHtml(row.description)}</td>
							<td>${row.created_at}</td>
							<td>
								<button class='btn btn-warning btn-sm edit-category-btn'>Edit</button>
								<button class='btn btn-danger btn-sm delete-category-btn'>Delete</button>
							</td>
						</tr>
					`);

					tableRow.find('.edit-category-btn').data({
						id: row.id,
						name: row.name,
						description: row.description
					}).on('click', function () {
						const $btn = $(this);
						editCategory(
							$btn.data('id'),
							$btn.data('name'),
							$btn.data('description')
						);
					});

					tableRow.find('.delete-category-btn').on('click', function () {
						confirmDelete(row.id);
					});

					tableBody.append(tableRow);
				});

				generatePagination(response.totalPages, response.currentPage);
			} else {
				$('#goalCategoryTableBody').html('<tr><td colspan="5" class="text-center">No categories found</td></tr>');
			}
		}).fail(function(jqXHR, textStatus, errorThrown) {
			console.error("AJAX Error:", textStatus, errorThrown);
			$('#goalCategoryTableBody').html('<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>');
		});
	}

	function generatePagination(totalPages, currentPage) {
		let paginationDiv = $('#pagination');
		paginationDiv.empty();

		if (totalPages > 1) {
			for (let i = 1; i <= totalPages; i++) {
				let button = `<button class="btn ${i === currentPage ? 'btn-primary' : 'btn-secondary'} m-1" onclick="fetchCategories(${i})">${i}</button>`;
				paginationDiv.append(button);
			}
		}
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

    $(document).ready(function() {
		$('form').submit(function(e) {
			e.preventDefault();
			let formData = $(this).serialize() + '&ajax=1';
			
			$.post('processgoal.php', formData, function(response) {
				$('#status').html(`<div class='alert ${response.success ? 'alert-success' : 'alert-danger'}'>${response.message}</div>`);

				if (response.success) {
					$('#name').val('');
					$('#description').val('');
					$('#edit_id').val('');
					$('#submit-btn').text('Add Category');
					fetchCategories(1);
					
					// Auto-hide status message after 3 seconds
					setTimeout(function() {
						$('#status').fadeOut();
					}, 3000);
				}
			}, 'json').fail(function(jqXHR, textStatus, errorThrown) {
				console.error("AJAX Error:", textStatus, errorThrown);
				$('#status').html(`<div class='alert alert-danger'>Error processing request</div>`);
			});
		});

		fetchCategories(1);
	});

    function editCategory(id, name, description) {
		$('#edit_id').val(id);
		$('#name').val(name);
		$('#description').val(description);
		$('#submit-btn').text('Update Category');

		if ($('#cancel-btn').length === 0) {
			$('#submit-btn').after('<button type="button" class="btn btn-secondary ms-2" id="cancel-btn" onclick="resetForm()">Cancel</button>');
		}
	}
	
	function resetForm() {
		$('#edit_id').val('');
		$('#name').val('');
		$('#description').val('');
		$('#submit-btn').text('Add Category');
		$('#cancel-btn').remove();
	}

    function confirmDelete(id) {
        if (confirm('Are you sure you want to delete this category?')) {
            $.get('processgoal.php?delete=' + id, function(response) {
                try {
                    let res = typeof response === 'string' ? JSON.parse(response) : response;
                    alert(res.message);
                    if (res.success) fetchCategories(1);
                } catch (error) {
                    console.error("Error parsing delete response:", error, response);
                    alert('Error deleting category');
                }
            }).fail(function(xhr, status, error) {
                console.error("AJAX Delete Error:", status, error, xhr.responseText);
                alert('Error deleting category');
            });
        }
    }
</script>

</head>
<body class="container mt-5">
    <div style="margin: 20px;">
        <h2 class="mb-4">Manage Goal Categories</h2>
        <div id="status"></div>

        <form method="post" class="mb-4">
            <input type="hidden" id="edit_id" name="edit_id">
            <div class="mb-3">
                <label for="name" class="form-label">Goal Category Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary" id="submit-btn">Add Category</button>
        </form>

        <h3>Existing Goal Categories</h3>
		<table id="goalCategoriesTable" class="table table-bordered">
			<thead>
				<tr>
					<th>ID</th>
					<th>Name</th>
					<th>Description</th>
					<th>Created At</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody id="goalCategoryTableBody">
				<tr><td colspan="5" class="text-center">Loading...</td></tr>
			</tbody>
		</table>
		<div id="pagination" class="mt-3"></div>
    </div>
</body>
</html>