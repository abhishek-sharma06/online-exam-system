<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Direct query
    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = ?");
    $stmt->execute([$username, $username, $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<h2>Login Debug Results</h2>";
    echo "Username/Email: $username<br>";
    echo "Role: $role<br>";

    if ($user) {
        echo "✅ User found!<br>";
        echo "ID: {$user['id']}<br>";
        echo "Username: {$user['username']}<br>";
        echo "Email: {$user['email']}<br>";
        echo "Status: {$user['status']}<br>";
        echo "Stored password hash: {$user['password']}<br>";

        if (password_verify($password, $user['password'])) {
            echo "✅✅✅ PASSWORD VERIFICATION SUCCEEDED!<br>";
            echo "The password you typed matches the database.<br>";
            echo "If you still can't login via normal page, the problem is in the session or redirect logic, not the password.<br>";
        } else {
            echo "❌❌❌ PASSWORD VERIFICATION FAILED!<br>";
            echo "The password you typed does NOT match the hash stored in database.<br>";
            echo "Solution: Update the password hash using the SQL below.<br>";
        }
    } else {
        echo "❌ No user found with username/email '$username' and role '$role'.<br>";
        echo "Check that you selected the correct role and the user exists in the database.<br>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Debug Login Tool</title></head>
<body>
<h2>Debug Login Tool</h2>
<form method="POST">
    <input type="text" name="username" placeholder="Username or Email" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <select name="role">
        <option value="admin">Administrator</option>
        <option value="candidate">Candidate</option>
    </select><br><br>
    <button type="submit">Debug Login</button>
</form>
</body>
</html>