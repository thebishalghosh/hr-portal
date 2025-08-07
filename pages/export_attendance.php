<?php
// Include the database connection file
include '../includes/db_connect.php';

// Get export parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$department = isset($_GET['department']) ? $_GET['department'] : '';
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Validate date formats
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date)) {
    $start_date = date('Y-m-01');
}
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date)) {
    $end_date = date('Y-m-d');
}

// Build the SQL query with filters
$params = [];
$types = "";

$sql = "SELECT a.attendance_id, a.date, a.status, a.check_in_time, a.check_out_time, 
        a.remarks, e.employee_id, e.full_name, e.department
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.date BETWEEN ? AND ? AND e.status = 'Active'";
$types .= "ss";
$params[] = $start_date;
$params[] = $end_date;

if (!empty($department)) {
    $sql .= " AND e.department = ?";
    $types .= "s";
    $params[] = $department;
}

if (!empty($employee_id)) {
    $sql .= " AND e.employee_id = ?";
    $types .= "i";
    $params[] = $employee_id;
}

if (!empty($status)) {
    $sql .= " AND a.status = ?";
    $types .= "s";
    $params[] = $status;
}

$sql .= " ORDER BY a.date DESC, e.department, e.full_name";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Prepare data for export
$data = [];
$data[] = ['Date', 'Employee', 'Department', 'Status', 'Check-In', 'Check-Out', 'Hours Worked', 'Remarks'];

while ($row = $result->fetch_assoc()) {
    $hours = '-';
    if (!empty($row['check_in_time']) && !empty($row['check_out_time'])) {
        $check_in = new DateTime($row['check_in_time']);
        $check_out = new DateTime($row['check_out_time']);
        $interval = $check_in->diff($check_out);
        $hours = $interval->h + ($interval->i / 60);
        $hours = number_format($hours, 2);
    }
    
    $data[] = [
        date('Y-m-d', strtotime($row['date'])),
        $row['full_name'],
        $row['department'],
        $row['status'],
        !empty($row['check_in_time']) ? date('h:i A', strtotime($row['check_in_time'])) : '-',
        !empty($row['check_out_time']) ? date('h:i A', strtotime($row['check_out_time'])) : '-',
        $hours,
        $row['remarks'] ?: '-'
    ];
}

// Generate filename
$filename = 'attendance_report_' . $start_date . '_to_' . $end_date;
if (!empty($department)) {
    $filename .= '_' . str_replace(' ', '_', $department);
}
if (!empty($status)) {
    $filename .= '_' . $status;
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