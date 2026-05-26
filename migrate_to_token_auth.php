<?php
// ==========================================
// FILE: migrate_to_token_auth.php
// PURPOSE: Database migration from OTP to token-based email verification
// ==========================================

require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "╔════════════════════════════════════════════════════╗\n";
echo "║   Migrating to Token-Based Email Verification     ║\n";
echo "╚════════════════════════════════════════════════════╝\n\n";

$migrations = [
    [
        'name' => 'Add verification token columns',
        'sql' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS verification_token_expires_at TIMESTAMP NULL"
    ],
    [
        'name' => 'Create index on verification_token',
        'sql' => "ALTER TABLE users ADD INDEX IF NOT EXISTS idx_verification_token (verification_token)"
    ]
];

$successful = 0;
$failed = 0;

foreach ($migrations as $migration) {
    echo "▶ {$migration['name']}...\n";
    
    try {
        $db->exec($migration['sql']);
        echo "  ✓ Success\n";
        $successful++;
    } catch (PDOException $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n╔════════════════════════════════════════════════════╗\n";
echo "║                Migration Summary                   ║\n";
echo "╚════════════════════════════════════════════════════╝\n";
echo "✓ Successful: $successful\n";
if ($failed > 0) {
    echo "✗ Failed: $failed\n";
} else {
    echo "✓ All migrations completed successfully!\n";
}

echo "\n✓ Migration complete! Your system is now using token-based email verification.\n";
echo "\nNote: Old OTP and otp_expires_at columns are still in the database but no longer used.\n";
echo "You can optionally delete them later with:\n";
echo "  ALTER TABLE users DROP COLUMN otp, DROP COLUMN otp_expires_at;\n";
?>
