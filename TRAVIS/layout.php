<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function sidebar(string $active = ''): void {
    $items = [
        'Overview' => [
            ['index.php','Dashboard','bi-speedometer2','dashboard'],
            ['monitoring.php','Live Monitoring','bi-camera-video','monitoring'],
            ['analytics.php','Analytics','bi-graph-up','analytics'],
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
    echo '<div class="sidebar-brand"><div class="logo">TRAVIS</div><div><h5>TRAVIS</h5><small>Traffic Violation Analytics</small></div></div>';
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
    $admin = current_admin();
    $name = $admin['full_name'] ?? 'System Admin';
    $init = initials($name);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8" />';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1" />';
    echo '<title>TRAVIS — ' . esc($title) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />';
    echo '<link href="css/style.css" rel="stylesheet" />';
    echo '<style>.empty-state{border:1px dashed #d1d5db;border-radius:14px;padding:24px;text-align:center;color:#6b7280;background:#f9fafb}.camera-stage{min-height:420px;background:linear-gradient(135deg,#0f172a,#1e3a8a);border-radius:18px;display:flex;align-items:center;justify-content:center;color:#fff;position:relative;overflow:hidden}.camera-stage video{width:100%;height:100%;max-height:480px;object-fit:contain;background:#000}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.mini-metric{background:#fff;border:1px solid #edf2f7;border-radius:14px;padding:14px}.mini-metric small{color:#64748b}.mini-metric strong{display:block;font-size:1.3rem}.nav-link.active{background:rgba(255,255,255,.12);color:#fff}</style>';
    echo '</head><body>';
    sidebar($active);
    echo '<div class="main-wrapper"><header class="topbar"><button class="btn btn-light d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>';
    echo '<div class="search"><i class="bi bi-search"></i><input class="form-control" placeholder="' . esc($search) . '" /></div>';
    echo '<div class="ms-auto d-flex align-items-center gap-3"><small class="text-muted d-none d-md-block" id="liveClock"></small>';
    $alertCount = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE status = 'active'", 0);
    echo '<a href="alerts.php" class="btn btn-light position-relative bell"><i class="bi bi-bell"></i>';
    if ((int)$alertCount > 0) echo '<span class="badge bg-danger">' . num($alertCount) . '</span>';
    echo '</a><div class="dropdown"><button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown"><span class="avatar">' . esc($init) . '</span><span class="d-none d-md-inline small fw-semibold">' . esc($name) . '</span></button>';
    echo '<ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li><li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="#"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li></ul></div></div></header><main class="content">';
}

function page_end(bool $chart = false): void {
    echo '</main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    if ($chart) echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    echo '<script src="js/app.js"></script></body></html>';
}

function empty_state(string $message): void {
    echo '<div class="empty-state"><i class="bi bi-inbox fs-3 d-block mb-2"></i>' . esc($message) . '</div>';
}
