<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function sidebar(string $active = ''): void {
    $items = [
        'Main' => [
            ['dashboard.php', 'Dashboard', 'bi-speedometer2', 'dashboard'],
            ['violations.php', 'Traffic Violation Records', 'bi-cone-striped', 'violations'],
            ['payments.php', 'Payment Management', 'bi-cash-coin', 'payments'],
            ['reports.php', 'Collection Reports', 'bi-file-earmark-bar-graph', 'reports'],
            ['history.php', 'Payment History', 'bi-clock-history', 'history'],
        ],
        'Account' => [
            ['notifications.php', 'Notifications', 'bi-bell', 'notifications'],
            ['profile.php', 'Profile', 'bi-person-circle', 'profile'],
        ],
    ];
    echo '<aside class="sidebar" id="sidebar">';
    echo '<div class="sidebar-brand"><img class="brand-logo" src="' . esc(asset_url('assets/images/travis-logo.jpg')) . '" alt="TRAVIS logo"><div class="brand-text"><h5>TRAVIS</h5><small>Treasurer<br>Portal</small></div></div>';
    foreach ($items as $section => $links) {
        echo '<div class="nav-section">' . esc($section) . '</div><ul class="nav flex-column">';
        foreach ($links as [$href, $label, $icon, $key]) {
            $class = $active === $key ? 'nav-link active' : 'nav-link';
            echo '<li><a class="' . $class . '" href="' . esc($href) . '" title="' . esc($label) . '"><i class="bi ' . esc($icon) . '"></i> <span class="nav-label">' . esc($label) . '</span></a></li>';
        }
        echo '</ul>';
    }
    echo '<ul class="nav flex-column nav-logout"><li><button type="button" class="nav-link nav-logout-btn" data-bs-toggle="modal" data-bs-target="#signOutModal" title="Logout"><i class="bi bi-box-arrow-right"></i> <span class="nav-label">Logout</span></button></li></ul>';
    echo '<div class="sidebar-footer">TRAVIS v1.0 &middot; LGU System<br>&copy; ' . esc(date('Y')) . ' City Government</div>';
    echo '</aside><div class="backdrop" id="backdrop"></div>';
}

function page_start(string $title, string $active = '', string $search = 'Search violations, receipts, plates...', string $subtitle = '', bool $showSearch = true): void {
    $admin = current_admin();
    $name = $admin['full_name'] ?? 'Treasury Personnel';
    $role = $admin['role'] ?? 'Treasurer';
    $init = initials($name);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1" />';
    echo '<title>TRAVIS &middot; Treasurer &mdash; ' . esc($title) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />';
    $styleFile = dirname(__DIR__, 2) . '/css/style.css';
    $styleVersion = is_file($styleFile) ? (string) filemtime($styleFile) : '1';
    echo '<link href="' . esc(asset_url('css/style.css')) . '?v=' . esc($styleVersion) . '" rel="stylesheet" />';
    echo '<style>.empty-state{border-radius:14px;padding:24px;text-align:center}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.mini-metric{border-radius:14px;padding:14px}.mini-metric strong{display:block;font-size:1.3rem}.nav-link.active{background:rgba(255,255,255,.12);color:#fff}.notif-card{border-radius:14px;padding:16px;margin-bottom:12px}.pending-row{cursor:pointer}</style>';
    echo '</head><body class="admin-dashboard admin-navy-theme treasurer-navy">';
    sidebar($active);
    echo '<div class="main-wrapper"><header class="topbar">';
    echo '<button class="btn btn-light" id="sidebarToggle" title="Toggle sidebar"><i class="bi bi-list"></i></button>';
    echo '<div class="topbar-title-block"><h5 class="mb-0">' . esc($title) . '</h5>';
    if ($subtitle !== '') echo '<small class="text-muted">' . esc($subtitle) . '</small>';
    echo '</div>';
    if ($showSearch) echo '<div class="search"><i class="bi bi-search"></i><input class="form-control" placeholder="' . esc($search) . '" /></div>';
    echo '<div class="ms-auto d-flex align-items-center gap-3">';
    $pendingCount = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending', 'overdue')", 0);
    echo '<a href="' . esc(app_url('notifications.php')) . '" class="btn btn-light position-relative bell"><i class="bi bi-bell"></i>';
    if ((int)$pendingCount > 0) echo '<span class="ping"></span>';
    echo '</a><div class="dropdown"><button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown"><span class="avatar">' . esc($init) . '</span><span class="d-none d-md-flex flex-column align-items-start lh-sm"><span class="small fw-semibold">' . esc($name) . '</span><span class="role-pill">' . esc($role) . '</span></span></button>';
    echo '<ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="' . esc(app_url('profile.php')) . '"><i class="bi bi-person me-2"></i>Profile</a></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#signOutModal"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button></li></ul></div></div></header><main class="content">';
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
    echo '<script>
    (function () {
      var toggleBtn = document.getElementById("sidebarToggle");
      if (!toggleBtn) return;
      if (localStorage.getItem("travisSidebarCollapsed") === "1") {
        document.body.classList.add("sidebar-collapsed");
      }
      toggleBtn.addEventListener("click", function () {
        if (window.innerWidth >= 992) {
          document.body.classList.toggle("sidebar-collapsed");
          localStorage.setItem("travisSidebarCollapsed", document.body.classList.contains("sidebar-collapsed") ? "1" : "0");
        }
      });
    })();
    </script>';
    if ($showLoginSuccess) {
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var element=document.getElementById("loginSuccessModal");if(element){bootstrap.Modal.getOrCreateInstance(element).show();}});</script>';
    }
    echo '</body></html>';
}

function empty_state(string $message): void {
    echo '<div class="empty-state"><i class="bi bi-inbox fs-3 d-block mb-2"></i>' . esc($message) . '</div>';
}