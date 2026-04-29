<?php
require_once 'config/database.php';

$db = (new Database())->getConnection();

echo "Fixing exam_attempts table to allow multiple retakes...\n\n";

// Check current table structure
$stmt = $db->query("SHOW CREATE TABLE exam_attempts");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Current table structure:\n";
echo $result['Create Table'] . "\n\n";

// Drop the old table
try {
    $db->exec("DROP TABLE IF EXISTS exam_attempts");
    echo "✓ Dropped old exam_attempts table\n\n";
} catch (Exception $e) {
    echo "Error dropping table: " . $e->getMessage() . "\n";
    exit;
}

// Create new table WITHOUT UNIQUE constraint
try {
    $create_table_sql = "CREATE TABLE exam_attempts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        exam_id INT NOT NULL,
        user_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        total_marks INT DEFAULT 0,
        obtained_marks INT DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0,
        status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES exams(id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX idx_exam_user (exam_id, user_id)
    )";
    
    $db->exec($create_table_sql);
    echo "✓ Created new exam_attempts table with INDEX (no UNIQUE constraint)\n\n";
    
    // Verify new structure
    $stmt = $db->query("SHOW CREATE TABLE exam_attempts");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "New table structure:\n";
    echo $result['Create Table'] . "\n\n";
    
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
    exit;
}

echo "✅ Table fixed! Multiple retakes are now supported.\n";
?>
