<?php
// Logout Page - clear session and cookies and prevent cached pages from being shown
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Clear session data
$_SESSION = [];
if (ini_get("session.use_cookies")) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000,
		$params['path'], $params['domain'], $params['secure'], $params['httponly']
	);
}
session_unset();
session_destroy();

// Prevent caching of authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

header('Location: login.php');
exit;
