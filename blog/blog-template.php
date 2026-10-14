<?php
// blog/blog-template.php

require_once '../api/db/mfddb.php';
$db = new MfdDb();

// Define base URL for Next.js application
$baseUrl = 'http://localhost:3000'; // Change this to your actual Next.js URL

// Get company information from database config
$companyConfig = $db->getCompanyConfig();
$siteName = $companyConfig['name'] ?? 'LitmFin';
$siteTagline = $companyConfig['tagline'] ?? 'Smart Investments for Smart People';
$siteLogo = $companyConfig['logo'] ?? '';

if (isset($id) && !empty($id)) {
    $blog = $db->getBlogById($id);
} else {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $url = preg_replace('/\.php$/', '', $requestPath);
    $blog = $db->getBlogPostByUrl($url);
}

if (!$blog) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Not Found | ' . $siteName . '</title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:"DM Sans",sans-serif;background:#f5f4f0;display:flex;align-items:center;justify-content:center;min-height:100vh}
            .wrap{text-align:center;padding:2rem}
            .num{font-family:"DM Serif Display",serif;font-size:7rem;color:#1c3557;line-height:1}
            p{margin:1rem 0 2rem;color:#64748b}
            a{background:#1c3557;color:#fff;padding:.75rem 2rem;border-radius:4px;text-decoration:none;font-size:.9rem}
        </style></head><body>
        <div class="wrap"><div class="num">404</div>
        <p>The article you\'re looking for could not be found.</p>
        <a href="' . $baseUrl . '/blog">&#8592; Back to Blogs</a></div>
    </body></html>';
    exit;
}

// Topic icon map
$topicIcons = [
    "Fund Education"      => "fas fa-graduation-cap",
    "Goal Planning"       => "fas fa-bullseye",
    "Tax Planning"        => "fas fa-file-invoice-dollar",
    "Investment Strategy" => "fas fa-chart-line",
    "Advanced Learning"   => "fas fa-brain",
    "Advanced Funds"      => "fas fa-layer-group",
    "Retirement"          => "fas fa-umbrella",
    "Fund Comparison"     => "fas fa-scale-balanced",
    "Beginner Guides"     => "fas fa-book-open",
    "Thematic Funds"      => "fas fa-leaf"
];
$topicIcon = $topicIcons[$blog['topic']] ?? "fas fa-newspaper";

// Only show image if file physically exists on disk
$hasImage = !empty($blog['image']) && file_exists('../uploads/blog/' . $blog['image']);
$imageSrc = $hasImage ? htmlspecialchars('../uploads/blog/' . $blog['image']) : null;

// Split description into paragraphs
$paragraphs = array_filter(array_map('trim', explode("\n\n", $blog['description'])));

// Latest blogs sorted by date, excluding current post (show only 3)
$latestBlogs = $db->getLatestBlogs(3, $blog['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($blog['title']); ?> | <?php echo $siteName; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($blog['description'], 0, 160)); ?>">
    <meta property="og:title"       content="<?php echo htmlspecialchars($blog['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr($blog['description'], 0, 160)); ?>">
    <meta property="og:type"        content="article">
    <?php if ($hasImage): ?><meta property="og:image" content="<?php echo $imageSrc; ?>"><?php endif; ?>
    <meta property="article:published_time" content="<?php echo $blog['date']; ?>">
    <meta property="article:tag"    content="<?php echo htmlspecialchars($blog['topic']); ?>">

    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ── Tokens ──────────────────────────────────────── */
        :root {
            --navy:    #1c3557;
            --navy-d:  #142640;
            --gold:    #c49a3c;
            --ink:     #111827;
            --body-c:  #374151;
            --muted:   #6b7280;
            --border:  #e5e7eb;
            --bg:      #f8f7f4;
            --paper:   #ffffff;
            --gray-50: #f9fafb;
            --gray-800:#1f2937;
            --gray-900:#111827;
            --gray-950:#030712;
            --radius:  6px;
        }
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--ink); line-height: 1.7; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }

        /* ══════════════════════════════════════════════════
           HEADER
        ══════════════════════════════════════════════════ */
        .site-header {
            position: sticky; top: 0; z-index: 100;
            background: var(--paper);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 1px 8px rgba(0,0,0,.06);
        }
        .header-inner {
            max-width: 1280px; margin: 0 auto; padding: 0 1.5rem;
            height: 64px; display: flex; align-items: center; justify-content: space-between; position: relative;
        }
        .logo-link {
            display: flex;
            align-items: center;
            gap: .65rem;
            font-family: 'DM Serif Display', serif;
            font-size: 1.35rem;
            color: var(--navy);
            flex-shrink: 0;
            text-decoration: none;
        }
        .logo-circle {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), #2e5a8e);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-circle i { color: #fff; font-size: .9rem; }
        /* Desktop nav */
        .desktop-nav {
            display: flex;
            align-items: center;
            gap: .2rem;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        .nav-link {
            padding: .5rem .9rem;
            font-size: .88rem;
            font-weight: 500;
            color: var(--body-c);
            border-radius: var(--radius);
            transition: all .2s;
            white-space: nowrap;
            text-decoration: none;
        }
        .nav-link:hover { color: var(--navy); background: #eef2ff; }
        .nav-dropdown { position: relative; }
        .nav-dropdown-btn {
            padding: .5rem .9rem;
            font-size: .88rem;
            font-weight: 500;
            color: var(--body-c);
            border-radius: var(--radius);
            transition: all .2s;
            background: none;
            border: none;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }
        .nav-dropdown-btn:hover { color: var(--navy); background: #eef2ff; }
        .nav-dropdown-btn i { font-size: .68rem; transition: transform .2s; }
        .nav-dropdown:hover .nav-dropdown-btn i { transform: rotate(180deg); }
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: var(--paper);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 8px 28px rgba(0,0,0,.1);
            padding: .5rem;
            min-width: 156px;
            opacity: 0;
            visibility: hidden;
            transition: all .2s;
            pointer-events: none;
        }
        .nav-dropdown:hover .dropdown-menu { opacity: 1; visibility: visible; pointer-events: all; }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: .55rem;
            padding: .6rem .9rem;
            font-size: .87rem;
            font-weight: 500;
            color: var(--body-c);
            border-radius: 5px;
            transition: all .2s;
            text-decoration: none;
        }
        .dropdown-item:hover { color: var(--navy); background: #eef2ff; }
        /* Login */
        .login-btn {
            padding: .5rem 1.3rem;
            border-radius: var(--radius);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            font-size: .87rem;
            font-weight: 600;
            flex-shrink: 0;
            transition: opacity .2s;
            text-decoration: none;
        }
        .login-btn:hover { opacity: .9; }
        /* Hamburger */
        .hamburger { display: none; background: none; border: none; padding: .5rem; cursor: pointer; color: var(--ink); }
        /* Mobile menu */
        .mobile-menu { display: none; background: var(--paper); border-top: 1px solid var(--border); padding: 1rem 1.5rem 1.5rem; }
        .mobile-menu.open { display: block; }
        .mobile-nav-link {
            display: block;
            padding: .85rem 1rem;
            font-size: .9rem;
            font-weight: 500;
            color: var(--body-c);
            border-left: 3px solid transparent;
            border-radius: 0 var(--radius) var(--radius) 0;
            transition: all .2s;
            text-decoration: none;
        }
        .mobile-nav-link:hover { color: var(--navy); border-left-color: var(--navy); background: #eef2ff; }
        .mobile-section-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            padding: .6rem 1rem;
            margin-top: .5rem;
        }
        .mobile-login { margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 1rem; }
        .mobile-login a {
            display: block;
            text-align: center;
            padding: .75rem;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            font-weight: 600;
            border-radius: var(--radius);
            text-decoration: none;
        }
        @media (max-width: 1023px) { .desktop-nav { display: none; } .login-btn { display: none; } .hamburger { display: block; } }

        /* ══════════════════════════════════════════════════
           PAGE LAYOUT
        ══════════════════════════════════════════════════ */
        .page-wrap {
            max-width: 1280px; margin: 0 auto; padding: 2.5rem 1.5rem 5rem;
            display: grid; grid-template-columns: 1fr 330px; gap: 2.25rem; align-items: start;
        }
        @media (max-width: 1023px) { .page-wrap { grid-template-columns: 1fr; } }
        @media (max-width: 640px)  { .page-wrap { padding: 1.5rem 1rem 4rem; } }

        /* ARTICLE STYLES */
        .article-card { background: var(--paper); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 5px rgba(0,0,0,.07); }
        .hero-img { width: 100%; aspect-ratio: 16/7; object-fit: cover; display: block; }
        .article-body-wrap { padding: 2.25rem 2.5rem 2.5rem; }
        @media (max-width: 640px) { .article-body-wrap { padding: 1.5rem 1.25rem; } }

        .topic-badge {
            display: inline-flex; align-items: center; gap: .4rem;
            background: var(--navy); color: #fff;
            font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            padding: .35rem .9rem; border-radius: 3px; margin-bottom: 1.1rem;
        }
        .topic-badge i { font-size: .68rem; opacity: .8; }

        .article-title {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            line-height: 1.22; color: var(--ink); margin-bottom: 1.1rem; letter-spacing: -.015em;
        }

        .article-meta {
            display: flex; flex-wrap: wrap; gap: 1.25rem;
            font-size: .83rem; color: var(--muted);
            padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); margin-bottom: 1.85rem;
        }
        .article-meta span { display: inline-flex; align-items: center; gap: .4rem; }
        .article-meta i { color: var(--gold); }

        .article-content { font-size: 1.05rem; color: var(--body-c); line-height: 1.9; }
        .article-content p { margin-bottom: 1.35rem; }
        .article-content p:last-child { margin-bottom: 0; }
        .article-content p:first-child::first-letter {
            font-family: 'DM Serif Display', serif;
            font-size: 3.4rem;
            line-height: .82;
            float: left;
            margin-right: .12em;
            margin-top: .08em;
            color: var(--navy);
        }

        .art-divider {
            display: flex; align-items: center; gap: .9rem;
            margin: 2rem 0 1.85rem; color: var(--gold); font-size: .75rem; letter-spacing: .12em;
        }
        .art-divider::before, .art-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .share-bar { display: flex; align-items: center; flex-wrap: wrap; gap: .6rem; }
        .share-label { font-size: .78rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
        .share-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .5rem 1.1rem; border: 1px solid var(--border); border-radius: 4px;
            background: var(--paper); font-family: inherit; font-size: .82rem; font-weight: 500;
            color: var(--body-c); cursor: pointer; transition: all .2s;
        }
        .share-btn:hover { border-color: var(--navy); color: var(--navy); background: #eef2ff; }
        .share-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
        .share-btn.copied { border-color: #16a34a; color: #16a34a; background: #f0fdf4; }

        /* SIDEBAR STYLES */
        .sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
        .sidebar-card { background: var(--paper); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 5px rgba(0,0,0,.07); }
        .sidebar-heading {
            font-family: 'DM Serif Display', serif;
            font-size: 1.1rem;
            color: var(--navy);
            padding: 1.1rem 1.3rem .9rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .sidebar-heading i { color: var(--gold); font-size: .95rem; }

        .latest-item {
            display: flex; gap: .85rem; padding: .95rem 1.3rem;
            border-bottom: 1px solid var(--border); transition: background .18s;
            text-decoration: none;
        }
        .latest-item:last-child { border-bottom: none; }
        .latest-item:hover { background: var(--gray-50); }

        .latest-thumb {
            flex-shrink: 0; width: 68px; height: 54px; border-radius: 6px; overflow: hidden;
            background: linear-gradient(135deg, var(--navy), #2e5a8e);
            display: flex; align-items: center; justify-content: center;
        }
        .latest-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .latest-thumb i { color: rgba(255,255,255,.3); font-size: 1.25rem; }

        .latest-meta { flex: 1; min-width: 0; }
        .latest-topic { font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--gold); margin-bottom: .18rem; }
        .latest-title {
            font-size: .87rem; font-weight: 600; color: var(--ink); line-height: 1.35;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: .3rem;
            transition: color .2s;
        }
        .latest-item:hover .latest-title { color: var(--navy); }
        .latest-date { font-size: .71rem; color: var(--muted); display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; }
        .latest-date i { font-size: .63rem; }

        /* FOOTER STYLES */
        .site-footer { background: linear-gradient(to bottom, var(--gray-900), var(--gray-950)); color: #fff; }
        .footer-inner { max-width: 1280px; margin: 0 auto; padding: 3rem 1.5rem 0; }

        .footer-top { display: grid; grid-template-columns: 2fr 3fr; gap: 3rem; padding-bottom: 2rem; }
        @media (max-width: 1023px) { .footer-top { grid-template-columns: 1fr; gap: 2rem; } }

        .footer-logo-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem; }
        .footer-logo-circle {
            width: 54px; height: 54px; background: var(--paper); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .footer-logo-circle i { color: var(--navy); font-size: 1.4rem; }
        .footer-brand-name { font-size: 1.35rem; font-weight: 700; color: #e2e2e2; }
        .footer-brand-tag  { font-size: .82rem; color: #9ca3af; margin-top: .15rem; }
        .footer-desc       { font-size: .87rem; color: #9ca3af; max-width: 340px; line-height: 1.65; }

        .footer-links-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.75rem; }
        @media (max-width: 767px) { .footer-links-grid { grid-template-columns: repeat(2, 1fr); } }

        .footer-col-title { font-size: .88rem; font-weight: 700; color: #e2e2e2; margin-bottom: .9rem; padding-bottom: .6rem; border-bottom: 1px solid #e2e2e2; }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: .45rem; }
        .footer-col a {
            font-size: .83rem;
            color: #9ca3af;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: color .2s;
            text-decoration: none;
        }
        .footer-col a:hover { color: #fff; }
        .footer-col a .dot { width: 5px; height: 5px; background: #3b82f6; border-radius: 50%; opacity: 0; flex-shrink: 0; transition: opacity .2s; }
        .footer-col a:hover .dot { opacity: 1; }

        .footer-social-row {
            border-top: 1px solid var(--gray-800);
            padding: 1.2rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .footer-social-label { font-size: .84rem; font-weight: 500; color: #9ca3af; }
        .social-icons { display: flex; gap: .45rem; }
        .social-icon {
            width: 36px; height: 36px; border-radius: 50%; background: var(--gray-800);
            display: flex; align-items: center; justify-content: center; color: #fff;
            transition: all .2s;
            text-decoration: none;
        }
        .social-icon:hover { background: #374151; color: #93c5fd; }
        .social-icon svg { width: 16px; height: 16px; }

        .footer-disclaimer { background: var(--gray-950); padding: 1.5rem; margin: 0 -1.5rem; }
        .footer-disclaimer p { font-size: .75rem; color: #6b7280; text-align: center; line-height: 1.7; max-width: 1200px; margin: 0 auto; }

        .footer-copyright { text-align: center; padding: 1.25rem 1.5rem 1.5rem; font-size: .82rem; color: #6b7280; }
        .footer-copyright a { color: #60a5fa; transition: color .2s; text-decoration: none; }
        .footer-copyright a:hover { color: #93c5fd; }

        @keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        .article-card { animation: fadeUp .5s ease both; }
        .sidebar      { animation: fadeUp .5s .12s ease both; }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="site-header">
    <div class="header-inner">
        <a href="<?php echo $baseUrl; ?>/" class="logo-link">
            <div class="logo-circle"><i class="fas fa-chart-line"></i></div>
            <?php echo $siteName; ?>
        </a>

        <nav class="desktop-nav">
            <a href="<?php echo $baseUrl; ?>/asset-allocation" class="nav-link">Asset Allocation</a>
            <a href="<?php echo $baseUrl; ?>/offerings" class="nav-link">Offerings</a>

            <div class="nav-dropdown">
                <button class="nav-dropdown-btn">Resources <i class="fas fa-chevron-down"></i></button>
                <div class="dropdown-menu">
                    <a href="<?php echo $baseUrl; ?>/blog" class="dropdown-item"><i class="fas fa-newspaper"></i> Blogs</a>
                    <a href="<?php echo $baseUrl; ?>/media" class="dropdown-item"><i class="fas fa-photo-video"></i> Media</a>
                </div>
            </div>

            <a href="<?php echo $baseUrl; ?>/about-us" class="nav-link">About Us</a>
        </nav>

        <a href="<?php echo $baseUrl; ?>/login" class="login-btn">Login</a>

        <button class="hamburger" onclick="document.getElementById('mobMenu').classList.toggle('open')" aria-label="Menu">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <div class="mobile-menu" id="mobMenu">
        <a href="<?php echo $baseUrl; ?>/asset-allocation" class="mobile-nav-link">Asset Allocation</a>
        <a href="<?php echo $baseUrl; ?>/offerings" class="mobile-nav-link">Offerings</a>
        <div class="mobile-section-label">Resources</div>
        <a href="<?php echo $baseUrl; ?>/blog" class="mobile-nav-link"><i class="fas fa-newspaper"></i> Blogs</a>
        <a href="<?php echo $baseUrl; ?>/media" class="mobile-nav-link"><i class="fas fa-photo-video"></i> Media</a>
        <a href="<?php echo $baseUrl; ?>/about-us" class="mobile-nav-link" style="margin-top:.5rem">About Us</a>
        <div class="mobile-login"><a href="<?php echo $baseUrl; ?>/login">Login / Sign Up</a></div>
    </div>
</header>

<!-- MAIN CONTENT -->
<main>
    <div class="page-wrap">
        <article class="article-card">
            <?php if ($hasImage): ?>
            <img class="hero-img" src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>">
            <?php endif; ?>

            <div class="article-body-wrap">
                <div class="topic-badge">
                    <i class="<?php echo $topicIcon; ?>"></i>
                    <?php echo htmlspecialchars($blog['topic']); ?>
                </div>

                <h1 class="article-title"><?php echo htmlspecialchars($blog['title']); ?></h1>

                <div class="article-meta">
                    <span><i class="far fa-calendar-alt"></i><?php echo date('F j, Y', strtotime($blog['date'])); ?></span>
                    <span><i class="far fa-clock"></i><?php echo htmlspecialchars($blog['read_time']); ?></span>
                    <span><i class="fas fa-tag"></i><?php echo htmlspecialchars($blog['topic']); ?></span>
                </div>

                <div class="article-content">
                    <?php foreach ($paragraphs as $para): ?>
                        <p><?php echo nl2br(htmlspecialchars($para)); ?></p>
                    <?php endforeach; ?>
                </div>

                <div class="art-divider">&#10022;</div>

                <div class="share-bar">
                    <span class="share-label">Share</span>
                    <button class="share-btn" onclick="shareLinkedIn()">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.92 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z"/></svg>
                        LinkedIn
                    </button>
                    <button class="share-btn" onclick="shareTwitter('<?php echo urlencode($blog['title']); ?>')">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                        Twitter
                    </button>
                    <button class="share-btn" onclick="shareFacebook()">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        Facebook
                    </button>
                    <button class="share-btn" onclick="copyLink(this)">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        Copy link
                    </button>
                </div>
            </div>
        </article>

        <aside class="sidebar">
            <?php if (!empty($latestBlogs)): ?>
            <div class="sidebar-card">
                <div class="sidebar-heading">
                    <i class="fas fa-newspaper"></i> Latest Blogs
                </div>

                <?php foreach ($latestBlogs as $lb):
                    $lbImg  = !empty($lb['image']) && file_exists('../uploads/blog/' . $lb['image']);
                    $lbIcon = $topicIcons[$lb['topic']] ?? 'fas fa-newspaper';
                ?>
                <a href="<?php echo $baseUrl . htmlspecialchars($lb['url']); ?>" class="latest-item">
                    <div class="latest-thumb">
                        <?php if ($lbImg): ?>
                            <img src="../uploads/blog/<?php echo htmlspecialchars($lb['image']); ?>" alt="<?php echo htmlspecialchars($lb['title']); ?>">
                        <?php else: ?>
                            <i class="<?php echo $lbIcon; ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="latest-meta">
                        <div class="latest-topic"><?php echo htmlspecialchars($lb['topic']); ?></div>
                        <div class="latest-title"><?php echo htmlspecialchars($lb['title']); ?></div>
                        <div class="latest-date">
                            <i class="far fa-calendar-alt"></i>
                            <?php echo date('M j, Y', strtotime($lb['date'])); ?>
                            &nbsp;&bull;&nbsp;
                            <i class="far fa-clock"></i>
                            <?php echo htmlspecialchars($lb['read_time']); ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</main>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-top">
            <div>
                <div class="footer-logo-row">
                    <div class="footer-logo-circle">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="footer-brand-name"><?php echo $siteName; ?></div>
                        <div class="footer-brand-tag"><?php echo $siteTagline; ?></div>
                    </div>
                </div>
                <p class="footer-desc">
                    We empower individuals to make smart investment decisions through
                    technology-driven solutions and expert guidance.
                </p>
            </div>

            <div class="footer-links-grid">
                <div class="footer-col">
                    <div class="footer-col-title">Investments</div>
                    <ul>
                        <li><a href="<?php echo $baseUrl; ?>/mutual-funds"><span class="dot"></span>Mutual Funds</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/sip-calculator"><span class="dot"></span>SIP Calculator</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/lumpsum"><span class="dot"></span>Lumpsum Investment</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">Services</div>
                    <ul>
                        <li><a href="<?php echo $baseUrl; ?>/asset-allocation"><span class="dot"></span>Asset Allocation</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/tax-planning"><span class="dot"></span>Tax Planning</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">Company</div>
                    <ul>
                        <li><a href="<?php echo $baseUrl; ?>/about-us"><span class="dot"></span>About Us</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/careers"><span class="dot"></span>Careers</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/blog"><span class="dot"></span>Blog</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/media"><span class="dot"></span>Media</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">Support</div>
                    <ul>
                        <li><a href="<?php echo $baseUrl; ?>/contact"><span class="dot"></span>Contact Us</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/faq"><span class="dot"></span>FAQ</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/privacy-policy"><span class="dot"></span>Privacy Policy</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/terms"><span class="dot"></span>Terms of Use</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer-social-row">
            <span class="footer-social-label">Follow us:</span>
            <div class="social-icons">
                <a class="social-icon" href="https://www.linkedin.com/company/litm-financial-pvt-ltd/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.92 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z"/></svg>
                </a>
                <a class="social-icon" href="https://x.com/FinancialLitm" target="_blank" rel="noopener noreferrer" aria-label="Twitter">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                </a>
                <a class="social-icon" href="https://facebook.com/..." target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a class="social-icon" href="https://instagram.com/litmfin" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                </a>
                <a class="social-icon" href="https://youtube.com/..." target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.5 12 3.5 12 3.5s-7.505 0-9.377.55A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.552 9.376.552 9.376.552s7.505 0 9.377-.55a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                </a>
            </div>
        </div>

        <div class="footer-disclaimer">
            <p>Mutual fund investments are subject to market risks. Please read the scheme information and other related
            documents carefully before investing. Past performance is not indicative of future returns. Portfolio returns
            and allocation between equity and debt are estimated based on a number of factors including the user's risk
            profile, goal horizon and disclosed financial position. Performance of any investment portfolio can neither
            be predicted nor guaranteed.</p>
        </div>
    </div>

    <div class="footer-copyright">
        &copy; <?php echo date('Y'); ?> All rights reserved <?php echo $siteName; ?>.
        <span style="margin:0 .4rem">&bull;</span>
        Developed by <a href="https://www.zieers.com/" target="_blank" rel="noopener noreferrer">Zieers Systems Pvt Ltd</a>
    </div>
</footer>

<script>
    var baseUrl = '<?php echo $baseUrl; ?>';
    
    function shareLinkedIn() {
        window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(window.location.href), '_blank', 'width=600,height=500');
    }
    function shareTwitter(title) {
        window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(title) + '&url=' + encodeURIComponent(window.location.href), '_blank', 'width=600,height=400');
    }
    function shareFacebook() {
        window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href), '_blank', 'width=600,height=500');
    }
    function copyLink(btn) {
        navigator.clipboard.writeText(window.location.href).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Copied!';
            btn.classList.add('copied');
            setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('copied'); }, 2200);
        });
    }
</script>
</body>
</html>