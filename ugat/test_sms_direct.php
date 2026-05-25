<?php
/**
 * Direct Semaphore API Test - Bypasses all app code
 * Run this to isolate whether the issue is your app or Semaphore
 * DELETE THIS FILE after testing!
 */

require_once 'config/sms.php';

$apikey = SEMAPHORE_API_KEY;
$number = isset($_GET['phone']) ? $_GET['phone'] : '09282642447';  // Default test number
$message = 'UGAT Direct Test - ' . date('Y-m-d H:i:s');
$sendername = SEMAPHORE_SENDER_NAME;

// Normalize phone to 09xx format
$number = preg_replace('/[^0-9+]/', '', $number);
if (preg_match('/^\+639(\d{9})$/', $number, $m)) {
    $number = '09' . $m[1];
} elseif (preg_match('/^639(\d{9})$/', $number, $m)) {
    $number = '09' . $m[1];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Direct Semaphore API Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .status { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; border-left: 4px solid #ccc; }
        input, button { padding: 10px; margin: 10px 0; }
        button { background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .divider { border-top: 2px solid #ddd; margin: 30px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Direct Semaphore API Test</h1>
        
        <div class="status info">
            ℹ️ This bypasses your app and tests Semaphore directly. Use to isolate the issue.
        </div>

        <h2>Configuration</h2>
        <form method="GET">
            <label>Your Phone Number:</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($number); ?>" placeholder="09XXXXXXXXX or +639XXXXXXXXX">
            <button type="submit">Test with this number</button>
        </form>

        <h2>Request Details</h2>
        <pre>
URL:     https://api.semaphore.co/api/v4/messages
Method:  POST

Parameters:
  apikey:     <?php echo substr($apikey, 0, 8); ?>...
  number:     <?php echo $number; ?>
  message:    <?php echo htmlspecialchars($message); ?>
  sendername: <?php echo $sendername; ?>
        </pre>

        <h2>Sending...</h2>
        <?php

        $post_data = [
            'apikey'     => $apikey,
            'number'     => $number,
            'message'    => $message,
            'sendername' => $sendername
        ];

        $ch = curl_init('https://api.semaphore.co/api/v4/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        echo '<h3>Response</h3>';

        if ($curl_error) {
            echo '<div class="status error">✕ cURL Error: ' . htmlspecialchars($curl_error) . '</div>';
        } elseif ($http_code === 200) {
            echo '<div class="status success">[✓] HTTP 200 - Success!</div>';
        } else {
            echo '<div class="status error">✕ HTTP ' . $http_code . '</div>';
            if ($http_code === 401) {
                echo '<p><strong>Your API key is INVALID</strong> — Get new one from https://semaphore.co</p>';
            } elseif ($http_code === 404) {
                echo '<p><strong>404 Not Found</strong> — Endpoint issue or network blocking</p>';
            } elseif ($http_code === 400) {
                echo '<p><strong>Bad Request</strong> — Check phone number format</p>';
            }
        }

        echo '<h4>Raw Response:</h4>';
        echo '<pre>' . htmlspecialchars($response) . '</pre>';

        // Parse JSON
        $data = json_decode($response, true);
        if ($data) {
            echo '<h4>Parsed JSON:</h4>';
            echo '<pre>' . json_encode($data, JSON_PRETTY_PRINT) . '</pre>';

            if (isset($data[0])) {
                echo '<h3>Message Details</h3>';
                echo '<pre>';
                foreach ($data[0] as $key => $value) {
                    echo "$key: $value\n";
                }
                echo '</pre>';
            }
        }

        ?>

        <div class="divider"></div>

        <h2>Troubleshooting</h2>
        <ul>
            <li><strong>HTTP 401:</strong> Invalid API key → Get correct one from semaphore.co</li>
            <li><strong>HTTP 404:</strong> Endpoint not found → Network blocking or wrong URL</li>
            <li><strong>HTTP 400:</strong> Bad phone format → Use 09XXXXXXXXX or +639XXXXXXXXX</li>
            <li><strong>cURL Error:</strong> Network issue → Try different connection</li>
            <li><strong>HTTP 200 but status 'failed':</strong> Check account balance → https://semaphore.co/dashboard</li>
        </ul>

        <div class="divider"></div>

        <p style="color: #666; font-size: 12px;">
            This is a debug file. Remember to delete it after testing! <code>rm test_sms_direct.php</code>
        </p>
    </div>
</body>
</html>
