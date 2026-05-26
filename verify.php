<?php
// ==========================================
// FILE: verify.php
// PURPOSE: Email verification via token link
// ==========================================

session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if (!isset($_GET['token'])) {
    $error = '❌ Invalid verification link.';
} else {
    $token = trim($_GET['token']);

    if (empty($token)) {
        $error = '❌ Invalid token.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // ✅ FIXED COLUMN NAMES
            $stmt = $db->prepare("
                SELECT id, email, full_name, email_verified, verification_token_expires_at 
                FROM users 
                WHERE verification_token = ?
            ");
            $stmt->execute([$token]);

            if ($stmt->rowCount() === 0) {
                $error = '❌ Invalid verification token.';
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Already verified
                if ($user['email_verified'] == 1) {
                    $success = '✓ Email already verified. <a href="login.php">Login here</a>.';
                }

                // Expired token
                elseif (
                    empty($user['verification_token_expires_at']) ||
                    strtotime($user['verification_token_expires_at']) < time()
                ) {
                    $error = '❌ Verification link expired. Please register again.';
                }

                // Valid → verify user
                else {
                    $update_stmt = $db->prepare("
                        UPDATE users 
                        SET email_verified = 1, verification_token = NULL, status = 'active' 
                        WHERE id = ?
                    ");

                    if ($update_stmt->execute([$user['id']])) {

                        $success = '✓ Email verified successfully!';

                        // Auto login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['email_verified'] = 1;

                    } else {
                        $error = '❌ Verification failed.';
                    }
                }
            }

        } catch (PDOException $e) {
            $error = '❌ Database error.';
        }
    }
}
?>