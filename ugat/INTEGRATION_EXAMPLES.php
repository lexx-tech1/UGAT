<?php
/**
 * ============================================================
 * COMPLETE SMS + EMAIL INTEGRATION EXAMPLES
 * ============================================================
 * 
 * This file contains ready-to-use code examples for:
 * - SMS via UniSMS (FREE, unlimited)
 * - Email via Gmail SMTP (FREE, unlimited)
 * - User Preferences (SMS, Email, or Both)
 * - Notification Logging
 */

// ============================================================
// EXAMPLE 1: Order Placed - Send SMS to Trainee
// ============================================================
/*
File: pages/trainee/place_order.php

require_once '../../config/sms_helpers.php';

// ... after order is successfully created ...

$order_id = $conn->insert_id;
$order_total = $_POST['total'] ?? 0;
$user_id = $_SESSION['ugat_trainee']['id'];

// Send SMS notification (respects user preference)
sendOrderPlacedNotificationDual(
    $user_id,
    $order_id,
    '₱' . number_format($order_total, 2)
);

echo json_encode(['success' => true, 'order_id' => $order_id]);
*/

// ============================================================
// EXAMPLE 2: Admin Approves Workshop Enrollment - Send Notification
// ============================================================
/*
File: pages/admin/approve_enrollment.php

require_once '../../config/sms_helpers.php';

// ... after updating enrollment status to 'enrolled' ...

// Get enrollment details
$stmt = $conn->prepare("
    SELECT e.user_id, w.workshop_name, w.workshop_date
    FROM enrollments e
    JOIN workshops w ON e.workshop_id = w.id
    WHERE e.id = ?
");
$stmt->bind_param('i', $enrollment_id);
$stmt->execute();
$result = $stmt->get_result();
$enrollment = $result->fetch_assoc();

// Send dual notification (SMS + Email based on preference)
sendWorkshopEnrollmentNotificationDual(
    $enrollment['user_id'],
    $enrollment['workshop_name'],
    $enrollment['workshop_date']
);

echo json_encode(['success' => true, 'message' => 'Enrollment approved and trainee notified']);
*/

// ============================================================
// EXAMPLE 3: Send SMS Only (Backward Compatible)
// ============================================================
/*
// If you ONLY want SMS (ignores email preference)
sendOrderPlacedNotification($user_id, $order_id, $total);

// Phone number is fetched from:
// - users.phone or trainee_profiles.phone (for trainee)
// - notification_preferences.phone_number (if set)
*/

// ============================================================
// EXAMPLE 4: Admin Force-Send Notification (Override Preference)
// ============================================================
/*
File: pages/admin/send_notification.php

$force_method = $_POST['force_method'] ?? 'both';  // sms, email, both, or null

sendNotificationToUser(
    $user_id = 42,
    $template = 'order_placed',
    $replacements = [
        'order_id' => 'ORD-12345',
        'total' => '₱5,000'
    ],
    $force_method = $force_method  // null = respects user preference
);

// If $force_method = 'email', always send email even if user prefers SMS
// If $force_method = null, respects user notification_preferences.email_enabled
*/

// ============================================================
// EXAMPLE 5: Get User Notification Preferences
// ============================================================
/*
require_once '../../config/notification_preferences.php';

$user_id = 42;
$preference = getUserNotificationPreference($user_id);

// Returns:
// {
//   "phone_enabled": true,
//   "email_enabled": true,
//   "email": "user@example.com",
//   "email_verified": true
// }

// Check what channels to use
$channels = getNotificationChannels($user_id);
// Returns array: ['sms', 'email'] or ['sms'] or ['email']
*/

// ============================================================
// EXAMPLE 6: Send Verification Email & Get Code
// ============================================================
/*
File: pages/trainee/enable_email_notifications.js

// Step 1: User enters email
const email = document.getElementById('email_input').value;

// Step 2: Send verification email
fetch('send_verification_email.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'email=' + encodeURIComponent(email)
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        alert('Verification code sent to ' + email);
        // Show code input field
    }
});

// Step 3: User gets code from email, enters it
const code = document.getElementById('code_input').value;

// Step 4: Verify code
fetch('verify_email_code.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'email=' + encodeURIComponent(email) + '&code=' + code
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        alert('Email verified! Notifications will now be sent to ' + email);
        // Update UI, save preferences
    }
});
*/

// ============================================================
// EXAMPLE 7: Update User Preferences
// ============================================================
/*
File: pages/trainee/update_notification_preferences.php (POST)

$user_id = $_SESSION['ugat_trainee']['id'];
$phone_enabled = $_POST['phone_enabled'] ?? false;
$email_enabled = $_POST['email_enabled'] ?? false;

setUserNotificationPreference(
    $user_id,
    $phone_enabled,
    $email_enabled
);

echo json_encode(['success' => true]);
*/

