<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "Admin Accounts in Database:\n";
echo "==================================\n";

$stmt = $db->query("SELECT id, username, full_name, role FROM users WHERE role = 'admin' LIMIT 5");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($admins)) {
    echo "No admin accounts found. Checking all users...\n";
    $stmt = $db->query("SELECT id, username, full_name, role FROM users LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "ID: {$user['id']} | Username: {$user['username']} | Role: {$user['role']}\n";
    }
} else {
    foreach ($admins as $admin) {
        echo "ID: {$admin['id']} | Username: {$admin['username']} | Name: {$admin['full_name']} | Role: {$admin['role']}\n";
    }
}
?>
