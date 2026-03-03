<?php
require_once 'header.php';

if (!isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$academic_year = getAcademicYear();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_admin'])) {
    try {
        $marketing = $_POST['marketing_activities'];
        $student_affairs = $_POST['student_affairs_involvement'];
        $career = $_POST['career_advice_placements'];
        $innovation = $_POST['innovation_entrepreneurship'];
        $exam = $_POST['exam_evaluation_duties'];
        $univ = $_POST['university_docs'];
        $iqac = $_POST['iqac_work'];
        $proctoring = $_POST['student_proctoring'];

        $proof_path = null;
        // Handle File Upload
        if (isset($_FILES['summary_proof']) && $_FILES['summary_proof']['error'] == 0) {
            $uploadResult = uploadFile($_FILES['summary_proof'], 'uploads/');
            if ($uploadResult['success']) {
                $proof_path = $uploadResult['filePath'];
            } else {
                throw new Exception($uploadResult['message']);
            }
        }

        // Fetch existing proof if not uploading new one
        if (!$proof_path) {
            $stmt = $pdo->prepare("SELECT proof_file_path FROM ad_administration WHERE faculty_id = ? AND academic_year = ?");
            $stmt->execute([$user_id, $academic_year]);
            $existing = $stmt->fetchColumn();
            $proof_path = $existing;
        }

        $stmt = $pdo->prepare("INSERT INTO ad_administration (faculty_id, academic_year, marketing_activities, student_affairs_involvement, career_advice_placements, innovation_entrepreneurship, exam_evaluation_duties, university_docs, iqac_work, student_proctoring, proof_file_path) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               marketing_activities = VALUES(marketing_activities), 
                               student_affairs_involvement = VALUES(student_affairs_involvement), 
                               career_advice_placements = VALUES(career_advice_placements), 
                               innovation_entrepreneurship = VALUES(innovation_entrepreneurship), 
                               exam_evaluation_duties = VALUES(exam_evaluation_duties), 
                               university_docs = VALUES(university_docs), 
                               iqac_work = VALUES(iqac_work), 
                               student_proctoring = VALUES(student_proctoring),
                               proof_file_path = VALUES(proof_file_path)");
        $stmt->execute([$user_id, $academic_year, $marketing, $student_affairs, $career, $innovation, $exam, $univ, $iqac, $proctoring, $proof_path]);
        
        $message = "Administration details updated successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error updating details: " . $e->getMessage();
        $messageType = "warning";
    }
}

// Fetch Data
$stmt = $pdo->prepare("SELECT * FROM ad_administration WHERE faculty_id = ? AND academic_year = ?");
$stmt->execute([$user_id, $academic_year]);
$admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<div class="header-flex" style="display: flex; justify-content: space-between; align-items: center;">
    <h2>Administration (<?php echo $academic_year; ?>)</h2>
    <a href="dashboard.php" class="btn btn-secondary" style="background: #95a5a6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px;">&larr; Back to Dashboard</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<?php 
$page_section = 'Administration';
require_once 'journey_widget.php';
?>

<div class="form-container">
    <h3>Administrative Responsibilities (Weight: 15%)</h3>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Any Marketing and Brand Building activities done by you? How many?</label>
            <textarea name="marketing_activities" placeholder="Details..."><?php echo htmlspecialchars($admin_data['marketing_activities'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Have your ever involved in Student Affairs? And found any out comes?</label>
            <textarea name="student_affairs_involvement" placeholder="Details..."><?php echo htmlspecialchars($admin_data['student_affairs_involvement'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Are you a part of Career Advice and Students Placements?</label>
            <textarea name="career_advice_placements" placeholder="Details..."><?php echo htmlspecialchars($admin_data['career_advice_placements'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Have you involved in Innovation and Entrepreneurship?</label>
            <textarea name="innovation_entrepreneurship" placeholder="Details..."><?php echo htmlspecialchars($admin_data['innovation_entrepreneurship'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Have you done Examination and Evaluation and how many?</label>
            <textarea name="exam_evaluation_duties" placeholder="Details..."><?php echo htmlspecialchars($admin_data['exam_evaluation_duties'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>How many Universities Documentation done so far?</label>
            <textarea name="university_docs" placeholder="Details..."><?php echo htmlspecialchars($admin_data['university_docs'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Have you done IQAC work?</label>
            <textarea name="iqac_work" placeholder="Details..."><?php echo htmlspecialchars($admin_data['iqac_work'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>How did you do the Student Proctoring?</label>
            <textarea name="student_proctoring" placeholder="Details..."><?php echo htmlspecialchars($admin_data['student_proctoring'] ?? ''); ?></textarea>
        </div>

         <div class="form-group">
            <label>Upload Consolidated Proof (Single PDF/ZIP)</label>
            <input type="file" name="summary_proof" accept=".pdf,.zip,.jpg,.png">
            <?php if (!empty($admin_data['proof_file_path'])): ?>
                <p style="margin-top: 5px; font-size: 0.9em;">
                    Current Proof: <a href="<?php echo htmlspecialchars($admin_data['proof_file_path']); ?>" target="_blank" style="color: var(--primary-gold);">View File</a>
                </p>
            <?php endif; ?>
        </div>

        <button type="submit" name="submit_admin" class="btn btn-primary">Save Details</button>
    </form>
</div>

<?php 
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ad_administration WHERE faculty_id = ? AND academic_year = ?");
$stmtCount->execute([$user_id, $academic_year]);
$section_record_count = $stmtCount->fetchColumn();
require_once 'section_chat_widget.php'; 
?>

<?php require_once 'footer.php'; ?>
