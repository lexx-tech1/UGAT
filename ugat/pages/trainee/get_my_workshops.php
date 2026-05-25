<?php
session_name('ugat_trainee');

session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Get trainee address for certificate
$addrStmt = $conn->prepare("SELECT address, city, province, region, barangay, first_name, last_name FROM trainee_profiles WHERE user_id = ? LIMIT 1");
$addrStmt->bind_param('i', $user_id);
$addrStmt->execute();
$traineeProfile = $addrStmt->get_result()->fetch_assoc();
$addrStmt->close();
$traineeAddress = '';
if ($traineeProfile) {
    $parts = array_filter([
        $traineeProfile['address'],
        $traineeProfile['barangay'],
        $traineeProfile['city'],
        $traineeProfile['province'],
        $traineeProfile['region'],
    ]);
    $traineeAddress = implode(', ', $parts);
    $traineeName = trim(($traineeProfile['first_name'] ?? '') . ' ' . ($traineeProfile['last_name'] ?? ''));
}

// Get enrolled workshops for this user (pending + enrolled + completed)
$sql = "
    SELECT
        w.id,
        w.title,
        w.category,
        w.facilitator,
        w.location,
        w.status           AS workshop_status,
        e.status           AS enroll_status,
        e.enrolled_at
    FROM enrollments e
    JOIN workshops w ON w.id = e.workshop_id
    WHERE e.user_id = ?
      AND e.status IN ('pending', 'enrolled', 'completed')
    ORDER BY e.enrolled_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$workshops = [];

foreach ($rows as $row) {

    // Get sessions for this workshop
    $sStmt = $conn->prepare("
        SELECT
            ws.id,
            ws.session_no,
            ws.session_date,
            ws.status        AS session_status,
            a.status         AS attend_status
        FROM workshop_sessions ws
        LEFT JOIN attendance a
               ON a.session_id = ws.id
              AND a.user_id    = ?
        WHERE ws.workshop_id = ?
        ORDER BY ws.session_no ASC
    ");
    $sStmt->bind_param('ii', $user_id, $row['id']);
    $sStmt->execute();
    $sessionRows = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $sessions     = [];
    $attended     = 0;
    $foundCurrent = false;

    foreach ($sessionRows as $i => $s) {
        $isDone = ($s['attend_status'] === 'present' || $s['attend_status'] === 'late');
        if ($isDone) $attended++;

        $isCurrent = false;
        if (!$isDone && !$foundCurrent && $s['session_status'] !== 'cancelled') {
            $isCurrent    = true;
            $foundCurrent = true;
        }

        $sessions[] = [
            'label'   => 'Session ' . ($i + 1),
            'date'    => $s['session_date'] ? date('M j, Y', strtotime($s['session_date'])) : '—',
            'done'    => $isDone,
            'current' => $isCurrent,
        ];
    }

    $total = count($sessionRows);
    $rate  = $total > 0 ? round(($attended / $total) * 100) : 0;

    // Build date range from sessions
    $dates     = array_filter(array_column($sessionRows, 'session_date'));
    $first     = $dates ? date('M j, Y', strtotime(min($dates))) : '';
    $last      = $dates ? date('M j, Y', strtotime(max($dates))) : '';
$dateRange = !$first ? '—' : ($first === $last ? $first : $first . ' – ' . $last);


    // Compute status dynamically from actual session dates
    $today   = date('Y-m-d');
    $minDate = $dates ? min($dates) : null;
    $maxDate = $dates ? max($dates) : null;

    if (!$minDate) {
        $dynamicStatus = $row['workshop_status'];
    } elseif ($today < $minDate) {
        $dynamicStatus = 'upcoming';
    } elseif ($today > $maxDate) {
        $dynamicStatus = 'completed';
    } else {
        $dynamicStatus = 'ongoing';
    }

    $enroll = $row['enroll_status'];

    if ($enroll === 'pending') {
        $displayStatus = 'upcoming';
        $certStatus    = 'locked';
    } elseif ($enroll === 'completed' || $dynamicStatus === 'completed') {
        $displayStatus = 'completed';
        $certStatus    = $attended === $total && $total > 0 ? 'pending' : 'locked';
    } else {
        $displayStatus = $dynamicStatus;
        $certStatus    = 'locked';
    }

    // Check if certificate was issued
$cStmt = $conn->prepare("
    SELECT certificate_number, issued_at FROM certificates
    WHERE user_id = ? AND workshop_id = ?
    LIMIT 1
");
    $cStmt->bind_param('ii', $user_id, $row['id']);
    $cStmt->execute();
    $cert = $cStmt->get_result()->fetch_assoc();

    if ($cert) {
        $certStatus = 'issued';
        $certNo = $cert['certificate_number'] ?? '';       
        $certDate   = $cert['issued_at'] ? date('M j, Y', strtotime($cert['issued_at'])) : '';
    } else {
        $certNo   = '';
        $certDate = '';
    }

    $workshops[] = [
        'id'             => 'ws-' . $row['id'],
        'enrollStatus'   => $row['enroll_status'],
        'title'          => $row['title'],
        'category'       => $row['category'] ?? 'Workshop',
        'img'            => 'https://images.unsplash.com/photo-1523348837708-15d4a09cfac2?w=600&q=80',
        'dateRange'      => $dateRange,
        'location'       => $row['location'] ?? 'UGAT Demo Farm',
        'facilitator'    => $row['facilitator'] ?? '—',
        'status'         => $displayStatus,
        'sessions'       => $sessions,
        'attended'       => $attended,
        'total'          => $total,
        'rate'           => $rate,
        'certStatus'     => $certStatus,
        'certNo'         => $certNo,
        'certDate'       => $certDate,
        'traineeAddress' => $traineeAddress ?? '',
        'traineeName'    => $traineeName ?? '',
    ];
}

echo json_encode(['success' => true, 'workshops' => $workshops]);