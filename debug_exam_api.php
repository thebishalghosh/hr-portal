<?php
// debug_exam_api.php
// Run this file in the browser to debug Exam API issues.

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Exam API Debugger</h1>";

// 1. Check Auth & Session
echo "<h2>1. Authentication & Session</h2>";
if (!isset($_SESSION['user_id'])) {
    die("<p style='color:red'>Error: User is not logged in.</p>");
}

echo "<p><strong>User ID:</strong> " . htmlspecialchars($_SESSION['user_id']) . "</p>";
echo "<p><strong>Session Token:</strong> " . (isset($_SESSION['session_token']) ? htmlspecialchars($_SESSION['session_token']) : '<span style="color:red">MISSING</span>') . "</p>";

// 2. Database Connection
echo "<h2>2. Database & User Details</h2>";
$db_path = __DIR__ . '/includes/db_connect.php';
if (file_exists($db_path)) {
    include $db_path;
    echo "<p style='color:green'>Database connection file found.</p>";
} else {
    die("<p style='color:red'>Error: db_connect.php not found at $db_path</p>");
}

if (!isset($conn)) {
    die("<p style='color:red'>Error: Database connection variable \$conn is not set.</p>");
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("<p style='color:red'>Error: User not found in 'employees' table.</p>");
}

$candidate_email = isset($user['email']) ? $user['email'] : '';
echo "<p><strong>User Name:</strong> " . htmlspecialchars($user['full_name'] ?? 'N/A') . "</p>";
echo "<p><strong>User Email (DB):</strong> " . htmlspecialchars($candidate_email) . "</p>";
echo "<p><strong>User Role:</strong> " . htmlspecialchars($_SESSION['user_role'] ?? 'N/A') . "</p>";

if (empty($candidate_email)) {
    die("<p style='color:red'>Error: User email is empty. Cannot query API.</p>");
}

// 3. API Configuration
echo "<h2>3. API Configuration</h2>";
$config_path = __DIR__ . '/includes/exam_api_config.php';
if (file_exists($config_path)) {
    include $config_path;
    echo "<p style='color:green'>Config file loaded.</p>";
} else {
    die("<p style='color:red'>Error: exam_api_config.php not found.</p>");
}

global $exam_api_url, $exam_api_key;

echo "<p><strong>API URL:</strong> " . htmlspecialchars($exam_api_url) . "</p>";
echo "<p><strong>API Key:</strong> " . (empty($exam_api_key) ? '<span style="color:red">MISSING</span>' : '********' . substr($exam_api_key, -4)) . "</p>";

// 4. API Request Execution
echo "<h2>4. API Request Execution</h2>";

$request_url = $exam_api_url . '?email=' . urlencode($candidate_email) . '&session_token=' . urlencode($_SESSION['session_token'] ?? '');

echo "<p><strong>Full Request URL:</strong> <a href='" . htmlspecialchars($request_url) . "' target='_blank'>" . htmlspecialchars($request_url) . "</a></p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $request_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$headers = [
    'X-API-KEY: ' . trim($exam_api_key),
    'Content-Type: application/json'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

echo "<p><strong>Sending Request...</strong></p>";

$start_time = microtime(true);
$response = curl_exec($ch);
$end_time = microtime(true);
$duration = round($end_time - $start_time, 4);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<p><strong>Time Taken:</strong> {$duration}s</p>";
echo "<p><strong>HTTP Status Code:</strong> " . ($http_code == 200 ? "<span style='color:green'>200 OK</span>" : "<span style='color:red'>$http_code</span>") . "</p>";

if ($curl_error) {
    echo "<p style='color:red'><strong>cURL Error:</strong> " . htmlspecialchars($curl_error) . "</p>";
}

// 5. Response Analysis
echo "<h2>5. Raw Response</h2>";
echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($response) . "</pre>";

echo "<h2>6. JSON Decode Check</h2>";
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<p style='color:red'><strong>JSON Error:</strong> " . json_last_error_msg() . "</p>";
} else {
    echo "<p style='color:green'>JSON Decoded Successfully.</p>";
    echo "<p><strong>Status Field:</strong> " . htmlspecialchars($data['status'] ?? 'MISSING') . "</p>";

    if (isset($data['assigned_exams'])) {
        $count = count($data['assigned_exams']);
        echo "<p><strong>Assigned Exams Count:</strong> $count</p>";
        if ($count > 0) {
            echo "<pre>" . print_r($data['assigned_exams'], true) . "</pre>";
        } else {
            echo "<p style='color:orange'>The 'assigned_exams' array is empty.</p>";
        }
    } else {
        echo "<p style='color:red'>Key 'assigned_exams' is missing from response.</p>";
    }
}
?>