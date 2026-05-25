<?php
// ============================================================
//  pages/trainee/get_notification_preferences.php
//  
//  Get user's notification preferences
//  Method: GET
//  Requires: Logged-in user session
// ============================================================

session_name('ugat_trainee');
session_start();
require_once '../../config/db.php';
require_once '../../config/notification_preferences.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // Get user email from users, phone from trainee_profiles
    $user_result = $conn->query("SELECT u.email, tp.phone FROM users u LEFT JOIN trainee_profiles tp ON tp.user_id = u.id WHERE u.id = $user_id LIMIT 1");
    $user = $user_result->fetch_assoc() ?: [];
    
    // Get preferences
    $prefs = getUserNotificationPreference($user_id, $conn);
    
    if ($prefs === null) {
        // No preferences set yet - return defaults
        $prefs = [
            'email' => $user['email'] ?? '',
            'phone_enabled' => true,
            'email_enabled' => false,
            'email_verified' => false
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'phone' => $user['phone'] ?? '',
            'email' => $prefs['email'],
            'phone_enabled' => (bool)$prefs['phone_enabled'],
            'email_enabled' => (bool)$prefs['email_enabled'],
            'email_verified' => (bool)$prefs['email_verified']
        ]
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error retrieving preferences']);
}
?>
