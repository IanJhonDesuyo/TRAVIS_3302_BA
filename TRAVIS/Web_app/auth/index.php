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
<title>TRAVIS Login · Municipality of Nasugbu</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html, body {
    height: 100%;
    margin: 0;
    font-family: 'Poppins', sans-serif;
}

body {
    background: url('../../assets/images/nasugbu-municipal-hall.jpg') center 45% / cover fixed no-repeat;
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
}

/* Dark overlay for the entire background */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(6, 15, 30, 0.35);
    z-index: 0;
}

/* White gradient overlay - fades from left to right */
.gradient-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to right, 
        rgba(255, 255, 255, 0.92) 0%,
        rgba(255, 255, 255, 0.85) 25%,
        rgba(255, 255, 255, 0.65) 45%,
        rgba(255, 255, 255, 0.35) 60%,
        rgba(255, 255, 255, 0.10) 75%,
        rgba(255, 255, 255, 0) 100%
    );
    z-index: 0;
    pointer-events: none;
}

.login-wrapper {
    position: relative;
    z-index: 1;
    width: 100%;
    padding: 30px 40px;
}

/* Left Panel - Info Side */
.info-side {
    padding: 3rem 2.5rem 3rem 0;
    color: #1a2a3a;
}

.badge-pill {
    display: inline-flex !important;
    align-items: center;
    gap: 8px;
    padding: 6px 18px 6px 14px;
    border-radius: 999px;
    background: rgba(26, 35, 80, 0.08);
    border: 1px solid rgba(26, 35, 80, 0.15);
    color: #1a2350;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 1.2rem !important;
    width: fit-content !important;
}

.badge-pill i {
    font-size: 0.8rem;
    color: #1a2350;
}

