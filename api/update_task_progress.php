<?php
// Add these at the top to catch any PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to the client, but log them

// Ensure we're always returning JSON
header('Content-Type: application/json');

try {
    include '../includes/db_connect.php';
    
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    // Get JSON data
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No input data provided');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($data['task_id']) || !isset($data['status']) || !isset($data['description'])) {
        throw new Exception('Missing required fields');
    }
    
    $task_id = intval($data['task_id']);
    $status = $data['status'];
    $description = $data['description'];
    $links = isset($data['links']) ? $data['links'] : [];
    $update_date = date('Y-m-d');
    
    // Verify the task belongs to the user (check task_assignments table)
    $verify_query = "
        SELECT t.task_id 
        FROM tasks t
        LEFT JOIN task_assignments ta ON t.task_id = ta.task_id
        WHERE t.task_id = ? AND (ta.employee_id = ? OR t.assigned_by = ?)";
    
    $stmt = $conn->prepare($verify_query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("iii", $task_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('You are not authorized to update this task');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Insert progress update
    $insert_progress = "
        INSERT INTO task_progress (task_id, employee_id, update_date, status, description)
        VALUES (?, ?, ?, ?, ?)
    ";
    
    $stmt = $conn->prepare($insert_progress);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("iisss", $task_id, $user_id, $update_date, $status, $description);
    if (!$stmt->execute()) {
        throw new Exception('Error inserting progress: ' . $stmt->error);
    }
    
    $progress_id = $conn->insert_id;
    
    // Update task status
    $update_task = "
        UPDATE tasks 
        SET status = ?, updated_at = NOW()
        WHERE task_id = ?
    ";
    
    $stmt = $conn->prepare($update_task);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $status, $task_id);
    if (!$stmt->execute()) {
        throw new Exception('Error updating task: ' . $stmt->error);
    }
    
    // Handle links if provided
    if (!empty($links)) {
        $insert_link = "
            INSERT INTO task_links (progress_id, link_url, link_description)
            VALUES (?, ?, ?)
        ";
        
        $stmt = $conn->prepare($insert_link);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        foreach ($links as $link) {
            if (!empty($link['url'])) {
                $link_url = $link['url'];
                $link_description = isset($link['description']) ? $link['description'] : '';
                
                $stmt->bind_param("iss", $progress_id, $link_url, $link_description);
                if (!$stmt->execute()) {
                    throw new Exception('Error inserting link: ' . $stmt->error);
                }
            }
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Task progress updated successfully',
        'progress_id' => $progress_id
    ]);
    
} catch (Exception $e) {
    // If we have an active transaction, roll it back
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    // Log the error
    error_log("API Error: " . $e->getMessage());
    
    // Always set content type
    header('Content-Type: application/json');
    http_response_code(500);
    
    // Ensure we output valid JSON
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
    exit;
}
?>