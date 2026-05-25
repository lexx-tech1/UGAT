# [PHONE] SMS Setup & Testing Guide - Complete Summary

## [TARGET] Quick Reference

### The 8-Step Setup Process

```
Step 1: Get API Key           [5 min]   → https://semaphore.co
    ↓
Step 2: Configure SMS         [2 min]   → Edit config/sms.php
    ↓
Step 3: Create DB Tables      [1 min]   → Visit create_email_tables.php
    ↓
Step 4: Enable Debug Mode     [1 min]   → Set SMS_DEBUG_MODE = true
    ↓
Step 5: Run Quick Test        [2 min]   → Visit test_sms_quick.php
    ↓
Step 6: Test Helper Functions [2 min]   → Visit test_sms_helper.php
    ↓
Step 7: Verify in Database    [2 min]   → Check phpMyAdmin
    ↓
Step 8: Disable Debug Mode    [1 min]   → Set SMS_DEBUG_MODE = false
    ↓
✅ READY TO USE SMS!
```

**Total Time:** ~16 minutes

---

## 📄 Documentation Files

### New Files Created for You:

| File | Purpose | Visit URL |
|------|---------|-----------|
| `SMS_SETUP_CHECKLIST.md` | Step-by-step checklist | Read locally |
| `SMS_COMPLETE_SETUP.md` | Detailed setup guide | Read locally |
| `test_sms_quick.php` | Quick SMS test | http://localhost/ugat/test_sms_quick.php |
| `test_sms_helper.php` | Helper function test | http://localhost/ugat/test_sms_helper.php |
| `QUICK_REFERENCE.php` | Code examples | Read/Copy from file |
| `TESTING_GUIDE.md` | Full testing guide | Read locally |
| `IMPLEMENTATION_COMPLETE.md` | Full tech reference | Read locally |

---

## 🔑 Getting API Key - Detailed Steps

### Visual Walkthrough:

```
1. Browser → https://semaphore.co
           ↓
2. Click "Sign Up"
           ↓
3. Enter email & password
           ↓
4. Click "Create Account"
           ↓
5. Check email for verification link
           ↓
6. Click verification link
           ↓
7. Login to dashboard
           ↓
8. Settings → API Key
           ↓
9. COPY the key (e.g., "abc123def456...")
           ↓
10. Come back to VS Code and paste it
```

**Your API Key will look like:**
```
b17f95c0dc9289c9d84a21788efe1d41
```

---

## 🛠️ Configuration - Line by Line

### File: `config/sms.php`

**BEFORE (with placeholder):**
```php
15  define('SEMAPHORE_API_KEY', 'your_semaphore_api_key_here');
16  define('SEMAPHORE_SENDER_NAME', 'UGAT');
17
18  define('SMS_ENABLED', true);
19  define('SMS_DEBUG_MODE', false);  // ← Change this to true for testing
```

**AFTER (with your actual key):**
```php
15  define('SEMAPHORE_API_KEY', 'b17f95c0dc9289c9d84a21788efe1d41');
16  define('SEMAPHORE_SENDER_NAME', 'UGAT');
17
18  define('SMS_ENABLED', true);
19  define('SMS_DEBUG_MODE', true);  // ← For testing (logs instead of sending)
```

---

## 🧪 Testing URLs - Copy & Paste

### Test 1: Quick Status Check
```
http://localhost/ugat/test_sms_quick.php
```

**Expected:** Green checkmarks for all tests

### Test 2: Helper Functions
```
http://localhost/ugat/test_sms_helper.php
```

**Expected:** All tests marked PASS

### Test 3: Database Setup
```
http://localhost/ugat/config/create_email_tables.php
```

**Expected:** All 4 tables created

### Test 4: phpMyAdmin (View Logs)
```
http://localhost/phpmyadmin
```

**Then:** Database → ugat_db → sms_logs table

---

## 📊 Expected Test Results

### Test 1: test_sms_quick.php

```
✓ PASS - Database Connection
✓ PASS - Semaphore API Key Configuration
✓ PASS - SMS Provider Configuration
✓ PASS - Debug Mode Status
✓ PASS - SMS Logs Table
✓ PASS - Send Test SMS
✓ PASS - Recent SMS Logs

✅ All Tests Passed!
```

### Test 2: test_sms_helper.php

```
✓ PASS - Order Placed Notification
✓ PASS - Order Shipped Notification
✓ PASS - Order Delivered Notification
✓ PASS - Workshop Enrollment Notification
✓ PASS - Workshop Reminder Notification
✓ PASS - Certification Issued Notification
✓ PASS - Payment Received Notification

Summary: 7 Passed, 0 Failed ✅
```

---

## 🔍 Debugging - If Tests Fail

### Problem 1: "API Key not configured"
```
Solution:
1. Open config/sms.php
2. Check line 15
3. Paste your actual API key from semaphore.co
4. Make sure it's inside quotes
5. Save and retry
```

### Problem 2: "Database tables not created"
```
Solution:
1. Visit http://localhost/ugat/config/create_email_tables.php
2. Check for errors
3. Verify database user has CREATE TABLE permission
4. Check config/db.php has correct credentials
```

