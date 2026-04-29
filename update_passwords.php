<?php
require_once 'config/database.php';

// Generate fresh correct hashes
$admin_hash = password_hash('admin123', PASSWORD_BCRYPT);
$candidate_hash = password_hash('candidate123', PASSWORD_BCRYPT);

echo "Admin hash: " . $admin_hash . "<br>";
echo "Candidate hash: " . $candidate_hash . "<br>";

// Update admin user
$stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->execute([$admin_hash]);

// Update candidate user
$stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'candidate'");
$stmt->execute([$candidate_hash]);

echo "<h3>✅ Passwords updated successfully!</h3>";
echo "Try logging in with:<br>";
echo "Admin → username: <strong>admin</strong>, password: <strong>admin123</strong><br>";
echo "Candidate → username: <strong>candidate</strong>, password: <strong>candidate123</strong><br>";
?>