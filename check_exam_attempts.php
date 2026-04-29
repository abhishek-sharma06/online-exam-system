<?php
require_once 'config/database.php';

$db = (new Database())->getConnection();

// Get test user
$stmt = $db->prepare("SELECT id FROM users WHERE username = 'consenttest'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Test user not found\n";
    exit;
}

$user_id = $user['id'];
echo "Test User ID: $user_id\n\n";

// Check exam attempts
$stmt = $db->prepare("SELECT id, exam_id, user_id, status, started_at FROM exam_attempts WHERE user_id = ? ORDER BY started_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Exam Attempts for this user:\n";
if (empty($attempts)) {
    echo "  (No attempts yet - user hasn't passed consent screen)\n";
} else {
    foreach ($attempts as $attempt) {
        echo "  - Exam ID: {$attempt['exam_id']}, Status: {$attempt['status']}, Time: {$attempt['started_at']}\n";
    }
}
?>
