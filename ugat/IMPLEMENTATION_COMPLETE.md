# [BELL] UGAT Dual-Channel Notification System - Implementation Complete

## Overview

A complete **SMS + Email notification system** for UGAT (Urban Gardening and Agricultural Technologies) using:
- **Semaphore API** for SMS (optimized for Philippines)
- **Gmail/SMTP via PHPMailer** for Email
- **User preference system** (SMS only, Email only, or Both)

All notifications are logged for audit trails and user history.

---

## [CHECK] What's Been Implemented

### Phase 1: Database & Configuration ✓

**Database Tables Created:**
- `notification_preferences` - User notification channel preferences and email
- `email_logs` - All email send attempts with metadata
- `trainee_email_log` - User-level email history for UI display
- `email_verification_tokens` - Email verification flow support
- `sms_logs` (existing) - SMS send history
- `trainee_sms_log` (existing) - User-level SMS history

**Configuration Files:**
- [config/sms.php](config/sms.php) - Semaphore API settings + SMS templates
- [config/email.php](config/email.php) - Gmail SMTP settings + Email templates (HTML + plain text)
- [config/sms.php](config/sms.php) - Notification preference defaults

### Phase 2: Service Layer ✓

**Backend Services:**
- [config/sms_service.php](config/sms_service.php)
  - `SmsService` class with Semaphore API integration
  - Methods: `sendSms()`, `sendSmsToMultiple()`, `logSmsToDatabase()`
  - Auto phone number format conversion (+63 → 0)
  
- [config/email_service.php](config/email_service.php)
  - `EmailService` class with Gmail/SMTP integration
  - Methods: `sendEmail()`, `sendEmailToMultiple()`, `logEmailToDatabase()`
  - Same interface as SmsService for consistency

- [config/notification_preferences.php](config/notification_preferences.php)
  - `getUserNotificationPreference()` - Get user's channel preferences
  - `setUserNotificationPreference()` - Save user preferences
  - `getNotificationChannels()` - Determine which channels to use (with fallback)
  - `sendEmailVerification()` - Send verification email flow
  - `verifyEmailWithToken()` - Verify email with token

### Phase 3: Notification Helpers ✓

**Single-Channel Functions** (SMS only):
- [config/sms_helpers.php](config/sms_helpers.php) with 8 helper functions:
  - `sendOrderPlacedNotification()`
  - `sendOrderShippedNotification()`
  - `sendOrderDeliveredNotification()`
  - `sendWorkshopEnrollmentNotification()`
  - `sendWorkshopReminderNotification()`
  - `sendCertificationIssuedNotification()`
  - `sendPaymentReceivedNotification()`
  - `sendSmsForEvent()` - Generic event-based SMS

**Dual-Channel Functions** (SMS + Email):
- Same 8 functions with "Dual" suffix:
  - `sendOrderPlacedNotificationDual()`
  - `sendOrderShippedNotificationDual()`
  - `sendOrderDeliveredNotificationDual()`
  - `sendWorkshopEnrollmentNotificationDual()`
  - `sendWorkshopReminderNotificationDual()`
  - `sendCertificationIssuedNotificationDual()`
  - `sendPaymentReceivedNotificationDual()`
  - `sendNotificationByPreference()` - Generic multi-channel sender

### Phase 4: API Endpoints ✓

**Trainee Endpoints:**
- [pages/trainee/get_notification_preferences.php](pages/trainee/get_notification_preferences.php) (GET)
  - Returns: `{ email, phone_enabled, email_enabled, email_verified }`

- [pages/trainee/update_notification_preferences.php](pages/trainee/update_notification_preferences.php) (POST)
  - Request: `{ phone_enabled: bool, email_enabled: bool, email: string }`

- [pages/trainee/get_all_notifications.php](pages/trainee/get_all_notifications.php) (GET)
  - Query params: `type=both|sms|email`, `limit=50`, `offset=0`
  - Returns: Combined SMS + Email notifications with channel indicators

**Admin Endpoints:**
- [pages/admin/send_notification.php](pages/admin/send_notification.php) (POST)
  - Send to single user, group (workshop), or all trainees
  - Override user preferences with `force_method: sms|email|both`
  - Request: `{ recipient_type, recipient_id, template, replacements, force_method }`

