<?php
// ============================================================
//  auth/change_password.php
//  Changes the logged-in user's password after verifying current one.
// ============================================================

ini_set('session.cookie_path', '/');
// Determine which session to use based on role
$requested_role = $_GET['role'] ?? 'trainee';
if ($requested_role === 'admin') {
    session_name('ugat_admin');
} else {
    session_name('ugat_trainee');
}
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$user_id         = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password']     ?? '';

if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
    exit;
}

// Verify current password
$stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($current_password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    exit;
}

// Update to new password
$new_hash = password_hash($new_password, PASSWORD_BCRYPT);
$stmt     = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->bind_param('si', $new_hash, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
}

$stmt->close();
$conn->close();