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

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_research'])) {
        $title = $_POST['title'];
        $pub_type = $_POST['publication_type'];
        $journal = $_POST['journal_name'];
        $date = $_POST['publication_date'];
        $impact = $_POST['impact_factor'];
        $desc = $_POST['description'];

        // Insert Record
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO ad_appraisal_research (faculty_id, academic_year, publication_type, title, journal_name, publication_date, impact_factor, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $academic_year, $pub_type, $title, $journal, $date, $impact, $desc]);
            $record_id = $pdo->lastInsertId();

            // Handle File Upload
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
                $uploadResult = uploadFile($_FILES['proof_file'], 'uploads/');
                if ($uploadResult['success']) {
                    $fileStmt = $pdo->prepare("INSERT INTO ad_appraisal_files (faculty_id, section_name, record_id, file_path, original_name) VALUES (?, 'Research', ?, ?, ?)");
                    $fileStmt->execute([$user_id, $record_id, $uploadResult['filePath'], $_FILES['proof_file']['name']]);
                } else {
                    throw new Exception($uploadResult['message']);
                }
            }

            $pdo->commit();
            $message = "Research record added successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = "warning";
        }
    } elseif (isset($_POST['submit_summary'])) {
        // Handle Summary Submission
        try {
            $phd = $_POST['phd_guidance'];
            $j_count = $_POST['journal_count'];
            $c_count = $_POST['conference_count'];
            $c_org = $_POST['conference_organized'];
            $funding = $_POST['research_funding'];
            $coe = $_POST['coe_member'];
            $gmu = $_POST['gmu_bulletin'];
            
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
                $stmt = $pdo->prepare("SELECT proof_file_path FROM ad_research_summary WHERE faculty_id = ? AND academic_year = ?");
                $stmt->execute([$user_id, $academic_year]);
                $existing = $stmt->fetchColumn();
                $proof_path = $existing;
            }

            $stmt = $pdo->prepare("INSERT INTO ad_research_summary (faculty_id, academic_year, phd_guidance, journal_count, conference_count, conference_organized, research_funding, coe_member, gmu_bulletin, proof_file_path) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   phd_guidance = VALUES(phd_guidance), 
                                   journal_count = VALUES(journal_count), 
                                   conference_count = VALUES(conference_count), 
                                   conference_organized = VALUES(conference_organized), 
                                   research_funding = VALUES(research_funding), 
                                   coe_member = VALUES(coe_member), 
                                   gmu_bulletin = VALUES(gmu_bulletin),
                                   proof_file_path = VALUES(proof_file_path)");
            $stmt->execute([$user_id, $academic_year, $phd, $j_count, $c_count, $c_org, $funding, $coe, $gmu, $proof_path]);
            
            $message = "Research summary updated successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error updating summary: " . $e->getMessage();
            $messageType = "warning";
        }
    }
}

