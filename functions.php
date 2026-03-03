<?php
require_once 'db_config.php';

session_start();

// Session Check
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if needed, or handle in individual pages. 
    // functions.php is usually included, so we don't redirect here strictly,
    // but we remove the auto-login mock code.
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;

    // PRIMARY: Return from Session (ERP Data) if available
    if (isset($_SESSION['erp_profile'])) {
        $erp = $_SESSION['erp_profile'];
        return [
            'id' => $_SESSION['user_id'], // Local ID for Relationships
            'username' => $erp['USER_NAME'],
            'full_name' => $erp['NAME'],
            'department' => $erp['DISCIPLINE'] ?? $erp['SCHOOL'], // Map Dept
            'designation' => $erp['DESIGNATION'],
            'role' => $_SESSION['role'], // Role from DB/Login Logic
            'photo_url' => '', // Handle photo if needed
            'erp_data' => $erp // Raw access
        ];
    }

    // FALLBACK: Local DB (Legacy/Admin)
    $stmt = $pdo->prepare("SELECT * FROM ad_faculty_users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAcademicYear() {
    $month = (int)date('n');
    $year  = (int)date('Y');
    // Aug–Dec: current year is start year; Jan–Jul: previous year is start year
    $startYear = ($month >= 8) ? $year : $year - 1;
    return $startYear . '-' . ($startYear + 1); // e.g. "2025-2026"
}

// Function to handle file uploads
function uploadFile($file, $destinationDir) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error code: ' . $file['error']];
    }
    
    // Check file size (e.g. 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Max 5MB.'];
    }

    // Allow only specific types (PDF, Images)
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, PNG allowed.'];
    }

    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $destinationDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filePath' => $targetPath];
    }

    return ['success' => false, 'message' => 'Failed to move uploaded file.'];
}
?>
