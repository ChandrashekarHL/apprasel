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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_training'])) {
        $title = $_POST['title'];
        $prog_type = $_POST['program_type'];
        $org_by = $_POST['organized_by'];
        $duration = $_POST['duration_days'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $outcome = $_POST['outcome'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO ad_appraisal_training (faculty_id, academic_year, program_type, title, organized_by, duration_days, start_date, end_date, outcome) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $academic_year, $prog_type, $title, $org_by, $duration, $start_date, $end_date, $outcome]);
            $record_id = $pdo->lastInsertId();

            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
                $uploadResult = uploadFile($_FILES['proof_file'], 'uploads/');
                if ($uploadResult['success']) {
                    $fileStmt = $pdo->prepare("INSERT INTO ad_appraisal_files (faculty_id, section_name, record_id, file_path, original_name) VALUES (?, 'Training', ?, ?, ?)");
                    $fileStmt->execute([$user_id, $record_id, $uploadResult['filePath'], $_FILES['proof_file']['name']]);
                } else {
                    throw new Exception($uploadResult['message']);
                }
            }

            $pdo->commit();
            $message = "Training record added successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = "warning";
        }
    } elseif (isset($_POST['submit_summary'])) {
        // Handle Summary Submission
        try {
            $taught = $_POST['training_courses_taught'];
            $undergone = $_POST['training_undergone'];
            $fdp_under = $_POST['fdp_undergone'];
            $fdp_cond = $_POST['fdp_conducted'];

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
                $stmt = $pdo->prepare("SELECT proof_file_path FROM ad_training_summary WHERE faculty_id = ? AND academic_year = ?");
                $stmt->execute([$user_id, $academic_year]);
                $existing = $stmt->fetchColumn();
                $proof_path = $existing;
            }

            $stmt = $pdo->prepare("INSERT INTO ad_training_summary (faculty_id, academic_year, training_courses_taught, training_undergone, fdp_undergone, fdp_conducted, proof_file_path) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   training_courses_taught = VALUES(training_courses_taught), 
                                   training_undergone = VALUES(training_undergone), 
                                   fdp_undergone = VALUES(fdp_undergone), 
                                   fdp_conducted = VALUES(fdp_conducted),
                                   proof_file_path = VALUES(proof_file_path)");
            $stmt->execute([$user_id, $academic_year, $taught, $undergone, $fdp_under, $fdp_cond, $proof_path]);
            
            $message = "Training summary updated successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error updating summary: " . $e->getMessage();
            $messageType = "warning";
        }
    }
}

