<?php
// Uploads the GCash QR code image for display in checkout
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']); exit;
}

if (empty($_FILES['qr_image']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']); exit;
}

$file = $_FILES['qr_image'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','jfif','png','gif','webp','bmp'])) {
    echo json_encode(['success' => false, 'message' => 'Image files only.']); exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File must be under 5 MB.']); exit;
}

$dest = __DIR__ . '/../../uploads/gcash_qr.' . $ext;

// Remove old QR files
foreach (glob(__DIR__ . '/../../uploads/gcash_qr.*') as $old) {
    if (is_file($old)) unlink($old);
}

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Upload failed.']); exit;
}

$path = 'uploads/gcash_qr.' . $ext;
$conn->query("INSERT INTO settings (`key`, `value`) VALUES ('gcash_qr_path', '$path') ON DUPLICATE KEY UPDATE `value` = '$path', updated_at = NOW()");
$conn->close();

echo json_encode(['success' => true, 'path' => $path]);
