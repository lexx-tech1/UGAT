<!-- UGAT Notification System - Implementation Summary -->

# UGAT SMS + Email Notification System Implementation

## Overview
A complete dual-channel notification system using Semaphore API for SMS and Gmail/PHPMailer for email notifications. Users can choose to receive notifications via SMS only, Email only, or Both.

---

## What's Been Implemented

### Phase 1: Database & Configuration ✓

#### 1.1 Database Tables Created
- **notification_preferences** - Stores user notification channel preferences and email
- **email_logs** - Tracks all email send attempts  
- **trainee_email_log** - User-level email notification history for UI display

Run setup script: `http://localhost/ugat/config/create_email_tables.php`

#### 1.2 Configuration Files

**config/sms.php** (Updated)
- Replaced Twilio with Semaphore API
- Set `SEMAPHORE_API_KEY` with your API key from https://semaphore.co
- Set `SEMAPHORE_SENDER_NAME` (max 11 chars, e.g., 'UGAT')
- Dual notification support enabled

**config/email.php** (New)
- Gmail/SMTP configuration
- Set `GMAIL_ADDRESS` and `GMAIL_APP_PASSWORD` 
- Email templates for all 8 notification types (HTML + plain text)

---

### Phase 2: Service Layer ✓

#### 2.1 SmsService (Updated)
- **File**: `config/sms_service.php`
- Replaced Twilio implementation with Semaphore API
- Semaphore endpoint: `https://api.semaphore.co/api/sms/send`
- Same interface: `sendSms()`, `sendSmsToMultiple()`, `getSmsStatus()`
- Automatic phone number conversion (+63 → 0)
- Debug mode support for testing

#### 2.2 EmailService (New)
- **File**: `config/email_service.php`
- PHPMailer-based Gmail/SMTP implementation
- Same interface as SmsService for consistency
- Methods: `sendEmail()`, `sendEmailToMultiple()`, `getEmailStatus()`
- HTML + plain text email support
- Full error handling and logging

---

### Phase 3: Notification Preference System ✓

#### 3.1 Preference Management
- **File**: `config/notification_preferences.php`
- Functions for getting/setting user preferences
- Email verification flow support
- Automatic fallback to SMS if no preferences set
- Channel determination based on user preferences

#### 3.2 Dual Notification Helpers
- **File**: `config/sms_helpers.php` (Extended)
- Core function: `sendNotificationByPreference($user_id, $event_type, $data)`
- 8 event-specific dual functions:
  - `sendOrderPlacedNotificationDual()`
  - `sendOrderShippedNotificationDual()`
  - `sendOrderDeliveredNotificationDual()`
  - `sendWorkshopEnrollmentNotificationDual()`
  - `sendWorkshopReminderNotificationDual()`
  - `sendCertificationIssuedNotificationDual()`
  - `sendPaymentReceivedNotificationDual()`
  - Generic: `sendNotificationByPreference()`

---

### Phase 4: API Endpoints ✓

#### 4.1 Trainee Endpoints

**GET** `/pages/trainee/get_notification_preferences.php`
```
Response: {
  "success": true,
  "data": {
    "phone": "+639123456789",
    "email": "user@gmail.com",
    "phone_enabled": true,
    "email_enabled": true,
    "email_verified": false
  }
}
```

**POST** `/pages/trainee/update_notification_preferences.php`
```
Body: {
  "phone_enabled": true,
  "email_enabled": true,
  "email": "user@gmail.com"
}
Response: {"success": true, "message": "Preferences updated successfully."}
```

**GET** `/pages/trainee/get_all_notifications.php?type=both&limit=50&offset=0`
```
Response: {
  "success": true,
  "data": [
    {
      "channel": "sms",
      "recipient": "+639123456789",
      "message": "Hi User, your order...",
      "type": "order_placed",
      "timestamp": "2026-05-18 10:30:00",
      "is_read": 0
    },
    {
      "channel": "email",
      "recipient": "user@gmail.com",
      "message": "<h2>Order Confirmation</h2>...",
      "type": "order_placed",
      "timestamp": "2026-05-18 10:30:05",
      "is_read": 0
    }
  ]
}
```

