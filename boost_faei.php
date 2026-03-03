<?php
require_once 'db_config.php';

$userId = 1; 

echo "Boosting FAEI for User $userId...\n";

// 1. Insert 5 Dummy Research Papers
// Schema: faculty_id, academic_year, publication_type, title, journal_name, publication_date, impact_factor, description
$papers = [
    ['Journal', 'Advanced Machine Learning in Healthcare', 'Nature Medicine', '2025-10-15', 12.5],
    ['Journal', 'Optimizing Cloud Architectures', 'IEEE Transactions', '2025-11-20', 8.4],
    ['Conference', 'AI Ethics in 2026', 'NeurIPS 2026', '2025-12-05', 5.0],
    ['Conference', 'Quantum Computing Primitives', 'ICML 2026', '2026-01-10', 6.2],
    ['Journal', 'Sustainable Energy Grids', 'Elsevier Energy', '2025-09-01', 4.5]
];

foreach ($papers as $p) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ad_appraisal_research 
            (faculty_id, academic_year, publication_type, title, journal_name, publication_date, impact_factor, status, description, created_at)
            VALUES (?, '2025-2026', ?, ?, ?, ?, ?, 'Published', 'High Impact Research', NOW())
        ");
        $stmt->execute([$userId, $p[0], $p[1], $p[2], $p[3], $p[4]]);
        echo "Inserted Paper: {$p[1]}\n";
    } catch (Exception $e) {
        echo "Error inserting paper: " . $e->getMessage() . "\n";
    }
}

// 2. Update Student Feedback to 9.5 (Verified Source)
// Schema: ad_academic_source
$stmt = $pdo->prepare("
    UPDATE ad_academic_source 
    SET avg_student_feedback = 9.5, avg_attainment_level = '1', class_avg_grade = 'A+' 
    WHERE faculty_id = ?
");
$stmt->execute([$userId]);
echo "Updated Student Feedback to 9.5 for all courses.\n";

echo "Done. Score Boosted. Refresh Dashboard.\n";
?>
