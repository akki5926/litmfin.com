<?php
    if(!isset($_SESSION)){ session_start(); }
    if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
        http_response_code(403);
        exit("Unauthorized access.");
    }
    
    // Gather some basic system stats
    $phpVersion = phpversion();
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $loadedExtensions = count(get_loaded_extensions());
?>
<style>
    .dashboard-banner {
        background: linear-gradient(135deg, #1e2340 0%, #2b325a 100%);
        color: white;
        border-radius: 12px;
        padding: 40px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(30, 35, 64, 0.15);
    }
    
    .dashboard-banner::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(201,79,26,0.15) 0%, rgba(201,79,26,0) 70%);
        border-radius: 50%;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
        height: 100%;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }

    .icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 15px;
    }
    
    .icon-primary { background: rgba(201, 79, 26, 0.1); color: #C94F1A; }
    .icon-info { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
    .icon-danger { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

    .quick-link {
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    
    .system-info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .system-info-list li {
        padding: 12px 0;
        border-bottom: 1px solid #f0f2f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .system-info-list li:last-child {
        border-bottom: none;
    }
    .system-info-label {
        color: #6c757d;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .system-info-value {
        font-weight: 600;
        color: #1e2340;
        font-size: 0.95rem;
    }
</style>

<div class="container-fluid py-4">
    <!-- Welcome Banner -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="dashboard-banner">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="fw-bold mb-2">Welcome to the Console</h2>
                        <p class="mb-0 text-white-50" style="font-size: 1.1rem;">Manage your application configuration, run database updates, monitor system health, and view AUM metrics from one centralized location.</p>
                    </div>
                    <div class="col-md-4 text-md-end mt-4 mt-md-0 d-none d-md-block">
                        <i class="fas fa-sliders-h fa-5x" style="color: rgba(255,255,255,0.1);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h5 class="fw-bold mb-4" style="color: #1e2340;">Quick Actions</h5>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <a class="quick-link ajax-link" data-url="sitesettings.php">
                <div class="stat-card">
                    <div class="icon-wrapper icon-primary">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">Global Configurations</h6>
                    <p class="text-muted small mb-0">Update site-wide settings like contact emails, social links, and API keys.</p>
                </div>
            </a>
        </div>
        
        <div class="col-md-4">
            <a class="quick-link ajax-link" data-url="run_update_db.php">
                <div class="stat-card">
                    <div class="icon-wrapper icon-info">
                        <i class="fas fa-database"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">Update Database</h6>
                    <p class="text-muted small mb-0">Run structural database updates and safely recreate stored procedures.</p>
                </div>
            </a>
        </div>
        
        <div class="col-md-4">
            <a class="quick-link ajax-link" data-url="tool_view_error_logs.php">
                <div class="stat-card">
                    <div class="icon-wrapper icon-danger">
                        <i class="fas fa-bug"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-2">System Error Logs</h6>
                    <p class="text-muted small mb-0">Monitor and manage application errors across the API and Admin directories.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- System Information -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h6 class="fw-bold m-0" style="color: #1e2340;"><i class="fas fa-server text-secondary me-2"></i>Server Environment</h6>
                </div>
                <div class="card-body p-4">
                    <ul class="system-info-list">
                        <li>
                            <span class="system-info-label"><i class="fab fa-php text-primary"></i> PHP Version</span>
                            <span class="system-info-value"><?php echo htmlspecialchars($phpVersion); ?></span>
                        </li>
                        <li>
                            <span class="system-info-label"><i class="fas fa-microchip text-success"></i> Server Software</span>
                            <span class="system-info-value"><?php echo htmlspecialchars(explode(' ', $serverSoftware)[0]); ?></span>
                        </li>
                        <li>
                            <span class="system-info-label"><i class="fas fa-puzzle-piece text-warning"></i> Loaded Extensions</span>
                            <span class="system-info-value"><?php echo $loadedExtensions; ?> modules</span>
                        </li>
                        <li>
                            <span class="system-info-label"><i class="far fa-clock text-info"></i> Server Time</span>
                            <span class="system-info-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mt-4 mt-lg-0 d-flex align-items-center justify-content-center opacity-25">
            <img src="../img/logo.png" alt="AutoWealth" style="max-width: 250px; filter: grayscale(100%);" onerror="this.style.display='none'">
        </div>
    </div>
</div>

<script>
    // Initialize standard AJAX links within the dashboard so the quick action cards work
    $('.ajax-link').on('click', function(e) {
        e.preventDefault();
        var url = $(this).data('url');
        
        // Update active state in sidebar to match
        $('#sidebar ul li a').removeClass('active');
        $('#sidebar ul li a[data-url="' + url + '"]').addClass('active');

        $('#main_content').html('<div class="text-center mt-5"><i class="fas fa-spinner fa-spin fa-3x text-secondary"></i><p class="mt-3 text-secondary">Loading...</p></div>');
        
        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                $('#main_content').html(response);
            },
            error: function() {
                $('#main_content').html('<div class="alert alert-danger m-4">Error loading page.</div>');
            }
        });
    });
</script>
