<?php
// Include the database connection file
include '../includes/db_connect.php';

// Get filter parameters (same as in employees.php)
$search = isset($_GET['search']) ? $_GET['search'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

// Build the SQL query with filters
$sql = "SELECT * FROM employees WHERE 1=1";

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR mobile_number LIKE '%$search%')";
}

if (!empty($department)) {
    $department = $conn->real_escape_string($department);
    $sql .= " AND department = '$department'";
}

if (!empty($status)) {
    $status = $conn->real_escape_string($status);
    $sql .= " AND status = '$status'";
}

if (!empty($role)) {
    $role = $conn->real_escape_string($role);
    $sql .= " AND role = '$role'";
}

$sql .= " ORDER BY full_name ASC";

// Execute the query
$result = $conn->query($sql);

// Prepare data for export
$data = [];
$data[] = ['Employee ID', 'Full Name', 'Email', 'Mobile Number', 'Role', 'Department', 'Status', 'Hire Date', 'Address'];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        $row['employee_id'],
        $row['full_name'],
        $row['email'],
        $row['mobile_number'],
        $row['role'],
        $row['department'],
        $row['status'],
        $row['hire_date'] ?? 'N/A',
        $row['address'] ?? 'N/A'
    ];
}

// Generate filename
$filename = 'employees_export_' . date('Y-m-d');
if (!empty($department)) {
    $filename .= '_' . str_replace(' ', '_', $department);
}
if (!empty($status)) {
    $filename .= '_' . $status;
}
if (!empty($role)) {
    $filename .= '_' . str_replace(' ', '_', $role);
}

// Excel Export
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Create a file pointer
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, $data[0], "\t");

// Output the data rows
for ($i = 1; $i < count($data); $i++) {
    fputcsv($output, $data[$i], "\t");
}

fclose($output);

// Close the connection
$conn->close();
exit;
?>