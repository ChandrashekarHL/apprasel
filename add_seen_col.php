<?php
require_once 'db_config.php';

try {
    // Check if column exists
    $check = $pdo->query("SHOW COLUMNS FROM `ad_daily_ai_activity` LIKE 'briefing_seen'");
    if ($check->rowCount() == 0) {
        $sql = "ALTER TABLE `ad_daily_ai_activity` ADD COLUMN `briefing_seen` INT(1) DEFAULT 0";
        $pdo->exec($sql);
        echo "Database Updated: Added briefing_seen column.";
    } else {
        echo "Database Verified: briefing_seen column exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
