<?php
// ============================================================
//  admin/get_reports.php
//  Returns all report tab data as JSON.
//  Actions: overview, program, attendance, certifications, inventory
// ============================================================
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$action = $_GET['action'] ?? 'overview';

// ── Period filter helper ──────────────────────────────────────
// Safe query helper — returns 0 on failure instead of crashing
function safeCount($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) {
        error_log('UGAT Reports SQL error: ' . $conn->error . ' | Query: ' . $sql);
        return 0;
    }
    $row = $r->fetch_assoc();
    return $row ? (int)$row['n'] : 0;
}

function periodWhere($col, $period, $from = null, $to = null) {
    switch ($period) {
        case 'month':   return "AND ($col IS NOT NULL AND $col >= DATE_FORMAT(NOW(),'%Y-%m-01') AND $col <= NOW())";
        case 'quarter': return "AND ($col IS NOT NULL AND $col >= DATE_SUB(NOW(), INTERVAL 3 MONTH))";
        case 'year':    return "AND ($col IS NOT NULL AND YEAR($col) = YEAR(NOW()))";
        case 'custom':
            $f = $from ? date('Y-m-d', strtotime($from)) : '1970-01-01';
            $t = $to   ? date('Y-m-d 23:59:59', strtotime($to)) : date('Y-m-d 23:59:59');
            return "AND ($col IS NOT NULL AND $col BETWEEN '$f' AND '$t')";
        default: return ''; // all time — no filter
    }
}

$period = $_GET['period'] ?? 'all';
$from   = $_GET['from']   ?? null;
$to     = $_GET['to']     ?? null;

