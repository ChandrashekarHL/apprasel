<?php
require_once 'db_config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$date = $_POST['date'] ?? date('Y-m-d');

if ($action === 'fetch_history') {
    $stmt = $pdo->prepare("SELECT interaction_log, status FROM ad_daily_ai_activity WHERE faculty_id = ? AND activity_date = ?");
    $stmt->execute([$user_id, $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $logs = [];
    if ($row && $row['interaction_log']) {
        $logs = json_decode($row['interaction_log'], true) ?? [];
    }
    
    echo json_encode(['status' => 'success', 'logs' => $logs, 'task_status' => $row['status'] ?? 'None']);
    exit;
}

if ($action === 'save_interaction') {
    $user_msg = $_POST['user_msg'] ?? '';
    $ai_msg = $_POST['ai_msg'] ?? '';
    
    if (!$user_msg && !$ai_msg) {
        echo json_encode(['status' => 'ignored', 'reason' => 'Empty message']);
        exit;
    }

    // Get current log
    $stmt = $pdo->prepare("SELECT id, interaction_log FROM ad_daily_ai_activity WHERE faculty_id = ? AND activity_date = ?");
    $stmt->execute([$user_id, $date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $logs = json_decode($row['interaction_log'], true) ?? [];
        if ($user_msg) $logs[] = ['role' => 'user', 'text' => $user_msg, 'time' => date('H:i')];
        if ($ai_msg)   $logs[] = ['role' => 'ai',   'text' => $ai_msg,   'time' => date('H:i')];
        
        $newJson = json_encode($logs);
        
        $upd = $pdo->prepare("UPDATE ad_daily_ai_activity SET interaction_log = ? WHERE id = ?");
        $upd->execute([$newJson, $row['id']]);
    }
    
    echo json_encode(['status' => 'saved']);
    exit;
}

if ($action === 'mark_completed') {
    $stmt = $pdo->prepare("UPDATE ad_daily_ai_activity SET status = 'Completed', completed_at = NOW() WHERE faculty_id = ? AND activity_date = ?");
    $stmt->execute([$user_id, $date]);
    echo json_encode(['status' => 'marked_completed']);
    exit;
}

if ($action === 'save_briefing') {
    $content = $_POST['content'] ?? '';
    if ($content) {
        $stmt = $pdo->prepare("UPDATE ad_daily_ai_activity SET briefing_html = ?, briefing_seen = 1 WHERE faculty_id = ? AND activity_date = ?");
        $stmt->execute([$content, $user_id, $date]);
    }
    echo json_encode(['status' => 'briefing_saved']);
    exit;
}

if ($action === 'fetch_all_dates') {
    $stmt = $pdo->prepare("SELECT activity_date, status FROM ad_daily_ai_activity WHERE faculty_id = ? ORDER BY activity_date DESC");
    $stmt->execute([$user_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['dates' => $dates]);
    exit;
}
if ($action === 'agentic_nudge_all') {
    // Only for HOD/Admin
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Get HOD department
    $stmt = $pdo->prepare("SELECT department FROM ad_faculty_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hod = $stmt->fetch();
    $dept = $hod['department'] ?? '';

    if (!$dept) {
        echo json_encode(['status' => 'error', 'message' => 'Department not found']);
        exit;
    }

    // Find faculty missing DAR
    $darMissingStmt = $pdo->prepare("
        SELECT u.id, u.emp_id, u.full_name
        FROM ad_faculty_users u
        LEFT JOIN ac_dar d ON d.EMP_ID = u.emp_id AND d.DATE = CURDATE()
        WHERE d.SL_NO IS NULL
          AND u.role = 'Faculty'
          AND u.department = ?
    ");
    $darMissingStmt->execute([$dept]);
    $toNudge = $darMissingStmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($toNudge as $f) {
        $msg = "Mallika (Sentinel): Your Head of Department has requested a DAR update. This task is currently flagged for active oversight.";
        
        // 1. Send Notification
        $notif = $pdo->prepare("INSERT INTO ad_ai_notifications (faculty_id, type, message) VALUES (?, 'dar_reminder', ?)");
        $notif->execute([$f['id'], $msg]);

        // 2. Create Agentic Oversight Entry (Sentinel Flag)
        $oversight = $pdo->prepare("
            INSERT INTO ad_agentic_oversight (faculty_id, category, message, status) 
            VALUES (?, 'dar_missing', ?, 'active')
            ON DUPLICATE KEY UPDATE status = 'active', created_at = NOW()
        ");
        // Note: We don't have a UNIQUE constraint on faculty_id/category yet, but it's good practice.
        // Actually, just INSERT is fine if we want multiple nudges over time, or check if active first.
        
        $check = $pdo->prepare("SELECT id FROM ad_agentic_oversight WHERE faculty_id = ? AND category = 'dar_missing' AND status = 'active'");
        $check->execute([$f['id']]);
        if (!$check->fetch()) {
            $oversight = $pdo->prepare("INSERT INTO ad_agentic_oversight (faculty_id, category, message, status) VALUES (?, 'dar_missing', ?, 'active')");
            $oversight->execute([$f['id'], "HOD requested DAR update via Mallika Sentinel Loop."]);
        }
        
        $count++;
    }

    echo json_encode(['status' => 'success', 'nudged_count' => $count]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action unrecognized or incomplete']);
?>
