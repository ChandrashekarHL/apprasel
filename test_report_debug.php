<?php
// Test script to debug report generation
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'functions.php';
require_once 'config_api.php';
require_once 'faculty_performance_analyzer.php';
require_once 'WorkloadEngine.php';

echo "Testing report generation...\n\n";

try {
    // Test 1: Database connection
    echo "1. Testing database connection... ";
    if (!isset($pdo)) {
        die("FAILED: \$pdo not defined\n");
    }
    echo "OK\n";
    
    // Test 2: FacultyPerformanceAnalyzer
    echo "2. Testing FacultyPerformanceAnalyzer class... ";
    $analyzer = new FacultyPerformanceAnalyzer($pdo);
    echo "OK\n";
    
    // Test 3: Get first faculty
    echo "3. Getting first faculty... ";
    $stmt = $pdo->query("SELECT id, full_name FROM ad_faculty_users LIMIT 1");
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$faculty) {
        die("FAILED: No faculty found\n");
    }
    echo "OK (ID: {$faculty['id']}, Name: {$faculty['full_name']})\n";
    
    $fid = $faculty['id'];
    
    // Test 4: FAEI Analysis
    echo "4. Testing getFAEIAnalysis()... ";
    $faei = $analyzer->getFAEIAnalysis($fid);
    if (!$faei || !isset($faei['faei'])) {
        die("FAILED: getFAEIAnalysis returned invalid data\n");
    }
    echo "OK (FAEI: {$faei['faei']})\n";
    
    // Test 5: Weekly Progress
    echo "5. Testing analyzeWeeklyProgress()... ";
    $weekly = $analyzer->analyzeWeeklyProgress($fid);
    if (!$weekly || !isset($weekly['history'])) {
        die("FAILED: analyzeWeeklyProgress returned invalid data\n");
    }
    echo "OK (Weeks: " . count($weekly['history']) . ")\n";
    
    // Test 6: Task Completion
    echo "6. Testing analyzeTaskCompletion()... ";
    $tasks = $analyzer->analyzeTaskCompletion($fid, 60);
    if (!$tasks || !isset($tasks['total'])) {
        die("FAILED: analyzeTaskCompletion returned invalid data\n");
    }
    echo "OK (Total: {$tasks['total']})\n";
    
    // Test 7: AI Interactions
    echo "7. Testing analyzeAIInteractions()... ";
    $aiData = $analyzer->analyzeAIInteractions($fid, 60);
    if (!$aiData || !isset($aiData['active_days'])) {
        die("FAILED: analyzeAIInteractions returned invalid data\n");
    }
    echo "OK (Active days: {$aiData['active_days']})\n";
    
    // Test 8: Trend
    echo "8. Testing calculateTrend()... ";
    $trend = $analyzer->calculateTrend($fid);
    if (!$trend || !isset($trend['label'])) {
        die("FAILED: calculateTrend returned invalid data\n");
    }
    echo "OK (Trend: {$trend['label']})\n";
    
    echo "\n✅ All tests passed! Report generation should work.\n";
    echo "\nTry accessing: http://localhost/apprasel/generate_hod_report.php\n";
    echo "with POST data: {\"faculty_id\": $fid}\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
