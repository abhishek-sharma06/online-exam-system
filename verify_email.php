<?php
// ==========================================
// FILE: verify_email.php
// PURPOSE: Email verification page with OTP verification
// ==========================================

session_start();
require_once 'config/database.php';

$error = '';
$success = '';

// Redirect if not pending verification
if (!isset($_SESSION['pending_verification'])) {
    header('Location: index.php');
    exit;
}

$pending_email = $_SESSION['pending_verification']['email'];

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $error = '❌ OTP is required';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Verify OTP
            $stmt = $db->prepare("
                SELECT id, otp, otp_expires_at, email_verified 
                FROM users 
                WHERE email = ? AND otp = ?
            ");
            $stmt->execute([$pending_email, $otp]);
            
            if ($stmt->rowCount() === 0) {
                $error = '❌ Invalid OTP. Please try again.';
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if OTP has expired
                if (strtotime($user['otp_expires_at']) < time()) {
                    $error = '❌ OTP has expired. Please register again.';
                } else {
                    // Mark email as verified
                    $update_stmt = $db->prepare("
                        UPDATE users 
                        SET email_verified = TRUE, otp = NULL, otp_expires_at = NULL 
                        WHERE id = ?
                    ");
                    
                    if ($update_stmt->execute([$user['id']])) {
                        $success = '✓ Email verified successfully! Redirecting to login...';
                        unset($_SESSION['pending_verification']);
                        header('Refresh: 2; url=login.php');
                    } else {
                        $error = '❌ Verification failed. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = '❌ Database error: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Verification - Exam System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .verify-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .verify-container h2 {
            text-align: center;
            color: #333;
        }
        .verify-container p {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.5);
        }
        .submit-btn {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .submit-btn:hover {
            background-color: #45a049;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #bee5eb;
        }
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        .back-link a {
            color: #4CAF50;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <h2>Email Verification</h2>
        <p>Please enter the OTP sent to your email: <strong><?php echo htmlspecialchars($pending_email); ?></strong></p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <div class="info">
                Check your email for the verification code. The OTP is valid for 15 minutes.
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="otp">One-Time Password (OTP):</label>
                    <input type="text" id="otp" name="otp" placeholder="Enter the 6-digit code" required autofocus maxlength="10">
                </div>
                <button type="submit" class="submit-btn">Verify Email</button>
            </form>
            
            <div class="back-link">
                <a href="register.php">Back to Registration</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
