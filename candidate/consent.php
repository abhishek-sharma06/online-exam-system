<?php
// ==========================================
// FILE: candidate/consent.php
// PURPOSE: Proctoring consent page before exam
// ==========================================

require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and is a candidate
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit();
}

// Validate exam_id
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    error_log("Consent.php: Missing or invalid exam_id parameter");
    header("Location: dashboard.php");
    exit();
}

$exam_id = intval($_GET['exam_id']);
error_log("Consent.php: Attempting to load exam_id = $exam_id");

// Get database connection and verify exam exists and is active
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

// Try to fetch the exam - allow any status, not just 'active'
$stmt = $db->prepare("SELECT id, title, duration_minutes, status FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

error_log("Consent.php: Exam fetch result - " . ($exam ? "Found (status: " . $exam['status'] . ")" : "Not found"));

if (!$exam) {
    error_log("Consent.php: Exam not found, redirecting to dashboard");
    header("Location: dashboard.php");
    exit();
}

// Check if exam has questions - if not, show warning but still allow consent
$stmt = $db->prepare("SELECT COUNT(*) as count FROM questions WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$question_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$has_questions = $question_count > 0;
error_log("Consent.php: Exam has $question_count questions");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Camera & Microphone Consent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-warning">
                <h3>⚠️ Important: Proctoring Consent</h3>
            </div>
            <div class="card-body">
                <div id="errorAlert" class="alert alert-danger" style="display:none;"></div>
                <h5>This exam will be proctored. You agree to:</h5>
                <ul>
                    <li>Allow access to your webcam and microphone</li>
                    <li>Your webcam will capture images every 10-15 seconds</li>
                    <li>Tab switching or window minimization will be logged</li>
                    <li>3 violations will lead to automatic disqualification</li>
                    <li>Do not use external devices or secondary screens</li>
                </ul>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="consentCheck">
                    <label class="form-check-label">I agree to the proctoring terms</label>
                </div>
                <button type="button" class="btn btn-primary" id="proceedBtn" disabled onclick="requestPermissions()">Proceed to Exam</button>
                <button type="button" class="btn btn-secondary ms-2" onclick="window.location.href='dashboard.php'">Cancel</button>
                <button type="button" class="btn btn-outline-info btn-sm ms-2" id="testModeBtn" onclick="testModeSkipDeviceCheck()" style="display:none;">Skip Device Check (TEST)</button>
                <div id="debugInfo" class="mt-3 small text-muted" style="display:none;"></div>
            </div>
        </div>
    </div>
    
    <script>
        let cameraStreamActive = false;
        let microphoneActive = false;
        
        document.getElementById('consentCheck').addEventListener('change', function() {
            document.getElementById('proceedBtn').disabled = !this.checked;
        });
        
        async function verifyDevices() {
            try {
                // Check if getUserMedia is supported
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    console.warn('[DEVICE CHECK] getUserMedia not supported');
                    alert('Your browser does not support camera/microphone access. Please use a modern browser like Chrome, Firefox, or Edge.');
                    return false;
                }
                
                // Create abort controller with timeout
                const abortController = new AbortController();
                const timeoutId = setTimeout(() => abortController.abort(), 10000); // 10 second timeout
                
                // Request camera and audio with constraints
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true
                    }
                });
                
                clearTimeout(timeoutId);
                
                // Verify we got both tracks
                const videoTracks = stream.getVideoTracks();
                const audioTracks = stream.getAudioTracks();
                
                console.log(`[DEVICE CHECK] Video tracks: ${videoTracks.length}, Audio tracks: ${audioTracks.length}`);
                
                if (videoTracks.length === 0) {
                    stream.getTracks().forEach(t => t.stop());
                    console.warn('[DEVICE CHECK] No camera detected');
                    alert('❌ CAMERA NOT DETECTED! Camera is required to take this exam.\n\nPlease:\n1. Connect a webcam\n2. Check browser permissions\n3. Restart and try again');
                    return false;
                }
                
                if (audioTracks.length === 0) {
                    stream.getTracks().forEach(t => t.stop());
                    console.warn('[DEVICE CHECK] No microphone detected');
                    alert('❌ MICROPHONE NOT DETECTED! Microphone is required to take this exam.\n\nPlease:\n1. Connect a microphone\n2. Check browser permissions\n3. Restart and try again');
                    return false;
                }
                
                // Test that devices are actually working
                if (videoTracks[0].readyState !== 'live' || audioTracks[0].readyState !== 'live') {
                    stream.getTracks().forEach(t => t.stop());
                    console.warn('[DEVICE CHECK] Device not in live state');
                    alert('⚠️ Device is not in LIVE state. Please check your hardware and permissions.');
                    return false;
                }
                
                cameraStreamActive = true;
                microphoneActive = true;
                console.log('[DEVICE CHECK] ✅ Camera and Microphone verified and active');
                
                // Clean up for now, will be used in exam
                stream.getTracks().forEach(t => t.stop());
                return true;
                
            } catch(err) {
                console.error('[DEVICE CHECK] ERROR:', err.name, err.message);
                
                if (err.name === 'NotAllowedError') {
                    alert('❌ PERMISSION DENIED!\n\nYou denied access to camera/microphone. This exam requires both devices.\n\nTo proceed:\n1. Reload this page\n2. Click "Allow" when prompted\n3. Try again');
                } else if (err.name === 'NotFoundError') {
                    alert('❌ DEVICE NOT FOUND!\n\nNo camera or microphone detected on your system.\n\nPlease:\n1. Connect camera and microphone\n2. Make sure they are not in use by another application\n3. Try again');
                } else if (err.name === 'NotReadableError') {
                    alert('❌ DEVICE NOT ACCESSIBLE!\n\nCamera/Microphone is being used by another application.\n\nPlease:\n1. Close other applications using camera (Zoom, Skype, etc.)\n2. Try again');
                } else if (err.name === 'AbortError') {
                    alert('❌ TIMEOUT!\n\nDevice verification took too long. Please check your hardware and try again.');
                } else {
                    alert('❌ ERROR: ' + err.message + '\n\nPlease check your camera and microphone.');
                }
                return false;
            }
        }
        
        function requestPermissions() {
            // Show loading state
            const btn = document.getElementById('proceedBtn');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '🔍 Verifying Camera & Microphone...';
            
            console.log('[CONSENT] Device verification starting...');
            
            // Verify devices with proper error handling
            verifyDevices().then(devicesOk => {
                if (devicesOk) {
                    console.log('[CONSENT] All devices verified. Proceeding to exam...');
                    btn.textContent = '✅ Proceeding...';
                    // Small delay to ensure UI updates
                    setTimeout(() => {
                        console.log('[CONSENT] Redirecting to take_exam.php');
                        window.location.href = 'take_exam.php?id=<?php echo $exam_id; ?>';
                    }, 500);
                } else {
                    console.warn('[CONSENT] Device verification failed, button re-enabled');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            }).catch(err => {
                console.error('[CONSENT] Unexpected error:', err);
                alert('An unexpected error occurred. Please try again.');
                btn.disabled = false;
                btn.textContent = originalText;
            });
        }
        
        function testModeSkipDeviceCheck() {
            console.log('[CONSENT] TEST MODE: Skipping device check...');
            window.location.href = 'take_exam.php?id=<?php echo $exam_id; ?>&test_mode=1';
        }
        
        // Auto-log page state for debugging
        console.log('[CONSENT] Page loaded, exam_id=<?php echo $exam_id; ?>');
        
        // Enable test mode button if localhost
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            document.getElementById('testModeBtn').style.display = 'inline-block';
        }
        
        window.addEventListener('beforeunload', function() {
            console.log('[CONSENT] Page unloading...');
        });
    </script>
</body>
</html>