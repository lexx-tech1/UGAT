<?php
// ============================================================
//  admin/get_certifications.php
//  Returns eligible trainees and issued certificates as JSON.
//  Actions: eligible, issued, stats
// ============================================================
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$action = $_GET['action'] ?? 'stats';

// Helper: check trainee_profiles table exists
$has_tp = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
$name_sel = $has_tp
    ? "COALESCE(CONCAT(tp.first_name,' ',tp.last_name), SUBSTRING_INDEX(u.email,'@',1))"
    : "SUBSTRING_INDEX(u.email,'@',1)";
$tp_join = $has_tp ? "LEFT JOIN trainee_profiles tp ON tp.user_id = u.id" : "";
$phone_sel = $has_tp ? "tp.phone" : "NULL";

switch ($action) {

    // ── Stats KPIs ────────────────────────────────────────────
    case 'stats':
        $issued   = (int)$conn->query("SELECT COUNT(*) AS n FROM certificates WHERE status='issued'")->fetch_assoc()['n'];
        $eligible = (int)$conn->query("SELECT COUNT(*) AS n FROM certificates WHERE status='eligible'")->fetch_assoc()['n'];

        // In-progress: enrolled but not yet eligible
        $r = $conn->query("
            SELECT COUNT(DISTINCT e.user_id, e.workshop_id) AS n
            FROM enrollments e
            WHERE NOT EXISTS (
                SELECT 1 FROM certificates c
                WHERE c.user_id = e.user_id AND c.workshop_id = e.workshop_id
            )
        ");
        $in_progress = $r ? (int)$r->fetch_assoc()['n'] : 0;

        // Inactive
        $inactive = (int)$conn->query("SELECT COUNT(*) AS n FROM users WHERE role='trainee' AND is_active=0")->fetch_assoc()['n'];

        echo json_encode([
            'success'     => true,
            'issued'      => $issued,
            'eligible'    => $eligible,
            'in_progress' => $in_progress,
            'inactive'    => $inactive,
        ]);
        break;

    // ── Eligible trainees ─────────────────────────────────────
    case 'eligible':
        $r = $conn->query("
            SELECT
                c.id,
                c.user_id,
                c.workshop_id,
                $name_sel AS name,
                u.email,
                $phone_sel AS contact,
                w.title AS workshop,
                c.attendance_rate AS rate,
                c.created_at AS completed_on,
                -- Count total sessions in workshop
                (SELECT COUNT(*) FROM workshop_sessions ws WHERE ws.workshop_id = w.id) AS sessions_req,
                -- Count sessions attended by this trainee
                (SELECT COUNT(*) FROM attendance a
                 JOIN workshop_sessions ws2 ON ws2.id = a.session_id
                 WHERE ws2.workshop_id = w.id
                   AND a.user_id = c.user_id
                   AND a.status IN ('present','late')
                ) AS sessions_done
            FROM certificates c
            JOIN users u ON u.id = c.user_id
            $tp_join
            JOIN workshops w ON w.id = c.workshop_id
            WHERE c.status = 'eligible'
            ORDER BY c.created_at DESC
        ");
        $eligible = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $eligible[] = [
                'id'           => (int)$row['id'],
                'user_id'      => (int)$row['user_id'],
                'workshop_id'  => (int)$row['workshop_id'],
                'name'         => $row['name'],
                'email'        => $row['email'],
                'contact'      => $row['contact'] ?? '',
                'workshop'     => $row['workshop'],
                'rate'         => round((float)($row['rate'] ?? 0)),
                'sessions_req' => (int)($row['sessions_req'] ?? 0),
                'sessions_done'=> (int)($row['sessions_done'] ?? 0),
                'completed_on' => $row['completed_on']
                    ? date('M j, Y', strtotime($row['completed_on'])) : '—',
            ];
        }
        echo json_encode(['success' => true, 'eligible' => $eligible]);
        break;

    // ── Issued certificates ───────────────────────────────────
    case 'issued':
        $search   = $_GET['search']   ?? '';
        $workshop = $_GET['workshop'] ?? '';
        $sort     = $_GET['sort']     ?? 'newest';

        $where = "WHERE c.status = 'issued'";
        if ($search) {
            $s = $conn->real_escape_string($search);
            $where .= " AND ($name_sel LIKE '%$s%' OR w.title LIKE '%$s%' OR c.certificate_number LIKE '%$s%')";
        }
        if ($workshop) {
            $wk = $conn->real_escape_string($workshop);
            $where .= " AND w.title = '$wk'";
        }

        $order = $sort === 'oldest' ? 'c.issued_at ASC'
               : ($sort === 'name' ? 'name ASC'
               : 'c.issued_at DESC');

        $has_sms = $conn->query("SHOW COLUMNS FROM certificates LIKE 'sms_sent'")->num_rows > 0;
        $sms_col = $has_sms ? 'c.sms_sent' : '0';

        $r = $conn->query("
            SELECT
                c.id,
                c.user_id,
                c.workshop_id,
                $name_sel AS name,
                u.email,
                $phone_sel AS contact,
                w.title AS workshop,
                c.certificate_number,
                c.issued_at,
                c.attendance_rate,
                $sms_col AS sms_sent,
                -- Sessions
                (SELECT COUNT(*) FROM workshop_sessions ws WHERE ws.workshop_id = w.id) AS sessions_req,
                (SELECT COUNT(*) FROM attendance a
                 JOIN workshop_sessions ws2 ON ws2.id = a.session_id
                 WHERE ws2.workshop_id = w.id
                   AND a.user_id = c.user_id
                   AND a.status IN ('present','late')
                ) AS sessions_done
            FROM certificates c
            JOIN users u ON u.id = c.user_id
            $tp_join
            JOIN workshops w ON w.id = c.workshop_id
            $where
            ORDER BY $order
        ");
        $issued = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $issued[] = [
                'id'             => (int)$row['id'],
                'user_id'        => (int)$row['user_id'],
                'workshop_id'    => (int)$row['workshop_id'],
                'name'           => $row['name'],
                'email'          => $row['email'],
                'contact'        => $row['contact'] ?? '',
                'workshop'       => $row['workshop'],
                'cert_no'        => $row['certificate_number'] ?? '—',
                'issued_on'      => $row['issued_at']
                    ? date('M j, Y', strtotime($row['issued_at'])) : '—',
                'rate'           => round((float)($row['attendance_rate'] ?? 0)),
                'sms_sent'       => (bool)$row['sms_sent'],
                'sessions_req'   => (int)($row['sessions_req'] ?? 0),
                'sessions_done'  => (int)($row['sessions_done'] ?? 0),
            ];
        }
        echo json_encode(['success' => true, 'issued' => $issued]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();