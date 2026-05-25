<?php
// ============================================================
//  config/email.php  —  Email Configuration
//  
//  Configure email provider (PHPMailer with Gmail SMTP)
// ============================================================

// Email Provider Settings
define('EMAIL_PROVIDER', getenv('EMAIL_PROVIDER') ?: 'gmail_smtp');

define('EMAIL_DEBUG_MODE', filter_var(getenv('EMAIL_DEBUG_MODE') ?: false, FILTER_VALIDATE_BOOLEAN));

define('GMAIL_ADDRESS',      getenv('GMAIL_ADDRESS')      ?: '');
define('GMAIL_APP_PASSWORD', getenv('GMAIL_APP_PASSWORD') ?: '');
define('SMTP_HOST',          getenv('SMTP_HOST')          ?: 'smtp.gmail.com');
define('SMTP_PORT',     (int)(getenv('SMTP_PORT')         ?: 587));
define('SMTP_ENCRYPTION',    getenv('SMTP_ENCRYPTION')    ?: 'tls');

define('EMAIL_FROM_NAME',    getenv('EMAIL_FROM_NAME')    ?: 'UGAT Notifications');
define('EMAIL_FROM_ADDRESS', getenv('EMAIL_FROM_ADDRESS') ?: getenv('GMAIL_ADDRESS') ?: '');

define('EMAIL_ENABLED', filter_var(getenv('EMAIL_ENABLED') !== false ? getenv('EMAIL_ENABLED') : true, FILTER_VALIDATE_BOOLEAN));

