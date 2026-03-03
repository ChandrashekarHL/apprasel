<?php
require_once 'header.php';
require_once 'WorkloadEngine.php';
require_once 'faculty_performance_analyzer.php'; // NEW: Performance monitoring

$engine = new WorkloadEngine($pdo);
$analyzer = new FacultyPerformanceAnalyzer($pdo); // NEW
$user = getCurrentUser($pdo);
$today = date('Y-m-d');

// Role Check - Case Insensitive
$userRole = $_SESSION['role'] ?? '';
$isHOD = stripos($userRole, 'Reviewer') !== false || stripos($userRole, 'Admin') !== false;

if (!$isHOD) {
    header("Location: dashboard.php");
    exit;
}

// NEW: Get comprehensive performance data using analyzer (Hierarchical View)
// We pass the logged-in user's EMP_ID so the analyzer can determine their scope (VC/Dean/HOD)
// We also pass department as a fallback.
$requesterId = !empty($user['emp_id']) ? $user['emp_id'] : $user['username'];
$facultyPerformance = $analyzer->getAllFacultyPerformance('flag_priority', $requesterId, $user['department']);

// Aggregate stats
$totalFaculty = count($facultyPerformance);
$redFlagged = count(array_filter($facultyPerformance, fn($f) => $f['flag'] === 'red'));
$yellowFlagged = count(array_filter($facultyPerformance, fn($f) => $f['flag'] === 'yellow'));
$greenCount = count(array_filter($facultyPerformance, fn($f) => $f['flag'] === 'green'));

// Calculate averages
$totalTUI = array_sum(array_column($facultyPerformance, 'tui'));
$totalFAEI = array_sum(array_column($facultyPerformance, 'faei'));
$totalWeeklyCompletion = array_sum(array_column($facultyPerformance, 'weekly_completion'));
$avgTUI = $totalFaculty > 0 ? round($totalTUI / $totalFaculty, 1) : 0;
$avgFAEI = $totalFaculty > 0 ? round($totalFAEI / $totalFaculty, 1) : 0;
$avgWeeklyCompletion = $totalFaculty > 0 ? round($totalWeeklyCompletion / $totalFaculty, 1) : 0;
$compliance = $avgWeeklyCompletion; // For backward compatibility with charts
?>

