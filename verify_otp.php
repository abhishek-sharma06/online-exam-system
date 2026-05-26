<?php
// ==========================================
// FILE: verify_otp.php
// PURPOSE: OTP verification page
// ==========================================

session_start();
require_once 'config/database.php';
require_once 'includes/email.php';

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

// Get email from session or GET
$email = $_SESSION['pending_otp_email'] ?? $_GET['email'] ?? '';

if (empty($email)) {
    $error = '❌ No email provided for verification.';
}

// Handle OTP verification form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($email)) {
    $entered_otp = trim($_POST['otp'] ?? '');
    
    if (empty($entered_otp)) {
        $error = '❌ Please enter the OTP.';
    } elseif (!preg_match('/^\d{6}$/', $entered_otp)) {
        $error = '❌ OTP must be 6 digits.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Find user with matching email and OTP
            $stmt = $db->prepare("
                SELECT id, email, verification_token, verification_token_expires_at, email_verified, role, full_name, username
                FROM users 
                WHERE email = ? AND verification_token = ?
            ");
            $stmt->execute([$email, $entered_otp]);
            
            if ($stmt->rowCount() === 0) {
                $error = '❌ Invalid OTP. Please check and try again.';
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if OTP has expired
                if (!empty($user['verification_token_expires_at']) && strtotime($user['verification_token_expires_at']) < time()) {
                    $error = '❌ OTP has expired. Please register again to get a new OTP.';
                } elseif ($user['email_verified']) {
                    // Email already verified
                    $success = '✓ Your email is already verified. You can now log in.';
                    header('Refresh: 2; url=login.php');
                } else {
                    // Mark email as verified and activate account
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
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['username'] = $user['username'];
                        
                        $success = '✓ Email verified successfully! Redirecting to dashboard...';
                        
                        // Clear pending OTP session
                        unset($_SESSION['pending_otp_email']);
                        
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
    <title>Verify OTP - ExamSystem Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', sans-serif;
        }
        .auth-card {
            transform: translateY(-20px);
            animation: fadeIn 0.6s ease-out;
        }
        .otp-input {
            text-align: center;
            font-size: 1.5em;
            letter-spacing: 0.5em;
        }
        .otp-input input {
            width: 100%;
            text-align: center;
            font-size: 1.5em;
            letter-spacing: 0.5em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="auth-card">
                    <div class="auth-header">
                        <i class="fas fa-key"></i>
                        <h2>Verify Your Email</h2>
                        <p class="mb-0">Enter the 6-digit OTP sent to your email</p>
                    </div>
                    <div class="auth-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger fade-in">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if($success): ?>
                            <div class="alert alert-success fade-in">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(empty($success)): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="otp" class="form-label">OTP Code</label>
                                <input type="text" name="otp" id="otp" class="form-control otp-input" 
                                       placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">OTP sent to: <?php echo htmlspecialchars($email); ?></small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-check me-2"></i>Verify OTP
                            </button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="register.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Back to Registration
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>