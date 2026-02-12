<?php
include '../includes/db_connect.php';
include '../includes/auth.php'; // Need auth to get session user_id
include 'send_mail.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approval</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
<?php

// Check if leave_id is passed
if (isset($_GET['leave_id'])) {
    $leave_id = $_GET['leave_id'];
    $admin_id = $_SESSION['user_id']; // Get Admin ID

    // 1. Fetch Info
    $query = "SELECT e.email, e.full_name, l.leave_type, l.start_date, l.end_date
              FROM leaves l
              JOIN employees e ON l.employee_id = e.employee_id
              WHERE l.leave_id = ?";

    $stmt_info = $conn->prepare($query);
    $stmt_info->bind_param("i", $leave_id);
    $stmt_info->execute();
    $result_info = $stmt_info->get_result();
    $leave_data = $result_info->fetch_assoc();
    $stmt_info->close();

    // 2. Fetch Admin Name
    $admin_query = "SELECT full_name FROM employees WHERE employee_id = ?";
    $stmt_admin = $conn->prepare($admin_query);
    $stmt_admin->bind_param("i", $admin_id);
    $stmt_admin->execute();
    $res_admin = $stmt_admin->get_result();
    $admin_name = ($res_admin->num_rows > 0) ? $res_admin->fetch_assoc()['full_name'] : 'Admin';
    $stmt_admin->close();

    // 3. Update Status AND Action By
    $sql = "UPDATE leaves SET status = 'Approved', action_by = ? WHERE leave_id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $admin_id, $leave_id);

        if ($stmt->execute()) {

            // 4. Send Email
            if ($leave_data && !empty($leave_data['email'])) {
                $to = $leave_data['email'];
                $subject = "Leave Approved: " . $leave_data['leave_type'];
                $body = "
                    <h3>Leave Request Approved</h3>
                    <p>Dear " . htmlspecialchars($leave_data['full_name']) . ",</p>
                    <p>Your request for <strong>" . htmlspecialchars($leave_data['leave_type']) . "</strong> has been <span style='color:green; font-weight:bold;'>APPROVED</span>.</p>
                    <p><strong>Dates:</strong> " . $leave_data['start_date'] . " to " . $leave_data['end_date'] . "</p>
                    <p><strong>Approved By:</strong> " . htmlspecialchars($admin_name) . "</p>
                    <br>
                    <p>Regards,<br>HR Team</p>
                ";
                sendMail($to, $subject, $body);
            }

            echo "<script>
                Swal.fire({
                    title: 'Success!',
                    text: 'Leave request approved successfully.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='/hr-portal/pages/view_leave.php';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        $stmt->close();
    }
} else {
    echo "<script>window.history.back();</script>";
}

$conn->close();
?>
</body>
</html>