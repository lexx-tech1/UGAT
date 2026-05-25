<?php
/**
 * ============================================================
 *  QUICK REFERENCE: Using the Notification System
 * ============================================================
 * 
 * This file shows the most common ways to send notifications
 * in your UGAT code. Copy and paste as needed.
 */

// ============================================================
// 1. SEND SMS ONLY (Backward Compatible)
// ============================================================

require_once 'config/sms_helpers.php';

// Order notifications
sendOrderPlacedNotification($user_id, 'ORD-2026-001', '₱5,000');
sendOrderShippedNotification($user_id, 'ORD-2026-001', 'https://track.com/123');
sendOrderDeliveredNotification($user_id, 'ORD-2026-001');
sendPaymentReceivedNotification($user_id, '₱5,000', 'ORD-2026-001');

// Workshop notifications
sendWorkshopEnrollmentNotification($user_id, 'Hydroponics 101', '2026-06-15', '2:00 PM');
sendWorkshopReminderNotification($user_id, 'Hydroponics 101', '2026-06-15', '2:00 PM');

// Certificate notifications
sendCertificationIssuedNotification($user_id, 'Hydroponics 101', 'https://cert-link.com');


// ============================================================
// 2. SEND SMS + EMAIL (Respects User Preferences)
// ============================================================

require_once 'config/sms_helpers.php';

// Order notifications
$result = sendOrderPlacedNotificationDual($user_id, 'ORD-2026-001', '₱5,000');
sendOrderShippedNotificationDual($user_id, 'ORD-2026-001');
sendOrderDeliveredNotificationDual($user_id, 'ORD-2026-001');
sendPaymentReceivedNotificationDual($user_id, '₱5,000', 'ORD-2026-001');

// Workshop notifications
sendWorkshopEnrollmentNotificationDual($user_id, 'Hydroponics 101', '2026-06-15', '2:00 PM');
sendWorkshopReminderNotificationDual($user_id, 'Hydroponics 101', '2026-06-15', '2:00 PM');

// Certificate notifications
sendCertificationIssuedNotificationDual($user_id, 'Hydroponics 101');

// Check results
if ($result['success']) {
    echo "Notification sent via: " . implode(', ', $result['channels_used']);
    // channels_used: ['sms'], ['email'], or ['sms', 'email']
} else {
    echo "Failed to send notification: " . $result['message'];
}


// ============================================================
// 3. SEND CUSTOM NOTIFICATION (Generic)
// ============================================================

require_once 'config/sms_helpers.php';

// Via SMS
sendSmsForEvent('order_placed', $user_id, [
    'order_id' => 'ORD-123',
    'total' => '₱2,500',
    'link' => 'https://track.com'
]);

// Via SMS + Email (respects preferences)
sendNotificationByPreference($user_id, 'order_placed', [
    'order_id' => 'ORD-123',
    'total' => '₱2,500',
    'link' => 'https://track.com'
]);


// ============================================================
// 4. ADMIN: SEND NOTIFICATION TO USER/GROUP/ALL
// ============================================================

// Via cURL POST request
$ch = curl_init('http://localhost/ugat/pages/admin/send_notification.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'recipient_type' => 'single',  // 'single', 'group', or 'all'
    'recipient_id' => 42,           // user_id (for single) or workshop_id (for group)
    'template' => 'order_placed',
    'replacements' => [
        'order_id' => 'ORD-123',
        'total' => '₱5,000'
    ],
    'force_method' => 'both'        // 'sms', 'email', or 'both'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);

if ($data['success']) {
    echo "Sent to {$data['summary']['success']} users";
} else {
    echo "Error: " . $data['message'];
}


// ============================================================
// 5. TRAINEE: GET NOTIFICATION PREFERENCES
// ============================================================

// Client-side JavaScript fetch
fetch('/ugat/pages/trainee/get_notification_preferences.php')
    .then(r => r.json())
    .then(data => {
        console.log('Phone enabled:', data.data.phone_enabled);
        console.log('Email enabled:', data.data.email_enabled);
        console.log('Email verified:', data.data.email_verified);
    });


// ============================================================
// 6. TRAINEE: UPDATE NOTIFICATION PREFERENCES
// ============================================================

// Client-side JavaScript fetch
fetch('/ugat/pages/trainee/update_notification_preferences.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        phone_enabled: true,
        email_enabled: true,
        email: 'user@example.com'
    })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        alert('Preferences updated!');
    }
});


