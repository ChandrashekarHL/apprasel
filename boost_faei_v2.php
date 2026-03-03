<?php
require_once 'db_config.php';

$userId = 1; 

echo "Boosting FAEI (Part 2) for User $userId...\n";
echo "Goal: Maximize Mentoring Count and Admin Volume.\n";

// 1. Insert 15 Individual Mentoring Logs (Count-based score)
// ACS Logic: min(count * 0.1, 1.0) -> Need 10 entries.
for ($i=1; $i<=15; $i++) {
    $stmt = $pdo->prepare("
        INSERT INTO ad_activity_logs (faculty_id, log_date, category, duration_minutes, description, created_at)
        VALUES (?, CURDATE() - INTERVAL ? DAY, 'Mentoring', 30, ?, NOW())
    ");
    $stmt->execute([$userId, $i, "Student Project Mentoring Session #$i"]);
}
echo "Inserted 15 Mentoring Logs.\n";

// 2. Insert Extra Admin Logs (Volume-based score)
// RRF Logic: min(hours / 2, 1.0) -> Need 2+ hours. (Already done but ensuring cushion)
for ($i=1; $i<=5; $i++) {
    $stmt = $pdo->prepare("
        INSERT INTO ad_activity_logs (faculty_id, log_date, category, duration_minutes, description, created_at)
        VALUES (?, CURDATE() - INTERVAL ? DAY, 'Admin', 60, ?, NOW())
    ");
    $stmt->execute([$userId, $i, "Committee Meeting #$i"]);
}
echo "Inserted 5 Extra Admin Logs (5 hours).\n";

echo "Done. FAEI Components (ACS & RRF) should now be maximized.\n";
?>
