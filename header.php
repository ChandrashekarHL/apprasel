<?php
require_once 'functions.php';
$currentUser = getCurrentUser($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Workload & Academic Effectiveness System (FW-AEMS)</title>c Effectiveness System (FW-AEMS)</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php
    require_once 'ai_helper.php';
    // Check if AI Chatbot should trigger
    $aiConfig = null;
    if (isLoggedIn() && function_exists('getChatbotConfig')) {
        $aiConfig = getChatbotConfig($pdo, $_SESSION['user_id'] ?? 0, getAcademicYear());
    }
    
    if ($aiConfig): ?>
    <!-- Old AI Chat Removed -->
    <?php endif; ?>
</head>
<body>
    <div class="layout-wrapper">
        <aside class="sidebar">
            <div class="logo">
                <img src="assets/logo.png" alt="FW-AEMS Logo" style="max-width: 120px; height: auto; display: block; margin: 0 auto 10px;">
                <span style="font-size: 0.9em; font-weight: bold; color: var(--primary-gold);">FW-AEMS</span>
                <span style="font-size: 0.4em; display: block; opacity: 0.7; line-height: 1.2; margin-top: 5px;">Faculty Workload &<br>Academic Effectiveness</span>
            </div>
            <nav>
                <ul>
                    <?php 
                        $dashLink = (isset($_SESSION['role']) && ($_SESSION['role'] == 'Reviewer' || $_SESSION['role'] == 'Admin')) ? 'hod_dashboard.php' : 'dashboard.php';
                    ?>
                    <li><a href="<?php echo $dashLink; ?>">Dashboard</a></li>
                    <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'Faculty'): ?>
                    <li><a href="academic.php">Academic</a></li>
                    <li><a href="research.php">Research</a></li>
                    <li><a href="training.php">Training</a></li>
                    <li><a href="consultancy.php">Consultancy</a></li>
                    <li><a href="administration.php">Administration</a></li>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'Reviewer'): ?>
                    <li><a href="reviewer_dashboard.php">Reviewer Panel</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        
        <div class="main-content">
            <header class="top-bar">
                <div class="user-info">
                    <?php echo htmlspecialchars($currentUser['username'] ?? 'Guest'); ?> | <a href="logout.php" style="color: var(--primary-dark); text-decoration: none; font-weight: bold;">Logout</a>
                </div>
            </header>
            <main class="container">
