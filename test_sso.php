<?php
/**
 * SSO Test Utility
 * 
 * This script helps test the SSO functionality by simulating an ERP session.
 * Use this to verify the SSO flow works correctly before deploying to production.
 * 
 * USAGE:
 * 1. Access this file in your browser: http://localhost/apprasel/test_sso.php
 * 2. Enter an employee ID that exists in your ERP system
 * 3. Click "Simulate SSO Login"
 * 4. The script will set the session and redirect to sso_login.php
 */

session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emp_id'])) {
    $_SESSION['emp_id'] = $_POST['emp_id'];
    header("Location: sso_login.php");
    exit;
}

// Clear session if requested
if (isset($_GET['clear'])) {
    session_destroy();
    header("Location: test_sso.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSO Test Utility</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.9em;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        
        .info-box h3 {
            color: #1976D2;
            margin-bottom: 8px;
            font-size: 1em;
        }
        
        .info-box p {
            color: #555;
            font-size: 0.9em;
            line-height: 1.6;
        }
        
        .session-status {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        .session-status h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 0.95em;
        }
        
        .session-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .session-item:last-child {
            border-bottom: none;
        }
        
        .session-key {
            font-weight: 600;
            color: #555;
        }
        
        .session-value {
            color: #2196F3;
            font-family: monospace;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 25px;
            border-radius: 4px;
        }
        
        .warning h3 {
            color: #856404;
            margin-bottom: 8px;
            font-size: 1em;
        }
        
        .warning p {
            color: #856404;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-vial"></i>
            SSO Test Utility
        </h1>
        <p class="subtitle">Simulate ERP session for testing SSO auto-login</p>
        
        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> How This Works</h3>
            <p>
                This utility simulates the ERP session by setting <code>$_SESSION['emp_id']</code> 
                and redirecting to the SSO login endpoint. This allows you to test the SSO flow 
                without needing access to the actual ERP system.
            </p>
        </div>
        
        <div class="session-status">
            <h3>Current Session Status</h3>
            <?php if (isset($_SESSION['emp_id'])): ?>
                <div class="session-item">
                    <span class="session-key">Employee ID:</span>
                    <span class="session-value"><?php echo htmlspecialchars($_SESSION['emp_id']); ?></span>
                </div>
                <div class="session-item">
                    <span class="session-key">Status:</span>
                    <span class="session-value" style="color: #4CAF50;">Active</span>
                </div>
            <?php else: ?>
                <div class="session-item">
                    <span class="session-key">Status:</span>
                    <span class="session-value" style="color: #f44336;">No session</span>
                </div>
            <?php endif; ?>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="emp_id">
                    <i class="fas fa-id-badge"></i> Employee ID
                </label>
                <input 
                    type="text" 
                    id="emp_id" 
                    name="emp_id" 
                    placeholder="Enter employee ID (e.g., EMP12345)" 
                    value="<?php echo isset($_SESSION['emp_id']) ? htmlspecialchars($_SESSION['emp_id']) : ''; ?>"
                    required
                >
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Simulate SSO Login
            </button>
            
            <?php if (isset($_SESSION['emp_id'])): ?>
                <a href="?clear=1" class="btn btn-secondary" style="width: 100%; text-align: center;">
                    <i class="fas fa-times-circle"></i> Clear Session
                </a>
            <?php endif; ?>
        </form>
        
        <div class="warning">
            <h3><i class="fas fa-exclamation-triangle"></i> Important Notes</h3>
            <p>
                <strong>1.</strong> The employee ID must exist in the ERP system<br>
                <strong>2.</strong> The ERP API endpoint must be accessible<br>
                <strong>3.</strong> This is for testing only - remove in production
            </p>
        </div>
    </div>
</body>
</html>
