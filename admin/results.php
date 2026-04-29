<?php
// Admin Results Page
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is admin
if (!is_admin()) {
    header('Location: ../login.php');
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Results</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Exam Results</h1>
        <!-- Add results display functionality here -->
    </div>
</body>
</html>
