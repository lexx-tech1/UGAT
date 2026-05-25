# Notification System Testing Guide

## Quick Start Testing

### Step 1: Create Database Tables
Visit in browser: **http://localhost/ugat/config/create_email_tables.php**

Expected output:
```
✓ notification_preferences table created successfully
✓ email_logs table created successfully
✓ trainee_email_log table created successfully
```

---

## Step 2: Enable Debug Mode (Recommended for Testing)

Edit **config/sms.php**:
```php
define('SMS_DEBUG_MODE', true);  // Change to true
```

Edit **config/email.php**:
```php
define('EMAIL_DEBUG_MODE', true);  // Change to true
```

This will log notifications to error_log instead of actually sending them.

---

## Step 3: Configure Credentials

### Configure Semaphore API
Edit **config/sms.php**:
```php
define('SEMAPHORE_API_KEY', 'b17f95c0dc9289c9d84a21788efe1d41');
define('SEMAPHORE_SENDER_NAME', 'UGAT');
```

### Configure Gmail
Edit **config/email.php**:
```php
define('GMAIL_ADDRESS', 'your_gmail@gmail.com');
define('GMAIL_APP_PASSWORD', 'your_16_char_app_password');
```

---

## Step 4: Test API Endpoints

### Test 1: Get User Notification Preferences
```bash
# Assuming you're logged in as user_id 3
curl -X GET "http://localhost/ugat/pages/trainee/get_notification_preferences.php"
```

Expected response:
```json
{
  "success": true,
  "data": {
    "phone": "+639123456789",
    "email": "",
    "phone_enabled": true,
    "email_enabled": false,
    "email_verified": false
  }
}
```

### Test 2: Update User Preferences
```bash
curl -X POST "http://localhost/ugat/pages/trainee/update_notification_preferences.php" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_enabled": true,
    "email_enabled": true,
    "email": "testuser@gmail.com"
  }'
```

Expected response:
```json
{
  "success": true,
  "message": "Preferences updated successfully."
}
```

### Test 3: Send Notification from Admin
```bash
curl -X POST "http://localhost/ugat/pages/admin/send_notification.php" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_type": "single",
    "recipient_id": 3,
    "template": "order_placed",
    "replacements": {
      "order_id": "ORD-2026-001",
      "total": "₱2,500"
    }
  }'
```

Expected response (in debug mode):
```json
{
  "success": true,
  "message": "Sent to 1 users",
  "summary": {
    "total": 1,
    "success": 1,
    "failed": 0
  }
}
```

### Test 4: Get All Notifications (Trainee)
```bash
curl -X GET "http://localhost/ugat/pages/trainee/get_all_notifications.php?type=both&limit=10"
```

Expected response:
```json
{
  "success": true,
  "data": [
    {
      "channel": "sms",
      "recipient": "+639123456789",
      "message": "Hi Test User, your order #ORD-2026-001 has been placed. Total: ₱2,500.",
      "type": "order_placed",
      "timestamp": "2026-05-18 15:30:00",
      "is_read": 0
    },
    {
      "channel": "email",
      "recipient": "testuser@gmail.com",
      "message": "<h2>Order Confirmation</h2>...",
      "type": "order_placed",
      "timestamp": "2026-05-18 15:30:05",
      "is_read": 0
    }
  ]
}
```

### Test 5: Get Admin Notification History
```bash
curl -X GET "http://localhost/ugat/pages/admin/get_notifications.php?type=both&limit=50"
```

---

## Step 5: Test Using PHP Code

### Test Direct SMS Send
Create file **test_sms.php** in project root:
```php
<?php
require_once 'config/db.php';
require_once 'config/sms_service.php';

$sms = getSmsService($conn);
$result = $sms->sendSms(
    '+639123456789',
    'Hello! This is a test SMS from UGAT.',
    ['test' => true]
);

var_dump($result);
// Check error_log if SMS_DEBUG_MODE is true
?>
```

### Test Direct Email Send
Create file **test_email.php** in project root:
```php
<?php
require_once 'config/db.php';
require_once 'config/email_service.php';

$email = getEmailService($conn);
$result = $email->sendEmail(
    'testuser@gmail.com',
    'Test Email from UGAT',
    '<h2>Hello!</h2><p>This is a test email from UGAT.</p>',
    ['test' => true]
);

var_dump($result);
// Check error_log if EMAIL_DEBUG_MODE is true
?>
```

### Test Dual Notification
Create file **test_dual.php** in project root:
```php
<?php
require_once 'config/db.php';
require_once 'config/sms_helpers.php';

// Set user preferences first
setUserNotificationPreference(3, 'testuser@gmail.com', true, true, $conn);

// Send order placed notification (SMS + Email)
$result = sendOrderPlacedNotificationDual(3, 'ORD-2026-001', '₱2,500');

var_dump($result);
// Should show success for both SMS and email
?>
```

---

## Step 6: Check Database

### View Notification Preferences
```sql
SELECT * FROM notification_preferences WHERE user_id = 3;
```

### View SMS Logs
```sql
SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10;
```

### View Email Logs
```sql
SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 10;
```

### View Trainee SMS Logs
```sql
SELECT * FROM trainee_sms_log WHERE user_id = 3 ORDER BY received_at DESC;
```

### View Trainee Email Logs
```sql
SELECT * FROM trainee_email_log WHERE user_id = 3 ORDER BY received_at DESC;
```

---

## Troubleshooting

### Issue: "PHPMailer not installed"
**Solution**: Install via Composer
```bash
cd /path/to/ugat
composer require phpmailer/phpmailer
```

### Issue: "Semaphore API error"
**Solution**: 
1. Check API key in `config/sms.php`
2. Verify phone number format (should convert +63 → 0 automatically)
3. Check error_log for detailed error messages

### Issue: "Gmail authentication failed"
**Solution**:
1. Use App Password, not regular Gmail password
2. Generate new app password at: https://myaccount.google.com/apppasswords
3. Verify GMAIL_ADDRESS matches the Gmail account

### Issue: No tables created
**Solution**:
1. Check database connection in `config/db.php`
2. Verify user has CREATE TABLE permissions
3. Check browser output for error messages

---

## Debug Logging

### View PHP Error Log
```bash
# Windows
type %AppData%\PHP\error_log

# Linux/Mac
tail -f /var/log/php-errors.log
```

### Enable PHP Error Logging
Add to **php.ini**:
```ini
error_log = /var/log/php-errors.log
log_errors = On
error_reporting = E_ALL
```

---

## Performance Notes

- Sending to 100+ users: Use `sendSmsForEventToMultiple()` for batch processing
- Email sending: Consider async job queue for large volumes (future enhancement)
- Database indices created on: user_id, sent_at, notification_type, status

---

## Next Steps

After successful testing:
1. Disable debug mode in config files
2. Set production Semaphore API key
3. Test with real phone numbers and emails
4. Create frontend UI components (Phase 5)
5. Monitor error logs for any issues

---

## Contact & Support

For issues or questions:
1. Check error_log files
2. Review API responses from endpoints
3. Verify database contents
4. Check configuration files for missing values

