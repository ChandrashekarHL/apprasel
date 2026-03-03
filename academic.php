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

// Handle Form Submission for the Checklist
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_academic_form'])) {
    
    // Check if already submitted
    $check = $pdo->prepare("SELECT id FROM ad_appraisal_academic_defs WHERE faculty_id = ? AND academic_year = ?");
    $check->execute([$user_id, $academic_year]);
    
    if ($check->rowCount() > 0) {
        $message = "You have already submitted the academic process form for this year.";
        $messageType = "warning";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Collect Form Data
            $fields = [
                'weekly_load' => $_POST['weekly_load'],
                'teaching_diary' => $_POST['teaching_diary'],
                'student_register' => $_POST['student_register'],
                'eval_ontime' => $_POST['eval_ontime'],
                'marks_entry_ontime' => $_POST['marks_entry_ontime'],
                'regular_classes' => $_POST['regular_classes'],
                'syllabus_coverage' => $_POST['syllabus_coverage'],
                'attainment_calc' => $_POST['attainment_calc'],
                'materials_developed' => $_POST['materials_developed']
            ];
            
            // Upload proof if any
            $filePath = null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
               $res = uploadFile($_FILES['proof_file'], 'uploads/');
               if ($res['success']) $filePath = $res['filePath'];
            }

            $sql = "INSERT INTO ad_appraisal_academic_defs (faculty_id, academic_year, weekly_load, teaching_diary, student_register, eval_ontime, marks_entry_ontime, regular_classes, syllabus_coverage, attainment_calc, materials_developed, proof_file_path) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $user_id, $academic_year, 
                $fields['weekly_load'], $fields['teaching_diary'], $fields['student_register'],
                $fields['eval_ontime'], $fields['marks_entry_ontime'], $fields['regular_classes'],
                $fields['syllabus_coverage'], $fields['attainment_calc'], $fields['materials_developed'],
                $filePath
            ]);

            $pdo->commit();
            $message = "Academic details submitted successfully!";
            $messageType = "success";

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch Read-Only Academic Data from Source
$stmt = $pdo->prepare("SELECT * FROM ad_academic_source WHERE faculty_id = ? AND academic_year = ?");
$stmt->execute([$user_id, $academic_year]);
$academic_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Previous Form Submission
$stmt = $pdo->prepare("SELECT * FROM ad_appraisal_academic_defs WHERE faculty_id = ? AND academic_year = ?");
$stmt->execute([$user_id, $academic_year]);
$form_submission = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Reading List (table may not exist yet â€” guard with try-catch)
try {
    $rlStmt = $pdo->prepare("SELECT * FROM ad_reading_list WHERE faculty_id = ? ORDER BY status ASC, added_at DESC");
    $rlStmt->execute([$user_id]);
    $reading_list = $rlStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reading_list = []; // table not created yet â€” run run_reading_migration.php
}
$rl_planned  = array_filter($reading_list, fn($r) => $r['status'] === 'planned');
$rl_verified = array_filter($reading_list, fn($r) => $r['status'] === 'verified');



// --- Daily AI Activity Logic ---
$today = date('Y-m-d');
$dailyStmt = $pdo->prepare("SELECT * FROM ad_daily_ai_activity WHERE faculty_id = ? AND activity_date = ?");
$dailyStmt->execute([$user_id, $today]);
$dailyTask = $dailyStmt->fetch(PDO::FETCH_ASSOC);

if (!$dailyTask) {
    // Generate Task (Simulated AI)
    $tasks = [
        "Review student attendance for the current week.",
        "Update the course outcome attainment spreadsheet.",
        "Upload a sample quiz question to the question bank.",
        "Verify your weekly teaching plan against execution.",
        "Check feedback for your latest module.",
        "Organize a brief doubt-clearing session for lagging students."
    ];
    $taskText = $tasks[array_rand($tasks)];
    $ins = $pdo->prepare("INSERT INTO ad_daily_ai_activity (faculty_id, activity_date, activity_text, status) VALUES (?, ?, ?, 'Assigned')");
    $ins->execute([$user_id, $today, $taskText]);
    
    // Auto-mark previous days as Missed if they are still Assigned
    $missedStmt = $pdo->prepare("UPDATE ad_daily_ai_activity SET status='Missed' WHERE faculty_id = ? AND activity_date < ? AND status='Assigned'");
    $missedStmt->execute([$user_id, $today]);
    
    // Refetch
    $dailyStmt->execute([$user_id, $today]);
    $dailyTask = $dailyStmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Completion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_daily_task'])) {
    $upd = $pdo->prepare("UPDATE ad_daily_ai_activity SET status='Completed', completed_at=NOW() WHERE id = ?");
    $upd->execute([$dailyTask['id']]);
    // Refresh to show updated status
    header("Refresh:0");
    exit;
}
// -------------------------------
?>

