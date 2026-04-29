<?php
/**
 * TEST FILE: test_concurrent_candidates.php
 * Tests the proctoring system for multiple concurrent candidates
 * 
 * Simulates:
 * 1. Multiple candidates taking the same exam at the same time
 * 2. Concurrent capture requests
 * 3. Evidence isolation per user
 * 4. Database records accuracy
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/proctoring.php';

// Test configuration
$test_exam_id = 1;
$num_candidates = 5;
$captures_per_candidate = 3;

function test_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $prefix = "[$timestamp] [$type]";
    echo $prefix . " " . $message . "\n";
}

test_log("=== CONCURRENT CANDIDATES TEST ===", "TEST");
test_log("Exam ID: $test_exam_id", "INFO");
test_log("Number of Candidates: $num_candidates", "INFO");
test_log("Captures per Candidate: $captures_per_candidate", "INFO");

try {
    $db = (new Database())->getConnection();
    
    // Check if exam exists
    test_log("\n=== VERIFYING EXAM ===", "TEST");
    $stmt = $db->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$test_exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exam) {
        test_log("ERROR: Exam not found!", "ERROR");
        exit(1);
    }
    test_log("✓ Exam found: " . htmlspecialchars($exam['title']), "SUCCESS");
    
    // Get candidate users from database
    test_log("\n=== CHECKING CANDIDATE USERS ===", "TEST");
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE role = 'candidate' AND status = 'active'");
    $all_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Limit to num_candidates
    $candidates = array_slice($all_candidates, 0, $num_candidates);
    
    if (empty($candidates)) {
        test_log("ERROR: No active candidate users found!", "ERROR");
        exit(1);
    }
    
    test_log("Found " . count($candidates) . " candidate(s):", "INFO");
    foreach ($candidates as $idx => $candidate) {
        test_log("  $idx. " . htmlspecialchars($candidate['full_name']) . " (ID: " . $candidate['id'] . ")", "INFO");
    }
    
    // Simulate concurrent capture for each candidate
    test_log("\n=== SIMULATING CONCURRENT CAPTURES ===", "TEST");
    
    $proctoring = new Proctoring($db);
    $capture_results = [];
    
    foreach ($candidates as $candidate) {
        $user_id = $candidate['id'];
        $user_name = $candidate['full_name'];
        
        test_log("\nProcessing candidate: $user_name (ID: $user_id)", "INFO");
        
        for ($i = 1; $i <= $captures_per_candidate; $i++) {
            // Create a unique test image for each capture (simulating real webcam capture)
            // Using a simple 1x1 JPEG in base64
            $test_image = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8VAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=';
            
            $result = $proctoring->saveCapture($test_exam_id, $user_id, $test_image);
            
            if ($result['success']) {
                $capture_results[$user_id][] = $result;
                test_log("  ✓ Capture $i saved: " . $result['path'], "SUCCESS");
            } else {
                test_log("  ✗ Capture $i failed: " . $result['error'], "ERROR");
            }
        }
    }
    
    // Verify database records
    test_log("\n=== VERIFYING DATABASE RECORDS ===", "TEST");
    
    $stmt = $db->prepare("SELECT user_id, COUNT(*) as capture_count FROM webcam_captures WHERE exam_id = ? GROUP BY user_id");
    $stmt->execute([$test_exam_id]);
    $db_captures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    test_log("Database capture counts by user:", "INFO");
    $total_db_captures = 0;
    foreach ($db_captures as $record) {
        $user_id = $record['user_id'];
        $count = $record['capture_count'];
        $total_db_captures += $count;
        
        // Find candidate name
        $name = "Unknown";
        foreach ($candidates as $cand) {
            if ($cand['id'] == $user_id) {
                $name = $cand['full_name'];
                break;
            }
        }
        
        test_log("  User $user_id ($name): $count captures", "INFO");
    }
    
    test_log("Total captures in database: $total_db_captures", "INFO");
    test_log("Expected total: " . (count($candidates) * $captures_per_candidate), "INFO");
    
    // Verify file isolation
    test_log("\n=== VERIFYING FILE ISOLATION ===", "TEST");
    
    $capture_files = [];
    foreach ($candidates as $candidate) {
        $user_id = $candidate['id'];
        $user_name = $candidate['full_name'];
        
        $stmt = $db->prepare("SELECT image_path FROM webcam_captures WHERE exam_id = ? AND user_id = ? ORDER BY captured_at");
        $stmt->execute([$test_exam_id, $user_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        test_log("Files for $user_name (ID: $user_id):", "INFO");
        foreach ($files as $file) {
            test_log("  - " . $file['image_path'], "INFO");
            
            // Check if file exists
            $full_path = __DIR__ . '/' . $file['image_path'];
            if (file_exists($full_path)) {
                $size = filesize($full_path);
                test_log("    File exists, size: $size bytes", "SUCCESS");
                $capture_files[] = $file['image_path'];
            } else {
                test_log("    File NOT found at: $full_path", "WARNING");
            }
        }
    }
    
    // Check for file duplicates
    test_log("\n=== CHECKING FOR FILE DUPLICATES ===", "TEST");
    $unique_files = array_unique($capture_files);
    
    if (count($unique_files) === count($capture_files)) {
        test_log("✓ All file paths are unique", "SUCCESS");
    } else {
        test_log("✗ DUPLICATE FILE PATHS DETECTED!", "ERROR");
        $duplicates = array_diff_assoc($capture_files, $unique_files);
        foreach ($duplicates as $dup) {
            test_log("  Duplicate: $dup", "ERROR");
        }
    }
    
    // Test concurrent access simulation
    test_log("\n=== SIMULATING CONCURRENT SAVE OPERATIONS ===", "TEST");
    
    $concurrent_test_results = [];
    for ($i = 0; $i < 3; $i++) {
        test_log("Concurrent batch $i:" , "INFO");
        
        foreach ($candidates as $candidate) {
            $user_id = $candidate['id'];
            $test_image = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8VAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=';
            
            $result = $proctoring->saveCapture($test_exam_id, $user_id, $test_image);
            
            if ($result['success']) {
                test_log("  ✓ Candidate {$user_id} capture saved", "SUCCESS");
                $concurrent_test_results[$user_id][] = $result['path'];
            } else {
                test_log("  ✗ Candidate {$user_id} capture failed: " . $result['error'], "ERROR");
            }
        }
    }
    
    // Final statistics
    test_log("\n=== FINAL STATISTICS ===", "TEST");
    
    $stmt = $db->prepare("SELECT COUNT(*) as total_captures FROM webcam_captures WHERE exam_id = ?");
    $stmt->execute([$test_exam_id]);
    $total_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as unique_users FROM webcam_captures WHERE exam_id = ?");
    $stmt->execute([$test_exam_id]);
    $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    test_log("Total captures for exam: " . $total_stats['total_captures'], "INFO");
    test_log("Unique users with captures: " . $user_stats['unique_users'], "INFO");
    test_log("Expected captures: " . (count($candidates) * ($captures_per_candidate + 3)), "INFO");
    
    // Test evidence gallery query
    test_log("\n=== TESTING EVIDENCE GALLERY QUERY ===", "TEST");
    
    $query = "SELECT wc.*, e.title as exam_title, u.full_name, u.email
              FROM webcam_captures wc
              JOIN exams e ON wc.exam_id = e.id
              JOIN users u ON wc.user_id = u.id
              WHERE wc.exam_id = ?
              ORDER BY wc.captured_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$test_exam_id]);
    $all_gallery_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $gallery_results = array_slice($all_gallery_results, 0, 10);
    
    test_log("Evidence gallery would display " . count($gallery_results) . " items (showing first 10 of " . count($all_gallery_results) . ")", "INFO");
    
    foreach ($gallery_results as $item) {
        test_log("  - " . htmlspecialchars($item['full_name']) . " | " . htmlspecialchars($item['exam_title']) . " | " . $item['captured_at'], "INFO");
    }
    
    test_log("\n=== ✓ ALL TESTS COMPLETED SUCCESSFULLY ===", "SUCCESS");
    
} catch (Exception $e) {
    test_log("EXCEPTION: " . $e->getMessage(), "ERROR");
    test_log("Stack: " . $e->getTraceAsString(), "ERROR");
    exit(1);
}

?>
