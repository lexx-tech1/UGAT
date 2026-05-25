# [ROCKET] SMS Setup Checklist - Complete & Verify

## [LIST] Pre-Setup Checklist

Before you start, make sure you have:
- [ ] Access to XAMPP/htdocs/ugat folder
- [ ] VS Code or text editor open
- [ ] Browser (Chrome/Firefox)
- [ ] This checklist handy

---

## [CHECK] Step 1: Get Semaphore API Key (5 minutes)

### What You're Doing
Getting a unique key that lets your code send SMS via Semaphore

### Steps
1. [ ] Go to **https://semaphore.co** in browser
2. [ ] Click **Sign Up** (top right)
3. [ ] Enter your email and password
4. [ ] Click **Create Account**
5. [ ] Check your email for verification link
6. [ ] Click verification link
7. [ ] Log into Semaphore dashboard
8. [ ] Find **Settings** → **API Key**
9. [ ] **COPY** your API key (looks like: `abc123def456...`)

### Your API Key
```
🔑 Paste your key here: _________________________________
```

---

## [CHECK] Step 2: Configure SMS System (2 minutes)

### What You're Doing
Telling your UGAT system where to send SMS and with what API key

### Steps

1. [ ] Open VS Code
2. [ ] Press `Ctrl+P` → type `sms.php` → Press Enter
3. [ ] Find line 15-16 (around `SEMAPHORE_API_KEY`)
4. [ ] Replace this:
   ```php
   define('SEMAPHORE_API_KEY', 'your_semaphore_api_key_here');
   ```
   With this:
   ```php
   define('SEMAPHORE_API_KEY', 'your_actual_api_key_copied_above');
   ```
5. [ ] **Save** (Ctrl+S)

### Verification
- [ ] Line 15 shows your actual API key
- [ ] Line 16 shows `SEMAPHORE_SENDER_NAME', 'UGAT'`
- [ ] Line 18 shows `SMS_ENABLED', true`

---

## ✅ Step 3: Create Database Tables (1 minute)

### What You're Doing
Creating database tables to store SMS logs and preferences

### Steps

1. [ ] Open browser
2. [ ] Visit: **http://localhost/ugat/config/create_email_tables.php**
3. [ ] You should see:
   ```
   ✓ notification_preferences table created successfully
   ✓ email_logs table created successfully
   ✓ trainee_email_log table created successfully
   ✓ email_verification_tokens table created successfully
   Tables created: 4/4
   ```

### If you see errors:
- [ ] Check database connection in `config/db.php`
- [ ] Make sure MySQL/MariaDB is running
- [ ] Check user has CREATE TABLE permission

---

## ✅ Step 4: Enable Debug Mode (1 minute)

### What You're Doing
Setting debug mode so SMS tests log instead of actually sending (saves credits!)

### Steps

1. [ ] Open `config/sms.php` (Ctrl+P → type `sms.php`)
2. [ ] Find line 18: `define('SMS_DEBUG_MODE', false);`
3. [ ] Change `false` to `true`:
   ```php
   define('SMS_DEBUG_MODE', true);
   ```
4. [ ] **Save** (Ctrl+S)

### Verification
- [ ] Line 18 shows `SMS_DEBUG_MODE', true`
- [ ] SMS will now log to error_log instead of actually sending

---

## ✅ Step 5: Quick Test (Run 2 Tests)

### Test 1: Quick Test (Show status)

1. [ ] Open browser
2. [ ] Visit: **http://localhost/ugat/test_sms_quick.php**
3. [ ] You should see green checkmarks:
   - [ ] ✅ Database connected successfully
   - [ ] ✅ API Key configured
   - [ ] ✅ Semaphore provider configured
   - [ ] ✅ SMS sent successfully!

### Test 2: Helper Functions (Test all notifications)

1. [ ] Visit: **http://localhost/ugat/test_sms_helper.php**
2. [ ] You should see:
   - [ ] ✓ PASS - Order Placed Notification
   - [ ] ✓ PASS - Order Shipped Notification
   - [ ] ✓ PASS - Order Delivered Notification
   - [ ] ✓ PASS - Workshop Enrollment Notification
   - [ ] ✓ PASS - Workshop Reminder Notification
   - [ ] ✓ PASS - Certification Issued Notification
   - [ ] ✓ PASS - Payment Received Notification
   - [ ] All tests passed!

