<?php
// ==========================================
// FILE: admin/manage_questions.php
// PURPOSE: Add, edit, delete questions for a specific exam
// ==========================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get exam ID from URL
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$exam_id) {
    header("Location: manage_exams.php");
    exit();
}

// Fetch exam details
try {
    $stmt = $db->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam) {
        header("Location: manage_exams.php");
        exit();
    }
} catch(PDOException $e) {
    error_log("Error fetching exam: " . $e->getMessage());
    header("Location: manage_exams.php");
    exit();
}

// ---------- Handle Add / Edit / Delete actions ----------

// Add new question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $question_text = sanitizeInput($_POST['question_text']);
    $option_a = sanitizeInput($_POST['option_a']);
    $option_b = sanitizeInput($_POST['option_b']);
    $option_c = sanitizeInput($_POST['option_c']);
    $option_d = sanitizeInput($_POST['option_d']);
    $correct_answer = sanitizeInput($_POST['correct_answer']);
    $marks = intval($_POST['marks']);

    try {
        $query = "INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, marks) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$exam_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks]);
        
        header("Location: manage_questions.php?exam_id=$exam_id&msg=added");
        exit();
    } catch(PDOException $e) {
        error_log("Error adding question: " . $e->getMessage());
    }
}

// Edit existing question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $question_id = intval($_POST['question_id']);
    $question_text = sanitizeInput($_POST['question_text']);
    $option_a = sanitizeInput($_POST['option_a']);
    $option_b = sanitizeInput($_POST['option_b']);
    $option_c = sanitizeInput($_POST['option_c']);
    $option_d = sanitizeInput($_POST['option_d']);
    $correct_answer = sanitizeInput($_POST['correct_answer']);
    $marks = intval($_POST['marks']);

    try {
        $query = "UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, marks=? WHERE id=?";
        $stmt = $db->prepare($query);
        $stmt->execute([$question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks, $question_id]);
        
        header("Location: manage_questions.php?exam_id=$exam_id&msg=edited");
        exit();
    } catch(PDOException $e) {
        error_log("Error editing question: " . $e->getMessage());
    }
}

// Delete question
if (isset($_GET['delete'])) {
    $question_id = intval($_GET['delete']);
    try {
        $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$question_id]);
        
        header("Location: manage_questions.php?exam_id=$exam_id&msg=deleted");
        exit();
    } catch(PDOException $e) {
        error_log("Error deleting question: " . $e->getMessage());
    }
}

// Fetch all questions of this exam
try {
    $stmt = $db->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching questions: " . $e->getMessage());
    $questions = [];
}
?>
<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addQuestionModal">Add Question</button>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Questions - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center">
            <h2>Questions for: <strong><?php echo htmlspecialchars($exam['title']); ?></strong></h2>
            <a href="manage_exams.php" class="btn btn-secondary">⬅ Back to Exams</a>
        </div>
        
        <div class="mt-3 mb-3">
            <!-- Button to open Add Question modal -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                <i class="fas fa-plus"></i> Add New Question
            </button>
            
            <!-- NEW: Link to Question Bank (Import/Export) -->
            <a href="question_bank.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-dark ms-2">
                <i class="fas fa-upload"></i> Import/Export Questions
            </a>
        </div>

        <!-- Questions Table -->
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Question</th>
                    <th>Option A</th>
                    <th>Option B</th>
                    <th>Option C</th>
                    <th>Option D</th>
                    <th>Correct</th>
                    <th>Marks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($questions) > 0): ?>
                    <?php foreach ($questions as $q): ?>
                    <tr>
                        <td><?php echo $q['id']; ?></td>
                        <td><?php echo htmlspecialchars(substr($q['question_text'], 0, 80)) . (strlen($q['question_text']) > 80 ? '…' : ''); ?></td>
                        <td><?php echo htmlspecialchars($q['option_a']); ?></td>
                        <td><?php echo htmlspecialchars($q['option_b']); ?></td>
                        <td><?php echo htmlspecialchars($q['option_c']); ?></td>
                        <td><?php echo htmlspecialchars($q['option_d']); ?></td>
                        <td><span class="badge bg-success"><?php echo $q['correct_answer']; ?></span></td>
                        <td><?php echo $q['marks']; ?></td>
                        <td>
                            <!-- Edit button (opens modal with pre-filled data) -->
                            <button class="btn btn-sm btn-warning edit-btn" 
                                    data-id="<?php echo $q['id']; ?>"
                                    data-text="<?php echo htmlspecialchars($q['question_text']); ?>"
                                    data-a="<?php echo htmlspecialchars($q['option_a']); ?>"
                                    data-b="<?php echo htmlspecialchars($q['option_b']); ?>"
                                    data-c="<?php echo htmlspecialchars($q['option_c']); ?>"
                                    data-d="<?php echo htmlspecialchars($q['option_d']); ?>"
                                    data-correct="<?php echo $q['correct_answer']; ?>"
                                    data-marks="<?php echo $q['marks']; ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <!-- Delete link -->
                            <a href="?exam_id=<?php echo $exam_id; ?>&delete=<?php echo $q['id']; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Delete this question?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center">No questions yet. Click "Add New Question" to start.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ========== MODAL: ADD QUESTION ========== -->
    <div class="modal fade" id="addQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Add New Question</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Question Text</label>
                            <textarea name="question_text" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><label>Option A</label><input type="text" name="option_a" class="form-control" required></div>
                            <div class="col-md-6"><label>Option B</label><input type="text" name="option_b" class="form-control" required></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6"><label>Option C</label><input type="text" name="option_c" class="form-control" required></div>
                            <div class="col-md-6"><label>Option D</label><input type="text" name="option_d" class="form-control" required></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <label>Correct Answer</label>
                                <select name="correct_answer" class="form-control" required>
                                    <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Marks</label>
                                <input type="number" name="marks" class="form-control" value="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========== MODAL: EDIT QUESTION ========== -->
    <div class="modal fade" id="editQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="question_id" id="edit_question_id">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">Edit Question</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Question Text</label>
                            <textarea name="question_text" id="edit_question_text" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><label>Option A</label><input type="text" name="option_a" id="edit_option_a" class="form-control" required></div>
                            <div class="col-md-6"><label>Option B</label><input type="text" name="option_b" id="edit_option_b" class="form-control" required></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6"><label>Option C</label><input type="text" name="option_c" id="edit_option_c" class="form-control" required></div>
                            <div class="col-md-6"><label>Option D</label><input type="text" name="option_d" id="edit_option_d" class="form-control" required></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <label>Correct Answer</label>
                                <select name="correct_answer" id="edit_correct_answer" class="form-control" required>
                                    <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Marks</label>
                                <input type="number" name="marks" id="edit_marks" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS + Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // When any "Edit" button is clicked, fill the edit modal with that question's data
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_question_id').value = this.dataset.id;
                document.getElementById('edit_question_text').value = this.dataset.text;
                document.getElementById('edit_option_a').value = this.dataset.a;
                document.getElementById('edit_option_b').value = this.dataset.b;
                document.getElementById('edit_option_c').value = this.dataset.c;
                document.getElementById('edit_option_d').value = this.dataset.d;
                document.getElementById('edit_correct_answer').value = this.dataset.correct;
                document.getElementById('edit_marks').value = this.dataset.marks;
                // Show the edit modal
                new bootstrap.Modal(document.getElementById('editQuestionModal')).show();
            });
        });
    </script>
</body>
</html>