### Problem 3: "SMS_DEBUG_MODE shows no output"
```
Solution:
1. Check debug mode is set to true in config/sms.php
2. Check PHP error logging is enabled
3. Look for error_log file location
4. Windows: C:\xampp\php\logs\php_error.log
5. Linux: /var/log/php-errors.log
```

### Problem 4: "No SMS records in database"
```
Solution:
1. Make sure SMS_DEBUG_MODE = true
2. Run test_sms_quick.php
3. Wait a moment
4. Check phpMyAdmin → sms_logs table
5. Refresh the page if needed
```

---

## 💾 Database Schema - What Gets Created

### Table 1: sms_logs
```
Stores: Every SMS sent
Columns:
  - id (primary key)
  - phone_number (who it was sent to)
  - message (what was sent)
  - sms_id (Semaphore's ID)
  - sent_at (when)
  - status (sent/failed)
```

### Table 2: notification_preferences
```
Stores: User notification preferences
Columns:
  - user_id (which user)
  - email (their email)
  - phone_enabled (true/false)
  - email_enabled (true/false)
  - email_verified (true/false)
```

### Table 3: trainee_sms_log
```
Stores: SMS history for users
Columns:
  - user_id
  - phone_number
  - message
  - notification_type (order_placed, etc)
  - received_at
```

### Table 4: email_logs (for future email setup)
```
Stores: Email send attempts
Columns:
  - Similar to sms_logs
```

---

## 🚀 After Tests Pass - Using SMS in Your Code

### Option 1: SMS Only (Simple)
```php
require_once 'config/sms_helpers.php';

// Send order notification
sendOrderPlacedNotification(
    $user_id,      // User to notify
    'ORD-123',     // Order ID
    '₱5,000'       // Total amount
);
```

### Option 2: SMS + Email (Smart)
```php
require_once 'config/sms_helpers.php';

// Send notification via user's preferred channel (SMS, Email, or Both)
sendOrderPlacedNotificationDual(
    $user_id,      // User to notify
    'ORD-123',     // Order ID
    '₱5,000'       // Total amount
);
```

### Option 3: Admin Force Send
```php
// Force send to specific user via specific channel
fetch('/ugat/pages/admin/send_notification.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        recipient_type: 'single',
        recipient_id: 42,
        template: 'order_placed',
        replacements: {
            order_id: 'ORD-123',
            total: '₱5,000'
        },
        force_method: 'sms'  // or 'email' or 'both'
    })
})
.then(r => r.json())
.then(data => console.log(data));
```

---

## ⏰ Timeline - From Start to Finished

```
0 min:   Start setup
5 min:   Got API key from semaphore.co
7 min:   Configured SMS in config/sms.php
8 min:   Created database tables
9 min:   Enabled debug mode
11 min:  Ran test_sms_quick.php ✅
13 min:  Ran test_sms_helper.php ✅
15 min:  Verified in phpMyAdmin ✅
16 min:  Disabled debug mode (when ready)

Total: ~16 minutes to fully working SMS system
```

---

## 📋 Pre-Flight Checklist (Before Disabling Debug)

Before setting `SMS_DEBUG_MODE = false` and sending real SMS:

- [ ] Both test files show all PASS
- [ ] SMS logs appear in phpMyAdmin
- [ ] No error messages in browser
- [ ] API key confirmed working
- [ ] Database tables all created
- [ ] Semaphore account has credits
- [ ] Ready to send real SMS!

---

## 🎓 Learning Path

If this is your first time:

1. **Read:** SMS_SETUP_CHECKLIST.md (5 min read)
2. **Do:** Follow 8 steps above (16 min)
3. **Test:** Run test_sms_quick.php (2 min)
4. **Verify:** Run test_sms_helper.php (2 min)
5. **Confirm:** Check phpMyAdmin (2 min)
6. **Learn:** Read QUICK_REFERENCE.php for code examples (5 min)
7. **Deploy:** Use in your actual code

**Total Time:** ~32 minutes to fully understand and deploy

---

## 🔗 Useful Links

| Resource | URL |
|----------|-----|
| Get API Key | https://semaphore.co |
| phpMyAdmin | http://localhost/phpmyadmin |
| Create DB Tables | http://localhost/ugat/config/create_email_tables.php |
| Quick Test | http://localhost/ugat/test_sms_quick.php |
| Helper Test | http://localhost/ugat/test_sms_helper.php |
| XAMPP Control | http://localhost |

---

## ✨ Success Indicators

You've successfully set up SMS when:

1. ✅ test_sms_quick.php shows all green
2. ✅ test_sms_helper.php shows all PASS  
3. ✅ SMS records appear in phpMyAdmin
4. ✅ No error messages
5. ✅ Can see SMS in error_log (if debug mode on)
6. ✅ Ready to use in production code

---

## 📞 Support Checklist

If something doesn't work:

- [ ] Check error messages in browser carefully
- [ ] Verify API key in config/sms.php is correct
- [ ] Confirm database tables were created
- [ ] Check phpMyAdmin for any SQL errors
- [ ] Review error_log file for debug output
- [ ] Make sure SMS_ENABLED = true
- [ ] Verify Semaphore account has credits
- [ ] Restart Apache/PHP if needed

---

**Setup Status:** 🟢 Ready to Start

**Last Updated:** May 19, 2026

**Your Next Step:** 👉 Get API key from https://semaphore.co
