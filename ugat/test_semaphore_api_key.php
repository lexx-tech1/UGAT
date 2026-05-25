<?php
/**
 * Quick Semaphore API Key Validator
 * Helps verify if your API key is valid
 */

require_once 'config/sms.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semaphore API Key Validator</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .status { padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid; }
        .success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .warning { background: #fff3cd; color: #856404; border-left-color: #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .checklist { margin: 20px 0; }
        .checklist li { margin: 10px 0; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 Semaphore API Key Validator</h1>
        
        <div class="status info">
            ℹ️ This tool checks if your Semaphore API key is valid and your account is active.
        </div>

        <h2>Current Configuration</h2>
        <pre>
API Key Set: <?php echo defined('SEMAPHORE_API_KEY') && !empty(SEMAPHORE_API_KEY) ? '✓ Yes' : '✗ No'; ?>
Provider: <?php echo SMS_PROVIDER; ?>
Sender Name: <?php echo SEMAPHORE_SENDER_NAME; ?>
        </pre>

        <h2>Quick Checklist</h2>
        <ol class="checklist">
            <li>
                <strong>Do you have a Semaphore account?</strong><br>
                If not, sign up at <a href="https://semaphore.co" target="_blank">https://semaphore.co</a>
            </li>
            
            <li>
                <strong>Is your API key correct?</strong>
                <?php
                if (defined('SEMAPHORE_API_KEY')) {
                    $key = SEMAPHORE_API_KEY;
                    if (empty($key) || $key === 'your_semaphore_api_key_here') {
                        echo '<div class="status error">✕ API key is NOT set or is the placeholder value</div>';
                        echo '<p>Go to <a href="https://semaphore.co" target="_blank">semaphore.co</a>, login, and copy your API key to <code>config/sms.php</code></p>';
                    } else {
                        echo '<div class="status success">✓ API key is set (preview: ' . substr($key, 0, 8) . '...)</div>';
                    }
                }
                ?>
            </li>

            <li>
                <strong>Does your account have SMS balance?</strong><br>
                Check your Semaphore dashboard at <a href="https://semaphore.co/dashboard" target="_blank">https://semaphore.co/dashboard</a>
                <ul>
                    <li>Free accounts get limited credits</li>
                    <li>Add credit if balance is 0</li>
                </ul>
            </li>

            <li>
                <strong>Is your phone number correct?</strong><br>
                Format should be:
                <ul>
                    <li><code>09123456789</code> (Philippine format), OR</li>
                    <li><code>+639123456789</code> (International format)</li>
                </ul>
            </li>
        </ol>

        <h2>Test Your API Key</h2>
        <p>Click below to test if your API key works:</p>
        
        <?php
        if (isset($_POST['test_api'])) {
            $api_key = SEMAPHORE_API_KEY;
            
            if (empty($api_key) || $api_key === 'your_semaphore_api_key_here') {
                echo '<div class="status error">✕ API key not configured</div>';
            } else {
                // Test with a simple request
                $test_data = [
                    'apikey' => $api_key,
                    'number' => '09282642447',  // Semaphore test number
                    'message' => 'UGAT API Key Test - ' . date('Y-m-d H:i:s'),
                    'sendername' => SEMAPHORE_SENDER_NAME
                ];

                $ch = curl_init('https://api.semaphore.co/api/sms/send');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test_data));
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                echo '<h3>Response:</h3>';
                echo '<pre>' . htmlspecialchars($response) . '</pre>';
                echo '<p><strong>HTTP Code:</strong> ' . $http_code . '</p>';

                $data = json_decode($response, true);
                
                if ($http_code === 200 && $data && isset($data[0]['message_id'])) {
                    echo '<div class="status success"><span class="icon icon-check"></span> API Key is VALID!</div>';
                    echo '<p>Your API key works and SMS can be sent.</p>';
                } else if ($http_code === 401) {
                    echo '<div class="status error">✕ HTTP 401 - Unauthorized</div>';
                    echo '<p><strong>Your API key is INVALID.</strong></p>';
                    echo '<p>Get a new one from <a href="https://semaphore.co/dashboard" target="_blank">https://semaphore.co/dashboard</a></p>';
                } else if ($http_code === 404) {
                    echo '<div class="status error">✕ HTTP 404 - Not Found</div>';
                    echo '<p><strong>Possible causes:</strong></p>';
                    echo '<ul>';
                    echo '<li>Your firewall/proxy is blocking the request</li>';
                    echo '<li>Semaphore API endpoint is temporarily down</li>';
                    echo '<li>Invalid API key format</li>';
                    echo '</ul>';
                } else if ($http_code === 400) {
                    echo '<div class="status error">✕ HTTP 400 - Bad Request</div>';
                    echo '<p>Check your phone number format or message content.</p>';
                } else {
                    echo '<div class="status warning">[WARNING] HTTP ' . $http_code . '</div>';
                }
            }
        } else {
            echo '<form method="POST">';
            echo '<button type="submit" name="test_api">Test API Key Now</button>';
            echo '</form>';
        }
        ?>

        <h2>Still Not Working?</h2>
        <div class="status warning">
            <strong>If HTTP 404 persists:</strong>
            <ol>
                <li>Try in a different browser</li>
                <li>Check if your ISP/firewall blocks Semaphore</li>
                <li>Try from a different network (e.g., mobile hotspot)</li>
                <li>Contact Semaphore support at <a href="https://semaphore.co/faq" target="_blank">https://semaphore.co/faq</a></li>
            </ol>
        </div>

        <h2>Contact Semaphore Support</h2>
        <ul>
            <li>[EMAIL] Email: Check your Semaphore account dashboard</li>
            <li>🌐 FAQ: <a href="https://semaphore.co/faq" target="_blank">https://semaphore.co/faq</a></li>
            <li>📚 Docs: <a href="https://api.semaphore.co/docs" target="_blank">https://api.semaphore.co/docs</a></li>
            <li>💬 Live Chat: Available on semaphore.co website</li>
        </ul>
    </div>
</body>
</html>
