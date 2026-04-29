<?php
/**
 * Proctoring Functions File
 * Handles all monitoring, image capture, and violation logging
 */

require_once __DIR__ . '/../config/database.php';

class Proctoring {
    private $conn;
    private $max_violations = 3;

    public function __construct($db) {
        $this->conn = $db;
        $this->createTablesIfNeeded();
    }

    private function createTablesIfNeeded() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS proctoring_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exam_id INT NOT NULL,
                user_id INT NOT NULL,
                violation_type VARCHAR(50) NOT NULL,
                violation_details TEXT,
                screenshot_path VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (exam_id) REFERENCES exams(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_exam_user (exam_id, user_id)
            )";
            $this->conn->exec($sql);

            $sql2 = "CREATE TABLE IF NOT EXISTS webcam_captures (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exam_id INT NOT NULL,
                user_id INT NOT NULL,
                image_path VARCHAR(255),
                captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (exam_id) REFERENCES exams(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_exam_user (exam_id, user_id)
            )";
            $this->conn->exec($sql2);
            
            $sql2b = "CREATE TABLE IF NOT EXISTS screenshot_captures (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exam_id INT NOT NULL,
                user_id INT NOT NULL,
                image_path VARCHAR(255),
                captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (exam_id) REFERENCES exams(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_exam_user (exam_id, user_id)
            )";
            $this->conn->exec($sql2b);
            
