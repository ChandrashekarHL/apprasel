<?php
/**
 * AJAX: Reading List management
 * Actions: add_books | get_books | verify_book | get_context (for Mallika)
 */
require_once 'db_config.php';
require_once 'functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }

$faculty_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

// ─────────────────────────────────────────────────────────
// ACTION: add_books  — save selected books to reading list
// ─────────────────────────────────────────────────────────
if ($action === 'add_books') {
    $books  = $input['books']  ?? [];   // [{title, author, relevance}]
    $course = $input['course'] ?? '';

    if (empty($books)) { echo json_encode(['success'=>false,'message'=>'No books selected']); exit; }

    $sql = "INSERT INTO ad_reading_list (faculty_id, course_title, book_title, author)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE added_at = added_at"; // ignore duplicates

    $stmt = $pdo->prepare($sql);
    $added = 0;
    foreach ($books as $b) {
        $stmt->execute([$faculty_id, $course, trim($b['title']), trim($b['author'] ?? '')]);
        if ($stmt->rowCount() > 0) $added++;
    }
    echo json_encode(['success'=>true, 'added'=>$added, 'message'=>"$added book(s) added to your reading list."]);
    exit;
}

// ─────────────────────────────────────────────────────────
// ACTION: get_books  — fetch faculty reading list
// ─────────────────────────────────────────────────────────
if ($action === 'get_books') {
    $course = $input['course'] ?? '';
    $sql = "SELECT * FROM ad_reading_list WHERE faculty_id = ?";
    $params = [$faculty_id];
    if ($course) { $sql .= " AND course_title = ?"; $params[] = $course; }
    $sql .= " ORDER BY added_at DESC";
    $rows = $pdo->prepare($sql);
    $rows->execute($params);
    echo json_encode(['success'=>true, 'books'=> $rows->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ─────────────────────────────────────────────────────────
// ACTION: verify_book — save assessment + mark verified
// ─────────────────────────────────────────────────────────
if ($action === 'verify_book') {
    $book_id   = intval($input['book_id']   ?? 0);
    $takeaways = trim($input['takeaways']   ?? '');
    $ai_score  = intval($input['ai_score']  ?? 0);
    $ai_feedback = trim($input['ai_feedback'] ?? '');

    if (!$book_id || !$takeaways) { echo json_encode(['success'=>false,'message'=>'Missing data']); exit; }

    $stmt = $pdo->prepare("UPDATE ad_reading_list
        SET status='verified', takeaways=?, ai_score=?, ai_feedback=?, verified_at=NOW()
        WHERE id=? AND faculty_id=?");
    $stmt->execute([$takeaways, $ai_score, $ai_feedback, $book_id, $faculty_id]);

    echo json_encode(['success'=> $stmt->rowCount() > 0, 'message'=> 'Assessment saved!']);
    exit;
}

// ─────────────────────────────────────────────────────────
// ACTION: get_context — for Mallika AI awareness
// ─────────────────────────────────────────────────────────
if ($action === 'get_context') {
    $total   = $pdo->prepare("SELECT COUNT(*) FROM ad_reading_list WHERE faculty_id=?");
    $total->execute([$faculty_id]);
    $planned = $pdo->prepare("SELECT COUNT(*) FROM ad_reading_list WHERE faculty_id=? AND status='planned'");
    $planned->execute([$faculty_id]);
    $verified = $pdo->prepare("SELECT COUNT(*) FROM ad_reading_list WHERE faculty_id=? AND status='verified'");
    $verified->execute([$faculty_id]);
    $recent = $pdo->prepare("SELECT book_title, course_title, status, ai_score FROM ad_reading_list WHERE faculty_id=? ORDER BY added_at DESC LIMIT 3");
    $recent->execute([$faculty_id]);

    echo json_encode([
        'success'   => true,
        'total'     => $total->fetchColumn(),
        'planned'   => $planned->fetchColumn(),
        'verified'  => $verified->fetchColumn(),
        'recent'    => $recent->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action']);
?>
