<?php
// ==========================================
// FILE: admin/results.php
// PURPOSE: Display exam results for admin
// ==========================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is an admin
if (!is_admin()) {
    header('Location: ../login.php');
    exit;
}

// Get database connection with enhanced error handling
$database = null;
$db = null;
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log("Database connection error in admin/results.php: " . $e->getMessage());
    // Provide a more user-friendly error message
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Database Error</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>Database Connection Error</h4>
                <p>Unable to connect to the database. Please contact your system administrator.</p>
                <p><small>Error details: " . htmlspecialchars($e->getMessage()) . "</small></p>
            </div>
            <a href='../login.php' class='btn btn-primary'>Return to Login</a>
        </div>
    </body>
    </html>";
    exit;
}

// Fetch all completed exam attempts with user and exam details
$results = [];
try {
    // Check if required tables exist before querying
    $tables_check = $db->query("SHOW TABLES LIKE 'exam_attempts'");
    if ($tables_check->rowCount() == 0) {
        throw new Exception("Required table 'exam_attempts' does not exist");
    }
    
    $tables_check = $db->query("SHOW TABLES LIKE 'exams'");
    if ($tables_check->rowCount() == 0) {
        throw new Exception("Required table 'exams' does not exist");
    }
    
    $tables_check = $db->query("SHOW TABLES LIKE 'users'");
    if ($tables_check->rowCount() == 0) {
        throw new Exception("Required table 'users' does not exist");
    }
    
    $query = "SELECT ea.*, e.title as exam_title, u.full_name, u.username 
              FROM exam_attempts ea 
              JOIN exams e ON ea.exam_id = e.id 
              JOIN users u ON ea.user_id = u.id 
              WHERE ea.status = 'completed'
              ORDER BY ea.completed_at DESC 
              LIMIT 200";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching results: " . $e->getMessage());
    // Provide a more user-friendly error message
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Database Error</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>Database Query Error</h4>
                <p>Unable to fetch exam results. Please contact your system administrator.</p>
                <p><small>Error details: " . htmlspecialchars($e->getMessage()) . "</small></p>
            </div>
            <a href='dashboard.php' class='btn btn-primary'>Return to Dashboard</a>
        </div>
    </body>
    </html>";
    exit;
} catch (Exception $e) {
    error_log("General error in admin/results.php: " . $e->getMessage());
    // Provide a more user-friendly error message
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>System Error</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head>
    <body>
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>System Error</h4>
                <p>An error occurred while processing your request.</p>
                <p><small>Error details: " . htmlspecialchars($e->getMessage()) . "</small></p>
            </div>
            <a href='dashboard.php' class='btn btn-primary'>Return to Dashboard</a>
        </div>
    </body>
    </html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Exam Results - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 1200px; margin: 20px auto; }
        .badge { font-size: 0.85em; }
        .table th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">Exam System - Admin Dashboard</span>
            <div>
                <a href="dashboard.php" class="text-white me-3">Dashboard</a>
                <a href="../logout.php" class="text-white">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="mb-4">Exam Results</h1>

        <?php if (empty($results)): ?>
            <div class="alert alert-info">
                <h4>No Results Found</h4>
                <p>No completed exam attempts found in the system.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Student Name</th>
                            <th>Username</th>
                            <th>Exam Title</th>
                            <th>Total Marks</th>
                            <th>Obtained Marks</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Completed On</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['username']); ?></td>
                            <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                            <td><?php echo $result['total_marks']; ?></td>
                            <td><?php echo $result['obtained_marks']; ?></td>
                            <td><?php echo $result['percentage']; ?>%</td>
                            <td>
                                <span class="badge bg-<?php echo $result['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($result['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d-m-Y H:i', strtotime($result['completed_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted">Showing <?php echo count($results); ?> most recent results</p>
        <?php endif; ?>
        
        <div class="mt-3">
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
