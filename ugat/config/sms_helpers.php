<?php
// ============================================================
//  config/sms_helpers.php
//  Helper functions for sending SMS notifications programmatically
//  
//  Usage: Include this file and call sendSmsForEvent()
// ============================================================

require_once __DIR__ . '/sms_service.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_service.php';
require_once __DIR__ . '/notification_preferences.php';

/**
 * Send automatic SMS notification for various system events
 * 
 * @param string $event_type Type of event (e.g., 'order_placed', 'workshop_enrollment')
 * @param int $user_id User to notify
 * @param array $data Event data with replacements for template
 * @return array
 */
function sendSmsForEvent(string $event_type, int $user_id, array $data = []): array
{
    global $conn;
    
    try {
        // Get user phone number
$result = $conn->query(
            "SELECT tp.phone,
                    CONCAT(COALESCE(tp.first_name,''), ' ', COALESCE(tp.last_name,'')) as name
             FROM users u
             LEFT JOIN trainee_profiles tp ON u.id = tp.user_id
             WHERE u.id = $user_id LIMIT 1"
        );
        
        if ($result->num_rows === 0 || !($user = $result->fetch_assoc())) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        if (empty($user['phone'])) {
            return ['success' => false, 'message' => 'User phone number not configured'];
        }

        // Prepare template replacements
        $replacements = array_merge(['name' => $user['name'] ?? 'Valued User'], $data);
        $message = getSmsTemplate($event_type, $replacements);

        // Send SMS
        $sms = getSmsService($conn);
        $result = $sms->sendSms($user['phone'], $message, [
            'event_type' => $event_type,
            'user_id' => $user_id,
            'triggered_by' => 'system'
        ]);

        // Log in trainee_sms_log
        if ($result['success']) {
            $stmt = $conn->prepare(
                "INSERT INTO trainee_sms_log (user_id, phone_number, message, notification_type, status, received_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            
            if ($stmt) {
                $status = 'received';
                $stmt->bind_param('issss', $user_id, $user['phone'], $message, $event_type, $status);
                $stmt->execute();
                $stmt->close();
            }
        }

        return $result;

    } catch (\Exception $e) {
        error_log('SMS Event Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error sending SMS: ' . $e->getMessage()];
    }
}

/**
 * Send SMS to multiple users for an event
 * 
 * @param string $event_type
 * @param array $user_ids Array of user IDs
 * @param array $data Event data
 * @return array Results
 */
function sendSmsForEventToMultiple(string $event_type, array $user_ids, array $data = []): array
{
    $results = [];
    foreach ($user_ids as $user_id) {
        $results[$user_id] = sendSmsForEvent($event_type, $user_id, $data);
    }
    return $results;
}

/**
 * Send order placement notification
 * 
 * @param int $user_id
 * @param string $order_id
 * @param string $total
 * @param string $tracking_link
 * @return array
 */
function sendOrderPlacedNotification(int $user_id, string $order_id, string $total, string $tracking_link = ''): array
{
    return sendSmsForEvent('order_placed', $user_id, [
        'order_id' => $order_id,
        'total' => $total,
        'link' => $tracking_link ?: 'https://ugat.local/pages/trainee/TraineeShop.html'
    ]);
}

/**
 * Send order shipped notification
 * 
 * @param int $user_id
 * @param string $order_id
 * @param string $tracking_link
 * @return array
 */
function sendOrderShippedNotification(int $user_id, string $order_id, string $tracking_link = ''): array
{
    return sendSmsForEvent('order_shipped', $user_id, [
        'order_id' => $order_id,
        'tracking_link' => $tracking_link ?: 'https://ugat.local/track'
    ]);
}

/**
 * Send order delivered notification
 * 
 * @param int $user_id
 * @param string $order_id
 * @return array
 */
function sendOrderDeliveredNotification(int $user_id, string $order_id): array
{
    return sendSmsForEvent('order_delivered', $user_id, [
        'order_id' => $order_id
    ]);
}

/**
 * Send workshop enrollment notification
 * 
 * @param int $user_id
 * @param string $workshop_name
 * @param string $workshop_date
 * @param string $enrollment_link
 * @return array
 */
function sendWorkshopEnrollmentNotification(int $user_id, string $workshop_name, string $workshop_date, string $enrollment_link = ''): array
{
    return sendSmsForEvent('workshop_enrollment', $user_id, [
        'workshop_name' => $workshop_name,
        'date' => $workshop_date,
        'link' => $enrollment_link ?: 'https://ugat.local/pages/trainee/TraineeWorkshops.html'
    ]);
}

/**
 * Send workshop reminder notification
 * 
 * @param int $user_id
 * @param string $workshop_name
 * @param string $workshop_date
 * @param string $workshop_time
 * @return array
 */
function sendWorkshopReminderNotification(int $user_id, string $workshop_name, string $workshop_date, string $workshop_time = ''): array
{
    return sendSmsForEvent('workshop_reminder', $user_id, [
        'workshop_name' => $workshop_name,
        'date' => $workshop_date,
        'time' => $workshop_time ?: '09:00 AM'
    ]);
}

/**
 * Send certification issued notification
 * 
 * @param int $user_id
 * @param string $workshop_name
 * @param string $cert_link
 * @return array
 */
function sendCertificationIssuedNotification(int $user_id, string $workshop_name, string $cert_link = ''): array
{
    return sendSmsForEvent('certification_issued', $user_id, [
        'workshop_name' => $workshop_name,
        'link' => $cert_link ?: 'https://ugat.local/pages/trainee/TraineeProfile.html'
    ]);
}

/**
 * Send payment received notification
 * 
 * @param int $user_id
 * @param string $amount
 * @param string $order_id
 * @return array
 */
function sendPaymentReceivedNotification(int $user_id, string $amount, string $order_id): array
{
    return sendSmsForEvent('payment_received', $user_id, [
        'amount' => $amount,
        'order_id' => $order_id
    ]);
}

// ============================================================
//  DUAL NOTIFICATION FUNCTIONS (SMS + Email)
//  Uses user preferences to determine which channels to use
// ============================================================

/**
 * Send notification via SMS and/or Email based on user preferences
 * 
 * @param int $user_id
 * @param string $event_type
 * @param array $data Event data for template substitution
 * @return array Results including both channels
 */
function sendNotificationByPreference(int $user_id, string $event_type, array $data = []): array
{
    global $conn;
    
    try {
        $channels = getNotificationChannels($user_id, $event_type, $conn);
        
        $results = [
            'success' => false,
            'sms' => ['success' => false, 'message' => 'SMS not sent'],
            'email' => ['success' => false, 'message' => 'Email not sent'],
            'channels_used' => []
        ];

        // Get user info
        $user_result = $conn->query(
            "SELECT tp.phone, u.email as user_email, tp.first_name, tp.last_name
             FROM users u
             LEFT JOIN trainee_profiles tp ON u.id = tp.user_id
             WHERE u.id = $user_id LIMIT 1"
        );
        
        if ($user_result->num_rows === 0) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $user = $user_result->fetch_assoc();
        $user_name = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
        $user_name = trim($user_name) ?: 'Valued User';
        
        // Prepare replacements
        $replacements = array_merge(['name' => $user_name], $data);

        // Send SMS if enabled for this user
        if ($channels['use_sms'] && $user['phone']) {
            $message = getSmsTemplate($event_type, $replacements);
            $sms = getSmsService($conn);
            $sms_result = $sms->sendSms($user['phone'], $message, [
                'event_type' => $event_type,
                'user_id' => $user_id,
                'triggered_by' => 'system',
                'channel' => 'sms'
            ]);
            
            $results['sms'] = $sms_result;
            if ($sms_result['success']) {
                $results['channels_used'][] = 'sms';
                $results['success'] = true;
                
                // Log in trainee_sms_log
                $stmt = $conn->prepare(
                    "INSERT INTO trainee_sms_log (user_id, phone_number, message, notification_type, status, received_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())"
                );
                if ($stmt) {
                    $status = 'received';
                    $stmt->bind_param('issss', $user_id, $user['phone'], $message, $event_type, $status);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        // Send Email if enabled for this user
        if ($channels['use_email']) {
            $notification_prefs = getUserNotificationPreference($user_id, $conn);
            $recipient_email = $notification_prefs['email'] ?? $user['user_email'];
            
            if ($recipient_email) {
                $email_template = getEmailTemplate($event_type, $replacements, 'html');
                $email = getEmailService($conn);
                $email_result = $email->sendEmail(
                    $recipient_email,
                    $email_template['subject'],
                    $email_template['body'],
                    [
                        'event_type' => $event_type,
                        'user_id' => $user_id,
                        'triggered_by' => 'system',
                        'channel' => 'email'
                    ]
                );
                
                $results['email'] = $email_result;
                if ($email_result['success']) {
                    $results['channels_used'][] = 'email';
                    $results['success'] = true;
                    
                    // Log in trainee_email_log
                    $stmt = $conn->prepare(
                        "INSERT INTO trainee_email_log (user_id, recipient_email, subject, message, notification_type, status, received_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())"
                    );
                    if ($stmt) {
                        $status = 'received';
                        $stmt->bind_param('isssss', $user_id, $recipient_email, $email_template['subject'], $email_template['body'], $event_type, $status);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }

        return $results;

    } catch (\Exception $e) {
        error_log('Notification Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error sending notification: ' . $e->getMessage()];
    }
}

/**
 * Send order placement notification (SMS + Email)
 * 
 * @param int $user_id
 * @param string $order_id
 * @param string $total
 * @param string $tracking_link
 * @return array
 */
function sendOrderPlacedNotificationDual(int $user_id, string $order_id, string $total, string $tracking_link = ''): array
{
    return sendNotificationByPreference($user_id, 'order_placed', [
        'order_id' => $order_id,
        'total' => $total,
        'link' => $tracking_link ?: 'https://ugat.local/pages/trainee/TraineeShop.html'
    ]);
}

/**
 * Send order shipped notification (SMS + Email)
 * 
 * @param int $user_id
 * @param string $order_id
 * @param string $tracking_link
 * @return array
 */
function sendOrderShippedNotificationDual(int $user_id, string $order_id, string $tracking_link = ''): array
{
    return sendNotificationByPreference($user_id, 'order_shipped', [
        'order_id' => $order_id,
        'tracking_link' => $tracking_link ?: 'https://ugat.local/track'
    ]);
}

/**
 * Send order delivered notification (SMS + Email)
 * 
 * @param int $user_id
 * @param string $order_id
 * @return array
 */
function sendOrderDeliveredNotificationDual(int $user_id, string $order_id): array
{
    return sendNotificationByPreference($user_id, 'order_delivered', [
        'order_id' => $order_id
    ]);
}

/**
 * Send workshop enrollment notification (SMS + Email)
 * 
 * @param int $user_id
 * @param string $workshop_name
 * @param string $workshop_date
 * @param string $workshop_time
 * @param string $enrollment_link
 * @return array
 */
function sendWorkshopEnrollmentNotificationDual(int $user_id, string $workshop_name, string $workshop_date, string $workshop_time = '', string $enrollment_link = ''): array
{
    return sendNotificationByPreference($user_id, 'workshop_enrollment', [
        'workshop_name' => $workshop_name,
        'date' => $workshop_date,
        'time' => $workshop_time ?: '09:00 AM',
        'link' => $enrollment_link ?: 'https://ugat.local/pages/trainee/TraineeWorkshops.html'
    ]);
}

/**
 * Send workshop reminder notification (SMS + Email)
 * 
 * @param int $user_id
 * @param string $workshop_name
 * @param string $workshop_date
 * @param string $workshop_time
 * @return array
 */
function sendWorkshopReminderNotificationDual(int $user_id, string $workshop_name, string $workshop_date, string $workshop_time = ''): array
{
    return sendNotificationByPreference($user_id, 'workshop_reminder', [
        'workshop_name' => $workshop_name,
        'date' => $workshop_date,
        'time' => $workshop_time ?: '09:00 AM'
    ]);
}

/**
 * Send certification issued notification (SMS + Email)
 * 
 * @param int $user_id
 * @param string $workshop_name
 * @param string $cert_link
 * @return array
 */
function sendCertificationIssuedNotificationDual(int $user_id, string $workshop_name, string $cert_link = ''): array
{
    return sendNotificationByPreference($user_id, 'certification_issued', [
        'workshop_name' => $workshop_name,
        'link' => $cert_link ?: 'https://ugat.local/pages/trainee/TraineeProfile.html'
    ]);
}

/**
 * Send payment received notification (SMS + Email)
 * 
 * @param int $user_id
 * @param string $amount
 * @param string $order_id
 * @return array
 */
function sendPaymentReceivedNotificationDual(int $user_id, string $amount, string $order_id): array
{
    return sendNotificationByPreference($user_id, 'payment_received', [
        'amount' => $amount,
        'order_id' => $order_id
    ]);
}


