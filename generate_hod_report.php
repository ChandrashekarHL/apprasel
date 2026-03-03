<?php
/**
 * Generate Comprehensive HOD Performance Report
 * 
 * Detailed data-rich report including:
 * - Daily logging streak
 * - Task-by-task completion
 * - Weekly progress table (all weeks)
 * - FAEI component breakdown
 * - Activity history
 * - AI conversation summary
 */

require_once 'functions.php';
require_once 'config_api.php';
require_once 'faculty_performance_analyzer.php';
require_once 'WorkloadEngine.php';

// Turn off error display to prevent HTML errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Security check - less strict since we're called from HOD dashboard
    session_start();
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Session not found. Please refresh the page.']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $faculty_id = $input['faculty_id'] ?? 0;
    
    if (!$faculty_id) {
        echo json_encode(['error' => 'Faculty ID required']);
        exit;
    }
    
    // Initialize
    $analyzer = new FacultyPerformanceAnalyzer($pdo);
    $engine = new WorkloadEngine($pdo);
    
    // Get faculty info
    $stmt = $pdo->prepare("SELECT full_name, designation, department FROM ad_faculty_users WHERE id = ?");
    $stmt->execute([$faculty_id]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$faculty) {
        echo json_encode(['error' => 'Faculty not found']);
        exit;
    }

// 1. FAEI Component Breakdown
$faeiData = $analyzer->getFAEIAnalysis($faculty_id);

// 2. Weekly Progress (ALL weeks)
$weeklyData = $analyzer->analyzeWeeklyProgress($faculty_id);
$weeklyHistory = $weeklyData['history'];

// 3. Daily Tasks (last 60 days for comprehensive view)
$taskData = $analyzer->analyzeTaskCompletion($faculty_id, 60);

// 4. Daily Logging Streak & Task Details
$stmt = $pdo->prepare(
    "SELECT activity_date, activity_text, status, completed_at, interaction_log
     FROM ad_daily_ai_activity 
     WHERE faculty_id = ? 
     ORDER BY activity_date DESC 
     LIMIT 90"
);
$stmt->execute([$faculty_id]);
$dailyTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate streak
$streak = 0;
$maxStreak = 0;
$currentStreak = 0;
$lastDate = null;

foreach ($dailyTasks as $task) {
    $date = new DateTime($task['activity_date']);
    
    if ($lastDate === null) {
        $currentStreak = ($task['status'] === 'Completed') ? 1 : 0;
    } else {
        $diff = $lastDate->diff($date)->days;
        if ($diff === 1 && $task['status'] === 'Completed') {
            $currentStreak++;
        } else {
            $maxStreak = max($maxStreak, $currentStreak);
            $currentStreak = ($task['status'] === 'Completed') ? 1 : 0;
        }
    }
    
    $lastDate = $date;
}
$maxStreak = max($maxStreak, $currentStreak);
$streak = $currentStreak;

// 5. Activity Logs (recent 30 days detailed)
$stmt = $pdo->prepare(
    "SELECT log_date, category, description, duration_minutes, created_at
     FROM ad_activity_logs 
     WHERE faculty_id = ? 
     AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     ORDER BY log_date DESC, created_at DESC 
     LIMIT 50"
);
$stmt->execute([$faculty_id]);
$activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group activities by category
$activityByCategory = [];
foreach ($activityLogs as $log) {
    $cat = $log['category'];
    if (!isset($activityByCategory[$cat])) {
        $activityByCategory[$cat] = ['count' => 0, 'hours' => 0];
    }
    $activityByCategory[$cat]['count']++;
    $activityByCategory[$cat]['hours'] += $log['duration_minutes'] / 60;
}

// 6. AI Engagement Analysis
$aiData = $analyzer->analyzeAIInteractions($faculty_id, 60);

// 7. Trend & Flag
$trend = $analyzer->calculateTrend($faculty_id);
$flag = $analyzer->determinePerformanceFlag($faculty_id, $faeiData['faei']/10, $weeklyData, $taskData);

