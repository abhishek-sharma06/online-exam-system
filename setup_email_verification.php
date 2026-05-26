<?php
// ==========================================
// FILE: setup_email_verification.php
// PURPOSE: Database migration for email verification system
// ==========================================

require_once 'config/database.php';

echo "╔════════════════════════════════════════════════════╗\n";
echo "║   Email Verification System Database Setup        ║\n";
echo "╚════════════════════════════════════════════════════╝\n\n";

$database = new Database();
$db = $database->getConnection();

// Check if columns already exist
try {
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $needs_migration = false;
    $migrations = [];
    
    if (!in_array('is_verified', $columns)) {
        $migrations[] = "ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER email_verified";
        $needs_migration = true;
    }
    
    if (!in_array('verification_token', $columns)) {
        $migrations[] = "ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL AFTER is_verified";
        $needs_migration = true;
    }
    
    if (!in_array('verification_token_expires_at', $columns)) {
        $migrations[] = "ALTER TABLE users ADD COLUMN verification_token_expires_at TIMESTAMP NULL AFTER verification_token";
        $needs_migration = true;
    }
    
    if (!$needs_migration) {
        echo "✓ All required columns already exist!\n\n";
    } else {
        echo "Running migrations...\n\n";
        
        foreach ($migrations as $index => $sql) {
            $migration_num = $index + 1;
            echo "▶ Migration $migration_num: Adding column...\n";
            
            try {
                $db->exec($sql);
                echo "  ✓ Success\n\n";
            } catch (PDOException $e) {
                echo "  ✗ Error: " . $e->getMessage() . "\n\n";
            }
        }
    }
    
    echo "╔════════════════════════════════════════════════════╗\n";
    echo "║              Migration Complete!                  ║\n";
    echo "╚════════════════════════════════════════════════════╝\n\n";
    
    echo "✓ Database is ready for email verification system.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
