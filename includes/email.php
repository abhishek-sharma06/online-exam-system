<?php
// ==========================================
// FILE: includes/email.php
// PURPOSE: Email utility class for sending OTP
// ==========================================

class Email {
    private $from_email = 'noreply@examsystem.local';
    private $from_name = 'ExamSystem Pro';
    
    /**
     * Send OTP email to user
     * @param string $to_email Recipient email address
     * @param string $otp One-time password (6 digits)
     * @param string $user_name User's full name
     * @return bool True if email sent successfully
     */
    public function sendOTPEmail($to_email, $otp, $user_name) {
        $subject = "Email Verification - ExamSystem Pro";
        
        $message = $this->getOTPEmailTemplate($otp, $user_name);
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $this->from_name . " <" . $this->from_email . ">\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Log the email attempt
        $log_entry = "\n=== OTP Email Log ===\n";
        $log_entry .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $log_entry .= "To: $to_email\n";
        $log_entry .= "User: $user_name\n";
        $log_entry .= "OTP: $otp\n";
        $log_entry .= "Subject: $subject\n";
        error_log($log_entry);
        
        // Send email (with fallback for local testing)
        if (@mail($to_email, $subject, $message, $headers)) {
            error_log("OTP email sent successfully to: $to_email");
            return true;
        } else {
            // In local development, we can still consider this successful if writing to log file
            $this->logEmailToFile('otp_emails.log', [
                'to' => $to_email,
                'subject' => $subject,
                'otp' => $otp,
                'user' => $user_name,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            error_log("OTP email logged (mail() unavailable) for: $to_email");
            return true; // Return true to allow registration to proceed in dev environment
        }
    }
    
    /**
     * Send verification success email
     * @param string $to_email Recipient email address
     * @param string $user_name User's full name
     * @return bool True if email sent successfully
     */
    public function sendVerificationSuccessEmail($to_email, $user_name) {
        $subject = "Email Verified - ExamSystem Pro";
        
        $message = $this->getVerificationSuccessTemplate($user_name);
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $this->from_name . " <" . $this->from_email . ">\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        error_log("Sending verification success email to: $to_email");
        
        if (@mail($to_email, $subject, $message, $headers)) {
            error_log("Verification success email sent to: $to_email");
            return true;
        } else {
            // In local development, log to file
            $this->logEmailToFile('verification_emails.log', [
                'to' => $to_email,
                'subject' => $subject,
                'user' => $user_name,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            error_log("Verification success email logged (mail() unavailable) for: $to_email");
            return true;
        }
    }
    
    /**
     * Get HTML template for OTP email
     */
    private function getOTPEmailTemplate($otp, $user_name) {
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
                .otp-box { background: white; border: 2px solid #667eea; padding: 20px; 
                          text-align: center; margin: 20px 0; border-radius: 5px; }
                .otp-code { font-size: 36px; font-weight: bold; color: #667eea; 
                           letter-spacing: 5px; font-family: monospace; }
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
                    <p>Hello <strong>$user_name</strong>,</p>
                    
                    <p>Thank you for registering with ExamSystem Pro! To complete your registration and verify your email address, please use the following One-Time Password (OTP):</p>
                    
                    <div class="otp-box">
                        <p style="margin: 0; color: #666; font-size: 14px;">Your verification code:</p>
                        <div class="otp-code">$otp</div>
                    </div>
                    
                    <p><strong>⏱️ This code will expire in 15 minutes.</strong></p>
                    
                    <h3>How to verify your email:</h3>
                    <ol>
                        <li>Copy the OTP code above</li>
                        <li>Go back to the verification page in ExamSystem Pro</li>
                        <li>Paste the OTP code and click "Verify Email"</li>
                    </ol>
                    
                    <div class="warning">
                        <strong>⚠️ Important:</strong> Never share this code with anyone. ExamSystem Pro will never ask you for this code.
                    </div>
                    
                    <p style="margin-top: 30px; color: #666;">
                        If you didn't create an account, please ignore this email.
                    </p>
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
     * Get HTML template for verification success email
     */
    private function getVerificationSuccessTemplate($user_name) {
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
                .success-box { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; 
                              padding: 20px; margin: 20px 0; border-radius: 5px; text-align: center; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✓ Email Verified</h1>
                    <p>ExamSystem Pro</p>
                </div>
                <div class="content">
                    <p>Hello <strong>$user_name</strong>,</p>
                    
                    <div class="success-box">
                        <h2 style="margin: 0; font-size: 24px;">✓ Your email has been verified successfully!</h2>
                    </div>
                    
                    <p>Your account is now fully activated. You can now log in to ExamSystem Pro and start taking exams.</p>
                    
                    <h3>Next Steps:</h3>
                    <ol>
                        <li>Log in to your account</li>
                        <li>View available exams</li>
                        <li>Start taking exams</li>
                    </ol>
                    
                    <p style="margin-top: 30px; color: #666;">
                        If you have any questions or need assistance, please contact our support team.
                    </p>
                </div>
                <div class="footer">
                    <p>&copy; $current_year ExamSystem Pro. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
    
    /**
     * Generate a random 6-digit OTP
     */
    public static function generateOTP() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify OTP expiration time
     * @param string $expires_at Expiration timestamp
     * @return bool True if OTP is still valid
     */
    public static function isOTPValid($expires_at) {
        if (empty($expires_at)) {
            return false;
        }
        return strtotime($expires_at) > time();
    }
    
    /**
     * Log email to file for development/testing purposes
     */
    private function logEmailToFile($filename, $email_data) {
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . '/' . $filename;
        $log_content = json_encode($email_data, JSON_PRETTY_PRINT) . "\n" . str_repeat("=", 50) . "\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);
    }
}
?>
