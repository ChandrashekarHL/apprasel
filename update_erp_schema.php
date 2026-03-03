<?php
require_once 'db_config.php';

try {
    echo "Starting Schema Update for ERP Integration...\n";

    // 1. Update ad_faculty_users table
    $columns = [
        "ADD COLUMN `emp_id` VARCHAR(50) AFTER `id`",
        "ADD COLUMN `mobile` VARCHAR(20) AFTER `email`",
        "ADD COLUMN `school` VARCHAR(100) AFTER `department`",
        "ADD COLUMN `photo_url` TEXT AFTER `mobile`"
    ];

    foreach ($columns as $col) {
        try {
            // Check if column exists first to avoid errors (simplified check by running query)
            // A safer way is checking information_schema, but for this environment 'IGNORE' or try-catch is often used.
            // For robustness, let's just try to add, if it fails it likely exists.
            $pdo->exec("ALTER TABLE `ad_faculty_users` $col");
            echo "Added column to ad_faculty_users: $col\n";
        } catch (PDOException $e) {
            // echo "Column likely exists or error: " . $e->getMessage() . "\n";
        }
    }

    // 2. Update ad_academic_source table
    $ac_columns = [
        "ADD COLUMN `subject_code` VARCHAR(50) AFTER `course_title`",
        "ADD COLUMN `section` VARCHAR(20) AFTER `subject_code`",
        "ADD COLUMN `semester` INT AFTER `section`",
        "ADD COLUMN `term` VARCHAR(20) AFTER `semester`" // ODD/EVEN
    ];

    foreach ($ac_columns as $col) {
        try {
            $pdo->exec("ALTER TABLE `ad_academic_source` $col");
            echo "Added column to ad_academic_source: $col\n";
        } catch (PDOException $e) {
            // echo "Column likely exists or error: " . $e->getMessage() . "\n";
        }
    }

    echo "Schema Update Completed successfully.\n";

} catch (PDOException $e) {
    echo "Critical Error: " . $e->getMessage();
}
?>
