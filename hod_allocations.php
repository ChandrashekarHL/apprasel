<?php
require_once 'header.php';

if (!isLoggedIn() || $_SESSION['role'] == 'Faculty') {
   // header("Location: dashboard.php"); 
   // Assuming HoD check passes
}

$message = '';

// Handle Group Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_group'])) {
    $fid = $_POST['faculty_id'];
    $gid = $_POST['group_id'] ?: null; // Handle unassigned as NULL
    
    $stmt = $pdo->prepare("UPDATE ad_faculty_users SET group_id = ? WHERE id = ?");
    if ($stmt->execute([$gid, $fid])) {
        $message = "Faculty group updated successfully.";
        $msgType = "success";
    }
}

// Handle Create User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_faculty'])) {
    $name = $_POST['new_name'];
    $user = $_POST['new_username'];
    $pass = $_POST['new_password']; // Plaintext for demo as per mock
    $dept = $_POST['new_dept'];
    $desig = $_POST['new_designation'];
    $gid = $_POST['new_group_id'] ?: null;
    
    // Check duplicate
    $chk = $pdo->prepare("SELECT id FROM ad_faculty_users WHERE username = ?");
    $chk->execute([$user]);
    if ($chk->rowCount() > 0) {
        $message = "Error: Username '$user' already exists.";
        $msgType = "error"; // Styling needs to handle 'error' class or just use warning
    } else {
        $sql = "INSERT INTO ad_faculty_users (username, password, full_name, department, designation, role, group_id, date_joined) VALUES (?, ?, ?, ?, ?, 'Faculty', ?, CURDATE())";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$user, $pass, $name, $dept, $desig, $gid])) {
            $message = "New Faculty '$name' created successfully!";
            $msgType = "success";
        } else {
            $message = "Database Error: Could not create user.";
            $msgType = "error";
        }
    }
}

// Fetch Groups
$groups = $pdo->query("SELECT * FROM ad_workload_groups ORDER BY group_code")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Faculty with Group Info
// Fetch Faculty with Group Info (Filtered by HOD Dept)
$currentUser = getCurrentUser($pdo);
$dept = $currentUser['department'];

$sql = "SELECT u.id, u.full_name, u.department, u.designation, u.group_id, g.group_code, g.group_name 
        FROM ad_faculty_users u 
        LEFT JOIN ad_workload_groups g ON u.group_id = g.id
        WHERE u.role = 'Faculty'
        AND (u.department = ? OR u.school = ?)
        ORDER BY u.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dept, $dept]);
$faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<style>
    .alloc-grid {
        display: grid;
        grid-template-columns: 3fr 1fr;
        gap: 20px;
    }
    .group-card {
        background: #fff;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        border-left: 4px solid #ddd;
        opacity: 0.9;
    }
    .group-A { border-color: #e74c3c; } /* Teaching Red/Orange */
    .group-B { border-color: #f1c40f; } /* Balanced Yellow */
    .group-C { border-color: #3498db; } /* Research Blue */
    .group-D { border-color: #9b59b6; } /* Admin Purple */
    .group-E { border-color: #2ecc71; } /* Special Green */
    
    .alloc-table select {
        padding: 5px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
</style>

<div class="header-flex">
    <h2>Faculty Workload Allocation</h2>
    <a href="hod_dashboard.php" class="btn btn-secondary">&larr; Monitor</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $msgType; ?>"><?php echo $message; ?></div>
<?php endif; ?>


<div class="alloc-grid">
    <div class="form-container" style="margin: 0;">
        <h3>Assign Workload Groups</h3>
        <table class="data-table alloc-table">
            <thead>
                <tr>
                    <th>Faculty Name</th>
                    <th>Dept & Designation</th>
                    <th>Select Group</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($faculty as $f): ?>
                <tr>
                    <td><?php echo htmlspecialchars($f['full_name']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($f['department']); ?><br>
                        <small style="color:#666;"><?php echo htmlspecialchars($f['designation']); ?></small>
                    </td>
                    <form method="POST">
                        <input type="hidden" name="faculty_id" value="<?php echo $f['id']; ?>">
                        <td>
                            <select name="group_id">
                                <option value="">-- Unassigned --</option>
                                <?php foreach($groups as $g): ?>
                                    <option value="<?php echo $g['id']; ?>" <?php if($f['group_id'] == $g['id']) echo 'selected'; ?>>
                                        Group <?php echo $g['group_code']; ?> - <?php echo $g['group_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="submit" name="assign_group" class="btn btn-sm btn-primary">Save</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Reference Sidebar -->
    <div>
        <h4 style="margin-top: 0;">Group Reference</h4>
        <?php foreach($groups as $g): ?>
        <div class="group-card group-<?php echo $g['group_code']; ?>">
            <strong>Group <?php echo $g['group_code']; ?></strong><br>
            <?php echo $g['group_name']; ?>
            <div style="font-size: 0.85em; margin-top: 5px; color: #555;">
                T: <?php echo intval($g['target_teaching']); ?>% | 
                R: <?php echo intval($g['target_research']); ?>% | 
                A: <?php echo intval($g['target_admin']); ?>%
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
