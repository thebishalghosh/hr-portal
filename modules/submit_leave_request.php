<?php
include '../includes/db_connect.php';
include '../includes/auth.php';

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
// Include the database connection file


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
}

// Close the connection
$conn->close();
?>

</body>
</html>