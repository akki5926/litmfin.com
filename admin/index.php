<?php
    if(!isset($_SESSION)){ session_start(); }
    try {
        require_once('database.php');
        $db = new MYSQLDB();
        $siteName = $db->getSiteName() ?: 'Admin Panel';
    } catch (\Throwable $e) {
        die("<div style='padding: 20px; background: #ffebee; color: #c62828; font-family: sans-serif; border: 1px solid #ef9a9a; border-radius: 5px;'><strong>Fatal Initialization Error:</strong><br>" . $e->getMessage() . "<br><br><small>In file: " . $e->getFile() . " on line " . $e->getLine() . "</small></div>");
    }

    /**
     * -------------------------------------------------
     * LOGIN PROCESSING LOGIC
     * -------------------------------------------------
     */
if (isset($_POST['admin_user']) && isset($_POST['admin_pass'])) {
    $username = $_POST['admin_user'];
    $password = $_POST['admin_pass'];

    $loginResult = $db->checkAdminUserLogin($username, $password);

    if ($loginResult && count($loginResult) > 0) {
        $_SESSION["loggedin"] = true;
        $_SESSION["user"] = $username;
        $_SESSION["superuser"] = $loginResult[0]['admin_role'] ?? 0;
        $_SESSION["userid"] = $loginResult[0]['ad_id'];
        
        header("location: index.php");
        exit;
    } else {
        $_SESSION["logincheck"] = "fail";
        header("location: login.php");
        exit;
    }
}

    if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
        header("location: login.php");
        exit;
    }
    
    $superUser  = $_SESSION["superuser"] ?? 0;
    $isSubAdmin = ($superUser == 0); // subAdmin: not a super admin
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName ?? ''); ?> Admin | Panel</title>
    
    <!-- Prevent flash of unstyled theme -->
    <script>
        const savedTheme = localStorage.getItem('litmfin_theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* Ensure Font Awesome icons display */
        i[class^="fas "], i[class*=" fas "],
        i[class^="far "], i[class*=" far "],
        i[class^="fab "], i[class*=" fab "] {
            display: inline-block !important;
            font-style: normal !important;
        }
        
        :root {
            --sidebar-width: 240px;
            --sidebar-bg: #ffffff;
            --sidebar-surface: #f8f9fb;
            --sidebar-border: #e8eaef;
            --accent: #C94F1A;
            --accent-glow: rgba(201, 79, 26, 0.12);
            --accent-soft: rgba(201, 79, 26, 0.08);
            --text-primary: #1e2340;
            --text-muted: #6b7a99;
            --text-dim: #adb5c7;
            --topbar-bg: #ffffff;
            --page-bg: #f0f2f9;
            --card-bg: #ffffff;
            --font-main: 'Sora', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            font-family: var(--font-main);
            font-size: 13px;
            background: var(--page-bg);
            margin: 0;
            padding: 0;
            height: 100%;
            color: #1e2340;
        }

        #main_content,
        #main_content *:not(code):not(pre):not(.font-mono):not(i):not(.fa):not(.fas):not(.far):not(.fab):not(.fa-solid) {
            font-family: var(--font-main) !important;
            font-size: inherit;
        }

        #main_content h1 { font-size: 20px !important; }
        #main_content h2 { font-size: 17px !important; }
        #main_content h3 { font-size: 15px !important; }
        #main_content h4 { font-size: 13px !important; }
        #main_content h5 { font-size: 12px !important; }
        #main_content h6 { font-size: 11.5px !important; }
        #main_content p,
        #main_content td,
        #main_content th,
        #main_content label,
        #main_content input,
        #main_content select,
        #main_content textarea,
        #main_content button,
        #main_content .form-control,
        #main_content .btn { 
            font-size: 13px !important; 
            font-family: var(--font-main) !important;
        }
        #main_content small,
        #main_content .small { font-size: 11.5px !important; }

        #main_content code,
        #main_content pre,
        #main_content .font-mono {
            font-family: var(--font-mono) !important;
            font-size: 12px !important;
        }

        /* ─── SIDEBAR ─────────────────────────────────── */
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 9999;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            border-right: 0.5px solid var(--sidebar-border);
            scrollbar-width: thin;
            scrollbar-color: var(--text-dim) transparent;
        }

        #sidebar::-webkit-scrollbar { width: 3px; }
        #sidebar::-webkit-scrollbar-thumb { 
            background: var(--text-dim); 
            border-radius: 4px; 
        }

        /* Logo header */
        .sidebar-header {
            padding: 10px 1px;
            background: var(--sidebar-surface);
            display: flex;
            align-items: center;
            gap: 9px;
            border-bottom: 0.5px solid var(--sidebar-border);
            flex-shrink: 0;
        }

        .sidebar-header .logo-wrap {
            flex-basis: 60%;
        }

        .sidebar-header .logo-wrap img {
            width: 100%;
            height: auto;
        }

        .sidebar-header .brand-text {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-primary);
            letter-spacing: 0.01em;
            line-height: 1.3;
        }

        .sidebar-header .brand-sub {
            font-size: 9px;
            color: var(--text-muted);
            /* font-weight: 400; */
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* Nav list */
        #sidebar ul {
            list-style: none;
            margin: 0;
            padding: 4px 0;
            flex: 1;
        }

        /* Category labels */
        .nav-category {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            padding: 5px 14px 1px;
            margin: 0;
            background: transparent;
            height: auto;
            border-top: none;
        }

        #sidebar ul li {
            margin-bottom: 0;
        }

        /* Nav items */
        #sidebar ul li a {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 3px 10px;
            margin: 1px 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 11.5px;
            font-weight: 400;
            border-left: 2px solid transparent;
            border-radius: 6px;
            white-space: nowrap;
            overflow: hidden;
            cursor: pointer;
            line-height: 1.3;
            transition: background 0.15s, color 0.15s;
        }

        #sidebar ul li a:hover {
            color: var(--text-primary);
            background: #f3f4f6;
            border-left-color: transparent;
        }

        #sidebar ul li a.active {
            color: var(--accent);
            background: var(--accent-soft);
            border-left-color: var(--accent);
            font-weight: 500;
        }

        /* Nav icons */
        #sidebar ul li a .nav-icon {
            width: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
            background: transparent;
            color: var(--text-dim);
        }

        #sidebar ul li a:hover .nav-icon {
            color: var(--text-muted);
            background: transparent;
            box-shadow: none;
        }

        #sidebar ul li a.active .nav-icon {
            color: var(--accent);
            background: transparent;
            box-shadow: none;
        }

        /* Dropdown toggle styles */
        .dropdown-toggle {
            cursor: pointer;
        }
        
        #sidebar .dropdown-toggle::after {
            display: none !important;
        }
        
        .dropdown-toggle .nav-chevron {
            margin-left: auto;
            font-size: 9px;
            opacity: 0.5;
            transition: transform 0.2s;
            flex-shrink: 0;
        }

        .dropdown-toggle.open .nav-chevron {
            transform: rotate(180deg);
        }

        /* Sub-menu */
        .sub-menu {
            background: transparent;
            padding: 0;
            margin-bottom: 0;
            display: none;
        }
        
        .sub-menu.show {
            display: block;
        }

        .sub-menu li a {
            padding: 2px 10px 2px 34px !important;
            font-size: 11px !important;
            color: var(--text-muted) !important;
            border-left: 2px solid transparent !important;
            border-radius: 5px !important;
            margin: 1px 6px !important;
            gap: 8px !important;
        }

        .sub-menu li a::before {
            display: none;
        }

        .sub-menu li a:hover {
            color: var(--text-primary) !important;
            background: #f3f4f6 !important;
            border-left-color: transparent !important;
        }
        
        .sub-menu li a.active {
            color: var(--accent) !important;
            background: var(--accent-soft) !important;
            border-left-color: var(--accent) !important;
            font-weight: 500 !important;
        }

        /* Logout special */
        #sidebar ul li a.text-danger {
            color: #e05252 !important;
        }
        #sidebar ul li a.text-danger:hover {
            background: rgba(224, 82, 82, 0.06) !important;
            border-left-color: transparent !important;
        }
        #sidebar ul li a.text-danger .nav-icon {
            color: #e05252;
        }

        /* ─── SIDEBAR PROTECTION (prevent loaded pages from affecting sidebar) ─── */
        #sidebar,
        #sidebar *:not(i):not(.fa):not(.fas):not(.far):not(.fab):not(.fa-solid) {
            font-family: var(--font-main) !important;
            font-size: inherit !important;
        }

        #sidebar { font-size: 13px !important; }
        #sidebar .sidebar-header { font-size: 13px !important; }
        #sidebar .nav-category { font-size: 9px !important; }
        #sidebar ul li a { font-size: 11.5px !important; }
        #sidebar ul li a .nav-icon { font-size: 10px !important; }
        .sub-menu li a { font-size: 11px !important; }
        .dropdown-toggle .nav-chevron { font-size: 9px !important; }

        /* ─── MAIN CONTENT ─────────────────────────────── */
        #content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top bar */
        .top-bar {
            background: var(--topbar-bg);
            padding: 0 24px;
            height: 52px;
            border-bottom: 1px solid #e5e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 8px rgba(30,35,64,0.05);
        }

        .top-bar .page-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-bar .page-breadcrumb .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: none;
        }

        #page-title {
            font-size: 13.5px;
            font-weight: 600;
            color: #1e2340;
            margin: 0;
            letter-spacing: -0.01em;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 7px;
            background: #f5f6fc;
            border: 1px solid #e5e8f0;
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 500;
            color: #3d4a68;
            cursor: default;
        }

        .user-badge .avatar-sm {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 500;
        }

        /* ─── DASHBOARD HOME ───────────────────────────── */
        #main_content {
            padding: 24px;
            flex: 1;
        }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, #0a0f1e 0%, #1a1f3a 50%, #0f1628 100%);
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -40px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(91,110,245,0.2) 0%, transparent 70%);
            pointer-events: none;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: 40%;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(167,139,250,0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .welcome-banner .welcome-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 6px;
        }

        .welcome-banner h2 {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin: 0 0 6px;
            letter-spacing: -0.02em;
        }

        .welcome-banner p {
            font-size: 12.5px;
            color: rgba(255,255,255,0.45);
            margin: 0;
        }

        .welcome-banner .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(91,110,245,0.15);
            border: 1px solid rgba(91,110,245,0.25);
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 11px;
            color: rgba(255,255,255,0.7);
            margin-top: 14px;
        }

        .status-pill .pulse {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 8px rgba(74,222,128,0.6);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* Section label */
        .section-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8892b0;
            margin-bottom: 12px;
        }

        /* Stat cards */
        .stat-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 20px;
            border: 1px solid #e8ebf4;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: default;
            box-shadow: 0 1px 4px rgba(30,35,64,0.04);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(30,35,64,0.08);
        }

        .stat-card .card-glow {
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            opacity: 0.08;
            transform: translate(20px, -20px);
        }

        .stat-card.c-blue  .card-glow { background: #5b6ef5; }
        .stat-card.c-green .card-glow { background: #4ade80; }
        .stat-card.c-amber .card-glow { background: #fbbf24; }
        .stat-card.c-purple .card-glow { background: #a78bfa; }

        .stat-card .card-icon-wrap {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            margin-bottom: 14px;
        }

        .stat-card.c-blue  .card-icon-wrap { background: rgba(91,110,245,0.1);  color: #5b6ef5; }
        .stat-card.c-green .card-icon-wrap { background: rgba(74,222,128,0.1);  color: #16a34a; }
        .stat-card.c-amber .card-icon-wrap { background: rgba(251,191,36,0.1);  color: #d97706; }
        .stat-card.c-purple .card-icon-wrap { background: rgba(167,139,250,0.1); color: #a78bfa; }

        .stat-card .card-label {
            font-size: 11.5px;
            color: #8892b0;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .stat-card .card-value {
            font-size: 26px;
            font-weight: 700;
            color: #1e2340;
            letter-spacing: -0.03em;
            font-family: var(--font-mono);
            line-height: 1;
        }

        .stat-card .card-bar {
            height: 3px;
            border-radius: 2px;
            margin-top: 14px;
            opacity: 0.3;
        }

        .stat-card.c-blue  .card-bar { background: #5b6ef5; }
        .stat-card.c-green .card-bar { background: #4ade80; }
        .stat-card.c-amber .card-bar { background: #fbbf24; }
        .stat-card.c-purple .card-bar { background: #a78bfa; }

        /* Quick access grid */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 0;
        }

        .quick-btn {
            background: #fff;
            border: 1px solid #e8ebf4;
            border-radius: 12px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            cursor: pointer;
            transition: all 0.18s ease;
            text-decoration: none;
            color: inherit;
        }

        .quick-btn:hover {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
            transform: translateY(-1px);
            text-decoration: none;
            color: inherit;
        }

        .quick-btn .qb-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--accent-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: var(--accent);
        }

        .quick-btn .qb-label {
            font-size: 11.5px;
            font-weight: 500;
            color: #3d4a68;
            line-height: 1.3;
        }

        /* ─── LOADER ──────────────────────────────────── */
        .loader-wrapper {
            display: none;
            text-align: center;
            padding: 40px;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255,255,255,0.97);
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(30,35,64,0.15);
            z-index: 10000;
            min-width: 180px;
        }

        .loader-wrapper .spinner-border {
            width: 2.4rem;
            height: 2.4rem;
            color: var(--accent) !important;
        }

        .loader-wrapper p {
            font-size: 11px;
            color: #8892b0;
            margin: 10px 0 0;
            font-weight: 500;
        }

        .loading-active {
            pointer-events: none;
            opacity: 0.5;
            transition: opacity 0.2s;
        }

        .sidebar-loading { cursor: wait !important; }
        .ajax-link.disabled-link { pointer-events: none; opacity: 0.5; }

        /* ─── STAGGER ANIMATIONS ─────────────────────── */
        .stat-card { animation: fadeUp 0.4s ease both; }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }

        .quick-btn { animation: fadeUp 0.4s ease both; }
        .quick-btn:nth-child(1) { animation-delay: 0.2s; }
        .quick-btn:nth-child(2) { animation-delay: 0.25s; }
        .quick-btn:nth-child(3) { animation-delay: 0.3s; }
        .quick-btn:nth-child(4) { animation-delay: 0.35s; }
        .quick-btn:nth-child(5) { animation-delay: 0.4s; }
        .quick-btn:nth-child(6) { animation-delay: 0.45s; }
        .quick-btn:nth-child(7) { animation-delay: 0.5s; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .welcome-banner { animation: fadeUp 0.35s ease both; }
        
        /* Additional styles for loaded content */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.375rem 0.75rem;
        }
        
        /* ─── DARK THEME OVERRIDES ───────────────────────── */
        [data-bs-theme="dark"] {
            --sidebar-bg: #1a1d24;
            --sidebar-surface: #222732;
            --sidebar-border: #2d3342;
            --text-primary: #e2e8f0;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --topbar-bg: #1a1d24;
            --page-bg: #0f1115;
            --card-bg: #1e222b;
        }
        [data-bs-theme="dark"] body { color: var(--text-primary); }
        [data-bs-theme="dark"] .quick-btn { background: var(--card-bg); border-color: var(--sidebar-border); color: var(--text-primary); }
        [data-bs-theme="dark"] .quick-btn .qb-label { color: var(--text-primary); }
        [data-bs-theme="dark"] .stat-card { background: var(--card-bg); border-color: var(--sidebar-border); }
        [data-bs-theme="dark"] .stat-card .card-value { color: var(--text-primary); }
        [data-bs-theme="dark"] .top-bar { border-bottom-color: var(--sidebar-border); box-shadow: 0 1px 8px rgba(0,0,0,0.3); }
        [data-bs-theme="dark"] #sidebar ul li a:hover { background: rgba(255,255,255,0.05); }
        [data-bs-theme="dark"] .sub-menu li a:hover { background: rgba(255,255,255,0.05) !important; }
        [data-bs-theme="dark"] #page-title { color: var(--text-primary); }
        [data-bs-theme="dark"] .user-badge { background: rgba(255,255,255,0.05); border-color: var(--sidebar-border); color: var(--text-primary); }
        [data-bs-theme="dark"] .loader-wrapper { background: rgba(26, 29, 36, 0.95); box-shadow: 0 8px 40px rgba(0,0,0,0.3); }
        [data-bs-theme="dark"] .dataTables_wrapper { color: var(--text-primary); }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- ═══════════════════════════════════════
         SIDEBAR
    ═══════════════════════════════════════ -->
    <nav id="sidebar">
        <!-- Logo -->
        <div class="sidebar-header">
            <div class="logo-wrap">
                <img src="../img/logo.png" alt="Logo">
            </div>
            <div>
                <!-- <div class="brand-text"><?php echo htmlspecialchars($siteName ?? ''); ?></div> -->
                <div class="brand-sub">Admin Panel</div>
            </div>
        </div>

        <ul class="list-unstyled mb-0">
            <li><a href="#" class="ajax-link active" data-url="index.php">
                <span class="nav-icon"><i class="fas fa-home"></i></span> Dashboard
            </a></li>

            <?php if (!$isSubAdmin): ?>

            <?php if ($superUser == 1 || $superUser == 2): ?>
            <div class="nav-category">Sync Data</div>
                <li><a href="#" class="ajax-link" data-url="getnav-from-amfi.php">
                    <span class="nav-icon"><i class="fas fa-sync"></i></span> Update NAV & Payout
                </a></li>
                <!-- <li><a href="#" class="ajax-link" data-url="getsch-from-Nse.php">
                    <span class="nav-icon"><i class="fas fa-rotate"></i></span> Update SCH
                </a></li> -->
				<li><a href="#" class="ajax-link" data-url="update_nifty_data.php">
                    <span class="nav-icon"><i class="fas fa-sync"></i></span> Update Nifty Data
                </a></li>
                <li><a href="#" class="ajax-link" data-url="upload_mcap_data.php">
                    <span class="nav-icon"><i class="fas fa-chart-pie"></i></span> Update MCAP Data
                </a></li>
            <?php endif; ?>

            <div class="nav-category">Operations</div>
            <li><a href="#" class="ajax-link" data-url="user_list.php">
                <span class="nav-icon"><i class="fas fa-users"></i></span> Customer List
            </a></li>
            <li><a href="#" class="ajax-link" data-url="addinvestment.php">
                <span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Investment
            </a></li>
            <?php if ($superUser == 1): ?>
            <li><a href="#" class="ajax-link" data-url="mailback_upload_form.php">
                <span class="nav-icon"><i class="fas fa-upload"></i></span> Upload Mailback
            </a></li>
            <?php endif; ?>
            <li><a href="#" class="ajax-link" data-url="upload_cas_statement.php">
                <span class="nav-icon"><i class="fas fa-file-invoice"></i></span> Upload CAS Statements
            </a></li>
            <?php if ($superUser == 1 || $superUser == 2): ?>
                <li><a href="#" class="ajax-link" data-url="familysettings.php">
                    <span class="nav-icon"><i class="fas fa-users"></i></span> Family Settings
                </a></li>
            <?php endif; ?>
            <?php else: ?>
            <div class="nav-category">Operations</div>
            <li><a href="#" class="ajax-link" data-url="upload_cas_statement.php">
                <span class="nav-icon"><i class="fas fa-file-invoice"></i></span> Upload CAS Statements
            </a></li>
            <?php endif; /* end !$isSubAdmin for Operations */ ?>

            <div class="nav-category">Reports</div>
            <?php if (!$isSubAdmin): ?>
            <li><a href="#" class="ajax-link" data-url="transaction_history.php">
                <span class="nav-icon"><i class="fas fa-history"></i></span> Trans. History (NSE)
            </a></li>
            <li><a href="#" class="ajax-link" data-url="transaction_report.php">
                <span class="nav-icon"><i class="fas fa-file"></i></span> Trans. Reports (MB)
            </a></li>
            <li><a href="#" class="ajax-link" data-url="sipreportnse.php">
                <span class="nav-icon"><i class="fas fa-chart-bar"></i></span> SIP Reports (NSE)
            </a></li>
			<li><a href="#" class="ajax-link" data-url="clientdetailsreport.php">
				<span class="nav-icon"><i class="fas fa-users"></i></span> Client Details Report
			</a></li>
            <?php endif; ?>
            <?php if ($superUser != 2): ?>
            <li><a href="#" class="ajax-link" data-url="consolidatedPortfolio.php">
                <span class="nav-icon"><i class="fas fa-layer-group"></i></span> Portfolio Reports
            </a></li>
            <li><a href="#" class="ajax-link" data-url="cas_portfolio_list.php">
                <span class="nav-icon"><i class="fas fa-file"></i></span> CAS Portfolio Reports
            </a></li>
            <?php endif; ?>

            <?php if (!$isSubAdmin): ?>
            <?php if ($superUser == 1): ?>
            <div class="nav-category">Strategy &amp; Tools</div>
			<li>
				<a href="#" class="ajax-link" data-url="utility_tools.php">
					<i class="fas fa-tools"></i> Utility Tools
				</a>
			</li>
            <li><a href="#" class="ajax-link" data-url="goal.php">
                <span class="nav-icon"><i class="fas fa-bullseye"></i></span> Goals
            </a></li>
            
            <!-- FIXED: Recommendations Dropdown -->
            <li class="nav-dropdown">
                <a href="#" class="dropdown-toggle" data-dropdown="recSub">
                    <span class="nav-icon"><i class="fas fa-lightbulb"></i></span>
                    Recommendations
                    <i class="fas fa-chevron-down nav-chevron"></i>
                </a>
                <ul class="sub-menu" id="recSub">
                    <li><a href="#" class="ajax-link" data-url="recommendation-list.php">Mutual Funds</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Content Management Section - Single consolidated menu item -->
            <div class="nav-category">Content</div>
            <li><a href="#" class="ajax-link" data-url="content.php">
                <span class="nav-icon"><i class="fas fa-folder-open"></i></span> Content Management
            </a></li>

            <?php if ($superUser == 1 || $superUser == 2): ?>
                <li><a href="#" class="ajax-link" data-url="uploadmasterfile.php">
                    <span class="nav-icon"><i class="fas fa-upload"></i></span> Master Files
                </a></li>
                <li><a href="#" class="ajax-link" data-url="uploadCity.php">
                    <span class="nav-icon"><i class="fas fa-map-pin"></i></span> City Pincode
                </a></li>
                <li><a href="#" class="ajax-link" data-url="display_faq.php">
                    <span class="nav-icon"><i class="fas fa-question"></i></span> Add FAQ
                </a></li>
            <?php endif; ?>
            <?php endif; /* end !$isSubAdmin for Strategy/Content */ ?>

            <?php if ($superUser == 1 || $superUser == 0): ?>
            <div class="nav-category">System</div>
            
            <!-- FIXED: Settings Dropdown -->
            <li class="nav-dropdown">
                <a href="#" class="dropdown-toggle" data-dropdown="setSub">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span>
                    Settings
                    <i class="fas fa-chevron-down nav-chevron"></i>
                </a>
                <ul class="sub-menu" id="setSub">
                    <?php if (!$isSubAdmin): ?>
                    <li><a href="#" class="ajax-link" data-url="dbbackup.php">Backup</a></li>
                    <?php endif; ?>
                    <li><a href="#" class="ajax-link" data-url="change_password.php">Password</a></li>
                    <?php if ($superUser == 1): ?>
                        <li><a href="#" class="ajax-link" data-url="admin_nse_credentials.php">NSE Credentials</a></li>
                        <li><a href="#" class="ajax-link" data-url="adminManagement.php">Admin Management</a></li>
                        <li><a href="#" class="ajax-link" data-url="assignsubadmin.php">Assign SubAdmin</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

        </ul>

        <!-- Sidebar Footer with User Profile -->
        <div style="
            margin-top: auto;
            border-top: 0.5px solid var(--sidebar-border);
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        ">
            <!-- Avatar circle with user initials -->
            <div style="
                width: 26px; height: 26px;
                border-radius: 50%;
                background: var(--accent-soft);
                display: flex; align-items: center; justify-content: center;
                font-size: 10px; font-weight: 500; color: var(--accent);
                flex-shrink: 0;
                font-family: var(--font-main);
            ">
                <?php echo strtoupper(substr($_SESSION["user"] ?? 'A', 0, 2)); ?>
            </div>

            <!-- Name and role -->
            <div style="flex: 1; overflow: hidden;">
                <div style="
                    font-size: 11px; font-weight: 500;
                    color: var(--text-primary);
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
                    font-family: var(--font-main);
                ">
                    <?php echo htmlspecialchars($_SESSION["user"] ?? 'Admin'); ?>
                </div>
                <div style="font-size: 9px; color: var(--text-dim); font-family: var(--font-main);">
                    <?php 
                    if ($superUser == 1) {
                        echo 'Super Admin';
                    } elseif ($superUser == 2) {
                        echo 'Admin';
                    } else {
                        echo 'Sub Admin';
                    }
                    ?>
                </div>
            </div>

            <!-- Logout icon -->
            <a href="logout.php" title="Logout" style="
                color: #e05252;
                font-size: 13px;
                text-decoration: none;
                display: flex; align-items: center;
                padding: 4px;
                border-radius: 5px;
                flex-shrink: 0;
            " onmouseover="this.style.background='rgba(224,82,82,0.08)'"
               onmouseout="this.style.background='transparent'">
                <i class="fas fa-power-off"></i>
            </a>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════
         MAIN CONTENT
    ═══════════════════════════════════════ -->
    <div id="content">
        <!-- Top bar -->
        <div class="top-bar">
            <div class="page-breadcrumb">
                <span class="dot"></span>
                <h6 id="page-title" class="m-0">Dashboard Home</h6>
            </div>
            <div class="top-bar-right">
                <button id="themeToggleBtn" class="btn btn-sm btn-outline-secondary me-2 rounded-circle" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="user-badge">
                    <div class="avatar-sm"><?php echo strtoupper(substr($_SESSION["user"] ?? 'A', 0, 1)); ?></div>
                    <?php echo htmlspecialchars($_SESSION["user"] ?? 'Guest'); ?>
                </div>
            </div>
        </div>

        <div class="container-fluid p-0">
            <div id="main_content">

                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <div class="welcome-label">Overview</div>
                    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION["user"] ?? 'Admin'); ?> 👋</h2>
                    <p><?php echo htmlspecialchars($siteName ?? ''); ?> Admin Panel — manage your data sync, customer goals, and portfolios.</p>
                    <div class="status-pill">
                        <span class="pulse"></span>
                        All systems operational
                    </div>
                </div>

                <!-- Stat Cards -->
                <div class="section-label">Key Metrics</div>
                <div class="row g-3 mb-4">
                    <?php if (!$isSubAdmin): ?>
                    <div class="col-md-3">
                        <div class="stat-card c-blue">
                            <div class="card-glow"></div>
                            <div class="card-icon-wrap"><i class="fas fa-users"></i></div>
                            <div class="card-label">Total Investors</div>
                            <div class="card-value"><?php echo $db->getUserCount(); ?></div>
                            <div class="card-bar"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card c-amber">
                            <div class="card-glow"></div>
                            <div class="card-icon-wrap"><i class="fas fa-chart-pie"></i></div>
                            <div class="card-label">Total Mutual Funds</div>
                            <div class="card-value"><?php echo $db->getMutualFundCount(); ?></div>
                            <div class="card-bar"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card c-green">
                            <div class="card-glow"></div>
                            <div class="card-icon-wrap"><i class="fas fa-star"></i></div>
                            <div class="card-label">Recommended Funds</div>
                            <div class="card-value"><?php echo $db->getRecommendedCount(); ?></div>
                            <div class="card-bar"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card c-purple">
                            <div class="card-glow"></div>
                            <div class="card-icon-wrap"><i class="fas fa-rss"></i></div>
                            <div class="card-label">Total Blogs</div>
                            <div class="card-value"><?php echo $db->getBlogCount(); ?></div>
                            <div class="card-bar"></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="col-md-6">
                        <div class="stat-card c-blue">
                            <div class="card-glow"></div>
                            <div class="card-icon-wrap"><i class="fas fa-layer-group"></i></div>
                            <div class="card-label">Portfolio Reports</div>
                            <div class="card-value"><i class="fas fa-arrow-right" style="font-size:18px;"></i></div>
                            <div class="card-bar"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card c-green">
                            <div class="card-glow"></div>
                            <div class="card-icon-wrap"><i class="fas fa-file"></i></div>
                            <div class="card-label">CAS Portfolio Reports</div>
                            <div class="card-value"><i class="fas fa-arrow-right" style="font-size:18px;"></i></div>
                            <div class="card-bar"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Access -->
                <div class="section-label">Quick Access</div>
                <div class="quick-grid">
                    <?php if (!$isSubAdmin): ?>
                    <a href="#" class="quick-btn ajax-link" data-url="user_list.php">
                        <div class="qb-icon"><i class="fas fa-users"></i></div>
                        <div class="qb-label">Customer List</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($superUser != 2): ?>
                    <a href="#" class="quick-btn ajax-link" data-url="consolidatedPortfolio.php">
                        <div class="qb-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="qb-label">Portfolio Reports</div>
                    </a>
                    <?php endif; ?>
                    <?php if (!$isSubAdmin && $superUser != 2): ?>
                    <a href="#" class="quick-btn ajax-link" data-url="goal.php">
                        <div class="qb-icon"><i class="fas fa-bullseye"></i></div>
                        <div class="qb-label">Goals</div>
                    </a>
                    <?php endif; ?>
                    <?php if (!$isSubAdmin): ?>
                    <a href="#" class="quick-btn ajax-link" data-url="transaction_history.php">
                        <div class="qb-icon"><i class="fas fa-clock-rotate-left"></i></div>
                        <div class="qb-label">Trans. History</div>
                    </a>
                    <?php endif; ?>
                    <a href="#" class="quick-btn ajax-link" data-url="upload_cas_statement.php">
                        <div class="qb-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="qb-label">Upload CAS Statements</div>
                    </a>
                    <?php if (!$isSubAdmin && $superUser != 2): ?>
                    <a href="#" class="quick-btn ajax-link" data-url="recommendation-list.php">
                        <div class="qb-icon"><i class="fas fa-lightbulb"></i></div>
                        <div class="qb-label">Recommendations</div>
                    </a>
                    <?php endif; ?>
                    <?php if (!$isSubAdmin): ?>
					<a href="#" class="quick-btn ajax-link" data-url="clientdetailsreport.php">
						<div class="qb-icon"><i class="fas fa-users-viewfinder"></i></div>
						<div class="qb-label">Client Details Report</div>
					</a>
                    <a href="#" class="quick-btn ajax-link" data-url="content.php">
                        <div class="qb-icon"><i class="fas fa-folder-open"></i></div>
                        <div class="qb-label">Content Management</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($superUser != 2): ?>
                    <a href="#" class="quick-btn ajax-link" data-url="cas_portfolio_list.php">
                        <div class="qb-icon"><i class="fas fa-file"></i></div>
                        <div class="qb-label">CAS Portfolio Reports</div>
                    </a>
                    <?php endif; ?>
                </div>

            </div><!-- /#main_content -->

            <div class="loader-wrapper" id="loader">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading…</p>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $.ajaxSetup({ cache: false });
    let activeRequest = null;
    let isProcessing = false;
    let currentUrl = '';
    
    // Store loaded content
    let loadedContent = {};
    
    let pageReinitCallbacks = {};
    
    // Helper function for pages to register their script reinitialization
    window.registerPageReinit = function(callback) {
        if (currentUrl) {
            pageReinitCallbacks[currentUrl] = callback;
        }
        window.reinitPageScripts = callback;
    };
    
    // Function to disable sidebar links temporarily
    function disableSidebarLinks(disable) {
        if (disable) {
            $('.ajax-link').addClass('disabled-link');
            $('#sidebar').addClass('sidebar-loading');
        } else {
            $('.ajax-link').removeClass('disabled-link');
            $('#sidebar').removeClass('sidebar-loading');
        }
    }
    
    // Function to show/hide loader
    function setLoading(show) {
        if (show) {
            isProcessing = true;
            disableSidebarLinks(true);
            $('#loader').fadeIn('fast');
            $('#main_content').addClass('loading-active');
        } else {
            isProcessing = false;
            disableSidebarLinks(false);
            $('#loader').fadeOut('fast');
            $('#main_content').removeClass('loading-active');
        }
    }
    
    // Function to initialize DataTables in loaded content
    function initDataTablesInContent() {
        if ($.fn.DataTable) {
            $('#main_content').find('table.datatable, table.data-table').each(function() {
                if (!$.fn.DataTable.isDataTable(this)) {
                    try {
                        $(this).DataTable({ 
                            responsive: true, 
                            pageLength: 25, 
                            destroy: true,
                            language: {
                                search: "Search:",
                                lengthMenu: "Show _MENU_ entries",
                                info: "Showing _START_ to _END_ of _TOTAL_ entries"
                            }
                        });
                    } catch(e) {
                        console.warn('DataTable init error:', e);
                    }
                }
            });
        }
    }
    
    // Function to load content
    window.loadContent = function(url, title, forceRefresh = false) { return loadContent(url, title, forceRefresh); };

    function loadContent(url, title, forceRefresh = false) {
        if (isProcessing) return false;
        
        if (!forceRefresh && currentUrl === url) {
            $(document).trigger('sameUrlClicked', [url]);
            return false;
        }
        
        if (activeRequest) { activeRequest.abort(); activeRequest = null; }
        
        setLoading(true);
        
        activeRequest = $.ajax({
            url: url,
            type: 'GET',
            dataType: 'html',
            timeout: 300000,
            success: function(response) {
                window.reinitPageScripts = null; // Clear old callback
                
                loadedContent[url] = response;
                currentUrl = url;
                $('#main_content').html(response);
                $('#page-title').text(title);
                setTimeout(function() {
                    initDataTablesInContent();
                    // Re-initialize any non-DataTable scripts that loaded scripts
                    if (typeof pageReinitCallbacks[url] === 'function') {
                        pageReinitCallbacks[url]();
                    } else if (typeof window.reinitPageScripts === 'function') {
                        pageReinitCallbacks[url] = window.reinitPageScripts;
                        window.reinitPageScripts();
                    }
                }, 150);
                setLoading(false);
            },
            error: function(xhr, status, error) {
                setLoading(false);
                let errorMsg = '';
                if (status === 'timeout') {
                    errorMsg = '<div class="alert alert-warning">The request timed out. The operation may still be processing in the background.</div>';
                } else if (status === 'abort') {
                    errorMsg = '<div class="alert alert-info">Request was cancelled.</div>';
                } else if (xhr.status === 401 || (xhr.responseText && xhr.responseText.indexOf('Session expired') > -1)) {
                    errorMsg = '<div class="alert alert-danger">Session expired. Please <a href="login.php">login again</a>.</div>';
                } else {
                    errorMsg = '<div class="alert alert-danger">Error ' + (xhr.status || 'Unknown') + ': ' + (xhr.statusText || 'Unknown error') + '</div>';
                }
                $('#main_content').html(errorMsg);
                console.error('AJAX Error:', status, error);
            },
            complete: function() { activeRequest = null; }
        });
        
        return true;
    }
    
    // Handle dropdown toggles
    $('.dropdown-toggle').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (isProcessing) return false;
        
        var dropdownId = $(this).data('dropdown');
        var targetMenu = $('#' + dropdownId);
        var isOpen = targetMenu.hasClass('show');
        
        // Close all other dropdowns
        $('.sub-menu').removeClass('show');
        $('.dropdown-toggle').removeClass('open');
        
        if (!isOpen) {
            targetMenu.addClass('show');
            $(this).addClass('open');
        }
    });
    
    // Handle sidebar link clicks
	//18-June-2026: Changed by Akhilesh Kumar
    //$('.ajax-link').on('click', function(e) {
	$(document).on('click', '.ajax-link', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (isProcessing) return false;
        
        const url = $(this).data('url');
        const title = $(this).text().trim();
        const confirmation = $(this).data('confirm');
        
        if (currentUrl === url) {
            $(document).trigger('sameUrlClicked', [url]);
            return false;
        }
        if (confirmation && !confirm(confirmation)) return false;
        // Update active state
        $('#sidebar a').removeClass('active');
		$(this).addClass('active');						   
        const forceRefresh = url === 'admin_nse_credentials.php' || url === 'content.php';
        if (url === 'index.php') {
            location.reload();
            return false;
        }
        loadContent(url, title, forceRefresh);
        return false;
    });
    
    // Handle direct dashboard link
    $('a[href="index.php"]').on('click', function(e) {
        if (!isProcessing) location.reload();
        e.preventDefault();
        return false;
    });
    
    window.clearPageCache = function(url) {
        if (loadedContent[url]) delete loadedContent[url];
    };
    
    window.refreshCurrentPage = function() {
        if (currentUrl) {
            const activeLink = $('.ajax-link.active');
            const title = activeLink.length ? activeLink.text().trim() : '';
            loadContent(currentUrl, title, true);
        }
    };
    
    window.addEventListener('popstate', function() { location.reload(); });
    
	// Theme Toggling Logic
    const themeBtn = document.getElementById('themeToggleBtn');
    const htmlEl = document.documentElement;

    function updateThemeIcon(theme) {
        if (!themeBtn) return;
        if (theme === 'dark') {
            themeBtn.innerHTML = '<i class="fas fa-sun text-warning"></i>';
        } else {
            themeBtn.innerHTML = '<i class="fas fa-moon"></i>';
        }
    }

    updateThemeIcon(htmlEl.getAttribute('data-bs-theme'));

    if (themeBtn) {
        themeBtn.addEventListener('click', function() {
            let currentTheme = htmlEl.getAttribute('data-bs-theme');
            let newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            htmlEl.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('litmfin_theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }
});
</script>
</body>
</html>