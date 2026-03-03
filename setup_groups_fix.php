<?php
require_once 'db_config.php';

echo "<h2>Database Update Log</h2>";

function executeSQL($pdo, $sql, $msg) {
    try {
        $pdo->exec($sql);
        echo "<div style='color:green'>✔ $msg</div>";
    } catch (PDOException $e) {
        // Ignore specific errors like Duplicate Column/Constraint
        if (strpos($e->getMessage(), "Duplicate column") !== false || 
            strpos($e->getMessage(), "Duplicate key") !== false ||
            strpos($e->getMessage(), "already exists") !== false) {
            echo "<div style='color:orange'>⚠ $msg (Already Exists/Skipped)</div>";
        } else {
            echo "<div style='color:red'>✘ $msg: " . $e->getMessage() . "</div>";
        }
    }
}

try {
    // 1. Disable Constraints Globally for Session
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<div>Constraints Disabled.</div>";

    // 2. Run the Table Creation SQL
    $sqlFile = 'add_srs_users.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        echo "<div style='color:green'>✔ Workload Groups Table Created/Reset.</div>";
    }

    // 3. Smart Alters for `ad_faculty_users`
    
    // Add 'group_id'
    executeSQL($pdo, 
        "ALTER TABLE `ad_faculty_users` ADD COLUMN `group_id` INT NULL AFTER `designation`", 
        "Added group_id column"
    );

    // Add 'role'
    executeSQL($pdo, 
        "ALTER TABLE `ad_faculty_users` ADD COLUMN `role` ENUM('Faculty', 'Reviewer', 'Admin') DEFAULT 'Faculty' AFTER `email`", 
        "Added role column"
    );

    // Module 1 Compliance: Tenure & Teaching Role
    executeSQL($pdo, 
        "ALTER TABLE `ad_faculty_users` ADD COLUMN `teaching_role` ENUM('UG', 'PG', 'PhD', 'Mixed') DEFAULT 'UG' AFTER `designation`", 
        "Added Teaching Role"
    );
     executeSQL($pdo, 
        "ALTER TABLE `ad_faculty_users` ADD COLUMN `tenure_start` DATE NULL, ADD COLUMN `tenure_end` DATE NULL", 
        "Added Tenure Dates"
    );

    // Update existing constraint? Drop first to be safe if checking re-run
    try {
        $pdo->exec("ALTER TABLE `ad_faculty_users` DROP FOREIGN KEY `fk_user_group`");
    } catch (Exception $e) {} // Ignore if not exists

    // Add Constraint
    executeSQL($pdo, 
        "ALTER TABLE `ad_faculty_users` ADD CONSTRAINT `fk_user_group` FOREIGN KEY (`group_id`) REFERENCES `ad_workload_groups`(`id`)", 
        "Added Foreign Key fk_user_group"
    );

    // 4. Update Mock Data
    $pdo->exec("UPDATE `ad_faculty_users` SET `group_id` = (SELECT id FROM ad_workload_groups WHERE group_code = 'B'), `role` = 'Faculty' WHERE username LIKE 'faculty%'");
    $pdo->exec("UPDATE `ad_faculty_users` SET `group_id` = (SELECT id FROM ad_workload_groups WHERE group_code = 'D'), `role` = 'Reviewer' WHERE username LIKE 'reviewer%'");
    echo "<div style='color:green'>✔ Mock Data Updated.</div>";

    // 5. Re-enable Constraints
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<div>Constraints Enabled. Update Complete.</div>";

} catch (PDOException $e) {
    echo "<h1>CRITICAL ERROR: " . $e->getMessage() . "</h1>";
}
?>
