<?php
require_once 'header.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'Reviewer') {
    // Redirect if not reviewer
    echo "<script>window.location.href='dashboard.php?login=reviewer';</script>"; 
    // In production, real auth check. Here simplistic redirect for demo flow.
    exit;
}

$currentUser = getCurrentUser($pdo);

// Fetch all faculty members
$stmt = $pdo->query("SELECT * FROM ad_faculty_users WHERE LOWER(designation) NOT LIKE '%dean%' AND username != 'reviewer1'");
$faculty_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<h2>Reviewer Dashboard</h2>
<p class="subtitle">Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?> (Dean)</p>

<div class="form-container">
    <h3>Faculty Appraisal Status </h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Faculty Name</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Academic</th>
                <th>Research</th>
                <th>Training</th>
                <th>Consultancy</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($faculty_list as $faculty): 
                // Real Status Logic
                $statusMap = [];
                $tables = ['ad_appraisal_research', 'ad_appraisal_training', 'ad_appraisal_consultancy'];
                foreach($tables as $tbl) {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM $tbl WHERE faculty_id = ?");
                    $chk->execute([$faculty['id']]);
                    $count = $chk->fetchColumn();
                    $statusMap[$tbl] = ($count > 0) ? '<span class="status completed">'.$count.' Entries</span>' : '<span class="status pending">Pending</span>';
                }
                
                // Academic Source Check
                $acChk = $pdo->prepare("SELECT COUNT(*) FROM ad_academic_source WHERE faculty_id = ?");
                $acChk->execute([$faculty['id']]);
                $acCount = $acChk->fetchColumn();
                $acStatus = ($acCount > 0) ? '<span class="status completed">Synced</span>' : '<span class="status pending">No Data</span>';
            ?>
            <tr>
                <td><?php echo htmlspecialchars($faculty['full_name']); ?></td>
                <td><?php echo htmlspecialchars($faculty['department']); ?></td>
                <td><?php echo htmlspecialchars($faculty['designation']); ?></td>
                
                <td><?php echo $acStatus; ?></td> <!-- Academic -->
                <td><?php echo $statusMap['ad_appraisal_research']; ?></td> <!-- Research -->
                <td><?php echo $statusMap['ad_appraisal_training']; ?></td> <!-- Training -->
                <td><?php echo $statusMap['ad_appraisal_consultancy']; ?></td> <!-- Consultancy -->
                <td>
                    <a href="review_faculty.php?id=<?php echo $faculty['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.9rem;">Review</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
