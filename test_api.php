<?php
include 'includes/exam_api_config.php';

echo "<h1>API Debug Test</h1>";

// 1. Inspect the Key
echo "<h2>1. Key Inspection</h2>";
echo "Key: [" . $exam_api_key . "]<br>";
echo "Length: " . strlen($exam_api_key) . "<br>";
echo "Hex Dump: " . bin2hex($exam_api_key) . "<br>";

// 2. Make the Request
echo "<h2>2. API Request</h2>";
$email = 'bishal.g@travarsa.net'; // Use a known valid email
$url = $exam_api_url . '?email=' . urlencode($email);

echo "URL: " . $url . "<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output

// Capture verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$headers = [
    'X-API-KEY: ' . trim($exam_api_key),
    'Content-Type: application/json',
    'Accept: application/json'
];

echo "Headers being sent:<br>";
echo "<pre>" . print_r($headers, true) . "</pre>";

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Disable SSL verification for testing
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

echo "HTTP Code: " . $http_code . "<br>";
echo "cURL Error: " . $curl_error . "<br>";
echo "Response:<br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// 3. Verbose Log
echo "<h2>3. Verbose Log</h2>";
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";

curl_close($ch);
?>