// ============================================================
// 7. TRAINEE: GET ALL NOTIFICATIONS
// ============================================================

// Get all notifications (SMS + Email combined)
fetch('/ugat/pages/trainee/get_all_notifications.php?type=both&limit=50&offset=0')
    .then(r => r.json())
    .then(data => {
        data.data.forEach(notif => {
            console.log(`${notif.channel.toUpperCase()}: ${notif.message}`);
        });
    });

// Get SMS only
fetch('/ugat/pages/trainee/get_all_notifications.php?type=sms&limit=50')

// Get Email only
fetch('/ugat/pages/trainee/get_all_notifications.php?type=email&limit=50')


// ============================================================
// 8. ADMIN: GET NOTIFICATION HISTORY
// ============================================================

// Get all sent notifications (SMS + Email)
fetch('/ugat/pages/admin/get_notifications.php?type=both&limit=50')
    .then(r => r.json())
    .then(data => {
        console.log(`Total notifications: ${data.pagination.total}`);
        data.data.forEach(notif => {
            console.log(`${notif.type} sent to ${notif.recipient}`);
        });
    });

// With date filtering
fetch('/ugat/pages/admin/get_notifications.php?type=both&start_date=2026-05-01&end_date=2026-05-31')


// ============================================================
// COMMON TEMPLATES SUPPORTED
// ============================================================

/**
 * Available notification templates:
 * - order_placed
 * - order_shipped
 * - order_delivered
 * - workshop_enrollment
 * - workshop_reminder
 * - certification_issued
 * - payment_received
 * - admin_alert
 * 
 * Each template supports placeholders:
 * {name}, {order_id}, {total}, {workshop_name}, {date}, {time}, {link}, etc.
 */


// ============================================================
// DEBUG MODE (Testing)
// ============================================================

/**
 * To test without sending real SMS/Email:
 * 
 * In config/sms.php:
 *   define('SMS_DEBUG_MODE', true);
 * 
 * In config/email.php:
 *   define('EMAIL_DEBUG_MODE', true);
 * 
 * Notifications will be logged to error_log instead.
 * Check: tail -f /var/log/php-errors.log
 */


// ============================================================
// ERROR HANDLING EXAMPLE
// ============================================================

require_once 'config/sms_helpers.php';

$result = sendOrderPlacedNotificationDual($user_id, 'ORD-123', '₱5000');

if ($result['success']) {
    // All channels sent successfully
    http_response_code(200);
    echo json_encode(['success' => true, 'sent_via' => $result['channels_used']]);
} else {
    // All channels failed
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $result['message']]);
}

// Check individual channel results
if (!$result['sms']['success']) {
    error_log("SMS failed: " . $result['sms']['message']);
}
if (!$result['email']['success']) {
    error_log("Email failed: " . $result['email']['message']);
}


// ============================================================
// SETUP CHECKLIST
// ============================================================

/**
 * 1. [CHECK] Run database setup:
 *    http://localhost/ugat/config/create_email_tables.php
 * 
 * 2. [CHECK] Configure Semaphore API in config/sms.php:
 *    define('SEMAPHORE_API_KEY', 'your_key_from_semaphore.co');
 * 
 * 3. [CHECK] Configure Gmail in config/email.php:
 *    define('GMAIL_ADDRESS', 'your@gmail.com');
 *    define('GMAIL_APP_PASSWORD', '16_char_app_password');
 * 
 * 4. [CHECK] Test in debug mode:
 *    define('SMS_DEBUG_MODE', true);
 *    define('EMAIL_DEBUG_MODE', true);
 * 
 * 5. [CHECK] Disable debug when ready:
 *    define('SMS_DEBUG_MODE', false);
 *    define('EMAIL_DEBUG_MODE', false);
 * 
 * 6. [CHECK] Start using notifications in your code!
 */
?>
