<?php
// ==========================================
// FILE: debug_exams.php
// PURPOSE: Debug script to check exams database
// ==========================================

require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h1>Exam System Debug</h1>";

// Check database tables
echo "<h2>1. Checking Database Tables</h2>";
$required_tables = ['exams', 'questions', 'exam_attempts', 'exam_responses', 'users'];
$missing_tables = [];

foreach($required_tables as $table) {
    try {
        $stmt = $db->query("DESCRIBE $table");
        echo "<p style='color:green;'>✓ <strong>$table</strong> table exists</p>";
    } catch(Exception $e) {
        echo "<p style='color:red;'>✗ <strong>$table</strong> table NOT FOUND</p>";
        $missing_tables[] = $table;
    }
}

if(count($missing_tables) > 0) {
    echo "<div style='background-color:#ffdddd;padding:15px;border:1px solid red;border-radius:5px;margin:10px 0;'>";
    echo "<h3 style='color:red;margin-top:0;'>Missing Tables Found!</h3>";
    echo "<p>The following tables are missing: <strong>" . implode(', ', $missing_tables) . "</strong></p>";
    echo "<p><a href='setup_database.php' style='padding:10px 20px;background-color:green;color:white;border-radius:5px;text-decoration:none;font-weight:bold;'>Click here to automatically create missing tables</a></p>";
    echo "</div>";
}

// Check if exams table has status column
echo "<h2>2. Checking exams Table Structure</h2>";
try {
    $stmt = $db->query("DESCRIBE exams");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_status = false;
    foreach($columns as $col) {
        if($col['Field'] === 'status') {
            $has_status = true;
            break;
        }
    }
    
    if(!$has_status) {
        echo "<p style='color:red;'><strong>WARNING:</strong> 'status' column not found in exams table!</p>";
        echo "<p>Run this SQL command in phpMyAdmin:</p>";
        echo "<code style='background:#f0f0f0;padding:10px;display:block;overflow-x:auto;'>ALTER TABLE exams ADD COLUMN status VARCHAR(20) DEFAULT 'inactive';</code>";
    } else {
        echo "<p style='color:green;'>✓ Status column exists</p>";
    }
} catch(Exception $e) {
    echo "<p style='color:orange;'>Cannot check exams table (table may not exist)</p>";
}

// Check total exams
echo "<h2>3. Total Exams in Database</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM exams");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total exams: <strong>" . $result['total'] . "</strong></p>";
} catch(Exception $e) {
    echo "<p style='color:orange;'>Cannot check (exams table may not exist)</p>";
}

// Check active exams
echo "<h2>4. Active Exams</h2>";
try {
    $stmt = $db->query("SELECT id, title, duration_minutes, status FROM exams WHERE status = 'active' ORDER BY created_at DESC");
    $active = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(count($active) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Title</th><th>Duration</th><th>Status</th></tr>";
        foreach($active as $exam) {
            echo "<tr>";
            echo "<td>" . $exam['id'] . "</td>";
            echo "<td>" . htmlspecialchars($exam['title']) . "</td>";
            echo "<td>" . $exam['duration_minutes'] . " min</td>";
            echo "<td>" . htmlspecialchars($exam['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange;'><strong>No active exams found!</strong></p>";
        echo "<p>Go to <a href='admin/manage_exams.php'>Manage Exams</a> to create one.</p>";
    }
} catch(Exception $e) {
    echo "<p style='color:orange;'>Cannot check (exams table may not exist)</p>";
}

// Check all exams with their status
echo "<h2>5. All Exams (with Status)</h2>";
try {
    $stmt = $db->query("SELECT id, title, status, created_at, COUNT(q.id) as questions FROM exams e LEFT JOIN questions q ON e.id = q.exam_id GROUP BY e.id ORDER BY e.created_at DESC");
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(count($all) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Questions</th><th>Created</th></tr>";
        foreach($all as $exam) {
            echo "<tr>";
            echo "<td>" . $exam['id'] . "</td>";
            echo "<td>" . htmlspecialchars($exam['title']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($exam['status']) . "</strong></td>";
            echo "<td>" . $exam['questions'] . "</td>";
            echo "<td>" . $exam['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No exams in database.</p>";
    }
} catch(Exception $e) {
    echo "<p style='color:orange;'>Cannot check (exams table may not exist)</p>";
}

// Offer to activate exams
echo "<h2>6. Fix Inactive Exams</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM exams WHERE status IS NULL OR status = 'inactive'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($result['total'] > 0) {
        echo "<p>Found " . $result['total'] . " inactive exam(s).</p>";
        echo "<p>Click the button below to activate them:</p>";
        echo "<form method='POST' style='display:inline;'>";
        echo "<input type='hidden' name='action' value='activate_all'>";
        echo "<button type='submit' class='btn btn-success' onclick=\"return confirm('Activate all inactive exams?')\">Activate All Exams</button>";
        echo "</form>";
    } else {
        echo "<p style='color:green;'>All exams have a status set.</p>";
    }
} catch(Exception $e) {
    echo "<p style='color:orange;'>Cannot check (exams table may not exist)</p>";
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'activate_all') {
    try {
        $stmt = $db->prepare("UPDATE exams SET status = 'active' WHERE status IS NULL OR status = 'inactive'");
        $stmt->execute();
        echo "<div style='background:lightgreen;padding:10px;margin:10px 0;border-radius:5px;'>";
        echo "<strong>✓ All exams activated successfully!</strong>";
        echo "</div>";
    } catch(PDOException $e) {
        echo "<div style='background:lightcoral;padding:10px;margin:10px 0;border-radius:5px;'>";
        echo "<strong>✗ Error: " . $e->getMessage() . "</strong>";
        echo "</div>";
    }
}

?>

<hr>
<p><a href="candidate/dashboard.php">← Back to Candidate Dashboard</a> | <a href="setup_database.php">Setup Database Tables →</a></p>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h1, h2, h3 {
    color: #333;
}
code {
    background-color: #f0f0f0;
    padding: 2px 5px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}
p {
    line-height: 1.6;
}
table {
    margin: 10px 0;
    background: white;
    border-collapse: collapse;
}
</style>
