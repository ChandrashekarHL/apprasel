<?php
require_once 'header.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'Reviewer') {
    header("Location: dashboard.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Invalid Faculty ID");
}

$faculty_id = $_GET['id'];
$academic_year = getAcademicYear();

// Fetch Faculty Details
$stmt = $pdo->prepare("SELECT * FROM ad_faculty_users WHERE id = ?");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    die("Faculty not found");
}

// Handle Grading Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_grade'])) {
    $section = $_POST['section_name'];
    $score = $_POST['score'];
    $max_score = $_POST['max_score'];
    $remarks = $_POST['remarks'];
    $reviewer_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO ad_appraisal_reviews (faculty_id, reviewer_id, academic_year, section_name, score_awarded, max_score, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$faculty_id, $reviewer_id, $academic_year, $section, $score, $max_score, $remarks]);

    echo "<script>alert('Grade saved for $section');</script>";
}

// Handle Final Verdict Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_final'])) {
    $total = $_POST['total_marks'];
    $comments = $_POST['comments'];
    $rec_cand = $_POST['recommendation_candidate'];
    $rec_mgmt = $_POST['recommendation_management'];
    $signature = $_POST['signature'];
    $reviewer_id = $_SESSION['user_id'];

    // Delete existing verdict to overwrite
    $stmt = $pdo->prepare("DELETE FROM ad_appraisal_final_verdict WHERE faculty_id = ? AND academic_year = ?");
    $stmt->execute([$faculty_id, $academic_year]);

    $stmt = $pdo->prepare("INSERT INTO ad_appraisal_final_verdict (faculty_id, reviewer_id, academic_year, total_marks, comments, recommendation_candidate, recommendation_management, signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$faculty_id, $reviewer_id, $academic_year, $total, $comments, $rec_cand, $rec_mgmt, $signature]);

    echo "<script>alert('Final Verdict Submitted Successfully');</script>";
}

