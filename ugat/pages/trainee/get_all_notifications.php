<?php
// ============================================================
//  pages/trainee/get_all_notifications.php
//  
//  Get combined SMS and Email notifications for trainee
//  Method: GET
//  Requires: Logged-in user session
//  
//  Query parameters:
//  - type: sms|email|both (default: both)
//  - limit: number of records (default: 50)
//  - offset: pagination offset (default: 0)
// ============================================================

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$type = $_GET['type'] ?? 'both';
$limit = min((int)($_GET['limit'] ?? 50), 500);
$offset = (int)($_GET['offset'] ?? 0);

$all_notifications = [];
$total_count = 0;

try {
    // Get SMS notifications for user
    if ($type === 'sms' || $type === 'both') {
        $query = "SELECT 'sms' as channel, phone_number as recipient, message, notification_type as type, 
                         status, received_at as timestamp, is_read
                  FROM trainee_sms_log 
                  WHERE user_id = $user_id
                  ORDER BY received_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $all_notifications[] = $row;
            }
        }
        
        // Get total count for SMS
        $count_result = $conn->query("SELECT COUNT(*) as count FROM trainee_sms_log WHERE user_id = $user_id");
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            $total_count += $count_row['count'];
        }
    }

    // Get Email notifications for user
    if ($type === 'email' || $type === 'both') {
        $query = "SELECT 'email' as channel, recipient_email as recipient, message, notification_type as type, 
                         status, received_at as timestamp, is_read
                  FROM trainee_email_log 
                  WHERE user_id = $user_id
                  ORDER BY received_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $all_notifications[] = $row;
            }
        }
        
        // Get total count for Email
        $count_result = $conn->query("SELECT COUNT(*) as count FROM trainee_email_log WHERE user_id = $user_id");
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            $total_count += $count_row['count'];
        }
    }

    // Sort combined results by timestamp (most recent first)
    usort($all_notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    echo json_encode([
        'success' => true,
        'data' => array_slice($all_notifications, 0, $limit),
        'pagination' => [
            'total' => $total_count,
            'limit' => $limit,
            'offset' => $offset,
            'returned' => count(array_slice($all_notifications, 0, $limit))
        ]
    ]);

} catch (\Exception $e) {
    error_log('Notification retrieval error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error retrieving notifications']);
}
?>
