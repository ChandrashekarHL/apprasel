<?php
require_once 'header.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    header("Location: login.php"); // or login page
    exit;
}

$user = getCurrentUser($pdo);
$today = date('Y-m-d');
?>


<?php
// Include AI Helper
require_once 'ai_helper.php';

// Check for missing submissions
$alerts = [];
$completed_sections = 0;

// Get Status Report from AI Helper
$ai_status = checkAppraisalStatus($pdo, $user['id'], getAcademicYear());

$sections = [
    'ad_appraisal_research' => 'Research', 
    'ad_appraisal_training' => 'Training', 
    'ad_appraisal_consultancy' => 'Consultancy',
    'ad_administration' => 'Administration'
]; 
$total_sections = count($sections);
$section_counts = [];

// Fallback counts for the insights grid (still useful)
foreach ($sections as $table => $name) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE faculty_id = ? AND academic_year = ?");
        $stmt->execute([$user['id'], getAcademicYear()]);
        $count = $stmt->fetchColumn();
    } catch (Exception $e) { $count = 0; }
    
    if ($count > 0) $completed_sections++; // Simple count check for progress bar
    $section_counts[$name] = $count;
}

$completion_percentage = ($total_sections > 0) ? ($completed_sections / $total_sections) * 100 : 0;
?>

<div class="dashboard-header">
    <h2>Dashboard</h2>
    <p class="subtitle">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>. Here is your appraisal overview.</p>
</div>

// Progress Widget
<?php 
$isFacultyRole = isset($_SESSION['role']) && stripos($_SESSION['role'], 'Faculty') !== false;
if($isFacultyRole): 
    require_once 'WorkloadEngine.php';
    $wEngine = new WorkloadEngine($pdo);
    
    // 1. Get Group Info
    $targets = $wEngine->getFacultyTargets($user['id']);
    $groupCode = $targets['group_code'] ?? 'NA';
    $groupName = $targets['group_name'] ?? 'General Faculty';
    
    // Performance Metrics for AI Proactivity
    require_once 'faculty_performance_analyzer.php';
    $analyzer = new FacultyPerformanceAnalyzer($pdo);
    $currentSummary = $analyzer->getAllFacultyPerformance('name', null, $user['department']);
    
    // Find self in results
    $myMetrics = ['faei' => 0, 'trend' => 'Stable'];
    foreach ($currentSummary as $fData) {
        if ($fData['id'] == $user['id']) {
            $myMetrics = $fData;
            break;
        }
    }
    
    $currentFAEI = $myMetrics['faei'];
    $currentTrend = $myMetrics['trend'];

    // 2. Get Weekly Progress (Planned vs Executed)
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $plan = $wEngine->getWeeklyPlan($user['id'], $weekStart);
    
    // Get actual logged hours for this week
    $logStmt = $pdo->prepare("SELECT SUM(duration_minutes)/60 FROM ad_activity_logs WHERE faculty_id = ? AND log_date BETWEEN ? AND ?");
    $logStmt->execute([$user['id'], $weekStart, date('Y-m-d', strtotime('sunday this week'))]);
    $executedHrs = number_format($logStmt->fetchColumn() ?: 0, 1);
    
    $plannedHrs = 0;
    if ($plan) {
         $plannedHrs = $plan['planned_teaching_hrs'] + $plan['planned_research_hrs'] + $plan['planned_admin_hrs'] + $plan['planned_mentoring_hrs'] + $plan['planned_aav_hrs'];
    }
    $progressPct = ($plannedHrs > 0) ? min(($executedHrs / $plannedHrs) * 100, 100) : 0;

    // Get History for Toggle
    $history = $wEngine->getYearlyProgress($user['id']);

    // Today's Logs for AI Context
    $todayLogsStmt = $pdo->prepare("SELECT COUNT(*) FROM ad_activity_logs WHERE faculty_id = ? AND log_date = CURDATE()");
    $todayLogsStmt->execute([$user['id']]);
    $todayLogsCount = $todayLogsStmt->fetchColumn();

    // AI Notifications (Feed)
    $notifStmt = $pdo->prepare("SELECT * FROM ad_ai_notifications WHERE faculty_id = ? AND status = 'unread' ORDER BY created_at DESC LIMIT 5");
    $notifStmt->execute([$user['id']]);
    $aiNotifications = $notifStmt->fetchAll();
?>

