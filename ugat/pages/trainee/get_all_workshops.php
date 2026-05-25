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

$sql = "
    SELECT
        w.id,
        w.title,
        w.category,
        w.facilitator,
        w.location,
        w.status,
        w.max_slots,
        (SELECT COUNT(*) FROM enrollments e 
         WHERE e.workshop_id = w.id 
         AND e.status IN ('pending','enrolled')) AS filled_slots,
        (SELECT COUNT(*) FROM enrollments e 
         WHERE e.workshop_id = w.id 
         AND e.user_id = ? 
         AND e.status = 'enrolled') AS already_enrolled,
        (SELECT COUNT(*) FROM enrollments e 
         WHERE e.workshop_id = w.id 
         AND e.user_id = ? 
         AND e.status = 'pending') AS is_pending
    FROM workshops w
    WHERE w.status IN ('upcoming', 'ongoing')
    ORDER BY w.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$workshops = [];
foreach ($rows as $row) {
    $sStmt = $conn->prepare("
        SELECT MIN(session_date) as first_date, MAX(session_date) as last_date, COUNT(*) as session_count
        FROM workshop_sessions WHERE workshop_id = ?
    ");
    $sStmt->bind_param('i', $row['id']);
    $sStmt->execute();
    $sRow = $sStmt->get_result()->fetch_assoc();

    $slots_left = max(0, (int)$row['max_slots'] - (int)$row['filled_slots']);

    // Compute status dynamically from session dates instead of the stored field
    $today = date('Y-m-d');
    $firstDate = $sRow['first_date'];
    $lastDate  = $sRow['last_date'];
    if (!$firstDate) {
        $dynamicStatus = $row['status'];
    } elseif ($today < $firstDate) {
        $dynamicStatus = 'upcoming';
    } elseif ($today > $lastDate) {
        $dynamicStatus = 'completed';
    } else {
        $dynamicStatus = 'ongoing';
    }

    // Skip workshops that are already fully in the past
    if ($dynamicStatus === 'completed') continue;

    $workshops[] = [
        'id'              => 'ws-' . $row['id'],
        'raw_id'          => $row['id'],
        'title'           => $row['title'],
        'category'        => $row['category'] ?? 'Workshop',
        'img'             => 'https://images.unsplash.com/photo-1523348837708-15d4a09cfac2?w=600&q=80',
        'location'        => $row['location'] ?? 'UGAT Demo Farm',
        'facilitator'     => $row['facilitator'] ?? '—',
        'status'          => $dynamicStatus,
        'firstDate'       => $firstDate ? date('M j, Y', strtotime($firstDate)) : '—',
        'sessionCount'    => (int)$sRow['session_count'],
        'slotsLeft'       => $slots_left,
        'alreadyEnrolled' => (int)$row['already_enrolled'] > 0,
        'isPending'       => (int)$row['is_pending'] > 0,
    ];
}

echo json_encode(['success' => true, 'workshops' => $workshops]);