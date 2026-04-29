<?php
// ==========================================
// FILE: setup_database.php
// PURPOSE: Create missing database tables
// ==========================================

require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h1>Exam System Database Setup</h1>";

// SQL commands to create missing tables
$tables = [
    'exam_attempts' => "
        CREATE TABLE IF NOT EXISTS exam_attempts (
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
            UNIQUE KEY unique_attempt (exam_id, user_id)
        )
    ",
    
    'exam_responses' => "
        CREATE TABLE IF NOT EXISTS exam_responses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            exam_id INT NOT NULL,
            user_id INT NOT NULL,
            question_id INT NOT NULL,
            selected_answer VARCHAR(1),
            is_correct BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (exam_id) REFERENCES exams(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (question_id) REFERENCES questions(id),
            UNIQUE KEY unique_response (exam_id, user_id, question_id)
        )
    "
];

$created_count = 0;
$failed_count = 0;

foreach($tables as $table_name => $sql) {
    echo "<h2>Creating table: <code>$table_name</code></h2>";
    
    try {
        $db->exec($sql);
        echo "<p style='color:green;'><strong>✓ Table '$table_name' created/verified successfully!</strong></p>";
        $created_count++;
    } catch(PDOException $e) {
        echo "<p style='color:red;'><strong>✗ Error with '$table_name':</strong></p>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        $failed_count++;
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>Tables created/verified: <strong style='color:green;'>$created_count</strong></p>";
if($failed_count > 0) {
    echo "<p>Errors: <strong style='color:red;'>$failed_count</strong></p>";
} else {
    echo "<p style='color:green;'><strong>All tables created successfully!</strong></p>";
}

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Go to <a href='admin/manage_exams.php'>Manage Exams</a> to create an exam</li>";
echo "<li>Add questions to your exam</li>";
echo "<li>Go to <a href='candidate/dashboard.php'>Candidate Dashboard</a> to see available exams</li>";
echo "</ol>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h1, h2 {
    color: #333;
}
code {
    background-color: #f0f0f0;
    padding: 2px 5px;
    border-radius: 3px;
}
p {
    line-height: 1.6;
}
</style>
