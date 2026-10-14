<?php
require_once('auth_guard.php');

// uploadcity.php
if(!isset($_SESSION)) { session_start(); } 
require_once('database.php');

// Check admin login
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] != true) {
    header("location: login.php");
    exit;
}

$db = new MySQLDB();

// Get states and countries for dropdowns
$states = $db->getAllStates();
$countries = $db->getAllCountries();
?>
<div class="page-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-map-marker-alt me-2"></i>City Pincode Manager</h2>
            </div>
            <a href="index.php" class="btn btn-outline-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<div class="container">
    <!-- Upload Section -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-upload"></i> Bulk Upload
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h5>Click or Drag & Drop File</h5>
                        <p class="text-muted small">Supported formats: CSV, TXT (columns: CITY, STATE_CODE, COUNTRY_CODE, PINCODE)</p>
                        <input type="file" id="cityFile" accept=".csv,.txt">
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" id="downloadTemplate" class="text-decoration-none">
                            <i class="fas fa-download me-1"></i> Download Template (CSV)
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>File Format Tips:</strong><br>
                        • First row must be header<br>
                        • Columns: CITY, STATE_CODE, COUNTRY_CODE, PINCODE<br>
                        • City names will be auto-formatted (capitalized)<br>
                        • State and Country codes should match existing codes in database
                    </div>
                </div>
            </div>
            <div id="uploadStatus" class="mt-3" style="display: none;"></div>
        </div>
    </div>
    
    <!-- Add Manual Entry Section -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-plus-circle"></i> Add New City
        </div>
        <div class="card-body">
            <form id="cityForm">
                <input type="hidden" id="cityId" name="id" value="0">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">City Name *</label>
                        <input type="text" class="form-control" id="cityName" placeholder="e.g., Mumbai" required>
                        <small class="text-muted">First letter of each word will be capitalized</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">State *</label>
                        <select class="form-select" id="stateCode" required>
                            <option value="">Select State</option>
                            <?php foreach($states as $state): ?>
                            <option value="<?php echo htmlspecialchars($state['state_code']); ?>">
                                <?php echo htmlspecialchars($state['state_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Country *</label>
                        <select class="form-select" id="countryCode" required>
                            <option value="">Select Country</option>
                            <?php foreach($countries as $country): ?>
                            <option value="<?php echo htmlspecialchars($country['country_code']); ?>">
                                <?php echo htmlspecialchars($country['country_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Pincode *</label>
                        <input type="text" class="form-control" id="pincode" placeholder="6 digits" maxlength="6" pattern="\d{6}" required>
                        <small class="text-muted">6 digit numeric code</small>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- City List Section -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <div><i class="fas fa-list"></i> City List</div>
            <div class="d-flex gap-2">
                <div class="search-box">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by city, pincode, state...">
                    </div>
                </div>
                <button class="btn btn-outline-secondary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>City Name</th>
                            <th>State</th>
                            <th>Country</th>
                            <th>Pincode</th>
                            <th style="width: 100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cityTableBody">
                        <tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading data...</p></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination-controls">
                <span class="text-muted small" id="paginationInfo"></span>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" id="prevPage" disabled><i class="fas fa-chevron-left"></i> Prev</button>
                    <button class="btn btn-sm btn-outline-secondary" id="nextPage">Next <i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit City</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="mb-3">
                    <label class="form-label">City Name *</label>
                    <input type="text" class="form-control" id="editCityName" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">State *</label>
                    <select class="form-select" id="editStateCode" required>
                        <option value="">Select State</option>
                        <?php foreach($states as $state): ?>
                        <option value="<?php echo htmlspecialchars($state['state_code']); ?>">
                            <?php echo htmlspecialchars($state['state_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Country *</label>
                    <select class="form-select" id="editCountryCode" required>
                        <option value="">Select Country</option>
                        <?php foreach($countries as $country): ?>
                        <option value="<?php echo htmlspecialchars($country['country_code']); ?>">
                            <?php echo htmlspecialchars($country['country_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pincode *</label>
                    <input type="text" class="form-control" id="editPincode" maxlength="6" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveEditBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this city record?</p>
                <p class="text-muted small" id="deleteCityName"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --primary-color: #2c3e50;
        --secondary-color: #3498db;
    }
    
    .page-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, #1a252f 100%);
        color: white;
        padding: 20px 0;
        margin-bottom: 30px;
        border-radius: 0 0 15px 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .page-header h2 {
        margin: 0;
        font-weight: 600;
    }
    
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }
    
    .card-header {
        background: white;
        border-bottom: 2px solid #f0f2f5;
        padding: 15px 20px;
        font-weight: 600;
        border-radius: 12px 12px 0 0 !important;
    }
    
    .card-header i {
        margin-right: 8px;
        color: var(--secondary-color);
    }
    
    .btn-primary {
        background: var(--secondary-color);
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
    }
    
    .btn-primary:hover {
        background: #2980b9;
    }
    
    .upload-area {
        border: 2px dashed #cbd5e0;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        background: #fafbfc;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }
    
    .upload-area:hover {
        border-color: var(--secondary-color);
        background: #f0f7ff;
    }
    
    .upload-area i {
        font-size: 48px;
        color: #a0aec0;
        margin-bottom: 15px;
    }
    
    .badge-pincode {
        background: #e9ecef;
        color: #495057;
        font-family: monospace;
        padding: 4px 8px;
        border-radius: 6px;
    }
    
    .action-buttons .btn {
        padding: 4px 8px;
        margin: 0 2px;
        font-size: 12px;
    }
    
    .search-box {
        max-width: 300px;
    }
    
    .pagination-controls {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
        padding: 15px;
    }
    
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
    }
    
    .form-control, .form-select, .form-control:focus, .form-select:focus {
        border-radius: 8px;
    }
    
    .table th, .table td {
        vertical-align: middle;
    }
    
    #cityFile {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        opacity: 0;
        cursor: pointer;
    }
    
    .upload-area {
        position: relative;
        overflow: hidden;
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        display: none;
        justify-content: center;
        align-items: center;
    }
    
    .loading-spinner {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    // Use a unique namespace for this module
    if (window.CityPincodeManager && window.CityPincodeManager.active) {
        console.log('City manager already active, resetting...');
        // Instead of skipping, we reset the state but keep the manager
        if (window.CityPincodeManager.reset) {
            window.CityPincodeManager.reset();
        }
        return;
    }
    
    // Create namespace
    window.CityPincodeManager = {
        active: true,
        currentPage: 0,
        pageSize: 20,
        totalRecords: 0,
        currentSearch: '',
        deleteId: 0,
        isLoading: false,
        API_URL: 'citypincodeapi.php',
        initialized: false,
        
        reset: function() {
            this.currentPage = 0;
            this.currentSearch = '';
            this.totalRecords = 0;
            this.isLoading = false;
            this.deleteId = 0;
            this.loadCities();
        },
        
        loadCities: function() {
            var self = this;
            if (self.isLoading) return;
            self.isLoading = true;
            
            $('#cityTableBody').html('<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading data...</p></td></tr>');
            
            $.ajax({
                url: self.API_URL,
                method: 'POST',
                data: {
                    action: 'list_cities',
                    search: self.currentSearch,
                    limit: self.pageSize,
                    offset: self.currentPage * self.pageSize,
                    _t: new Date().getTime()
                },
                dataType: 'json',
                timeout: 30000,
                success: function(res) {
                    self.isLoading = false;
                    if(res.success) {
                        self.totalRecords = res.total || 0;
                        self.renderCityTable(res.data || []);
                        var start = self.currentPage * self.pageSize + 1;
                        var end = Math.min((self.currentPage + 1) * self.pageSize, self.totalRecords);
                        $('#paginationInfo').text(self.totalRecords > 0 ? 'Showing ' + start + ' to ' + end + ' of ' + self.totalRecords : 'No entries');
                        $('#prevPage').prop('disabled', self.currentPage === 0);
                        $('#nextPage').prop('disabled', (self.currentPage + 1) * self.pageSize >= self.totalRecords);
                    } else {
                        $('#cityTableBody').html('<tr><td colspan="5" class="text-center text-danger py-4">' + (res.message || 'Failed to load data') + '</td></tr>');
                    }
                },
                error: function() {
                    self.isLoading = false;
                    $('#cityTableBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Network error. Please try again.</td></tr>');
                }
            });
        },
        
        renderCityTable: function(cities) {
            if(!cities || !cities.length) {
                $('#cityTableBody').html('<tr><td colspan="5" class="text-center text-muted py-4">No records found</td></tr>');
                return;
            }
            
            var html = '';
            for(var i = 0; i < cities.length; i++) {
                var c = cities[i];
                html += '<tr>' +
                    '<td><strong>' + this.escapeHtml(c.city_name) + '</strong></td>' +
                    '<td><span class="badge bg-secondary">' + this.escapeHtml(c.state_name || c.state_code) + '</span></td>' +
                    '<td><span class="badge bg-info">' + this.escapeHtml(c.country_name || c.country_code) + '</span></td>' +
                    '<td><span class="badge-pincode">' + c.pincode + '</span></td>' +
                    '<td class="action-buttons">' +
                        '<button class="btn btn-sm btn-outline-primary" onclick="window.CityPincodeManager.editCity(' + c.id + ')"><i class="fas fa-edit"></i></button>' +
                        '<button class="btn btn-sm btn-outline-danger" onclick="window.CityPincodeManager.confirmDelete(' + c.id + ', \'' + this.escapeHtml(c.city_name) + '\')"><i class="fas fa-trash-alt"></i></button>' +
                    '</td>' +
                '</tr>';
            }
            $('#cityTableBody').html(html);
        },
        
        editCity: function(id) {
            var self = this;
            if (self.isLoading) return;
            self.isLoading = true;
            $('#loadingOverlay').fadeIn(200);
            
            $.ajax({
                url: self.API_URL,
                method: 'POST',
                data: { action: 'get_city', id: id, _t: new Date().getTime() },
                dataType: 'json',
                success: function(res) {
                    $('#loadingOverlay').fadeOut(200);
                    self.isLoading = false;
                    if(res.success && res.data) {
                        $('#editId').val(res.data.id);
                        $('#editCityName').val(res.data.city_name);
                        $('#editStateCode').val(res.data.state_code);
                        $('#editCountryCode').val(res.data.country_code);
                        $('#editPincode').val(res.data.pincode);
                        new bootstrap.Modal(document.getElementById('editModal')).show();
                    } else {
                        self.showToast(res.message || 'City not found', 'error');
                    }
                },
                error: function() {
                    $('#loadingOverlay').fadeOut(200);
                    self.isLoading = false;
                    self.showToast('Failed to load city data', 'error');
                }
            });
        },
        
        confirmDelete: function(id, name) {
            this.deleteId = id;
            $('#deleteCityName').text('City: ' + name);
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        },
        
        showToast: function(msg, type) {
            if (this.toastTimeout) clearTimeout(this.toastTimeout);
            var toast = $('#toastNotification');
            toast.html('<div class="alert alert-' + (type === 'error' ? 'danger' : 'success') + ' mb-0 shadow alert-dismissible fade show" role="alert">' +
                '<i class="fas ' + (type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle') + ' me-2"></i>' +
                msg +
                '<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>' +
            '</div>').fadeIn();
            this.toastTimeout = setTimeout(function() { toast.fadeOut(); }, 4000);
        },
        
        escapeHtml: function(t) {
            if(!t) return '';
            return $('<div>').text(t).html();
        },
        
        saveCity: function() {
            var self = this;
            var id = $('#cityId').val();
            var cityName = $('#cityName').val().trim();
            var stateCode = $('#stateCode').val();
            var countryCode = $('#countryCode').val();
            var pincode = $('#pincode').val().trim();
            
            if(!cityName || !stateCode || !countryCode || !pincode) {
                self.showToast('All fields are required', 'error');
                return;
            }
            if(!/^\d{6}$/.test(pincode)) {
                self.showToast('Pincode must be 6 digits', 'error');
                return;
            }
            
            $('#loadingOverlay').fadeIn(200);
            $.ajax({
                url: self.API_URL,
                method: 'POST',
                data: {
                    action: 'save_city',
                    id: id,
                    city_name: cityName,
                    state_code: stateCode,
                    country_code: countryCode,
                    pincode: pincode,
                    _t: new Date().getTime()
                },
                dataType: 'json',
                success: function(res) {
                    $('#loadingOverlay').fadeOut(200);
                    if(res.success) {
                        self.showToast(res.message, 'success');
                        $('#cityForm')[0].reset();
                        $('#cityId').val('0');
                        self.currentPage = 0;
                        self.currentSearch = '';
                        $('#searchInput').val('');
                        self.loadCities();
                    } else {
                        self.showToast(res.message, 'error');
                    }
                },
                error: function() {
                    $('#loadingOverlay').fadeOut(200);
                    self.showToast('Failed to save city', 'error');
                }
            });
        },
        
        updateCity: function() {
            var self = this;
            var id = $('#editId').val();
            var cityName = $('#editCityName').val().trim();
            var stateCode = $('#editStateCode').val();
            var countryCode = $('#editCountryCode').val();
            var pincode = $('#editPincode').val().trim();
            
            if(!cityName || !stateCode || !countryCode || !pincode) {
                self.showToast('All fields required', 'error');
                return;
            }
            if(!/^\d{6}$/.test(pincode)) {
                self.showToast('Pincode must be 6 digits', 'error');
                return;
            }
            
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            $('#loadingOverlay').fadeIn(200);
            $.ajax({
                url: self.API_URL,
                method: 'POST',
                data: {
                    action: 'save_city',
                    id: id,
                    city_name: cityName,
                    state_code: stateCode,
                    country_code: countryCode,
                    pincode: pincode,
                    _t: new Date().getTime()
                },
                dataType: 'json',
                success: function(res) {
                    $('#loadingOverlay').fadeOut(200);
                    if(res.success) {
                        self.showToast(res.message, 'success');
                        self.loadCities();
                    } else {
                        self.showToast(res.message, 'error');
                    }
                },
                error: function() {
                    $('#loadingOverlay').fadeOut(200);
                    self.showToast('Failed to update city', 'error');
                }
            });
        },
        
        deleteCity: function() {
            var self = this;
            if(self.deleteId) {
                bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                $('#loadingOverlay').fadeIn(200);
                $.ajax({
                    url: self.API_URL,
                    method: 'POST',
                    data: {
                        action: 'delete_city',
                        id: self.deleteId,
                        _t: new Date().getTime()
                    },
                    dataType: 'json',
                    success: function(res) {
                        $('#loadingOverlay').fadeOut(200);
                        if(res.success) {
                            self.showToast(res.message, 'success');
                            if(self.currentPage > 0 && self.totalRecords - 1 <= self.currentPage * self.pageSize) {
                                self.currentPage--;
                                if(self.currentPage < 0) self.currentPage = 0;
                            }
                            self.loadCities();
                        } else {
                            self.showToast(res.message, 'error');
                        }
                        self.deleteId = 0;
                    },
                    error: function() {
                        $('#loadingOverlay').fadeOut(200);
                        self.showToast('Failed to delete city', 'error');
                        self.deleteId = 0;
                    }
                });
            }
        },
        
        uploadFile: function(file) {
            var self = this;
            var fileExt = file.name.split('.').pop().toLowerCase();
            if(!['csv', 'txt'].includes(fileExt)) {
                self.showToast('Only CSV or TXT files are allowed', 'error');
                $('#cityFile').val('');
                return;
            }
            if(file.size > 10 * 1024 * 1024) {
                self.showToast('File size should not exceed 10MB', 'error');
                $('#cityFile').val('');
                return;
            }
            
            var formData = new FormData();
            formData.append('city_file', file);
            formData.append('action', 'upload_cities');
            formData.append('_t', new Date().getTime());
            
            $('#uploadStatus').hide();
            $('#loadingOverlay').fadeIn(200);
            
            $.ajax({
                url: self.API_URL,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                timeout: 60000,
                success: function(res) {
                    $('#loadingOverlay').fadeOut(200);
                    var statusDiv = $('#uploadStatus');
                    if(res.success) {
                        statusDiv.removeClass('alert-danger').addClass('alert alert-success').html('<i class="fas fa-check-circle me-2"></i>' + res.message).show();
                        self.currentPage = 0;
                        self.currentSearch = '';
                        $('#searchInput').val('');
                        self.loadCities();
                        self.showToast('File uploaded successfully!', 'success');
                    } else {
                        statusDiv.removeClass('alert-success').addClass('alert alert-danger').html('<i class="fas fa-exclamation-circle me-2"></i>' + res.message).show();
                        self.showToast(res.message, 'error');
                    }
                    setTimeout(function() { statusDiv.fadeOut(); }, 5000);
                    $('#cityFile').val('');
                },
                error: function(xhr, status, error) {
                    $('#loadingOverlay').fadeOut(200);
                    var errMsg = 'Upload failed';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errMsg = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        errMsg = 'Upload failed: ' + xhr.responseText.substring(0, 100);
                    }
                    self.showToast(errMsg, 'error');
                    $('#cityFile').val('');
                }
            });
        }
    };
    
    // Expose functions to window
    window.CityPincodeManager.editCity = window.CityPincodeManager.editCity.bind(window.CityPincodeManager);
    window.CityPincodeManager.confirmDelete = window.CityPincodeManager.confirmDelete.bind(window.CityPincodeManager);
    
    // Initialize when document is ready
    $(document).ready(function() {
        var manager = window.CityPincodeManager;
        
        // Add necessary elements if they don't exist
        if ($('#toastNotification').length === 0) {
            $('body').append('<div class="toast-notification" id="toastNotification" style="display: none;"></div>');
        }
        
        if ($('#loadingOverlay').length === 0) {
            $('body').append('<div class="loading-overlay" id="loadingOverlay" style="display: none;"><div class="loading-spinner"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 mb-0">Processing...</p></div></div>');
        }
        
        // Load data
        setTimeout(function() { manager.loadCities(); }, 100);
        
        // Bind events
        $('#cityForm').off('submit').on('submit', function(e) { e.preventDefault(); manager.saveCity(); });
        $('#saveEditBtn').off('click').on('click', function() { manager.updateCity(); });
        $('#confirmDeleteBtn').off('click').on('click', function() { manager.deleteCity(); });
        
        var searchTimeout;
        $('#searchInput').off('keyup').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                manager.currentSearch = $('#searchInput').val();
                manager.currentPage = 0;
                manager.loadCities();
            }, 500);
        });
        
        $('#refreshBtn').off('click').on('click', function() {
            if(!manager.isLoading) {
                manager.currentSearch = '';
                $('#searchInput').val('');
                manager.currentPage = 0;
                manager.loadCities();
            }
        });
        
        $('#prevPage').off('click').on('click', function() {
            if(manager.currentPage > 0 && !manager.isLoading) {
                manager.currentPage--;
                manager.loadCities();
            }
        });
        
        $('#nextPage').off('click').on('click', function() {
            if((manager.currentPage + 1) * manager.pageSize < manager.totalRecords && !manager.isLoading) {
                manager.currentPage++;
                manager.loadCities();
            }
        });
        
        $('#cityFile').off('change').on('change', function(e) {
            if(this.files && this.files.length) {
                manager.uploadFile(this.files[0]);
            }
        });
        
        $('#uploadArea').off('click').on('click', function(e) {
            e.stopPropagation();
            $('#cityFile').click();
        });
        
        $('#uploadArea').off('dragover').on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('border-primary bg-light');
        });
        
        $('#uploadArea').off('dragleave').on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('border-primary bg-light');
        });
        
        $('#uploadArea').off('drop').on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('border-primary bg-light');
            if(e.originalEvent.dataTransfer.files.length) {
                $('#cityFile')[0].files = e.originalEvent.dataTransfer.files;
                $('#cityFile').trigger('change');
            }
        });
        
        $('#downloadTemplate').off('click').on('click', function(e) {
            e.preventDefault();
            var csv = 'CITY,STATE_CODE,COUNTRY_CODE,PINCODE\nMumbai,MH,IND,400001\nDelhi,DL,IND,110001\nBangalore,KA,IND,560001';
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.href = url;
            link.setAttribute('download', 'city_pincode_template.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
        
        console.log('City Pincode Manager initialized successfully');
    });
})();
</script>