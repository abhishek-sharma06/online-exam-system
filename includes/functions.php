<?php
function is_admin() {
    // Check if the user is logged in and has admin privileges
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}
function redirect($url) {
    header("Location: $url");
    exit;
}
?>