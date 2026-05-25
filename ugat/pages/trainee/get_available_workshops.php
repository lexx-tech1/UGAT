<?php
// ============================================================
//  trainee/get_available_workshops.php
//  Returns upcoming and ongoing workshops for trainee enrollment form.
//  Accessible by logged-in trainees (no admin role required).
// ============================================================

ini_set('session.cookie_path', '/');
session_name('ugat_trainee');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$r = $conn->query(
    "SELECT w.id, w.title, w.status, w.max_slots, w.location, w.category,
            (SELECT MIN(session_date) FROM workshop_sessions s
             WHERE s.workshop_id = w.id) AS first_session_date,
            (SELECT COUNT(*) FROM workshop_sessions s
             WHERE s.workshop_id = w.id) AS session_count,
            (SELECT COUNT(*) FROM enrollments e
             WHERE e.workshop_id = w.id) AS filled_slots
     FROM workshops w
WHERE w.status = 'upcoming'
     ORDER BY w.created_at DESC"
);

$workshops = [];
while ($row = $r->fetch_assoc()) $workshops[] = $row;

echo json_encode(['success' => true, 'workshops' => $workshops]);
$conn->close();