// ============================================================
// EXAMPLE 8: Admin Send to Group
// ============================================================
/*
// Send to all trainees in a workshop
sendNotificationToGroup(
    $group_id = $workshop_id,
    $template = 'workshop_reminder',
    $replacements = [
        'workshop_name' => 'Basic Farming 101',
        'date' => 'May 20, 2026 at 2:00 PM'
    ]
);

// Each trainee receives based on their preference:
// - If SMS enabled: Gets SMS
// - If Email enabled: Gets Email
// - If both enabled: Gets both
*/

// ============================================================
// EXAMPLE 9: Admin Send to All Trainees
// ============================================================
/*
// System-wide announcement
sendNotificationToAll(
    $template = 'admin_alert',
    $replacements = [
        'title' => 'System Maintenance',
        'message' => 'UGAT will be down for maintenance on May 25'
    ]
);

// All trainees notified via their preferred channel
*/

// ============================================================
// EXAMPLE 10: Handle Notification Failures
// ============================================================
/*
$result = sendOrderPlacedNotificationDual($user_id, $order_id, $total);

if (!$result['success']) {
    // Log error but don't fail the main operation
    error_log('Notification failed for order ' . $order_id . ': ' . $result['message']);
    
    // You could:
    // 1. Retry later
    // 2. Send to admin for manual follow-up
    // 3. Store in queue for retry
    // 4. Just log and continue (order still placed)
}

// Order/enrollment/etc always succeeds even if notification fails
// This prevents business logic failures due to SMS/Email issues
*/

// ============================================================
// EXAMPLE 11: Batch Send (Efficient)
// ============================================================
/*
// Instead of sending one by one in a loop:
foreach ($trainees as $trainee) {
    sendOrderPlacedNotificationDual($trainee['id'], $order_id, $total);
    // This sends to ALL trainees, respecting each one's preference
}

// Better: Use group send
sendNotificationToGroup(
    $group_id = $some_group,
    $template = 'order_placed',
    $replacements = ['order_id' => $order_id, 'total' => $total]
);
// Much faster, logs to database efficiently
*/

// ============================================================
// EXAMPLE 12: Use in Scheduled Tasks
// ============================================================
/*
File: cron_workshop_reminders.php (run daily)

require_once 'config/sms_helpers.php';

// Get all workshops that start tomorrow
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $conn->prepare("
    SELECT id, workshop_name, workshop_date
    FROM workshops
    WHERE DATE(workshop_date) = ?
");
$stmt->bind_param('s', $tomorrow);
$stmt->execute();
$workshops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all enrolled trainees
foreach ($workshops as $workshop) {
    $stmt2 = $conn->prepare("
        SELECT e.user_id
        FROM enrollments e
        WHERE e.workshop_id = ? AND e.status = 'enrolled'
    ");
    $stmt2->bind_param('i', $workshop['id']);
    $stmt2->execute();
    $enrollments = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Send reminder to each trainee
    foreach ($enrollments as $enrollment) {
        sendWorkshopReminderNotificationDual(
            $enrollment['user_id'],
            $workshop['workshop_name'],
            $workshop['workshop_date']
        );
    }
}

// Log: "Sent 45 workshop reminders"
*/

// ============================================================
// REQUIRED FUNCTIONS (in sms_helpers.php)
// ============================================================

/*
Available notification functions:

SMS + Email (Respect Preferences):
- sendOrderPlacedNotificationDual($user_id, $order_id, $total)
- sendOrderShippedNotificationDual($user_id, $order_id)
- sendOrderDeliveredNotificationDual($user_id, $order_id)
- sendWorkshopEnrollmentNotificationDual($user_id, $workshop_name, $date)
- sendWorkshopReminderNotificationDual($user_id, $workshop_name, $date)
- sendCertificationIssuedNotificationDual($user_id, $cert_title)
- sendPaymentReceivedNotificationDual($user_id, $amount)
- sendAdminAlertNotificationDual($admin_id, $title, $message)

SMS Only (Old Way):
- sendOrderPlacedNotification($user_id, $order_id, $total)
- sendOrderShippedNotification($user_id, $order_id)
- sendOrderDeliveredNotification($user_id, $order_id)
- sendWorkshopEnrollmentNotification($user_id, $workshop_name, $date)
- sendWorkshopReminderNotification($user_id, $workshop_name, $date)
- sendCertificationIssuedNotification($user_id, $cert_title)
- sendPaymentReceivedNotification($user_id, $amount)

Admin Functions:
- sendNotificationToUser($user_id, $template, $replacements, $force_method)
- sendNotificationToGroup($group_id, $template, $replacements)
- sendNotificationToAll($template, $replacements)
*/

