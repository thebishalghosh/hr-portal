<?php
include '../includes/db_connect.php';
include '../includes/auth.php';
include 'send_mail.php'; // Include the mailer

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Leave</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
<?php

// Get the logged-in user's employee ID
$employee_id = $_SESSION['user_id'];

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];

    // Validate the form data
    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'All fields are required!',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then(() => {
                window.history.back();
            });
        </script>";
        exit();
    }

    // Prepare the SQL query to insert the leave request
    $sql = "INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')";

    // Prepare the statement
    if ($stmt = $conn->prepare($sql)) {
        // Bind the parameters
        $stmt->bind_param("issss", $employee_id, $leave_type, $start_date, $end_date, $reason);

        // Execute the statement
        if ($stmt->execute()) {

            // --- EMAIL NOTIFICATION LOGIC START ---

            // 1. Get Employee Name
            $emp_query = "SELECT full_name FROM employees WHERE employee_id = ?";
            $emp_stmt = $conn->prepare($emp_query);
            $emp_stmt->bind_param("i", $employee_id);
            $emp_stmt->execute();
            $emp_result = $emp_stmt->get_result();
            $employee_name = ($emp_result->num_rows > 0) ? $emp_result->fetch_assoc()['full_name'] : 'Employee';
            $emp_stmt->close();

            // 2. Get All Admin Emails
            $admin_query = "SELECT email FROM employees WHERE role = 'admin' AND status = 'Active'";
            $admin_result = $conn->query($admin_query);

            $admin_emails = [];
            if ($admin_result->num_rows > 0) {
                while ($row = $admin_result->fetch_assoc()) {
                    if (!empty($row['email'])) {
                        $admin_emails[] = $row['email'];
                    }
                }
            }

            // 3. Send Email if admins exist
            if (!empty($admin_emails)) {
                $to = $admin_emails[0]; // First admin is main recipient
                $cc_list = array_slice($admin_emails, 1); // Rest are CC

                $subject = "New Leave Request: $employee_name";

                $content = "
                    <p>A new leave request has been submitted and requires your attention.</p>
                    <table class='info-table'>
                        <tr><td>Employee:</td><td>$employee_name</td></tr>
                        <tr><td>Leave Type:</td><td>$leave_type</td></tr>
                        <tr><td>Start Date:</td><td>$start_date</td></tr>
                        <tr><td>End Date:</td><td>$end_date</td></tr>
                        <tr><td>Reason:</td><td>" . nl2br(htmlspecialchars($reason)) . "</td></tr>
                    </table>
                    <div style='text-align: center;'>
                        <a href='http://localhost/hr-portal/pages/view_leave.php' class='btn'>Review Request</a>
                    </div>
                ";

                $body = get_email_template("New Leave Request", $content, "#2563eb"); // Blue

                // Call the helper function
                sendMail($to, $subject, $body, $cc_list);
            }
            // --- EMAIL NOTIFICATION LOGIC END ---

            echo "<script>
                Swal.fire({
                    title: 'Success!',
                    text: 'Leave request submitted successfully.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='/hr-portal/pages/request_leave.php';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while submitting the leave request.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }

        // Close the statement
        $stmt->close();
    } else {
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'Error preparing the statement.',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }
}

// Close the connection
$conn->close();
?>

</body>
</html>