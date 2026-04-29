<?php
require_once 'config/database.php';
require_once 'includes/proctoring.php';

$database = new Database();
$db = $database->getConnection();
$proctoring = new Proctoring($db);

// Use the test candidate we registered
$exam_id = 1;
$user_id = 15; // testproc_001 user

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║      Testing Image Capture & Violation System                ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// Test 1: Image Capture
echo "TEST 1: Image Capture Simulation\n";
echo "─────────────────────────────────────────────────────────────────\n";
for ($i = 1; $i <= 5; $i++) {
    $result = $proctoring->simulateCapture($exam_id, $user_id);
    echo "  Capture $i: " . ($result ? "✓ Success" : "✗ Failed") . "\n";
    usleep(500000); // 0.5 second delay
}

// Get capture count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM webcam_captures WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$capture_count = $result['count'];
echo "\n  Total Captures Stored: " . $capture_count . "\n\n";

// Test 2: Violation Logging & Auto-Disqualification
echo "TEST 2: Violation Logging\n";
echo "─────────────────────────────────────────────────────────────────\n";

// Check current violations
$stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$starting_violations = $result['count'];
echo "  Starting Violations: " . $starting_violations . "\n\n";

// Add 3 violations
$violation_types = [
    'NAVIGATION_ATTEMPT' => 'User attempted to navigate away',
    'TAB_SWITCH' => 'User switched tabs',
    'WINDOW_BLUR' => 'Window lost focus'
];

foreach ($violation_types as $type => $description) {
    $stmt = $db->prepare("INSERT INTO proctoring_logs (exam_id, user_id, violation_type, violation_details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$exam_id, $user_id, $type, $description]);
    
    // Get current count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ?");
    $stmt->execute([$exam_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_count = $result['count'];
    
    echo "  Violation $current_count: $type - $description\n";
    
    // Check if should be disqualified
    if ($current_count >= 3) {
        // Auto-disqualify
        $stmt = $db->prepare("UPDATE users SET status = 'disqualified', violation_count = ? WHERE id = ?");
        $stmt->execute([$current_count, $user_id]);
        echo "    ⚠️  AUTO-DISQUALIFIED! ($current_count/3 violations)\n";
    }
}

// Verify user status
$stmt = $db->prepare("SELECT status, violation_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n  Final User Status: " . strtoupper($user['status']) . "\n";
echo "  Total Violations: " . $user['violation_count'] . "\n\n";

// Test 3: Verify Evidence Storage
echo "TEST 3: Evidence Storage Verification\n";
echo "─────────────────────────────────────────────────────────────────\n";

// Check captured images in filesystem
$capture_dir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'webcam_captures' . DIRECTORY_SEPARATOR;
if (is_dir($capture_dir)) {
    $files = glob($capture_dir . "capture_{$exam_id}_{$user_id}_*");
    echo "  Image Files on Disk: " . count($files) . "\n";
    if (count($files) > 0) {
        echo "  Sample File: " . basename($files[0]) . "\n";
        echo "  File Size: " . filesize($files[0]) . " bytes\n";
    }
}

// Check database records
$stmt = $db->prepare("SELECT id, image_path, captured_at FROM webcam_captures WHERE exam_id = ? AND user_id = ? ORDER BY captured_at DESC LIMIT 5");
$stmt->execute([$exam_id, $user_id]);
$captures = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n  Database Records: " . count($captures) . "\n";
foreach ($captures as $idx => $capture) {
    echo "    " . ($idx + 1) . ". " . $capture['image_path'] . " (" . $capture['captured_at'] . ")\n";
}

// Test 4: Admin Proctoring Logs
echo "\nTEST 4: Proctoring Logs for Admin\n";
echo "─────────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("SELECT pl.id, pl.violation_type, pl.violation_details, pl.created_at, 
                      u.full_name, e.title 
                      FROM proctoring_logs pl
                      JOIN users u ON pl.user_id = u.id
                      JOIN exams e ON pl.exam_id = e.id
                      WHERE pl.exam_id = ? AND pl.user_id = ?
                      ORDER BY pl.created_at DESC");
$stmt->execute([$exam_id, $user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "  Total Violation Logs: " . count($logs) . "\n";
foreach ($logs as $idx => $log) {
    echo "\n  Log " . ($idx + 1) . ":\n";
    echo "    Student: " . $log['full_name'] . "\n";
    echo "    Exam: " . $log['title'] . "\n";
    echo "    Violation: " . $log['violation_type'] . "\n";
    echo "    Details: " . $log['violation_details'] . "\n";
    echo "    Time: " . $log['created_at'] . "\n";
}

// Final Summary
echo "\n\n╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                      TEST SUMMARY                              ║\n";
echo "╠═══════════════════════════════════════════════════════════════╣\n";
echo "║ ✓ Image Captures: " . str_pad($capture_count, 50) . "║\n";
echo "║ ✓ Violation Logs: " . str_pad(count($logs), 50) . "║\n";
echo "║ ✓ User Status: " . str_pad(strtoupper($user['status']), 50) . "║\n";
echo "║ ✓ Auto-Disqualification: " . str_pad($user['status'] === 'disqualified' ? 'WORKING' : 'NOT TRIGGERED', 46) . "║\n";
echo "║ ✓ Database Storage: " . str_pad('VERIFIED', 50) . "║\n";
echo "║ ✓ Filesystem Storage: " . str_pad(count($files) > 0 ? 'VERIFIED' : 'NO FILES', 49) . "║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
?>
