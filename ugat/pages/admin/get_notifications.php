<?php
// ============================================================
//  pages/admin/get_notifications.php
//  
//  Get combined SMS and Email notification history
//  Method: GET
//  Requires: Admin session
//  
//  Query parameters:
//  - type: sms|email|both (default: both)
//  - limit: number of records (default: 50)
//  - offset: pagination offset (default: 0)
//  - start_date: filter by date YYYY-MM-DD
//  - end_date: filter by date YYYY-MM-DD
// ============================================================

ini_set('session.cookie_path', '/');
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';
require_once '../../config/auth_guard.php';

header('Content-Type: application/json');

// Check admin access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Optional: Check if admin
// requireRole('admin');

$type = $_GET['type'] ?? 'both';
$limit = min((int)($_GET['limit'] ?? 50), 500);
$offset = (int)($_GET['offset'] ?? 0);
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$all_notifications = [];
$total_count = 0;

try {
    // Build date filter
    $date_filter = '';
    if ($start_date && $end_date) {
        $start_date = $conn->real_escape_string($start_date);
        $end_date = $conn->real_escape_string($end_date);
        $date_filter = " WHERE sent_at >= '$start_date 00:00:00' AND sent_at <= '$end_date 23:59:59'";
    }

    // Get SMS notifications
    if ($type === 'sms' || $type === 'both') {
        $query = "SELECT 'sms' as type, phone_number as recipient, message, sms_id as message_id, 
                         status, sent_at, metadata 
                  FROM sms_logs 
                  $date_filter
                  ORDER BY sent_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['metadata'] = json_decode($row['metadata'], true);
                $all_notifications[] = $row;
            }
        }
        
        // Get total count for SMS
        $count_result = $conn->query("SELECT COUNT(*) as count FROM sms_logs $date_filter");
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            $total_count += $count_row['count'];
        }
    }

    // Get Email notifications
    if ($type === 'email' || $type === 'both') {
        $query = "SELECT 'email' as type, recipient_email as recipient, message, id as message_id, 
                         status, sent_at, metadata 
                  FROM email_logs 
                  $date_filter
                  ORDER BY sent_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['metadata'] = json_decode($row['metadata'], true);
                $all_notifications[] = $row;
            }
        }
        
        // Get total count for Email
        $count_result = $conn->query("SELECT COUNT(*) as count FROM email_logs $date_filter");
        if ($count_result) {
            $count_row = $count_result->fetch_assoc();
            $total_count += $count_row['count'];
        }
    }

    // Sort combined results by sent_at (most recent first)
    usort($all_notifications, function($a, $b) {
        return strtotime($b['sent_at']) - strtotime($a['sent_at']);
    });

    echo json_encode([
        'success' => true,
        'data' => $all_notifications,
        'pagination' => [
            'total' => $total_count,
            'limit' => $limit,
            'offset' => $offset,
            'returned' => count($all_notifications)
        ]
    ]);

} catch (\Exception $e) {
    error_log('Notification history error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error retrieving notifications']);
}
?>
