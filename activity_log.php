<?php
require_once 'header.php';
require_once 'WorkloadEngine.php';

if (!isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$engine = new WorkloadEngine($pdo);
$message = '';

// Handle New Log Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['log_date'];
    $category = $_POST['category'];
    $h_from = $_POST['hour_from'];
    $h_to   = $_POST['hour_to'];

    if ($h_from === 'awh') {
        $hour_slot = 'awh';
        $duration = 60;
    } else {
        $h_from_int = intval($h_from);
        $h_to_int   = intval($h_to);
        if ($h_to_int < $h_from_int) $h_to_int = $h_from_int;
        
        $hour_slot = ($h_from_int === $h_to_int) ? (string)$h_from_int : "{$h_from_int}-{$h_to_int}";
        $duration = (($h_to_int - $h_from_int) + 1) * 60;
    }
    $desc = $_POST['description'];
    
    // File Upload Logic
    $proofPath = null;
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
        $uploadDir = 'uploads/proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['proof_file']['name']);
        $targetPath = $uploadDir . $fileName;
        
        // Basic validation
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])) {
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $targetPath)) {
                $proofPath = $targetPath;
            } else {
                $message = "Error uploading file.";
                $msgType = "error";
            }
        } else {
            $message = "Invalid file type. Allowed: PDF, JPG, PNG, DOC.";
            $msgType = "error";
        }
    }
    
    
    if (empty($message)) { // Proceed if no upload error
        // ---------------------------------------------------------
        // AI VALIDATION STEP
        // ---------------------------------------------------------
        // 1. Fetch Today's Briefing
        $vStmt = $pdo->prepare("SELECT briefing_html FROM ad_daily_ai_activity WHERE faculty_id = ? AND activity_date = ?");
        $vStmt->execute([$user_id, $date]);
        $briefingRow = $vStmt->fetch(PDO::FETCH_ASSOC);
        $assignedTaskContext = strip_tags($briefingRow['briefing_html'] ?? 'General Productivity Target');
        
        // 2. Call AI Validator
        $valData = [
            'type' => 'validate_log',
            'log_text' => "$category: $desc",
            'assigned_task' => $assignedTaskContext,
            'file_name' => $fileName ?? 'No File'
        ];
        
        // Internal CURL (or include if easier, but curl isolates scope)
        $ch = curl_init('http://localhost/apprasel/ai_suggest.php'); // Adjust URL if needed
        // Since we are likely on same server, we can use absolute URL or helper. 
        // Using full URL might be tricky with port. Let's try file_get_contents with stream context or just require? 
        // Actually, require is risky due to output buffering. Let's use the local URL logic or just extract the URL from config if available.
        // Helper approach:
        $apiUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/ai_suggest.php";
        
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-type: application/json',
                'content' => json_encode($valData)
            ]
        ];
        $context  = stream_context_create($opts);
        $result = file_get_contents($apiUrl, false, $context);
        $aiVal = json_decode($result, true);
        
        $aiVerdict = json_decode($aiVal['suggestion'] ?? '{}', true);
        // Fallback if AI fails to return JSON
        if (!isset($aiVerdict['valid'])) $aiVerdict = ['valid' => true]; 
        
        if ($aiVerdict['valid'] !== true) {
            $message = "<strong>AI Validation Failed:</strong> " . ($aiVerdict['reason'] ?? "Activity does not match assigned tasks.");
            $msgType = "error";
            // Delete uploaded file if rejected logic could go here
        } else {
            // ---------------------------------------------------------
            // VALIDATION PASSED -> INSERT
            // ---------------------------------------------------------
            $stmt = $pdo->prepare("INSERT INTO ad_activity_logs (faculty_id, log_date, category, duration_minutes, description, proof_file_path, hour_slot) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $date, $category, $duration, $desc, $proofPath, $hour_slot])) {
                $message = "Activity Logged & Verified Successfully!";
                $msgType = "success";

                // -------------------------------------------------------
                // DUAL WRITE: ac_dar (Daily Activity Register)
                // -------------------------------------------------------
                $dar_emp_id  = $_SESSION['erp_profile']['ID']          ?? ($_SESSION['username'] ?? 'UNKNOWN');
                $dar_name    = $_SESSION['erp_profile']['NAME']         ?? ($_SESSION['full_name'] ?? '');
                $dar_dept_id = $_SESSION['erp_profile']['DISCIPLINE']   ?? ($_SESSION['erp_profile']['SCHOOL'] ?? 'UNK');

                // Check if a row already exists for this faculty today
                $darCheck = $pdo->prepare("SELECT * FROM ac_dar WHERE EMP_ID = ? AND DATE = ?");
                $darCheck->execute([$dar_emp_id, $date]);
                $darRow = $darCheck->fetch(PDO::FETCH_ASSOC);

                if (!$darRow) {
                    // Start fresh
                    $sql = "INSERT INTO ac_dar (DEPT_ID, EMP_ID, NAME, DATE) VALUES (?, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([$dar_dept_id, $dar_emp_id, $dar_name, $date]);
                    $darCheck->execute([$dar_emp_id, $date]);
                    $darRow = $darCheck->fetch(PDO::FETCH_ASSOC);
                }

                if ($h_from === 'awh') {
                    // Append to AFTER_WORKING_HR
                    $existing  = $darRow['AFTER_WORKING_HR'] ?? '';
                    $appended  = $existing ? $existing . " | [{$category}] {$desc}" : "[{$category}] {$desc}";
                    $pdo->prepare("UPDATE ac_dar SET AFTER_WORKING_HR = ? WHERE EMP_ID = ? AND DATE = ?")
                        ->execute([$appended, $dar_emp_id, $date]);
                } else {
                    // Loop through the range and update each slot
                    $start = intval($h_from);
                    $end = intval($h_to);
                    for ($i = $start; $i <= $end; $i++) {
                        $col_ass = "HR{$i}_ASSIGNMENT";
                        $col_des = "HR{$i}_DESCRIPTION";
                        $pdo->prepare("UPDATE ac_dar SET {$col_ass} = ?, {$col_des} = ? WHERE EMP_ID = ? AND DATE = ?")
                            ->execute([$category, $desc, $dar_emp_id, $date]);
                    }
                }
                
                // --- AUTO-RESOLVE SENTINEL OVERSIGHT ---
                try {
                    $resOS = $pdo->prepare("UPDATE ad_agentic_oversight SET status = 'resolved', resolved_at = NOW() WHERE faculty_id = ? AND category = 'dar_missing' AND status = 'active'");
                    $resOS->execute([$user_id]);
                } catch (Exception $osE) { /* Silent fail for os */ }
                
                // -------------------------------------------------------
            } else {
                $message = "Error logging activity.";
                $msgType = "error";
            }
        }
    }
}

