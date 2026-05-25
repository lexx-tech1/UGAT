<?php
/**
 * Gmail SMTP Configuration
 * 
 * SETUP INSTRUCTIONS:
 * 
 * 1. Enable 2-Factor Authentication:
 *    Go to: https://myaccount.google.com/security
 *    Enable "2-Step Verification"
 * 
 * 2. Create App Password:
 *    Go to: https://myaccount.google.com/apppasswords
 *    Select: App = "Mail", Device = "Windows Computer"
 *    Copy the 16-character password
 * 
 * 3. Configure below:
 *    - Set GMAIL_SENDER_EMAIL to your Gmail address
 *    - Set GMAIL_SENDER_PASSWORD to the 16-char App Password
 * 
 * 4. Test:
 *    Visit: http://localhost/ugat/test_gmail_verification.php
 */

// ============================================
// GMAIL SMTP CREDENTIALS - EDIT THESE
// ============================================
if (!defined('GMAIL_SENDER_EMAIL'))    define('GMAIL_SENDER_EMAIL',    'gumabaojohnmanuel1506@gmail.com');
if (!defined('GMAIL_SENDER_PASSWORD')) define('GMAIL_SENDER_PASSWORD', 'hovd irvh yyza dbea');
if (!defined('GMAIL_SENDER_NAME'))     define('GMAIL_SENDER_NAME',     'UGAT Notifications');

// ============================================
// GMAIL SMTP SETTINGS - DO NOT CHANGE
// ============================================
if (!defined('GMAIL_SMTP_HOST'))       define('GMAIL_SMTP_HOST',       'smtp.gmail.com');
if (!defined('GMAIL_SMTP_PORT'))       define('GMAIL_SMTP_PORT',        587);
if (!defined('GMAIL_SMTP_ENCRYPTION')) define('GMAIL_SMTP_ENCRYPTION', 'tls');

// ============================================
// DEBUG MODE - Set to false in production
// ============================================
if (!defined('EMAIL_DEBUG_MODE'))      define('EMAIL_DEBUG_MODE',       false);

