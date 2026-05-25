<?php
// ============================================================
//  admin/get_sms_notifications.php
//  Get SMS notification history for admin
//  
//  GET /admin/get_sms_notifications.php?limit=50&offset=0
// ============================================================

session_name('ugat_admin');
session_start();
require_once '../../config/db.php';
require_once '../../config/auth_guard.php';

requireRole('admin');

header('Content-Type: application/json');

$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);
$admin_id = (int)$_SESSION['user_id'];

// Limit to prevent abuse
if ($limit > 500) $limit = 500;
if ($limit < 1) $limit = 50;

try {
    // Get total count
    $count_result = $conn->query(
        "SELECT COUNT(*) as total FROM sms_notifications WHERE admin_id = $admin_id"
    );
    $total = (int)$count_result->fetch_assoc()['total'];

    // Get notifications
    $result = $conn->query(
        "SELECT id, recipient_type, recipient_id, template, message, count, status, sent_at 
         FROM sms_notifications 
         WHERE admin_id = $admin_id 
         ORDER BY sent_at DESC 
         LIMIT $limit OFFSET $offset"
    );

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ]);

} catch (\Exception $e) {
    error_log('SMS Notification Fetch Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching notifications.']);
}
