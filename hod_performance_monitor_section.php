<!-- AI Performance Monitoring Section - Add after line 263 in hod_dashboard.php -->

<!-- CSS for Performance Monitoring -->
<style>
.btn-filter {
    padding: 8px 15px;
    border: 2px solid #ddd;
    background: white;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.85em;
    transition: all 0.2s;
}

.btn-filter.active {
    background: var(--primary-brand);
    color: white;
    border-color: var(--primary-brand);
}

.btn-filter:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<!-- AI Performance Monitor Table -->
<div class="data-card" style="margin-top: 30px;">
    <div class="card-header">
        <h3><i class="fas fa-robot"></i> AI Performance Intelligence <span style="font-weight: normal; color: #95a5a6; font-size: 0.8em; margin-left: 10px;">(Semester-wide Analysis)</span></h3>
        <div style="display: flex; gap: 10px;">
            <button onclick="filterPerformance('all')" class="btn-filter active" id="filter-all">
                All (<?php echo $totalFaculty; ?>)
            </button>
            <button onclick="filterPerformance('red')" class="btn-filter" id="filter-red" style="border-color: #e74c3c; color: #e74c3c;">
                🔴 Critical (<?php echo $redFlagged; ?>)
            </button>
            <button onclick="filterPerformance('yellow')" class="btn-filter" id="filter-yellow" style="border-color: #f39c12; color: #f39c12;">
                🟡 Warning (<?php echo $yellowFlagged; ?>)
            </button>
            <button onclick="filterPerformance('green')" class="btn-filter" id="filter-green" style="border-color: #27ae60; color: #27ae60;">
                🟢 Good (<?php echo $greenCount; ?>)
            </button>
        </div>
    </div>
    <table class="modern-table">
        <thead>
            <tr>
                <th>Faculty Member</th>
                <th>Status</th>
                <th>FAEI</th>
                <th>Weekly Progress</th>
                <th>Daily Tasks</th>
                <th>Trend</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($facultyPerformance as $fp): ?>
            <tr class="faculty-row" data-flag="<?php echo $fp['flag']; ?>">
                <td>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($fp['name']); ?></div>
                    <div style="font-size: 0.85em; color: #95a5a6;"><?php echo htmlspecialchars($fp['department']); ?></div>
                </td>
                <td>
                    <?php
                    $flagColors = ['red' => '#e74c3c', 'yellow' => '#f39c12', 'green' => '#27ae60'];
                    $flagLabels = ['red' => 'Critical', 'yellow' => 'Warning', 'green' => 'Good'];
                    $flagIcons = ['red' => 'fa-exclamation-circle', 'yellow' => 'fa-exclamation-triangle', 'green' => 'fa-check-circle'];
                    ?>
                    <span class="performance-flag" style="background: <?php echo $flagColors[$fp['flag']]; ?>; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.75em; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fas <?php echo $flagIcons[$fp['flag']]; ?>"></i>
                        <?php echo $flagLabels[$fp['flag']]; ?>
                    </span>
                </td>
                <td>
                    <strong><?php echo $fp['faei']; ?></strong> <span style="color: #bdc3c7;">/10</span>
                </td>
                <td>
                    <strong><?php echo $fp['weekly_completion']; ?>%</strong> 
                    <span style="font-size: 0.8em; color: #95a5a6;">(<?php echo $fp['weeks_submitted']; ?>/<?php echo $fp['total_weeks']; ?> weeks)</span>
                </td>
                <td>
                    <?php echo $fp['task_completion']; ?>% 
                    <span style="font-size: 0.8em; color: #95a5a6;">(<?php echo $fp['completed_tasks']; ?>/<?php echo $fp['total_tasks']; ?>)</span>
                </td>
                <td>
                    <span style="font-size: 0.85em;"><?php echo $fp['trend']; ?></span>
                </td>
                <td>
                    <button onclick="viewAIReport(<?php echo $fp['id']; ?>)" class="btn" style="padding: 6px 12px; font-size: 0.85em; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">
                        <i class="fas fa-chart-line"></i> AI Report
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($facultyPerformance)): ?>
                <tr><td colspan="7" style="text-align: center; color: #95a5a6;">No faculty data found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- JavaScript for AI Report Modal and Filtering -->
<script>
// AI Report Modal
function viewAIReport(facultyId) {
    // Show loading modal
    const modal = document.createElement('div');
    modal.id = 'aiReportModal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; justify-content: center; align-items: center;';
    
    modal.innerHTML = `
        <div style="background: white; width: 800px; max-width: 90%; border-radius: 15px; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
            <div style="background: linear-gradient(135deg, #3498db, #2980b9); padding: 20px; color: white;">
                <h3 style="margin: 0;"><i class="fas fa-robot"></i> AI Performance Report</h3>
            </div>
            <div id="reportContent" style="padding: 30px; min-height: 300px; max-height: 500px; overflow-y: auto; flex: 1;">
                <div style="text-align: center; padding: 60px;">
                    <i class="fas fa-robot fa-spin fa-3x" style="color: #3498db;"></i><br><br>
                    <strong>Generating comprehensive report...</strong>
                </div>
            </div>
            <div style="padding: 15px; background: #f8f9fa; text-align: right;">
                <button onclick="closeAIReport()" style="padding: 10px 20px; background: #95a5a6; color: white; border: none; border-radius: 5px; cursor: pointer;">Close</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Fetch AI report
    fetch('generate_hod_report.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({faculty_id: facultyId})
    })
    .then(r => {
        // Check if response is OK
        if (!r.ok) {
            return r.json().then(err => {
                throw new Error(err.message || err.error || 'Server error');
            });
        }
        return r.json();
    })
    .then(data => {
        if (data.error) {
            throw new Error(data.message || data.error);
        }
        document.getElementById('reportContent').innerHTML = data.report || '<div style="text-align: center; color: #e74c3c;"><i class="fas fa-exclamation-circle fa-2x"></i><br><br>No report data available</div>';
    })
    .catch(err => {
        console.error('Report error:', err);
        document.getElementById('reportContent').innerHTML = 
            `<div style="text-align: center; color: #e74c3c; padding: 40px;">
                <i class="fas fa-exclamation-circle fa-3x" style="margin-bottom: 20px;"></i>
                <h3>Report Generation Failed</h3>
                <p style="color: #555;">${err.message}</p>
                <p style="font-size: 0.9em; color: #777; margin-top: 20px;">Please refresh the page and try again.<br>If the problem persists, contact system administrator.</p>
            </div>`;
    });
}

function closeAIReport() {
    document.getElementById('aiReportModal')?.remove();
}

// Filter performance by flag
function filterPerformance(flag) {
    const rows = document.querySelectorAll('.faculty-row');
    const buttons = document.querySelectorAll('.btn-filter');
    
    // Update button states
    buttons.forEach(btn => btn.classList.remove('active'));
    document.getElementById('filter-' + flag).classList.add('active');
    
    // Filter rows
    rows.forEach(row => {
        if (flag === 'all' || row.dataset.flag === flag) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
