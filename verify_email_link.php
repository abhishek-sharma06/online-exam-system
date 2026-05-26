<?php
// ==========================================
// FILE: verify_email_link.php
// PURPOSE: Email verification via link - logs user in and redirects to dashboard
// ==========================================

session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: candidate/dashboard.php');
    }
    exit;
}

// Check if token and email are provided
if (!isset($_GET['token']) || !isset($_GET['email'])) {
    $error = '❌ Invalid verification link. Please check the link in your email.';
} else {
    $token = trim($_GET['token']);
    $email = trim($_GET['email']);
    
    if (empty($token) || empty($email)) {
        $error = '❌ Invalid verification link. Missing token or email.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Find user with matching token and email
            $stmt = $db->prepare("
                SELECT id, email, verification_token, verification_token_expires_at, email_verified, role
                FROM users 
                WHERE email = ? AND verification_token = ?
            ");
            $stmt->execute([$email, $token]);
            
            if ($stmt->rowCount() === 0) {
                $error = '❌ Invalid verification link. Token does not match.';
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if token has expired
                if (!empty($user['verification_token_expires_at']) && strtotime($user['verification_token_expires_at']) < time()) {
                    $error = '❌ Verification link has expired. Please register again to get a new link.';
                } elseif ($user['email_verified']) {
                    // Email already verified
                    $success = '✓ Your email is already verified. You can now log in.';
                    header('Refresh: 2; url=login.php');
                } else {
                    // Mark email as verified
                    $update_stmt = $db->prepare("
                        UPDATE users 
                        SET email_verified = TRUE, status = 'active', verification_token = NULL, verification_token_expires_at = NULL 
                        WHERE id = ?
                    ");
                    
                    if ($update_stmt->execute([$user['id']])) {
                        // Auto-login the user
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Get full user details for session
                        $user_stmt = $db->prepare("SELECT username, full_name FROM users WHERE id = ?");
                        $user_stmt->execute([$user['id']]);
                        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $_SESSION['username'] = $user_data['username'];
                        $_SESSION['full_name'] = $user_data['full_name'];
                        
                        $success = '✓ Email verified successfully! Redirecting to dashboard...';
                        
                        // Redirect to appropriate dashboard
                        if ($user['role'] === 'admin') {
                            header('Refresh: 2; url=admin/dashboard.php');
                        } else {
                            header('Refresh: 2; url=candidate/dashboard.php');
                        }
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
        }
        .success-icon {
            color: #28a745;
        }
        .error-icon {
            color: #dc3545;
        }
        .loading-spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            text-align: left;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: left;
        }
        .btn {
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
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
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <?php if (!empty($success)): ?>
            <div class="verify-icon success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Verification Successful!</h2>
            <div class="success">
                <?php echo $success; ?>
            </div>
            <p>You will be redirected to your dashboard shortly...</p>
            <div class="loading-spinner"></div>
            <p style="font-size: 0.85em; color: #999; margin-top: 20px;">
                If you are not redirected automatically, <a href="candidate/dashboard.php" style="color: #667eea;">click here</a>.
            </p>
        <?php else: ?>
            <div class="verify-icon error-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h2>Verification Failed</h2>
            <div class="error">
                <?php echo $error ?: '❌ An unknown error occurred. Please try again.'; ?>
            </div>
            <p>You can try the following:</p>
            <div style="text-align: left; background: #f9f9f9; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 0.9em;">
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Go back to your email and click the verification link again</li>
                    <li>Check if the link has expired (links expire after 24 hours)</li>
                    <li>Register again to get a new verification link</li>
                </ul>
            </div>
            <div style="margin-top: 20px;">
                <a href="login.php" class="btn btn-primary">Go to Login</a>
                <a href="register.php" class="btn btn-secondary">Register Again</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
