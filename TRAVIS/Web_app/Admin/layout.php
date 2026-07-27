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
    echo '<div class="sidebar-brand"><img class="municipal-seal" src="' . esc(asset_url('assets/images/nasugbu-seal.jpg')) . '" alt="Municipality of Nasugbu seal"><div class="municipal-brand-copy"><div class="brand-logo-wordmark">NASUGBU · TMO</div><small>Traffic Management Office</small></div></div>';
    foreach ($items as $section => $links) {
        echo '<div class="nav-section">' . esc($section) . '</div><ul class="nav flex-column">';
        foreach ($links as [$href,$label,$icon,$key]) {
            $class = $active === $key ? 'nav-link active' : 'nav-link';
            echo '<li><a class="' . $class . '" href="' . esc($href) . '" title="' . esc($label) . '"><i class="bi ' . esc($icon) . '"></i> <span class="nav-label">' . esc($label) . '</span></a></li>';
        }
        echo '</ul>';
    }
    echo '</aside><div class="backdrop" id="backdrop"></div>';
}

function page_start(string $title, string $active = '', string $search = 'Search...', string $subtitle = '', bool $showSearch = true): void {
    ensure_ml_api_running();
    $admin = current_admin();
    $name = $admin['full_name'] ?? 'System Admin';
    $init = initials($name);
    $styleFile = dirname(__DIR__, 2) . '/css/style.css';
    $styleVersion = is_file($styleFile) ? (string) filemtime($styleFile) : '1';
    $municipalStyleFile = dirname(__DIR__, 2) . '/css/municipal-portals.css';
    $municipalStyleVersion = is_file($municipalStyleFile) ? (string) filemtime($municipalStyleFile) : '1';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1" />';
    echo '<title>TRAVIS — ' . esc($title) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />';
    echo '<link href="' . esc(asset_url('css/style.css')) . '?v=' . esc($styleVersion) . '" rel="stylesheet" />';
    echo '<style>.empty-state{border:1px dashed #d1d5db;border-radius:14px;padding:24px;text-align:center;color:#6b7280;background:#f9fafb}.camera-stage{min-height:420px;background:linear-gradient(135deg,#0f172a,#1e3a8a);border-radius:18px;display:flex;align-items:center;justify-content:center;color:#fff;position:relative;overflow:hidden}.camera-stage video{width:100%;height:100%;max-height:480px;object-fit:contain;background:#000}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.mini-metric{background:#fff;border:1px solid #edf2f7;border-radius:14px;padding:14px}.mini-metric small{color:#64748b}.mini-metric strong{display:block;font-size:1.3rem}.nav-link.active{background:rgba(255,255,255,.12);color:#fff}.sidebar,.sidebar .nav-link,.sidebar .nav-section{font-family:"Poppins",sans-serif}.sidebar-brand{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:4px;height:84px;padding:0 20px;box-sizing:border-box}.topbar{height:84px;box-sizing:border-box;display:flex;align-items:center}.brand-logo-wordmark{font-family:"Poppins",sans-serif;font-weight:800;font-size:1.8rem;letter-spacing:.5px;line-height:1;background:linear-gradient(90deg,#ffffff 0%,#bfdbfe 45%,#3b82f6 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent;display:inline-block}.sidebar-brand small{color:#94a3b8;font-family:"Poppins",sans-serif;font-size:.72rem;letter-spacing:.3px}</style>';
    echo '<link href="' . esc(asset_url('css/municipal-portals.css')) . '?v=' . esc($municipalStyleVersion) . '" rel="stylesheet" />';
    echo '</head><body class="admin-dashboard municipal-portal">';
    sidebar($active);
    echo '<div class="main-wrapper"><header class="topbar"><button type="button" class="btn sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="true"><i class="bi bi-list" aria-hidden="true"></i></button>';
    echo '<div class="municipal-topbar-scene"><div class="municipal-topbar-copy"><strong>Municipality of Nasugbu</strong><small>Traffic Management Office · Public Service Portal</small></div></div>';
    echo '<div class="ms-auto d-flex align-items-center topbar-actions"><small class="topbar-clock d-none d-md-inline-flex" id="liveClock"></small>';
    $alertCount = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE status = 'active'", 0);
    echo '<a href="' . esc(app_url('alerts.php')) . '" class="btn position-relative bell topbar-notification" aria-label="Open alerts"><i class="bi bi-bell"></i>';
    if ((int)$alertCount > 0) echo '<span class="badge bg-danger">' . num($alertCount) . '</span>';
    echo '</a><div class="dropdown"><button class="btn topbar-profile d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-label="Open account menu"><span class="avatar">' . esc($init) . '</span><span class="d-none d-md-inline small fw-semibold">' . esc($name) . '</span></button>';
    echo '<ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li><li><a class="dropdown-item" href="' . esc(app_url('settings.php')) . '"><i class="bi bi-gear me-2"></i>Settings</a></li><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#signOutModal"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button></li></ul></div></div></header><main class="content">';
}

