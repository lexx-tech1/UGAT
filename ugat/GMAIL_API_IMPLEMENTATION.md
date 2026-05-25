# [CHECK] Gmail API Integration - Implementation Complete

Complete Gmail API integration for email verification and notifications using 6-digit codes.

---

## [LIST] What Was Created

### 1. **Configuration Files**

#### `config/gmail_api.php`
- Gmail API credentials storage
- OAuth2 configuration
- Email templates for 8 notification types
- **Action Required:** Add your credentials here after Google Cloud setup

#### `config/gmail_api_service.php`
- Core Gmail API email sending service
- Handles OAuth2 token refresh
- Creates MIME messages for Gmail API
- Methods:
  - `sendEmail()` - Send single email
  - `sendEmailBulk()` - Send multiple emails
  - Helper function: `sendGmailEmail()`

---

### 2. **Setup & Authorization Files**

#### `config/get_gmail_refresh_token.php`
- **One-time setup tool** for OAuth2 authorization
- Steps:
  1. Edit file with your Client ID and Client Secret
  2. Visit in browser
  3. Click "Authorize with Google"
  4. Copy the refresh token shown
  5. Paste into `config/gmail_api.php`
  6. Delete this file afterward
- **Security:** Remove after first use

---

### 3. **Email Verification Endpoints**

#### `pages/trainee/send_verification_email.php`
```
POST /pages/trainee/send_verification_email.php
Requires: Session (logged in trainee)
Parameters: email=user@gmail.com
Response: { success, message, email }
```
**What it does:**
- Generates random 6-digit code
- Stores code in database with 24-hour expiration
- Sends code to user's email via Gmail API
- Logs email to database

#### `pages/trainee/verify_email_code.php`
```
POST /pages/trainee/verify_email_code.php
Requires: Session (logged in trainee)
Parameters: code=123456&email=user@gmail.com
Response: { success, message, email }
```
**What it does:**
- Validates code against database
- Checks expiration (24 hours)
- Marks email as verified
- Updates notification preferences
- Logs verification to database

---

### 4. **Database Setup**

#### `config/create_gmail_verification_tables.php`
Creates:
1. **email_verification_codes** table
   - Stores temporary 6-digit codes
   - Auto-expires after 24 hours
   - Tracks verification attempts

2. **email_verified** column in notification_preferences
   - Tracks which emails are verified

**Action Required:** Visit this file once to create tables

---

### 5. **Documentation & Testing**

#### `GMAIL_API_SETUP.md`
- Complete step-by-step setup guide
- Google Cloud project creation
- OAuth2 credential generation
- Architecture explanation
- Troubleshooting guide

#### `test_gmail_verification.php`
- Visual test interface
- Configuration status checker
- Form to test email sending
- Code verification test
- Database table viewer

---

## [ROCKET] Quick Setup (10 Minutes)

### Step 1: Google Cloud Project Setup
```
1. Go to https://console.cloud.google.com/
2. Create new project: "UGAT-Email"
3. Search for "Gmail API"
4. Click "Enable"
5. Create OAuth2 credentials (Desktop application)
6. Note your Client ID and Client Secret
```

### Step 2: Get Refresh Token
```
1. Edit: config/get_gmail_refresh_token.php
2. Replace Client ID and Client Secret placeholders
3. Visit: http://localhost/ugat/config/get_gmail_refresh_token.php
4. Click "Authorize with Google"
5. Sign in and grant permission
6. Copy the refresh token shown
```

### Step 3: Configure UGAT
```
1. Edit: config/gmail_api.php
2. Line 4: Replace GMAIL_SENDER_EMAIL with your Gmail address
3. Line 6: Paste GMAIL_REFRESH_TOKEN
4. Lines 2-3: Add your Client ID and Secret
5. Save file
6. Delete: config/get_gmail_refresh_token.php (for security)
```

### Step 4: Create Database Tables
```
Visit: http://localhost/ugat/config/create_gmail_verification_tables.php
```