// Fetch Existing Records
$stmt = $pdo->prepare("SELECT r.*, f.file_path FROM ad_appraisal_research r LEFT JOIN ad_appraisal_files f ON r.id = f.record_id AND f.section_name = 'Research' WHERE r.faculty_id = ? AND r.academic_year = ? ORDER BY r.created_at DESC");
$stmt->execute([$user_id, $academic_year]);
$research_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Summary Data
$summaryMsg = [];
$stmt = $pdo->prepare("SELECT * FROM ad_research_summary WHERE faculty_id = ? AND academic_year = ?");
$stmt->execute([$user_id, $academic_year]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch as associative array


?>

<div class="header-flex" style="display: flex; justify-content: space-between; align-items: center;">
    <h2>Research & Publications (<?php echo $academic_year; ?>)</h2>
    <a href="dashboard.php" class="btn btn-secondary" style="background: #95a5a6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px;">&larr; Back to Dashboard</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<?php 
$page_section = 'Research';
require_once 'journey_widget.php';
?>

<!-- Research Summary Form -->
<div class="form-container" style="margin-bottom: 30px;">
    <h3>Annual Research Summary (<?php echo $academic_year; ?>)</h3>
    <form action="" method="POST" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Are you doing any PhD Guidance?</label>
                <input type="text" name="phd_guidance" placeholder="Yes/No or Details" value="<?php echo htmlspecialchars($summary['phd_guidance'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Research-Journal Publications Count</label>
                <input type="number" name="journal_count" placeholder="0" value="<?php echo htmlspecialchars($summary['journal_count'] ?? ''); ?>">
            </div>
             <div class="form-group">
                <label>Conference Publications Count</label>
                <input type="number" name="conference_count" placeholder="0" value="<?php echo htmlspecialchars($summary['conference_count'] ?? ''); ?>">
            </div>
             <div class="form-group">
                <label>Conferences Organized? (How Many)</label>
                <input type="text" name="conference_organized" placeholder="e.g. Yes, 2" value="<?php echo htmlspecialchars($summary['conference_organized'] ?? ''); ?>">
            </div>
             <div class="form-group">
                <label>Did you Avail any Research Funding?</label>
                <input type="text" name="research_funding" placeholder="Yes/No, Amount" value="<?php echo htmlspecialchars($summary['research_funding'] ?? ''); ?>">
            </div>
             <div class="form-group">
                <label>Are you part of Established Centre of Excellence?</label>
                <input type="text" name="coe_member" placeholder="Yes/No" value="<?php echo htmlspecialchars($summary['coe_member'] ?? ''); ?>">
            </div>
             <div class="form-group" style="grid-column: span 2;">
                <label>Any Contribution to GMU-Research Bulletin?</label>
                <input type="text" name="gmu_bulletin" placeholder="Details" value="<?php echo htmlspecialchars($summary['gmu_bulletin'] ?? ''); ?>">
            </div>
             <div class="form-group" style="grid-column: span 2;">
                <label>Upload Consolidated Proof (Single PDF/ZIP)</label>
                <input type="file" name="summary_proof" accept=".pdf,.zip,.jpg,.png">
                <?php if (!empty($summary['proof_file_path'])): ?>
                    <p style="margin-top: 5px; font-size: 0.9em;">
                        Current Proof: <a href="<?php echo htmlspecialchars($summary['proof_file_path']); ?>" target="_blank" style="color: var(--primary-gold);">View File</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <button type="submit" name="submit_summary" class="btn btn-primary">Update Summary</button>
    </form>
</div>

<!-- Add New Record Form -->
<div class="form-container">
    <h3>Add New Publication</h3>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Publication Type *</label>
            <select name="publication_type" required>
                <option value="Journal">Journal</option>
                <option value="Conference">Conference Paper</option>
                <option value="Book Chapter">Book Chapter</option>
                <option value="Patent">Patent</option>
            </select>
        </div>

        <div class="form-group">
            <label>Title of Paper/Patent *</label>
            <input type="text" name="title" required placeholder="Enter full title">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Journal/Conference Name</label>
                <input type="text" name="journal_name" placeholder="e.g. IEEE Transactions on...">
            </div>
            <div class="form-group">
                <label>Publication Date</label>
                <input type="date" name="publication_date">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Impact Factor (if applicable)</label>
                <input type="number" step="0.01" name="impact_factor" placeholder="e.g. 2.5">
            </div>
            <div class="form-group">
                <label>Upload Proof (PDF/Image) *</label>
                <input type="file" name="proof_file" required accept=".pdf,.jpg,.png,.jpeg">
                <small style="color: grey;">Max 5MB. Clear scan or screenshot.</small>
            </div>
        </div>

        <div class="form-group">
            <label>Description / DOI / Link</label>
            <textarea name="description" placeholder="Additional details..."></textarea>
        </div>

        <button type="submit" name="submit_research" class="btn btn-primary">Save Record</button>
    </form>
</div>

<!-- Existing Records List -->
<div class="form-container" style="padding: 0; box-shadow: none;">
    <h3>My Submissions</h3>
    <?php if (count($research_records) > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Title</th>
                <th>Journal/Conf</th>
                <th>Date</th>
                <th>Proof</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($research_records as $record): ?>
            <tr>
                <td><?php echo htmlspecialchars($record['publication_type']); ?></td>
                <td><?php echo htmlspecialchars($record['title']); ?></td>
                <td><?php echo htmlspecialchars($record['journal_name']); ?></td>
                <td><?php echo htmlspecialchars($record['publication_date']); ?></td>
                <td>
                    <?php if ($record['file_path']): ?>
                        <a href="<?php echo htmlspecialchars($record['file_path']); ?>" target="_blank" style="color: var(--accent-color);"><i class="fas fa-paperclip"></i> View</a>
                    <?php else: ?>
                        <span style="color: red;">Missing</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="status <?php echo $record['status'] == 'Published' ? 'completed' : 'pending'; ?>">
                        <?php echo htmlspecialchars($record['status']); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="padding: 20px;">No research records submitted yet.</p>
    <?php endif; ?>
</div>

<?php 
// Check record count to auto-open chat if empty
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ad_appraisal_research WHERE faculty_id = ? AND academic_year = ?");
$stmtCount->execute([$user_id, $academic_year]);
$section_record_count = $stmtCount->fetchColumn();
require_once 'section_chat_widget.php'; 
?>

<?php require_once 'footer.php'; ?>
