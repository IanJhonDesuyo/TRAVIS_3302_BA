<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Admin/db_connect.php';

function public_escape(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function public_image_url(?string $path): string {
    if (!$path) return '';
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    return '../../' . $normalized;
}

function public_excerpt(string $content, int $length = 170): string {
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');
    return mb_strimwidth($plain, 0, $length, '…', 'UTF-8');
}

function public_type_icon(string $type): string {
    return match ($type) {
        'traffic advisory' => 'bi-signpost-split',
        'road closure' => 'bi-cone-striped',
        'emergency notice' => 'bi-exclamation-triangle',
        'event' => 'bi-calendar-event',
        'tmo activity' => 'bi-people',
        default => 'bi-megaphone',
    };
}

$allowedTypes = ['traffic advisory', 'tmo activity', 'public notice', 'event', 'road closure', 'emergency notice'];
$search = trim((string)($_GET['search'] ?? ''));
$type = strtolower(trim((string)($_GET['type'] ?? '')));
if (!in_array($type, $allowedTypes, true)) $type = '';

$where = ["a.status = 'published'", 'a.publish_date <= NOW()', '(a.expiry_date IS NULL OR a.expiry_date >= NOW())'];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(a.title LIKE ? OR a.content LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($type !== '') {
    $where[] = 'a.announcement_type = ?';
    $params[] = $type;
    $types .= 's';
}

$sql = 'SELECT a.*, u.full_name AS author_name
        FROM public_announcements a
        LEFT JOIN users u ON u.user_id = a.created_by
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.publish_date DESC, a.announcement_id DESC
        LIMIT 50';
$stmt = $conn->prepare($sql);
$announcements = [];
if ($stmt) {
    if ($params) $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) $announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$featured = $announcements[0] ?? null;
$remaining = $featured ? array_slice($announcements, 1) : [];

$trafficSettings = [
    'tomtom_api_key' => '',
    'tomtom_center_latitude' => '14.07395',
    'tomtom_center_longitude' => '120.63267',
    'tomtom_map_zoom' => '13',
];
$settingsTable = $conn->query("SHOW TABLES LIKE 'system_settings'");
if ($settingsTable && $settingsTable->num_rows > 0) {
    $keys = "'tomtom_api_key','tomtom_center_latitude','tomtom_center_longitude','tomtom_map_zoom'";
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($keys)");
    while ($result && ($row = $result->fetch_assoc())) {
        if (array_key_exists($row['setting_key'], $trafficSettings)) $trafficSettings[$row['setting_key']] = (string)$row['setting_value'];
    }
}
$tomtomEnabled = strlen($trafficSettings['tomtom_api_key']) >= 20;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Official traffic advisories, public notices, and announcements from the Traffic Management Office.">
  <title>TMO Public Information | TRAVIS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <?php if ($tomtomEnabled): ?><link rel="stylesheet" href="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/maps/maps.css"><?php endif; ?>
  <style>
    :root{--navy:#082f5b;--blue:#0f65b5;--sky:#eaf5ff;--teal:#0b8c83;--ink:#132238;--muted:#66758a;--line:#dce7f1;--paper:#fff;--bg:#f5f9fc}
    *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,system-ui,sans-serif;line-height:1.65}a{color:inherit}.container{width:min(1160px,calc(100% - 32px));margin:auto}
    .top-strip{background:var(--navy);color:#d9ecff;font-size:.82rem}.top-strip .container{display:flex;justify-content:space-between;gap:20px;padding:8px 0}.top-strip span{display:flex;align-items:center;gap:7px}
    header{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20;box-shadow:0 4px 18px rgba(8,47,91,.05)}nav{display:flex;align-items:center;justify-content:space-between;padding:15px 0}.brand{display:flex;align-items:center;gap:12px;text-decoration:none}.brand img{width:50px;height:50px;border-radius:12px;object-fit:cover}.brand strong{display:block;color:var(--navy);font-size:1.08rem}.brand small{display:block;color:var(--muted);font-size:.76rem}.nav-links{display:flex;align-items:center;gap:24px}.nav-links a{text-decoration:none;font-weight:600;font-size:.9rem}.nav-links a:hover{color:var(--blue)}
    .hero{background:linear-gradient(120deg,rgba(8,47,91,.98),rgba(15,101,181,.9));color:#fff;padding:70px 0 78px;position:relative;overflow:hidden}.hero:after{content:"";position:absolute;width:440px;height:440px;border:80px solid rgba(255,255,255,.06);border-radius:50%;right:-130px;top:-190px}.hero-inner{position:relative;z-index:1;max-width:760px}.eyebrow{text-transform:uppercase;letter-spacing:.13em;font-size:.78rem;font-weight:800;color:#8edcff}.hero h1{font-size:clamp(2.1rem,5vw,4rem);line-height:1.08;margin:12px 0 17px}.hero p{font-size:1.05rem;color:#d8eaff;max-width:650px;margin:0}
    .filters{position:relative;margin:-30px auto 46px;z-index:5;background:#fff;padding:16px;border:1px solid var(--line);border-radius:18px;box-shadow:0 15px 40px rgba(8,47,91,.12);display:grid;grid-template-columns:1fr 230px auto auto;gap:10px}.control{width:100%;border:1px solid #cbd9e6;border-radius:11px;padding:12px 14px;font:inherit;background:#fff}.control:focus{outline:3px solid rgba(15,101,181,.13);border-color:var(--blue)}.button{border:0;border-radius:11px;padding:12px 20px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}.button-primary{background:var(--blue);color:#fff}.button-light{background:#edf4fa;color:var(--navy)}
    .section-heading{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:20px}.section-heading h2{font-size:1.7rem;margin:0}.section-heading p{color:var(--muted);margin:3px 0 0}.count{color:var(--muted);font-size:.88rem}
    .featured{display:grid;grid-template-columns:1.1fr .9fr;background:#fff;border:1px solid var(--line);border-radius:22px;overflow:hidden;box-shadow:0 12px 35px rgba(8,47,91,.08);margin-bottom:32px}.featured-media{min-height:390px;background:linear-gradient(135deg,#d9efff,#c9e9e5);display:grid;place-items:center;overflow:hidden}.featured-media img{width:100%;height:100%;object-fit:cover}.placeholder-icon{font-size:5rem;color:rgba(15,101,181,.5)}.featured-copy{padding:40px;display:flex;flex-direction:column;justify-content:center}.badge{align-self:flex-start;display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:6px 11px;background:var(--sky);color:var(--blue);font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.featured h2{font-size:clamp(1.6rem,3vw,2.35rem);line-height:1.2;margin:17px 0 12px}.meta{display:flex;flex-wrap:wrap;gap:13px;color:var(--muted);font-size:.82rem}.excerpt{color:#526176;margin:20px 0 24px}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding-bottom:70px}.card{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden;display:flex;flex-direction:column;transition:.2s ease}.card:hover{transform:translateY(-4px);box-shadow:0 15px 35px rgba(8,47,91,.1)}.card-media{height:190px;background:linear-gradient(135deg,#e3f2ff,#d9f2ef);display:grid;place-items:center;overflow:hidden}.card-media img{width:100%;height:100%;object-fit:cover}.card-media .placeholder-icon{font-size:3rem}.card-body{padding:22px;display:flex;flex-direction:column;flex:1}.card h3{font-size:1.12rem;line-height:1.35;margin:13px 0 9px}.card .excerpt{font-size:.9rem;margin:14px 0 20px}.read-more{margin-top:auto;color:var(--blue);font-weight:700;text-decoration:none;font-size:.9rem}.read-more:hover{text-decoration:underline}
    .empty{background:#fff;border:1px dashed #bdcfdf;border-radius:20px;text-align:center;padding:65px 25px;margin-bottom:70px}.empty i{display:block;font-size:3rem;color:var(--blue)}.empty h2{margin:12px 0 4px}.empty p{color:var(--muted);margin:0}
    footer{background:#061f3b;color:#d4e5f6;padding:38px 0}.footer-inner{display:flex;justify-content:space-between;align-items:center;gap:24px}.footer-inner p{color:#9fb6ca;margin:4px 0 0;font-size:.86rem}
    dialog{width:min(760px,calc(100% - 28px));max-height:88vh;border:0;border-radius:20px;padding:0;box-shadow:0 25px 80px rgba(0,0,0,.3)}dialog::backdrop{background:rgba(3,20,38,.7)}.dialog-image{width:100%;max-height:330px;object-fit:cover}.dialog-body{padding:30px}.dialog-body h2{line-height:1.25;margin:14px 0 8px}.dialog-content{white-space:pre-wrap;margin-top:24px;color:#394b60}.dialog-close{position:absolute;right:14px;top:14px;width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,.95);cursor:pointer;font-size:1.1rem}
    @media(max-width:850px){.nav-links{display:none}.filters{grid-template-columns:1fr 1fr}.filters input{grid-column:1/-1}.featured{grid-template-columns:1fr}.featured-media{min-height:260px}.grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:560px){.top-strip .container span:last-child{display:none}.hero{padding:50px 0 64px}.filters{grid-template-columns:1fr}.filters input{grid-column:auto}.grid{grid-template-columns:1fr}.featured-copy{padding:25px}.footer-inner{align-items:flex-start;flex-direction:column}}

    /* Civic-portal visual direction inspired by Naga's People's Budget Portal. */
    :root{--navy:#102a43;--blue:#176b87;--sky:#e9f2f1;--teal:#138a7e;--orange:#f2a33a;--cream:#f7f5ef;--ink:#172b3a;--muted:#66756f;--line:#d9dfd8;--bg:#f7f5ef}
    .top-strip{color:#dfeae7;font-size:.78rem;letter-spacing:.02em}
    header{background:rgba(255,255,255,.96);backdrop-filter:blur(12px);box-shadow:none}.brand img{width:48px;height:48px;border-radius:50%;border:2px solid #e1e8e4}.nav-links{gap:28px}.nav-links a{font-size:.82rem;font-weight:700}.nav-pill{background:var(--orange);padding:9px 15px;border-radius:999px;color:var(--navy)!important}
    .hero{background:var(--cream);color:var(--ink);padding:78px 0 108px;border-bottom:1px solid var(--line)}.hero:before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(16,42,67,.12) 1px,transparent 1px);background-size:22px 22px;mask-image:linear-gradient(90deg,transparent,black)}.hero:after{width:520px;height:520px;border:0;background:var(--orange);right:-330px;top:-300px}.hero-inner{max-width:none;display:grid;grid-template-columns:1.25fr .75fr;gap:72px;align-items:center}.eyebrow{color:var(--teal);display:flex;align-items:center;gap:10px}.eyebrow:before{content:"";width:34px;height:3px;background:var(--orange)}.hero h1{font-size:clamp(2.7rem,6vw,5.5rem);letter-spacing:-.055em;line-height:.96;margin:20px 0 25px}.hero h1 span{color:var(--blue)}.hero p{color:#52645f}.hero-panel{background:var(--navy);color:#fff;padding:30px;box-shadow:16px 16px 0 var(--orange)}.hero-panel-label{color:#8fd5cd;text-transform:uppercase;font-size:.7rem;font-weight:800;letter-spacing:.13em}.hero-panel strong{display:block;font-size:3.2rem;line-height:1;margin:12px 0 8px}.hero-panel p{color:#cfddd9;font-size:.84rem}.hero-panel hr{border:0;border-top:1px solid rgba(255,255,255,.16);margin:24px 0}.hero-panel-status{display:flex;align-items:center;gap:10px;font-weight:700;font-size:.85rem}.pulse{width:9px;height:9px;border-radius:50%;background:#52d4a8;box-shadow:0 0 0 6px rgba(82,212,168,.14)}
    .section-heading h2{font-size:clamp(1.7rem,3vw,2.5rem);letter-spacing:-.04em}.count{text-transform:uppercase;letter-spacing:.08em;font-size:.78rem;font-weight:700}.featured{border-radius:3px;box-shadow:10px 10px 0 #dce8e5;margin-bottom:38px}.badge{border-radius:2px}.card{border-radius:3px}.card:hover{box-shadow:7px 7px 0 var(--orange)}
    @media(max-width:850px){.hero-inner{grid-template-columns:1fr;gap:45px}.hero-panel{max-width:430px}}
    .traffic-section{padding:72px 0;background:#fff;border-bottom:1px solid var(--line)}.traffic-heading{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:25px}.traffic-heading h2{font-size:clamp(2rem,4vw,3.3rem);letter-spacing:-.05em;line-height:1;margin:8px 0}.traffic-heading p{color:var(--muted);max-width:650px;margin:0}.live-chip{display:flex;align-items:center;gap:9px;background:#eaf8f3;color:#08745c;padding:8px 12px;border-radius:999px;font-size:.75rem;font-weight:800}.traffic-shell{display:grid;grid-template-columns:330px 1fr;min-height:560px;border:1px solid var(--line);box-shadow:10px 10px 0 #dce8e5}.route-panel{padding:24px;background:var(--cream);border-right:1px solid var(--line)}.route-panel h3{margin:0 0 5px}.route-panel>p{font-size:.82rem;color:var(--muted);margin:0 0 22px}.route-field{margin-bottom:14px}.route-field label{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;font-weight:800;margin-bottom:6px}.route-field input{width:100%;border:1px solid #cbd6d0;background:#fff;padding:11px 12px;font:inherit}.route-field input:focus{outline:3px solid rgba(19,138,126,.12);border-color:var(--teal)}.route-action{width:100%;margin-top:4px}.route-result{margin-top:22px;padding:18px;background:var(--navy);color:#fff;display:none}.route-result.show{display:block}.route-result-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.route-result small{display:block;color:#a9c5cf;font-size:.68rem;text-transform:uppercase}.route-result strong{font-size:1.25rem}.route-note{font-size:.7rem;color:#b9cbd1;margin:12px 0 0}.map-wrap{position:relative;min-height:560px}.traffic-map{position:absolute;inset:0}.map-legend{position:absolute;z-index:2;bottom:22px;left:22px;background:rgba(255,255,255,.94);padding:10px 13px;box-shadow:0 7px 22px rgba(16,42,67,.16);font-size:.72rem;display:flex;gap:13px}.legend-item{display:flex;align-items:center;gap:5px}.legend-dot{width:18px;height:4px}.map-setup{height:100%;min-height:560px;display:grid;place-items:center;text-align:center;padding:30px;background:repeating-linear-gradient(45deg,#f3f6f3,#f3f6f3 12px,#eef2ee 12px,#eef2ee 24px)}.map-setup i{font-size:3rem;color:var(--blue)}.map-setup h3{margin:10px 0 4px}.map-setup p{color:var(--muted);max-width:470px;margin:0}.route-error{color:#a52a2a;font-size:.78rem;margin-top:10px}.tomtom-attribution{font-size:.67rem;color:var(--muted);margin-top:12px}@media(max-width:850px){.traffic-shell{grid-template-columns:1fr}.route-panel{border-right:0;border-bottom:1px solid var(--line)}.map-wrap,.map-setup{min-height:430px}}@media(max-width:560px){.traffic-heading{align-items:flex-start;flex-direction:column}.traffic-section{padding:52px 0}.traffic-shell{margin:0 -16px}.map-wrap,.map-setup{min-height:360px}}
    .route-field{position:relative}.place-suggestions{position:absolute;z-index:15;left:0;right:0;top:100%;margin:3px 0 0;padding:5px;background:#fff;border:1px solid #cbd6d0;box-shadow:0 12px 28px rgba(16,42,67,.16);max-height:230px;overflow-y:auto;display:none}.place-suggestions.show{display:block}.place-suggestion{width:100%;border:0;background:#fff;text-align:left;padding:10px;cursor:pointer;font:inherit;color:var(--ink)}.place-suggestion:hover,.place-suggestion.active{background:var(--sky)}.place-suggestion strong{display:block;font-size:.82rem}.place-suggestion small{display:block;color:var(--muted);font-size:.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.place-searching{padding:10px;color:var(--muted);font-size:.75rem}.route-field input[aria-expanded="true"]{border-color:var(--teal)}
    .route-panel input:disabled,.route-panel button:disabled{opacity:.58;cursor:not-allowed}
  </style>
</head>
<body>
  <div class="top-strip"><div class="container"><span><i class="bi bi-shield-check"></i> Official TMO Public Information Portal</span><span><i class="bi bi-clock"></i> Updated <?= public_escape(date('F j, Y')) ?></span></div></div>
  <header><nav class="container"><a class="brand" href="index.php"><img src="../../assets/images/travis-logo.jpg" alt="TRAVIS logo"><span><strong>TRAVIS · TMO</strong><small>Traffic Management Office</small></span></a><div class="nav-links"><a href="index.php">Home</a><a href="#traffic">Traffic Map</a><a href="#announcements">Announcements</a><a class="nav-pill" href="#contact">Public Information</a></div></nav></header>

  <section class="hero"><div class="container hero-inner"><div><span class="eyebrow">A safer, informed community</span><h1>Traffic updates.<br><span>Made public.</span></h1><p>Your direct source for official traffic advisories, road notices, community activities, and emergency information from the Traffic Management Office.</p></div><aside class="hero-panel"><span class="hero-panel-label">Public information desk</span><strong><?= count($announcements) ?></strong><p>Active <?= count($announcements) === 1 ? 'announcement' : 'announcements' ?> available to the public right now.</p><hr><div class="hero-panel-status"><span class="pulse"></span>Official TMO information service</div></aside></div></section>

  <section class="traffic-section" id="traffic"><div class="container"><div class="traffic-heading"><div><span class="eyebrow">Plan before you travel</span><h2>Traffic conditions & route outlook</h2><p>See current congestion, reported incidents, and estimated travel conditions for a route and departure time within the coming days.</p></div><span class="live-chip"><span class="pulse"></span>Live traffic layer</span></div><div class="traffic-shell"><aside class="route-panel"><h3>Check a route</h3><p>Enter two places, then choose when you plan to leave.</p><form id="routeForm"><div class="route-field"><label for="routeOrigin">Starting point</label><input id="routeOrigin" required placeholder="e.g. Naga City Hall" autocomplete="off"></div><div class="route-field"><label for="routeDestination">Destination</label><input id="routeDestination" required placeholder="e.g. SM City Naga" autocomplete="off"></div><div class="route-field"><label for="routeDeparture">Departure date and time</label><input id="routeDeparture" type="datetime-local" required></div><button class="button button-primary route-action" type="submit" <?= !$tomtomEnabled ? 'disabled' : '' ?>><i class="bi bi-signpost-2"></i>Check traffic outlook</button><div class="route-error" id="routeError" role="alert"></div></form><div class="route-result" id="routeResult"><div class="route-result-grid"><div><small>Estimated time</small><strong id="routeTime">—</strong></div><div><small>Traffic delay</small><strong id="routeDelay">—</strong></div><div><small>Distance</small><strong id="routeDistance">—</strong></div><div><small>Outlook</small><strong id="routeLevel">—</strong></div></div><p class="route-note">Future estimates use historical traffic patterns. Actual conditions may change.</p></div><p class="tomtom-attribution">Traffic and routing data powered by TomTom.</p></aside><div class="map-wrap"><?php if ($tomtomEnabled): ?><div class="traffic-map" id="trafficMap"></div><div class="map-legend"><span class="legend-item"><span class="legend-dot" style="background:#39a96b"></span>Moving</span><span class="legend-item"><span class="legend-dot" style="background:#f2a33a"></span>Slow</span><span class="legend-item"><span class="legend-dot" style="background:#d64545"></span>Congested</span></div><?php else: ?><div class="map-setup"><div><i class="bi bi-map"></i><h3>Traffic map setup required</h3><p>An administrator can enable this map by adding a TomTom API key under Admin → Settings → TomTom Public Traffic Map.</p></div></div><?php endif; ?></div></div></div></section>

  <main id="announcements" class="container">
    <form class="filters" method="get"><input class="control" type="search" name="search" value="<?= public_escape($search) ?>" placeholder="Search announcements…" aria-label="Search announcements"><select class="control" name="type" aria-label="Announcement type"><option value="">All announcement types</option><?php foreach ($allowedTypes as $option): ?><option value="<?= public_escape($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= public_escape(ucwords($option)) ?></option><?php endforeach; ?></select><button class="button button-primary" type="submit"><i class="bi bi-search"></i>Search</button><?php if ($search !== '' || $type !== ''): ?><a class="button button-light" href="index.php">Clear</a><?php endif; ?></form>

    <div class="section-heading"><div><h2>Latest announcements</h2><p>Official information published by TMO.</p></div><span class="count"><?= count($announcements) ?> <?= count($announcements) === 1 ? 'announcement' : 'announcements' ?></span></div>

    <?php if (!$featured): ?>
      <section class="empty"><i class="bi bi-megaphone"></i><h2>No announcements found</h2><p><?= $search !== '' || $type !== '' ? 'Try changing your search or filter.' : 'Please check again soon for official TMO updates.' ?></p></section>
    <?php else: ?>
      <article class="featured"><?php $featuredImage = public_image_url($featured['image_path'] ?? null); ?><div class="featured-media"><?php if ($featuredImage): ?><img src="<?= public_escape($featuredImage) ?>" alt="<?= public_escape($featured['title']) ?>"><?php else: ?><i class="bi <?= public_escape(public_type_icon($featured['announcement_type'])) ?> placeholder-icon"></i><?php endif; ?></div><div class="featured-copy"><span class="badge"><i class="bi <?= public_escape(public_type_icon($featured['announcement_type'])) ?>"></i><?= public_escape(ucwords($featured['announcement_type'])) ?></span><h2><?= public_escape($featured['title']) ?></h2><div class="meta"><span><i class="bi bi-calendar3"></i> <?= public_escape(date('F j, Y · g:i A', strtotime($featured['publish_date']))) ?></span><span><i class="bi bi-patch-check"></i> Official TMO post</span></div><p class="excerpt"><?= public_escape(public_excerpt($featured['content'], 260)) ?></p><button class="button button-primary" type="button" onclick="document.getElementById('announcement-<?= (int)$featured['announcement_id'] ?>').showModal()">Read full announcement <i class="bi bi-arrow-right"></i></button></div></article>

      <?php if ($remaining): ?><div class="grid"><?php foreach ($remaining as $post): $image = public_image_url($post['image_path'] ?? null); ?><article class="card"><div class="card-media"><?php if ($image): ?><img src="<?= public_escape($image) ?>" alt="<?= public_escape($post['title']) ?>" loading="lazy"><?php else: ?><i class="bi <?= public_escape(public_type_icon($post['announcement_type'])) ?> placeholder-icon"></i><?php endif; ?></div><div class="card-body"><span class="badge"><i class="bi <?= public_escape(public_type_icon($post['announcement_type'])) ?>"></i><?= public_escape(ucwords($post['announcement_type'])) ?></span><h3><?= public_escape($post['title']) ?></h3><div class="meta"><span><i class="bi bi-calendar3"></i> <?= public_escape(date('M j, Y', strtotime($post['publish_date']))) ?></span></div><p class="excerpt"><?= public_escape(public_excerpt($post['content'])) ?></p><a class="read-more" href="#" onclick="event.preventDefault();document.getElementById('announcement-<?= (int)$post['announcement_id'] ?>').showModal()">Read announcement <i class="bi bi-arrow-right"></i></a></div></article><?php endforeach; ?></div><?php endif; ?>

      <?php foreach ($announcements as $post): $dialogImage = public_image_url($post['image_path'] ?? null); ?><dialog id="announcement-<?= (int)$post['announcement_id'] ?>"><?php if ($dialogImage): ?><img class="dialog-image" src="<?= public_escape($dialogImage) ?>" alt="<?= public_escape($post['title']) ?>"><?php endif; ?><button class="dialog-close" type="button" aria-label="Close" onclick="this.closest('dialog').close()"><i class="bi bi-x-lg"></i></button><div class="dialog-body"><span class="badge"><i class="bi <?= public_escape(public_type_icon($post['announcement_type'])) ?>"></i><?= public_escape(ucwords($post['announcement_type'])) ?></span><h2><?= public_escape($post['title']) ?></h2><div class="meta"><span><i class="bi bi-calendar3"></i> <?= public_escape(date('F j, Y · g:i A', strtotime($post['publish_date']))) ?></span><span><i class="bi bi-patch-check"></i> Official TMO post</span></div><div class="dialog-content"><?= public_escape($post['content']) ?></div></div></dialog><?php endforeach; ?>
    <?php endif; ?>
  </main>

  <footer id="contact"><div class="container footer-inner"><div><strong>TRAVIS · Traffic Management Office</strong><p>Providing reliable public traffic information for a safer community.</p></div><span><i class="bi bi-shield-check"></i> Official information portal</span></div></footer>
  <script>
  document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close()}));
  document.getElementById('routeOrigin').placeholder = 'e.g. Nasugbu Municipal Hall';
  document.getElementById('routeDestination').placeholder = 'e.g. Nasugbu Public Market';
  <?php if (!$tomtomEnabled): ?>
  document.querySelectorAll('#routeForm input, #routeForm button').forEach(control => control.disabled = true);
  document.getElementById('routeError').textContent = 'TomTom API key required. Ask an administrator to configure it in Admin → Settings.';
  <?php endif; ?>
  </script>
  <?php if ($tomtomEnabled): ?>
  <script src="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/maps/maps-web.min.js"></script>
  <script src="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/services/services-web.min.js"></script>
  <script>
  (() => {
    const key = <?= json_encode($trafficSettings['tomtom_api_key']) ?>;
    const center = [<?= json_encode((float)$trafficSettings['tomtom_center_longitude']) ?>, <?= json_encode((float)$trafficSettings['tomtom_center_latitude']) ?>];
    const map = tt.map({key,container:'trafficMap',center,zoom:<?= json_encode((int)$trafficSettings['tomtom_map_zoom']) ?>});
    map.addControl(new tt.NavigationControl());
    map.on('load', () => { map.showTrafficFlow(); map.showTrafficIncidents(); });

    const departure = document.getElementById('routeDeparture');
    const now = new Date(); now.setMinutes(now.getMinutes() - now.getTimezoneOffset() + 30);
    departure.min = new Date(Date.now() - new Date().getTimezoneOffset()*60000).toISOString().slice(0,16);
    departure.max = new Date(Date.now() - new Date().getTimezoneOffset()*60000 + 7*86400000).toISOString().slice(0,16);
    departure.value = now.toISOString().slice(0,16);

    async function findPlace(query, input) {
      if (input?.dataset.longitude && input?.dataset.latitude) return {lng:Number(input.dataset.longitude),lat:Number(input.dataset.latitude)};
      const response = await tt.services.fuzzySearch({key,query,center,limit:1,countrySet:'PH'});
      if (!response.results.length) throw new Error(`No location found for “${query}”.`);
      return response.results[0].position;
    }
    function enablePlaceSuggestions(input) {
      const list = document.createElement('div');
      list.className = 'place-suggestions'; list.setAttribute('role','listbox');
      input.parentElement.appendChild(list); input.setAttribute('autocomplete','off'); input.setAttribute('aria-expanded','false');
      let timer, results = [], active = -1, requestNumber = 0;
      const close = () => { list.classList.remove('show'); input.setAttribute('aria-expanded','false'); active = -1; };
      const choose = result => { input.value = result.address?.freeformAddress || result.poi?.name || ''; input.dataset.longitude = result.position.lng; input.dataset.latitude = result.position.lat; close(); };
      const render = () => {
        list.innerHTML = '';
        if (!results.length) { close(); return; }
        results.forEach((result,index) => {
          const button = document.createElement('button'); button.type = 'button'; button.className = `place-suggestion${index===active?' active':''}`; button.setAttribute('role','option');
          const address = result.address?.freeformAddress || [result.address?.municipality,result.address?.countrySubdivision].filter(Boolean).join(', ');
          const title = result.poi?.name || result.address?.streetName || result.address?.municipality || address;
          const strong = document.createElement('strong'); strong.textContent = title;
          const small = document.createElement('small'); small.textContent = address;
          button.append(strong,small); button.addEventListener('mousedown',event=>{event.preventDefault();choose(result)}); list.appendChild(button);
        });
        list.classList.add('show'); input.setAttribute('aria-expanded','true');
      };
      input.addEventListener('input', () => {
        delete input.dataset.longitude; delete input.dataset.latitude; clearTimeout(timer);
        const query = input.value.trim(); if (query.length < 2) { close(); return; }
        timer = setTimeout(async () => {
          const currentRequest = ++requestNumber;
          list.innerHTML = '<div class="place-searching">Finding nearby places…</div>'; list.classList.add('show');
          try { const response = await tt.services.fuzzySearch({key,query,center,radius:50000,limit:6,countrySet:'PH'}); if (currentRequest !== requestNumber) return; results = response.results; active = -1; render(); }
          catch (_) { if (currentRequest === requestNumber) close(); }
        },300);
      });
      input.addEventListener('keydown', event => {
        if (!list.classList.contains('show') || !results.length) return;
        if (event.key === 'ArrowDown') { event.preventDefault(); active=(active+1)%results.length; render(); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); active=(active-1+results.length)%results.length; render(); }
        else if (event.key === 'Enter' && active >= 0) { event.preventDefault(); choose(results[active]); }
        else if (event.key === 'Escape') close();
      });
      input.addEventListener('blur',()=>setTimeout(close,120));
    }
    const originInput = document.getElementById('routeOrigin');
    const destinationInput = document.getElementById('routeDestination');
    enablePlaceSuggestions(originInput); enablePlaceSuggestions(destinationInput);
    const minutes = seconds => `${Math.floor(seconds/3600) ? Math.floor(seconds/3600)+' hr ' : ''}${Math.round((seconds%3600)/60)} min`;
    document.getElementById('routeForm').addEventListener('submit', async event => {
      event.preventDefault();
      const error = document.getElementById('routeError'); error.textContent = '';
      const button = event.currentTarget.querySelector('button'); button.disabled = true; button.innerHTML = '<i class="bi bi-arrow-repeat"></i> Calculating…';
      try {
        const [origin,destination] = await Promise.all([findPlace(originInput.value,originInput),findPlace(destinationInput.value,destinationInput)]);
        const response = await tt.services.calculateRoute({key,locations:[origin,destination],departAt:new Date(departure.value).toISOString(),traffic:true,travelMode:'car',routeType:'fastest'});
        const route = response.routes[0], summary = route.summary, delay = Math.max(0,summary.trafficDelayInSeconds || summary.travelTimeInSeconds-(summary.noTrafficTravelTimeInSeconds || summary.travelTimeInSeconds));
        document.getElementById('routeTime').textContent = minutes(summary.travelTimeInSeconds);
        document.getElementById('routeDelay').textContent = delay > 30 ? `+${minutes(delay)}` : 'Minimal';
        document.getElementById('routeDistance').textContent = `${(summary.lengthInMeters/1000).toFixed(1)} km`;
        document.getElementById('routeLevel').textContent = delay > 900 ? 'Heavy' : delay > 300 ? 'Moderate' : 'Light';
        document.getElementById('routeResult').classList.add('show');
        const geojson = response.toGeoJson();
        if (map.getSource('public-route')) { map.getSource('public-route').setData(geojson); } else { map.addSource('public-route',{type:'geojson',data:geojson});map.addLayer({id:'public-route',type:'line',source:'public-route',paint:{'line-color':'#102a43','line-width':6,'line-opacity':.9}}); }
        const bounds = new tt.LngLatBounds(); geojson.features[0].geometry.coordinates.forEach(point=>bounds.extend(point)); map.fitBounds(bounds,{padding:55});
      } catch (routeError) { error.textContent = routeError.message || 'Unable to calculate this route. Check the locations and try again.'; }
      finally { button.disabled = false; button.innerHTML = '<i class="bi bi-signpost-2"></i> Check traffic outlook'; }
    });
  })();
  </script>
  <?php endif; ?>
</body>
</html>
    