<?php
// ============================================================
//  get_user_addresses.php — UGAT TrainTrack
//  Returns all saved addresses for the logged-in user
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

// Fetch all addresses with region/province/city/barangay names
$stmt = $conn->prepare("
    SELECT 
        ua.id,
        ua.full_name,
        ua.contact_number,
        ua.address_line,
        ua.barangay_id,
        ua.city_id,
        ua.province_id,
        ua.region_id,
        ua.is_default,
        b.name AS barangay_name,
        c.name AS city_name,
        p.name AS province_name,
        r.name AS region_name
    FROM user_addresses ua
    LEFT JOIN barangays b  ON b.id  = ua.barangay_id
    LEFT JOIN cities    c  ON c.id  = ua.city_id
    LEFT JOIN provinces p  ON p.id  = ua.province_id
    LEFT JOIN regions   r  ON r.id  = ua.region_id
    WHERE ua.user_id = ?
    ORDER BY ua.is_default DESC, ua.created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$addresses = [];
while ($row = $result->fetch_assoc()) {
    // Build full readable address string
    $parts = array_filter([
        $row['address_line'],
        $row['barangay_name'],
        $row['city_name'],
        $row['province_name'],
        $row['region_name'],
    ]);
    $addresses[] = [
        'id'             => (int)$row['id'],
        'full_name'      => $row['full_name'],
        'contact_number' => $row['contact_number'],
        'address_line'   => $row['address_line'],
        'barangay_id'    => $row['barangay_id'] ? (int)$row['barangay_id'] : null,
        'city_id'        => $row['city_id']     ? (int)$row['city_id']     : null,
        'province_id'    => $row['province_id'] ? (int)$row['province_id'] : null,
        'region_id'      => $row['region_id']   ? (int)$row['region_id']   : null,
        'barangay_name'  => $row['barangay_name']  ?? '',
        'city_name'      => $row['city_name']       ?? '',
        'province_name'  => $row['province_name']   ?? '',
        'region_name'    => $row['region_name']     ?? '',
        'full_address'   => implode(', ', $parts),
        'is_default'     => (bool)$row['is_default'],
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'addresses' => $addresses]);