<?php
include '../includes/db_connect.php';
include '../includes/auth.php';
include 'send_mail.php'; // Include the mailer

// Ensure user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: /hr-portal/dashboard.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Ticket</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if (empty($ticket_id) || empty($status)) {
         echo "<script>
            Swal.fire({ title: 'Error!', text: 'Invalid data submitted!', icon: 'error' })
            .then(() => { window.history.back(); });
        </script>";
        exit();
    }

    // Fetch previous assignment to check if it changed
    $prev_assign_query = "SELECT ticket_code, subject, assigned_to FROM support_tickets WHERE ticket_id = ?";
    $prev_stmt = $conn->prepare($prev_assign_query);
    $prev_stmt->bind_param("i", $ticket_id);
    $prev_stmt->execute();
    $ticket_data = $prev_stmt->get_result()->fetch_assoc();
    $prev_stmt->close();

    $ticket_code = $ticket_data['ticket_code'] ?? 'Unknown';
    $ticket_subject = $ticket_data['subject'] ?? '';
    $previously_assigned_to = $ticket_data['assigned_to'] ?? null;

    $sql = "UPDATE support_tickets SET status = ?, assigned_to = ?, admin_notes = ? WHERE ticket_id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sisi", $status, $assigned_to, $admin_notes, $ticket_id);

        if ($stmt->execute()) {

            // --- EMAIL NOTIFICATION FOR ASSIGNMENT ---
            // Send email only if assigned to someone NEW (not previously assigned to this person)
            if ($assigned_to != null && $assigned_to != $previously_assigned_to) {
                // Get the email of the assigned admin
                $assignee_query = "SELECT full_name, email FROM employees WHERE employee_id = ?";
                $assignee_stmt = $conn->prepare($assignee_query);
                $assignee_stmt->bind_param("i", $assigned_to);
                $assignee_stmt->execute();
                $assignee_result = $assignee_stmt->get_result();

                if ($assignee_result->num_rows > 0) {
                    $assignee = $assignee_result->fetch_assoc();
                    $assignee_email = $assignee['email'];
                    $assignee_name = $assignee['full_name'];

                    if (!empty($assignee_email)) {
                        $email_subject = "Ticket Assigned to You: $ticket_code";

                        $content = "
                            <p>Hi $assignee_name,</p>
                            <p>A Support Ticket has been assigned to you for resolution.</p>
                            <table class='info-table' style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                                <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold; width: 30%;'>Ticket ID:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>$ticket_code</strong></td></tr>
                                <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Status:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$status</td></tr>
                                <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold;'>Subject:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$ticket_subject</td></tr>
                                <tr><td style='padding: 8px; border-bottom: 1px solid #ddd; font-weight: bold; vertical-align: top;'>Notes:</td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>" . nl2br(htmlspecialchars($admin_notes)) . "</td></tr>
                            </table>
                            <p style='margin-top: 20px; font-style: italic; color: #555;'>Please log in to the HR Portal to review and manage this ticket.</p>
                        ";

                        if (function_exists('get_email_template')) {
                            $body = get_email_template("Ticket Assignment", $content, "#f59e0b"); // Warning/Amber color
                        } else {
                            $body = $content;
                        }

                        if (function_exists('sendMail')) {
                            sendMail($assignee_email, $email_subject, $body);
                        }
                    }
                }
                $assignee_stmt->close();
            }
            // --- END EMAIL NOTIFICATION ---

             echo "<script>
                Swal.fire({ title: 'Success!', text: 'Ticket updated successfully.', icon: 'success' })
                .then(() => { window.location.href='/hr-portal/pages/manage_tickets.php'; });
            </script>";
        } else {
             echo "<script>
                Swal.fire({ title: 'Error!', text: 'Database error. Could not update ticket.', icon: 'error' })
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