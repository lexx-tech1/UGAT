<?php
// ============================================================
//  admin/get_attendance.php
//  Actions: records, workshops, sessions, session_trainees
// ============================================================
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$action = $_GET['action'] ?? 'records';

$has_tp = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
$name_sel = $has_tp
    ? "COALESCE(CONCAT(tp.first_name,' ',tp.last_name), SUBSTRING_INDEX(u.email,'@',1))"
    : "SUBSTRING_INDEX(u.email,'@',1)";
$tp_join = $has_tp ? "LEFT JOIN trainee_profiles tp ON tp.user_id = u.id" : "";

switch ($action) {

    // ── KPI stats ─────────────────────────────────────────────
    case 'stats':
        $r = $conn->query("SELECT
            COUNT(*) AS total,
            SUM(status='present') AS present,
            SUM(status='late')    AS late,
            SUM(status='absent')  AS absent
            FROM attendance");
        $kpi = $r ? $r->fetch_assoc() : ['total'=>0,'present'=>0,'late'=>0,'absent'=>0];
        echo json_encode(['success' => true, 'kpi' => $kpi]);
        break;

    // ── By Trainee records ────────────────────────────────────
    case 'records':
        $r = $conn->query("
            SELECT
                e.user_id,
                e.workshop_id,
                $name_sel AS trainee_name,
                u.email,
                w.title AS workshop,
                COUNT(DISTINCT ws.id) AS total_sessions,
                COUNT(DISTINCT CASE WHEN a.status IS NOT NULL THEN ws.id END) AS done_sessions,
                SUM(a.status = 'present') AS present,
                SUM(a.status = 'late')    AS late,
                SUM(a.status = 'absent')  AS absent,
                MIN(ws.session_date) AS first_date,
                MAX(ws.session_date) AS last_date,
                e.status AS enroll_status
            FROM enrollments e
            JOIN users u ON u.id = e.user_id
            $tp_join
            JOIN workshops w ON w.id = e.workshop_id
            JOIN workshop_sessions ws ON ws.workshop_id = w.id
            LEFT JOIN attendance a ON a.session_id = ws.id AND a.user_id = e.user_id
            GROUP BY e.user_id, e.workshop_id
            ORDER BY trainee_name ASC
        ");

        $records = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $total   = (int)$row['total_sessions'];
            $done    = (int)$row['done_sessions'];
            $present = (int)$row['present'];
            $late    = (int)$row['late'];
            $rate    = $done > 0 ? round((($present + $late) / $done) * 100) : 0;

            // Status
            if ($done >= $total && $total > 0) $status = 'Completed';
            elseif ($done > 0)                  $status = 'Ongoing';
            else                                $status = 'Incomplete';

            // Date range
            $dateRange = '';
            if ($row['first_date'] && $row['last_date']) {
                $first = date('M j, Y', strtotime($row['first_date']));
                $last  = date('M j, Y', strtotime($row['last_date']));
                $dateRange = $first === $last ? $first : "$first – $last";
            }

            $records[] = [
                'user_id'      => (int)$row['user_id'],
                'workshop_id'  => (int)$row['workshop_id'],
                'trainee'      => $row['trainee_name'],
                'email'        => $row['email'],
                'workshop'     => $row['workshop'],
                'date_range'   => $dateRange,
                'total'        => $total,
                'done'         => $done,
                'present'      => $present,
                'late'         => $late,
                'absent'       => (int)$row['absent'],
                'rate'         => $rate,
                'status'       => $status,
            ];
        }
        echo json_encode(['success' => true, 'records' => $records]);
        break;

    // ── By Workshop summary ───────────────────────────────────
    case 'workshops':
        $r = $conn->query("
            SELECT
                w.title,
                COUNT(DISTINCT ws.id) AS sessions,
                COUNT(DISTINCT e.user_id) AS enrolled,
                SUM(a.status='present') AS present,
                SUM(a.status='late')    AS late,
                SUM(a.status='absent')  AS absent,
                COUNT(a.id)             AS total_att
            FROM workshops w
            LEFT JOIN workshop_sessions ws ON ws.workshop_id = w.id
            LEFT JOIN enrollments e ON e.workshop_id = w.id
            LEFT JOIN attendance a ON a.session_id = ws.id
            GROUP BY w.id
            ORDER BY w.title ASC
        ");
        $workshops = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $t   = (int)$row['total_att'];
            $p   = (int)$row['present'];
            $l   = (int)$row['late'];
            $avg = $t > 0 ? round((($p+$l)/$t)*100) . '%' : '0%';
            $workshops[] = [
                'name'     => $row['title'],
                'sessions' => (int)$row['sessions'],
                'enrolled' => (int)$row['enrolled'],
                'avg_rate' => $avg,
                'present'  => $p,
                'late'     => $l,
                'absent'   => (int)$row['absent'],
            ];
        }
        echo json_encode(['success' => true, 'workshops' => $workshops]);
        break;

    // ── By Session summary ────────────────────────────────────
    case 'sessions':
        $r = $conn->query("
            SELECT
                w.title AS workshop,
                ws.id AS session_id,
                ws.session_no,
                ws.session_date,
                SUM(a.status='present') AS present,
                SUM(a.status='late')    AS late,
                SUM(a.status='absent')  AS absent,
                COUNT(a.id)             AS total
            FROM workshop_sessions ws
            JOIN workshops w ON w.id = ws.workshop_id
            LEFT JOIN attendance a ON a.session_id = ws.id
            GROUP BY ws.id
            ORDER BY ws.session_date DESC
            LIMIT 50
        ");
        $sessions = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $t    = (int)$row['total'];
            $p    = (int)$row['present'];
            $l    = (int)$row['late'];
            $rate = $t > 0 ? round((($p+$l)/$t)*100) . '%' : '0%';
            $sessions[] = [
                'workshop'   => $row['workshop'],
                'session_id' => (int)$row['session_id'],
                'session'    => 'Session ' . $row['session_no'],
                'date'       => $row['session_date'] ? date('M j, Y', strtotime($row['session_date'])) : '—',
                'present'    => $p,
                'late'       => $l,
                'absent'     => (int)$row['absent'],
                'rate'       => $rate,
            ];
        }
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        break;

    // ── Session detail: per-trainee attendance ────────────────
    case 'session_detail':
        $uid = (int)($_GET['user_id']     ?? 0);
        $wid = (int)($_GET['workshop_id'] ?? 0);

        if (!$uid || !$wid) {
            echo json_encode(['success' => false, 'message' => 'Missing user_id or workshop_id.']);
            break;
        }

        $r = $conn->query("
            SELECT
                ws.id AS session_id,
                ws.session_no,
                ws.session_date,
                a.status,
                a.recorded_at
            FROM workshop_sessions ws
            LEFT JOIN attendance a ON a.session_id = ws.id AND a.user_id = $uid
            WHERE ws.workshop_id = $wid
            ORDER BY ws.session_no ASC
        ");
        $sessions = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $sessions[] = [
                'session_id'  => (int)$row['session_id'],
                'num'         => (int)$row['session_no'],
                'date'        => $row['session_date'] ? date('M j, Y', strtotime($row['session_date'])) : 'TBD',
                'status'      => $row['status'] ?? 'upcoming',
                'notes'       => '',
            ];
        }
        echo json_encode(['success' => true, 'sessions' => $sessions]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();