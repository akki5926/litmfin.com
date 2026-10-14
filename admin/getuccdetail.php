<?php
require_once('auth_guard.php');

/**
 * getuccdetail.php
 * Loaded as an AJAX fragment into index.php's #main_content div.
 * NO <!DOCTYPE>, NO <html>/<head>/<body>, NO CDN <script> tags —
 * all libraries (jQuery, Bootstrap 5, Font Awesome) are already
 * present in the parent index.php.
 */

if (!isset($_SESSION)) { session_start(); }

if (!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)) {
    echo '<div class="alert alert-danger m-3"><i class="fas fa-lock me-2"></i>Session expired. Please <a href="login.php">log in</a>.</div>';
    exit;
}

require_once('database.php');
$db = new MYSQLDB();

$superUser    = $_SESSION['superuser'] ?? 0;
$isSuperAdmin = ($superUser == 1);

// ── Handle AJAX UCC Deletion Request ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_ucc') {
    if (!$isSuperAdmin) {
        echo json_encode(['success' => false, 'message' => 'Permission denied. Only Super Admin can delete user data.']);
        exit;
    }
    $targetUcc = trim($_POST['ucc'] ?? '');
    if (empty($targetUcc)) {
        echo json_encode(['success' => false, 'message' => 'No UCC provided for deletion.']);
        exit;
    }
    $res = $db->deleteUserByClientCode($targetUcc);
    echo json_encode($res);
    exit;
}

// ── Input sanitisation ────────────────────────────────────────────────────────
$UCC = isset($_REQUEST['UCC']) ? trim($_REQUEST['UCC']) : '';
$ref = isset($_REQUEST['ref']) ? trim($_REQUEST['ref']) : 'user_list.php';

// Whitelist allowed referrer pages
$allowedRefs = ['user_list.php', 'consolidatedPortfolio.php'];
if (!in_array($ref, $allowedRefs)) {
    $ref = 'user_list.php';
}

$refName = ($ref === 'consolidatedPortfolio.php') ? 'Portfolio' : 'User List';

if (empty($UCC)) {
    echo '<div class="alert alert-danger m-3"><i class="fas fa-exclamation-circle me-2"></i>No UCC provided.</div>';
    exit;
}

// ── Database fetch ────────────────────────────────────────────────────────────
$userData = $db->getUserByClientCode($UCC);

if (!$userData) {
    echo '<div class="alert alert-warning m-3"><i class="fas fa-search me-2"></i>No user found for UCC: <strong>' . htmlspecialchars($UCC) . '</strong></div>';
    exit;
}

// ── Field extraction ──────────────────────────────────────────────────────────
$name  = $userData['PRIMARY_HOLDER_NAME']  ?? '';
$dob   = $userData['PRIMARY_HOLDER_DOB']   ?? '';
$pan   = $userData['PRIMARY_HOLDER_PAN']   ?? '';

$pincode     = $userData['PINCODE'] ?? $userData['PRIMARY_HOLDER_PINCODE'] ?? '';
$countryCode = $userData['COUNTRY'] ?? $userData['PRIMARY_HOLDER_COUNTRY'] ?? '';
$country     = $db->getMasterDescription('mtb_country', 'country_code', $countryCode, 'country_name');
$stateCode   = $userData['STATE']   ?? $userData['PRIMARY_HOLDER_STATE']   ?? '';
$state       = $db->getMasterDescription('mtb_state', 'state_code', $stateCode, 'state_name');
$city        = $userData['CITY']    ?? $userData['PRIMARY_HOLDER_CITY']    ?? '';

$address = $userData['ADDRESS_1'] ?? '';
if (!empty($userData['ADDRESS_2'])) { $address .= ', ' . $userData['ADDRESS_2']; }

$mobile = $userData['INDIAN_MOBILE_NO'] ?? '';
$email  = $userData['EMAIL']            ?? '';

