<?php
/**
 * Helper function to fetch assigned exams from the Exam Portal API.
 *
 * @param string $candidate_email The email of the candidate/employee.
 * @param string $session_token The current session token.
 * @return array An array of assigned exams or an empty array on failure.
 */
function getAssignedExams($candidate_email, $session_token) {
    // Initialize empty array
    $assigned_exams = [];

    // Check if config exists before including
    $config_path = __DIR__ . '/exam_api_config.php';

    // We use global variables if they are already set, otherwise try to include config
    global $exam_api_url, $exam_api_key;

    if ((empty($exam_api_url) || empty($exam_api_key)) && file_exists($config_path)) {
        include $config_path;
    }

    // Validate inputs and config
    if (empty($candidate_email) || empty($exam_api_url) || empty($exam_api_key)) {
        return [];
    }

    // Build Request URL
    $request_url = $exam_api_url . '?email=' . urlencode($candidate_email) . '&session_token=' . urlencode($session_token);

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 seconds timeout

    // --- FIX: Disable SSL Verification for Localhost/Dev Environments ---
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $headers = [
        'X-API-KEY: ' . trim($exam_api_key),
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    curl_close($ch);

    // Error Handling
    if ($curl_error) {
        error_log("Exam API cURL Error: " . $curl_error);
        return [];
    }

    if ($http_code !== 200) {
        error_log("Exam API HTTP Error: " . $http_code);
        return [];
    }

    // --- FIX: Clean Response (Remove PHP Warnings) ---
    // The API is returning PHP warnings before the JSON, causing json_decode to fail.
    // We find the first '{' and extract the JSON string from there.
    $jsonStart = strpos($response, '{');
    if ($jsonStart !== false) {
        $response = substr($response, $jsonStart);
    }

    // Decode JSON
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Exam API JSON Decode Error: " . json_last_error_msg());
        return [];
    }

    // Validate Response Structure
    if (isset($data['status']) && $data['status'] === 'success' && !empty($data['assigned_exams'])) {
        return $data['assigned_exams'];
    }

    return [];
}
?>