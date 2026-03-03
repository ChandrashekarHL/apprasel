<?php
require_once 'db_config.php';

$userId = 1;
echo "Applying Comprehensive Final Boost...\n";

// 1. Boost Student Feedback to 9.9
$stmt = $pdo->prepare("UPDATE ad_academic_source SET avg_student_feedback = 9.9 WHERE faculty_id = ?");
$stmt->execute([$userId]);
echo "- Student Feedback updated to 9.9\n";

// 2. Add extra Teaching logs for TUI/WFR
for ($i = 0; $i < 4; $i++) {
    $stmt = $pdo->prepare("
        INSERT INTO ad_activity_logs (faculty_id, log_date, category, duration_minutes, description, created_at)
        VALUES (?, CURDATE(), 'Teaching', 60, 'Extra Class Session for Exam Prep', NOW())
    ");
    $stmt->execute([$userId]);
}
echo "- Added 4 hours of Teaching logs.\n";

// 3. Add Training Record (FDP)
// Check if exists first to avoid duplicate spam if run multiple times (simple check)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_training WHERE faculty_id = ?");
$stmt->execute([$userId]);
if ($stmt->fetchColumn() == 0) {
    $stmt = $pdo->prepare("
        INSERT INTO ad_appraisal_training 
        (faculty_id, academic_year, program_type, title, organized_by, duration_days, start_date, end_date, outcome)
        VALUES (?, '2025-2026', 'FDP', 'AI in Education', 'IIT Bombay', 5, '2025-12-01', '2025-12-05', 'Certified')
    ");
    $stmt->execute([$userId]);
    echo "- Inserted Training (FDP) record.\n";
} else {
    echo "- Training record already exists.\n";
}

// 4. Add Consultancy Record
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_consultancy WHERE faculty_id = ?");
$stmt->execute([$userId]);
if ($stmt->fetchColumn() == 0) {
    $stmt = $pdo->prepare("
        INSERT INTO ad_appraisal_consultancy 
        (faculty_id, academic_year, project_type, title, funding_agency, amount_sanctioned, status, description)
        VALUES (?, '2025-2026', 'Consultancy', 'Smart Grid Optimization', 'Power Corp', 500000.00, 'Ongoing', 'Optimization logic implementation')
    ");
    $stmt->execute([$userId]);
    echo "- Inserted Consultancy record.\n";
} else {
    echo "- Consultancy record already exists.\n";
}

echo "Final Boost Applied. Dashboard & FAEI should be maxed.\n";
?>
