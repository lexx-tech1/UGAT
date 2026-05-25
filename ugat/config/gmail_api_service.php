<?php
/**
 * Gmail SMTP Email Service
 * Sends emails via Gmail SMTP using PHPMailer
 */

require_once 'gmail_api.php';
require_once 'db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class GmailApiService {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host = GMAIL_SMTP_HOST;
        $this->mail->Port = GMAIL_SMTP_PORT;
        $this->mail->SMTPSecure = GMAIL_SMTP_ENCRYPTION;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = GMAIL_SENDER_EMAIL;
        $this->mail->Password = GMAIL_SENDER_PASSWORD;
        $this->mail->setFrom(GMAIL_SENDER_EMAIL, GMAIL_SENDER_NAME);
        $this->mail->CharSet = 'UTF-8';
        // Allow XAMPP's PHP to connect to Gmail without a CA bundle
        $this->mail->SMTPOptions = [
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ];
    }
    
    /**
     * Send email via Gmail SMTP
     */
    public function sendEmail($to, $subject, $html_body, $text_body = null) {
        try {
            // Reset for new email
            $this->mail->clearAddresses();
            $this->mail->clearAllRecipients();
            
            if (!$text_body) {
                $text_body = strip_tags($html_body);
            }
            
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $html_body;
            $this->mail->AltBody = $text_body;
            $this->mail->isHTML(true);
            
            $this->mail->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'email_id' => $this->mail->getLastMessageID()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'email_id' => null
            ];
        }
    }
    
    /**
     * Send bulk emails
     */
    public function sendEmailBulk($recipients, $subject, $html_body, $text_body = null) {
        $results = [];
        foreach ($recipients as $email) {
            $results[$email] = $this->sendEmail($email, $subject, $html_body, $text_body);
        }
        return $results;
    }
}

// Helper function for backward compatibility
function sendGmailEmail($to, $subject, $html_body, $text_body = null) {
    try {
        $service = new GmailApiService();
        return $service->sendEmail($to, $subject, $html_body, $text_body);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'email_id' => null
        ];
    }
}

?>
