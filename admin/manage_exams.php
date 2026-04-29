<?php
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

// Handle exam creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $duration = intval($_POST['duration']);
        
        try {
            $query = "INSERT INTO exams (title, description, duration_minutes, created_by, status) 
                      VALUES (?, ?, ?, ?, 'active')";
            $stmt = $db->prepare($query);
            $stmt->execute([$title, $description, $duration, $_SESSION['user_id']]);
            
            header("Location: manage_exams.php?msg=created");
            exit();
        } catch(PDOException $e) {
            error_log("Error creating exam: " . $e->getMessage());
        }
    } elseif ($_POST['action'] === 'delete') {
        $exam_id = intval($_POST['exam_id']);
        
        try {
            error_log("Delete requested for exam id: " . $exam_id . " by user: " . ($_SESSION['user_id'] ?? 'unknown'));
            // Perform safe cascade delete inside a transaction
            $db->beginTransaction();

            // 1) proctoring logs
            $stmt = $db->prepare("DELETE FROM proctoring_logs WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $proctoring_deleted = $stmt->rowCount();

            // 2) webcam captures: attempt to delete files from disk if present
            $stmt = $db->prepare("SELECT image_path FROM webcam_captures WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($files as $f) {
                $path = __DIR__ . '/../' . $f;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            $stmt = $db->prepare("DELETE FROM webcam_captures WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $captures_deleted = $stmt->rowCount();

            // 3) exam_responses
            $stmt = $db->prepare("DELETE FROM exam_responses WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $responses_deleted = $stmt->rowCount();

            // 4) exam_attempts
            $stmt = $db->prepare("DELETE FROM exam_attempts WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $attempts_deleted = $stmt->rowCount();

            // 5) questions
            $stmt = $db->prepare("DELETE FROM questions WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $questions_deleted = $stmt->rowCount();

            // 6) finally delete exam
            $stmt = $db->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->execute([$exam_id]);
            $exam_deleted = $stmt->rowCount();

            if ($exam_deleted > 0) {
                $db->commit();
                error_log("Exam $exam_id deleted; proctoring:$proctoring_deleted captures:$captures_deleted responses:$responses_deleted attempts:$attempts_deleted questions:$questions_deleted");
                header("Location: manage_exams.php?msg=deleted");
                exit();
            } else {
                $db->rollBack();
                error_log("Delete attempted but exam row not removed for id: " . $exam_id);
                header("Location: manage_exams.php?msg=not_deleted");
                exit();
            }
        } catch(PDOException $e) {
            error_log("Error deleting exam: " . $e->getMessage());
            $enc = base64_encode($e->getMessage());
            header("Location: manage_exams.php?msg=delete_error&err=".$enc);
            exit();
        }
    }
}

// Fetch all exams
try {
    $query = "SELECT e.*, COUNT(q.id) as question_count 
              FROM exams e 
              LEFT JOIN questions q ON e.id = q.exam_id 
              GROUP BY e.id 
              ORDER BY e.created_at DESC";
    $stmt = $db->query($query);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    $exams = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Manage Exams</span>
            <a href="dashboard.php" class="btn btn-outline-light">Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">Exam deleted successfully.</div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
            <div class="alert alert-success">Exam created successfully.</div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'not_deleted'): ?>
            <div class="alert alert-warning">Delete request processed but exam was not removed (maybe foreign key or not found).</div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'delete_error'): ?>
            <div class="alert alert-danger">Error deleting exam. Debug: <?php echo htmlspecialchars(base64_decode($_GET['err'] ?? '')); ?></div>
        <?php endif; ?>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createExamModal">
            Create New Exam
        </button>
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Duration</th>
                    <th>Questions</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($exams as $exam): ?>
                <tr>
                    <td><?php echo $exam['id']; ?></td>
                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                    <td><?php echo $exam['duration_minutes']; ?> min</td>
                    <td><?php echo $exam['question_count']; ?></td>
                    <td>
                        <span class="badge bg-<?php echo $exam['status'] == 'active' ? 'success' : 'danger'; ?>">
                            <?php echo $exam['status']; ?>
                        </span>
                    </td>
                    <td>
                        <a href="manage_questions.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">Questions</a>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display:inline;">
                            <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this exam?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Create Exam Modal -->
    <div class="modal fade" id="createExamModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Exam</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label>Exam Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create Exam</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>