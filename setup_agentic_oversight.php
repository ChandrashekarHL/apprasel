<?php
require_once 'db_config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS ad_agentic_oversight (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        category VARCHAR(50) NOT NULL, -- e.g. 'dar_missing', 'performance_drag'
        message TEXT,
        status ENUM('active', 'resolved') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        INDEX (faculty_id),
        INDEX (status)
    )";
    $pdo->exec($sql);
    echo "Table 'ad_agentic_oversight' created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>