// Nominee
$nomineename         = $userData['NOMINEE_1_NAME']         ?? $userData['NOMINEE_NAME']         ?? '';
$nominee_dob         = $userData['NOMINEE_1_DOB']          ?? $userData['NOMINEE_DOB']          ?? '';
$nomineeRelationCode = $userData['NOMINEE_1_RELATIONSHIP'] ?? $userData['NOMINEE_RELATIONSHIP'] ?? '';
$nomineeRelation     = $db->getMasterDescription('mtb_nominee_relation', 'code', $nomineeRelationCode, 'value');
$guardianName        = $userData['GUARDIAN_FIRST_NAME']    ?? $userData['GUARDIAN_NAME']        ?? '';

// Bank
$bankCode      = $userData['BD_BANK_CODE_1']  ?? $userData['BANK_NAME']      ?? '';
$bankname      = $db->getMasterDescription('mtb_billdeskbank', 'BD_BANK_CODE', $bankCode, 'BD_BANK_NAME');
$ifsccode      = $userData['IFSC_CODE_1']     ?? $userData['IFSC_CODE']       ?? '';
$branchname    = $userData['BANK_BRANCH_1']   ?? $userData['BRANCH_NAME']     ?? $userData['BRANCH'] ?? '';
$accountnumber = $userData['ACCOUNT_NO_1']    ?? $userData['ACCOUNT_NUMBER']  ?? '';
$accountTypeCode = $userData['ACCOUNT_TYPE_1'] ?? $userData['ACCOUNT_TYPE']  ?? '';
$accountType   = $db->getMasterDescription('mtb_bank_acc_type', 'code', $accountTypeCode, 'details');
$branchaddress = $userData['BANK_BRANCH_1']   ?? $userData['BRANCH_ADDRESS']  ?? $userData['BRANCH'] ?? '';

// Other
$holdingNatureCode = $userData['HOLDING_NATURE']    ?? $userData['HOLD_NATURE']  ?? '';
$holdingNature     = $db->getMasterDescription('mtb_client_holding', 'code', $holdingNatureCode, 'details');
$taxStatusCode     = $userData['TAX_STATUS']        ?? '';
$taxStatus         = $db->getMasterDescription('mtb_tax_status', 'code', $taxStatusCode, 'tax_status');
$occupationCode    = $userData['OCCUPATION_CODE']   ?? '';
$occupation        = $db->getMasterDescription('mtb_occupation_code', 'code', $occupationCode, 'details');
$createdDate       = $userData['CREATED_AT']        ?? $userData['created_at']  ?? '';
$activationStatus  = $userData['UCC_ACTIVATION']    ?? '0';

$statusMessage = ($activationStatus == '1') ? 'Activated' : 'Pending';
$statusClass   = ($activationStatus == '1') ? 'text-success' : 'text-warning';
$statusIcon    = ($activationStatus == '1') ? 'fa-check-circle' : 'fa-clock';

// ── Helper ────────────────────────────────────────────────────────────────────
function formatDate($value) {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') return '-';
    return date('d-M-Y', strtotime($value));
}

