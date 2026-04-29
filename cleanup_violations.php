<?php
require_once 'config/database.php';

$db = (new Database())->getConnection();

// Delete old test data
$db->exec('DELETE FROM proctoring_logs WHERE user_id IN (SELECT id FROM users WHERE username LIKE "test_%" OR username LIKE "multiexam%" OR full_name LIKE "%Test%")');
echo 'Cleaned up old violations ✓' . PHP_EOL;

// Verify cleanup
$stmt = $db->query('SELECT COUNT(*) as count FROM proctoring_logs');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'Total violations now: ' . $result['count'] . PHP_EOL;
?>
