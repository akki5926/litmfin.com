<?php
require_once('auth_guard.php');

/**
 * uploadmasterfile.php
 * Modernized MFD Kingdom Master File Upload Interface
 * Corrected to prevent AJAX loader hangs by removing redundant jQuery.
 */
$output = '
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-12 col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="fas fa-file-upload me-2"></i>Master File Uploads
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="fileType" class="form-label fw-semibold">Select Master Type</label>
                            <select name="fileType" id="fileType" class="form-select form-select-lg">
                                <option value="" disabled selected>— Choose File Category —</option>
                                <option value="Fund House">Fund House (AMC)</option>
                                <option value="MF Master File">MF Scheme Master</option>
                                <option value="Bond Master Update">Bond Master Update</option>
                            </select>
                            <div id="fileTypeDescription" class="form-text mt-2 text-muted italic">
                                Please select a category to view upload instructions.
                            </div>
                        </div>

                        <!-- Import from NSE Section -->
                        <!-- FIX #1: Removed "!important" so JavaScript can toggle display -->
                        <div class="mb-4" id="importNseBtnWrapper" style="display:none;">
                            <button type="button" id="importNseBtn" class="btn btn-info btn-lg px-5 py-3 fw-bold w-100" onclick="redirectToUpdateSch()">
                                <i class="fas fa-sync-alt me-2"></i> Import From NSE Invest
                            </button>
                        </div>

                        <!-- OR Divider -->
                        <div class="mb-4 text-center" id="orDivider" style="display:none;">
                            <div style="position: relative; height: 1px; background: #ddd; margin: 10px 0;">
                                <span style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 15px; color: #999; font-weight: 600;">OR</span>
                            </div>
                        </div>

                        <!-- Manual Upload Section -->
                        <div class="card border-2" id="uploadSection" style="display:none; border-color: #e9ecef !important;">
                            <div class="card-body p-4">
                                <h6 class="card-title mb-3 text-secondary">
                                    <i class="fas fa-file-upload me-2"></i> Manual File Upload
                                </h6>

                                <div class="mb-3">
                                    <label for="fileToUpload" class="form-label fw-semibold" id="fileInputLabel">Upload Excel File (.xls, .xlsx)</label>
                                    <div class="input-group">
                                        <input type="file" class="form-control" name="fileToUpload" id="fileToUpload" accept=".xls,.xlsx" disabled>
                                        <label class="input-group-text" for="fileToUpload"><i class="fas fa-folder-open"></i></label>
                                    </div>
                                    <!-- FIX #3: File validation message placeholder -->
                                    <div id="fileValidationMsg" class="form-text text-danger mt-1" style="display:none;"></div>
                                </div>

                                <div class="mb-3">
                                    <label for="remarks" class="form-label fw-semibold">Remarks (Optional)</label>
                                    <textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Enter any notes about this batch..." disabled></textarea>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-between align-items-center">
                                    <div id="templateDownload" style="display:none;">
                                        <a id="templateLink" href="#" class="btn btn-outline-secondary btn-sm" download>
                                            <i class="fas fa-download me-1"></i> Download Template
                                        </a>
                                    </div>
                                    <button type="button" name="uploadFileBtn" id="uploadFileBtn" class="btn btn-primary px-5 py-2 fw-bold" onclick="uploadFileToDb()" disabled>
                                        <i class="fas fa-cloud-upload-alt me-2"></i> Start Upload
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="mt-4">
                        <div id="fileUploadLoader" class="text-center" style="display:none;">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-primary fw-bold">Processing your file, please wait...</p>
                        </div>
                        <div id="ResultDisplay"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>';

echo $output;
?>

<style>
    #fileTypeDescription {
        min-height: 20px;
        transition: all 0.3s ease;
    }
    .alert {
        border-radius: 8px;
        font-weight: 500;
    }
    .btn-primary {
        box-shadow: 0 4px 6px rgba(0, 123, 255, 0.15);
        transition: transform 0.2s;
    }
    .btn-primary:active {
        transform: scale(0.98);
    }
    .card {
        border-radius: 12px;
    }
    .italic { font-style: italic; }
    .amc-suggestion {
        background-color: #fff9e6 !important;
        font-style: italic;
    }
    
    /* Compact Table Styles */
    #ResultDisplay table td, #ResultDisplay table th {
        padding: 4px 8px !important;
        vertical-align: middle;
    }
    #ResultDisplay .form-control-sm {
        padding: 0.15rem 0.4rem !important;
        font-size: 12px !important;
        height: auto !important;
    }
    #ResultDisplay .btn-sm {
        padding: 0.15rem 0.4rem !important;
        font-size: 11.5px !important;
    }
