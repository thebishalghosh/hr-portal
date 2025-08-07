<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>
<?php
// Include the database connection file
include '../includes/db_connect.php';

// Check if leave_id is passed in the URL
if (isset($_GET['leave_id'])) {
    $leave_id = $_GET['leave_id'];

    // Update the leave status to 'Rejected'
    $sql = "UPDATE leaves SET status = 'Rejected' WHERE leave_id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Bind the parameter
        $stmt->bind_param("i", $leave_id);

        // Execute the statement
        if ($stmt->execute()) {
            echo "<script>
                Swal.fire({
                    title: 'Rejected!',
                    text: 'Leave request rejected successfully.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='/hr-portal/pages/view_leave.php';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while rejecting the leave request.',
                    icon: 'error',
                    confirmButtonText: 'OK'
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
            });
        </script>";
    }
} else {
    echo "<script>
        Swal.fire({
            title: 'Error!',
            text: 'No leave ID provided.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
    </script>";
}

// Close the connection
$conn->close();
?>

</body>
</html>