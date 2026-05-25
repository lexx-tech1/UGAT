<?php
ini_set('session.cookie_path', '/');
session_name('ugat_trainee');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id     = (int)$_SESSION['user_id'];
$workshop_id = (int)($_GET['workshop_id'] ?? 0);

if (!$workshop_id) {
    echo json_encode(['success' => false, 'message' => 'Workshop ID required.']);
    exit;
}

// Same conflict logic as submit_enrollment.php:
// Check if any session date of the target workshop matches any session date
// of a workshop the user is already enrolled/pending in.
$stmt = $conn->prepare("
    SELECT w.title AS conflicting_workshop,
           ws_new.session_date AS conflicting_date
    FROM workshop_sessions ws_new
    JOIN workshop_sessions ws_existing ON ws_existing.session_date = ws_new.session_date
    JOIN enrollments e ON e.workshop_id = ws_existing.workshop_id
    JOIN workshops w   ON w.id          = e.workshop_id
    WHERE ws_new.workshop_id = ?
      AND e.user_id          = ?
      AND e.status IN ('pending', 'enrolled')
      AND e.workshop_id     != ?
    LIMIT 1
");
$stmt->bind_param('iii', $workshop_id, $user_id, $workshop_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if ($row) {
    $date = date('F j, Y', strtotime($row['conflicting_date']));
    echo json_encode([
        'success'              => true,
        'conflict'             => true,
        'conflicting_workshop' => $row['conflicting_workshop'],
        'conflicting_date'     => $date,
        'message'              => 'Schedule conflict: this workshop has a session on ' . $date
                                . ', which overlaps with your enrollment in "'
                                . $row['conflicting_workshop'] . '".',
    ]);
} else {
    echo json_encode(['success' => true, 'conflict' => false]);
}
