<?php
/**
 * Verify Email Verification Code
 * POST /pages/trainee/verify_email_code.php
 * 
 * Verifies the 6-digit code and marks email as verified
 */

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
$code = isset($_POST['code']) ? trim($_POST['code']) : null;
$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : null;

if (!$code || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Code and email are required']);
    exit;
}

try {
    // Check if code is valid and not expired
    $query = "SELECT * FROM email_verification_codes 
              WHERE user_id = ? AND email = ? AND code = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $user_id, $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $verification = $result->fetch_assoc();
    $stmt->close();
    
    if (!$verification) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
        exit;
    }
    
    // Mark code as verified
    $query = "UPDATE email_verification_codes SET verified_at = NOW() 
              WHERE user_id = ? AND email = ? AND code = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $user_id, $email, $code);
    $stmt->execute();
    $stmt->close();
    
    // Update notification preferences with verified email
    setUserNotificationPreference($user_id, $email, true, true, $conn);
    
    // Log verification
    $query = "INSERT INTO email_logs (recipient_email, subject, message, status, sent_at, metadata)
              VALUES (?, ?, ?, ?, NOW(), ?)";
    $metadata = json_encode([
        'type' => 'email_verified',
        'user_id' => $user_id
    ]);
    $subject = 'Email Verified';
    $message = 'Email address has been verified';
    $status = 'verified';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssss', $email, $subject, $message, $status, $metadata);
    $stmt->execute();
    $stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully! You will now receive email notifications.',
        'email' => $email
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function sanitize_email($email) {
    return trim(strtolower($email));
}

?>
