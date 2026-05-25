<?php
// ============================================================
//  auth/login.php  —  Login Handler (Admin & Trainee)
//  Expects a POST request (form or fetch/AJAX)
// ============================================================
ini_set('session.cookie_path', '/');

require_once '../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── 1. Collect input ─────────────────────────────────────────
$email    = trim(strtolower($_POST['email']    ?? ''));
$password = $_POST['password']                 ?? '';

$ip         = $_SERVER['REMOTE_ADDR']          ?? null;
$user_agent = $_SERVER['HTTP_USER_AGENT']      ?? null;

// ── 2. Basic validation ──────────────────────────────────────
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

// ── Helper: log the attempt ───────────────────────────────────
function logAttempt(mysqli $conn, ?int $user_id, string $email,
                    ?string $ip, ?string $ua, string $status): void
{
    $stmt = $conn->prepare(
        'INSERT INTO login_logs (user_id, email, ip_address, user_agent, status)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $user_id, $email, $ip, $ua, $status);
    $stmt->execute();
    $stmt->close();
}

// ── 3. Look up the user ──────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT id, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1'
);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

// ── 4. Verify credentials ────────────────────────────────────
if (!$user || !password_verify($password, $user['password_hash'])) {
    logAttempt($conn, $user['id'] ?? null, $email, $ip, $user_agent, 'failed');
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    $conn->close();
    exit;
}

if (!$user['is_active']) {
    logAttempt($conn, $user['id'], $email, $ip, $user_agent, 'failed');
    echo json_encode(['success' => false, 'message' => 'Your account has been deactivated. Contact admin.']);
    $conn->close();
    exit;
}

// ── 5. Successful login — set session ────────────────────────
logAttempt($conn, $user['id'], $email, $ip, $user_agent, 'success');

// Regenerate session ID to prevent fixation
ini_set('session.cookie_path', '/');
$session_name = ($user['role'] === 'admin') ? 'ugat_admin' : 'ugat_trainee';
session_name($session_name);
session_start();
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['email']   = $email;
$_SESSION['role']    = $user['role'];

// ── 6. Redirect based on role ────────────────────────────────
$redirect = match($user['role']) {
    'admin'   => '/UGAT/pages/admin/AdminDashboard.html',
    'trainee' => '/UGAT/pages/trainee/TraineeDashboard.html',
    default   => '/UGAT/pages/auth/Login.html',
};

echo json_encode([
    'success'  => true,
    'message'  => 'Login successful.',
    'role'     => $user['role'],
    'redirect' => $redirect,
]);

$conn->close();