<?php
// ==========================================
// FILE: login_email.php
// PURPOSE: Login with email verification check
// ==========================================

session_start();
require_once 'config/database.php';

$error = '';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['is_verified']) {
    header('Location: candidate/dashboard.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = '❌ Email and password are required';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Find user by email
            $stmt = $db->prepare("
                SELECT id, email, password, full_name, is_verified, role 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                $error = '❌ Invalid email or password';
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if (!password_verify($password, $user['password'])) {
                    $error = '❌ Invalid email or password';
                } 
                // Check if email is verified
                elseif ($user['is_verified'] == 0) {
                    $error = '❌ Your email is not verified yet. Please check your email for the verification link.';
                } 
                // Login successful
                else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['is_verified'] = 1;
                    $_SESSION['role'] = $user['role'] ?? 'candidate';
                    
                    // Redirect to dashboard
                    if ($user['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                    } else {
                        header('Location: candidate/dashboard.php');
                    }
                    exit;
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
    <title>Login - ExamSystem Pro</title>
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
        .register-link {
            text-align: center;
            margin-top: 20px;
        }
        .register-link a {
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
                        <i class="fas fa-sign-in-alt"></i>
                        <h2>Login</h2>
                        <p class="mb-0">ExamSystem Pro</p>
                    </div>
                    <div class="auth-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-arrow-right-to-bracket me-2"></i>Login
                            </button>
                        </form>

                        <div class="register-link">
                            Don't have an account? <a href="register_email.php">Register here</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