- [pages/admin/get_notifications.php](pages/admin/get_notifications.php) (GET)
  - Combined SMS + Email admin log
  - Query params: `type=both|sms|email`, `limit=50`, `offset=0`
  - Optional date filtering: `start_date`, `end_date`

### Phase 5: Database Setup ✓

- [config/create_email_tables.php](config/create_email_tables.php)
  - Automated setup script - creates all 4 required tables
  - Run once: `http://localhost/ugat/config/create_email_tables.php`

---

## [ROCKET] Quick Start (Setup Steps)

### Step 1: Create Database Tables
```bash
Visit: http://localhost/ugat/config/create_email_tables.php
```

Expected output:
```
✓ notification_preferences table created successfully
✓ email_logs table created successfully
✓ trainee_email_log table created successfully
✓ email_verification_tokens table created successfully
Tables created: 4/4
```

### Step 2: Configure Semaphore API
Edit `config/sms.php`:
```php
define('SEMAPHORE_API_KEY', 'your_actual_api_key');  // Get from https://semaphore.co
define('SEMAPHORE_SENDER_NAME', 'UGAT');  // Max 11 chars
define('SMS_DEBUG_MODE', false);  // true = log instead of sending
```

### Step 3: Configure Gmail/SMTP
Edit `config/email.php`:
```php
define('GMAIL_ADDRESS', 'your_gmail@gmail.com');
define('GMAIL_APP_PASSWORD', 'your_16_char_app_password');  // From https://myaccount.google.com/apppasswords
define('EMAIL_DEBUG_MODE', false);  // true = log instead of sending
```

### Step 4: (Optional) Enable Debug Mode for Testing
```php
// In config/sms.php
define('SMS_DEBUG_MODE', true);

// In config/email.php
define('EMAIL_DEBUG_MODE', true);
```

Notifications will be logged to `error_log` instead of being sent.

---

## 📱 Usage Examples

### Example 1: Send SMS Only (Backward Compatible)
```php
require_once 'config/sms_helpers.php';

// User must have phone number set in users.phone
sendOrderPlacedNotification($user_id, $order_id, '₱5000');
```

### Example 2: Send SMS + Email (Respects User Preferences)
```php
require_once 'config/sms_helpers.php';

// Will send to SMS if user has phone_enabled=true
// Will send to Email if user has email_enabled=true AND email_verified=true
sendOrderPlacedNotificationDual($user_id, $order_id, '₱5000');
```

### Example 3: Force Send via Specific Channel (Admin)
```php
// In place_order.php or similar
$ch = curl_init('http://localhost/ugat/pages/admin/send_notification.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'recipient_type' => 'single',
    'recipient_id' => $user_id,
    'template' => 'order_placed',
    'replacements' => [
        'order_id' => $order_id,
        'total' => '₱5000'
    ],
    'force_method' => 'both'  // sms, email, or both
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);
// $data['success'] = true/false
```

### Example 4: Send to Workshop Group
```php
$ch = curl_init('http://localhost/ugat/pages/admin/send_notification.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'recipient_type' => 'group',
    'recipient_id' => $workshop_id,  // workshop_id, not user_id
    'template' => 'workshop_reminder',
    'replacements' => [
        'workshop_name' => 'Hydroponics 101',
        'date' => '2026-06-15',
        'time' => '2:00 PM'
    ],
    'force_method' => 'both'
]));
// ... rest of curl setup ...
```

---

## [CHART] Supported Notification Templates

Both SMS and Email systems support these 8 notification types:

| Template Key | Usage | SMS Max Length |
|---|---|---|
| `order_placed` | Order confirmation | 160 chars |
| `order_shipped` | Shipment notification | 160 chars |
| `order_delivered` | Delivery confirmation | 160 chars |
| `workshop_enrollment` | Workshop signup confirmation | 160 chars |
| `workshop_reminder` | Upcoming workshop reminder | 160 chars |
| `certification_issued` | Certificate ready | 160 chars |
| `payment_received` | Payment confirmation | 160 chars |
| `admin_alert` | Admin-to-user alerts | 160 chars |

Templates support placeholder replacement: `{name}`, `{order_id}`, `{workshop_name}`, etc.

---

## 🔐 API Request/Response Examples