</style>
<script>
    /**
     * Note: jQuery is NOT loaded here because it is already 
     * present in index.php. Loading it twice causes AJAX errors.
     */
    
    // Define the config globally so it can be accessed by both handlers
    window.fileTypeConfig = {
        'Fund House': {
            desc: '<strong>Required:</strong> AMC Master file from NSENMF (Download Master-AMC).',
            tpl: 'fund_house_template.xls',
            fileAccept: '.xls, .xlsx',
            allowedExt: ['xls', 'xlsx']
        },
        'MF Master File': {
            desc: '<strong>Required:</strong> Scheme Master file from NSENMF or upload CSV file.',
            tpl: 'SchemeMaster-Template.csv',
            fileAccept: '.csv',
            allowedExt: ['csv']
        },
        'Bond Master Update': {
            desc: '<strong>Manage Bonds:</strong> Add, edit, or delete custom Bond assets.',
            tpl: 'bond_master_template.xlsx',
            fileAccept: '.xls, .xlsx',
            allowedExt: ['xls', 'xlsx']
        }
    };

    $(document).ready(function() {
        // FAIL-SAFE: Hide the main index loader if it's still running
        $('#loader').hide(); 
        
        // Initialize the AMC Manager
        if (window.AmcManager) {
            window.AmcManager.init();
        }

        $('#fileType').off('change').on('change', function() {
            var selectedOption = $(this).val();
            var $resultDisplay = $('#ResultDisplay');
            
            $resultDisplay.empty();
            $('#fileToUpload').val(''); 
            
            // Reset all sections to hidden
            document.getElementById('importNseBtnWrapper').style.display = 'none';
            document.getElementById('orDivider').style.display = 'none';
            document.getElementById('uploadSection').style.display = 'none';
            document.getElementById('templateDownload').style.display = 'none';

            // New logic for Fund House
            if (selectedOption === 'Fund House') {
                $('#fileTypeDescription').html('<i class="fas fa-info-circle me-1"></i> Edit AMC names sourced from the scheme master file.');
                window.AmcManager.loadAmcData();
                return; // Stop further execution for this option
            }

            // New logic for Bond Master Update manual CRUD + listing
            if (selectedOption === 'Bond Master Update') {
                var cfg = window.fileTypeConfig[selectedOption];
                $('#fileTypeDescription').html('<i class="fas fa-info-circle me-1"></i> ' + cfg.desc);
                $('#templateLink').attr('href', cfg.tpl);
                $('#fileToUpload').attr('accept', cfg.fileAccept);
                $('#fileInputLabel').text('Upload Excel File (.xls, .xlsx)');
                
                // Hide upload section as requested for Bond Master Update
                document.getElementById('uploadSection').style.display = 'none';
                $('input[type="file"], #remarks, #uploadFileBtn').prop('disabled', true);
                document.getElementById('templateDownload').style.display = 'none';

                window.BondManager.loadBondData();
                return;
            }

            var cfg = window.fileTypeConfig[selectedOption];
            if (cfg) {
                $('#fileTypeDescription').html('<i class="fas fa-info-circle me-1"></i> ' + cfg.desc);
                $('#templateLink').attr('href', cfg.tpl);
                
                // Update file input accept attribute
                $('#fileToUpload').attr('accept', cfg.fileAccept);
                
                // Update label based on file type
                if (selectedOption === 'MF Master File') {
                    $('#fileInputLabel').text('Upload CSV File (.csv)');
                } else {
                    $('#fileInputLabel').text('Upload Excel File (.xls, .xlsx)');
                }
                
                // IMPORTANT: Show import button ONLY when MF Master File is selected
                if (selectedOption === 'MF Master File') {
                    document.getElementById('importNseBtnWrapper').style.display = 'block';
                    document.getElementById('orDivider').style.display = 'block';
                }
                
                // Always show upload section
                document.getElementById('uploadSection').style.display = 'block';
                
                // Enable Elements
                $('input[type="file"], #remarks, #uploadFileBtn').prop('disabled', false);
                document.getElementById('templateDownload').style.display = 'block';
            } else {
                $('#fileTypeDescription').text('Please select a category to view upload instructions.');
                $('input[type="file"], #remarks, #uploadFileBtn').prop('disabled', true);
            }
        });

        // FIX #3: Live file validation on selection
        $('#fileToUpload').off('change').on('change', function() {
            var fileType = $('#fileType').val();
            var cfg = window.fileTypeConfig[fileType];
            if (!cfg || !this.files[0]) return;

            var fileName = this.files[0].name;
            var fileExt = fileName.split('.').pop().toLowerCase();
            var fileSize = this.files[0].size;
            var maxSize = 10 * 1024 * 1024; // 10MB

            $('#fileValidationMsg').hide().text('');

            if (cfg.allowedExt.indexOf(fileExt) === -1) {
                $('#fileValidationMsg')
                    .text('Invalid file type ".' + fileExt + '". Please upload: ' + cfg.fileAccept)
                    .show();
                $('#uploadFileBtn').prop('disabled', true);
                return;
            }

            if (fileSize > maxSize) {
                $('#fileValidationMsg')
                    .text('File size exceeds 10MB limit. Please upload a smaller file.')
                    .show();
                $('#uploadFileBtn').prop('disabled', true);
                return;
            }

            $('#uploadFileBtn').prop('disabled', false);
        });
    });

    function redirectToUpdateSch() {
        if (typeof window.loadContent === 'function') {
            // Clear active states in the sidebar
            $('#sidebar a').removeClass('active');
            // Load the NSE update page via the global loader
            window.loadContent('getsch-from-Nse.php', 'Update Scheme Master', true);
        } else {
            $('#main_content').load('getsch-from-Nse.php');
        }
    }

    function uploadFileToDb() {
        var selectedFile = $('#fileToUpload').prop('files')[0];
        var fileType = $('#fileType').val();
        var remarks = $('#remarks').val();
        var $resultDisplay = $('#ResultDisplay');
        var cfg = window.fileTypeConfig[fileType];

        // Guard: no file selected
        if (!selectedFile) {
            $resultDisplay.html('<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Please select a file first!</div>');
            return;
        }

        // Guard: wrong extension (double-check even if live validation ran)
        var fileExt = selectedFile.name.split('.').pop().toLowerCase();
        if (cfg && cfg.allowedExt.indexOf(fileExt) === -1) {
            $resultDisplay.html(
                '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>' +
                'Invalid file type. For <strong>' + fileType + '</strong>, please upload: ' + cfg.fileAccept +
                '</div>'
            );
            return;
        }

        var formData = new FormData();
        // FIX #2: Key must match name="fileToUpload" so server reads $_FILES['fileToUpload']
        formData.append('fileToUpload', selectedFile);
        formData.append('fileType', fileType);
        formData.append('remarks', remarks);

        // Show loader, clear old results, disable button
        $('#fileUploadLoader').show();
        $resultDisplay.empty();
        $('#uploadFileBtn').prop('disabled', true);

        $.ajax({
            url: 'extractmasfiletodb.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#fileUploadLoader').hide();
                $('#uploadFileBtn').prop('disabled', false);
                
                try {
                    var responseObject = JSON.parse(response);
                    var msg = responseObject.message || 'No message returned from server.';

                    // FIX #4: Check message on parsed object, not raw response string
                    var isSuccess = msg.toLowerCase().includes('success');
                    var alertClass = isSuccess ? 'alert-success' : 'alert-danger';
                    var icon = isSuccess ? 'fa-check-circle' : 'fa-times-circle';

                    $resultDisplay.html(
                        '<div class="alert ' + alertClass + ' mt-3">' +
                            '<i class="fas ' + icon + ' me-2"></i> ' + msg +
                        '</div>'
                    );
                } catch (e) {
                    // Server returned non-JSON (e.g. PHP error/warning text)
                    $resultDisplay.html(
                        '<div class="alert alert-danger mt-3">' +
                            '<i class="fas fa-times-circle me-2"></i>' +
                            '<strong>Unexpected server response:</strong><br><pre style="font-size:12px;margin-top:8px;">' +
                            $('<div>').text(response).html() +
                            '</pre>' +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                $('#fileUploadLoader').hide();
                $('#uploadFileBtn').prop('disabled', false);
                $resultDisplay.html(
                    '<div class="alert alert-danger mt-3">' +
                        '<i class="fas fa-exclamation-circle me-2"></i>' +
                        'Upload failed. Server returned: <strong>' + (xhr.status || 'unknown') + ' ' + (error || '') + '</strong>' +
                    '</div>'
                );
            }
        });
    }

    // --- New AMC Manager Module ---
    // Use window assignment to avoid syntax errors on AJAX reload due to duplicate 'const' declaration
    window.AmcManager = {
        init: function() {
            // This can be used for any initial setup if needed
        },

        loadAmcData: function() {
            var $resultDisplay = $('#ResultDisplay');
            var $loader = $('#fileUploadLoader');

            // Preserve active filter before reloading
            var preservedFilter = $('#amcStatusFilter').val() || 'both';

            $loader.show();
            $resultDisplay.html('');

            $.ajax({
                url: 'amc_master_handler.php',
                type: 'POST',
                data: { action: 'get_amc_data' },
                dataType: 'json',
                success: function(response) {
                    $loader.hide();
                    if (response.success) {
                        window.AmcManager.renderTable(response.data, preservedFilter);
                    } else {
                        $resultDisplay.html('<div class="alert alert-danger">Error: ' + (response.message || 'Could not load AMC data.') + '</div>');
                    }
                },
                error: function() {
                    $loader.hide();
                    $resultDisplay.html('<div class="alert alert-danger">A server error occurred while fetching AMC data.</div>');
                }
            });
        },

        renderTable: function(data, preservedFilter) {
            var $resultDisplay = $('#ResultDisplay');

            var filterBothSelected = preservedFilter === 'both' ? 'selected' : '';
            var filterExistingSelected = preservedFilter === 'existing' ? 'selected' : '';
            var filterNewSelected = preservedFilter === 'new' ? 'selected' : '';

            var tableHtml = `
                <!-- Add Custom AMC Form -->
                <div class="card border-0 shadow-sm bg-light mb-4">
                    <div class="card-body p-3">
                        <h6 class="card-title fw-bold text-primary mb-3">
                            <i class="fas fa-plus-circle me-2"></i>Add Custom Fund House (AMC)
                        </h6>
                        <form id="customAmcForm" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="customAmcCode" class="form-label fw-semibold text-muted small">AMC Code</label>
                                <input type="text" id="customAmcCode" class="form-control form-control-sm" placeholder="Enter AMC Code" required>
                            </div>
                            <div class="col-md-5">
                                <label for="customAmcLongName" class="form-label fw-semibold text-muted small">Long Name</label>
                                <input type="text" id="customAmcLongName" class="form-control form-control-sm" placeholder="Enter Long Name" required>
                            </div>
                            <div class="col-md-2">
                                <label for="customAmcKra" class="form-label fw-semibold text-muted small">KRA</label>
                                <select id="customAmcKra" class="form-select form-select-sm">
                                    <option value="">--KRA--</option>
                                    <option value="CAMS">CAMS</option>
                                    <option value="KFINTECH">KFINTECH</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-primary w-100 fw-bold" onclick="window.AmcManager.addCustomAmc(this)">
                                    <i class="fas fa-plus me-1"></i> Add AMC
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Status Filter Dropdown -->
                <div class="row mb-3 align-items-center">
                    <div class="col-md-4 col-sm-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white text-muted fw-semibold"><i class="fas fa-filter text-primary me-1"></i> Filter Status:</span>
                            <select id="amcStatusFilter" class="form-select form-select-sm fw-semibold" onchange="window.AmcManager.filterTable()">
                                <option value="both" ${filterBothSelected}>Show Both</option>
                                <option value="existing" ${filterExistingSelected}>Only Existing</option>
                                <option value="new" ${filterNewSelected}>Only New Suggestions</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8 col-sm-6 text-end">
                        <button type="button" class="btn btn-sm btn-info fw-bold text-dark shadow-sm" onclick="window.AmcManager.updateAllKra(this)">
                            <i class="fas fa-magic me-1"></i> Auto-Update All KRAs
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 600px;">
                    <table class="table table-bordered table-hover table-sm" style="font-size: 13px;">
                        <thead class="table-light" style="position: sticky; top: 0;">
                            <tr>
                                <th style="width: 5%;" class="text-center">#</th>
                                <th style="width: 15%;">AMC Code</th>
                                <th style="width: 45%;">Long Name (to save in AMC Master)</th>
                                <th style="width: 15%;">KRA</th>
                                <th style="width: 20%;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            if (data.length > 0) {
                data.forEach((item, index) => {
                    var isSuggestion = item.is_suggestion;
                    var suggestionClass = isSuggestion ? 'amc-suggestion' : '';
                    
                    var actionButton = isSuggestion 
                        ? `<button type="button" class="btn btn-sm btn-success amc-action-btn" onclick="window.AmcManager.saveRow(this)"><i class="fas fa-plus me-1"></i> Add</button>`
                        : `<button type="button" class="btn btn-sm btn-warning text-dark amc-action-btn me-1" onclick="window.AmcManager.saveRow(this)"><i class="fas fa-sync-alt me-1"></i> Update</button><button type="button" class="btn btn-sm btn-danger amc-action-btn" onclick="window.AmcManager.deleteRow(this)"><i class="fas fa-trash-alt me-1"></i> Delete</button>`;

                    tableHtml += `
                        <tr data-amc-code="${window.AmcManager.escape(item.amc_code)}">
                            <td class="text-center align-middle">${index + 1}</td>
                            <td class="font-monospace bg-light align-middle">${window.AmcManager.escape(item.amc_code)}</td>
                            <td class="align-middle">
                                <input type="text" class="form-control form-control-sm amc-long-name ${suggestionClass}" value="${window.AmcManager.escape(item.long_name)}">
                            </td>
                            <td class="align-middle">
                                <select class="form-select form-select-sm amc-kra ${suggestionClass}">
                                    <option value="" ${!item.kra ? 'selected' : ''}>--KRA--</option>
                                    <option value="CAMS" ${item.kra === 'CAMS' ? 'selected' : ''}>CAMS</option>
                                    <option value="KFINTECH" ${item.kra === 'KFINTECH' ? 'selected' : ''}>KFINTECH</option>
                                </select>
                            </td>
                            <td class="text-center align-middle action-cell text-nowrap">
                                ${actionButton}
                            </td>
                        </tr>
                    `;
                });
            } else {
                tableHtml += '<tr><td colspan="5" class="text-center text-muted">No AMC codes found in scheme master.</td></tr>';
            }

            tableHtml += '</tbody></table></div>';
            $resultDisplay.html(tableHtml);

            // Apply filter on render
            window.AmcManager.filterTable();
        },

        filterTable: function() {
            var filterVal = $('#amcStatusFilter').val() || 'both';
            var visibleIndex = 1;
            var visibleCount = 0;

            // Remove existing temporary empty row if any
            $('#ResultDisplay tbody tr.no-filter-results').remove();

            $('#ResultDisplay tbody tr').each(function() {
                var $row = $(this);
                // Skip the empty state row
                if ($row.find('td').length === 1 && $row.find('td').attr('colspan') === '5') {
                    return;
                }

                var isSuggestion = $row.find('.amc-long-name').hasClass('amc-suggestion');
                var show = false;

                if (filterVal === 'both') {
                    show = true;
                } else if (filterVal === 'existing') {
                    show = !isSuggestion;
                } else if (filterVal === 'new') {
                    show = isSuggestion;
                }

                if (show) {
                    $row.show();
                    $row.find('td:first').text(visibleIndex++);
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            if (visibleCount === 0 && $('#ResultDisplay tbody tr').length > 0) {
                $('#ResultDisplay tbody').append(`
                    <tr class="no-filter-results">
                        <td colspan="5" class="text-center text-muted py-3">No matching AMC records found.</td>
                    </tr>
                `);
            }
        },

        addCustomAmc: function(button) {
            var $btn = $(button);
            var amcCode = $('#customAmcCode').val().trim();
            var longName = $('#customAmcLongName').val().trim();
            var kra = $('#customAmcKra').val();

            if (!amcCode || !longName) {
                alert('Please enter both AMC Code and Long Name.');
                return;
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adding...');

            $.ajax({
                url: 'amc_master_handler.php',
                type: 'POST',
                data: { action: 'save_amc_name', amc_code: amcCode, long_name: longName, kra: kra, sif_code: '' },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).html('<i class="fas fa-plus me-1"></i> Add AMC');
                    if (response.success) {
                        alert(response.message || 'Custom AMC added successfully!');
                        // Clear the form fields
                        $('#customAmcCode').val('');
                        $('#customAmcLongName').val('');
                        // Reload the table data
                        window.AmcManager.loadAmcData();
                    } else {
                        alert('Error: ' + (response.message || 'Could not add custom AMC.'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-plus me-1"></i> Add AMC');
                    alert('An error occurred while adding custom AMC.');
                }
            });
        },

        saveRow: function(button) {
            var $btn = $(button);
            var $row = $btn.closest('tr');
            var amcCode = $row.data('amc-code');
            var longName = $row.find('.amc-long-name').val().trim();
            var kra = $row.find('.amc-kra').val();
            var isAdd = $btn.hasClass('btn-success');

            if (!amcCode || !longName) {
                alert('AMC Code and Long Name are required to save.');
                return;
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'amc_master_handler.php',
                type: 'POST',
                data: { action: 'save_amc_name', amc_code: amcCode, long_name: longName, kra: kra, sif_code: '' },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        // Remove visual styling for suggestion
                        $row.find('.amc-long-name').removeClass('amc-suggestion');
                        // Update action cell to show "Update" and "Delete" buttons
                        $row.find('.action-cell').html('<button type="button" class="btn btn-sm btn-warning text-dark amc-action-btn me-1" onclick="window.AmcManager.saveRow(this)"><i class="fas fa-sync-alt me-1"></i> Update</button><button type="button" class="btn btn-sm btn-danger amc-action-btn" onclick="window.AmcManager.deleteRow(this)"><i class="fas fa-trash-alt me-1"></i> Delete</button>');
                        alert(response.message || 'Saved successfully!');
                        
                        // Apply filter status on saved row
                        window.AmcManager.filterTable();
                    } else {
                        // Restore button text on API error
                        $btn.html(isAdd ? '<i class="fas fa-plus me-1"></i> Add' : '<i class="fas fa-sync-alt me-1"></i> Update');
                        alert('Error: ' + (response.message || 'Could not save.'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html(isAdd ? '<i class="fas fa-plus me-1"></i> Add' : '<i class="fas fa-sync-alt me-1"></i> Update');
                    alert('An error occurred while saving.');
                }
            });
        },

        deleteRow: function(button) {
            var $btn = $(button);
            var $row = $btn.closest('tr');
            var amcCode = $row.data('amc-code');

            if (!amcCode) {
                alert('AMC Code is required to delete.');
                return;
            }

            if (!confirm('Are you sure you want to delete the AMC code "' + amcCode + '"? This action will remove it from the AMC master list.')) {
                return;
            }

            var $actionCell = $row.find('.action-cell');
            $actionCell.find('button').prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...');

            $.ajax({
                url: 'amc_master_handler.php',
                type: 'POST',
                data: { action: 'delete_amc', amc_code: amcCode },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message || 'Deleted successfully!');
                        window.AmcManager.loadAmcData();
                    } else {
                        window.AmcManager.loadAmcData();
                        alert('Error: ' + (response.message || 'Could not delete.'));
                    }
                },
                error: function() {
                    window.AmcManager.loadAmcData();
                    alert('An error occurred while deleting.');
                }
            });
        },

        updateAllKra: function(button) {
            var $btn = $(button);
            if (!confirm('This will scan the Scheme Master and automatically update the KRA for all AMCs where possible. Do you want to proceed?')) {
                return;
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

            $.ajax({
                url: 'amc_master_handler.php',
                type: 'POST',
                data: { action: 'update_all_kra' },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).html('<i class="fas fa-magic me-1"></i> Auto-Update All KRAs');
                    if (response.success) {
                        alert(response.message || 'Updated successfully!');
                        window.AmcManager.loadAmcData(); // reload table to reflect changes
                    } else {
                        alert('Error: ' + (response.message || 'Could not update KRAs.'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-magic me-1"></i> Auto-Update All KRAs');
                    alert('An error occurred while updating KRAs.');
                }
            });
        },

        escape: function(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
        }
    };

    // --- New Bond Manager Module ---
    window.BondManager = {
        bondsList: [],
        filteredList: [],
        currentPage: 1,
        pageSize: 15,
        sortColumn: '',
        sortDirection: 'asc',

        loadBondData: function() {
            var $resultDisplay = $('#ResultDisplay');
            var $loader = $('#fileUploadLoader');

            $loader.show();
            $resultDisplay.html('');

            $.ajax({
                url: 'bond_master_handler.php',
                type: 'POST',
                data: { action: 'get_bond_data' },
                dataType: 'json',
                success: function(response) {
                    $loader.hide();
                    if (response.success) {
                        window.BondManager.bondsList = response.data || [];
                        window.BondManager.filteredList = window.BondManager.bondsList;
                        window.BondManager.currentPage = 1;
                        window.BondManager.renderTableLayout();
                    } else {
                        $resultDisplay.html('<div class="alert alert-danger">Error: ' + (response.message || 'Could not load Bond data.') + '</div>');
                    }
                },
                error: function() {
                    $loader.hide();
                    $resultDisplay.html('<div class="alert alert-danger">A server error occurred while fetching Bond data.</div>');
                }
            });
        },

        renderTableLayout: function() {
            var $resultDisplay = $('#ResultDisplay');

            var assetClassSelect = `
                <select id="bondAssetClass" class="form-select form-select-sm" required>
                    <option value="Gold">Gold</option>
                    <option value="Debt">Debt</option>
                    <option value="Hybrid">Hybrid</option>
                    <option value="Equity">Equity</option>
                </select>
            `;
            
            var payoutFreqSelect = `
                <select id="bondPayoutFrequency" class="form-select form-select-sm">
                    <option value="">--Select Frequency--</option>
                    <option value="Monthly">Monthly</option>
                    <option value="Quarterly">Quarterly</option>
                    <option value="Semi-annually">Semi-annually</option>
                    <option value="Annually">Annually</option>
                    <option value="On-maturity">On-maturity</option>
                </select>
            `;

            var html = `
                <!-- Add/Edit Bond Form -->
                <div class="card border-0 shadow-sm bg-light mb-4">
                    <div class="card-body p-3">
                        <h6 class="card-title fw-bold text-primary mb-3">
                            <i class="fas fa-plus-circle me-2"></i><span id="bondFormTitle">Add Custom Bond</span>
                        </h6>
                        <form id="customBondForm" class="row g-3">
                            <input type="hidden" id="bondSlNo" value="">
                            <div class="col-md-3 col-sm-6">
                                <label for="bondAssetClass" class="form-label fw-semibold text-muted small">Asset Class</label>
                                ${assetClassSelect}
                            </div>
                            <div class="col-md-5 col-sm-12">
                                <label for="bondInvestmentName" class="form-label fw-semibold text-muted small">Bond Name *</label>
                                <input type="text" id="bondInvestmentName" class="form-control form-control-sm" placeholder="e.g. 2023-24, Series I" required>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="bondFaceValue" class="form-label fw-semibold text-muted small">Face Value</label>
                                <input type="number" id="bondFaceValue" class="form-control form-control-sm" placeholder="e.g. 1000" min="0">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="bondNseCode" class="form-label fw-semibold text-muted small">NSE Code</label>
                                <input type="text" id="bondNseCode" class="form-control form-control-sm" placeholder="NSE Code">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="bondBseCode" class="form-label fw-semibold text-muted small">BSE Code</label>
                                <input type="text" id="bondBseCode" class="form-control form-control-sm" placeholder="BSE Code">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="bondInterestRate" class="form-label fw-semibold text-muted small">Coupon Rate (%)</label>
                                <input type="number" step="0.01" id="bondInterestRate" class="form-control form-control-sm" placeholder="e.g. 7.50">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="bondPayoutFrequency" class="form-label fw-semibold text-muted small">Payout Frequency</label>
                                ${payoutFreqSelect}
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="bondRedemptionDate" class="form-label fw-semibold text-muted small">Redemption Date</label>
                                <input type="date" id="bondRedemptionDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label for="bondIntAmtPerPayout" class="form-label fw-semibold text-muted small">Int Amt per Payout</label>
                                <input type="number" step="0.01" id="bondIntAmtPerPayout" class="form-control form-control-sm" placeholder="e.g. 74.06">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label fw-semibold text-muted small">Interest Pay Date</label>
                                <div class="input-group input-group-sm">
                                    <select id="bondInterestPayDay" class="form-select">
                                        <option value="">Day</option>
                                        ${Array.from({length: 31}, (_, i) => `<option value="${i+1}">${i+1}</option>`).join('')}
                                    </select>
                                    <select id="bondInterestPayMonth" class="form-select">
                                        <option value="">Month</option>
                                        <option value="Jan">Jan</option>
                                        <option value="Feb">Feb</option>
                                        <option value="Mar">Mar</option>
                                        <option value="Apr">Apr</option>
                                        <option value="May">May</option>
                                        <option value="Jun">Jun</option>
                                        <option value="Jul">Jul</option>
                                        <option value="Aug">Aug</option>
                                        <option value="Sep">Sep</option>
                                        <option value="Oct">Oct</option>
                                        <option value="Nov">Nov</option>
                                        <option value="Dec">Dec</option>
                                    </select>
                                </div>
                                <input type="hidden" id="bondInterestPayDate" value="">
                            </div>
                            <div class="col-12 text-end">
                                <button type="button" id="bondCancelBtn" class="btn btn-sm btn-secondary me-2 fw-bold" style="display:none;" onclick="window.BondManager.resetForm()">
                                    Cancel
                                </button>
                                <button type="button" id="bondSubmitBtn" class="btn btn-sm btn-primary fw-bold" onclick="window.BondManager.saveBond(this)">
                                    <i class="fas fa-plus me-1"></i> Add Bond
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 mt-4 p-2 px-3 rounded border" style="background-color: #cfe2ff; border-color: #9ec5fe !important;">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-list me-2"></i>Existing Bonds</h5>
                </div>

                <!-- Search, Filter and Stats -->
                <div class="row mb-3 align-items-center">
                    <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                        <select id="bondFilterAssetClass" class="form-select form-select-sm fw-semibold" onchange="window.BondManager.filterTable()">
                            <option value="">All Asset Classes</option>
                            <option value="Gold">Gold</option>
                            <option value="Debt">Debt</option>
                            <option value="Hybrid">Hybrid</option>
                            <option value="Equity">Equity</option>
                        </select>
                    </div>
                    <div class="col-md-5 col-sm-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white text-muted fw-semibold"><i class="fas fa-search text-primary me-1"></i> Search:</span>
                            <input type="text" id="bondSearchQuery" class="form-control form-control-sm fw-semibold" placeholder="Search by Bond Name, NSE/BSE Code..." onkeyup="window.BondManager.filterTable()">
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12 text-md-end mt-2 mt-md-0 text-muted small">
                        Total Bonds: <span id="bondTotalCount" class="fw-bold text-primary">0</span>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 500px;">
                    <style>
                        .sort-header { cursor: pointer; user-select: none; }
                        .sort-header:hover { background-color: #e9ecef !important; }
                    </style>
                    <table class="table table-bordered table-hover table-sm mb-0" style="font-size: 13px;" id="bondsTable">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th style="width: 4%;" class="text-center">#</th>
                                <th style="width: 10%;" class="sort-header" onclick="window.BondManager.sortTable('asset_class')">Asset Class <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-asset_class"></i></th>
                                <th style="width: 25%;" class="sort-header" onclick="window.BondManager.sortTable('investment_name')">Bond Name <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-investment_name"></i></th>
                                <th style="width: 8%;" class="sort-header" onclick="window.BondManager.sortTable('face_value')">Face Value <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-face_value"></i></th>
                                <th style="width: 10%;" class="sort-header" onclick="window.BondManager.sortTable('nse_code')">NSE Code <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-nse_code"></i></th>
                                <th style="width: 10%;" class="sort-header" onclick="window.BondManager.sortTable('bse_code')">BSE Code <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-bse_code"></i></th>
                                <th style="width: 8%;" class="sort-header" onclick="window.BondManager.sortTable('interest_rate')">Coupon Rate <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-interest_rate"></i></th>
                                <th style="width: 10%;" class="sort-header" onclick="window.BondManager.sortTable('payout_frequency')">Payout Freq <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-payout_frequency"></i></th>
                                <th style="width: 10%;" class="sort-header" onclick="window.BondManager.sortTable('redemption_date')">Redemption Date <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-redemption_date"></i></th>
                                <th style="width: 10%;" class="sort-header" onclick="window.BondManager.sortTable('int_amt_per_payout')">Payout Amt <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-int_amt_per_payout"></i></th>
                                <th style="width: 10%;" class="sort-header" onclick="window.BondManager.sortTable('interest_pay_date')">Pay Date <i class="fas fa-sort text-muted sort-icon ms-1" id="sort-icon-interest_pay_date"></i></th>
                                <th style="width: 10%;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="bondTableBody">
                            <!-- Populated dynamically by renderPage -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="row align-items-center mt-3">
                    <div class="col-sm-6 text-start text-muted small">
                        Showing <span id="bondShowingCountStart" class="fw-bold">0</span> to <span id="bondShowingCountEnd" class="fw-bold">0</span> of <span id="bondShowingCountTotal" class="fw-bold">0</span> entries
                    </div>
                    <div class="col-sm-6 text-end" id="bondPaginationContainer">
                        <!-- Populated dynamically by renderPage -->
                    </div>
                </div>
            `;

            $resultDisplay.html(html);

            // Payout Frequency change handler to toggle Month visibility
            $('#bondPayoutFrequency').off('change').on('change', function() {
                var freq = $(this).val();
                var $monthSelect = $('#bondInterestPayMonth');
                if (freq === 'Monthly') {
                    $monthSelect.val('Jan').hide();
                } else {
                    $monthSelect.show();
                }
                window.BondManager.updateInterestPayDayOptions();
                window.BondManager.updateInterestPayDateValue();
            });

            // Update day options and hidden input when month changes
            $('#bondInterestPayMonth').off('change').on('change', function() {
                window.BondManager.updateInterestPayDayOptions();
                window.BondManager.updateInterestPayDateValue();
            });

            // Update hidden input when day changes
            $('#bondInterestPayDay').off('change').on('change', function() {
                window.BondManager.updateInterestPayDateValue();
            });

            // Trigger initial change layout
            $('#bondPayoutFrequency').trigger('change');

            // Render current page content
            window.BondManager.renderPage();
        },

        renderPage: function() {
            var data = window.BondManager.filteredList;
            var start = (window.BondManager.currentPage - 1) * window.BondManager.pageSize;
            var end = start + window.BondManager.pageSize;
            var pageData = data.slice(start, end);

            var tbodyHtml = '';
            if (pageData.length > 0) {
                pageData.forEach((item, index) => {
                    var globalIndex = start + index + 1;
                    tbodyHtml += `
                        <tr data-bond-name="${window.BondManager.escape(item.investment_name)}" data-nse-code="${window.BondManager.escape(item.nse_code)}" data-bse-code="${window.BondManager.escape(item.bse_code)}">
                            <td class="text-center align-middle">${globalIndex}</td>
                            <td class="align-middle">${window.BondManager.escape(item.asset_class)}</td>
                            <td class="align-middle fw-semibold text-dark">${window.BondManager.escape(item.investment_name)}</td>
                            <td class="align-middle">${item.face_value !== null ? item.face_value : ''}</td>
                            <td class="align-middle font-monospace">${window.BondManager.escape(item.nse_code)}</td>
                            <td class="align-middle font-monospace">${window.BondManager.escape(item.bse_code)}</td>
                            <td class="align-middle">${item.interest_rate !== null ? item.interest_rate + '%' : ''}</td>
                            <td class="align-middle">${window.BondManager.escape(item.payout_frequency)}</td>
                            <td class="align-middle">${window.BondManager.escape(window.BondManager.formatDisplayDate(item.redemption_date))}</td>
                            <td class="align-middle">${item.int_amt_per_payout !== null ? item.int_amt_per_payout : ''}</td>
                            <td class="align-middle small">${window.BondManager.escape(item.interest_pay_date)}</td>
                            <td class="text-center align-middle action-cell text-nowrap">
                                <button type="button" class="btn btn-sm btn-warning text-dark me-1" onclick="window.BondManager.editBond(${item.slno})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="window.BondManager.deleteBond(${item.slno}, '${window.BondManager.escape(item.investment_name).replace(/'/g, "\\'")}')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbodyHtml += '<tr><td colspan="12" class="text-center text-muted py-3">No Bond Master records found.</td></tr>';
            }

            $('#bondTableBody').html(tbodyHtml);

            // Update stats
            var totalCount = data.length;
            var showingStart = totalCount > 0 ? start + 1 : 0;
            var showingEnd = Math.min(end, totalCount);

            $('#bondShowingCountStart').text(showingStart);
            $('#bondShowingCountEnd').text(showingEnd);
            $('#bondShowingCountTotal').text(totalCount);
            $('#bondTotalCount').text(totalCount);

            // Render pagination links
            var totalPages = Math.ceil(totalCount / window.BondManager.pageSize);
            var paginationHtml = '';

            if (totalPages > 1) {
                paginationHtml += '<nav aria-label="Page navigation" class="d-inline-block"><ul class="pagination pagination-sm justify-content-end mb-0">';
                
                // Previous button
                var prevDisabled = window.BondManager.currentPage === 1 ? 'disabled' : '';
                paginationHtml += `<li class="page-item ${prevDisabled}"><a class="page-link" href="javascript:void(0)" onclick="window.BondManager.changePage(${window.BondManager.currentPage - 1})"><i class="fas fa-chevron-left small"></i></a></li>`;
                
                // Page numbers (smart paging: show first, last, and window around current page)
                var current = window.BondManager.currentPage;
                var range = 2; // number of pages to show before and after current
                for (var i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= current - range && i <= current + range)) {
                        var active = i === current ? 'active' : '';
                        paginationHtml += `<li class="page-item ${active}"><a class="page-link" href="javascript:void(0)" onclick="window.BondManager.changePage(${i})">${i}</a></li>`;
                    } else if (i === current - range - 1 || i === current + range + 1) {
                        paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                // Next button
                var nextDisabled = window.BondManager.currentPage === totalPages ? 'disabled' : '';
                paginationHtml += `<li class="page-item ${nextDisabled}"><a class="page-link" href="javascript:void(0)" onclick="window.BondManager.changePage(${window.BondManager.currentPage + 1})"><i class="fas fa-chevron-right small"></i></a></li>`;
                
                paginationHtml += '</ul></nav>';
            }

            $('#bondPaginationContainer').html(paginationHtml);
        },

        changePage: function(page) {
            window.BondManager.currentPage = page;
            window.BondManager.renderPage();
        },

        filterTable: function() {
            var q = ($('#bondSearchQuery').val() || '').toLowerCase().trim();
            var assetFilter = $('#bondFilterAssetClass').val() || '';
            
                window.BondManager.filteredList = window.BondManager.bondsList.filter(function(item) {
                var matchAsset = true;
                if (assetFilter) {
                    matchAsset = (item.asset_class === assetFilter);
                }
                
                var matchSearch = true;
                if (q) {
                    var name = (item.investment_name || '').toLowerCase();
                    var nse = (item.nse_code || '').toLowerCase();
                    var bse = (item.bse_code || '').toLowerCase();
                    matchSearch = (name.indexOf(q) !== -1 || nse.indexOf(q) !== -1 || bse.indexOf(q) !== -1);
                }
                
                return matchAsset && matchSearch;
                });
            
            if (window.BondManager.sortColumn) {
                window.BondManager.applySort();
            } else {
                window.BondManager.currentPage = 1;
                window.BondManager.renderPage();
            }
        },

        sortTable: function(column) {
            if (window.BondManager.sortColumn === column) {
                window.BondManager.sortDirection = window.BondManager.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                window.BondManager.sortColumn = column;
                window.BondManager.sortDirection = 'asc';
            }
            
            // Update icons
            $('.sort-icon').removeClass('fa-sort-up fa-sort-down text-primary').addClass('fa-sort text-muted');
            var iconClass = window.BondManager.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
            $('#sort-icon-' + column).removeClass('fa-sort text-muted').addClass(iconClass + ' text-primary');
            
            window.BondManager.applySort();
        },
        
        applySort: function() {
            var col = window.BondManager.sortColumn;
            var dir = window.BondManager.sortDirection === 'asc' ? 1 : -1;
            
            window.BondManager.filteredList.sort(function(a, b) {
                var valA = a[col] !== null ? a[col] : '';
                var valB = b[col] !== null ? b[col] : '';
                
                // Try numeric sort for numeric columns
                if (['face_value', 'interest_rate', 'int_amt_per_payout'].includes(col)) {
                    var numA = parseFloat(valA) || 0;
                    var numB = parseFloat(valB) || 0;
                    return (numA - numB) * dir;
                }
                
                // String comparison
                valA = String(valA).toLowerCase();
                valB = String(valB).toLowerCase();
                
                if (valA < valB) return -1 * dir;
                if (valA > valB) return 1 * dir;
                return 0;
            });
            
            window.BondManager.currentPage = 1;
            window.BondManager.renderPage();
        },

        editBond: function(slno) {
            var bond = window.BondManager.bondsList.find(function(b) {
                return b.slno == slno;
            });
            if (!bond) return;

            $('#bondSlNo').val(bond.slno);
            $('#bondAssetClass').val(bond.asset_class || 'Debt');
            $('#bondInvestmentName').val(bond.investment_name || '');
            $('#bondFaceValue').val(bond.face_value !== null ? bond.face_value : '');
            $('#bondNseCode').val(bond.nse_code || '');
            $('#bondBseCode').val(bond.bse_code || '');
            // Parse float to string safely
            $('#bondInterestRate').val(bond.interest_rate !== null ? bond.interest_rate : '');
            $('#bondPayoutFrequency').val(bond.payout_frequency || '');
            $('#bondRedemptionDate').val(window.BondManager.parseDateToYmd(bond.redemption_date));
            $('#bondIntAmtPerPayout').val(bond.int_amt_per_payout !== null ? bond.int_amt_per_payout : '');
            
            // Trigger layout updates first
            $('#bondPayoutFrequency').trigger('change');

            // Parse custom day/month formatted string
            var parsedInterestDate = window.BondManager.parseInterestPayStr(bond.interest_pay_date);
            $('#bondInterestPayMonth').val(parsedInterestDate.month);
            
            // Rebuild days dropdown for the parsed month before setting the day select value
            window.BondManager.updateInterestPayDayOptions();
            $('#bondInterestPayDay').val(parsedInterestDate.day);
            window.BondManager.updateInterestPayDateValue();

            $('#bondFormTitle').text('Edit Bond Master');
            $('#bondSubmitBtn').html('<i class="fas fa-sync-alt me-1"></i> Update Bond').removeClass('btn-primary').addClass('btn-warning text-dark');
            $('#bondCancelBtn').show();

            // Smooth scroll to form
            var $formCard = $('#customBondForm').closest('.card');
            if ($formCard.length) {
                $('html, body').animate({
                    scrollTop: $formCard.offset().top - 20
                }, 300);
            }
        },

        resetForm: function() {
            $('#bondSlNo').val('');
            $('#bondAssetClass').val('Debt');
            $('#bondInvestmentName').val('');
            $('#bondFaceValue').val('');
            $('#bondNseCode').val('');
            $('#bondBseCode').val('');
            $('#bondInterestRate').val('');
            $('#bondPayoutFrequency').val('');
            $('#bondRedemptionDate').val('');
            $('#bondIntAmtPerPayout').val('');
            $('#bondInterestPayDay').val('');
            $('#bondInterestPayMonth').val('');
            $('#bondInterestPayDate').val('');
            $('#bondPayoutFrequency').trigger('change');

            $('#bondFormTitle').text('Add Custom Bond');
            $('#bondSubmitBtn').html('<i class="fas fa-plus me-1"></i> Add Bond').removeClass('btn-warning text-dark').addClass('btn-primary');
            $('#bondCancelBtn').hide();
        },

        saveBond: function(button) {
            var $btn = $(button);
            var slno = $('#bondSlNo').val();
            var assetClass = $('#bondAssetClass').val();
            var investmentName = $('#bondInvestmentName').val().trim();
            var faceValue = $('#bondFaceValue').val();
            var nseCode = $('#bondNseCode').val().trim();
            var bseCode = $('#bondBseCode').val().trim();
            var interestRate = $('#bondInterestRate').val();
            var payoutFrequency = $('#bondPayoutFrequency').val();
            var redemptionDate = $('#bondRedemptionDate').val().trim();
            var intAmtPerPayout = $('#bondIntAmtPerPayout').val();
            var interestPayDate = $('#bondInterestPayDate').val().trim();

            if (!investmentName) {
                alert('Bond Name is required.');
                return;
            }

            var payDay = $('#bondInterestPayDay').val();
            var payMonth = $('#bondInterestPayMonth').val();
            if (payDay && payMonth) {
                var maxDays = 31;
                if (['Apr', 'Jun', 'Sep', 'Nov'].indexOf(payMonth) !== -1) {
                    maxDays = 30;
                } else if (payMonth === 'Feb') {
                    maxDays = 29;
                }
                if (parseInt(payDay, 10) > maxDays) {
                    alert('Invalid Interest Pay Date: ' + payMonth + ' cannot have ' + payDay + ' days.');
                    return;
                }
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'bond_master_handler.php',
                type: 'POST',
                data: {
                    action: 'save_bond',
                    slno: slno,
                    asset_class: assetClass,
                    investment_name: investmentName,
                    face_value: faceValue,
                    nse_code: nseCode,
                    bse_code: bseCode,
                    interest_rate: interestRate,
                    payout_frequency: payoutFrequency,
                    redemption_date: redemptionDate,
                    int_amt_per_payout: intAmtPerPayout,
                    interest_pay_date: interestPayDate
                },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        alert(response.message || 'Saved successfully!');
                        window.BondManager.resetForm();
                        window.BondManager.loadBondData();
                    } else {
                        $btn.html(slno ? '<i class="fas fa-sync-alt me-1"></i> Update Bond' : '<i class="fas fa-plus me-1"></i> Add Bond');
                        alert('Error: ' + (response.message || 'Could not save.'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html(slno ? '<i class="fas fa-sync-alt me-1"></i> Update Bond' : '<i class="fas fa-plus me-1"></i> Add Bond');
                    alert('An error occurred while saving.');
                }
            });
        },

        deleteBond: function(slno, name) {
            if (!confirm('Are you sure you want to delete the bond "' + name + '"? This will remove it from the Bond Master list.')) {
                return;
            }

            $.ajax({
                url: 'bond_master_handler.php',
                type: 'POST',
                data: { action: 'delete_bond', slno: slno },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message || 'Deleted successfully!');
                        window.BondManager.loadBondData();
                    } else {
                        alert('Error: ' + (response.message || 'Could not delete.'));
                    }
                },
                error: function() {
                    alert('An error occurred while deleting.');
                }
            });
        },

        updateInterestPayDateValue: function() {
            var day = $('#bondInterestPayDay').val();
            var month = $('#bondInterestPayMonth').val();
            var freq = $('#bondPayoutFrequency').val();
            if (day) {
                if (freq === 'Monthly') {
                    month = 'Jan';
                }
                $('#bondInterestPayDate').val(day + '-' + (month || 'Jan'));
            } else {
                $('#bondInterestPayDate').val('');
            }
        },

        updateInterestPayDayOptions: function() {
            var month = $('#bondInterestPayMonth').val();
            var $daySelect = $('#bondInterestPayDay');
            var selectedDay = $daySelect.val();

            var maxDays = 31;
            if (month) {
                if (['Apr', 'Jun', 'Sep', 'Nov'].indexOf(month) !== -1) {
                    maxDays = 30;
                } else if (month === 'Feb') {
                    maxDays = 29; // Allow 29 to accommodate leap years since there is no year selector
                }
            }

            var html = '<option value="">Day</option>';
            for (var i = 1; i <= maxDays; i++) {
                html += '<option value="' + i + '">' + i + '</option>';
            }
            $daySelect.html(html);

            // Restore selected day, capping it at maxDays if it was higher
            if (selectedDay) {
                var dayNum = parseInt(selectedDay, 10);
                if (dayNum > maxDays) {
                    selectedDay = String(maxDays);
                }
                $daySelect.val(selectedDay);
            }
        },

        parseInterestPayStr: function(value) {
            if (!value) return { day: '', month: '' };
            value = value.trim();

            // Extract the first sequence of digits as the day
            var dayMatch = value.match(/\d+/);
            var day = dayMatch ? dayMatch[0] : '';

            // Extract the month name if present
            var monthMatch = value.match(/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i);
            var month = '';
            if (monthMatch) {
                var m = monthMatch[0];
                month = m.charAt(0).toUpperCase() + m.slice(1).toLowerCase();
            } else {
                // If it is monthly frequency, it defaults to 'Jan'
                var freq = $('#bondPayoutFrequency').val();
                if (freq === 'Monthly') {
                    month = 'Jan';
                }
            }
            return { day: day, month: month };
        },

        formatDisplayDate: function(dateStr) {
            if (!dateStr) return '';
            dateStr = String(dateStr).trim();
            // YYYY-MM-DD -> DD-MM-YYYY
            if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                var parts = dateStr.split('-');
                return parts[2] + '-' + parts[1] + '-' + parts[0];
            }
            // DD-MM-YYYY or DD/MM/YYYY
            if (/^\d{2}[\/\-]\d{2}[\/\-]\d{4}$/.test(dateStr)) {
                var parts = dateStr.split(/[\/\-]/);
                return parts[0] + '-' + parts[1] + '-' + parts[2];
            }
            // YYYY/MM/DD
            if (/^\d{4}[\/]\d{2}[\/]\d{2}$/.test(dateStr)) {
                var parts = dateStr.split('/');
                return parts[2] + '-' + parts[1] + '-' + parts[0];
            }
            return dateStr;
        },

        parseDateToYmd: function(dateStr) {
            if (!dateStr) return '';
            dateStr = String(dateStr).trim();
            
            // If it's already YYYY-MM-DD
            if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                return dateStr;
            }

            // If it's a 4-digit year (e.g. "2026")
            if (/^\d{4}$/.test(dateStr)) {
                var yr = parseInt(dateStr, 10);
                if (yr >= 1900 && yr <= 2100) {
                    return yr + '-01-01';
                }
            }
            
            // Split by slash or dash
            var parts = dateStr.split(/[\/\-]/);
            if (parts.length === 3) {
                var p0 = parts[0].trim();
                var p1 = parts[1].trim();
                var p2 = parts[2].trim();
                
                var day, month, year;
                
                if (p0.length === 4) {
                    // YYYY-MM-DD or YYYY/MM/DD
                    year = p0;
                    month = p1;
                    day = p2;
                } else if (p2.length === 4) {
                    // DD/MM/YYYY or DD-MM-YYYY
                    day = p0;
                    month = p1;
                    year = p2;
                } else if (p2.length === 2) {
                    // MM-DD-YY or DD-MM-YY (convert 2-digit year to 4-digit)
                    month = p0;
                    day = p1;
                    year = p2;
                    var yNum = parseInt(year, 10);
                    if (!isNaN(yNum)) {
                        year = (yNum >= 70 ? '19' : '20') + year;
                    }
                }
                
                if (day && month && year && year !== '0000' && year !== '1900') {
                    return year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                }
            }
            
            // Fallback: If it's a numeric Excel serial date (e.g. 46253, > 10000)
            if (/^\d+$/.test(dateStr)) {
                var days = parseInt(dateStr, 10);
                if (days > 10000) { // Excel serial dates for modern dates are 40000+
                    var excelEpoch = new Date(1899, 11, 30);
                    excelEpoch.setDate(excelEpoch.getDate() + days);
                    var y = excelEpoch.getFullYear();
                    var m = String(excelEpoch.getMonth() + 1).padStart(2, '0');
                    var d = String(excelEpoch.getDate()).padStart(2, '0');
                    return y + '-' + m + '-' + d;
                }
            }
            
            // Fallback: Try standard Date parsing if valid
            if (dateStr !== '0000-00-00' && dateStr !== '1900-01-01') {
                var ts = Date.parse(dateStr);
                if (!isNaN(ts)) {
                    var date = new Date(ts);
                    var y = date.getFullYear();
                    if (y > 1900) {
                        var m = String(date.getMonth() + 1).padStart(2, '0');
                        var d = String(date.getDate()).padStart(2, '0');
                        return y + '-' + m + '-' + d;
                    }
                }
            }
            return '';
        },

        escape: function(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
        }
    };
</script>