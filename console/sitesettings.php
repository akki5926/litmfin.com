<?php
    if(!isset($_SESSION)){ session_start(); }
    if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
        http_response_code(403);
        exit("Unauthorized access.");
    }

require_once('../admin/database.php');
$db = new MYSQLDB();

// Handle AJAX Post
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'updateSettings') {
    header('Content-Type: application/json');
    $registeredAddress = $_POST["registered_address"] ?? '';
    $corporateAddress = $_POST["corporate_address"] ?? '';
    $supportEmail = $_POST["support_email"] ?? '';
    $supportPhone = $_POST["support_phone"] ?? '';
    
    $messages = [];
    $success = true;

    // Handle Logo Upload
    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        $fileType = $_FILES['logo_upload']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $uploadDir = __DIR__ . '/../img/';
            $targetFile = $uploadDir . 'logo.png';
            
            // Overwrite existing logo (force png extension logic in frontend if they upload jpg, but saving as logo.png)
            if (@move_uploaded_file($_FILES['logo_upload']['tmp_name'], $targetFile)) {
                $messages[] = 'Website logo successfully updated.';
            } else {
                $success = false;
                $messages[] = 'Failed to move uploaded logo file (check permissions).';
            }
        } else {
            $success = false;
            $messages[] = 'Invalid logo file format. Please upload PNG or JPG only.';
        }
    } elseif (isset($_FILES['logo_upload']['error']) && $_FILES['logo_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        $success = false;
        $messages[] = 'Error uploading logo file.';
    }

    // Update Company Info
    $return = $db->updateCompanyInfo($registeredAddress, $corporateAddress, $supportEmail, $supportPhone);
    if ($return) {
        $messages[] = 'Company information updated successfully.';
    } else {
        $success = false;
        $messages[] = 'Company information update failed.';
    }
    
    echo json_encode([
        'success' => $success,
        'message' => implode('<br>', $messages)
    ]);
    exit;
}

// Fetch the company information from the database
$companyInfo = $db->getCompanyInfo();
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 text-primary"><i class="fas fa-building me-2"></i>Global Configurations</h5>
    </div>
    <div class="card-body bg-light">
        <form id="siteSettingsForm" enctype="multipart/form-data">
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Text Configurations -->
                    <div class="mb-3">
                        <label for="registered_address" class="form-label">Registered Office Address:</label>
                        <textarea class="form-control" name="registered_address" id="registered_address" rows="3"><?php echo htmlspecialchars($companyInfo['registered_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="corporate_address" class="form-label">Corporate Office Address:</label>
                        <textarea class="form-control" name="corporate_address" id="corporate_address" rows="3"><?php echo htmlspecialchars($companyInfo['corporate_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="support_email" class="form-label">Customer Support Email ID:</label>
                        <input type="email" class="form-control" name="support_email" id="support_email" value="<?php echo htmlspecialchars($companyInfo['support_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="support_phone" class="form-label">Customer Support Phone Number:</label>
                        <input type="tel" class="form-control" name="support_phone" id="support_phone" value="<?php echo htmlspecialchars($companyInfo['support_phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Logo Upload Section -->
                    <div class="card border-0 shadow-sm mt-4 mt-md-0">
                        <div class="card-body text-center">
                            <h6 class="fw-bold mb-3">Website Logo</h6>
                            
                            <div class="mb-3 p-3 bg-white border rounded">
                                <img src="../img/logo.png?v=<?php echo time(); ?>" alt="Current Logo" class="img-fluid" style="max-height: 100px;" id="currentLogoPreview">
                            </div>
                            
                            <div class="mb-3 text-start">
                                <label for="logo_upload" class="form-label small">Upload New Logo (PNG/JPG)</label>
                                <input class="form-control form-control-sm" type="file" id="logo_upload" name="logo_upload" accept=".png,.jpg,.jpeg">
                            </div>
                            <p class="text-muted" style="font-size: 11px;">Recommended size: 250px x 60px. Will be renamed to logo.png automatically.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end mt-4">
                <button type="submit" class="btn btn-primary" id="btnSaveSettings"><i class="fas fa-save me-2"></i>Save Changes</button>
            </div>
        </form>

        <div id="settingsMessage" class="mt-3"></div>
    </div>
</div>

<script>
$(document).ready(function() {
    
    // Live preview for logo upload
    $('#logo_upload').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#currentLogoPreview').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });

    $('#siteSettingsForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#btnSaveSettings');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');
        
        // Use FormData to handle file uploads
        const formData = new FormData(this);
        formData.append('action', 'updateSettings');
        
        $.ajax({
            url: 'sitesettings.php',
            type: 'POST',
            data: formData,
            contentType: false, // Required for FormData
            processData: false, // Required for FormData
            dataType: 'json',
            success: function(response) {
                const msgDiv = $('#settingsMessage');
                if(response.success) {
                    msgDiv.html(`<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                    // Reset the file input so it doesn't upload again next time unless changed
                    $('#logo_upload').val('');
                    
                    // Force refresh sidebar logo if it exists in the DOM
                    const sidebarLogo = $('.sidebar-header img');
                    if(sidebarLogo.length) {
                        sidebarLogo.attr('src', '../img/logo.png?v=' + new Date().getTime());
                    }
                } else {
                    msgDiv.html(`<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-1"></i> ${response.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                }
            },
            error: function() {
                $('#settingsMessage').html(`<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-1"></i> A network error occurred while saving.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Changes');
            }
        });
    });
});
</script>