### Get User Preferences
```bash
GET /pages/trainee/get_notification_preferences.php
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "phone": "+63912345678",
    "email": "user@example.com",
    "phone_enabled": true,
    "email_enabled": true,
    "email_verified": true
  }
}
```

### Update User Preferences
```bash
POST /pages/trainee/update_notification_preferences.php
Content-Type: application/json

{
  "phone_enabled": true,
  "email_enabled": true,
  "email": "newemail@example.com"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Preferences updated successfully."
}
```

### Send Admin Notification
```bash
POST /pages/admin/send_notification.php
Content-Type: application/json

{
  "recipient_type": "single",
  "recipient_id": 42,
  "template": "order_placed",
  "replacements": {
    "order_id": "ORD-2026-001",
    "total": "₱2,500"
  },
  "force_method": "both"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Sent to 1 users",
  "summary": {
    "total": 1,
    "success": 1,
    "failed": 0
  },
  "results": [
    {
      "user_id": 42,
      "result": {
        "success": true,
        "sms": { "success": true, "message": "SMS sent successfully.", "sms_id": "123456" },
        "email": { "success": true, "message": "Email sent successfully.", "email_id": "email_123" },
        "channels_used": ["sms", "email"]
      }
    }
  ]
}
```

### Get User Notifications
```bash
GET /pages/trainee/get_all_notifications.php?type=both&limit=10&offset=0
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "channel": "sms",
      "recipient": "+63912345678",
      "message": "Hi John, your order #ORD-001 has been delivered!",
      "type": "order_delivered",
      "status": "received",
      "timestamp": "2026-05-18 14:30:00",
      "is_read": 0
    },
    {
      "channel": "email",
      "recipient": "john@example.com",
      "message": "...",
      "type": "order_delivered",
      "status": "received",
      "timestamp": "2026-05-18 14:30:00",
      "is_read": 0
    }
  ],
  "pagination": {
    "total": 25,
    "limit": 10,
    "offset": 0,
    "returned": 10
  }
}
```

---

## [TEST] Testing

### Enable Debug Mode
```php
// config/sms.php
define('SMS_DEBUG_MODE', true);

// config/email.php
define('EMAIL_DEBUG_MODE', true);
```

Notifications will be logged to your PHP error log instead of being sent. Check:
- `tail -f /var/log/php-errors.log` (Linux)
- Windows Event Viewer for IIS/Apache

### Test SMS Sending
```php
require_once 'config/sms_helpers.php';

// Will log to error_log instead of sending
$result = sendOrderPlacedNotification(1, 'ORD-123', '₱5000');
var_dump($result);
```

### Test Email Sending
```php
require_once 'config/sms_helpers.php';

// Will log to error_log instead of sending
$result = sendOrderPlacedNotificationDual(1, 'ORD-123', '₱5000');
var_dump($result['email']);
```

---

## 📝 Database Schema

### notification_preferences
```sql
- id (PK)
- user_id (FK to users) - UNIQUE
- email VARCHAR(180)
- phone_enabled TINYINT (default 1)
- email_enabled TINYINT (default 1)
- email_verified TINYINT (default 0)
- created_at TIMESTAMP
- updated_at TIMESTAMP
```

### sms_logs
```sql
- id (PK)
- phone_number VARCHAR(20)
- message LONGTEXT
- sms_id VARCHAR(100)
- metadata JSON
- sent_at TIMESTAMP
- status VARCHAR(20)
```

### email_logs
```sql
- id (PK)
- user_id (FK to users, nullable)
- recipient_email VARCHAR(180)
- subject VARCHAR(255)
- message LONGTEXT
- email_provider VARCHAR(50)
- sent_at TIMESTAMP
- status VARCHAR(20)
- metadata JSON
```

### trainee_email_log
```sql
- id (PK)
- user_id (FK to users)
- recipient_email VARCHAR(180)
- subject VARCHAR(255)
- message LONGTEXT
- notification_type VARCHAR(50)
- status VARCHAR(20)
- received_at TIMESTAMP
- is_read TINYINT
```

### email_verification_tokens
```sql
- id (PK)
- user_id (FK to users)
- email VARCHAR(180)
- token VARCHAR(255) - UNIQUE
- expires_at DATETIME
- created_at TIMESTAMP
```

---

