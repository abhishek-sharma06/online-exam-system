<?php
// ==========================================
// FILE: admin/evidence_gallery.php
// PURPOSE: Display all webcam captures and screenshots during exams
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

// Get filter parameters
$filter_exam = isset($_GET['exam']) ? intval($_GET['exam']) : 0;
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Build query for webcam captures
$query = "SELECT wc.*, e.title as exam_title, u.full_name, u.email
          FROM webcam_captures wc
          JOIN exams e ON wc.exam_id = e.id
          JOIN users u ON wc.user_id = u.id
          WHERE 1=1";
$params = [];

if ($filter_exam > 0) {
    $query .= " AND wc.exam_id = ?";
    $params[] = $filter_exam;
}
if ($filter_user > 0) {
    $query .= " AND wc.user_id = ?";
    $params[] = $filter_user;
}

$query .= " ORDER BY wc.captured_at DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $captures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching captures: " . $e->getMessage());
    $captures = [];
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
    $stmt = $db->query("SELECT DISTINCT u.id, u.full_name FROM users u JOIN webcam_captures wc ON u.id = wc.user_id ORDER BY u.full_name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $users = [];
}

// Get statistics
$stats_query = "SELECT COUNT(*) as total_captures, COUNT(DISTINCT exam_id) as exams_with_evidence, COUNT(DISTINCT user_id) as users_with_evidence FROM webcam_captures";
try {
    $stmt = $db->prepare($stats_query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $stats = ['total_captures' => 0, 'exams_with_evidence' => 0, 'users_with_evidence' => 0];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Evidence Gallery - Exam Proctoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .evidence-container { margin-top: 20px; }
        .evidence-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; padding: 20px 0; }
        .evidence-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .evidence-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .evidence-image { width: 100%; height: 200px; overflow: hidden; background: #f0f0f0; }
        .evidence-image img { width: 100%; height: 100%; object-fit: cover; }
        .evidence-info { padding: 12px; }
        .evidence-meta { font-size: 0.85rem; color: #666; margin: 5px 0; }
        .student-name { font-weight: bold; color: #333; }
        .exam-title { color: #0066cc; font-size: 0.9rem; }
        .capture-time { color: #999; font-size: 0.8rem; }
        .btn-view { width: 100%; margin-top: 10px; }
        .stats-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stat-item { text-align: center; padding: 15px; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #0066cc; }
        .stat-label { color: #666; font-size: 0.9rem; }
        .filter-section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 3rem; color: #ccc; margin-bottom: 20px; }
        .modal-image { max-width: 100%; height: auto; }
    </style>
</head>
<body>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-images"></i> Evidence Gallery</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-box">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total_captures']; ?></div>
                    <div class="stat-label">Total Evidence Items</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-box">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['exams_with_evidence']; ?></div>
                    <div class="stat-label">Exams with Evidence</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-box">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['users_with_evidence']; ?></div>
                    <div class="stat-label">Students with Evidence</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Filters</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label for="exam" class="form-label">Exam:</label>
                <select name="exam" id="exam" class="form-select">
                    <option value="0">All Exams</option>
                    <?php foreach($exams as $exam): ?>
                    <option value="<?php echo $exam['id']; ?>" <?php echo $filter_exam == $exam['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($exam['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="user" class="form-label">Student:</label>
                <select name="user" id="user" class="form-select">
                    <option value="0">All Students</option>
                    <?php foreach($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Evidence Grid -->
    <div class="evidence-container">
        <?php if (!empty($captures)): ?>
        <div class="evidence-grid">
            <?php foreach($captures as $capture): ?>
            <div class="evidence-card">
                <div class="evidence-image">
                    <img src="../<?php echo htmlspecialchars($capture['image_path']); ?>" alt="Evidence" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22150%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22200%22 height=%22150%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-family=%22Arial%22 font-size=%2214%22 fill=%22%23999%22%3EImage Not Found%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="evidence-info">
                    <div class="student-name"><?php echo htmlspecialchars($capture['full_name']); ?></div>
                    <div class="evidence-meta exam-title"><?php echo htmlspecialchars($capture['exam_title']); ?></div>
                    <div class="evidence-meta"><?php echo htmlspecialchars($capture['email']); ?></div>
                    <div class="evidence-meta capture-time">
                        <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i:s', strtotime($capture['captured_at'])); ?>
                    </div>
                    <a href="../<?php echo htmlspecialchars($capture['image_path']); ?>" target="_blank" class="btn btn-sm btn-info btn-view">
                        <i class="fas fa-expand"></i> View Full Size
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h4>No Evidence Found</h4>
            <p class="text-muted">There are no webcam captures available for the selected filters.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