.info-side h1 {
    font-size: 4.5rem;
    font-weight: 900;
    letter-spacing: -0.04em;
    line-height: 1.02;
    margin-bottom: 0.25rem;
    margin-top: 0;
    background: linear-gradient(135deg, #0a1128 0%, #1a2350 35%, #2a3a7a 70%, #3a4a9a 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
}

.info-side h4 {
    font-weight: 700;
    color: #1a2350;
    font-size: 1.35rem;
    margin-bottom: 1rem;
    letter-spacing: -0.01em;
}

.info-side .lead-copy {
    max-width: 540px;
    color: #3a4a6a;
    font-size: 0.98rem;
    line-height: 1.75;
    font-weight: 400;
}

/* KPI Cards */
.kpi {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: 2rem;
    max-width: 460px;
}

.kpi .glass {
    padding: 16px 14px;
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(26, 35, 80, 0.06);
    border-radius: 14px;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.kpi .glass:hover {
    background: rgba(255, 255, 255, 0.7);
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}

.kpi .glass h2 {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 4px;
    color: #1a2350;
    letter-spacing: -0.02em;
}

.kpi .glass h2 i {
    font-size: 1.3rem;
    color: #1a2350;
}

.kpi .glass small {
    color: #5a6a8a;
    font-size: 0.65rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    display: block;
    font-weight: 600;
}

.footer-strip {
    margin-top: 2.5rem;
    color: #5a6a8a;
    font-size: 0.78rem;
    letter-spacing: 0.02em;
}

.footer-strip i {
    color: #1a2350;
}

/* ============================================
   GLASS LOGIN CARD - NAVY BLUE THEME
   ============================================ */
.login-card {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(30px);
    -webkit-backdrop-filter: blur(30px);
    border: 1px solid rgba(255, 255, 255, 0.20);
    border-radius: 24px;
    box-shadow: 
        0 25px 80px rgba(0, 0, 0, 0.25),
        0 10px 30px rgba(0, 0, 0, 0.10),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
    overflow: hidden;
    transition: all 0.4s ease;
}

.login-card:hover {
    box-shadow: 
        0 35px 100px rgba(0, 0, 0, 0.30),
        0 15px 40px rgba(0, 0, 0, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

/* Card Header - Mac style */
.login-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px 28px;
    background: rgba(255, 255, 255, 0.04);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.login-card-header .dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.login-card-header .dot.red { 
    background: #ff5f57;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}
.login-card-header .dot.yellow { 
    background: #febc2e;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}
.login-card-header .dot.green { 
    background: #28c840;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.login-card-header .live {
    margin-left: auto;
    font-size: 0.6rem;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.06);
    padding: 4px 14px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.login-card-header .live i {
    color: #28c840;
    font-size: 0.55rem;
}

/* Card Body */
.login-card-body {
    padding: 42px 44px 44px;
}

/* Logo/Agency */
.logo-holder {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
}

.auth-seal {
    width: 68px;
    height: 68px;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.auth-seal:hover {
    transform: scale(1.05);
    border-color: rgba(255, 255, 255, 0.30);
}

.auth-agency {
    text-align: left;
}

.auth-agency strong {
    display: block;
    color: #ffffff;
    font-size: 1.1rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.auth-agency small {
    display: block;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.7rem;
    font-weight: 500;
    margin-top: 2px;
    letter-spacing: 0.05em;
}

/* Welcome Text */
.login-card h2 {
    color: #ffffff;
    font-weight: 800;
    font-size: 1.8rem;
    margin-bottom: 0.1rem;
    letter-spacing: -0.02em;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.login-card .subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    margin-bottom: 1.8rem;
    font-weight: 400;
}

/* Form Elements */
.form-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    margin-bottom: 6px;
    display: block;
}

.input-icon-group {
    position: relative;
}

.input-icon-group .field-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.35);
    font-size: 1rem;
    pointer-events: none;
    transition: color 0.3s ease;
}

.login-card .form-control {
    background: rgba(255, 255, 255, 0.07);
    border: 2px solid #1a2350;
    color: #ffffff;
    border-radius: 14px;
    padding: 0.8rem 1rem 0.8rem 2.8rem;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s ease;
    font-family: 'Poppins', sans-serif;
}

.login-card .form-control::placeholder {
    color: rgba(255, 255, 255, 0.30);
    font-weight: 400;
}

.login-card .form-control:focus {
    background: rgba(255, 255, 255, 0.11);
    border-color: #3a4a9a;
    box-shadow: 0 0 0 4px rgba(26, 35, 80, 0.25);
    color: #ffffff;
}

.login-card .form-control:focus ~ .field-icon {
    color: rgba(255, 255, 255, 0.8);
}

.input-icon-group .toggle-visibility {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 0;
    color: rgba(255, 255, 255, 0.35);
    font-size: 1.1rem;
    cursor: pointer;
    line-height: 1;
    transition: color 0.3s ease;
}

.input-icon-group .toggle-visibility:hover {
    color: rgba(255, 255, 255, 0.8);
}

.login-card input[type="password"],
.login-card input[type="text"].pw-field {
    padding-right: 2.8rem;
}

/* Checkbox & Forgot Link */
.form-check-input {
    width: 18px;
    height: 18px;
    background-color: rgba(255, 255, 255, 0.08);
    border: 2px solid rgba(255, 255, 255, 0.12);
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.form-check-input:checked {
    background-color: #1a2350;
    border-color: #1a2350;
}

.form-check-input:focus {
    box-shadow: 0 0 0 3px rgba(26, 35, 80, 0.25);
}

.form-check-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    padding-left: 2px;
}

a.forgot-link {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
}

a.forgot-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: #ffffff;
    transition: width 0.3s ease;
}

a.forgot-link:hover {
    color: #ffffff;
}

a.forgot-link:hover::after {
    width: 100%;
}

/* Sign In Button - Navy Blue Theme */
.btn-signin {
    background: linear-gradient(135deg, #0a1128 0%, #1a2350 50%, #2a3a7a 100%);
    border: none;
    color: #fff;
    font-weight: 700;
    border-radius: 14px;
    padding: 0.85rem 1rem;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    font-family: 'Poppins', sans-serif;
    letter-spacing: 0.02em;
    position: relative;
    overflow: hidden;
}

.btn-signin::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 50%);
    pointer-events: none;
}

.btn-signin:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(26, 35, 80, 0.35);
    color: #fff;
}

.btn-signin:active {
    transform: translateY(0);
    box-shadow: 0 8px 25px rgba(26, 35, 80, 0.20);
}

/* Divider */
hr {
    border: none;
    border-top: 2px solid rgba(255, 255, 255, 0.05);
    margin: 1.5rem 0;
}

.small-footer {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.7rem;
    line-height: 1.7;
    letter-spacing: 0.03em;
}

.small-footer i {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.65rem;
}

.alert-danger {
    background: rgba(220, 53, 69, 0.12);
    border: 2px solid rgba(220, 53, 69, 0.15);
    color: #ffb3bd;
    border-radius: 14px;
    font-size: 0.85rem;
    font-weight: 500;
    padding: 12px 16px;
}

/* Responsive */
@media (max-width: 1199px) {
    .info-side h1 { font-size: 3.6rem; }
    .info-side h4 { font-size: 1.15rem; }
    .login-card-body { padding: 32px 30px 36px; }
}

@media (max-width: 991px) {
    body { align-items: flex-start; padding: 20px 0; }
    body::before { background: rgba(6, 15, 30, 0.5); }
    .gradient-overlay { 
        background: rgba(255, 255, 255, 0.90);
        width: 100%;
    }
    .login-wrapper { padding: 20px; }
    .info-side { display: none; }
    .login-card { margin: 0 auto; max-width: 460px; }
}

@media (max-width: 575px) {
    .login-wrapper { padding: 12px; }
    .login-card-body { padding: 24px 20px 28px; }
    .login-card h2 { font-size: 1.5rem; }
    .auth-seal { width: 56px; height: 56px; }
    .logo-holder { gap: 12px; }
    .login-card-header { padding: 12px 18px; }
}
</style>

</head>
<body>

<!-- White gradient overlay - fades from left to right -->
<div class="gradient-overlay"></div>

<div class="login-wrapper">
<div class="row w-100 align-items-center g-0">

<!-- Left Panel -->
<div class="col-lg-7 col-xl-7 info-side">
    <span class="badge-pill">
        <i class="bi bi-stars"></i> AI Smart Traffic Command Center
    </span>
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

<!-- Right Panel - Login Card -->
<div class="col-lg-5 col-xl-5">
<div class="mx-auto" style="max-width: 480px;">

<div class="login-card">

    <div class="login-card-header">
        <span class="dot red"></span>
        <span class="dot yellow"></span>
        <span class="dot green"></span>
        <span class="live"><i class="bi bi-record-fill me-1"></i> Live &bull; Nasugbu</span>
    </div>

    <div class="login-card-body">
        <div class="logo-holder">
            <img class="auth-seal" src="../../assets/images/nasugbu-seal.jpg" alt="Municipality of Nasugbu Seal">
            <div class="auth-agency">
                <strong>NASUGBU · TMO</strong>
                <small>Traffic Management Office</small>
            </div>
        </div>

        <h2 class="text-center mb-0">Welcome Back</h2>
        <p class="text-center subtitle">Authorized Personnel Only</p>

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

            <div class="mb-3">
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
                <div class="form-check d-flex align-items-center gap-2">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <a href="#" class="forgot-link">Forgot Password?</a>
            </div>

            <button class="btn btn-signin w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <hr>

        <div class="text-center small-footer">
            <i class="bi bi-shield-check me-1"></i>
            Traffic Violation Recognition and AI Surveillance<br>
            Powered by Artificial Intelligence
        </div>
    </div>

</div>

</div>
</div>

</div>
</div>

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

</body>
</html>