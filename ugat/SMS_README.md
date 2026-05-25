# UGAT SMS Messaging API - Implementation Summary

## Overview
A complete SMS notification system for the UGAT (Urban Gardening and Agricultural Technologies) platform, supporting both Admin and Trainee notifications.

## 📦 What's Included

### Core Files (Backend)
- **config/sms.php** - Configuration, templates, and settings
- **config/sms_service.php** - SMS service class (Twilio integration)
- **config/sms_helpers.php** - Easy-to-use helper functions
- **config/create_sms_tables.php** - Database setup script

### API Endpoints (Backend)
- **pages/admin/send_sms_notification.php** - Admin send SMS API
- **pages/admin/get_sms_notifications.php** - Admin notification history
- **pages/trainee/get_sms_notifications.php** - Trainee SMS retrieval

### Frontend Components
- **pages/admin/admin_sms.js** - Admin UI for sending SMS
- **pages/trainee/trainee_sms_notifications.js** - Trainee notification display

### Documentation
- **SMS_INTEGRATION_GUIDE.php** - Complete setup & integration guide
- **INTEGRATION_EXAMPLES.php** - Code examples for common use cases
- **README.md** - This file

## 🚀 Quick Setup

### 1. Create Database Tables
```
Visit: http://localhost/ugat/config/create_sms_tables.php
```

### 2. Configure Twilio
Edit `config/sms.php`:
```php
define('TWILIO_ACCOUNT_SID', 'your_account_sid');
define('TWILIO_AUTH_TOKEN', 'your_auth_token');
define('TWILIO_PHONE_NUMBER', '+1234567890');
```

### 3. Test Mode (Optional)
```php
define('SMS_DEBUG_MODE', true);  // Log instead of sending
```

### 4. Add UI Elements
In admin dashboard:
```html
<button id="sms-send-btn">Send SMS</button>
<div id="sms-history"></div>
<script src="admin_sms.js"></script>
```

In trainee notifications:
```html
<div id="sms-notifications-container"></div>
<script src="trainee_sms_notifications.js"></script>
```

## 📱 Usage Examples

### Send SMS When Order Placed
```php
require_once '../../config/sms_helpers.php';

sendOrderPlacedNotification($user_id, $order_id, '₱5000');
```

### Send Batch SMS to Workshop Group
```php
require_once '../../config/sms_helpers.php';

sendWorkshopReminderNotification($user_id, 'Vegetable Growing 101', '2026-05-25', '9:00 AM');
```

### Send Custom SMS
```php
$sms = getSmsService($conn);
$result = $sms->sendSms('+63912345678', 'Your custom message here');
```

## 🔧 Available Helper Functions

| Function | Purpose |
|----------|---------|
| `sendOrderPlacedNotification()` | Send order confirmation |
| `sendOrderShippedNotification()` | Send shipment alert |
| `sendOrderDeliveredNotification()` | Send delivery confirmation |
| `sendWorkshopEnrollmentNotification()` | Send enrollment confirmation |
| `sendWorkshopReminderNotification()` | Send workshop reminder |
| `sendCertificationIssuedNotification()` | Send certification alert |
| `sendPaymentReceivedNotification()` | Send payment confirmation |
| `sendSmsForEvent()` | Send custom event SMS |
| `sendSmsForEventToMultiple()` | Batch send SMS |

## 🗄️ Database Tables

### sms_logs
Stores all SMS records with delivery status tracking.

### sms_notifications
Admin-sent notification history.

### trainee_sms_log
Trainee message inbox for tracking received SMS.

## 👥 Admin Features

- Send SMS to single trainee
- Send SMS to workshop group
- Send SMS to all trainees
- Use pre-built templates or custom messages
- View notification history
- Track delivery status

## 🎓 Trainee Features

- View SMS message history
- See notification type badges
- Filter by date/timestamp
- Privacy: masked phone numbers

## 📋 SMS Templates

Pre-built templates with variable substitution:

1. **order_placed** - Order confirmation
2. **order_shipped** - Shipment notification
3. **order_delivered** - Delivery confirmation
4. **workshop_enrollment** - Enrollment confirmation
5. **workshop_reminder** - Day-of reminder
6. **certification_issued** - Certificate ready
7. **payment_received** - Payment confirmation

Templates support variables like `{name}`, `{order_id}`, `{date}`, etc.

## ⚙️ Configuration Options

```php
SMS_PROVIDER = 'twilio'              // SMS provider
SMS_ENABLED = true                   // Global enable/disable
SMS_DEBUG_MODE = false               // Test without sending
TWILIO_ACCOUNT_SID = 'xxx'          // Twilio credentials
TWILIO_AUTH_TOKEN = 'xxx'           // Twilio credentials
TWILIO_PHONE_NUMBER = '+1234567890' // Twilio number
```

## 🔐 Security Notes

- ✅ Authentication required on all endpoints
- ✅ Role-based access control (admin/trainee)
- ✅ Phone numbers validated with regex
- ✅ Message length validated (max 1600 chars)
- ✅ SQL injection prevention with prepared statements
- ✅ HTML escaping on frontend
- ⚠️ Store credentials in environment variables in production
- ⚠️ Implement rate limiting to prevent abuse
- ⚠️ Log all SMS for compliance

## 📊 API Response Format

### Successful Response
```json
{
  "success": true,
  "message": "SMS sent successfully.",
  "sms_id": "SM1234567890abcdef",
  "details": {
    "total_recipients": 5,
    "successful": 5,
    "failed": 0
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "User phone number not found"
}
```

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| "SMS service is disabled" | Set `SMS_ENABLED = true` in sms.php |
| "Trainee phone number not found" | Add phone to user profile |
| "Invalid phone number format" | Use format: +country_codeNUMBER |
| "Twilio error: 401" | Check API credentials in sms.php |
| SMS not sending | Enable debug mode and check error logs |

## 📞 Integration with Events

Common integration points:

1. **Order Placement** → `place_order.php`
2. **Workshop Enrollment** → `submit_enrollment.php`
3. **Certificate Issuance** → `export_certificates.php`
4. **Payment Processing** → `place_order.php` (payment section)
5. **Scheduled Reminders** → Cron job script

See `INTEGRATION_EXAMPLES.php` for code samples.

## 🎯 Next Steps

1. ✅ Setup database tables
2. ✅ Configure Twilio credentials
3. ✅ Test with SMS_DEBUG_MODE = true
4. ✅ Integrate into existing workflows
5. ✅ Add UI buttons to admin/trainee dashboards
6. ✅ Deploy to production with real credentials

## 📚 Additional Resources

- [SMS_INTEGRATION_GUIDE.php](SMS_INTEGRATION_GUIDE.php) - Detailed setup guide
- [INTEGRATION_EXAMPLES.php](INTEGRATION_EXAMPLES.php) - Code examples
- [Twilio Documentation](https://www.twilio.com/docs)
- [Twilio Console](https://www.twilio.com/console)

## 💡 Support

For issues or questions:
1. Check the integration guide
2. Review the error logs
3. Test with SMS_DEBUG_MODE enabled
4. Verify Twilio credentials
5. Ensure users have phone numbers

---

**Version:** 1.0  
**Last Updated:** May 2026  
**Status:** ✅ Ready for Production