<div class="header-flex" style="display: flex; justify-content: space-between; align-items: center;">
    <h2>Academic Performance (<?php echo $academic_year; ?>)</h2>
    <a href="dashboard.php" class="btn btn-secondary" style="background: #95a5a6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px;">&larr; Back to Dashboard</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<?php 
$page_section = 'Academic';
require_once 'journey_widget.php';
?>

<!-- Daily AI Activity UI -->
<div class="form-container" style="border-left: 5px solid #3498db; background: #f0f8ff;">
    <h3 style="color: #2980b9;"><i class="fas fa-robot"></i> Daily AI Academic Activity</h3>
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <p style="margin: 0; font-size: 1.1em; color: #2c3e50;">
                Today's Task: <strong><?php echo htmlspecialchars($dailyTask['activity_text']); ?></strong>
            </p>
            <p style="margin: 5px 0 0; font-size: 0.85em; color: #7f8c8d;">
                Date: <?php echo date('d M Y'); ?> | Status: 
                <?php 
                    $stKey = $dailyTask['status'];
                    $stColor = ($stKey == 'Completed') ? 'green' : (($stKey == 'Missed') ? 'red' : 'orange');
                    echo "<span style='color: $stColor; font-weight: bold;'>$stKey</span>";
                ?>
            </p>
        </div>
        <div>
            <?php if ($dailyTask['status'] == 'Assigned'): ?>
                <form method="POST" style="margin:0;">
                    <button type="submit" name="complete_daily_task" class="btn btn-primary" style="background: #2980b9;">
                        <i class="fas fa-check"></i> Mark as Done
                    </button>
                </form>
            <?php elseif ($dailyTask['status'] == 'Completed'): ?>
                <button disabled class="btn" style="background: #27ae60; color: white; opacity: 0.8; cursor: default;">
                     Completed <i class="fas fa-check-circle"></i>
                </button>
            <?php else: ?>
                <button disabled class="btn" style="background: #e74c3c; color: white; opacity: 0.8; cursor: default;">
                     Missed <i class="fas fa-times-circle"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Part 1: Read-Only Data from University API -->
