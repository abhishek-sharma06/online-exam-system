<?php
require_once 'includes/proctoring.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Initialize proctoring to create tables
$proctoring = new Proctoring($db);

echo "✅ Proctoring system initialized - all tables created.\n";
?>
