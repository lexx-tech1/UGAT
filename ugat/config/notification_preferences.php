<?php
// ============================================================
//  config/notification_preferences.php
//  
//  Handles user notification preferences (SMS, Email, or Both)
// ============================================================

require_once 'db.php';

/**
 * Get user's notification preferences
 * 
 * @param int $user_id
 * @param mysqli $conn Database connection
 * @return array|null ['email' => string, 'phone_enabled' => bool, 'email_enabled' => bool, 'email_verified' => bool]
 */
function getUserNotificationPreference(int $user_id, $conn)
{
    $stmt = $conn->prepare('SELECT email, phone_enabled, email_enabled, email_verified FROM notification_preferences WHERE user_id = ? LIMIT 1');
    
    if (!$stmt) {
        error_log('Preference DB Error: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $prefs = $result->fetch_assoc();
    $stmt->close();

    return [
        'email' => $prefs['email'],
        'phone_enabled' => (bool)$prefs['phone_enabled'],
        'email_enabled' => (bool)$prefs['email_enabled'],
        'email_verified' => (bool)$prefs['email_verified']
    ];
}

/**
 * Set user's notification preferences
 * 
 * @param int $user_id
 * @param string $email
 * @param bool $phone_enabled
 * @param bool $email_enabled
 * @param mysqli $conn Database connection
 * @return array ['success' => bool, 'message' => string]
 */
function setUserNotificationPreference(int $user_id, string $email = '', bool $phone_enabled = true, bool $email_enabled = false, $conn = null): array
{
    if ($conn === null) {
        global $conn;
    }

    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection not available.'];
    }

    // Check if preferences exist
    $check = $conn->prepare('SELECT id FROM notification_preferences WHERE user_id = ? LIMIT 1');
    if (!$check) {
        return ['success' => false, 'message' => 'Database error.'];
    }

    $check->bind_param('i', $user_id);
    $check->execute();
    $result = $check->get_result();
    $exists = $result->num_rows > 0;
    $check->close();

    if ($exists) {
        // Update existing preferences
        $stmt = $conn->prepare(
            'UPDATE notification_preferences SET email = ?, phone_enabled = ?, email_enabled = ?, updated_at = NOW() WHERE user_id = ?'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }

        $stmt->bind_param('siii', $email, $phone_enabled, $email_enabled, $user_id);
    } else {
        // Insert new preferences
        $stmt = $conn->prepare(
            'INSERT INTO notification_preferences (user_id, email, phone_enabled, email_enabled, created_at, updated_at) 
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error.'];
        }

        $stmt->bind_param('isii', $user_id, $email, $phone_enabled, $email_enabled);
    }

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Preferences updated successfully.'];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to update preferences.'];
    }
}

/**
 * Get which channels to use for a user based on preferences
 * Falls back to SMS if no preferences set
 * 
 * @param int $user_id
 * @param string $event_type Optional event type for overrides
 * @param mysqli $conn Database connection
 * @return array ['use_sms' => bool, 'use_email' => bool]
 */
function getNotificationChannels(int $user_id, string $event_type = '', $conn = null): array
{
    if ($conn === null) {
        global $conn;
    }

    $prefs = getUserNotificationPreference($user_id, $conn);

    // If no preferences set, use default
    if ($prefs === null) {
        $default = NOTIFICATION_PREFERENCE_DEFAULT;
        return [
            'use_sms' => ($default === 'sms' || $default === 'both'),
            'use_email' => ($default === 'email' || $default === 'both')
        ];
    }

    return [
        'use_sms' => $prefs['phone_enabled'],
        'use_email' => $prefs['email_enabled'] && $prefs['email_verified']
    ];
}