<div class="form-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 style="margin:0;">Courses Taught <span style="font-size:0.75em;background:#27ae60;color:white;padding:2px 8px;border-radius:12px;vertical-align:middle;">University Verified</span></h3>
            <p class="text-muted" style="margin:4px 0 0; font-size:0.88em;">Synced automatically from GMU ERP on login. Click Re-sync to refresh.</p>
        </div>
        <button id="btnResync" onclick="resyncSubjects()" style="background:#2980b9;color:white;border:none;padding:9px 18px;border-radius:6px;cursor:pointer;font-size:0.9em;white-space:nowrap;">
            <i class="fas fa-sync-alt"></i> Re-sync from University
        </button>
    </div>
    <div id="resyncMsg" style="display:none;margin-bottom:10px;"></div>

    <?php if (count($academic_courses) > 0): ?>
    <div style="overflow-x:auto;">
    <table class="data-table" style="min-width:900px;">
        <thead>
            <tr>
                <th>Subject Code</th>
                <th>Course Title</th>
                <th>Program &amp; Semester</th>
                <th>Section</th>
                <th>Term</th>
                <th>Avg Feedback</th>
                <th>Result %</th>
                <th>Attainment</th>
                <th>AI Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($academic_courses as $row): ?>
            <tr>
                <td><?php $sc = trim($row['subject_code'] ?? ''); echo $sc ? '<code style="background:#f0f0f0;padding:2px 7px;border-radius:4px;font-size:0.85em;color:#2c3e50;">'.htmlspecialchars($sc).'</code>' : '<span style="color:#bbb;">&ndash;</span>'; ?></td>




                <td><strong><?php echo htmlspecialchars($row['course_title']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['program_semester']); ?></td>
                <td><?php $sec = trim($row['section'] ?? ''); echo $sec ? htmlspecialchars($sec) : '<span style="color:#bbb;">&ndash;</span>'; ?></td>
                <td>
                    <?php
                        $term = strtoupper($row['term'] ?? '');
                        if ($term === 'ODD') {
                            echo "<span style='background:#8e44ad;color:white;padding:2px 9px;border-radius:12px;font-size:0.8em;font-weight:bold;'>ODD</span>";
                        } elseif ($term === 'EVEN') {
                            echo "<span style='background:#16a085;color:white;padding:2px 9px;border-radius:12px;font-size:0.8em;font-weight:bold;'>EVEN</span>";
                        } else {
                            echo '<span style="color:#bbb;">&ndash;</span>';
                        }
                    ?>
                </td>







                <td><?php $fb = trim($row['avg_student_feedback'] ?? ''); echo $fb ? htmlspecialchars($fb) : '<span style="color:#bbb;">&ndash;</span>'; ?></td>
                <td>
                    <?php
                        echo (isset($row['percentage_result']) && $row['percentage_result'] !== null && $row['percentage_result'] !== '')
                            ? htmlspecialchars($row['percentage_result']) . '%'
                            : '<span style="color:#bbb;">&ndash;</span>';
                    ?>
                </td>
                <td>
                    <?php
                        $level = $row['avg_attainment_level'] ?? null;
                        if ($level && $level !== '') {
                            $color = $level == '1' ? '#27ae60' : ($level == '2' ? '#e67e22' : '#e74c3c');
                            $label = $level == '1' ? 'High' : ($level == '2' ? 'Med' : 'Low');
                            echo "<span style='color:$color;font-weight:bold;'>$level <small>($label)</small></span>";
                        } else { echo '<span style="color:#bbb;">&ndash;</span>'; }
                    ?>
                </td>
                <td>
                    <button type="button"
                            style="background:#17a2b8;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;font-size:0.85em;"
                            onclick="suggestBooks('<?php echo htmlspecialchars($row['course_title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['program_semester'], ENT_QUOTES); ?>')">
                        <i class="fas fa-book"></i> Books
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
        <div style="text-align:center;padding:30px;color:#888;border:2px dashed #ddd;border-radius:8px;">
            <i class="fas fa-database" style="font-size:2.5em;margin-bottom:12px;display:block;color:#bdc3c7;"></i>
            <strong>No subjects found.</strong><br>
            <span style="font-size:0.9em;">Click <strong>"Re-sync from University"</strong> above to fetch your courses from the GMU ERP.</span>
        </div>
    <?php endif; ?>
