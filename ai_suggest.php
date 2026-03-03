<?php
ob_start(); // Start output buffering
require_once 'functions.php';
require_once 'config_api.php'; // Secure API configuration

header('Content-Type: application/json');

// Disable display errors to prevent breaking JSON
ini_set('display_errors', 0);
error_reporting(0);

if (!isLoggedIn() && php_sapi_name() !== 'cli') {
    ob_end_clean();
    echo json_encode(['error' => 'Unauthorized', 'suggestion' => 'Authentication required.']);
    exit;
}

$jsonInput = file_get_contents('php://input');
$input = json_decode($jsonInput, true);

// Support for Command Line / Test Mocking
if (!$input && isset($argv[1])) {
    $input = json_decode($argv[1], true);
}
$section = $input['section'] ?? 'General';
$user_input = $input['user_input'] ?? '';
$days = $input['days'] ?? 0;
$type = $input['type'] ?? 'general';
$course = $input['course'] ?? '';
$program = $input['program'] ?? '';

// Use secure API configuration
$url = getGeminiApiUrl();

if ($type === 'books' && !empty($course)) {
    $prompt = "Recommend 4-5 high-quality standard textbooks for the subject '$course' for '$program' level students. Return ONLY a JSON array like: [{\"title\": \"Book Title\", \"author\": \"Author Name\", \"relevance\": \"One sentence on why this book suits this course\"}]. No markdown, no explanation, just the JSON array.";
} elseif ($type === 'reading_verify') {
    $book   = $input['book_title'] ?? 'this book';
    $course = $input['course']      ?? '';
    $points = $input['takeaways']   ?? '';
    $prompt = "You are an academic reading verifier.\nFaculty says they read: \"$book\" (for course: \"$course\").\nTheir key takeaways: \"$points\"\n\nTask: Check if the takeaways are genuine and relevant to the book. Are these points plausible for someone who actually read '$book'?\n1. If the takeaways are relevant and specific (not just generic), mark as verified.\n2. If completely vague, irrelevant, or copied from Wikipedia blurbs, mark as rejected.\n\nOutput ONLY JSON: {\"status\": \"verified\" | \"rejected\", \"feedback\": \"One encouraging sentence about their learning\", \"score\": 1-5}. No markdown.";
} elseif ($type === 'quiz_gen') {
    $book = $input['book_title'] ?? 'this book';
    $prompt = "Generate a single, specific, conceptual question about the book '$book' to test if a reader has actually read it. The question should not be answerable by just reading the back cover. Do not provide the answer. Just the question.";
} elseif ($type === 'quiz_grade') {
    $book = $input['book_title'];
    $question = $input['question'];
    $answer = $input['user_answer'];
    $prompt = "You are a teacher. \nBook: '$book'\nQuestion: '$question'\nStudent Answer: '$answer'\n\nTask: Evaluate if the answer demonstrates that the student has read the book or understands the concept. \nOutput strictly JSON: {\"status\": \"verified\" (or \"rejected\"), \"reason\": \"short explanation\"}. Do not output markdown.";

} elseif ($type === 'section_journey') {
    $section = $input['section_name'] ?? 'General';
    $db_state = $input['section_db_state'] ?? 'No records found.';
    $recent_logs = $input['recent_dar_logs'] ?? 'No recent activity logs for this section.';
    
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

} elseif ($type === 'supervisor_init') {
    $name = $input['name'] ?? 'Faculty';
    $missed = $input['missed_count'] ?? 0;
    
    if ($missed > 0) {
        $prompt = "You are 'Mallika', the AI HOD (Head of Department). Faculty '$name' has missed $missed daily tasks.
        
        Start warmly: 'Good morning, $name. I noticed you've been away for $missed days.'
        Then ask supportively: 'Is everything alright? Are you facing any challenges I can help with?'
        
        Be empathetic and solution-oriented, not accusatory. Under 40 words.";
    } else {
        $prompt = "You are 'Mallika', the AI HOD. Faculty '$name' is up to date.
        
        Start with: 'Good morning, $name!'
        Then ask: 'What are your main priorities today? I'm here if you need any guidance.'
        
        Be warm and encouraging. Under 30 words.";
    }

} elseif ($type === 'daily_briefing_gen') {
    $name = $input['name'] ?? 'Faculty';
    $group = $input['group'] ?? 'General';
    $schedule = $input['schedule'] ?? 'No fixed schedule.';
    $pending = $input['pending'] ?? 'None';
    
    $targets = $input['targets'] ?? [];
    $targetsStr = json_encode($targets);

    $is_new = $input['is_new'] ?? false;
    $activityStatus = $input['activity_status'] ?? [];
    $activityStr = json_encode($activityStatus);

    $prompt = "You are Mallika, the Faculty Performance Manager (HOD Mode).
    User: $name (Group: $group).
    
    1. **Workload Mandate** (Targets):
    $targetsStr
    
    2. **Fixed Teaching**:
    $schedule
    
    3. **Current Activity Status** (How many records faculty has in each section):
    $activityStr
    
    4. **YOUR ORDER**:
    Identify the top 2-3 highest weighted non-academic categories from the Mandate.
    For EACH high priority category:
    - IF activity count is 0 for that category: Assign a FOUNDATIONAL/ONBOARDING task to get started
    - IF activity count is > 0: Assign a CONTINUATION task to build on existing work
    
    Output strictly JSON: {\"briefing\": \"<Your HTML Output>\"}. 
    
    **HTML Structure Requirement:**
    Use a 'Card' layout. 
    <div>
      <h3>Today's Assigned Objectives</h3>
      <p>Based on your Group $group mandate, here are your required actions:</p>
      
      <div style='display:flex; flex-wrap:wrap; gap:15px; margin-top:15px;'>
         <!-- Generate 2-3 Cards like this based on priorities -->
         <div style='background:white; border-left: 5px solid #e74c3c; padding:15px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1); flex:1; min-width:200px;'>
            <h4 style='margin:0 0 10px; color:#c0392b;'><i class='fas fa-flask'></i> [Category Name]</h4>
            <p style='font-size:0.95em; color:#34495e; margin:0;'>[Specific Deliverable]</p>
         </div>
      </div>
      
      <p style='margin-top:15px; font-size:0.85em; color:#7f8c8d;'>* Completing these validates your daily $group contribution.</p>
    </div>
    
    **Category-Specific Task Examples:**
    
    RESEARCH (if count = 0):
    - 'Start: Select a research topic in your field of expertise'
    - 'Begin: Conduct initial literature survey on a current problem'
    - 'Initiate: Define a problem statement for potential publication'
    
    RESEARCH (if count > 0):
    - 'Continue: Review and refine your paper draft'
    - 'Update: Add recent citations to ongoing literature review'
    - 'Progress: Analyze collected data for publication'
    
    TRAINING (if count = 0):
    - 'Start: Identify relevant certification or workshop in your domain'
    - 'Begin: Explore online courses for skill development'
    
    TRAINING (if count > 0):
    - 'Continue: Complete pending course modules'
    - 'Update: Document completed training activities'
    
    CONSULTANCY (if count = 0):
    - 'Start: Identify potential industry collaboration opportunities'
    - 'Begin: Reach out to local industries for consultancy projects'
    
    CONSULTANCY (if count > 0):
    - 'Continue: Progress on ongoing consultancy project'
    - 'Update: Prepare deliverables for client'
    
    ADMINISTRATION (if count = 0):
    - 'Start: Review department policies and responsibilities'
    - 'Begin: Set up your faculty administrative profile'
    
    ADMINISTRATION (if count > 0):
    - 'Continue: Complete pending administrative tasks'
    - 'Update: Review and respond to department communications'
    
    **Rules:**
    - If Research is high priority, make a Research Card with appropriate task based on activity count.
    - If Admin is high priority, make an Admin Card with appropriate task based on activity count.
    - Do NOT make cards for low-priority areas.";

    if ($is_new) {
        $prompt .= "\n\nCRITICAL CONTEXT: This user is a NEW FACULTY/ACCOUNT (Day 1). They have NO active projects. 
        - Do NOT assign 'Reviewing drafts', 'Writing papers', or 'Analyzing data' (they have nothing yet).
        - ASSIGN FOUNDATIONAL TASKS ONLY:
          * Research: 'Select a research topic', 'Conduct initial literature survey', 'Define problem statement'.
          * Admin: 'Set up faculty profile', 'Review dept policies'.
          * Mentoring: 'Identify potential mentees'.
        - Start them at step 0.";
    }

} elseif ($type === 'supervisor_reply') {
    $history = $input['history'] ?? '';
    $last_user_msg = $input['user_msg'];
    $faculty_name = $input['name'] ?? 'Faculty';
    $role = $input['role'] ?? 'Faculty';
    
    if ($role === 'Reviewer' || $role === 'Admin') {
        // HOD/Admin Mode Chat
        $dept_name = $input['department'] ?? 'Department';
        $total_f = $input['total_faculty'] ?? 0;
        $miss_dar = $input['missing_dar_count'] ?? 0;
        $crit = $input['critical_count'] ?? 0;
        $faei = $input['avg_faei'] ?? 0;

        $prompt = "You are 'Mallika', an AI Department Governance Assistant for $dept_name.
        Your User: $faculty_name (HOD/Admin)
        
        Real-Time Department Status:
        - Total Faculty: $total_f
        - Missing DAR Today: $miss_dar
        - Critical Faculty (Flags): $crit
        - Avg Dept Effectiveness: $faei/10

        Role & Task: 
        1. Oversee department compliance and performance.
        2. Be TRUTHFUL and FIRM about compliance gaps. 
        3. If DARs are missing ($miss_dar), you MUST mention this as a priority. Do NOT say 'everything is fine' or 'making great strides' if more than 5 faculty are lagging.
        4. Focus on governance and accountability.

        History: $history
        HOD Message: $last_user_msg

        Output strictly JSON: {\"message\": \"truthful_response\", \"action\": {\"type\": \"agentic_nudge_all\", \"label\": \"Nudge All Lagging\"} | null}. No markdown.";
    } else {
        // Faculty Mode Chat
        $sectionContext = isset($input['section']) && !empty($input['section']) 
            ? "CRITICAL CONTEXT: The user is currently viewing their '" . $input['section'] . "' section. Your primary goal right now is to guide them on completing this specific section. If they haven't filled it, ask them to add a record for " . $input['section'] . "." 
            : "";
            
        $prompt = "You are 'Mallika', the AI HOD assistant for faculty.
        User: $faculty_name (Faculty)
        History: $history
        Latest Message: $last_user_msg
        
        $sectionContext
        
        Rules:
        1. Ask 'What did you accomplish today?' if not reporting.
        2. Help when stuck, be empathetic.
        3. Probe vague responses supportively.

        Output strictly JSON: {\"message\": \"supportive_response\", \"action\": {\"type\": \"log\"|\"assist\"|\"followup\", \"label\": \"Take Action\"} | null}. No markdown.";
    }
} elseif ($type === 'agentic_proactive_check') {
    $name = $input['name'] ?? 'Faculty';
    $role = $input['role'] ?? 'Faculty';
    $faculty_id = $_SESSION['user_id'] ?? ($input['faculty_id'] ?? 1);
    
    if ($role === 'Reviewer' || $role === 'Admin') {
        // HOD Mode — Existing logic remains
        // ... (preserving context)
        $dept_name = $input['department'] ?? 'Department';
        $critical_count = $input['critical_count'] ?? 0;
        $warning_count = $input['warning_count'] ?? 0;
        $missing_dar_count = $input['missing_dar_count'] ?? 0;
        $total_faculty = $input['total_faculty'] ?? 0;
        $avg_faei = $input['avg_faei'] ?? 0;

        $prompt = "You are 'Mallika', an AI Department Performance Assistant. 
        User: $name (Reviewer/HOD for $dept_name)
        
        Department Status Today:
        - Total Faculty: $total_faculty
        - Critical Faculty (RED): $critical_count
        - Warning Faculty (YELLOW): $warning_count
        - Faculty Missing DAR Today: $missing_dar_count
        - Avg Department FAEI: $avg_faei/10
        
        Task: Provide a TRUTHFUL, concise, proactive summary.
        
        CRITICAL GOVERNANCE RULES:
        1. IF $missing_dar_count > 0: You MUST mention that DAR entries are missing. Do NOT say 'most are up to date' if more than 10% are missing (e.g., 18 missing is a serious compliance lag).
        2. IF $critical_count > 0: Report these as urgent performance risks.
        3. Suggest the 'Nudge All Lagging' action if more than 3 faculty are missing DAR.
        4. Be professional and firm about compliance, yet supportive in tone.
        
        Output strictly JSON: 
        {
          \"message\": \"Your truthful proactive summary (under 40 words)\",
          \"trigger_type\": \"hod_alert\",
          \"action\": {
             \"label\": \"Nudge All Lagging\",
             \"type\": \"agentic_nudge_all\",
             \"count\": $missing_dar_count
          }
        }";
    } else {
        // Faculty Mode (Enhanced Awareness)
        $missed_dar = $input['missed_dar'] ?? false;
        $faei = $input['faei'] ?? 0;
        $trend = $input['trend'] ?? 'Stable';
        $recent_logs = $input['recent_logs_count'] ?? 0;
        $books_planned  = $input['books_planned']  ?? 0;
        $books_verified = $input['books_verified'] ?? 0;

        $prompt = "You are 'Mallika', an advanced proactive AI Faculty Assistant. 
        User: $name (Role: $role)
        Current Context:
        - Missing DAR Entry for Today: " . ($missed_dar ? 'YES' : 'NO') . "
        - FAEI Score: $faei/10
        - Performance Trend: $trend
        - Logs recorded today: $recent_logs
        - Reading List: $books_planned book(s) pending read, $books_verified book(s) assessed
        
        Task: Initiate a proactive, supportive conversation.
        
        GUIDELINES:
        1. IF Missed DAR & log count is 0: Prioritize this. 'I noticed you haven't filled your DAR yet...'
        2. IF Trend is 'Improving': Congratulate them warmly! 
        3. IF FAEI is high (>8): Praise their effectiveness.
        4. IF Trend is 'Declining' OR FAEI < 5: Express supportive concern. Offer priority help.
        5. IF books_planned > 0 AND books_verified == 0: Gently remind them about the unread books.
        
        Output strictly JSON: 
        {
          \"message\": \"Your proactive opening message (under 40 words)\",
          \"trigger_type\": \"dar_reminder\" | \"praise\" | \"concern\" | \"escalation\" | \"general\"
        }
        
        Do not use markdown.";
    }


} elseif ($type === 'hod_performance_report') {
    $faculty_id = $input['faculty_id'] ?? 0;
    $faculty_name = $input['faculty_name'] ?? 'Faculty';
    $metrics = $input['metrics'] ?? [];
    $weekly_summary = $input['weekly_summary'] ?? '';
    $conversation_summary = $input['conversation_summary'] ?? '';
    $flag_color = $input['flag_color'] ?? 'yellow';
    
    $faei = $metrics['faei'] ?? 0;
    $weeklyCompletion = $metrics['weekly_completion'] ?? 0;
    $weeksSubmitted = $metrics['weeks_submitted'] ?? 0;
    $totalWeeks = $metrics['total_weeks'] ?? 0;
    $taskCompletion = $metrics['task_completion'] ?? 0;
    $missedCount = $metrics['missed_count'] ?? 0;
    $aiEngagement = $metrics['ai_engagement_days'] ?? 0;
    $trend = $metrics['trend'] ?? 'Unknown';
    
    $prompt = "You are an AI Performance Analyst generating reports for the HOD (Head of Department).
    
    Faculty: $faculty_name
    Performance Flag: $flag_color (RED=Critical, YELLOW=Warning, GREEN=Good)
    
    Comprehensive Metrics:
    - FAEI (Effectiveness Index): $faei/10
    - Weekly Completion Rate: $weeklyCompletion% (average across ALL $totalWeeks semester weeks)
    - Weeks with Plans Submitted: $weeksSubmitted/$totalWeeks
    - Daily Task Completion: $taskCompletion%
    - Missed Daily Tasks (30 days): $missedCount
    - AI Supervisor Engagement: $aiEngagement active days
    - Performance Trend: $trend
    
    Weekly Progress Summary:
    $weekly_summary
    
    AI Conversation Patterns:
    $conversation_summary
    
    Task: Generate a professional, data-driven performance report for HOD review.
    
    Output strictly JSON: {\"report\": \"<HTML content>\"}
    
    HTML Structure Requirements:
    
    1. **Performance Summary** (2-3 sentences):
       - Overall assessment based on flag color
       - Key insight from metrics
       - Brief trend observation
    
    2. **Strengths** (if applicable):
       <ul>
       <li>Specific positive aspects from data</li>
       <li>Highlight good metrics (>70% completion, improving trend, etc.)</li>
       </ul>
       If no strengths, omit this section entirely.
    
    3. **Areas of Concern**:
       <div style='background:#fff3cd; border-left:4px solid #f39c12; padding:15px; margin:10px 0;'>
       <strong>⚠️ Key Issues:</strong>
       <ul>
       <li>Specific problems (low completion, missed tasks, declining trend)</li>
       <li>Be data-specific: 'Weekly completion at 45% vs target 70%'</li>
       </ul>
       </div>
    
    4. **Actionable Recommendations for HOD**:
       <div style='background:#e8f4f8; border-left:4px solid #3498db; padding:15px; margin:10px 0;'>
       <strong>📋 Recommended Actions:</strong>
       <ol>
       <li><strong>Immediate:</strong> [Specific action HOD should take this week]</li>
       <li><strong>Short-term:</strong> [Action for next 2-4 weeks]</li>
       <li><strong>Support needed:</strong> [Resources/interventions]</li>
       </ol>
       </div>
    
    Flag-Specific Guidance:
    
    **If RED flag:**
    - Emphasize URGENCY ('Immediate intervention required')
    - Suggest 1-on-1 meeting with faculty
    - Mention potential impact on department performance
    - Recommend formal performance improvement plan if persistent
    
    **If YELLOW flag:**
    - Balanced tone ('Monitor closely')
    - Suggest check-in conversation
    - Offer support resources (training, mentoring)
    - Set 2-week review milestone
    
    **If GREEN flag:**
    - Acknowledge good performance
    - Suggest growth opportunities (leadership, special projects)
    - Identify 1 area for further development
    - Recommend for recognition/awards if exceptional
    
    Guidelines:
    - Be professional and objective
    - Use data points, not vague statements ('45% completion' not 'low performance')
    - Provide SPECIFIC actions, not generic advice
    - If trend is 'Improving', acknowledge progress
    - If trend is 'Declining', emphasize early intervention
    - Reference conversation patterns if relevant (e.g., 'Faculty seeking help frequently - may need training')
    
    Keep report concise (300-400 words max). Use proper HTML formatting with <strong>, <ul>, <li>, <div> tags.
    Do not use markdown. Output ONLY the JSON.";

} elseif ($type === 'validate_log') {
    $logText = $input['log_text'] ?? '';
    $assignedTask = $input['assigned_task'] ?? 'General Productivity';
    $fileName = $input['file_name'] ?? 'None';
    
    $prompt = "You are an activity auditor.
    Assigned Task: \"$assignedTask\"
    User Log: \"$logText\"
    Proof File: \"$fileName\"
    
    Task: Verify if the User Log reasonably matches the Assigned Task. 
    1. If they match (or if the log is a valid subset), return valid=true.
    2. If the user is logging something completely unrelated (e.g. Assigned 'Write Paper', Logged 'Walked Dog'), return valid=false.
    3. If no file is attached but the task likely needs one (like 'Draft Paper'), mention that in the reason but you can still mark likely valid if text is good. Be lenient but logical.
    
    Output strictly JSON: {\"valid\": true, \"reason\": \"Short explanation (1 sentence)\"}.";
} elseif (!empty($user_input)) {
    // Free-form chat mode
    $prompt = "Context: I am a faculty member using the '$section' section of the University Appraisal System. 
    User Question: \"$user_input\"
    
    Instructions: Answer the user's question directly, professionally, and concisely (under 50 words). If the question is about what to include, give specific examples relevant to '$section'.";
} else {
    // Default suggestion mode
    $prompt = "I am a university faculty member. I have not updated my '$section' appraisal section for $days days. Give me one single, short, specific, and actionable suggestion (under 20 words) on what I can do to improve or update this section. Examples: 'Publish a paper', 'Attend a workshop', etc. Do not be generic.";
}

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Debug Logging using secure config function
logAIDebug("API Call - Type: $type - HTTP Code: $httpCode - Error: $curlError - Response Length: " . strlen($response));

// Clear buffer before outputting JSON
ob_end_clean();

if ($httpCode === 200) {
    $result = json_decode($response, true);
    $suggestion = $result['candidates'][0]['content']['parts'][0]['text'] ?? "Consider updating your records with recent activities.";
    
    file_put_contents('ai_debug.log', "ENTERING PERSISTENT LOG BLOCK - Type: $type, Role: $role, Faculty ID: $faculty_id\n", FILE_APPEND);
    
    // --- PERSISTENT NOTIFICATION LOGGING ---
    if ($type === 'agentic_proactive_check' && $role === 'Faculty') {
        try {
            $jsonStr = preg_replace('/```json|```/', '', $suggestion);
            $aiData = json_decode($jsonStr, true);
            
            $messageToLog = "";
            $nType = "reminder";
            
            if (is_array($aiData) && isset($aiData['message'])) {
                $messageToLog = $aiData['message'];
                $nType = $aiData['trigger_type'] ?? 'reminder';
            } elseif (!empty(trim($suggestion))) {
                // Fallback for raw text response
                $messageToLog = trim($suggestion);
                // Simple heuristic for type
                if (stripos($messageToLog, 'congrat') !== false || stripos($messageToLog, 'excellent') !== false) $nType = 'praise';
                if (stripos($messageToLog, 'lagging') !== false || stripos($messageToLog, 'critical') !== false) $nType = 'escalation';
            }

            if (!empty($messageToLog)) {
                $stmt = $pdo->prepare("INSERT INTO ad_ai_notifications (faculty_id, type, message) VALUES (?, ?, ?)");
                $success = $stmt->execute([$faculty_id, $nType, $messageToLog]);
                
                if (!$success) {
                    file_put_contents('ai_debug.log', "SQL Insert Failed for $faculty_id: " . implode(' ', $stmt->errorInfo()) . "\n", FILE_APPEND);
                } else {
                    file_put_contents('ai_debug.log', "Logged Notif for $faculty_id: [$nType] $messageToLog\n", FILE_APPEND);
                }
            }
        } catch (Exception $e) { 
            file_put_contents('ai_debug.log', "Exception in Notif Log: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    echo json_encode(['suggestion' => trim($suggestion)]);
} else {
    // FALLBACK MECHANISM (Rate Limit or API Down)
    if ($type === 'daily_briefing_gen') {
        // Generate a template-based briefing locally
        $fallbackBriefing = "
        <h3><i class='fas fa-exclamation-triangle' style='color:#f39c12'></i> Offline Briefing</h3>
        <p><b>Good morning, $name.</b><br>
        (AI Service is temporarily busy, but here is your schedule overview)</p>
        
        <div style='background:#f8f9fa; padding:15px; border-left:4px solid #2c3e50; margin:10px 0;'>
            <strong>Today's Schedule:</strong><br>
            " . nl2br($input['schedule']) . "
        </div>
        
        <p><strong>Standard Targets for Group $group:</strong></p>
        <ul>
            <li>Review pending administrative tasks.</li>
            <li>Update course files for today's classes.</li>
            <li>Log any research activity if free time permits.</li>
        </ul>";
        
        echo json_encode(['suggestion' => json_encode(['briefing' => $fallbackBriefing])]);
    } else {
        ob_end_clean();
        echo json_encode(['suggestion' => "AI Service Busy (Rate Limit or API Down). Please try again. (Debug: $httpCode)"]);
    }
}
?>
