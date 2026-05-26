<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle actions: disqualify or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        header("Location: manage_candidates.php?msg=invalid");
        exit();
    }

    if ($action === 'disqualify') {
        try {
            $stmt = $db->prepare("UPDATE users SET status = 'disqualified' WHERE id = ? AND role = 'candidate'");
            $stmt->execute([$user_id]);
            header("Location: manage_candidates.php?msg=disqualified");
            exit();
        } catch (PDOException $e) {
            error_log('Error disqualifying user: ' . $e->getMessage());
            header("Location: manage_candidates.php?msg=error");
            exit();
        }
    }

    if ($action === 'delete') {
        try {
            // Find the user email/username before deletion for extra cleanup
            $stmt = $db->prepare("SELECT email, username FROM users WHERE id = ? AND role = 'candidate'");
            $stmt->execute([$user_id]);
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$candidate) {
                header("Location: manage_candidates.php?msg=not_deleted");
                exit();
            }

            $db->beginTransaction();

            // 1) proctoring_logs
            $stmt = $db->prepare("DELETE FROM proctoring_logs WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // 2) suspected_cheating
            $stmt = $db->prepare("DELETE FROM suspected_cheating WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // 3) webcam_captures - delete files
            $stmt = $db->prepare("SELECT image_path FROM webcam_captures WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($files as $f) {
                $path = __DIR__ . '/../' . $f;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            $stmt = $db->prepare("DELETE FROM webcam_captures WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // 4) screenshot_captures - delete files
            $stmt = $db->prepare("SELECT image_path FROM screenshot_captures WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($files as $f) {
                $path = __DIR__ . '/../' . $f;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            $stmt = $db->prepare("DELETE FROM screenshot_captures WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // 5) exam_responses
            $stmt = $db->prepare("DELETE FROM exam_responses WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // 6) exam_attempts
            $stmt = $db->prepare("DELETE FROM exam_attempts WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // 7) delete the candidate user row
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'candidate'");
            $stmt->execute([$user_id]);
            $deleted = $stmt->rowCount();

            // 8) extra cleanup: remove any remaining candidate records with the same email or username
            if ($deleted > 0) {
                $cleanup = $db->prepare("DELETE FROM users WHERE role = 'candidate' AND (email = ? OR username = ?)");
                $cleanup->execute([$candidate['email'], $candidate['username']]);
            }

            if ($deleted > 0) {
                $db->commit();
                header("Location: manage_candidates.php?msg=deleted");
                exit();
            } else {
                $db->rollBack();
                header("Location: manage_candidates.php?msg=not_deleted");
                exit();
            }

        } catch (PDOException $e) {
            error_log('Error deleting user: ' . $e->getMessage());
            if ($db->inTransaction()) $db->rollBack();
            header("Location: manage_candidates.php?msg=error&err=".base64_encode($e->getMessage()));
            exit();
        }
    }
}

// Fetch candidates
try {
    $stmt = $db->query("SELECT id, username, full_name, email, status, violation_count, created_at FROM users WHERE role = 'candidate' ORDER BY created_at DESC");
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching candidates: ' . $e->getMessage());
    $candidates = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Manage Candidates</span>
            <a href="dashboard.php" class="btn btn-outline-light">Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">Candidate deleted successfully.</div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'disqualified'): ?>
            <div class="alert alert-success">Candidate disqualified successfully.</div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'not_deleted'): ?>
            <div class="alert alert-warning">Delete request processed but candidate was not removed.</div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'invalid'): ?>
            <div class="alert alert-warning">Invalid request.</div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'error'): ?>
            <div class="alert alert-danger">Error processing request. Debug: <?php echo htmlspecialchars(base64_decode($_GET['err'] ?? '')); ?></div>
        <?php endif; ?>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Violations</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $c): ?>
                <tr>
                    <td><?php echo $c['id']; ?></td>
                    <td><?php echo htmlspecialchars($c['username']); ?></td>
                    <td><?php echo htmlspecialchars($c['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['email']); ?></td>
                    <td><span class="badge bg-<?php echo $c['status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo $c['status']; ?></span></td>
                    <td><?php echo intval($c['violation_count']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="disqualify">
                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Disqualify this candidate?')">Disqualify</button>
                        </form>
                        <form method="POST" style="display:inline; margin-left:6px;">
                            <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Permanently delete this candidate and all related data?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