<!-- Reading List Section -->
<div class="form-container" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
        <div>
            <h3 style="margin:0;">
                <i class="fas fa-book-reader" style="color:#8e44ad;margin-right:8px;"></i>My Reading List
                <?php if(count($reading_list)>0): ?>
                <span style="background:#8e44ad;color:white;border-radius:20px;padding:1px 9px;font-size:0.7em;margin-left:6px;vertical-align:middle;"><?php echo count($reading_list); ?></span>
                <?php endif; ?>
            </h3>
            <p class="text-muted" style="margin:4px 0 0;font-size:0.85em;">Books you plan to read or have assessed. AI Mallika tracks your reading progress.</p>
        </div>
    </div>

    <?php if(count($reading_list) > 0): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:20px;">
        <?php foreach($reading_list as $rl):
            $verified = $rl['status'] === 'verified';
            $score   = intval($rl['ai_score'] ?? 0);
            $stars = str_repeat('&#9733;', $score) . str_repeat('&#9734;', 5-$score);
        ?>
        <div style="border:1px solid #e1e4e8;border-radius:12px;padding:20px;background:#fff;position:relative;box-shadow:0 4px 12px rgba(0,0,0,0.03);display:flex;flex-direction:column;">
            <?php if($verified): ?>
            <span style="position:absolute;top:16px;right:16px;background:#27ae60;color:white;font-size:0.75em;padding:4px 10px;border-radius:20px;font-weight:700;">&#10003; ASSESSED</span>
            <?php else: ?>
            <span style="position:absolute;top:16px;right:16px;background:#fff8e1;color:#f39c12;border:1px solid #ffecb3;font-size:0.75em;padding:4px 12px;border-radius:20px;font-weight:700;">READING</span>
            <?php endif; ?>

            <div style="font-size:1.6em;margin-bottom:12px;color:#3498db;"><i class="fas fa-book"></i></div>
            <div style="font-weight:700;color:#1c2833;font-size:1.05em;line-height:1.3;margin-bottom:6px;padding-right:75px;"><?php echo htmlspecialchars($rl['book_title']); ?></div>
            <?php if($rl['author']): ?>
            <div style="color:#7f8c8d;font-size:0.85em;">by <?php echo htmlspecialchars($rl['author']); ?></div>
            <?php endif; ?>
            <?php if($rl['course_title']): ?>
            <div style="font-size:0.8em;color:#aeb6bf;margin-top:6px;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-graduation-cap"></i><?php echo htmlspecialchars($rl['course_title']); ?>
            </div>
            <?php endif; ?>

            <div style="flex-grow:1;"></div>

            <?php if($verified): ?>
            <div style="margin-top:16px;padding-top:14px;border-top:1px dashed #d5d8dc;">
                <div style="color:#f39c12;letter-spacing:3px;font-size:1.2em;margin-bottom:6px;"><?php echo $stars; ?></div>
                <p style="font-size:0.85em;color:#566573;margin:0;line-height:1.5;"><?php echo htmlspecialchars(substr($rl['ai_feedback']??'',0,120)); ?></p>
            </div>
            <?php else: ?>
            <button onclick="openAssessModal(<?php echo $rl['id']; ?>, '<?php echo htmlspecialchars($rl['book_title'],ENT_QUOTES); ?>', '<?php echo htmlspecialchars($rl['course_title']??'',ENT_QUOTES); ?>')"
                style="width:100%;margin-top:20px;background:#8e44ad;color:white;border:none;padding:12px;border-radius:8px;font-size:0.9em;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background 0.2s;"
                onmouseover="this.style.background='#7d3c98'" onmouseout="this.style.background='#8e44ad'">
                <i class="fas fa-pencil-alt"></i> Submit Assessment
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:40px;color:#bdc3c7;border:1px dashed #d5d8dc;border-radius:12px;background:#fdfefe;">
        <i class="fas fa-book-open" style="font-size:3em;display:block;margin-bottom:14px;color:#eaeded;"></i>
        <strong style="font-size:1.1em;color:#aeb6bf;">No books in your reading list yet.</strong><br>
        <span style="font-size:0.9em;color:#bdc3c7;margin-top:6px;display:inline-block;">Click <strong>Books</strong> on any course above to get AI recommendations and add books.</span>
    </div>
    <?php endif; ?>
</div>

</div>

<!-- Assessment Modal -->
<div id="assessModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:1001;justify-content:center;align-items:center;">
    <div style="background:white;border-radius:14px;width:560px;max-width:94%;position:relative;box-shadow:0 8px 40px rgba(0,0,0,0.25);overflow:hidden;">
        <div style="padding:20px 24px 16px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h3 style="margin:0;font-size:1.05em;color:#2c3e50;"><i class="fas fa-pen-alt" style="color:#8e44ad;margin-right:8px;"></i>Submit Reading Assessment</h3>
                <p id="assessBookName" style="margin:4px 0 0;font-size:0.82em;color:#7f8c8d;"></p>
            </div>
            <span onclick="closeAssessModal()" style="cursor:pointer;font-size:22px;color:#bbb;">&times;</span>
        </div>
        <div style="padding:22px 24px;">
            <div style="background:#f8f9fb;border-radius:8px;padding:13px;margin-bottom:16px;font-size:0.84em;color:#5d6d7e;border-left:4px solid #8e44ad;">
                <strong>How this works:</strong> Write 2&ndash;3 specific things you learned from reading this book. Be specific &ndash; AI Mallika will verify your takeaways and log your reading progress automatically.
            </div>
            <label style="font-size:0.78em;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#7f8c8d;display:block;margin-bottom:6px;">
                <i class="fas fa-lightbulb" style="margin-right:4px;"></i>Key Takeaways from this book
            </label>
            <textarea id="assessTakeaway" rows="5"
                placeholder="e.g. Chapter 5 explains how virtual memory works using paging tables. I learned that TLB (Translation Lookaside Buffer) significantly reduces memory access time. The book also compares segmentation vs paging approaches in detail..."
                style="width:100%;padding:11px 13px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:0.88em;resize:vertical;box-sizing:border-box;font-family:inherit;outline:none;min-height:100px;"
                onfocus="this.style.borderColor='#8e44ad'" onblur="this.style.borderColor='#e0e0e0'"></textarea>
            <input type="hidden" id="assessBookId">
            <input type="hidden" id="assessCourse">
            <div id="assessResult" style="display:none;margin-top:12px;border-radius:8px;padding:13px;"></div>
            <button onclick="submitAssessment()" id="btnAssess"
                style="width:100%;margin-top:12px;background:linear-gradient(135deg,#8e44ad,#6c3483);color:white;border:none;padding:12px;border-radius:8px;font-size:0.95em;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                <i class="fas fa-paper-plane"></i> Submit to AI Mallika
            </button>
        </div>
    </div>
