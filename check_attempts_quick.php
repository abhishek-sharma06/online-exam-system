<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->prepare('SELECT id FROM users WHERE username = "consenttest"');
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_id = $user['id'];

$stmt = $db->prepare('SELECT id, exam_id, status FROM exam_attempts WHERE user_id = ?');
$stmt->execute([$user_id]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'User ID: ' . $user_id . PHP_EOL;
echo 'Exam Attempts: ' . count($attempts) . PHP_EOL;
foreach ($attempts as $a) {
    echo '  - Exam ' . $a['exam_id'] . ': ' . $a['status'] . PHP_EOL;
}
?>