// Email templates (HTML and plain text versions)
$EMAIL_TEMPLATES = [
    'order_placed' => [
        'subject' => 'Order Confirmation - UGAT Shop',
        'html' => '<h2>Order Confirmation</h2>
<p>Hi {name},</p>
<p>Your order <strong>#{order_id}</strong> has been received and is being processed.</p>
<p><strong>Order Total:</strong> {total}</p>
<p><a href="{link}" style="background-color: #4B8423; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">View Order Details</a></p>
<p>Thank you for shopping with UGAT!</p>',
        'text' => 'Order Confirmation\n\nHi {name},\n\nYour order #{order_id} has been received.\nOrder Total: {total}\n\nView your order: {link}\n\nThank you for shopping with UGAT!'
    ],
    'order_confirmed' => [
        'subject' => 'Order Confirmed - UGAT Shop',
        'html' => '<h2>Order Confirmed</h2>
<p>Hi {name},</p>
<p>Good news! Your order <strong>#{order_id}</strong> has been confirmed and is now being processed by our team.</p>
<p>We will notify you once your order is being prepared for delivery.</p>
<p>Thank you for shopping with UGAT!</p>',
        'text' => 'Order Confirmed\n\nHi {name},\n\nYour order #{order_id} has been confirmed and is being processed.\n\nThank you for shopping with UGAT!'
    ],
    'order_preparing' => [
        'subject' => 'Your Order Is Being Prepared - UGAT Shop',
        'html' => '<h2>Order Being Prepared</h2>
<p>Hi {name},</p>
<p>Your order <strong>#{order_id}</strong> is now being carefully prepared for delivery.</p>
<p>We will notify you once it is out for delivery.</p>
<p>Thank you for your patience!</p>',
        'text' => 'Order Preparing\n\nHi {name},\n\nYour order #{order_id} is now being prepared for delivery.\n\nThank you!'
    ],
    'order_shipped' => [
        'subject' => 'Your Order Is Out for Delivery - UGAT Shop',
        'html' => '<h2>Out for Delivery</h2>
<p>Hi {name},</p>
<p>Great news! Your order <strong>#{order_id}</strong> is now out for delivery.</p>
<p>Expect your package to arrive today. Please make sure someone is available to receive it.</p>
<p>Thank you for shopping with UGAT!</p>',
        'text' => 'Out for Delivery\n\nHi {name},\n\nYour order #{order_id} is out for delivery. Expect it today!\n\nThank you!'
    ],
    'order_delivered' => [
        'subject' => 'Your Order Has Been Delivered - UGAT Shop',
        'html' => '<h2>Delivery Confirmation</h2>
<p>Hi {name},</p>
<p>Your order <strong>#{order_id}</strong> has been successfully delivered!</p>
<p>We hope you enjoy your purchase. Thank you for supporting UGAT!</p>
<p>If you have any questions, please contact us.</p>',
        'text' => 'Delivery Confirmation\n\nHi {name},\n\nYour order #{order_id} has been delivered!\n\nThank you for supporting UGAT!'
    ],
    'order_cancelled' => [
        'subject' => 'Order Cancelled - UGAT Shop',
        'html' => '<h2>Order Cancelled</h2>
<p>Hi {name},</p>
<p>Your order <strong>#{order_id}</strong> has been cancelled.</p>
<p>If you did not request this cancellation or have questions, please contact UGAT directly.</p>
<p>We hope to serve you again soon!</p>',
        'text' => 'Order Cancelled\n\nHi {name},\n\nYour order #{order_id} has been cancelled. Contact UGAT if you have questions.'
    ],
    'enrollment_rejected' => [
        'subject' => 'Workshop Enrollment Update - UGAT',
        'html' => '<h2>Enrollment Not Approved</h2>
<p>Hi {name},</p>
<p>We regret to inform you that your enrollment in <strong>{workshop_name}</strong> was not approved at this time.</p>
<p>Please contact UGAT for more information or to inquire about future workshop opportunities.</p>
<p>Thank you for your interest in UGAT programs.</p>',
        'text' => 'Enrollment Update\n\nHi {name},\n\nYour enrollment in {workshop_name} was not approved. Contact UGAT for more information.'
    ],
    'workshop_enrollment' => [
        'subject' => 'Workshop Enrollment Confirmed - UGAT',
        'html' => '<h2>Workshop Enrollment Confirmed</h2>
<p>Hi {name},</p>
<p>You have been successfully enrolled in:</p>
<p><strong>{workshop_name}</strong></p>
<p><strong>Date:</strong> {date}<br>
<strong>Time:</strong> {time}</p>
<p><a href="{link}" style="background-color: #4B8423; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">View Workshop Details</a></p>
<p>We look forward to seeing you there!</p>',
        'text' => 'Workshop Enrollment Confirmed\n\nHi {name},\n\nYou have been enrolled in: {workshop_name}\nDate: {date}\nTime: {time}\n\nView details: {link}\n\nSee you soon!'
    ],
    'workshop_reminder' => [
        'subject' => 'Reminder: Upcoming Workshop - UGAT',
        'html' => '<h2>Workshop Reminder</h2>
<p>Hi {name},</p>
<p>This is a friendly reminder about your upcoming workshop:</p>
<p><strong>{workshop_name}</strong></p>
<p><strong>Date:</strong> {date}<br>
<strong>Time:</strong> {time}</p>
<p>Make sure to arrive on time!</p>',
        'text' => 'Workshop Reminder\n\nHi {name},\n\nReminder: {workshop_name}\nDate: {date}\nTime: {time}\n\nSee you soon!'
    ],
    'certification_issued' => [
        'subject' => 'Your Certification is Ready - UGAT',
        'html' => '<h2>Certification Issued</h2>
<p>Hi {name},</p>
<p>Congratulations! Your certification for <strong>{workshop_name}</strong> is now available.</p>
<p><a href="{link}" style="background-color: #4B8423; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Download Certificate</a></p>
<p>Well done on completing the workshop!</p>',
        'text' => 'Certification Issued\n\nHi {name},\n\nCongratulations! Your certification for {workshop_name} is ready.\n\nDownload: {link}'
    ],
    'payment_received' => [
        'subject' => 'Payment Received - UGAT',
        'html' => '<h2>Payment Confirmation</h2>
<p>Hi {name},</p>
<p>Thank you! We have received your payment of <strong>{amount}</strong> for order <strong>#{order_id}</strong>.</p>
<p>Your order is now being prepared for shipment.</p>
<p>Thank you for shopping with UGAT!</p>',
        'text' => 'Payment Confirmation\n\nHi {name},\n\nThank you! Payment of {amount} received for order #{order_id}.\n\nThank you for shopping with UGAT!'
    ],
    'admin_alert' => [
        'subject' => 'UGAT Admin Alert',
        'html' => '<h2>Admin Alert</h2>
<p><strong>{message}</strong></p>
<p>Timestamp: {timestamp}</p>',
        'text' => 'Admin Alert\n\n{message}\n\nTimestamp: {timestamp}'
    ],
];

/**
 * Get email template with substituted values
 * 
 * @param string $template_key
 * @param array $replacements
 * @param string $format 'html' or 'text'
 * @return array ['subject' => string, 'body' => string]
 */
function getEmailTemplate(string $template_key, array $replacements = [], string $format = 'html'): array
{
    global $EMAIL_TEMPLATES;
    
    if (!isset($EMAIL_TEMPLATES[$template_key])) {
        return [
            'subject' => 'UGAT Notification',
            'body' => 'No template found.'
        ];
    }
    
    $template = $EMAIL_TEMPLATES[$template_key];
    $subject = $template['subject'] ?? 'UGAT Notification';
    $body = $format === 'text' ? ($template['text'] ?? '') : ($template['html'] ?? '');
    
    // Replace all placeholders
    foreach ($replacements as $key => $value) {
        $subject = str_replace('{' . $key . '}', $value, $subject);
        $body = str_replace('{' . $key . '}', $value, $body);
    }
    
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

