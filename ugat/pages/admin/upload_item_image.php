<?php
session_name('ugat_admin');

session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No image uploaded.']);
    exit;
}

$allowed = ['image/jpeg','image/png','image/webp','image/gif'];
$mime    = mime_content_type($_FILES['image']['tmp_name']);
if (!in_array($mime, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    exit;
}

if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 2MB.']);
    exit;
}

$uploadDir = '../../uploads/inventory/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext      = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
$filename = 'item_' . time() . '_' . rand(1000,9999) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save image.']);
    exit;
}

echo json_encode(['success' => true, 'path' => 'uploads/inventory/' . $filename]);