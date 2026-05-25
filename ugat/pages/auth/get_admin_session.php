<?php
// ============================================================
//  auth/get_admin_session.php
//  Returns the logged-in admin's profile data as JSON.
// ============================================================

ini_set('session.cookie_path', '/');
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not logged in as admin.']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    'SELECT u.email, u.role, u.created_at,
            a.first_name, a.last_name, a.phone, a.profile_pic
     FROM users u
     JOIN admin_profiles a ON a.user_id = u.id
     WHERE u.id = ? LIMIT 1'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Admin profile not found.']);
    exit;
}

echo json_encode(['success' => true, 'user' => $user]);