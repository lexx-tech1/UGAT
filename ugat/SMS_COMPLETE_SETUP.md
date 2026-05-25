# [ROCKET] Complete SMS Setup Guide - Full Configuration & Testing

## Overview
This guide will walk you through every step to get the SMS notification system working perfectly, from getting an API key to testing actual SMS sends.

---

## [CHECK] Prerequisites

Before starting, you need:
- ✅ Access to XAMPP/PHP installation
- ✅ UGAT project running at `http://localhost/ugat`
- ✅ Database connection working
- ✅ Google Chrome or similar browser with developer tools (optional)
- ✅ A phone number to test with (Philippine mobile number preferred)

---

## [LIST] Complete Setup Checklist

- [ ] Step 1: Get Semaphore API Key (5 min)
- [ ] Step 2: Configure SMS System (2 min)
- [ ] Step 3: Create Database Tables (1 min)
- [ ] Step 4: Enable Debug Mode (1 min)
- [ ] Step 5: Test SMS System (5 min)
- [ ] Step 6: Verify in Database (2 min)
- [ ] Step 7: Disable Debug Mode (1 min)
- [ ] Step 8: Production Ready (DONE!)

---

## 🔑 STEP 1: Get Semaphore API Key

### 1.1 Create Free Semaphore Account

**Go to:** https://semaphore.co

1. Click **Sign Up** (top right)
2. Enter your email address
3. Create a password
4. Click **Create Account**

### 1.2 Verify Your Email

You'll receive an email from Semaphore:
1. Open your email inbox
2. Find email from `support@semaphore.co`
3. Click the **Verify Email** link
4. You're now logged into Semaphore!

### 1.3 Get Your API Key

Once logged in to Semaphore dashboard:

1. Look for **Settings** or **Account Settings** (top menu)
2. Find section labeled **API Key** or **Authentication**
3. You'll see something like:
   ```
   API Key: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
   ```
4. **Copy this entire string** - this is your API key

**Example API Key:**
```
b17f95c0dc9289c9d84a21788efe1d41
```

⚠️ **Important:** Keep this secret! Don't share it publicly.

### 1.4 Check Available Balance

In Semaphore dashboard:
- Look for **Wallet** or **Credits** section
- You should see free credits available
- This allows you to send test SMS

---

## 🛠️ STEP 2: Configure SMS System

### 2.1 Edit `config/sms.php`

**File location:** `c:\IT\XAMPP\htdocs\ugat\config\sms.php`

Open in VS Code:
1. Press `Ctrl+P` (Quick Open)
2. Type: `sms.php`
3. Press Enter
4. Find these lines (around line 15-16):

**BEFORE:**
```php
define('SEMAPHORE_API_KEY', 'your_semaphore_api_key_here');
define('SEMAPHORE_SENDER_NAME', 'UGAT');
```

**AFTER:** Replace with your actual API key:
```php
define('SEMAPHORE_API_KEY', 'b17f95c0dc9289c9d84a21788efe1d41');
define('SEMAPHORE_SENDER_NAME', 'UGAT');
```

✅ **Save file** (Ctrl+S)

### 2.2 Verify SMS Configuration

Scroll down in `config/sms.php` and verify you see:
```php
define('SMS_ENABLED', true);           // SMS is enabled
define('SMS_DEBUG_MODE', false);       // Will enable in Step 4
define('SMS_PROVIDER', 'semaphore');   // Uses Semaphore
```

✅ All set!

---

## 🗄️ STEP 3: Create Database Tables

### 3.1 Open Browser

Navigate to: **http://localhost/ugat/config/create_email_tables.php**

You should see:
```
✓ notification_preferences table created successfully
✓ email_logs table created successfully  
✓ trainee_email_log table created successfully
✓ email_verification_tokens table created successfully
Tables created: 4/4
```

✅ **All tables created!**

### 3.2 Verify Database Tables

Open phpMyAdmin:
1. Go to: **http://localhost/phpmyadmin**
2. Click on database: **ugat_db** (left sidebar)
3. You should see new tables:
   - `notification_preferences`
   - `email_logs`
   - `trainee_email_log`
   - `email_verification_tokens`
   - `sms_logs` (existing)

✅ **All tables visible!**

---

## 🔍 STEP 4: Enable Debug Mode (Testing)

### 4.1 Enable SMS Debug Mode

Edit `config/sms.php`:

Find (around line 18):
```php
define('SMS_DEBUG_MODE', false);
```

Change to:
```php
define('SMS_DEBUG_MODE', true);
```

This logs SMS to error_log instead of actually sending them (saves credits!).

✅ **Save file**

### 4.2 Verify Debug Setting

In same file, you should now see:
```php
define('SMS_ENABLED', true);
define('SMS_DEBUG_MODE', true);        // ✅ Debug mode ON
define('SMS_PROVIDER', 'semaphore');
```

---

## 🧪 STEP 5: Test SMS System

### Test Method 1: Quick PHP Test (Easiest)

