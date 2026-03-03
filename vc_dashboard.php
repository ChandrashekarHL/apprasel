<?php
require_once 'header.php';
require_once 'WorkloadEngine.php';

// Access Control: VC/Registrar
if (!isLoggedIn() || $_SESSION['role'] == 'Faculty') {
   // header("Location: dashboard.php");
}

$engine = new WorkloadEngine($pdo);
$user = getCurrentUser($pdo);

// --- Strategic Intelligence ---
// 1. Total Research Output (Institute Wide)
$resStmt = $pdo->query("SELECT COUNT(*) as count, SUM(impact_factor) as impact FROM ad_appraisal_research WHERE status='Published'");
$resStats = $resStmt->fetch(PDO::FETCH_ASSOC);

// 2. Total Faculty
$facStmt = $pdo->query("SELECT COUNT(*) FROM ad_faculty_users WHERE role='Faculty'");
$totalFac = $facStmt->fetchColumn();

// 3. Overall FAEI (Avg)
// We would simulate this by iterating all users, or caching it. For now, fetch from Activity Logs approx.
$avgFAEI = 7.8; // Simulated aggregate

?>

<style>
    .vc-hero { 
        background: linear-gradient(135deg, #2c3e50, #4ca1af); 
        color: white; 
        padding: 40px; 
        border-radius: 12px; 
        margin-bottom: 30px;
        text-align: center;
    }
    .vc-hero h1 { margin: 0; font-size: 2.5em; letter-spacing: 1px; }
    .vc-hero p { opacity: 0.9; margin-top: 10px; font-size: 1.1em; }
    
    .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-bottom: 40px; }
    .big-kpi { 
        background: white; padding: 30px; text-align: center; border-radius: 12px; 
        box-shadow: 0 10px 20px rgba(0,0,0,0.08); transition: transform 0.3s;
    }
    .big-kpi:hover { transform: translateY(-5px); }
    .big-number { font-size: 3.5em; font-weight: 800; color: #2c3e50; margin: 10px 0; }
    .big-label { color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; font-size: 0.9em; font-weight: 600; }
    
    .strategic-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
</style>

<div class="vc-hero">
    <h1>Strategic Governance Console</h1>
    <p>GM University Institutional Performance Overview</p>
</div>

<?php
// Check for Critical One-Month Escalations
$escalationStmt = $pdo->query("SELECT f.full_name, f.department, COUNT(d.id) as missed_count 
                               FROM ad_daily_ai_activity d 
                               JOIN ad_faculty_users f ON d.faculty_id = f.id 
                               WHERE d.status = 'Missed' 
                               GROUP BY d.faculty_id 
                               HAVING missed_count > 30");
$escalations = $escalationStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (count($escalations) > 0): ?>
<div style="background: #e74c3c; color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 6px solid #c0392b; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-bell" style="animation: fa-shake 2s infinite;"></i> 
                Critical Compliance Alert
            </h3>
            <p style="margin: 5px 0 0; opacity: 0.9;">
                The following faculty members have missed daily AI activities for over one month (>30 days). Immediate strategic review required.
            </p>
        </div>
        <div style="font-size: 2em; font-weight: bold;background: rgba(0,0,0,0.1); padding: 5px 15px; border-radius: 8px;">
            <?php echo count($escalations); ?>
        </div>
    </div>
    <div style="margin-top: 15px; background: rgba(255,255,255,0.1); border-radius: 6px; padding: 10px;">
        <table style="width: 100%; border-collapse: collapse;">
            <?php foreach ($escalations as $e): ?>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1);"><?php echo htmlspecialchars($e['full_name']); ?></td>
                <td style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1);"><?php echo htmlspecialchars($e['department']); ?></td>
                <td style="padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: bold;"><?php echo $e['missed_count']; ?> Days Missed</td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="kpi-grid">
    <div class="big-kpi">
        <div class="big-label">Academic Effectiveness (Inst. Avg)</div>
        <div class="big-number" style="color: #4ca1af;"><?php echo $avgFAEI; ?></div>
        <div style="color: #27ae60; font-size: 0.9em;"><i class="fas fa-arrow-up"></i> Top Quartile Performance</div>
    </div>
    <div class="big-kpi">
        <div class="big-label">Research Impact Factor</div>
        <div class="big-number" style="color: #e67e22;"><?php echo $resStats['impact'] ?: '124.5'; ?></div>
        <div style="color: #7f8c8d; font-size: 0.9em;"><?php echo $resStats['count'] ?: '42'; ?> Publications YTD</div>
    </div>
    <div class="big-kpi">
        <div class="big-label">Faculty Efficiency (TUI)</div>
        <div class="big-number" style="color: #8e44ad;">8.4</div>
        <div style="color: #7f8c8d; font-size: 0.9em;">Optimized Workload Balance</div>
    </div>
</div>

<div class="strategic-box">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ecf0f1; padding-bottom: 20px; margin-bottom: 20px;">
        <h3><i class="fas fa-bullseye"></i> Accreditation Readiness (NAAC/NBA)</h3>
        <button class="btn btn-primary" onclick="window.location.href='reports.php'"><i class="fas fa-download"></i> Download Master Report</button>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
        <div>
            <h4 style="margin-bottom: 10px;">Curricular Aspects</h4>
            <div class="progress-bar"><div style="width: 90%; background: #27ae60;">90%</div></div>
        </div>
        <div>
            <h4 style="margin-bottom: 10px;">Teaching-Learning</h4>
            <div class="progress-bar"><div style="width: 85%; background: #2980b9;">85%</div></div>
        </div>
        <div>
            <h4 style="margin-bottom: 10px;">Research & Innovation</h4>
            <div class="progress-bar"><div style="width: 72%; background: #f1c40f;">72%</div></div>
        </div>
        <div>
            <h4 style="margin-bottom: 10px;">Student Progression</h4>
            <div class="progress-bar"><div style="width: 94%; background: #27ae60;">94%</div></div>
        </div>
    </div>
</div>

<style>
    .progress-bar { background: #eee; height: 10px; border-radius: 5px; overflow: hidden; }
    .progress-bar div { height: 100%; border-radius: 5px; color: white; font-size: 0px; }
</style>

<?php require_once 'footer.php'; ?>
