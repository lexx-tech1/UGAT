<?php
/**
 * Direct Semaphore API Test
 * Tests if SMS can actually be sent to your phone
 */

require_once 'config/sms.php';
require_once 'config/db.php';

$test_number = $_GET['phone'] ?? '+639123456789';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semaphore Direct API Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .test-box { background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #007bff; }
        input { padding: 10px; width: 100%; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .divider { border-top: 2px solid #ddd; margin: 30px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Semaphore Direct API Test</h1>
        <p>Tests direct communication with Semaphore API to identify SMS delivery issues.</p>

        <!-- Step 1: Check Configuration -->
        <div class="test-box">
            <h2>Step 1: Configuration Check</h2>
            <?php
            if (empty(SEMAPHORE_API_KEY)) {
                echo '<div class="status error">✕ SEMAPHORE_API_KEY is NOT SET</div>';
                echo '<p><strong>Action Required:</strong> Edit <code>config/sms.php</code> and add your API key:</p>';
                echo '<pre>define(\'SEMAPHORE_API_KEY\', \'your_actual_api_key_here\');</pre>';
            } else {
                $key_preview = substr(SEMAPHORE_API_KEY, 0, 5) . '...' . substr(SEMAPHORE_API_KEY, -5);
                echo '<div class="status success"><span class="icon icon-check"></span> SEMAPHORE_API_KEY is SET</div>';
                echo '<p>API Key (masked): <code>' . $key_preview . '</code></p>';
            }
            
            echo '<p><strong>Sender Name:</strong> <code>' . SEMAPHORE_SENDER_NAME . '</code></p>';
            echo '<p><strong>SMS Provider:</strong> <code>' . SMS_PROVIDER . '</code></p>';
            echo '<p><strong>Debug Mode:</strong> <code>' . (SMS_DEBUG_MODE ? 'ENABLED' : 'DISABLED') . '</code></p>';
            ?>
        </div>

        <!-- Step 2: Test Phone Number -->
        <div class="test-box">
            <h2>Step 2: Phone Number Format Check</h2>
            <form method="GET">
                <label>Enter phone number to test (with +63):</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($test_number); ?>" placeholder="+639123456789">
                <button type="submit">Test Phone Format</button>
            </form>

            <?php
            // Test phone format
            function validatePhoneFormat($phone) {
                // Remove spaces
                $phone = str_replace(' ', '', $phone);
                
                // Check if starts with +63 or 0
                if (preg_match('/^\+63\d{9,10}$/', $phone)) {
                    return ['valid' => true, 'formatted' => $phone, 'message' => 'Valid Philippine number with +63'];
                } elseif (preg_match('/^0\d{10}$/', $phone)) {
                    // Convert 0 to +63
                    $formatted = '+63' . substr($phone, 1);
                    return ['valid' => true, 'formatted' => $formatted, 'message' => 'Valid Philippine number, converted from 0 to +63'];
                } else {
                    return ['valid' => false, 'formatted' => $phone, 'message' => 'Invalid format. Use +639XXXXXXXXX or 09XXXXXXXXX'];
                }
            }

            $phone_check = validatePhoneFormat($test_number);
            if ($phone_check['valid']) {
                echo '<div class="status success"><span class="icon icon-check"></span> ' . $phone_check['message'] . '</div>';
                echo '<p><strong>Formatted Phone:</strong> <code>' . $phone_check['formatted'] . '</code></p>';
            } else {
                echo '<div class="status error">✕ ' . $phone_check['message'] . '</div>';
            }
            ?>
        </div>

        <!-- Step 3: Send Test SMS -->
        <div class="test-box">
            <h2>Step 3: Send Test SMS</h2>
            <?php
            if (!empty(SEMAPHORE_API_KEY) && $phone_check['valid']) {
                echo '<p>Ready to send test SMS. Click button below:</p>';
                echo '<form method="POST">';
                echo '<input type="hidden" name="phone" value="' . htmlspecialchars($test_number) . '">';
                echo '<input type="hidden" name="send_sms" value="1">';
                echo '<button type="submit">Send Test SMS Now</button>';
                echo '</form>';

                // Handle SMS sending
                if ($_POST['send_sms'] ?? false) {
                    $phone = $_POST['phone'] ?? $test_number;
                    $formatted_phone = validatePhoneFormat($phone)['formatted'];
                    // Convert +63 to 0 for Semaphore
                    $semaphore_phone = preg_replace('/^\+63/', '0', $formatted_phone);
                    $message = 'UGAT Test Message: Your SMS system is working correctly! - ' . date('H:i:s');

                    echo '<div class="divider"></div>';
                    echo '<h3>Sending SMS...</h3>';
                    echo '<pre>';
                    echo 'To (formatted): ' . $formatted_phone . "\n";
                    echo 'To (Semaphore format): ' . $semaphore_phone . "\n";
                    echo 'Message: ' . $message . "\n";
                    echo 'Sender: ' . SEMAPHORE_SENDER_NAME . "\n";
                    echo 'API Key: ' . (defined('SEMAPHORE_API_KEY') ? substr(SEMAPHORE_API_KEY, 0, 10) . '...' : 'NOT SET') . "\n";
                    echo '</pre>';

                    // Make API request
                    $post_fields = [
                        'apikey' => SEMAPHORE_API_KEY,
                        'number' => $semaphore_phone,
                        'message' => $message,
                        'sendername' => SEMAPHORE_SENDER_NAME
                    ];

                    echo '<h4>POST Data:</h4>';
                    echo '<pre>';
                    foreach ($post_fields as $k => $v) {
                        if ($k === 'apikey') {
                            echo $k . ': ' . substr($v, 0, 10) . "...\n";
                        } else {
                            echo $k . ': ' . $v . "\n";
                        }
                    }
                    echo '</pre>';

                    $ch = curl_init();
                    curl_setopt_array($ch, array(
                        CURLOPT_URL => 'https://api.semaphore.co/api/sms/send',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query($post_fields),
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 0
                    ));

                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    curl_close($ch);

                    echo '<div class="divider"></div>';
                    echo '<h3>API Response:</h3>';

                    // Check for curl errors
                    if ($curl_error) {
                        echo '<div class="status error">✕ CURL Error: ' . htmlspecialchars($curl_error) . '</div>';
                    } elseif ($http_code != 200) {
                        echo '<div class="status error">✕ HTTP Error: ' . $http_code . '</div>';
                        echo '<p><strong>Common causes:</strong></p>';
                        echo '<ul>';
                        echo '<li>Invalid API Key - check Semaphore dashboard</li>';
                        echo '<li>Account not active - verify account at semaphore.co</li>';
                        echo '<li>Insufficient SMS balance - add credit to your account</li>';
                        echo '</ul>';
                    } else {
                        echo '<div class="status success"><span class="icon icon-check"></span> HTTP 200 - Request successful</div>';
                    }

                    // Parse response
                    echo '<h4>Raw Response:</h4>';
                    echo '<pre>' . htmlspecialchars($response) . '</pre>';

                    // Try to parse JSON
                    $json = @json_decode($response, true);
                    if ($json) {
                        echo '<h4>Parsed Response:</h4>';
                        echo '<pre>' . json_encode($json, JSON_PRETTY_PRINT) . '</pre>';

                        if (isset($json[0]['status'])) {
                            if ($json[0]['status'] == 'success') {
                                echo '<div class="status success"><span class="icon icon-check"></span> SMS sent successfully!</div>';
                                echo '<p><strong>Message ID:</strong> ' . ($json[0]['message_id'] ?? 'N/A') . '</p>';
                                echo '<p><strong>Next Step:</strong> Check your phone for the SMS message.</p>';
                            } else {
                                echo '<div class="status error">✕ Status: ' . htmlspecialchars($json[0]['status']) . '</div>';
                                if (isset($json[0]['result'])) {
                                    echo '<p><strong>Result:</strong> ' . htmlspecialchars($json[0]['result']) . '</p>';
                                }
                            }
                        }
                    }

                    // Log to database
                    try {
                        $stmt = $conn->prepare("INSERT INTO sms_logs (phone_number, message, status, metadata, sent_at) VALUES (?, ?, ?, ?, NOW())");
                        $status = ($json && isset($json[0]['status']) && $json[0]['status'] == 'success') ? 'test_sent' : 'test_failed';
                        $metadata = json_encode(['test' => true, 'http_code' => $http_code]);
                        $stmt->bind_param('ssss', $semaphore_phone, $message, $status, $metadata);
                        $stmt->execute();
                        echo '<p style="color: #666; margin-top: 15px;">✓ Test logged to sms_logs table</p>';
                    } catch (Exception $e) {
                        echo '<p style="color: #c00;">Could not log to database: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                }
            } else {
                if (empty(SEMAPHORE_API_KEY)) {
                    echo '<div class="status error">✕ Cannot send: API key not configured</div>';
                } else {
                    echo '<div class="status error">✕ Cannot send: Invalid phone number format</div>';
                }
            }
            ?>
        </div>

        <!-- Step 4: Check Database Logs -->
        <div class="test-box">
            <h2>Step 4: Recent SMS Logs in Database</h2>
            <?php
            try {
                $result = $conn->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10");
                if ($result && $result->num_rows > 0) {
                    echo '<table style="width: 100%; border-collapse: collapse;">';
                    echo '<tr style="background: #f0f0f0;"><th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Time</th><th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Phone</th><th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Status</th><th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Message Preview</th></tr>';
                    while ($row = $result->fetch_assoc()) {
                        $msg_preview = substr($row['message'], 0, 50) . (strlen($row['message']) > 50 ? '...' : '');
                        echo '<tr><td style="border: 1px solid #ddd; padding: 10px;">' . htmlspecialchars($row['sent_at']) . '</td>';
                        echo '<td style="border: 1px solid #ddd; padding: 10px;">' . htmlspecialchars($row['phone_number']) . '</td>';
                        echo '<td style="border: 1px solid #ddd; padding: 10px;"><code>' . htmlspecialchars($row['status']) . '</code></td>';
                        echo '<td style="border: 1px solid #ddd; padding: 10px;">' . htmlspecialchars($msg_preview) . '</td></tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="status info">ℹ️ No SMS logs found yet</div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">✕ Error reading logs: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- Troubleshooting -->
        <div class="test-box">
            <h2><span class="icon icon-tool"></span> Troubleshooting</h2>
            <ul>
                <li><strong>API Key Invalid:</strong> Check your Semaphore account at https://semaphore.co and copy the correct API key</li>
                <li><strong>Phone Number Error:</strong> Use format +639XXXXXXXXX or 09XXXXXXXXX</li>
                <li><strong>Request Timeout:</strong> Check your internet connection</li>
                <li><strong>Insufficient Balance:</strong> Log into Semaphore dashboard and check your SMS credits</li>
                <li><strong>Message Not Received:</strong> 
                    <ul>
                        <li>Check if Semaphore reports "success"</li>
                        <li>Wait 2-3 minutes for delivery</li>
                        <li>Check spam folder on your phone</li>
                        <li>Verify phone number is correct</li>
                        <li>Contact Semaphore support if issue persists</li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="divider"></div>
        <p style="color: #666; font-size: 14px;">
            <strong>Questions?</strong> Check the SMS setup guide at <code>SMS_COMPLETE_SETUP.md</code> or visit Semaphore docs at <code>https://semaphore.co/docs</code>
        </p>
    </div>
</body>
</html>
