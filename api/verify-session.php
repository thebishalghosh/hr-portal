<?php
header('Content-Type: application/json');

// Define the secret API key (Using the same key as candidates.php for consistency)
define('API_KEY', 'kkasdhbe53jh');

// Get the API key from the request headers
$api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';

// Validate the API key
if ($api_key !== API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Invalid API Key']);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Get the session token from the POST body
$input = json_decode(file_get_contents('php://input'), true);
$session_token = isset($input['session_token']) ? $input['session_token'] : (isset($_POST['session_token']) ? $_POST['session_token'] : '');

if (empty($session_token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request: Session token is required']);
    exit;
}

// Include the database connection
require_once '../includes/db_connect.php';

try {
    // Prepare query to verify session and get user email and role in one go
    $query = "
        SELECT e.email, e.role
        FROM user_sessions s
        JOIN employees e ON s.employee_id = e.employee_id
        WHERE s.session_token = ?
        AND s.status = 'active'
        AND s.expires_at > NOW()
    ";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $session_token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Map internal role to external role
        // 'user' -> 'candidate', 'admin' -> 'admin'
        $role = strtolower($user['role']) === 'admin' ? 'admin' : 'candidate';

        // Session is valid, return success, email, and role
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'user_email' => $user['email'],
            'role' => $role
        ]);
    } else {
        // Session not found, expired, or inactive
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid or expired session']);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
    error_log("SSO Verification Error: " . $e->getMessage());
}

$conn->close();
?>
