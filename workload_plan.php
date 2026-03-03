<?php
require_once 'header.php';
require_once 'WorkloadEngine.php';

if (!isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$engine = new WorkloadEngine($pdo);

// Get Next Monday or Current Week Start
$today = new DateTime();
// If today is Sunday/Saturday, maybe plan for next week? 
// Let's assume standard 'Current Week' for now or 'Next Week' if user selects.
// Simple default: Previous Monday (if today is Mon) or Previous Monday.
$weekStart = clone $today;
$weekStart->modify('this week monday');
$weekStartStr = $weekStart->format('Y-m-d');

// Fetch existing plan
$plan = $engine->getWeeklyPlan($user_id, $weekStartStr);
$isLocked = ($plan && $plan['status'] == 'Locked');

// Fetch Targets
$targets = $engine->getFacultyTargets($user_id);
$hasGroup = !empty($targets['group_code']);

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isLocked && $hasGroup) {
    $teaching = $_POST['teaching'];
    $research = $_POST['research'];
    $admin = $_POST['admin'];
    $mentoring = $_POST['mentoring'];
    $aav = $_POST['aav'];
    $constraints = $_POST['constraints'] ?? '';
    $action = $_POST['action']; // 'draft' or 'submit'
    
    // AUTO-APPROVAL LOGIC
    // Previously: $status = ($action == 'submit') ? 'Submitted' : 'Draft';
    // Now: Directly 'Approved' on submit, based on Group rules.
    $status = ($action == 'submit') ? 'Approved' : 'Draft';
    
    $total = $teaching + $research + $admin + $mentoring + $aav;
    
    if ($action == 'submit' && ($total > 45 || $total < 35)) {
        $message = "Warning: Total hours ($total) should be closer to 40 for final submission.";
        $msgType = "warning";
        // We allow it but warn
    }
    
    // Save to DB
    $stmt = $pdo->prepare("
        INSERT INTO ad_workload_plans 
        (faculty_id, week_start_date, planned_teaching_hrs, planned_research_hrs, planned_admin_hrs, planned_mentoring_hrs, planned_aav_hrs, timetable_constraints, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        planned_teaching_hrs=VALUES(planned_teaching_hrs),
        planned_research_hrs=VALUES(planned_research_hrs),
        planned_admin_hrs=VALUES(planned_admin_hrs),
        planned_mentoring_hrs=VALUES(planned_mentoring_hrs),
        planned_aav_hrs=VALUES(planned_aav_hrs),
        timetable_constraints=VALUES(timetable_constraints),
        status=VALUES(status)
    ");
    $stmt->execute([$user_id, $weekStartStr, $teaching, $research, $admin, $mentoring, $aav, $constraints, $status]);
    
    if ($action == 'submit') {
        $message = "Plan Automatically Approved (Group Mandate Verified).";
        $msgType = "success";
        // Log Audit
        // $engine->logAudit(); // TODO
    } else {
        $message = "Draft Saved.";
        $msgType = "info";
    }
    
    // Refresh Plan
    $plan = $engine->getWeeklyPlan($user_id, $weekStartStr);
}

// Defaults
// Dynamic Defaults based on Group Targets (if no plan exists)
$val_teaching = $plan['planned_teaching_hrs'] ?? (40 * (($targets['target_teaching'] ?? 40)/100));
$val_research = $plan['planned_research_hrs'] ?? (40 * (($targets['target_research'] ?? 25)/100));
$val_admin = $plan['planned_admin_hrs'] ?? (40 * (($targets['target_admin'] ?? 10)/100));
$val_mentoring = $plan['planned_mentoring_hrs'] ?? (40 * (($targets['target_mentoring'] ?? 15)/100));
$val_aav = $plan['planned_aav_hrs'] ?? (40 * (($targets['target_aav'] ?? 10)/100));

?>

<style>
    .workload-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .planning-card {
        background: #fff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #eee;
    }
    .slider-container {
        margin-bottom: 25px;
    }
    .slider-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c3e50;
    }
    .total-tracker {
        font-size: 1.5em;
        font-weight: bold;
        text-align: center;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #ddd;
    }
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: bold;
        text-transform: uppercase;
    }
    .status-Draft { background: #ffeaa7; color: #d35400; }
    .status-Submitted { background: #55efc4; color: #00b894; }
</style>

<div class="header-flex">
    <h2>Weekly Workload Plan</h2>
    <a href="dashboard.php" class="btn btn-secondary">&larr; Back</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $msgType ?? 'info'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="workload-grid">
    <!-- Left: The Planning Form -->
    <div class="planning-card">
        <?php if (!$hasGroup): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-lock" style="font-size: 3em; color: #e67e22; margin-bottom: 20px;"></i>
                <h3 style="color: #2c3e50;">Planning Restricted</h3>
                <p class="alert alert-warning" style="margin-top: 15px;">
                    <strong>Workload policy not assigned by HOD.</strong><br>
                    You cannot plan your workload until your HOD assigns you to a specific workload group (e.g., Teaching-Focused or Research-Focused).
                </p>
                <p style="color: #7f8c8d; font-size: 0.9em; margin-top: 10px;">
                    Please contact your Head of Department to proceed.
                </p>
            </div>
        <?php else: ?>
            <h3>
                Week of <?php echo date('M d, Y', strtotime($weekStartStr)); ?>
                <?php if(isset($plan['status'])): ?>
                    <span class="status-badge status-<?php echo $plan['status']; ?>" style="float: right;"><?php echo $plan['status']; ?></span>
                <?php endif; ?>
            </h3>
            
            <form method="POST">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <button type="button" onclick="autoFillPlan()" class="btn btn-secondary" style="font-size: 0.85em; display: flex; align-items: center; gap: 5px;">
                        <i class="fas fa-magic"></i> Auto-Generate Rules
                    </button>
                    <div class="total-tracker" id="totalHoursBox" style="margin: 0; padding: 10px 20px; font-size: 1.2em;">
                        <span id="totalHours">0</span> / 40 Hrs
                    </div>
                </div>

                <!-- Hidden Inputs for Targets to use in JS -->
                <input type="hidden" id="tgt_teaching" value="<?php echo 40 * (($targets['target_teaching'] ?? 0)/100); ?>">
                <input type="hidden" id="tgt_research" value="<?php echo 40 * (($targets['target_research'] ?? 0)/100); ?>">
                <input type="hidden" id="tgt_admin" value="<?php echo 40 * (($targets['target_admin'] ?? 0)/100); ?>">
                <input type="hidden" id="tgt_mentoring" value="<?php echo 40 * (($targets['target_mentoring'] ?? 0)/100); ?>">
                <input type="hidden" id="tgt_aav" value="<?php echo 40 * (($targets['target_aav'] ?? 0)/100); ?>">

                <div class="slider-container">
                    <div class="slider-label">
                        <span>Teaching (Hours)</span>
                        <span id="val_teaching"><?php echo $val_teaching; ?>h</span>
                    </div>
                    <input type="range" name="teaching" id="inp_teaching" min="0" max="40" step="0.5" value="<?php echo $val_teaching; ?>" oninput="updateTotal()">
                    <small>Lectures, Tutorials, Labs</small>
                </div>

                <div class="slider-container">
                    <div class="slider-label">
                        <span>Research & Innovation</span>
                        <span id="val_research"><?php echo $val_research; ?>h</span>
                    </div>
                    <input type="range" name="research" id="inp_research" min="0" max="40" step="0.5" value="<?php echo $val_research; ?>" oninput="updateTotal()">
                    <small>Publications, Proposals, Patents</small>
                </div>

                <div class="slider-container">
                    <div class="slider-label">
                        <span>Administration</span>
                        <span id="val_admin"><?php echo $val_admin; ?>h</span>
                    </div>
                    <input type="range" name="admin" id="inp_admin" min="0" max="40" step="0.5" value="<?php echo $val_admin; ?>" oninput="updateTotal()">
                    <small>Committees, Coord. Duties</small>
                </div>
                
                <div class="slider-container">
                    <div class="slider-label">
                        <span>Mentoring & Counseling</span>
                        <span id="val_mentoring"><?php echo $val_mentoring; ?>h</span>
                    </div>
                    <input type="range" name="mentoring" id="inp_mentoring" min="0" max="40" step="0.5" value="<?php echo $val_mentoring; ?>" oninput="updateTotal()">
                    <small>Student Meetings, Guidance</small>
                </div>

                <div class="slider-container">
                    <div class="slider-label">
                        <span>Academic Prep (AAV)</span>
                        <span id="val_aav"><?php echo $val_aav; ?>h</span>
                    </div>
                    <input type="range" name="aav" id="inp_aav" min="0" max="40" step="0.5" value="<?php echo $val_aav; ?>" oninput="updateTotal()">
                    <small>Evaluation, Assessment, Prep</small>
                </div>
                
                <hr>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">Timetable Constraints / Availability</label>
                    <textarea name="constraints" class="form-control" rows="3" placeholder="E.g. Cannot take classes on Mon 10-11 (Research Meet). Prefer post-lunch slots." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"><?php echo htmlspecialchars($plan['timetable_constraints'] ?? ''); ?></textarea>
                </div>

                <?php if ($plan && $plan['status'] == 'Rejected'): ?>
                <div class="alert alert-danger">
                    <strong>Plan Rejected:</strong> <?php echo htmlspecialchars($plan['rejection_reason']); ?>
                    <br><small>Please update and resubmit.</small>
                </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="action" value="draft" class="btn btn-secondary" style="flex: 1;">Save Draft</button>
                    <button type="submit" name="action" value="submit" id="submitBtn" class="btn btn-success" style="flex: 1;">Submit for Approval</button>
                </div>
                <p id="validationMsg" style="text-align: center; color: red; font-size: 0.9em; margin-top: 10px; display: none;">Total hours must be exactly 40 to submit.</p>
            </form>
        <?php endif; ?>
    </div>

    <!-- Right: Policy & Guidance -->
    <div class="planning-card" style="background: #fbfbfb;">
        <h3>Policy Guidelines</h3>
        <p><strong>Standard 40-Hour Work Week Protocol</strong></p>
        <ul>
            <li><strong>Teaching:</strong> Includes direct contact hours + 1 hour prep per lecture.</li>
            <li><strong>Research:</strong> Minimum 10 hours blocked for Associate Profs & above.</li>
            <li><strong>Mentoring:</strong> Minimum 2 hours/week mandatory for all faculty.</li>
        </ul>
        
        <hr>
        
        <h4>Your Allocation: <?php echo htmlspecialchars($targets['group_name'] ?? 'General Faculty'); ?> (Group <?php echo $targets['group_code'] ?? 'N/A'; ?>)</h4>
        <div id="roleTemplate">
            <?php if($hasGroup): ?>
            <p><?php echo htmlspecialchars($targets['description']); ?></p>
            <p><strong>Mandatory Targets (Weekly 40h):</strong></p>
            <ul style="padding-left: 20px;">
                <li>Teaching: <?php echo intval($targets['target_teaching']); ?>% (<?php echo 40 * ($targets['target_teaching']/100); ?>h)</li>
                <li>Research: <?php echo intval($targets['target_research']); ?>% (<?php echo 40 * ($targets['target_research']/100); ?>h)</li>
                <li>Admin: <?php echo intval($targets['target_admin']); ?>% (<?php echo 40 * ($targets['target_admin']/100); ?>h)</li>
                <li>Mentoring: <?php echo intval($targets['target_mentoring']); ?>% (<?php echo 40 * ($targets['target_mentoring']/100); ?>h)</li>
                 <li>AAV (Prep): <?php echo intval($targets['target_aav']); ?>% (<?php echo 40 * ($targets['target_aav']/100); ?>h)</li>
            </ul>
            <?php else: ?>
                <p class="alert alert-warning">Workload policy not assigned by HOD. Please contact your HOD to assign a workload group.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function autoFillPlan() {
    const fields = ['teaching', 'research', 'admin', 'mentoring', 'aav'];
    
    fields.forEach(f => {
        const targetVal = parseFloat(document.getElementById('tgt_' + f).value) || 0;
        document.getElementById('inp_' + f).value = targetVal;
    });
    
    // Auto-fill might exceed 40 due to rounding. Let's normalize.
    restrictTotal(document.getElementById('inp_teaching')); 
}

// New: Enforce Hard Cap on Input Change
const sliders = document.querySelectorAll('input[type="range"]');
sliders.forEach(slider => {
    slider.addEventListener('input', function() {
        restrictTotal(this);
    });
});

function restrictTotal(changedInput) {
    const inputs = document.querySelectorAll('input[type="range"]');
    let sumOthers = 0;
    
    inputs.forEach(input => {
        if (input !== changedInput) {
            sumOthers += parseFloat(input.value);
        }
    });
    
    const maxAllowed = 40 - sumOthers;
    let currentVal = parseFloat(changedInput.value);
    
    if (currentVal > maxAllowed) {
        // Clamp it
        currentVal = Math.max(0, maxAllowed); // Ensure no negative
        changedInput.value = currentVal; 
        
        // Show temporary toast or feedback?
        // document.getElementById('validationMsg').innerText = "Max limit reached. Reduce other piles to increase this.";
        // document.getElementById('validationMsg').style.display = 'block';
    }
    
    updateTotal();
}

function updateTotal() {
    const inputs = document.querySelectorAll('input[type="range"]');
    let total = 0;
    inputs.forEach(input => {
        const val = parseFloat(input.value);
        total += val;
        // Update label text
        document.getElementById('val_' + input.name).innerText = val + 'h';
    });
    
    // Round to avoid float errors ex: 40.0000001
    total = Math.round(total * 10) / 10;
    
    const totalEl = document.getElementById('totalHours');
    totalEl.innerText = total;
    
    const box = document.getElementById('totalHoursBox');
    const btn = document.getElementById('submitBtn');
    const msg = document.getElementById('validationMsg');
    
    if (total === 40) {
        box.style.color = 'green';
        box.style.borderColor = 'green';
        box.style.background = '#e8f5e9';
        
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        msg.style.display = 'none';
        
    } else {
        // Technically this block is less reachable now with Hard Cap,
        // but useful if AutoFill or initial state is weird.
        if (total > 40) {
            box.style.color = 'red';
            box.style.borderColor = 'red';
            box.style.background = '#ffebee';
            msg.innerText = "Error: Total exceeds 40 hours (" + total + "h). Reduce others.";
        } else {
            box.style.color = '#e67e22'; 
            box.style.borderColor = '#e67e22';
            box.style.background = '#fef9e7';
            msg.innerText = "Add " + (40 - total).toFixed(1) + "h to reach 40h.";
        }
        
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        msg.style.display = 'block';
    }
}

// Init
updateTotal();
</script>

<?php require_once 'footer.php'; ?>
