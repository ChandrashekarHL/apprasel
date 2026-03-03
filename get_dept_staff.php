<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db_config.php';

// Get JSON Input
$data = json_decode(file_get_contents("php://input"));

// Initialize response
$response = array();

// Logic: 
// 1. If 'dept' is provided, fetch by Department (HOD View).
// 2. If 'dept' is empty but 'school' is provided, fetch by School (Dean View).

$emp_id = isset($data->emp_id) ? $data->emp_id : (isset($data->username) ? $data->username : null);
$dept = isset($data->dept) ? $data->dept : null;
$school = isset($data->school) ? $data->school : null;
$faculty = isset($data->faculty) ? $data->faculty : null;

if ($emp_id) {
    try {
        // 1. Identify the Requester's Role
        $stmtRef = $pdo->prepare("SELECT USER_GROUP, DESIGNATION, SCHOOL, FACULTY, DEPT FROM staff_master WHERE EMP_ID = :id");
        $stmtRef->execute([':id' => $emp_id]);
        $requester = $stmtRef->fetch(PDO::FETCH_ASSOC);

        if ($requester) {
            $role = strtoupper($requester['USER_GROUP']);
            $designation = strtoupper($requester['DESIGNATION']);

            $query = "";
            $params = [];

            // --- LOGIC BRANCH 1: VICE CHANCELLOR (VC) ---
            // If VC, show Deans, Directors, Associate Deans, Associate Directors
            if ($role === 'VC' || strpos($designation, 'VICE CHANCELLOR') !== false || $emp_id === 'VC_ID') { // Add specific VC ID checks if needed
                $query = "SELECT SL_NO, EMP_ID, NAME, DESIGNATION, DEPT, SCHOOL, FACULTY, MOBILE, EMAIL, DOJ, USER_GROUP 
                          FROM staff_master 
                          WHERE STATUS = 'WORKING' 
                          AND (USER_GROUP IN ('DEAN', 'DIRECTOR') 
                               OR DESIGNATION LIKE '%DEAN%' 
                               OR DESIGNATION LIKE '%DIRECTOR%')";
                $view_type = "VC View: Leadership Team";
            } 
            
            // --- LOGIC BRANCH 2: DEAN / DIRECTOR ---
            // If Dean/Director, show HODs in their School/Faculty
            elseif (in_array($role, ['DEAN', 'DIRECTOR']) || strpos($designation, 'DEAN') !== false || strpos($designation, 'DIRECTOR') !== false) {
                
                // Use provided school/faculty OR fallback to requester's own
                $target_school = $school ? $school : $requester['SCHOOL'];
                $target_faculty = $faculty ? $faculty : $requester['FACULTY'];

                $query = "SELECT SL_NO, EMP_ID, NAME, DESIGNATION, DEPT, SCHOOL, FACULTY, MOBILE, EMAIL, DOJ, USER_GROUP 
                          FROM staff_master 
                          WHERE STATUS = 'WORKING' 
                          AND (USER_GROUP = 'HOD' OR DESIGNATION LIKE '%HOD%' OR DESIGNATION LIKE '%HEAD%')";

                if ($target_school) {
                    $query .= " AND SCHOOL = :school";
                    $params[':school'] = $target_school;
                } elseif ($target_faculty) {
                     $query .= " AND FACULTY = :faculty";
                     $params[':faculty'] = $target_faculty;
                }
                
                $view_type = "Dean View: HODs in " . ($target_school ?? $target_faculty);
            }

            // --- LOGIC BRANCH 3: HOD ---
            // If HOD, show Staff in their Department
            elseif ($role === 'HOD' || strpos($designation, 'HOD') !== false || strpos($designation, 'HEAD') !== false) {
                
                // Use provided dept OR valid fallback
                $target_dept = $dept ? $dept : $requester['DEPT'];

                $query = "SELECT SL_NO, EMP_ID, NAME, DESIGNATION, DEPT, SCHOOL, FACULTY, MOBILE, EMAIL, DOJ, USER_GROUP 
                          FROM staff_master 
                          WHERE STATUS = 'WORKING' 
                          AND DEPT = :dept
                          AND USER_GROUP NOT IN ('DEAN', 'DIRECTOR', 'VC') 
                          AND EMP_ID != :requester_id"; // Exclude Self

                $params[':dept'] = $target_dept;
                $params[':requester_id'] = $emp_id;
                
                $view_type = "HOD View: Staff in $target_dept";
            } 

            // --- DEFAULT / FALLBACK ---
            else {
                // Return nothing or self? Let's return error for now to enforce hierarchy
                // Or maybe they are just a normal staff checking something?
                 http_response_code(403);
                 echo json_encode(["status" => "error", "message" => "Unauthorized Role for this API: $role"]);
                 exit;
            }

            // EXECUTE QUERY
            if ($query) {
                $query .= " ORDER BY NAME ASC";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $num = $stmt->rowCount();

                if ($num > 0) {
                    $staff_arr = array();
                    $staff_arr["status"] = "success";
                    $staff_arr["count"] = $num;
                    $staff_arr["view_type"] = $view_type;
                    $staff_arr["data"] = array();

                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        extract($row);
                        $staff_item = array(
                            "id" => $SL_NO,
                            "emp_id" => $EMP_ID,
                            "name" => $NAME,
                            "designation" => $DESIGNATION,
                            "department" => $DEPT,
                            "school" => $SCHOOL,
                            "faculty" => $FACULTY,
                            "mobile" => $MOBILE,
                            "email" => $EMAIL,
                            "role" => $USER_GROUP
                        );
                        array_push($staff_arr["data"], $staff_item);
                    }
                    http_response_code(200);
                    echo json_encode($staff_arr);
                } else {
                    http_response_code(404);
                    echo json_encode(["status" => "error", "message" => "No users found for this view.", "view" => $view_type]);
                }
            }

        } else {
             http_response_code(404);
             echo json_encode(["status" => "error", "message" => "Requester Employee ID not found."]);
        }

    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(array("status" => "error", "message" => "Database error: " . $e->getMessage()));
    }
} else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Incomplete data. 'emp_id' is required to determine view permission."));
}
?>
