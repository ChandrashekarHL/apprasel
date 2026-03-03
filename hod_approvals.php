<?php
require_once 'header.php';
require_once 'WorkloadEngine.php';

if (!isLoggedIn() || $_SESSION['role'] == 'Faculty') {
   // header("Location: dashboard.php");
}

$engine = new WorkloadEngine($pdo);
$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $plan_id = $_POST['plan_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $reason = $_POST['reject_reason'] ?? '';
    
    $newStatus = ($action == 'approve') ? 'Approved' : 'Rejected'; // SRS: Approved plans are LOCKED
    if ($newStatus == 'Approved') $newStatus = 'Locked'; 
    
    $sql = "UPDATE ad_workload_plans SET status = ?, rejection_reason = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$newStatus, $reason, $plan_id])) {
        $message = "Plan " . ucfirst($action) . " successfully.";
        
        // Audit Log
        $auditSql = "INSERT INTO ad_plan_audit_logs (plan_id, action_by, action_type, comment) VALUES (?, ?, ?, ?)";
        $pdo->prepare($auditSql)->execute([$plan_id, $_SESSION['user_id'], ($action=='approve'?'Approved':'Rejected'), $reason]);
    }
}

// Fetch Pending Plans
// Filter by HOD Department
$currentUser = getCurrentUser($pdo);
$dept = $currentUser['department'];

$sql = "SELECT p.*, u.full_name, u.department, u.designation 
        FROM ad_workload_plans p 
        JOIN ad_faculty_users u ON p.faculty_id = u.id 
        WHERE p.status = 'Submitted' 
        AND (u.department = ? OR u.school = ?)
        ORDER BY p.week_start_date ASC, u.full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dept, $dept]);
$pendingPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
    .approval-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        margin-bottom: 15px;
        padding: 20px;
        border-left: 4px solid #f1c40f;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .plan-meta { color: #7f8c8d; font-size: 0.9em; margin-bottom: 5px; }
    .plan-stats { display: flex; gap: 15px; margin-top: 10px; font-weight: 500; font-size: 0.9em; }
    .stat-item { background: #f8f9fa; padding: 3px 8px; border-radius: 4px; }
    
    .reject-box {
        display: none; /* Toggled via JS */
        margin-top: 10px;
    }
</style>

<div class="header-flex">
    <h2>Workload Plan Approvals</h2>
    <a href="hod_dashboard.php" class="btn btn-secondary">&larr; Dashboard</a>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<div class="form-container" style="background: transparent; box-shadow: none; padding: 0;">
    <?php if (empty($pendingPlans)): ?>
        <div class="alert alert-info">No pending plans to review. Great job!</div>
    <?php else: ?>
        <?php foreach ($pendingPlans as $plan): ?>
        <div class="approval-card">
            <div style="flex: 1;">
                <h4 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($plan['full_name']); ?> <span style="font-weight: normal; color: #777;">(<?php echo htmlspecialchars($plan['department']); ?>)</span></h4>
                <div class="plan-meta">Week of <?php echo date('M d, Y', strtotime($plan['week_start_date'])); ?></div>
                
                <?php if(!empty($plan['timetable_constraints'])): ?>
                    <div style="margin-top: 5px; color: #d35400; font-size: 0.85em;">
                        <strong><i class="fas fa-exclamation-circle"></i> Constraints:</strong> 
                        <?php echo htmlspecialchars($plan['timetable_constraints']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="plan-stats">
                    <span class="stat-item">Teaching: <?php echo $plan['planned_teaching_hrs']; ?>h</span>
                    <span class="stat-item">Research: <?php echo $plan['planned_research_hrs']; ?>h</span>
                    <span class="stat-item">Admin: <?php echo $plan['planned_admin_hrs']; ?>h</span>
                    <span class="stat-item">Total: <?php echo ($plan['planned_teaching_hrs']+$plan['planned_research_hrs']+$plan['planned_admin_hrs']+$plan['planned_mentoring_hrs']+$plan['planned_aav_hrs']); ?>h</span>
                </div>
            </div>
            
            <div style="width: 300px; text-align: right;">
                <form method="POST">
                    <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                    <div id="btns-<?php echo $plan['id']; ?>">
                        <button type="submit" name="action" value="approve" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                        <button type="button" onclick="showReject(<?php echo $plan['id']; ?>)" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                    </div>
                    <div id="reject-<?php echo $plan['id']; ?>" class="reject-box">
                        <textarea name="reject_reason" placeholder="Reason for rejection..." style="width: 100%; border: 1px solid #e74c3c; padding: 5px; font-size: 0.9em; margin-bottom: 5px;"></textarea>
                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" style="width: 100%;">Confirm Reject</button>
                        <a href="#" onclick="hideReject(<?php echo $plan['id']; ?>); return false;" style="font-size: 0.8em; color: #777;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function showReject(id) {
    document.getElementById('btns-'+id).style.display = 'none';
    document.getElementById('reject-'+id).style.display = 'block';
}
function hideReject(id) {
    document.getElementById('btns-'+id).style.display = 'block';
    document.getElementById('reject-'+id).style.display = 'none';
}
</script>

<?php require_once 'footer.php'; ?>
