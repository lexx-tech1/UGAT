<?php
ini_set('session.cookie_path', '/');
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$enrollment_id = (int)($_GET['enrollment_id'] ?? 0);

if (!$enrollment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing enrollment_id.']);
    exit;
}

$stmt = $conn->prepare(
   'SELECT
        e.id            AS enrollment_id,
        e.status        AS enrollment_status,
        e.enrolled_at,
        e.reviewed_at,

        u.id            AS user_id,
        u.email,

        tp.first_name,
        tp.last_name,
        tp.middle_name,
        tp.phone,
        tp.address,
        tp.barangay,
        tp.city,
        tp.province,
        tp.region,
        tp.nationality,
        tp.gender,
        tp.civil_status,
        tp.birthdate,
        tp.education,
        tp.employment,
        tp.learner_class,
        tp.is_pwd,
        tp.guardian_name,
        tp.guardian_addr,
        tp.profile_pic,

        w.title         AS workshop_title,
        w.category      AS workshop_category,
        w.facilitator   AS workshop_facilitator

      FROM enrollments e
      JOIN users           u  ON u.id  = e.user_id
      JOIN trainee_profiles tp ON tp.user_id = e.user_id
      JOIN workshops        w  ON w.id = e.workshop_id
     WHERE e.id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $enrollment_id);
$stmt->execute();
$result = $stmt->get_result();
$row    = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Enrollment not found.']);
    exit;
}

echo json_encode(['success' => true, 'detail' => $row]);