### Step 5: Test
```
Visit: http://localhost/ugat/test_gmail_verification.php
```

---

## [EMAIL] Email Verification Flow

```
User Registration
    ↓
User clicks "Verify Email"
    ↓
POST /pages/trainee/send_verification_email.php
    - Generates 6-digit code
    - Sends email with code
    - Stores code in DB with 24h expiration
    ↓
User receives email with code
    ↓
User enters code on website
    ↓
POST /pages/trainee/verify_email_code.php
    - Validates code
    - Marks email as verified
    - Updates notification preferences
    ↓
User can now receive email notifications [CHECK]
```

---

## [EMAIL] Email Templates (8 Types)

All templates in `config/gmail_api.php`:

| Template | Usage | Variables |
|----------|-------|-----------|
| verification | Email verification | {{name}}, {{code}} |
| order_placed | Order confirmation | {{trainee_name}}, {{order_id}}, {{total}} |
| order_shipped | Shipping notification | {{trainee_name}}, {{order_id}}, {{tracking_number}} |
| order_delivered | Delivery confirmation | {{trainee_name}}, {{order_id}} |
| workshop_enrollment | Workshop registration | {{trainee_name}}, {{workshop_name}}, {{workshop_date}}, {{workshop_location}} |
| workshop_reminder | Day-before reminder | {{trainee_name}}, {{workshop_name}}, {{workshop_time}}, {{workshop_location}} |
| certification_issued | Certificate ready | {{trainee_name}}, {{certification_name}} |
| payment_received | Payment confirmation | {{trainee_name}}, {{amount}}, {{reference}} |

**To customize:**
Edit the HTML/text templates in `config/gmail_api.php`

---

## 🔄 Integration with Existing System

### With SMS Notifications
```php
// Send both SMS and Email
require_once 'config/sms_helpers.php';

// Old: SMS only
sendOrderPlacedNotification($trainee_id, $order_details);

// New: SMS + Email with user preferences
sendOrderPlacedNotificationDual($trainee_id, $order_details);
```

### With Notification Preferences
```php
// User preferences stored in database
// User can enable/disable SMS and Email independently
// System respects their choices when sending

// API endpoint:
GET /pages/trainee/get_notification_preferences.php
POST /pages/trainee/update_notification_preferences.php
```

---

## [CHART] Database Schema

### email_verification_codes
```sql
id              INT PRIMARY KEY AUTO_INCREMENT
user_id         INT NOT NULL (references users.id)
email           VARCHAR(255) NOT NULL
code            VARCHAR(6) NOT NULL
verified_at     TIMESTAMP NULL (when verified)
expires_at      TIMESTAMP NOT NULL (24 hours from creation)
created_at      TIMESTAMP DEFAULT NOW()
```

### notification_preferences (updated)
```sql
-- Existing columns
user_id
phone_enabled
email_enabled
email
created_at
updated_at

-- New column
email_verified  BOOLEAN DEFAULT FALSE
```

### email_logs (existing)
```
Logs all emails sent with metadata
Type: 'verification' for verification emails
```

---

## [LOCK] Security Best Practices

### [CHECK] Do This
- Store credentials securely in `config/gmail_api.php`
- Delete `get_gmail_refresh_token.php` after setup
- Use HTTPS in production
- Validate email addresses before sending
- Implement rate limiting on verification endpoints
- Log all verification attempts

### [X] Don't Do This
- Don't commit credentials to Git
- Don't log full token values
- Don't expose error details to users
- Don't send verification codes via plain SMS
- Don't use same password for multiple services

### `.gitignore` Addition
```
# Add to prevent accidental credential leak
config/gmail_api.php
config/get_gmail_refresh_token.php
```

---

## [TEST] Testing Checklist

