<?php
return [
    // ==========================================
    // LOCALHOST CONFIGURATION (Default)
    // ==========================================
    'from_email' => 'noreply@localhost.com',  // Local testing email
    'from_name' => 'ExamSystem Pro',

    // Use PHP mail() function for localhost testing
    'smtp_enabled' => false,  // Disabled - using PHP mail() instead

    // Optional debugging and timeout settings.
    'smtp_debug' => false,
    'smtp_timeout' => 30,

    // ==========================================
    // LIVE HOSTING CONFIGURATION (Uncomment and update for production)
    // ==========================================
    /*
    'from_email' => 'noreply@yourdomain.com',  // Use your live domain email (e.g., noreply@yourdomain.infinityfree.app)
    'from_name' => 'ExamSystem Pro',
    'smtp_enabled' => false,  // Or true if using SMTP on live hosting
    'smtp_debug' => false,
    'smtp_timeout' => 30,
    */
];
