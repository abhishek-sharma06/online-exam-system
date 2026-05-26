<?php
// ==========================================
// FILE: config/database.php
// PURPOSE: Database connection and session start
// ==========================================

// Start session only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Database {
    // ==========================================
    // LOCALHOST CONFIGURATION (Default)
    // ==========================================
    private $host = "localhost";
    private $db_name = "exam_system";
    private $username = "root";
    private $password = "";

    // ==========================================
    // LIVE HOSTING CONFIGURATION (Uncomment and update for production)
    // ==========================================
    /*
    private $host = "your-live-host.com";  // e.g., sql109.infinityfree.com or your hosting provider's MySQL host
    private $db_name = "your_database_name";  // Database name provided by hosting
    private $username = "your_db_username";  // Database username provided by hosting
    private $password = "your_db_password";  // Database password provided by hosting
    */

    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                  $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            die("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

$database = new Database();
$db = $database->getConnection();
?>