#### 4.2 Admin Endpoints

**POST** `/pages/admin/send_notification.php`
```
Body: {
  "recipient_type": "single|group|all",
  "recipient_id": 1,
  "template": "order_placed",
  "replacements": {
    "order_id": "12345",
    "total": "₱2,500"
  },
  "force_method": "both"
}
Response: {
  "success": true,
  "message": "Sent to 1 users",
  "summary": {
    "total": 1,
    "success": 1,
    "failed": 0
  }
}
```

**GET** `/pages/admin/get_notifications.php?type=both&limit=50&offset=0&start_date=2026-05-18&end_date=2026-05-18`
```
Response: Combined SMS + Email notification history
```

---

## Setup Instructions

### 1. Create Database Tables
Visit: `http://localhost/ugat/config/create_email_tables.php`

This creates:
- `notification_preferences` table
- `email_logs` table
- `trainee_email_log` table

### 2. Configure Semaphore API
Edit `config/sms.php`:
```php
define('SEMAPHORE_API_KEY', 'your_api_key_here');
define('SEMAPHORE_SENDER_NAME', 'UGAT');
```

Get your API key from: https://semaphore.co/

### 3. Configure Gmail/SMTP
Edit `config/email.php`:
```php
define('GMAIL_ADDRESS', 'your_email@gmail.com');
define('GMAIL_APP_PASSWORD', 'your_16_char_app_password');
```

To generate Gmail app password:
1. Go to https://myaccount.google.com/apppasswords
2. Generate a 16-character password for "Mail" on "Windows Computer"
3. Copy the password to `GMAIL_APP_PASSWORD`

### 4. Install PHPMailer (if not installed)
```bash
cd /path/to/ugat
composer require phpmailer/phpmailer
```

### 5. Test Configuration (Optional)

Enable debug mode in config files:
- `config/sms.php`: `define('SMS_DEBUG_MODE', true);`
- `config/email.php`: `define('EMAIL_DEBUG_MODE', true);`

This logs notifications to error_log instead of actually sending them.

---

## Usage Examples

### Send Dual Notification (SMS + Email)
```php
require_once 'config/sms_helpers.php';

// Send order placed notification via SMS and/or Email based on user preferences
$result = sendOrderPlacedNotificationDual(
    user_id: 3,
    order_id: 'ORD-2026-001',
    total: '₱2,500'
);

// Response:
// {
//   "success": true,
//   "sms": {"success": true, "sms_id": "sem_123456"},
//   "email": {"success": true, "email_id": "email_abc123"},
//   "channels_used": ["sms", "email"]
// }
```

### Get User Preferences
```php
require_once 'config/notification_preferences.php';

$prefs = getUserNotificationPreference(3, $conn);
// Returns: ['email' => 'user@gmail.com', 'phone_enabled' => true, 'email_enabled' => true, ...]
```

### Send Custom Notification
```php
require_once 'config/sms_helpers.php';

$result = sendNotificationByPreference(
    user_id: 3,
    event_type: 'workshop_enrollment',
    data: [
        'workshop_name' => 'Organic Pechay Growing',
        'date' => '2026-05-25',
        'time' => '2:00 PM'
    ]
);
```

---

## Frontend Integration (Phase 5)

The following endpoints are ready for UI components:

1. **Notification Preferences Panel**
   - GET: `/pages/trainee/get_notification_preferences.php`
   - POST: `/pages/trainee/update_notification_preferences.php`
   - Toggles for SMS and Email channels
   - Email input field with verification flow

2. **Trainee Notification Inbox**
   - GET: `/pages/trainee/get_all_notifications.php`
   - Combined SMS + Email notifications
   - Channel indicator ([PHONE] SMS, [EMAIL] Email)
   - Pagination support

