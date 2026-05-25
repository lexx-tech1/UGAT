<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/approve_error.log');
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

if (!$conn) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'DB connection failed.']);
    exit;
}

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action']        ?? '';
$id     = (int)($data['enrollment_id'] ?? 0);

if (!$id || !in_array($action, ['approve', 'reject'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$newStatus = ($action === 'approve') ? 'enrolled' : 'rejected';

$detailStmt = $conn->prepare(
    'SELECT e.user_id,
            w.title AS workshop_title,
            u.email AS user_email,
            COALESCE(np.email, "") AS notif_email,
            CONCAT(COALESCE(tp.first_name,""), " ", COALESCE(tp.last_name,"")) AS trainee_name
     FROM enrollments e
     JOIN workshops w ON e.workshop_id = w.id
     JOIN users u ON e.user_id = u.id
     LEFT JOIN trainee_profiles tp ON tp.user_id = e.user_id
     LEFT JOIN notification_preferences np ON np.user_id = e.user_id
     WHERE e.id = ?'
);

if ($detailStmt) {
    $detailStmt->bind_param('i', $id);
    $detailStmt->execute();
    $enrollmentDetail = $detailStmt->get_result()->fetch_assoc();
    $detailStmt->close();
} else {
    $enrollmentDetail = null;
}

$stmt = $conn->prepare(
    'UPDATE enrollments
        SET status = ?, reviewed_at = NOW()
      WHERE id = ? AND status = "pending"'
);

if ($stmt) {
    $stmt->bind_param('si', $newStatus, $id);
    $stmt->execute();
    $rows_affected = $stmt->affected_rows;
    $stmt->close();
} else {
    $rows_affected = 0;
}

if ($rows_affected > 0) {
    if ($action === 'approve' && $enrollmentDetail) {
        try {
            require_once '../../config/sms_helpers.php';
            require_once '../../config/email_service.php';
            require_once '../../config/email.php';

            // Send SMS (direct, bypasses preferences)
            $sms_result = sendWorkshopEnrollmentNotification(
                $enrollmentDetail['user_id'],
                $enrollmentDetail['workshop_title'],
                ''
            );
            error_log('SMS Result: ' . json_encode($sms_result));

            // Send approval email (always send — this is a critical notification)
            $recipient_email = trim($enrollmentDetail['notif_email']) ?: $enrollmentDetail['user_email'];
            if ($recipient_email) {
                $name = trim($enrollmentDetail['trainee_name']) ?: 'Trainee';
                $template = getEmailTemplate('workshop_enrollment', [
                    'name'          => $name,
                    'workshop_name' => $enrollmentDetail['workshop_title'],
                    'date'          => 'Check workshop details',
                    'time'          => '',
                    'link'          => 'http://localhost:8080/UGAT/pages/trainee/TraineeWorkshops.html',
                ]);
                $email_svc = getEmailService($conn);
                $email_result = $email_svc->sendEmail($recipient_email, $template['subject'], $template['body']);
                error_log('Email Result: ' . json_encode($email_result));
            }
        } catch (\Throwable $e) {
            error_log('Notification Error: ' . $e->getMessage());
        }
    }

    if ($action === 'reject' && $enrollmentDetail) {
        try {
            require_once '../../config/sms_helpers.php';
            require_once '../../config/email_service.php';
            require_once '../../config/email.php';

            sendSmsForEvent('enrollment_rejected', (int)$enrollmentDetail['user_id'], [
                'workshop_name' => $enrollmentDetail['workshop_title'],
            ]);

            $recipient_email = trim($enrollmentDetail['notif_email']) ?: $enrollmentDetail['user_email'];
            if ($recipient_email) {
                $name     = trim($enrollmentDetail['trainee_name']) ?: 'Trainee';
                $template = getEmailTemplate('enrollment_rejected', [
                    'name'          => $name,
                    'workshop_name' => $enrollmentDetail['workshop_title'],
                ]);
                getEmailService($conn)->sendEmail($recipient_email, $template['subject'], $template['body']);
            }
        } catch (\Throwable $e) {
            error_log('Rejection notification error: ' . $e->getMessage());
        }
    }

    $msg = ($action === 'approve')
        ? 'Enrollment approved! Trainee is now enrolled.'
        : 'Enrollment rejected.';
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => $msg, 'new_status' => $newStatus]);
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Enrollment not found or already reviewed.']);
}

$conn->close();