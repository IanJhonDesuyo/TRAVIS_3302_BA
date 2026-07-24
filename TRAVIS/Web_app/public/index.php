<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Admin/db_connect.php';

function public_escape(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function public_image_url(?string $path): string {
    if (!$path) return '';
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (!is_file($absolute)) return '';
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
    /* ============================================================
       TOKENS — Naga civic-portal identity: deep navy, working teal,
       signal orange, warm paper. Hard offset shadows + a dot-grid
       hero are the signature; everything else stays quiet.
       ============================================================ */
    :root{
      --navy:#102a43;      --navy-ink:#0b1e30;
      --teal:#12746a;      --teal-light:#e5f1ef;
      --orange:#e6952e;    --orange-light:#fdf1de;
      --cream:#f7f5ee;     --paper:#ffffff;
      --ink:#16242f;       --muted:#5f6f6a;
      --line:#dfe3da;      --danger:#b5342a;
      --shadow-color:#d9e0d6;
      --radius-sm:4px; --radius-md:8px; --radius-lg:14px;
      --space-1:4px; --space-2:8px; --space-3:12px; --space-4:16px;
      --space-5:24px; --space-6:32px; --space-7:48px; --space-8:64px;
      --container:1180px;
    }

    /* ============================================================
       RESET & BASE
       ============================================================ */
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;background:var(--cream);color:var(--ink);font-family:'Inter',system-ui,sans-serif;line-height:1.65;-webkit-font-smoothing:antialiased}
    a{color:inherit}
    img{max-width:100%;display:block}
    button{font-family:inherit}
    .container{width:min(var(--container),calc(100% - 40px));margin:auto}
    :focus-visible{outline:3px solid var(--orange);outline-offset:2px}
    .visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
    @media(prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important;scroll-behavior:auto!important}}

    /* ============================================================
       SHARED PIECES — buttons, form controls, badges
       ============================================================ */
    .button{border:1px solid transparent;border-radius:var(--radius-sm);padding:12px 20px;font:inherit;font-weight:700;font-size:.92rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:transform .15s ease,box-shadow .15s ease,background-color .15s ease}
    .button-primary{background:var(--navy);color:#fff}
    .button-primary:hover{background:var(--navy-ink);box-shadow:4px 4px 0 var(--orange);transform:translate(-2px,-2px)}
    .button-light{background:var(--paper);color:var(--navy);border-color:var(--line)}
    .button-light:hover{border-color:var(--navy);background:var(--teal-light)}
    .button:disabled{opacity:.5;cursor:not-allowed;box-shadow:none;transform:none}

    .control{width:100%;border:1px solid #c7cec2;border-radius:var(--radius-sm);padding:12px 14px;font:inherit;background:var(--paper);color:var(--ink)}
    .control:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(18,116,106,.14)}

    .badge{align-self:flex-start;display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:6px 12px;background:var(--teal-light);color:var(--teal);font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}

    /* ============================================================
       TOP STRIP + HEADER
       ============================================================ */
    .top-strip{background:var(--navy-ink);color:#c9d8d3;font-size:.78rem;letter-spacing:.02em}
    .top-strip .container{display:flex;justify-content:space-between;gap:20px;padding:8px 0}
    .top-strip span{display:flex;align-items:center;gap:7px}

    header{background:rgba(247,245,238,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:30}
    nav{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
    .brand{display:flex;align-items:center;gap:12px;text-decoration:none}
    .brand img{width:44px;height:44px;border-radius:50%;border:2px solid var(--paper);box-shadow:0 0 0 1px var(--line);object-fit:cover}
    .brand strong{display:block;color:var(--navy);font-size:1.04rem;letter-spacing:-.01em}
    .brand small{display:block;color:var(--muted);font-size:.74rem}
    .nav-links{display:flex;align-items:center;gap:26px}
    .nav-links a{text-decoration:none;font-weight:700;font-size:.84rem;color:var(--ink);padding:4px 0;border-bottom:2px solid transparent;transition:border-color .15s ease,color .15s ease}
    .nav-links a:hover{color:var(--teal);border-color:var(--teal)}
    .nav-pill{background:var(--orange);padding:9px 16px!important;border-radius:999px;color:var(--navy-ink)!important;border-bottom:0!important}
    .nav-pill:hover{background:#d98722;border-color:transparent!important}
    .nav-toggle{display:none;border:1px solid var(--line);background:var(--paper);border-radius:var(--radius-sm);width:42px;height:38px;align-items:center;justify-content:center;font-size:1.2rem;color:var(--navy);cursor:pointer}

    /* ============================================================
       HERO
       ============================================================ */
    .hero{position:relative;background:linear-gradient(rgba(247,245,238,.71),rgba(247,245,238,.75)),url('../../assets/images/nasugbu-municipal-hall.jpg') center 58%/cover no-repeat;color:var(--ink);padding:64px 0 96px;border-bottom:1px solid var(--line);overflow:hidden}
    .hero:before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(16,42,67,.13) 1px,transparent 1px);background-size:22px 22px;mask-image:linear-gradient(90deg,transparent,#000 25%);pointer-events:none}
    .hero-inner{position:relative;display:grid;grid-template-columns:1.25fr .75fr;gap:56px;align-items:center}
    .eyebrow{display:flex;align-items:center;gap:10px;color:var(--teal);text-transform:uppercase;letter-spacing:.13em;font-size:.76rem;font-weight:800}
    .eyebrow:before{content:"";width:30px;height:3px;background:var(--orange);flex:none}
    .hero h1{font-size:clamp(2.4rem,5vw,4.6rem);letter-spacing:-.045em;line-height:1;margin:18px 0 20px}
    .hero h1 span{color:var(--teal)}
    .hero p{font-size:1.02rem;color:var(--muted);max-width:560px;margin:0}

    .hero-panel{background:var(--navy);color:#fff;padding:28px;border-radius:var(--radius-md);box-shadow:12px 12px 0 var(--shadow-color)}
    .hero-panel-label{display:block;color:#8fd0c6;text-transform:uppercase;font-size:.7rem;font-weight:800;letter-spacing:.12em}
    .hero-panel strong{display:block;font-size:3rem;line-height:1;margin:12px 0 6px;letter-spacing:-.03em}
    .hero-panel p{color:#c6d7d1;font-size:.85rem;margin:0}
    .hero-panel hr{border:0;border-top:1px solid rgba(255,255,255,.16);margin:22px 0}
    .hero-panel-status{display:flex;align-items:center;gap:10px;font-weight:700;font-size:.84rem}
    .pulse{width:9px;height:9px;border-radius:50%;background:#4fcf9e;box-shadow:0 0 0 6px rgba(79,207,158,.16);flex:none;animation:status-pulse 2s ease-out infinite}
    @keyframes status-pulse{0%,100%{box-shadow:0 0 0 5px rgba(79,207,158,.18)}50%{box-shadow:0 0 0 11px rgba(79,207,158,0)}}
    @keyframes float-panel{50%{transform:translateY(-7px)}}
    @keyframes map-reveal{from{opacity:0;transform:scale(.975)}to{opacity:1;transform:scale(1)}}
    .hero-panel{animation:float-panel 5s ease-in-out infinite}
    .reveal{opacity:0;transform:translateY(28px);transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1)}
    .reveal.is-visible{opacity:1;transform:none}

    /* ============================================================
       TRAFFIC SECTION (map + route planner)
       ============================================================ */
    .traffic-section{padding:88px 0;background:radial-gradient(circle at 8% 28%,rgba(18,116,106,.13),transparent 34%),radial-gradient(circle at 92% 72%,rgba(230,149,46,.13),transparent 32%),linear-gradient(180deg,rgba(255,255,255,.2),rgba(229,241,239,.3));border-bottom:0}
    .traffic-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:32px;margin-bottom:34px}
    .traffic-heading h2{font-size:clamp(2.15rem,4vw,3.35rem);letter-spacing:-.045em;line-height:1;margin:10px 0 14px;max-width:760px}
    .traffic-heading p{color:var(--muted);max-width:630px;margin:0;font-size:.98rem}
    .live-chip{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.84);color:var(--teal);padding:10px 15px;border:1px solid rgba(18,116,106,.12);border-radius:999px;box-shadow:0 9px 25px rgba(16,42,67,.08);font-size:.74rem;font-weight:800;flex:none;backdrop-filter:blur(10px)}

    .traffic-shell{display:grid;grid-template-columns:370px 1fr;min-height:570px;border:1px solid rgba(16,42,67,.1);border-radius:24px;overflow:hidden;box-shadow:0 30px 75px rgba(16,42,67,.16)}
    .route-panel{position:relative;padding:36px 32px;background:linear-gradient(160deg,var(--navy),var(--navy-ink));color:#fff;border-right:0}
    .route-panel:before{content:"Route planner";display:inline-flex;margin-bottom:24px;padding:6px 10px;border:1px solid rgba(143,208,198,.24);border-radius:999px;background:rgba(143,208,198,.1);color:#9bd9cf;font-size:.65rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
    .route-panel h3{margin:0 0 7px;font-size:1.35rem;letter-spacing:-.02em}
    .route-panel>p{font-size:.82rem;color:#b8cbc5;margin:0 0 28px;line-height:1.6}
    .route-field{position:relative;margin-bottom:17px}
    .route-field label{display:block;font-size:.65rem;text-transform:uppercase;letter-spacing:.09em;font-weight:800;color:#a8c6c0;margin-bottom:8px}
    .route-field input{width:100%;min-height:50px;border:1px solid rgba(255,255,255,.16);border-radius:9px;background:rgba(255,255,255,.96);color:var(--navy-ink);padding:12px 14px;font:inherit;transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease}
    .route-field input:focus{outline:none;border-color:#76d1c1;box-shadow:0 0 0 4px rgba(118,209,193,.14);transform:translateY(-1px)}
    .route-field input[aria-expanded="true"]{border-color:var(--teal)}
    .route-action{width:100%;min-height:50px;margin-top:8px;background:var(--orange);color:var(--navy-ink);border-radius:9px}
    .route-action:hover{background:#f2a13b;box-shadow:5px 5px 0 rgba(255,255,255,.16);transform:translate(-2px,-2px)}
    .route-error{color:#ffb5ae;font-size:.78rem;margin-top:11px;min-height:1em}
    .route-result{margin-top:22px;padding:18px;background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.1);border-radius:10px;display:none}
    .route-result.show{display:block}
    .route-result-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .route-result small{display:block;color:#a9c5cf;font-size:.66rem;text-transform:uppercase;letter-spacing:.04em}
    .route-result strong{font-size:1.3rem}
    .route-note{font-size:.7rem;color:#b9cbd1;margin:12px 0 0}
    .tomtom-attribution{font-size:.66rem;color:#89a6a0;margin-top:18px}
    .route-panel input:disabled,.route-panel button:disabled{opacity:.55;cursor:not-allowed}

    .place-suggestions{position:absolute;z-index:15;left:0;right:0;top:100%;margin:4px 0 0;padding:5px;background:var(--paper);border:1px solid #c7cec2;border-radius:var(--radius-sm);box-shadow:0 12px 28px rgba(16,42,67,.16);max-height:230px;overflow-y:auto;display:none}
    .place-suggestions.show{display:block}
    .place-suggestion{width:100%;border:0;background:transparent;text-align:left;padding:10px;border-radius:var(--radius-sm);cursor:pointer;font:inherit;color:var(--ink)}
    .place-suggestion:hover,.place-suggestion.active{background:var(--teal-light)}
    .place-suggestion strong{display:block;font-size:.82rem}
    .place-suggestion small{display:block;color:var(--muted);font-size:.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .place-searching{padding:10px;color:var(--muted);font-size:.75rem}

    .map-wrap{position:relative;min-height:570px;background:#edf3ef}
    .traffic-map{position:absolute;inset:0;visibility:hidden;opacity:0}
    .map-wrap.map-active .traffic-map{visibility:visible;opacity:1;animation:map-reveal .65s cubic-bezier(.22,1,.36,1) both}
    .map-gate{position:absolute;z-index:5;inset:0;display:grid;place-items:center;padding:42px;text-align:center;background:radial-gradient(circle at 70% 20%,rgba(230,149,46,.16),transparent 30%),linear-gradient(135deg,#e5f1ef,#f8f4e9 72%);transition:opacity .45s ease,visibility .45s ease}
    .map-gate:before,.map-gate:after{content:"";position:absolute;border:1px solid rgba(18,116,106,.12);border-radius:50%;pointer-events:none}.map-gate:before{width:420px;height:420px;right:-160px;top:-190px}.map-gate:after{width:280px;height:280px;left:-120px;bottom:-130px}
    .map-gate-card{position:relative;z-index:1;max-width:450px;padding:42px;background:rgba(255,255,255,.58);border:1px solid rgba(255,255,255,.82);border-radius:20px;box-shadow:0 25px 65px rgba(16,42,67,.14);backdrop-filter:blur(18px) saturate(120%)}
    .map-gate-icon{display:grid;place-items:center;width:70px;height:70px;margin:0 auto 22px;border-radius:18px;background:linear-gradient(145deg,var(--navy),#174e66);color:#fff;font-size:1.8rem;box-shadow:0 12px 28px rgba(16,42,67,.22);transform:rotate(-3deg)}
    .map-gate h3{margin:0 0 10px;color:var(--navy);font-size:1.45rem;letter-spacing:-.02em}
    .map-gate p{margin:0 auto 26px;color:var(--muted);font-size:.9rem;line-height:1.7;max-width:340px}
    .map-gate .button{min-height:48px;border-radius:9px;padding-inline:22px}
    .map-wrap.map-active .map-gate{opacity:0;visibility:hidden;pointer-events:none}
    .map-wrap:not(.map-active) .map-legend{opacity:0;visibility:hidden}
    .map-legend{transition:opacity .35s ease .3s,visibility .35s ease .3s}
    .map-legend{position:absolute;z-index:2;bottom:20px;left:20px;background:rgba(255,255,255,.96);padding:10px 13px;border-radius:var(--radius-sm);box-shadow:0 7px 22px rgba(16,42,67,.16);font-size:.72rem;display:flex;gap:13px}
    .legend-item{display:flex;align-items:center;gap:5px}
    .legend-dot{width:16px;height:4px;border-radius:2px}
    .map-setup{height:100%;min-height:540px;display:grid;place-items:center;text-align:center;padding:30px;background:repeating-linear-gradient(45deg,#f2f4ee,#f2f4ee 12px,#ecefe6 12px,#ecefe6 24px)}
    .map-setup i{font-size:2.6rem;color:var(--teal)}
    .map-setup h3{margin:10px 0 4px}
    .map-setup p{color:var(--muted);max-width:440px;margin:0}

    /* ============================================================
       ANNOUNCEMENTS — filters, featured post, card grid
       ============================================================ */
    .filters{position:relative;margin:32px auto 48px;z-index:5;background:var(--paper);padding:16px;border:1px solid var(--line);border-radius:var(--radius-md);box-shadow:0 15px 40px rgba(16,42,67,.1);display:grid;grid-template-columns:1fr 230px auto auto;gap:10px}

    .section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:22px}
    .section-heading h2{font-size:clamp(1.6rem,3vw,2.2rem);letter-spacing:-.03em;margin:0}
    .section-heading p{color:var(--muted);margin:4px 0 0}
    .count{color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-size:.76rem;font-weight:700;flex:none}

    .featured{display:grid;grid-template-columns:1.1fr .9fr;background:var(--paper);border:1px solid var(--line);border-radius:var(--radius-md);overflow:hidden;box-shadow:10px 10px 0 var(--shadow-color);margin-bottom:36px}
    .featured-media{min-height:380px;background:linear-gradient(135deg,var(--teal-light),var(--orange-light));display:grid;place-items:center;overflow:hidden}
    .featured-media img{width:100%;height:100%;object-fit:cover}
    .placeholder-icon{font-size:4.6rem;color:rgba(18,116,106,.4)}
    .featured-copy{padding:40px;display:flex;flex-direction:column;justify-content:center}
    .featured h2{font-size:clamp(1.5rem,3vw,2.1rem);line-height:1.22;letter-spacing:-.02em;margin:16px 0 12px}
    .meta{display:flex;flex-wrap:wrap;gap:14px;color:var(--muted);font-size:.8rem}
    .excerpt{color:#4b5a54;margin:18px 0 22px}

    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;padding-bottom:72px}
    .card{background:var(--paper);border:1px solid var(--line);border-radius:var(--radius-md);overflow:hidden;display:flex;flex-direction:column;transition:transform .18s ease,box-shadow .18s ease}
    .card:hover{transform:translateY(-4px);box-shadow:7px 7px 0 var(--orange)}
    .card-media{height:180px;background:linear-gradient(135deg,var(--teal-light),var(--orange-light));display:grid;place-items:center;overflow:hidden}
    .card-media img{width:100%;height:100%;object-fit:cover}
    .card-media .placeholder-icon{font-size:2.8rem}
    .card-body{padding:22px;display:flex;flex-direction:column;flex:1}
    .card h3{font-size:1.08rem;line-height:1.35;letter-spacing:-.01em;margin:13px 0 9px}
    .card .excerpt{font-size:.88rem;margin:12px 0 20px}
    .read-more{margin-top:auto;color:var(--teal);font-weight:700;text-decoration:none;font-size:.88rem;display:inline-flex;align-items:center;gap:6px}
    .read-more:hover{text-decoration:underline}

    .announcement-toolbar{display:flex;align-items:center;gap:14px;flex:none}
    .carousel-controls{display:flex;gap:8px}
    .carousel-button{width:42px;height:42px;border:1px solid var(--line);border-radius:50%;background:var(--paper);color:var(--navy);cursor:pointer;display:grid;place-items:center;font-size:1rem;transition:transform .18s ease,background-color .18s ease,color .18s ease}
    .carousel-button:hover:not(:disabled){background:var(--navy);color:#fff;transform:translateY(-2px)}
    .carousel-button:disabled{opacity:.35;cursor:not-allowed}
    .announcement-carousel{position:relative;padding-bottom:72px}
    .announcement-track{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - 44px)/3);gap:22px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;overscroll-behavior-inline:contain;padding:4px 8px 18px 0;scrollbar-width:none}
    .announcement-track::-webkit-scrollbar{display:none}
    .announcement-track .card{scroll-snap-align:start;min-width:0;min-height:430px}
    .announcement-track:has(.card:only-child){grid-auto-columns:min(520px,100%)}

    .empty{background:var(--paper);border:1px dashed #b9c4b7;border-radius:var(--radius-lg);text-align:center;padding:64px 25px;margin-bottom:72px}
    .empty i{display:block;font-size:2.8rem;color:var(--teal)}
    .empty h2{margin:14px 0 4px}
    .empty p{color:var(--muted);margin:0}

    /* ============================================================
       DIALOG (announcement detail)
       ============================================================ */
    dialog{width:min(760px,calc(100% - 28px));max-height:88vh;border:0;border-radius:var(--radius-md);padding:0;box-shadow:0 25px 80px rgba(0,0,0,.32)}
    dialog::backdrop{background:rgba(9,25,38,.72);animation:backdrop-in .25s ease both}
    dialog[open]{animation:dialog-in .35s cubic-bezier(.22,1,.36,1) both}
    @keyframes dialog-in{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:none}}
    @keyframes backdrop-in{from{opacity:0}to{opacity:1}}
    .dialog-image{width:100%;max-height:320px;object-fit:cover}
    .dialog-body{padding:32px;position:relative}
    .dialog-body h2{line-height:1.25;letter-spacing:-.02em;margin:14px 0 8px}
    .dialog-content{white-space:pre-wrap;margin-top:22px;color:#33443e}
    .dialog-close{position:absolute;right:14px;top:14px;width:38px;height:38px;border:0;border-radius:50%;background:rgba(255,255,255,.95);box-shadow:0 4px 14px rgba(0,0,0,.18);cursor:pointer;font-size:1.05rem;display:grid;place-items:center}
    .dialog-close:hover{background:var(--paper)}

    /* ============================================================
       FOOTER
       ============================================================ */
    footer{background:var(--navy-ink);color:#c9d8d3;padding:40px 0}
    .footer-inner{display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap}
    .footer-inner strong{color:#fff;font-size:1rem}
    .footer-inner p{color:#8ea299;margin:4px 0 0;font-size:.86rem}
    .footer-status{display:flex;align-items:center;gap:8px;font-size:.84rem;font-weight:600;color:#dce9e5}

    /* ============================================================
       RESPONSIVE
       ============================================================ */
    @media(max-width:850px){
      .nav-links{position:absolute;top:100%;left:0;right:0;background:var(--paper);border-bottom:1px solid var(--line);flex-direction:column;align-items:flex-start;gap:2px;padding:10px 0;display:none;box-shadow:0 14px 24px rgba(16,42,67,.1)}
      .nav-links.open{display:flex}
      .nav-links a{width:100%;padding:12px max(20px,calc((100% - min(var(--container),calc(100% - 40px)))/2 + 20px))}
      .nav-pill{width:calc(100% - 40px)!important;margin:6px auto 0!important;text-align:center;border-radius:var(--radius-sm)}
      .nav-toggle{display:flex}
      .hero-inner{grid-template-columns:1fr;gap:36px}
      .hero-panel{max-width:420px}
      .filters{grid-template-columns:1fr 1fr}
      .filters input{grid-column:1/-1}
      .featured{grid-template-columns:1fr}
      .featured-media{min-height:240px}
      .grid{grid-template-columns:repeat(2,1fr)}
      .announcement-track{grid-auto-columns:calc((100% - 22px)/2)}
      .traffic-shell{grid-template-columns:1fr}
      .route-panel{border-right:0;border-bottom:1px solid var(--line)}
      .map-wrap,.map-setup{min-height:420px}
    }
    @media(max-width:560px){
      .top-strip .container span:last-child{display:none}
      .hero{padding:44px 0 60px}
      .filters{grid-template-columns:1fr}
      .filters input{grid-column:auto}
      .grid{grid-template-columns:1fr}
      .section-heading{align-items:flex-start}
      .announcement-toolbar{align-items:flex-end;flex-direction:column;gap:8px}
      .announcement-track{grid-auto-columns:88%}
      .featured-copy{padding:24px}
      .traffic-section{padding:52px 0}
      .traffic-shell{margin:0 -8px;border-radius:0;border-left:0;border-right:0}
      .map-wrap,.map-setup{min-height:340px}
      .footer-inner{flex-direction:column;align-items:flex-start}
    }
  </style>
</head>
<body>
  <div class="top-strip"><div class="container"><span><i class="bi bi-shield-check"></i> Official TMO Public Information Portal</span><span><i class="bi bi-clock"></i> Updated <?= public_escape(date('F j, Y')) ?></span></div></div>
  <header><nav class="container"><a class="brand" href="index.php"><img src="../../assets/images/nasugbu-seal.jpg" alt="Official seal of the Municipality of Nasugbu, Batangas"><span><strong>NASUGBU · TMO</strong><small>Traffic Management Office</small></span></a><button class="nav-toggle" id="navToggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks"><i class="bi bi-list"></i></button><div class="nav-links" id="navLinks"><a href="index.php">Home</a><a href="#traffic">Traffic Map</a><a href="#announcements">Announcements</a><a class="nav-pill" href="#contact">Public Information</a></div></nav></header>

  <section class="hero"><div class="container hero-inner"><div><span class="eyebrow">A safer, informed community</span><h1>Traffic updates.<br><span>Made public.</span></h1><p>Your direct source for official traffic advisories, road notices, community activities, and emergency information from the Traffic Management Office.</p></div><aside class="hero-panel"><span class="hero-panel-label">Public information desk</span><strong><?= count($announcements) ?></strong><p>Active <?= count($announcements) === 1 ? 'announcement' : 'announcements' ?> available to the public right now.</p><hr><div class="hero-panel-status"><span class="pulse"></span>Official TMO information service</div></aside></div></section>

  <section class="traffic-section" id="traffic"><div class="container"><div class="traffic-heading"><div><span class="eyebrow">Plan before you travel</span><h2>Traffic conditions & route outlook</h2><p>See current congestion, reported incidents, and estimated travel conditions for a route and departure time within the coming days.</p></div><span class="live-chip"><span class="pulse"></span>Live traffic layer</span></div><div class="traffic-shell"><aside class="route-panel"><h3>Check a route</h3><p>Enter two places, then choose when you plan to leave.</p><form id="routeForm"><div class="route-field"><label for="routeOrigin">Starting point</label><input id="routeOrigin" required placeholder="e.g. Naga City Hall" autocomplete="off"></div><div class="route-field"><label for="routeDestination">Destination</label><input id="routeDestination" required placeholder="e.g. SM City Naga" autocomplete="off"></div><div class="route-field"><label for="routeDeparture">Departure date and time</label><input id="routeDeparture" type="datetime-local" required></div><button class="button button-primary route-action" type="submit" <?= !$tomtomEnabled ? 'disabled' : '' ?>><i class="bi bi-signpost-2"></i>Check traffic outlook</button><div class="route-error" id="routeError" role="alert"></div></form><div class="route-result" id="routeResult"><div class="route-result-grid"><div><small>Estimated time</small><strong id="routeTime">—</strong></div><div><small>Traffic delay</small><strong id="routeDelay">—</strong></div><div><small>Distance</small><strong id="routeDistance">—</strong></div><div><small>Outlook</small><strong id="routeLevel">—</strong></div></div><p class="route-note">Future estimates use historical traffic patterns. Actual conditions may change.</p></div><p class="tomtom-attribution">Traffic and routing data powered by TomTom.</p></aside><div class="map-wrap"><?php if ($tomtomEnabled): ?><div class="traffic-map" id="trafficMap"></div><div class="map-legend"><span class="legend-item"><span class="legend-dot" style="background:#39a96b"></span>Moving</span><span class="legend-item"><span class="legend-dot" style="background:#f2a33a"></span>Slow</span><span class="legend-item"><span class="legend-dot" style="background:#d64545"></span>Congested</span></div><?php else: ?><div class="map-setup"><div><i class="bi bi-map"></i><h3>Traffic map setup required</h3><p>An administrator can enable this map by adding a TomTom API key under Admin → Settings → TomTom Public Traffic Map.</p></div></div><?php endif; ?></div></div></div></section>

  <main id="announcements" class="container">
    <form class="filters" method="get"><input class="control" type="search" name="search" value="<?= public_escape($search) ?>" placeholder="Search announcements…" aria-label="Search announcements"><select class="control" name="type" aria-label="Announcement type"><option value="">All announcement types</option><?php foreach ($allowedTypes as $option): ?><option value="<?= public_escape($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= public_escape(ucwords($option)) ?></option><?php endforeach; ?></select><button class="button button-primary" type="submit"><i class="bi bi-search"></i>Search</button><?php if ($search !== '' || $type !== ''): ?><a class="button button-light" href="index.php">Clear</a><?php endif; ?></form>

    <div class="section-heading"><div><h2>Latest announcements</h2><p>Official information published by TMO.</p></div><div class="announcement-toolbar"><span class="count"><?= count($announcements) ?> <?= count($announcements) === 1 ? 'announcement' : 'announcements' ?></span><?php if (count($announcements) > 1): ?><div class="carousel-controls" aria-label="Announcement navigation"><button class="carousel-button" id="announcementPrev" type="button" aria-label="Previous announcements"><i class="bi bi-arrow-left"></i></button><button class="carousel-button" id="announcementNext" type="button" aria-label="Next announcements"><i class="bi bi-arrow-right"></i></button></div><?php endif; ?></div></div>

    <?php if (!$featured): ?>
      <section class="empty"><i class="bi bi-megaphone"></i><h2>No announcements found</h2><p><?= $search !== '' || $type !== '' ? 'Try changing your search or filter.' : 'Please check again soon for official TMO updates.' ?></p></section>
    <?php else: ?>
      <div class="announcement-carousel"><div class="announcement-track" id="announcementTrack" tabindex="0" aria-label="Latest announcements"><?php foreach ($announcements as $post): $image = public_image_url($post['image_path'] ?? null); ?><article class="card"><div class="card-media"><?php if ($image): ?><img src="<?= public_escape($image) ?>" alt="<?= public_escape($post['title']) ?>" loading="lazy"><?php else: ?><i class="bi <?= public_escape(public_type_icon($post['announcement_type'])) ?> placeholder-icon"></i><?php endif; ?></div><div class="card-body"><span class="badge"><i class="bi <?= public_escape(public_type_icon($post['announcement_type'])) ?>"></i><?= public_escape(ucwords($post['announcement_type'])) ?></span><h3><?= public_escape($post['title']) ?></h3><div class="meta"><span><i class="bi bi-calendar3"></i> <?= public_escape(date('M j, Y', strtotime($post['publish_date']))) ?></span></div><p class="excerpt"><?= public_escape(public_excerpt($post['content'])) ?></p><a class="read-more" href="#" onclick="event.preventDefault();document.getElementById('announcement-<?= (int)$post['announcement_id'] ?>').showModal()">Read announcement <i class="bi bi-arrow-right"></i></a></div></article><?php endforeach; ?></div></div>

      <?php foreach ($announcements as $post): $dialogImage = public_image_url($post['image_path'] ?? null); ?><dialog id="announcement-<?= (int)$post['announcement_id'] ?>"><?php if ($dialogImage): ?><img class="dialog-image" src="<?= public_escape($dialogImage) ?>" alt="<?= public_escape($post['title']) ?>"><?php endif; ?><button class="dialog-close" type="button" aria-label="Close" onclick="this.closest('dialog').close()"><i class="bi bi-x-lg"></i></button><div class="dialog-body"><span class="badge"><i class="bi <?= public_escape(public_type_icon($post['announcement_type'])) ?>"></i><?= public_escape(ucwords($post['announcement_type'])) ?></span><h2><?= public_escape($post['title']) ?></h2><div class="meta"><span><i class="bi bi-calendar3"></i> <?= public_escape(date('F j, Y · g:i A', strtotime($post['publish_date']))) ?></span><span><i class="bi bi-patch-check"></i> Official TMO post</span></div><div class="dialog-content"><?= public_escape($post['content']) ?></div></div></dialog><?php endforeach; ?>
    <?php endif; ?>
  </main>

  <footer id="contact"><div class="container footer-inner"><div><strong>TRAVIS · Traffic Management Office</strong><p>Providing reliable public traffic information for a safer community.</p></div><span class="footer-status"><i class="bi bi-shield-check"></i> Official information portal</span></div></footer>
  <script>
  document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close()}));
  (() => {
    const toggle = document.getElementById('navToggle');
    const links = document.getElementById('navLinks');
    if (!toggle || !links) return;
    toggle.addEventListener('click', () => {
      const open = links.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.innerHTML = open ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-list"></i>';
    });
    links.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
      links.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.innerHTML = '<i class="bi bi-list"></i>';
    }));
  })();
  (() => {
    const animated = document.querySelectorAll('.hero-inner > *, .traffic-heading, .traffic-shell, .section-heading, .featured, .card, .empty');
    animated.forEach((element, index) => {
      element.classList.add('reveal');
      element.style.transitionDelay = `${Math.min(index % 4, 3) * 70}ms`;
    });
    if (!('IntersectionObserver' in window)) {
      animated.forEach(element => element.classList.add('is-visible'));
      return;
    }
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, {threshold:.12, rootMargin:'0px 0px -35px'});
    animated.forEach(element => observer.observe(element));
  })();
  (() => {
    const track = document.getElementById('announcementTrack');
    const previous = document.getElementById('announcementPrev');
    const next = document.getElementById('announcementNext');
    if (!track || !previous || !next) return;
    const controls = previous.parentElement;
    const scrollAmount = () => Math.max(280, track.clientWidth * .82);
    const updateControls = () => {
      controls.hidden = track.scrollWidth <= track.clientWidth + 4;
      previous.disabled = track.scrollLeft <= 4;
      next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
    };
    previous.addEventListener('click', () => track.scrollBy({left:-scrollAmount(),behavior:'smooth'}));
    next.addEventListener('click', () => track.scrollBy({left:scrollAmount(),behavior:'smooth'}));
    track.addEventListener('scroll', updateControls, {passive:true});
    track.addEventListener('keydown', event => {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      track.scrollBy({left:event.key === 'ArrowLeft' ? -scrollAmount() : scrollAmount(),behavior:'smooth'});
    });
    window.addEventListener('resize', updateControls);
    updateControls();
  })();
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
    const mapWrap = document.querySelector('.map-wrap');
    const mapGate = document.createElement('div');
    mapGate.className = 'map-gate';
    mapGate.innerHTML = '<div class="map-gate-card"><span class="map-gate-icon"><i class="bi bi-map"></i></span><h3>Ready to check the roads?</h3><p>Open the interactive map only when you need it. Live traffic flow and reported incidents will load on demand.</p><button class="button button-primary" id="showMapButton" type="button"><i class="bi bi-eye"></i> Show live traffic map</button></div>';
    mapWrap.prepend(mapGate);
    let map = null;
    let resolveMapReady;
    const mapReady = new Promise(resolve => { resolveMapReady = resolve; });
    const showMap = () => {
      mapWrap.classList.add('map-active');
      if (map) return mapReady;
      map = tt.map({key,container:'trafficMap',center,zoom:<?= json_encode((int)$trafficSettings['tomtom_map_zoom']) ?>});
      map.addControl(new tt.NavigationControl());
      map.on('load', () => { map.showTrafficFlow(); map.showTrafficIncidents(); resolveMapReady(map); });
      return mapReady;
    };
    document.getElementById('showMapButton').addEventListener('click', showMap);

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
        await showMap();
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
