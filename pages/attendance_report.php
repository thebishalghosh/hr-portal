<?php
include '../includes/auth.php';

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /hr-portal/dashboard.php');
    exit();
}

// Include the database connection file
include '../includes/db_connect.php';

// Include the sidebar
include '../includes/sidebar.php';

// Default values for filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
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

// Fetch all departments for the filter dropdown
$dept_sql = "SELECT DISTINCT department FROM employees WHERE status = 'Active' ORDER BY department";
$dept_result = $conn->query($dept_sql);
$departments = [];
while ($dept_row = $dept_result->fetch_assoc()) {
    $departments[] = $dept_row['department'];
}

// Fetch all employees for the filter dropdown
$emp_sql = "SELECT employee_id, full_name, department FROM employees WHERE status = 'Active' ORDER BY department, full_name";
$emp_result = $conn->query($emp_sql);
$employees = [];
while ($emp_row = $emp_result->fetch_assoc()) {
    $employees[] = $emp_row;
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

// Calculate statistics
$total_days = 0;
$present_count = 0;
$absent_count = 0;
$leave_count = 0;
$late_count = 0; 
$attendance_by_employee = [];


// Process the results for statistics
if ($result->num_rows > 0) {
    $temp_result = $result;
    while ($row = $temp_result->fetch_assoc()) {
        $total_days++;
        
        // Check for late in remarks
        if (!empty($row['remarks']) && stripos($row['remarks'], 'late') !== false) {
            $late_count++;
        }
        
        if ($row['status'] == 'Present') {
            $present_count++;
        } elseif ($row['status'] == 'Absent') {
            $absent_count++;
        } elseif ($row['status'] == 'Leave') {
            $leave_count++;
        }
        
        // Track attendance by employee
        $emp_key = $row['employee_id'];
        if (!isset($attendance_by_employee[$emp_key])) {
            $attendance_by_employee[$emp_key] = [
                'name' => $row['full_name'],
                'department' => $row['department'],
                'present' => 0,
                'absent' => 0,
                'leave' => 0,
                'late' => 0, // Add this line
                'total' => 0
            ];
        }
        
        $attendance_by_employee[$emp_key]['total']++;
        
        // Check for late in remarks for employee stats
        if (!empty($row['remarks']) && stripos($row['remarks'], 'late') !== false) {
            $attendance_by_employee[$emp_key]['late']++;
        }
        
        if ($row['status'] == 'Present') {
            $attendance_by_employee[$emp_key]['present']++;
        } elseif ($row['status'] == 'Absent') {
            $attendance_by_employee[$emp_key]['absent']++;
        } elseif ($row['status'] == 'Leave') {
            $attendance_by_employee[$emp_key]['leave']++;
        }
    }
    
    // Reset the result pointer
    $result->data_seek(0);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .page-title {
            font-family: 'Arial', sans-serif;
            color: #333;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .filter-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .report-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .stats-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .status-present {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-absent {
            color: #dc3545;
            font-weight: 600;
        }
        
        .status-leave {
            color: #ffc107;
            font-weight: 600;
        }
        
        .stats-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .employee-stats-table th,
        .employee-stats-table td {
            vertical-align: middle;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
        }
                .status-leave {
            color: #ffc107;
            font-weight: 600;
        }
        
        .status-late {
            color: #17a2b8;
            font-weight: 600;
        }

    </style>
</head>

<body>
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="page-title">
                <i class="fas fa-chart-bar me-2"></i>Attendance Report
            </h4>
        </div>
        
        <!-- Filters Section -->
        <div class="card filter-card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter Options
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm" class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                        <?php echo ($department == $dept) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="employee_id" class="form-label">Employee</label>
                        <select class="form-select" id="employee_id" name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>" 
                                        <?php echo ($employee_id == $emp['employee_id']) ? 'selected' : ''; ?>
                                        data-department="<?php echo htmlspecialchars($emp['department']); ?>">
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="Present" <?php echo ($status == 'Present') ? 'selected' : ''; ?>>Present</option>
                            <option value="Absent" <?php echo ($status == 'Absent') ? 'selected' : ''; ?>>Absent</option>
                            <option value="Leave" <?php echo ($status == 'Leave') ? 'selected' : ''; ?>>Leave</option>
                        </select>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Generate Report
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" id="resetFilters">
                            <i class="fas fa-undo me-2"></i>Reset Filters
                        </button>
                        <button type="button" class="btn btn-success ms-2" id="exportExcel">
                            <i class="fas fa-file-excel me-2"></i>Export to Excel
                        </button>
                        <!-- <button type="button" class="btn btn-danger ms-2" id="exportPDF">
                            <i class="fas fa-file-pdf me-2"></i>Export to PDF
                        </button> -->
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistics Section -->
        <div class="row mb-4" style="justify-content:space-evenly;">
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-check stats-icon text-primary"></i>
                        <h5 class="card-title">Total Records</h5>
                        <h3 class="mb-0"><?php echo $total_days; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check stats-icon text-success"></i>
                        <h5 class="card-title">Present</h5>
                        <h3 class="mb-0 text-success"><?php echo $present_count; ?></h3>
                        <p class="mb-0"><?php echo ($total_days > 0) ? round(($present_count / $total_days) * 100, 1) . '%' : '0%'; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <i class="fas fa-user-times stats-icon text-danger"></i>
                        <h5 class="card-title">Absent</h5>
                        <h3 class="mb-0 text-danger"><?php echo $absent_count; ?></h3>
                        <p class="mb-0"><?php echo ($total_days > 0) ? round(($absent_count / $total_days) * 100, 1) . '%' : '0%'; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <i class="fas fa-user-clock stats-icon text-warning"></i>
                        <h5 class="card-title">Leave</h5>
                        <h3 class="mb-0 text-warning"><?php echo $leave_count; ?></h3>
                        <p class="mb-0"><?php echo ($total_days > 0) ? round(($leave_count / $total_days) * 100, 1) . '%' : '0%'; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <i class="fas fa-clock stats-icon text-info"></i>
                        <h5 class="card-title">Late Arrivals</h5>
                        <h3 class="mb-0 text-info"><?php echo $late_count; ?></h3>
                        <p class="mb-0"><?php echo ($total_days > 0) ? round(($late_count / $total_days) * 100, 1) . '%' : '0%'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Statistics Table -->
        <?php if (!empty($attendance_by_employee)): ?>
        <div class="card report-card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>Employee Attendance Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover employee-stats-table" id="employeeStatsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Leave</th>
                                <th>Late</th>
                                <th>Attendance Rate</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($attendance_by_employee as $emp_id => $stats): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stats['name']); ?></td>
                                    <td><?php echo htmlspecialchars($stats['department']); ?></td>
                                    <td class="text-success"><?php echo $stats['present']; ?></td>
                                    <td class="text-danger"><?php echo $stats['absent']; ?></td>
                                    <td class="text-warning"><?php echo $stats['leave']; ?></td>
                                    <td class="text-info"><?php echo $stats['late']; ?></td>
                                    <td>
                                        <?php 
                                            $attendance_rate = ($stats['total'] > 0) ? ($stats['present'] / $stats['total']) * 100 : 0;
                                            $progress_class = 'bg-danger';
                                            if ($attendance_rate >= 90) {
                                                $progress_class = 'bg-success';
                                            } elseif ($attendance_rate >= 75) {
                                                $progress_class = 'bg-info';
                                            } elseif ($attendance_rate >= 50) {
                                                $progress_class = 'bg-warning';
                                            }
                                        ?>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2">
                                                <div class="progress-bar <?php echo $progress_class; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo round($attendance_rate, 1); ?>%" 
                                                     aria-valuenow="<?php echo round($attendance_rate, 1); ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                            <span><?php echo round($attendance_rate, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Detailed Attendance Records -->
        <div class="card report-card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-list-alt me-2"></i>Detailed Attendance Records
                </h5>
                <p class="text-muted mb-0 mt-2">
                    <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?>
                    <?php if (!empty($department)): ?>
                        | Department: <?php echo htmlspecialchars($department); ?>
                    <?php endif; ?>
                    <?php if (!empty($employee_id)): 
                        $emp_name = '';
                        foreach ($employees as $emp) {
                            if ($emp['employee_id'] == $employee_id) {
                                $emp_name = $emp['full_name'];
                                break;
                            }
                        }
                    ?>
                        | Employee: <?php echo htmlspecialchars($emp_name); ?>
                    <?php endif; ?>
                    <?php if (!empty($status)): ?>
                        | Status: <?php echo htmlspecialchars($status); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="attendanceTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th>Hours</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y (D)', strtotime($row['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td>
                                            <?php if ($row['status'] == 'Present'): ?>
                                                <span class="status-present"><i class="fas fa-check-circle me-1"></i>Present</span>
                                            <?php elseif ($row['status'] == 'Absent'): ?>
                                                <span class="status-absent"><i class="fas fa-times-circle me-1"></i>Absent</span>
                                            <?php elseif ($row['status'] == 'Leave'): ?>
                                                <span class="status-leave"><i class="fas fa-user-clock me-1"></i>Leave</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($row['check_in_time']) ? date('h:i A', strtotime($row['check_in_time'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($row['check_out_time']) ? date('h:i A', strtotime($row['check_out_time'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if (!empty($row['check_in_time']) && !empty($row['check_out_time'])) {
                                                    $check_in = new DateTime($row['check_in_time']);
                                                    $check_out = new DateTime($row['check_out_time']);
                                                    $interval = $check_in->diff($check_out);
                                                    $hours = $interval->h + ($interval->i / 60);
                                                    echo number_format($hours, 2);
                                                } else {
                                                    echo '-';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo !empty($row['remarks']) ? htmlspecialchars($row['remarks']) : '-'; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No attendance records found for the selected criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#attendanceTable').DataTable({
                "pageLength": 25,
                "order": [[0, "desc"]],
                "responsive": true
            });
            
            $('#employeeStatsTable').DataTable({
                "pageLength": 25,
                "order": [[5, "desc"]],
                "responsive": true
            });
            
            // Reset filters button
            $('#resetFilters').click(function() {
                window.location.href = 'attendance_report.php';
            });
            
            // Filter department and employee dropdown linkage
            $('#department').change(function() {
                var selectedDept = $(this).val();
                
                // Reset employee dropdown
                $('#employee_id').val('');
                
                // If a department is selected, filter employees
                if (selectedDept) {
                    $('#employee_id option').each(function() {
                        if ($(this).val() === '' || $(this).data('department') === selectedDept) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                } else {
                    // Show all employees if no department is selected
                    $('#employee_id option').show();
                }
            });
            
            // Trigger change to apply initial filtering
            $('#department').trigger('change');
            
            // Date range validation
            $('#filterForm').submit(function(e) {
                var startDate = new Date($('#start_date').val());
                var endDate = new Date($('#end_date').val());
                
                if (startDate > endDate) {
                    alert('Start date cannot be after end date');
                    e.preventDefault();
                }
            });
            
            // Export to Excel functionality
            $('#exportExcel').click(function() {
                // Create a form to submit the current filters
                var form = $('#filterForm').clone();
                form.attr('action', 'export_attendance.php');
                form.append('<input type="hidden" name="export_type" value="excel">');
                form.appendTo('body').submit().remove();
            });
            
            // Export to PDF functionality
            // $('#exportPDF').click(function() {
            //     // Create a form to submit the current filters
            //     var form = $('#filterForm').clone();
            //     form.attr('action', 'export_attendance.php');
            //     form.append('<input type="hidden" name="export_type" value="pdf">');
            //     form.appendTo('body').submit().remove();
            // });
        });
    </script>
</body>
</html>

<?php
// Close the connection
$conn->close();
?>