<?php
/**
 * Quick SMS Test - Run this to test SMS immediately
 * 
 * Visit: http://localhost/ugat/test_sms_quick.php
 * 
 * This file tests:
 * 1. Semaphore API connection
 * 2. SMS sending capability
 * 3. Database logging
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>UGAT SMS Quick Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4B8423;
            border-bottom: 3px solid #4B8423;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            font-size: 18px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
        }
        .code {
            font-family: 'Courier New', monospace;
            background-color: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid #dee2e6;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
            margin-right: 10px;
        }
        .status-ok {
            background-color: #28a745;
            color: white;
        }
        .status-error {
            background-color: #dc3545;
            color: white;
        }
        .status-warning {
            background-color: #ffc107;
            color: black;
        }
        .test-item {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #4B8423;
            background-color: #f9f9f9;
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
        tr:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🧪 UGAT SMS Quick Test</h1>
    <p>This test verifies your SMS notification system is working correctly.</p>

    <?php
    // Include required files
    require_once 'config/db.php';
    require_once 'config/sms.php';
    require_once 'config/sms_service.php';

    echo '<h2>Test Status</h2>';

    // Test 1: Check database connection
    echo '<div class="test-item">';
    echo '<h3>Test 1: Database Connection</h3>';
    
    if ($conn && !$conn->connect_error) {
        echo '<span class="status-badge status-ok">✓ PASS</span>';
        echo '<p class="success"><strong>[✓] Database connected successfully</strong></p>';
    } else {
        echo '<span class="status-badge status-error">✗ FAIL</span>';
        echo '<p class="error"><strong>✕ Database connection failed</strong></p>';
        echo '<p>Error: ' . ($conn->connect_error ?? 'Unknown error') . '</p>';
        exit;
    }
    echo '</div>';

    // Test 2: Check Semaphore API Key
    echo '<div class="test-item">';
    echo '<h3>Test 2: Semaphore API Key Configuration</h3>';
    
    if (defined('SEMAPHORE_API_KEY') && SEMAPHORE_API_KEY !== 'your_semaphore_api_key_here') {
        echo '<span class="status-badge status-ok">✓ PASS</span>';
        echo '<p class="success"><strong>[✓] API Key configured</strong></p>';
        echo '<p>API Key (first 10 chars): <span class="code">' . substr(SEMAPHORE_API_KEY, 0, 10) . '...</span></p>';
    } else {
        echo '<span class="status-badge status-error">✗ FAIL</span>';
        echo '<p class="error"><strong>✕ API Key not configured</strong></p>';
        echo '<p>Edit <span class="code">config/sms.php</span> and set SEMAPHORE_API_KEY to your actual API key from semaphore.co</p>';
    }
    echo '</div>';

    // Test 3: Check SMS Provider
    echo '<div class="test-item">';
    echo '<h3>Test 3: SMS Provider Configuration</h3>';
    
    if (defined('SMS_PROVIDER') && SMS_PROVIDER === 'semaphore') {
        echo '<span class="status-badge status-ok">✓ PASS</span>';
        echo '<p class="success"><strong>[✓] Semaphore provider configured</strong></p>';
        echo '<p>Provider: <span class="code">' . SMS_PROVIDER . '</span></p>';
    } else {
        echo '<span class="status-badge status-error">✗ FAIL</span>';
        echo '<p class="error"><strong>✕ Semaphore provider not configured</strong></p>';
    }
    echo '</div>';

    // Test 4: Check Debug Mode
    echo '<div class="test-item">';
    echo '<h3>Test 4: Debug Mode Status</h3>';
    
    if (defined('SMS_DEBUG_MODE') && SMS_DEBUG_MODE) {
        echo '<span class="status-badge status-warning">⚠ WARNING</span>';
        echo '<p class="warning"><strong>[!] Debug mode is ENABLED</strong></p>';
        echo '<p>SMS will be logged instead of actually sent. This is good for testing!</p>';
        echo '<p>Check error_log for debug output.</p>';
    } elseif (defined('SMS_DEBUG_MODE') && !SMS_DEBUG_MODE) {
        echo '<span class="status-badge status-ok">✓ PASS</span>';
        echo '<p class="success"><strong>[✓] Debug mode is DISABLED</strong></p>';
        echo '<p>SMS will actually be sent via Semaphore API.</p>';
    }
    echo '</div>';

    // Test 5: Check SMS Logs Table
    echo '<div class="test-item">';
    echo '<h3>Test 5: SMS Logs Table</h3>';
    
    $result = $conn->query("SHOW TABLES LIKE 'sms_logs'");
    if ($result && $result->num_rows > 0) {
        echo '<span class="status-badge status-ok">✓ PASS</span>';
        echo '<p class="success"><strong>[✓] SMS logs table exists</strong></p>';
        
        // Count records
        $count_result = $conn->query("SELECT COUNT(*) as count FROM sms_logs");
        $count_row = $count_result->fetch_assoc();
        echo '<p>Current records: <strong>' . $count_row['count'] . '</strong></p>';
    } else {
        echo '<span class="status-badge status-error">✗ FAIL</span>';
        echo '<p class="error"><strong>✕ SMS logs table not found</strong></p>';
        echo '<p>Run: <span class="code">http://localhost/ugat/config/create_email_tables.php</span></p>';
    }
    echo '</div>';

    // Test 6: Test SMS Send
    echo '<div class="test-item">';
    echo '<h3>Test 6: Send Test SMS</h3>';
    
    $sms = getSmsService($conn);
    $test_result = $sms->sendSms(
        '+639123456789',
        'Hello UGAT! This is a test SMS from your notification system. Sent at ' . date('Y-m-d H:i:s'),
        ['test' => true, 'test_time' => date('Y-m-d H:i:s')]
    );
    
    if ($test_result['success']) {
        echo '<span class="status-badge status-ok">✓ PASS</span>';
        echo '<p class="success"><strong>[✓] SMS sent successfully!</strong></p>';
        echo '<p>Message ID: <span class="code">' . $test_result['sms_id'] . '</span></p>';
        echo '<p>Status: <span class="code">' . $test_result['message'] . '</span></p>';
    } else {
        echo '<span class="status-badge status-error">✗ FAIL</span>';
        echo '<p class="error"><strong>✕ SMS send failed</strong></p>';
        echo '<p>Error: ' . $test_result['message'] . '</p>';
    }
    echo '</div>';

    // Test 7: Check Recent SMS Logs
    echo '<div class="test-item">';
    echo '<h3>Test 7: Recent SMS Logs</h3>';
    
    $logs = $conn->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 5");
    if ($logs && $logs->num_rows > 0) {
        echo '<span class="status-badge status-ok">✓ PASS</span>';
        echo '<p class="success"><strong>[✓] SMS logs are being recorded</strong></p>';
        
        echo '<table>';
        echo '<tr><th>Date/Time</th><th>Phone</th><th>Status</th><th>Message Preview</th></tr>';
        
        while ($log = $logs->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $log['sent_at'] . '</td>';
            echo '<td><span class="code">' . $log['phone_number'] . '</span></td>';
            echo '<td>' . $log['status'] . '</td>';
            echo '<td>' . substr($log['message'], 0, 50) . '...</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    } else {
        echo '<span class="status-badge status-warning">⚠ WARNING</span>';
        echo '<p class="warning"><strong>[!] No SMS logs found yet</strong></p>';
        echo '<p>Try sending an SMS above first.</p>';
    }
    echo '</div>';

    // Summary
    echo '<h2>Summary</h2>';
    
    $all_ok = ($conn && !$conn->connect_error) && 
              (defined('SEMAPHORE_API_KEY') && SEMAPHORE_API_KEY !== 'your_semaphore_api_key_here') &&
              (defined('SMS_PROVIDER') && SMS_PROVIDER === 'semaphore') &&
              $test_result['success'];
    
    if ($all_ok) {
        echo '<div class="success">';
        echo '<h3>[✓] All Tests Passed!</h3>';
        echo '<p>Your SMS notification system is working correctly and ready to use.</p>';
        echo '<p><strong>Next steps:</strong></p>';
        echo '<ul>';
        echo '<li>Use SMS functions in your code (e.g., sendOrderPlacedNotificationDual())</li>';
        echo '<li>Monitor SMS logs in database</li>';
        echo '<li>Check Semaphore dashboard for sent messages</li>';
        echo '<li>Set up email notifications (similar process)</li>';
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div class="error">';
        echo '<h3>✕ Some Tests Failed</h3>';
        echo '<p>Please fix the errors above and run this test again.</p>';
        echo '<p>Common issues:</p>';
        echo '<ul>';
        echo '<li>API key not configured in config/sms.php</li>';
        echo '<li>Database tables not created - run config/create_email_tables.php</li>';
        echo '<li>Database connection issue - check config/db.php</li>';
        echo '</ul>';
        echo '</div>';
    }
    ?>

    <h2>Additional Resources</h2>
    <div class="info">
        <p><strong>📖 Documentation:</strong></p>
        <ul>
            <li><a href="SMS_COMPLETE_SETUP.md">Complete SMS Setup Guide</a></li>
            <li><a href="QUICK_REFERENCE.php">Quick Reference Code Examples</a></li>
            <li><a href="TESTING_GUIDE.md">Testing Guide</a></li>
            <li><a href="IMPLEMENTATION_COMPLETE.md">Full Implementation Details</a></li>
        </ul>
    </div>

    <div class="info">
        <p><strong>🔗 Useful Links:</strong></p>
        <ul>
            <li><a href="https://semaphore.co" target="_blank">Semaphore API - Get API Key</a></li>
            <li><a href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin - Database</a></li>
            <li><a href="config/create_email_tables.php" target="_blank">Create Database Tables</a></li>
        </ul>
    </div>

</div>

</body>
</html>
