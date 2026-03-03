<?php
require_once 'db_config.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ad_faculty_users LIKE 'emp_id'");
    $col = $stmt->fetch();
    if ($col) {
        echo "VERIFIED: 'emp_id' column exists.\n";
    } else {
        echo "FAILED: 'emp_id' column MISSING.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
