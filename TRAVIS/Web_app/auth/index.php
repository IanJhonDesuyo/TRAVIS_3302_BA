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
<link href="css/style.css" rel="stylesheet">

<style>
html,body{height:100%;margin:0;font-family:Inter,sans-serif}
body{
background:
linear-gradient(rgba(7,24,45,.38),rgba(7,24,45,.52)),
url('../uploads/image/image.png') center/cover no-repeat fixed;
overflow:hidden;
}
.bg-glow{
position:fixed;inset:0;pointer-events:none;
background:
radial-gradient(circle at 15% 20%,rgba(79,195,247,.22),transparent 25%),
radial-gradient(circle at 80% 70%,rgba(0,184,169,.16),transparent 30%);
animation:floatGlow 8s ease-in-out infinite alternate;
}
@keyframes floatGlow{from{transform:translateY(-10px)}to{transform:translateY(10px)}}
.login-wrapper{min-height:100vh;display:flex;align-items:center}
.info-side{color:#fff;padding:4rem}
.info-panel{
max-width:720px;
padding:38px;
background:rgba(7,24,45,.38);
backdrop-filter:blur(18px);
-webkit-backdrop-filter:blur(18px);
border:1px solid rgba(255,255,255,.22);
border-radius:24px;
box-shadow:0 25px 60px rgba(0,0,0,.3);
}
.info-side h1{font-size:3rem;font-weight:800}
.info-side p{max-width:620px;color:#d9e6f4}
.kpi{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-top:30px}
.kpi .glass{
padding:18px;text-align:center;
background:rgba(255,255,255,.1);
border:1px solid rgba(255,255,255,.16);
border-radius:16px;
}
.login-card{
background:rgba(255,255,255,.14);
backdrop-filter:blur(22px);
border:1px solid rgba(255,255,255,.22);
border-radius:24px;
padding:38px;
color:#fff;
box-shadow:0 25px 60px rgba(0,0,0,.35);
}
.login-card .form-control{
background:rgba(255,255,255,.12);
border-color:rgba(255,255,255,.22);
color:#fff;
}
.login-card .form-control::placeholder{color:#d7e7f7}
.logo-holder{display:flex;justify-content:center;gap:20px;margin-bottom:15px}
.logo-circle{
width:70px;height:70px;border-radius:50%;
background:rgba(255,255,255,.15);
display:flex;align-items:center;justify-content:center;
font-weight:700
}
@media(max-width:991px){
body{overflow:auto}
.info-side{display:none}
.login-card{margin:30px 0}
}
</style>
</head>
<body>

<div class="bg-glow"></div>

<div class="container-fluid login-wrapper">
<div class="row w-100 align-items-center">

<div class="col-lg-7 info-side">
<div class="info-panel">
<span class="badge bg-info text-dark mb-3">AI Smart Traffic Command Center</span>
<h1>TRAVIS</h1>
<h4 class="mb-4">Traffic Violation Recognition and AI Surveillance</h4>

<p>
An AI-powered intelligent traffic monitoring platform designed to assist Local Government Units
in monitoring traffic violations, congestion, collisions, and road conditions using
Computer Vision and Machine Learning.
</p>

<div class="kpi">
<div class="glass">
<h2>24</h2>
<small>Active Cameras</small>
</div>

<div class="glass">
<h2>AI</h2>
<small>Monitoring Online</small>
</div>

<div class="glass">
<h2>24/7</h2>
<small>Traffic Surveillance</small>
</div>
</div>

<div class="mt-5 text-light opacity-75">
<i class="bi bi-shield-check me-2"></i>
Municipality of Nasugbu &bull; Batangas State University &bull; TRAVIS v1.0
</div>
</div>

</div>

<div class="col-lg-5">
<div class="mx-auto" style="max-width:470px">
<div class="login-card">

<div class="logo-holder">
<div class="logo-circle">LGU</div>
<div class="logo-circle">BSU</div>
</div>

<h2 class="text-center fw-bold">Welcome Back</h2>
<p class="text-center text-light opacity-75 mb-4">
Authorized Personnel Only
</p>

<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="">
<div class="mb-3">
<label class="form-label">Email / Username</label>
<input class="form-control" type="email" name="identifier" placeholder="Enter email address" required>
</div>

<div class="mb-3">
<label class="form-label">Password</label>
<input class="form-control" type="password" name="password" placeholder="Enter password" required>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="remember" name="remember">
<label class="form-check-label" for="remember">Remember me</label>
</div>
<a href="#" class="text-info text-decoration-none">Forgot Password?</a>
</div>

<button class="btn btn-primary w-100 py-2">
<i class="bi bi-box-arrow-in-right me-2"></i>Sign In
</button>
</form>

<hr class="border-light opacity-25">

<div class="text-center small text-light opacity-75">
Traffic Violation Recognition and AI Surveillance<br>
Powered by Artificial Intelligence
</div>

</div>
</div>
</div>

</div>
</div>

</body>
</html>