**Create file:** `c:\IT\XAMPP\htdocs\ugat\test_sms_quick.php`

Paste this code:
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/sms_service.php';

echo "<h1>🧪 SMS Test</h1>";

// Test 1: Direct SMS Send
echo "<h2>Test 1: Send SMS</h2>";
$sms = getSmsService($conn);
$result = $sms->sendSms(
    '+639123456789',  // Test phone number
    'Hello UGAT! This is a test SMS.',
    ['test' => true, 'timestamp' => date('Y-m-d H:i:s')]
);

echo "<pre>";
var_dump($result);
echo "</pre>";

if ($result['success']) {
    echo "<p style='color:green;'><strong>✅ SMS sent successfully!</strong></p>";
    echo "<p>Message ID: " . $result['sms_id'] . "</p>";
} else {
    echo "<p style='color:red;'><strong>❌ SMS failed:</strong></p>";
    echo "<p>" . $result['message'] . "</p>";
}

// Test 2: Check debug log
echo "<h2>Test 2: Check Error Log</h2>";
echo "<p>Check PHP error log for debug output (if SMS_DEBUG_MODE is true)</p>";
echo "<p><strong>Windows:</strong> Look in XAMPP\\php\\logs\\php_error.log</p>";
echo "<p><strong>Linux:</strong> Check /var/log/php-errors.log</p>";

?>
```

**Visit:** http://localhost/ugat/test_sms_quick.php

**Expected Output:**
```
Test 1: Send SMS

array(3) {
  ["success"]=>
  bool(true)
  ["message"]=>
  string(21) "SMS sent successfully."
  ["sms_id"]=>
  string(15) "test_647d8c9f2"
}

✅ SMS sent successfully!
```

✅ **SMS test passed!**

---

### Test Method 2: Using Helper Functions

**Create file:** `c:\IT\XAMPP\htdocs\ugat\test_sms_helper.php`

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/sms_helpers.php';

echo "<h1>🧪 SMS Helper Functions Test</h1>";

// Test: Send Order Notification via SMS
echo "<h2>Test: Order Placed Notification</h2>";

// First, get a real user or create test data
$user_id = 1;  // Adjust based on your database

// Make sure user has phone number
$user_result = $conn->query("SELECT phone FROM users WHERE id = $user_id LIMIT 1");
$user = $user_result->fetch_assoc();

if (!$user['phone']) {
    echo "<p style='color:orange;'><strong>⚠️ User $user_id has no phone number</strong></p>";
    echo "<p>Updating user with test phone number...</p>";
    $conn->query("UPDATE users SET phone = '+639123456789' WHERE id = $user_id");
}

// Send notification
$result = sendOrderPlacedNotification($user_id, 'ORD-2026-TEST-001', '₱1,500');

echo "<pre>";
var_dump($result);
echo "</pre>";

if ($result['success']) {
    echo "<p style='color:green;'><strong>✅ Order notification sent!</strong></p>";
} else {
    echo "<p style='color:red;'><strong>❌ Failed:</strong> " . $result['message'] . "</p>";
}

?>
```

**Visit:** http://localhost/ugat/test_sms_helper.php

**Expected Output:**
```
Test: Order Placed Notification

array(3) {
  ["success"]=>
  bool(true)
  ["message"]=>
  string(21) "SMS sent successfully."
  ["sms_id"]=>
  string(15) "test_647d8c9f2"
}

✅ Order notification sent!
```

✅ **Helper function test passed!**

---

### Test Method 3: Using API Endpoint

**Open browser console (F12)** and run:

```javascript
// Test: Send notification via admin API
fetch('http://localhost/ugat/pages/admin/send_notification.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        recipient_type: 'single',
        recipient_id: 1,
        template: 'order_placed',
        replacements: {
            order_id: 'ORD-2026-TEST-001',
            total: '₱1,500'
        },
        force_method: 'sms'
    })
})
.then(r => r.json())
.then(data => console.log(JSON.stringify(data, null, 2)))
```

**Expected Output:**
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

✅ **API test passed!**

---

## 🗄️ STEP 6: Verify in Database

### 6.1 Check SMS Logs

Open phpMyAdmin and run this SQL query:

```sql
SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 5;
```

You should see:
```
| id | phone_number    | message                           | sms_id        | status | sent_at             |
|----|-----------------|-----------------------------------|---------------|--------|---------------------|
| 1  | +639123456789   | Hello UGAT! This is a test SMS.  | test_647d8c9f | sent   | 2026-05-19 10:30:00 |
```

✅ **SMS logged in database!**

### 6.2 Check Notification Preferences

```sql
SELECT * FROM notification_preferences WHERE user_id = 1;
```

### 6.3 Check Email Logs

```sql
SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 5;
```

---

## 📊 STEP 7: Check PHP Error Log (If Debug Mode)

### Windows XAMPP