## 🔗 Integration with Existing Code

### From place_order.php
```php
// Before (SMS only)
require_once '../../config/sms_helpers.php';
sendOrderPlacedNotification($user_id, $order_id, '₱5000');

// After (SMS + Email, respects user preferences)
require_once '../../config/sms_helpers.php';
sendOrderPlacedNotificationDual($user_id, $order_id, '₱5000');
```

### From approve_enrollment.php
```php
// Before
sendWorkshopEnrollmentNotification($user_id, $workshop_name, $workshop_date, $link);

// After
sendWorkshopEnrollmentNotificationDual($user_id, $workshop_name, $workshop_date, '', $link);
```

All existing SMS-only functions remain unchanged for backward compatibility. Simply add `Dual` suffix to enable email.

---

## [TOOLS] Configuration Reference

| Setting | File | Default | Notes |
|---|---|---|---|
| `SEMAPHORE_API_KEY` | sms.php | `your_semaphore_api_key_here` | Required, get from https://semaphore.co |
| `SEMAPHORE_SENDER_NAME` | sms.php | `UGAT` | Max 11 chars |
| `SMS_DEBUG_MODE` | sms.php | `false` | Set to true for testing |
| `SMS_ENABLED` | sms.php | `true` | Disable to pause SMS service |
| `GMAIL_ADDRESS` | email.php | `your_gmail@gmail.com` | Required |
| `GMAIL_APP_PASSWORD` | email.php | `your_app_password_here` | 16-char app password from Google |
| `SMTP_HOST` | email.php | `smtp.gmail.com` | Don't change unless using different provider |
| `SMTP_PORT` | email.php | `587` | TLS port |
| `EMAIL_DEBUG_MODE` | email.php | `false` | Set to true for testing |
| `EMAIL_ENABLED` | email.php | `true` | Disable to pause email service |
| `NOTIFICATION_PREFERENCE_DEFAULT` | sms.php | `sms` | Fallback: 'sms', 'email', or 'both' |

---

## [TARGET] Key Features

[CHECK] **Semaphore API Integration** - Optimized for Philippines, instant delivery  
[CHECK] **Gmail SMTP** - No monthly fees, unlimited sending  
[CHECK] **User Preferences** - Users choose SMS, Email, or Both  
[CHECK] **Email Verification** - Token-based email confirmation  
[CHECK] **Fallback Logic** - Defaults to SMS if email not verified  
[CHECK] **Admin Override** - Force send via specific channel  
[CHECK] **Batch Sending** - Send to groups (workshops) or all trainees  
[CHECK] **Audit Trail** - All notifications logged with metadata  
[CHECK] **Debug Mode** - Test without sending real messages  
[CHECK] **Backward Compatible** - Existing SMS functions unchanged  
[CHECK] **HTML + Plain Text** - Email templates support both formats  
[CHECK] **Template System** - 8 notification types with placeholders  

---

## 🐛 Troubleshooting

### SMS not sending
- Check `SMS_DEBUG_MODE` is `false` in config/sms.php
- Verify `SEMAPHORE_API_KEY` is set and valid
- Check user has valid phone number with country code (+63...)
- Check error_log for cURL errors

### Email not sending
- Check `EMAIL_DEBUG_MODE` is `false` in config/email.php
- Verify Gmail credentials: `GMAIL_ADDRESS` and `GMAIL_APP_PASSWORD`
- Make sure Gmail app password is exactly 16 characters
- Verify email is in notification_preferences and email_verified=1
- Check error_log for PHPMailer errors

### No notifications being sent
- Check database tables exist: Run `config/create_email_tables.php`
- Verify user preferences are set with `get_notification_preferences` API
- Check if email_enabled and email_verified=1 when sending email
- Check user has valid phone number when sending SMS

---

## [PHONE] Support

For issues or questions:
1. Check [TESTING_GUIDE.md](TESTING_GUIDE.md) for detailed testing procedures
2. Review [INTEGRATION_EXAMPLES.php](INTEGRATION_EXAMPLES.php) for code examples
3. Check error logs: `php error_log` or Apache/IIS event logs
4. Verify Semaphore API key and Gmail app password are configured

---

**Implementation Date:** May 18, 2026  
**Status:** [CHECK] Complete and Ready for Testing