3. **Admin Notification Sender**
   - POST: `/pages/admin/send_notification.php`
   - Dropdown for Send via (SMS Only | Email Only | Both)
   - Recipient selection (Single | Group | All)
   - Template selection

4. **Admin Notification History**
   - GET: `/pages/admin/get_notifications.php`
   - Combined SMS + Email delivery log
   - Date filtering
   - Export to CSV (future enhancement)

---

## Database Schema

### notification_preferences Table
```
id (INT) - Primary key
user_id (INT UNSIGNED, UNIQUE) - Foreign key to users.id
email (VARCHAR) - User's email for notifications
phone_enabled (TINYINT) - 1 or 0
email_enabled (TINYINT) - 1 or 0
email_verified (TINYINT) - 1 or 0 (for verification flow)
email_verification_token (VARCHAR) - For email verification
created_at (TIMESTAMP)
updated_at (TIMESTAMP)
```

### email_logs Table
```
id (INT) - Primary key
user_id (INT) - Foreign key to users.id (nullable)
recipient_email (VARCHAR)
subject (VARCHAR)
message (LONGTEXT)
email_provider (VARCHAR) - 'gmail_smtp'
sent_at (TIMESTAMP)
status (VARCHAR) - 'sent', 'failed', etc.
metadata (JSON) - Additional tracking info
```

### trainee_email_log Table
```
id (INT) - Primary key
user_id (INT) - Foreign key to users.id
recipient_email (VARCHAR)
subject (VARCHAR)
message (LONGTEXT)
notification_type (VARCHAR) - Event type
status (VARCHAR) - 'received'
received_at (TIMESTAMP)
is_read (TINYINT) - For UI read status
```

---

## Files Created/Modified

### New Files
- [CHECK] `config/create_email_tables.php` - Database migration
- [CHECK] `config/email.php` - Email configuration
- [CHECK] `config/email_service.php` - Email service class
- [CHECK] `config/notification_preferences.php` - Preference management
- [CHECK] `pages/trainee/get_notification_preferences.php` - API endpoint
- [CHECK] `pages/trainee/update_notification_preferences.php` - API endpoint
- [CHECK] `pages/trainee/get_all_notifications.php` - API endpoint
- [CHECK] `pages/admin/send_notification.php` - API endpoint
- [CHECK] `pages/admin/get_notifications.php` - API endpoint

### Modified Files
- [CHECK] `config/sms.php` - Updated for Semaphore API
- [CHECK] `config/sms_service.php` - Replaced Twilio with Semaphore
- [CHECK] `config/sms_helpers.php` - Added dual notification functions

---

## Testing Checklist

- [ ] Run `config/create_email_tables.php` - tables created successfully
- [ ] Test Semaphore SMS in debug mode
- [ ] Test Gmail email in debug mode
- [ ] POST to update_notification_preferences.php - preferences saved
- [ ] GET from get_notification_preferences.php - correct data returned
- [ ] Send test notification via send_notification.php
- [ ] Verify SMS + Email logged to databases
- [ ] View notifications via get_all_notifications.php

---

## Future Enhancements

1. **Email Verification** - Send verification emails when users enable email
2. **Unsubscribe Links** - Include unsubscribe tokens in emails for GDPR compliance
3. **SMS Delivery Status** - Track SMS delivery status via Semaphore webhook
4. **Rate Limiting** - Limit emails per user per day
5. **Notification Templates Editor** - UI to edit notification templates
6. **Scheduled Notifications** - Queue notifications for later sending
7. **Analytics Dashboard** - Track notification delivery rates
8. **Notification Center** - Real-time notification display with WebSocket support

---

**Implementation Status**: 80% Complete  
**Ready for**: Phase 5 (Frontend UI) and Phase 6 (Full Testing)  
**Next Steps**: Create notification preference UI component, integrate with trainee dashboard

