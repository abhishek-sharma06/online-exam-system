<?php
/**
 * Proctoring System Diagnostics Page
 * Used to test and verify the proctoring system is working correctly
 */

require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get violation count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$violations = $stmt->fetch(PDO::FETCH_ASSOC);
$violation_count = $violations['count'] ?? 0;

// Get recent violations
$stmt = $db->prepare("SELECT * FROM proctoring_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$recent_violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user status
$stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get exam attempts
$stmt = $db->prepare("SELECT e.title, ea.started_at, ea.status FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.user_id = ? ORDER BY ea.started_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Proctoring System Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-ok { color: #28a745; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .diag-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .violation-item { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-bottom: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Proctoring Diagnostics</span>
            <a href="<?php echo $_SESSION['role'] === 'candidate' ? 'candidate/dashboard.php' : 'admin/dashboard.php'; ?>" class="btn btn-light btn-sm">Back</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">Proctoring System Status</h2>

        <!-- User Status -->
        <div class="diag-card">
            <h5>User Status</h5>
            <p>User ID: <strong><?php echo $user_id; ?></strong></p>
            <p>Account Status: <span class="<?php echo $user['status'] === 'disqualified' ? 'status-error' : 'status-ok'; ?>">
                <?php echo strtoupper($user['status']); ?>
            </span></p>
        </div>

        <!-- Violation Summary -->
        <div class="diag-card">
            <h5>Violation Summary</h5>
            <p>Total Violations: <strong><?php echo $violation_count; ?></strong>/3</p>
            <p>
                Status: 
                <span class="<?php echo $violation_count === 0 ? 'status-ok' : ($violation_count < 3 ? 'status-warning' : 'status-error'); ?>">
                    <?php 
                    if ($violation_count === 0) echo "✓ NO VIOLATIONS";
                    elseif ($violation_count < 3) echo "⚠ WARNING - " . (3 - $violation_count) . " violations remaining";
                    else echo "✗ DISQUALIFIED";
                    ?>
                </span>
            </p>
        </div>

        <!-- Exam Attempts -->
        <div class="diag-card">
            <h5>Exam Attempts</h5>
            <?php if (count($exams) > 0): ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Started</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                            <td><small><?php echo date('Y-m-d H:i:s', strtotime($exam['started_at'])); ?></small></td>
                            <td><span class="badge bg-<?php echo $exam['status'] === 'completed' ? 'success' : 'primary'; ?>">
                                <?php echo ucfirst($exam['status']); ?>
                            </span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No exam attempts yet.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Violations -->
        <div class="diag-card">
            <h5>Recent Violations</h5>
            <?php if (count($recent_violations) > 0): ?>
                <?php foreach ($recent_violations as $v): ?>
                    <div class="violation-item">
                        <strong><?php echo htmlspecialchars($v['violation_type']); ?></strong>
                        <small class="d-block text-muted"><?php echo date('Y-m-d H:i:s', strtotime($v['created_at'])); ?></small>
                        <small><?php echo htmlspecialchars($v['description']); ?></small>
                        <?php if ($v['image_path']): ?>
                            <div class="mt-2">
                                <a href="<?php echo htmlspecialchars($v['image_path']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Evidence</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-success">✓ No violations recorded.</p>
            <?php endif; ?>
        </div>

        <!-- System Tests -->
        <div class="diag-card">
            <h5>System Tests</h5>
            <div id="test-results"></div>
            <button class="btn btn-primary" onclick="runTests()">Run Tests</button>
        </div>
    </div>

    <script>
        async function runTests() {
            const resultsDiv = document.getElementById('test-results');
            resultsDiv.innerHTML = '<p>Running tests...</p>';

            let results = '<h6>Test Results:</h6>';
            let passed = 0;
            let failed = 0;

            // Test 1: Database connection
            try {
                const r = await fetch('includes/proctoring.php', {
                    method: 'POST',
                    body: new FormData(document.createElement('form'))
                });
                results += '<div class="alert alert-warning">✓ Server is responding</div>';
                passed++;
            } catch (e) {
                results += `<div class="alert alert-danger">✗ Server error: ${e.message}</div>`;
                failed++;
            }

            // Test 2: Session check
            try {
                const formData = new FormData();
                formData.append('action', 'check_status');
                const r = await fetch('includes/proctoring.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                if (r.ok) {
                    results += '<div class="alert alert-success">✓ Session is active</div>';
                    passed++;
                } else {
                    results += '<div class="alert alert-danger">✗ Session check failed (HTTP ' + r.status + ')</div>';
                    failed++;
                }
            } catch (e) {
                results += `<div class="alert alert-danger">✗ Session test error: ${e.message}</div>`;
                failed++;
            }

            // Test 3: Log violation (test violation)
            try {
                const formData = new FormData();
                formData.append('action', 'log_violation');
                formData.append('exam_id', '1');
                formData.append('violation_type', 'TEST_VIOLATION');
                formData.append('description', 'This is a test violation from diagnostics');
                
                const r = await fetch('includes/proctoring.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                if (r.ok) {
                    const json = await r.json();
                    if (json.success) {
                        results += '<div class="alert alert-success">✓ Violation logging works</div>';
                        passed++;
                    } else {
                        results += `<div class="alert alert-danger">✗ Violation logging failed: ${json.error}</div>`;
                        failed++;
                    }
                } else {
                    results += '<div class="alert alert-danger">✗ Violation logging HTTP error: ' + r.status + '</div>';
                    failed++;
                }
            } catch (e) {
                results += `<div class="alert alert-danger">✗ Violation test error: ${e.message}</div>`;
                failed++;
            }

            results += `<hr><p><strong>Results:</strong> ${passed} passed, ${failed} failed</p>`;
            resultsDiv.innerHTML = results;

            // Refresh page after 3 seconds to show new violation
            if (failed === 0) {
                setTimeout(() => location.reload(), 2000);
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
