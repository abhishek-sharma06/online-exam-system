<?php
// ==========================================
// FILE: config/phpmailer.php
// PURPOSE: PHPMailer configuration for SendGrid SMTP
// ==========================================

// ==========================================
// LOCALHOST CONFIGURATION (Default)
// ==========================================
// SendGrid SMTP Configuration
define('SENDGRID_HOST', 'smtp.sendgrid.net');
define('SENDGRID_PORT', 587);
define('SENDGRID_USERNAME', 'apikey');
define('SENDGRID_API_KEY', ''); // Replace with your SendGrid API key

// Email Configuration
define('SENDER_EMAIL', 'abhisheksharmam6@gmail.com'); // Change to your domain
define('SENDER_NAME', 'ExamSystem Pro');

// Verification Settings
define('TOKEN_EXPIRY_MINUTES', 15);
define('VERIFICATION_URL', 'http://localhost/exam_system/verify.php'); // Localhost URL for testing

// ==========================================
// LIVE HOSTING CONFIGURATION (Uncomment and update for production)
// ==========================================
/*
// SendGrid SMTP Configuration (same as localhost, update API key if needed)
define('SENDGRID_HOST', 'smtp.sendgrid.net');
define('SENDGRID_PORT', 587);
define('SENDGRID_USERNAME', 'apikey');
define('SENDGRID_API_KEY', 'SG.your-live-api-key'); // Update with your live SendGrid API key

// Email Configuration
define('SENDER_EMAIL', 'noreply@yourdomain.com'); // Update to your live domain email
define('SENDER_NAME', 'ExamSystem Pro');

// Verification Settings
define('TOKEN_EXPIRY_MINUTES', 15);
define('VERIFICATION_URL', 'https://yourdomain.com/verify.php'); // Update to your live domain URL (e.g., https://yourdomain.infinityfreeapp.com/verify.php)
*/

?>
