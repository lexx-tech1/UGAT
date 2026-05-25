<?php
// ============================================================
//  config/sms_service.php  —  SMS Service Handler
//  
//  Handles sending SMS messages via configured provider.
// ============================================================

require_once __DIR__ . '/sms.php';

class SmsService
{
    private $provider;
    private $db;
    private $unisms_api_key;

    public function __construct($database = null)
    {
        $this->provider = SMS_PROVIDER;
        $this->db = $database;

        // Prefer API key stored in settings table over hardcoded constant
        $dbKey = null;
        if ($database) {
            $r = $database->query("SELECT value FROM settings WHERE `key` = 'sms_api_key' LIMIT 1");
            if ($r && $row = $r->fetch_assoc()) $dbKey = $row['value'];
        }
        $this->unisms_api_key = (!empty($dbKey)) ? $dbKey : (defined('UNISMS_API_KEY') ? UNISMS_API_KEY : '');
    }

    /**
     * Send SMS to a phone number
     * 
     * @param string $phone_number Phone number with country code (e.g., +63912345678 or 09123456789)
     * @param string $message SMS message content
     * @param array $metadata Optional metadata to store in database
     * @return array ['success' => bool, 'message' => string, 'sms_id' => string|null]
     */
    public function sendSms(string $phone_number, string $message, array $metadata = []): array
    {
        // Sanitize phone number - remove spaces and non-numeric except +
        $phone_number = preg_replace('/[^0-9+]/', '', $phone_number);
        
        // Validate message length
        if (strlen($message) === 0 || strlen($message) > 1600) {
            return [
                'success' => false,
                'message' => 'Message must be between 1 and 1600 characters.'
            ];
        }

        $sms_id = null;
        $response = null;

        // Send via provider
        if (SMS_DEBUG_MODE) {
            // Test mode - log instead of sending
            error_log("SMS DEBUG: To=$phone_number, Message=$message");
            $sms_id = 'test_' . uniqid();
            $response = ['success' => true, 'sid' => $sms_id];
        } elseif (!SMS_ENABLED) {
            return ['success' => false, 'message' => 'SMS service is disabled.'];
        } elseif ($this->provider === 'unisms') {
            $response = $this->sendViaUniSMS($phone_number, $message);
            $sms_id = $response['sid'] ?? null;
        } elseif ($this->provider === 'semaphore') {
            $response = $this->sendViaSemaphore($phone_number, $message);
            $sms_id = $response['sid'] ?? null;
        } else {
            return ['success' => false, 'message' => 'Unknown SMS provider configured.'];
        }

        // Store in database if available
        if ($this->db && !empty($response['success'])) {
            $this->logSmsToDatabase($phone_number, $message, $sms_id, $metadata);
        }

        return [
            'success' => (bool)($response['success'] ?? false),
            'message' => $response['message'] ?? 'SMS sent successfully.',
            'sms_id' => $sms_id
        ];
    }

    /**
     * Send SMS to multiple recipients
     * 
     * @param array $phone_numbers Array of phone numbers
     * @param string $message Message to send
     * @param array $metadata Metadata for tracking
     * @return array Results for each recipient
     */
    public function sendSmsToMultiple(array $phone_numbers, string $message, array $metadata = []): array
    {
        $results = [];
        
        foreach ($phone_numbers as $phone) {
            $results[$phone] = $this->sendSms($phone, $message, $metadata);
        }
        
        return $results;
    }

