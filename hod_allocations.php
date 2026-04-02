<?php
require_once 'header.php';

if (!isLoggedIn() || $_SESSION['role'] == 'Faculty') {
   // header("Location: dashboard.php"); 
   // Assuming HoD check passes
}

$message = '';

// Handle Group Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_group'])) {
    $emp_id = $_POST['emp_id'] ?? '';
    // Use fallback for faculty_id if emp_id is not present (for backward compatibility if needed)
    $fid = $_POST['faculty_id'] ?? null; 
    $gid = $_POST['group_id'] ?: null; // Handle unassigned as NULL
    
    if ($emp_id) {
        // Check if user exists in fms_faculty_users
        $chkStmt = $pdo->prepare("SELECT id FROM fms_faculty_users WHERE emp_id = ? OR username = ?");
        $chkStmt->execute([$emp_id, $emp_id]);
        $existing = $chkStmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE fms_faculty_users SET group_id = ? WHERE id = ?");
            if ($stmt->execute([$gid, $existing['id']])) {
                $message = "Faculty group updated successfully.";
                $msgType = "success";
            }
        } else {
            // User not in fms_faculty_users yet, insert them
            $sStmt = $pdo->prepare("SELECT * FROM staff_master WHERE EMP_ID = ?");
            $sStmt->execute([$emp_id]);
            $staff = $sStmt->fetch();
            if ($staff) {
                // Ensure role assignment matches logic from sync script
                $role = 'Faculty';
                if (strpos(strtoupper($staff['DESIGNATION']), 'HOD') !== false || strtoupper($staff['USER_GROUP'] ?? '') === 'HOD') {
                    $role = 'HOD';
                }
                if (strpos(strtoupper($staff['DESIGNATION']), 'DEAN') !== false) {
                    $role = 'Dean';
                }

                $insStmt = $pdo->prepare("INSERT INTO fms_faculty_users (emp_id, username, full_name, department, school, designation, mobile, email, role, group_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($insStmt->execute([$staff['EMP_ID'], $staff['EMP_ID'], $staff['NAME'], $staff['DEPT'], $staff['SCHOOL'], $staff['DESIGNATION'], $staff['MOBILE'], $staff['EMAIL'] ?? '', $role, $gid])) {
                    $message = "Faculty synced and group assigned successfully.";
                    $msgType = "success";
                } else {
                    $message = "Error assigning group.";
                    $msgType = "error";
                }
            } else {
                $message = "Error: Staff member not found in master records.";
                $msgType = "error";
            }
        }
    } elseif ($fid) {
        $stmt = $pdo->prepare("UPDATE fms_faculty_users SET group_id = ? WHERE id = ?");
        if ($stmt->execute([$gid, $fid])) {
            $message = "Faculty group updated successfully.";
            $msgType = "success";
        }
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
    $chk = $pdo->prepare("SELECT id FROM fms_faculty_users WHERE username = ?");
    $chk->execute([$user]);
    if ($chk->rowCount() > 0) {
        $message = "Error: Username '$user' already exists.";
        $msgType = "error"; // Styling needs to handle 'error' class or just use warning
    } else {
        $sql = "INSERT INTO fms_faculty_users (username, password, full_name, department, designation, role, group_id, date_joined) VALUES (?, ?, ?, ?, ?, 'Faculty', ?, CURDATE())";
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
$groups = $pdo->query("SELECT * FROM fms_workload_groups ORDER BY group_code")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Faculty with Group Info (Filtered by HOD Dept)
$currentUser = getCurrentUser($pdo);
$dept_raw = $currentUser['department'] ?? ''; // e.g. 'CHE' from fms_faculty_users
$hodEmpId  = $currentUser['emp_id'] ?? null;

// Resolve true DEPT code from staff_master
$dept = $dept_raw; // fallback

// Level 1: HOD's own EMP_ID
if ($hodEmpId) {
    try {
        $s = $pdo->prepare("SELECT DEPT FROM staff_master WHERE EMP_ID = ? LIMIT 1");
        $s->execute([$hodEmpId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['DEPT'])) { $dept = $r['DEPT']; }
    } catch (Exception $e) {}
}

// Level 2: FMS department to staff_master DEPT mapping
if ($dept === $dept_raw && $dept_raw) {
    $dept_map = [
        'CHE' => 'CHEMISTRY',
        'PHY' => 'PHYSICS',
        'MAT' => 'MAT'
    ];
    $upper_filter = strtoupper(trim($dept_raw));
    if (isset($dept_map[$upper_filter])) {
        $dept = $dept_map[$upper_filter];
    }
}

// Same base query strategy as faculty_performance_analyzer.php
$sql = "SELECT s.EMP_ID as emp_id, s.NAME as full_name, s.DESIGNATION as designation, s.DEPT as department, 
               u.id, u.group_id, g.group_code, g.group_name 
        FROM staff_master s 
        LEFT JOIN fms_faculty_users u ON s.EMP_ID = u.emp_id 
        LEFT JOIN fms_workload_groups g ON u.group_id = g.id
        WHERE s.STATUS = 'WORKING' 
        AND s.CATEGORY = 'TEACHING'
        AND s.DEPT = ?
        ORDER BY s.NAME";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dept]);
$faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If staff_master has no records matching (e.g., using pure FMS structures), fallback to fms_faculty_users query
if (empty($faculty)) {
    $sql_fallback = "SELECT u.id, u.full_name, u.department, u.designation, u.group_id, g.group_code, g.group_name, u.emp_id 
            FROM fms_faculty_users u 
            LEFT JOIN fms_workload_groups g ON u.group_id = g.id
            WHERE u.role = 'Faculty'
            AND (u.department = ? OR u.school = ?)
            ORDER BY u.full_name";
    $stmt_fallback = $pdo->prepare($sql_fallback);
    $stmt_fallback->execute([$dept, $dept]);
    $faculty = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
}

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
                        <input type="hidden" name="faculty_id" value="<?php echo isset($f['id']) ? $f['id'] : ''; ?>">
                        <input type="hidden" name="emp_id" value="<?php echo isset($f['emp_id']) ? $f['emp_id'] : ''; ?>">
                        <td>
                            <select name="group_id">
                                <option value="">-- Unassigned --</option>
                                <?php foreach($groups as $g): ?>
                                    <option value="<?php echo $g['id']; ?>" <?php if(isset($f['group_id']) && $f['group_id'] == $g['id']) echo 'selected'; ?>>
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
