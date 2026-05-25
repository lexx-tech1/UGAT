<?php
// ============================================================
//  SMS INTEGRATION GUIDE
//  How to integrate SMS notifications into your UGAT system
// ============================================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>UGAT SMS Integration Guide</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1, h2 { color: #4CAF50; }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .step {
            background: #f9f9f9;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin: 15px 0;
        }
        .code-block {
            background: #272822;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #28a745;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔔 UGAT SMS Notification System - Setup Guide</h1>

    <div class="success">
        <strong>✓ Success!</strong> SMS API files have been created and are ready to integrate.
    </div>

    <h2>📋 Files Created</h2>
    <ul>
        <li><code>config/sms.php</code> - SMS configuration and templates</li>
        <li><code>config/sms_service.php</code> - SMS sending service class</li>
        <li><code>config/sms_helpers.php</code> - Helper functions for easy integration</li>
        <li><code>pages/admin/send_sms_notification.php</code> - Admin SMS sending API</li>
        <li><code>pages/admin/get_sms_notifications.php</code> - Admin notification history</li>
        <li><code>pages/admin/admin_sms.js</code> - Admin frontend component</li>
        <li><code>pages/trainee/get_sms_notifications.php</code> - Trainee SMS retrieval API</li>
        <li><code>pages/trainee/trainee_sms_notifications.js</code> - Trainee notification display</li>
        <li><code>config/create_sms_tables.php</code> - Database setup script</li>
    </ul>

    <h2>🚀 Quick Start (5 Steps)</h2>

    <div class="step">
        <h3>Step 1: Create Database Tables</h3>
        <p>Run this script to create required SMS tables:</p>
        <div class="code-block">
http://localhost/ugat/config/create_sms_tables.php
        </div>
        <p>This will create:</p>
        <ul>
            <li><code>sms_logs</code> - All SMS records</li>
            <li><code>sms_notifications</code> - Admin-sent notifications</li>
            <li><code>trainee_sms_log</code> - Trainee message history</li>
        </ul>
        <p>It will also add a <code>phone</code> column to the <code>users</code> table if needed.</p>
    </div>

    <div class="step">
        <h3>Step 2: Configure SMS Provider</h3>
        <p>Edit <code>config/sms.php</code> and add your Twilio credentials:</p>
        <div class="code-block">
define('TWILIO_ACCOUNT_SID', 'your_account_sid_here');
define('TWILIO_AUTH_TOKEN', 'your_auth_token_here');
define('TWILIO_PHONE_NUMBER', '+1234567890');
        </div>
        <p>Get these from: <a href="https://www.twilio.com/console" target="_blank">https://www.twilio.com/console</a></p>
        <p><strong>For testing:</strong> Set <code>SMS_DEBUG_MODE = true</code> to log instead of send</p>
    </div>

    <div class="step">
        <h3>Step 3: Enable Phone Number Field</h3>
        <p>Ensure users have phone numbers in the database. During registration or profile update:</p>
        <div class="code-block">
UPDATE users SET phone = '+63912345678' WHERE id = 1;
        </div>
        <p>Phone format should be international: <code>+country_codeNUMBER</code></p>
    </div>

    <div class="step">
        <h3>Step 4: Add Admin SMS UI</h3>
        <p>In your <code>AdminDashboard.html</code>, add:</p>
        <div class="code-block">
&lt;div id="sms-history"&gt;&lt;/div&gt;
&lt;button id="sms-send-btn"&gt;Send SMS Notification&lt;/button&gt;
&lt;script src="admin_sms.js"&gt;&lt;/script&gt;
        </div>
    </div>

    <div class="step">
        <h3>Step 5: Add Trainee SMS Display</h3>
        <p>In your <code>TraineeNotifications.html</code>, add:</p>
        <div class="code-block">
&lt;div id="sms-notifications-container"&gt;&lt;/div&gt;
&lt;script src="trainee_sms_notifications.js"&gt;&lt;/script&gt;
        </div>
    </div>

    <h2>💻 Integration Examples</h2>

    <h3>Example 1: Send SMS When Order is Placed</h3>
    <p>In your <code>pages/trainee/place_order.php</code>:</p>
    <div class="code-block">
require_once '../../config/sms_helpers.php';

// ... after order is created ...

sendOrderPlacedNotification(
    $user_id,
    $order_id,
    '₱' . $total,
    'https://ugat.local/pages/trainee/TraineeShop.html'
);
    </div>

    <h3>Example 2: Send SMS When Workshop Starts</h3>
    <p>Create a <code>trigger_workshop_reminders.php</code> (run via cron):</p>
    <div class="code-block">
require_once 'config/sms_helpers.php';

$conn->query("SELECT we.user_id, w.title, w.start_date, w.start_time 
             FROM workshop_enrollments we 
             JOIN workshops w ON we.workshop_id = w.id 
             WHERE w.start_date = DATE(NOW())");

while ($row = $result->fetch_assoc()) {
    sendWorkshopReminderNotification(
        $row['user_id'],
        $row['title'],
        $row['start_date'],
        $row['start_time']
    );
}
    </div>

    <h3>Example 3: Admin Sends Custom SMS</h3>
    <p>Already built-in! Use the admin button to send to:</p>
    <ul>
        <li>Single trainee</li>
        <li>All trainees in a workshop</li>
        <li>All trainees</li>
    </ul>

    <h2>🔧 API Endpoints</h2>

    <h3>Admin Endpoints</h3>
    <div class="code-block">
POST /pages/admin/send_sms_notification.php
{
  "recipient_type": "single|group|all",
  "recipient_id": 123,
  "template": "order_placed",
  "custom_message": "Custom message here",
  "template_replacements": { "name": "John" }
}

GET /pages/admin/get_sms_notifications.php?limit=50&offset=0
    </div>

    <h3>Trainee Endpoints</h3>
    <div class="code-block">
GET /pages/trainee/get_sms_notifications.php?limit=50&offset=0
    </div>

    <h2>📱 Available SMS Templates</h2>
    <ul>
        <li><code>order_placed</code> - "Hi {name}, your order #{order_id} has been placed..."</li>
        <li><code>order_shipped</code> - "Hi {name}, your order #{order_id} has been shipped..."</li>
        <li><code>order_delivered</code> - "Hi {name}, your order #{order_id} has been delivered..."</li>
        <li><code>workshop_enrollment</code> - "Hi {name}, you have been enrolled in {workshop_name}..."</li>
        <li><code>workshop_reminder</code> - "Reminder: {workshop_name} is on {date}..."</li>
        <li><code>certification_issued</code> - "Congratulations {name}! Your certification is ready..."</li>
        <li><code>payment_received</code> - "Thank you {name}! Payment of {amount} received..."</li>
    </ul>

    <h2>⚙️ Configuration Options</h2>
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background: #f0f0f0;">
            <th>Option</th>
            <th>Type</th>
            <th>Default</th>
            <th>Description</th>
        </tr>
        <tr>
            <td><code>SMS_PROVIDER</code></td>
            <td>string</td>
            <td>'twilio'</td>
            <td>SMS provider to use</td>
        </tr>
        <tr>
            <td><code>SMS_ENABLED</code></td>
            <td>bool</td>
            <td>true</td>
            <td>Enable/disable SMS globally</td>
        </tr>
        <tr>
            <td><code>SMS_DEBUG_MODE</code></td>
            <td>bool</td>
            <td>false</td>
            <td>Log instead of sending (testing)</td>
        </tr>
    </table>

    <div class="warning">
        <strong>⚠️ Important Security Notes:</strong>
        <ul>
            <li>Never commit Twilio credentials to version control</li>
            <li>Use environment variables in production</li>
            <li>Restrict API endpoints to authenticated users only</li>
            <li>Log all SMS sends for compliance</li>
            <li>Implement rate limiting to prevent abuse</li>
        </ul>
    </div>

    <div class="success">
        <strong>✓ Done!</strong> Your SMS notification system is ready to use.
        <br><br>
        <a href="../../pages/admin/AdminDashboard.html" style="color: #4CAF50; text-decoration: none; font-weight: bold;">
            Go to Admin Dashboard →
        </a>
    </div>

</div>
</body>
</html>
