<?php
require_once('auth_guard.php');

// Note: This file is designed to be loaded into index.php via AJAX.
// It assumes session and database connectivity are handled by the parent.
?>

<style>
    /* Scoped styles to prevent sidebar font/spacing changes */
    #upload-mailback-container {
        font-size: 13px !important;
        max-width: 800px;
    }

    #upload-mailback-container h4 {
        font-size: 1.1rem !important;
        font-weight: bold;
        margin-bottom: 15px;
    }

    .upload-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    #upload-result {
        margin-top: 20px;
        font-size: 12px;
    }
</style>

<div id="upload-mailback-container" class="container-fluid mt-2">
    <h4>Upload Mailback (TXT File)</h4>
    
    <div class="upload-card">
        <form id="mailbackForm" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Select RTA:</label>
                    <select name="rta_type" id="rta_type" class="form-select form-select-sm" required>
                        <option value="">-- Select RTA --</option>
                        <option value="CAMS">CAMS</option>
                        <option value="KFintech">KFintech</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Document Type:</label>
                    <select name="doc_type" id="doc_type" class="form-select form-select-sm" required>
                        <option value="">-- First Select RTA --</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-bold">Select TXT File:</label>
                    <input type="file" name="mailback_file" id="mailback_file" class="form-control form-control-sm" accept=".txt" required>
                    <div class="form-text" style="font-size: 10px;">Only .txt files are allowed.</div>
                </div>

                <div class="col-md-12 text-center mt-3">
                    <button type="submit" id="upload-btn" class="btn btn-primary btn-sm px-4">
                        <i class="fas fa-upload me-1"></i> Upload & Process
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div id="upload-result"></div>
</div>

<script>
$(document).ready(function() {
    // Dynamic Dropdown Logic
    const rtaDocs = {
        'CAMS': ['WBR9', 'WBR10', 'WBR49'],
        'KFintech': ['MFSD201']
    };

    $('#rta_type').on('change', function() {
        const rta = $(this).val();
        const $docType = $('#doc_type');
        
        $docType.empty().append('<option value="">-- Select Document --</option>');
        
        if (rta && rtaDocs[rta]) {
            rtaDocs[rta].forEach(doc => {
                $docType.append(`<option value="${doc}">${doc}</option>`);
            });
        }
    });

    // File Upload AJAX Logic
    $('#mailbackForm').on('submit', function(e) {
        e.preventDefault();
        
        const $btn = $('#upload-btn');
        const $result = $('#upload-result');
        const formData = new FormData(this);

        // UI Feedback
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
        $result.html('<div class="alert alert-info py-2">Uploading and reading file... please wait.</div>');

        $.ajax({
            url: 'process_mailback_upload.php', // Your backend processing file
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                // Assuming backend returns a JSON or HTML result
                $result.html(response);
                $btn.prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Upload & Process');
                $('#mailbackForm')[0].reset();
            },
            error: function() {
                $result.html('<div class="alert alert-danger py-2">An error occurred during upload.</div>');
                $btn.prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Upload & Process');
            }
        });
    });
});
</script>