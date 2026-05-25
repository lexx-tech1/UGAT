<?php
/**
 * Direct UniSMS API Test
 * 
 * This script tests the UniSMS API endpoint directly without using your app code.
 * Useful for diagnosing API connectivity and authentication issues.
 */

// Get the configuration
require_once 'config/sms.php';

$test_phone = $_GET['phone'] ?? '';
$test_message = $_GET['message'] ?? 'Hi! This is a test message from UGAT notification system. Disregard if not expecting.';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniSMS Direct API Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .content { padding: 40px; }
        
        .section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        .section:last-child { border-bottom: none; }
        
        .section h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-error { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        .config-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
        }
        
        .config-item {
            margin: 8px 0;
        }
        
        .config-key { color: #0066cc; font-weight: 600; }
        .config-value { color: #666; }
        .config-success { color: #28a745; }
        .config-error { color: #dc3545; }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        input, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover { transform: translateY(-2px); }
        button:active { transform: translateY(0); }
        
        .response-box {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 12px;
            line-height: 1.6;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .response-success { border-color: #28a745; background: #d4edda; }
        .response-error { border-color: #dc3545; background: #f8d7da; }
        
        .tip {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 13px;
            color: #1565c0;
        }
        
        .tip strong { color: #0d47a1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 UniSMS Direct API Test</h1>
            <p>Test UniSMS API connectivity and send test SMS without using your app</p>
        </div>
        
        <div class="content">
            <!-- Configuration Status -->
            <div class="section">
                <h2>
                    ⚙️ Configuration Status
                    <?php if (!empty(UNISMS_API_KEY)): ?>
                        <span class="status-badge badge-success">✓ Configured</span>
                    <?php else: ?>
                        <span class="status-badge badge-error">✗ Not Configured</span>
                    <?php endif; ?>
                </h2>
                
                <div class="config-box">
                    <div class="config-item">
                        <span class="config-key">SMS Provider:</span>
                        <span class="config-value"><?php echo SMS_PROVIDER; ?></span>
                        <?php if (SMS_PROVIDER === 'unisms'): ?>
                            <span class="config-success"> ✓</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="config-item">
                        <span class="config-key">API Key:</span>
                        <?php if (!empty(UNISMS_API_KEY)): ?>
                            <span class="config-success">✓ Set (<?php echo substr(UNISMS_API_KEY, 0, 10); ?>...)</span>
                        <?php else: ?>
                            <span class="config-error">✗ Not Set</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="config-item">
                        <span class="config-key">API URL:</span>
                        <span class="config-value"><?php echo UNISMS_API_URL ?? 'Not defined'; ?></span>
                    </div>
                    
                    <div class="config-item">
                        <span class="config-key">SMS Enabled:</span>
                        <span class="config-value"><?php echo SMS_ENABLED ? 'Yes' : 'No'; ?></span>
                    </div>
                    
                    <div class="config-item">
                        <span class="config-key">Debug Mode:</span>
                        <span class="config-value"><?php echo SMS_DEBUG_MODE ? 'ON (logging only)' : 'OFF (sending real SMS)'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Test Form -->
            <div class="section">
                <h2>📤 Send Test SMS</h2>
                
                <form method="GET">
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="text" id="phone" name="phone" placeholder="09171234567 or +639171234567" 
                               value="<?php echo htmlspecialchars($test_phone); ?>" required>
                        <div class="tip">
                            <strong>Format:</strong> Use 09XXXXXXXXX or +639XXXXXXXXX format.
                            <strong>Test Number:</strong> 09282642447 (if available)
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" rows="4" placeholder="Use natural language (not automated looking). Example: Hi, your order is ready for pickup." required><?php echo htmlspecialchars($test_message); ?></textarea>
                        <div class="tip"><strong>Max Length:</strong> 670 characters. <strong>Important:</strong> Use natural language — avoid spam-like words like "TEST", timestamps, or too many technical terms.</div>
                    </div>
                    
                    <button type="submit">Send Test SMS →</button>
                </form>
            </div>
            
            <!-- API Response -->
            <?php if (!empty($test_phone) && $_SERVER['REQUEST_METHOD'] === 'GET'): ?>
                <div class="section">
                    <h2>📡 API Response</h2>
                    
                    <?php
                        // Validate inputs
                        if (empty($test_phone)) {
                            echo '<div class="status-badge badge-error">Phone number required</div>';
                        } elseif (strlen($test_message) > 670) {
                            echo '<div class="status-badge badge-error">Message too long (max 670 chars)</div>';
                        } else {
                            // Make the actual API call
                            $url = UNISMS_API_URL;
                            $payload = [
                                'recipient' => $test_phone,
                                'content'   => $test_message
                            ];
                            
                            $ch = curl_init($url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                            curl_setopt($ch, CURLOPT_USERPWD, UNISMS_API_KEY . ':');
                            
                            $response = curl_exec($ch);
                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $curl_error = curl_error($ch);
                            curl_close($ch);
                            
                            // Display results
                            echo '<div style="margin-bottom: 15px;">';
                            
                            if ($curl_error) {
                                echo '<div class="status-badge badge-error">✕ cURL Error</div>';
                                echo '<div class="response-box response-error">';
                                echo htmlspecialchars($curl_error);
                                echo '</div>';
                            } else {
                                $success = ($http_code === 200);
                                echo '<div class="status-badge ' . ($success ? 'badge-success' : 'badge-error') . '">';
                                echo $success ? '[✓] HTTP 200 - Success' : '✕ HTTP ' . $http_code . ' - Error';
                                echo '</div>';
                            }
                            
                            echo '<h3 style="margin-top: 20px; margin-bottom: 10px; color: #333;">Request Details:</h3>';
                            echo '<div class="config-box">';
                            echo 'Endpoint: ' . htmlspecialchars(UNISMS_API_URL) . "\n";
                            echo 'Method: POST\n';
                            echo 'Auth: HTTP Basic (API Key as username)\n';
                            echo "Phone: " . htmlspecialchars($test_phone) . "\n";
                            echo "Message: " . htmlspecialchars($test_message) . "\n";
                            echo '</div>';
                            
                            echo '<h3 style="margin-top: 20px; margin-bottom: 10px; color: #333;">Response:</h3>';
                            echo '<div class="response-box ' . ($success ? 'response-success' : 'response-error') . '">';
                            
                            if (empty($response)) {
                                echo '(Empty response - check HTTP code)';
                            } else {
                                // Try to parse as JSON for pretty printing
                                $decoded = json_decode($response, true);
                                if ($decoded !== null) {
                                    echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                } else {
                                    echo htmlspecialchars($response);
                                }
                            }
                            
                            echo '</div>';
                            
                            // If HTTP 422, provide specific feedback about the message
                            if ($http_code === 422) {
                                echo '<div class="tip" style="margin-top: 20px; background: #fff3cd; border-color: #ffc107;">';
                                echo '<strong>[!] HTTP 422 - Spam Filter Triggered</strong><br>';
                                echo 'Your message was flagged as spam. Here\'s analysis:<br><br>';
                                
                                $message_lower = strtolower($test_message);
                                $issues = [];
                                
                                // Check for spam trigger words
                                $spam_words = ['test', 'system', 'automated', 'automatic', 'bot', 'alert', 'urgent', 'click here', 'verify', 'confirm', 'update', 'action required'];
                                foreach ($spam_words as $word) {
                                    if (strpos($message_lower, $word) !== false) {
                                        $issues[] = "Contains word: <strong>'$word'</strong>";
                                    }
                                }
                                
                                // Check for technical jargon
                                if (preg_match('/\d{4}-\d{2}-\d{2}/', $test_message)) {
                                    $issues[] = "Contains <strong>timestamp format</strong> (dates/times look automated)";
                                }
                                
                                // Check for URLs
                                if (preg_match('/http|\.com|\.php|localhost/', $message_lower)) {
                                    $issues[] = "Contains <strong>URLs or links</strong> (common in spam)";
                                }
                                
                                // Check for ALL CAPS
                                if (strlen($test_message) > 5 && strlen(preg_replace('/[^A-Z]/', '', $test_message)) / strlen($test_message) > 0.3) {
                                    $issues[] = "Has <strong>excessive CAPS</strong> (looks like shouting/spam)";
                                }
                                
                                // Check for special characters
                                if (preg_match('/[!]{2,}|\*{2,}|#{2,}/', $test_message)) {
                                    $issues[] = "Has <strong>excessive punctuation</strong> (!!, **, ##, etc)";
                                }
                                
                                if (!empty($issues)) {
                                    echo '<strong>Detected Issues:</strong><br>';
                                    foreach ($issues as $issue) {
                                        echo '✕ ' . $issue . '<br>';
                                    }
                                    echo '<br>';
                                }
                                
                                echo '<strong>[✓] How to Fix:</strong><br>';
                                echo '• Remove "system" or "notification system" mentions<br>';
                                echo '• Avoid words like TEST, ALERT, URGENT, AUTOMATED<br>';
                                echo '• No timestamps in the message<br>';
                                echo '• No URLs or links<br>';
                                echo '• Use normal sentence case (not ALL CAPS)<br>';
                                echo '• Use single punctuation (!, not !!)<br>';
                                echo '• Sound like a real person, not a machine<br><br>';
                                
                                echo '<strong>📋 Suggested Message:</strong><br>';
                                echo '<code style="background: #f0f0f0; padding: 8px; display: block; border-radius: 4px;">';
                                echo htmlspecialchars('Hi! Your enrollment has been confirmed. Welcome to the workshop!');
                                echo '</code>';
                                
                                echo '</div>';
                            }
                            
                            // Log to database if successful
                            if ($success) {
                                $data = json_decode($response, true);
                                if (isset($data['id'])) {
                                    $sms_id = $data['id'];
                                    
                                    require_once 'config/db.php';
                                    $stmt = $conn->prepare(
                                        'INSERT INTO sms_logs (phone_number, message, sms_id, status, sent_at) 
                                         VALUES (?, ?, ?, ?, NOW())'
                                    );
                                    
                                    if ($stmt) {
                                        $status = 'sent';
                                        $stmt->bind_param('ssss', $test_phone, $test_message, $sms_id, $status);
                                        $stmt->execute();
                                        $stmt->close();
                                        
                                        echo '<div class="tip" style="margin-top: 20px;">';
                                        echo '<strong>✓ Logged to Database</strong> — Message ID: ' . htmlspecialchars($sms_id);
                                        echo '</div>';
                                    }
                                }
                            }
                            
                            echo '</div>';
                        }
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Help -->
            <div class="section">
                <h2>❓ Troubleshooting</h2>
                
                <div class="tip">
                    <strong>HTTP 200 + message_id?</strong> Success! SMS sent. Check your phone in 1-2 minutes.
                </div>
                
                <div class="tip">
                    <strong>HTTP 422 "looks like spam"?</strong> 
                    <br><br>
                    <strong>Common Triggers:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Words: TEST, SYSTEM, ALERT, URGENT, AUTOMATED, VERIFY, CONFIRM, CLICK</li>
                        <li>Timestamps: "2026-05-19 02:12:50" or date/time formats</li>
                        <li>URLs: http://, .com, .php, localhost, links</li>
                        <li>ALL CAPS text or excessive punctuation (!!!, ???, ***)</li>
                        <li>Technical jargon that looks auto-generated</li>
                    </ul>
                    
                    <strong>Solution:</strong> Use natural, conversational language as if texting a friend.
                    <br><br>
                    <strong>Bad:</strong> "TEST SMS from UGAT system - 2026-05-19 02:12:50"
                    <br>
                    <strong>Good:</strong> "Hi! Your order is ready for pickup tomorrow."
                </div>
                
                <div class="tip">
                    <strong>HTTP 401?</strong> API key is invalid or expired. Get a new one from https://unismsapi.com/
                </div>
                
                <div class="tip">
                    <strong>HTTP 400?</strong> Check phone number format. Must be 09XXXXXXXXX or +639XXXXXXXXX
                </div>
                
                <div class="tip">
                    <strong>Connection timeout?</strong> Your firewall may be blocking unismsapi.com. Try from mobile hotspot.
                </div>
                
                <div class="tip">
                    <strong>Message not received?</strong> Check spam folder, verify phone number, or contact UniSMS support.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
