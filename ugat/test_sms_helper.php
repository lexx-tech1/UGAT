<?php
/**
 * SMS Helper Functions Test
 * 
 * Visit: http://localhost/ugat/test_sms_helper.php
 * 
 * This file tests:
 * 1. SMS notification helper functions
 * 2. Database logging
 * 3. Semaphore API integration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>UGAT SMS Helper Functions Test</title>
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
        .test-result {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #4B8423;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .warning {
            background-color: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .info {
            background-color: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
            margin: 10px 0;
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
            margin-bottom: 10px;
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
        .notification-type {
            font-weight: bold;
            color: #4B8423;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>📱 UGAT SMS Helper Functions Test</h1>
    <p>This test verifies all SMS notification helper functions are working correctly.</p>

    <?php
    require_once 'config/db.php';
    require_once 'config/sms.php';
    require_once 'config/sms_helpers.php';

    echo '<h2>Notification Helper Tests</h2>';

    // Get a test user (just id, we'll use a default test phone)
    $user_result = $conn->query("SELECT id FROM users WHERE is_active = 1 LIMIT 1");
    
    if (!$user_result || $user_result->num_rows === 0) {
        echo '<div class="test-result error">';
        echo '<span class="status-badge status-error">✗ FAIL</span>';
        echo '<h3>No Active Users Found</h3>';
        echo '<p>Please ensure there is at least one active user in the users table.</p>';
        echo '</div>';
        exit;
    }

    $user = $user_result->fetch_assoc();
    $user_id = $user['id'];
    $user_phone = '+639123456789';  // Test phone number

    // Ensure test user has phone number and notification preferences configured
    $update_user = $conn->query("UPDATE users SET phone = '$user_phone' WHERE id = $user_id");
    
    if (!$update_user) {
        echo '<div class="test-result warning">';
        echo '<p>[!] Warning: Could not update users table. Trying notification_preferences table...</p>';
        echo '</div>';
    }

    // Also ensure notification_preferences has SMS enabled for this user
    $check_prefs = $conn->query("SELECT id FROM notification_preferences WHERE user_id = $user_id");
    
    if ($check_prefs && $check_prefs->num_rows === 0) {
        // Create notification preference for test user
        $conn->query("INSERT INTO notification_preferences (user_id, email, phone_enabled, email_enabled, email_verified, created_at, updated_at) 
                      VALUES ($user_id, 'test@ugat.test', 1, 0, 0, NOW(), NOW())");
    } else {
        // Update to ensure SMS is enabled
        $conn->query("UPDATE notification_preferences SET phone_enabled = 1 WHERE user_id = $user_id");
    }

    echo '<p><strong>Using Test User:</strong> ID=' . $user_id . ', Phone=' . $user_phone . '</p>';
    echo '<p style="color: #666; font-size: 0.9em;">✓ Test phone stored in database for user</p>';

    // Array of tests
    $tests = [
        [
            'name' => 'Order Placed Notification',
            'function' => 'sendOrderPlacedNotification',
            'args' => [$user_id, 'ORD-2026-TEST-001', '₱2,500']
        ],
        [
            'name' => 'Order Shipped Notification',
            'function' => 'sendOrderShippedNotification',
            'args' => [$user_id, 'ORD-2026-TEST-001', 'https://track.test.com']
        ],
        [
            'name' => 'Order Delivered Notification',
            'function' => 'sendOrderDeliveredNotification',
            'args' => [$user_id, 'ORD-2026-TEST-001']
        ],
        [
            'name' => 'Workshop Enrollment Notification',
            'function' => 'sendWorkshopEnrollmentNotification',
            'args' => [$user_id, 'Hydroponics 101', '2026-06-15', 'https://test.com/workshop']
        ],
        [
            'name' => 'Workshop Reminder Notification',
            'function' => 'sendWorkshopReminderNotification',
            'args' => [$user_id, 'Hydroponics 101', '2026-06-15', '2:00 PM']
        ],
        [
            'name' => 'Certification Issued Notification',
            'function' => 'sendCertificationIssuedNotification',
            'args' => [$user_id, 'Hydroponics 101', 'https://cert.test.com']
        ],
        [
            'name' => 'Payment Received Notification',
            'function' => 'sendPaymentReceivedNotification',
            'args' => [$user_id, '₱2,500', 'ORD-2026-TEST-001']
        ],
    ];

    // Run each test
    $passed = 0;
    $failed = 0;

    foreach ($tests as $test) {
        echo '<div class="test-result';
        
        $function_name = $test['function'];
        
        if (!function_exists($function_name)) {
            echo ' error">';
            echo '<span class="status-badge status-error">✗ FAIL</span>';
            echo '<h3>' . $test['name'] . '</h3>';
            echo '<p><strong>Error:</strong> Function ' . $function_name . '() not found</p>';
            echo '</div>';
            $failed++;
            continue;
        }

        // Call the function
        $result = call_user_func_array($function_name, $test['args']);
        
        if (isset($result['success']) && $result['success']) {
            echo ' success">';
            echo '<span class="status-badge status-ok">✓ PASS</span>';
            echo '<h3>' . $test['name'] . '</h3>';
            echo '<p><strong>Status:</strong> ' . $result['message'] . '</p>';
            if (isset($result['sms_id'])) {
                echo '<p><strong>SMS ID:</strong> <span class="code">' . $result['sms_id'] . '</span></p>';
            }
            $passed++;
        } else {
            echo ' error">';
            echo '<span class="status-badge status-error">✗ FAIL</span>';
            echo '<h3>' . $test['name'] . '</h3>';
            echo '<p><strong>Error:</strong> ' . ($result['message'] ?? 'Unknown error') . '</p>';
            $failed++;
        }
        
        echo '</div>';
    }

    // Summary
    echo '<h2>Test Summary</h2>';
    echo '<div class="test-result info">';
    echo '<p><strong>Total Tests:</strong> ' . count($tests) . '</p>';
    echo '<p><strong>Passed:</strong> <span style="color: #28a745; font-weight: bold;">' . $passed . '</span></p>';
    echo '<p><strong>Failed:</strong> <span style="color: #dc3545; font-weight: bold;">' . $failed . '</span></p>';
    
    if ($failed === 0 && $passed > 0) {
        echo '<p style="margin-top: 15px;"><strong>[✓] All tests passed!</strong></p>';
        echo '<p>Your SMS notification helper functions are working correctly.</p>';
    } else {
        echo '<p style="margin-top: 15px;"><strong>✕ Some tests failed.</strong></p>';
        echo '<p>Check the errors above and verify your configuration.</p>';
    }
    
    echo '</div>';

    // Show recent logs
    echo '<h2>Recent SMS Logs</h2>';
    
    $logs = $conn->query("
        SELECT id, sent_at, phone_number, message, sms_id, status 
        FROM sms_logs 
        ORDER BY sent_at DESC 
        LIMIT 10
    ");

    if ($logs && $logs->num_rows > 0) {
        echo '<table>';
        echo '<tr>';
        echo '<th>Timestamp</th>';
        echo '<th>Phone</th>';
        echo '<th>Status</th>';
        echo '<th>Message Preview</th>';
        echo '</tr>';

        while ($log = $logs->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $log['sent_at'] . '</td>';
            echo '<td><span class="code">' . $log['phone_number'] . '</span></td>';
            echo '<td>' . $log['status'] . '</td>';
            echo '<td>' . substr($log['message'], 0, 60) . '...</td>';
            echo '</tr>';
        }

        echo '</table>';
    } else {
        echo '<div class="test-result warning">';
        echo '<p>No SMS logs found yet. Try running tests above first.</p>';
        echo '</div>';
    }

    // Debug info
    echo '<h2>Debug Information</h2>';
    echo '<div class="info test-result">';
    echo '<p><strong>SMS_DEBUG_MODE:</strong> <span class="code">' . (SMS_DEBUG_MODE ? 'true' : 'false') . '</span></p>';
    echo '<p><strong>SMS_ENABLED:</strong> <span class="code">' . (SMS_ENABLED ? 'true' : 'false') . '</span></p>';
    echo '<p><strong>SMS_PROVIDER:</strong> <span class="code">' . SMS_PROVIDER . '</span></p>';
    echo '<p><strong>API Key Set:</strong> <span class="code">' . (SEMAPHORE_API_KEY !== 'your_semaphore_api_key_here' ? 'Yes' : 'No') . '</span></p>';
    
    if (SMS_DEBUG_MODE) {
        echo '<p><strong style="color: #ffc107;">[!] Debug mode is enabled - SMS are logged to error_log instead of sent</strong></p>';
    }
    
    echo '</div>';

    ?>

</div>

</body>
</html>
