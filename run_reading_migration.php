<?php
/**
 * One-time migration: creates ad_reading_list table.
 * Access: http://localhost/apprasel/run_reading_migration.php
 * Delete this file after running!
 */
require_once 'db_config.php';

$sql = "CREATE TABLE IF NOT EXISTS ad_reading_list (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id    INT NOT NULL,
    course_title  VARCHAR(200) DEFAULT '',
    book_title    VARCHAR(300) NOT NULL,
    author        VARCHAR(200) DEFAULT '',
    status        ENUM('planned','verified') NOT NULL DEFAULT 'planned',
    takeaways     TEXT,
    ai_score      TINYINT UNSIGNED DEFAULT NULL,
    ai_feedback   TEXT,
    added_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at   DATETIME DEFAULT NULL,
    UNIQUE KEY uq_faculty_book (faculty_id, book_title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

echo "<h2>Reading List Migration</h2><pre>";
try {
    $pdo->exec($sql);
    echo "✅ Table ad_reading_list created (or already exists).\n";

    // Show columns
    $cols = $pdo->query("SHOW COLUMNS FROM ad_reading_list")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nColumns:\n";
    foreach ($cols as $c) echo "  {$c['Field']}  {$c['Type']}\n";
    echo "\n✅ Done! Delete this file now.\n";
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
