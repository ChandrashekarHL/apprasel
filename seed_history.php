<?php
require_once 'db_config.php'; // Reuse DB connection
require_once 'WorkloadEngine.php';

// Target User: Assuming ID 1 (faculty1) based on context
$userId = 1; 

echo "Seeding history for User ID: $userId\n";

$weeks = [
    '2026-01-05',
    '2026-01-12'
];

foreach ($weeks as $startDate) {
    echo "Processing Week: $startDate... ";
    
    // 1. Insert Plan (Target 40h)
    $stmt = $pdo->prepare("
        INSERT INTO ad_workload_plans 
        (faculty_id, week_start_date, planned_teaching_hrs, planned_research_hrs, planned_admin_hrs, planned_mentoring_hrs, planned_aav_hrs, status, created_at)
        VALUES (?, ?, 16, 10, 4, 5, 5, 'Locked', NOW())
        ON DUPLICATE KEY UPDATE status='Locked'
    ");
    $stmt->execute([$userId, $startDate]);
    
    // 2. Insert Logs (Randomized Execution)
    // Teaching: 16h planned -> Execute ~15-16h
    insertLogs($pdo, $userId, $startDate, 'Teaching', 16);
    
    // Research: 10h planned -> Execute ~8-12h
    insertLogs($pdo, $userId, $startDate, 'Research', 10);
    
    // Admin: 4h planned -> Execute ~4h
    insertLogs($pdo, $userId, $startDate, 'Admin', 4);
    
    echo "Done.\n";
}

function insertLogs($pdo, $facultyId, $weekStart, $category, $targetHrs) {
    // Distribute hours across 5 days (Mon-Fri)
    $date = new DateTime($weekStart);
    
    for ($i=0; $i<5; $i++) {
        $dailyMins = ($targetHrs / 5) * 60; 
        $variance = rand(-30, 30); // +/- 30 mins variance
        $mins = max(10, $dailyMins + $variance);
        
        $logDate = $date->format('Y-m-d');
        
        $stmt = $pdo->prepare("
            INSERT INTO ad_activity_logs (faculty_id, log_date, category, duration_minutes, description, created_at)
            VALUES (?, ?, ?, ?, 'Dummy Activity', NOW())
        ");
        $stmt->execute([$facultyId, $logDate, $category, $mins]);
        
        $date->modify('+1 day');
    }
}

echo "Seeding Complete. Refresh Dashboard.\n";
?>
