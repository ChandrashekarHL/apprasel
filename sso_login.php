<?php
/**
 * SSO Auto-Login Endpoint
 * 
 * This endpoint enables Single Sign-On from the main ERP system.
 * Users already authenticated in ERP are automatically logged into the appraisal system.
 * 
 * SIMPLIFIED APPROACH:
 * - If user exists in database: Direct login (no API call needed)
 * - If user doesn't exist: Requires API call to fetch profile (first-time only)
 * 
 * Flow:
 * 1. User clicks button in ERP dashboard (https://erp.gmit.info/fms/dashboard.php)
 * 2. This endpoint reads $_SESSION['emp_id'] from shared session
 * 3. Checks if user exists in local database
 * 4. If exists: Direct login, If not: Fetch from API and sync
 * 5. Creates local session and redirects to appropriate dashboard
 */

require_once 'db_config.php';
require_once 'ErpService.php';

session_start();

// Initialize error message
$error = '';

// Check if employee ID is available in session
if (!isset($_SESSION['emp_id']) || empty($_SESSION['emp_id'])) {
    // No ERP session found - redirect to login page
    header("Location: login.php?error=sso_session_missing");
    exit;
}

$empId = $_SESSION['emp_id'];

try {
    // STEP 1: Check if user already exists in local database
    $stmt = $pdo->prepare("SELECT * FROM ad_faculty_users WHERE emp_id = ?");
    $stmt->execute([$empId]);
    $userDB = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userDB) {
        // ✅ USER EXISTS - Direct login without API call
        // This is the fast path for returning users
        
        $_SESSION['user_id'] = $userDB['id'];
        $_SESSION['role'] = $userDB['role'];
        $_SESSION['username'] = $userDB['username'];
        $_SESSION['full_name'] = $userDB['full_name'];
        
        // Store basic profile data (from database)
        $_SESSION['erp_profile'] = [
            'ID' => $userDB['emp_id'],
            'USER_NAME' => $userDB['username'],
            'NAME' => $userDB['full_name'],
            'DESIGNATION' => $userDB['designation'],
            'DISCIPLINE' => $userDB['department'],
            'SCHOOL' => $userDB['school'] ?? '',
            'MOBILE_NO' => $userDB['mobile'] ?? '',
            'PHOTO' => $userDB['photo_url'] ?? ''
        ];
        
        // Redirect based on role
        if (stripos($userDB['role'], 'Admin') !== false || stripos($userDB['role'], 'Reviewer') !== false) {
            header("Location: hod_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
        
    } else {
        // ❌ USER DOESN'T EXIST - Need to fetch from API (first-time login)
        // This requires the new API endpoint: get_user_by_id.php
        
        $erpService = new ErpService($pdo);
        
        // Fetch user profile from ERP API using employee ID
        $apiResponse = $erpService->fetchUserByEmployeeId($empId);
        
        if (!$apiResponse['success']) {
            // API call failed
            $error = "Failed to fetch user profile from ERP: " . $apiResponse['message'];
            throw new Exception($error);
        }
        
        // Sync user profile to local database
        $syncResult = $erpService->syncFullProfile($apiResponse['data']);
        
        if (!$syncResult['success']) {
            $error = "Failed to sync user profile: " . $syncResult['message'];
            throw new Exception($error);
        }
        
        // Get the newly created user
        $userId = $syncResult['faculty_id'];
        $stmt = $pdo->prepare("SELECT * FROM ad_faculty_users WHERE id = ?");
        $stmt->execute([$userId]);
        $userDB = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userDB) {
            throw new Exception("User not found in database after sync");
        }
        
        // ROLE SYNC: If ERP says HOD, ensure DB says Admin/HOD
        $erpGroup = $apiResponse['data']['user']['USER_GROUP'] ?? '';
        if (strtoupper($erpGroup) === 'HOD' && stripos($userDB['role'], 'Admin') === false) {
            $upd = $pdo->prepare("UPDATE ad_faculty_users SET role = 'Admin' WHERE id = ?");
            $upd->execute([$userId]);
            $userDB['role'] = 'Admin';
        }
        
        // Create local session
        $_SESSION['user_id'] = $userDB['id'];
        $_SESSION['role'] = $userDB['role'];
        $_SESSION['erp_profile'] = $apiResponse['data']['user'];
        $_SESSION['username'] = $apiResponse['data']['user']['USER_NAME'];
        $_SESSION['full_name'] = $apiResponse['data']['user']['NAME'];
        
        // Redirect based on role
        if (stripos($_SESSION['role'], 'Admin') !== false || stripos($_SESSION['role'], 'Reviewer') !== false) {
            header("Location: hod_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
    }
    
} catch (Exception $e) {
    // Log error and redirect to login with error message
    error_log("SSO Login Error: " . $e->getMessage());
    header("Location: login.php?error=sso_failed&message=" . urlencode($e->getMessage()));
    exit;
}
?>
