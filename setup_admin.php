<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "╔════════════════════════════════════════════════════╗\n";
echo "║      Admin Password Setup and Reset                ║\n";
echo "╚════════════════════════════════════════════════════╝\n\n";

// Create a new admin account with password "admin123"
$username = "admin";
$password = "admin123";
$full_name = "System Administrator";
$email = "admin@examsystem.local";
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Check if admin already exists
$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);

if ($stmt->rowCount() > 0) {
    // Update existing admin password
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->execute([$hashed_password, $username]);
    echo "✓ Admin password updated!\n";
} else {
    // Create new admin account
    $stmt = $db->prepare("INSERT INTO users (full_name, email, username, password, role, status, created_at) VALUES (?, ?, ?, ?, 'admin', 'active', NOW())");
    $stmt->execute([$full_name, $email, $username, $hashed_password]);
    echo "✓ Admin account created!\n";
}

echo "\n📝 Admin Credentials:\n";
echo "   Username: $username\n";
echo "   Password: $password\n";
echo "   Email: $email\n";
echo "   Role: admin\n";

echo "\n╔════════════════════════════════════════════════════╗\n";
echo "║     You can now login with these credentials      ║\n";
echo "╚════════════════════════════════════════════════════╝\n";
?>
