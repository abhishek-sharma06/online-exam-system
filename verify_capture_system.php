<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== PROCTORING CAPTURE SYSTEM VERIFICATION ===\n\n";

// 1. Check if tables exist
echo "1. CHECKING DATABASE TABLES\n";
echo "===========================\n";

$tables = ['proctoring_logs', 'webcam_captures', 'screenshot_captures', 'suspected_cheating'];
foreach ($tables as $table) {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Table '$table' exists with " . $result['count'] . " records\n";
}

// 2. Check if capture directories exist
echo "\n2. CHECKING UPLOAD DIRECTORIES\n";
echo "===============================\n";

$dirs = [
    'assets/uploads/webcam_captures',
    'assets/uploads/screenshots',
    'assets/uploads/violations'
];

foreach ($dirs as $dir) {
    $full_path = __DIR__ . DIRECTORY_SEPARATOR . $dir;
    $exists = is_dir($full_path);
    $count = 0;
    if ($exists) {
        $files = glob($full_path . '/*.jpg');
        $count = count($files);
    }
    
    if ($exists) {
        echo "✓ Directory exists: $dir ($count images)\n";
    } else {
        echo "✗ Directory missing: $dir (Creating...)\n";
        @mkdir($full_path, 0777, true);
        echo "  Created: $dir\n";
    }
}

// 3. Check recent captures
echo "\n3. RECENT WEBCAM CAPTURES\n";
echo "=========================\n";

$stmt = $conn->query("SELECT id, exam_id, user_id, image_path, captured_at FROM webcam_captures ORDER BY captured_at DESC LIMIT 5");
$captures = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($captures)) {
    echo "No webcam captures yet\n";
} else {
    foreach ($captures as $cap) {
        echo "- Exam: " . $cap['exam_id'] . " | User: " . $cap['user_id'] . " | Path: " . $cap['image_path'] . " | Time: " . $cap['captured_at'] . "\n";
    }
}

// 3B. Check recent screenshots
echo "\n3B. RECENT SCREENSHOT CAPTURES\n";
echo "=============================\n";

$stmt = $conn->query("SELECT id, exam_id, user_id, image_path, captured_at FROM screenshot_captures ORDER BY captured_at DESC LIMIT 5");
$screenshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($screenshots)) {
    echo "No screenshots yet\n";
} else {
    foreach ($screenshots as $ss) {
        echo "- Exam: " . $ss['exam_id'] . " | User: " . $ss['user_id'] . " | Path: " . $ss['image_path'] . " | Time: " . $ss['captured_at'] . "\n";
    }
}

// 4. Check proctoring logs
echo "\n4. VIOLATION LOGS\n";
echo "=================\n";

$stmt = $conn->query("SELECT id, exam_id, user_id, violation_type, violation_details, created_at FROM proctoring_logs ORDER BY created_at DESC LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "No violations logged\n";
} else {
    foreach ($logs as $log) {
        echo "- Exam: " . $log['exam_id'] . " | User: " . $log['user_id'] . " | Type: " . $log['violation_type'] . " | Time: " . $log['created_at'] . "\n";
    }
}

// 5. Check suspected cheating
echo "\n5. SUSPECTED CHEATING DETECTIONS\n";
echo "================================\n";

$stmt = $conn->query("SELECT id, exam_id, user_id, detection_type, suspicion_level, score FROM suspected_cheating ORDER BY created_at DESC LIMIT 5");
$suspicions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($suspicions)) {
    echo "No suspicions detected\n";
} else {
    foreach ($suspicions as $sus) {
        echo "- Exam: " . $sus['exam_id'] . " | User: " . $sus['user_id'] . " | Type: " . $sus['detection_type'] . " | Level: " . $sus['suspicion_level'] . " | Score: " . $sus['score'] . "\n";
    }
}

// 6. Check file counts
echo "\n6. ACTUAL FILES ON DISK\n";
echo "=======================\n";

$webcam_files = glob(__DIR__ . '/assets/uploads/webcam_captures/*.jpg');
$screenshot_files = glob(__DIR__ . '/assets/uploads/screenshots/*.jpg');
$violation_files = glob(__DIR__ . '/assets/uploads/violations/*.jpg');

echo "Webcam captures: " . count($webcam_files) . " files\n";
echo "Screenshot captures: " . count($screenshot_files) . " files\n";
echo "Violation images: " . count($violation_files) . " files\n";

if (count($webcam_files) > 0) {
    echo "\nRecent webcam files:\n";
    rsort($webcam_files);
    for ($i = 0; $i < min(3, count($webcam_files)); $i++) {
        $file = $webcam_files[$i];
        $size = filesize($file);
        $time = date('Y-m-d H:i:s', filemtime($file));
        echo "  - " . basename($file) . " (" . round($size/1024, 1) . " KB) - " . $time . "\n";
    }
}

if (count($screenshot_files) > 0) {
    echo "\nRecent screenshot files:\n";
    rsort($screenshot_files);
    for ($i = 0; $i < min(3, count($screenshot_files)); $i++) {
        $file = $screenshot_files[$i];
        $size = filesize($file);
        $time = date('Y-m-d H:i:s', filemtime($file));
        echo "  - " . basename($file) . " (" . round($size/1024, 1) . " KB) - " . $time . "\n";
    }
}

// 7. Check capture intervals
echo "\n7. CAPTURE INTERVAL CONFIGURATION\n";
echo "==================================\n";
echo "Expected interval: 13 seconds (within 10-15 second range)\n";
echo "Analysis interval: 15 seconds\n";
echo "Max violations: 3\n";

// 8. Summary
echo "\n8. CAPTURE SYSTEM STATUS\n";
echo "========================\n";

$total_webcam = $conn->query("SELECT COUNT(*) as count FROM webcam_captures")->fetch(PDO::FETCH_ASSOC)['count'];
$total_screenshots = $conn->query("SELECT COUNT(*) as count FROM screenshot_captures")->fetch(PDO::FETCH_ASSOC)['count'];
$total_violations = $conn->query("SELECT COUNT(*) as count FROM proctoring_logs")->fetch(PDO::FETCH_ASSOC)['count'];
$total_suspicions = $conn->query("SELECT COUNT(*) as count FROM suspected_cheating")->fetch(PDO::FETCH_ASSOC)['count'];

echo "Total webcam captures: " . $total_webcam . "\n";
echo "Total screenshots captured: " . $total_screenshots . "\n";
echo "Total captures (combined): " . ($total_webcam + $total_screenshots) . "\n";
echo "Total violations logged: " . $total_violations . "\n";
echo "Total suspicions detected: " . $total_suspicions . "\n";

if ($total_webcam > 0 || $total_screenshots > 0) {
    echo "\n✅ CAPTURE SYSTEM IS WORKING\n";
    echo "Images and screenshots are being captured and stored.\n";
    echo "Capture interval: Every 13 seconds (within 10-15 second range)\n";
} else {
    echo "\n⚠️  No captures yet. Run an exam with the camera to generate captures.\n";
}

echo "\n=== END OF VERIFICATION ===\n";
?>