1. Open File Explorer
2. Navigate to: `C:\xampp\php\logs\`
3. Open: `php_error.log`
4. You should see entries like:
```
[19-May-2026 10:30:00 UTC] SMS DEBUG: To=+639123456789, Message=Hello UGAT!...
```

### Linux

```bash
tail -f /var/log/php-errors.log
```

---

## ✅ STEP 8: Disable Debug Mode (Production)

Once everything works perfectly, disable debug mode:

### 8.1 Edit `config/sms.php`

Change:
```php
define('SMS_DEBUG_MODE', true);
```

To:
```php
define('SMS_DEBUG_MODE', false);
```

✅ **Save file**

Now SMS will actually send via Semaphore API instead of just logging!

---

## 🎯 Complete Verification Checklist

Run through this checklist to confirm everything works:

### Database Setup
- [ ] Navigate to `http://localhost/ugat/config/create_email_tables.php`
- [ ] See all 4 tables created successfully
- [ ] Verify in phpMyAdmin that tables exist

### Configuration
- [ ] API key entered in `config/sms.php`
- [ ] SMS_DEBUG_MODE = true (for testing)
- [ ] SMS_ENABLED = true
- [ ] SMS_PROVIDER = 'semaphore'

### Testing
- [ ] Run `test_sms_quick.php` - Shows success
- [ ] Run `test_sms_helper.php` - Shows success  
- [ ] Use API endpoint - Returns success
- [ ] Check database logs - SMS recorded

### Debug Logging
- [ ] Check PHP error log for debug entries
- [ ] See "SMS DEBUG" messages

### Final Production Setup
- [ ] Disable SMS_DEBUG_MODE (set to false)
- [ ] Delete test files (test_sms_quick.php, etc.)
- [ ] Verify Semaphore account has credits
- [ ] Test with real phone number (optional)

---

## 🐛 Troubleshooting

### Issue: "Semaphore API key not configured"

**Solution:**
1. Check `config/sms.php` line 15
2. Verify your API key is exactly as copied from Semaphore
3. Ensure no extra spaces at beginning/end
4. Make sure it's inside quotes:
   ```php
   define('SEMAPHORE_API_KEY', 'your_actual_key');  // ✅ Correct
   define('SEMAPHORE_API_KEY', your_actual_key);    // ❌ Wrong
   ```

### Issue: "SMS sent but not recorded in database"

**Solution:**
1. Check database connection in `config/db.php`
2. Verify sms_logs table exists
3. Check file permissions
4. Restart Apache/PHP

### Issue: "SMS_DEBUG_MODE shows no output"

**Solution:**
1. Check PHP error logging is enabled
2. Verify error_log path in php.ini
3. Make sure error_reporting = E_ALL
4. Restart Apache

### Issue: "Test file not found"

**Solution:**
- Make sure you created the file in correct location
- Correct path: `c:\IT\XAMPP\htdocs\ugat\test_sms_quick.php`
- Visit: `http://localhost/ugat/test_sms_quick.php`

### Issue: "Database tables not created"

**Solution:**
1. Check database user has CREATE TABLE permission
2. Verify db.php connection works
3. Check for SQL errors in browser output
4. Try creating table manually in phpMyAdmin

---

## 📞 Quick Reference Commands

### Test SMS Send (PHP CLI)
```bash
cd C:\xampp\htdocs\ugat
php -r "require 'config/db.php'; require 'config/sms_service.php'; $sms = getSmsService($conn); var_dump($sms->sendSms('+639123456789', 'Test'));"
```

### Check Error Log (Windows)
```bash
type C:\xampp\php\logs\php_error.log | findstr /C:"SMS DEBUG"
```

### Check Error Log (Linux)
```bash
tail -50 /var/log/php-errors.log | grep "SMS"
```

### Query SMS Logs (MySQL)
```sql
SELECT COUNT(*) as total_sms, MAX(sent_at) as latest 
FROM sms_logs;
```

---

## 🎓 Next Steps After Successful Setup

1. **Disable Debug Mode** - Stop logging, actually send SMS
2. **Test with Real Phone** - Send to your actual phone number
3. **Monitor Semaphore Balance** - Check credits used
4. **Integrate with Code** - Use in place_order.php, etc.
5. **Set Up Email** - Follow same steps for Gmail
6. **Configure User Preferences** - Let users choose SMS/Email/Both
7. **Monitor Logs** - Watch database for issues

---

## 📈 Performance Tips

- **Batch Sending:** Use `sendSmsForEventToMultiple()` for multiple users
- **Error Handling:** Check response arrays for errors
- **Logging:** Regularly clean old logs from sms_logs table
- **Credits:** Monitor Semaphore account balance
- **Rate Limiting:** Semaphore has rate limits - check documentation

---

## ✨ You're All Set!

Your SMS notification system is now:
- ✅ Configured with Semaphore API
- ✅ Database tables created
- ✅ Tested and verified
- ✅ Ready for production

**Next:** Set up Email notifications or start using SMS in your code!

---

**Last Updated:** May 19, 2026  
**Status:** Ready for Production
