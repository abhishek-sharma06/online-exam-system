<?php
// ==========================================
// FILE: admin/dashboard.php
// PURPOSE: Admin dashboard with statistics
// ==========================================

require_once __DIR__ . '/../includes/auth.php';

// Prevent browser caching of authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize stats array
$stats = [
    'candidates' => 0,
    'exams' => 0,
    'results' => 0,
    'attempts' => 0
];

try {
    // Get total candidates
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'candidate' AND status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['candidates'] = $result['count'] ?? 0;
    
    // Get active exams
    $stmt = $db->query("SELECT COUNT(*) as count FROM exams WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['exams'] = $result['count'] ?? 0;
    
    // Get completed exam results
    $stmt = $db->query("SELECT COUNT(*) as count FROM exam_attempts WHERE status = 'completed'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['results'] = $result['count'] ?? 0;
    
    // Get total attempts
    $stmt = $db->query("SELECT COUNT(*) as count FROM exam_attempts");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['attempts'] = $result['count'] ?? 0;
    
} catch(PDOException $e) {
    error_log("Error fetching admin stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | ExamSystem Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header text-center">
                <i class="fas fa-chalkboard-user fa-2x mb-2"></i>
                <h4>Admin Panel</h4>
                <small><?php echo $_SESSION['full_name']; ?></small>
            </div>
            <div class="sidebar-nav">
                <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="manage_exams.php"><i class="fas fa-file-alt"></i> Manage Exams</a>
                <a href="manage_candidates.php"><i class="fas fa-user-times"></i> Manage Candidates</a>
                <a href="results.php"><i class="fas fa-chart-line"></i> View Results</a>
                <a href="proctoring_logs.php"><i class="fas fa-video"></i> Proctoring Logs</a>
                <a href="evidence_gallery.php"><i class="fas fa-images"></i> Evidence Gallery</a>
                <a href="suspected_cheating.php"><i class="fas fa-exclamation-triangle"></i> Suspected Cheating</a>
                <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar">
                <h4 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview</h4>
                <div>
                    <span class="text-muted me-3"><i class="far fa-clock"></i> <?php echo date('F j, Y'); ?></span>
                    <span class="badge bg-primary">Admin</span>
                </div>
            </div>

            <div class="p-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card primary">
                            <i class="fas fa-users fa-2x"></i>
                            <div class="stat-value"><?php echo number_format($stats['candidates']); ?></div>
                            <div class="stat-label">Total Candidates</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <i class="fas fa-file-alt fa-2x"></i>
                            <div class="stat-value"><?php echo number_format($stats['exams']); ?></div>
                            <div class="stat-label">Active Exams</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card info">
                            <i class="fas fa-check-circle fa-2x"></i>
                            <div class="stat-value"><?php echo number_format($stats['results']); ?></div>
                            <div class="stat-label">Completed Exams</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <i class="fas fa-chart-line fa-2x"></i>
                            <div class="stat-value"><?php echo round(($stats['results'] / max($stats['exams'],1)) * 100); ?>%</div>
                            <div class="stat-label">Completion Rate</div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-line me-2"></i>Recent Performance
                            </div>
                            <div class="card-body">
                                <canvas id="performanceChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-bell me-2"></i>Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="manage_exams.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create New Exam</a>
                                    <a href="manage_exams.php" class="btn btn-success"><i class="fas fa-edit me-2"></i>Manage Questions</a>
                                    <a href="proctoring_logs.php" class="btn btn-info"><i class="fas fa-eye me-2"></i>View Violations</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sample chart data - replace with your actual data
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Exams Taken',
                    data: [12, 19, 15, 25, 22, 30],
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>