<?php
/**
 * Faculty Performance Analyzer
 * 
 * Analyzes faculty performance based on:
 * - Weekly progress (all semester weeks via WorkloadEngine)
 * - Daily task completion (fms_daily_ai_activity)
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
    public function getAllFacultyPerformance($sort_by = 'flag_priority', $requester_emp_id = null, $dept_filter = null, $allow_all = false) {
        
        $authorized_emp_ids = [];

        // ── Resolve the actual DEPT code from staff_master ───────────────────────
        $resolved_dept = null;

        // Level 1: Look up the requester's own record in staff_master by their ERP/EMP ID
        if ($requester_emp_id) {
            try {
                $hodStmt = $this->pdo->prepare(
                    "SELECT DEPT FROM staff_master WHERE EMP_ID = ? LIMIT 1"
                );
                $hodStmt->execute([$requester_emp_id]);
                $hodRow = $hodStmt->fetch(PDO::FETCH_ASSOC);
                if ($hodRow && !empty($hodRow['DEPT'])) {
                    $resolved_dept = $hodRow['DEPT'];
                }
            } catch (Exception $e) { /* continue */ }
        }

        // Level 2: FMS department to staff_master DEPT mapping
        if (!$resolved_dept && $dept_filter) {
            $dept_map = [
                'CHE' => 'CHEMISTRY',
                'PHY' => 'PHYSICS',
                'MAT' => 'MAT'
            ];
            
            $upper_filter = strtoupper(trim($dept_filter));
            if (isset($dept_map[$upper_filter])) {
                $resolved_dept = $dept_map[$upper_filter];
            } else {
                // If it's a prefix like "CSE" or "MECH", this keeps it intact
                $resolved_dept = $dept_filter;
            }
        }

        // ── Step 2: Fetch Performance Data filtered by the resolved DEPT ──────────
        $sql = "SELECT 
                    s.EMP_ID as emp_id,
                    s.NAME as full_name,
                    s.DESIGNATION as designation,
                    s.DEPT as department,
                    u.id, 
                    u.username, 
                    u.group_id, 
                    g.group_code 
                FROM staff_master s
                LEFT JOIN fms_faculty_users u ON s.EMP_ID = u.emp_id
                LEFT JOIN fms_workload_groups g ON u.group_id = g.id
                WHERE s.STATUS = 'WORKING' AND s.CATEGORY = 'TEACHING'";
        
        $params = [];
        
        if ($resolved_dept) {
            // Primary: filter by the exact DEPT code from staff_master
            $sql .= " AND s.DEPT = ?";
            $params[] = $resolved_dept;
        } elseif (!$allow_all) {
            // If no filter at all, show nothing for safety
            $sql .= " AND 1=0";
        }
        
        $sql .= " ORDER BY s.NAME";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $facultyList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        foreach ($facultyList as $faculty) {
            $fid = $faculty['id']; // This could be null if they haven't logged in
            
            // Core metrics
            $faei = $fid ? $this->engine->calculateFAEI($fid) : 0;
            $tui  = $fid ? $this->engine->calculateTUI($fid) : 0;
            
            // Weekly analysis
            $weeklyData = $fid ? $this->analyzeWeeklyProgress($fid) : [
                'completion_rate' => 0, 'weeks_submitted' => 0, 'total_weeks' => 0, 'pattern' => 'No account'
            ];
            
            // Daily tasks
            $taskData = $fid ? $this->analyzeTaskCompletion($fid) : [
                'completed' => 0, 'missed' => 0, 'total' => 0, 'completion_rate' => 0
            ];
            
            // AI engagement
            $aiData = $fid ? $this->analyzeAIInteractions($fid) : [
                'active_days' => 0, 'help_seeking_count' => 0, 'reporting_count' => 0, 'pattern' => 'No account', 'summary' => 'Never logged in'
            ];
            
            // Trend calculation
            $trend = $fid ? $this->calculateTrend($fid) : ['label' => 'Insufficient data', 'direction' => 0];
            
            // Agentic Oversight check
            $is_oversight = false;
            $oversight_msg = '';
            if ($fid) {
                $oversightStmt = $this->pdo->prepare("SELECT message FROM fms_agentic_oversight WHERE faculty_id = ? AND status = 'active' LIMIT 1");
                $oversightStmt->execute([$fid]);
                $oversight = $oversightStmt->fetch(PDO::FETCH_ASSOC);
                $is_oversight = (bool)$oversight;
                $oversight_msg = $oversight['message'] ?? '';
            }

            // Performance flag
            $flag = $this->determinePerformanceFlag($fid, $faei, $weeklyData, $taskData);
            
            // Overall score
            $score = $this->calculatePerformanceScore($faei, $weeklyData, $taskData, $aiData, $trend);
            
            $results[] = [
                'id' => $fid,
                'name' => $faculty['full_name'],
                'emp_id' => $faculty['emp_id'] ?? '',
                'username' => $faculty['username'] ?? '',
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
             FROM fms_daily_ai_activity 
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
             FROM fms_daily_ai_activity 
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
             FROM fms_daily_ai_activity 
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
