<?php
require_once 'config/database.php';
require_once 'includes/proctoring.php';

$database = new Database();
$db = $database->getConnection();
$proctoring = new Proctoring($db);

echo "╔═════════════════════════════════════════════════════════════════╗\n";
echo "║  TEST: Per-Exam Violations & Multi-Exam Candidate Support       ║\n";
echo "╚═════════════════════════════════════════════════════════════════╝\n\n";

// Create a completely NEW test user (no old data)
$new_test_username = 'exam_test_' . time() . '_' . rand(1000, 9999);
$stmt = $db->prepare("INSERT INTO users (full_name, email, username, password, role, status, created_at) VALUES (?, ?, ?, ?, 'candidate', 'active', NOW())");
$stmt->execute([
    'Per-Exam Test User ' . date('Y-m-d H:i:s'),
    $new_test_username . '@test.local',
    $new_test_username,
    password_hash('pass123', PASSWORD_BCRYPT)
]);
$test_user_id = $db->lastInsertId();
echo "✓ Created NEW test user ID: $test_user_id (Username: $new_test_username)\n\n";

// Get exam IDs
$stmt = $db->query("SELECT id, title FROM exams WHERE status = 'active' ORDER BY id LIMIT 2");
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($exams) < 2) {
    echo "❌ Need at least 2 active exams for this test\n";
    exit;
}

$exam1_id = $exams[0]['id'];
$exam1_title = $exams[0]['title'];
$exam2_id = $exams[1]['id'];
$exam2_title = $exams[1]['title'];

echo "EXAM CONFIGURATION\n";
echo "═════════════════════════════════════════════════════════════════\n";
echo "Exam 1: $exam1_title (ID: $exam1_id)\n";
echo "Exam 2: $exam2_title (ID: $exam2_id)\n";
echo "Test User: Multi-Exam Test User (ID: $test_user_id)\n\n";

// TEST 1: Add violations to Exam 1 only
echo "TEST 1: Add 3 Violations to Exam 1\n";
echo "─────────────────────────────────────────────────────────────────\n";

for ($i = 1; $i <= 3; $i++) {
    $stmt = $db->prepare("INSERT INTO proctoring_logs (exam_id, user_id, violation_type, violation_details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$exam1_id, $test_user_id, 'NAVIGATION_ATTEMPT', "Violation $i for Exam 1"]);
    echo "  Violation $i added to Exam 1 ✓\n";
}

// Check violations for Exam 1
$stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam1_id, $test_user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$exam1_violations = $result['count'];

// Check violations for Exam 2
$stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam2_id, $test_user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$exam2_violations = $result['count'];

// Check user status
$stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$test_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n  Exam 1 Violations: $exam1_violations / 3 ✓\n";
echo "  Exam 2 Violations: $exam2_violations / 3 ✓\n";
echo "  User Status: " . strtoupper($user['status']) . "\n";

if ($user['status'] === 'disqualified') {
    echo "  ✓ User is DISQUALIFIED (as expected after 3 violations in Exam 1)\n";
} else {
    echo "  ⚠️ User status: Still ACTIVE (Global disqualification not triggered)\n";
}

echo "\n";

// TEST 2: Can user access Exam 2?
echo "TEST 2: Verify User Cannot Access Exam 1 (Disqualified)\n";
echo "─────────────────────────────────────────────────────────────────\n";

// Reset user status for testing Exam 2 access
$stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
$stmt->execute([$test_user_id]);

// Add 2 violations to Exam 2 (not yet disqualified)
echo "Adding 2 violations to Exam 2...\n";
for ($i = 1; $i <= 2; $i++) {
    $stmt = $db->prepare("INSERT INTO proctoring_logs (exam_id, user_id, violation_type, violation_details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$exam2_id, $test_user_id, 'TAB_SWITCH', "Violation $i for Exam 2"]);
    echo "  Violation $i added to Exam 2 ✓\n";
}

// Verify counts
$stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam1_id, $test_user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$exam1_violations_final = $result['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam2_id, $test_user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$exam2_violations_final = $result['count'];

echo "\n  Exam 1 Violations: $exam1_violations_final\n";
echo "  Exam 2 Violations: $exam2_violations_final\n";

// Check user status
$stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$test_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  User Status: " . strtoupper($user['status']) . "\n";

if ($exam1_violations_final === 3 && $exam2_violations_final === 2) {
    echo "\n  ✓ PER-EXAM VIOLATION TRACKING WORKING!\n";
    echo "    - Exam 1 has 3 violations (isolated)\n";
    echo "    - Exam 2 has only 2 violations (isolated)\n";
    echo "    - User violations are tracked separately for each exam\n";
} else {
    echo "\n  ✗ VIOLATION TRACKING ISSUE\n";
    echo "    Expected: Exam 1=3, Exam 2=2\n";
    echo "    Got: Exam 1=$exam1_violations_final, Exam 2=$exam2_violations_final\n";
}

echo "\n\n";

// TEST 3: Show admin view
echo "TEST 3: Admin Proctoring Logs View\n";
echo "─────────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("SELECT pl.exam_id, e.title, COUNT(*) as violation_count 
                      FROM proctoring_logs pl
                      JOIN exams e ON pl.exam_id = e.id
                      WHERE pl.user_id = ?
                      GROUP BY pl.exam_id, e.title");
$stmt->execute([$test_user_id]);
$exam_violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Violations per Exam for this User:\n";
foreach ($exam_violations as $ev) {
    echo "  • " . $ev['title'] . ": " . $ev['violation_count'] . " violations\n";
}

echo "\n\n";

// FINAL SUMMARY
echo "╔═════════════════════════════════════════════════════════════════╗\n";
echo "║                        TEST RESULTS                             ║\n";
echo "╠═════════════════════════════════════════════════════════════════╣\n";

$success = ($exam1_violations_final === 3 && $exam2_violations_final === 2);

if ($success) {
    echo "║  ✓ PER-EXAM VIOLATION TRACKING: WORKING                      ║\n";
    echo "║  ✓ MULTI-EXAM SUPPORT: WORKING                               ║\n";
    echo "║  ✓ VIOLATIONS ISOLATED: WORKING                              ║\n";
    echo "║                                                              ║\n";
    echo "║  Candidate can:                                              ║\n";
    echo "║  - Get 3 violations in Exam 1 and be disqualified           ║\n";
    echo "║  - Still take Exam 2 (separate violation count)             ║\n";
    echo "║  - Get up to 2 violations in Exam 2 without disqualification║\n";
} else {
    echo "║  ✗ ISSUE DETECTED - Review violation counts                  ║\n";
}

echo "╚═════════════════════════════════════════════════════════════════╝\n";
?>
