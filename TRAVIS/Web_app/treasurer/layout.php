<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function sidebar(string $active = ''): void {
    $items = [
        'Overview' => [
            ['dashboard.php', 'Dashboard', 'bi-speedometer2', 'dashboard'],
        ],
        'Collections' => [
            ['violations.php', 'Violations', 'bi-cone-striped', 'violations'],
            ['payments.php', 'Payments', 'bi-cash-coin', 'payments'],
            ['reports.php', 'Collection Reports', 'bi-file-earmark-bar-graph', 'reports'],
            ['history.php', 'Payment History', 'bi-clock-history', 'history'],
        ],
        'Account' => [
            ['notifications.php', 'Notifications', 'bi-bell', 'notifications'],
            ['profile.php', 'Profile', 'bi-person-circle', 'profile'],
        ],
    ];
    echo '<aside class="sidebar" id="sidebar">';
    echo '<div class="sidebar-brand"><div class="brand-logo-wordmark">TRAVIS</div><small>Traffic Violation Analytics</small></div>';
    foreach ($items as $section => $links) {
        echo '<div class="nav-section">' . esc($section) . '</div><ul class="nav flex-column">';
        foreach ($links as [$href, $label, $icon, $key]) {
            $class = $active === $key ? 'nav-link active' : 'nav-link';
            echo '<li><a class="' . $class . '" href="' . esc($href) . '"><i class="bi ' . esc($icon) . '"></i> ' . esc($label) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</aside><div class="backdrop" id="backdrop"></div>';
}

function page_start(string $title, string $active = '', string $search = 'Search violations, receipts, plates...', string $subtitle = '', bool $showSearch = true): void {
    $admin = current_admin();
    $name = $admin['full_name'] ?? 'Treasury Personnel';
    $role = $admin['role'] ?? 'Treasurer';
    $init = initials($name);
    $styleFile = dirname(__DIR__, 2) . '/css/style.css';
    $styleVersion = is_file($styleFile) ? (string) filemtime($styleFile) : '1';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1" />';
    echo '<title>TRAVIS &middot; Treasurer &mdash; ' . esc($title) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />';
    echo '<link href="' . esc(asset_url('css/style.css')) . '?v=' . esc($styleVersion) . '" rel="stylesheet" />';
    echo '<style>.empty-state{border:1px dashed #d1d5db;border-radius:14px;padding:24px;text-align:center;color:#6b7280;background:#f9fafb}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.mini-metric{background:#fff;border:1px solid #edf2f7;border-radius:14px;padding:14px}.mini-metric small{color:#64748b}.mini-metric strong{display:block;font-size:1.3rem}.nav-link.active{background:rgba(255,255,255,.12);color:#fff}.notif-card{border:1px solid #edf2f7;border-radius:14px;padding:16px;background:#fff;margin-bottom:12px}.notif-card.tone-danger{border-left:4px solid var(--danger,#dc2626)}.notif-card.tone-warning{border-left:4px solid var(--accent,#f59e0b)}.notif-card.tone-success{border-left:4px solid var(--secondary,#16a34a)}.notif-card.tone-info{border-left:4px solid var(--primary,#1e3a8a)}.pending-row{cursor:pointer}.pending-row:hover{background:#f9fafb}.sidebar,.sidebar .nav-link,.sidebar .nav-section{font-family:"Poppins",sans-serif}.sidebar-brand{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:4px;height:84px;padding:0 20px;box-sizing:border-box}.topbar{height:84px;box-sizing:border-box;display:flex;align-items:center}.brand-logo-wordmark{font-family:"Poppins",sans-serif;font-weight:800;font-size:1.8rem;letter-spacing:.5px;line-height:1;background:linear-gradient(90deg,#fff 0%,#bfdbfe 45%,#3b82f6 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;display:inline-block}.sidebar-brand small{color:#94a3b8;font-family:"Poppins",sans-serif;font-size:.72rem;letter-spacing:.3px}.treasurer-page-heading{margin-bottom:1.5rem}.treasurer-page-heading .page-title{font-size:1.5rem}.role-pill{font-size:.68rem;color:var(--muted)}</style>';
    echo '<style id="treasurer-portal-theme">
    body.admin-dashboard{--tp-navy-950:#060f1e;--tp-navy-900:#0a1a30;--tp-navy-800:#0f2544;--tp-border:rgba(255,255,255,.10);--tp-cyan:#4fc3f7;--tp-soft:#c9d8ea;font-family:"Poppins",sans-serif;background:radial-gradient(circle at 12% 8%,rgba(56,189,248,.09),transparent 28%),radial-gradient(circle at 88% 82%,rgba(37,99,235,.10),transparent 34%),linear-gradient(160deg,var(--tp-navy-950),var(--tp-navy-900) 48%,var(--tp-navy-800));color:#fff}
    body.admin-dashboard .topbar{background:var(--tp-navy-900)!important;border-bottom:1px solid var(--tp-border)!important;box-shadow:none}
    body.admin-dashboard .topbar .search .form-control{color:#fff!important;background:rgba(255,255,255,.06)!important;border:1px solid var(--tp-border)!important}
    body.admin-dashboard .topbar .search .form-control::placeholder{color:#94a3b8}body.admin-dashboard .topbar .search i,body.admin-dashboard .topbar #liveClock{color:var(--tp-soft)!important}
    body.admin-dashboard .topbar .btn-light{color:#fff!important;background:rgba(255,255,255,.06)!important;border:1px solid var(--tp-border)!important}body.admin-dashboard .topbar .btn-light:hover{background:rgba(255,255,255,.12)!important}body.admin-dashboard .role-pill{color:#94a3b8!important}
    body.admin-dashboard .page-title{color:#fff;font-weight:800}body.admin-dashboard .page-sub{color:var(--tp-soft)}
    body.admin-dashboard .stat-card,body.admin-dashboard .section-card,body.admin-dashboard .notif-card,body.admin-dashboard .mini-metric,body.admin-dashboard .payment-summary-card{color:#fff!important;background:rgba(255,255,255,.035)!important;border:1px solid var(--tp-border)!important;border-radius:18px!important;box-shadow:0 14px 30px rgba(0,0,0,.25)!important;backdrop-filter:blur(8px)}
    body.admin-dashboard .stat-card{padding:20px;overflow:hidden;position:relative}body.admin-dashboard .stat-card:after{content:"";position:absolute;width:85px;height:85px;right:-30px;top:-35px;border-radius:50%;background:rgba(56,189,248,.07)}body.admin-dashboard .stat-card .stat-icon{width:44px;height:44px;border-radius:12px;margin-bottom:7px}body.admin-dashboard .stat-card .stat-label{color:var(--tp-soft)!important}body.admin-dashboard .stat-card .stat-value{color:#fff!important;font-weight:800}body.admin-dashboard .stat-card small{color:var(--tp-soft)!important}
    body.admin-dashboard .section-card{padding:20px}body.admin-dashboard .section-head h6,body.admin-dashboard .section-card h4,body.admin-dashboard .section-card h5,body.admin-dashboard .section-card h6{color:#fff!important}body.admin-dashboard .section-card small,body.admin-dashboard .section-card .text-muted,body.admin-dashboard .notif-card .text-muted{color:var(--tp-soft)!important}body.admin-dashboard .section-head a{color:var(--tp-cyan)!important}
    body.admin-dashboard .table{--bs-table-bg:transparent;--bs-table-color:var(--tp-soft);--bs-table-border-color:var(--tp-border);color:var(--tp-soft)!important}body.admin-dashboard .table>:not(caption)>*>*{background:transparent!important;color:var(--tp-soft);border-color:rgba(255,255,255,.07)}body.admin-dashboard .table thead th{color:#94a3b8!important;background:transparent!important;border-color:var(--tp-border)!important}body.admin-dashboard .table tbody .fw-semibold{color:#fff}body.admin-dashboard .table tbody tr:hover>*{background:rgba(56,189,248,.05)!important;color:#fff}
    body.admin-dashboard .form-label{color:var(--tp-soft)!important}body.admin-dashboard .form-control,body.admin-dashboard .form-select,body.admin-dashboard .input-group-text{color:#fff!important;background-color:rgba(255,255,255,.055)!important;border-color:var(--tp-border)!important}body.admin-dashboard .form-control::placeholder{color:#8496aa}body.admin-dashboard .form-control:focus,body.admin-dashboard .form-select:focus{border-color:var(--tp-cyan)!important;box-shadow:0 0 0 .2rem rgba(79,195,247,.12)!important}body.admin-dashboard .form-select{color-scheme:dark;cursor:pointer;padding-right:2.5rem;background-image:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22 viewBox=%220 0 16 16%22%3E%3Cpath fill=%22none%22 stroke=%22%234fc3f7%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%222%22 d=%22m2 5 6 6 6-6%22/%3E%3C/svg%3E")!important;background-repeat:no-repeat!important;background-position:right .8rem center!important;background-size:14px 11px!important}body.admin-dashboard .form-select:hover{border-color:rgba(79,195,247,.42)!important;background-color:rgba(255,255,255,.075)!important}body.admin-dashboard .form-select option,body.admin-dashboard .form-select optgroup{color:#e2e8f0;background:#0f2544}body.admin-dashboard .form-select option:checked{color:#fff;background:#1d4f7a}body.admin-dashboard .form-select:disabled{cursor:not-allowed;opacity:.6;background-color:rgba(255,255,255,.025)!important}body.admin-dashboard .form-control[readonly],body.admin-dashboard .form-control:disabled{background:rgba(255,255,255,.035)!important;color:#aebed0!important}
    body.admin-dashboard .filter-toolbar{background:rgba(255,255,255,.025)!important;border-color:var(--tp-border)!important}body.admin-dashboard .payment-summary-card .ps-row{border-color:var(--tp-border)!important}body.admin-dashboard .payment-summary-card .ps-label{color:var(--tp-soft)!important}body.admin-dashboard .payment-summary-card .ps-value{color:#fff!important}
    body.admin-dashboard .notif-card{border-left-width:4px!important}body.admin-dashboard .empty-state{color:var(--tp-soft)!important;background:rgba(255,255,255,.025)!important;border-color:var(--tp-border)!important}
    body.admin-dashboard .icon-link{display:inline-grid;place-items:center;width:30px;height:30px;border:1px solid var(--tp-border);border-radius:8px;color:var(--tp-cyan);margin-left:4px}body.admin-dashboard .icon-link:hover{color:#fff;background:rgba(56,189,248,.14)}
    body.admin-dashboard .alert{border-color:var(--tp-border)}body.admin-dashboard hr{border-color:var(--tp-border);opacity:1}
    body.admin-dashboard .modal-backdrop.show{opacity:.72;background:#020817;backdrop-filter:blur(4px)}
    body.admin-dashboard .modal .modal-dialog{width:min(94vw,540px)}
    body.admin-dashboard .modal .modal-content{position:relative;overflow:hidden;color:#fff!important;background:radial-gradient(circle at 100% 0,rgba(56,189,248,.12),transparent 15rem),linear-gradient(145deg,#0a1a30,#0f2544)!important;background-color:#0a1a30!important;border:1px solid rgba(79,195,247,.18)!important;border-radius:22px!important;box-shadow:0 30px 85px rgba(0,0,0,.58)!important;backdrop-filter:blur(20px)!important}
    body.admin-dashboard .modal .modal-content:before{content:"";display:block;height:4px;background:linear-gradient(90deg,#2563eb,#00a99d,#4fc3f7)}
    body.admin-dashboard .modal .modal-header{padding:1.15rem 1.35rem;border-color:var(--tp-border)!important}body.admin-dashboard .modal .modal-body{padding:1.4rem}body.admin-dashboard .modal .modal-footer{gap:.55rem;padding:1rem 1.35rem 1.25rem;border-color:var(--tp-border)!important}
    body.admin-dashboard .modal .modal-title,body.admin-dashboard .modal h4,body.admin-dashboard .modal h5,body.admin-dashboard .modal strong{color:#fff!important}body.admin-dashboard .modal p,body.admin-dashboard .modal small,body.admin-dashboard .modal .text-muted{color:var(--tp-soft)!important}body.admin-dashboard .modal .btn-close{filter:invert(1) grayscale(1);opacity:.75}body.admin-dashboard .modal .btn-close:hover{opacity:1}
    body.admin-dashboard .modal:not(.auth-prompt) .modal-body.text-center>div:first-child,body.admin-dashboard .modal:not(.auth-prompt) .modal-body>.text-center>div:first-child{display:grid;width:64px;height:64px;margin:0 auto 1rem;place-items:center;border:1px solid rgba(52,211,153,.25);border-radius:20px;background:rgba(52,211,153,.12);font-size:1.8rem!important;box-shadow:0 12px 28px rgba(0,0,0,.2)}
    body.admin-dashboard .modal .payment-summary-card{padding:.35rem 1rem!important;border-radius:14px!important;background:rgba(255,255,255,.035)!important;box-shadow:none!important}
    body.admin-dashboard .modal .btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;min-height:40px;padding:.5rem 1rem;border-radius:11px;font-weight:700}body.admin-dashboard .modal .btn-primary,body.admin-dashboard .modal .btn-save-payment,body.admin-dashboard .modal .btn-login-success,body.admin-dashboard .modal .btn-signout{color:#fff!important;border:0!important;background:linear-gradient(135deg,#2563eb,#00a99d)!important;box-shadow:0 10px 24px rgba(37,99,235,.24)}body.admin-dashboard .modal .btn-light,body.admin-dashboard .modal .auth-prompt-cancel{color:var(--tp-soft)!important;border:1px solid var(--tp-border)!important;background:rgba(255,255,255,.06)!important}body.admin-dashboard .modal .btn:hover{color:#fff!important;filter:brightness(1.08);transform:translateY(-1px)}
    body.admin-dashboard .auth-prompt .modal-content{border-color:rgba(79,195,247,.2)!important}body.admin-dashboard .auth-prompt .auth-prompt-accent{display:none}body.admin-dashboard .auth-prompt-brand,body.admin-dashboard .auth-prompt-note{color:#94a3b8!important}body.admin-dashboard .auth-prompt-eyebrow{color:var(--tp-cyan)!important}body.admin-dashboard .auth-prompt h4{color:#fff!important}body.admin-dashboard .auth-prompt p{color:var(--tp-soft)!important}body.admin-dashboard .auth-prompt-icon{color:#34d399!important;border:1px solid rgba(52,211,153,.25)!important;background:rgba(52,211,153,.12)!important;box-shadow:0 14px 30px rgba(0,0,0,.22)!important}body.admin-dashboard .signout-modal .auth-prompt-icon{color:#60a5fa!important;border-color:rgba(96,165,250,.25)!important;background:rgba(96,165,250,.12)!important}
    body.admin-dashboard .dropdown-menu{min-width:190px;padding:.5rem;background:linear-gradient(145deg,#0a1a30,#0f2544)!important;border:1px solid rgba(79,195,247,.18)!important;border-radius:14px!important;box-shadow:0 18px 45px rgba(0,0,0,.45)!important;overflow:hidden}body.admin-dashboard .dropdown-menu.show{animation:tp-dropdown-in .18s ease-out both}body.admin-dashboard .dropdown-item{display:flex;align-items:center;min-height:38px;padding:.55rem .7rem;color:var(--tp-soft)!important;border-radius:9px;font-size:.84rem;font-weight:500;transition:background-color .15s ease,color .15s ease}body.admin-dashboard .dropdown-item i{width:20px;color:#7dd3fc}body.admin-dashboard .dropdown-item:hover,body.admin-dashboard .dropdown-item:focus{color:#fff!important;background:rgba(56,189,248,.11)!important}body.admin-dashboard .dropdown-item.active,body.admin-dashboard .dropdown-item:active{color:#fff!important;background:linear-gradient(90deg,rgba(37,99,235,.7),rgba(0,169,157,.65))!important}body.admin-dashboard .dropdown-item.text-danger{color:#fca5a5!important}body.admin-dashboard .dropdown-item.text-danger i{color:#f87171}body.admin-dashboard .dropdown-item.text-danger:hover{color:#fff!important;background:rgba(248,113,113,.13)!important}body.admin-dashboard .dropdown-divider{margin:.4rem 0;border-color:var(--tp-border)}body.admin-dashboard .dropdown-header{color:#94a3b8;font-size:.68rem;letter-spacing:.08em;text-transform:uppercase}@keyframes tp-dropdown-in{from{opacity:0;transform:translateY(-6px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
    body.admin-dashboard .btn-outline-secondary{color:var(--tp-soft);border-color:var(--tp-border)}body.admin-dashboard .btn-outline-secondary:hover{color:#fff;background:rgba(255,255,255,.1)}
    @media(max-width:767.98px){body.admin-dashboard .content{padding:1rem}body.admin-dashboard .section-card{padding:16px}body.admin-dashboard .treasurer-page-heading{margin-bottom:1rem}}
    @media print{body.admin-dashboard{background:#fff!important;color:#111827!important}body.admin-dashboard .sidebar,body.admin-dashboard .topbar,body.admin-dashboard .treasurer-page-heading,body.admin-dashboard .no-print{display:none!important}body.admin-dashboard .main-wrapper{width:100%!important;margin:0!important}body.admin-dashboard .content{padding:0!important}body.admin-dashboard .section-card,body.admin-dashboard .stat-card{color:#111827!important;background:#fff!important;border:1px solid #d1d5db!important;box-shadow:none!important}body.admin-dashboard .section-card h4,body.admin-dashboard .section-card h5,body.admin-dashboard .section-card h6,body.admin-dashboard .table tbody .fw-semibold{color:#111827!important}body.admin-dashboard .section-card small,body.admin-dashboard .section-card .text-muted,body.admin-dashboard .table>:not(caption)>*>*,body.admin-dashboard .table thead th{color:#374151!important;border-color:#d1d5db!important}body.admin-dashboard .modal.show .modal-content{color:#111827!important;background:#fff!important;background-color:#fff!important;border:0!important;box-shadow:none!important}body.admin-dashboard .modal.show .modal-content:before{display:none}body.admin-dashboard .modal.show .modal-title,body.admin-dashboard .modal.show h4,body.admin-dashboard .modal.show h5,body.admin-dashboard .modal.show strong{color:#111827!important}body.admin-dashboard .modal.show p,body.admin-dashboard .modal.show small,body.admin-dashboard .modal.show .text-muted{color:#374151!important}}
    </style>';
    echo '</head><body class="admin-dashboard">';
    sidebar($active);
    echo '<div class="main-wrapper"><header class="topbar">';
    echo '<button class="btn btn-light d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>';
    if ($showSearch) echo '<div class="search"><i class="bi bi-search"></i><input class="form-control" placeholder="' . esc($search) . '" /></div>';
    echo '<div class="ms-auto d-flex align-items-center gap-3"><small class="text-muted d-none d-md-block" id="liveClock"></small>';
    $pendingCount = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending', 'overdue')", 0);
    echo '<a href="' . esc(app_url('notifications.php')) . '" class="btn btn-light position-relative bell"><i class="bi bi-bell"></i>';
    if ((int)$pendingCount > 0) echo '<span class="badge bg-danger">' . num($pendingCount) . '</span>';
    echo '</a><div class="dropdown"><button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown"><span class="avatar">' . esc($init) . '</span><span class="d-none d-md-flex flex-column align-items-start lh-sm"><span class="small fw-semibold">' . esc($name) . '</span><span class="role-pill">' . esc($role) . '</span></span></button>';
    echo '<ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="' . esc(app_url('profile.php')) . '"><i class="bi bi-person me-2"></i>Profile</a></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#signOutModal"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button></li></ul></div></div></header><main class="content">';
    if (!defined('TRAVIS_EMBEDDED_ADMIN_PAGE')) {
        echo '<div class="treasurer-page-heading"><h1 class="page-title">' . esc($title) . '</h1>';
        if ($subtitle !== '') echo '<p class="page-sub mt-1">' . esc($subtitle) . '</p>';
        echo '</div>';
    }
}

function page_end(bool $chart = false): void {
    $showLoginSuccess = !empty($_SESSION['login_success']);
    $loginName = (string)($_SESSION['user']['name'] ?? 'Treasury Personnel');
    unset($_SESSION['login_success']);

    echo '</main></div>';
    echo '<div class="modal fade auth-prompt signout-modal" id="signOutModal" tabindex="-1" aria-labelledby="signOutModalLabel" aria-describedby="signOutModalDescription" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="auth-prompt-accent"></div><div class="modal-body text-center"><div class="auth-prompt-brand"><span class="auth-prompt-brand-mark">T</span><span>TRAVIS TREASURY</span></div><div class="auth-prompt-icon signout-icon"><i class="bi bi-box-arrow-right"></i></div><span class="auth-prompt-eyebrow">Session control</span><h4 id="signOutModalLabel">Sign out of TRAVIS?</h4><p id="signOutModalDescription">Your current session will end securely. You will need to sign in again to access the Treasurer Portal.</p><div class="auth-prompt-actions"><button type="button" class="btn auth-prompt-cancel" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i><span>Stay signed in</span></button><form method="post" action="' . esc(app_url('logout.php')) . '"><input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '"><button class="btn btn-signout" type="submit"><span>Sign out securely</span><i class="bi bi-box-arrow-right"></i></button></form></div><small class="auth-prompt-note"><i class="bi bi-shield-lock"></i> Your account and session data remain protected.</small></div></div></div></div>';
    if ($showLoginSuccess) {
        echo '<div class="modal fade auth-prompt login-success-modal" id="loginSuccessModal" tabindex="-1" aria-labelledby="loginSuccessModalLabel" aria-describedby="loginSuccessModalDescription" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="auth-prompt-accent"></div><div class="modal-body text-center"><div class="auth-prompt-brand"><span class="auth-prompt-brand-mark">T</span><span>TRAVIS TREASURY</span></div><div class="auth-prompt-icon login-success-icon"><i class="bi bi-check-lg"></i></div><span class="auth-prompt-eyebrow">Identity verified</span><h4 id="loginSuccessModalLabel">Welcome back, ' . esc($loginName) . '!</h4><p id="loginSuccessModalDescription">You have signed in successfully. Your Treasurer Portal dashboard is ready.</p><button type="button" class="btn btn-login-success" data-bs-dismiss="modal"><span>Continue to dashboard</span><i class="bi bi-arrow-right"></i></button><small class="auth-prompt-note"><i class="bi bi-shield-check"></i> Secure treasurer session active.</small></div></div></div></div>';
    }
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    if ($chart) echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    echo '<script src="' . esc(asset_url('js/app.js')) . '"></script>';
    if ($showLoginSuccess) {
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var element=document.getElementById("loginSuccessModal");if(element){bootstrap.Modal.getOrCreateInstance(element).show();}});</script>';
    }
    echo '</body></html>';
}

function empty_state(string $message): void {
    echo '<div class="empty-state"><i class="bi bi-inbox fs-3 d-block mb-2"></i>' . esc($message) . '</div>';
}
