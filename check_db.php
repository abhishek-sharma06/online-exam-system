<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== EXAMS ===\n";
$stmt = $conn->query('SELECT id, title, status, total_questions FROM exams');
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($exams as $e) {
    echo 'ID: '.$e['id'].' | Title: '.$e['title'].' | Status: '.$e['status'].' | Questions: '.$e['total_questions']."\n";
}

echo "\n=== QUESTIONS COUNT BY EXAM ===\n";
$stmt = $conn->query('SELECT exam_id, COUNT(*) as count FROM questions GROUP BY exam_id');
$qs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($qs as $q) {
    echo 'Exam ID: '.$q['exam_id'].' | Count: '.$q['count']."\n";
}

echo "\n=== ADDING TEST QUESTIONS ===\n";

// First, delete existing questions for exam 1
$stmt = $conn->prepare("DELETE FROM questions WHERE exam_id = ?");
$stmt->execute(array(1));
echo "Deleted existing questions for exam 1\n";

// Add questions to exam 1 (sales)
$questions_data = array(
    array(
        'exam_id' => 1,
        'question_text' => 'What is 2 + 2?',
        'option_a' => '3',
        'option_b' => '4',
        'option_c' => '5',
        'option_d' => '6',
        'correct_answer' => 'b',
        'marks' => 1
    ),
    array(
        'exam_id' => 1,
        'question_text' => 'What is the capital of France?',
        'option_a' => 'London',
        'option_b' => 'Berlin',
        'option_c' => 'Paris',
        'option_d' => 'Madrid',
        'correct_answer' => 'c',
        'marks' => 1
    ),
    array(
        'exam_id' => 1,
        'question_text' => 'What is 5 * 6?',
        'option_a' => '20',
        'option_b' => '30',
        'option_c' => '40',
        'option_d' => '50',
        'correct_answer' => 'b',
        'marks' => 1
    )
);

foreach($questions_data as $q) {
    $stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(array($q['exam_id'], $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['marks']));
}

echo "Added 3 test questions to exam 1\n";

// Update exam to set total questions
$stmt = $conn->prepare("UPDATE exams SET total_questions = (SELECT COUNT(*) FROM questions WHERE exam_id = ?) WHERE id = ?");
$stmt->execute(array(1, 1));

echo "Updated exam total_questions count\n";

echo "\n=== FINAL QUESTION COUNT ===\n";
$stmt = $conn->query('SELECT exam_id, COUNT(*) as count FROM questions GROUP BY exam_id');
$qs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($qs as $q) {
    echo 'Exam ID: '.$q['exam_id'].' | Count: '.$q['count']."\n";
}
?>
