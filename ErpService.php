<?php
require_once 'db_config.php';

class ErpService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetch user profile from ERP API using Employee ID (for SSO)
     * @param string $empId Employee ID from ERP session
     * @return array API response with user data or error
     */
    public function fetchUserByEmployeeId($empId) {
        $apiUrl = "https://erp.gmit.info/api/fwaems/get_user_by_id.php";
        
        $curl = curl_init();
        
        $postData = ['emp_id' => $empId];
        $jsonData = json_encode($postData);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200 || !$response) {
            return [
                'success' => false,
                'message' => 'Unable to fetch user from ERP: ' . $curlError,
                'http_code' => $httpCode
            ];
        }

        $apiResult = json_decode($response, true);
        
        if (isset($apiResult['status']) && strtolower($apiResult['status']) == 'success') {
            return [
                'success' => true,
                'data' => $apiResult['data']
            ];
        } else {
            return [
                'success' => false,
                'message' => $apiResult['message'] ?? 'Unknown error from ERP API'
            ];
        }
    }

    /**
     * Main function to sync a user and their subjects from the ERP API response data.
     * @param array $apiData The 'data' part of the JSON response.
     */
    public function syncFullProfile($apiData) {
        if (!isset($apiData['user'])) {
            return ['success' => false, 'message' => 'No user data found in API response'];
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Sync User Profile
            $facultyId = $this->syncUser($apiData['user']);

            // 2. Sync Subjects (Academic Source)
            if (isset($apiData['subject_mapping']) && is_array($apiData['subject_mapping'])) {
                $this->syncSubjects($facultyId, $apiData['subject_mapping']);
            }

            $this->pdo->commit();
            return ['success' => true, 'faculty_id' => $facultyId, 'message' => 'Sync successful'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    private function syncUser($userData) {
        // Extract fields
        $username = $userData['USER_NAME']; // Email as username? Or just USER_NAME
        $fullName = $userData['NAME'];
        $empId = $userData['ID'];
        $dept = $userData['DISCIPLINE'] ?? $userData['SCHOOL'] ?? 'Unknown';
        $designation = $userData['DESIGNATION'];
        $mobile = $userData['MOBILE_NO'];
        $school = $userData['SCHOOL'];
        
        // Handle Photo (Nested JSON)
        $photoUrl = '';
        if (!empty($userData['PHOTO'])) {
            $photoArr = json_decode($userData['PHOTO'], true);
            if (is_array($photoArr) && isset($photoArr[0]['name'])) {
                $photoUrl = $photoArr[0]['name'];
            }
        }

        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM ad_faculty_users WHERE username = ? OR emp_id = ?");
        $stmt->execute([$username, $empId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // User exists: Return ID only. DO NOT update details in DB (User Request).
            // Profile details are now handled via Session.
            return $existing['id'];
        } else {
            // Determine Role from ERP Group
            $erpGroup = $userData['USER_GROUP'] ?? 'FACULTY';
            $role = (strtoupper($erpGroup) === 'HOD') ? 'Admin' : 'Faculty';

            // Insert
            // Insert
            $insertSql = "INSERT INTO ad_faculty_users 
                (username, full_name, department, designation, mobile, emp_id, school, photo_url, date_joined, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)";
            
            $insStmt = $this->pdo->prepare($insertSql);
            $insStmt->execute([$username, $fullName, $dept, $designation, $mobile, $empId, $school, $photoUrl, $role]);
            $facultyId = $this->pdo->lastInsertId();
        }

        return $facultyId;
    }

    /**
     * Normalize API academic year format: "2025-26" → "2025-2026"
     */
    private function normalizeAcademicYear($apiYear) {
        // Matches "2025-26" pattern
        if (preg_match('/^(\d{4})-(\d{2})$/', $apiYear, $m)) {
            $startYear = (int)$m[1];
            $endYear = $startYear + 1;
            return "$startYear-$endYear";
        }
        return $apiYear; // Already in full format or unexpected
    }

    /**
     * Public method to allow re-syncing subjects for an existing user (e.g. on every login).
     * Called directly with faculty_id and the subject_mapping array from API response.
     */
    public function syncSubjectsForUser($facultyId, $subjects) {
        if (empty($subjects) || !is_array($subjects)) return;
        $this->syncSubjects($facultyId, $subjects);
    }

    /**
     * Sync subject_mapping from the ERP API into ad_academic_source.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE so it is safe to call on every login.
     */
    private function syncSubjects($facultyId, $subjects) {
        $upsertSql = "INSERT INTO ad_academic_source
            (faculty_id, academic_year, program_semester, course_title, subject_code,
             section, semester, term, is_cc, approved, avg_attainment_level)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                course_title       = VALUES(course_title),
                program_semester   = VALUES(program_semester),
                semester           = VALUES(semester),
                term               = VALUES(term),
                is_cc              = VALUES(is_cc),
                approved           = VALUES(approved)";

        $stmt = $this->pdo->prepare($upsertSql);

        foreach ($subjects as $sub) {
            $acYear  = $this->normalizeAcademicYear($sub['ACADEMIC_YEAR']); // "2025-2026"
            $program = $sub['PROGRAMME'] ?? 'UG';     // "UG"
            $course  = $sub['COURSE']    ?? '';        // "BTECH"
            $sem     = (int)($sub['SEM'] ?? 0);
            $section = $sub['SECTION']   ?? '';
            $subCode = $sub['SUBJECT_CODE'] ?? '';
            $subName = $sub['SUBJECT']   ?? '';
            $term    = $sub['SEASON']    ?? '';        // "ODD" / "EVEN"
            $isCC    = isset($sub['CC_FLAG']) ? (int)$sub['CC_FLAG'] : 0;
            $approved = isset($sub['APPROVED']) ? (int)$sub['APPROVED'] : 1;

            // Friendly label: "B.Tech UG — Sem 5"
            $progSem = trim("$course $program") . " — Sem $sem";

            // Skip rows missing critical identifiers
            if (empty($subCode) || empty($section)) continue;

            $stmt->execute([
                $facultyId, $acYear, $progSem, $subName, $subCode,
                $section, $sem, $term, $isCC, $approved,
                2   // Default avg_attainment_level (medium) — updated separately
            ]);
        }
    }
}
?>
