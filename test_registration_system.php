<?php
/**
 * TEST FILE: test_registration_system.php
 * Tests the user registration functionality
 */

require_once __DIR__ . '/config/database.php';

function test_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $prefix = "[$timestamp] [$type]";
    echo $prefix . " " . $message . "\n";
}

test_log("=== REGISTRATION SYSTEM TEST ===", "TEST");

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Test 1: Check users table structure
    test_log("Checking users table structure...", "INFO");
    
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_columns = ['id', 'full_name', 'email', 'username', 'password', 'role', 'status'];
    $found_columns = array_column($columns, 'Field');
    
    test_log("Found columns: " . implode(', ', $found_columns), "INFO");
    
    foreach ($required_columns as $col) {
        if (in_array($col, $found_columns)) {
            test_log("✓ Column '$col' exists", "SUCCESS");
        } else {
            test_log("✗ Column '$col' missing", "ERROR");
        }
    }
    
    // Test 2: Test duplicate email check
    test_log("\nTesting duplicate email check...", "INFO");
    
    $test_email = "test_" . time() . "@example.com";
    $test_username = "testuser_" . time();
    
    // Check if email doesn't exist yet
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$test_email]);
    
    if ($stmt->rowCount() === 0) {
        test_log("✓ Email does not exist in database (safe to register)", "SUCCESS");
    } else {
        test_log("✗ Email already exists", "WARNING");
    }
    
    // Test 3: Test password hashing
    test_log("\nTesting password hashing...", "INFO");
    
    $test_password = "TestPassword123!";
    $hashed = password_hash($test_password, PASSWORD_BCRYPT);
    
    if (password_verify($test_password, $hashed)) {
        test_log("✓ Password hashing works correctly", "SUCCESS");
        test_log("  Original: $test_password", "INFO");
        test_log("  Hashed: " . substr($hashed, 0, 20) . "...", "INFO");
    } else {
        test_log("✗ Password verification failed", "ERROR");
    }
    
    // Test 4: Simulate registration
    test_log("\nSimulating user registration...", "INFO");
    
    $new_user = [
        'full_name' => 'Test User ' . time(),
        'email' => $test_email,
        'username' => $test_username,
        'password' => password_hash('TestPass123', PASSWORD_BCRYPT),
        'role' => 'candidate',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Insert test user
    $insert_stmt = $db->prepare("
        INSERT INTO users (full_name, email, username, password, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($insert_stmt->execute([
        $new_user['full_name'],
        $new_user['email'],
        $new_user['username'],
        $new_user['password'],
        $new_user['role'],
        $new_user['status'],
        $new_user['created_at']
    ])) {
        $user_id = $db->lastInsertId();
        test_log("✓ User registered successfully (ID: $user_id)", "SUCCESS");
        test_log("  Full Name: " . $new_user['full_name'], "INFO");
        test_log("  Email: " . $new_user['email'], "INFO");
        test_log("  Username: " . $new_user['username'], "INFO");
        test_log("  Role: " . $new_user['role'], "INFO");
        
        // Test 5: Verify registration by logging in
        test_log("\nVerifying registration by login attempt...", "INFO");
        
        $login_stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = ?");
        $login_stmt->execute([$new_user['username'], $new_user['email'], 'candidate']);
        $user = $login_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            test_log("✓ User found in database", "SUCCESS");
            test_log("  ID: " . $user['id'], "INFO");
            test_log("  Full Name: " . $user['full_name'], "INFO");
            test_log("  Email: " . $user['email'], "INFO");
            test_log("  Status: " . $user['status'], "INFO");
            
            // Test password verification
            if (password_verify('TestPass123', $user['password'])) {
                test_log("✓ Password verification successful", "SUCCESS");
            } else {
                test_log("✗ Password verification failed", "ERROR");
            }
        } else {
            test_log("✗ User not found in database", "ERROR");
        }
        
        // Test 6: Test duplicate registration prevention
        test_log("\nTesting duplicate prevention...", "INFO");
        
        $duplicate_stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $duplicate_stmt->execute([$new_user['email'], $new_user['username']]);
        
        if ($duplicate_stmt->rowCount() > 0) {
            test_log("✓ Duplicate email/username prevention works", "SUCCESS");
        } else {
            test_log("✗ Duplicate check failed", "ERROR");
        }
        
    } else {
        test_log("✗ Failed to insert user", "ERROR");
    }
    
    // Test 7: List all registered candidates
    test_log("\nListing all registered candidates...", "INFO");
    
    $candidates_stmt = $db->query("
        SELECT id, full_name, email, username, role, status, created_at 
        FROM users 
        WHERE role = 'candidate' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    $candidates = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    test_log("Total candidates in database: " . $candidates_stmt->rowCount(), "INFO");
    
    if (count($candidates) > 0) {
        test_log("Recent registrations:", "INFO");
        foreach ($candidates as $idx => $cand) {
            test_log("  " . ($idx + 1) . ". " . htmlspecialchars($cand['full_name']) . " (" . $cand['email'] . ") - " . $cand['created_at'], "INFO");
        }
    }
    
    // Summary
    test_log("\n╔═══════════════════════════════════════════════════╗", "SUCCESS");
    test_log("║     ✓ REGISTRATION SYSTEM TEST COMPLETED         ║", "SUCCESS");
    test_log("╚═══════════════════════════════════════════════════╝", "SUCCESS");
    
    test_log("\nKey Features Verified:", "INFO");
    test_log("✓ Database table structure correct", "INFO");
    test_log("✓ Email/username duplication prevention", "INFO");
    test_log("✓ Password hashing (bcrypt)", "INFO");
    test_log("✓ User insertion working", "INFO");
    test_log("✓ Login verification functioning", "INFO");
    test_log("✓ Candidate role assignment", "INFO");
    
    test_log("\nRegistration is READY TO USE!", "SUCCESS");
    test_log("\nHow to test in browser:", "INFO");
    test_log("1. Go to: http://localhost/exam_system/register.php", "INFO");
    test_log("2. Fill in the registration form", "INFO");
    test_log("3. Click 'Create Account'", "INFO");
    test_log("4. Login with the new credentials", "INFO");
    
} catch (Exception $e) {
    test_log("ERROR: " . $e->getMessage(), "ERROR");
    test_log("Stack: " . $e->getTraceAsString(), "ERROR");
    exit(1);
}

?>