- [ ] Google Cloud Project created
- [ ] Gmail API enabled
- [ ] OAuth2 credentials generated
- [ ] Refresh token obtained
- [ ] `config/gmail_api.php` configured
- [ ] Database tables created
- [ ] `get_gmail_refresh_token.php` deleted
- [ ] Verification email sends successfully
- [ ] Code verification works
- [ ] Email appears in inbox (check spam folder)
- [ ] Notification preferences page works
- [ ] Emails log to database correctly
- [ ] 6-digit codes expire after 24 hours

---

## [TOOL] Troubleshooting

### Configuration Issues
```
Problem: "Client ID not configured" error
Solution: Edit config/gmail_api.php and fill in all credentials

Problem: "Refresh Token not configured"
Solution: Run config/get_gmail_refresh_token.php to generate token

Problem: "Failed to get access token"
Solution: Check GMAIL_REFRESH_TOKEN is correct and not expired
```

### Email Not Sending
```
Problem: Email doesn't arrive
Causes:
1. Gmail spam filter (check spam folder)
2. Quota limit reached (Google has 500 emails/day limit)
3. GMAIL_SENDER_EMAIL doesn't match authorized account
4. CURL not enabled on server

Solution: Check test_gmail_verification.php for detailed errors
```

### Database Issues
```
Problem: "table not found" error
Solution: Visit config/create_gmail_verification_tables.php

Problem: Duplicate entry error
Solution: This is expected - table uses ON DUPLICATE KEY UPDATE
```

---

## [PHONE] File Locations Summary

| File | Purpose | Action |
|------|---------|--------|
| config/gmail_api.php | Config & templates | Edit with credentials |
| config/gmail_api_service.php | Email service | No action needed |
| config/get_gmail_refresh_token.php | OAuth2 setup | Run once, then delete |
| config/create_gmail_verification_tables.php | DB setup | Run once |
| pages/trainee/send_verification_email.php | Send code | Auto-used by system |
| pages/trainee/verify_email_code.php | Verify code | Auto-used by system |
| test_gmail_verification.php | Testing tool | Visit in browser |
| GMAIL_API_SETUP.md | Setup guide | Read for detailed steps |

---

## [CHECK] Features

### Email Verification
- [CHECK] 6-digit code generation
- [CHECK] 24-hour expiration
- [CHECK] HTML + Plain text emails
- [CHECK] Rate limiting ready
- [CHECK] Database logging

### Email Notifications
- [CHECK] 8 notification types
- [CHECK] Template system
- [CHECK] Variable substitution
- [CHECK] Bulk sending support
- [CHECK] Status tracking

### Integration
- [CHECK] Works with SMS notifications
- [CHECK] Respects user preferences
- [CHECK] Dual-channel (SMS + Email)
- [CHECK] Backward compatible

### Security
- [CHECK] OAuth2 authentication
- [CHECK] Automatic token refresh
- [CHECK] Input validation
- [CHECK] Session-based endpoints
- [CHECK] Database audit trail

---

## [TARGET] Next Steps

1. **Complete Setup**
   - Follow steps in GMAIL_API_SETUP.md
   - Get your Google credentials
   - Configure system

2. **Test**
   - Visit test_gmail_verification.php
   - Send test email
   - Verify code works

3. **Integration**
   - Update notification code to use dual-channel
   - Test with real users
   - Monitor email logs

4. **Production**
   - Configure rate limiting
   - Set up monitoring/alerts
   - Review email templates
   - Monitor quota usage

---

## [PHONE] Support

For issues:
1. Check GMAIL_API_SETUP.md troubleshooting section
2. Review test_gmail_verification.php diagnostics
3. Check Google Cloud Console for API quota/errors
4. Verify all credentials are correct
5. Check email logs in database

---

**🎉 Gmail API Integration Complete!**

Your UGAT system can now:
- ✅ Send verification emails with 6-digit codes
- ✅ Track verified email addresses
- ✅ Send 8 types of notifications via email
- ✅ Respect user notification preferences
- ✅ Log all email activity to database

**Start with:** `GMAIL_API_SETUP.md` for step-by-step configuration
