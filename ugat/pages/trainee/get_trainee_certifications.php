<?php
session_name('ugat_trainee');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get trainee name
$stmt = $conn->prepare("SELECT first_name, last_name FROM trainee_profiles WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
$trainee_name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: 'Trainee';

// Get all certificates
$stmt = $conn->prepare("
    SELECT c.id, c.status, c.certificate_number, c.issued_at, c.attendance_rate,
           w.title AS workshop_title, w.id AS workshop_id
    FROM certificates c
    JOIN workshops w ON w.id = c.workshop_id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res  = $stmt->get_result();
$certs = [];
while ($row = $res->fetch_assoc()) {
    $certs[] = [
        'id'             => (int)$row['id'],
        'status'         => $row['status'],
        'cert_no'        => $row['certificate_number'],
        'issued_at'      => $row['issued_at'],
        'attendance_rate'=> $row['attendance_rate'],
        'workshop_title' => $row['workshop_title'],
        'workshop_id'    => (int)$row['workshop_id'],
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'certificates' => $certs, 'trainee_name' => $trainee_name]);
