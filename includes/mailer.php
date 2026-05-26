<?php
// ==========================================
// FILE: includes/mailer.php
// PURPOSE: PHPMailer helper functions for SendGrid
// ==========================================

require_once __DIR__ . '/../config/phpmailer.php';

// Check if PHPMailer is installed, if not provide fallback
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    die('❌ PHPMailer not installed. Run: composer require phpmailer/phpmailer');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $last_error = '';

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configureSMTP();
    }

    /**
     * Configure SMTP settings for SendGrid
     */
    private function configureSMTP() {
        try {
            $this->mail->isSMTP();
            $this->mail->Host = SENDGRID_HOST;
            $this->mail->Port = SENDGRID_PORT;
            $this->mail->SMTPAuth = true;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Username = SENDGRID_USERNAME;
            $this->mail->Password = SENDGRID_API_KEY;
            $this->mail->CharSet = 'UTF-8';
            $this->mail->SMTPDebug = 0; // Set to 2 for debugging
        } catch (Exception $e) {
            $this->last_error = "SMTP Configuration Error: {$e->getMessage()}";
        }
    }

    /**
     * Send verification email with link
     * @param string $email User's email
     * @param string $name User's full name
     * @param string $token Verification token
     * @return bool True if sent successfully
     */
    public function sendVerificationEmail($email, $name, $token) {
        try {
            $verification_link = VERIFICATION_URL . "?token=" . urlencode($token);
            
            $this->mail->setFrom(SENDER_EMAIL, SENDER_NAME);
            $this->mail->addAddress($email, $name);
            $this->mail->Subject = 'Verify Your Email - ExamSystem Pro';
            
            $html_body = $this->getVerificationEmailTemplate($name, $verification_link, $token);
            $this->mail->msgHTML($html_body);
            
            // Plain text alternative
            $plain_text = "Hello $name,\n\nClick the link below to verify your email:\n$verification_link\n\nThis link expires in " . TOKEN_EXPIRY_MINUTES . " minutes.";
            $this->mail->AltBody = $plain_text;
            
            $this->mail->send();
            $this->last_error = '';
            return true;
            
        } catch (Exception $e) {
            $this->last_error = "Email Send Error: {$this->mail->ErrorInfo}";
            error_log("Email verification send failed: {$this->last_error}");
            return false;
        }
    }

    /**
     * Get verification email HTML template
     */
    private function getVerificationEmailTemplate($name, $verification_link, $token) {
        $expiry_time = TOKEN_EXPIRY_MINUTES;
        $current_year = date('Y');
        
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                         color: white; padding: 30px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
                .button { 
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white; 
                    padding: 15px 40px; 
                    text-align: center; 
                    text-decoration: none;
                    border-radius: 5px; 
                    font-weight: bold;
                    margin: 20px 0;
                }
                .button:hover { opacity: 0.9; }
                .link-section { 
                    background: white; 
                    border: 1px solid #ddd; 
                    padding: 15px; 
                    margin: 20px 0; 
                    border-radius: 5px; 
                    word-break: break-all;
                }
                .link-section a { color: #667eea; text-decoration: none; font-size: 12px; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
                .warning { color: #e74c3c; font-size: 14px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Email Verification</h1>
                    <p>ExamSystem Pro</p>
                </div>
                <div class="content">
                    <p>Hello <strong>$name</strong>,</p>
                    <p>Thank you for registering with ExamSystem Pro! To complete your registration, please verify your email address by clicking the button below:</p>
                    <center>
                        <a href="$verification_link" class="button">Verify Email Address</a>
                    </center>
                    <p style="text-align: center; color: #666;">or copy and paste this link in your browser:</p>
                    <div class="link-section">
                        <a href="$verification_link">$verification_link</a>
                    </div>
                    <p><strong>⏱️ This link will expire in $expiry_time minutes.</strong></p>
                    <div class="warning">
                        <strong>⚠️ Important:</strong> Never share this link with anyone. If you didn't create this account, please ignore this email.
                    </div>
                </div>
                <div class="footer">
                    <p>&copy; $current_year ExamSystem Pro. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Get last error message
     */
    public function getLastError() {
        return $this->last_error;
    }
}
?>
