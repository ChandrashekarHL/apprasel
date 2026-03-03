<?php
require_once 'db_config.php';

$userId = 1; 
$weekStart = '2026-01-12';

echo "Forcing Completion for User $userId, Week: $weekStart\n";

// 1. Clear existing logs for this week to avoid duplicates/mess
$stmt = $pdo->prepare("DELETE FROM ad_activity_logs WHERE faculty_id = ? AND log_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)");
$stmt->execute([$userId, $weekStart, $weekStart]);

// 2. Insert Exact Logs to match Standard Plan (40h)
// Plan: Teaching 16, Research 10, Admin 4, Mentoring 5, AAV 5
$activities = [
    'Teaching' => 16,
    'Research' => 10,
    'Admin' => 4,
    'Mentoring' => 5,
    'AAV' => 5
];

$dateObj = new DateTime($weekStart);

foreach ($activities as $cat => $hours) {
    // Insert single log entry for simplicity, or split?
    // Let's insert one bulk entry per category on Monday to ensure total is exact.
    
    $mins = $hours * 60;
    $logDate = $dateObj->format('Y-m-d'); // All on Monday is fine for weekly sum
    
    $stmt = $pdo->prepare("
        INSERT INTO ad_activity_logs (faculty_id, log_date, category, duration_minutes, description, created_at)
        VALUES (?, ?, ?, ?, 'Forced Completion Activity', NOW())
    ");
    $stmt->execute([$userId, $logDate, $cat, $mins]);
    
    echo "Logged $hours hrs for $cat.\n";
}

echo "Done. Total 40h logged for week of $weekStart.\n";
?>
