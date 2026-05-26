<?php
// ==========================================
// FILE: register_email.php
// PURPOSE: User registration with email verification
// ==========================================

session_start();
require_once 'config/database.php';
require_once 'includes/mailer.php';
require_once 'config/phpmailer.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['is_verified']) {
    header('Location: candidate/dashboard.php');
    exit;
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    
    // Validation
    if (empty($email)) {
        $error = '❌ Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ Invalid email format';
    } elseif (empty($password)) {
        $error = '❌ Password is required';
    } elseif (strlen($password) < 6) {
        $error = '❌ Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = '❌ Passwords do not match';
    } elseif (empty($full_name)) {
        $error = '❌ Full name is required';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = '❌ Email already registered. Please use a different email or login.';
            } else {
                // Generate verification token
                $verification_token = bin2hex(random_bytes(32)); // 64-character hex string
                $token_expiry = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_MINUTES . ' minutes'));
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $created_at = date('Y-m-d H:i:s');
                
                // Insert user
                $insert_stmt = $db->prepare("
                    INSERT INTO users (email, password, full_name, verification_token, token_expiry, is_verified, role, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, 'candidate', 'active', ?)
                ");
                
                if ($insert_stmt->execute([$email, $hashed_password, $full_name, $verification_token, $token_expiry, $created_at])) {
                    // Send verification email
                    $mailer = new Mailer();
                    if ($mailer->sendVerificationEmail($email, $full_name, $verification_token)) {
                        $success = '✓ Registration successful! Please check your email for the verification link.';
                        // Store in session for reference
                        $_SESSION['pending_verification'] = [
                            'email' => $email,
                            'full_name' => $full_name
                        ];
                    } else {
                        $error = '❌ Registration successful but failed to send verification email. Error: ' . $mailer->getLastError();
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
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .auth-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .auth-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .auth-header i {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .auth-header h2 {
            margin: 10px 0 5px;
            font-weight: 700;
        }
        .auth-body {
            padding: 40px;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #663a91 100%);
            color: white;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="auth-card">
                    <div class="auth-header">
                        <i class="fas fa-user-plus"></i>
                        <h2>Create Account</h2>
                        <p class="mb-0">ExamSystem Pro</p>
                    </div>
                    <div class="auth-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <div class="text-center">
                                <p class="text-muted">Redirecting to login page...</p>
                                <script>
                                    setTimeout(() => {
                                        window.location.href = 'login.php';
                                    }, 3000);
                                </script>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-user me-2"></i>Full Name</label>
                                    <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Enter password (min 6 characters)" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-arrow-right me-2"></i>Register Now
                                </button>
                            </form>

                            <div class="login-link">
                                Already have an account? <a href="login.php">Login here</a>
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
