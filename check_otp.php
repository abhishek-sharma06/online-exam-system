<?php
require_once 'config/database.php';

$email = $_GET['email'] ?? '';

if (empty($email)) {
    echo "<h2>Check OTP for User</h2>";
    echo "<form method='GET'>";
    echo "<label>Email: <input type='email' name='email' required></label>";
    echo "<button type='submit'>Check OTP</button>";
    echo "</form>";
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT username, full_name, verification_token, verification_token_expires_at, status, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() === 0) {
        echo "<h2>User not found</h2>";
        echo "<p>No user found with email: $email</p>";
    } else {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<h2>OTP Information for: " . htmlspecialchars($user['full_name']) . "</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Username</td><td>" . htmlspecialchars($user['username']) . "</td></tr>";
        echo "<tr><td>Email</td><td>" . htmlspecialchars($email) . "</td></tr>";
        echo "<tr><td>OTP</td><td><strong style='color:red; font-size:18px;'>" . htmlspecialchars($user['verification_token']) . "</strong></td></tr>";
        echo "<tr><td>Expires At</td><td>" . htmlspecialchars($user['verification_token_expires_at']) . "</td></tr>";
        echo "<tr><td>Status</td><td>" . htmlspecialchars($user['status']) . "</td></tr>";
        echo "<tr><td>Email Verified</td><td>" . ($user['email_verified'] ? 'Yes' : 'No') . "</td></tr>";
        echo "</table>";

        if (!empty($user['verification_token'])) {
            echo "<br><strong>Use this OTP to verify your account at:</strong> <a href='verify_otp.php?email=" . urlencode($email) . "'>verify_otp.php</a>";
        }
    }
} catch (PDOException $e) {
    echo "<h2>Database Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>