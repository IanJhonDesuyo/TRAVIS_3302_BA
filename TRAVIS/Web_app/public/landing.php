<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Admin/db_connect.php';

$activeAnnouncements = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM public_announcements WHERE status='published' AND publish_date <= NOW() AND (expiry_date IS NULL OR expiry_date >= NOW())");
if ($result) $activeAnnouncements = (int)($result->fetch_assoc()['total'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Official public traffic information portal of the Municipality of Nasugbu, Batangas.">
  <title>Welcome to Nasugbu TMO</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--navy:#0b2742;--navy-deep:#071b2e;--teal:#137b70;--orange:#ea9625;--cream:#f7f4eb;--white:#fff;--muted:#60716b}
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;min-height:100vh;background:var(--cream);color:var(--navy);font-family:'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
    a{color:inherit}
    .landing{position:relative;min-height:100vh;overflow:hidden;background:linear-gradient(90deg,rgba(247,244,235,.98) 0%,rgba(247,244,235,.91) 45%,rgba(247,244,235,.3) 72%),url('../../assets/images/nasugbu-municipal-hall.jpg') center/cover no-repeat}
    .landing:before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(11,39,66,.13) 1px,transparent 1px);background-size:24px 24px;mask-image:linear-gradient(90deg,#000,transparent 55%);pointer-events:none}
    .container{position:relative;width:min(1180px,calc(100% - 40px));margin:auto}
    nav{display:flex;align-items:center;justify-content:space-between;padding:24px 0}
    .brand{display:flex;align-items:center;gap:13px;text-decoration:none}
    .brand img{width:58px;height:58px;border-radius:50%;object-fit:cover;background:#fff;border:3px solid #fff;box-shadow:0 6px 22px rgba(7,27,46,.15)}
    .brand strong,.brand small{display:block}.brand strong{font-size:1rem;letter-spacing:.02em}.brand small{margin-top:3px;color:var(--muted);font-size:.73rem}
    .official{display:flex;align-items:center;gap:8px;padding:9px 14px;border:1px solid rgba(11,39,66,.12);border-radius:999px;background:rgba(255,255,255,.65);font-size:.76rem;font-weight:700;backdrop-filter:blur(10px)}
    .hero{display:grid;grid-template-columns:minmax(0,640px) 1fr;align-items:center;min-height:calc(100vh - 106px);padding:60px 0 100px}
    .eyebrow{display:inline-flex;align-items:center;gap:10px;color:var(--teal);font-size:.74rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}
    .eyebrow:before{content:"";width:34px;height:3px;background:var(--orange)}
    h1{margin:19px 0 22px;font-size:clamp(3rem,6.5vw,5.8rem);line-height:.96;letter-spacing:-.055em}
    h1 span{color:var(--teal)}
    .lead{max-width:590px;margin:0;color:#475d56;font-size:clamp(1rem,1.5vw,1.14rem);line-height:1.75}
    .actions{display:flex;align-items:center;gap:14px;margin-top:34px;flex-wrap:wrap}
    .button{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:52px;padding:0 24px;border:1px solid transparent;border-radius:7px;text-decoration:none;font-weight:800;font-size:.9rem;transition:transform .2s ease,box-shadow .2s ease,background-color .2s ease}
    .button-primary{background:var(--navy);color:#fff;box-shadow:7px 7px 0 var(--orange)}
    .button-primary:hover{background:var(--navy-deep);transform:translate(-2px,-2px);box-shadow:10px 10px 0 var(--orange)}
    .button-light{background:rgba(255,255,255,.76);border-color:rgba(11,39,66,.15);backdrop-filter:blur(8px)}
    .button-light:hover{background:#fff;transform:translateY(-2px)}
    .quick-info{display:flex;gap:36px;margin-top:56px;padding-top:26px;border-top:1px solid rgba(11,39,66,.13)}
    .quick-info div{display:grid;gap:3px}.quick-info strong{font-size:1.35rem}.quick-info small{color:var(--muted);font-size:.73rem;text-transform:uppercase;letter-spacing:.07em;font-weight:700}
    .side-card{position:absolute;right:0;bottom:68px;width:275px;padding:22px;border:1px solid rgba(255,255,255,.55);border-radius:14px;background:rgba(7,27,46,.88);color:#fff;box-shadow:0 25px 70px rgba(7,27,46,.3);backdrop-filter:blur(14px);animation:float 5s ease-in-out infinite}
    .side-card i{display:grid;place-items:center;width:42px;height:42px;margin-bottom:22px;border-radius:10px;background:rgba(79,207,158,.15);color:#65d8ae;font-size:1.15rem}.side-card strong{display:block;font-size:1.05rem}.side-card p{margin:7px 0 0;color:#c4d5ce;font-size:.78rem;line-height:1.6}
    .reveal{opacity:0;transform:translateY(24px);animation:reveal .8s cubic-bezier(.22,1,.36,1) forwards}.hero-copy{animation-delay:.12s}.quick-info{animation-delay:.28s}
    @keyframes reveal{to{opacity:1;transform:none}}@keyframes float{50%{transform:translateY(-8px)}}
    @media(max-width:850px){.landing{background:linear-gradient(rgba(247,244,235,.88),rgba(247,244,235,.95)),url('../../assets/images/nasugbu-municipal-hall.jpg') 62% center/cover no-repeat}.hero{grid-template-columns:1fr;min-height:auto;padding:80px 0 150px}.side-card{position:relative;right:auto;bottom:auto;margin:60px 0 0}.official{display:none}}
    @media(max-width:560px){.container{width:min(100% - 28px,1180px)}nav{padding:17px 0}.brand img{width:50px;height:50px}.hero{padding:54px 0 90px}h1{font-size:3.25rem}.actions{align-items:stretch;flex-direction:column}.button{width:100%}.quick-info{gap:20px;justify-content:space-between}.quick-info strong{font-size:1.1rem}.side-card{width:100%}}
    @media(prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important}}
  </style>
</head>
<body>
  <main class="landing">
    <div class="container">
      <nav>
        <a class="brand" href="landing.php"><img src="../../assets/images/nasugbu-seal.jpg" alt="Official seal of the Municipality of Nasugbu, Batangas"><span><strong>NASUGBU · TMO</strong><small>Traffic Management Office</small></span></a>
        <span class="official"><i class="bi bi-patch-check-fill"></i> Official municipal portal</span>
      </nav>
      <section class="hero">
        <div class="hero-copy reveal">
          <span class="eyebrow">Welcome to Nasugbu</span>
          <h1>Plan ahead.<br><span>Travel safer.</span></h1>
          <p class="lead">Access official traffic conditions, route outlooks, road advisories, and public announcements from the Nasugbu Traffic Management Office.</p>
          <div class="actions">
            <a class="button button-primary" href="index.php">Enter Public Portal <i class="bi bi-arrow-right"></i></a>
            <a class="button button-light" href="index.php#traffic"><i class="bi bi-map"></i> View Traffic Map</a>
          </div>
          <div class="quick-info reveal">
            <div><strong><?= $activeAnnouncements ?></strong><small>Active notices</small></div>
            <div><strong>Live</strong><small>Traffic outlook</small></div>
            <div><strong>24/7</strong><small>Public access</small></div>
          </div>
        </div>
        <aside class="side-card"><i class="bi bi-shield-check"></i><strong>Official public information</strong><p>Verified updates and travel information published by the Nasugbu Traffic Management Office.</p></aside>
      </section>
    </div>
  </main>
</body>
</html>
