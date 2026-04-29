<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if (!$auth->isAuthenticated() || !$auth->isAdmin()) redirect('../login.php');

// Fetch stats
$stmt = $db->query("SELECT COUNT(*) as total_users FROM users WHERE role='candidate'");
$total_candidates = $stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(*) as total_exams FROM exams");
$total_exams = $stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(*) as total_attempts FROM results WHERE status='completed'");
$total_attempts = $stmt->fetchColumn();

// Recent exam performances
$stmt = $db->query("SELECT e.title, AVG(r.percentage) as avg_score, COUNT(r.id) as attempts 
                    FROM exams e LEFT JOIN results r ON e.id = r.exam_id 
                    GROUP BY e.id ORDER BY e.created_at DESC LIMIT 5");
$exam_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark"><div class="container">Admin Analytics <a href="dashboard.php" class="btn btn-outline-light">Back</a></div></nav>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4"><div class="card bg-primary text-white p-3"><h5>Total Candidates</h5><h2><?php echo $total_candidates; ?></h2></div></div>
            <div class="col-md-4"><div class="card bg-success text-white p-3"><h5>Total Exams</h5><h2><?php echo $total_exams; ?></h2></div></div>
            <div class="col-md-4"><div class="card bg-info text-white p-3"><h5>Completed Attempts</h5><h2><?php echo $total_attempts; ?></h2></div></div>
        </div>
        <div class="mt-4">
            <canvas id="examChart" width="400" height="200"></canvas>
        </div>
        <script>
            const ctx = document.getElementById('examChart').getContext('2d');
            const exams = <?php echo json_encode(array_column($exam_stats, 'title')); ?>;
            const scores = <?php echo json_encode(array_column($exam_stats, 'avg_score')); ?>;
            new Chart(ctx, { type: 'bar', data: { labels: exams, datasets: [{ label: 'Average Score (%)', data: scores, backgroundColor: '#007bff' }] } });
        </script>
    </div>
</body>
</html>