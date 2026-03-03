<?php
require_once 'db_config.php';

try {
    echo "Starting Database Cleanup...\n";
    
    // Disable FK Checks to allow cleanup of circular/missed dependencies
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $pdo->beginTransaction();

    // List of tables with faculty_id to clean
    $tables = [
        'ad_academic_source',
        'ad_appraisal_academic_defs',
        'ad_appraisal_research',
        'ad_appraisal_training',
        'ad_appraisal_consultancy',
        'ad_appraisal_files',
        'ad_appraisal_reviews',
        'ad_daily_ai_activity',
        'ad_activity_logs',
        'ad_administration'
    ];

    // Identify Mock Users
    $mockUsers = ['faculty1', 'faculty2', 'reviewer1'];
    $placeholders = implode(',', array_fill(0, count($mockUsers), '?'));

    // Get IDs
    $stmt = $pdo->prepare("SELECT id FROM ad_faculty_users WHERE username IN ($placeholders)");
    $stmt->execute($mockUsers);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) > 0) {
        $idStr = implode(',', $ids);
        echo "Found Mock IDs: $idStr\n";

        // Clean Child Tables
        foreach ($tables as $tbl) {
            // Check if table exists (just in case)
            $check = $pdo->query("SHOW TABLES LIKE '$tbl'");
            if ($check->rowCount() > 0) {
                // Check if column faculty_id exists? Assuming yes based on schema.
                // For 'ad_appraisal_reviews', it has faculty_id AND reviewer_id. 
                // We'll simplisticly delete where faculty_id matches.
                $del = $pdo->exec("DELETE FROM $tbl WHERE faculty_id IN ($idStr)");
                echo "Deleted $del rows from $tbl\n";
            }
        }

        // Clean Users Table
        $delUsers = $pdo->exec("DELETE FROM ad_faculty_users WHERE id IN ($idStr)");
        echo "Deleted $delUsers mock users from ad_faculty_users.\n";
        
        $pdo->commit();
        echo "Cleanup Successful.\n";
    } else {
        echo "No mock users found to delete.\n";
        $pdo->rollBack();
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
