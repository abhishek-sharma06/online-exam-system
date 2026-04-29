<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Generate correct hash for test123
$hash = password_hash('test123', PASSWORD_BCRYPT);

// Update all candidates
$stmt = $db->prepare("UPDATE users SET password = ? WHERE role = 'candidate'");
$stmt->execute([$hash]);

echo "✅ Passwords updated for all candidates\n";
echo "Hash: $hash\n";

// Verify
$stmt = $db->prepare("SELECT username, password FROM users WHERE role='candidate'");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($users as $user) {
    echo "User: {$user['username']}, Hash: {$user['password']}\n";
}
?>
