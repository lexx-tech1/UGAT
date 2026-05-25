<?php
// ============================================================
//  auth/upload_avatar.php
//  Handles profile picture upload for both admin and trainee.
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

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];
$file    = $_FILES['avatar'];

// Validate type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo         = finfo_open(FILEINFO_MIME_TYPE);
$mime          = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, or WEBP images are allowed.']);
    exit;
}

// Validate size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Image must be smaller than 2MB.']);
    exit;
}

// Create upload directory
$upload_dir = '../../uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get old avatar
$table = $role === 'admin' ? 'admin_profiles' : 'trainee_profiles';
$old   = $conn->prepare("SELECT profile_pic FROM {$table} WHERE user_id = ? LIMIT 1");
$old->bind_param('i', $user_id);
$old->execute();
$old_result = $old->get_result()->fetch_assoc();
$old->close();

if (!empty($old_result['profile_pic'])) {
    $old_path = '../../' . $old_result['profile_pic'];
    if (file_exists($old_path)) unlink($old_path);
}

// Save new file
$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'avatar_' . $user_id . '_' . time() . '.' . strtolower($ext);
$dest     = $upload_dir . $filename;
$db_path  = 'uploads/avatars/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save image.']);
    exit;
}

// Update DB
$stmt = $conn->prepare("UPDATE {$table} SET profile_pic = ? WHERE user_id = ?");
$stmt->bind_param('si', $db_path, $user_id);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Profile picture updated!',
    'pic_url' => '/UGAT/' . $db_path,
]);