function page_end(bool $chart = false): void {
    $showLoginSuccess = !empty($_SESSION['login_success']);
    $loginName = (string)($_SESSION['user']['name'] ?? 'User');
    unset($_SESSION['login_success']);

    echo '</main></div>';
    echo '<div class="modal fade auth-prompt signout-modal" id="signOutModal" tabindex="-1" aria-labelledby="signOutModalLabel" aria-describedby="signOutModalDescription" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="auth-prompt-accent"></div><div class="modal-body text-center"><div class="auth-prompt-brand"><span class="auth-prompt-brand-mark">T</span><span>TRAVIS SECURITY</span></div><div class="auth-prompt-icon signout-icon"><i class="bi bi-box-arrow-right"></i></div><span class="auth-prompt-eyebrow">Session control</span><h4 id="signOutModalLabel">Sign out of TRAVIS?</h4><p id="signOutModalDescription">Your current session will end securely. You will need to enter your credentials again to access the dashboard.</p><div class="auth-prompt-actions"><button type="button" class="btn auth-prompt-cancel" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i><span>Stay signed in</span></button><form method="post" action="' . esc(app_url('logout.php')) . '"><input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '"><button class="btn btn-signout" type="submit"><span>Sign out securely</span><i class="bi bi-box-arrow-right"></i></button></form></div><small class="auth-prompt-note"><i class="bi bi-shield-lock"></i> Your account and session data remain protected.</small></div></div></div></div>';
    echo <<<'HTML'
<div class="modal fade actionable-alert-modal" id="actionableAlertModal" tabindex="-1" aria-labelledby="actionableAlertType" aria-describedby="actionableAlertMessage" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="actionable-alert-accent"></div>
    <button type="button" class="actionable-alert-close" data-bs-dismiss="modal" aria-label="Dismiss alert"><i class="bi bi-x-lg"></i></button>
    <div class="modal-body">
      <div class="actionable-alert-icon"><i id="actionableAlertIcon" class="bi bi-exclamation-octagon-fill"></i></div>
      <span class="actionable-alert-eyebrow">TRAVIS operational alert</span><h3 id="actionableAlertType">Critical traffic alert</h3>
      <p id="actionableAlertMessage"></p><small id="actionableAlertTime" class="d-block mb-4"></small>
      <div class="actionable-alert-actions">
        <a class="btn actionable-view-btn" href="/TRAVIS/Web_app/Admin/monitoring.php"><i class="bi bi-camera-video"></i><span>View live</span></a>
        <button type="button" class="btn actionable-ack-btn" id="acknowledgeActionableAlert"><i class="bi bi-check2-circle"></i><span>Acknowledge</span></button>
      </div>
      <small class="actionable-alert-note d-block mt-4" id="actionableAlertNote"><i class="bi bi-clock-history me-1"></i>Officer-absence reminders follow the configured alert cooldown.</small>
    </div>
  </div></div>
</div>
<style>
.actionable-alert-modal .modal-content{position:relative;overflow:hidden;border:1px solid rgba(16,47,73,.16);border-radius:18px;background:linear-gradient(145deg,#fff 0%,#f8fbfa 100%)!important;color:#10202c!important;box-shadow:0 18px 48px rgba(15,35,52,.24)}
.actionable-alert-modal{pointer-events:none}.actionable-alert-modal .modal-dialog{position:fixed;right:20px;bottom:20px;width:min(360px,calc(100vw - 40px));max-width:none;min-height:0;margin:0;pointer-events:auto;transform:translateY(18px) scale(.98)!important}.actionable-alert-modal.show .modal-dialog{transform:translateY(0) scale(1)!important}.actionable-alert-modal .modal-body{padding:18px!important;text-align:left!important}.actionable-alert-modal .actionable-alert-icon{width:44px;height:44px;border-radius:13px;font-size:21px;margin:0 0 12px;background:#fee2e2;color:#dc2626}.officer-alert-modal .actionable-alert-icon{background:#fff3d6;color:#b76a05}.actionable-alert-modal h3{font-size:1.05rem;line-height:1.3;margin:0 34px 7px 0!important}.actionable-alert-modal p{font-size:.82rem;line-height:1.5;margin:0 0 5px;color:#536b65}.actionable-alert-eyebrow{font-size:.62rem;letter-spacing:.12em;margin-bottom:5px;color:#087d78}.actionable-alert-modal #actionableAlertTime{font-size:.7rem;margin-bottom:14px!important}.actionable-alert-note{font-size:.65rem;margin-top:11px!important}.actionable-alert-close{position:absolute;top:12px;right:12px;z-index:2;width:30px;height:30px;border:0;border-radius:9px;background:rgba(16,47,73,.07);color:#526b64;display:grid;place-items:center;transition:.18s ease}.actionable-alert-close:hover{background:rgba(16,47,73,.13);color:#102f49}.actionable-alert-actions{display:grid;grid-template-columns:1fr 1.2fr;gap:8px}.actionable-alert-actions .btn{min-height:38px;border-radius:10px;font-size:.73rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px}.actionable-view-btn{border:1px solid rgba(8,125,120,.3)!important;color:#087d78!important;background:#fff!important}.actionable-ack-btn{border:0!important;background:linear-gradient(135deg,#102f49,#087d78)!important;color:#fff!important}.critical-alert-modal .actionable-ack-btn{background:linear-gradient(135deg,#b91c1c,#dc2626)!important}@media(max-width:575.98px){.actionable-alert-modal .modal-dialog{right:10px;bottom:10px;width:calc(100vw - 20px)}.actionable-alert-modal .modal-body{padding:16px!important}}
.actionable-alert-accent{height:6px;background:linear-gradient(90deg,#dc2626,#f97316)}.officer-alert-modal .actionable-alert-accent{background:linear-gradient(90deg,#d97706,#f59e0b)}
.actionable-alert-icon{width:76px;height:76px;margin:0 auto 18px;border-radius:22px;display:grid;place-items:center;background:#fee2e2;color:#dc2626;font-size:34px}.officer-alert-modal .actionable-alert-icon{background:#fef3c7;color:#b45309}
.actionable-alert-eyebrow{display:block;color:#64748b;font-size:.72rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase;margin-bottom:8px}.actionable-alert-modal h3{font-weight:800;color:#10202c;margin-bottom:12px}.actionable-alert-modal p{color:#475569;line-height:1.65}.actionable-alert-modal #actionableAlertTime,.actionable-alert-note{color:#64748b}
</style>
HTML;
    if ($showLoginSuccess) {
        echo '<div class="modal fade auth-prompt login-success-modal" id="loginSuccessModal" tabindex="-1" aria-labelledby="loginSuccessModalLabel" aria-describedby="loginSuccessModalDescription" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="auth-prompt-accent"></div><div class="modal-body text-center"><div class="auth-prompt-brand"><span class="auth-prompt-brand-mark">T</span><span>TRAVIS COMMAND CENTER</span></div><div class="auth-prompt-icon login-success-icon"><i class="bi bi-check-lg"></i></div><span class="auth-prompt-eyebrow">Identity verified</span><h4 id="loginSuccessModalLabel">Welcome back, ' . esc($loginName) . '!</h4><p id="loginSuccessModalDescription">You have signed in successfully. Your secure dashboard and live traffic intelligence are ready.</p><button type="button" class="btn btn-login-success" data-bs-dismiss="modal"><span>Open dashboard</span><i class="bi bi-arrow-right"></i></button><small class="auth-prompt-note"><i class="bi bi-shield-check"></i> Secure administrator session active.</small></div></div></div></div>';
    }
    $municipalStyleFile = dirname(__DIR__, 2) . '/css/municipal-portals.css';
    $municipalStyleVersion = is_file($municipalStyleFile) ? (string) filemtime($municipalStyleFile) : '1';
    echo '<link href="' . esc(asset_url('css/municipal-portals.css')) . '?v=' . esc($municipalStyleVersion) . '" rel="stylesheet" />';
    $bootstrapScriptFile = dirname(__DIR__, 2) . '/assets/vendor/bootstrap/bootstrap.bundle.min.js';
    $bootstrapScriptVersion = is_file($bootstrapScriptFile) ? (string) filemtime($bootstrapScriptFile) : '1';
    echo '<script src="' . esc(asset_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) . '?v=' . esc($bootstrapScriptVersion) . '"></script>';
    if ($chart) echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    $appScriptFile = dirname(__DIR__, 2) . '/js/app.js';
    $appScriptVersion = is_file($appScriptFile) ? (string) filemtime($appScriptFile) : '1';
    echo '<script src="' . esc(asset_url('js/app.js')) . '?v=' . esc($appScriptVersion) . '"></script>';
    $alertScriptFile = dirname(__DIR__, 2) . '/js/actionable-alerts.js';
    $alertScriptVersion = is_file($alertScriptFile) ? (string) filemtime($alertScriptFile) : '1';
    echo '<script src="' . esc(asset_url('js/actionable-alerts.js')) . '?v=' . esc($alertScriptVersion) . '"></script>';
    if ($showLoginSuccess) {
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var element=document.getElementById("loginSuccessModal");if(element){bootstrap.Modal.getOrCreateInstance(element).show();}});</script>';
    }
    echo '</body></html>';
}

function empty_state(string $message): void {
    echo '<div class="empty-state"><i class="bi bi-inbox fs-3 d-block mb-2"></i>' . esc($message) . '</div>';
}
