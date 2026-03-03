<?php
/**
 * Faculty Performance Analyzer
 * 
 * Analyzes faculty performance based on:
 * - Weekly progress (all semester weeks via WorkloadEngine)
 * - Daily task completion (ad_daily_ai_activity)
 * - AI supervisor interactions
 * - FAEI metrics and components
 * 
 * Provides automatic flagging (red/yellow/green) and generates
 * comprehensive performance data for HOD dashboard.
 */

require_once 'db_config.php';
require_once 'WorkloadEngine.php';

class FacultyPerformanceAnalyzer {
    private $pdo;
    private $engine;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->engine = new WorkloadEngine($pdo);
    }
    
    /**
     * Get comprehensive performance data for all faculty
     * @param string $sort_by 'flag_priority', 'faei', 'name', 'weekly_completion'
     * @return array Faculty performance data
     */
    /**
     * Get comprehensive performance data for all faculty
     * @param string $sort_by 'flag_priority', 'faei', 'name', 'weekly_completion'
     * @param string|null $department Optional department filter
     * @return array Faculty performance data
     */
    /**
     * Get comprehensive performance data for all faculty
     * @param string $sort_by 'flag_priority', 'faei', 'name', 'weekly_completion'
     * @param string|null $department Optional department filter
     * @param int|null $excludeId Optional ID to exclude (e.g. self)
     * @return array Faculty performance data
     */
    /**
     * Get comprehensive performance data for authorized faculty
     * @param string $sort_by 'flag_priority', 'faei', 'name', 'weekly_completion'
     * @param string|null $requester_emp_id The EMP_ID (or username) of the person requesting data
     * @param string|null $dept_filter Optional explicit department filter
     * @return array Faculty performance data
     */
    /**
     * Get comprehensive performance data for authorized faculty
     * @param string $sort_by 'flag_priority', 'faei', 'name', 'weekly_completion'
     * @param string|null $requester_emp_id The EMP_ID (or username) of the person requesting data
     * @param string|null $dept_filter Optional explicit department filter
     * @return array Faculty performance data
     */
    public function getAllFacultyPerformance($sort_by = 'flag_priority', $requester_emp_id = null, $dept_filter = null) {
        
        $authorized_emp_ids = [];

        // 1. Call External API to get Authorized Staff List
        // The API handles the hierarchy logic (VC -> Dean -> HOD) internally.
        if ($requester_emp_id) {
            $apiUrl = "https://erp.gmit.info/v3/fms/get_staff_by_dept.php";
            
            // Prepare POST data based on user input or session data
            // Session username is often the EMP_ID or mapped to it.
            $postData = [
                'username' => $requester_emp_id, 
                'dept' => $dept_filter // detailed filter if needed, often optional for Dean/VC
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); // Use Form Data (Standard POST)
            // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: applications/x-www-form-urlencoded']); // Optional, default for curl
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For dev/local if needed
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            // Debugging (Uncomment if needed)
            // error_log("API Response for $requester_emp_id: " . $response);

            if ($response) {
                $result = json_decode($response, true);
                if (isset($result['status']) && $result['status'] === 'success' && !empty($result['data'])) {
                    // Extract EMP_IDs from the API response
                    $authorized_emp_ids = array_column($result['data'], 'EMP_ID');
                }
            }
        }

        // 2. Fetch Performance Data for Authorized EMP_IDs
        $sql = "SELECT u.id, u.full_name, u.designation, u.department, u.group_id, g.group_code 
                FROM ad_faculty_users u
                LEFT JOIN ad_workload_groups g ON u.group_id = g.id
                WHERE u.username NOT LIKE 'reviewer%'";
        
        $params = [];
        
        if (!empty($authorized_emp_ids)) {
            // Filter by the allowed EMP_IDs from API
            $placeholders = str_repeat('?,', count($authorized_emp_ids) - 1) . '?';
            $sql .= " AND (emp_id IN ($placeholders) OR username IN ($placeholders))";
            $params = array_merge($params, $authorized_emp_ids, $authorized_emp_ids); //Check both fields to be safe
        } elseif ($dept_filter) {
            // Fallback: Legacy Department Filter if API fails or returns nothing
            $sql .= " AND (department = ? OR school = ?)"; 
            $params[] = $dept_filter;
            $params[] = $dept_filter;
        } elseif ($requester_emp_id && empty($authorized_emp_ids)) {
             // API was called but returned no authorized users (e.g. access denied)
             // Force return empty set
             $sql .= " AND 1=0"; 
        }
        
        $sql .= " ORDER BY full_name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $facultyList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        foreach ($facultyList as $faculty) {
            $fid = $faculty['id'];
            
            // Core metrics
            $faei = $this->engine->calculateFAEI($fid);
            $tui = $this->engine->calculateTUI($fid);
            
            // Weekly analysis
            $weeklyData = $this->analyzeWeeklyProgress($fid);
            
            // Daily tasks
            $taskData = $this->analyzeTaskCompletion($fid);
            
            // AI engagement
            $aiData = $this->analyzeAIInteractions($fid);
            
            // Trend calculation
            $trend = $this->calculateTrend($fid);
            
            // Agentic Oversight check
            $oversightStmt = $this->pdo->prepare("SELECT message FROM ad_agentic_oversight WHERE faculty_id = ? AND status = 'active' LIMIT 1");
            $oversightStmt->execute([$fid]);
            $oversight = $oversightStmt->fetch(PDO::FETCH_ASSOC);
            $is_oversight = (bool)$oversight;
            $oversight_msg = $oversight['message'] ?? '';

            // Performance flag
            $flag = $this->determinePerformanceFlag($fid, $faei, $weeklyData, $taskData);
            
            // Overall score
            $score = $this->calculatePerformanceScore($faei, $weeklyData, $taskData, $aiData, $trend);
            
            $results[] = [
                'id' => $fid,
                'name' => $faculty['full_name'],
                'designation' => $faculty['designation'],
                'department' => $faculty['department'],
                'faei' => round($faei * 10, 1), // Convert to 0-10 scale
                'tui' => round($tui * 10, 1),
                'weekly_completion' => $weeklyData['completion_rate'],
                'weeks_submitted' => $weeklyData['weeks_submitted'],
                'total_weeks' => $weeklyData['total_weeks'],
                'completed_tasks' => $taskData['completed'],
                'total_tasks' => $taskData['total'],
                'task_completion' => $taskData['completion_rate'],
                'missed_count' => $taskData['missed'],
                'ai_engagement_days' => $aiData['active_days'],
                'trend' => $trend['label'],
                'trend_direction' => $trend['direction'],
                'flag' => $flag,
                'score' => $score,
                'group_code' => $faculty['group_code'],
                'is_oversight' => $is_oversight,
                'oversight_message' => $oversight_msg
            ];
        }
        
        // Sort results
        usort($results, function($a, $b) use ($sort_by) {
            if ($sort_by === 'flag_priority') {
                $priority = ['red' => 0, 'yellow' => 1, 'green' => 2];
                return $priority[$a['flag']] - $priority[$b['flag']];
            } elseif ($sort_by === 'faei') {
                return $b['faei'] - $a['faei'];
            } elseif ($sort_by === 'weekly_completion') {
                return $b['weekly_completion'] - $a['weekly_completion'];
            } else {
                return strcmp($a['name'], $b['name']);
            }
        });
        
        return $results;
    }
    
    /**
     * Analyze weekly progress for entire semester
     * Uses WorkloadEngine::getYearlyProgress()
     */
    public function analyzeWeeklyProgress($faculty_id) {
        $weeklyHistory = $this->engine->getYearlyProgress($faculty_id);
        
        if (empty($weeklyHistory)) {
            return [
                'completion_rate' => 0,
                'weeks_submitted' => 0,
                'total_weeks' => 0,
                'pattern' => 'No data'
            ];
        }
        
        $totalWeeks = count($weeklyHistory);
        $submittedWeeks = 0;
        $completionRates = [];
        
        foreach ($weeklyHistory as $week) {
            $planned = floatval($week['planned']);
            $executed = floatval($week['executed']);
            
            if ($planned > 0) {
                $submittedWeeks++;
                $completionRates[] = min(($executed / $planned) * 100, 110); // Cap at 110%
            }
        }
        
        $avgCompletion = count($completionRates) > 0 
            ? round(array_sum($completionRates) / count($completionRates), 1) 
            : 0;
        
        // Pattern detection
        $pattern = $this->detectPattern($completionRates);
        
        return [
            'completion_rate' => $avgCompletion,
            'weeks_submitted' => $submittedWeeks,
            'total_weeks' => $totalWeeks,
            'pattern' => $pattern,
            'history' => $weeklyHistory
        ];
    }
    
    /**
     * Analyze daily task completion
     */
    public function analyzeTaskCompletion($faculty_id, $days = 30) {
        $startDate = date('Y-m-d', strtotime("-$days days"));
        
        $stmt = $this->pdo->prepare(
            "SELECT status, COUNT(*) as count 
             FROM ad_daily_ai_activity 
             WHERE faculty_id = ? AND activity_date >= ? 
             GROUP BY status"
        );
        $stmt->execute([$faculty_id, $startDate]);
        $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $completed = $statusCounts['Completed'] ?? 0;
        $missed = $statusCounts['Missed'] ?? 0;
        $assigned = $statusCounts['Assigned'] ?? 0;
        $total = $completed + $missed + $assigned;
        
        $completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;
        
        return [
            'completed' => $completed,
            'missed' => $missed,
            'total' => $total,
            'completion_rate' => $completionRate
        ];
    }
    
    /**
     * Analyze AI supervisor interactions
     */
    public function analyzeAIInteractions($faculty_id, $days = 30) {
        $startDate = date('Y-m-d', strtotime("-$days days"));
        
        // Count days with AI interaction
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT activity_date) as active_days,
                    GROUP_CONCAT(DISTINCT activity_date ORDER BY activity_date DESC) as dates
             FROM ad_daily_ai_activity 
             WHERE faculty_id = ? 
             AND activity_date >= ? 
             AND interaction_log IS NOT NULL 
             AND interaction_log != ''"
        );
        $stmt->execute([$faculty_id, $startDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $activeDays = $result['active_days'] ?? 0;
        
        // Analyze conversation patterns (basic)
        $stmt = $this->pdo->prepare(
            "SELECT interaction_log 
             FROM ad_daily_ai_activity 
             WHERE faculty_id = ? 
             AND activity_date >= ? 
             AND interaction_log IS NOT NULL 
             ORDER BY activity_date DESC 
             LIMIT 10"
        );
        $stmt->execute([$faculty_id, $startDate]);
        $conversations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Simple pattern detection
        $helpSeeking = 0;
        $reporting = 0;
        
        foreach ($conversations as $conv) {
            if (empty($conv)) continue;
            $lower = strtolower($conv);
            if (strpos($lower, 'help') !== false || strpos($lower, 'stuck') !== false || strpos($lower, 'how') !== false) {
                $helpSeeking++;
            }
            if (strpos($lower, 'completed') !== false || strpos($lower, 'done') !== false || strpos($lower, 'finished') !== false) {
                $reporting++;
            }
        }
        
        $pattern = 'Low engagement';
        if ($activeDays > 20) $pattern = 'Highly engaged';
        elseif ($activeDays > 10) $pattern = 'Moderately engaged';
        elseif ($helpSeeking > $reporting) $pattern = 'Help-seeking';
        elseif ($reporting > 0) $pattern = 'Regular reporting';
        
        return [
            'active_days' => $activeDays,
            'help_seeking_count' => $helpSeeking,
            'reporting_count' => $reporting,
            'pattern' => $pattern,
            'summary' => "$pattern - $activeDays active days in last $days days"
        ];
    }
    
    /**
     * Get FAEI analysis with components
     */
    public function getFAEIAnalysis($faculty_id) {
        return [
            'faei' => round($this->engine->calculateFAEI($faculty_id) * 10, 1),
            'tui' => round($this->engine->calculateTUI($faculty_id) * 10, 1),
            'wfr' => round($this->engine->calculateWFR($faculty_id) * 10, 1),
            'acs' => round($this->engine->calculateACS($faculty_id) * 10, 1),
            'rrf' => round($this->engine->calculateRRF($faculty_id) * 10, 1)
        ];
    }
    
    /**
     * Calculate performance trend (last 4 weeks vs previous 4)
     */
    public function calculateTrend($faculty_id, $weeks = 4) {
        $weeklyHistory = $this->engine->getYearlyProgress($faculty_id);
        
        if (count($weeklyHistory) < 8) {
            return ['label' => 'Insufficient data', 'direction' => 0];
        }
        
        // Get recent 4 and previous 4 weeks
        $recent = array_slice($weeklyHistory, 0, $weeks);
        $previous = array_slice($weeklyHistory, $weeks, $weeks);
        
        $recentAvg = $this->calculateAvgCompletion($recent);
        $previousAvg = $this->calculateAvgCompletion($previous);
        
        $diff = $recentAvg - $previousAvg;
        
        if ($diff > 10) {
            return ['label' => 'Improving', 'direction' => 1, 'diff' => round($diff, 1)];
        } elseif ($diff < -10) {
            return ['label' => 'Declining', 'direction' => -1, 'diff' => round($diff, 1)];
        } else {
            return ['label' => 'Stable', 'direction' => 0, 'diff' => round($diff, 1)];
        }
    }
    
    /**
     * Calculate average completion from weeks array
     */
    private function calculateAvgCompletion($weeks) {
        $rates = [];
        foreach ($weeks as $week) {
            $planned = floatval($week['planned']);
            $executed = floatval($week['executed']);
            if ($planned > 0) {
                $rates[] = ($executed / $planned) * 100;
            }
        }
        return count($rates) > 0 ? array_sum($rates) / count($rates) : 0;
    }
    
    /**
     * Detect weekly performance pattern
     */
    private function detectPattern($completionRates) {
        if (count($completionRates) < 3) return 'Insufficient data';
        
        $below70 = count(array_filter($completionRates, fn($r) => $r < 70));
        $above80 = count(array_filter($completionRates, fn($r) => $r >= 80));
        $total = count($completionRates);
        
        if ($below70 / $total > 0.6) return 'Consistent underperformer';
        if ($above80 / $total > 0.8) return 'Strong performer';
        
        // Check variance for sporadic pattern
        $mean = array_sum($completionRates) / $total;
        $variance = array_sum(array_map(fn($r) => pow($r - $mean, 2), $completionRates)) / $total;
        $stdDev = sqrt($variance);
        
        if ($stdDev > 30) return 'Sporadic engagement';
        
        return 'Moderate performer';
    }
    
    /**
     * Determine performance flag color
     */
    public function determinePerformanceFlag($faculty_id, $faei, $weeklyData, $taskData) {
        // RED criteria (critical)
        if ($faei < 0.5 || // FAEI < 5
            $taskData['missed'] >= 5 || // 5+ missed daily tasks
            $weeklyData['completion_rate'] < 50 || // <50% weekly completion
            $taskData['completion_rate'] < 30) { // <30% daily task completion
            return 'red';
        }
        
        // YELLOW criteria (warning)
        if ($faei < 0.7 || // FAEI 5-7
            $taskData['missed'] >= 2 || // 2-4 missed tasks
            $weeklyData['completion_rate'] < 70 || // 50-70% weekly completion
            $taskData['completion_rate'] < 60) { // 30-60% daily completion
            return 'yellow';
        }
        
        // GREEN (good performance)
        return 'green';
    }
    
    /**
     * Calculate overall performance score (0-100)
     */
    public function calculatePerformanceScore($faei, $weeklyData, $taskData, $aiData, $trend) {
        $score = 0;
        
        // FAEI - 30%
        $score += ($faei * 10) * 3; // FAEI is 0-1, convert to 0-10, multiply by 3 for 30%
        
        // Weekly Completion - 35%
        $score += $weeklyData['completion_rate'] * 0.35;
        
        // Daily Task Completion - 15%
        $score += $taskData['completion_rate'] * 0.15;
        
        // AI Engagement - 10% (based on active days out of 30)
        $engagementScore = min(($aiData['active_days'] / 30) * 100, 100);
        $score += $engagementScore * 0.10;
        
        // Trend - 10%
        if ($trend['direction'] === 1) {
            $score += 10; // Improving
        } elseif ($trend['direction'] === 0) {
            $score += 5; // Stable
        }
        // Declining gets 0
        
        return min(round($score), 100);
    }
}
?>