// ============================================
// EMAIL TEMPLATES
// ============================================
$email_templates = [
    'verification' => [
        'subject' => 'UGAT Email Verification Code',
        'body_html' => '<html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #f5f5f5; padding: 20px; text-align: center;">
                    <h2>UGAT Email Verification</h2>
                </div>
                <div style="padding: 20px;">
                    <p>Hi {{name}},</p>
                    <p>Thank you for signing up at UGAT. Please verify your email address using the code below:</p>
                    <div style="background-color: #e3f2fd; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px;">
                        <h3 style="margin: 0; color: #1976d2; font-family: monospace; font-size: 32px;">{{code}}</h3>
                    </div>
                    <p>This code will expire in 24 hours.</p>
                    <p style="color: #666; font-size: 12px;">If you did not request this, please ignore this email.</p>
                </div>
            </body>
        </html>',
        'body_text' => 'UGAT Email Verification\n\nHi {{name}},\n\nYour verification code is: {{code}}\n\nThis code will expire in 24 hours.\n\nIf you did not request this, please ignore this email.'
    ],
    
    'order_placed' => [
        'subject' => 'Order Confirmed - UGAT',
        'body_html' => '<html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="padding: 20px;">
                    <h2>Order Confirmed!</h2>
                    <p>Hi {{trainee_name}},</p>
                    <p>Thank you for your order. Here are your order details:</p>
                    <p><strong>Order ID:</strong> {{order_id}}</p>
                    <p><strong>Total:</strong> ₱{{total}}</p>
                    <p>We will notify you when your order is shipped.</p>
                    <p style="color: #666; font-size: 12px;">Thank you for shopping at UGAT!</p>
                </div>
            </body>
        </html>',
        'body_text' => 'Order Confirmed!\n\nHi {{trainee_name}},\n\nThank you for your order.\n\nOrder ID: {{order_id}}\nTotal: ₱{{total}}\n\nWe will notify you when your order is shipped.'
    ],
    
    'order_shipped' => [
        'subject' => 'Your Order Has Shipped - UGAT',
        'body_html' => '<html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="padding: 20px;">
                    <h2>Order Shipped!</h2>
                    <p>Hi {{trainee_name}},</p>
                    <p>Your order is on the way!</p>
                    <p><strong>Order ID:</strong> {{order_id}}</p>
                    <p><strong>Tracking Number:</strong> {{tracking_number}}</p>
                    <p>You can track your package using the tracking number above.</p>
                </div>
            </body>
        </html>',
        'body_text' => 'Order Shipped!\n\nHi {{trainee_name}},\n\nYour order is on the way!\n\nOrder ID: {{order_id}}\nTracking Number: {{tracking_number}}'
    ],
    
    'order_delivered' => [
        'subject' => 'Your Order Has Been Delivered - UGAT',
        'body_html' => '<html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="padding: 20px;">
                    <h2>Order Delivered!</h2>
                    <p>Hi {{trainee_name}},</p>
                    <p>Your order has been successfully delivered!</p>
                    <p><strong>Order ID:</strong> {{order_id}}</p>
                    <p>Thank you for your purchase. We hope you enjoy your items!</p>
                </div>
            </body>
        </html>',
        'body_text' => 'Order Delivered!\n\nHi {{trainee_name}},\n\nYour order has been successfully delivered!\n\nOrder ID: {{order_id}}'
    ],
    
    'workshop_enrollment' => [
        'subject' => 'Welcome to {{workshop_name}} - UGAT',
        'body_html' => '<html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="padding: 20px;">
                    <h2>Enrollment Confirmed!</h2>
                    <p>Hi {{trainee_name}},</p>
                    <p>You have been enrolled in <strong>{{workshop_name}}</strong>.</p>
                    <p><strong>Date:</strong> {{workshop_date}}</p>
                    <p><strong>Location:</strong> {{workshop_location}}</p>
                    <p>We look forward to seeing you!</p>
                </div>
            </body>
        </html>',
        'body_text' => 'Enrollment Confirmed!\n\nHi {{trainee_name}},\n\nYou have been enrolled in {{workshop_name}}.\n\nDate: {{workshop_date}}\nLocation: {{workshop_location}}'
    ],
    
    'workshop_reminder' => [
        'subject' => '{{workshop_name}} Starts Tomorrow - UGAT',
        'body_html' => '<html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="padding: 20px;">
                    <h2>Workshop Reminder</h2>
                    <p>Hi {{trainee_name}},</p>
                    <p><strong>{{workshop_name}}</strong> starts tomorrow!</p>
                    <p><strong>Time:</strong> {{workshop_time}}</p>
                    <p><strong>Location:</strong> {{workshop_location}}</p>
                    <p>See you there!</p>
                </div>
            </body>
        </html>',
        'body_text' => 'Workshop Reminder\n\nHi {{trainee_name}},\n\n{{workshop_name}} starts tomorrow!\n\nTime: {{workshop_time}}\nLocation: {{workshop_location}}'
    ],
    
    'certification_issued' => [
        'subject' => 'Your Certificate is Ready - UGAT',
        'body_html' => '<html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="padding: 20px;">
                    <h2>Certificate Issued!</h2>
                    <p>Hi {{trainee_name}},</p>
                    <p>Congratulations! Your certificate for <strong>{{certification_name}}</strong> is now ready.</p>
                    <p>You can download it from your UGAT account.</p>
                    <p>Great work!</p>
                </div>
            </body>
        </html>',
        'body_text' => 'Certificate Issued!\n\nHi {{trainee_name}},\n\nCongratulations! Your certificate for {{certification_name}} is now ready.\n\nYou can download it from your UGAT account.'
    ],
    
    'payment_received' => [
        'subject' => 'Payment Received - UGAT',
        'body_html' => '<html>
            <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="padding: 20px;">
                    <h2>Payment Received</h2>
                    <p>Hi {{trainee_name}},</p>
                    <p>Thank you! We have received your payment.</p>
                    <p><strong>Amount:</strong> ₱{{amount}}</p>
                    <p><strong>Reference:</strong> {{reference}}</p>
                    <p>Your account has been updated.</p>
                </div>
            </body>
        </html>',
        'body_text' => 'Payment Received\n\nHi {{trainee_name}},\n\nThank you! We have received your payment.\n\nAmount: ₱{{amount}}\nReference: {{reference}}'
    ]
];

return $email_templates;
?>
