<?php
// test_connection.php
header('Content-Type: text/plain');

$targetHost = 'erp.gmit.info';
$targetPort = 443;
$targetUrl = "https://erp.gmit.info/v3/fms/get_staff_by_dept.php";

echo "--- Diagnostic Tool for ERP Connection ---\n";
echo "Server IP: " . $_SERVER['SERVER_ADDR'] . "\n";
echo "Target Host: $targetHost\n";

// 1. DNS Resolution
echo "\n[1] Testing DNS Resolution...\n";
$ip = gethostbyname($targetHost);
echo "Resolved IP: $ip\n";
if ($ip == $targetHost) {
    echo "ERROR: DNS Resolution Failed!\n";
} else {
    echo "DNS Resolution Success.\n";
}

// 2. Socket Connection Test
echo "\n[2] Testing TCP Connection to Port $targetPort...\n";
$fp = @fsockopen($targetHost, $targetPort, $errno, $errstr, 5);
if (!$fp) {
    echo "ERROR: $errstr ($errno)\n";
} else {
    echo "SUCCESS: Connected to port $targetPort\n";
    fclose($fp);
}

// 3. cURL Test (Verbose)
echo "\n[3] Testing cURL Connection (Verbose)...\n";
$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10s connection timeout
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15s total timeout

// Capture verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$error = curl_error($ch);
$info = curl_getinfo($ch);

if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "cURL Success! HTTP Code: " . $info['http_code'] . "\n";
}

echo "\n--- Verbose Log ---\n";
rewind($verbose);
echo stream_get_contents($verbose);


// 4. Localhost Test (Alternative)
echo "\n[4] Testing Localhost Alternative...\n";
// If the app is on the same server, maybe we can use localhost or 127.0.0.1
// This mimics the API call but targeting localhost
$localUrl = "http://127.0.0.1/v3/fms/get_staff_by_dept.php"; // Assuming http on localhost
echo "Trying: $localUrl\n";

$ch2 = curl_init($localUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 2);
$res2 = curl_exec($ch2);
if(curl_errno($ch2)) {
    echo "Localhost Error: " . curl_error($ch2) . "\n";
} else {
    echo "Localhost HTTP Code: " . curl_getinfo($ch2, CURLINFO_HTTP_CODE) . "\n";
}


echo "\n--- End of Diagnostic ---\n";
?>
