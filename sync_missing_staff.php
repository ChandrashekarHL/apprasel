<?php
// Enable Error Reporting for Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

$result = "";
$apiUrl = "http://127.0.0.1/v3/fms/get_staff_by_dept.php";

// Initialize counts
$addedCount = 0;
$skippedCount = 0;
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $dept = trim($_POST['dept']); // Optional

    $postData = [
        'username' => $username,
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
        $result = "<div style='color:red'>CURL Error: $curlError</div>";
    } else {
        $decoded = json_decode($response, true);
        
        if ($decoded && isset($decoded['data'])) {
            $apiData = $decoded['data'];
            
            // 2. Prepare Insert Statement
            // Using default password '123456' or similar since auth is via API mostly
            // Mapping API fields to Local DB fields
            $sql = "INSERT INTO ad_faculty_users 
                    (emp_id, username, full_name, department, school, designation, mobile, email, role) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            // 3. Check and Insert
            foreach ($apiData as $staff) {
                $empId = $staff['EMP_ID'];
                
                // Check if exists
                $checkStmt = $pdo->prepare("SELECT id FROM ad_faculty_users WHERE emp_id = ?");
                $checkStmt->execute([$empId]);
                
                if ($checkStmt->fetch()) {
                    $skippedCount++;
                } else {
                    // Normalize Data
                    $name = $staff['NAME'];
                    $designation = $staff['DESIGNATION'];
                    $department = $staff['DEPT'];
                    $school = $staff['SCHOOL'];
                    $mobile = $staff['MOBILE'];
                    $email = $staff['EMAIL']; // Might be empty
                    // $status = 'Active'; // Column not in DB
                    
                    // Role Logic
                    $role = 'Faculty';
                    if (strpos(strtoupper($designation), 'HOD') !== false || strtoupper($staff['USER_GROUP'] ?? '') === 'HOD') {
                        $role = 'HOD';
                    }
                    if (strpos(strtoupper($designation), 'DEAN') !== false) {
                        $role = 'Dean';
                    }

                    // Generate Username if needed (Use EMP_ID usually)
                    $username = $empId; 
                    // Password column removed as it doesn't exist in DB

                    try {
                        $stmt->execute([
                            $empId, $username, $name, $department, $school, $designation, $mobile, $email, $role
                        ]);
                        $addedCount++;
                    } catch (PDOException $e) {
                         $errors[] = "Error adding $name ($empId): " . $e->getMessage();
                    }
                }
            }
            
            $result = "<div style='background:#e8f5e9; padding:15px; border-left: 5px solid green;'>
                        <h3>Sync Complete</h3>
                        <ul>
                            <li><strong>Total from API:</strong> " . count($apiData) . "</li>
                            <li><strong>Added New:</strong> $addedCount</li>
                            <li><strong>Already Existed:</strong> $skippedCount</li>
                        </ul>
                       </div>";
                       
            if (!empty($errors)) {
                $result .= "<div style='background:#ffebee; padding:10px; margin-top:10px;'>
                            <strong>Errors:</strong><br>" . implode("<br>", $errors) . "</div>";
            }
            
        } else {
            $result = "<div style='color:red'><strong>Invalid API Response.</strong><br>
                       Raw Response: <textarea style='width:100%; height:100px;'>" . htmlspecialchars($response) . "</textarea><br>
                       CURL Info: <pre>" . print_r(curl_getinfo($ch), true) . "</pre></div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sync Staff from ERP</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; background: #f9f9f9; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input[type="text"] { width: 100%; padding: 10px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #218838; }
    </style>
</head>
<body>

<div class="container">
    <h2>Sync Missing Staff from ERP</h2>
    <p>This tool will fetch staff from the ERP API and add any missing users to your local database.</p>
    
    <form method="post">
        <label>Your Username / EMP_ID (Requester):</label>
        <input type="text" name="username" placeholder="e.g. HOD_EEE or DEAN_ENGG" required>
        
        <label>Department (Optional):</label>
        <input type="text" name="dept" placeholder="e.g. EEE">

        <button type="submit">Sync Users Now</button>
    </form>

    <?php if ($result): ?>
        <br>
        <?php echo $result; ?>
    <?php endif; ?>
</div>

</body>
</html>
