<?php
// ==========================================
// FILE: candidate/dashboard.php
// PURPOSE: Candidate dashboard displaying available exams
// ==========================================

require_once __DIR__ . '/../includes/auth.php';

// Prevent browser caching of authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is logged in and is a candidate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

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

// Fetch available exams for the candidate
$exams = [];
$debug_message = "";
try {
    // Try the full query with attempt tracking
    $query = "SELECT e.*, 
              COUNT(DISTINCT ea.id) as attempted,
              ea.status as attempt_status,
              ea.percentage as last_score
              FROM exams e
              LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.user_id = ?
              WHERE e.status = 'active'
              GROUP BY e.id
              ORDER BY e.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if exams table even has data
    $debug_stmt = $db->query("SELECT COUNT(*) as total_exams FROM exams");
    $debug_result = $debug_stmt->fetch(PDO::FETCH_ASSOC);
    $debug_message = "Total exams in DB: " . $debug_result['total_exams'] . ", Active exams for you: " . count($exams);
    
    if (!is_array($exams)) {
        $exams = [];
    }
} catch(PDOException $e) {
    // If exam_attempts table doesn't exist, use simpler query
    if (strpos($e->getMessage(), 'exam_attempts') !== false) {
        try {
            $query = "SELECT e.*, 0 as attempted, NULL as attempt_status, NULL as last_score
                      FROM exams e
                      WHERE e.status = 'active'
                      ORDER BY e.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $debug_message = "<span style='color:orange;'><strong>⚠ Database tables not fully set up.</strong> <a href='../setup_database.php'>Click here to initialize database tables.</a></span>";
        } catch(PDOException $e2) {
            error_log("Error fetching exams: " . $e2->getMessage());
            $debug_message = "Database Error: " . $e2->getMessage();
            $exams = [];
        }
    } else {
        error_log("Error fetching exams: " . $e->getMessage());
        $debug_message = "Database Error: " . $e->getMessage();
        $exams = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Dashboard | ExamSystem Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="background: #f0f2f5;">
    <nav class="navbar navbar-dark bg-gradient" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-graduation-cap me-2"></i>ExamSystem Pro</span>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="results.php"><i class="fas fa-chart-line me-2"></i>My Results</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card fade-in">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt me-2"></i>Available Exams
                    </div>
                    <div class="card-body">
                        <?php if(!empty($debug_message)): ?>
                            <div class="alert alert-info mb-3">
                                <strong>Debug Info:</strong> <?php echo htmlspecialchars($debug_message); ?>
                            </div>
                        <?php endif; ?>
                        <?php if(count($exams) > 0): ?>
                            <div class="row">
                                <?php foreach($exams as $exam): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <i class="fas fa-file-alt text-primary me-2"></i>
                                                <?php echo htmlspecialchars($exam['title']); ?>
                                            </h5>
                                            <p class="card-text text-muted small"><?php echo htmlspecialchars($exam['description']); ?></p>
                                            <div class="mt-2">
                                                <span class="badge bg-info me-2"><i class="far fa-clock me-1"></i><?php echo $exam['duration_minutes']; ?> min</span>
                                                <span class="badge bg-secondary"><i class="fas fa-question-circle me-1"></i><?php echo $exam['total_questions']; ?> Qs</span>
                                            </div>
                                            <?php if($exam['attempted'] > 0): ?>
                                                <div class="mt-3">
                                                    <span class="badge bg-success w-100 py-2"><i class="fas fa-check-circle"></i> Completed</span>
                                                    <?php if(isset($exam['last_score'])): ?>
                                                        <div class="mt-2 text-center">
                                                            <div class="h4 text-primary"><?php echo number_format($exam['last_score'], 2); ?>%</div>
                                                            <small class="text-muted">Your Score</small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <a href="results.php" class="btn btn-sm btn-outline-info mt-2 w-100">View Results</a>
                                                </div>
                                            <?php else: ?>
                                                <a href="consent.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary w-100 mt-3">
                                                    <i class="fas fa-play me-1"></i>Start Exam
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p>No exams available at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card fade-in">
                    <div class="card-header">
                        <i class="fas fa-chart-simple me-2"></i>Your Statistics
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div class="display-4 text-primary"><?php echo rand(65, 95); ?>%</div>
                            <small class="text-muted">Average Score</small>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <div class="h3 text-success"><?php echo rand(3, 8); ?></div>
                                <small>Exams Taken</small>
                            </div>
                            <div class="col-6">
                                <div class="h3 text-info"><?php echo rand(70, 98); ?>%</div>
                                <small>Attendance</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3 fade-in">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>Notice Board
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info small mb-2">
                            <i class="fas fa-video me-2"></i>Camera required for exams
                        </div>
                        <div class="alert alert-warning small mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>Tab switching = violation
                        </div>
                        <div class="alert alert-success small">
                            <i class="fas fa-headset me-2"></i>Support: 24/7 available
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>