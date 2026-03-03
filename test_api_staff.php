<?php
require_once 'db_config.php';

$result = "";
$apiUrl = "https://erp.gmit.info/v3/fms/get_staff_by_dept.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $dept = trim($_POST['dept']);

    $postData = [
        'username' => $username,
        'dept' => $dept
    ];

    // 1. EXECUTE CURL (Form Data)
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 2. PROCESS RESPONSE
    if ($curlError) {
        $result = "<div style='color:red;'><strong>CURL Error:</strong> $curlError</div>";
    } else {
        $decoded = json_decode($response, true);
        
        if ($decoded && isset($decoded['data'])) {
            // API returned Success Data -> Cross Reference with Local DB
            $apiData = $decoded['data'];
            $empIds = array_column($apiData, 'EMP_ID');
            
            // Check Local DB
            if (!empty($empIds)) {
                $placeholders = str_repeat('?,', count($empIds) - 1) . '?';
                $sql = "SELECT emp_id, username, full_name FROM ad_faculty_users WHERE emp_id IN ($placeholders) OR username IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($empIds, $empIds)); // Check both columns
                $localUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $localMap = [];
                foreach ($localUsers as $u) {
                    $localMap[$u['emp_id']] = $u; 
                    $localMap[$u['username']] = $u; 
                }
            } else {
                $localMap = [];
            }
            
            // Build Table
            $table = "<table border='1' cellspacing='0' cellpadding='5' style='width:100%; border-collapse:collapse;'>
                        <tr style='background:#eee;'><th>API EMP_ID</th><th>API Name</th><th>Local DB Status</th></tr>";
            
            foreach ($apiData as $staff) {
                $id = $staff['EMP_ID'];
                $exists = isset($localMap[$id]);
                $status = $exists ? "<span style='color:green; font-weight:bold;'>Found</span>" : "<span style='color:red; font-weight:bold;'>MISSING IN LOCAL DB</span>";
                $table .= "<tr><td>$id</td><td>{$staff['NAME']}</td><td>$status</td></tr>";
            }
            $table .= "</table>";
            
             $result = "<div style='background:#f4f4f4; padding:15px; border-left: 5px solid green;'>
                        <strong>API Success! Found " . count($apiData) . " records.</strong><br>
                        <p>If status is 'MISSING', the dashboard cannot display data for that user.</p>
                        $table
                       </div>";
        } elseif ($decoded) {
             // Valid JSON but maybe error status
             $formattedJson = json_encode($decoded, JSON_PRETTY_PRINT);
             $result = "<div style='background:#f4f4f4; padding:15px; border-left: 5px solid orange;'>
                        <strong>API Returned JSON (No Data):</strong><pre>$formattedJson</pre>
                       </div>";
        } else {
             // Not JSON
             $safeResponse = htmlspecialchars(substr($response, 0, 2000));
             $result = "<div style='color:red;'><strong>Raw Response (Not JSON):</strong> <br>
                        <textarea style='width:100%; height:200px;'>$safeResponse</textarea>
                        <br><strong>HTTP Code:</strong> $httpCode
                        </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Staff API</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; background: #f9f9f9; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>

<div class="container">
    <h2>Test Staff API Connectivity</h2>
    <p>API URL: <code><?php echo $apiUrl; ?></code></p>
    
    <form method="post">
        <div class="form-group">
            <label>Username / EMP_ID (Requester):</label>
            <input type="text" name="username" placeholder="e.g. DEAN001 or HOD_CSE" required>
        </div>
        
        <div class="form-group">
            <label>Department (Optional for Deans):</label>
            <input type="text" name="dept" placeholder="e.g. CSE">
        </div>

        <button type="submit">Test API</button>
    </form>

    <hr>

    <?php if ($result): ?>
        <h3>Result:</h3>
        <?php echo $result; ?>
    <?php endif; ?>
</div>

</body>
</html>
