<?php
/**
 * Admin Suspected Cheating Reports Page
 */

require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get filters
$filter_exam = isset($_GET['exam']) ? intval($_GET['exam']) : 0;
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$filter_level = isset($_GET['level']) ? $_GET['level'] : '';

// Build query
$query = "SELECT sc.*, u.username, u.email, e.title as exam_title 
          FROM suspected_cheating sc
          LEFT JOIN users u ON sc.user_id = u.id
          LEFT JOIN exams e ON sc.exam_id = e.id
          WHERE 1=1";
$params = [];

if ($filter_exam) {
    $query .= " AND sc.exam_id = ?";
    $params[] = $filter_exam;
}
if ($filter_user) {
    $query .= " AND sc.user_id = ?";
    $params[] = $filter_user;
}
if ($filter_level) {
    $query .= " AND sc.suspicion_level = ?";
    $params[] = $filter_level;
}

$query .= " ORDER BY sc.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$suspected = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = ['total_suspected' => 0, 'high_suspicion' => 0, 'medium_suspicion' => 0, 'low_suspicion' => 0];
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM suspected_cheating");
    $stats['total_suspected'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM suspected_cheating WHERE suspicion_level = 'HIGH'");
    $stats['high_suspicion'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM suspected_cheating WHERE suspicion_level = 'MEDIUM'");
    $stats['medium_suspicion'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM suspected_cheating WHERE suspicion_level = 'LOW'");
    $stats['low_suspicion'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    error_log("Error getting stats: " . $e->getMessage());
}

// Get filter options
$stmt = $db->query("SELECT DISTINCT e.id, e.title FROM exams e ORDER BY e.title");
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT DISTINCT u.id, u.username FROM users u WHERE u.role = 'candidate' ORDER BY u.username");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Suspected Cheating Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .suspicion-high { background-color: #f8d7da; border-left: 4px solid #dc3545; }
        .suspicion-medium { background-color: #fff3cd; border-left: 4px solid #ffc107; }
        .suspicion-low { background-color: #d1ecf1; border-left: 4px solid #17a2b8; }
        .badge-high { background-color: #dc3545; }
        .badge-medium { background-color: #ffc107; }
        .badge-low { background-color: #17a2b8; }
        .evidence-link { cursor: pointer; color: #007bff; }
        .indicator-item { padding: 5px; background: #f5f5f5; margin: 3px; border-radius: 3px; display: inline-block; font-size: 0.9em; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <nav class="mb-4">
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        <span class="ms-3"><strong>Suspected Cheating Reports</strong></span>
    </nav>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Suspected</h5>
                    <h3 style="color: #6c757d;"><?php echo $stats['total_suspected']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">🔴 High Suspicion</h5>
                    <h3 style="color: #dc3545;"><?php echo $stats['high_suspicion']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">🟡 Medium Suspicion</h5>
                    <h3 style="color: #ffc107;"><?php echo $stats['medium_suspicion']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">🟢 Low Suspicion</h5>
                    <h3 style="color: #17a2b8;"><?php echo $stats['low_suspicion']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Exam</label>
                    <select name="exam" class="form-select">
                        <option value="">All Exams</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo $filter_exam == $exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Student</label>
                    <select name="user" class="form-select">
                        <option value="">All Students</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Suspicion Level</label>
                    <select name="level" class="form-select">
                        <option value="">All Levels</option>
                        <option value="HIGH" <?php echo $filter_level === 'HIGH' ? 'selected' : ''; ?>>High</option>
                        <option value="MEDIUM" <?php echo $filter_level === 'MEDIUM' ? 'selected' : ''; ?>>Medium</option>
                        <option value="LOW" <?php echo $filter_level === 'LOW' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Suspected Cases -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-exclamation-triangle"></i> Suspected Cheating Cases</h5>
        </div>
        <div class="card-body">
            <?php if (empty($suspected)): ?>
                <div class="alert alert-info">
                    ✓ No suspected cheating cases found.
                </div>
            <?php else: ?>
                <?php foreach ($suspected as $case): ?>
                    <div class="mb-3 p-3 suspicion-<?php echo strtolower($case['suspicion_level']); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <strong><?php echo htmlspecialchars($case['username']); ?></strong>
                                <span class="badge badge-<?php echo strtolower($case['suspicion_level']); ?>">
                                    <?php echo $case['suspicion_level']; ?> (Score: <?php echo $case['score']; ?>)
                                </span>
                                <br/>
                                <small class="text-muted">
                                    Email: <?php echo htmlspecialchars($case['email']); ?><br/>
                                    Exam: <?php echo htmlspecialchars($case['exam_title'] ?? 'Unknown'); ?><br/>
                                    Detection: <?php echo $case['detection_type']; ?><br/>
                                    Time: <?php echo date('M d, Y H:i:s', strtotime($case['created_at'])); ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <strong>Indicators:</strong>
                                <div>
                                    <?php 
                                    $indicators = json_decode($case['indicators'], true) ?? [];
                                    if (empty($indicators)):
                                    ?>
                                        <small class="text-muted">No specific indicators</small>
                                    <?php else: ?>
                                        <?php foreach ($indicators as $indicator): ?>
                                            <span class="indicator-item"><?php echo htmlspecialchars($indicator); ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-2">
                                    <?php if ($case['camera_image_path']): ?>
                                        <a href="../<?php echo htmlspecialchars($case['camera_image_path']); ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-camera"></i> Camera Image
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($case['screenshot_path']): ?>
                                        <a href="../<?php echo htmlspecialchars($case['screenshot_path']); ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-image"></i> Screenshot
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
