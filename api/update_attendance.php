<?php
header('Content-Type: application/json');
include '../includes/auth.php';
include '../includes/db_connect.php';

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit();
}

$employee_id = $input['employee_id'] ?? null;
$date = $input['date'] ?? null;
$status = $input['status'] ?? null;
$remarks = $input['remarks'] ?? '';
$check_in = !empty($input['check_in_time']) ? $input['check_in_time'] : null;
$check_out = !empty($input['check_out_time']) ? $input['check_out_time'] : null;

if (!$employee_id || !$date || !$status) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

try {
    // Check if record exists
    $check_sql = "SELECT attendance_id FROM attendance WHERE employee_id = ? AND date = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update
        $sql = "UPDATE attendance SET status = ?, remarks = ?, check_in_time = ?, check_out_time = ? WHERE employee_id = ? AND date = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssis", $status, $remarks, $check_in, $check_out, $employee_id, $date);
    } else {
        // Insert
        $sql = "INSERT INTO attendance (employee_id, date, status, remarks, check_in_time, check_out_time) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $employee_id, $date, $status, $remarks, $check_in, $check_out);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Attendance updated']);
    } else {
        throw new Exception($conn->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>
