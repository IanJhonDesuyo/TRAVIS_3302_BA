<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function sidebar(string $active = ''): void {
    $items = [
        'Overview' => [
            ['dashboard.php','Dashboard','bi-speedometer2','dashboard'],
            ['monitoring.php','Live Monitoring','bi-camera-video','monitoring'],
        ],
        'Intelligence' => [
            ['decision_support.php','Decision Support','bi-cpu','decision-support'],
        ],
        'Enforcement' => [
            ['violations.php','Violations','bi-cone-striped','violations'],
            ['payments.php','Payments','bi-cash-coin','payments'],
            ['alerts.php','Alerts','bi-bell','alerts'],
        ],
        'Administration' => [
            ['reports.php','Reports','bi-file-earmark-bar-graph','reports'],
            ['users.php','User Management','bi-people','users'],
            ['public-website.php','Public Website','bi-globe2','public'],
            ['settings.php','Settings','bi-gear','settings'],
        ],
    ];
    echo '<aside class="sidebar" id="sidebar">';
    echo '<div class="sidebar-brand"><div class="brand-logo-wordmark">TRAVIS</div><small>Traffic Violation Analytics</small></div>';
    foreach ($items as $section => $links) {
        echo '<div class="nav-section">' . esc($section) . '</div><ul class="nav flex-column">';
        foreach ($links as [$href,$label,$icon,$key]) {
            $class = $active === $key ? 'nav-link active' : 'nav-link';
            echo '<li><a class="' . $class . '" href="' . esc($href) . '"><i class="bi ' . esc($icon) . '"></i> ' . esc($label) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</aside><div class="backdrop" id="backdrop"></div>';
}

function page_start(string $title, string $active = '', string $search = 'Search...'): void {
    ensure_ml_api_running();
    $admin = current_admin();
    $name = $admin['full_name'] ?? 'System Admin';
    $init = initials($name);
    $styleFile = dirname(__DIR__, 2) . '/css/style.css';
    $styleVersion = is_file($styleFile) ? (string) filemtime($styleFile) : '1';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1" />';
    echo '<title>TRAVIS — ' . esc($title) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />';
    echo '<link href="' . esc(asset_url('css/style.css')) . '?v=' . esc($styleVersion) . '" rel="stylesheet" />';
    echo '<style>.empty-state{border:1px dashed #d1d5db;border-radius:14px;padding:24px;text-align:center;color:#6b7280;background:#f9fafb}.camera-stage{min-height:420px;background:linear-gradient(135deg,#0f172a,#1e3a8a);border-radius:18px;display:flex;align-items:center;justify-content:center;color:#fff;position:relative;overflow:hidden}.camera-stage video{width:100%;height:100%;max-height:480px;object-fit:contain;background:#000}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.mini-metric{background:#fff;border:1px solid #edf2f7;border-radius:14px;padding:14px}.mini-metric small{color:#64748b}.mini-metric strong{display:block;font-size:1.3rem}.nav-link.active{background:rgba(255,255,255,.12);color:#fff}.sidebar,.sidebar .nav-link,.sidebar .nav-section{font-family:"Poppins",sans-serif}.sidebar-brand{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:4px;height:84px;padding:0 20px;box-sizing:border-box}.topbar{height:84px;box-sizing:border-box;display:flex;align-items:center}.brand-logo-wordmark{font-family:"Poppins",sans-serif;font-weight:800;font-size:1.8rem;letter-spacing:.5px;line-height:1;background:linear-gradient(90deg,#ffffff 0%,#bfdbfe 45%,#3b82f6 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;display:inline-block}.sidebar-brand small{color:#94a3b8;font-family:"Poppins",sans-serif;font-size:.72rem;letter-spacing:.3px}</style>';
    echo '</head><body class="admin-dashboard">';
    sidebar($active);
    echo '<div class="main-wrapper"><header class="topbar"><button class="btn btn-light d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>';
    echo '<div class="search"><i class="bi bi-search"></i><input class="form-control" placeholder="' . esc($search) . '" /></div>';
    echo '<div class="ms-auto d-flex align-items-center gap-3"><small class="text-muted d-none d-md-block" id="liveClock"></small>';
    $alertCount = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE status = 'active'", 0);
    echo '<a href="' . esc(app_url('alerts.php')) . '" class="btn btn-light position-relative bell"><i class="bi bi-bell"></i>';
    if ((int)$alertCount > 0) echo '<span class="badge bg-danger">' . num($alertCount) . '</span>';
    echo '</a><div class="dropdown"><button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown"><span class="avatar">' . esc($init) . '</span><span class="d-none d-md-inline small fw-semibold">' . esc($name) . '</span></button>';
    echo '<ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li><li><a class="dropdown-item" href="' . esc(app_url('settings.php')) . '"><i class="bi bi-gear me-2"></i>Settings</a></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#signOutModal"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button></li></ul></div></div></header><main class="content">';
}

function page_end(bool $chart = false): void {
    $showLoginSuccess = !empty($_SESSION['login_success']);
    $loginName = (string)($_SESSION['user']['name'] ?? 'User');
    unset($_SESSION['login_success']);

    echo '</main></div>';
    echo '<div class="modal fade auth-prompt signout-modal" id="signOutModal" tabindex="-1" aria-labelledby="signOutModalLabel" aria-describedby="signOutModalDescription" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="auth-prompt-accent"></div><div class="modal-body text-center"><div class="auth-prompt-brand"><span class="auth-prompt-brand-mark">T</span><span>TRAVIS SECURITY</span></div><div class="auth-prompt-icon signout-icon"><i class="bi bi-box-arrow-right"></i></div><span class="auth-prompt-eyebrow">Session control</span><h4 id="signOutModalLabel">Sign out of TRAVIS?</h4><p id="signOutModalDescription">Your current session will end securely. You will need to enter your credentials again to access the dashboard.</p><div class="auth-prompt-actions"><button type="button" class="btn auth-prompt-cancel" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i><span>Stay signed in</span></button><form method="post" action="' . esc(app_url('logout.php')) . '"><input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '"><button class="btn btn-signout" type="submit"><span>Sign out securely</span><i class="bi bi-box-arrow-right"></i></button></form></div><small class="auth-prompt-note"><i class="bi bi-shield-lock"></i> Your account and session data remain protected.</small></div></div></div></div>';
    if ($showLoginSuccess) {
        echo '<div class="modal fade auth-prompt login-success-modal" id="loginSuccessModal" tabindex="-1" aria-labelledby="loginSuccessModalLabel" aria-describedby="loginSuccessModalDescription" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="auth-prompt-accent"></div><div class="modal-body text-center"><div class="auth-prompt-brand"><span class="auth-prompt-brand-mark">T</span><span>TRAVIS COMMAND CENTER</span></div><div class="auth-prompt-icon login-success-icon"><i class="bi bi-check-lg"></i></div><span class="auth-prompt-eyebrow">Identity verified</span><h4 id="loginSuccessModalLabel">Welcome back, ' . esc($loginName) . '!</h4><p id="loginSuccessModalDescription">You have signed in successfully. Your secure dashboard and live traffic intelligence are ready.</p><button type="button" class="btn btn-login-success" data-bs-dismiss="modal"><span>Open dashboard</span><i class="bi bi-arrow-right"></i></button><small class="auth-prompt-note"><i class="bi bi-shield-check"></i> Secure administrator session active.</small></div></div></div></div>';
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