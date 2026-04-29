<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if ($auth->isAuthenticated()) {
    if ($auth->isAdmin()) header('Location: admin/dashboard.php');
    else header('Location: candidate/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    if ($auth->login($username, $password, $role)) {
        if ($role === 'admin') header('Location: admin/dashboard.php');
        else header('Location: candidate/dashboard.php');
        exit;
    } else {
        $error = 'Invalid username/email or password! Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamSystem Pro - Login</title>
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
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }
        .input-icon input {
            padding-left: 45px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="auth-card">
                    <div class="auth-header">
                        <i class="fas fa-graduation-cap"></i>
                        <h2>ExamSystem Pro</h2>
                        <p class="mb-0">Secure Online Examination Platform</p>
                    </div>
                    <div class="auth-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger fade-in">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3 input-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="text" name="username" class="form-control" placeholder="Username or Email" required>
                            </div>
                            <div class="mb-3 input-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="form-control" placeholder="Password" required>
                            </div>
                            <div class="mb-3">
                                <select name="role" class="form-select" required>
                                    <option value="candidate">📖 I'm a Candidate</option>
                                    <option value="admin">⚙️ I'm an Administrator</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-arrow-right-to-bracket me-2"></i>Login to Dashboard
                            </button>
                        </form>
                        <hr class="my-4">
                        <div class="text-center">
                            <p class="mb-0">New to ExamSystem? <a href="register.php" class="text-decoration-none fw-bold">Create Account</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>