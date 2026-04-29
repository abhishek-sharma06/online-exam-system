<?php
// Home Page
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: candidate/dashboard.php');
    }
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Exam System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Welcome to Exam System</h1>
        <p>Please <a href="login.php">login</a> or <a href="register.php">register</a> to continue.</p>
    </div>
</body>
</html>
