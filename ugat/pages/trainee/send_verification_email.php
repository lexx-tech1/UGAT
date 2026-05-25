<?php
/**
 * Send Email Verification Code
 * POST /pages/trainee/send_verification_email.php
 * 
 * Sends 6-digit verification code to user's email
 */

require_once '../../config/gmail_api_service.php';
require_once '../../config/notification_preferences.php';
require_once '../../config/db.php';

ini_set('session.cookie_path', '/');
session_name('ugat_trainee');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : null;

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Generate 6-digit code
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Get user info
    $query = "SELECT first_name FROM trainee_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $user = ['first_name' => 'Trainee'];
    }
    
    // Save verification code to database
    $query = "INSERT INTO email_verification_codes (user_id, email, code, expires_at, created_at) 
              VALUES (?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE code = VALUES(code), expires_at = VALUES(expires_at), created_at = NOW()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isss', $user_id, $email, $verification_code, $expires_at);
    if (!$stmt->execute()) {
        throw new Exception('Failed to save verification code: ' . $conn->error);
    }
    $stmt->close();
    
    // Get email template
    $templates = (include '../../config/gmail_api.php');
    $template = $templates['verification'];
    
    // Prepare email body
    $html_body = str_replace(
        ['{{name}}', '{{code}}'],
        [htmlspecialchars($user['first_name']), $verification_code],
        $template['body_html']
    );
    
    $text_body = str_replace(
        ['{{name}}', '{{code}}'],
        [$user['first_name'], $verification_code],
        $template['body_text']
    );
    
    // Send verification email
    $gmail_service = new GmailApiService();
    $send_result = $gmail_service->sendEmail($email, $template['subject'], $html_body, $text_body);
    
    if (!$send_result['success']) {
        throw new Exception('Failed to send email: ' . $send_result['message']);
    }
    
    // Log email
    $query = "INSERT INTO email_logs (recipient_email, subject, message, status, sent_at, metadata)
              VALUES (?, ?, ?, ?, NOW(), ?)";
    $metadata = json_encode([
        'type' => 'verification',
        'user_id' => $user_id,
        'email_id' => $send_result['email_id']
    ]);
    $status = 'sent';
    $subject = 'Email Verification Code';
    $message = "Verification code: $verification_code";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssss', $email, $subject, $message, $status, $metadata);
    $stmt->execute();
    $stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Verification email sent. Check your inbox and spam folder.',
        'email' => $email
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function sanitize_email($email) {
    return trim(strtolower($email));
}

?>
