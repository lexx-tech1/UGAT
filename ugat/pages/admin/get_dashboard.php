<?php
// ============================================================
//  admin/get_dashboard.php
//  Returns all admin dashboard data as JSON.
// ============================================================
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$data = [];

// ── 1. KPI: Total Trainees ───────────────────────────────────
$r = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='trainee' AND is_active=1");
$data['total_trainees'] = (int)$r->fetch_assoc()['total'];

// Trainees added this month
$r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role='trainee' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$data['trainees_this_month'] = (int)$r->fetch_assoc()['cnt'];

// ── 2. KPI: Active Workshops ─────────────────────────────────
$r = $conn->query("SELECT COUNT(*) AS total FROM workshops WHERE status IN ('upcoming','ongoing')");
$data['active_workshops'] = (int)$r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM workshops WHERE status='upcoming'");
$data['upcoming_workshops'] = (int)$r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM workshops WHERE status='ongoing'");
$data['ongoing_workshops'] = (int)$r->fetch_assoc()['cnt'];

// ── 3. KPI: Avg Attendance Rate ──────────────────────────────
$r = $conn->query("SELECT COUNT(*) AS total, SUM(status='present') AS present FROM attendance");
$att = $r->fetch_assoc();
$data['avg_attendance'] = $att['total'] > 0
    ? round(($att['present'] / $att['total']) * 100) . '%'
    : '0%';

// ── 4. KPI: Certificates ─────────────────────────────────────
$r = $conn->query("SELECT COUNT(*) AS issued FROM certificates WHERE status='issued'");
$data['certs_issued'] = (int)$r->fetch_assoc()['issued'];

$r = $conn->query("SELECT COUNT(*) AS eligible FROM certificates WHERE status='eligible'");
$data['certs_eligible'] = (int)$r->fetch_assoc()['eligible'];

// ── 5. KPI: Stock Alerts ─────────────────────────────────────
$r = $conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE quantity=0");
$data['stock_out'] = (int)$r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE quantity>0 AND quantity<=low_stock_at");
$data['stock_low'] = (int)$r->fetch_assoc()['cnt'];

$data['stock_alerts'] = $data['stock_out'] + $data['stock_low'];

// ── 6. Enrollment Chart (monthly) ───────────────────────────
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$enrolled_by_month  = array_fill(0, 12, 0);
$certified_by_month = array_fill(0, 12, 0);

$r = $conn->query("SELECT MONTH(enrolled_at)-1 AS m, COUNT(*) AS cnt FROM enrollments WHERE YEAR(enrolled_at)=YEAR(NOW()) GROUP BY m");
while ($row = $r->fetch_assoc()) $enrolled_by_month[(int)$row['m']] = (int)$row['cnt'];

$r = $conn->query("SELECT MONTH(issued_at)-1 AS m, COUNT(*) AS cnt FROM certificates WHERE status='issued' AND YEAR(issued_at)=YEAR(NOW()) GROUP BY m");
while ($row = $r->fetch_assoc()) $certified_by_month[(int)$row['m']] = (int)$row['cnt'];

$current_month = (int)date('n');
$data['enrollment_chart'] = [
    'labels'    => array_slice($months, 0, $current_month),
    'enrolled'  => array_slice($enrolled_by_month,  0, $current_month),
    'certified' => array_slice($certified_by_month, 0, $current_month),
];

// ── 7. Attendance Bars ───────────────────────────────────────
$r = $conn->query(
    "SELECT w.title,
            SUM(a.status='present') AS present,
            SUM(a.status='late')    AS late,
            SUM(a.status='absent')  AS absent,
            COUNT(a.id)             AS total
     FROM workshops w
     JOIN workshop_sessions ws ON ws.workshop_id = w.id
     JOIN attendance a         ON a.session_id   = ws.id
     WHERE w.status IN ('ongoing','upcoming')
     GROUP BY w.id
     ORDER BY total DESC
     LIMIT 5"
);
$data['attendance_bars'] = [];
while ($row = $r->fetch_assoc()) {
    $data['attendance_bars'][] = [
        'name'    => $row['title'],
        'present' => (int)$row['present'],
        'late'    => (int)$row['late'],
        'absent'  => (int)$row['absent'],
        'total'   => (int)$row['total'],
    ];
}

// ── 8. Upcoming Sessions ─────────────────────────────────────
$r = $conn->query(
    "SELECT w.title, ws.session_no, ws.session_date, ws.start_time, ws.status,
            w.location, w.max_slots,
            (SELECT COUNT(*) FROM enrollments e WHERE e.workshop_id=w.id) AS enrolled_count
     FROM workshop_sessions ws
     JOIN workshops w ON w.id = ws.workshop_id
     WHERE ws.session_date >= CURDATE()
     ORDER BY ws.session_date ASC
     LIMIT 4"
);
$data['upcoming_sessions'] = [];
while ($row = $r->fetch_assoc()) {
    $date = new DateTime($row['session_date']);
    $data['upcoming_sessions'][] = [
        'month'    => strtoupper($date->format('M')),
        'day'      => $date->format('j'),
        'title'    => $row['title'] . ' — Session ' . $row['session_no'],
        'meta'     => $date->format('g:i A') . ' · ' . $row['location'] . ' · ' . $row['enrolled_count'] . '/' . $row['max_slots'] . ' enrolled',
        'status'   => $row['status'],
    ];
}

// ── 9. Certification Donut ───────────────────────────────────
$r = $conn->query("SELECT COUNT(*) AS cnt FROM certificates WHERE status='issued'");
$issued = (int)$r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM certificates WHERE status='eligible'");
$eligible = (int)$r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments WHERE status='enrolled'");
$in_progress = (int)$r->fetch_assoc()['cnt'];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role='trainee' AND is_active=0");
$inactive = (int)$r->fetch_assoc()['cnt'];

$data['cert_donut'] = [
    ['label'=>'Certified',   'value'=>$issued,      'color'=>'#4B8423'],
    ['label'=>'Eligible',    'value'=>$eligible,    'color'=>'#f4a523'],
    ['label'=>'In Progress', 'value'=>$in_progress, 'color'=>'#8dc63f'],
    ['label'=>'Inactive',    'value'=>$inactive,    'color'=>'#e0e0e0'],
];

// ── 10. Stock Alerts ─────────────────────────────────────────
$r = $conn->query(
    "SELECT name, quantity, unit, low_stock_at,
            CASE WHEN quantity=0 THEN 'out' ELSE 'low' END AS alert_status
     FROM inventory
     WHERE quantity <= low_stock_at
     ORDER BY quantity ASC
     LIMIT 5"
);
$data['stock_alerts_list'] = [];
while ($row = $r->fetch_assoc()) {
    $data['stock_alerts_list'][] = [
        'name'   => $row['name'],
        'qty'    => $row['quantity'] . ' ' . $row['unit'],
        'status' => $row['alert_status'],
    ];
}

// ── 11. Activity Feed ────────────────────────────────────────
$r = $conn->query("SELECT action, color, created_at FROM activity_log ORDER BY created_at DESC LIMIT 6");
$data['activity'] = [];
while ($row = $r->fetch_assoc()) {
    $diff = time() - strtotime($row['created_at']);
    if ($diff < 3600)       $time = round($diff/60)  . 'm ago';
    elseif ($diff < 86400)  $time = round($diff/3600) . 'h ago';
    else                    $time = round($diff/86400) . 'd ago';

    $data['activity'][] = [
        'text'  => $row['action'],
        'color' => $row['color'],
        'time'  => $time,
    ];
}

$conn->close();
echo json_encode(['success' => true, 'data' => $data]);