<?php
/**
 * SMS Diagnostic Tool - Troubleshooting Helper
 * 
 * Visit: http://localhost/ugat/test_sms_diagnostic.php
 * 
 * This tool helps diagnose and fix common SMS setup issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>UGAT SMS Diagnostic Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #4B8423 0%, #2d5a16 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .diagnostic-section {
            margin: 30px 0;
            padding: 25px;
            border-left: 5px solid #4B8423;
            background-color: #f9f9f9;
            border-radius: 6px;
        }
        .diagnostic-section h2 {
            color: #4B8423;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .check-item {
            display: flex;
            align-items: center;
            margin: 12px 0;
            padding: 12px;
            background: white;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        .check-icon {
            font-size: 24px;
            margin-right: 15px;
            width: 30px;
            text-align: center;
        }
        .check-content {
            flex: 1;
        }
        .check-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .check-value {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #666;
            background-color: #f5f5f5;
            padding: 6px 10px;
            border-radius: 3px;
            display: inline-block;
        }
        .status-ok {
            color: #28a745;
        }
        .status-error {
            color: #dc3545;
        }
        .status-warning {
            color: #ffc107;
        }
        .fix-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .fix-title {
            font-weight: bold;
            color: #856404;
            margin-bottom: 10px;
        }
        .fix-steps {
            list-style-position: inside;
            color: #856404;
        }
        .fix-steps li {
            margin: 8px 0;
        }
        .code-block {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 12px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .success-message {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .error-message {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .warning-message {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background-color: #4B8423;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2d5a16;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .summary-box {
            background: linear-gradient(135deg, #4B8423 0%, #2d5a16 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin: 30px 0;
        }
        .summary-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .summary-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
            font-size: 16px;
        }
        .summary-icon {
            margin-right: 10px;
            font-size: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4B8423;
            color: white;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><span class="icon icon-tool"></span> SMS Diagnostic Tool</h1>
        <p>Complete system health check and troubleshooting guide</p>
    </div>

    <div class="content">

        <?php
        // Include required files
        require_once 'config/db.php';
        require_once 'config/sms.php';

        // Collect all diagnostics
        $diagnostics = [];

        // 1. Database Connection
        $db_ok = $conn && !$conn->connect_error;
        $diagnostics['database'] = [
            'name' => 'Database Connection',
            'ok' => $db_ok,
            'details' => $db_ok ? 'Connected' : $conn->connect_error ?? 'Unknown error'
        ];

        // 2. API Key
        $api_key_ok = defined('SEMAPHORE_API_KEY') && SEMAPHORE_API_KEY !== 'your_semaphore_api_key_here';
        $diagnostics['api_key'] = [
            'name' => 'Semaphore API Key',
            'ok' => $api_key_ok,
            'details' => $api_key_ok ? 'Configured (' . substr(SEMAPHORE_API_KEY, 0, 10) . '...)' : 'Not configured'
        ];

        // 3. SMS Provider
        $provider_ok = defined('SMS_PROVIDER') && SMS_PROVIDER === 'semaphore';
        $diagnostics['provider'] = [
            'name' => 'SMS Provider',
            'ok' => $provider_ok,
            'details' => defined('SMS_PROVIDER') ? SMS_PROVIDER : 'Not defined'
        ];

        // 4. SMS Enabled
        $sms_enabled = defined('SMS_ENABLED') && SMS_ENABLED;
        $diagnostics['enabled'] = [
            'name' => 'SMS Enabled',
            'ok' => $sms_enabled,
            'details' => $sms_enabled ? 'Yes' : 'No'
        ];

        // 5. Debug Mode
        $debug_mode = defined('SMS_DEBUG_MODE') && SMS_DEBUG_MODE;
        $diagnostics['debug'] = [
            'name' => 'Debug Mode',
            'ok' => !$debug_mode,  // Actually ok if debug is disabled for production
            'details' => $debug_mode ? 'Enabled (testing mode)' : 'Disabled (production mode)',
            'warning' => !$debug_mode
        ];

        // 6. SMS Logs Table
        $sms_logs_exists = false;
        $sms_logs_count = 0;
        if ($db_ok) {
            $result = $conn->query("SHOW TABLES LIKE 'sms_logs'");
            $sms_logs_exists = $result && $result->num_rows > 0;
            if ($sms_logs_exists) {
                $count = $conn->query("SELECT COUNT(*) as c FROM sms_logs");
                if ($count) {
                    $row = $count->fetch_assoc();
                    $sms_logs_count = $row['c'];
                }
            }
        }
        $diagnostics['sms_logs'] = [
            'name' => 'SMS Logs Table',
            'ok' => $sms_logs_exists,
            'details' => $sms_logs_exists ? "$sms_logs_count records" : 'Table not found'
        ];

        // 7. Notification Preferences Table
        $prefs_exists = false;
        if ($db_ok) {
            $result = $conn->query("SHOW TABLES LIKE 'notification_preferences'");
            $prefs_exists = $result && $result->num_rows > 0;
        }
        $diagnostics['preferences'] = [
            'name' => 'Notification Preferences Table',
            'ok' => $prefs_exists,
            'details' => $prefs_exists ? 'Exists' : 'Not found'
        ];

        // 8. File Permissions
        $config_path = __DIR__ . '/config/sms.php';
        $sms_php_exists = file_exists($config_path);
        $diagnostics['config'] = [
            'name' => 'SMS Config File',
            'ok' => $sms_php_exists,
            'details' => $sms_php_exists ? 'Found' : 'Not found'
        ];

        // Calculate overall status
        $critical_ok = $db_ok && $api_key_ok && $sms_enabled && $sms_logs_exists;
        $overall_status = $critical_ok ? 'ready' : 'needs_work';

        // Display Summary
        ?>

        <div class="summary-box">
            <div class="summary-title">System Status Summary</div>
            <?php if ($overall_status === 'ready'): ?>
                <div class="summary-item">
                    <span class="summary-icon">[✓]</span>
                    <span>SMS system is ready to use</span>
                </div>
                <div class="summary-item">
                    <span class="summary-icon">✓</span>
                    <span><?php echo $sms_logs_count; ?> SMS messages in database</span>
                </div>
            <?php else: ?>
                <div class="summary-item">
                    <span class="summary-icon">[!]</span>
                    <span>SMS system requires configuration</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Diagnostic Checks -->
        <div class="diagnostic-section">
            <h2>🔍 System Health Checks</h2>

            <?php foreach ($diagnostics as $key => $diag): ?>
                <div class="check-item">
                    <div class="check-icon <?php echo $diag['ok'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $diag['ok'] ? '✓' : '✗'; ?>
                    </div>
                    <div class="check-content">
                        <div class="check-label"><?php echo $diag['name']; ?></div>
                        <div class="check-value"><?php echo $diag['details']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Troubleshooting Guides -->
        <div class="diagnostic-section">
            <h2>🛠️ Troubleshooting Guides</h2>

            <?php if (!$api_key_ok): ?>
                <div class="fix-box">
                    <div class="fix-title">✕ API Key Not Configured</div>
                    <div class="fix-steps">
                        <li>Go to <strong>https://semaphore.co</strong></li>
                        <li>Login to your account</li>
                        <li>Go to <strong>Settings → API Keys</strong></li>
                        <li>Copy your API key</li>
                        <li>Open <strong>config/sms.php</strong> in VS Code</li>
                        <li>Find line 15: <code>define('SEMAPHORE_API_KEY', ...</code></li>
                        <li>Replace with: <code>define('SEMAPHORE_API_KEY', 'your_key_here');</code></li>
                        <li>Save the file</li>
                        <li>Refresh this page to verify</li>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$sms_logs_exists): ?>
                <div class="fix-box">
                    <div class="fix-title">✕ SMS Logs Table Not Found</div>
                    <div class="fix-steps">
                        <li>Open browser</li>
                        <li>Visit: <strong>http://localhost/ugat/config/create_email_tables.php</strong></li>
                        <li>Wait for success message</li>
                        <li>Check for: "✓ sms_logs table created successfully"</li>
                        <li>Refresh this diagnostic page</li>
                    </div>
                    <div class="button-group">
                        <a href="config/create_email_tables.php" class="btn btn-primary" target="_blank">
                            Create Database Tables
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$db_ok): ?>
                <div class="error-message">
                    <strong>✕ Database Connection Failed</strong><br>
                    Error: <?php echo $conn->connect_error ?? 'Unknown error'; ?><br><br>
                    <strong>Fix:</strong><br>
                    1. Check that MySQL/MariaDB is running in XAMPP<br>
                    2. Verify credentials in config/db.php<br>
                    3. Check database exists: ugat_db<br>
                    4. Restart Apache from XAMPP Control Panel
                </div>
            <?php endif; ?>

            <?php if (!$sms_enabled): ?>
                <div class="warning-message">
                    <strong>[!] SMS Not Enabled</strong><br>
                    SMS_ENABLED is set to false<br><br>
                    <strong>Fix:</strong><br>
                    1. Open config/sms.php<br>
                    2. Find: define('SMS_ENABLED', false);<br>
                    3. Change to: define('SMS_ENABLED', true);<br>
                    4. Save and refresh this page
                </div>
            <?php endif; ?>

        </div>

        <!-- Quick Actions -->
        <div class="diagnostic-section">
            <h2>⚡ Quick Actions</h2>
            <div class="button-group">
                <a href="test_sms_quick.php" class="btn btn-primary" target="_blank">
                    Run Quick Test
                </a>
                <a href="test_sms_helper.php" class="btn btn-primary" target="_blank">
                    Test Helper Functions
                </a>
                <a href="config/create_email_tables.php" class="btn btn-primary" target="_blank">
                    Create DB Tables
                </a>
                <a href="http://localhost/phpmyadmin" class="btn btn-secondary" target="_blank">
                    Open phpMyAdmin
                </a>
            </div>
        </div>

        <!-- Configuration Reference -->
        <div class="diagnostic-section">
            <h2>📋 Configuration Reference</h2>
            
            <h3 style="margin-top: 20px; color: #333;">File: config/sms.php</h3>
            <table>
                <tr>
                    <th>Setting</th>
                    <th>Current Value</th>
                    <th>Expected</th>
                </tr>
                <tr>
                    <td><strong>SEMAPHORE_API_KEY</strong></td>
                    <td><?php echo $api_key_ok ? '✓ Configured' : '✗ Missing'; ?></td>
                    <td>Your API key from semaphore.co</td>
                </tr>
                <tr>
                    <td><strong>SEMAPHORE_SENDER_NAME</strong></td>
                    <td><?php echo defined('SEMAPHORE_SENDER_NAME') ? SEMAPHORE_SENDER_NAME : 'Not set'; ?></td>
                    <td>UGAT (max 11 chars)</td>
                </tr>
                <tr>
                    <td><strong>SMS_PROVIDER</strong></td>
                    <td><?php echo defined('SMS_PROVIDER') ? SMS_PROVIDER : 'Not set'; ?></td>
                    <td>semaphore</td>
                </tr>
                <tr>
                    <td><strong>SMS_ENABLED</strong></td>
                    <td><?php echo SMS_ENABLED ? '✓ true' : '✗ false'; ?></td>
                    <td>true</td>
                </tr>
                <tr>
                    <td><strong>SMS_DEBUG_MODE</strong></td>
                    <td><?php echo SMS_DEBUG_MODE ? 'true (testing)' : 'false (production)'; ?></td>
                    <td>true for testing, false for production</td>
                </tr>
            </table>
        </div>

        <!-- Log Location Help -->
        <div class="diagnostic-section">
            <h2>📂 Finding Debug Output</h2>
            
            <h3 style="margin-top: 15px; color: #333;">If SMS_DEBUG_MODE = true:</h3>
            <p>SMS messages are logged instead of sent. Find them here:</p>
            
            <h4 style="margin-top: 15px;">Windows (XAMPP):</h4>
            <div class="code-block">C:\xampp\php\logs\php_error.log</div>
            <p>Search for: "SMS DEBUG:"</p>
            
            <h4 style="margin-top: 15px;">Linux/Mac:</h4>
            <div class="code-block">/var/log/php-errors.log
# Or run:
tail -f /var/log/php-errors.log | grep "SMS DEBUG"</div>
            
            <h4 style="margin-top: 15px;">Alternative: Check Database</h4>
            <p>SMS logs always save to database (even in debug mode):</p>
            <div class="code-block">SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10;</div>
        </div>

        <!-- Success Criteria -->
        <div class="diagnostic-section">
            <h2>✨ Success Criteria - All Should Be Green</h2>
            
            <table>
                <tr>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
                <tr>
                    <td>Database Connection</td>
                    <td><?php echo $db_ok ? '[✓]' : '[✕]'; ?></td>
                    <td>Must be connected</td>
                </tr>
                <tr>
                    <td>API Key Configured</td>
                    <td><?php echo $api_key_ok ? '[✓]' : '[✕]'; ?></td>
                    <td>From semaphore.co</td>
                </tr>
                <tr>
                    <td>Provider Set</td>
                    <td><?php echo $provider_ok ? '[✓]' : '[✕]'; ?></td>
                    <td>Should be 'semaphore'</td>
                </tr>
                <tr>
                    <td>SMS Enabled</td>
                    <td><?php echo $sms_enabled ? '[✓]' : '[✕]'; ?></td>
                    <td>Should be true</td>
                </tr>
                <tr>
                    <td>SMS Logs Table</td>
                    <td><?php echo $sms_logs_exists ? '[✓]' : '[✕]'; ?></td>
                    <td>Must exist in database</td>
                </tr>
                <tr>
                    <td>Prefs Table</td>
                    <td><?php echo $prefs_exists ? '[✓]' : '[✕]'; ?></td>
                    <td>For user preferences</td>
                </tr>
            </table>
        </div>

        <!-- Next Steps -->
        <div class="diagnostic-section">
            <h2>📍 Next Steps</h2>
            
            <?php if ($overall_status === 'ready'): ?>
                <div class="success-message">
                    <strong>[✓] System is Ready!</strong><br><br>
                    You can now:<br>
                    1. Use SMS functions in your code<br>
                    2. Deploy the notification system<br>
                    3. Monitor SMS logs in database<br>
                    4. Check Semaphore dashboard for message status
                </div>
            <?php else: ?>
                <div class="warning-message">
                    <strong>⚠️ Complete Configuration First</strong><br><br>
                    1. Fix any ❌ items above<br>
                    2. Click "Run Quick Test" to verify<br>
                    3. Check all items turn to ✅<br>
                    4. Then you're ready to deploy
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>
