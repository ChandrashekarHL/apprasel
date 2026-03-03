<?php
require_once 'header.php';
require_once 'WorkloadEngine.php';

// Access Control: Dean Only (For demo, we allow HoD/Admin to peek)
if (!isLoggedIn() || $_SESSION['role'] == 'Faculty') {
   // header("Location: dashboard.php");
}

$engine = new WorkloadEngine($pdo);
$user = getCurrentUser($pdo);

// --- Intelligence Engine: Cross-Department Analysis ---
$weekStart = date('Y-m-d', strtotime('monday this week'));

// Fetch all faculty plans joined with department
$sql = "SELECT u.department, u.id, p.status, 
        (p.planned_teaching_hrs + p.planned_research_hrs + p.planned_admin_hrs + p.planned_mentoring_hrs + p.planned_aav_hrs) as total_planned
        FROM ad_faculty_users u 
        LEFT JOIN ad_workload_plans p ON u.id = p.faculty_id AND p.week_start_date = ?
        WHERE u.role = 'Faculty'";
        
$stats = $pdo->prepare($sql);
$stats->execute([$weekStart]);
$rows = $stats->fetchAll(PDO::FETCH_ASSOC);

$deptData = [];
foreach($rows as $r) {
    // Determine Department
    $d = $r['department'] ?: 'Unassigned';
    if (!isset($deptData[$d])) {
        $deptData[$d] = ['faculty_count' => 0, 'total_hours' => 0, 'submitted_count' => 0, 'stress_status' => 'Normal'];
    }
    
    $deptData[$d]['faculty_count']++;
    $load = $r['total_planned'] ?: 0;
    $deptData[$d]['total_hours'] += $load;
    if ($r['status'] == 'Submitted' || $r['status'] == 'Approved' || $r['status'] == 'Locked') {
        $deptData[$d]['submitted_count']++;
    }
}

// Calculate Averages and "Stress Points"
$chartLabels = [];
$chartLoad = [];
$chartColors = [];

foreach($deptData as $dept => &$data) {
    if ($data['faculty_count'] > 0) {
        $avgLoad = $data['total_hours'] / $data['faculty_count'];
        $compliance = ($data['submitted_count'] / $data['faculty_count']) * 100;
        
        $data['avg_load'] = round($avgLoad, 1);
        $data['compliance'] = round($compliance);
        
        // Stress Logic (Module 7)
        if ($avgLoad > 42) {
            $data['stress_status'] = 'Critical Overload';
            $data['color'] = '#e74c3c'; // Red
        } elseif ($avgLoad < 30) {
            $data['stress_status'] = 'Underutilized';
            $data['color'] = '#f1c40f'; // Yellow
        } else {
            $data['stress_status'] = 'Balanced';
            $data['color'] = '#27ae60'; // Green
        }
        
        $chartLabels[] = $dept;
        $chartLoad[] = $avgLoad;
        $chartColors[] = $data['color'];
    }
}
unset($data); // Break ref
?>

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .dean-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-top: 20px; }
    .metric-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .stress-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; color: white; }
</style>

<div class="header-flex">
    <div>
        <h2><i class="fas fa-university"></i> Dean's Strategic Board</h2>
        <p style="color: #7f8c8d; margin: 0;">Cross-Department Load Analysis & Stress Detection</p>
    </div>
    <a href="vc_dashboard.php" class="btn btn-outline">Switch to VC View &rarr;</a>
</div>

<div class="dean-grid">
    <!-- Left: Load Analysis Chart -->
    <div class="metric-card">
        <h3>Department Load Variance (Avg Hours/Faculty)</h3>
        <canvas id="deptChart" height="150"></canvas>
        <p style="font-size: 0.9em; color: #7f8c8d; margin-top: 15px; text-align: center;">
            <span style="color: #27ae60;">● Balanced (30-42h)</span> | 
            <span style="color: #f1c40f;">● Underutilized (<30h)</span> | 
            <span style="color: #e74c3c;">● Overloaded (>42h)</span>
        </p>
    </div>

    <!-- Right: Stress Points List -->
    <div class="metric-card">
        <h3>Detected Stress Points</h3>
        <?php 
        $hasStress = false;
        if(empty($deptData)) echo "<p>No department data found.</p>";
        else {
             echo '<ul style="list-style: none; padding: 0;">';
            foreach($deptData as $dept => $d) {
                if ($d['stress_status'] != 'Balanced') {
                    $hasStress = true;
                    echo "<li style='margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;'>";
                    echo "<div style='display: flex; justify-content: space-between; align-items: center;'>";
                    echo "<strong>{$dept}</strong>";
                    echo "<span class='stress-badge' style='background: {$d['color']}'>{$d['stress_status']}</span>";
                    echo "</div>";
                    echo "<div style='font-size: 0.85em; color: #7f8c8d; margin-top: 5px;'>Avg Load: {$d['avg_load']}h | Compliance: {$d['compliance']}%</div>";
                    echo "</li>";
                }
            }
            echo '</ul>';
        }
        
        if (!$hasStress) {
            echo '<div class="alert alert-success"><i class="fas fa-check"></i> All Departments are optimized and balanced.</div>';
        }
        ?>
    </div>
</div>

<div class="metric-card" style="margin-top: 25px;">
    <h3>Institutional Department Matrix</h3>
    <table class="modern-table">
        <thead>
            <tr>
                <th>Department</th>
                <th>Faculty Strength</th>
                <th>Avg Workload</th>
                <th>Compliance Rate</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($deptData as $dept => $d): ?>
            <tr>
                <td><?php echo htmlspecialchars($dept); ?></td>
                <td><?php echo $d['faculty_count']; ?></td>
                <td><strong><?php echo $d['avg_load']; ?> h</strong></td>
                <td>
                    <div style="background: #ecf0f1; border-radius: 10px; width: 100px; height: 8px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 10px;">
                        <div style="width: <?php echo $d['compliance']; ?>%; background: #3498db; height: 100%;"></div>
                    </div>
                    <?php echo $d['compliance']; ?>%
                </td>
                <td><span style="color: <?php echo $d['color']; ?>; font-weight: bold;"><?php echo $d['stress_status']; ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
const ctx = document.getElementById('deptChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Avg Weekly Hours',
            data: <?php echo json_encode($chartLoad); ?>,
            backgroundColor: <?php echo json_encode($chartColors); ?>,
            borderWidth: 0,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, suggestedMax: 50, grid: { color: '#f0f0f0' } }
        }
    }
});
</script>

<?php require_once 'footer.php'; ?>
