<?php
// ============================================================
//  admin/get_workshops.php
// ============================================================
session_name('ugat_admin');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$action = $_GET['action'] ?? 'workshops';

switch ($action) {

    /* ────────────────────────────────────────────────────────
       WORKSHOPS
       ──────────────────────────────────────────────────────── */
    case 'workshops':
        $r = $conn->query(
            "SELECT w.*,
                    (SELECT COUNT(*) FROM enrollments e
                      WHERE e.workshop_id = w.id
                        AND e.status IN ('enrolled','completed')) AS filled_slots,
                    (SELECT COUNT(*) FROM workshop_sessions s
                      WHERE s.workshop_id = w.id) AS session_count,
                    (SELECT MIN(session_date) FROM workshop_sessions s2
                      WHERE s2.workshop_id = w.id) AS first_session_date,
                    (SELECT MAX(session_date) FROM workshop_sessions s3
                      WHERE s3.workshop_id = w.id) AS last_session_date
             FROM workshops w
             ORDER BY w.created_at DESC"
        );
        $today     = date('Y-m-d');
        $workshops = [];
        while ($row = $r->fetch_assoc()) {
            // Compute status dynamically from session dates
            $first = $row['first_session_date'];
            $last  = $row['last_session_date'];
            if ($first && $last) {
                if ($today < $first)      $row['status'] = 'upcoming';
                elseif ($today > $last)   $row['status'] = 'completed';
                else                      $row['status'] = 'ongoing';
            }
            $workshops[] = $row;
        }
        echo json_encode(['success' => true, 'workshops' => $workshops]);
        break;

    /* ────────────────────────────────────────────────────────
       TRAINEES
       One row per enrollment (not per user) so that:
         • every enrollment has its own enrollment_id
         • pending enrollments appear as separate rows
         • approve/reject actions work correctly
       ──────────────────────────────────────────────────────── */
    case 'trainees':
        $r = $conn->query(
            "SELECT
                 e.id            AS enrollment_id,
                 e.status        AS enrollment_status,
                 e.enrolled_at,
                 e.reviewed_at,

                 u.id,
                 u.email,
                 u.created_at,

                 t.first_name,
                 t.last_name,
                 t.phone,
                 t.address,
                 t.profile_pic,
                 t.status        AS trainee_status,

                 w.title         AS workshop,
                 w.id            AS workshop_id,

                 -- Attendance for this specific workshop
                 (SELECT COUNT(*)
                    FROM attendance a
                    JOIN workshop_sessions ws ON ws.id = a.session_id
                   WHERE a.user_id = u.id
                     AND ws.workshop_id = w.id
                     AND a.status = 'present'
                 ) AS sessions_attended,

                 (SELECT COUNT(*)
                    FROM workshop_sessions ws2
                   WHERE ws2.workshop_id = w.id
                 ) AS total_sessions

             FROM users u
             JOIN trainee_profiles t ON t.user_id = u.id
             JOIN enrollments e      ON e.user_id = u.id
             JOIN workshops w        ON w.id      = e.workshop_id
             WHERE u.role      = 'trainee'
               AND u.is_active = 1
               -- Include ALL statuses so admin can see and act on pending/rejected
               AND e.status IN ('pending','enrolled','completed','rejected','dropped')
             ORDER BY
                 -- Pending enrollments bubble to the top by default
                 CASE WHEN e.status = 'pending' THEN 0 ELSE 1 END,
                 e.enrolled_at DESC"
        );

        $trainees = [];
        while ($row = $r->fetch_assoc()) {
            $trainees[] = $row;
        }
        echo json_encode(['success' => true, 'trainees' => $trainees]);
        break;

    /* ────────────────────────────────────────────────────────
       ATTENDANCE
       ──────────────────────────────────────────────────────── */
    case 'attendance':
        $r = $conn->query(
            "SELECT u.id AS user_id, u.email,
                    CONCAT(t.first_name, ' ', t.last_name) AS trainee_name,
                    t.profile_pic,
                    w.title AS workshop, w.id AS workshop_id,
                    COUNT(a.id) AS total_sessions,
                    SUM(a.status = 'present') AS present,
                    SUM(a.status = 'absent')  AS absent,
                    SUM(a.status = 'late')    AS late,
                    ROUND(SUM(a.status = 'present') / NULLIF(COUNT(a.id),0) * 100) AS rate
             FROM users u
             JOIN trainee_profiles t ON t.user_id = u.id
             -- Only show attendance for approved (enrolled) trainees
             JOIN enrollments e      ON e.user_id = u.id AND e.status IN ('enrolled','completed')
             JOIN workshops w        ON w.id = e.workshop_id
             LEFT JOIN workshop_sessions ws ON ws.workshop_id = w.id
             LEFT JOIN attendance a  ON a.session_id = ws.id AND a.user_id = u.id
             WHERE u.role = 'trainee'
             GROUP BY u.id, w.id
             ORDER BY u.id DESC"
        );
        $records = [];
        while ($row = $r->fetch_assoc()) $records[] = $row;
        echo json_encode(['success' => true, 'attendance' => $records]);
        break;

    /* ────────────────────────────────────────────────────────
       CERTIFICATIONS
       ──────────────────────────────────────────────────────── */
    case 'certifications':
        // Eligible: pull directly from certificates table (status='eligible')
        // so it stays in sync with checkEligibility() which flags both present+late
        $eligible_r = $conn->query(
            "SELECT c.user_id, c.workshop_id,
                    c.attendance_rate AS rate,
                    c.created_at AS completed_on,
                    u.email,
                    COALESCE(t.phone, '')                                          AS phone,
                    COALESCE(CONCAT(t.first_name,' ',t.last_name),
                             SUBSTRING_INDEX(u.email,'@',1))                       AS trainee_name,
                    COALESCE(t.profile_pic, '')                                    AS profile_pic,
                    COALESCE(w.title, 'Unknown Workshop')                          AS workshop,
                    (SELECT COUNT(*) FROM workshop_sessions ws
                      WHERE ws.workshop_id = c.workshop_id)                        AS total_sessions,
                    (SELECT COUNT(*) FROM attendance a
                      JOIN workshop_sessions ws2 ON ws2.id = a.session_id
                      WHERE ws2.workshop_id = c.workshop_id
                        AND a.user_id = c.user_id
                        AND a.status IN ('present','late'))                        AS sessions_done
             FROM certificates c
             JOIN  users u             ON u.id       = c.user_id
             LEFT JOIN trainee_profiles t ON t.user_id = u.id
             LEFT JOIN workshops w        ON w.id       = c.workshop_id
             WHERE c.status = 'eligible'
             ORDER BY c.created_at DESC"
        );
        $eligible = [];
        while ($row = $eligible_r->fetch_assoc()) $eligible[] = $row;

        $issued_r = $conn->query(
            "SELECT c.id, c.certificate_number AS cert_no, c.issued_at,
                    u.email,
                    COALESCE(t.phone, '')                                          AS phone,
                    COALESCE(CONCAT(t.first_name,' ',t.last_name),
                             SUBSTRING_INDEX(u.email,'@',1))                       AS trainee_name,
                    COALESCE(t.profile_pic, '')                                    AS profile_pic,
                    COALESCE(w.title, 'Unknown Workshop')                          AS workshop
             FROM certificates c
             JOIN  users u             ON u.id       = c.user_id
             LEFT JOIN trainee_profiles t ON t.user_id = u.id
             LEFT JOIN workshops w        ON w.id       = c.workshop_id
             WHERE c.status = 'issued'
             ORDER BY c.issued_at DESC"
        );
        $issued = [];
        while ($row = $issued_r->fetch_assoc()) $issued[] = $row;

        echo json_encode(['success' => true, 'eligible' => $eligible, 'issued' => $issued]);
        break;

    /* ────────────────────────────────────────────────────────
       SESSIONS
       ──────────────────────────────────────────────────────── */
    case 'sessions':
        $workshop_id = (int)($_GET['workshop_id'] ?? 0);
        if (!$workshop_id) {
            echo json_encode(['success' => false, 'message' => 'Workshop ID required.']);
            break;
        }
        $r = $conn->query(
            "SELECT * FROM workshop_sessions
              WHERE workshop_id = $workshop_id
              ORDER BY session_no ASC"
        );
        $sessions = [];
        while ($row = $r->fetch_assoc()) $sessions[] = $row;
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        break;

    /* ────────────────────────────────────────────────────────
       ENROLLED — used by attendance modal
       Only returns properly enrolled trainees (not pending)
       ──────────────────────────────────────────────────────── */
    case 'enrolled':
        $workshop_id = (int)($_GET['workshop_id'] ?? 0);
        if (!$workshop_id) {
            echo json_encode(['success' => false, 'message' => 'Workshop ID required.']);
            break;
        }
        $r = $conn->query(
            "SELECT u.id,
                    CONCAT(t.first_name, ' ', t.last_name) AS name,
                    t.phone, t.profile_pic, u.email
             FROM enrollments e
             JOIN users u            ON u.id = e.user_id
             JOIN trainee_profiles t ON t.user_id = u.id
             WHERE e.workshop_id = $workshop_id
               -- Only enrolled trainees can have attendance logged
               AND e.status IN ('enrolled','completed')
             ORDER BY t.last_name ASC"
        );
        $trainees = [];
        while ($row = $r->fetch_assoc()) $trainees[] = $row;
        echo json_encode(['success' => true, 'trainees' => $trainees]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();