// Helper to fetch data for sections
function getSectionData($pdo, $table, $fid, $year) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE faculty_id = ? AND academic_year = ?");
    $stmt->execute([$fid, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$academic_data = getSectionData($pdo, 'ad_academic_source', $faculty_id, $academic_year);
$research_data = getSectionData($pdo, 'ad_appraisal_research', $faculty_id, $academic_year);
$training_data = getSectionData($pdo, 'ad_appraisal_training', $faculty_id, $academic_year);
$consultancy_data = getSectionData($pdo, 'ad_appraisal_consultancy', $faculty_id, $academic_year);

?>

<div class="header-flex" style="display: flex; justify-content: space-between; align-items: center;">
    <h2>Appraisal Review: <?php echo htmlspecialchars($faculty['full_name']); ?></h2>
    <a href="reviewer_dashboard.php" class="btn btn-secondary" style="background: #95a5a6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px;">&larr; Back to List</a>
</div>

<!-- 1. ACADEMIC SECTION (30%) -->
<div class="form-container">
    <h3>1. Academic Performance (Weight: 30%)</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Sem</th>
                <th>Course</th>
                <th>Feedback</th>
                <th>Result</th>
                <th>Class Grade</th>
                <th>Attainment</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($academic_data as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['program_semester']); ?></td>
                <td><?php echo htmlspecialchars($row['course_title']); ?></td>
                <td><?php echo htmlspecialchars($row['avg_student_feedback']); ?></td>
                <td><?php echo htmlspecialchars($row['percentage_result']); ?>%</td>
                <td><?php echo htmlspecialchars($row['class_avg_grade']); ?></td>
                <td><?php echo htmlspecialchars($row['avg_attainment_level']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php
    // Fetch Qualitative Form Data
    $stmt = $pdo->prepare("SELECT * FROM ad_appraisal_academic_defs WHERE faculty_id = ? AND academic_year = ?");
    $stmt->execute([$faculty_id, $academic_year]);
    $acad_form = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>

    <?php if ($acad_form): ?>
    <h4 style="margin-top:20px;">Manual Requirements Checklist</h4>
    <div style="background: #eef2f3; padding: 10px; border-radius: 5px; font-size: 0.9em;">
        <ul style="list-style: none; padding-left: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <li><strong>Weekly Load:</strong> <?php echo $acad_form['weekly_load']; ?></li>
            <li><strong>Teaching Diary:</strong> <?php echo $acad_form['teaching_diary']; ?></li>
            <li><strong>Student Register:</strong> <?php echo $acad_form['student_register']; ?></li>
            <li><strong>Eval OnTime:</strong> <?php echo $acad_form['eval_ontime']; ?></li>
            <li><strong>Marks OnTime:</strong> <?php echo $acad_form['marks_entry_ontime']; ?></li>
            <li><strong>Regular Classes:</strong> <?php echo $acad_form['regular_classes']; ?></li>
            <li><strong>Syllabus Coverage:</strong> <?php echo $acad_form['syllabus_coverage']; ?></li>
            <li><strong>Attainment Calc:</strong> <?php echo $acad_form['attainment_calc']; ?></li>
        </ul>
        <?php if($acad_form['proof_file_path']): ?>
            <p style="margin-top: 10px;"><strong>Proof File:</strong> <a href="<?php echo htmlspecialchars($acad_form['proof_file_path']); ?>" target="_blank">Download</a></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
        <p class="text-muted"><em>No Checklist Form Submitted.</em></p>
    <?php endif; ?>

    <form action="" method="POST" class="form-group" style="margin-top: 15px; background: #f9f9f9; padding: 15px;">
        <input type="hidden" name="section_name" value="Academic">
        <input type="hidden" name="max_score" value="30">
        <label>Award Score (Max 30):</label>
        <input type="number" name="score" max="30" step="0.1" required style="width: 100px; display: inline-block;">
        <input type="text" name="remarks" placeholder="Remarks" style="width: 300px; display: inline-block;">
        <button type="submit" name="submit_grade" class="btn btn-primary" style="padding: 5px 15px;">Save Grade</button>
    </form>
</div>

<!-- 2. RESEARCH SECTION (25%) -->
<div class="form-container">
    <h3>2. Research & Publications (Weight: 25%)</h3>
    <?php if ($research_data): ?>
    <ul>
        <?php foreach ($research_data as $row): ?>
            <li><?php echo htmlspecialchars($row['title']); ?> (<?php echo htmlspecialchars($row['publication_type']); ?>) - Status: <?php echo htmlspecialchars($row['status']); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
        <p>No records submitted.</p>
    <?php endif; ?>

    <form action="" method="POST" class="form-group" style="margin-top: 15px; background: #f9f9f9; padding: 15px;">
        <input type="hidden" name="section_name" value="Research">
        <input type="hidden" name="max_score" value="25">
        <label>Award Score (Max 25):</label>
        <input type="number" name="score" max="25" step="0.1" required style="width: 100px; display: inline-block;">
        <input type="text" name="remarks" placeholder="Remarks" style="width: 300px; display: inline-block;">
        <button type="submit" name="submit_grade" class="btn btn-primary" style="padding: 5px 15px;">Save Grade</button>
    </form>
</div>

<!-- 3. TRAINING SECTION (15%) -->
<div class="form-container">
    <h3>3. Training & Competency (Weight: 15%)</h3>
    <?php if ($training_data): ?>
    <ul>
        <?php foreach ($training_data as $row): ?>
            <li><?php echo htmlspecialchars($row['title']); ?> (<?php echo htmlspecialchars($row['program_type']); ?>)</li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
        <p>No records submitted.</p>
    <?php endif; ?>

    <form action="" method="POST" class="form-group" style="margin-top: 15px; background: #f9f9f9; padding: 15px;">
        <input type="hidden" name="section_name" value="Training">
        <input type="hidden" name="max_score" value="15">
        <label>Award Score (Max 15):</label>
        <input type="number" name="score" max="15" step="0.1" required style="width: 100px; display: inline-block;">
        <input type="text" name="remarks" placeholder="Remarks" style="width: 300px; display: inline-block;">
        <button type="submit" name="submit_grade" class="btn btn-primary" style="padding: 5px 15px;">Save Grade</button>
    </form>
</div>

<!-- 4. CONSULTANCY SECTION (15%) -->
<div class="form-container">
    <h3>4. Consultancy & Innovation (Weight: 15%)</h3>
    <?php if ($consultancy_data): ?>
    <ul>
        <?php foreach ($consultancy_data as $row): ?>
            <li><?php echo htmlspecialchars($row['title']); ?> (<?php echo htmlspecialchars($row['project_type']); ?>) - ₹<?php echo $row['amount_sanctioned']; ?></li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
        <p>No records submitted.</p>
    <?php endif; ?>

    <form action="" method="POST" class="form-group" style="margin-top: 15px; background: #f9f9f9; padding: 15px;">
        <input type="hidden" name="section_name" value="Consultancy">
        <input type="hidden" name="max_score" value="15">
        <label>Award Score (Max 15):</label>
        <input type="number" name="score" max="15" step="0.1" required style="width: 100px; display: inline-block;">
        <input type="text" name="remarks" placeholder="Remarks" style="width: 300px; display: inline-block;">
        <button type="submit" name="submit_grade" class="btn btn-primary" style="padding: 5px 15px;">Save Grade</button>
    </form>
</div>

<!-- 5. ADMINISTRATION SECTION (15%) -->
<div class="form-container">
    <h3>5. Administration (Weight: 15%)</h3>
    <?php
    // Fetch Administration Data
    $stmt = $pdo->prepare("SELECT * FROM ad_administration WHERE faculty_id = ? AND academic_year = ?");
    $stmt->execute([$faculty_id, $academic_year]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>

    <?php if ($admin_data): ?>
    <div style="background: #eef2f3; padding: 10px; border-radius: 5px; font-size: 0.9em; margin-bottom: 15px;">
        <ul style="list-style: none; padding-left: 0;">
            <li><strong>Marketing & Brand Building:</strong> <?php echo nl2br(htmlspecialchars($admin_data['marketing_activities'])); ?></li>
            <li><strong>Student Affairs Involvement:</strong> <?php echo nl2br(htmlspecialchars($admin_data['student_affairs_involvement'])); ?></li>
            <li><strong>Career Advice & Placements:</strong> <?php echo nl2br(htmlspecialchars($admin_data['career_advice_placements'])); ?></li>
            <li><strong>Innovation & Entrepreneurship:</strong> <?php echo nl2br(htmlspecialchars($admin_data['innovation_entrepreneurship'])); ?></li>
            <li><strong>Exam & Evaluation Duties:</strong> <?php echo nl2br(htmlspecialchars($admin_data['exam_evaluation_duties'])); ?></li>
            <li><strong>University Documentation:</strong> <?php echo nl2br(htmlspecialchars($admin_data['university_docs'])); ?></li>
            <li><strong>IQAC Work:</strong> <?php echo nl2br(htmlspecialchars($admin_data['iqac_work'])); ?></li>
            <li><strong>Student Proctoring:</strong> <?php echo nl2br(htmlspecialchars($admin_data['student_proctoring'])); ?></li>
        </ul>
    </div>
    <?php else: ?>
        <p class="text-muted">No administration details submitted.</p>
    <?php endif; ?>

    <form action="" method="POST" class="form-group" style="margin-top: 15px; background: #f9f9f9; padding: 15px;">
        <input type="hidden" name="section_name" value="Administration">
        <input type="hidden" name="max_score" value="15">
        <label>Award Score (Max 15):</label>
        <input type="number" name="score" max="15" step="0.1" required style="width: 100px; display: inline-block;">
        <input type="text" name="remarks" placeholder="Remarks" style="width: 300px; display: inline-block;">
        <button type="submit" name="submit_grade" class="btn btn-primary" style="padding: 5px 15px;">Save Grade</button>
    </form>
</div>

<!-- FINAL VERDICT & RECOMMENDATIONS -->
<div class="form-container" style="border-top: 5px solid var(--primary-dark);">
    <h3>Final Appraisal Verdict</h3>
    
    <?php
    // Calculate Total Score
    $stmt = $pdo->prepare("SELECT SUM(score_awarded) as total FROM ad_appraisal_reviews WHERE faculty_id = ? AND academic_year = ?");
    $stmt->execute([$faculty_id, $academic_year]);
    $total_score = $stmt->fetchColumn();
    
    // Fetch Existing Final Verdict
    $stmt = $pdo->prepare("SELECT * FROM ad_appraisal_final_verdict WHERE faculty_id = ? AND academic_year = ?");
    $stmt->execute([$faculty_id, $academic_year]);
    $verdict = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>

    <div style="font-size: 1.2em; font-weight: bold; margin-bottom: 20px; color: var(--primary-dark);">
        Total Marks Awarded: <?php echo number_format($total_score, 1); ?> / 100
    </div>

    <form action="" method="POST" class="form-group">
        <input type="hidden" name="total_marks" value="<?php echo $total_score; ?>">
        
        <div class="form-group">
            <label>Comments if Any</label>
            <textarea name="comments" rows="3"><?php echo htmlspecialchars($verdict['comments'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Recommendations to the Candidate</label>
            <textarea name="recommendation_candidate" rows="3"><?php echo htmlspecialchars($verdict['recommendation_candidate'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Recommendations to the Management</label>
            <textarea name="recommendation_management" rows="3"><?php echo htmlspecialchars($verdict['recommendation_management'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Signature of Appraising Authority (Name)</label>
            <input type="text" name="signature" value="<?php echo htmlspecialchars($verdict['signature'] ?? ''); ?>" required>
        </div>

        <button type="submit" name="submit_final" class="btn btn-primary">Submit Final Verdict</button>
    </form>
</div>

<?php require_once 'footer.php'; ?>
