<?php
/**
 * AJAX endpoint: Re-sync subjects from the GMU ERP API for the logged-in faculty.
 * Called by the "Re-sync from University" button on academic.php.
 * Returns JSON: {success: true/false, count: N, message: "..."}
 */
require_once 'db_config.php';
require_once 'functions.php';
require_once 'ErpService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if we have subject_mapping in session (set during login)
$subjects = $_SESSION['subject_mapping'] ?? [];

// If not in session, try calling the ERP API again using emp_id
if (empty($subjects)) {
    $empId = $_SESSION['erp_profile']['ID'] ?? null;

    if (!$empId) {
        echo json_encode(['success' => false, 'message' => 'No ERP employee ID in session. Please log out and log in again.']);
        exit;
    }

    // Call login API with just emp_id to refresh subject list
    $apiUrl = "https://erp.gmit.info/api/fwaems/get_user_by_id.php";
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['emp_id' => $empId]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        echo json_encode(['success' => false, 'message' => 'Could not reach the ERP server. Check your network.']);
        exit;
    }

    $apiResult = json_decode($response, true);
    if (!isset($apiResult['status']) || strtolower($apiResult['status']) !== 'success') {
        echo json_encode(['success' => false, 'message' => 'ERP API error: ' . ($apiResult['message'] ?? 'Unknown')]);
        exit;
    }

    $subjects = $apiResult['data']['subject_mapping'] ?? [];
    // Update session too
    $_SESSION['subject_mapping'] = $subjects;
}

if (empty($subjects)) {
    echo json_encode(['success' => false, 'message' => 'No subjects found in ERP API response.']);
    exit;
}

// Perform sync
try {
    $erpService = new ErpService($pdo);
    $erpService->syncSubjectsForUser($user_id, $subjects);
    echo json_encode([
        'success' => true,
        'count'   => count($subjects),
        'message' => count($subjects) . ' subject(s) synced from university.'
    ]);
} catch (Exception $e) {
    error_log("Subject sync error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Sync error: ' . $e->getMessage()]);
}
?>