// Generate comprehensive HTML report
$reportHTML = generateComprehensiveReport(
    $faculty,
    $faeiData,
    $weeklyHistory,
    $taskData,
    $dailyTasks,
    $streak,
    $maxStreak,
    $activityLogs,
    $activityByCategory,
    $aiData,
    $trend,
    $flag
);
    
    echo json_encode(['report' => $reportHTML]);
    
} catch (Exception $e) {
    // Return error as JSON instead of HTML
    http_response_code(500);
    echo json_encode([
        'error' => 'Report generation failed',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

// ============================================
// REPORT GENERATION FUNCTION
// ============================================

function generateComprehensiveReport($faculty, $faei, $weeklyHistory, $taskData, $dailyTasks, $streak, $maxStreak, $activityLogs, $activityByCategory, $aiData, $trend, $flag) {
    $flagColors = ['red' => '#e74c3c', 'yellow' => '#f39c12', 'green' => '#27ae60'];
    $flagColor = $flagColors[$flag];
    $flagLabel = ['red' => 'CRITICAL', 'yellow' => 'WARNING', 'green' => 'GOOD'][$flag];
    
    $html = "
    <div style='font-family: Arial, sans-serif; color: #2c3e50;'>
        <div style='background: linear-gradient(135deg, {$flagColor}, " . darkenColor($flagColor, 20) . "); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h2 style='margin: 0 0 10px 0;'>{$faculty['full_name']}</h2>
            <p style='margin: 0; opacity: 0.9;'>{$faculty['designation']} • {$faculty['department']}</p>
            <div style='margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 5px; display: inline-block;'>
                <strong style='font-size: 1.2em;'>STATUS: {$flagLabel}</strong>
            </div>
        </div>
        
        <!-- FAEI Component Breakdown -->
        <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
            <h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>
                <i class='fas fa-chart-line'></i> FAEI Components Breakdown
            </h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='background: #ecf0f1;'>
                    <th style='padding: 10px; text-align: left; border: 1px solid #bdc3c7;'>Metric</th>
                    <th style='padding: 10px; text-align: center; border: 1px solid #bdc3c7;'>Score</th>
                    <th style='padding: 10px; text-align: left; border: 1px solid #bdc3c7;'>Status</th>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'><strong>Overall FAEI</strong></td>
                    <td style='padding: 10px; text-align: center; border: 1px solid #bdc3c7; font-size: 1.3em; font-weight: bold; color: {$flagColor};'>{$faei['faei']}/10</td>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>" . getScoreLabel($faei['faei']) . "</td>
                </tr>
                <tr style='background: #f8f9fa;'>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>TUI (Time Utilization Index)</td>
                    <td style='padding: 10px; text-align: center; border: 1px solid #bdc3c7;'><strong>{$faei['tui']}/10</strong></td>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>" . getScoreLabel($faei['tui']) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>WFR (Workload Fulfillment Ratio)</td>
                    <td style='padding: 10px; text-align: center; border: 1px solid #bdc3c7;'><strong>{$faei['wfr']}/10</strong></td>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>" . getScoreLabel($faei['wfr']) . "</td>
                </tr>
                <tr style='background: #f8f9fa;'>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>ACS (Academic Contribution Score)</td>
                    <td style='padding: 10px; text-align: center; border: 1px solid #bdc3c7;'><strong>{$faei['acs']}/10</strong></td>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>" . getScoreLabel($faei['acs']) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>RRF (Role Responsibility Fulfillment)</td>
                    <td style='padding: 10px; text-align: center; border: 1px solid #bdc3c7;'><strong>{$faei['rrf']}/10</strong></td>
                    <td style='padding: 10px; border: 1px solid #bdc3c7;'>" . getScoreLabel($faei['rrf']) . "</td>
                </tr>
            </table>
        </div>
        
        <!-- Daily Logging & Streak -->
        <div style='background: #e8f4f8; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #3498db;'>
            <h3 style='margin-top: 0; color: #2c3e50;'>
                <i class='fas fa-fire'></i> Daily Logging Streak & Task Completion
            </h3>
            <div style='display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px;'>
                <div style='text-align: center; background: white; padding: 15px; border-radius: 5px;'>
                    <div style='font-size: 2em; font-weight: bold; color: #e74c3c;'>{$streak}</div>
                    <div style='font-size: 0.9em; color: #7f8c8d;'>Current Streak (Days)</div>
                </div>
                <div style='text-align: center; background: white; padding: 15px; border-radius: 5px;'>
                    <div style='font-size: 2em; font-weight: bold; color: #f39c12;'>{$maxStreak}</div>
                    <div style='font-size: 0.9em; color: #7f8c8d;'>Longest Streak</div>
                </div>
                <div style='text-align: center; background: white; padding: 15px; border-radius: 5px;'>
                    <div style='font-size: 2em; font-weight: bold; color: #27ae60;'>{$taskData['completion_rate']}%</div>
                    <div style='font-size: 0.9em; color: #7f8c8d;'>Task Completion Rate</div>
                </div>
            </div>
            <p style='margin: 10px 0 0 0; font-size: 0.9em;'>
                <strong>Last 60 Days:</strong> {$taskData['completed']} completed, {$taskData['missed']} missed out of {$taskData['total']} total tasks
            </p>
        </div>
        
        <!-- Daily Tasks Detailed -->
        <div style='background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0;'>
            <h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>
                <i class='fas fa-tasks'></i> Daily Tasks Assigned by AI Supervisor (Last 30 Days)
            </h3>
            <table style='width: 100%; border-collapse: collapse; font-size: 0.9em;'>
                <thead>
                    <tr style='background: #34495e; color: white;'>
                        <th style='padding: 10px; text-align: left;'>Date</th>
                        <th style='padding: 10px; text-align: left;'>Task Assigned</th>
                        <th style='padding: 10px; text-align: center;'>Status</th>
                        <th style='padding: 10px; text-align: left;'>Completed At</th>
                    </tr>
                </thead>
                <tbody>";
    
    $displayedTasks = array_slice($dailyTasks, 0, 30);
    foreach ($displayedTasks as $task) {
        $statusColor = [
            'Completed' => '#27ae60',
            'Missed' => '#e74c3c',
            'Assigned' => '#f39c12'
        ][$task['status']] ?? '#95a5a6';
        
        $completedAt = $task['completed_at'] ? date('M d, h:i A', strtotime($task['completed_at'])) : '-';
        
        $html .= "
                <tr style='border-bottom: 1px solid #ecf0f1;'>
                    <td style='padding: 10px;'>" . date('M d, Y', strtotime($task['activity_date'])) . "</td>
                    <td style='padding: 10px;'>" . htmlspecialchars($task['activity_text'] ?? 'Daily Check-in') . "</td>
                    <td style='padding: 10px; text-align: center;'>
                        <span style='background: {$statusColor}; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.85em; font-weight: bold;'>
                            {$task['status']}
                        </span>
                    </td>
                    <td style='padding: 10px;'>{$completedAt}</td>
                </tr>";
    }
    
    $html .= "
                </tbody>
            </table>
        </div>
        
        <!-- Weekly Progress Table -->
        <div style='background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0;'>
            <h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>
                <i class='fas fa-calendar-week'></i> Weekly Progress - Entire Semester
            </h3>
            <div style='max-height: 400px; overflow-y: auto;'>
                <table style='width: 100%; border-collapse: collapse; font-size: 0.9em;'>
                    <thead style='position: sticky; top: 0; background: #34495e; color: white;'>
                        <tr>
                            <th style='padding: 10px; text-align: left;'>Week #</th>
                            <th style='padding: 10px; text-align: left;'>Week Starting</th>
                            <th style='padding: 10px; text-align: right;'>Planned (hrs)</th>
                            <th style='padding: 10px; text-align: right;'>Executed (hrs)</th>
                            <th style='padding: 10px; text-align: center;'>Completion %</th>
                            <th style='padding: 10px; text-align: center;'>Status</th>
                        </tr>
                    </thead>
                    <tbody>";
    
    foreach ($weeklyHistory as $week) {
        $planned = floatval($week['planned']);
        $executed = floatval($week['executed']);
        $completion = $planned > 0 ? round(($executed / $planned) * 100) : 0;
        
        $completionColor = $completion >= 90 ? '#27ae60' : ($completion >= 70 ? '#f39c12' : '#e74c3c');
        
        $html .= "
                    <tr style='border-bottom: 1px solid #ecf0f1; background: " . ($week['week_no'] % 2 == 0 ? '#f8f9fa' : 'white') . ";'>
                        <td style='padding: 10px; font-weight: bold;'># {$week['week_no']}</td>
                        <td style='padding: 10px;'>" . date('M d, Y', strtotime($week['start_date'])) . "</td>
                        <td style='padding: 10px; text-align: right;'>{$planned}</td>
                        <td style='padding: 10px; text-align: right;'>{$executed}</td>
                        <td style='padding: 10px; text-align: center;'>
                            <strong style='color: {$completionColor};'>{$completion}%</strong>
                        </td>
                        <td style='padding: 10px; text-align: center; font-size: 0.85em;'>{$week['status']}</td>
                    </tr>";
    }
    
    $html .= "
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Activity Logs (Last 30 Days) -->
        <div style='background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0;'>
            <h3 style='margin-top: 0; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>
                <i class='fas fa-clipboard-list'></i> Recent Activity Logs (Last 30 Days)
            </h3>
            
            <!-- Activity Summary by Category -->
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;'>
                <h4 style='margin: 0 0 10px 0;'>Activity Summary by Category</h4>
                <div style='display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;'>";
    
    $categoryIcons = [
        'Teaching' => 'fa-chalkboard-teacher',
        'Research' => 'fa-flask',
        'Admin' => 'fa-cog',
        'Mentoring' => 'fa-users',
        'Training' => 'fa-graduation-cap'
    ];
    
    foreach ($activityByCategory as $cat => $data) {
        $icon = $categoryIcons[$cat] ?? 'fa-file-alt';
        $html .= "
                    <div style='background: white; padding: 10px; border-radius: 5px; text-align: center;'>
                        <i class='fas {$icon}' style='color: #3498db; font-size: 1.5em;'></i>
                        <div style='font-weight: bold; margin-top: 5px;'>{$cat}</div>
                        <div style='font-size: 0.9em; color: #7f8c8d;'>{$data['count']} activities</div>
                        <div style='font-size: 1.1em; color: #2c3e50; font-weight: bold;'>" . round($data['hours'], 1) . " hrs</div>
                    </div>";
    }
    
    $html .= "
                </div>
            </div>
            
            <!-- Detailed Activity List -->
            <div style='max-height: 300px; overflow-y: auto;'>
                <table style='width: 100%; border-collapse: collapse; font-size: 0.85em;'>
                    <thead style='position: sticky; top: 0; background: #ecf0f1;'>
                        <tr>
                            <th style='padding: 8px; text-align: left;'>Date</th>
                            <th style='padding: 8px; text-align: left;'>Category</th>
                            <th style='padding: 8px; text-align: left;'>Description</th>
                            <th style='padding: 8px; text-align: right;'>Duration</th>
                        </tr>
                    </thead>
                    <tbody>";
    
    foreach ($activityLogs as $log) {
        $hours = round($log['duration_minutes'] / 60, 1);
        $html .= "
                    <tr style='border-bottom: 1px solid #ecf0f1;'>
                        <td style='padding: 8px;'>" . date('M d', strtotime($log['log_date'])) . "</td>
                        <td style='padding: 8px;'><span style='background: #3498db; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8em;'>{$log['category']}</span></td>
                        <td style='padding: 8px;'>" . htmlspecialchars(substr($log['description'], 0, 60)) . (strlen($log['description']) > 60 ? '...' : '') . "</td>
                        <td style='padding: 8px; text-align: right;'><strong>{$hours}h</strong></td>
                    </tr>";
    }
    
    $html .= "
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Performance Trend & AI Insights -->
        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h3 style='margin-top: 0; border-bottom: 2px solid rgba(255,255,255,0.3); padding-bottom: 10px;'>
                <i class='fas fa-chart-line'></i> Performance Trend & AI Engagement
            </h3>
            <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'>
                <div>
                    <h4 style='margin: 10px 0 5px 0;'>Trend Analysis (Last 8 Weeks)</h4>
                    <div style='font-size: 1.8em; font-weight: bold;'>{$trend['label']}</div>
                    <p style='margin: 5px 0 0 0; opacity: 0.9;'>" . getTrendDescription($trend) . "</p>
                </div>
                <div>
                    <h4 style='margin: 10px 0 5px 0;'>AI Supervisor Engagement</h4>
                    <div style='font-size: 1.8em; font-weight: bold;'>{$aiData['active_days']} Days</div>
                    <p style='margin: 5px 0 0 0; opacity: 0.9;'>{$aiData['pattern']}</p>
                </div>
            </div>
        </div>
        
        <!-- HOD Recommendations -->
        <div style='background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 5px solid #f39c12;'>
            <h3 style='margin-top: 0; color: #856404;'>
                <i class='fas fa-lightbulb'></i> Recommended Actions for HOD
            </h3>
            " . getHODRecommendations($flag, $faei, $taskData, $trend) . "
        </div>
    </div>";
    
    return $html;
}

function getScoreLabel($score) {
    if ($score >= 8) return "<span style='color: #27ae60;'>●</span> Excellent";
    if ($score >= 6) return "<span style='color: #f39c12;'>●</span> Good";
    if ($score >= 4) return "<span style='color: #e67e22;'>●</span> Satisfactory";
    return "<span style='color: #e74c3c;'>●</span> Needs Improvement";
}

function getTrendDescription($trend) {
    if ($trend['direction'] === 1) {
        return "Performance improving by {$trend['diff']}% compared to previous period";
    } elseif ($trend['direction'] === -1) {
        return "Performance declining by " . abs($trend['diff']) . "% - intervention recommended";
    }
    return "Performance stable with {$trend['diff']}% variation";
}

function getHODRecommendations($flag, $faei, $taskData, $trend) {
    if ($flag === 'red') {
        return "
        <ol style='margin: 10px 0; color: #856404;'>
            <li style='margin-bottom: 10px;'><strong>Immediate Action:</strong> Schedule 1-on-1 meeting this week to discuss performance issues</li>
            <li style='margin-bottom: 10px;'><strong>Short-term:</strong> Implement 2-week performance improvement plan with specific milestones</li>
            <li style='margin-bottom: 10px;'><strong>Support:</strong> Assign peer mentor and reduce non-critical workload temporarily</li>
            <li><strong>Monitor:</strong> Daily check-ins for next 2 weeks, then weekly reviews</li>
        </ol>";
    } elseif ($flag === 'yellow') {
        return "
        <ol style='margin: 10px 0; color: #856404;'>
            <li style='margin-bottom: 10px;'><strong>Check-in:</strong> Have supportive conversation to identify challenges</li>
            <li style='margin-bottom: 10px;'><strong>Resources:</strong> Provide time management or task prioritization training</li>
            <li style='margin-bottom: 10px;'><strong>Follow-up:</strong> Set 2-week review milestone to track improvement</li>
        </ol>";
    } else {
        return "
        <ol style='margin: 10px 0; color: #856404;'>
            <li style='margin-bottom: 10px;'><strong>Recognition:</strong> Acknowledge good performance in department meeting</li>
            <li style='margin-bottom: 10px;'><strong>Growth:</strong> Consider for leadership role in curriculum development or mentoring</li>
            <li><strong>Development:</strong> Identify 1-2 areas for professional growth (e.g., research publications, conference presentations)</li>
        </ol>";
    }
}

function darkenColor($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, $r - ($r * $percent / 100));
    $g = max(0, $g - ($g * $percent / 100));
    $b = max(0, $b - ($b * $percent / 100));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
?>