</div>

<!-- AI Book Suggestion Modal -->
<div id="bookModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:white; border-radius:14px; width:620px; max-width:94%; max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 8px 40px rgba(0,0,0,0.25);">

        <!-- Modal Header -->
        <div style="padding:20px 24px 16px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between;">
            <div>
                <h3 id="modalTitle" style="margin:0; font-size:1.05em; color:#2c3e50;">Book Suggestions</h3>
                <p style="margin:3px 0 0; font-size:0.8em; color:#aaa;">AI-recommended textbooks Â· Click a book to select it</p>
            </div>
            <span onclick="closeModal()" style="cursor:pointer; font-size:22px; color:#bbb; line-height:1;">&times;</span>
        </div>

        <!-- Book Cards Area -->
        <div id="modalContent" style="padding:20px 24px 0;">
            <div style="text-align:center; padding:30px; color:#aaa;">
                <i class="fas fa-spinner fa-spin" style="font-size:1.8em; margin-bottom:10px; display:block;"></i>
                Asking AI for recommendations...
            </div>
        </div>

        <!-- Add to Reading List button -->
        <div style="padding:16px 24px 20px; border-top:1px solid #f0f0f0; margin-top:6px;">
            <button id="btnAddToList" onclick="addBooksToList()" disabled
                style="width:100%;background:linear-gradient(135deg,#3498db,#2980b9);color:white;border:none;padding:12px;border-radius:8px;font-size:0.95em;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;opacity:0.5;transition:all 0.2s;">
                <i class="fas fa-bookmark"></i> Select books above
            </button>
            <p style="text-align:center;font-size:0.78em;color:#aaa;margin:8px 0 0;">
                After adding, go to your <strong>Reading List</strong> below to submit your assessment once you've read the book.
            </p>
        </div>
    </div>
</div>




<script>
// Re-sync subjects from University ERP API
function resyncSubjects() {
    const btn = document.getElementById('btnResync');
    const msg = document.getElementById('resyncMsg');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
    msg.style.display = 'none';

    fetch('ajax_sync_subjects.php', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            msg.style.cssText = 'display:block;background:#d4edda;color:#155724;padding:10px 15px;border-radius:6px;border:1px solid #c3e6cb;';
            msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message + ' <strong>Reloading...</strong>';
            setTimeout(() => location.reload(), 1200);
        } else {
            msg.style.cssText = 'display:block;background:#f8d7da;color:#721c24;padding:10px 15px;border-radius:6px;border:1px solid #f5c6cb;';
            msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Re-sync from University';
        }
    })
    .catch(() => {
        msg.style.cssText = 'display:block;background:#f8d7da;color:#721c24;padding:10px 15px;border-radius:6px;';
        msg.innerHTML = '<i class="fas fa-times-circle"></i> Network error. Please try again.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Re-sync from University';
    });
}

let _currentCourse = '';
let _currentProgram = '';
let _selectedBooks = []; // array of {title, author, relevance}

