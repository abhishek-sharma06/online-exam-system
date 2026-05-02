<?php
// ==========================================
// FILE: register.php
// PURPOSE: User registration page with OTP verification
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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and trim inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($full_name)) {
        $error = '❌ Full name is required';
    } elseif (empty($email)) {
        $error = '❌ Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ Invalid email format';
    } elseif (empty($username)) {
        $error = '❌ Username is required';
    } elseif (strlen($username) < 3) {
        $error = '❌ Username must be at least 3 characters';
    } elseif (empty($password)) {
        $error = '❌ Password is required';
    } elseif (strlen($password) < 6) {
        $error = '❌ Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = '❌ Passwords do not match';
    } else {
        // Check if email already exists
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            
            if ($stmt->rowCount() > 0) {
                $error = '❌ Email or username already exists. Please use different credentials.';
            } else {
                // Insert new user with 'candidate' role and email_verified = false
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $created_at = date('Y-m-d H:i:s');
                
                // Generate OTP
                $otp = Email::generateOTP();
                $otp_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $insert_stmt = $db->prepare("
                    INSERT INTO users (full_name, email, username, password, otp, otp_expires_at, 
                                      email_verified, role, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, FALSE, 'candidate', 'active', ?)
                ");
                
                if ($insert_stmt->execute([$full_name, $email, $username, $hashed_password, $otp, $otp_expires_at, $created_at])) {
                    // Send OTP email
                    $emailer = new Email();
                    if ($emailer->sendOTPEmail($email, $otp, $full_name)) {
                        // Store registration data in session for verification page
                        $_SESSION['pending_verification'] = [
                            'email' => $email,
                            'full_name' => $full_name,
                            'username' => $username
                        ];
                        
                        $success = '✓ Registration successful! Redirecting to email verification...';
                        header('Refresh: 2; url=verify_email.php');
                    } else {
                        $error = '❌ Registration successful but failed to send verification email. Please try again or contact support.';
                    }
                } else {
                    $error = '❌ Registration failed. Please try again.';
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
    <title>Register - ExamSystem Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .auth-card {
            transform: translateY(-20px);
            animation: fadeIn 0.6s ease-out;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        .auth-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .auth-header i {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .auth-header h2 {
            margin: 10px 0 5px;
            font-weight: 700;
            font-size: 1.8em;
        }
        .auth-header p {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .auth-body {
            padding: 40px;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px 15px;
            font-size: 0.95em;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 0.9em;
        }
        .input-icon input {
            padding-left: 45px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .password-strength {
            margin-top: 8px;
            font-size: 0.85em;
        }
        .strength-bar {
            height: 5px;
            background: #ddd;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        .strength-bar-fill {
            height: 100%;
            background: #red;
            width: 0%;
            transition: all 0.3s;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 25px;
            border: none;
        }
        .fade-in {
            animation: fadeIn 0.4s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .text-muted-small {
            font-size: 0.85em;
            color: #999;
            margin-top: 8px;
        }
        hr {
            margin: 30px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        .link-register {
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            transition: all 0.3s;
        }
        .link-register:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="auth-card card">
                    <div class="auth-header">
                        <i class="fas fa-graduation-cap"></i>
                        <h2>ExamSystem Pro</h2>
                        <p class="mb-0">Create Your Account</p>
                    </div>
                    <div class="auth-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger fade-in" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success fade-in" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3 input-icon">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    name="full_name" 
                                    class="form-control" 
                                    placeholder="Full Name" 
                                    required
                                    maxlength="100">
                                <div class="text-muted-small">Your full name as it appears on official documents</div>
                            </div>

                            <div class="mb-3 input-icon">
                                <i class="fas fa-at"></i>
                                <input 
                                    type="text" 
                                    name="username" 
                                    class="form-control" 
                                    placeholder="Username" 
                                    required
                                    minlength="3"
                                    maxlength="50"
                                    pattern="[a-zA-Z0-9_]+"
                                    title="Username can only contain letters, numbers, and underscores">
                                <div class="text-muted-small">3+ characters (letters, numbers, underscores only)</div>
                            </div>

                            <div class="mb-3 input-icon">
                                <i class="fas fa-envelope"></i>
                                <input 
                                    type="email" 
                                    name="email" 
                                    class="form-control" 
                                    placeholder="Email Address" 
                                    required
                                    maxlength="100">
                                <div class="text-muted-small">We'll use this to verify your account</div>
                            </div>

                            <div class="mb-3 input-icon">
                                <i class="fas fa-lock"></i>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="password"
                                    class="form-control" 
                                    placeholder="Password" 
                                    required
                                    minlength="6"
                                    onchange="checkPasswordStrength()">
                                <div class="text-muted-small">Minimum 6 characters</div>
                                <div class="password-strength">
                                    <span id="strength-text"></span>
                                    <div class="strength-bar">
                                        <div id="strength-bar" class="strength-bar-fill"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 input-icon">
                                <i class="fas fa-lock"></i>
                                <input 
                                    type="password" 
                                    name="confirm_password" 
                                    class="form-control" 
                                    placeholder="Confirm Password" 
                                    required>
                                <div class="text-muted-small">Must match your password</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>

                        <hr>

                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php" class="link-register">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.length >= 10) strength += 25;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 15;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 10;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 25) {
                strengthText.textContent = '⚠️ Weak';
                strengthBar.style.background = '#dc3545';
            } else if (strength < 50) {
                strengthText.textContent = '📊 Fair';
                strengthBar.style.background = '#ffc107';
            } else if (strength < 75) {
                strengthText.textContent = '✓ Good';
                strengthBar.style.background = '#17a2b8';
            } else {
                strengthText.textContent = '✓✓ Strong';
                strengthBar.style.background = '#28a745';
            }
        }
    </script>
</body>
</html>