    /**
     * Send SMS via UniSMS API (Free, Unlimited)
     * 
     * @param string $phone_number Phone number (09XXXXXXXXX or +639XXXXXXXXX)
     * @param string $message Message content (max 670 chars)
     * @return array ['success' => bool, 'sid' => string|null, 'message' => string]
     */
    private function sendViaUniSMS(string $phone_number, string $message): array
    {
        if (empty($this->unisms_api_key)) {
            return ['success' => false, 'message' => 'UniSMS API key not configured.'];
        }

        if (!defined('UNISMS_API_URL') || empty(UNISMS_API_URL)) {
            return ['success' => false, 'message' => 'UniSMS API URL not configured.'];
        }

        // Validate message length for UniSMS (max 670 chars per SMS)
        if (strlen($message) > 670) {
            return ['success' => false, 'message' => 'UniSMS message limited to 670 characters.'];
        }

        $url = UNISMS_API_URL;
        
        // Prepare JSON payload
        $payload = [
            'recipient' => $phone_number,  // Accepts both 09XX and +63XX formats
            'content'   => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // HTTP Basic Auth: API key as username, empty password
        curl_setopt($ch, CURLOPT_USERPWD, $this->unisms_api_key . ':');

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Log for debugging
        error_log("UniSMS HTTP Code: $http_code");
        error_log("UniSMS Response: $response");

        if ($curl_error) {
            return ['success' => false, 'message' => 'cURL error: ' . $curl_error];
        }

        $data = json_decode($response, true);

        // UniSMS success response: HTTP 200 with 'id' in response
        if ($http_code === 200 && isset($data['id'])) {
            return [
                'success' => true,
                'sid'     => (string)$data['id'],
                'message' => 'SMS sent successfully via UniSMS. Status: ' . ($data['status'] ?? 'queued')
            ];
        }

        return [
            'success' => false,
            'message' => "UniSMS HTTP $http_code — " . ($response ?: 'No response from UniSMS API')
        ];
    }

    /**
     * Send SMS via Semaphore API
     * 
     * @param string $phone_number Must be in 09XXXXXXXXX format
     * @param string $message
     * @return array
     */
    private function sendViaSemaphore(string $phone_number, string $message): array
    {
        if (!defined('SEMAPHORE_API_KEY') || empty(SEMAPHORE_API_KEY)) {
            return ['success' => false, 'message' => 'Semaphore API key not configured.'];
        }

        $url = 'https://api.semaphore.co/api/v4/messages';

        $post_data = [
            'apikey'     => SEMAPHORE_API_KEY,
            'number'     => $phone_number,   // Already in 09XXXXXXXXX format
            'message'    => $message,
            'sendername' => SEMAPHORE_SENDER_NAME
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Log everything for debugging
        error_log("Semaphore HTTP Code: $http_code");
        error_log("Semaphore Response: $response");

        if ($curl_error) {
            return ['success' => false, 'message' => 'cURL error: ' . $curl_error];
        }

        $data = json_decode($response, true);

        if ($http_code === 200 && isset($data[0]['message_id'])) {
            return [
                'success' => true,
                'sid'     => (string)$data[0]['message_id'],
                'message' => 'SMS sent. Status: ' . ($data[0]['status'] ?? 'Pending')
            ];
        }

        return [
            'success' => false,
            'message' => "HTTP $http_code — " . ($response ?: 'No response from Semaphore')
        ];
    }

    /**
     * Log SMS to database for tracking
     * 
     * @param string $phone_number
     * @param string $message
     * @param string $sms_id
     * @param array $metadata
     * @return void
     */
    private function logSmsToDatabase(string $phone_number, string $message, string $sms_id, array $metadata = []): void
    {
        if (!$this->db) {
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO sms_logs (phone_number, message, sms_id, metadata, sent_at, status) 
                 VALUES (?, ?, ?, ?, NOW(), ?)'
            );
            
            if (!$stmt) {
                error_log('SMS Log DB Error: ' . $this->db->error);
                return;
            }

            // Handle metadata - if empty, use NULL
            $metadata_json = !empty($metadata) ? json_encode($metadata) : NULL;
            $status = 'sent';
            
            $stmt->bind_param('sssss', $phone_number, $message, $sms_id, $metadata_json, $status);
            $stmt->execute();
            $stmt->close();
        } catch (\Exception $e) {
            error_log('SMS logging error: ' . $e->getMessage());
        }
    }

    /**
     * Get SMS delivery status
     * 
     * @param string $sms_id Semaphore message ID
     * @return array
     */
    public function getSmsStatus(string $sms_id): array
    {
        if ($this->provider !== 'semaphore') {
            return ['success' => false, 'message' => 'SMS provider does not support status checking.'];
        }

        // Semaphore doesn't provide a built-in status check endpoint
        // Status is available via their web dashboard
        return [
            'success' => true,
            'message' => 'Check SMS status at: https://app.semaphore.co/sms',
            'message_id' => $sms_id
        ];
    }
}

/**
 * Get or create SmsService instance
 */
function getSmsService($db = null): SmsService
{
    if ($db === null) {
        global $conn;
        $db = $conn ?? null;
    }
    return new SmsService($db);
}
