<?php
require_once 'db_config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    die('Please log in to the FMS first, then visit this page.');
}

$user = getCurrentUser($pdo);
echo "<h2>HOD Department Diagnostic</h2>";

// 1. What is stored in fms_faculty_users for this HOD?
echo "<h3>1. Your fms_faculty_users record</h3><pre>";
print_r($user);
echo "</pre>";

$empId = $user['emp_id'] ?? null;
echo "<p><strong>emp_id from fms_faculty_users:</strong> " . htmlspecialchars($empId ?? 'NULL') . "</p>";
echo "<p><strong>department from fms_faculty_users:</strong> " . htmlspecialchars($user['department'] ?? 'NULL') . "</p>";

// 2. What does staff_master say for this emp_id?
echo "<h3>2. Your staff_master record</h3>";
if ($empId) {
    $s = $pdo->prepare("SELECT EMP_ID, NAME, DEPT, DESIGNATION, USER_GROUP, SCHOOL FROM staff_master WHERE EMP_ID = ? LIMIT 1");
    $s->execute([$empId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($row);
    echo "</pre>";
    $actual_dept = $row['DEPT'] ?? 'NOT FOUND';
    echo "<p><strong>DEPT code in staff_master:</strong> <span style='color:green;font-weight:bold;font-size:1.3em;'>$actual_dept</span></p>";
} else {
    echo "<p style='color:red;'>No emp_id found — cannot look up staff_master</p>";
    $actual_dept = null;
}

// 3. How many TEACHING staff are in that DEPT in staff_master?
echo "<h3>3. Teaching staff count in staff_master for DEPT = '$actual_dept'</h3>";
if ($actual_dept && $actual_dept !== 'NOT FOUND') {
    $s2 = $pdo->prepare("SELECT COUNT(*) as cnt FROM staff_master WHERE DEPT = ? AND STATUS='WORKING' AND CATEGORY='TEACHING'");
    $s2->execute([$actual_dept]);
    $cnt = $s2->fetch()['cnt'];
    echo "<p>Found: <strong>$cnt</strong> teaching staff with DEPT='$actual_dept'</p>";

    $s3 = $pdo->prepare("SELECT EMP_ID, NAME, DESIGNATION, CATEGORY FROM staff_master WHERE DEPT = ? AND STATUS='WORKING' AND CATEGORY='TEACHING' ORDER BY NAME");
    $s3->execute([$actual_dept]);
    $staff = $s3->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'><tr><th>EMP_ID</th><th>Name</th><th>Designation</th><th>Category</th></tr>";
    foreach ($staff as $st) {
        echo "<tr><td>{$st['EMP_ID']}</td><td>{$st['NAME']}</td><td>{$st['DESIGNATION']}</td><td>{$st['CATEGORY']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>Cannot check — no DEPT code resolved.</p>";
}

// 4. How many are in fms_faculty_users by dept?
echo "<h3>4. fms_faculty_users counts by department</h3>";
$s4 = $pdo->query("SELECT department, COUNT(*) as cnt FROM fms_faculty_users GROUP BY department ORDER BY department");
$depts = $s4->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'><tr><th>department (fms)</th><th>count</th></tr>";
foreach ($depts as $d) {
    $highlight = ($d['department'] === ($user['department'] ?? '')) ? "style='background:#ffe;'" : "";
    echo "<tr $highlight><td>{$d['department']}</td><td>{$d['cnt']}</td></tr>";
}
echo "</table>";

// 5. All DEPT values in staff_master
echo "<h3>5. All DEPT codes in staff_master (WORKING+TEACHING)</h3>";
$s5 = $pdo->query("SELECT DEPT, COUNT(*) as cnt FROM staff_master WHERE STATUS='WORKING' AND CATEGORY='TEACHING' GROUP BY DEPT ORDER BY DEPT");
$allDepts = $s5->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'><tr><th>DEPT</th><th>count</th></tr>";
foreach ($allDepts as $d) {
    echo "<tr><td>{$d['DEPT']}</td><td>{$d['cnt']}</td></tr>";
}
echo "</table>";

// 6. Session data
echo "<h3>6. Session data</h3><pre>";
print_r($_SESSION);
echo "</pre>";
?>
