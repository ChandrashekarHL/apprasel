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
    if (isset($_POST['submit_consultancy'])) {
        $title = $_POST['title'];
        $proj_type = $_POST['project_type'];
        $agency = $_POST['funding_agency'];
        $amount = $_POST['amount_sanctioned'];
        $status = $_POST['status'];
        $desc = $_POST['description'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO ad_appraisal_consultancy (faculty_id, academic_year, project_type, title, funding_agency, amount_sanctioned, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $academic_year, $proj_type, $title, $agency, $amount, $status, $desc]);
            $record_id = $pdo->lastInsertId();

            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
                $uploadResult = uploadFile($_FILES['proof_file'], 'uploads/');
                if ($uploadResult['success']) {
                    $fileStmt = $pdo->prepare("INSERT INTO ad_appraisal_files (faculty_id, section_name, record_id, file_path, original_name) VALUES (?, 'Consultancy', ?, ?, ?)");
                    $fileStmt->execute([$user_id, $record_id, $uploadResult['filePath'], $_FILES['proof_file']['name']]);
                } else {
                    throw new Exception($uploadResult['message']);
                }
            }

            $pdo->commit();
            $message = "Consultancy record added successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = "warning";
        }
    } elseif (isset($_POST['submit_summary'])) {
        // Handle Summary Submission
        try {
            $proj_list = $_POST['consultancy_projects_list'];
            $patents = $_POST['patents_filed_list'];
            $workshops = $_POST['innovation_workshops_list'];
            $startup = $_POST['startup_contribution'];

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
                $stmt = $pdo->prepare("SELECT proof_file_path FROM ad_consultancy_summary WHERE faculty_id = ? AND academic_year = ?");
                $stmt->execute([$user_id, $academic_year]);
                $existing = $stmt->fetchColumn();
                $proof_path = $existing;
            }

            $stmt = $pdo->prepare("INSERT INTO ad_consultancy_summary (faculty_id, academic_year, consultancy_projects_list, patents_filed_list, innovation_workshops_list, startup_contribution, proof_file_path) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   consultancy_projects_list = VALUES(consultancy_projects_list), 
                                   patents_filed_list = VALUES(patents_filed_list), 
                                   innovation_workshops_list = VALUES(innovation_workshops_list), 
                                   startup_contribution = VALUES(startup_contribution),
                                   proof_file_path = VALUES(proof_file_path)");
            $stmt->execute([$user_id, $academic_year, $proj_list, $patents, $workshops, $startup, $proof_path]);
            
            $message = "Consultancy summary updated successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error updating summary: " . $e->getMessage();
            $messageType = "warning";
        }
    }
}

// Fetch Records
$stmt = $pdo->prepare("SELECT c.*, f.file_path FROM ad_appraisal_consultancy c LEFT JOIN ad_appraisal_files f ON c.id = f.record_id AND f.section_name = 'Consultancy' WHERE c.faculty_id = ? AND c.academic_year = ? ORDER BY c.created_at DESC");
$stmt->execute([$user_id, $academic_year]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Summary Data
$stmt = $pdo->prepare("SELECT * FROM ad_consultancy_summary WHERE faculty_id = ? AND academic_year = ?");
$stmt->execute([$user_id, $academic_year]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<div class="header-flex" style="display: flex; justify-content: space-between; align-items: center;">
    <h2>Consultancy, IPR & Innovation (<?php echo $academic_year; ?>)</h2>
    <a href="dashboard.php" class="btn btn-secondary" style="background: #95a5a6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px;">&larr; Back to Dashboard</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<?php 
$page_section = 'Consultancy';
require_once 'journey_widget.php';
?>

<!-- Consultancy Summary Form -->
<div class="form-container" style="margin-bottom: 30px;">
    <h3>Consultancy & Innovation Summary (<?php echo $academic_year; ?>)</h3>
     <p class="subtitle" style="margin-top: -10px; margin-bottom: 20px; font-size: 0.9rem; color: #666;">Please list details as requested.</p>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>List out the consultancy Projects undertaken</label>
            <textarea name="consultancy_projects_list" placeholder="List projects..."><?php echo htmlspecialchars($summary['consultancy_projects_list'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>List out the Patents Filed</label>
            <textarea name="patents_filed_list" placeholder="List patents..."><?php echo htmlspecialchars($summary['patents_filed_list'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>List out the Guided/involved in Innovation workshops/Training</label>
            <textarea name="innovation_workshops_list" placeholder="Workshops guided..."><?php echo htmlspecialchars($summary['innovation_workshops_list'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Have you done any contribution to new product development and setting up start-ups?</label>
            <textarea name="startup_contribution" placeholder="Details of product dev or startups..."><?php echo htmlspecialchars($summary['startup_contribution'] ?? ''); ?></textarea>
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
    <h3>Add Project / Innovation</h3>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Category *</label>
            <select name="project_type" required>
                <option value="Consultancy">Consultancy Work</option>
                <option value="Funded Project">Funded Research Project</option>
                <option value="Product Dev">Product Development</option>
                <option value="Start-up">Start-up / Incubation</option>
            </select>
        </div>

        <div class="form-group">
            <label>Project Title *</label>
            <input type="text" name="title" required placeholder="Title of the project">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Funding Agency / Client</label>
                <input type="text" name="funding_agency" placeholder="Name of agency">
            </div>
            <div class="form-group">
                <label>Amount Sanctioned (INR)</label>
                <input type="number" step="0.01" name="amount_sanctioned" placeholder="0.00">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Status *</label>
                <select name="status">
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                    <option value="Proposed">Proposed</option>
                </select>
            </div>
            <div class="form-group">
                <label>Upload Document (Sanction letter etc) *</label>
                <input type="file" name="proof_file" required accept=".pdf,.jpg,.png">
                <small style="color: grey;">Max 5MB.</small>
            </div>
        </div>

        <div class="form-group">
            <label>Description / Details</label>
            <textarea name="description" placeholder="Brief details about the project..."></textarea>
        </div>

        <button type="submit" name="submit_consultancy" class="btn btn-primary">Save Project</button>
    </form>
</div>

<div class="form-container" style="padding: 0; box-shadow: none;">
    <h3>My Projects</h3>
    <?php if (count($records) > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Title</th>
                <th>Client/Agency</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Proof</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
                <td><?php echo htmlspecialchars($record['project_type']); ?></td>
                <td><?php echo htmlspecialchars($record['title']); ?></td>
                <td><?php echo htmlspecialchars($record['funding_agency']); ?></td>
                <td>₹ <?php echo number_format($record['amount_sanctioned'] ?? 0, 2); ?></td>
                <td>
                    <span class="status <?php echo $record['status'] == 'Completed' ? 'completed' : 'pending'; ?>">
                        <?php echo htmlspecialchars($record['status']); ?>
                    </span>
                </td>
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
        <p style="padding: 20px;">No consultancy projects recorded yet.</p>
    <?php endif; ?>
</div>

<?php 
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_consultancy WHERE faculty_id = ? AND academic_year = ?");
$stmtCount->execute([$user_id, $academic_year]);
$section_record_count = $stmtCount->fetchColumn();
require_once 'section_chat_widget.php'; 
?>

<?php require_once 'footer.php'; ?>
