<?php
header('Content-Type: application/json');

// Define the secret API key
define('API_KEY', 'kkasdhbe53jh'); // Replace with a strong, securely stored key

// Get the API key from the request headers
$api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';

// Validate the API key
if ($api_key !== API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include the database connection
require_once '../includes/db_connect.php';

// Fetch active candidates with selected fields
$stmt = $conn->prepare("
    SELECT
        employee_id,
        full_name,
        email,
        mobile_number,
        dob,
        college
    FROM
        employees
    WHERE
        status = 'Active'
");
$stmt->execute();
$result = $stmt->get_result();
$candidates = $result->fetch_all(MYSQLI_ASSOC);

// Return the data as JSON
echo json_encode($candidates);

$stmt->close();
$conn->close();
?>
