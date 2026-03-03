<?php
require_once 'db_config.php';

class WorkloadEngine {
    private $pdo;
    private $academicYear;

    public function __construct($pdo, $academicYear = '2024-2025') {
        $this->pdo = $pdo;
        $this->academicYear = $academicYear;
    }

    /**
     * Get or Create a Weekly Plan Stub
     */
    public function getWeeklyPlan($facultyId, $weekStartDate) {
        $stmt = $this->pdo->prepare("SELECT * FROM ad_workload_plans WHERE faculty_id = ? AND week_start_date = ?");
        $stmt->execute([$facultyId, $weekStartDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get Target Ratios for Faculty based on Group
     */
    public function getFacultyTargets($facultyId) {
        $sql = "SELECT g.*, u.full_name, u.role FROM ad_faculty_users u 
                LEFT JOIN ad_workload_groups g ON u.group_id = g.id 
                WHERE u.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$facultyId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate Time Utilization Index (TUI)
     * Formula: Actual Logged Hours / Planned Hours
     * Constraint: Capped at 1.10 (No reward for unhealthy overload)
     */
    public function calculateTUI($facultyId) {
        // Dynamic Academic Year
        $curMonth = date('n');
        $curYear = date('Y');
        
        if ($curMonth >= 8) { // Aug-Dec
            $start = "$curYear-08-01";
            $end = ($curYear + 1) . "-07-31";
        } else { // Jan-July
            $start = ($curYear - 1) . "-08-01";
            $end = "$curYear-07-31";
        }

        // 1. Get Executed Hours
        $stmt = $this->pdo->prepare("SELECT SUM(duration_minutes)/60 FROM ad_activity_logs WHERE faculty_id = ? AND log_date BETWEEN ? AND ?");
        $stmt->execute([$facultyId, $start, $end]);
        $executed = $stmt->fetchColumn() ?: 0;

        // 2. Get Planned Hours
        $stmt = $this->pdo->prepare("SELECT SUM(planned_teaching_hrs + planned_research_hrs + planned_admin_hrs + planned_mentoring_hrs + planned_aav_hrs) FROM ad_workload_plans WHERE faculty_id = ? AND week_start_date BETWEEN ? AND ?");
        $stmt->execute([$facultyId, $start, $end]);
        $planned = $stmt->fetchColumn() ?: 1;

        $tui = $executed / $planned;
        return min($tui, 1.10); // Cap at 1.10
    }

    /**
     * Calculate Workload Fulfilment Ratio (WFR)
     * Formula: Tasks Completed / Tasks Assigned
     * Proxy: We compare 'Executed %' vs 'Target %' for each category.
     */
    /**
     * Calculate Workload Fulfilment Ratio (WFR)
     * Formula: Tasks Completed / Tasks Assigned
     * Proxy: We compare 'Executed %' vs 'Target %' for each category.
     * Supports Date Range (Optional)
     */
    public function calculateWFR($facultyId, $startDate = null, $endDate = null) {
        // Simplified for MVP: Check if they are meeting the Group Targets
        
        $targets = $this->getFacultyTargets($facultyId); // Group Targets %
        if (!$targets) return 0.75; // Default safety

        // Get Actual Distribution % from Logs (Filtered by Date if provided)
        $sql = "SELECT category, SUM(duration_minutes) as mins FROM ad_activity_logs WHERE faculty_id = ?";
        $params = [$facultyId];
        
        if ($startDate && $endDate) {
            $sql .= " AND log_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
        $sql .= " GROUP BY category";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $totalMins = array_sum($logs) ?: 1;

        $scoreSum = 0; $count = 0;
        $map = ['Teaching'=>'target_teaching', 'Research'=>'target_research', 'Admin'=>'target_admin', 'Training'=>'target_training', 'Mentoring'=>'target_mentoring'];

        foreach($map as $cat => $targetField) {
            $targetPct = $targets[$targetField] ?? 0;
            if ($targetPct > 0) {
                // If totalMins is small (e.g. < 5h), ratio is volatile. Maybe use absolute hrs?
                // For now, stick to Ratio of distribution.
                $actualPct = (($logs[$cat] ?? 0) / $totalMins) * 100;
                // Ratio: Actual / Target. Cap at 1.0 (Fulfilled).
                $ratio = ($actualPct / $targetPct);
                $scoreSum += min($ratio, 1.0);
                $count++;
            }
        }
        return ($count > 0) ? round($scoreSum / $count, 2) : 0;
    }

    /**
     * Academic Contribution Score (ACS) - Composite
     * Formula: 0.35(T) + 0.25(R) + 0.20(M) + 0.20(AAV)
     */
    public function calculateACS($facultyId) {
        // 1. Teaching (T): Student Feedback (normalized 0-1)
        $stmt = $this->pdo->prepare("SELECT AVG(avg_student_feedback) FROM ad_academic_source WHERE faculty_id = ?");
        $stmt->execute([$facultyId]);
        $feedback = $stmt->fetchColumn() ?: 0; // Default 0 if missing
        $scoreT = $feedback / 10;

        // 2. Research (R): Publications presence
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_research WHERE faculty_id = ?");
        $stmt->execute([$facultyId]);
        $countR = $stmt->fetchColumn();
        $scoreR = min($countR * 0.2, 1.0); // 5 papers = 1.0

        // 3. Mentoring (M): Logs presence
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ad_activity_logs WHERE faculty_id = ? AND category='Mentoring'");
        $stmt->execute([$facultyId]);
        $countM = $stmt->fetchColumn();
        $scoreM = min($countM * 0.1, 1.0); // 10 sessions = 1.0

        // 4. AAV: Manual Compliance (TODO: Link to a real compliance table)
        $scoreAAV = 0; // 0 if no records

        return ($scoreT * 0.35) + ($scoreR * 0.25) + ($scoreM * 0.20) + ($scoreAAV * 0.20);
    }

    /**
     * Calculate Teaching Effectiveness (%)
     * Based on student feedback and log consistency
     */
    public function calculateTeachingEff($facultyId) {
        $stmt = $this->pdo->prepare("SELECT AVG(avg_student_feedback) FROM ad_academic_source WHERE faculty_id = ?");
        $stmt->execute([$facultyId]);
        $feedback = $stmt->fetchColumn(); 
        if ($feedback === false || $feedback === null) return 0;
        return round($feedback * 10, 1);
    }

    /**
     * Get Average Weekly Hours for teaching/admin (NBA requirement)
     */
    public function getLoadAverages($facultyId) {
        $stmt = $this->pdo->prepare("SELECT category, SUM(duration_minutes)/60 as hrs FROM ad_activity_logs WHERE faculty_id = ? GROUP BY category");
        $stmt->execute([$facultyId]);
        $data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return [
            'teaching_avg' => round($data['Teaching'] ?? 0, 1),
            'admin_avg' => round($data['Admin'] ?? 0, 1)
        ];
    }
    
    /**
     * Role Responsibility Fulfilment (RRF)
     * Proxy: Admin Log volume vs Target
     */
    public function calculateRRF($facultyId) {
        // Simple proxy: Did they log Admin hours?
        $stmt = $this->pdo->prepare("SELECT SUM(duration_minutes) FROM ad_activity_logs WHERE faculty_id = ? AND category='Admin'");
        $stmt->execute([$facultyId]);
        $mins = $stmt->fetchColumn() ?: 0;
        return min(($mins / 60) / 2, 1.0); // Expecting at least 2h admin/week on avg? Scaled.
    }

    /**
     * Calculate FAEI with Role-Based Weights
     */
    public function calculateFAEI($facultyId) {
        $tui = $this->calculateTUI($facultyId); // 0-1.1
        $wfr = $this->calculateWFR($facultyId); // 0-1.0
        $acs = $this->calculateACS($facultyId); // 0-1.0
        $rrf = $this->calculateRRF($facultyId); // 0-1.0

        // Determine Role for Weights
        $userData = $this->getFacultyTargets($facultyId);
        $role = $userData['role'] ?? 'Faculty';
        
        // Default: Standard
        $w1=0.20; $w2=0.25; $w3=0.40; $w4=0.15;

        // Admin Roles (HoD, Dean, Reviewer) get different weights
        if ($role == 'Reviewer' || $role == 'Admin' || strpos($userData['full_name'], 'Dean') !== false) {
            $w1=0.20; $w2=0.20; $w3=0.30; $w4=0.30;
        }

        $faei = ($tui * $w1) + ($wfr * $w2) + ($acs * $w3) + ($rrf * $w4);
        
        // Save to DB
        $this->saveMetric($facultyId, 'TUI', $tui);
        $this->saveMetric($facultyId, 'WFR', $wfr);
        $this->saveMetric($facultyId, 'ACS', $acs);
        $this->saveMetric($facultyId, 'RRF', $rrf);
        $this->saveMetric($facultyId, 'FAEI', $faei);
        
        return round($faei, 2);
    }
    
    public function getInterpretation($faei) {
        if ($faei >= 0.85) return ['Highly Effective', '#27ae60'];
        if ($faei >= 0.70) return ['Effective', '#2980b9'];
        if ($faei >= 0.55) return ['Satisfactory', '#f39c12'];
        return ['Needs Support', '#e74c3c'];
    }
    
    private function saveMetric($facultyId, $key, $val) {
        $stmt = $this->pdo->prepare("
            INSERT INTO ad_appraisal_metrics (faculty_id, academic_year, metric_key, score_value)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE score_value = VALUES(score_value)
        ");
        $stmt->execute([$facultyId, $this->academicYear, $key, $val]);
    }
    /**
     * Get Weekly Progress History for the Academic Year
     * Returns: [[week_no, start_date, planned, executed], ...]
     */
    public function getYearlyProgress($facultyId) {
        $history = [];
        
        // 1. Determine Semester Start & Dates
        $curMonth = date('n');
        $curYear = date('Y');
        
        if ($curMonth >= 8) { // Aug-Dec (Odd Sem)
            $startYear = $curYear;
            $endYear = $curYear + 1;
            $semStart = "$curYear-08-01";
        } else { // Jan-July (Even Sem)
            $startYear = $curYear - 1;
            $endYear = $curYear;
            $semStart = "$curYear-01-01";
        }
        
        // Adjust start/end for DB queries (keep Academic Year scope)
        $dbStart = "$startYear-08-01";
        $dbEnd = "$endYear-07-31";
        
        // 2. Fetch Data
        // Plans
        $stmt = $this->pdo->prepare("
            SELECT week_start_date, 
                   (planned_teaching_hrs + planned_research_hrs + planned_admin_hrs + planned_mentoring_hrs + planned_aav_hrs) as planned_total,
                   status
            FROM ad_workload_plans 
            WHERE faculty_id = ? AND week_start_date BETWEEN ? AND ?
        ");
        $stmt->execute([$facultyId, $dbStart, $dbEnd]);
        $plans = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        
        // Logs
        $stmt = $this->pdo->prepare("
            SELECT DATE_SUB(log_date, INTERVAL WEEKDAY(log_date) DAY) as week_start,
                   SUM(duration_minutes)/60 as executed_total
            FROM ad_activity_logs
            WHERE faculty_id = ? AND log_date BETWEEN ? AND ?
            GROUP BY week_start
        ");
        $stmt->execute([$facultyId, $dbStart, $dbEnd]);
        $logs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // 3. Generate Weeks Loop
        // From Sem Start Date to Today's Week
        $startDt = new DateTime($semStart);
        $startDt->modify('monday this week'); // Align to Monday
        $today = new DateTime();
        $today->modify('sunday this week'); // Include current week
        
        $interval = new DateInterval('P1W');
        $period = new DatePeriod($startDt, $interval, $today);
        
        $weekCounter = 1;
        $tempHistory = [];
        
        foreach ($period as $dt) {
            $dateStr = $dt->format('Y-m-d');
            
            // Check if future? No, loop stops at today.
            
            $p = $plans[$dateStr] ?? ['planned_total' => 0, 'status' => 'Not Started'];
            $e = $logs[$dateStr] ?? 0; // Hours
            
            // If no plan & no log, and it's in the past -> 'Not Completed' or 'Missed'
            // If no plan & no log, and it's current week -> 'Pending'
            
            $status = $p['status'];
            if ($status == 'Not Started') {
                if ($e > 0) $status = 'Unplanned Activity'; 
                else $status = 'Pending';
            }

            $tempHistory[] = [
                'week_no' => $weekCounter++,
                'start_date' => $dateStr,
                'planned' => $p['planned_total'],
                'executed' => number_format($e, 1),
                'status' => $status
            ];
        }
        
        // Return reversed (Newest First)
        return array_reverse($tempHistory);
    }

}
?>
