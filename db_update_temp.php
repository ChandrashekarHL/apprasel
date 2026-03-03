<?php
// db_update_temp.php - Run once to update schema
require_once 'db_config.php';

try {
    // Check if column exists in ad_activity_logs
    $check = $pdo->query("SHOW COLUMNS FROM `ad_activity_logs` LIKE 'proof_file_path'");
    if ($check->rowCount() == 0) {
        $sql = "ALTER TABLE `ad_activity_logs` 
                ADD COLUMN `proof_file_path` VARCHAR(255) DEFAULT NULL";
        $pdo->exec($sql);
        echo "Database Updated: Added proof_file_path column to ad_activity_logs.";
    } else {
        echo "Database Update: Column proof_file_path already exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