<!-- Workload Management Section -->
<div style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding: 25px; border-radius: 12px; color: white; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); position: relative;">
    
    <!-- Toggle Button (Plus Icon) -->
    <button onclick="toggleHistory()" id="histBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; color: white; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s;">
        <i class="fas fa-plus"></i>
    </button>

    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-right: 40px;">
        <div>
            <div style="background: rgba(255,255,255,0.2); display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; margin-bottom: 10px;">
                <i class="fas fa-tag"></i> <strong>Group <?php echo $groupCode; ?></strong>: <?php echo htmlspecialchars($groupName); ?>
            </div>
            <h3 style="margin: 0; color: white;">Weekly Workload Tracker</h3>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">Week of <?php echo date('M d', strtotime($weekStart)); ?></p>
        </div>
        
        <div style="text-align: right;">
            <div style="font-size: 2em; font-weight: bold; line-height: 1;"><?php echo $executedHrs; ?> <span style="font-size: 0.5em; opacity: 0.7;">/ <?php echo $plannedHrs; ?> hrs</span></div>
            <div style="font-size: 0.9em; opacity: 0.8;">Logged vs Planned</div>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div style="background: rgba(255,255,255,0.1); height: 8px; border-radius: 4px; margin: 20px 0; overflow: hidden;">
        <div style="background: #00d2ff; height: 100%; width: <?php echo $progressPct; ?>%; transition: width 0.5s;"></div>
    </div>
    
    <div style="display: flex; gap: 10px; margin-top: 5px;">
        <a href="workload_plan.php" class="btn btn-sm btn-secondary" style="background: white; color: #1e3c72; border: none;">
            <i class="fas fa-calendar-alt"></i> <?php echo $plan ? 'Edit Plan' : 'Create Plan'; ?>
        </a>
        <a href="activity_log.php" class="btn btn-sm btn-secondary" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
            <i class="fas fa-pen-nib"></i> Log Activity
        </a>
        <a href="appraisal_results.php" class="btn btn-sm btn-secondary" style="background: #f1c40f; color: #2c3e50; font-weight: bold; border: none;">
            <i class="fas fa-chart-line"></i> Analytics
        </a>
    </div>

    <!-- Hidden History Section -->
    <div id="historySection" style="display: none; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; animation: fadeIn 0.5s;">
        <h4 style="font-size: 0.9em; margin-bottom: 10px; opacity: 0.8;">Previous Weeks History</h4>
        <div style="display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.3) transparent;">
            <?php foreach(array_slice($history, 0, 10) as $week): 
                $executedVal = floatval(str_replace(',', '', $week['executed']));
                $plannedVal = floatval(str_replace(',', '', $week['planned']));
                
                // Default: Pending
                $statusColor = '#f39c12'; // Orange for Pending
                $statusIcon = 'fa-clock';
                $statusText = 'Pending';
                
                // 1. Admin Status Overrides (If Draft or Rejected)
                if ($week['status'] == 'Draft') {
                    $statusColor = '#95a5a6'; // Gray
                    $statusIcon = 'fa-pen';
                    $statusText = 'Draft';
                } elseif ($week['status'] == 'Rejected') {
                    $statusColor = '#e74c3c'; // Red
                    $statusIcon = 'fa-times-circle';
                    $statusText = 'Rejected';
                } else {
                    // 2. Execution Status (For Active Plans)
                    if ($executedVal > 0) {
                        if ($plannedVal > 0 && $executedVal >= $plannedVal) {
                            $statusColor = '#2ecc71'; // Green
                            $statusIcon = 'fa-check-circle';
                            $statusText = 'Completed';
                        } else {
                            $statusColor = '#3498db'; // Blue
                            $statusIcon = 'fa-spinner fa-spin'; // Animated
                            $statusText = 'In Progress';
                        }
                    }
                }
            ?>
            <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px; min-width: 120px; text-align: center; flex-shrink: 0; position: relative; border: 1px solid rgba(255,255,255,0.05);">
                <div style="font-size: 0.7em; opacity: 0.7; margin-bottom: 5px;">Week <?php echo $week['week_no']; ?> <br> <?php echo date('M d', strtotime($week['start_date'])); ?></div>
                
                <!-- Status Badge -->
                <div style="background: <?php echo $statusColor; ?>; color: white; font-size: 0.65em; padding: 2px 6px; border-radius: 10px; display: inline-block; margin-bottom: 5px;">
                    <i class="fas <?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?>
                </div>
                
                <div style="font-weight: bold; font-size: 1.1em; margin: 2px 0;"><?php echo $week['executed']; ?>h</div>
                <div style="font-size: 0.7em; opacity: 0.7;">Target: <?php echo $week['planned']; ?>h</div>
                
                <!-- Mini Bar -->
                <div style="background: rgba(255,255,255,0.1); height: 4px; border-radius: 2px; margin-top: 5px;">
                    <?php $miniPct = ($week['planned'] > 0) ? ($week['executed']/$week['planned'])*100 : 0; ?>
                    <div style="background: <?php echo ($miniPct >= 100) ? '#2ecc71' : '#f1c40f'; ?>; height: 100%; width: <?php echo min($miniPct, 100); ?>%;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function toggleHistory() {
    const section = document.getElementById('historySection');
    const btn = document.getElementById('histBtn');
    
    if (section.style.display === 'none') {
        section.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-minus"></i>';
        btn.style.background = 'rgba(255,255,255,0.4)';
    } else {
        section.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-plus"></i>';
        btn.style.background = 'rgba(255,255,255,0.2)';
    }
}
</script>

<!-- Progress Widget -->
<?php
    // Calculate WEIGHTED Appraisal Completion based on Group
    // "Take from the section like research, consultancy... based on the group"
    
    // 1. Fetch Real Targets from Database via WorkloadEngine
    require_once 'WorkloadEngine.php';
    $engine = new WorkloadEngine($pdo);
    $targetsDB = $engine->getFacultyTargets($user['id']);
    
    // Map DB columns to our Dashboard Keys
    // DB columns: target_teaching, target_research, target_admin, target_training, target_mentoring, target_aav
    $w = [
        'Teaching' => $targetsDB['target_teaching'] ?? 0,
        'Research' => $targetsDB['target_research'] ?? 0,
        'Administration' => $targetsDB['target_admin'] ?? 0,
        'Training' => $targetsDB['target_training'] ?? 0,
        'Mentoring' => $targetsDB['target_mentoring'] ?? 0,
        'Consultancy' => 0 // Consultancy is usually ad-hoc or part of Research in some models, defaulting to 0 if not in DB group table
    ];
    
    // Fallback if DB is empty (prevent AI errors)
    if (array_sum($w) == 0) {
        $w = ['Teaching' => 16, 'Research' => 10, 'Administration' => 4, 'Mentoring' => 5, 'Training' => 5, 'Consultancy' => 0];
    }
    
    // 2. Calculate Score
    $currentScore = 0;
    $maxScore = 100;
    
    // Check Research
    if (($section_counts['Research'] ?? 0) > 0) $currentScore += $w['Research'];
    // Check Training
    if (($section_counts['Training'] ?? 0) > 0) $currentScore += $w['Training'];
    // Check Consultancy
    if (($section_counts['Consultancy'] ?? 0) > 0) $currentScore += $w['Consultancy'];
    // Check Administration
    if (($section_counts['Administration'] ?? 0) > 0) $currentScore += $w['Administration'];
    
    // 3. Colors & Text
    $pColor = '#e74c3c';
    if ($currentScore >= 90) $pColor = '#2ecc71';
    else if ($currentScore >= 50) $pColor = '#f1c40f'; // Yellow
    else if ($currentScore >= 20) $pColor = '#e67e22'; // Orange
    
    // Explanation Text
    $focusText = "Balanced";
    if ($groupCode == 'A') $focusText = "Teaching/Training Focus";
    if ($groupCode == 'C') $focusText = "Research Focus";
?>

<div class="progress-widget" style="margin-top: 25px;">
    <div class="progress-circle" style="--percent: <?php echo $currentScore; ?>; --color: <?php echo $pColor; ?>;">
        <span><?php echo $currentScore; ?>%</span>
    </div>
    <div class="progress-info">
        <h3>Appraisal Progress</h3>
        <p>Your data entry progress is <strong><?php echo $currentScore; ?>%</strong> based on <strong>Group <?php echo $groupCode; ?></strong> priorities.</p>
        <small style="color: #7f8c8d; font-size: 0.8em;">
            Weights: 
            Research (<?php echo $w['Research']; ?>%), 
            Training (<?php echo $w['Training']; ?>%), 
            Admin (<?php echo $w['Administration']; ?>%), 
            Consultancy (<?php echo $w['Consultancy']; ?>%)
        </small>
    </div>
</div>

