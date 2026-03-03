<?php
/**
 * ERP API Endpoint: Get User by Employee ID
 * 
 * Location: https://erp.gmit.info/api/fwaems/get_user_by_id.php
 * 
 * Purpose: Fetch complete user profile and subject mappings by employee ID
 * Used by: SSO auto-login functionality in the appraisal system
 * 
 * Request Format (JSON):
 * {
 *     "emp_id": "EMP12345"
 * }
 * 
 * Response Format (Success):
 * {
 *     "status": "success",
 *     "data": {
 *         "user": { ... user details ... },
 *         "subject_mapping": [ ... subjects ... ]
 *     }
 * }
 */

// Enable CORS if needed (adjust domain as needed)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Include your ERP database configuration
// IMPORTANT: Update this path to match your ERP database config file
require_once '../config/database.php'; // Adjust path as needed
// OR use direct connection:
// $host = 'localhost';
// $dbname = 'erp_database';
// $username = 'erp_user';
// $password = 'erp_password';
// $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

try {
    // Read JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validate input
    if (!isset($data['emp_id']) || empty($data['emp_id'])) {
        throw new Exception('Employee ID is required');
    }
    
    $empId = trim($data['emp_id']);
    
    // ========================================
    // FETCH USER DATA
    // ========================================
    // IMPORTANT: Update table and column names to match your ERP database schema
    // This is a template - adjust the query based on your actual database structure
    
    $userQuery = "
        SELECT 
            EMP_ID as ID,
            USER_NAME,
            NAME,
            DESIGNATION,
            DEPT as DISCIPLINE,
            SCHOOL,
            MOBILE_NO,
            EMAIL,
            USER_GROUP,
            PHOTO
        FROM staff_master 
        WHERE EMP_ID = :emp_id
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($userQuery);
    $stmt->execute(['emp_id' => $empId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Employee not found');
    }
    
    // ========================================
    // FETCH SUBJECT MAPPINGS
    // ========================================
    // IMPORTANT: Update table and column names to match your ERP database schema
    // This query should fetch all subjects assigned to this faculty member
    
    $subjectQuery = "
        SELECT 
            ACADEMIC_YEAR,
            PROGRAMME,
            COURSE,
            SEM,
            SECTION,
            SUBJECT_CODE,
            SUBJECT,
            SEASON
        FROM subject_allocation
        WHERE EMP_ID = :emp_id
        AND ACADEMIC_YEAR = :current_year
        ORDER BY SEM, SECTION
    ";
    
    // Get current academic year (adjust logic as needed)
    $currentYear = date('Y') . '-' . (date('Y') + 1);
    
    $stmt = $pdo->prepare($subjectQuery);
    $stmt->execute([
        'emp_id' => $empId,
        'current_year' => $currentYear
    ]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========================================
    // RETURN SUCCESS RESPONSE
    // ========================================
    echo json_encode([
        'status' => 'success',
        'data' => [
            'user' => $user,
            'subject_mapping' => $subjects
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // Other errors
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
