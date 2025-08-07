<?php
include '../includes/auth.php';

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /hr-portal/dashboard.php');
    exit();
}

// Include the database connection file
include '../includes/db_connect.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get all posted values
    $employee_ids = $_POST['employee_id'] ?? [];
    $attendance_dates = $_POST['attendance_date'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    $check_in_times = $_POST['check_in_time'] ?? [];
    $check_out_times = $_POST['check_out_time'] ?? [];

    // Validate input data
    if (empty($employee_ids) || count($employee_ids) !== count($attendance_dates) || 
        count($employee_ids) !== count($statuses)) {
        header("Location: /hr-portal/pages/attendance.php?status=failure&message=Invalid form data submitted");
        exit();
    }

    // Initialize a flag for successful attendance update
    $attendance_updated = true;
    $conn->begin_transaction();

    try {
        // Loop through each employee and check if attendance exists or needs updating
        for ($i = 0; $i < count($employee_ids); $i++) {
            $employee_id = $conn->real_escape_string($employee_ids[$i]);
            $attendance_date = $conn->real_escape_string($attendance_dates[$i]);
            $status = $conn->real_escape_string($statuses[$i]);
            $remark = $conn->real_escape_string($remarks[$i] ?? '');
            $check_in = !empty($check_in_times[$i]) ? $conn->real_escape_string($check_in_times[$i]) : null;
            $check_out = !empty($check_out_times[$i]) ? $conn->real_escape_string($check_out_times[$i]) : null;

            // Check if attendance already exists for this employee on the given date
            $check_sql = "SELECT attendance_id FROM attendance WHERE employee_id = ? AND date = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $employee_id, $attendance_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_stmt->close();

            if ($check_result->num_rows > 0) {
                // Attendance exists, update the record
                $update_sql = "UPDATE attendance SET status = ?, remarks = ?, 
                              check_in_time = ?, check_out_time = ? 
                              WHERE employee_id = ? AND date = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssssss", $status, $remark, $check_in, $check_out, $employee_id, $attendance_date);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Error updating attendance: " . $conn->error);
                }
                $update_stmt->close();
            } else {
                // Attendance doesn't exist, insert a new record
                $insert_sql = "INSERT INTO attendance (employee_id, date, status, remarks, check_in_time, check_out_time) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("isssss", $employee_id, $attendance_date, $status, $remark, $check_in, $check_out);
                
                if (!$insert_stmt->execute()) {
                    throw new Exception("Error inserting attendance: " . $conn->error);
                }
                $insert_stmt->close();
            }
        }

        // Commit the transaction
        $conn->commit();
        
        // Redirect with success message
        $redirect_date = !empty($attendance_dates) ? $attendance_dates[0] : date('Y-m-d');
        header("Location: /hr-portal/pages/attendance.php?status=success&date={$redirect_date}&message=Attendance records updated successfully");
        
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();
        
        // Redirect with error message
        $redirect_date = !empty($attendance_dates) ? $attendance_dates[0] : date('Y-m-d');
        $error_message = urlencode($e->getMessage());
        header("Location: /hr-portal/pages/attendance.php?status=failure&date={$redirect_date}&message={$error_message}");
    }
    
    exit();
}

// If not POST request, redirect to attendance page
header("Location: /hr-portal/pages/attendance.php");
exit();
?>