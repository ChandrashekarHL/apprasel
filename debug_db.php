<?php
require_once 'db_config.php';

try {
    echo "Checking DB Connection...\n";
    $stmt = $pdo->query("SELECT 1");
    echo "Connection OK.\n";
} catch (PDOException $e) {
    echo "Connection Failed: " . $e->getMessage() . "\n";
}
?>
