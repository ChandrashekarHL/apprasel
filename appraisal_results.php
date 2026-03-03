<?php
require_once 'header.php';
require_once 'WorkloadEngine.php';

if (!isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$engine = new WorkloadEngine($pdo);

// Calculate Metrics (raw values for comparison)
$faei = $engine->calculateFAEI($user_id) * 10;
$tui_raw = $engine->calculateTUI($user_id) * 100;
$wfr_raw = $engine->calculateWFR($user_id) * 100;
$acs_raw = $engine->calculateACS($user_id) * 10;
$rrf_raw = $engine->calculateRRF($user_id) * 10;

// Display versions
$faei_display = number_format($faei, 1);
$tui = $tui_raw . '%';
$wfr = $wfr_raw . '%';
$acs = number_format($acs_raw, 1) . '/10';
$rrf = number_format($rrf_raw, 1) . '/10';

// Detect Low Performance Areas (triggers AI)
$lowAreas = [];
if ($faei < 7) $lowAreas[] = 'Overall FAEI';
if ($tui_raw < 70) $lowAreas[] = 'Time Utilization';
if ($wfr_raw < 70) $lowAreas[] = 'Workload Fulfilment';
if ($acs_raw < 6) $lowAreas[] = 'Academic Contribution';
if ($rrf_raw < 6) $lowAreas[] = 'Role Responsibility';

$shouldTriggerAI = !empty($lowAreas);

// Get faculty info for AI context
$targets = $engine->getFacultyTargets($user_id);
$groupCode = $targets['group_code'] ?? 'General';
$user = getCurrentUser($pdo);
?>

<style>
    .metrics-container {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    .metric-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #eee;
    }
    .metric-value {
        font-size: 2.5em;
        font-weight: bold;
        color: var(--primary-color);
        margin: 10px 0;
    }
    .metric-label {
        font-weight: 600;
        color: #7f8c8d;
        font-size: 0.9em;
    }
    .faei-display {
        background: #2c3e50;
        color: white;
        padding: 40px;
        border-radius: 15px;
        text-align: center;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    .faei-score {
        font-size: 4em;
        font-weight: 800;
        color: #f1c40f;
    }
    .grade-badge {
        background: rgba(255,255,255,0.2);
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 1.2em;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>

<div class="header-flex">
    <h2>Academic Effectiveness Insights</h2>
    <a href="dashboard.php" class="btn btn-secondary">&larr; Back</a>
</div>

<!-- Main FAEI Score -->
<div class="faei-display">
    <h3 style="margin: 0; opacity: 0.8; color: white;">Faculty Academic Effectiveness Index (FAEI)</h3>
    <div class="faei-score"><?php echo $faei_display; ?> <span style="font-size: 0.3em; opacity: 0.5;">/ 10</span></div>
    
    <?php
    $grade = 'B';
    if ($faei_display >= 9) $grade = 'A+ (Outstanding)';
    elseif ($faei_display >= 8) $grade = 'A (Excellent)';
    elseif ($faei_display >= 7) $grade = 'B (Good)';
    elseif ($faei_display >= 5) $grade = 'C (Satisfactory)';
    else $grade = 'D (Needs Improvement)';
    ?>
    <span class="grade-badge"><?php echo $grade; ?></span>
    <p style="margin-top: 15px; max-width: 600px; margin-left: auto; margin-right: auto; opacity: 0.8;">
        Your FAEI is a composite score driven by your Time Utilization, Workload Fulfilment, Academic Contribution, and Role Responsibility.
    </p>
</div>

<!-- Detailed Metrics -->
<div class="metrics-container">
    <div class="metric-card">
        <div class="metric-label">Time Utilization (TUI)</div>
        <div class="metric-value" style="color: #e67e22;"><?php echo $tui; ?></div>
        <small>Planned vs Executed</small>
    </div>
    <div class="metric-card">
        <div class="metric-label">Workload Fulfilment (WFR)</div>
        <div class="metric-value" style="color: #27ae60;"><?php echo $wfr; ?></div>
        <small>Role Expectations</small>
    </div>
    <div class="metric-card">
        <div class="metric-label">Contribution Score (ACS)</div>
        <div class="metric-value" style="color: #2980b9;"><?php echo $acs; ?></div>
        <small>Research & Outcomes</small>
    </div>
    <div class="metric-card">
        <div class="metric-label">Role Responsibility (RRF)</div>
        <div class="metric-value" style="color: #8e44ad;"><?php echo $rrf; ?></div>
        <small>Admin & Compliance</small>
    </div>
</div>

<div class="form-container">
    <h3>Improvement Suggestions</h3>
    <ul>
        <li><strong>Boost TUI:</strong> Your execution is <?php echo ($tui < 8) ? 'lagging behind' : 'matching'; ?> your plan. Try to log activities daily.</li>
        <li><strong>Enhance ACS:</strong> Add more Research publications to increase your Contribution score.</li>
    </ul>
</div>

<!-- AI Performance Coach Modal -->
<div id="aiCoachModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 12000; justify-content: center; align-items: center;">
    <div style="background: white; width: 700px; max-width: 90%; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.4); animation: modalSlideIn 0.4s ease-out;">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #e74c3c, #c0392b); padding: 30px; color: white; display: flex; align-items: center; gap: 20px;">
            <div style="background: rgba(255,255,255,0.2); width: 70px; height: 70px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 35px;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 1.6em;">Performance Improvement Coach</h2>
                <p style="margin: 5px 0 0; opacity: 0.9; font-size: 0.95em;">AI-Powered Personalized Recommendations</p>
            </div>
        </div>
        
        <!-- Alert Banner -->
        <?php if ($shouldTriggerAI): ?>
        <div style="background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px 20px; display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-exclamation-triangle" style="color: #f39c12; font-size: 24px;"></i>
            <div style="flex: 1;">
                <strong style="color: #856404;">Areas Requiring Attention:</strong>
                <p style="margin: 5px 0 0; color: #856404; font-size: 0.95em;">
                    <?php echo implode(', ', $lowAreas); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- AI Suggestions Content -->
        <div id="aiSuggestionsContent" style="padding: 30px; min-height: 250px; max-height: 400px; overflow-y: auto; color: #2c3e50; line-height: 1.7;">
            <div style="text-align: center; padding: 60px 20px; color: #95a5a6;">
                <i class="fas fa-robot fa-spin fa-3x" style="margin-bottom: 20px; color: #3498db;"></i><br>
                <strong style="font-size: 1.1em;">Analyzing your performance data...</strong><br>
                <small>Generating personalized improvement strategies</small>
            </div>
        </div>
        
        <!-- Footer Actions -->
        <div style="background: #f8f9fa; padding: 20px; text-align: right; border-top: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
            <small style="color: #7f8c8d;"><i class="fas fa-magic"></i> Powered by AI Analytics</small>
            <div style="display: flex; gap: 10px;">
                <button onclick="dismissCoach()" style="background: #95a5a6; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 1em; transition: background 0.3s;">
                    Maybe Later
                </button>
                <button onclick="acceptCoachingSuggestions()" id="btnAcceptCoach" disabled style="background: #27ae60; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-size: 1em; opacity: 0.5; transition: all 0.3s;">
                    <i class="fas fa-check"></i> I'll Work On This
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const shouldTrigger = <?php echo $shouldTriggerAI ? 'true' : 'false'; ?>;
    
    if (shouldTrigger) {
        // Auto-trigger AI coach after 1.5 seconds
        setTimeout(() => {
            const modal = document.getElementById('aiCoachModal');
            if (modal) {
                modal.style.display = 'flex';
                generateAISuggestions();
            }
        }, 1500);
    }
});

function generateAISuggestions() {
    const facultyName = <?php echo json_encode($user['full_name']); ?>;
    const groupCode = <?php echo json_encode($groupCode); ?>;
    const faei = <?php echo $faei; ?>;
    const tui = <?php echo $tui_raw; ?>;
    const wfr = <?php echo $wfr_raw; ?>;
    const acs = <?php echo $acs_raw; ?>;
    const rrf = <?php echo $rrf_raw; ?>;
    const lowAreas = <?php echo json_encode($lowAreas); ?>;
    
    fetch('ai_suggest.php', {
        method: 'POST',
        body: JSON.stringify({
            type: 'performance_coach',
            name: facultyName,
            group: groupCode,
            metrics: {
                faei: faei,
                tui: tui,
                wfr: wfr,
                acs: acs,
                rrf: rrf
            },
            low_areas: lowAreas
        })
    })
    .then(r => r.json())
    .then(data => {
        let suggestions = data.suggestion || '';
        
        // Try to parse JSON response
        try {
            let jsonStr = suggestions.replace(/```json/g, '').replace(/```/g, '').trim();
            let parsed = JSON.parse(jsonStr);
            suggestions = parsed.suggestions || parsed.message || suggestions;
        } catch(e) {}
        
        document.getElementById('aiSuggestionsContent').innerHTML = suggestions;
        
        // Enable accept button
        const btn = document.getElementById('btnAcceptCoach');
        btn.disabled = false;
        btn.style.opacity = '1';
    })
    .catch(err => {
        document.getElementById('aiSuggestionsContent').innerHTML = 
            '<div style="text-align: center; color: #e74c3c; padding: 40px;"><i class="fas fa-exclamation-circle fa-2x"></i><br><br><strong>Unable to load AI suggestions</strong><br><small>Please try refreshing the page</small></div>';
    });
}

function dismissCoach() {
    document.getElementById('aiCoachModal').style.display = 'none';
}

function acceptCoachingSuggestions() {
    // Store acknowledgment in localStorage
    localStorage.setItem('coachAcknowledged_' + new Date().toDateString(), 'true');
    dismissCoach();
    
    // Show success toast
    const toast = document.createElement('div');
    toast.style.cssText = 'position: fixed; bottom: 30px; right: 30px; background: #27ae60; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); z-index: 15000; animation: slideInRight 0.3s ease;';
    toast.innerHTML = '<i class="fas fa-check-circle"></i> Great! Focus on these improvements for better results.';
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php require_once 'footer.php'; ?>
