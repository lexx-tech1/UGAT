<?php
/**
 * Comprehensive SMS API Testing Tool
 * 
 * Verifies all SMS APIs and integrations:
 * - UniSMS API (Primary)
 * - Semaphore API (Backup)
 * - SMS Service Class
 * - Database Logging
 * - Helper Functions
 * - SMS Endpoints
 * 
 * Visit: http://localhost/ugat/test_all_sms_apis.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/sms.php';
require_once 'config/sms_service.php';
require_once 'config/sms_helpers.php';

$test_results = [];
$overall_status = 'PASS';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS API Verification Test Suite</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .test-section {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .test-section-title {
            background: #f5f5f5;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 16px;
            color: #333;
            border-bottom: 2px solid #667eea;
        }
        .test-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .test-item:last-child { border-bottom: none; }
        .test-label {
            flex: 1;
            font-weight: 500;
            color: #333;
        }
        .test-result {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            min-width: 100px;
        }
        .result-pass {
            background: #d4edda;
            color: #155724;
        }
        .result-fail {
            background: #f8d7da;
            color: #721c24;
        }
        .result-warn {
            background: #fff3cd;
            color: #856404;
        }
        .test-details {
            padding: 10px 20px;
            background: #f9f9f9;
            font-size: 12px;
            color: #666;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }
        .status-row {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        .status-box {
            flex: 1;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: white;
            font-weight: bold;
        }
        .status-box.pass { background: #28a745; }
        .status-box.fail { background: #dc3545; }
        .status-box.warn { background: #ffc107; color: #333; }
        .api-credentials {
            background: #f0f8ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .api-credentials h4 { margin-bottom: 10px; color: #333; }
        .api-credentials p { margin: 5px 0; }
        .masked { color: #888; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>📊 SMS API Verification Test Suite</h1>
        <p>Complete verification of all SMS APIs and integrations</p>
    </div>

    <div class="content">

        <!-- API Configuration -->
        <div class="api-credentials">
            <h4>🔑 Configured SMS Providers:</h4>
            <p><strong>Primary Provider:</strong> <?= strtoupper(SMS_PROVIDER) ?></p>
            <p><strong>UniSMS API Key:</strong> 
                <span class="masked"><?= substr(UNISMS_API_KEY, 0, 10) . '...' . substr(UNISMS_API_KEY, -5) ?></span>
            </p>
            <p><strong>Semaphore API Key:</strong> 
                <span class="masked"><?= substr(SEMAPHORE_API_KEY, 0, 10) . '...' . substr(SEMAPHORE_API_KEY, -5) ?></span>
            </p>
            <p><strong>SMS Debug Mode:</strong> <strong><?= SMS_DEBUG_MODE ? 'ENABLED (Testing Mode)' : 'DISABLED (Production)' ?></strong></p>
        </div>

        <!-- TEST 1: Configuration Check -->
        <div class="test-section">
            <div class="test-section-title"><span class="icon icon-check"></span> TEST 1: Configuration Check</div>
            
            <div class="test-item">
                <span class="test-label">SMS Service Enabled</span>
                <span class="test-result result-<?= SMS_ENABLED ? 'pass' : 'fail' ?>">
                    <?= SMS_ENABLED ? 'PASS' : 'FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-label">UniSMS API Key Configured</span>
                <span class="test-result result-<?= !empty(UNISMS_API_KEY) ? 'pass' : 'fail' ?>">
                    <?= !empty(UNISMS_API_KEY) ? 'PASS' : 'FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-label">UniSMS API URL Configured</span>
                <span class="test-result result-<?= !empty(UNISMS_API_URL) ? 'pass' : 'fail' ?>">
                    <?= !empty(UNISMS_API_URL) ? 'PASS' : 'FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-label">Semaphore API Key Configured</span>
                <span class="test-result result-<?= !empty(SEMAPHORE_API_KEY) ? 'pass' : 'fail' ?>">
                    <?= !empty(SEMAPHORE_API_KEY) ? 'PASS' : 'FAIL' ?>
                </span>
            </div>

            <div class="test-item">
                <span class="test-label">Dual Notifications Enabled</span>
                <span class="test-result result-<?= ENABLE_DUAL_NOTIFICATIONS ? 'pass' : 'warn' ?>">
                    <?= ENABLE_DUAL_NOTIFICATIONS ? 'ENABLED' : 'DISABLED' ?>
                </span>
            </div>
        </div>

        <!-- TEST 2: Database Check -->
        <div class="test-section">
            <div class="test-section-title">🗄️ TEST 2: Database Tables</div>
            
            <?php
            $tables = ['sms_logs', 'trainee_sms_log', 'notification_preferences'];
            foreach ($tables as $table) {
                $result = $conn->query("SELECT COUNT(*) FROM $table");
                $exists = $result !== false;
                $count = $exists ? $result->fetch_row()[0] : 0;
            ?>
                <div class="test-item">
                    <span class="test-label">Table: <strong><?= $table ?></strong></span>
                    <span class="test-result result-<?= $exists ? 'pass' : 'fail' ?>">
                        <?= $exists ? "EXISTS ($count rows)" : 'MISSING' ?>
                    </span>
                </div>
            <?php } ?>
        </div>

        <!-- TEST 3: SMS Service Class -->
        <div class="test-section">
            <div class="test-section-title">⚙️ TEST 3: SMS Service Class</div>
            
            <?php
            $smsService = new SmsService($conn);
            $serviceOk = $smsService !== null;
            ?>
                <div class="test-item">
                    <span class="test-label">SmsService Class Instantiated</span>
                    <span class="test-result result-<?= $serviceOk ? 'pass' : 'fail' ?>">
                        <?= $serviceOk ? 'PASS' : 'FAIL' ?>
                    </span>
                </div>

                <div class="test-item">
                    <span class="test-label">Service has sendSms() method</span>
                    <span class="test-result result-<?= method_exists($smsService, 'sendSms') ? 'pass' : 'fail' ?>">
                        <?= method_exists($smsService, 'sendSms') ? 'PASS' : 'FAIL' ?>
                    </span>
                </div>

                <div class="test-item">
                    <span class="test-label">Service has sendSmsToMultiple() method</span>
                    <span class="test-result result-<?= method_exists($smsService, 'sendSmsToMultiple') ? 'pass' : 'fail' ?>">
                        <?= method_exists($smsService, 'sendSmsToMultiple') ? 'PASS' : 'FAIL' ?>
                    </span>
                </div>
        </div>

        <!-- TEST 4: Helper Functions -->
        <div class="test-section">
            <div class="test-section-title">📞 TEST 4: SMS Helper Functions</div>
            
            <?php
            $helpers = [
                'sendOrderPlacedNotification',
                'sendOrderShippedNotification',
                'sendOrderDeliveredNotification',
                'sendWorkshopEnrollmentNotification',
                'sendWorkshopReminderNotification',
                'sendCertificationIssuedNotification',
                'sendPaymentReceivedNotification',
                'sendAdminAlertNotification'
            ];

            foreach ($helpers as $func) {
                $exists = function_exists($func);
            ?>
                <div class="test-item">
                    <span class="test-label">Function: <strong><?= $func ?>()</strong></span>
                    <span class="test-result result-<?= $exists ? 'pass' : 'fail' ?>">
                        <?= $exists ? 'EXISTS' : 'MISSING' ?>
                    </span>
                </div>
            <?php } ?>
        </div>

        <!-- TEST 5: API Connectivity -->
        <div class="test-section">
            <div class="test-section-title">🌐 TEST 5: API Connectivity</div>
            
            <?php
            // Test UniSMS API connectivity
            $unisms_test = testUniSmsApi();
            ?>
                <div class="test-item">
                    <span class="test-label">UniSMS API Reachable</span>
                    <span class="test-result result-<?= $unisms_test['reachable'] ? 'pass' : 'fail' ?>">
                        <?= $unisms_test['reachable'] ? 'PASS' : 'FAIL' ?>
                    </span>
                </div>
                <div class="test-details">
                    Status: <?= $unisms_test['status'] ?><br>
                    Response Time: <?= $unisms_test['time_ms'] ?>ms
                </div>

            <?php
            // Test Semaphore API connectivity
            $semaphore_test = testSemaphoreApi();
            ?>
                <div class="test-item">
                    <span class="test-label">Semaphore API Reachable</span>
                    <span class="test-result result-<?= $semaphore_test['reachable'] ? 'pass' : 'fail' ?>">
                        <?= $semaphore_test['reachable'] ? 'PASS' : 'FAIL' ?>
                    </span>
                </div>
                <div class="test-details">
                    Status: <?= $semaphore_test['status'] ?><br>
                    Response Time: <?= $semaphore_test['time_ms'] ?>ms
                </div>
        </div>

        <!-- TEST 6: SMS Endpoints -->
        <div class="test-section">
            <div class="test-section-title">🔌 TEST 6: SMS API Endpoints</div>
            
            <?php
            $endpoints = [
                'pages/admin/send_sms_notification.php',
                'pages/admin/get_sms_notifications.php',
                'pages/trainee/get_sms_notifications.php',
                'config/create_sms_tables.php'
            ];

            foreach ($endpoints as $endpoint) {
                $path = __DIR__ . '/' . $endpoint;
                $exists = file_exists($path);
            ?>
                <div class="test-item">
                    <span class="test-label">Endpoint: <strong><?= $endpoint ?></strong></span>
                    <span class="test-result result-<?= $exists ? 'pass' : 'fail' ?>">
                        <?= $exists ? 'EXISTS' : 'MISSING' ?>
                    </span>
                </div>
            <?php } ?>
        </div>

        <!-- Summary -->
        <div class="status-row">
            <div class="status-box pass">
                <span class="icon icon-check"></span> All Tests Completed<br>
                Check results above
            </div>
        </div>

    </div>
</div>

</body>
</html>

<?php

/**
 * Test UniSMS API connectivity
 */
function testUniSmsApi() {
    $start = microtime(true);
    
    $ch = curl_init(UNISMS_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $end = microtime(true);
    $time_ms = round(($end - $start) * 1000);
    
    return [
        'reachable' => $http_code > 0 && $http_code < 500,
        'status' => $error ?: "HTTP $http_code",
        'time_ms' => $time_ms
    ];
}

/**
 * Test Semaphore API connectivity
 */
function testSemaphoreApi() {
    $start = microtime(true);
    
    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $end = microtime(true);
    $time_ms = round(($end - $start) * 1000);
    
    return [
        'reachable' => $http_code > 0 && $http_code < 500,
        'status' => $error ?: "HTTP $http_code",
        'time_ms' => $time_ms
    ];
}

?>
