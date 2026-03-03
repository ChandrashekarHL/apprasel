<?php
// Enable Error Reporting for Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

// List of all Departments in your institution
$departments = [
    'CSE', 'ISE', 'ECE', 'EEE', 'ME', 'CV', 
    'AI&ML', 'BT', 'MBA', 'MCA', 
    'PHY', 'CHE', 'MAT', 'ENG', 'ADM' // Added ADM for admin staff if needed
];

$result = "";
$apiUrl = "http://127.0.0.1/v3/fms/get_staff_by_dept.php";

$totalAdded = 0;
$totalSkipped = 0;
$errors = [];
$logs = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $passcode = trim($_POST['passcode']);
    $requester = trim($_POST['admin_user']);
    if (empty($requester)) $requester = 'PRINCIPAL';
    
    if ($passcode !== '0909') { // Hardcoded Passcode
        $result = "<div style='color:red; padding:10px; border:1px solid red; background:#ffebee;'><strong>Error:</strong> Invalid Passcode. Access Denied.</div>";
    } else {
        // AUTHENTICATED
        // Disable time limit for long sync
        set_time_limit(300); 

        foreach ($departments as $dept) {
            $logs[] = "Syncing Department: <strong>$dept</strong> (as $requester)...";
            
            $postData = [
                'username' => $requester,
                'dept' => $dept
            ];

            // 1. Fetch Staff from API
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // Fix for NAT Loopback: Send request to localhost but with correct Host header
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: erp.gmit.info']);
            
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $errors[] = "$dept: CURL Error - $curlError";
                continue;
            }

            $decoded = json_decode($response, true);
            
            if ($decoded && isset($decoded['data']) && !empty($decoded['data'])) {
                $apiData = $decoded['data'];
                $deptAdded = 0;
                
                // 2. Insert Logic
                $sql = "INSERT INTO ad_faculty_users 
                        (emp_id, username, full_name, department, school, designation, mobile, email, role) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);

                foreach ($apiData as $staff) {
                    $empId = $staff['EMP_ID'];
                    
                    // Check if exists
                    $checkStmt = $pdo->prepare("SELECT id FROM ad_faculty_users WHERE emp_id = ?");
                    $checkStmt->execute([$empId]);
                    
                    if ($checkStmt->fetch()) {
                        $totalSkipped++;
                    } else {
                        // Try Insert
                        try {
                            // Normalize Data
                            $name = $staff['NAME'];
                            $designation = $staff['DESIGNATION'];
                            $department = $staff['DEPT'];
                            $school = $staff['SCHOOL'];
                            $mobile = $staff['MOBILE'];
                            $email = $staff['EMAIL']; 
                            
                            // Role Logic
                            $role = 'Faculty';
                            if (strpos(strtoupper($designation), 'HOD') !== false || strtoupper($staff['USER_GROUP'] ?? '') === 'HOD') {
                                $role = 'HOD';
                            }
                            if (strpos(strtoupper($designation), 'DEAN') !== false) {
                                $role = 'Dean';
                            }

                            // Generate Username
                            $username = $empId; 
                            // Password column removed

                            $stmt->execute([
                                $empId, $username, $name, $department, $school, $designation, $mobile, $email, $role
                            ]);
                            $deptAdded++;
                            $totalAdded++;
                        } catch (PDOException $e) {
                             $errors[] = "Error adding $name ($empId): " . $e->getMessage();
                        }
                    }
                }
                $logs[] = "  -> Added: $deptAdded users.";
            } else {
                // Improved Error Logging
                 $errMsg = isset($decoded['message']) ? $decoded['message'] : "Unknown Error";
                 $logs[] = "  -> Failed: API said '$errMsg'.<br>Raw Response: " . htmlspecialchars(substr($response, 0, 500)) . "...";
                 $logs[] = "CURL Info: <pre>" . print_r(curl_getinfo($ch), true) . "</pre>";
            }
        }
        
        $result = "<div style='background:#e8f5e9; padding:15px; border-left: 5px solid green;'>
                    <h3>Global Sync Complete</h3>
                    <ul>
                        <li><strong>Total Added:</strong> $totalAdded</li>
                        <li><strong>Total Skipped (Existing):</strong> $totalSkipped</li>
                    </ul>
                   </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sync ALL Departments</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; background: #f9f9f9; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #0056b3; }
        .log-box { background: #333; color: #fff; padding: 10px; margin-top: 20px; border-radius: 4px; font-family: monospace; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>

<div class="container">
    <h2>Sync ALL Departments</h2>
    <p>This tool will iterate through ALL departments to sync staff.</p>
    
    <form method="post">
        <label>Passcode:</label>
        <input type="password" name="passcode" placeholder="Enter Access Code (0909)" required>
        
        <label>Run As (Admin Username/ID):</label>
        <input type="text" name="admin_user" value="PRINCIPAL" placeholder="e.g. PRINCIPAL, ADMIN, or Your ID">
        <small style="color:#666; display:block; margin-top:-10px; margin-bottom:15px;">Use a user ID that has permission to view all departments.</small>

        <button type="submit">Start Global Sync</button>
    </form>

    <?php if ($result): ?>
        <br>
        <?php echo $result; ?>
    <?php endif; ?>

    <?php if (!empty($logs)): ?>
        <div class="log-box">
            <?php echo implode("<br>", $logs); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div style="background:#ffebee; padding:10px; margin-top:10px; border-left: 5px solid red;">
            <strong>Errors:</strong><br><?php echo implode("<br>", $errors); ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
