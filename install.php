<?php
require_once 'db_config.php';

$sqlFile = 'setup_db.sql';

if (!file_exists($sqlFile)) {
    die("Error: SQL file not found.");
}

$sql = file_get_contents($sqlFile);

try {
    $pdo->exec($sql);
    echo "<h1>Database Setup Completed Successfully!</h1>";
    echo "<p>Tables created and mock data inserted.</p>";
    echo "<a href='dashboard.php'>Go to Dashboard</a>";
} catch (PDOException $e) {
    echo "<h1>Error setting up database:</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
