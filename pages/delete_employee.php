<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start output buffering
ob_start();

// Include necessary files
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /hr-portal/dashboard.php');
    exit();
}

// Get employee ID from URL parameter
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate employee ID
if ($employee_id <= 0) {
    $_SESSION['error'] = 'Invalid employee ID.';
    header('Location: /hr-portal/pages/employees.php');
    exit();
}

// Check if employee exists
$check_sql = "SELECT employee_id FROM employees WHERE employee_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $employee_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Employee not found.';
    header('Location: /hr-portal/pages/employees.php');
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete employee's leave requests
    $delete_leaves = "DELETE FROM leaves WHERE employee_id = ?";
    $stmt_leaves = $conn->prepare($delete_leaves);
    $stmt_leaves->bind_param("i", $employee_id);
    $stmt_leaves->execute();
    $stmt_leaves->close();

    // Delete employee's attendance records
    $delete_attendance = "DELETE FROM attendance WHERE employee_id = ?";
    $stmt_attendance = $conn->prepare($delete_attendance);
    $stmt_attendance->bind_param("i", $employee_id);
    $stmt_attendance->execute();
    $stmt_attendance->close();

    // Delete employee's performance reviews
    $delete_reviews = "DELETE FROM performance_reviews WHERE employee_id = ?";
    $stmt_reviews = $conn->prepare($delete_reviews);
    $stmt_reviews->bind_param("i", $employee_id);
    $stmt_reviews->execute();
    $stmt_reviews->close();

    // Delete employee's task assignments
    $delete_assignments = "DELETE FROM task_assignments WHERE employee_id = ?";
    $stmt_assignments = $conn->prepare($delete_assignments);
    $stmt_assignments->bind_param("i", $employee_id);
    $stmt_assignments->execute();
    $stmt_assignments->close();

    // Delete employee's user sessions
    $delete_sessions = "DELETE FROM user_sessions WHERE employee_id = ?";
    $stmt_sessions = $conn->prepare($delete_sessions);
    $stmt_sessions->bind_param("i", $employee_id);
    $stmt_sessions->execute();
    $stmt_sessions->close();

    // Delete employee's notifications
    $delete_notifications = "DELETE FROM notifications WHERE recipient_id = ?";
    $stmt_notifications = $conn->prepare($delete_notifications);
    $stmt_notifications->bind_param("i", $employee_id);
    $stmt_notifications->execute();
    $stmt_notifications->close();

    // Finally, delete the employee
    $delete_employee = "DELETE FROM employees WHERE employee_id = ?";
    $stmt_employee = $conn->prepare($delete_employee);
    $stmt_employee->bind_param("i", $employee_id);
    $stmt_employee->execute();
    $stmt_employee->close();

    // Commit transaction
    $conn->commit();
    $_SESSION['success'] = 'Employee and all related data deleted successfully.';

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = 'Error deleting employee: ' . $e->getMessage();
}

// Close connection
$conn->close();

// Redirect back to employees list
header('Location: /hr-portal/pages/candidate.php');
exit();
?> 