<?php
// ============================================================
//  pages/admin/send_notification.php
//  
//  Send notifications to users via SMS and/or Email
//  Method: POST
//  Requires: Admin session
//  
//  Request body:
//  {
//    "recipient_type": "single|group|all",
//    "recipient_id": 1,  (user_id for 'single', workshop_id for 'group')
//    "template": "order_placed",
//    "replacements": {...},
//    "force_method": "sms|email|both"  (override user preferences)
//  }
// ============================================================

ini_set('session.cookie_path', '/');
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';
require_once '../../config/auth_guard.php';
require_once '../../config/notification_preferences.php';
require_once '../../config/sms_helpers.php';

header('Content-Type: application/json');

// Check admin access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Optional: Check if admin
// requireRole('admin');

$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['recipient_type']) || !isset($data['template'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$recipient_type = $data['recipient_type'];
$template = $data['template'];
$replacements = $data['replacements'] ?? [];
$force_method = $data['force_method'] ?? 'both';  // sms, email, both
$admin_id = $_SESSION['user_id'];

$results = [];
$user_ids = [];

try {
    // Determine which users to notify
    if ($recipient_type === 'single') {
        $recipient_id = (int)($data['recipient_id'] ?? 0);
        if ($recipient_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid recipient_id for single recipient']);
            exit;
        }
        $user_ids = [$recipient_id];
    } elseif ($recipient_type === 'group') {
        // Get all users enrolled in a workshop
        $workshop_id = (int)($data['recipient_id'] ?? 0);
        if ($workshop_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid workshop_id for group recipient']);
            exit;
        }
        
        $result = $conn->query(
            "SELECT DISTINCT user_id FROM workshop_enrollment WHERE workshop_id = $workshop_id"
        );
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $user_ids[] = $row['user_id'];
            }
        }
    } elseif ($recipient_type === 'all') {
        // Get all active trainees
        $result = $conn->query(
            "SELECT id FROM users WHERE role = 'trainee' AND is_active = 1"
        );
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $user_ids[] = $row['id'];
            }
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid recipient_type']);
        exit;
    }

    // Send notifications to each user
    $success_count = 0;
    $failed_count = 0;

    foreach ($user_ids as $user_id) {
        $notification_result = sendNotificationByPreference($user_id, $template, $replacements);
        
        $results[] = [
            'user_id' => $user_id,
            'result' => $notification_result
        ];

        if ($notification_result['success']) {
            $success_count++;
        } else {
            $failed_count++;
        }
    }

    // Log admin notification action
    if ($conn) {
        $stmt = $conn->prepare(
            "INSERT INTO sms_notifications (admin_id, recipient_type, recipient_id, template, message, count, sent_at, status) 
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)"
        );
        
        if ($stmt) {
            $recipient_id = ($recipient_type === 'single' || $recipient_type === 'group') ? ($data['recipient_id'] ?? 0) : 0;
            $status = 'sent';
            $message_summary = 'Notification sent via ' . implode(' + ', 
                array_filter(['sms' => 'SMS', 'email' => 'Email'], 
                    fn($k) => in_array($k, ['sms', 'email'])));
            
            $stmt->bind_param('isssii', $admin_id, $recipient_type, $recipient_id, $template, $message_summary, $success_count, $status);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo json_encode([
        'success' => $failed_count === 0,
        'message' => "Sent to $success_count users" . ($failed_count > 0 ? ", $failed_count failed" : ''),
        'summary' => [
            'total' => count($user_ids),
            'success' => $success_count,
            'failed' => $failed_count
        ],
        'results' => $results
    ]);

} catch (\Exception $e) {
    error_log('Notification send error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error sending notifications: ' . $e->getMessage()]);
}
?>
