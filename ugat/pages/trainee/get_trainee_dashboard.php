<?php
ini_set('session.cookie_path', '/');
session_name('ugat_trainee');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ── 1. Enrolled Workshops (ALL statuses including pending) ────
$stmt = $conn->prepare("
    SELECT
        w.id, w.title, w.category, w.status AS workshop_status,
        w.location, e.status AS enrollment_status,
        (SELECT COUNT(*) FROM workshop_sessions ws WHERE ws.workshop_id = w.id) AS total_sessions,
        (SELECT COUNT(*) FROM attendance a
         JOIN workshop_sessions ws ON ws.id = a.session_id
         WHERE ws.workshop_id = w.id AND a.user_id = e.user_id
           AND a.status IN ('present','late')) AS attended_sessions,
        (SELECT MIN(ws.session_date) FROM workshop_sessions ws WHERE ws.workshop_id = w.id) AS first_session_date
    FROM enrollments e
    JOIN workshops w ON w.id = e.workshop_id
    WHERE e.user_id = ?
      AND e.status IN ('pending','enrolled','completed','dropped')
    ORDER BY CASE WHEN e.status = 'pending' THEN 0 ELSE 1 END, e.id DESC
    LIMIT 10
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$workshopsRes = $stmt->get_result();

$workshops = [];
while ($row = $workshopsRes->fetch_assoc()) {
    $total    = (int)$row['total_sessions'];
    $attended = (int)$row['attended_sessions'];
    $workshops[] = [
        'id'                => (int)$row['id'],
        'title'             => $row['title'],
        'category'          => $row['category'],
        'workshop_status'   => $row['workshop_status'],
        'enrollment_status' => $row['enrollment_status'],
        'total_sessions'    => $total,
        'attended_sessions' => $attended,
        'progress_pct'      => $total > 0 ? round(($attended / $total) * 100) : 0,
        'first_session_date'=> $row['first_session_date'],
    ];
}
$stmt->close();

// ── 2. Certificates ───────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT c.id, c.status, c.certificate_number, c.issued_at, c.attendance_rate, w.title AS workshop_title
    FROM certificates c
    JOIN workshops w ON w.id = c.workshop_id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC LIMIT 5
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$certRes = $stmt->get_result();
$certificates = [];
while ($row = $certRes->fetch_assoc()) {
    $certificates[] = [
        'id'             => (int)$row['id'],
        'status'         => $row['status'],
        'cert_no'        => $row['certificate_number'],
        'issued_at'      => $row['issued_at'],
        'attendance_rate'=> $row['attendance_rate'],
        'workshop_title' => $row['workshop_title'],
    ];
}
$stmt->close();

// ── 3. Stats ──────────────────────────────────────────────────
$statsRes  = $conn->query("SELECT COUNT(*) AS n FROM enrollments WHERE user_id=$user_id AND status IN ('pending','enrolled','completed')");
$issuedRes = $conn->query("SELECT COUNT(*) AS n FROM certificates WHERE user_id=$user_id AND status='issued'");
$totalEnrolled = (int)($statsRes->fetch_assoc()['n'] ?? 0);
$totalIssued   = (int)($issuedRes->fetch_assoc()['n'] ?? 0);

// ── 4. Notifications ──────────────────────────────────────────
$notifications = [];

$notifRes = $conn->prepare("
    SELECT e.enrolled_at, e.reviewed_at, e.status, w.title
    FROM enrollments e JOIN workshops w ON w.id = e.workshop_id
    WHERE e.user_id = ?
    ORDER BY e.enrolled_at DESC LIMIT 5
");
$notifRes->bind_param('i', $user_id);
$notifRes->execute();
$nResult = $notifRes->get_result();
while ($row = $nResult->fetch_assoc()) {
    if ($row['status'] === 'pending') {
        $msg = "Enrollment in \"{$row['title']}\" is pending admin approval.";
        $dot = 'yellow'; $date = $row['enrolled_at'];
    } elseif ($row['status'] === 'enrolled') {
        $msg = "Enrollment in \"{$row['title']}\" has been approved!";
        $dot = 'green'; $date = $row['reviewed_at'] ?? $row['enrolled_at'];
    } elseif ($row['status'] === 'rejected') {
        $msg = "Your enrollment in \"{$row['title']}\" was rejected.";
        $dot = 'red'; $date = $row['reviewed_at'] ?? $row['enrolled_at'];
    } else {
        $msg = "Enrollment in \"{$row['title']}\" confirmed.";
        $dot = 'green'; $date = $row['enrolled_at'];
    }
    $notifications[] = ['type'=>'enrollment','message'=>$msg,'date'=>$date,'dot'=>$dot,'status'=>$row['status']];
}
$notifRes->close();

$certNotifRes = $conn->prepare("
    SELECT c.issued_at, w.title, c.certificate_number
    FROM certificates c JOIN workshops w ON w.id = c.workshop_id
    WHERE c.user_id = ? AND c.status = 'issued'
    ORDER BY c.issued_at DESC LIMIT 2
");
$certNotifRes->bind_param('i', $user_id);
$certNotifRes->execute();
$cnResult = $certNotifRes->get_result();
while ($row = $cnResult->fetch_assoc()) {
    $notifications[] = [
        'type'=>'certificate',
        'message'=>"Your \"{$row['title']}\" certificate ({$row['certificate_number']}) has been issued!",
        'date'=>$row['issued_at'], 'dot'=>'blue', 'status'=>'issued'
    ];
}
$certNotifRes->close();

usort($notifications, fn($a,$b) => strtotime($b['date']) - strtotime($a['date']));
$conn->close();

echo json_encode([
    'success'       => true,
    'workshops'     => $workshops,
    'certificates'  => $certificates,
    'notifications' => array_slice($notifications, 0, 5),
    'stats'         => ['total_enrolled'=>$totalEnrolled,'total_issued'=>$totalIssued],
]);