// Fetch Records
$stmt = $pdo->prepare("SELECT t.*, f.file_path FROM ad_appraisal_training t LEFT JOIN ad_appraisal_files f ON t.id = f.record_id AND f.section_name = 'Training' WHERE t.faculty_id = ? AND t.academic_year = ? ORDER BY t.created_at DESC");
$stmt->execute([$user_id, $academic_year]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Summary Data
$stmt = $pdo->prepare("SELECT * FROM ad_training_summary WHERE faculty_id = ? AND academic_year = ?");
$stmt->execute([$user_id, $academic_year]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<div class="header-flex" style="display: flex; justify-content: space-between; align-items: center;">
    <h2>Training & Competency Development (<?php echo $academic_year; ?>)</h2>
    <a href="dashboard.php" class="btn btn-secondary" style="background: #95a5a6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px;">&larr; Back to Dashboard</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<?php 
$page_section = 'Training';
require_once 'journey_widget.php';
?>

<!-- Training Summary Form -->
<div class="form-container" style="margin-bottom: 30px;">
    <h3>Training & Competency Summary (<?php echo $academic_year; ?>)</h3>
    <p class="subtitle" style="margin-top: -10px; margin-bottom: 20px; font-size: 0.9rem; color: #666;">Please list details as requested.</p>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>List of Training Courses Taught with number of students in each course?</label>
            <textarea name="training_courses_taught" placeholder="e.g. 1. Advanced Java (45 students)&#10;2. Python Basics (60 students)"><?php echo htmlspecialchars($summary['training_courses_taught'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>List out the training undergone if any?</label>
            <textarea name="training_undergone" placeholder="Training programs you attended..."><?php echo htmlspecialchars($summary['training_undergone'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>List out the FDP programs undergone any?</label>
            <textarea name="fdp_undergone" placeholder="FDPs you attended..."><?php echo htmlspecialchars($summary['fdp_undergone'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>List of FDP programs conducted if any?</label>
            <textarea name="fdp_conducted" placeholder="FDPs you organized/conducted..."><?php echo htmlspecialchars($summary['fdp_conducted'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Upload Consolidated Proof (Single PDF/ZIP)</label>
            <input type="file" name="summary_proof" accept=".pdf,.zip,.jpg,.png">
            <?php if (!empty($summary['proof_file_path'])): ?>
                <p style="margin-top: 5px; font-size: 0.9em;">
                    Current Proof: <a href="<?php echo htmlspecialchars($summary['proof_file_path']); ?>" target="_blank" style="color: var(--primary-gold);">View File</a>
                </p>
            <?php endif; ?>
        </div>

        <button type="submit" name="submit_summary" class="btn btn-primary">Update Summary</button>
    </form>
</div>

<div class="form-container">
    <h3>Add New Activity</h3>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Program Type *</label>
            <select name="program_type" required>
                <option value="FDP">Faculty Development Program (FDP)</option>
                <option value="Workshop">Workshop</option>
                <option value="Seminar">Seminar / Webinar</option>
                <option value="Course">Certification Course (MOOC/NPTEL)</option>
            </select>
        </div>

        <div class="form-group">
            <label>Topic / Title *</label>
            <input type="text" name="title" required placeholder="Enter topic or title">
        </div>

        <div class="form-group">
            <label>Organized By *</label>
            <input type="text" name="organized_by" required placeholder="Institute or Organization name">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Duration (Days)</label>
                <input type="number" name="duration_days" min="1">
            </div>
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date">
            </div>
        </div>

        <div class="form-group">
            <label>Upload Certificate *</label>
            <input type="file" name="proof_file" required accept=".pdf,.jpg,.png">
            <small style="color: grey;">Max 5MB.</small>
        </div>

        <div class="form-group">
            <label>Learning Outcome</label>
            <textarea name="outcome" placeholder="Briefly describe what was registered/learned..."></textarea>
        </div>

        <button type="submit" name="submit_training" class="btn btn-primary">Save Activity</button>
    </form>
</div>

<div class="form-container" style="padding: 0; box-shadow: none;">
    <h3>My Activities</h3>
    <?php if (count($records) > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Title</th>
                <th>Organized By</th>
                <th>Duration</th>
                <th>Dates</th>
                <th>Proof</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
                <td><?php echo htmlspecialchars($record['program_type']); ?></td>
                <td><?php echo htmlspecialchars($record['title']); ?></td>
                <td><?php echo htmlspecialchars($record['organized_by']); ?></td>
                <td><?php echo htmlspecialchars($record['duration_days']); ?> Days</td>
                <td><?php echo htmlspecialchars($record['start_date']) . ' to ' . htmlspecialchars($record['end_date']); ?></td>
                <td>
                    <?php if ($record['file_path']): ?>
                        <a href="<?php echo htmlspecialchars($record['file_path']); ?>" target="_blank" style="color: var(--accent-color);"><i class="fas fa-paperclip"></i> View</a>
                    <?php else: ?>
                        <span style="color: red;">Missing</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="padding: 20px;">No training activities recorded yet.</p>
    <?php endif; ?>
</div>

<?php 
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_training WHERE faculty_id = ? AND academic_year = ?");
$stmtCount->execute([$user_id, $academic_year]);
$section_record_count = $stmtCount->fetchColumn();
require_once 'section_chat_widget.php'; 
?>

<?php require_once 'footer.php'; ?>