/* â”€â”€ Book Suggestion Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function suggestBooks(course, program) {
    _currentCourse  = course;
    _currentProgram = program;
    _selectedBooks  = [];

    document.getElementById('bookModal').style.display = 'flex';
    document.getElementById('modalTitle').innerText = 'Books for ' + course;
    document.getElementById('modalContent').innerHTML =
        '<div style="text-align:center;padding:30px;color:#aaa;"><i class="fas fa-spinner fa-spin" style="font-size:1.8em;margin-bottom:10px;display:block;"></i>Asking AI for recommendations...</div>';
    updateAddBtn();

    fetch('ai_suggest.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ type: 'books', course: course, program: program })
    })
    .then(r => r.json())
    .then(data => {
        try {
            let raw = (data.suggestion || '').replace(/```json/g,'').replace(/```/g,'').trim();
            const books = JSON.parse(raw);
            if (!Array.isArray(books) || !books.length) throw new Error('empty');

            let html = '<div style="display:flex;flex-direction:column;gap:10px;padding-bottom:6px;">';
            books.forEach((b, i) => {
                html += `
                <div class="book-card" id="bcard_${i}" data-idx="${i}"
                    onclick="toggleBook(${i}, '${b.title.replace(/'/g,"\\'")}', '${(b.author||'').replace(/'/g,"\\'")}', '${(b.relevance||'').replace(/'/g,"\\'")}', this)"
                    style="border:2px solid #e8ecef;border-radius:10px;padding:13px 15px;cursor:pointer;transition:all 0.18s;display:flex;align-items:flex-start;gap:12px;background:#fff;">
                    <div style="width:36px;height:36px;border-radius:8px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1em;">ðŸ“˜</div>
                    <div style="flex:1;">
                        <div style="font-weight:700;color:#2c3e50;font-size:0.92em;">${b.title}</div>
                        <div style="color:#7f8c8d;font-size:0.8em;margin:2px 0;">by ${b.author||'Unknown'}</div>
                        <div style="color:#95a5a6;font-size:0.78em;">${b.relevance||''}</div>
                    </div>
                    <div class="book-check" style="width:22px;height:22px;border:2px solid #ddd;border-radius:6px;flex-shrink:0;margin-top:2px;display:flex;align-items:center;justify-content:center;font-size:0.9em;color:white;"></div>
                </div>`;
            });
            html += '</div>';
            document.getElementById('modalContent').innerHTML = html;
        } catch(e) {
            document.getElementById('modalContent').innerHTML =
                '<p style="color:#888;text-align:center;padding:20px;">Could not load suggestions. Please try again.</p>';
        }
    })
    .catch(() => { document.getElementById('modalContent').innerText = 'Error connecting to AI.'; });
}

function toggleBook(idx, title, author, relevance, el) {
    const already = _selectedBooks.findIndex(b => b.title === title);
    const check   = el.querySelector('.book-check');
    if (already >= 0) {
        _selectedBooks.splice(already, 1);
        el.style.borderColor = '#e8ecef';
        el.style.background  = '#fff';
        check.style.background  = '';
        check.style.borderColor = '#ddd';
        check.innerHTML = '';
    } else {
        _selectedBooks.push({ title, author, relevance });
        el.style.borderColor = '#3498db';
        el.style.background  = '#eaf4ff';
        check.style.background  = '#3498db';
        check.style.borderColor = '#3498db';
        check.innerHTML = 'âœ“';
    }
    updateAddBtn();
}

function updateAddBtn() {
    const btn = document.getElementById('btnAddToList');
    if (!btn) return;
    const n = _selectedBooks.length;
    btn.disabled = n === 0;
    btn.innerHTML = n > 0
        ? `<i class="fas fa-bookmark"></i> Add ${n} Book${n>1?'s':''} to Reading List`
        : `<i class="fas fa-bookmark"></i> Select books above`;
    btn.style.opacity = n > 0 ? '1' : '0.5';
}

function addBooksToList() {
    if (!_selectedBooks.length) return;
    const btn = document.getElementById('btnAddToList');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('ajax_reading_list.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'add_books', books:_selectedBooks, course:_currentCourse })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.style.background = '#27ae60';
            btn.innerHTML = `<i class="fas fa-check"></i> ${data.message} â€” Reloading...`;
            setTimeout(() => { closeModal(); location.reload(); }, 1200);
        } else {
            btn.disabled = false;
            btn.innerHTML = 'âš ï¸ ' + (data.message || 'Error. Try again.');
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = 'âš ï¸ Network error'; });
}

function closeModal() { document.getElementById('bookModal').style.display = 'none'; }

/* â”€â”€ Assessment Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function openAssessModal(bookId, bookTitle, course) {
    document.getElementById('assessBookId').value   = bookId;
    document.getElementById('assessCourse').value   = course;
    document.getElementById('assessBookName').innerText = 'ðŸ“˜ ' + bookTitle;
    document.getElementById('assessTakeaway').value = '';
    document.getElementById('assessResult').style.display = 'none';
    document.getElementById('assessResult').innerHTML = '';
    document.getElementById('btnAssess').disabled = false;
    document.getElementById('btnAssess').innerHTML = '<i class="fas fa-paper-plane"></i> Submit to AI Mallika';
    document.getElementById('assessModal').style.display = 'flex';
}

function closeAssessModal() { document.getElementById('assessModal').style.display = 'none'; }

function submitAssessment() {
    const bookId   = document.getElementById('assessBookId').value;
    const course   = document.getElementById('assessCourse').value;
    const bookName = document.getElementById('assessBookName').innerText.replace('ðŸ“˜ ','');
    const takeaways = document.getElementById('assessTakeaway').value.trim();
    const resultDiv = document.getElementById('assessResult');
    const btn       = document.getElementById('btnAssess');

    if (takeaways.length < 40) {
        alert('Please write at least 2â€“3 specific key takeaways (40+ characters).');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> AI Mallika is verifying...';
    resultDiv.style.display = 'none';

    // Step 1: AI verify takeaways
    fetch('ai_suggest.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ type:'reading_verify', book_title:bookName, course:course, takeaways:takeaways })
    })
    .then(r => r.json())
    .then(data => {
        let res = {};
        try {
            let raw = (data.suggestion||'').replace(/```json/g,'').replace(/```/g,'').trim();
            res = JSON.parse(raw);
        } catch(e) { res = {status:'verified', feedback: data.suggestion, score: 3}; }

        const verified  = (res.status||'').toLowerCase().includes('verified');
        const score     = parseInt(res.score) || 3;
        const stars     = 'â˜…'.repeat(score) + 'â˜†'.repeat(5-score);
        const feedback  = res.feedback || '';

        resultDiv.style.display = 'block';
        if (verified) {
            resultDiv.style.cssText = 'display:block;background:#d4edda;border:1px solid #c3e6cb;border-radius:8px;padding:14px;margin-top:12px;';
            resultDiv.innerHTML = `
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <i class="fas fa-check-circle" style="color:#27ae60;font-size:1.2em;"></i>
                    <strong style="color:#155724;">Verified by AI Mallika!</strong>
                    <span style="margin-left:auto;color:#f39c12;letter-spacing:2px;">${stars}</span>
                </div>
                <p style="margin:0;color:#155724;font-size:0.88em;">${feedback}</p>`;

            // Step 2: Save to DB
            fetch('ajax_reading_list.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ action:'verify_book', book_id:bookId, takeaways:takeaways, ai_score:score, ai_feedback:feedback })
            })
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    resultDiv.innerHTML += '<p style="margin:8px 0 0;font-size:0.82em;color:#155724;"><i class="fas fa-save"></i> Assessment saved. Reloading...</p>';
                    setTimeout(() => { closeAssessModal(); location.reload(); }, 1500);
                }
            });
        } else {
            resultDiv.style.cssText = 'display:block;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:14px;margin-top:12px;';
            resultDiv.innerHTML = `
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <i class="fas fa-exclamation-triangle" style="color:#856404;font-size:1.2em;"></i>
                    <strong style="color:#856404;">Not specific enough yet</strong>
                </div>
                <p style="margin:0;color:#856404;font-size:0.88em;">${feedback || 'Please add more specific chapter references or concepts from the book.'}</p>`;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Try Again';
        }
    })
    .catch(() => {
        resultDiv.style.cssText = 'display:block;';
        resultDiv.innerHTML = '<p style="color:red;">Error verifying. Please try again.</p>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit to AI Mallika';
    });
}

window.onclick = function(e) {
    if (e.target === document.getElementById('bookModal'))   closeModal();
    if (e.target === document.getElementById('assessModal')) closeAssessModal();
};
</script>

</div> <!-- End form-container -->
</div> <!-- End full-page content area -->

<?php 
// Section name is already set at the top for journey_widget.php
$section_record_count = $academicData['courses'] ?? 0; // Use actual count to trigger auto-open
require_once 'section_chat_widget.php'; 
?>

<?php require_once 'footer.php'; ?>