<style>
    /* HoD Dashboard Specific Styles - Premium Corporate Look */
    :root {
        --primary-brand: #2c3e50;
        --secondary-brand: #34495e;
        --accent-gold: #f1c40f;
        --success-green: #27ae60;
        --bg-light: #f4f6f9;
        --card-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
        --card-hover: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
    }
    
    body {
        background-color: var(--bg-light);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .hod-container {
        max-width: 1200px;
        margin: 0 auto;
        padding-top: 20px;
    }

    .dashboard-hero {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 4px solid var(--primary-brand);
    }

    /* AI Chat Tags/Chips */
    .chat-chip {
        display: inline-block;
        padding: 8px 12px;
        margin: 5px 5px 0 0;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        font-size: 0.85em;
        color: #495057;
        cursor: pointer;
        transition: all 0.2s;
    }
    .chat-chip:hover {
        background: #e9ecef;
        border-color: #adb5bd;
        color: #2c3e50;
    }

    .hero-text h2 { margin: 0; font-size: 1.8em; color: var(--primary-brand); }
    .hero-text p { margin: 5px 0 0; color: #7f8c8d; font-size: 0.95em; }

    .action-bar { display: flex; gap: 15px; }
    .btn-action {
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 500;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .btn-primary-action { background: var(--primary-brand); color: white; border: none; }
    .btn-primary-action:hover { background: #1a252f; transform: translateY(-1px); }
    .btn-outline-action { border: 1px solid #bdc3c7; color: #7f8c8d; background: white; }
    .btn-outline-action:hover { border-color: var(--primary-brand); color: var(--primary-brand); }

    /* KPI Cards */
    .kpi-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .kpi-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
        transition: transform 0.2s;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--card-hover); }
    
    .kpi-label { font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; color: #95a5a6; font-weight: 600; margin-bottom: 10px; display: block; }
    .kpi-value { font-size: 2.2em; font-weight: 700; color: var(--primary-brand); }
    .kpi-icon { position: absolute; right: 20px; bottom: 20px; font-size: 2.5em; opacity: 0.1; }

    /* Performance Table */
    .data-card {
        background: white;
        border-radius: 10px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }
    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid #ecf0f1;
        background: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .card-header h3 { margin: 0; font-size: 1.1em; color: var(--secondary-brand); }

    .modern-table { width: 100%; border-collapse: collapse; }
    .modern-table th { background: #f8f9fa; padding: 15px 25px; text-align: left; font-size: 0.85em; color: #7f8c8d; text-transform: uppercase; font-weight: 600; }
    .modern-table td { padding: 15px 25px; border-bottom: 1px solid #ecf0f1; color: #2c3e50; font-size: 0.95em; }
    .modern-table tr:hover { background: #fcfcfc; }
    .modern-table tr:last-child td { border-bottom: none; }

    /* Badges */
    .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.75em; font-weight: 700; text-transform: uppercase; display: inline-block; }
    .status-Submitted { background: rgba(39, 174, 96, 0.1); color: #27ae60; }
    .status-Not { background: rgba(231, 76, 60, 0.1); color: #e74c3c; /* substring match hack */ } 
    .status-Draft { background: rgba(243, 156, 18, 0.1); color: #f39c12; }

    .score-badge { font-weight: 700; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
    .score-good { color: #27ae60; }
    .score-avg { color: #f39c12; }
    .score-poor { color: #e74c3c; }
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="hod-container">
    
    <!-- Hero Header -->
    <div class="dashboard-hero">
        <div class="hero-text">
            <h2>FW-AEMS Governance Center</h2>
            <p>Welcome, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>. Real-time academic effectiveness overview.</p>
            <div style="font-size: 0.85em; margin-top: 5px;">
                <!-- <a href="dean_dashboard.php" style="color: var(--primary-brand); text-decoration: underline; margin-right: 15px;">Dean's Console</a>
                <a href="vc_dashboard.php" style="color: var(--primary-brand); text-decoration: underline;">VC Strategic View</a> -->
            </div>
        </div>
        <div class="action-bar">
            <a href="hod_approvals.php" class="btn-action btn-outline-action" style="border-color: #f1c40f; color: #f39c12;">
                <i class="fas fa-check-double"></i> Approvals
            </a>
            <a href="reports.php" class="btn-action btn-outline-action">
                <i class="fas fa-file-contract"></i> Reports
            </a>
            <a href="admin_config.php" class="btn-action btn-outline-action" style="border-color: #7f8c8d; color: #7f8c8d;">
                <i class="fas fa-tools"></i> Config
            </a>
            <a href="hod_allocations.php" class="btn-action btn-primary-action">
                <i class="fas fa-users-cog"></i> Manage Allocations
            </a>
            <a href="dashboard.php" class="btn-action btn-outline-action">
                <i class="fas fa-arrow-left"></i> Main Board
            </a>
        </div>
    </div>

    <!-- Analytics Row (New!) -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div class="data-card" style="padding: 20px;">
            <h4 style="margin-top: 0; color: #7f8c8d;">Workload Distribution (Heatmap Simulation)</h4>
            <canvas id="workloadChart" height="100"></canvas>
        </div>
        <div class="data-card" style="padding: 20px;">
             <h4 style="margin-top: 0; color: #7f8c8d;">Compliance Status</h4>
             <canvas id="complianceChart" height="100"></canvas>
        </div>
    </div>

    <!-- Daily Activity Compliance Alert (New Feature) -->
    <?php
    // Daily Activity Compliance Alert (Filtered by Dept)
    $defaultersStmt = $pdo->prepare("SELECT f.full_name, COUNT(d.id) as missed_count 
                                   FROM ad_daily_ai_activity d 
                                   JOIN ad_faculty_users f ON d.faculty_id = f.id 
                                   WHERE d.status = 'Missed' 
                                   AND (f.department = ? OR f.school = ?)
                                   GROUP BY d.faculty_id 
                                   HAVING missed_count > 0 
                                   ORDER BY missed_count DESC");
    $defaultersStmt->execute([$user['department'], $user['department']]);
    $defaulters = $defaultersStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <?php if (count($defaulters) > 0): ?>
    <div class="data-card" style="margin-bottom: 30px; border-left: 5px solid #e74c3c;">
        <div class="card-header" style="background: #fff5f5;">
            <h3 style="color: #c0392b;"><i class="fas fa-exclamation-triangle"></i> Daily Activity Compliance Alerts</h3>
            <span style="font-size: 0.85em; color: #7f8c8d;">Action Required: Review with Faculty</span>
        </div>
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Faculty Name</th>
                    <th>Missed Daily Activities</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($defaulters as $d): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($d['full_name']); ?></strong></td>
                    <td style="color: #c0392b; font-weight: bold;"><?php echo $d['missed_count']; ?> Days</td>
                    <td>
                        <?php if ($d['missed_count'] > 30): ?>
                            <span class="status-badge" style="background: #c0392b; color: white;">VC ESCALATED</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: #f39c12; color: white;">HoD Warning</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm" style="border: 1px solid #ddd; padding: 5px 10px; cursor: pointer;">Notify</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ⚠️ DAR Not Filled Today Panel -->
    <?php
    // Find faculty with no ac_dar entry for today
    try {
        $darMissingStmt = $pdo->prepare("
            SELECT u.id, u.emp_id, u.full_name, u.designation, u.department
            FROM ad_faculty_users u
            LEFT JOIN ac_dar d ON d.EMP_ID = u.emp_id AND d.DATE = CURDATE()
            WHERE d.SL_NO IS NULL
              AND u.role = 'Faculty'
              AND (u.department = ? OR u.school = ?)
            ORDER BY u.full_name
        ");
        $darMissingStmt->execute([$user['department'], $user['department']]);
        $darMissingFaculty = $darMissingStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $darMissingFaculty = [];
    }

    // Fetch active Sentinel Oversight flags for this HOD's department
    $oversightFlags = [];
    try {
        $osStmt = $pdo->prepare("SELECT faculty_id, category, message, created_at FROM ad_agentic_oversight WHERE status = 'active'");
        $osStmt->execute();
        while($row = $osStmt->fetch(PDO::FETCH_ASSOC)) {
            $oversightFlags[$row['faculty_id']][] = $row;
        }
    } catch (Exception $e) {}
    ?>

    <?php if (!empty($darMissingFaculty)): ?>
    <div class="data-card" style="margin-bottom: 30px; border-left: 5px solid #e67e22;">
        <div class="card-header" style="background: #fdf6ec;">
            <h3 style="color: #d35400;">
                <i class="fas fa-clipboard-list"></i>
                DAR Not Filled Today
                <span style="background: #e67e22; color: white; font-size: 0.75em; padding: 3px 8px; border-radius: 10px; margin-left: 8px;">
                    <?php echo count($darMissingFaculty); ?> faculty
                </span>
            </h3>
            <span style="font-size: 0.85em; color: #7f8c8d;">
                <?php echo date('l, d M Y'); ?> — These faculty have not submitted any DAR entry today
            </span>
        </div>
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Faculty Member</th>
                    <th>Employee ID</th>
                    <th>Designation</th>
                    <th>DAR Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($darMissingFaculty as $mf): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($mf['full_name']); ?></strong><br>
                        <small style="color: #95a5a6;"><?php echo htmlspecialchars($mf['department']); ?></small>
                        <?php if (isset($oversightFlags[$mf['id']])): ?>
                            <div style="margin-top: 5px;">
                                <span style="background: #2c3e50; color: white; font-size: 0.65em; padding: 2px 6px; border-radius: 4px; font-weight: bold;">
                                    <i class="fas fa-robot"></i> Mallika Sentinel
                                </span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="font-family: monospace; color: #555;">
                        <?php echo htmlspecialchars($mf['emp_id'] ?? '—'); ?>
                    </td>
                    <td><?php echo htmlspecialchars($mf['designation'] ?? '—'); ?></td>
                    <td>
                        <span class="status-badge" style="background: rgba(230, 126, 34, 0.12); color: #e67e22;">
                            <i class="fas fa-exclamation-circle"></i> Not Filed Today
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    </div>

    <!-- Agentic Escalations (New!) -->
    <?php
    try {
        $escalationsStmt = $pdo->prepare("
            SELECT n.*, f.full_name, f.designation 
            FROM ad_ai_notifications n
            JOIN ad_faculty_users f ON n.faculty_id = f.id
            WHERE n.type = 'escalation' 
              AND n.status = 'unread'
              AND (f.department = ? OR f.school = ?)
            ORDER BY n.created_at DESC
        ");
        $escalationsStmt->execute([$user['department'], $user['department']]);
        $escalations = $escalationsStmt->fetchAll();
    } catch (Exception $e) { $escalations = []; }
    ?>

    <?php if (!empty($escalations)): ?>
    <div class="data-card" style="margin-bottom: 30px; border-left: 5px solid #8e44ad; background: #faf5ff;">
        <div class="card-header" style="background: rgba(142, 68, 173, 0.05);">
            <h3 style="color: #8e44ad;"><i class="fas fa-robot"></i> Mallika's Agentic Escalations</h3>
            <span style="font-size: 0.85em; color: #7f8c8d;">AI identified critical performance risks needing HOD attention</span>
        </div>
        <div style="padding: 15px;">
            <?php foreach ($escalations as $e): ?>
            <div style="background: white; border: 1px solid #e9dcf1; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: bold; color: #2c3e50;"><?php echo htmlspecialchars($e['full_name']); ?> <span style="font-weight: normal; color: #7f8c8d; font-size: 0.9em;">(<?php echo htmlspecialchars($e['designation']); ?>)</span></div>
                    <div style="color: #4a5568; font-size: 0.95em; margin-top: 5px; font-style: italic;">"<?php echo htmlspecialchars($e['message']); ?>"</div>
                    <div style="font-size: 0.75em; color: #a0aec0; margin-top: 5px;"><?php echo date('d M, h:i A', strtotime($e['created_at'])); ?></div>
                </div>
                <div>
                    <a href="hod_faculty_detail.php?id=<?php echo $e['faculty_id']; ?>" class="btn-action btn-outline-action" style="font-size: 0.85em;">
                        Review Depth
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Data Grid -->
    <div class="data-card">
        <!-- ... existing table ... -->
        <div class="card-header">
            <h3>Faculty Performance Matrix <span style="font-weight: normal; color: #95a5a6; font-size: 0.8em; margin-left: 10px;">(Current Week | Module 5)</span></h3>
        </div>
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Faculty Member</th>
                    <th>Designation</th>
                    <th>Plan Status</th>
                    <th>Implementation (TUI)</th>
                    <th>Effectiveness (FAEI)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($facultyPerformance as $row): ?>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($row['name']); ?></div>
                            <?php if (!empty($row['is_oversight'])): ?>
                                <span class="badge" style="background: #8e44ad; color: white; font-size: 0.65em; padding: 2px 6px; border-radius: 4px;" title="Mallika Sentinel: <?php echo htmlspecialchars($row['oversight_message']); ?>">
                                    <i class="fas fa-robot"></i> SENTINEL
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.85em; color: #95a5a6;">
                            <?php echo htmlspecialchars($row['department']); ?> 
                            <?php if(!empty($row['group_code'])): ?>
                                • <span style="color: #34495e; font-weight: 500;">Group <?php echo htmlspecialchars($row['group_code']); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($row['designation']); ?></td>
                    <td>
                        <span class="status-badge">
                            <?php echo $row['trend']; ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color: #2c3e50;"><?php echo $row['tui']; ?></strong>
                        <span style="font-size: 0.8em; color: #bdc3c7;">/ 10</span>
                    </td>
                    <td>
                         <?php 
                            $s = $row['faei'];
                            $c = ($s >= 8) ? 'score-good' : (($s >= 5) ? 'score-avg' : 'score-poor');
                        ?>
                        <span class="score-badge <?php echo $c; ?>"><?php echo $s; ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($facultyPerformance)): ?>
                    <tr><td colspan="6" style="text-align: center; color: #95a5a6;">No faculty data found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php include 'hod_performance_monitor_section.php'; ?>

</div>

<!-- Floating Chat Button (Mallika) -->
<div id="mallika-fab" onclick="toggleChat()" style="position: fixed; bottom: 30px; right: 30px; width: 65px; height: 65px; background: linear-gradient(135deg, #2c3e50, #f39c12); border-radius: 50%; box-shadow: 0 6px 20px rgba(44, 62, 80, 0.4); display: flex; justify-content: center; align-items: center; cursor: pointer; z-index: 10000; transition: all 0.3s ease;">
    <i class="fas fa-robot" style="color: white; font-size: 30px;"></i>
</div>

<!-- Chat Interface -->
<div id="mallika-chat" style="display: none; flex-direction: column; position: fixed; bottom: 20px; right: 110px; width: 400px; height: 600px; max-height: 80vh; background: white; border-radius: 15px; box-shadow: 0 5px 30px rgba(0,0,0,0.2); z-index: 10000; overflow: hidden; border: 1px solid #eee;">
    <div style="background: #2c3e50; padding: 15px; display: flex; justify-content: space-between; align-items: center; color: white;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 35px; height: 35px; background: white; border-radius: 50%; display: flex; justify-content: center; align-items: center;">
                <i class="fas fa-robot" style="color: #2c3e50; font-size: 18px;"></i>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 1em;">Mallika AI (HOD Mode)</h4>
                <span id="chat-status" style="font-size: 0.75em; opacity: 0.8;">Online</span>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="toggleHistoryDrawer()" style="background: transparent; border: none; color: white; cursor: pointer;"><i class="fas fa-history"></i></button>
            <button onclick="toggleChat()" style="background: transparent; border: none; color: white; cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div id="history-drawer" style="position: absolute; top: 60px; left: 0; width: 100%; height: calc(100% - 60px); background: white; transform: translateX(100%); transition: transform 0.3s; z-index: 2;">
        <div style="padding: 15px; border-bottom: 1px solid #eee; font-weight: bold; color: #7f8c8d;">Previous Interactions</div>
        <div id="history-list" style="height: calc(100% - 50px); overflow-y: auto;"></div>
    </div>
    <div id="chat-messages" style="flex: 1; padding: 20px; overflow-y: auto; background: #f8f9fa;"></div>
    <div style="padding: 15px; background: white; border-top: 1px solid #eee;">
        <div style="display: flex; gap: 10px;">
            <input type="text" id="chat-input" placeholder="Ask about faculty performance..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; outline: none;">
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

    window.addEventListener('load', function() {
        // Debug: Clear trigger flag if URL has ?debug=1
        if (window.location.search.includes('debug=1')) {
            sessionStorage.removeItem('mallikaHODProactiveTrigger');
            console.log("Mallika HOD: Debug mode active, cleared trigger flag.");
        }

        const hasTriggered = sessionStorage.getItem('mallikaHODProactiveTrigger');
        if (!hasTriggered) {
            console.log("Mallika HOD: Will trigger analysis in 2.5s...");
            setTimeout(runAgenticAnalysis, 2500);
        } else {
            console.log("Mallika HOD: Already triggered for this session.");
        }
    });

    function runAgenticAnalysis() {
        console.log("Mallika HOD: Starting proactive analysis...");
        const dept = <?php echo json_encode($user['department']); ?>;
        const crit = <?php echo json_encode($redFlagged ?? 0); ?>;
        const warn = <?php echo json_encode($yellowFlagged ?? 0); ?>;
        const totalFactor = <?php echo json_encode($totalFaculty ?? 0); ?>;
        const miss = <?php echo json_encode(count($darMissingFaculty ?? [])); ?>;
        const faei = <?php echo json_encode($avgFAEI ?? 0); ?>;
        
        console.log("Mallika HOD: Metrics - Dept:", dept, "Total:", totalFactor, "Crit:", crit, "Warn:", warn, "Miss:", miss, "FAEI:", faei);

        fetch('ai_suggest.php', {
            method: 'POST',
            body: JSON.stringify({ 
                type: 'agentic_proactive_check', 
                name: facultyNameChat, 
                role: 'Reviewer',
                department: dept,
                total_faculty: totalFactor,
                critical_count: crit,
                warning_count: warn,
                missing_dar_count: miss,
                avg_faei: faei
            })
        })
        .then(r => {
            if (!r.ok) throw new Error("HTTP error " + r.status);
            return r.json();
        })
        .then(data => {
            console.log("Mallika HOD: Received AI Suggestion:", data);
            let aiResp = { message: "" };
            try {
                let jsonStr = data.suggestion.replace(/```json/g, '').replace(/```/g, '').trim();
                aiResp = JSON.parse(jsonStr);
            } catch(e) { 
                console.warn("Mallika HOD: Failed to parse JSON, using raw suggestion:", e);
                aiResp.message = data.suggestion; 
            }

            if (aiResp.message && aiResp.message.length > 5) {
                console.log("Mallika HOD: Triggering Chat with message:", aiResp.message);
                if (document.getElementById('mallika-chat').style.display === 'none') {
                    toggleChat();
                }
                renderMessage('ai', aiResp.message, aiResp.action);
                saveInteraction(null, aiResp.message);
                chatHistory.push("ai: " + aiResp.message);
                sessionStorage.setItem('mallikaHODProactiveTrigger', 'true');
            } else {
                console.log("Mallika HOD: No proactive message generated or message too short.");
            }
        })
        .catch(err => console.error("Mallika HOD: Proactive Fetch Error:", err));
    }

    function toggleChat() {
        const chat = document.getElementById('mallika-chat');
        chat.style.display = chat.style.display === 'none' ? 'flex' : 'none';
        if (chat.style.display === 'flex') loadChat(currentDate);
    }

    function toggleHistoryDrawer() {
        const drawer = document.getElementById('history-drawer');
        const isOpen = drawer.style.transform === 'translateX(0%)';
        drawer.style.transform = isOpen ? 'translateX(100%)' : 'translateX(0%)';
        if (!isOpen) loadHistoryList();
    }

    function loadHistoryList() {
        fetch('ai_chat_handler.php', { method: 'POST', body: 'action=fetch_all_dates', headers: {'Content-Type': 'application/x-www-form-urlencoded'} })
        .then(r => r.ok ? r.json() : {dates:[]})
        .catch(() => ({dates:[]}))
        .then(data => {
            const list = document.getElementById('history-list'); list.innerHTML = '';
            data.dates.forEach(d => {
                const item = document.createElement('div');
                item.style.padding = '15px'; item.style.borderBottom = '1px solid #f1f1f1'; item.style.cursor = 'pointer';
                item.innerHTML = `<strong>${d.activity_date}</strong>`;
                item.onclick = () => { currentDate = d.activity_date; toggleHistoryDrawer(); loadChat(currentDate); };
                list.appendChild(item);
            });
        });
    }

    function loadChat(date) {
        const area = document.getElementById('chat-messages');
        area.innerHTML = '<div style="text-align:center; padding:20px; color:#aaa;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        fetch('ai_chat_handler.php', { method: 'POST', body: `action=fetch_history&date=${date}`, headers: {'Content-Type': 'application/x-www-form-urlencoded'} })
        .then(r => r.ok ? r.json() : {logs:[]})
        .catch(() => ({logs:[]}))
        .then(data => {
            area.innerHTML = '';
            if (data.logs.length === 0) {
                renderMessage('ai', "Hello HOD! I'm Mallika. I've analyzed today's department performance. How can I help you oversee the faculty?");
                renderQuickActions();
            } else {
                data.logs.forEach(log => {
                    renderMessage(log.role, log.text);
                    chatHistory.push(log.role + ": " + log.text);
                });
            }
        });
    }

    function renderMessage(role, text, action = null) {
        const area = document.getElementById('chat-messages');
        const div = document.createElement('div');
        div.style.cssText = "margin-bottom:15px; max-width:80%; padding:10px 15px; border-radius:12px; line-height:1.4; font-size:0.95em;";
        if (role === 'ai') {
            div.style.background = '#e9ecef'; div.style.color = '#2c3e50';
            div.innerHTML = '<strong>Mallika:</strong><br>' + text;
            
            if (action) {
                // Defensive check: if action is a string, convert to object
                if (typeof action === 'string') {
                    action = { type: action, label: 'Take Action' };
                }
                const btn = document.createElement('button');
                btn.innerHTML = `<i class="fas fa-paper-plane"></i> ${action.label || 'Take Action'}`;
                btn.style.cssText = "display:block; width:100%; margin-top:10px; padding:8px; background:#2c3e50; color:white; border:none; border-radius:5px; cursor:pointer; font-size:0.9em;";
                btn.onclick = () => executeMallikaAction(action, btn);
                div.appendChild(btn);
            }
        } else {
            div.style.background = '#3498db'; div.style.color = 'white'; div.style.marginLeft = 'auto';
            div.innerHTML = text;
        }
        area.appendChild(div); area.scrollTop = area.scrollHeight;
    }

    function renderQuickActions() {
        const area = document.getElementById('chat-messages');
        const container = document.createElement('div');
        container.style.cssText = "margin: 10px 0 20px 40px;";
        
        const prompts = [
            { label: "Dept Status", text: "What is the status of the dept?" },
            { label: "Dept FAEI", text: "What is the FAEI of the department?" },
            { label: "Lagging Area", text: "Where are we lagging behind?" }
        ];
        
        prompts.forEach(p => {
            const chip = document.createElement('div');
            chip.className = 'chat-chip';
            chip.innerHTML = `<i class="fas fa-magic" style="color:#f1c40f; font-size:0.8em;"></i> ${p.label}`;
            chip.onclick = () => {
                document.getElementById('chat-input').value = p.text;
                sendMessage();
                container.remove(); // Clear suggestions once used
            };
            container.appendChild(chip);
        });
        area.appendChild(container);
    }

    function executeMallikaAction(action, btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        const formData = new FormData();
        formData.append('action', action.type);
        
        fetch('ai_chat_handler.php', { method: 'POST', body: formData })
        .then(r => r.ok ? r.json() : {status:'error'})
        .catch(() => ({status:'error'}))
        .then(data => {
            if (data.status === 'success') {
                btn.innerHTML = '<i class="fas fa-check"></i> Sent!';
                btn.style.background = '#27ae60';
                renderMessage('ai', `Acknowledged. I have sent personalized reminders to ${data.nudged_count} faculty members and placed them under **Mallika Sentinel Oversight**. I will notify you once they complete their DARs.`);
            } else {
                btn.innerHTML = 'Failed';
                btn.style.background = '#e74c3c';
            }
        });
    }

    function sendMessage() {
        const input = document.getElementById('chat-input');
        const text = input.value.trim(); if(!text) return;
        renderMessage('user', text); input.value = '';
        saveInteraction(text, null); chatHistory.push("user: " + text);
        
        const loader = document.createElement('div'); loader.id = 'ai-typing';
        loader.innerHTML = '<small style="color:#aaa; margin-left: 10px;">Mallika is thinking...</small>';
        document.getElementById('chat-messages').appendChild(loader);

        // Fetch metrics again for fresh context
        const dept = <?php echo json_encode($user['department']); ?>;
        const crit = <?php echo json_encode($redFlagged ?? 0); ?>;
        const warn = <?php echo json_encode($yellowFlagged ?? 0); ?>;
        const totalFactor = <?php echo json_encode($totalFaculty ?? 0); ?>;
        const miss = <?php echo json_encode(count($darMissingFaculty ?? [])); ?>;
        const faei = <?php echo json_encode($avgFAEI ?? 0); ?>;

        fetch('ai_suggest.php', {
            method: 'POST',
            body: JSON.stringify({ 
                type: 'supervisor_reply', 
                user_msg: text, 
                history: chatHistory.join('\n'),
                role: 'Reviewer',
                department: dept,
                total_faculty: totalFactor,
                missing_dar_count: miss,
                critical_count: crit,
                avg_faei: faei
            })
        })
        .then(r => {
            if (!r.ok) throw new Error("Status " + r.status);
            return r.json();
        })
        .catch(err => {
            return { suggestion: "Error: " + err.message };
        })
        .then(data => {
            document.getElementById('ai-typing').remove();
            
            let aiResp = { message: data.suggestion };
            try {
                // Try parsing as JSON if AI returns it
                if (data.suggestion.includes('{')) {
                    let jsonStr = data.suggestion.replace(/```json/g, '').replace(/```/g, '').trim();
                    aiResp = JSON.parse(jsonStr);
                }
            } catch(e) { console.warn("Chat JSON parse failed, using raw."); }

            renderMessage('ai', aiResp.message, aiResp.action);
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

    document.getElementById('chat-input').addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
</script>

<?php require_once 'footer.php'; ?>

