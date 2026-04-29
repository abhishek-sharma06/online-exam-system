<?php
// ==========================================
// FILE: candidate/results.php
// PURPOSE: Display exam results for candidate
// ==========================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a candidate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

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

// Fetch all completed attempts for this candidate
try {
    $query = "SELECT ea.*, e.title as exam_title 
              FROM exam_attempts ea 
              JOIN exams e ON ea.exam_id = e.id 
              WHERE ea.user_id = ? AND ea.status = 'completed'
              ORDER BY ea.completed_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching results: " . $e->getMessage());
    $results = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand">My Exam Results</span>
            <div>
                Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> |
                <a href="dashboard.php" class="text-white">Dashboard</a> |
                <a href="../logout.php" class="text-white">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Your Exam Results</h2>
        
        <?php if(count($results) > 0): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Exam Title</th>
                        <th>Total Marks</th>
                        <th>Obtained Marks</th>
                        <th>Percentage</th>
                        <th>Status</th>
                        <th>Completed On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($results as $result): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                        <td><?php echo $result['total_marks']; ?></td>
                        <td><?php echo $result['obtained_marks']; ?></td>
                        <td><?php echo $result['percentage']; ?>%</td>
                        <td>
                            <span class="badge bg-<?php echo $result['status'] == 'completed' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($result['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d-m-Y H:i', strtotime($result['completed_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">You haven't taken any exams yet.</div>
        <?php endif; ?>
        
        <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
</body>
</html>