// ── Helper: render a read-only form field with optional code hint ─────────────
function renderField($label, $value, $code = '', $icon = '') {
    $iconHtml = $icon ? '<i class="fas ' . $icon . ' me-1 text-primary" style="font-size:11px"></i>' : '';
    $hint = ($code && $value !== $code && $value !== '') ? '<small class="text-muted fst-italic" style="font-size:11px">(Code: ' . htmlspecialchars($code) . ')</small>' : '';
    echo '
    <div class="col-md-4 mb-3">
        <label class="form-label fw-semibold" style="color:#4e73df;font-size:12px">' . $iconHtml . htmlspecialchars($label) . '</label>
        <input class="form-control form-control-sm" type="text" disabled value="' . htmlspecialchars($value ?: '—') . '" />
        ' . $hint . '
    </div>';
}
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     STYLES  (scoped inside .ucc-detail-wrapper so they don't leak to sidebar)
════════════════════════════════════════════════════════════════════════════════ -->
<style>
    .ucc-detail-wrapper .ucc-page-header {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: white;
        padding: 18px 24px;
        border-radius: 10px;
        margin-bottom: 18px;
    }
    .ucc-detail-wrapper .ucc-page-header h5 { margin: 0; font-weight: 700; font-size: 16px; }
    .ucc-detail-wrapper .ucc-page-header .breadcrumb-trail {
        font-size: 12px;
        opacity: .85;
        margin-top: 4px;
    }
    .ucc-detail-wrapper .ucc-page-header .breadcrumb-trail a {
        color: rgba(255,255,255,.85);
        text-decoration: none;
        cursor: pointer;
    }
    .ucc-detail-wrapper .ucc-page-header .breadcrumb-trail a:hover { color:#fff; text-decoration:underline; }

    .ucc-detail-wrapper .status-bar {
        background: #fff;
        border-radius: 8px;
        padding: 12px 18px;
        margin-bottom: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
        border-left: 4px solid #4e73df;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
    }

    .ucc-detail-wrapper .section-card {
        background: #fff;
        border-radius: 10px;
        margin-bottom: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,.05);
    }
    .ucc-detail-wrapper .section-toggle {
        background: #f8f9fc;
        padding: 12px 18px;
        cursor: pointer;
        border-bottom: 2px solid #e3e6f0;
        font-weight: 600;
        color: #4e73df;
        font-size: 13px;
        user-select: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background .2s;
    }
    .ucc-detail-wrapper .section-toggle:hover { background: #eaecf4; }
    .ucc-detail-wrapper .section-toggle .chevron {
        transition: transform .25s;
        font-size: 11px;
    }
    .ucc-detail-wrapper .section-toggle.collapsed .chevron { transform: rotate(-90deg); }

    .ucc-detail-wrapper .section-body { padding: 16px 18px; }

    .ucc-detail-wrapper .form-control:disabled {
        background-color: #f8f9fc;
        border-color: #e3e6f0;
        color: #5a5c69;
        font-size: 13px;
    }
    .ucc-detail-wrapper .form-label { margin-bottom: 3px; }

    .ucc-detail-wrapper .btn-back-ucc {
        border: 2px solid #e3e6f0;
        background: #fff;
        color: #4e73df;
        border-radius: 50px;
        padding: 8px 24px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: all .2s;
    }
    .ucc-detail-wrapper .btn-back-ucc:hover {
        background: #4e73df;
        color: #fff;
        border-color: #4e73df;
    }
    .ucc-detail-wrapper .back-top-btn {
        position: fixed;
        bottom: 28px;
        right: 28px;
        background: #4e73df;
        color: #fff;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(0,0,0,.2);
        z-index: 1050;
        transition: background .2s, transform .2s;
    }
    .ucc-detail-wrapper .back-top-btn:hover { background:#224abe; transform:translateY(-3px); }
</style>

<!-- ═══════════════════════════════════════════════════════════════════════════
     HTML
════════════════════════════════════════════════════════════════════════════════ -->
<div class="ucc-detail-wrapper" style="padding:16px">

    <!-- Page header -->
    <div class="ucc-page-header d-flex justify-content-between align-items-center">
        <div>
            <h5><i class="fas fa-user-circle me-2"></i>UCC Details &mdash; <?php echo htmlspecialchars($UCC); ?></h5>
            <div class="breadcrumb-trail">
                <a href="#" onclick="uccLoadPrev(); return false;">
                    <i class="fas fa-arrow-left me-1"></i><?php echo htmlspecialchars($refName); ?>
                </a>
                &nbsp;/&nbsp; UCC Details
            </div>
        </div>
        <?php if ($isSuperAdmin): ?>
        <div>
            <button type="button" id="btnOpenDeleteModal" class="btn btn-danger btn-sm shadow-sm px-3 py-2 fw-semibold">
                <i class="fas fa-trash-alt me-1"></i> Delete User
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isSuperAdmin): ?>
    <!-- Deletion Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="deleteConfirmModalLabel" style="font-size:15px; font-weight:600;">
              <i class="fas fa-exclamation-triangle me-2"></i>Confirm UCC Deletion
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4 text-center">
            <i class="fas fa-user-slash text-danger mb-3" style="font-size: 48px;"></i>
            <h6 class="fw-bold mb-2">Are you sure you want to delete this user?</h6>
            <p class="text-muted mb-0" style="font-size: 13px;">
              Deleting UCC <strong class="text-dark"><?php echo htmlspecialchars($UCC); ?></strong> (<?php echo htmlspecialchars($name); ?>) will permanently remove all associated user data, portfolios, transactions, and goals from the database.
            </p>
            <div class="alert alert-warning border-0 mt-3 mb-0 text-start py-2" style="font-size: 12px;">
              <i class="fas fa-info-circle me-1"></i> This action cannot be undone.
            </div>
          </div>
          <div class="modal-footer bg-light border-top-0 d-flex justify-content-between">
            <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>No
            </button>
            <button type="button" id="btnConfirmDeleteUcc" class="btn btn-danger px-4 fw-semibold" data-ucc="<?php echo htmlspecialchars($UCC); ?>">
              <i class="fas fa-check me-1"></i>Yes
            </button>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Status bar -->
    <div class="status-bar">
        <div>
            <strong><i class="fas fa-info-circle me-1" style="color:#4e73df"></i>Account Status:</strong>
            <span class="<?php echo $statusClass; ?> fw-bold ms-2">
                <i class="fas <?php echo $statusIcon; ?> me-1"></i><?php echo $statusMessage; ?>
            </span>
        </div>
        <small class="text-muted">
            <i class="far fa-calendar-alt me-1"></i>Created: <?php echo formatDate($createdDate); ?>
        </small>
    </div>

    <!-- ── PERSONAL DETAILS (open by default) ──────────────────────────────── -->
    <div class="section-card">
        <div class="section-toggle" data-ucc-target="personalDetails">
            <span><i class="fas fa-user me-2"></i>Personal Details</span>
            <i class="fas fa-chevron-down chevron"></i>
        </div>
        <div id="personalDetails" class="section-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold" style="color:#4e73df;font-size:12px">
                        <i class="fas fa-id-card me-1" style="font-size:11px"></i>Full Name
                    </label>
                    <input class="form-control form-control-sm" type="text" disabled value="<?php echo htmlspecialchars($name ?: '—'); ?>" />
                </div>
                <?php renderField('Date of Birth',  formatDate($dob), '', 'fa-birthday-cake'); ?>
                <?php renderField('PAN Number',     $pan,             '', 'fa-fingerprint');   ?>
            </div>
            <div class="row">
                <?php renderField('Pincode', $pincode); ?>
                <?php renderField('Country', $country, $countryCode); ?>
                <?php renderField('State',   $state,   $stateCode);   ?>
                <?php renderField('City',    $city);                  ?>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-semibold" style="color:#4e73df;font-size:12px">
                        <i class="fas fa-map-marker-alt me-1" style="font-size:11px"></i>Address
                    </label>
                    <input class="form-control form-control-sm" type="text" disabled value="<?php echo htmlspecialchars($address ?: '—'); ?>" />
                </div>
            </div>
            <div class="row">
                <?php renderField('Mobile Number', $mobile, '', 'fa-phone');   ?>
                <?php renderField('Email ID',      $email,  '', 'fa-envelope'); ?>
            </div>
        </div>
    </div>

    <!-- ── NOMINEE DETAILS (collapsed) ─────────────────────────────────────── -->
    <div class="section-card">
        <div class="section-toggle collapsed" data-ucc-target="nomineeDetails">
            <span><i class="fas fa-users me-2"></i>Nominee Details</span>
            <i class="fas fa-chevron-down chevron"></i>
        </div>
        <div id="nomineeDetails" class="section-body" style="display:none">
            <div class="row">
                <?php renderField('Nominee Name',     $nomineename,     ''); ?>
                <?php renderField('Nominee DOB',      formatDate($nominee_dob), ''); ?>
                <?php renderField('Nominee Relation', $nomineeRelation, $nomineeRelationCode); ?>
                <?php renderField('Guardian Name',    $guardianName,    ''); ?>
            </div>
        </div>
    </div>

    <!-- ── BANK DETAILS (collapsed) ─────────────────────────────────────────── -->
    <div class="section-card">
        <div class="section-toggle collapsed" data-ucc-target="bankDetails">
            <span><i class="fas fa-university me-2"></i>Bank Details</span>
            <i class="fas fa-chevron-down chevron"></i>
        </div>
        <div id="bankDetails" class="section-body" style="display:none">
            <div class="row">
                <?php renderField('Bank Name',      $bankname,      $bankCode);       ?>
                <?php renderField('IFSC Code',      $ifsccode,      '');              ?>
                <?php renderField('Branch Name',    $branchname,    '');              ?>
            </div>
            <div class="row">
                <?php renderField('Account Number', $accountnumber,  '');             ?>
                <?php renderField('Account Type',   $accountType,    $accountTypeCode); ?>
                <?php renderField('Branch Address', $branchaddress,  '');             ?>
            </div>
        </div>
    </div>

    <!-- ── OTHER DETAILS (collapsed) ─────────────────────────────────────────── -->
    <div class="section-card">
        <div class="section-toggle collapsed" data-ucc-target="otherDetails">
            <span><i class="fas fa-ellipsis-h me-2"></i>Other Details</span>
            <i class="fas fa-chevron-down chevron"></i>
        </div>
        <div id="otherDetails" class="section-body" style="display:none">
            <div class="row">
                <?php renderField('Holding Nature',        $holdingNature, $holdingNatureCode); ?>
                <?php renderField('Tax Status',            $taxStatus,     $taxStatusCode);     ?>
                <?php renderField('Occupation Description',$occupation,    $occupationCode);    ?>
            </div>
        </div>
    </div>

    <!-- Back button -->
    <div class="text-center my-3">
        <button class="btn-back-ucc" onclick="uccLoadPrev(); return false;">
            <i class="fas fa-arrow-left me-2"></i>Back to <?php echo htmlspecialchars($refName); ?>
        </button>
    </div>

    <!-- Back-to-top -->
    <div class="back-top-btn" title="Back to top" onclick="$('#content').scrollTop ? $('#content').scrollTop(0) : window.scrollTo({top:0,behavior:'smooth'})">
        <i class="fas fa-arrow-up"></i>
    </div>
</div><!-- /.ucc-detail-wrapper -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     SCRIPT  — no CDN imports; uses jQuery & Bootstrap already in index.php
════════════════════════════════════════════════════════════════════════════════ -->
<script>
(function ($) {

    // ── Collapsible sections (pure JS, no Bootstrap collapse dependency) ──────
    $(document).on('click', '.ucc-detail-wrapper .section-toggle', function () {
        var target  = '#' + $(this).data('ucc-target');
        var isOpen  = $(target).is(':visible');
        $(target).slideToggle(200);
        $(this).toggleClass('collapsed', !isOpen);
    });

    // ── Back navigation: reload referrer page into #main_content ─────────────
    window.uccLoadPrev = function () {
        var refUrl  = <?php echo json_encode($ref); ?>;
        var refLabel = <?php echo json_encode($refName); ?>;

        $('#page-title').text(refLabel);
        $('#main_content').hide();
        $('#loader-text').text('Loading ' + refLabel + '...');
        $('#loader').show();

        $('#main_content').load(refUrl, function (response, status, xhr) {
            $('#loader').hide();
            if (status === 'error') {
                $('#main_content').html(
                    '<div class="alert alert-danger">Error ' + xhr.status + ': ' + xhr.statusText + '</div>'
                );
            }
            $('#main_content').fadeIn();
        });
    };

    // ── Delete Modal & Ajax Action ──────────────────────────────────────────
    $(document).off('click', '#btnOpenDeleteModal').on('click', '#btnOpenDeleteModal', function() {
        var modalEl = document.getElementById('deleteConfirmModal');
        if (modalEl) {
            var deleteModal = new bootstrap.Modal(modalEl);
            deleteModal.show();
        }
    });

    $(document).off('click', '#btnConfirmDeleteUcc').on('click', '#btnConfirmDeleteUcc', function() {
        var ucc = $(this).data('ucc');
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

        $.post('getuccdetail.php', {
            action: 'delete_ucc',
            ucc: ucc
        }, function(response) {
            try {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                var modalEl = document.getElementById('deleteConfirmModal');
                if (modalEl) {
                    var deleteModal = bootstrap.Modal.getInstance(modalEl);
                    if (deleteModal) {
                        deleteModal.hide();
                    }
                }
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');

                if (res.success) {
                    alert(res.message || 'User data deleted successfully.');
                    uccLoadPrev();
                } else {
                    alert(res.message || 'Failed to delete user.');
                    btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Yes');
                }
            } catch(e) {
                alert('An error occurred while deleting user data.');
                btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i>Yes');
            }
        });
    });

})(jQuery);
</script>