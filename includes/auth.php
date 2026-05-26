<?php
// ==========================================
// FILE: includes/auth.php
// PURPOSE: Authentication functions
// ==========================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Login user with username/email and role
    public function login($username, $password, $role) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = ?");
        $stmt->execute([$username, $username, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password']) && $user['status'] == 'active') {
            // Check email verification for candidates
            if ($role === 'candidate' && !$user['email_verified']) {
                // Store data for error message
                $_SESSION['unverified_email'] = [
                    'email' => $user['email'],
                    'full_name' => $user['full_name']
                ];
                $_SESSION['login_error'] = 'email_not_verified';
                return false;
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            return true;
        }
        return false;
    }
    
    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public function isCandidate() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'candidate';
    }
    
    public function logout() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Unset all session variables
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }

        // Destroy the session
        session_unset();
        session_destroy();

        return true;
    }
    
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) return null;
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Create auth object with database connection
$auth = new Auth($db);
?>