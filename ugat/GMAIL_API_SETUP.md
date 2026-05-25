# [EMAIL] Gmail SMTP Setup Guide

Complete guide for setting up Gmail SMTP for email verification and notifications. **NO Google Cloud setup needed!**

## [ROCKET] Quick Start (5 Minutes)

### Step 1: Enable 2-Factor Authentication

1. Go to **https://myaccount.google.com/security**
2. Click **"2-Step Verification"**
3. Follow the setup (you'll get codes via phone or authenticator app)
4. **Keep this enabled** - it's required for App Passwords

### Step 2: Create Gmail App Password

1. Go to **https://myaccount.google.com/apppasswords**
2. Select:
   - **App:** Mail
   - **Device:** Windows Computer (or your device type)
3. Click **"Generate"**
4. Google will show a **16-character password**
5. **Copy it** (without spaces)

### Step 3: Configure UGAT

1. Edit [config/gmail_api.php](../config/gmail_api.php)
2. Replace these two lines:
   ```php
   define('GMAIL_SENDER_EMAIL', 'your_gmail@gmail.com');  // Your Gmail address
   define('GMAIL_SENDER_PASSWORD', 'your_app_password_here');  // 16-character App Password
   ```

   With YOUR email and password:
   ```php
   define('GMAIL_SENDER_EMAIL', 'yourname@gmail.com');  // Your Gmail address
   define('GMAIL_SENDER_PASSWORD', 'abcd efgh ijkl mnop');  // Copy from App Passwords
   ```

3. Save the file

### Step 4: Create Database Tables

Visit: **http://localhost/ugat/config/create_gmail_verification_tables.php**

This creates:
- `email_verification_codes` table (for 6-digit verification codes)
- `email_logs` table (tracks all email sends)

### Step 5: Test Email Setup

Visit: **http://localhost/ugat/test_gmail_verification.php**

Send a test email to verify everything works!

---

## [LIST] Architecture

### How It Works

```
User enables email notifications
    ↓
App sends verification email via Gmail SMTP
    ↓
Email contains 6-digit code
    ↓
User enters code on website
    ↓
Email marked as verified
    ↓
User receives all notifications (SMS + Email based on preferences)
```

### Files Used

| File | Purpose |
|------|---------|
| `config/gmail_api.php` | Configuration & email templates |
| `config/gmail_api_service.php` | Gmail API email sending service |
| `config/get_gmail_refresh_token.php` | OAuth2 authorization helper |
| `pages/trainee/send_verification_email.php` | API endpoint to send verification email |
| `pages/trainee/verify_email_code.php` | API endpoint to verify code |
| `config/create_gmail_verification_tables.php` | Database setup script |

### API Endpoints

#### Send Verification Email
```
POST /pages/trainee/send_verification_email.php
Parameters: email (your email address)
Response: { success, message, email }
```

**Example:**
```bash
curl -X POST http://localhost/ugat/pages/trainee/send_verification_email.php \
  -d "email=yourname@gmail.com"
```

#### Verify Code
```
POST /pages/trainee/verify_email_code.php
Parameters: code (6-digit), email (your email address)
Response: { success, message, email }
```

**Example:**
```bash
curl -X POST http://localhost/ugat/pages/trainee/verify_email_code.php \
  -d "code=123456&email=yourname@gmail.com"
```

---

## [TEST] Testing

### Test 1: Send Verification Email

Visit: **http://localhost/ugat/test_gmail_verification.php**

This tests:
- [CHECK] Gmail API connection
- [CHECK] Email sending
- [CHECK] Database logging
- [CHECK] Code generation

### Test 2: Verify Code

In the test page, enter the code from your email to verify it works.

---

## [EMAIL] Email Templates

All templates are in [config/gmail_api.php](../config/gmail_api.php):

1. **Verification Email** - 6-digit code for account verification
2. **Order Placed** - Order confirmation
3. **Order Shipped** - Shipping notification
4. **Order Delivered** - Delivery confirmation
5. **Workshop Enrollment** - Workshop registration
6. **Workshop Reminder** - Workshop starts tomorrow
7. **Certification Issued** - Certificate ready
8. **Payment Received** - Payment confirmation

To customize, edit the templates in `config/gmail_api.php`.

---

## [LOCK] Security

### Best Practices

1. **Never share your App Password**
   - Keep `GMAIL_SENDER_PASSWORD` in `.gitignore`
   - Each app should have its own App Password

2. **Delete setup files after configuration**
   - Remove `config/get_gmail_refresh_token.php`

3. **Restrict access to config files**
   - Only your server should read `config/gmail_api.php`
   - Use file permissions to restrict

4. **Revoke access if exposed**
   - Go to https://myaccount.google.com/apppasswords
   - Delete the compromised password
   - Generate a new one

### File Permissions

```bash
# Linux/Mac
chmod 600 config/gmail_api.php
```

---

## [X] Troubleshooting

### "SMTP auth failed" or "Authentication failed"

**Problem**: Gmail rejected your credentials
- App Password incorrect
- Using regular Gmail password instead of App Password
- Email address wrong

**Fix**:
1. Verify you're using **App Password** (not your Gmail login password)
2. Copy the password fresh from https://myaccount.google.com/apppasswords
3. Make sure GMAIL_SENDER_EMAIL matches your Gmail account
4. Remove any spaces from the password

### "2-Factor authentication not enabled"

**Problem**: Can't access App Passwords page
- 2FA must be enabled first

**Fix**:
1. Go to https://myaccount.google.com/security
2. Enable "2-Step Verification"
3. Once enabled, App Passwords will be available

### "Email not received"

**Possible causes**:
1. Gmail spam filter (check spam folder)
2. Wrong email address configured
3. Firewall/ISP blocking Gmail

**Fix**:
1. Check GMAIL_SENDER_EMAIL in `config/gmail_api.php`
2. Test sending from Gmail web directly
3. Check spam/promotions folder
4. Verify Gmail account can send emails

### "CURL error: Connection timeout"

**Problem**: Can't connect to Gmail SMTP server
- Firewall blocking port 587
- Network issues

**Fix**:
1. Check internet connection
2. Whitelist `smtp.gmail.com:587` in firewall
3. Try from different network to test
4. Check if ISP blocks port 587 (try port 465 instead)

### "Port 587 blocked"

**Problem**: Your ISP/network blocks port 587
- Common with some mobile networks

**Fix**:
In `config/gmail_api.php`, try port 465 with SSL:
```php
define('GMAIL_SMTP_PORT', 465);
define('GMAIL_SMTP_ENCRYPTION', 'ssl');  // Changed from 'tls'
```

### "Email template variables not replaced"

**Problem**: Email shows {{name}} instead of actual name
- Not passing variables correctly

**Fix**:
When sending, use `str_replace()` or template engine to replace variables before sending.

---

## [PHONE] Support

For issues:
1. Verify 2FA is enabled
2. Check App Password is correct
3. Verify GMAIL_SENDER_EMAIL is correct
4. Check network can reach smtp.gmail.com:587
5. Review email logs in database

---

## [CHECK] Checklist

- [ ] 2-Factor Authentication enabled on Gmail
- [ ] App Password created and copied
- [ ] `config/gmail_api.php` updated with email and password
- [ ] `config/get_gmail_refresh_token.php` deleted or disabled
- [ ] Database tables created
- [ ] Test email sends successfully
- [ ] Verification codes work
- [ ] Real email verification works

---

**Setup Complete! Your UGAT system can now send verified emails via Gmail API.** [CHECK]