### If Tests Fail:
- [ ] Check API key in `config/sms.php`
- [ ] Check database tables created
- [ ] Check SMS_DEBUG_MODE is true
- [ ] Look at error messages

---

## ✅ Step 6: Verify in Database (2 minutes)

### What You're Doing
Making sure SMS is actually being logged to database

### Steps

1. [ ] Open phpMyAdmin: **http://localhost/phpmyadmin**
2. [ ] Click database: **ugat_db**
3. [ ] Look for table: **sms_logs**
4. [ ] Click on it
5. [ ] You should see records from your tests with:
   - [ ] Phone number
   - [ ] SMS message
   - [ ] Timestamp
   - [ ] Status = "sent"

### SQL Query to Check
```sql
SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 5;
```

---

## ✅ Step 7: Check Debug Log (Optional)

### What You're Doing
Verifying SMS was logged (if debug mode is on)

### Windows XAMPP:
1. [ ] Open: `C:\xampp\php\logs\php_error.log`
2. [ ] Look for lines with: `SMS DEBUG:`
3. [ ] You should see your test SMS messages

### Linux:
```bash
tail -f /var/log/php-errors.log | grep "SMS DEBUG"
```

---

## ✅ Step 8: Disable Debug Mode (When Ready)

### What You're Doing
Turning off debug mode so real SMS are actually sent

### Do This ONLY After Tests Pass:

1. [ ] Open `config/sms.php`
2. [ ] Find line 18: `define('SMS_DEBUG_MODE', true);`
3. [ ] Change `true` to `false`:
   ```php
   define('SMS_DEBUG_MODE', false);
   ```
4. [ ] **Save** (Ctrl+S)

⚠️ **WARNING:** After this, SMS will actually be sent and use Semaphore credits!

---

## ✅ Final Verification Checklist

- [ ] API key is configured in `config/sms.php`
- [ ] Database tables created
- [ ] test_sms_quick.php shows all green checkmarks
- [ ] test_sms_helper.php shows all PASS
- [ ] SMS logs appear in phpMyAdmin
- [ ] Debug mode is enabled (for testing)
- [ ] Ready to disable debug and go live

---

## 🎯 You're Ready to Use SMS!

Now you can use these functions in your code:

### Send SMS Only
```php
sendOrderPlacedNotification($user_id, $order_id, $total);
sendWorkshopEnrollmentNotification($user_id, $workshop_name, $date, $link);
sendCertificationIssuedNotification($user_id, $workshop_name, $cert_link);
```

### Send SMS + Email (Respects user preferences)
```php
sendOrderPlacedNotificationDual($user_id, $order_id, $total);
sendWorkshopEnrollmentNotificationDual($user_id, $workshop_name, $date, $time, $link);
sendCertificationIssuedNotificationDual($user_id, $workshop_name, $cert_link);
```

---

## 🐛 Troubleshooting Quick Links

| Problem | Solution |
|---------|----------|
| "API key not configured" | Check `config/sms.php` line 15 has your real API key |
| "Tables not created" | Run `config/create_email_tables.php` in browser |
| "No records in sms_logs" | Make sure SMS_DEBUG_MODE = true to test |
| "SMS not sending" | Disable DEBUG_MODE = false to actually send |
| "Semaphore error" | Check API key is correct, has no extra spaces |
| "Database connection failed" | Check `config/db.php` has correct credentials |

---

## 📚 Full Documentation

For more details, see:
- **SMS_COMPLETE_SETUP.md** - Detailed setup guide
- **QUICK_REFERENCE.php** - Code examples
- **TESTING_GUIDE.md** - Testing procedures
- **IMPLEMENTATION_COMPLETE.md** - Full technical reference

---

## ✨ Success Indicators

You're done when you see:
- ✅ Both test files show all PASS/OK
- ✅ SMS logs appear in database
- ✅ No errors in browser
- ✅ Debug log shows SMS messages (if debug mode on)
- ✅ Can use SMS functions in your code

---

**Created:** May 18, 2026  
**Status:** Ready to Use
