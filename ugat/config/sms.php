<?php
// ============================================================
//  config/sms.php  —  SMS Configuration
//  
//  Configure your SMS provider credentials here.
//  Currently supports: Semaphore API (Philippines)
// ============================================================

// SMS Provider Settings
define('SMS_PROVIDER', getenv('SMS_PROVIDER') ?: 'unisms');

define('UNISMS_API_KEY', getenv('UNISMS_API_KEY') ?: '');
define('UNISMS_API_URL', getenv('UNISMS_API_URL') ?: 'https://unismsapi.com/api/sms');

define('SEMAPHORE_API_KEY',    getenv('SEMAPHORE_API_KEY')    ?: '');
define('SEMAPHORE_SENDER_NAME',getenv('SEMAPHORE_SENDER_NAME')?: 'SEMAPHORE');

define('SMS_ENABLED',    filter_var(getenv('SMS_ENABLED')    !== false ? getenv('SMS_ENABLED')    : true,  FILTER_VALIDATE_BOOLEAN));
define('SMS_DEBUG_MODE', filter_var(getenv('SMS_DEBUG_MODE') !== false ? getenv('SMS_DEBUG_MODE') : false, FILTER_VALIDATE_BOOLEAN));

// Notification Preferences
define('NOTIFICATION_PREFERENCE_DEFAULT', 'sms');  // Options: 'sms', 'email', 'both'
define('ENABLE_DUAL_NOTIFICATIONS', true);  // Enable SMS + Email dual notification system

// Default SMS templates
$SMS_TEMPLATES = [
    'order_placed'          => 'Hi {name}, your UGAT order #{order_id} has been placed. Total: {total}. We will notify you of updates.',
    'order_confirmed'       => 'Hi {name}, your UGAT order #{order_id} has been confirmed and is being processed.',
    'order_preparing'       => 'Hi {name}, your UGAT order #{order_id} is now being prepared for delivery.',
    'order_shipped'         => 'Hi {name}, your UGAT order #{order_id} is out for delivery. Expect it today!',
    'order_delivered'       => 'Hi {name}, your UGAT order #{order_id} has been delivered. Thank you for shopping with UGAT!',
    'order_cancelled'       => 'Hi {name}, your UGAT order #{order_id} has been cancelled. Contact UGAT for assistance.',
    'workshop_enrollment'   => 'Hi {name}, your enrollment in {workshop_name} has been approved. Check the UGAT app for details.',
    'enrollment_rejected'   => 'Hi {name}, your enrollment in {workshop_name} was not approved. Contact UGAT for more information.',
    'workshop_reminder'     => 'Reminder: {workshop_name} is on {date} at {time}. See you there!',
    'certification_issued'  => 'Congratulations {name}! Your certification for {workshop_name} is ready. Check the UGAT app to download it.',
    'admin_alert'           => 'Admin Alert: {message}',
    'payment_received'      => 'Thank you {name}! Your GCash payment of {amount} for order #{order_id} has been received.',
];

/**
 * Get SMS template with substituted values
 * 
 * @param string $template_key
 * @param array $replacements
 * @return string
 */
function getSmsTemplate(string $template_key, array $replacements = []): string
{
    global $SMS_TEMPLATES;
    
    $template = $SMS_TEMPLATES[$template_key] ?? 'No template found.';
    
    foreach ($replacements as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    
    return $template;
}
