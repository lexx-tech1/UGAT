<?php
// ============================================================
//  admin/send_sms_notification.php
//  Admin API to send SMS notifications to trainees or groups
//  
//  POST /admin/send_sms_notification.php
//  {
//    "recipient_type": "single|group|all",
//    "recipient_id": "user_id or group_id (for single/group)",
//    "template": "template_key",
//    "custom_message": "Custom message (optional, overrides template)",
//    "template_replacements": { "name": "John", ... }
//  }
// ============================================================

session_name('ugat_admin');
session_start();
require_once '../../config/db.php';
require_once '../../config/auth_guard.php';
require_once '../../config/sms_service.php';

requireRole('admin');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Validate inputs
$recipient_type = trim($data['recipient_type'] ?? '');
$recipient_id = (int)($data['recipient_id'] ?? 0);
$template = trim($data['template'] ?? '');
$custom_message = trim($data['custom_message'] ?? '');
$replacements = $data['template_replacements'] ?? [];

if (empty($recipient_type) || !in_array($recipient_type, ['single', 'group', 'all'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid recipient type.']);
    exit;
}

if ($recipient_type !== 'all' && $recipient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Recipient ID required.']);
    exit;
}

if (empty($custom_message) && empty($template)) {
    echo json_encode(['success' => false, 'message' => 'Provide either template or custom message.']);
    exit;
}

try {
    $sms = getSmsService($conn);
    $admin_id = (int)$_SESSION['user_id'];
    $phone_numbers = [];
    $recipient_details = [];

    // Get phone numbers based on recipient type
    if ($recipient_type === 'single') {
        // Send to single trainee
        $result = $conn->query(
            "SELECT u.id, u.phone, tp.first_name, tp.last_name 
             FROM users u 
             LEFT JOIN trainee_profiles tp ON u.id = tp.user_id 
             WHERE u.id = $recipient_id AND u.role = 'trainee' AND u.is_active = 1"
        );
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Trainee not found.']);
            exit;
        }
        
        $user = $result->fetch_assoc();
        if (empty($user['phone'])) {
            echo json_encode(['success' => false, 'message' => 'Trainee phone number not found.']);
            exit;
        }
        
        $phone_numbers[] = $user['phone'];
        $recipient_details[] = $user;
        
    } elseif ($recipient_type === 'group') {
        // Send to group (e.g., all trainees in a workshop)
        $result = $conn->query(
            "SELECT DISTINCT u.id, u.phone, tp.first_name, tp.last_name 
             FROM users u 
             LEFT JOIN trainee_profiles tp ON u.id = tp.user_id 
             LEFT JOIN workshop_enrollments we ON u.id = we.user_id 
             WHERE we.workshop_id = $recipient_id AND u.role = 'trainee' AND u.is_active = 1 AND u.phone IS NOT NULL"
        );
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No trainees found in this group.']);
            exit;
        }
        
        while ($user = $result->fetch_assoc()) {
            $phone_numbers[] = $user['phone'];
            $recipient_details[] = $user;
        }
        
    } else { // all
        // Send to all active trainees
        $result = $conn->query(
            "SELECT u.id, u.phone, tp.first_name, tp.last_name 
             FROM users u 
             LEFT JOIN trainee_profiles tp ON u.id = tp.user_id 
             WHERE u.role = 'trainee' AND u.is_active = 1 AND u.phone IS NOT NULL"
        );
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No trainees with phone numbers found.']);
            exit;
        }
        
        while ($user = $result->fetch_assoc()) {
            $phone_numbers[] = $user['phone'];
            $recipient_details[] = $user;
        }
    }

    // Prepare message
    if (!empty($custom_message)) {
        $message = $custom_message;
    } else {
        $message = getSmsTemplate($template, $replacements);
    }

    // Send SMS to all recipients
    $results = $sms->sendSmsToMultiple($phone_numbers, $message, [
        'sender_id' => $admin_id,
        'recipient_type' => $recipient_type,
        'recipient_id' => $recipient_id,
        'template' => $template
    ]);

    // Log notification in database
    $stmt = $conn->prepare(
        "INSERT INTO sms_notifications (admin_id, recipient_type, recipient_id, template, message, count, sent_at, status) 
         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)"
    );
    
    $count = count($results);
    $status = 'sent';
    
    $stmt->bind_param('isissis', $admin_id, $recipient_type, $recipient_id, $template, $message, $count, $status);
    $stmt->execute();
    $notification_id = $conn->insert_id;
    $stmt->close();

    // Count successful sends
    $successful = 0;
    $failed = 0;
    foreach ($results as $result) {
        if ($result['success']) {
            $successful++;
        } else {
            $failed++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "SMS sent to $successful recipient(s). Failed: $failed",
        'notification_id' => $notification_id,
        'details' => [
            'total_recipients' => count($results),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ]
    ]);

} catch (\Exception $e) {
    error_log('SMS Send Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error sending SMS: ' . $e->getMessage()]);
}
