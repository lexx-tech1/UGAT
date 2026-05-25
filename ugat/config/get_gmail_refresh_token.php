<?php
/**
 * Gmail SMTP - App Password Setup Guide
 * 
 * INSTRUCTIONS:
 * 1. Follow the steps below to get your Gmail App Password
 * 2. Paste the 16-character password into config/gmail_api.php
 * 3. Delete this file after setup for security
 */

?>
<!DOCTYPE html>
<html>
<head>
    <title>Gmail SMTP Setup Guide</title>
    <style>
        body { font-family: Arial; max-width: 700px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; display: flex; align-items: center; gap: 10px; }
        .step { background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 5px; }
        .step h3 { color: #4CAF50; margin-top: 0; }
        .step ol, .step ul { margin: 10px 0; padding-left: 20px; }
        .step li { margin: 8px 0; line-height: 1.6; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d4edda; border: 1px solid #28a745; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .link-button { display: inline-block; background: #2196f3; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; margin: 10px 0; }
        .link-button:hover { background: #1976d2; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f5f5f5; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon icon-email"></span> Gmail SMTP Setup - 3 Easy Steps</h1>
        
        <div class="warning">
            <strong>[!] Important:</strong> You must use a <strong>Gmail App Password</strong>, NOT your regular Gmail password. This is more secure.
        </div>
        
        <!-- Step 1: Enable 2FA -->
        <div class="step">
            <h3>Step 1: Enable 2-Factor Authentication</h3>
            <p>Gmail requires 2FA to generate App Passwords:</p>
            <ol>
                <li>Go to <a href="https://myaccount.google.com/security" target="_blank">https://myaccount.google.com/security</a></li>
                <li>Click <strong>"2-Step Verification"</strong></li>
                <li>Follow the setup (you'll get codes via phone or authenticator app)</li>
                <li>Once enabled, continue to Step 2</li>
            </ol>
        </div>
        
        <!-- Step 2: Generate App Password -->
        <div class="step">
            <h3>Step 2: Generate App Password</h3>
            <p>After 2FA is enabled:</p>
            <ol>
                <li>Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a></li>
                <li>Select:
                    <ul>
                        <li><strong>App:</strong> Mail</li>
                        <li><strong>Device:</strong> Windows Computer (or your device type)</li>
                    </ul>
                </li>
                <li>Click <strong>"Generate"</strong></li>
                <li><strong>Copy the 16-character password</strong> shown (without spaces)</li>
            </ol>
            <div class="success">
                <strong>[✓] Got your password?</strong> Continue to Step 3
            </div>
        </div>
        
        <!-- Step 3: Configure UGAT -->
        <div class="step">
            <h3>Step 3: Configure UGAT</h3>
            <ol>
                <li>Edit <code>config/gmail_api.php</code></li>
                <li>Find these lines:
                    <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px;"><code>define('GMAIL_SENDER_EMAIL', 'your_gmail@gmail.com');
define('GMAIL_SENDER_PASSWORD', 'your_app_password_here');</code></pre>
                </li>
                <li>Replace with YOUR email and 16-digit password:
                    <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px;"><code>define('GMAIL_SENDER_EMAIL', 'yourname@gmail.com');
define('GMAIL_SENDER_PASSWORD', 'abcd efgh ijkl mnop');</code></pre>
                </li>
                <li>Save the file</li>
                <li>[CELEBRATE] <strong>Done!</strong> Test sending emails now</li>
            </ol>
        </div>
        
        <!-- Verify Setup -->
        <div class="step">
            <h3>[✓] Test Your Setup</h3>
            <p>Visit this page to test:</p>
            <a href="../test_gmail_verification.php" class="link-button"><span class="icon icon-email"></span> Test Email Setup</a>
        </div>
        
        <!-- Troubleshooting -->
        <div class="step">
            <h3>❓ Troubleshooting</h3>
            <table>
                <tr>
                    <th>Problem</th>
                    <th>Solution</th>
                </tr>
                <tr>
                    <td>"SMTP auth failed"</td>
                    <td>Check GMAIL_SENDER_PASSWORD is correct 16-digit password (without spaces)</td>
                </tr>
                <tr>
                    <td>"2-Factor not enabled"</td>
                    <td>App Passwords require 2FA. Enable at <a href="https://myaccount.google.com/security" target="_blank">Security Settings</a></td>
                </tr>
                <tr>
                    <td>"Email not sent"</td>
                    <td>Check GMAIL_SENDER_EMAIL is correct Gmail address</td>
                </tr>
                <tr>
                    <td>"Can't find App Passwords"</td>
                    <td>You may be using a Google Workspace account. Use your regular Gmail instead.</td>
                </tr>
            </table>
        </div>
        
        <!-- Security Notes -->
        <div class="warning" style="background: #e3f2fd; border-color: #2196f3; color: #1565c0;">
            <h4 style="margin-top: 0;">[LOCK] Security Best Practices:</h4>
            <ul>
                <li><strong>Never use your Gmail login password</strong> - always use App Password</li>
                <li><strong>Don't commit credentials to Git</strong> - add to .gitignore</li>
                <li><strong>Delete this file</strong> after setup</li>
                <li><strong>Each app gets its own password</strong> - good for security</li>
                <li><strong>Can revoke anytime</strong> from App Passwords page</li>
            </ul>
        </div>
        
        <hr>
        <p style="color: #666; font-size: 14px;">
            Need help? See <code>GMAIL_API_SETUP.md</code> for detailed instructions
        </p>
    </div>
</body>
</html>
