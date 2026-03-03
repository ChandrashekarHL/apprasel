<?php
// Helper to check appraisal status
function checkAppraisalStatus($pdo, $user_id, $academic_year) {
    $sections = [
        'Academic' => 'ad_academic_source',
        'Research' => 'ad_research_summary',
        'Training' => 'ad_training_summary',
        'Consultancy' => 'ad_consultancy_summary',
        'Administration' => 'ad_administration'
    ];
    
    $statusReport = [];
    
    foreach ($sections as $name => $table) {
        try {
            $stmt = $pdo->prepare("SELECT updated_at FROM $table WHERE faculty_id = ? AND academic_year = ?");
            $stmt->execute([$user_id, $academic_year]);
            $updated_at = $stmt->fetchColumn();
            
            if (!$updated_at) {
                // Determine if table even has data for this user (might be totally empty)
                $statusReport[$name] = ['status' => 'empty', 'days' => -1];
            } else {
                $diff = time() - strtotime($updated_at);
                $days = floor($diff / (60 * 60 * 24));
                
                if ($days > 7) {
                    $statusReport[$name] = ['status' => 'inactive', 'days' => $days];
                } else {
                    $statusReport[$name] = ['status' => 'active', 'days' => $days];
                }
            }
        } catch (Exception $e) {
            // Table might not exist yet or other error
            $statusReport[$name] = ['status' => 'error', 'days' => 0];
        }
    }
    return $statusReport;
}

function getPageSection() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $mapping = [
        'dashboard.php' => 'Dashboard',
        'academic.php' => 'Academic',
        'research.php' => 'Research',
        'training.php' => 'Training',
        'consultancy.php' => 'Consultancy',
        'administration.php' => 'Administration'
    ];
    return $mapping[$current_page] ?? null;
}

function getChatbotConfig($pdo, $user_id, $academic_year) {
    $section = getPageSection();
    if (!$section) return null;

    $report = checkAppraisalStatus($pdo, $user_id, $academic_year);
    $status = $report[$section] ?? null;

    // If status exists (for specific sections), use it
    if ($status) {
        $isTriggered = ($status['status'] == 'inactive' || $status['status'] == 'empty');
        $msg = "Hello! I am Mallika, your AI assistant. How can I help you with your " . $section . " details today?";
        
        if ($isTriggered) {
            $days = ($status['days'] == -1) ? "a while" : $status['days'] . " days";
            $msg = "Hi! I noticed you haven't added anything to the " . $section . " section for " . $days . ".";
            if ($status['status'] == 'empty') {
                $msg = "Hi! It looks like your " . $section . " section is still empty. Let's get started!";
            }
        }

        return [
            'trigger' => $isTriggered,
            'message' => $msg,
            'sectionName' => $section,
            'daysInactive' => $status['days'] ?? 0
        ];
    }
    
    // Fallback for Dashboard or other pages without status tracking
    return [
        'trigger' => false,
        'message' => "Hello! I am Mallika, your AI assistant. Need help with your appraisal?",
        'sectionName' => $section,
        'daysInactive' => 0
    ];
}
?>
