<?php
// ==========================================
// FILE: verify_email.php
// PURPOSE: Redirect to new email verification link system (DEPRECATED)
// ==========================================

session_start();

// Clear any old session data
if (isset($_SESSION['pending_verification'])) {
    unset($_SESSION['pending_verification']);
}

$message = 'Email verification now uses secure links sent to your inbox. Please click the link in your verification email to activate your account.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verify-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            padding: 40px;
            text-align: center;
        }
        .verify-container h2 {
            color: #333;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .verify-container p {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.95em;
            line-height: 1.6;
        }
        .verify-icon {
            font-size: 3em;
            margin-bottom: 20px;
            color: #667eea;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: left;
            font-size: 0.9em;
        }
        .info-box strong {
            color: #333;
        }
        .btn {
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #663a91 100%);
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-icon">
            <i class="fas fa-envelope-open"></i>
        </div>
        <h2>Check Your Email</h2>
        <p><?php echo htmlspecialchars($message); ?></p>
        
        <div class="info-box">
            <strong>✓ Email Verification Simplified!</strong><br>
            <br>
            Our system now uses secure verification links. This means:
            <ul style="margin-top: 10px; padding-left: 20px; margin-bottom: 0;">
                <li>You'll receive an email with a verification link</li>
                <li>Click the link to instantly verify your account</li>
                <li>You'll be logged in automatically</li>
                <li>Links expire in 24 hours for security</li>
            </ul>
        </div>
        
        <p style="margin-top: 20px; color: #999; font-size: 0.85em;">
            Didn't receive the email? Check your spam folder or register again.
        </p>
        
        <div style="margin-top: 30px;">
            <a href="login.php" class="btn btn-primary">Go to Login</a>
        </div>
    </div>
</body>
</html>
