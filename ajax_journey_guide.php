<?php
require_once 'config_api.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$academic_year = getAcademicYear();

$data = json_decode(file_get_contents('php://input'), true);
$section = $data['section_name'] ?? 'General';

header('Content-Type: application/json');

try {
    // 1. Fetch DB State based on Section
    $db_state = "No specific data found for this section.";
    
    if ($section === 'Academic') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as courses FROM ad_academic_source WHERE faculty_id = ? AND academic_year = ?");
        $stmt->execute([$user_id, $academic_year]);
        $courses = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as reading, SUM(CASE WHEN status='verified' THEN 1 ELSE 0 END) as assessed FROM ad_reading_list WHERE faculty_id = ?");
        $stmt->execute([$user_id]);
        $reading = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $db_state = "Teaching $courses courses. Books in reading list: " . $reading['reading'] . " (" . $reading['assessed'] . " assessed).";
    } elseif ($section === 'Research') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as pubs, SUM(CASE WHEN publication_type='Journal' THEN 1 ELSE 0 END) as journals FROM ad_appraisal_research WHERE faculty_id = ? AND academic_year = ?");
        $stmt->execute([$user_id, $academic_year]);
        $pubs = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_state = "Total publications this year: " . $pubs['pubs'] . " (" . $pubs['journals'] . " journals).";
    } elseif ($section === 'Training') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as programs FROM ad_appraisal_training WHERE faculty_id = ? AND academic_year = ?");
        $stmt->execute([$user_id, $academic_year]);
        $programs = $stmt->fetchColumn();
        $db_state = "Total training programs attended/completed: $programs.";
    } elseif ($section === 'Consultancy') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as projects, SUM(amount_generated) as total_amount FROM ad_appraisal_consultancy WHERE faculty_id = ? AND academic_year = ?");
        $stmt->execute([$user_id, $academic_year]);
        $cons = $stmt->fetch(PDO::FETCH_ASSOC);
        $amount = $cons['total_amount'] ?? 0;
        $db_state = "Total consultancy projects: " . $cons['projects'] . " (Amount generated: $amount).";
    } elseif ($section === 'Administration') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as roles FROM ad_appraisal_administration WHERE faculty_id = ? AND academic_year = ?");
        $stmt->execute([$user_id, $academic_year]);
        $roles = $stmt->fetchColumn();
        $db_state = "Total administrative roles held: $roles.";
    }

    // 2. Fetch Recent DAR Logs
    $stmt = $pdo->prepare("SELECT log_date, description FROM ad_activity_logs WHERE faculty_id = ? AND category = ? ORDER BY log_date DESC LIMIT 5");
    $stmt->execute([$user_id, $section]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $recent_logs = "";
    if (count($logs) > 0) {
        foreach ($logs as $log) {
            $recent_logs .= "- [" . $log['log_date'] . "] " . $log['description'] . "\n";
        }
    } else {
        $recent_logs = "No recent Daily Activity Report logs submitted for the '$section' category.";
    }

    $prompt = "You are 'Mallika', an expert AI academic mentor for university faculty. The user is currently on the '$section' page of their performance appraisal.
    
    Current database state for this section:
    $db_state
    
    Recent Daily Activity Report (DAR) logs for '$section':
    $recent_logs
    
    Task: Act as a deeply personalized mentor. Determine their current phase. In your analysis, directly reference what they wrote in their DAR. 
    Most importantly, for the 'next_step', provide highly actionable, specific, and deep guidance. Do not just say 'do a literature review'; give them specific advice (e.g., 'Refer to recent IEEE/ACM papers on [their topic] to identify research gaps', or 'Try this methodology to improve accuracy'). Guide them step-by-step.
    
    Output strictly as a raw JSON object. Do NOT wrap it in markdown (no ```json). Do not include any outside text.
    {
      \"current_phase\": \"MALLIKA'S ASSESSMENT (e.g. 'Phase: Literature Review')\",
      \"analysis\": \"Encouraging mentor analysis of their DAR entries (2-3 sentences)\",
      \"next_step\": \"Specific, deep actionable advice on how exactly to proceed, referencing their specific topic and strategies (1-2 sentences)\"
    }";
    
    $url = getGeminiApiUrl();
    
    $reqData = [
        'contents' => [
            [
                'parts' => [['text' => $prompt]]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reqData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        throw new Exception("Failed to connect to AI engine (HTTP $httpCode).");
    }
    
    $aiResponse = json_decode($response, true);
    $raw_suggestion = $aiResponse['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
    $raw_suggestion = preg_replace('/```json|```/', '', $raw_suggestion);
    $suggestion = json_decode(trim($raw_suggestion), true);
    
    if (!isset($suggestion['current_phase']) || empty($suggestion['current_phase'])) {
        // Fallback
        $suggestion = [
            'current_phase' => 'Phase: Unknown',
            'analysis' => 'I am currently having trouble analyzing your journey.',
            'next_step' => 'Please continue updating your Daily Activity Reports.'
        ];
    }
    
    echo json_encode(['success' => true, 'guide' => $suggestion]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
