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

function page_start(string $title, string $active = '', string $search = 'Search violations, receipts, plates...', string $subtitle = ''): void {
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
    echo '<link href="' . esc(asset_url('css/style.css')) . '" rel="stylesheet" />';
    echo '<style>.empty-state{border:1px dashed #d1d5db;border-radius:14px;padding:24px;text-align:center;color:#6b7280;background:#f9fafb}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.mini-metric{background:#fff;border:1px solid #edf2f7;border-radius:14px;padding:14px}.mini-metric small{color:#64748b}.mini-metric strong{display:block;font-size:1.3rem}.nav-link.active{background:rgba(255,255,255,.12);color:#fff}.notif-card{border:1px solid #edf2f7;border-radius:14px;padding:16px;background:#fff;margin-bottom:12px}.notif-card.tone-danger{border-left:4px solid var(--danger,#dc2626)}.notif-card.tone-warning{border-left:4px solid var(--accent,#f59e0b)}.notif-card.tone-success{border-left:4px solid var(--secondary,#16a34a)}.notif-card.tone-info{border-left:4px solid var(--primary,#1e3a8a)}.pending-row{cursor:pointer}.pending-row:hover{background:#f9fafb}</style>';
    echo '</head><body class="admin-dashboard">';
    sidebar($active);
    echo '<div class="main-wrapper"><header class="topbar">';
    echo '<div class="topbar-title-block"><h5 class="mb-0">' . esc($title) . '</h5>';
    if ($subtitle !== '') echo '<small class="text-muted">' . esc($subtitle) . '</small>';
    echo '</div>';
    echo '<div class="search"><i class="bi bi-search"></i><input class="form-control" placeholder="' . esc($search) . '" /></div>';
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
    echo '<div class="modal fade signout-modal" id="signOutModal" tabindex="-1" aria-labelledby="signOutModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><div class="signout-icon"><i class="bi bi-box-arrow-right"></i></div><h4 class="fw-bold mb-2" id="signOutModalLabel">Sign out of TRAVIS?</h4><p class="text-muted mb-4">Are you sure you want to end your current session? You will need to sign in again to access the Treasurer Portal.</p><div class="d-flex gap-3 justify-content-center"><button type="button" class="btn btn-light px-4" data-bs-dismiss="modal"><i class="bi bi-x-circle me-2"></i>Cancel</button><form method="post" action="' . esc(app_url('logout.php')) . '"><input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '"><button class="btn btn-signout px-4" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button></form></div></div></div></div></div>';
    if ($showLoginSuccess) {
        echo '<div class="modal fade login-success-modal" id="loginSuccessModal" tabindex="-1" aria-labelledby="loginSuccessModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><div class="login-success-icon"><i class="bi bi-check-lg"></i></div><h4 class="fw-bold mb-2" id="loginSuccessModalLabel">Login Successful!</h4><p class="text-muted mb-4">Welcome back, <strong>' . esc($loginName) . '</strong>. You have successfully signed in to the TRAVIS Treasurer Portal.</p><button type="button" class="btn btn-login-success px-5" data-bs-dismiss="modal"><i class="bi bi-speedometer2 me-2"></i>Continue to Dashboard</button></div></div></div></div>';
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
