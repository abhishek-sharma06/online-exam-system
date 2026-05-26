<?php
// ==========================================
// FILE: admin/proctoring_logs.php
// PURPOSE: View and manage proctoring violations
// ==========================================

require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Create proctoring_logs table if it doesn't exist
try {
    $sql = "CREATE TABLE IF NOT EXISTS proctoring_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        exam_id INT NOT NULL,
        user_id INT NOT NULL,
        violation_type VARCHAR(50) NOT NULL,
        description TEXT,
        violation_details TEXT,
        violation_count INT DEFAULT 1,
        image_path VARCHAR(255),
        screenshot_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES exams(id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX idx_exam_user (exam_id, user_id)
    )";
    $db->exec($sql);
} catch(PDOException $e) {
    error_log("Error creating table: " . $e->getMessage());
}

// Get filter parameters
$filter_exam = isset($_GET['exam']) ? intval($_GET['exam']) : 0;
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

// Build query
$query = "SELECT pl.*, e.title as exam_title, u.full_name, u.email
          FROM proctoring_logs pl
          JOIN exams e ON pl.exam_id = e.id
          JOIN users u ON pl.user_id = u.id
          WHERE 1=1";
$params = [];

if ($filter_exam > 0) {
    $query .= " AND pl.exam_id = ?";
    $params[] = $filter_exam;
}
if ($filter_user > 0) {
    $query .= " AND pl.user_id = ?";
    $params[] = $filter_user;
}
if ($filter_type) {
    $query .= " AND pl.violation_type = ?";
    $params[] = $filter_type;
}

$query .= " ORDER BY pl.created_at DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching logs: " . $e->getMessage());
    $logs = [];
}

// Get list of exams for filter
try {
    $stmt = $db->query("SELECT id, title FROM exams ORDER BY title");
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $exams = [];
}

// Get list of users for filter
try {
    $stmt = $db->query("SELECT DISTINCT u.id, u.full_name FROM users u JOIN proctoring_logs pl ON u.id = pl.user_id ORDER BY u.full_name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $users = [];
}

// Get violation types for filter
try {
    $stmt = $db->query("SELECT DISTINCT violation_type FROM proctoring_logs ORDER BY violation_type");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $types = [];
}

// Get statistics
$stats = ['total_violations' => 0, 'total_disqualified' => 0, 'violations_by_type' => []];
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM proctoring_logs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_violations'] = $result['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'disqualified'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_disqualified'] = $result['total'];
    
    $stmt = $db->query("SELECT violation_type, COUNT(*) as count FROM proctoring_logs GROUP BY violation_type");
    $stats['violations_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error getting stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Proctoring Logs | ExamSystem Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stat-card { padding: 20px; border-radius: 10px; margin-bottom: 20px; color: white; }
        .stat-card.primary { background: #4361ee; }
        .stat-card.danger { background: #e63946; }
        .stat-card.warning { background: #f77f00; }
        .stat-value { font-size: 32px; font-weight: bold; }
        .violation-badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .violation-TAB_SWITCH { background-color: #ffc107; color: black; }
        .violation-WINDOW_BLUR { background-color: #ff6b6b; color: white; }
        .violation-RIGHT_CLICK { background-color: #ff8787; color: white; }
        .violation-COPY_ATTEMPT { background-color: #ffaa00; color: white; }
        .violation-PASTE_ATTEMPT { background-color: #ff7100; color: white; }
        .violation-DEV_TOOLS { background-color: #c92a2a; color: white; }
        .violation-FULLSCREEN_EXIT { background-color: #ff922b; color: white; }
        .violation-NAVIGATION_ATTEMPT { background-color: #a61e4d; color: white; }
        .violation-MULTIPLE_FACES { background-color: #800080; color: white; }
        .violation-NO_FACE_DETECTED { background-color: #000000; color: white; }
        .violation-UNAUTHORIZED_DEVICE { background-color: #d63031; color: white; }
        .violation-OFF_SCREEN_FOCUS { background-color: #0984e3; color: white; }
        .violation-LOW_LIGHT { background-color: #636e72; color: white; }
        .violation-SUSPICIOUS_AUDIO { background-color: #e67e22; color: white; }
    </style>
</head>
<body style="background: #f0f2f5;">
    <nav class="navbar navbar-dark bg-gradient" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
        <div class="container-fluid">
            <span class="navbar-brand"><i class="fas fa-shield-alt me-2"></i>Proctoring Logs</span>
            <a href="dashboard.php" class="btn btn-light btn-sm">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <div class="stat-value"><?php echo $stats['total_violations']; ?></div>
                    <div>Total Violations</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card danger">
                    <i class="fas fa-ban fa-2x mb-2"></i>
                    <div class="stat-value"><?php echo $stats['total_disqualified']; ?></div>
                    <div>Disqualified Students</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card warning">
                    <h5>Violations by Type</h5>
                    <div style="font-size: 14px;">
                        <?php foreach($stats['violations_by_type'] as $vtype): ?>
                        <div class="mb-1">
                            <span class="violation-badge violation-<?php echo htmlspecialchars($vtype['violation_type']); ?>">
                                <?php echo htmlspecialchars($vtype['violation_type']); ?>: <?php echo $vtype['count']; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-filter me-2"></i>Filters
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Exam</label>
                        <select name="exam" class="form-select">
                            <option value="0">All Exams</option>
                            <?php foreach($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo $filter_exam == $exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">User</label>
                        <select name="user" class="form-select">
                            <option value="0">All Users</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Violation Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach($types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['violation_type']); ?>" <?php echo $filter_type == $type['violation_type'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['violation_type']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Violation Logs
            </div>
            <div class="card-body">
                <?php if(count($logs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Exam</th>
                                <th>Violation Type</th>
                                <th>Description</th>
                                <th>Evidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($log['full_name']); ?></td>
                                <td><small><?php echo htmlspecialchars($log['email']); ?></small></td>
                                <td><?php echo htmlspecialchars($log['exam_title']); ?></td>
                                <td>
                                    <span class="violation-badge violation-<?php echo htmlspecialchars($log['violation_type']); ?>">
                                        <?php echo htmlspecialchars($log['violation_type']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($log['violation_details'] ?? ''); ?></small></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if(!empty($log['image_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($log['image_path']); ?>" target="_blank" class="btn btn-sm btn-info" title="Camera Photo">
                                            <i class="fas fa-camera"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if(!empty($log['screenshot_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($log['screenshot_path']); ?>" target="_blank" class="btn btn-sm btn-warning" title="Screenshot">
                                            <i class="fas fa-screenshot"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if(empty($log['image_path']) && empty($log['screenshot_path'])): ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>No proctoring violations found.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
