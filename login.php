    <?php
    require_once 'db_config.php';
    session_start();

    $message = '';

    require_once 'ErpService.php';

    // Handle SSO error messages from URL parameters
    if (isset($_GET['error'])) {
        if ($_GET['error'] === 'sso_session_missing') {
            $message = 'SSO session not found. Please login from the main ERP system or use the form below.';
        } elseif ($_GET['error'] === 'sso_failed' && isset($_GET['message'])) {
            $message = 'SSO Login Failed: ' . htmlspecialchars($_GET['message']);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // --- ERP API INTEGRATION ---
        $apiUrl = "https://erp.gmit.info/api/fwaems/login.php"; // Updated URL from screenshot
        
        // START: Real API Call Logic
        $curl = curl_init();
        
        $postData = [
            'username' => $username,
            'password' => $password
        ];
        $jsonData = json_encode($postData);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonData, // Send JSON
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json' // Set Header
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));

        $response = curl_exec($curl);
        $curlError = curl_error($curl); // Capture Curl Errors
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // Fallback for Demo/Testing if API fails or is unreachable (e.g., in dev environment)
        // REMOVE THIS BLOCK IN PRODUCTION if strictly enforcing API
        // Fallback for Demo/Testing if API fails (DEV ONLY)
        // NOTE: Passwords are NOT stored locally. This fallback allows login by Username only in DEV.
        // DISABLE THIS in Production.
        if ($httpCode !== 200 || !$response) {
            // Local DB Fallback (Username Only)
            $stmt = $pdo->prepare("SELECT * FROM ad_faculty_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Redirect based on role
                if (stripos($user['role'], 'Admin') !== false || stripos($user['role'], 'Reviewer') !== false) {
                    header("Location: hod_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            }
        }

        // Process API Response
        if ($response) {
            $apiResult = json_decode($response, true);
            
            // Check if API returned success
            // Based on sample: {"status":"success", ...}
            if (isset($apiResult['status']) && strtolower($apiResult['status']) == 'success') {
                
                // Sync user profile to Local DB (first-time creates, returning users just fetch ID)
                $erpService = new ErpService($pdo);
                $syncResult = $erpService->syncFullProfile($apiResult['data']);
                
                if ($syncResult['success']) {
                    $userId = $syncResult['faculty_id'];

                    // ALWAYS re-sync subjects on every login (refreshes current semester subjects)
                    if (!empty($apiResult['data']['subject_mapping'])) {
                        $erpService->syncSubjectsForUser($userId, $apiResult['data']['subject_mapping']);
                    }
                    
                    // Fetch basic DB row for ID/Role, but use API for details
                    $stmt = $pdo->prepare("SELECT * FROM ad_faculty_users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $userDB = $stmt->fetch(PDO::FETCH_ASSOC);

                    // ROLE SYNC: If ERP says HOD, ensure DB says Admin/HOD
                    $erpGroup = $apiResult['data']['user']['USER_GROUP'] ?? '';
                    if (strtoupper($erpGroup) === 'HOD' && stripos($userDB['role'], 'Admin') === false) {
                        $upd = $pdo->prepare("UPDATE ad_faculty_users SET role = 'Admin' WHERE id = ?");
                        $upd->execute([$userId]);
                        $userDB['role'] = 'Admin'; 
                    }

                    // STORE IN SESSION (Primary Source of Truth now)
                    $_SESSION['user_id']        = $userDB['id'];
                    $_SESSION['role']           = $userDB['role'];
                    $_SESSION['erp_profile']    = $apiResult['data']['user'];
                    $_SESSION['username']        = $apiResult['data']['user']['USER_NAME'];
                    $_SESSION['full_name']       = $apiResult['data']['user']['NAME'];
                    $_SESSION['subject_mapping'] = $apiResult['data']['subject_mapping'] ?? [];
                    
                    // Redirect based on HOD/Admin role
                    if (stripos($_SESSION['role'], 'Admin') !== false || stripos($_SESSION['role'], 'Reviewer') !== false) {
                        header("Location: hod_dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit;
                } else {
                    $message = "Login successful but failed to sync profile: " . $syncResult['message'];
                }
            } else {
                // API Login Failed
                // Show more detail for debugging
                $msgDetail = isset($apiResult['message']) ? $apiResult['message'] : 'Unknown Error';
                $message = "API Login Failed: " . $msgDetail;
                
                // Uncomment to see raw response on screen if needed:
                // $message .= " | Raw: " . htmlspecialchars(substr($response, 0, 100));
            }
        } else {
            $message = "Unable to connect to ERP Server. Error: " . $curlError;
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - FW-AEMS</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link href="assets/style.css" rel="stylesheet">
        <style>
            :root {
                --primary-dark: #5b1f1f;
                --primary-gold: #e9c66f;
                --secondary-gold: #f7f3b7;
                --white: #ffffff;
                --light-gray: #f8f9fa;
            }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: radial-gradient(circle at center, #762a2a 0%, #5b1f1f 100%);
                height: 100vh;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-container {
                width: 100%;
                max-width: 400px;
                padding: 20px;
            }
            .login-card {
                background: white;
                padding: 40px; 
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                position: relative;
                overflow: hidden;
                border-top: 5px solid var(--primary-gold);
            }
            .logo-section {
                text-align: center;
                margin-bottom: 30px;
            }
            .logo-section img {
                width: 80px; 
                height: auto;
                margin-bottom: 15px;
            }
            .logo-section h1 {
                font-size: 1.8em;
                color: var(--primary-dark);
                margin: 0;
                font-weight: 700;
            }
            .logo-section p {
                color: #666;
                margin: 5px 0 0;
                font-size: 0.9em;
            }
            .input-group {
                margin-bottom: 20px;
                position: relative;
            }
            .input-group i {
                position: absolute;
                left: 15px;
                top: 50%;
                transform: translateY(-50%);
                color: #aaa;
                transition: color 0.3s;
            }
            .input-group input {
                width: 100%;
                padding: 12px 15px 12px 45px; /* Space for icon */
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 1em;
                transition: all 0.3s;
                outline: none;
                background: #f9f9f9;
            }
            .input-group input:focus {
                border-color: var(--primary-gold);
                background: white;
                box-shadow: 0 0 0 4px rgba(233, 198, 111, 0.1);
            }
            .input-group input:focus + i {
                color: var(--primary-gold);
            }
            .btn-submit {
                width: 100%;
                background: linear-gradient(to right, var(--primary-gold), #d4b05a);
                color: var(--primary-dark);
                font-weight: 700;
                padding: 14px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-size: 1.1em;
                transition: transform 0.2s, box-shadow 0.2s;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .btn-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(233, 198, 111, 0.4);
            }
            .error-msg {
                background: #e74c3c;
                color: white;
                padding: 10px;
                border-radius: 6px;
                margin-bottom: 20px;
                text-align: center;
                font-size: 0.9em;
            }
            .demo-box {
                background: #f8f9fa;
                border: 1px dashed #ccc;
                padding: 15px;
                border-radius: 8px;
                margin-top: 25px;
                font-size: 0.85em;
                color: #555;
                text-align: center;
            }
            .copyright {
                text-align: center;
                color: rgba(255,255,255,0.5);
                font-size: 0.8em;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <img src="assets/logo.png" alt="FW-AEMS Logo">
                <h1>FW-AEMS</h1>
                <p>Faculty Workload & Effectiveness</p>
            </div>

            <?php if($message): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required autocomplete="off">
                    <i class="fas fa-user-circle"></i>
                </div>
                
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class="fas fa-lock"></i>
                </div>

                <button type="submit" class="btn-submit">Sign In</button>
            </form>


        </div>
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> University Academic System
        </div>
    </div>

    </body>
    </html>
