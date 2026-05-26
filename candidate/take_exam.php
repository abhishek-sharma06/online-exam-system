<?php
// ==========================================
// FILE: candidate/take_exam.php
// PURPOSE: Display and manage exam questions
// ==========================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a candidate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Create exam_attempts table if it doesn't exist
try {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS exam_attempts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        exam_id INT NOT NULL,
        user_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        total_marks INT DEFAULT 0,
        obtained_marks INT DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0,
        status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES exams(id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX idx_exam_user (exam_id, user_id)
    )";
    $db->exec($create_table_sql);
} catch(PDOException $e) {
    error_log("Note: exam_attempts table creation: " . $e->getMessage());
}

// Validate and fetch exam
$exam_id = intval($_GET['id']);
$stmt = $db->prepare("SELECT * FROM exams WHERE id = ? AND status = 'active'");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header("Location: dashboard.php?error=invalid_exam");
    exit();
}

// Check if user is disqualified GLOBALLY
$stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['status'] === 'disqualified') {
    header("Location: dashboard.php?msg=disqualified");
    exit();
}

// Check if user is disqualified for THIS SPECIFIC EXAM (only TAB_SWITCH counts toward automatic disqualification)
$stmt = $db->prepare("SELECT COUNT(*) as violation_count FROM proctoring_logs WHERE exam_id = ? AND user_id = ? AND violation_type = 'TAB_SWITCH' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute([$exam_id, $_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$exam_violations = $result['violation_count'];

// Disqualify only if tab-switch count is more than thrice (>3)
if ($exam_violations > 3) {
    // Update global status if disqualified
    $stmt = $db->prepare("UPDATE users SET status = 'disqualified' WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    header("Location: dashboard.php?msg=disqualified_from_exam");
    exit();
}

// Fetch questions for this exam
$stmt = $db->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id ASC");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    header("Location: dashboard.php?error=no_questions");
    exit();
}

// Start exam session if not started
if (!isset($_SESSION['exam_started']) || $_SESSION['exam_id'] != $exam_id) {
    $_SESSION['exam_started'] = time();
    $_SESSION['exam_id'] = $exam_id;
    
    // Create exam attempt record
    try {
        // Don't check for existing - allow retakes by creating new attempt
        $stmt = $db->prepare("INSERT INTO exam_attempts (exam_id, user_id, started_at, status) VALUES (?, ?, NOW(), 'in_progress')");
        $stmt->execute([$exam_id, $_SESSION['user_id']]);
    } catch(PDOException $e) {
        // If unique constraint fails, just continue (user is retaking)
        error_log("Note: Exam attempt already exists (retake): " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($exam['title']); ?> - Exam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .timer { position: fixed; top: 20px; right: 20px; background: red; color: white; padding: 10px 20px; border-radius: 5px; font-size: 24px; font-weight: bold; z-index: 1000; }
        .webcam-preview { position: sticky; top: 20px; width: 100%; border: 3px solid #3a0ca3; border-radius: 10px; background: black; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .webcam-preview video { width: 100%; height: auto; object-fit: cover; transform: scaleX(-1); display: block; }
        .question-card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        body { background: #f0f2f5; font-family: Arial; }
        @keyframes blink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0.3; } }
        @keyframes slideDown { from { transform: translateX(-50%) translateY(-100px); opacity: 0; } to { transform: translateX(-50%) translateY(0); opacity: 1; } }
        .blink { animation: blink 0.7s infinite; }
        #candidate-warnings .alert { margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        /* Suspicion score card */
        #suspicion-score-card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.12); overflow: hidden; }
        #suspicion-score-bar { transition: width 0.6s cubic-bezier(0.4,0,0.2,1), background 0.6s ease; }
        /* Live audio indicator */
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
        #audio-indicator.active { animation: pulse 1s infinite; }
    </style>
</head>
<body oncontextmenu="return false">
<div class="timer" id="timer"></div>
<div id="violation-indicator"></div>

<div class="container-fluid mt-4" style="max-width: 1400px;">
    <div class="row">
        <!-- Main Exam Column -->
        <div class="col-md-8">
            <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
            <form method="POST" action="submit_exam.php" id="examForm">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                <input type="hidden" id="current_index" name="current_index" value="0">
                <div id="question_container"></div>
                <div class="d-flex gap-2">
                    <button type="button" id="prevBtn" class="btn btn-secondary">Previous</button>
                    <button type="button" id="nextBtn" class="btn btn-primary">Next</button>
                    <button type="submit" id="submitBtn" class="btn btn-success ms-auto">Submit Exam</button>
                </div>
            </form>
        </div>
        
        <!-- Webcam & Warnings Column -->
        <div class="col-md-4">
            <!-- Webcam feed -->
            <div class="webcam-preview mb-2">
                <video id="webcam" autoplay playsinline muted></video>
            </div>

            <!-- Live Audio Indicator -->
            <div class="d-flex align-items-center gap-2 mb-3 px-1">
                <span id="audio-indicator" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#adb5bd;"></span>
                <small id="audio-label" style="color:#6c757d;font-size:12px;">Microphone monitoring active</small>
            </div>

            <!-- AI Suspicion Risk Score -->
            <div class="card mb-3" id="suspicion-score-card">
                <div class="card-header py-2" style="background:linear-gradient(135deg,#3a0ca3,#4361ee);color:white;font-size:13px;font-weight:bold;">
                    🧠 AI Suspicion Score
                </div>
                <div class="card-body py-3 px-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small style="font-weight:600;color:#333;">Risk Level</small>
                        <span id="suspicion-score-badge" style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:bold;background:#28a745;color:white;">✅ Low Risk</span>
                    </div>
                    <div style="background:#e9ecef;border-radius:20px;height:14px;overflow:hidden;">
                        <div id="suspicion-score-bar" style="height:100%;width:0%;background:#28a745;border-radius:20px;"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small style="color:#aaa;font-size:10px;">0%</small>
                        <small id="suspicion-score-label" style="font-weight:bold;color:#333;font-size:12px;">0%</small>
                        <small style="color:#aaa;font-size:10px;">100%</small>
                    </div>
                </div>
            </div>

            <!-- Proctoring Status Warnings -->
            <h6 class="mb-2" style="font-weight:600;">🛡️ Proctoring Status</h6>
            <div id="candidate-warnings">
                <!-- Warnings appear here -->
            </div>
        </div>
    </div>
</div>

    <!-- TensorFlow.js core and backend -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
    <!-- COCO-SSD object detection model -->
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd"></script>
    <!-- face-api.js for face/gaze tracking -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="../assets/js/proctoring.js"></script>
    <script src="../assets/js/ai_proctoring.js"></script>
<script>
    // Initialize proctoring system
    document.addEventListener('DOMContentLoaded', async () => {
        const success = await initProctoring(<?php echo $exam_id; ?>, <?php echo $_SESSION['user_id']; ?>);
        if (!success) {
            console.warn('Proctoring initialization failed');
        }
        // debug: inspect QUESTIONS and first item
        console.log('QUESTIONS loaded', QUESTIONS);
        console.log('First question preview', QUESTIONS && QUESTIONS[0]);
        // render first question (and a short delayed fallback to avoid race conditions)
        renderQuestion(0);
        setTimeout(() => {
            try { renderQuestion(0); } catch (e) { console.warn('Fallback renderQuestion failed', e); }
        }, 50);
    });
    
    // Timer
    const duration = <?php echo $exam['duration_minutes']; ?> * 60;
    const startTime = <?php echo $_SESSION['exam_started']; ?>;
    function updateTimer() {
        const now = Math.floor(Date.now() / 1000);
        let remaining = duration - (now - startTime);
        if (remaining <= 0) {
            document.getElementById('examForm').submit();
            return;
        }
        const mins = Math.floor(remaining/60);
        const secs = remaining%60;
        document.getElementById('timer').innerText = `${mins.toString().padStart(2,'0')}:${secs.toString().padStart(2,'0')}`;
        setTimeout(updateTimer, 1000);
    }
    updateTimer();

    // Disable right-click, copy, paste
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('copy', e => e.preventDefault());
    document.addEventListener('paste', e => e.preventDefault());

    // Questions data passed to JS
    const QUESTIONS = <?php echo json_encode(array_values($questions)); ?>;
    let answers = {}; // store selected answers

    function renderQuestion(idx) {
        const container = document.getElementById('question_container');
        if (idx < 0 || idx >= QUESTIONS.length) return;
        const q = QUESTIONS[idx];
        if (!q) {
            container.innerHTML = '<div class="alert alert-danger">Question data missing.</div>';
            return;
        }
        document.getElementById('current_index').value = idx;
        container.innerHTML = `
            <div class="question-card" id="question_${q.id}">
                <h5>${idx+1}. ${escapeHtml(q.question_text)}</h5>
                <div class="options mt-3">
                    ${renderOption(q, 'A')}
                    ${renderOption(q, 'B')}
                    ${renderOption(q, 'C')}
                    ${renderOption(q, 'D')}
                </div>
            </div>
        `;
        // debug: log the rendered HTML for troubleshooting
        console.log('Rendered question idx', idx, 'id', q.id, 'container HTML length', container.innerHTML.length);
        // restore selection
        const sel = answers[q.id];
        if (sel) {
            const input = document.querySelector(`#question_${q.id} input[value="${sel}"]`);
            if (input) input.checked = true;
        }
        // disable prev on first
        document.getElementById('prevBtn').disabled = (idx === 0);
        // change next/submit visibility
        document.getElementById('nextBtn').style.display = (idx === QUESTIONS.length -1) ? 'none' : 'inline-block';
        document.getElementById('submitBtn').style.display = (idx === QUESTIONS.length -1) ? 'inline-block' : 'none';
    }

    function renderOption(q, opt) {
        const label = q['option_' + opt.toLowerCase()];
        return `<div class="form-check"><input class="form-check-input" type="radio" name="question_${q.id}" value="${opt}" id="q${q.id}_${opt.toLowerCase()}"><label class="form-check-label" for="q${q.id}_${opt.toLowerCase()}">${opt}) ${escapeHtml(label)}</label></div>`;
    }

    function escapeHtml(s) { return (s+'').replace(/[&<>\"]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

    document.getElementById('nextBtn').addEventListener('click', () => moveQuestion(1));
    document.getElementById('prevBtn').addEventListener('click', () => moveQuestion(-1));

    function moveQuestion(dir) {
        // save current selection
        const idx = parseInt(document.getElementById('current_index').value);
        const q = QUESTIONS[idx];
        const selected = document.querySelector(`#question_${q.id} input[type=radio]:checked`);
        answers[q.id] = selected ? selected.value : '';
        const next = idx + dir;
        if (next < 0 || next >= QUESTIONS.length) return;
        renderQuestion(next);
        // scroll to top
        window.scrollTo(0,0);
    }

    // show warnings inside candidate page
    function showCandidateWarning(text) {
        const box = document.createElement('div');
        box.className = 'alert alert-warning';
        box.innerText = text;
        const container = document.getElementById('candidate-warnings');
        container.appendChild(box);
        setTimeout(() => box.remove(), 5000);
    }

    // expose from proctoring.js: showViolationWarning -> also call showCandidateWarning
    const originalShowViolationWarning = window.showViolationWarning || function(violationType, count) { alert(violationType); };
    window.showViolationWarning = function(violationType, count) {
        originalShowViolationWarning(violationType, count);
        showCandidateWarning(`${violationType.replace(/_/g,' ')} — Violation ${count}`);
    }

    // Ensure proctoring is stopped when the form is submitted (either by user or timer)
    const examFormEl = document.getElementById('examForm');
    if (examFormEl) {
        examFormEl.addEventListener('submit', (e) => {
            try {
                if (window.stopProctoring) window.stopProctoring();
            } catch (err) {
                console.warn('Error while stopping proctoring on submit:', err);
            }
            // allow form to continue submitting
        });
    }
</script>
</body>
</html>