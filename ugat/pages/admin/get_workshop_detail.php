<?php
// ============================================================
//  admin/get_workshop_detail.php
//  Returns full detail for a single workshop:
//    - workshop record + filled_slots
//    - sessions with attendance counts + computed status
//    - enrolled trainees with per-trainee attendance stats
// ============================================================
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Workshop ID required.']);
    exit;
}

// ── 1. Workshop basic info ────────────────────────────────────────────────────
$wr = $conn->query(
    "SELECT w.*,
            (SELECT COUNT(*) FROM enrollments e
             WHERE e.workshop_id = w.id) AS filled_slots
     FROM workshops w
     WHERE w.id = $id
     LIMIT 1"
);

if (!$wr || $wr->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Workshop not found.']);
    exit;
}
$workshop = $wr->fetch_assoc();

// ── 2. Sessions with attendance counts + computed status ──────────────────────
//    Status:  past date  → done
//             today      → current
//             future     → upcoming
$sr = $conn->query(
    "SELECT ws.*,
            CASE
                WHEN ws.session_date < CURDATE() THEN 'done'
                WHEN ws.session_date = CURDATE() THEN 'current'
                ELSE 'upcoming'
            END                                       AS computed_status,
            COALESCE(SUM(a.status = 'present'), 0)   AS present_count,
            COALESCE(SUM(a.status = 'absent'),  0)   AS absent_count,
            COALESCE(SUM(a.status = 'late'),    0)   AS late_count,
            COUNT(a.id)                               AS total_marked
     FROM workshop_sessions ws
     LEFT JOIN attendance a ON a.session_id = ws.id
     WHERE ws.workshop_id = $id
     GROUP BY ws.id
     ORDER BY ws.session_no ASC"
);
$sessions = [];
while ($row = $sr->fetch_assoc()) $sessions[] = $row;

// ── 3. Enrolled trainees with attendance stats ────────────────────────────────
$tr = $conn->query(
    "SELECT
         u.id,
         u.email,
         CONCAT(tp.first_name, ' ', tp.last_name)                              AS name,
         tp.phone,
         tp.profile_pic,
         e.status                                                                AS enrollment_status,
         COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.id END)          AS sessions_attended,
         COUNT(DISTINCT ws.id)                                                   AS total_sessions,
         ROUND(
             COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.id END)
             / NULLIF(COUNT(DISTINCT ws.id), 0) * 100
         )                                                                       AS attendance_rate
     FROM enrollments e
     JOIN users u              ON u.id = e.user_id
     JOIN trainee_profiles tp  ON tp.user_id = u.id
     LEFT JOIN workshop_sessions ws ON ws.workshop_id = e.workshop_id
     LEFT JOIN attendance a    ON a.session_id = ws.id AND a.user_id = u.id
     WHERE e.workshop_id = $id
     GROUP BY u.id, tp.first_name, tp.last_name, tp.phone, tp.profile_pic, e.status
     ORDER BY tp.last_name, tp.first_name"
);
$trainees = [];
while ($row = $tr->fetch_assoc()) $trainees[] = $row;

echo json_encode([
    'success'  => true,
    'workshop' => $workshop,
    'sessions' => $sessions,
    'trainees' => $trainees,
]);

$conn->close();