            $sql3 = "CREATE TABLE IF NOT EXISTS suspected_cheating (
                id INT PRIMARY KEY AUTO_INCREMENT,
                exam_id INT NOT NULL,
                user_id INT NOT NULL,
                detection_type VARCHAR(100) NOT NULL,
                suspicion_level ENUM('LOW', 'MEDIUM', 'HIGH') DEFAULT 'MEDIUM',
                score INT DEFAULT 0,
                indicators JSON,
                camera_image_path VARCHAR(500),
                screenshot_path VARCHAR(500),
                analysis_details JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (exam_id) REFERENCES exams(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_exam_user (exam_id, user_id),
                INDEX idx_suspicion_level (suspicion_level)
            )";
            $this->conn->exec($sql3);
        } catch (PDOException $e) {
            error_log('Error creating proctoring tables: ' . $e->getMessage());
        }
    }

    public function logViolation($exam_id, $user_id, $violation_type, $description = '', $image_path = null) {
        try {
            $query = "INSERT INTO proctoring_logs (exam_id, user_id, violation_type, violation_details, screenshot_path) VALUES (?, ?, ?, ?, ?)
                      ";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$exam_id, $user_id, $violation_type, $description, $image_path]);

            // Count violations for THIS EXAM only
            // Only TAB_SWITCH events contribute towards automatic disqualification
            $count_query = "SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ? AND violation_type = 'TAB_SWITCH'";
            $count_stmt = $this->conn->prepare($count_query);
            $count_stmt->execute([$exam_id, $user_id]);
            $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
            $violation_count = intval($result['count']);

            // Only disqualify if tab-switch count exceeds configured threshold (more than thrice)
            if ($violation_count > $this->max_violations) {
                $this->disqualifyUser($user_id);
                return ['success' => true, 'disqualified' => true, 'count' => $violation_count, 'exam_id' => $exam_id];
            }

            return ['success' => true, 'disqualified' => false, 'count' => $violation_count, 'exam_id' => $exam_id];
        } catch (PDOException $e) {
            error_log('Error logging violation: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function saveCapture($exam_id, $user_id, $image_data, $type = 'camera') {
        try {
            $image_data = preg_replace('#^data:image/[^;]+;base64,#', '', $image_data);
            $image_data = str_replace(' ', '+', $image_data);
            $image_binary = base64_decode($image_data);

            if (!$image_binary) {
                return ['success' => false, 'error' => 'Invalid image data'];
            }

            $timestamp = time();
            $rand = rand(10000, 99999);
            
            if ($type === 'screenshot') {
                $filename = "screenshot_{$exam_id}_{$user_id}_{$timestamp}_{$rand}.jpg";
                $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'screenshots' . DIRECTORY_SEPARATOR;
                $db_table = 'screenshot_captures';
                $db_path_prefix = 'assets/uploads/screenshots/';
            } else {
                $filename = "capture_{$exam_id}_{$user_id}_{$timestamp}_{$rand}.jpg";
                $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'webcam_captures' . DIRECTORY_SEPARATOR;
                $db_table = 'webcam_captures';
                $db_path_prefix = 'assets/uploads/webcam_captures/';
            }
            
            // Ensure parent directory exists first
            $parent_dir = dirname($upload_dir);
            if (!is_dir($parent_dir)) {
                @mkdir($parent_dir, 0777, true);
            }
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $filepath = $upload_dir . $filename;
            $db_path = $db_path_prefix . $filename;

            if (file_put_contents($filepath, $image_binary) !== false) {
                $query = "INSERT INTO {$db_table} (exam_id, user_id, image_path, captured_at) VALUES (?, ?, ?, NOW())";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$exam_id, $user_id, $db_path]);
                
                error_log("✓ {$type} saved: {$db_path}");
                return ['success' => true, 'filename' => $filename, 'path' => $db_path, 'type' => $type];
            }

            return ['success' => false, 'error' => 'Failed to save image'];
        } catch (PDOException $e) {
            error_log('Error saving capture: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function saveViolationImage($exam_id, $user_id, $image_data) {
        try {
            $image_data = preg_replace('#^data:image/[^;]+;base64,#', '', $image_data);
            $image_data = str_replace(' ', '+', $image_data);
            $image_binary = base64_decode($image_data);

            if (!$image_binary) {
                return ['success' => false, 'error' => 'Invalid image data'];
            }

            $timestamp = time();
            $rand = rand(1000, 9999);
            $filename = "violation_{$exam_id}_{$user_id}_{$timestamp}_{$rand}.jpg";
            $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'violations' . DIRECTORY_SEPARATOR;
            
            // Ensure parent directory exists first
            $parent_dir = dirname($upload_dir);
            if (!is_dir($parent_dir)) {
                @mkdir($parent_dir, 0777, true);
            }
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $filepath = $upload_dir . $filename;
            $db_path = 'assets/uploads/violations/' . $filename;

            if (file_put_contents($filepath, $image_binary) !== false) {
                return ['success' => true, 'filename' => $filename, 'path' => $db_path];
            }

            return ['success' => false, 'error' => 'Failed to save violation image'];
        } catch (Exception $e) {
            error_log('Error saving violation image: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function disqualifyUser($user_id) {
        try {
            $query = "UPDATE users SET status = 'disqualified' WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            return true;
        } catch (PDOException $e) {
            error_log('Error disqualifying user: ' . $e->getMessage());
            return false;
        }
    }

    public function isDisqualified($user_id) {
        try {
            $query = "SELECT status FROM users WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['status'] === 'disqualified';
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getViolationCount($exam_id, $user_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM proctoring_logs WHERE exam_id = ? AND user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$exam_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($result['count']);
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    public function logSuspectedCheating($exam_id, $user_id, $detection_type, $camera_image_path = null, $screenshot_path = null, $indicators = [], $suspicion_level = 'MEDIUM', $score = 0) {
        try {
            $query = "INSERT INTO suspected_cheating (exam_id, user_id, detection_type, suspicion_level, score, indicators, camera_image_path, screenshot_path, analysis_details) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            
            $indicators_json = json_encode($indicators);
            $analysis_json = json_encode([
                'detection_time' => date('Y-m-d H:i:s'),
                'indicators' => $indicators,
                'score' => $score,
                'level' => $suspicion_level
            ]);
            
            $stmt->execute([
                $exam_id,
                $user_id,
                $detection_type,
                $suspicion_level,
                $score,
                $indicators_json,
                $camera_image_path,
                $screenshot_path,
                $analysis_json
            ]);
            
            return ['success' => true, 'id' => $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            error_log('Error logging suspected cheating: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getSuspectedCheatingCount($exam_id, $user_id, $level = null) {
        try {
            if ($level) {
                $query = "SELECT COUNT(*) as count FROM suspected_cheating WHERE exam_id = ? AND user_id = ? AND suspicion_level = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$exam_id, $user_id, $level]);
            } else {
                $query = "SELECT COUNT(*) as count FROM suspected_cheating WHERE exam_id = ? AND user_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$exam_id, $user_id]);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($result['count']);
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function simulateCapture($exam_id, $user_id) {
        try {
            // Create a simple valid JPEG image data (1x1 pixel red)
            $jpeg_data = base64_decode('
/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a
HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNQD/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIy
MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjD/wAARCAABAAEDASIA
AhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8VAFQEB
AQAA/9oADAMBAAIRAxEAPwCwAA8A/9k=
');
            
            if (!$jpeg_data) {
                // Fallback: create a minimal JPEG using simple binary data
                // This is a minimal valid JPEG (1x1 red pixel)
                $jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xDB\x00C\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\t\t\x08\n\x0C\x14\r\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C $.\' \",#\x1C\x1C(7),01444\x1F\'9=82<.342\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00\xFF\xD9";
            }
            
            // Save the image
            $timestamp = time();
            $rand = rand(10000, 99999);
            $filename = "capture_{$exam_id}_{$user_id}_{$timestamp}_{$rand}.jpg";
            $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'webcam_captures' . DIRECTORY_SEPARATOR;
            
            // Ensure directory exists
            $parent_dir = dirname($upload_dir);
            if (!is_dir($parent_dir)) {
                @mkdir($parent_dir, 0777, true);
            }
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            
            $filepath = $upload_dir . $filename;
            $db_path = 'assets/uploads/webcam_captures/' . $filename;
            
            if (file_put_contents($filepath, $jpeg_data) !== false) {
                $query = "INSERT INTO webcam_captures (exam_id, user_id, image_path, captured_at) VALUES (?, ?, ?, NOW())";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$exam_id, $user_id, $db_path]);
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log('Error simulating capture: ' . $e->getMessage());
            return false;
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Session already started by config/database.php, but check if needed
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    error_log("===== PROCTORING AJAX REQUEST =====");
    error_log("POST data keys: " . implode(',', array_keys($_POST)));
    error_log("SESSION user_id: " . ($_SESSION['user_id'] ?? 'NONE'));
    
    if (!isset($_SESSION['user_id'])) {
        error_log("ERROR: Not authenticated!");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    try {
        $database = new Database();
        $db = $database->getConnection();
        $proctoring = new Proctoring($db);

        $action = $_POST['action'] ?? '';
        $exam_id = intval($_POST['exam_id'] ?? 0);
        $user_id = $_SESSION['user_id'];
        
        error_log("Action: {$action}, Exam: {$exam_id}, User: {$user_id}");

        if ($action === 'log_violation') {
            error_log("Processing violation...");
            $violation_type = sanitizeInput($_POST['violation_type'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $image_data = $_POST['image'] ?? '';
            
            error_log("Violation Type: {$violation_type}, Desc: {$description}, ImageLen: " . strlen($image_data));
            
            // If image provided, save it
            $image_path = null;
            if (!empty($image_data)) {
                error_log("Attempting to save violation image...");
                $image_result = $proctoring->saveViolationImage($exam_id, $user_id, $image_data);
                if ($image_result['success']) {
                    $image_path = $image_result['path'];
                    error_log("✓ Violation image saved: " . $image_path);
                } else {
                    error_log("✗ Failed to save image: " . $image_result['error']);
                }
            }
            
            // Log the violation
            error_log("Calling logViolation method...");
            $result = $proctoring->logViolation($exam_id, $user_id, $violation_type, $description, $image_path);
            error_log("logViolation result: " . json_encode($result));
            
            echo json_encode($result);
            exit;
        }

        if ($action === 'save_capture') {
            error_log("Processing image capture...");
            $image_data = $_POST['image'] ?? '';
            $capture_type = sanitizeInput($_POST['type'] ?? 'camera');
            error_log("Capture image length: " . strlen($image_data) . ", Type: " . $capture_type);
            
            $result = $proctoring->saveCapture($exam_id, $user_id, $image_data, $capture_type);
            error_log("saveCapture result: " . json_encode($result));
            
            echo json_encode($result);
            exit;
        }

        if ($action === 'check_status') {
            error_log("Checking disqualification status for user: {$user_id}");
            $disqualified = $proctoring->isDisqualified($user_id);
            $response = ['disqualified' => $disqualified, 'success' => true];
            error_log("Disqualification status: " . ($disqualified ? 'YES' : 'NO'));
            
            echo json_encode($response);
            exit;
        }
        
        if ($action === 'analyze_cheating') {
            error_log("Processing cheating analysis...");
            require_once __DIR__ . '/cheating_detector.php';
            
            $camera_image_path = $_POST['camera_path'] ?? '';
            $screenshot_path = $_POST['screenshot_path'] ?? '';
            $detection_type = sanitizeInput($_POST['detection_type'] ?? 'PERIODIC_ANALYSIS');
            
            error_log("Analyzing - Camera: {$camera_image_path}, Screenshot: {$screenshot_path}");
            
            // Perform analysis
            $report = CheatingDetector::generateCheatingReport($camera_image_path, $screenshot_path);
            
            error_log("Cheating analysis report: " . json_encode($report));
            
            // If suspicion detected, log it
            if ($report['suspicion_level'] !== 'LOW' || $report['score'] > 0) {
                $result = $proctoring->logSuspectedCheating(
                    $exam_id,
                    $user_id,
                    $detection_type,
                    $camera_image_path,
                    $screenshot_path,
                    $report['indicators'],
                    $report['suspicion_level'],
                    $report['score']
                );
                error_log("Suspected cheating logged: " . json_encode($result));
            }
            
            echo json_encode($report);
            exit;
        }

        error_log("ERROR: Unknown action: {$action}");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;

    } catch (Exception $e) {
        error_log("EXCEPTION in proctoring AJAX: " . $e->getMessage());
        error_log("Stack: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}

?>