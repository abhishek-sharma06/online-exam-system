<?php
// ==========================================
// FILE: includes/email.php
// PURPOSE: Send OTP emails using SendGrid API
// METHOD: HTTP POST via cURL (NO SMTP, NO Gmail password)
// ==========================================

require_once __DIR__ . '/../config/phpmailer.php';

class Email {
    private $last_error = '';

    // SendGrid API endpoint
    private const SENDGRID_API_URL = 'https://api.sendgrid.com/v3/mail/send';

    /**
     * Send OTP email for verification
     * @param string $to_email Recipient email
     * @param string $otp Generated OTP
     * @param string $name User's name
     * @return bool Success or failure
     */
    public function sendOTPEmail($to_email, $otp, $name) {

        // Validate email
        $to_email = filter_var($to_email, FILTER_VALIDATE_EMAIL);
        if (!$to_email) {
            $this->last_error = "Invalid email address";
            return false;
        }

        // Build HTML email content
        $html_message = "
            <h2>Hello " . htmlspecialchars($name) . ",</h2>
            <p>Thank you for registering.</p>

            <p>Your verification OTP is:</p>

            <h1 style='color:#667eea; letter-spacing:3px;'>" . htmlspecialchars($otp) . "</h1>

            <p>This OTP is valid for <strong>15 minutes</strong>.</p>

            <p>If you did not create this account, please ignore this email.</p>
        ";

        // Send email via API
        $email_sent = $this->sendViaAPI($to_email, 'Your OTP - ExamSystem Pro', $html_message);
        
        // Log OTP for development/testing (even if email fails)
        $log_data = [
            'to' => $to_email,
            'subject' => 'Your OTP - ExamSystem Pro',
            'otp' => $otp,
            'user' => $name,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!$email_sent) {
            $log_data['smtp_error'] = $this->last_error;
            $log_data['last_error'] = $this->last_error;
        }
        
        $log_entry = json_encode($log_data, JSON_PRETTY_PRINT) . "\n==================================================\n";
        file_put_contents(__DIR__ . '/../logs/otp_emails.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        return $email_sent;
    }

    /**
     * Core function: Send email using SendGrid API
     */
    private function sendViaAPI($to_email, $subject, $html_content) {

        $payload = [
            'personalizations' => [[
                'to' => [[
                    'email' => $to_email
                ]]
            ]],
            'from' => [
                'email' => SENDER_EMAIL
            ],
            'subject' => $subject,
            'content' => [[
                'type' => 'text/html',
                'value' => $html_content
            ]]
        ];

        $ch = curl_init(self::SENDGRID_API_URL);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . SENDGRID_API_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // Handle cURL error
        if ($error) {
            $this->last_error = "cURL Error: " . $error;
            error_log($this->last_error);
            return false;
        }

        // SendGrid success = 202
        if ($http_code !== 202) {
            $this->last_error = "SendGrid API Error (HTTP $http_code): $response";
            error_log($this->last_error);
            return false;
        }

        $this->last_error = '';
        return true;
    }

    /**
     * Get last error message
     */
    public function getLastError() {
        return $this->last_error;
    }

    /**
     * Generate 6-digit OTP
     */
    public static function generateOTP() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Check OTP validity
     */
    public static function isOTPValid($expires_at) {
        if (empty($expires_at)) {
            return false;
        }
        return strtotime($expires_at) > time();
    }
}
?>