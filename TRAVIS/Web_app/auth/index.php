<?php
declare(strict_types=1);

session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function respond_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload);
    exit;
}

function verify_password(string $password, string $storedPassword): bool
{
    if ($storedPassword === '') {
        return false;
    }

    $info = password_get_info($storedPassword);
    if (($info['algo'] ?? 0) !== 0) {
        return password_verify($password, $storedPassword);
    }

    return hash_equals($storedPassword, $password);
}

function portal_redirect_for_role(string $role, string $email = ''): string
{
    $normalizedRole = strtolower(trim($role));
    $normalizedEmail = strtolower(trim($email));

    $isTreasurer = in_array($normalizedRole, ['treasury personnel', 'treasurer'], true)
        || str_contains($normalizedEmail, 'treasurer')
        || str_contains($normalizedEmail, 'treasury');

    if ($isTreasurer) {
        return '../Treasurer/dashboard.php';
    }

    return '../Admin/dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../Admin/db_connect.php';

    $email = trim((string)($_POST['identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    $errors = [];
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if ($errors) {
        respond_json(['success' => false, 'message' => implode(' ', $errors)], 422);
    }

    $stmt = $conn->prepare('SELECT user_id, full_name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        respond_json(['success' => false, 'message' => 'Unable to prepare login request.'], 500);
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $isValid = $user
        && strcasecmp((string)$user['status'], 'active') === 0
        && verify_password($password, (string)$user['password']);

    if (!$isValid) {
        respond_json([
            'success' => false,
            'message' => 'Invalid credentials. Please verify your account details or contact the TRAVIS administrator.',
        ], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['user_id'],
        'name' => (string)$user['full_name'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
    ];
    $_SESSION['login_success'] = true;

    if ($remember) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $redirect = portal_redirect_for_role((string)$user['role'], (string)$user['email']);

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    if (!$isAjax && !str_contains($accept, 'application/json')) {
        header('Location: ' . $redirect);
        exit;
    }

    respond_json([
        'success' => true,
        'redirect' => $redirect,
        'user' => [
            'name' => (string)$user['full_name'],
            'role' => (string)$user['role'],
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!empty($_SESSION['user']['id'])) {
    header('Location: ' . portal_redirect_for_role((string)($_SESSION['user']['role'] ?? ''), (string)($_SESSION['user']['email'] ?? '')));
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TRAVIS Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="css/style.css" rel="stylesheet">

<style>
:root{
  --navy-950:#060f1e;
  --navy-900:#0a1a30;
  --navy-800:#0f2544;
  --navy-700:#15315c;
  --blue-accent:#38bdf8;
  --blue-accent-2:#2563eb;
  --cyan-glow:#4fc3f7;
  --text-soft:#c9d8ea;
  --border-glass:rgba(255,255,255,.10);
}

*{box-sizing:border-box}

html,body{height:100%;margin:0;font-family:'Poppins',sans-serif}

body{
  color:#fff;
  background:
    radial-gradient(circle at 12% 15%, rgba(56,189,248,.14), transparent 32%),
    radial-gradient(circle at 85% 75%, rgba(37,99,235,.16), transparent 35%),
    linear-gradient(160deg, var(--navy-950) 0%, var(--navy-900) 45%, var(--navy-800) 100%);
  overflow:hidden;
}

.bg-glow{
  position:fixed;inset:0;pointer-events:none;
  background:
    radial-gradient(circle at 18% 22%, rgba(79,195,247,.18), transparent 28%),
    radial-gradient(circle at 82% 68%, rgba(37,99,235,.16), transparent 32%);
  animation:floatGlow 8s ease-in-out infinite alternate;
}
@keyframes floatGlow{from{transform:translateY(-10px)}to{transform:translateY(10px)}}

.traffic-fx{position:fixed;inset:0;pointer-events:none;overflow:hidden;z-index:0}

.vision-grid{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(56,189,248,.08) 1px, transparent 1px),
    linear-gradient(90deg, rgba(56,189,248,.08) 1px, transparent 1px);
  background-size:52px 52px;
}

.login-wrapper{position:relative;z-index:1}

.login-wrapper{min-height:100vh;display:flex;align-items:center}

.info-side{padding:4rem 2.5rem 4rem 5.5rem}

.badge-pill{
  display:inline-flex !important;align-items:center;gap:8px;
  padding:8px 16px;border-radius:999px;
  background:rgba(56,189,248,.12);
  border:1px solid rgba(56,189,248,.28);
  color:var(--cyan-glow);
  font-size:.8rem;font-weight:600;letter-spacing:.02em;
  margin-bottom:.6rem !important;
  width:fit-content !important;
  clear:both;
}

.info-side h1{
  font-family:'Poppins',sans-serif;
  font-size:3.1rem;
  font-weight:800;
  letter-spacing:.01em;
  line-height:1.05;
  margin-bottom:.5rem;
  margin-top:0;
  display:block !important;
  width:fit-content !important;
  clear:both;
  background:linear-gradient(90deg,#ffffff 0%,#cfe4ff 40%,#38bdf8 75%,#2563eb 100%);
  -webkit-background-clip:text;
  background-clip:text;
  -webkit-text-fill-color:transparent;
  color:transparent;
}

.info-side h4{
  font-weight:600;color:var(--text-soft);margin-bottom:1.5rem;
}

.info-side p.lead-copy{
  max-width:600px;color:var(--text-soft);font-size:1.02rem;line-height:1.65;
}

.kpi{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:2rem;max-width:600px}
.kpi .glass{
  padding:18px 14px;text-align:center;
  background:rgba(255,255,255,.04);
  border:1px solid var(--border-glass);
  border-radius:16px;
}
.kpi .glass h2{
  font-size:1.6rem;font-weight:800;margin:0 0 4px;
  color:var(--cyan-glow);
}
.kpi .glass small{color:var(--text-soft);font-size:.72rem;letter-spacing:.03em;text-transform:uppercase}

.footer-strip{
  margin-top:2.5rem;color:var(--text-soft);opacity:.7;font-size:.85rem;
}

/* login card, styled like the dashboard panel from the reference */
.login-card{
  background:rgba(15,37,68,.55);
  backdrop-filter:blur(22px);
  -webkit-backdrop-filter:blur(22px);
  border:1px solid var(--border-glass);
  border-radius:22px;
  padding:0;
  box-shadow:0 30px 70px rgba(0,0,0,.45);
  overflow:hidden;
}

.login-card-header{
  display:flex;align-items:center;gap:8px;
  padding:16px 22px;
  background:rgba(255,255,255,.03);
  border-bottom:1px solid var(--border-glass);
}
.dot{width:10px;height:10px;border-radius:50%}
.dot.red{background:#ff5f57}
.dot.yellow{background:#febc2e}
.dot.green{background:#28c840}
.login-card-header .live{
  margin-left:auto;font-size:.7rem;letter-spacing:.08em;
  color:var(--text-soft);opacity:.75;text-transform:uppercase;
}

.login-card-body{padding:42px 44px 38px}

.logo-holder{display:flex;justify-content:center;gap:18px;margin-bottom:22px}
.logo-circle{
  width:68px;height:68px;border-radius:50%;
  background:linear-gradient(135deg, rgba(56,189,248,.22), rgba(37,99,235,.22));
  border:1px solid rgba(255,255,255,.12);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:.88rem;color:var(--cyan-glow);
}

.login-card h2{font-weight:800}
.login-card p.subtitle{color:var(--text-soft);opacity:.8}

.form-label{color:var(--cyan-glow);font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:8px;display:block}

.login-card .form-control{
  background:rgba(255,255,255,.05);
  border:1px solid var(--border-glass);
  color:var(--text-soft);
  border-radius:12px;
  padding:.8rem 1rem .8rem 2.7rem;
  font-size:.98rem;
}
.login-card .form-control::placeholder{color:rgba(201,216,234,.55)}
.login-card .form-control:focus{
  background:rgba(255,255,255,.07);
  border-color:var(--blue-accent);
  box-shadow:0 0 0 3px rgba(56,189,248,.18);
  color:var(--text-soft);
}

.input-icon-group{position:relative}
.input-icon-group i.field-icon{
  position:absolute;left:16px;top:50%;transform:translateY(-50%);
  color:var(--text-soft);opacity:.55;font-size:1rem;pointer-events:none;
}
.input-icon-group .toggle-visibility{
  position:absolute;right:16px;top:50%;transform:translateY(-50%);
  background:none;border:none;padding:0;color:var(--text-soft);opacity:.6;
  font-size:1rem;cursor:pointer;line-height:1;
}
.input-icon-group .toggle-visibility:hover{opacity:1;color:var(--cyan-glow)}
.input-icon-group .form-control:focus ~ i.field-icon{color:var(--blue-accent);opacity:.9}
.input-icon-group input[type="password"],.input-icon-group input[type="text"].pw-field{padding-right:2.6rem}

.form-check-input{background-color:rgba(255,255,255,.08);border-color:var(--border-glass)}
.form-check-input:checked{background-color:var(--blue-accent-2);border-color:var(--blue-accent-2)}

a.forgot-link{color:var(--cyan-glow);text-decoration:none;font-size:.85rem}
a.forgot-link:hover{text-decoration:underline}

.btn-signin{
  background:linear-gradient(90deg,var(--blue-accent-2),var(--cyan-glow));
  border:none;color:#fff;font-weight:700;
  border-radius:12px;padding:.7rem 1rem;
  box-shadow:0 12px 28px rgba(37,99,235,.35);
  transition:transform .15s ease, box-shadow .15s ease;
}
.btn-signin:hover{
  transform:translateY(-1px);
  box-shadow:0 16px 32px rgba(56,189,248,.4);
  color:#fff;
}

hr{border-color:var(--border-glass);opacity:1}

.small-footer{color:var(--text-soft);opacity:.65;font-size:.78rem}

.alert-danger{
  background:rgba(220,53,69,.14);
  border:1px solid rgba(220,53,69,.35);
  color:#ffb3bd;
  border-radius:12px;
}

@media(max-width:991px){
  body{overflow:auto}
  .info-side{display:none}
  .login-card{margin:30px 0}
}

/* Nasugbu TMO public-information visual theme */
:root{
  --municipal-navy:#102f49;
  --municipal-ink:#10202c;
  --municipal-teal:#087d78;
  --municipal-orange:#eb941f;
  --municipal-cream:#f7f5ee;
  --municipal-muted:#61716d;
}

body{
  color:var(--municipal-ink);
  background:
    linear-gradient(rgba(247,245,238,.66),rgba(247,245,238,.76)),
    url('../../assets/images/nasugbu-municipal-hall.jpg') center 58%/cover fixed no-repeat;
}

.bg-glow{display:none}
.traffic-fx{z-index:0}
.vision-grid{
  background-image:radial-gradient(rgba(16,42,67,.13) 1px,transparent 1px);
  background-size:22px 22px;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 35%);
  mask-image:linear-gradient(90deg,transparent,#000 35%);
}

.login-wrapper{padding:28px 34px}
.info-side{padding:3rem 3rem 3rem 4.5rem}
.badge-pill{
  padding:0;
  border:0;
  border-radius:0;
  background:transparent;
  color:var(--municipal-teal);
  font-size:.76rem;
  font-weight:800;
  letter-spacing:.13em;
  text-transform:uppercase;
}
.badge-pill::before{content:"";width:32px;height:3px;background:var(--municipal-orange)}
.badge-pill i{display:none}

.info-side h1{
  margin-top:1.25rem;
  color:var(--municipal-ink);
  background:none;
  -webkit-text-fill-color:currentColor;
  font-size:4.7rem;
  font-weight:900;
  letter-spacing:-.055em;
}
.info-side h4{
  max-width:650px;
  color:var(--municipal-teal);
  font-size:1.55rem;
  font-weight:800;
  line-height:1.35;
}
.info-side p.lead-copy{color:var(--municipal-muted);font-size:1rem;line-height:1.75}
.kpi .glass{
  text-align:left;
  background:rgba(255,253,247,.54);
  border:1px solid rgba(16,47,73,.14);
  border-radius:12px;
  box-shadow:0 12px 28px rgba(16,47,73,.07);
  backdrop-filter:blur(12px);
}
.kpi .glass h2{color:var(--municipal-teal)}
.kpi .glass small{color:#516760;font-weight:700}
.footer-strip{color:#536a64;opacity:1}

.login-card{
  color:#fff;
  background:rgba(16,47,73,.96);
  border:1px solid rgba(255,255,255,.12);
  border-radius:12px;
  box-shadow:14px 16px 0 rgba(8,125,120,.12),0 30px 70px rgba(16,47,73,.25);
  backdrop-filter:blur(20px) saturate(110%);
}
.login-card-header{
  padding:15px 22px;
  background:rgba(255,255,255,.035);
  border-bottom-color:rgba(255,255,255,.12);
}
.login-card-header .live{color:#9fd5cf;opacity:1;font-weight:700}
.login-card-body{padding:36px 44px 38px}
.logo-holder{align-items:center;gap:12px;margin-bottom:20px}
.auth-seal{
  width:68px;
  height:68px;
  object-fit:cover;
  border:3px solid rgba(255,255,255,.9);
  border-radius:50%;
  box-shadow:0 8px 20px rgba(0,0,0,.22);
}
.auth-agency{text-align:left}
.auth-agency strong{display:block;color:#fff;font-size:1rem;letter-spacing:.02em}
.auth-agency small{display:block;color:#9fd5cf;font-size:.72rem;margin-top:3px}
.login-card h2{color:#fff}
.login-card p.subtitle{color:#b9cbc9;opacity:1}
.form-label{color:#91d0ca}
.login-card .form-control{
  color:#fff;
  background:rgba(255,255,255,.065);
  border-color:rgba(255,255,255,.16);
}
.login-card .form-control::placeholder{color:rgba(221,233,231,.5)}
.login-card .form-control:focus{
  color:#fff;
  background:rgba(255,255,255,.09);
  border-color:#60c6bd;
  box-shadow:0 0 0 3px rgba(96,198,189,.17);
}
.input-icon-group i.field-icon,.input-icon-group .toggle-visibility{color:#a9c8c5}
.input-icon-group .toggle-visibility:hover{color:#fff}
.input-icon-group .form-control:focus ~ i.field-icon{color:#60c6bd}
.form-check-input{background-color:rgba(255,255,255,.08);border-color:rgba(255,255,255,.25)}
.form-check-input:checked{background-color:var(--municipal-orange);border-color:var(--municipal-orange)}
a.forgot-link{color:#9fd5cf}
.btn-signin{
  color:#10202c;
  background:linear-gradient(90deg,#eb941f,#f3aa43);
  box-shadow:0 12px 28px rgba(235,148,31,.25);
}
.btn-signin:hover{color:#10202c;box-shadow:0 16px 34px rgba(235,148,31,.34)}
hr{border-color:rgba(255,255,255,.13)}
.small-footer{color:#b5c9c6;opacity:.78}
.alert-danger{background:rgba(220,53,69,.18);border-color:rgba(255,145,157,.38);color:#ffd4da}

@media(max-width:1199px){.info-side h1{font-size:4rem}.info-side{padding-left:2.5rem}}
@media(max-width:991px){
  body{background-position:center}
  .login-wrapper{padding:18px}
  .login-card{margin:18px 0;box-shadow:8px 10px 0 rgba(8,125,120,.12),0 24px 55px rgba(16,47,73,.24)}
}
@media(max-width:575px){
  .login-wrapper{padding:10px}
  .login-card-body{padding:30px 22px}
  .auth-seal{width:58px;height:58px}
}
</style>
</head>
<body>

<div class="bg-glow"></div>

<div class="traffic-fx" aria-hidden="true">
  <div class="vision-grid"></div>
</div>

<div class="container-fluid login-wrapper">
<div class="row w-100 align-items-center">

<div class="col-lg-6 col-xl-7 info-side">
  <span class="badge-pill"><i class="bi bi-stars"></i> AI Smart Traffic Command Center</span>
  <h1>TRAVIS</h1>
  <h4>An AI, Computer Vision, and IoT-Based Traffic Monitoring and Decision Support System</h4>

  <p class="lead-copy">
    An AI-powered intelligent traffic monitoring platform designed to assist Local Government Units
    in monitoring traffic violations, congestion, collisions, and road conditions using
    Computer Vision and Machine Learning.
  </p>

  <div class="kpi">
    <div class="glass">
      <h2>1</h2>
      <small>Active Camera</small>
    </div>
    <div class="glass">
      <h2>AI</h2>
      <small>Monitoring Online</small>
    </div>
    <div class="glass">
      <h2><i class="bi bi-diagram-3-fill"></i></h2>
      <small>Decision Support</small>
    </div>
  </div>

  <div class="footer-strip">
    <i class="bi bi-shield-check me-2"></i>
    Municipality of Nasugbu &bull; Batangas State University &bull; TRAVIS v1.0
  </div>
</div>

<div class="col-lg-6 col-xl-5">
<div class="mx-auto" style="max-width:540px">
<div class="login-card">

  <div class="login-card-header">
    <span class="dot red"></span>
    <span class="dot yellow"></span>
    <span class="dot green"></span>
    <span class="live">Live &middot; Nasugbu</span>
  </div>

  <div class="login-card-body">
    <div class="logo-holder">
      <img class="auth-seal" src="../../assets/images/nasugbu-seal.jpg" alt="Municipality of Nasugbu seal">
      <div class="auth-agency"><strong>NASUGBU · TMO</strong><small>Traffic Management Office</small></div>
    </div>

    <h2 class="text-center mb-2">Welcome Back</h2>
    <p class="text-center subtitle mb-4">Authorized Personnel Only</p>

    <?php if(!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="mb-4">
        <label class="form-label">Email / Username</label>
        <div class="input-icon-group">
          <i class="bi bi-envelope field-icon"></i>
          <input class="form-control" type="email" name="identifier" placeholder="Enter email address" required>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="input-icon-group">
          <i class="bi bi-lock field-icon"></i>
          <input class="form-control" type="password" name="password" id="passwordField" placeholder="Enter password" required>
          <button type="button" class="toggle-visibility" id="togglePassword" aria-label="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="remember" name="remember">
          <label class="form-check-label" for="remember" style="color:var(--text-soft);font-size:.85rem">Remember me</label>
        </div>
        <a href="#" class="forgot-link">Forgot Password?</a>
      </div>

      <button class="btn btn-signin w-100 py-2">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>

    <hr>

    <div class="text-center small-footer">
      Traffic Violation Recognition and AI Surveillance<br>
      Powered by Artificial Intelligence
    </div>
  </div>

</div>
</div>
</div>

</div>
</div>

</body>
<script>
document.getElementById('togglePassword').addEventListener('click', function () {
  var field = document.getElementById('passwordField');
  var icon = this.querySelector('i');
  var isPassword = field.getAttribute('type') === 'password';
  field.setAttribute('type', isPassword ? 'text' : 'password');
  icon.classList.toggle('bi-eye');
  icon.classList.toggle('bi-eye-slash');
  this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
});
</script>
</html>
