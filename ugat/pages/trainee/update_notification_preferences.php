<?php
// ============================================================
//  pages/trainee/update_notification_preferences.php
//  
//  Update user's notification preferences (SMS, Email, or Both)
//  Method: POST
//  Requires: Logged-in user session
// ============================================================

session_name('ugat_trainee');
session_start();
require_once '../../config/db.php';
require_once '../../config/auth_guard.php';
require_once '../../config/notification_preferences.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['phone_enabled']) || !isset($data['email_enabled'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$phone_enabled = (bool)$data['phone_enabled'];
$email_enabled = (bool)$data['email_enabled'];
$email = isset($data['email']) ? trim($data['email']) : '';

// Update preferences
$result = setUserNotificationPreference(
    $user_id,
    $email,
    $phone_enabled,
    $email_enabled,
    $conn
);

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result);
?>