<!-- NEW: Mallika AI Insights (Notification Center) -->
<div class="data-card" style="margin-top: 25px; border-left: 5px solid #f39c12; background: #fffcf8; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(243, 156, 18, 0.1);">
     <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0; font-size: 1.2em; color: #2c3e50;"><i class="fas fa-robot"></i> Mallika's AI Recommendations</h3>
        <span style="font-size: 0.75em; background: #fdf6ec; color: #f39c12; padding: 3px 10px; border-radius: 12px; font-weight: bold;">Agentic Insights</span>
    </div>
    <style>
        @keyframes pulseSentinel {
            0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(231, 76, 60, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }
    </style>
    <div id="ai-notif-feed">
        <?php if (empty($aiNotifications)): ?>
            <p style="color: #95a5a6; font-size: 0.95em; font-style: italic; margin-left: 10px;">Mallika is currently analyzing your performance. No immediate alerts.</p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                <?php foreach ($aiNotifications as $n): ?>
                    <?php 
                        $isSentinel = strpos($n['message'], 'Mallika (Sentinel):') !== false; 
                        $cardBg = $isSentinel ? '#fff5f5' : 'white';
                        $cardBorder = $isSentinel ? '2px solid #e74c3c' : '1px solid #f9e3c5';
                        $cardShadow = $isSentinel ? 'box-shadow: 0 4px 15px rgba(231,76,60,0.2); animation: pulseSentinel 2s infinite;' : '';
                        $textColor = $isSentinel ? '#c0392b' : '#2c3e50';
                    ?>
                    <div style="background: <?php echo $cardBg; ?>; border: <?php echo $cardBorder; ?>; border-radius: 8px; padding: 12px; display: flex; gap: 15px; align-items: center; <?php echo $cardShadow; ?>">
                        <div style="font-size: 1.3em;">
                            <?php if($isSentinel): ?><i class="fas fa-shield-alt" style="color:#e74c3c"></i>
                            <?php elseif($n['type'] === 'praise'): ?><i class="fas fa-star" style="color:#f1c40f"></i>
                            <?php elseif($n['type'] === 'warning' || $n['type'] === 'dar_reminder' || $n['type'] === 'escalation'): ?><i class="fas fa-exclamation-triangle" style="color:#e67e22"></i>
                            <?php else: ?><i class="fas fa-lightbulb" style="color:#3498db"></i><?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="color: <?php echo $textColor; ?>; font-weight: <?php echo $isSentinel ? '700' : '500'; ?>; font-size: 0.95em;">
                                <?php if($isSentinel): ?>
                                    <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; margin-right: 8px; display: inline-block; animation: blinkBadge 1.5s infinite;">OVERSIGHT ACTIVE</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars(str_replace('Mallika (Sentinel): ', '', $n['message'])); ?>
                            </div>
                            <div style="font-size: 0.75em; color: <?php echo $isSentinel ? '#e74c3c' : '#95a5a6'; ?>; font-weight: <?php echo $isSentinel ? '600' : 'normal'; ?>; margin-top: 5px;"><?php echo date('M d, h:i A', strtotime($n['created_at'])); ?></div>
                        </div>
                        <button onclick="toggleChat()" style="background: <?php echo $isSentinel ? '#e74c3c' : 'transparent'; ?>; border: 1px solid <?php echo $isSentinel ? '#c0392b' : '#ddd'; ?>; padding: 6px 12px; border-radius: 4px; font-size: 0.85em; color: <?php echo $isSentinel ? 'white' : '#7f8c8d'; ?>; cursor: pointer; font-weight: <?php echo $isSentinel ? 'bold' : 'normal'; ?>; transition: 0.2s;">
                            <?php echo $isSentinel ? '<i class="fas fa-comment-dots"></i> Resolve' : 'Discuss'; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Dynamic Insight Cards (Re-ordered based on Group) -->
<?php
    // Prioritize cards based on Group
    $order = ['Research', 'Training', 'Consultancy', 'Administration']; // Default Order
    if ($groupCode == 'A') $order = ['Training', 'Research', 'Administration', 'Consultancy']; // Teaching Focus
    if ($groupCode == 'C') $order = ['Research', 'Consultancy', 'Training', 'Administration']; // Research Focus
    if ($groupCode == 'D') $order = ['Administration', 'Training', 'Research', 'Consultancy']; // Admin Focus
    
    $icons = [
        'Research' => 'fas fa-flask',
        'Training' => 'fas fa-chalkboard-teacher',
        'Consultancy' => 'fas fa-handshake',
        'Administration' => 'fas fa-tasks'
    ];
    $colors = [
        'Research' => '#e74c3c',
        'Training' => '#3498db',
        'Consultancy' => '#2ecc71',
        'Administration' => '#9b59b6'
    ];
?>

<div class="insights-grid">
    <?php foreach($order as $key): ?>
    <div class="insight-card" style="border-top: 4px solid <?php echo $colors[$key]; ?>">
        <span class="count" style="color: <?php echo $colors[$key]; ?>"><?php echo $section_counts[$key] ?? 0; ?></span>
        <span class="label"><i class="<?php echo $icons[$key]; ?>"></i> <?php echo $key; ?> Records</span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Notifications -->
<?php if (!empty($alerts)): ?>
<div class="notification-area">
    <h3><i class="fas fa-bell"></i> Notifications</h3>
    <?php foreach ($alerts as $alert): ?>
        <div class="notification-item">
            <i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>
            <span><?php echo $alert; ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="notification-area">
    <h3><i class="fas fa-bell"></i> Notifications</h3>
    <div class="notification-item success">
        <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
        <span>All systems go! No pending alerts.</span>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
// -------------------------------------------------------
// AGENTIC OVERSIGHT BANNER — Show if faculty is under sentinel watch
// -------------------------------------------------------
$oversightMsg = null;
if (isset($isRolesFaculty) && $isRolesFaculty) {
    try {
        $ovStmt = $pdo->prepare("SELECT message FROM ad_agentic_oversight WHERE faculty_id = ? AND status = 'active' LIMIT 1");
        $ovStmt->execute([$user['id']]);
        $oversightRow = $ovStmt->fetch(PDO::FETCH_ASSOC);
        if ($oversightRow) {
            $oversightMsg = $oversightRow['message'];
        }
    } catch (Exception $e) { /* Table might not exist */ }
}
?>

<?php if ($oversightMsg): ?>
<style>
#oversight-sentinel-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 100000; /* Higher than DAR nudge */
    background: linear-gradient(90deg, #8e44ad 0%, #9b59b6 50%, #8e44ad 100%);
    background-size: 200% 100%;
    animation: oversightSlide 0.5s ease-out, oversightPulse 4s ease-in-out infinite;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    height: 60px;
    box-shadow: 0 4px 25px rgba(142, 68, 173, 0.6);
    border-bottom: 2px solid #f1c40f;
}
@keyframes oversightSlide {
    from { transform: translateY(-100%); }
    to   { transform: translateY(0); }
}
@keyframes oversightPulse {
    0%, 100% { background-position: 0% 50%; opacity: 1; }
    50% { background-position: 100% 50%; opacity: 0.95; }
}
.sentinel-icon {
    font-size: 1.4em;
    margin-right: 12px;
    color: #f1c40f;
    text-shadow: 0 0 10px rgba(241, 196, 15, 0.5);
}
</style>
<div id="oversight-sentinel-bar">
    <div style="display:flex; align-items:center;">
        <i class="fas fa-robot sentinel-icon"></i>
        <div>
            <strong style="letter-spacing: 1px; font-size: 0.9em;">MALLIKA SENTINEL OVERSIGHT ACTIVE</strong>
            <div style="font-size: 0.8em; opacity: 0.9; margin-top: 2px;">
                <?php echo htmlspecialchars($oversightMsg); ?>
            </div>
        </div>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <span style="font-size: 0.7em; background: rgba(0,0,0,0.2); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1);">Monitoring Performance</span>
        <button class="dar-dismiss" onclick="document.getElementById('oversight-sentinel-bar').remove();" style="background:none; border:none; color:white; cursor:pointer; font-size:1.2em;">✕</button>
    </div>
</div>
<script>document.body.style.paddingTop = '60px';</script>
<?php endif; ?>

<?php
// -------------------------------------------------------
// DAR NUDGE BANNER — Show if faculty hasn't filled DAR today
// Runs for ALL Faculty users (ERP or local login)
// -------------------------------------------------------
$darNudgeRow   = false;  // default: banner hidden
$showDarNudge  = false;

// Only check for Faculty role (case-insensitive to handle any casing)
$isRolesFaculty = isset($user['role']) && stripos($user['role'], 'Admin') === false
                  && stripos($user['role'], 'Reviewer') === false;

if ($isRolesFaculty) {
    // Get EMP_ID: try erp_profile first, then user emp_id column, then username
    $dar_emp_id_check = $_SESSION['erp_profile']['ID']
        ?? ($user['emp_id'] ?? null)
        ?? ($user['username'] ?? null);

    $dar_today = date('Y-m-d');

    if ($dar_emp_id_check) {
        try {
            $darNudgeStmt = $pdo->prepare("SELECT SL_NO FROM ac_dar WHERE EMP_ID = ? AND DATE = ? LIMIT 1");
            $darNudgeStmt->execute([$dar_emp_id_check, $dar_today]);
            $darNudgeRow = $darNudgeStmt->fetch(PDO::FETCH_ASSOC);
            $showDarNudge = !$darNudgeRow;   // show banner when NO row found
        } catch (Exception $e) {
            // ac_dar table may not exist yet — silently skip
            $showDarNudge = false;
        }
    }
}
?>

<?php if ($showDarNudge): ?>

<style>
/* DAR sticky alert bar */
#dar-nudge-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 99999;
    background: linear-gradient(90deg, #c0392b 0%, #e74c3c 50%, #c0392b 100%);
    background-size: 200% 100%;
    animation: darSlide 0.4s ease-out, darPulse 3s ease-in-out infinite;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    height: 56px;
    box-shadow: 0 4px 20px rgba(192, 57, 43, 0.5);
    font-family: inherit;
}
@keyframes darSlide {
    from { transform: translateY(-100%); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
@keyframes darPulse {
    0%,100% { background-position: 0% 50%;   box-shadow: 0 4px 20px rgba(192,57,43,0.5); }
    50%      { background-position: 100% 50%; box-shadow: 0 4px 30px rgba(231,76,60,0.8); }
}
@keyframes bellRing {
    0%,100% { transform: rotate(0deg); }
    10%,30% { transform: rotate(-15deg); }
    20%,40% { transform: rotate(15deg); }
    50%      { transform: rotate(0deg); }
}
.dar-bell { display: inline-block; animation: bellRing 2.5s ease infinite; font-size: 1.3em; }
.dar-blink {
    display: inline-block;
    background: white; color: #c0392b;
    font-size: 0.65em; font-weight: 800; padding: 2px 7px;
    border-radius: 10px; margin-left: 8px; vertical-align: middle;
    animation: blinkBadge 1s step-start infinite;
}
@keyframes blinkBadge {
    0%,100% { opacity: 1; } 50% { opacity: 0; }
}
.dar-cta {
    background: white !important;
    color: #c0392b !important;
    padding: 7px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.88em;
    white-space: nowrap;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.dar-cta:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
.dar-dismiss {
    background: rgba(255,255,255,0.2);
    border: none; color: white;
    width: 28px; height: 28px; border-radius: 50%;
    cursor: pointer; font-size: 1em; line-height: 1;
    display: flex; align-items: center; justify-content: center;
    margin-left: 10px; transition: background 0.2s;
    flex-shrink: 0;
}
.dar-dismiss:hover { background: rgba(255,255,255,0.4); }
/* Push page content so it's not hidden behind the fixed bar */
body.dar-active { padding-top: 56px !important; }
</style>

<div id="dar-nudge-bar">
    <div style="display:flex; align-items:center; gap:12px; overflow:hidden;">
        <span class="dar-bell">🔔</span>
        <div style="overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">
            <strong style="font-size:0.95em;">DAR Pending for Today!</strong>
            <span class="dar-blink">ACTION REQUIRED</span>
            <span style="font-size:0.85em; opacity:0.9; margin-left:10px;">
                You're present today but haven't filled your Daily Activity Register. HOD will be alerted.
            </span>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
        <a href="activity_log.php" class="dar-cta">📝 Fill DAR Now →</a>
        <button class="dar-dismiss"
            onclick="document.getElementById('dar-nudge-bar').remove(); document.body.classList.remove('dar-active');"
            title="Dismiss">✕</button>
    </div>
</div>

<script>
    // Push content down so bar doesn't cover the top of the page
    document.body.classList.add('dar-active');
</script>

<?php endif; ?>



<!-- Reviewer Only View -->
<?php if($_SESSION['role'] == 'Reviewer'): ?>
    <div class="alert alert-success">
        <h3>Reviewer Dashboard</h3>
        <p>Please access the <a href="reviewer_dashboard.php">Reviewer Panel</a> or the <a href="hod_dashboard.php" style="font-weight: bold;">Department Workload Monitor</a>.</p>
    </div>
<?php endif; ?>

<!-- AI Supervisor Logic (Persistent "Mini GPT" Widget) -->
<?php 
if ($_SESSION['role'] == 'Faculty') {
    // Ensure daily row exists
    $today = date('Y-m-d');
    $supCheck = $pdo->prepare("SELECT * FROM ad_daily_ai_activity WHERE faculty_id = ? AND activity_date = ?");
    $supCheck->execute([$user['id'], $today]);
    $dailyRow = $supCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$dailyRow) {
        $ins = $pdo->prepare("INSERT INTO ad_daily_ai_activity (faculty_id, activity_date, activity_text, status, briefing_seen) VALUES (?, ?, 'Daily Check-in', 'Assigned', 0)");
        $ins->execute([$user['id'], $today]);
        $dailyRow = ['status' => 'Assigned', 'interaction_log' => null, 'briefing_html' => null, 'briefing_seen' => 0];
    } else {
        // Self-Healing: If row exists but seen is NULL, fix it to 0
        if (!isset($dailyRow['briefing_seen'])) { // Column might be missing from fetch if added later, or value is NULL
            // Check if column exists physically (we assume yes from previous steps)
            // Just update it
            try {
                 $fix = $pdo->prepare("UPDATE ad_daily_ai_activity SET briefing_seen = 0 WHERE id = ?");
                 $fix->execute([$dailyRow['id']]);
                 $dailyRow['briefing_seen'] = 0; // Fix in memory
            } catch (Exception $e) { /* Column might not exist yet, ignore */ }
        }
    }
    
    $isPending = ($dailyRow['status'] === 'Assigned' || $dailyRow['status'] === 'Missed');
    
    // Instead of forcing open in PHP, set a session flag for JavaScript to handle with delay
    if ($isPending || isset($_GET['test_supervisor'])) {
        echo "<script>sessionStorage.setItem('shouldShowMallika', 'true');</script>";
    }
    
    // Safe Missed Count Calc
    try {
        $mcStmt = $pdo->prepare("SELECT COUNT(*) FROM ad_daily_ai_activity WHERE faculty_id = ? AND status = 'Missed'");
        $mcStmt->execute([$user['id']]);
        $missedCount = $mcStmt->fetchColumn() ?: 0;
    } catch (Exception $e) { $missedCount = 0; }
    
    // Check Activity Status for Each Section (for AI context)
    $activityStatus = [
        'research' => 0,
        'training' => 0,
        'consultancy' => 0,
        'administration' => 0
    ];
    
    try {
        // Check Research Activity
        $resStmt = $pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_research WHERE faculty_id = ?");
        $resStmt->execute([$user['id']]);
        $activityStatus['research'] = $resStmt->fetchColumn() ?: 0;
        
        // Check Training Activity
        $trainStmt = $pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_training WHERE faculty_id = ?");
        $trainStmt->execute([$user['id']]);
        $activityStatus['training'] = $trainStmt->fetchColumn() ?: 0;
        
        // Check Consultancy Activity
        $consStmt = $pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_consultancy WHERE faculty_id = ?");
        $consStmt->execute([$user['id']]);
        $activityStatus['consultancy'] = $consStmt->fetchColumn() ?: 0;
        
        // Check Administration Activity
        $adminStmt = $pdo->prepare("SELECT COUNT(*) FROM ad_administration WHERE faculty_id = ?");
        $adminStmt->execute([$user['id']]);
        $activityStatus['administration'] = $adminStmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        // Keep defaults at 0 if error
    }
    
    // User is "new" if they have zero entries in ALL sections
    $totalActivity = array_sum($activityStatus);
    $isNewUser = ($totalActivity === 0);

    // RESTORED: Fetch Timetable for Today
    $dayName = date('l'); // e.g. Monday
    $schedStmt = $pdo->prepare("SELECT * FROM ad_faculty_timetable WHERE faculty_id = ? AND day_of_week = ? ORDER BY start_time");
    $schedStmt->execute([$user['id'], $dayName]);
    $scheduleRows = $schedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $scheduleStr = "";
    if ($scheduleRows) {
        foreach ($scheduleRows as $r) {
            $scheduleStr .= "• " . date('H:i', strtotime($r['start_time'])) . " - " . $r['course_name'] . " (" . $r['room_no'] . ")\n";
        }
    } else {
        $scheduleStr = "No fixed classes scheduled for today.";
    }
    
    // Ensure GroupCode is set
    if (!isset($groupCode)) $groupCode = 'General'; 
?>

<!-- PERSISTENT TASK BOARD (If Briefing Exists) -->
<?php if (!empty($dailyRow['briefing_html'])): ?>
<div class="task-board-widget" style="background: linear-gradient(to right, #ffffff, #f9f9f9); border-radius: 12px; padding: 25px; margin-top: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e0e0e0; position: relative; overflow: hidden;">
    <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #2c3e50;"></div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #2c3e50; font-size: 1.4em;"><i class="fas fa-clipboard-check" style="margin-right: 10px; color: #e67e22;"></i>Today's Assigned Board</h2>
        <span style="background: #e67e22; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold;">Action Required</span>
    </div>
    <div class="briefing-content-container">
        <?php echo $dailyRow['briefing_html']; ?>
    </div>
    
    <div style="margin-top: 15px; text-align: right; border-top: 1px solid #eee; padding-top: 15px;">
        <a href="activity_log.php" class="btn btn-primary" style="background: #3498db; border: none; padding: 10px 25px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            Continue Activity <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- 1. Daily Briefing Modal (Separate from Chat) -->
<div id="briefingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:11000; justify-content:center; align-items:center;"> <!-- Higher z-index -->
    <div style="background:white; width:600px; max-width:90%; border-radius:15px; overflow:hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5); animation: slideIn 0.3s ease-out;">
        <div style="background: linear-gradient(135deg, #2c3e50, #3498db); padding: 25px; color: white; display: flex; align-items: center; gap: 20px;">
            <div style="background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px;">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 1.5em;">Daily Executive Briefing</h2>
                <p style="margin: 5px 0 0; opacity: 0.9; font-size: 0.9em;"><?php echo date('l, F jS Y'); ?></p>
            </div>
        </div>
        
        <div id="briefingContent" style="padding: 30px; line-height: 1.6; color: #34495e; font-size: 1.05em; max-height: 400px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px; color: #999;">
                <i class="fas fa-circle-notch fa-spin fa-2x"></i><br><br>
                Generating your personalized schedule and targets...
            </div>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; text-align: right; border-top: 1px solid #eee;">
            <button onclick="acceptBriefing()" id="btnAccept" disabled style="background: #27ae60; color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 1em; cursor: pointer; opacity: 0.5;">
                I Acknowledge & Commit <i class="fas fa-check"></i>
            </button>
        </div>
    </div>
</div>

<script>
    // Briefing Logic
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Checking Briefing Status...");
        // Check HTML content presence to verify completeness
        const briefingExists = <?php echo !empty($dailyRow['briefing_html']) ? 1 : 0; ?>;
        const briefingSeen = <?php echo $dailyRow['briefing_seen'] ?? 0; ?>;
        
        console.log("Briefing Exists (DB HTML):", briefingExists);
        console.log("Briefing Seen (DB Flag):", briefingSeen);
        
        // Force test param
        const urlParams = new URLSearchParams(window.location.search);
        const forceTest = urlParams.has('test_supervisor');

        // Only show modal if NOT exists AND NOT seen 
        // (If exists in DB, we show the persistent board instead)
        if ((!briefingExists && !briefingSeen) || forceTest) {
            console.log("Triggering Briefing Modal... Condition Met.");
            const modal = document.getElementById('briefingModal');
            if(modal) {
                modal.style.display = 'flex';
                generateBriefing();
            }
        }
        
        // Handle pending tasks (shouldShowMallika) - WITH 2-SECOND DELAY
        if (sessionStorage.getItem('shouldShowMallika') === 'true') {
            sessionStorage.removeItem('shouldShowMallika');
            
            setTimeout(() => {
                const chat = document.getElementById('mallika-chat');
                if (chat && chat.style.display === 'none') {
                    const area = document.getElementById('chat-messages');
                    if (area && area.innerHTML.trim() === '') {
                        loadChat(currentDate); // Load existing chat if any
                    }
                    
                    chat.style.opacity = '0';
                    chat.style.display = 'flex';
                    setTimeout(() => {
                        chat.style.transition = 'opacity 0.3s ease';
                        chat.style.opacity = '1';
                    }, 50);
                }
            }, 2000); // 2 second delay for demo
        }
        
        // Auto-trigger Mallika chat after page reload (from briefing acknowledgment)
        // WITH 2-SECOND DELAY FOR DEMO
        if (sessionStorage.getItem('mallikaAutoTrigger') === 'true') {
            sessionStorage.removeItem('mallikaAutoTrigger'); // Clear flag
            
            setTimeout(() => {
                const chat = document.getElementById('mallika-chat');
                if (chat) {
                    // Add welcoming message first
                    const area = document.getElementById('chat-messages');
                    if (area) {
                        area.innerHTML = '';
                        renderMessage('ai', "Great! You've acknowledged today's objectives. I'm here to help you track your progress throughout the day. How would you like to start?");
                    }
                    
                    // Now show chat with smooth animation
                    chat.style.opacity = '0';
                    chat.style.display = 'flex';
                    
                    // Trigger animation
                    setTimeout(() => {
                        chat.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        chat.style.opacity = '1';
                        chat.style.transform = 'translateX(0)';
                    }, 50);
                }
            }, 2000); // 2 second delay for demo
        }
        
        // Auto-trigger Mallika if sections are not filled (ONLY if no briefing trigger)
        // WITH 2-SECOND DELAY FOR DEMO
        const activityStatus = <?php echo json_encode($activityStatus); ?>;
        const hasEmptySections = activityStatus && (
            activityStatus.research === 0 || 
            activityStatus.training === 0 || 
            activityStatus.consultancy === 0 || 
            activityStatus.administration === 0
        );
        
        // Only trigger if not already triggered by briefing AND has empty sections
        if (!sessionStorage.getItem('mallikaAutoTrigger') && hasEmptySections) {
            // Check if we already showed this today
            const today = new Date().toDateString();
            const lastShown = localStorage.getItem('mallikaSectionPrompt');
            
            if (lastShown !== today) {
                setTimeout(() => {
                    const chat = document.getElementById('mallika-chat');
                    if (chat && chat.style.display === 'none') {
                        // Prepare message about empty sections
                        let emptySections = [];
                        if (activityStatus.research === 0) emptySections.push('Research');
                        if (activityStatus.training === 0) emptySections.push('Training');
                        if (activityStatus.consultancy === 0) emptySections.push('Consultancy');
                        if (activityStatus.administration === 0) emptySections.push('Administration');
                        
                        const message = `Hello! I noticed you haven't filled the following sections yet: <strong>${emptySections.join(', ')}</strong>. Would you like me to help you get started with these areas?`;
                        
                        const area = document.getElementById('chat-messages');
                        if (area) {
                            area.innerHTML = '';
                            renderMessage('ai', message);
                        }
                        
                        // Show chat with animation
                        chat.style.opacity = '0';
                        chat.style.display = 'flex';
                        
                        setTimeout(() => {
                            chat.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            chat.style.opacity = '1';
                            chat.style.transform = 'translateX(0)';
                        }, 50);
                        
                        // Mark as shown today
                        localStorage.setItem('mallikaSectionPrompt', today);
                    }
                }, 2000); // 2 second delay for demo
            }
        }
        
        // IDLE DETECTION: Auto-trigger after 2 seconds of inactivity
        // (Separate from page load triggers)
        let idleTimer = null;
        let hasTriggeredIdle = false;
        const IDLE_TIME_MS = 2000; // 2 seconds
        const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
        
        function resetIdleTimer() {
            if (idleTimer) clearTimeout(idleTimer);
            
            // Only set timer if haven't triggered AND chat is closed
            const chat = document.getElementById('mallika-chat');
            if (!hasTriggeredIdle && chat && chat.style.display === 'none') {
                idleTimer = setTimeout(() => {
                    // Double-check chat is still closed
                    if (chat.style.display === 'none') {
                        hasTriggeredIdle = true;
                        
                        const proactiveMessages = [
                            "Hi! I noticed you've been quiet. Are you stuck on something? I'm here to help! 😊",
                            "Hey there! Need any assistance with your workload planning?",
                            "Hello! I see you're browsing. Is there anything I can clarify for you?",
                            "Hi! Just checking in - need help with any section?",
                            "Greetings! I'm Mallika. Feel free to ask me anything!"
                        ];
                        const msg = proactiveMessages[Math.floor(Math.random() * proactiveMessages.length)];
                        
                        // Show chat
                        const area = document.getElementById('chat-messages');
                        if (area && area.innerHTML.trim() === '') {
                            renderMessage('ai', msg);
                        }
                        
                        chat.style.opacity = '0';
                        chat.style.display = 'flex';
                        setTimeout(() => {
                            chat.style.transition = 'opacity 0.3s ease';
                            chat.style.opacity = '1';
                        }, 50);
                        
                        console.log('✨ Mallika: Idle engagement triggered');
                    }
                }, IDLE_TIME_MS);
            }
        }
        
        // Start idle tracking after all auto-triggers have had a chance to run
        setTimeout(() => {
            activityEvents.forEach(event => {
                document.addEventListener(event, resetIdleTimer, true);
            });
            resetIdleTimer(); // Start initial timer
            console.log('🤖 Mallika: Idle detector active (2s timeout)');
        }, 3000); // Start idle tracking 3 seconds after page load
    });

    let currentBriefingText = "";

    function generateBriefing() {
        // Call AI to generate briefing
        const facultyName = <?php echo json_encode($user['full_name']); ?>;
        const groupCode = <?php echo json_encode($groupCode); ?>;
        const scheduleStr = <?php echo json_encode($scheduleStr); ?>; // Defined in PHP above
        const missedCount = <?php echo (int)$missedCount; ?>;
        const isNewUser = <?php echo $isNewUser ? 'true' : 'false'; ?>;
        const activityStatus = <?php echo json_encode($activityStatus); ?>; // Activity counts per section

        fetch('ai_suggest.php', {
            method: 'POST',
            body: JSON.stringify({ 
                type: 'daily_briefing_gen', 
                name: facultyName, 
                group: groupCode,
                schedule: scheduleStr,
                targets: <?php echo json_encode($w); ?>,
                pending: missedCount > 0 ? missedCount + " missed tasks" : "None",
                is_new: isNewUser,
                activity_status: activityStatus  // Pass activity status to AI
            })
        })
        .then(r => r.json())
        .then(data => {
            let briefing = data.briefing || data.suggestion; 
            try {
                let jsonStr = data.suggestion.replace(/```json/g, '').replace(/```/g, '').trim();
                let parsed = JSON.parse(jsonStr);
                briefing = parsed.briefing;
            } catch(e) {}
            
            currentBriefingText = briefing;
            
            // Render nicely
            document.getElementById('briefingContent').innerHTML = briefing;
            const btn = document.getElementById('btnAccept');
            btn.disabled = false;
            btn.style.opacity = '1';
        });
    }

    function acceptBriefing() {
        const btn = document.getElementById('btnAccept');
        btn.innerHTML = 'Saving...';
        
        // Save to DB
        const fd = new FormData();
        fd.append('action', 'save_briefing');
        fd.append('date', "<?php echo $today; ?>");
        fd.append('content', currentBriefingText);
        
        fetch('ai_chat_handler.php', { method: 'POST', body: fd })
        .then(() => {
            document.getElementById('briefingModal').style.display = 'none';
            
            // Set flag to trigger Mallika after reload
            sessionStorage.setItem('mallikaAutoTrigger', 'true');
            
            // Reload page to show the Persistent Board
            window.location.reload();
        });
    }

</script>

<!-- Floating Chat Button (Mallika) -->
<div id="mallika-fab" onclick="toggleChat()" style="position: fixed; bottom: 30px; right: 30px; width: 65px; height: 65px; background: linear-gradient(135deg, #2c3e50, #3498db); border-radius: 50%; box-shadow: 0 6px 20px rgba(44, 62, 80, 0.4); display: flex; justify-content: center; align-items: center; cursor: pointer; z-index: 10000; transition: all 0.3s ease;">
    <i class="fas fa-robot" style="color: white; font-size: 30px;"></i>
</div>

<style>
#mallika-fab:hover {
    transform: scale(1.1) translateY(-3px);
    box-shadow: 0 8px 25px rgba(44, 62, 80, 0.5);
}
#mallika-fab:active {
    transform: scale(0.95);
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
</style>

<!-- Chat Interface -->
<div id="mallika-chat" style="display: none; flex-direction: column; position: fixed; bottom: 20px; right: 110px; width: 400px; height: 600px; max-height: 80vh; background: white; border-radius: 15px; box-shadow: 0 5px 30px rgba(0,0,0,0.2); z-index: 10000; overflow: hidden; border: 1px solid #eee;">
    
    <div style="background: #2c3e50; padding: 15px; display: flex; justify-content: space-between; align-items: center; color: white;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 35px; height: 35px; background: white; border-radius: 50%; display: flex; justify-content: center; align-items: center;">
                <i class="fas fa-robot" style="color: #2c3e50; font-size: 18px;"></i>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 1em;">Mallika AI</h4>
                <span id="chat-status" style="font-size: 0.75em; opacity: 0.8;">Online</span>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="toggleHistoryDrawer()" style="background: transparent; border: none; color: white; cursor: pointer;"><i class="fas fa-history"></i></button>
            <button onclick="toggleChat()" style="background: transparent; border: none; color: white; cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <!-- History Drawer -->
    <div id="history-drawer" style="position: absolute; top: 60px; left: 0; width: 100%; height: calc(100% - 60px); background: white; transform: translateX(100%); transition: transform 0.3s; z-index: 2;">
        <div style="padding: 15px; border-bottom: 1px solid #eee; font-weight: bold; color: #7f8c8d;">Previous Interactions</div>
        <div id="history-list" style="height: calc(100% - 50px); overflow-y: auto;">
            <div style="padding: 20px; text-align: center; color: #999;">Loading history...</div>
        </div>
    </div>

    <!-- Chat Area -->
    <div id="chat-messages" style="flex: 1; padding: 20px; overflow-y: auto; background: #f8f9fa;"></div>

    <!-- Input Area -->
    <div style="padding: 15px; background: white; border-top: 1px solid #eee;">
        <div style="display: flex; gap: 10px;">
            <input type="text" id="chat-input" placeholder="Type a message..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; outline: none;">
            <button onclick="sendMessage()" id="send-btn" style="width: 40px; height: 40px; border-radius: 50%; border: none; background: #2c3e50; color: white; cursor: pointer;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
    const facultyNameChat = <?php echo json_encode($user['full_name']); ?>;
    let currentDate = "<?php echo $today; ?>";
    let chatHistory = []; 

    // Agentic Proactivity — Auto-trigger analysis
    window.addEventListener('load', function() {
        // Debug: Clear trigger flag if URL has ?debug=1
        if (window.location.search.includes('debug=1')) {
            sessionStorage.removeItem('mallikaProactiveTrigger');
            console.log("Mallika: Debug mode active, cleared trigger flag.");
        }

        const hasTriggered = sessionStorage.getItem('mallikaProactiveTrigger');
        if (!hasTriggered && typeof facultyNameChat !== 'undefined') {
            console.log("Mallika: Will trigger analysis in 3s...");
            setTimeout(runAgenticAnalysis, 3000);
        } else {
            console.log("Mallika: Already triggered for this session or name missing.");
        }
    });

    function runAgenticAnalysis() {
        console.log("Mallika: Starting proactive analysis...");
        const currentFAEI = <?php echo json_encode($currentFAEI ?? 0); ?>;
        const currentTrend = <?php echo json_encode($currentTrend ?? 'Stable'); ?>;
        const darMissing = <?php echo ($darToday ?? false) ? 'false' : 'true'; ?>;
        const logsToday = <?php echo json_encode($todayLogsCount ?? 0); ?>;
        
        console.log("Mallika: Metrics - FAEI:", currentFAEI, "Trend:", currentTrend, "DAR Missing:", darMissing, "Logs Today:", logsToday);

        fetch('ai_suggest.php', {
            method: 'POST',
            body: JSON.stringify({ 
                type: 'agentic_proactive_check', 
                name: facultyNameChat, 
                faei: currentFAEI,
                trend: currentTrend,
                missed_dar: darMissing,
                recent_logs_count: logsToday,
                role: 'Faculty'
            })
        })
        .then(r => r.json())
        .then(data => {
            console.log("Mallika: Received AI Suggestion:", data);
            let aiResp = { message: "", trigger_type: "general" };
            try {
                let jsonStr = data.suggestion.replace(/```json/g, '').replace(/```/g, '').trim();
                aiResp = JSON.parse(jsonStr);
            } catch(e) { 
                console.warn("Mallika: Failed to parse JSON, using raw suggestion:", e);
                aiResp.message = data.suggestion; 
            }

            if (aiResp.message && aiResp.message.length > 5) {
                console.log("Mallika: Triggering Chat with message:", aiResp.message);
                // Auto-open chat if it's hidden
                const chat = document.getElementById('mallika-chat');
                if (chat.style.display === 'none') {
                    toggleChat();
                }
                
                // Render the proactive greeting
                renderMessage('ai', aiResp.message);
                saveInteraction(null, aiResp.message);
                chatHistory.push("ai: " + aiResp.message);
                
                // Mark as triggered for this session
                sessionStorage.setItem('mallikaProactiveTrigger', 'true');
            } else {
                console.log("Mallika: No proactive message generated or message too short.");
            }
        })
        .catch(err => console.error("Mallika: Proactive Fetch Error:", err));
    }

    loadChat(currentDate);

    function toggleChat() {
        const chat = document.getElementById('mallika-chat');
        chat.style.display = chat.style.display === 'none' ? 'flex' : 'none';
        if (chat.style.display === 'flex') {
            loadChat(currentDate);
        }
    }

    // ... (Use existing toggleHistoryDrawer, loadHistoryList, loadChat, renderMessageFunctions but ensure they don't call the old initSupervisor) ...
    // Note: I will reimplement them briefly to be safe and remove the old initSupervisor call logic.

    function toggleHistoryDrawer() {
        const drawer = document.getElementById('history-drawer');
        const isOpen = drawer.style.transform === 'translateX(0%)';
        drawer.style.transform = isOpen ? 'translateX(100%)' : 'translateX(0%)';
        if (!isOpen) loadHistoryList();
    }

    function loadHistoryList() {
        fetch('ai_chat_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=fetch_all_dates`
        })
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('history-list');
            list.innerHTML = '';
            data.dates.forEach(d => {
                const item = document.createElement('div');
                item.style.padding = '15px';
                item.style.borderBottom = '1px solid #f1f1f1';
                item.style.cursor = 'pointer';
                item.innerHTML = `<div><strong>${d.activity_date}</strong></div>`;
                item.onclick = () => {
                    currentDate = d.activity_date;
                    document.getElementById('chat-status').innerText = currentDate;
                    toggleHistoryDrawer();
                    loadChat(currentDate);
                };
                list.appendChild(item);
            });
        });
    }

    function loadChat(date) {
        const area = document.getElementById('chat-messages');
        area.innerHTML = '<div style="text-align:center; padding:20px; color:#aaa;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        chatHistory = [];

        fetch('ai_chat_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=fetch_history&date=${date}`
        })
        .then(r => r.json())
        .then(data => {
            area.innerHTML = '';
            const logs = data.logs;
            
            if (logs.length === 0) {
                 // Logic change: If no history, instead of forcing briefing, just greeting.
                 // We rely on the Briefing Modal for the heavy lifting.
                 // But we can add a simple greeting.
                 renderMessage('ai', "Hello! I am Mallika. I have access to your daily briefing. How can I assist you further?");
                 // saveInteraction(null, "Hello..."); // Optional
            } else {
                logs.forEach(log => {
                    renderMessage(log.role, log.text);
                    if(log.role !== 'system') chatHistory.push(log.role + ": " + log.text);
                });
            }
        });
    }

    function renderMessage(role, text) {
        const area = document.getElementById('chat-messages');
        const div = document.createElement('div');
        div.style.marginBottom = '15px';
        div.style.maxWidth = '80%';
        div.style.padding = '10px 15px';
        div.style.borderRadius = '12px';
        div.style.lineHeight = '1.4';
        div.style.fontSize = '0.95em';
        
        if (role === 'ai') {
            div.style.alignSelf = 'flex-start';
            div.style.background = '#e9ecef';
            div.style.color = '#2c3e50';
            div.innerHTML = '<strong>Mallika:</strong><br>' + text;
        } else {
            div.style.alignSelf = 'flex-end';
            div.style.marginLeft = 'auto'; 
            div.style.background = '#3498db';
            div.style.color = 'white';
            div.innerHTML = text;
        }
        area.appendChild(div);
        area.scrollTop = area.scrollHeight;
    }

    function sendMessage() {
        const input = document.getElementById('chat-input');
        const text = input.value.trim();
        if(!text) return;
        
        renderMessage('user', text);
        input.value = '';
        saveInteraction(text, null);
        chatHistory.push("user: " + text);
        
        const loader = document.createElement('div');
        loader.id = 'ai-typing';
        loader.innerHTML = '<small style="color:#aaa; margin-left: 10px;">Mallika is typing...</small>';
        document.getElementById('chat-messages').appendChild(loader);

        // Pass 'briefing_context' here? 
        // We can let the backend handle fetching the briefing content to inject into context.
        // For now, simpler: Just send user_msg. The backend `ai_chat_handler.php` doesn't do logic, `ai_suggest.php` does.
        // We should pass the briefing content if we have it client side, OR better, let ai_suggest fetch it?
        // Let's pass it if available (from DOM or variable)
        const briefingContext = (typeof currentBriefingText !== 'undefined' && currentBriefingText) ? "User's Today Briefing: " + currentBriefingText : "";

        fetch('ai_suggest.php', {
            method: 'POST',
            body: JSON.stringify({ 
                type: 'supervisor_reply', 
                user_msg: text, 
                history: chatHistory.join('\n') + "\n" + briefingContext 
            })
        })
        .then(r => r.json())
        .then(data => {
             document.getElementById('ai-typing').remove();
             let aiResp = { message: data.suggestion, action: "assist" };
             try {
                let jsonStr = data.suggestion.replace(/```json/g, '').replace(/```/g, '').trim();
                aiResp = JSON.parse(jsonStr);
             } catch(e) {}

             renderMessage('ai', aiResp.message);
             saveInteraction(null, aiResp.message);
             chatHistory.push("ai: " + aiResp.message);
        });
    }

    function saveInteraction(userMsg, aiMsg) {
        const fd = new FormData();
        fd.append('action', 'save_interaction');
        fd.append('date', currentDate);
        if(userMsg) fd.append('user_msg', userMsg);
        if(aiMsg) fd.append('ai_msg', aiMsg);
        fetch('ai_chat_handler.php', { method: 'POST', body: fd });
    }
    
    document.getElementById('chat-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') sendMessage();
    });
</script>
<?php } ?>

<?php require_once 'footer.php'; ?>
