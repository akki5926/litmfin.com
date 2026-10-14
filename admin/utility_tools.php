<?php
if(!isset($_SESSION)) { session_start(); }
?>

<style>
    .tool-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 1px solid #e0e0e0;
    }
    .tool-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        border-color: #667eea;
    }
    .tool-card-disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold text-primary"><i class="fas fa-tools me-2"></i>Utility Tools</h4>
            <p class="text-muted">Select a tool from the options below to analyze and manage your system data.</p>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Tool 1: Asset Class Modifier -->
        <div class="col-md-4">
            <a href="#" class="ajax-link text-decoration-none text-dark" data-url="tool_asset_class_modifier.php">
                <div class="card shadow-sm h-100 tool-card">
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-layer-group fa-3x text-primary mb-3"></i>
                        <h5 class="fw-bold">View and Modify Asset Class Types</h5>
                        <p class="text-muted small mb-0">View and update active fund's asset class mappings</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- Tool 2 -->
        <div class="col-md-4">
            <a href="#" class="ajax-link text-decoration-none text-dark" data-url="tool_manage_navs.php">
                <div class="card shadow-sm h-100 tool-card">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                    <h5 class="fw-bold">View & Manage NAVs</h5>
                    <p class="text-muted small mb-0">View and modify NAVs of all active funds across assets</p>
                </div>
                </div>
            </a>
        </div>

        <!-- Tool 3 -->
       <div class="col-md-4">
            <a href="#" class="ajax-link text-decoration-none text-dark" data-url="tool_mailback_data_check.php">
                <div class="card shadow-sm h-100 tool-card">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-envelope-open-text fa-3x text-secondary mb-3"></i>
                    <h5 class="fw-bold">Mailback Data Check</h5>
                    <p class="text-muted small mb-0">Analyze RTA mailback parsing and verify transaction records</p>
                </div>
                </div>
            </a>
        </div>

        <!-- Tool 4 -->
		<div class="col-md-4">
            <a href="#" class="ajax-link text-decoration-none text-dark" data-url="tool_view_error_logs.php">
                <div class="card shadow-sm h-100 tool-card">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5 class="fw-bold">View Error Logs</h5>
                    <p class="text-muted small mb-0">Check system upload, processing errors, and sync logs</p>
                </div>
                </div>
            </a>
        </div>

        <!-- Tool 5 -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 tool-card tool-card-disabled">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-piggy-bank fa-3x text-info mb-3"></i>
                    <h5 class="fw-bold">View SIF Funds</h5>
                    <p class="text-muted small mb-0">Monitor Special Investment Funds and track their allocations</p>
                </div>
            </div>
        </div>

        <!-- Tool 6 -->
		
        <div class="col-md-4">
            <div class="card shadow-sm h-100 tool-card tool-card-disabled">
                <div class="card-body p-4 text-center">
                    <i class="fas fa-chart-line fa-3x text-warning mb-3"></i>
                    <h5 class="fw-bold">View Bonds/PMS/AIF</h5>
                    <p class="text-muted small mb-0">Manage alternative investments, fixed deposits, and custom portfolio schemas.</p>
                </div>
            </div>
        </div>


    </div>
</div>