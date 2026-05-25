<?php
// ============================================================
//  save_user_address.php — UGAT TrainTrack
//  Save a new address or update an existing one
//  Also handles setting a default address
// ============================================================
ini_set('display_errors', 0);
error_reporting(0);
session_name('ugat_trainee');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$data    = json_decode(file_get_contents('php://input'), true);
$action  = $data['action'] ?? 'save'; // save | set_default | delete

// ── SET DEFAULT ──────────────────────────────────────────────
if ($action === 'set_default') {
    $addr_id = (int)($data['address_id'] ?? 0);
    if (!$addr_id) { echo json_encode(['success' => false, 'message' => 'Address ID required']); exit; }

    // Unset all defaults for this user
    $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    // Set the chosen one
    $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $addr_id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Default address updated']);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────
if ($action === 'delete') {
    $addr_id = (int)($data['address_id'] ?? 0);
    if (!$addr_id) { echo json_encode(['success' => false, 'message' => 'Address ID required']); exit; }

    $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $addr_id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Address deleted']);
    exit;
}

// ── SAVE / UPDATE ────────────────────────────────────────────
$full_name      = trim($data['full_name']      ?? '');
$contact_number = trim($data['contact_number'] ?? '');
$address_line   = trim($data['address_line']   ?? '');
$barangay_id    = !empty($data['barangay_id'])  ? (int)$data['barangay_id']  : null;
$city_id        = !empty($data['city_id'])       ? (int)$data['city_id']       : null;
$province_id    = !empty($data['province_id'])   ? (int)$data['province_id']   : null;
$region_id      = !empty($data['region_id'])     ? (int)$data['region_id']     : null;
$is_default     = !empty($data['is_default'])    ? 1 : 0;
$addr_id        = !empty($data['id'])            ? (int)$data['id'] : 0;

if (!$full_name || !$contact_number || !$address_line) {
    echo json_encode(['success' => false, 'message' => 'Full name, contact number, and address line are required.']);
    exit;
}

// If setting as default, unset others first
if ($is_default) {
    $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

if ($addr_id) {
    // UPDATE existing
    $stmt = $conn->prepare("
        UPDATE user_addresses SET
            full_name      = ?,
            contact_number = ?,
            address_line   = ?,
            barangay_id    = ?,
            city_id        = ?,
            province_id    = ?,
            region_id      = ?,
            is_default     = ?
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param('sssiiiiii i',
        $full_name, $contact_number, $address_line,
        $barangay_id, $city_id, $province_id, $region_id,
        $is_default, $addr_id, $user_id
    );
} else {
    // INSERT new
    // Check if user has no addresses yet — if so, auto-default
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM user_addresses WHERE user_id = ?");
    $check->bind_param('i', $user_id);
    $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['cnt'];
    $check->close();
    if ($cnt == 0) $is_default = 1;

    $stmt = $conn->prepare("
        INSERT INTO user_addresses
            (user_id, full_name, contact_number, address_line, barangay_id, city_id, province_id, region_id, is_default)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isssiiiii',
        $user_id, $full_name, $contact_number, $address_line,
        $barangay_id, $city_id, $province_id, $region_id, $is_default
    );
}

if ($stmt->execute()) {
    $new_id = $addr_id ?: (int)$conn->insert_id;
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Address saved.', 'address_id' => $new_id]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to save address.']);
}