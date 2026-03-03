<?php
require_once 'header.php';
require_once 'WorkloadEngine.php';

if (!isLoggedIn() || $_SESSION['role'] == 'Faculty') {
   // Access Control
}

$engine = new WorkloadEngine($pdo);
$type = $_GET['type'] ?? 'naac'; // naac, nba, contrib, audit

// Fetch Data filtered by HOD Department
$dept = $currentUser['department'] ?? null;

// Determine if user is a "Super Admin" who should see all depts
// Usually Super Admins have no specific academic department assigned or are explicitly named 'admin'
$isSuperAdmin = ($currentUser['username'] === 'admin' || empty($dept) || in_array($dept, ['Admin', 'University', 'Global']));

if (!$isSuperAdmin && !empty($dept)) {
    // Restricted to their own department
    $stmt = $pdo->prepare("SELECT * FROM ad_faculty_users WHERE role = 'Faculty' AND department = ? ORDER BY full_name");
    $stmt->execute([$dept]);
} else {
    // Super Admin sees all
    $stmt = $pdo->query("SELECT * FROM ad_faculty_users WHERE role = 'Faculty' ORDER BY department, full_name");
}
$facultyList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    :root {
        --primary-brand: #2c3e50;
        --secondary-brand: #34495e;
        --accent-gold: #f1c40f;
        --success-green: #27ae60;
        --danger-red: #e74c3c;
        --bg-light: #f4f6f9;
        --card-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
        --card-hover: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
    }
    
    .reports-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Inter', sans-serif;
    }

    .report-tabs {
        display: flex;
        gap: 5px;
        background: white;
        padding: 8px;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        margin-bottom: 25px;
    }

    .report-tab {
        flex: 1;
        padding: 12px 15px;
        text-align: center;
        text-decoration: none;
        color: #7f8c8d;
        font-weight: 600;
        font-size: 0.9em;
        border-radius: 8px;
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }

    .report-tab i { font-size: 1.4em; }
    .report-tab:hover { background: #f8f9fa; color: var(--primary-brand); }
    .report-tab.active {
        background: var(--primary-brand);
        color: white;
        box-shadow: 0 4px 12px rgba(44, 62, 80, 0.3);
    }

    .report-paper {
        background: white;
        padding: 50px;
        border-radius: 15px;
        box-shadow: var(--card-shadow);
        min-height: 800px;
        position: relative;
    }

    .university-header {
        text-align: center;
        border-bottom: 3px double var(--primary-brand);
        padding-bottom: 25px;
        margin-bottom: 30px;
    }

    .university-header h1 {
        margin: 0;
        font-size: 2.2em;
        color: var(--primary-brand);
        letter-spacing: 1px;
    }

    .university-header p {
        margin: 10px 0 0;
        color: #95a5a6;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 0.85em;
    }

    .report-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.8em;
        color: #7f8c8d;
        margin-bottom: 30px;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--primary-brand);
        margin-bottom: 20px;
        border-left: 5px solid var(--accent-gold);
        padding-left: 15px;
        font-size: 1.4em;
    }

    /* Modern Tables */
    .premium-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 15px;
    }

    .premium-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-size: 0.85em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #7f8c8d;
        border-bottom: 2px solid #ecf0f1;
    }

    .premium-table td {
        padding: 15px;
        border-bottom: 1px solid #ecf0f1;
        font-size: 0.95em;
        color: var(--secondary-brand);
    }

    .premium-table tr:hover td { background: #fdfdfd; }

    /* Contribution Cards */
    .contribution-card {
        background: #fff;
        border: 1px solid #ecf0f1;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        page-break-inside: avoid;
        transition: all 0.3s;
    }

    .contribution-card:hover {
        border-color: var(--accent-gold);
        box-shadow: var(--card-hover);
    }

    .card-header-top {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 20px;
    }

    .faculty-name { font-size: 1.3em; font-weight: 700; color: var(--primary-brand); }
    .faculty-id { font-family: monospace; color: #95a5a6; font-size: 0.9em; }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .metric-box {
        background: #fcfcfc;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #f0f0f0;
    }

    .metric-label { font-size: 0.75em; color: #95a5a6; text-transform: uppercase; display: block; margin-bottom: 5px; }
    .metric-value { font-size: 1.2em; font-weight: 600; color: var(--primary-brand); }

    .cert-text {
        font-style: italic;
        color: #7f8c8d;
        font-size: 0.9em;
        line-height: 1.6;
        padding-top: 15px;
        border-top: 1px dashed #ecf0f1;
    }

    .badge-status {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
    }
    .status-met { background: rgba(39, 174, 96, 0.1); color: var(--success-green); }
    .status-gap { background: rgba(231, 76, 60, 0.1); color: var(--danger-red); }

    .print-btn-float {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: var(--accent-gold);
        color: var(--primary-brand);
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s;
        border: none;
    }
    .print-btn-float:hover { transform: scale(1.1); background: #e1b100; }

    @media print {
        .no-print { display: none !important; }
        .report-paper { box-shadow: none; border: none; padding: 0; }
        .contribution-card { border: 1px solid #ddd !important; }
        body { background: white; }
    }
</style>

<div class="reports-container">
    <div class="header-flex no-print" style="margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0;">Institutional Performance Reports</h2>
            <p style="margin: 5px 0 0; color: #7f8c8d;">Governance Compliance & Faculty Audits</p>
        </div>
        <div>
            <a href="hod_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>

    <div class="report-tabs no-print">
        <!-- <a href="?type=naac" class="report-tab <?php echo $type=='naac'?'active':''; ?>">
            <i class="fas fa-university"></i>
            NAAC 
        </a>
        <a href="?type=nba" class="report-tab <?php echo $type=='nba'?'active':''; ?>">
            <i class="fas fa-chart-line"></i>
            NBA 
        </a> -->
        <a href="?type=contrib" class="report-tab <?php echo $type=='contrib'?'active':''; ?>">
            <i class="fas fa-file-signature"></i>
            Contribution Statements
        </a>
        <a href="?type=audit" class="report-tab <?php echo $type=='audit'?'active':''; ?>">
            <i class="fas fa-clipboard-check"></i>
            Process Audit
        </a>
    </div>

    <div class="report-paper">
        <button onclick="window.print()" class="print-btn-float no-print" title="Print Report">
            <i class="fas fa-print"></i>
        </button>

        <div class="university-header">
            <h1>GM UNIVERSITY</h1>
            <p>Academic Excellence & Governance Framework</p>
        </div>

        <div class="report-meta">
            <span>Report Type: <strong><?php echo strtoupper($type); ?></strong></span>
            <span>Generated: <strong><?php echo date('M d, Y | H:i'); ?></strong></span>
        </div>

        <?php if ($type == 'naac'): ?>
            <h3 class="section-title"><i class="fas fa-microscope"></i> Research, Innovations & Extension</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Faculty Research Productivity Metrics (Session 2024-25)</p>
            
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>Faculty Professional</th>
                        <th>Dept</th>
                        <th>Publications</th>
                        <th>Impact Index</th>
                        <th>ACS Score</th>
                        <th>Compliance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($facultyList as $f): 
                        $acs = $engine->calculateACS($f['id']); 
                        try {
                            $pQuery = $pdo->prepare("SELECT COUNT(*) as cnt, SUM(impact_factor) as imf FROM ad_appraisal_research WHERE faculty_id = ?");
                            $pQuery->execute([$f['id']]);
                            $resData = $pQuery->fetch(PDO::FETCH_ASSOC);
                            $pubCount = $resData['cnt'] ?? 0;
                            $impactFactor = $resData['imf'] ?? 0;
                        } catch (Exception $e) { $pubCount = 0; $impactFactor = 0; }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($f['full_name']); ?></div>
                            <div style="font-size: 0.8em; opacity: 0.7;"><?php echo htmlspecialchars($f['designation']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($f['department']); ?></td>
                        <td><?php echo $pubCount; ?></td>
                        <td><?php echo number_format($impactFactor, 1); ?></td>
                        <td><strong style="color: var(--primary-brand); font-size: 1.1em;"><?php echo $acs; ?></strong></td>
                        <td>
                            <?php if($acs > 0.5): ?>
                                <span class="badge-status status-met">Met</span>
                            <?php else: ?>
                                <span class="badge-status status-gap">Gap</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($type == 'nba'): ?>
            <h3 class="section-title"><i class="fas fa-tasks"></i> Faculty Information & Contributions</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Teaching Workload Compliance & Student-Faculty Ratios</p>
            
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>Faculty Member</th>
                        <th>Designation</th>
                        <th>Teaching Avg</th>
                        <th>Admin Engagement</th>
                        <th>TUI Index</th>
                        <th>Compliance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($facultyList as $f): 
                        $tui = $engine->calculateTUI($f['id']); 
                        $loads = $engine->getLoadAverages($f['id']);
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($f['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($f['designation']); ?></td>
                        <td><?php echo $loads['teaching_avg']; ?> h/wk</td>
                        <td><?php echo $loads['admin_avg']; ?> h/wk</td>
                        <td><?php echo number_format($tui, 2); ?></td>
                        <td>
                            <?php if($tui > 0.6): ?>
                                <span class="badge-status status-met">Compliant</span>
                            <?php else: ?>
                                <span class="badge-status status-gap">Underload</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($type == 'contrib'): ?>
            <h3 class="section-title"><i class="fas fa-award"></i> Annual Contribution Statements</h3>
            <p style="color: #7f8c8d; margin-bottom: 30px;">Certified performance summaries for HR & Governance records.</p>
            
            <?php foreach($facultyList as $f): 
                $faei = $engine->calculateFAEI($f['id']);
                $adminRolesQuery = $pdo->prepare("SELECT COUNT(DISTINCT description) FROM ad_activity_logs WHERE faculty_id = ? AND category = 'Admin'");
                $adminRolesQuery->execute([$f['id']]);
                $adminRolesCount = $adminRolesQuery->fetchColumn() ?: 0;
                $acadYear = getAcademicYear();
            ?>
            <div class="contribution-card">
                <div class="card-header-top">
                    <span class="faculty-name"><?php echo htmlspecialchars($f['full_name']); ?></span>
                    <span class="faculty-id">FACULTY ID: GMU-<?php echo $f['id']; ?></span>
                </div>
                
                <div class="metrics-grid">
                    <div class="metric-box">
                        <span class="metric-label">Teaching Efficiency</span>
                        <span class="metric-value"><?php echo $engine->calculateTeachingEff($f['id']); ?>%</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Research Index (ACS)</span>
                        <span class="metric-value"><?php echo $engine->calculateACS($f['id']); ?></span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Gov. Roles Logged</span>
                        <span class="metric-value"><?php echo $adminRolesCount; ?></span>
                    </div>
                    <div class="metric-box" style="border-color: var(--primary-brand); background: #f8f9fa;">
                        <span class="metric-label">Overall FAEI Score</span>
                        <span class="metric-value" style="color: var(--primary-brand); font-size: 1.4em;"><?php echo $faei; ?></span>
                    </div>
                </div>

                <div class="cert-text">
                    "This document certifies that the academic professional has fulfilled their designated duties for the Academic Session <strong><?php echo $acadYear; ?></strong>. The performance metrics are verified against the Daily Activity Register (DAR) and departmental governance standards."
                </div>
            </div>
            <?php endforeach; ?>

        <?php elseif ($type == 'audit'): ?>
            <h3 class="section-title"><i class="fas fa-search"></i> Departmental Process Audit</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px;">Verification of DAR consistency and planning compliance.</p>
            
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>Professional</th>
                        <th>Plan Status</th>
                        <th>Log Frequency</th>
                        <th>Audit Outcome</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($facultyList as $f): 
                        $plan = $engine->getWeeklyPlan($f['id'], date('Y-m-d', strtotime('monday this week')));
                        $status = $plan ? $plan['status'] : 'Missing';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($f['full_name']); ?></strong></td>
                        <td>
                            <?php if($status == 'Approved' || $status == 'Locked'): ?>
                                <span class="badge-status status-met"><i class="fas fa-check-circle"></i> Validated</span>
                            <?php else: ?>
                                <span class="badge-status status-gap"><i class="fas fa-times-circle"></i> <?php echo $status; ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span style="color: var(--success-green); font-weight: 500;">Active</span></td>
                        <td>
                            <?php if($status == 'Missing'): ?>
                                <strong style="color: var(--danger-red); font-size: 0.85em;">PROCESS DISCREPANCY DETECTED</strong>
                            <?php else: ?>
                                <span style="color: #95a5a6; font-size: 0.85em;">Compliance Maintained</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