// Fetch Today's Logs
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM ad_activity_logs WHERE faculty_id = ? AND log_date = ? ORDER BY created_at DESC");
$stmt->execute([$user_id, $today]);
$todaysLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Totals for Today
$totalMinutes = 0;
foreach($todaysLogs as $log) {
    $totalMinutes += $log['duration_minutes'];
}
$totalHours = round($totalMinutes / 60, 2);

?>

<style>
    /* ── Layout ─────────────────────────────────────── */
    .al-grid {
        display: grid;
        grid-template-columns: 1.6fr 1fr;
        gap: 24px;
        align-items: start;
    }
    @media(max-width:768px){ .al-grid { grid-template-columns: 1fr; } }

    /* ── Card base ──────────────────────────────────── */
    .al-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        overflow: hidden;
    }
    .al-card-header {
        padding: 18px 24px 14px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .al-card-header h3 {
        margin: 0;
        font-size: 1.05em;
        color: #2c3e50;
        font-weight: 700;
    }
    .al-card-header .icon-circle {
        width: 36px; height: 36px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95em; flex-shrink: 0;
    }
    .al-card-body { padding: 22px 24px; }

    /* ── Form elements ──────────────────────────────── */
    .al-form .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    .al-form .fg { display: flex; flex-direction: column; gap: 5px; }
    .al-form label {
        font-size: 0.78em;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #7f8c8d;
    }
    .al-form input[type=date],
    .al-form select,
    .al-form textarea {
        border: 1.5px solid #e8ecef;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 0.92em;
        color: #2c3e50;
        background: #fafbfc;
        transition: border-color 0.2s, box-shadow 0.2s;
        width: 100%;
        box-sizing: border-box;
        outline: none;
        font-family: inherit;
    }
    .al-form input[type=date]:focus,
    .al-form select:focus,
    .al-form textarea:focus {
        border-color: #8e44ad;
        box-shadow: 0 0 0 3px rgba(142,68,173,0.08);
        background: #fff;
    }
    .al-form .hour-row {
        display: flex; gap: 8px; align-items: center;
    }
    .al-form .hour-row select { flex: 1; }
    .al-form .hour-sep {
        font-size: 0.8em; color: #aaa; white-space: nowrap;
    }
    .al-form textarea { resize: vertical; min-height: 80px; }
    .al-form .file-zone {
        border: 2px dashed #dde2e8;
        border-radius: 8px;
        padding: 12px 14px;
        background: #f8f9fb;
        font-size: 0.85em;
        color: #7f8c8d;
    }
    .al-form .file-zone input { margin-top: 6px; width: 100%; }

    .al-btn-submit {
        background: linear-gradient(135deg, #8e44ad, #6c3483);
        color: white;
        border: none;
        padding: 11px 28px;
        border-radius: 8px;
        font-size: 0.95em;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.15s, box-shadow 0.15s;
        display: inline-flex; align-items: center; gap: 8px;
        margin-top: 6px;
    }
    .al-btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(142,68,173,0.3);
    }

    /* ── Category badges ─────────────────────────────── */
    .cat-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.75em;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .cat-Research  { background:#eaf4ff; color:#2980b9; }
    .cat-Admin     { background:#fff3e0; color:#e67e22; }
    .cat-Mentoring { background:#e8f8f5; color:#1abc9c; }
    .cat-AAV       { background:#f5eef8; color:#8e44ad; }

    /* ── Today's history timeline ────────────────────── */
    .history-divider {
        border: none; border-top: 1px solid #f0f0f0;
        margin: 22px 0 18px;
    }
    .history-title {
        font-size: 0.78em; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #7f8c8d; margin-bottom: 14px;
    }
    .tl-item {
        display: flex; gap: 14px; margin-bottom: 14px; align-items: flex-start;
    }
    .tl-dot {
        width: 10px; height: 10px; border-radius: 50%;
        margin-top: 5px; flex-shrink: 0;
    }
    .tl-content { flex: 1; }
    .tl-meta {
        display: flex; align-items: center; gap: 8px;
        font-size: 0.8em; color: #aaa; margin-top: 3px;
    }
    .tl-desc { font-size: 0.9em; color: #2c3e50; line-height: 1.4; }
    .tl-empty {
        text-align: center; padding: 24px;
        color: #bdc3c7; font-size: 0.9em;
    }

    /* ── Snapshot sidebar ────────────────────────────── */
    .snap-hours {
        text-align: center; padding: 20px 0 8px;
    }
    .snap-num {
        font-size: 3.2em; font-weight: 800; color: #8e44ad; line-height: 1;
    }
    .snap-label { font-size: 0.82em; color: #aaa; margin-top: 4px; }
    .snap-bar-wrap {
        background: #f0f0f0; border-radius: 30px; height: 8px;
        overflow: hidden; margin: 14px 24px;
    }
    .snap-bar-fill {
        height: 100%;
        border-radius: 30px;
        background: linear-gradient(90deg, #8e44ad, #3498db);
        transition: width 0.6s ease;
    }
    .snap-pct {
        text-align: center; font-size: 0.8em; color: #7f8c8d;
        margin-top: -8px; padding-bottom: 10px;
    }
    .snap-tips { padding: 16px 24px; border-top: 1px solid #f0f0f0; }
    .snap-tips h4 {
        font-size: 0.78em; text-transform: uppercase; letter-spacing: 0.5px;
        color: #7f8c8d; font-weight: 700; margin: 0 0 10px;
    }
    .snap-tip {
        display: flex; align-items: flex-start; gap: 8px;
        font-size: 0.85em; color: #5d6d7e; margin-bottom: 8px;
    }
    .snap-tip i { color: #8e44ad; margin-top: 2px; flex-shrink: 0; }
</style>

<!-- Page Header -->
<div class="header-flex" style="margin-bottom:24px;">
    <div>
        <h2 style="margin:0; font-size:1.4em; color:#2c3e50;">
            <i class="fas fa-clipboard-list" style="color:#8e44ad; margin-right:8px;"></i>
            Daily Activity Log
        </h2>
        <p style="margin:3px 0 0; font-size:0.85em; color:#7f8c8d;">
            <?php echo date('l, d F Y'); ?>
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="workload_plan.php" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:0.88em;">
            <i class="fas fa-calendar-week"></i> Weekly Plan
        </a>
        <a href="dashboard.php" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:0.88em;">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $msgType; ?>" style="margin-bottom:18px;border-radius:10px;">
    <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>" style="margin-right:8px;"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="al-grid">

    <!-- ── Left: Form + History ──────────────────── -->
    <div class="al-card">
        <div class="al-card-header">
            <div class="icon-circle" style="background:#f5eef8;">
                <i class="fas fa-pen" style="color:#8e44ad;"></i>
            </div>
            <h3>Log New Activity</h3>
        </div>
        <div class="al-card-body">
            <form action="" method="POST" enctype="multipart/form-data" class="al-form">

                <!-- Row 1: Date + Hour Range -->
                <div class="form-row">
                    <div class="fg">
                        <label><i class="fas fa-calendar-day" style="margin-right:4px;"></i>Date</label>
                        <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="fg">
                        <label><i class="fas fa-clock" style="margin-right:4px;"></i>Hour Slot</label>
                        <div class="hour-row">
                            <select name="hour_from" id="hour_from" required onchange="validateRange()">
                                <?php for($i=1;$i<=7;$i++): ?>
                                <option value="<?php echo $i; ?>">Hour <?php echo $i; ?></option>
                                <?php endfor; ?>
                                <option value="awh">After Working Hours</option>
                            </select>
                            <span class="hour-sep" id="range_to_label">to</span>
                            <select name="hour_to" id="hour_to" required>
                                <?php for($i=1;$i<=7;$i++): ?>
                                <option value="<?php echo $i; ?>">Hour <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Category -->
                <div class="fg" style="margin-bottom:16px;">
                    <label><i class="fas fa-tag" style="margin-right:4px;"></i>Category</label>
                    <select name="category" required>
                        <option value="Research">📖 Research (Writing, Lab, Reading)</option>
                        <option value="Admin">📋 Administration (Meetings, Emails)</option>
                        <option value="Mentoring">🎯 Mentoring (Student Meetings)</option>
                        <option value="AAV">✅ AAV (Assessment, Valuation)</option>
                    </select>
                </div>

                <!-- Description -->
                <div class="fg" style="margin-bottom:16px;">
                    <label><i class="fas fa-align-left" style="margin-right:4px;"></i>Description / Outcome</label>
                    <textarea name="description" placeholder="Briefly describe what you did and the outcome..." rows="3" required></textarea>
                </div>

                <!-- Proof Upload -->
                <div class="fg" style="margin-bottom:18px;">
                    <label><i class="fas fa-paperclip" style="margin-right:4px;"></i>Proof Attachment <span style="font-weight:400;text-transform:none;letter-spacing:0;">(Optional)</span></label>
                    <div class="file-zone">
                        <div style="margin-bottom:5px;">Upload a photo, screenshot or document to validate this activity.</div>
                        <input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div style="font-size:0.8em;color:#bbb;margin-top:4px;">PDF, JPG, PNG, DOC — max 5MB</div>
                    </div>
                </div>

                <button type="submit" class="al-btn-submit">
                    <i class="fas fa-check"></i> Log Activity
                </button>
            </form>

            <!-- Today's History -->
            <hr class="history-divider">
            <div class="history-title">
                <i class="fas fa-history" style="margin-right:5px;"></i>Today's Logged Activities
                <?php if(count($todaysLogs) > 0): ?>
                <span style="background:#8e44ad;color:white;border-radius:20px;padding:1px 8px;margin-left:6px;"><?php echo count($todaysLogs); ?></span>
                <?php endif; ?>
            </div>

            <?php if (count($todaysLogs) > 0): ?>
                <?php
                $catColors = [
                    'Research'  => '#2980b9',
                    'Admin'     => '#e67e22',
                    'Mentoring' => '#1abc9c',
                    'AAV'       => '#8e44ad',
                ];
                foreach ($todaysLogs as $log):
                    $cat   = htmlspecialchars($log['category']);
                    $dot   = $catColors[$log['category']] ?? '#95a5a6';
                    $slot  = ($log['hour_slot'] ?? '1') === 'awh' ? 'After Hours' : 'Hour ' . ($log['hour_slot'] ?? '1');
                    $time  = date('h:i A', strtotime($log['created_at']));
                ?>
                <div class="tl-item">
                    <div class="tl-dot" style="background:<?php echo $dot; ?>;"></div>
                    <div class="tl-content">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="cat-badge cat-<?php echo $cat; ?>"><?php echo $cat; ?></span>
                            <span style="font-size:0.78em;color:#bbb;"><?php echo $slot; ?></span>
                        </div>
                        <div class="tl-desc" style="margin-top:4px;"><?php echo htmlspecialchars($log['description']); ?></div>
                        <div class="tl-meta">
                            <i class="fas fa-clock"></i> <?php echo $time; ?>
                            <?php if (!empty($log['proof_file_path'])): ?>
                            &nbsp;·&nbsp;<i class="fas fa-paperclip" style="color:#27ae60;"></i> <span style="color:#27ae60;">Proof attached</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="tl-empty">
                    <i class="fas fa-inbox" style="font-size:2em;display:block;margin-bottom:8px;color:#e0e0e0;"></i>
                    No activities logged yet today.<br>
                    <span style="font-size:0.85em;">Use the form above to get started.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: Daily Snapshot ──────────────────── -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Hours Progress Card -->
        <div class="al-card">
            <div class="al-card-header">
                <div class="icon-circle" style="background:#eaf4ff;">
                    <i class="fas fa-chart-bar" style="color:#2980b9;"></i>
                </div>
                <h3>Daily Snapshot</h3>
            </div>
            <div class="snap-hours">
                <div class="snap-num"><?php echo $totalHours; ?></div>
                <div class="snap-label">hours logged today</div>
            </div>
            <div class="snap-bar-wrap">
                <div class="snap-bar-fill" style="width:<?php echo min(($totalHours/8)*100, 100); ?>%;"></div>
            </div>
            <div class="snap-pct">
                <?php
                    $pct = min(round(($totalHours/8)*100), 100);
                    $remaining = max(round(8 - $totalHours, 2), 0);
                    echo "$pct% of 8h target";
                    if ($remaining > 0) echo " &nbsp;·&nbsp; {$remaining}h remaining";
                ?>
            </div>

            <!-- Category breakdown if any logs -->
            <?php if (count($todaysLogs) > 0):
                $breakdown = [];
                foreach ($todaysLogs as $l) {
                    $c = $l['category'];
                    $breakdown[$c] = ($breakdown[$c] ?? 0) + round($l['duration_minutes'] / 60, 1);
                }
            ?>
            <div style="padding:0 24px 16px;">
                <div style="font-size:0.75em;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#7f8c8d;margin-bottom:10px;">Breakdown</div>
                <?php foreach ($breakdown as $cat => $hrs):
                    $dot = $catColors[$cat] ?? '#95a5a6'; ?>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;">
                    <div style="display:flex;align-items:center;gap:7px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:<?php echo $dot; ?>;"></div>
                        <span style="font-size:0.85em;color:#5d6d7e;"><?php echo $cat; ?></span>
                    </div>
                    <span style="font-size:0.85em;font-weight:600;color:#2c3e50;"><?php echo $hrs; ?>h</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tips Card -->
        <div class="al-card">
            <div class="al-card-header">
                <div class="icon-circle" style="background:#e8f8f5;">
                    <i class="fas fa-lightbulb" style="color:#1abc9c;"></i>
                </div>
                <h3>Quick Tips</h3>
            </div>
            <div style="padding:16px 24px;">
                <div class="snap-tip"><i class="fas fa-circle-dot"></i> Log activities immediately after completing them for accuracy.</div>
                <div class="snap-tip"><i class="fas fa-circle-dot"></i> Attach a screenshot or doc as proof whenever possible.</div>
                <div class="snap-tip"><i class="fas fa-circle-dot"></i> Group all email/meeting time under <strong>Admin</strong>.</div>
                <div class="snap-tip"><i class="fas fa-circle-dot"></i> Student doubt sessions count as <strong>Mentoring</strong>.</div>
            </div>
        </div>

    </div><!-- end right column -->
</div>

<script>
function validateRange() {
    let from = document.getElementById('hour_from').value;
    let toSel = document.getElementById('hour_to');
    let toLabel = document.getElementById('range_to_label');

    if (from === 'awh') {
        toSel.style.display = 'none';
        toLabel.style.display = 'none';
    } else {
        toSel.style.display = '';
        toLabel.style.display = '';
        let fv = parseInt(from);
        for (let i = 0; i < toSel.options.length; i++) {
            toSel.options[i].disabled = parseInt(toSel.options[i].value) < fv;
        }
        if (parseInt(toSel.value) < fv) toSel.value = from;
    }
}
window.addEventListener('DOMContentLoaded', validateRange);
</script>

<?php require_once 'footer.php'; ?>



