<?php
/**
 * Gmail API Verification Email Test
 * Visit: http://localhost/ugat/test_gmail_verification.php
 */

require_once 'config/gmail_api.php';
require_once 'config/gmail_api_service.php';
require_once 'config/db.php';

$test_results = [];
$test_email = 'test@example.com';  // Change this to your email

// Simulate a test user
$test_user_id = 999;
$test_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Gmail API Verification Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 5px;
        }
        .section h2 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .status-check {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .status-item {
            padding: 15px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-item.success {
            background: #e8f5e9;
            border: 1px solid #4caf50;
            color: #2e7d32;
        }
        .status-item.error {
            background: #ffebee;
            border: 1px solid #f44336;
            color: #c62828;
        }
        .status-item.warning {
            background: #fff3e0;
            border: 1px solid #ff9800;
            color: #e65100;
        }
        .icon {
            font-size: 20px;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: monospace;
            font-size: 14px;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #ccc;
            color: #333;
        }
        .btn-secondary:hover {
            background: #bbb;
        }
        .response {
            margin-top: 15px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 400px;
            overflow-y: auto;
            font-size: 13px;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            color: #1565c0;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .tip {
            background: #f3e5f5;
            border-left: 4px solid #9c27b0;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #f5f5f5;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon icon-email"></span> Gmail API Verification Test</h1>
        <p class="subtitle">Test email verification with 6-digit codes</p>
        
        <!-- Configuration Status -->
        <div class="section">
            <h2><span class="icon icon-tool"></span> Configuration Status</h2>
            <div class="status-check">
                <?php
                // Check Sender Email
                if (strpos(GMAIL_SENDER_EMAIL, 'your_') === 0 || GMAIL_SENDER_EMAIL === 'your_gmail@gmail.com') {
                    echo '<div class="status-item error"><span class="icon">✕</span> <span>Sender email not configured</span></div>';
                } else {
                    echo '<div class="status-item success"><span class="icon">✓</span> <span>Email: ' . htmlspecialchars(GMAIL_SENDER_EMAIL) . '</span></div>';
                }
                
                // Check Sender Password
                if (strpos(GMAIL_SENDER_PASSWORD, 'xxxx') === 0 || GMAIL_SENDER_PASSWORD === 'your_app_password_here') {
                    echo '<div class="status-item error"><span class="icon">✕</span> <span>App Password not configured</span></div>';
                } else {
                    echo '<div class="status-item success"><span class="icon">✓</span> <span>App Password configured</span></div>';
                }
                
                // Check SMTP Host
                if (defined('GMAIL_SMTP_HOST')) {
                    echo '<div class="status-item success"><span class="icon">✓</span> <span>SMTP: ' . GMAIL_SMTP_HOST . ':' . GMAIL_SMTP_PORT . '</span></div>';
                } else {
                    echo '<div class="status-item error"><span class="icon">✕</span> <span>SMTP config missing</span></div>';
                }
                
                // Check Database connection
                if ($conn->connect_error) {
                    echo '<div class="status-item error"><span class="icon">✕</span> <span>Database connection failed</span></div>';
                } else {
                    echo '<div class="status-item success"><span class="icon">✓</span> <span>Database connected</span></div>';
                }
                
                // Check tables exist
                $tables_exist = true;
                $check_table = "SHOW TABLES LIKE 'email_verification_codes'";
                $result = $conn->query($check_table);
                if ($result->num_rows === 0) {
                    echo '<div class="status-item error"><span class="icon">✕</span> <span>email_verification_codes table not found</span></div>';
                    $tables_exist = false;
                } else {
                    echo '<div class="status-item success"><span class="icon">✓</span> <span>Verification tables exist</span></div>';
                }
                ?>
            </div>
            
            <?php if (strpos(GMAIL_SENDER_EMAIL, 'your_') === 0 || GMAIL_SENDER_EMAIL === 'your_gmail@gmail.com'): ?>
                <div class="info-box">
                    <strong>[WARNING] Configuration Required:</strong>
                    <p style="margin-top: 8px;">
                        Please configure Gmail SMTP in <code>config/gmail_api.php</code>:<br>
                        1. Set GMAIL_SENDER_EMAIL to your Gmail<br>
                        2. Set GMAIL_SENDER_PASSWORD to your 16-char App Password<br>
                        <a href="GMAIL_SMTP_COMPLETE.md">View setup guide</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Send Verification Email -->
        <div class="section">
            <h2><span class="icon icon-email"></span> Send Verification Email</h2>
            <form id="sendForm" onsubmit="return sendVerificationEmail(event)">
                <div class="form-group">
                    <label>Email Address:</label>
                    <input type="email" id="emailInput" value="<?php echo htmlspecialchars($test_email); ?>" required>
                    <small style="color: #666;">Test email address (change to your Gmail)</small>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn-primary">📬 Send Verification Email</button>
                </div>
            </form>
            <div id="sendResponse"></div>
        </div>
        
        <!-- Verify Code -->
        <div class="section">
            <h2><span class="icon icon-check"></span> Verify Code</h2>
            <div class="info-box">
                <strong>📝 Steps:</strong>
                <ol style="margin-left: 20px; margin-top: 8px;">
                    <li>Click "Send Verification Email" above</li>
                    <li>Check your email for the 6-digit code</li>
                    <li>Enter the code below and click "Verify"</li>
                </ol>
            </div>
            <form id="verifyForm" onsubmit="return verifyCode(event)">
                <div class="form-group">
                    <label>6-Digit Code:</label>
                    <input type="text" id="codeInput" placeholder="123456" maxlength="6" pattern="[0-9]{6}" required>
                </div>
                <div class="form-group">
                    <label>Email Address:</label>
                    <input type="email" id="verifyEmailInput" value="<?php echo htmlspecialchars($test_email); ?>" required>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn-primary"><span class="icon icon-check"></span> Verify Code</button>
                </div>
            </form>
            <div id="verifyResponse"></div>
        </div>
        
        <!-- Setup Instructions -->
        <div class="section">
            <h2>⚙️ Quick Setup (3 Minutes)</h2>
            <div class="info-box">
                <strong><span class="icon icon-check"></span> Gmail SMTP Setup:</strong><br><br>
                <strong>Step 1: Enable 2FA</strong><br>
                Go to <a href="https://myaccount.google.com/security" target="_blank">myaccount.google.com/security</a> and enable 2-Step Verification<br><br>
                <strong>Step 2: Get App Password</strong><br>
                Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a> and create a password for "Mail" app<br><br>
                <strong>Step 3: Configure UGAT</strong><br>
                Edit <code>config/gmail_api.php</code> and set:<br>
                • GMAIL_SENDER_EMAIL = your Gmail<br>
                • GMAIL_SENDER_PASSWORD = 16-char App Password<br><br>
                <strong>Step 4: Create Tables</strong><br>
                <a href="config/create_gmail_verification_tables.php" target="_blank">Create email verification tables</a><br><br>
                <strong>Step 5: Test</strong><br>
                Reload this page and test sending emails!
            </div>
        </div>
        
        
        <!-- Help -->
        <div class="section">
            <h2>❓ Help & Troubleshooting</h2>
            <div class="tip">
                <strong>Configuration Error?</strong>
                <p style="margin-top: 8px;">
                    Check <code>config/gmail_api.php</code> and verify all credentials are filled in correctly.
                </p>
            </div>
            <div class="tip">
                <strong>Email Not Sent?</strong>
                <p style="margin-top: 8px;">
                    Check Google Cloud Console for quota limits or API errors. Verify Gmail account settings allow API access.
                </p>
            </div>
            <div class="tip">
                <strong>Code Not Working?</strong>
                <p style="margin-top: 8px;">
                    Codes expire after 24 hours. Make sure you entered the correct code and email address.
                </p>
            </div>
            <div class="tip">
                <strong>Need Help?</strong>
                <p style="margin-top: 8px;">
                    See detailed setup instructions in <code>GMAIL_API_SETUP.md</code>.
                </p>
            </div>
        </div>
    </div>
    
    <script>
        async function sendVerificationEmail(event) {
            event.preventDefault();
            const email = document.getElementById('emailInput').value;
            const responseDiv = document.getElementById('sendResponse');
            responseDiv.innerHTML = '<div class="response">Sending...</div>';
            
            try {
                // Note: This would require actual session/auth
                // For testing, we'll show the endpoint
                responseDiv.innerHTML = `<div class="info-box">
                    <strong>📌 API Endpoint:</strong>
                    <p>POST /pages/trainee/send_verification_email.php</p>
                    <p style="margin-top: 8px;">Parameters: <code>email=${email}</code></p>
                    <p style="margin-top: 8px; color: #f44336;"><strong>Note:</strong> Requires authentication (logged in as trainee)</p>
                </div>`;
            } catch (error) {
                responseDiv.innerHTML = `<div class="response">Error: ${error.message}</div>`;
            }
            return false;
        }
        
        async function verifyCode(event) {
            event.preventDefault();
            const code = document.getElementById('codeInput').value;
            const email = document.getElementById('verifyEmailInput').value;
            const responseDiv = document.getElementById('verifyResponse');
            responseDiv.innerHTML = '<div class="response">Verifying...</div>';
            
            try {
                responseDiv.innerHTML = `<div class="info-box">
                    <strong>📌 API Endpoint:</strong>
                    <p>POST /pages/trainee/verify_email_code.php</p>
                    <p style="margin-top: 8px;">Parameters: <code>code=${code}&email=${email}</code></p>
                    <p style="margin-top: 8px; color: #f44336;"><strong>Note:</strong> Requires authentication (logged in as trainee)</p>
                </div>`;
            } catch (error) {
                responseDiv.innerHTML = `<div class="response">Error: ${error.message}</div>`;
            }
            return false;
        }
    </script>
</body>
</html>
