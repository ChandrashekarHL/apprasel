<?php
require_once 'db_config.php';
require_once 'ErpService.php';

// 1. Read the mock response
$jsonFile = 'respose_api.txt';
if (!file_exists($jsonFile)) {
    die("Error: Response file not found.");
}

$jsonContent = file_get_contents($jsonFile);
$data = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Invalid JSON in map file - " . json_last_error_msg());
}

// 2. Initialize Service
$erpService = new ErpService($pdo);

// 3. Process the 'data' key from response
if (isset($data['data'])) {
    echo "Processing data for user: " . $data['data']['user']['USER_NAME'] . "...\n";
    
    $result = $erpService->syncFullProfile($data['data']);
    
    if ($result['success']) {
        echo "SUCCESS: Data synced for Faculty ID: " . $result['faculty_id'] . "\n";
        echo "Message: " . $result['message'] . "\n";
    } else {
        echo "FAILED: " . $result['message'] . "\n";
    }

} else {
    echo "Error: 'data' key missing in API response.\n";
}

echo "\n--- Verification ---\n";
echo "Check your database to see if 'SHIVANAGOWDA' was added/updated.\n";
?>