?>

*/

/**
 * EXAMPLE 2: Add SMS to workshop enrollment
 * File: pages/trainee/submit_enrollment.php
 * 
 * Add this after enrollment is recorded:
 */

/*

require_once '../../config/sms_helpers.php';

// ... after inserting enrollment record ...

// Get workshop details
$ws_result = $conn->query(
    "SELECT title, scheduled_date, start_time FROM workshops WHERE id = $workshop_id"
);
$workshop = $ws_result->fetch_assoc();

// SEND SMS NOTIFICATION
sendWorkshopEnrollmentNotification(
    $user_id,
    $workshop['title'],
    $workshop['scheduled_date']
);

*/

/**
 * EXAMPLE 3: Create a cron job for workshop reminders
 * File: cron/send_workshop_reminders.php
 * 
 * Run daily at 8:00 AM via cPanel or server cron
 * Add to crontab: 0 8 * * * /usr/bin/php /home/user/public_html/ugat/cron/send_workshop_reminders.php
 */

/*

<?php
require_once '../config/db.php';
require_once '../config/sms_helpers.php';

// Get workshops starting today
$today = date('Y-m-d');
$result = $conn->query(
    "SELECT DISTINCT we.user_id, w.id, w.title, w.scheduled_date, w.start_time
     FROM workshop_enrollments we
     JOIN workshops w ON we.workshop_id = w.id
     WHERE DATE(w.scheduled_date) = '$today'
     AND w.status = 'upcoming'"
);

$count = 0;
while ($row = $result->fetch_assoc()) {
    $sms = sendWorkshopReminderNotification(
        $row['user_id'],
        $row['title'],
        $row['scheduled_date'],
        $row['start_time']
    );

    if ($sms['success']) {
        $count++;
    }
}

echo "Sent $count workshop reminders on " . date('Y-m-d H:i:s');
?>

*/

/**
 * EXAMPLE 4: Add SMS to certification issuance
 * File: pages/admin/export_certificates.php
 * 
 * Add after certificate is issued:
 */

/*

require_once '../../config/sms_helpers.php';

// ... after certificate record is created ...

$cert_result = $conn->query(
    "SELECT uc.user_id, w.title FROM user_certifications uc
     JOIN workshops w ON uc.workshop_id = w.id
     WHERE uc.id = $cert_id"
);

$cert = $cert_result->fetch_assoc();

sendCertificationIssuedNotification(
    $cert['user_id'],
    $cert['title'],
    'https://ugat.local/pages/trainee/TraineeProfile.html'
);

*/

/**
 * EXAMPLE 5: Add SMS to payment confirmation
 * File: pages/trainee/place_order.php (payment section)
 */

/*

require_once '../../config/sms_helpers.php';

// ... after payment is confirmed ...

sendPaymentReceivedNotification(
    $user_id,
    '₱' . number_format($amount, 2),
    $order_id
);

*/

/**
 * EXAMPLE 6: Bulk SMS to group (e.g., all users in a workshop)
 * File: pages/admin/send_bulk_sms.php
 */

/*

<?php
require_once '../../config/sms_helpers.php';
require_once '../../config/auth_guard.php';

requireRole('admin');

$workshop_id = (int)$_POST['workshop_id'];
$message = $_POST['message'] ?? '';

// Get all users in workshop
$result = $conn->query(
    "SELECT u.id FROM users u
     JOIN workshop_enrollments we ON u.id = we.user_id
     WHERE we.workshop_id = $workshop_id AND u.role = 'trainee'"
);

$user_ids = [];
while ($row = $result->fetch_assoc()) {
    $user_ids[] = $row['id'];
}

// Send SMS to all
$results = sendSmsForEventToMultiple('workshop_reminder', $user_ids, [
    'workshop_name' => $_POST['workshop_name'] ?? '',
    'date' => $_POST['date'] ?? ''
]);

echo json_encode(['success' => true, 'results' => $results]);
?>

*/

?>
<!-- 
QUICK REFERENCE: Sender Functions

sendOrderPlacedNotification($user_id, $order_id, $total, $link = '')
sendOrderShippedNotification($user_id, $order_id, $link = '')
sendOrderDeliveredNotification($user_id, $order_id)
sendWorkshopEnrollmentNotification($user_id, $workshop_name, $date, $link = '')
sendWorkshopReminderNotification($user_id, $workshop_name, $date, $time = '')
sendCertificationIssuedNotification($user_id, $workshop_name, $link = '')
sendPaymentReceivedNotification($user_id, $amount, $order_id)
sendSmsForEvent($event_type, $user_id, $data = [])
sendSmsForEventToMultiple($event_type, $user_ids = [], $data = [])

All functions return: ['success' => bool, 'message' => string, 'sms_id' => string]
-->
