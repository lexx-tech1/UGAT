<?php
// ============================================================
//  auth/update_admin_profile.php
//  Updates the logged-in admin's profile and email.
// ============================================================

ini_set('session.cookie_path', '/');
// Use admin session
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not logged in as admin.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Update admin_profiles
$allowed = [
    'first_name' => 's',
    'last_name'  => 's',
    'phone'      => 's',
];

$fields = [];
$params = [];
$types  = '';

foreach ($allowed as $field => $type) {
    if (isset($_POST[$field])) {
        $fields[] = "$field = ?";
        $params[] = $_POST[$field];
        $types   .= $type;
    }
}

if (!empty($fields)) {
    $types   .= 'i';
    $params[] = $user_id;
    $sql      = 'UPDATE admin_profiles SET ' . implode(', ', $fields) . ' WHERE user_id = ?';
    $stmt     = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Profile update failed.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
}

// Update email in users table if provided
if (!empty($_POST['email'])) {
    $new_email = trim(strtolower($_POST['email']));

    // Check if taken by another user
    $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $check->bind_param('si', $new_email, $user_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        echo json_encode(['success' => false, 'message' => 'That email is already used by another account.']);
        $conn->close();
        exit;
    }
    $check->close();

    $estmt = $conn->prepare('UPDATE users SET email = ? WHERE id = ?');
    $estmt->bind_param('si', $new_email, $user_id);
    $estmt->execute();
    $estmt->close();

    // Update session
    $_SESSION['email'] = $new_email;
}

echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
$conn->close();