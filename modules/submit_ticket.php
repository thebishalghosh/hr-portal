<?php
include '../includes/db_connect.php';
include '../includes/auth.php';
include 'send_mail.php'; // Include the mailer

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /hr-portal/pages/login.php");
    exit();
}

$employee_id = $_SESSION['user_id'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Ticket</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $issue_type = $_POST['issue_type'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Basic Validation
    if (empty($issue_type) || empty($priority) || empty($subject) || empty($description)) {
        echo "<script>
            Swal.fire({ title: 'Error!', text: 'All required fields must be filled!', icon: 'error' })
            .then(() => { window.history.back(); });
        </script>";
        exit();
    }

    // Generate User-Friendly Ticket Code (e.g., TKT-1001)
    $result = $conn->query("SELECT MAX(ticket_id) as max_id FROM support_tickets");
    $row = $result->fetch_assoc();
    $next_id = ($row['max_id'] ? $row['max_id'] : 1000) + 1;
    $ticket_code = 'TKT-' . $next_id;

    // Handle Optional File Upload
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/tickets/';

        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_info = pathinfo($_FILES['attachment']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'];

        if (in_array($file_ext, $allowed_exts)) {
            if ($_FILES['attachment']['size'] <= 5 * 1024 * 1024) { // 5MB limit
                // Create unique file name
                $new_filename = $ticket_code . '_' . time() . '.' . $file_ext;
                $target_file = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                    $attachment_path = 'uploads/tickets/' . $new_filename; // Relative path for DB
                }
            } else {
                echo "<script>
                    Swal.fire({ title: 'Error!', text: 'File size exceeds 5MB limit.', icon: 'error' })
                    .then(() => { window.history.back(); });
                </script>";
                exit();
            }
        } else {
            echo "<script>
                Swal.fire({ title: 'Error!', text: 'Invalid file format. Allowed: JPG, PNG, PDF, DOC, TXT.', icon: 'error' })
                .then(() => { window.history.back(); });
            </script>";
            exit();
        }
    }

    // Insert into Database
    $sql = "INSERT INTO support_tickets (ticket_code, employee_id, issue_type, subject, description, priority, attachment)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sisssss", $ticket_code, $employee_id, $issue_type, $subject, $description, $priority, $attachment_path);

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

                $email_subject = "New Support Ticket: $ticket_code ($priority Priority)";

                $content = "
                    <p>A new IT Support Ticket has been raised and requires your attention.</p>
                    <table class='info-table' style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold; width: 30%;'>Ticket ID:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>$ticket_code</strong></td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Employee:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$employee_name</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Category:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$issue_type</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Priority:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$priority</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Subject:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$subject</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold; vertical-align: top;'>Description:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . nl2br(htmlspecialchars($description)) . "</td></tr>
                    </table>
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='http://localhost/hr-portal/pages/manage_tickets.php' style='background-color: #4f46e5; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Review Ticket</a>
                    </div>
                ";

                // Using the exact same template function as leave management
                if (function_exists('get_email_template')) {
                    $body = get_email_template("New Support Ticket", $content, "#4f46e5"); // Indigo color
                } else {
                    $body = $content; // Fallback just in case
                }

                // Call the helper function
                if (function_exists('sendMail')) {
                    sendMail($to, $email_subject, $body, $cc_list);
                }
            }
            // --- EMAIL NOTIFICATION LOGIC END ---

            echo "<script>
                Swal.fire({ title: 'Ticket Submitted!', text: 'Your ticket code is $ticket_code', icon: 'success' })
                .then(() => { window.location.href='/hr-portal/pages/my_tickets.php'; });
            </script>";
        } else {
            echo "<script>
                Swal.fire({ title: 'Error!', text: 'Database error. Could not create ticket.', icon: 'error' })
                .then(() => { window.history.back(); });
            </script>";
        }
        $stmt->close();
    }
}
$conn->close();
?>
</body>
</html>