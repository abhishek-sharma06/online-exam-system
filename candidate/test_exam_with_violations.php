<?php
// ==========================================
// FILE: candidate/test_exam_with_violations.php
// PURPOSE: Test exam with simulated violations and captures
// ==========================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/proctoring.php';

// Check if user is logged in and is a candidate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get exam
$exam_id = intval($_GET['exam_id'] ?? 1);
$stmt = $db->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    echo "Exam not found";
    exit();
}

// Check if disqualified
$stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['status'] === 'disqualified') {
    echo "<div class='alert alert-danger'>You have been disqualified from this exam due to violations.</div>";
    exit();
}

// Initialize Proctoring class
$proctoring = new Proctoring($db);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'simulate_violation') {
        // Simulate a violation
        $violation_type = $_POST['violation_type'] ?? 'NAVIGATION_ATTEMPT';
        
        // Get current violation count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ?");
        $stmt->execute([$exam_id, $_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_violations = $result['count'];
        
        // Log violation
        $stmt = $db->prepare("INSERT INTO proctoring_logs (exam_id, user_id, violation_type, violation_details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$exam_id, $_SESSION['user_id'], $violation_type, 'Simulated violation for testing']);
        
        // Check if should be disqualified
        $current_violations++;
        if ($current_violations >= 3) {
            $stmt = $db->prepare("UPDATE users SET status = 'disqualified', violation_count = ? WHERE id = ?");
            $stmt->execute([$current_violations, $_SESSION['user_id']]);
            $_POST['msg'] = "⚠️ DISQUALIFIED! You received " . $current_violations . " violations. You have been removed from the exam.";
        } else {
            $_POST['msg'] = "✓ Violation recorded! (" . $current_violations . "/3)";
        }
    }
    
    if ($action === 'capture_image') {
        // Simulate image capture
        $result = $proctoring->simulateCapture($exam_id, $_SESSION['user_id']);
        $_POST['msg'] = $result ? "✓ Image captured and stored!" : "❌ Failed to capture";
    }
}

// Get violation stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([$exam_id, $_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$violation_count = $result['count'];

// Get captures
$stmt = $db->prepare("SELECT COUNT(*) as count FROM webcam_captures WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam_id, $_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$capture_count = $result['count'];

// Get logs for this exam
$stmt = $db->prepare("SELECT * FROM proctoring_logs WHERE exam_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$exam_id, $_SESSION['user_id']]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Exam - Proctoring Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .exam-container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .exam-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .stats-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #667eea; }
        .violation-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
        .violation-badge.danger { background: #f8d7da; color: #721c24; }
        .violation-badge.warning { background: #fff3cd; color: #856404; }
        .log-entry { background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 5px; border-left: 3px solid #667eea; }
        .btn-custom { margin: 5px; }
        .timer { font-size: 24px; font-weight: bold; color: red; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="exam-container">
            <div class="exam-header">
                <div class="row">
                    <div class="col-md-8">
                        <h2><i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($exam['title']); ?></h2>
                        <p>Proctored Exam Test Mode</p>
                    </div>
                    <div class="col-md-4 text-right">
                        <button class="btn btn-danger" onclick="window.location='dashboard.php'"><i class="fas fa-times"></i> Exit Exam</button>
                    </div>
                </div>
            </div>

            <?php if(isset($_POST['msg'])): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <strong><i class="fas fa-info-circle"></i></strong> <?php echo htmlspecialchars($_POST['msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="stats-box">
                        <h5><i class="fas fa-shield-alt"></i> Proctoring Status</h5>
                        <p><strong>Violations:</strong> 
                            <span class="violation-badge <?php echo $violation_count >= 3 ? 'danger' : ($violation_count >= 2 ? 'warning' : ''); ?>">
                                <?php echo $violation_count; ?> / 3
                            </span>
                        </p>
                        <p><strong>Images Captured:</strong> <span class="badge bg-primary"><?php echo $capture_count; ?></span></p>
                        <p><strong>Status:</strong> 
                            <?php if($user['status'] === 'disqualified'): ?>
                                <span class="badge bg-danger">DISQUALIFIED</span>
                            <?php else: ?>
                                <span class="badge bg-success">ACTIVE</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="stats-box">
                        <h5><i class="fas fa-camera"></i> Image Capture Control</h5>
                        <p><small>Simulate 13-15 second interval captures</small></p>
                        <form method="POST" action="test_exam_with_violations.php?exam_id=<?php echo $exam_id; ?>" style="display: inline;">
                            <input type="hidden" name="action" value="capture_image">
                            <button type="submit" class="btn btn-primary btn-sm btn-custom">
                                <i class="fas fa-camera"></i> Capture Image Now
                            </button>
                        </form>
                    </div>

                    <div class="stats-box">
                        <h5><i class="fas fa-exclamation-triangle"></i> Violation Simulator</h5>
                        <p><small>Simulate violations to test auto-disqualification</small></p>
                        <form method="POST" action="test_exam_with_violations.php?exam_id=<?php echo $exam_id; ?>">
                            <input type="hidden" name="action" value="simulate_violation">
                            <select name="violation_type" class="form-select form-select-sm mb-2">
                                <option value="NAVIGATION_ATTEMPT">Navigation Attempt (Left Page)</option>
                                <option value="TAB_SWITCH">Tab Switch</option>
                                <option value="WINDOW_BLUR">Window Out of Focus</option>
                                <option value="MULTIPLE_FACES">Multiple Faces Detected</option>
                            </select>
                            <button type="submit" class="btn btn-warning btn-sm btn-custom">
                                <i class="fas fa-exclamation"></i> Log Violation
                            </button>
                        </form>
                        <?php if($violation_count >= 3): ?>
                            <div class="alert alert-danger mt-2">
                                <strong>⚠️ DISQUALIFIED!</strong> You have received 3 or more violations.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="stats-box">
                        <h5><i class="fas fa-list"></i> Recent Violations & Events</h5>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php if(empty($logs)): ?>
                                <p class="text-muted">No violations yet</p>
                            <?php else: ?>
                                <?php foreach($logs as $log): ?>
                                    <div class="log-entry">
                                        <strong><?php echo htmlspecialchars($log['violation_type']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($log['violation_details'] ?? 'No details'); ?></small>
                                        <br>
                                        <small class="text-muted"><?php echo $log['created_at']; ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="stats-box">
                        <h5><i class="fas fa-book"></i> Exam Information</h5>
                        <p><strong>Duration:</strong> <?php echo $exam['duration_minutes']; ?> minutes</p>
                        <p><strong>Total Questions:</strong> 0</p>
                        <p><strong>Proctoring Active:</strong> <span class="badge bg-info">Yes</span></p>
                    </div>

                    <div class="alert alert-info">
                        <strong>ℹ️ Test Instructions:</strong>
                        <ul style="margin-bottom: 0; margin-top: 10px;">
                            <li>Click "Capture Image Now" to simulate camera captures (13-15 sec interval in real exam)</li>
                            <li>Click "Log Violation" to simulate violations</li>
                            <li>After 3 violations, you'll be auto-disqualified</li>
                            <li>Check Proctoring Logs admin page to see all captured evidence</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