/**
 * Send verification email to user
 * 
 * @param int $user_id
 * @param string $email
 * @param mysqli $conn Database connection
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmailVerification(int $user_id, string $email, $conn = null): array
{
    if ($conn === null) {
        global $conn;
    }

    // Generate verification code
    $verification_code = bin2hex(random_bytes(16));

    // Store verification code
    $stmt = $conn->prepare(
        'INSERT INTO email_verification_tokens (user_id, email, token, expires_at) 
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
         ON DUPLICATE KEY UPDATE token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)'
    );

    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }

    $stmt->bind_param('isss', $user_id, $email, $verification_code, $verification_code);
    $stmt->execute();
    $stmt->close();

    // Prepare verification link
    $verification_link = 'http://localhost/ugat/pages/trainee/verify_email.php?token=' . $verification_code;

    // Get email template
    require_once 'email.php';
    require_once 'email_service.php';

    $template = [
        'subject' => 'Verify Your Email - UGAT',
        'html' => '<h2>Verify Your Email Address</h2>
<p>Hi,</p>
<p>Thank you for providing your email address. Please verify it by clicking the link below:</p>
<p><a href="' . $verification_link . '" style="background-color: #4B8423; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Verify Email</a></p>
<p>Or paste this link: ' . $verification_link . '</p>
<p>This link expires in 24 hours.</p>',
        'text' => 'Verify Your Email Address\n\nPlease verify your email by visiting: ' . $verification_link . '\n\nThis link expires in 24 hours.'
    ];

    // Send verification email
    $email_service = getEmailService($conn);
    $result = $email_service->sendEmail($email, $template['subject'], $template['html']);

    return $result;
}

/**
 * Verify email with token
 * 
 * @param string $token
 * @param mysqli $conn Database connection
 * @return array ['success' => bool, 'message' => string]
 */
function verifyEmailWithToken(string $token, $conn = null): array
{
    if ($conn === null) {
        global $conn;
    }

    // Find token
    $stmt = $conn->prepare(
        'SELECT user_id, email FROM email_verification_tokens 
         WHERE token = ? AND expires_at > NOW() LIMIT 1'
    );

    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Invalid or expired verification token.'];
    }

    $data = $result->fetch_assoc();
    $stmt->close();

    // Update notification preferences to mark email as verified
    $update = $conn->prepare(
        'UPDATE notification_preferences SET email = ?, email_verified = 1, updated_at = NOW() WHERE user_id = ?'
    );

    if (!$update) {
        return ['success' => false, 'message' => 'Database error.'];
    }

    $update->bind_param('si', $data['email'], $data['user_id']);
    $update->execute();
    $update->close();

    // Delete used token
    $delete = $conn->prepare('DELETE FROM email_verification_tokens WHERE token = ?');
    $delete->bind_param('s', $token);
    $delete->execute();
    $delete->close();

    return ['success' => true, 'message' => 'Email verified successfully!'];
}

/**
 * Verify email with token
 * 
 * @param int $user_id
 * @param string $token
 * @param mysqli $conn Database connection
 * @return array ['success' => bool, 'message' => string]
 */
function verifyEmailToken(int $user_id, string $token, $conn = null): array
{
    if ($conn === null) {
        global $conn;
    }

    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection not available.'];
    }

    $stmt = $conn->prepare(
        'UPDATE notification_preferences SET email_verified = 1, email_verification_token = NULL, updated_at = NOW() 
         WHERE user_id = ? AND email_verification_token = ? LIMIT 1'
    );

    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }

    $stmt->bind_param('is', $user_id, $token);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        return ['success' => true, 'message' => 'Email verified successfully!'];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Invalid verification token or email already verified.'];
    }
}

/**
 * Get user's email address for notifications
 * 
 * @param int $user_id
 * @param mysqli $conn Database connection
 * @return string|null
 */
function getUserEmail(int $user_id, $conn = null): ?string
{
    if ($conn === null) {
        global $conn;
    }

    $stmt = $conn->prepare('SELECT email FROM notification_preferences WHERE user_id = ? LIMIT 1');
    
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['email'];
}

/**
 * Get user's phone number from user profile
 * 
 * @param int $user_id
 * @param mysqli $conn Database connection
 * @return string|null
 */
function getUserPhone(int $user_id, $conn = null): ?string
{
    if ($conn === null) {
        global $conn;
    }

    $stmt = $conn->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['phone'];
}
