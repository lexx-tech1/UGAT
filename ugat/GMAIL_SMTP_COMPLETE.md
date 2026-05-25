# [EMAIL] Gmail SMTP Complete Setup (Simplified - No OAuth2!)

**TL;DR**: Use Gmail SMTP with App Passwords - NO Google Cloud, NO OAuth2, just 3 steps!

---

## [LIGHTNING] 3-Minute Setup

### Step 1: Enable 2FA
Go to https://myaccount.google.com/security → Enable "2-Step Verification" (2 minutes)

### Step 2: Get App Password
Go to https://myaccount.google.com/apppasswords → Select Mail + Device → Copy 16-char password (1 minute)

### Step 3: Configure UGAT
Edit `config/gmail_api.php`:
```php
define('GMAIL_SENDER_EMAIL', 'your_gmail@gmail.com');      // Your Gmail
define('GMAIL_SENDER_PASSWORD', 'xxxx xxxx xxxx xxxx');     // 16-char password
```

**Done! [CHECK]** Your Gmail SMTP is now configured.

---

## [TEST] Verify It Works

Visit: **http://localhost/ugat/config/create_gmail_verification_tables.php**

This creates database tables for email verification and logging.

Then visit: **http://localhost/ugat/test_gmail_verification.php**

Send a test email to verify everything works!

---

## [LIST] What You Get

[CHECK] **Email Verification** - 6-digit codes sent via Gmail  
[CHECK] **ALL Notifications** - Order, Workshop, Certificate, Payment emails  
[CHECK] **Completely FREE** - No API costs, no quotas  
[CHECK] **Easy Setup** - Just an App Password, nothing else  
[CHECK] **Secure** - OAuth2 under the hood (handled by Gmail)  
[CHECK] **Log Everything** - All emails tracked in database  
[CHECK] **Templates Included** - 8 notification types ready to use  

---

## [TOOL] Configuration File

Location: `config/gmail_api.php`

Only 3 lines you need to change:

```php
// Line 10: Your Gmail address
define('GMAIL_SENDER_EMAIL', 'your_gmail@gmail.com');

// Line 11: Your 16-character App Password
define('GMAIL_SENDER_PASSWORD', 'xxxx xxxx xxxx xxxx');

// Line 12: Name shown in emails (optional)
define('GMAIL_SENDER_NAME', 'UGAT Notifications');
```

Everything else is pre-configured and working!

---

## [EMAIL] Email Service

File: `config/gmail_api_service.php`

The service uses **PHPMailer** to send emails via Gmail SMTP. It's completely automatic - you don't need to touch it.

**Available functions:**

```php
// Send single email
sendGmailEmail($email, $subject, $html_body, $text_body);

// Get service object for advanced use
$service = new GmailSmtpService();
$result = $service->sendEmail(...);
```

---

## 📨 Database Tables

Created by `config/create_gmail_verification_tables.php`:

### email_verification_codes
Stores 6-digit codes for email verification

| Column | Type | Purpose |
|--------|------|---------|
| id | INT | Primary key |
| email | VARCHAR(255) | User email |
| code | VARCHAR(6) | 6-digit verification code |
| created_at | TIMESTAMP | When code was sent |
| expires_at | TIMESTAMP | Expires after 24 hours |

### email_logs
Tracks all emails sent

| Column | Type | Purpose |
|--------|------|---------|
| id | INT | Primary key |
| recipient | VARCHAR(255) | Email address |
| subject | TEXT | Email subject |
| status | VARCHAR(50) | sent/failed |
| message_id | VARCHAR(255) | Gmail message ID |
| sent_at | TIMESTAMP | When sent |

---

## 🔌 API Endpoints

### Send Verification Email
```
POST /pages/trainee/send_verification_email.php
Body: email=user@example.com
Response: { success, message, email }
```

### Verify Email Code
```
POST /pages/trainee/verify_email_code.php
Body: email=user@example.com&code=123456
Response: { success, message, email }
```

### Get Notifications (SMS + Email)
```
GET /pages/trainee/get_all_notifications.php
Response: { notifications, success }
```

---

## ❓ Common Questions

### Q: Why Gmail SMTP instead of Gmail API?
**A:** Simpler, faster, no OAuth2 complexity. Just paste an App Password and go!

### Q: Is it really free?
**A:** Yes! Gmail accounts get unlimited SMTP sending. No API costs, no quotas.

### Q: Can I use my regular Gmail password?
**A:** No - **must use App Password**. Never use your actual Gmail login password.

### Q: How many emails can I send?
**A:** Virtually unlimited. Gmail's daily limit is 500 per account, but you won't hit that.

### Q: What if 2FA doesn't work?
**A:** You MUST enable 2FA first - it's required for App Passwords.

### Q: Can I use Gmail on my domain (Google Workspace)?
**A:** Yes, same process - just use your workspace email and App Password.

### Q: What if I get SMTP errors?
**A:** Check:
1. Email address is correct
2. App Password is correct (16 characters)
3. 2FA is enabled
4. Port 587 isn't blocked by ISP

---

## [LOCK] Security Best Practices

1. **Never commit to Git** - Add to `.gitignore`:
   ```
   config/gmail_api.php
   config/get_gmail_refresh_token.php
   ```

2. **Use App Passwords** - Each app gets its own password

3. **Keep them secret** - Don't share, don't log, don't debug print

4. **Revoke anytime** - Go to https://myaccount.google.com/apppasswords and delete if compromised

5. **Rotate regularly** - Generate new password every 6 months for production

---

## 🚨 Troubleshooting

### "SMTP auth failed"
- Check App Password is correct (copy fresh from Google)
- Verify 2FA is enabled
- Try port 465 with SSL encryption

### "Email not received"
- Check email address is correct
- Look in spam folder
- Check Gmail is working (send test from web)

### "Connection timeout"
- Port 587 might be blocked by ISP
- Try port 465 instead
- Check firewall allows outbound to smtp.gmail.com

### "2-Factor not enabled"
- Go to https://myaccount.google.com/security
- Enable 2-Step Verification before getting App Password

---

## [CHECK] Verification Checklist

- [ ] 2-Factor Authentication enabled on Gmail
- [ ] App Password created and copied
- [ ] `config/gmail_api.php` has correct email and password
- [ ] Database tables created
- [ ] Test email sends successfully
- [ ] Verification codes work
- [ ] Notification emails send

---

## 📚 Related Files

- **Setup Guide**: GMAIL_API_SETUP.md
- **Implementation Details**: GMAIL_API_IMPLEMENTATION.md
- **Test Page**: test_gmail_verification.php
- **Database Setup**: config/create_gmail_verification_tables.php
- **Email Service**: config/gmail_api_service.php
- **Configuration**: config/gmail_api.php

---

**That's it! Your Gmail SMTP is ready to go. [ROCKET]**

Questions? Check GMAIL_API_SETUP.md for detailed troubleshooting.