switch ($action) {

    // ══════════════════════════════════════════════════════════
    //  OVERVIEW
    // ══════════════════════════════════════════════════════════
    case 'overview':
        $data = [];
        $pw_ov = periodWhere('created_at', $period, $from, $to);
        // Detect which date column attendance table uses
        $att_date_col = '';
        foreach (['created_at','date','recorded_at','timestamp','session_date'] as $try_col) {
            if ($conn->query("SHOW COLUMNS FROM attendance LIKE '$try_col'")->num_rows > 0) {
                $att_date_col = $try_col;
                break;
            }
        }
        // If attendance has no date col, join to workshop_sessions for date filtering
        $pw_att = $att_date_col ? periodWhere($att_date_col, $period, $from, $to) : '';
        $pw_cert = periodWhere('issued_at', $period, $from, $to); // issued_at exists (added by migration)

        // Total trainees (period filter on created_at)
        $data['total_trainees'] = safeCount($conn, "SELECT COUNT(*) AS n FROM users WHERE role='trainee' $pw_ov");

        // Active workshops
        $data['active_workshops'] = safeCount($conn, "SELECT COUNT(*) AS n FROM workshops WHERE status IN ('upcoming','ongoing')");

        $data['upcoming_workshops'] = safeCount($conn, "SELECT COUNT(*) AS n FROM workshops WHERE status='upcoming'");

        $data['ongoing_workshops'] = safeCount($conn, "SELECT COUNT(*) AS n FROM workshops WHERE status='ongoing'");

        $data['completed_workshops'] = safeCount($conn, "SELECT COUNT(*) AS n FROM workshops WHERE status='completed'");

        $data['total_sessions'] = safeCount($conn, "SELECT COUNT(*) AS n FROM workshop_sessions");

        // Attendance summary (period filtered)
        $r = $conn->query("SELECT
            COUNT(*) AS total,
            SUM(status='present') AS present,
            SUM(status='late')    AS late,
            SUM(status='absent')  AS absent
            FROM attendance WHERE 1=1 $pw_att");
        $att = $r ? $r->fetch_assoc() : null;
        $data['att_total']   = $att ? (int)$att['total']   : 0;
        $data['att_present'] = $att ? (int)$att['present'] : 0;
        $data['att_late']    = $att ? (int)$att['late']    : 0;
        $data['att_absent']  = $att ? (int)$att['absent']  : 0;
        $data['att_rate']    = $att['total'] > 0
            ? round((($att['present'] + $att['late']) / $att['total']) * 100)
            : 0;

        // At-risk trainees (< 67% attendance) — subquery avoids alias in HAVING
        $r = $conn->query("
            SELECT COUNT(*) AS n FROM (
                SELECT e.user_id, e.workshop_id,
                       COUNT(a.id) AS total_att,
                       SUM(a.status IN ('present','late')) AS attended
                FROM enrollments e
                JOIN workshop_sessions ws ON ws.workshop_id = e.workshop_id
                LEFT JOIN attendance a ON a.session_id = ws.id AND a.user_id = e.user_id
                GROUP BY e.user_id, e.workshop_id
            ) AS sub
            WHERE total_att > 0 AND (attended / total_att) < 0.67
        ");
        $data['at_risk'] = ($r && $r->num_rows > 0) ? (int)$r->fetch_assoc()['n'] : 0;

        // Certifications
        // For certs, use created_at if issued_at is not reliable
        $r = $conn->query("SELECT COUNT(*) AS n FROM certificates WHERE status='issued' $pw_cert");
        $res = $r ? $r->fetch_assoc() : null;
        $data['certs_issued'] = $res ? (int)$res['n'] : 0;

        $data['certs_eligible'] = safeCount($conn, "SELECT COUNT(*) AS n FROM certificates WHERE status='eligible'");

        // Cert rate = issued / total trainees
        $data['cert_rate'] = $data['total_trainees'] > 0
            ? round(($data['certs_issued'] / $data['total_trainees']) * 100)
            : 0;

        // SMS notifications — guard against missing sms_sent column
        $has_sms = $conn->query("SHOW COLUMNS FROM certificates LIKE 'sms_sent'")->num_rows > 0;
        if ($has_sms) {
            $r1 = $conn->query("SELECT COUNT(*) AS n FROM certificates WHERE sms_sent=1 $pw_cert");
            $r2 = $conn->query("SELECT COUNT(*) AS n FROM certificates WHERE sms_sent=0 AND status='issued' $pw_cert");
            $data['sms_sent']    = $r1 ? (int)$r1->fetch_assoc()['n'] : 0;
            $data['sms_pending'] = $r2 ? (int)$r2->fetch_assoc()['n'] : 0;
        } else {
            $data['sms_sent'] = $data['sms_pending'] = 0;
        }

        // Stock alerts
        $data['stock_out'] = safeCount($conn, "SELECT COUNT(*) AS n FROM inventory WHERE quantity=0");

        $data['stock_low'] = safeCount($conn, "SELECT COUNT(*) AS n FROM inventory WHERE quantity>0 AND quantity<=low_stock_at");

        // Trainee breakdown — check which date column exists in enrollments
        $has_enrolled_at = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'enrolled_at'")->num_rows > 0;
        $enroll_col = $has_enrolled_at ? 'enrolled_at' : 'created_at';
        $pw_enroll = periodWhere($enroll_col, $period, $from, $to);
        $r = $conn->query("SELECT COUNT(*) AS n FROM enrollments WHERE status='enrolled' $pw_enroll");
        $data['trainees_active'] = (int)$r->fetch_assoc()['n'];

        $r = $conn->query("SELECT COUNT(*) AS n FROM certificates WHERE status='eligible'");
        $data['trainees_pending'] = (int)$r->fetch_assoc()['n'];

        $data['trainees_inactive'] = safeCount($conn, "SELECT COUNT(*) AS n FROM users WHERE role='trainee' AND is_active=0 $pw_ov");

        // Enrollment chart — adapts to selected period
        $months_all = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $enroll_date_col = $has_enrolled_at ? 'enrolled_at' : 'created_at';
        $chart_labels = $chart_enrolled = $chart_certified = [];

        if ($period === 'month') {
            // Daily for current month
            $days_in_month = (int)date('t');
            $cur_day = (int)date('j');
            $enrolled_by_day  = array_fill(1, $days_in_month, 0);
            $certified_by_day = array_fill(1, $days_in_month, 0);

            $r = $conn->query("SELECT DAY($enroll_date_col) AS d, COUNT(*) AS cnt
                FROM enrollments
                WHERE YEAR($enroll_date_col)=YEAR(NOW())
                AND MONTH($enroll_date_col)=MONTH(NOW())
                GROUP BY d");
            if ($r) while ($row = $r->fetch_assoc()) $enrolled_by_day[(int)$row['d']] = (int)$row['cnt'];

            $r2 = $conn->query("SELECT DAY(issued_at) AS d, COUNT(*) AS cnt
                FROM certificates WHERE status='issued'
                AND YEAR(issued_at)=YEAR(NOW()) AND MONTH(issued_at)=MONTH(NOW())
                GROUP BY d");
            if ($r2) while ($row = $r2->fetch_assoc()) $certified_by_day[(int)$row['d']] = (int)$row['cnt'];

            for ($d = 1; $d <= $cur_day; $d++) {
                $chart_labels[]    = date('M') . ' ' . $d;
                $chart_enrolled[]  = $enrolled_by_day[$d] ?? 0;
                $chart_certified[] = $certified_by_day[$d] ?? 0;
            }

        } elseif ($period === 'quarter') {
            // Monthly for last 3 months
            for ($i = 2; $i >= 0; $i--) {
                $ts    = strtotime("-$i month");
                $m_num = (int)date('n', $ts);
                $y_num = (int)date('Y', $ts);
                $chart_labels[] = date('M Y', $ts);

                $r = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments
                    WHERE MONTH($enroll_date_col)=$m_num AND YEAR($enroll_date_col)=$y_num");
                $chart_enrolled[] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;

                $r2 = $conn->query("SELECT COUNT(*) AS cnt FROM certificates
                    WHERE status='issued' AND MONTH(issued_at)=$m_num AND YEAR(issued_at)=$y_num");
                $chart_certified[] = $r2 ? (int)$r2->fetch_assoc()['cnt'] : 0;
            }

        } elseif ($period === 'custom' && $from && $to) {
            // Monthly between custom dates
            $start = new DateTime($from);
            $end   = new DateTime($to);
            $interval = new DateInterval('P1M');
            $range = new DatePeriod($start, $interval, $end);
            foreach ($range as $dt) {
                $m_num = (int)$dt->format('n');
                $y_num = (int)$dt->format('Y');
                $chart_labels[] = $dt->format('M Y');

                $r = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments
                    WHERE MONTH($enroll_date_col)=$m_num AND YEAR($enroll_date_col)=$y_num");
                $chart_enrolled[] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;

                $r2 = $conn->query("SELECT COUNT(*) AS cnt FROM certificates
                    WHERE status='issued' AND MONTH(issued_at)=$m_num AND YEAR(issued_at)=$y_num");
                $chart_certified[] = $r2 ? (int)$r2->fetch_assoc()['cnt'] : 0;
            }
            // Add end month if not included
            $m_num = (int)$end->format('n');
            $y_num = (int)$end->format('Y');
            $last_label = $end->format('M Y');
            if (!in_array($last_label, $chart_labels)) {
                $chart_labels[] = $last_label;
                $r  = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments WHERE MONTH($enroll_date_col)=$m_num AND YEAR($enroll_date_col)=$y_num");
                $r2 = $conn->query("SELECT COUNT(*) AS cnt FROM certificates WHERE status='issued' AND MONTH(issued_at)=$m_num AND YEAR(issued_at)=$y_num");
                $chart_enrolled[]  = $r  ? (int)$r->fetch_assoc()['cnt']  : 0;
                $chart_certified[] = $r2 ? (int)$r2->fetch_assoc()['cnt'] : 0;
            }

        } else {
            // All Time or This Year — monthly for current year
            $enrolled_by_month  = array_fill(0, 12, 0);
            $certified_by_month = array_fill(0, 12, 0);

            $year_filter = $period === 'year' ? "AND YEAR($enroll_date_col)=YEAR(NOW())" : "AND YEAR($enroll_date_col)>=YEAR(NOW())-2";
            $r = $conn->query("SELECT MONTH($enroll_date_col)-1 AS m, COUNT(*) AS cnt
                               FROM enrollments WHERE 1=1 $year_filter GROUP BY m");
            if ($r) while ($row = $r->fetch_assoc()) $enrolled_by_month[(int)$row['m']] = (int)$row['cnt'];

            $year_filter2 = $period === 'year' ? "AND YEAR(issued_at)=YEAR(NOW())" : "AND YEAR(issued_at)>=YEAR(NOW())-2";
            $r2 = $conn->query("SELECT MONTH(issued_at)-1 AS m, COUNT(*) AS cnt
                                FROM certificates WHERE status='issued' $year_filter2 GROUP BY m");
            if ($r2) while ($row = $r2->fetch_assoc()) $certified_by_month[(int)$row['m']] = (int)$row['cnt'];

            $cur = (int)date('n');
            $chart_labels    = array_slice($months_all, 0, $cur);
            $chart_enrolled  = array_slice($enrolled_by_month, 0, $cur);
            $chart_certified = array_slice($certified_by_month, 0, $cur);
        }

        $data['enrollment_chart'] = [
            'labels'    => $chart_labels,
            'enrolled'  => $chart_enrolled,
            'certified' => $chart_certified,
        ];

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ══════════════════════════════════════════════════════════
    //  PROGRAM
    // ══════════════════════════════════════════════════════════
    case 'program':
        try {
            // Detect enrollment date column
            $has_enr_at = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'enrolled_at'")->num_rows > 0;
            $has_enr_ca = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'created_at'")->num_rows > 0;
            $enr_col    = $has_enr_at ? 'enrolled_at' : ($has_enr_ca ? 'created_at' : null);
            // Only filter by enroll date if the column exists
            $pw_prog = $enr_col ? periodWhere($enr_col, $period, $from, $to) : '';

            // Enrollment by workshop
            $ws_sql = "SELECT w.id, w.title, w.category, w.max_slots, w.status,
                       COUNT(DISTINCT e.user_id) AS enrolled,
                       COUNT(DISTINCT ws.id) AS total_sessions,
                       SUM(CASE WHEN ws.status='completed' THEN 1 ELSE 0 END) AS completed_sessions
                       FROM workshops w
                       LEFT JOIN enrollments e ON e.workshop_id = w.id " .
                       ($enr_col ? "AND ($enr_col IS NULL OR $enr_col $pw_prog)" : "") . "
                       LEFT JOIN workshop_sessions ws ON ws.workshop_id = w.id
                       GROUP BY w.id ORDER BY w.title ASC";
            // Simpler: no date filter on workshop list, always show all
            $r = $conn->query("SELECT w.id, w.title, w.category, w.max_slots, w.status,
                   COUNT(DISTINCT e.user_id) AS enrolled,
                   COUNT(DISTINCT ws.id) AS total_sessions,
                   SUM(CASE WHEN ws.status='completed' THEN 1 ELSE 0 END) AS completed_sessions
                   FROM workshops w
                   LEFT JOIN enrollments e ON e.workshop_id = w.id
                   LEFT JOIN workshop_sessions ws ON ws.workshop_id = w.id
                   GROUP BY w.id ORDER BY w.title ASC");
            $workshops = [];
            if ($r) while ($row = $r->fetch_assoc()) $workshops[] = $row;
            else throw new Exception('Workshops query failed: ' . $conn->error);

            // At-risk trainees — safely detect trainee_profiles
            $has_tp = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
            $name_select = $has_tp
                ? "COALESCE(CONCAT(tp.first_name,' ',tp.last_name), SUBSTRING_INDEX(u.email,'@',1))"
                : "SUBSTRING_INDEX(u.email,'@',1)";
            $tp_join = $has_tp ? "LEFT JOIN trainee_profiles tp ON tp.user_id = u.id" : "";

            // Use subquery to avoid alias reference in HAVING
            $r2 = $conn->query("
                SELECT * FROM (
                    SELECT " . $name_select . " AS name,
                        w.title AS workshop,
                        COUNT(a.id) AS total_sessions,
                        SUM(a.status IN ('present','late')) AS attended,
                        MAX(CASE WHEN a.status IN ('present','late') THEN ws.session_date ELSE NULL END) AS last_seen,
                        e.status
                    FROM enrollments e
                    JOIN users u ON u.id = e.user_id
                    " . $tp_join . "
                    JOIN workshops w ON w.id = e.workshop_id
                    JOIN workshop_sessions ws ON ws.workshop_id = w.id
                    LEFT JOIN attendance a ON a.session_id = ws.id AND a.user_id = e.user_id
                    GROUP BY e.user_id, e.workshop_id
                ) AS sub
                WHERE total_sessions > 0
                  AND (attended / total_sessions) < 0.67
                ORDER BY (attended / total_sessions) ASC
            ");
            $at_risk = [];
            if ($r2) {
                while ($row = $r2->fetch_assoc()) {
                    $at_risk[] = [
                        'name'      => $row['name'],
                        'workshop'  => $row['workshop'],
                        'sessions'  => ($row['attended'] ?? 0) . ' / ' . $row['total_sessions'],
                        'rate'      => $row['total_sessions'] > 0
                                        ? round(($row['attended'] / $row['total_sessions']) * 100) . '%'
                                        : '0%',
                        'last_seen' => $row['last_seen'] ? date('M j, Y', strtotime($row['last_seen'])) : 'Never',
                        'status'    => ucfirst($row['status'] ?? 'enrolled'),
                    ];
                }
            }

            echo json_encode(['success' => true, 'workshops' => $workshops, 'at_risk' => $at_risk]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ══════════════════════════════════════════════════════════
    //  ATTENDANCE
    // ══════════════════════════════════════════════════════════
    case 'attendance':
        // Detect attendance date column
        $att_col = '';
        foreach (['recorded_at','created_at','date','timestamp'] as $c) {
            if ($conn->query("SHOW COLUMNS FROM attendance LIKE '$c'")->num_rows > 0) {
                $att_col = $c; break;
            }
        }
        $pw           = $att_col ? periodWhere($att_col, $period, $from, $to) : '';
        $pw_att_col   = $att_col ? "a.$att_col" : null;

        // Overall KPIs
        $r   = $conn->query("SELECT COUNT(*) AS total, SUM(status='present') AS present,
                SUM(status='late') AS late, SUM(status='absent') AS absent
                FROM attendance WHERE 1=1 $pw");
        $kpi     = $r ? $r->fetch_assoc() : ['total'=>0,'present'=>0,'late'=>0,'absent'=>0];
        $total   = (int)($kpi['total']   ?? 0);
        $present = (int)($kpi['present'] ?? 0);
        $late    = (int)($kpi['late']    ?? 0);
        $absent  = (int)($kpi['absent']  ?? 0);
        $rate    = $total > 0 ? round((($present + $late) / $total) * 100) : 0;

        // Per-workshop attendance bars
        $pw_att_col = $att_col ? "a.$att_col" : null;
        $pw_bars = $pw_att_col ? periodWhere($pw_att_col, $period, $from, $to) : '';
        $r = $conn->query("
            SELECT w.title AS name,
                   SUM(a.status='present') AS present,
                   SUM(a.status='late')    AS late,
                   SUM(a.status='absent')  AS absent,
                   COUNT(a.id)             AS total
            FROM attendance a
            JOIN workshop_sessions ws ON ws.id = a.session_id
            JOIN workshops w          ON w.id  = ws.workshop_id
            WHERE 1=1 $pw_bars
            GROUP BY w.id
            ORDER BY total DESC
        ");
        $att_bars = [];
        if ($r) while ($row = $r->fetch_assoc()) $att_bars[] = $row;

        // Low attendance trainees (< 67%)
        $has_tp4 = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
        $name_sel4 = $has_tp4
            ? "COALESCE(CONCAT(tp.first_name,' ',tp.last_name), SUBSTRING_INDEX(u.email,'@',1))"
            : "SUBSTRING_INDEX(u.email,'@',1)";
        $tp_join4 = $has_tp4 ? "LEFT JOIN trainee_profiles tp ON tp.user_id = u.id" : "";

        $pw_low = $pw_att_col ? periodWhere($pw_att_col, $period, $from, $to) : '';
        // Use subquery to avoid alias reference in HAVING
        $low_sql = "SELECT * FROM (
            SELECT " . $name_sel4 . " AS name,
                   w.title AS workshop,
                   COUNT(a.id) AS total,
                   SUM(a.status IN ('present','late')) AS attended
            FROM enrollments e
            JOIN users u ON u.id = e.user_id
            " . $tp_join4 . "
            JOIN workshops w ON w.id = e.workshop_id
            JOIN workshop_sessions ws ON ws.workshop_id = w.id
            LEFT JOIN attendance a ON a.session_id = ws.id AND a.user_id = e.user_id
            WHERE 1=1 $pw_low
            GROUP BY e.user_id, e.workshop_id
        ) AS sub
        WHERE total > 0 AND (attended / total) < 0.67
        ORDER BY (attended / total) ASC";
        $r = $conn->query($low_sql);
        $low_att = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $low_att[] = [
                'name'     => $row['name'],
                'workshop' => $row['workshop'],
                'rate'     => $row['total'] > 0 ? round(($row['attended']/$row['total'])*100) : 0,
                'sessions' => $row['attended'] . ' / ' . $row['total'],
            ];
        }

        // Session-by-session log — filter by session_date
        $has_sess_date = $conn->query("SHOW COLUMNS FROM workshop_sessions LIKE 'session_date'")->num_rows > 0;
        $pw_sess = $has_sess_date ? periodWhere('ws.session_date', $period, $from, $to) : '';
        $r = $conn->query("
            SELECT w.title AS workshop,
                   ws.session_no, ws.session_date,
                   SUM(a.status='present') AS present,
                   SUM(a.status='late')    AS late,
                   SUM(a.status='absent')  AS absent,
                   COUNT(a.id)             AS total
            FROM workshop_sessions ws
            JOIN workshops w       ON w.id = ws.workshop_id
            LEFT JOIN attendance a ON a.session_id = ws.id
            WHERE 1=1 $pw_sess
            GROUP BY ws.id
            ORDER BY ws.session_date DESC
            LIMIT 50
        ");
        $session_log = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $t    = (int)$row['total'];
            $p    = (int)$row['present'];
            $l    = (int)$row['late'];
            $rate = $t > 0 ? round((($p + $l) / $t) * 100) : 0;
            $session_log[] = [
                'workshop'   => $row['workshop'],
                'session'    => 'Session ' . $row['session_no'],
                'date'       => $row['session_date']
                    ? date('M j, Y', strtotime($row['session_date'])) : '—',
                'present'    => $p,
                'late'       => $l,
                'absent'     => (int)$row['absent'],
                'rate'       => $rate . '%',
            ];
        }

        echo json_encode([
            'success'     => true,
            'kpi'         => compact('total','present','late','absent','rate'),
            'att_bars'    => $att_bars,
            'low_att'     => $low_att,
            'session_log' => $session_log,
        ]);
        break;

    // ══════════════════════════════════════════════════════════
    //  CERTIFICATIONS
    // ══════════════════════════════════════════════════════════
    case 'certifications':
        // KPIs
        $pw_c = periodWhere('issued_at', $period, $from, $to);
        $r = $conn->query("SELECT COUNT(*) AS n FROM certificates WHERE status='issued' $pw_c");
        $issued = (int)$r->fetch_assoc()['n'];

        $r = $conn->query("SELECT COUNT(*) AS n FROM certificates WHERE status='eligible'");
        $eligible = (int)$r->fetch_assoc()['n'];

        $r = $conn->query("SELECT COUNT(*) AS n FROM enrollments");
        $total_enrolled = (int)$r->fetch_assoc()['n'];

        // Guard against missing sms_sent column
        $has_sms   = $conn->query("SHOW COLUMNS FROM certificates LIKE 'sms_sent'")->num_rows > 0;
        $sms_sent   = $has_sms ? (int)$conn->query("SELECT COUNT(*) AS n FROM certificates WHERE sms_sent=1 $pw_c")->fetch_assoc()['n'] : 0;
        $sms_pending = $has_sms ? (int)$conn->query("SELECT COUNT(*) AS n FROM certificates WHERE sms_sent=0 AND status='issued' $pw_c")->fetch_assoc()['n'] : 0;

        $cert_rate = $total_enrolled > 0 ? round(($issued / $total_enrolled) * 100) : 0;

        // Certs by workshop — uses pw_c for issued filter
        $issued_filter = $period !== 'all' ? "AND (c.issued_at IS NOT NULL $pw_c)" : '';
        $r = $conn->query("
            SELECT w.title AS workshop,
                   COUNT(DISTINCT e.user_id) AS enrolled,
                   COUNT(DISTINCT CASE WHEN c.status='eligible' THEN c.user_id END) AS eligible,
                   COUNT(DISTINCT CASE WHEN c.status='issued' $issued_filter THEN c.user_id END) AS issued
            FROM workshops w
            LEFT JOIN enrollments e  ON e.workshop_id = w.id
            LEFT JOIN certificates c ON c.workshop_id = w.id AND c.user_id = e.user_id
            GROUP BY w.id
            ORDER BY w.title ASC
        ");
        $cert_by_workshop = [];
        while ($row = $r->fetch_assoc()) {
            $row['rate'] = $row['enrolled'] > 0
                ? round(($row['issued'] / $row['enrolled']) * 100) . '%'
                : '0%';
            $cert_by_workshop[] = $row;
        }

        // Eligible not yet issued
        $has_tp2 = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
        $name_sel2 = $has_tp2
            ? "COALESCE(CONCAT(tp.first_name,' ',tp.last_name), SUBSTRING_INDEX(u.email,'@',1))"
            : "SUBSTRING_INDEX(u.email,'@',1)";
        $tp_join2 = $has_tp2 ? "LEFT JOIN trainee_profiles tp ON tp.user_id = u.id" : "";

        $r = $conn->query("
            SELECT $name_sel2 AS name,
                   w.title AS workshop,
                   NULL AS attendance_rate,
                   c.created_at AS completed_on
            FROM certificates c
            JOIN users u    ON u.id = c.user_id
            $tp_join2
            JOIN workshops w ON w.id = c.workshop_id
            WHERE c.status = 'eligible'
            ORDER BY c.created_at DESC
        ");
        $cert_pending = [];
        while ($row = $r->fetch_assoc()) {
            $cert_pending[] = [
                'name'        => $row['name'],
                'workshop'    => $row['workshop'],
                'rate'        => ($row['attendance_rate'] ?? 0) . '%',
                'completedOn' => $row['completed_on']
                    ? date('M j, Y', strtotime($row['completed_on'])) : '—',
            ];
        }

        // Issued log
        $has_tp3 = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
        $name_sel3 = $has_tp3
            ? "COALESCE(CONCAT(tp.first_name,' ',tp.last_name), SUBSTRING_INDEX(u.email,'@',1))"
            : "SUBSTRING_INDEX(u.email,'@',1)";
        $tp_join3 = $has_tp3 ? "LEFT JOIN trainee_profiles tp ON tp.user_id = u.id" : "";

        // Apply period filter to issued log — use real sms_sent column
        $sms_col      = $has_sms ? 'c.sms_sent' : '0';
        $cert_log_sql = "SELECT " . $name_sel3 . " AS name,
                   w.title AS workshop,
                   c.certificate_number,
                   c.issued_at,
                   c.attendance_rate,
                   " . $sms_col . " AS sms_sent
            FROM certificates c
            JOIN users u ON u.id = c.user_id
            " . $tp_join3 . "
            JOIN workshops w ON w.id = c.workshop_id
            WHERE c.status = 'issued' " . $pw_c . "
            ORDER BY c.issued_at DESC";
        $r = $conn->query($cert_log_sql);
        $cert_log = [];
        while ($row = $r->fetch_assoc()) {
            $cert_log[] = [
                'name'       => $row['name'],
                'workshop'   => $row['workshop'],
                'cert_no'    => $row['certificate_number'] ?? '—',
                'issued_on'  => $row['issued_at']
                    ? date('M j, Y', strtotime($row['issued_at'])) : '—',
                'rate'       => ($row['attendance_rate'] ?? 0) . '%',
                'sms'        => (bool)$row['sms_sent'],
            ];
        }

        echo json_encode([
            'success'          => true,
            'kpi'              => compact('issued','eligible','cert_rate','sms_sent','sms_pending'),
            'cert_by_workshop' => $cert_by_workshop,
            'cert_pending'     => $cert_pending,
            'cert_log'         => $cert_log,
        ]);
        break;

    // ══════════════════════════════════════════════════════════
    //  INVENTORY
    // ══════════════════════════════════════════════════════════
    case 'inventory':
        // KPIs
        $r = $conn->query("SELECT
            COUNT(*)                                              AS total_skus,
            SUM(unit_price * quantity)                            AS total_value,
            SUM(quantity = 0)                                     AS out_of_stock,
            SUM(quantity > 0 AND quantity <= low_stock_at)        AS low_stock
            FROM inventory");
        $kpi = $r->fetch_assoc();

        // Stock value by item
        $r = $conn->query("
            SELECT name, category, unit, quantity AS stock,
                   unit_price, (unit_price * quantity) AS value,
                   low_stock_at AS reorder_point, max_stock,
                   CASE
                     WHEN quantity = 0              THEN 'out'
                     WHEN quantity <= low_stock_at  THEN 'low'
                     ELSE 'instock'
                   END AS status
            FROM inventory
            ORDER BY value DESC
        ");
        $inv_value = [];
        while ($row = $r->fetch_assoc()) $inv_value[] = $row;

        // Reorder summary
        $r = $conn->query("
            SELECT name, unit, quantity AS stock, low_stock_at AS reorder_point,
                   max_stock, unit_price
            FROM inventory
            WHERE quantity <= low_stock_at
            ORDER BY quantity ASC
        ");
        $inv_reorder = [];
        while ($row = $r->fetch_assoc()) {
            $suggest = max($row['max_stock'] - $row['stock'], $row['reorder_point'] * 2);
            $inv_reorder[] = [
                'name'         => $row['name'],
                'unit'         => $row['unit'],
                'stock'        => (int)$row['stock'],
                'reorder_point'=> (int)$row['reorder_point'],
                'suggest_qty'  => $suggest,
                'est_cost'     => $suggest * $row['unit_price'],
                'priority'     => $row['stock'] == 0 ? 'critical' : 'medium',
            ];
        }

        // Stock movement summary — filter transactions by period using subquery
        $pw_tx  = periodWhere('created_at', $period, $from, $to);
        $tx_sub = "SELECT item_id, type, qty FROM inventory_transactions WHERE 1=1 " . $pw_tx;
        $mv_sql = "SELECT i.id, i.name, i.unit, i.quantity AS current_stock,
                   COALESCE(SUM(CASE WHEN t.type IN ('in','po') THEN t.qty ELSE 0 END), 0) AS total_in,
                   COALESCE(SUM(CASE WHEN t.type = 'out' THEN t.qty ELSE 0 END), 0) AS total_out
                   FROM inventory i
                   LEFT JOIN (" . $tx_sub . ") AS t ON t.item_id = i.id
                   GROUP BY i.id ORDER BY i.name ASC";
        $r = $conn->query($mv_sql);
        $inv_movements = [];
        while ($row = $r->fetch_assoc()) {
            $opening = $row['current_stock'] - $row['total_in'] + $row['total_out'];
            $inv_movements[] = [
                'name'          => $row['name'],
                'unit'          => $row['unit'],
                'opening_stock' => max(0, $opening),
                'total_in'      => (int)$row['total_in'],
                'total_out'     => (int)$row['total_out'],
                'current_stock' => (int)$row['current_stock'],
                'net'           => (int)$row['total_in'] - (int)$row['total_out'],
            ];
        }

        echo json_encode([
            'success'       => true,
            'kpi'           => [
                'total_value'  => (float)$kpi['total_value'],
                'total_skus'   => (int)$kpi['total_skus'],
                'low_stock'    => (int)$kpi['low_stock'],
                'out_of_stock' => (int)$kpi['out_of_stock'],
            ],
            'inv_value'     => $inv_value,
            'inv_reorder'   => $inv_reorder,
            'inv_movements' => $inv_movements,
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();