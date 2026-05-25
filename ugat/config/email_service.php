<?php
// ============================================================
//  config/email_service.php  —  Email Service Handler
//  
//  Handles sending emails via Gmail/SMTP using PHPMailer.
// ============================================================

require_once __DIR__ . '/email.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $provider;
    private $db;

    public function __construct($database = null)
    {
        $this->provider = EMAIL_PROVIDER;
        $this->db = $database;
    }

    /**
     * Send email to a recipient
     * 
     * @param string $recipient_email Email address
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param array $metadata Optional metadata to store in database
     * @return array ['success' => bool, 'message' => string, 'email_id' => string|null]
     */
    public function sendEmail(string $recipient_email, string $subject, string $message, array $metadata = []): array
    {
        // Validate email format
        if (!isValidEmail($recipient_email)) {
            return [
                'success' => false,
                'message' => 'Invalid email address format.'
            ];
        }

        // Validate message length
        if (strlen($message) === 0 || strlen($message) > 100000) {
            return [
                'success' => false,
                'message' => 'Message must be between 1 and 100000 characters.'
            ];
        }

        $email_id = null;
        $response = null;

        // Send via provider
        if (EMAIL_DEBUG_MODE) {
            // Test mode - log instead of sending
            error_log("EMAIL DEBUG: To=$recipient_email, Subject=$subject, Message length=" . strlen($message));
            $email_id = 'test_' . uniqid();
            $response = ['success' => true, 'id' => $email_id];
        } elseif (!EMAIL_ENABLED) {
            return ['success' => false, 'message' => 'Email service is disabled.'];
        } elseif ($this->provider === 'gmail_smtp') {
            $response = $this->sendViaGmailSmtp($recipient_email, $subject, $message);
            $email_id = $response['id'] ?? null;
        } else {
            return ['success' => false, 'message' => 'Unknown email provider configured.'];
        }

        // Store in database if available
        if ($this->db && !empty($response['success'])) {
            $this->logEmailToDatabase($recipient_email, $subject, $message, $email_id, $metadata);
        }

        return [
            'success' => (bool)($response['success'] ?? false),
            'message' => $response['message'] ?? 'Email sent successfully.',
            'email_id' => $email_id
        ];
    }

    /**
     * Send email to multiple recipients
     * 
     * @param array $recipient_emails Array of email addresses
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param array $metadata Metadata for tracking
     * @return array Results for each recipient
     */
    public function sendEmailToMultiple(array $recipient_emails, string $subject, string $message, array $metadata = []): array
    {
        $results = [];
        
        foreach ($recipient_emails as $email) {
            $results[$email] = $this->sendEmail($email, $subject, $message, $metadata);
        }
        
        return $results;
    }

    /**
     * Send email via Gmail SMTP
     * 
     * @param string $recipient_email
     * @param string $subject
     * @param string $message (HTML)
     * @return array
     */
    private function sendViaGmailSmtp(string $recipient_email, string $subject, string $message): array
    {
        if (!defined('GMAIL_ADDRESS') || GMAIL_ADDRESS === 'your_gmail@gmail.com') {
            return [
                'success' => false,
                'message' => 'Gmail SMTP credentials not configured. Please set GMAIL_ADDRESS in config/email.php'
            ];
        }

        if (!defined('GMAIL_APP_PASSWORD') || GMAIL_APP_PASSWORD === 'your_app_password_here') {
            return [
                'success' => false,
                'message' => 'Gmail app password not configured. Please set GMAIL_APP_PASSWORD in config/email.php'
            ];
        }

        try {
            // Check if PHPMailer exists
            if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
                error_log('PHPMailer not installed. Run: composer require phpmailer/phpmailer');
                return [
                    'success' => false,
                    'message' => 'PHPMailer not installed. Please install via Composer.'
                ];
            }

            require __DIR__ . '/../vendor/autoload.php';

            $mail = new PHPMailer(true);

            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Username = GMAIL_ADDRESS;
            $mail->Password = GMAIL_APP_PASSWORD;
            $mail->SMTPOptions = [
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
            ];

            // Set sender and recipient
            $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
            $mail->addAddress($recipient_email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);

            // Send
            $mail->send();

            return [
                'success' => true,
                'id' => uniqid('email_'),
                'message' => 'Email sent successfully.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'PHPMailer error: ' . $mail->ErrorInfo ?? $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Log email to database for tracking
     * 
     * @param string $recipient_email
     * @param string $subject
     * @param string $message
     * @param string $email_id
     * @param array $metadata
     * @return void
     */
    private function logEmailToDatabase(string $recipient_email, string $subject, string $message, string $email_id, array $metadata = []): void
    {
        if (!$this->db) {
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO email_logs (recipient_email, subject, message, email_provider, sent_at, status, metadata) 
                 VALUES (?, ?, ?, ?, NOW(), ?, ?)'
            );
            
            if (!$stmt) {
                error_log('Email Log DB Error: ' . $this->db->error);
                return;
            }

            $metadata_json = json_encode($metadata);
            $status = 'sent';
            $provider = 'gmail_smtp';
            
            $stmt->bind_param('sssss', $recipient_email, $subject, $message, $provider, $status);
            $stmt->execute();
            $stmt->close();
        } catch (\Exception $e) {
            error_log('Email logging error: ' . $e->getMessage());
        }
    }
}

/**
 * Helper function to validate email
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get or create EmailService instance
 */
function getEmailService($db = null): EmailService
{
    if ($db === null) {
        global $conn;
        $db = $conn ?? null;
    }
    return new EmailService($db);
}
