<?php
// ==========================================
// FILE: candidate/submit_exam.php
// PURPOSE: Process exam submission and calculate score
// ==========================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a candidate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit();
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

$exam_id = intval($_POST['exam_id']);
$user_id = $_SESSION['user_id'];

// Create exam_attempts table if it doesn't exist
try {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS exam_attempts (
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
    )";
    $db->exec($create_table_sql);
} catch(PDOException $e) {
    error_log("Note: exam_attempts table creation: " . $e->getMessage());
}

// Create exam_responses table if it doesn't exist
try {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS exam_responses (
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
    )";
    $db->exec($create_table_sql);
} catch(PDOException $e) {
    error_log("Note: exam_responses table creation: " . $e->getMessage());
}

// Check if already submitted
$stmt = $db->prepare("SELECT status FROM exam_attempts WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing && $existing['status'] === 'completed') {
    // Already submitted, redirect to results
    header("Location: results.php");
    exit();
}

// Fetch all questions for this exam
$stmt = $db->prepare("SELECT * FROM questions WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    header("Location: dashboard.php?error=no_questions");
    exit();
}

$total_marks = 0;
$obtained_marks = 0;

// Process each question and calculate score
foreach ($questions as $q) {
    $total_marks += intval($q['marks']);
    $answer_key = "question_" . $q['id'];
    $user_answer = isset($_POST[$answer_key]) ? trim($_POST[$answer_key]) : '';
    $is_correct = ($user_answer === $q['correct_answer']);
    
    if ($is_correct) {
        $obtained_marks += intval($q['marks']);
    }

    // Record user's response
    try {
        $stmt2 = $db->prepare("INSERT INTO exam_responses (exam_id, user_id, question_id, selected_answer, is_correct) 
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE selected_answer = ?, is_correct = ?");
        $stmt2->execute([$exam_id, $user_id, $q['id'], $user_answer, $is_correct ? 1 : 0, $user_answer, $is_correct ? 1 : 0]);
    } catch(PDOException $e) {
        error_log("Error saving response: " . $e->getMessage());
    }
}

$percentage = ($total_marks > 0) ? round(($obtained_marks / $total_marks) * 100, 2) : 0;

// Update exam attempt with results
try {
    $stmt = $db->prepare("UPDATE exam_attempts SET 
                         total_marks = ?, 
                         obtained_marks = ?, 
                         percentage = ?, 
                         completed_at = NOW(), 
                         status = 'completed'
                         WHERE exam_id = ? AND user_id = ?");
    $stmt->execute([$total_marks, $obtained_marks, $percentage, $exam_id, $user_id]);
} catch(PDOException $e) {
    error_log("Error updating attempt: " . $e->getMessage());
}

// Clear exam session variables
unset($_SESSION['exam_started']);
unset($_SESSION['exam_id']);

// Redirect to results page
header("Location: results.php");
exit();
?>