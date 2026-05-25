<?php
ini_set('session.cookie_path', '/');
session_name('ugat_trainee');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$data    = json_decode(file_get_contents('php://input'), true);

$workshop_ids   = $data['workshop_ids']   ?? [];
$guardian_name   = trim($data['guardian_name']   ?? '');
$guardian_addr   = trim($data['guardian_addr']   ?? '');
$classification  = trim($data['classification']  ?? '');
$is_pwd          = (int)($data['is_pwd']          ?? 0);
$birthplace_city = trim($data['birthplace_city']  ?? '');
$birthplace_prov = trim($data['birthplace_prov']  ?? '');
$birthplace_reg  = trim($data['birthplace_reg']   ?? '');

// Sanitize phone number - remove all spaces and non-numeric characters except +
$contact_phone = trim($data['contact'] ?? '');
$contact_phone = preg_replace('/[^0-9+]/', '', $contact_phone);  // Remove spaces, dashes, etc.
if (empty($contact_phone) || !preg_match('/^\+\d{1,15}$/', $contact_phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Use +63 format.']);
    exit;
}

if (empty($workshop_ids)) {
    echo json_encode(['success' => false, 'message' => 'No workshop selected.']);
    exit;
}

// Check for session date conflicts with already-enrolled workshops
foreach ($workshop_ids as $wid) {
    $wid = (int)$wid;
    if (!$wid) continue;

    $conflict = $conn->prepare("
        SELECT w.title, ws_new.session_date
        FROM workshop_sessions ws_new
        JOIN workshop_sessions ws_existing ON ws_existing.session_date = ws_new.session_date
        JOIN enrollments e ON e.workshop_id = ws_existing.workshop_id
        JOIN workshops w   ON w.id          = e.workshop_id
        WHERE ws_new.workshop_id = ?
          AND e.user_id          = ?
          AND e.status IN ('pending', 'enrolled')
          AND e.workshop_id     != ?
        LIMIT 1
    ");
    $conflict->bind_param('iii', $wid, $user_id, $wid);
    $conflict->execute();
    $row = $conflict->get_result()->fetch_assoc();
    $conflict->close();

    if ($row) {
        $conflictDate  = date('F j, Y', strtotime($row['session_date']));
        $conflictTitle = $row['title'];
        echo json_encode([
            'success' => false,
            'message' => 'Schedule conflict: this workshop has a session on ' . $conflictDate . ', which overlaps with your enrollment in "' . $conflictTitle . '". Please choose a workshop with a different schedule.',
        ]);
        exit;
    }
}

$conn->begin_transaction();
try {
    // Update trainee profile with latest info
    $stmt = $conn->prepare(
        'UPDATE trainee_profiles SET
            first_name      = ?, last_name       = ?, middle_name     = ?,
            phone           = ?, address         = ?, city            = ?,
            province        = ?, region          = ?, barangay        = ?,
            nationality     = ?, gender          = ?, civil_status    = ?,
            birthdate       = ?, education       = ?, employment      = ?,
            learner_class   = ?, is_pwd          = ?,
            guardian_name   = ?, guardian_addr   = ?,
            birthplace_city = ?, birthplace_prov = ?, birthplace_reg  = ?
         WHERE user_id = ?'
    );
    $stmt->bind_param(
        'ssssssssssssssssssssssi',
        $data['first_name'],  $data['last_name'],   $data['middle_name'],
        $contact_phone,       $data['address'],      $data['city'],
        $data['province'],    $data['region'],       $data['barangay'],
        $data['nationality'], $data['sex'],          $data['civil_status'],
        $data['birthdate'],   $data['education'],    $data['employment'],
        $classification,      $is_pwd,
        $guardian_name,       $guardian_addr,
        $birthplace_city,     $birthplace_prov,      $birthplace_reg,
        $user_id
    );
    $stmt->execute();
    $stmt->close();

    // ── Enroll in each selected workshop ──────────────────────────────────────
    // Status is now 'pending' — admin must approve before it becomes 'enrolled'
    $enrolled = [];
    foreach ($workshop_ids as $wid) {
        $wid = (int)$wid;
        if (!$wid) continue;

        // Skip if trainee already has a pending or active enrollment for this workshop
        $chk = $conn->prepare(
            'SELECT id FROM enrollments
              WHERE user_id = ? AND workshop_id = ?
                AND status IN ("pending", "enrolled")
              LIMIT 1'
        );
        $chk->bind_param('ii', $user_id, $wid);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) { $chk->close(); continue; }
        $chk->close();

        // Insert with status = 'pending' (was 'enrolled')
        $ins = $conn->prepare(
            'INSERT INTO enrollments (user_id, workshop_id, status) VALUES (?, ?, "pending")'
        );
        $ins->bind_param('ii', $user_id, $wid);
        $ins->execute();
        $ins->close();
        $enrolled[] = $wid;
    }

    $conn->commit();
    echo json_encode([
        'success'  => true,
        'message'  => 'Enrollment submitted successfully!',
        'enrolled' => $enrolled,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log('Enrollment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Enrollment failed. Please try again.']);
}

$conn->close();