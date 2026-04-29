<?php
require_once '../includes/auth.php';
if (!$auth->isAdmin()) redirect('../login.php');
$exam_id = $_GET['exam_id'] ?? 0;

// Export CSV
if (isset($_GET['export'])) {
    $stmt = $db->prepare("SELECT * FROM questions WHERE exam_id = ?");
    $stmt->execute([$exam_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="questions.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','question_text','option_a','option_b','option_c','option_d','correct_answer','marks']);
    foreach ($questions as $q) fputcsv($out, $q);
    fclose($out);
    exit;
}

// Import CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (($handle = fopen($file, 'r')) !== false) {
        fgetcsv($handle); // skip header
        while (($data = fgetcsv($handle)) !== false) {
            $stmt = $db->prepare("INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$exam_id, $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7]]);
        }
        fclose($handle);
        $success = "Questions imported!";
    }
}
?>
<!DOCTYPE html>
<html><body><div class="container mt-4">
    <h2>Question Bank</h2>
    <a href="?exam_id=<?php echo $exam_id; ?>&export=1" class="btn btn-info">Export CSV</a>
    <form method="post" enctype="multipart/form-data" class="mt-3">
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit" class="btn btn-primary">Import CSV</button>
    </form>
    <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
</div></body></html>