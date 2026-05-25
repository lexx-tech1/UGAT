<?php
// ============================================================
//  trainee/get_sms_notifications.php
//  Get SMS notification history for logged-in trainee
//  
//  GET /trainee/get_sms_notifications.php?limit=50&offset=0
// ============================================================

session_name('ugat_trainee');
session_start();
require_once '../../config/db.php';
require_once '../../config/auth_guard.php';

requireRole('trainee');

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

// Limit to prevent abuse
if ($limit > 500) $limit = 500;
if ($limit < 1) $limit = 50;

try {
    // Get total count
    $count_result = $conn->query(
        "SELECT COUNT(*) as total FROM trainee_sms_log WHERE user_id = $user_id"
    );
    $total = (int)$count_result->fetch_assoc()['total'];

    // Get SMS messages
    $result = $conn->query(
        "SELECT id, phone_number, message, notification_type, status, received_at 
         FROM trainee_sms_log 
         WHERE user_id = $user_id 
         ORDER BY received_at DESC 
         LIMIT $limit OFFSET $offset"
    );

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Mask phone number for privacy
        $masked_phone = substr($row['phone_number'], 0, -4) . '****';
        $row['phone_number'] = $masked_phone;
        $messages[] = $row;
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ]);

} catch (\Exception $e) {
    error_log('Trainee SMS Log Fetch Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching messages.']);
}
