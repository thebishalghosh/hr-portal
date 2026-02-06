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

// Get date from URL parameter or use today's date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Get filter parameters
$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$selected_shift = isset($_GET['shift']) ? $_GET['shift'] : 'all';
$selected_college = isset($_GET['college']) ? $_GET['college'] : 'all';

// Get college options from database ENUM
$college_options = [];
$enum_query = "SHOW COLUMNS FROM employees LIKE 'college'";
$enum_result = $conn->query($enum_query);
if ($enum_result && $enum_row = $enum_result->fetch_assoc()) {
    $enum_str = $enum_row['Type'];
    preg_match_all("/'([^']+)'/", $enum_str, $matches);
    $college_options = $matches[1];
}



// Modify the SQL query to include branch and shift filters
$sql = "SELECT e.employee_id, e.full_name, e.department, e.branch, e.shift,
        a.check_in_time, a.check_out_time, a.status, a.remarks
        FROM employees AS e
        LEFT JOIN attendance AS a ON e.employee_id = a.employee_id AND a.date = ?
        WHERE e.status = 'Active'";

// Add branch filter
if ($selected_branch !== 'all') {
    $sql .= " AND e.branch = ?";
}

// Add shift filter
if ($selected_shift !== 'all') {
    $sql .= " AND e.shift = ?";
}

// Add college filter
if ($selected_college !== 'all') {
    $sql .= " AND e.college = ?";
}

$sql .= " ORDER BY e.department, e.full_name";

// Prepare and execute the statement with dynamic parameters
$stmt = $conn->prepare($sql);

// Build parameter array
$params = [$selected_date];
$types = "s";

if ($selected_branch !== 'all') {
    $params[] = $selected_branch;
    $types .= "s";
}
if ($selected_shift !== 'all') {
    $params[] = $selected_shift;
    $types .= "s";
}
if ($selected_college !== 'all') {
    $params[] = $selected_college;
    $types .= "s";
}

$stmt->bind_param($types, ...$params);


$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Check for success or failure status in URL
$status = isset($_GET['status']) ? $_GET['status'] : '';
$message = isset($_GET['message']) ? $_GET['message'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
            --border-radius-sm: 8px;
        }

        /** {*/
        /*    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;*/
        /*}*/

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow-lg);
            color: white;
        }

        .page-title {
            font-weight: 700;
            font-size: 2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            opacity: 0.9;
            margin-top: 0.5rem;
            font-weight: 400;
        }

        .controls-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
        }

        .filter-controls {
            display: flex;
            gap: 1.5rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: var(--card-shadow);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--card-shadow-lg);
        }

        .attendance-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid #e2e8f0;
            padding: 1.5rem;
        }

        .card-header h5 {
            font-weight: 700;
            color: var(--secondary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: var(--border-radius-sm);
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            transform: translateY(-1px);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: none;
            padding: 1rem;
            font-weight: 700;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .table tbody td {
            padding: 1rem;
            border-color: #f1f5f9;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .department-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            font-size: 0.875rem;
        }

        .status-select {
            border-radius: var(--border-radius-sm);
            border: 2px solid #e2e8f0;
            font-weight: 600;
        }

        .status-select option[value="Present"] {
            color: var(--success-color);
        }

        .status-select option[value="Absent"] {
            color: var(--danger-color);
        }

        .status-select option[value="Leave"] {
            color: var(--warning-color);
        }

        .form-control-sm, .form-select-sm {
            border-radius: var(--border-radius-sm);
            border: 2px solid #e2e8f0;
            font-weight: 500;
        }

        .btn-save-attendance {
            background: linear-gradient(135deg, var(--success-color) 0%, #047857 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            font-weight: 700;
            transition: all 0.2s ease;
            box-shadow: var(--card-shadow);
        }

        .btn-save-attendance:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-lg);
            color: white;
        }

        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            font-weight: 500;
            box-shadow: var(--card-shadow);
        }

        .alert-success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-info {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
        }

        .badge {
            font-weight: 600;
            padding: 0.5rem 0.75rem;
            border-radius: var(--border-radius-sm);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.25rem;
        }

        .stat-icon.present {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: var(--success-color);
        }

        .stat-icon.absent {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: var(--danger-color);
        }

        .stat-icon.leave {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: var(--warning-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .stat-label {
            font-weight: 600;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .form-group {
                width: 100%;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .table-responsive {
                border-radius: var(--border-radius-sm);
            }
        }

        @media (max-width: 576px) {
            .page-header {
                padding: 1.5rem;
            }

            .controls-section {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i>
                Attendance Management
            </h1>
            <p class="page-subtitle">Manage and track employee attendance records</p>
            </div>

        <!-- Status Messages -->
        <?php if ($status == 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><strong>Success!</strong> <?php echo htmlspecialchars($message ?: 'Attendance has been successfully updated.'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($status == 'failure'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><strong>Error!</strong> <?php echo htmlspecialchars($message ?: 'There was an issue updating the attendance. Please try again.'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Controls Section -->
        <div class="controls-section">
            <form id="dateForm" class="filter-controls">
                <div class="form-group">
                    <label for="attendance_date" class="form-label">Select Date</label>
                    <input type="date" id="attendance_date" name="date" class="form-control"
                           value="<?php echo $selected_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="branch" class="form-label">Branch</label>
                    <select name="branch" id="branch" class="form-select">
                        <option value="all" <?php echo $selected_branch === 'all' ? 'selected' : ''; ?>>All Branches</option>
                        <option value="Sarachi" <?php echo $selected_branch === 'Sarachi' ? 'selected' : ''; ?>>Sarachi</option>
                        <option value="Astra" <?php echo $selected_branch === 'Astra' ? 'selected' : ''; ?>>Astra</option>
                        <option value="PS-Qube" <?php echo $selected_branch === 'PS-Qube' ? 'selected' : ''; ?>>PS-Qube</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="shift" class="form-label">Shift</label>
                    <select name="shift" id="shift" class="form-select">
                        <option value="all" <?php echo $selected_shift === 'all' ? 'selected' : ''; ?>>All Shifts</option>
                        <option value="Morning" <?php echo $selected_shift === 'Morning' ? 'selected' : ''; ?>>Morning</option>
                        <option value="Evening" <?php echo $selected_shift === 'Evening' ? 'selected' : ''; ?>>Evening</option>
                        <option value="Full" <?php echo $selected_shift === 'Full' ? 'selected' : ''; ?>>Full</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="college" class="form-label">College</label>
                    <select name="college" id="college" class="form-select">
                        <option value="all" <?php echo $selected_college === 'all' ? 'selected' : ''; ?>>All Colleges</option>
                        <?php foreach ($college_options as $college): ?>
                            <option value="<?php echo htmlspecialchars($college); ?>" 
                                    <?php echo $selected_college === $college ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($college); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <?php
        // Calculate statistics
        $total_employees = 0;
        $present_count = 0;
        $absent_count = 0;
        $leave_count = 0;
        
        if ($result->num_rows > 0) {
            $result->data_seek(0); // Reset result pointer
            while ($row = $result->fetch_assoc()) {
                $total_employees++;
                $status = $row['status'] ?: 'Present';
                if ($status == 'Present') $present_count++;
                elseif ($status == 'Absent') $absent_count++;
                elseif ($status == 'Leave') $leave_count++;
            }
            $result->data_seek(0); // Reset again for main display
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon present">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-number"><?php echo $present_count; ?></div>
                <div class="stat-label">Present</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon absent">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-number"><?php echo $absent_count; ?></div>
                <div class="stat-label">Absent</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon leave">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-number"><?php echo $leave_count; ?></div>
                <div class="stat-label">On Leave</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); color: var(--primary-color);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_employees; ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="attendance-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-list"></i>
                    Attendance for <?php echo date('F d, Y', strtotime($selected_date)); ?>
                </h5>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="markAllPresent">
                        <i class="fas fa-check-circle me-1"></i> Mark All Present
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <form action="/hr-portal/modules/process_attendance.php" method="POST" id="attendanceForm">
                    <input type="hidden" name="csrf_token" value="<?php echo md5(uniqid(mt_rand(), true)); ?>">

                    <?php
                    $current_department = '';
                    if ($result->num_rows > 0) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-hover attendance-table mb-0">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th><i class="fas fa-user me-2"></i>Employee</th>';
                        echo '<th><i class="fas fa-building me-2"></i>Department</th>';
                        // echo '<th><i class="fas fa-map-marker-alt me-2"></i>Branch</th>';
                        // echo '<th><i class="fas fa-clock me-2"></i>Shift</th>';
                        echo '<th><i class="fas fa-clipboard-check me-2"></i>Status</th>';
                        echo '<th><i class="fas fa-sign-in-alt me-2"></i>Check-In</th>';
                        echo '<th><i class="fas fa-sign-out-alt me-2"></i>Check-Out</th>';
                        echo '<th><i class="fas fa-comment me-2"></i>Remarks</th>';
                        echo '<th><i class="fas fa-cog me-2"></i>Action</th>'; // Added Action Column
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';

                        while ($row = $result->fetch_assoc()) {
                            $employee_id = $row['employee_id'];
                            $employee_name = htmlspecialchars($row['full_name']);
                            $department = htmlspecialchars($row['department']);
                            $attendance_status = $row['status'] ?: 'Present';
                            $attendance_remarks = htmlspecialchars($row['remarks'] ?: '');
                            $check_in_time = $row['check_in_time'] ?: '';
                            $check_out_time = $row['check_out_time'] ?: '';

                            // Determine button state based on whether a record exists
                            $record_exists = !empty($row['status']);
                            $btn_class = $record_exists ? 'btn-success' : 'btn-primary';
                            $btn_icon = $record_exists ? 'fa-check' : 'fa-save';

                            // Display department header if it changes
                            if ($department != $current_department) {
                                $current_department = $department;
                                echo '<tr>';
                                echo '<td colspan="9" class="department-header">'; // Updated colspan
                                echo '<i class="fas fa-users me-2"></i>' . $department . ' Department';
                                echo '</td>';
                                echo '</tr>';
                            }

                            echo '<tr id="row-' . $employee_id . '">';
                            echo '<td>';
                            echo '<div class="d-flex align-items-center">';
                            echo '<div class="avatar-circle me-3">';
                            echo '<i class="fas fa-user"></i>';
                            echo '</div>';
                            echo '<div>';
                            echo '<div class="fw-bold">' . $employee_name . '</div>';
                            echo '<small class="text-muted">ID: ' . $employee_id . '</small>';
                            echo '</div>';
                            echo '</div>';
                            echo '</td>';
                            echo '<td><span class="badge bg-light text-dark">' . $department . '</span></td>';
                            // echo '<td><span class="badge bg-primary">' . htmlspecialchars($row['branch']) . '</span></td>';
                            // echo '<td><span class="badge bg-secondary">' . htmlspecialchars($row['shift']) . '</span></td>';
                            echo '<td>';
                            echo '<select class="form-select form-select-sm status-select" name="status[]" id="status-' . $employee_id . '" required>';
                            echo '<option value="Present" ' . ($attendance_status == 'Present' ? 'selected' : '') . '>✓ Present</option>';
                            echo '<option value="Absent" ' . ($attendance_status == 'Absent' ? 'selected' : '') . '>✗ Absent</option>';
                            echo '<option value="Leave" ' . ($attendance_status == 'Leave' ? 'selected' : '') . '>📅 Leave</option>';
                            echo '</select>';
                            echo '</td>';
                            echo '<td><input type="time" class="form-control form-control-sm" name="check_in_time[]" id="check_in-' . $employee_id . '" value="' . $check_in_time . '"></td>';
                            echo '<td><input type="time" class="form-control form-control-sm" name="check_out_time[]" id="check_out-' . $employee_id . '" value="' . $check_out_time . '"></td>';
                            echo '<td><input type="text" class="form-control form-control-sm" name="remarks[]" id="remarks-' . $employee_id . '" value="' . $attendance_remarks . '" placeholder="Optional remarks"></td>';
                            echo '<input type="hidden" name="employee_id[]" value="' . $employee_id . '">';
                            echo '<input type="hidden" name="attendance_date[]" value="' . $selected_date . '">';

                            // Action Button
                            echo '<td>';
                            echo '<button type="button" class="btn btn-sm ' . $btn_class . ' save-btn" onclick="saveRow(' . $employee_id . ')">';
                            echo '<i class="fas ' . $btn_icon . '"></i>';
                            echo '</button>';
                            echo '</td>';

                            echo '</tr>';
                        }

                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';

                        echo '<div class="p-4 bg-light border-top">';
                        echo '<div class="d-flex justify-content-between align-items-center">';
                        echo '<div class="text-muted">';
                        echo '<i class="fas fa-info-circle me-2"></i>';
                        echo 'Total: ' . $total_employees . ' employees';
                        echo '</div>';
                        echo '<button type="submit" class="btn btn-save-attendance">';
                        echo '<i class="fas fa-save me-2"></i>Save All Records';
                        echo '</button>';
                        echo '</div>';
                        echo '</div>';
                    } else {
                        echo '<div class="p-5 text-center">';
                        echo '<div class="mb-4">';
                        echo '<i class="fas fa-users text-muted" style="font-size: 4rem; opacity: 0.3;"></i>';
                        echo '</div>';
                        echo '<h5 class="text-muted">No Employees Found</h5>';
                        echo '<p class="text-muted">No employees found matching the selected criteria or all employees are inactive.</p>';
                        echo '</div>';
                    }
                    ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to save individual row
        function saveRow(employeeId) {
            const btn = document.querySelector(`#row-${employeeId} .save-btn`);

            // Show loading
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            const data = {
                employee_id: employeeId,
                date: '<?php echo $selected_date; ?>',
                status: document.getElementById(`status-${employeeId}`).value,
                check_in_time: document.getElementById(`check_in-${employeeId}`).value,
                check_out_time: document.getElementById(`check_out-${employeeId}`).value,
                remarks: document.getElementById(`remarks-${employeeId}`).value
            };

            fetch('/hr-portal/api/update_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-success');
                    btn.disabled = false;
                    // Button stays green until changed
                } else {
                    alert('Error: ' + data.message);
                    resetButton(btn);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving.');
                resetButton(btn);
            });
        }

        function resetButton(btn) {
            btn.innerHTML = '<i class="fas fa-save"></i>';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-primary');
            btn.disabled = false;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit when any filter changes
            ['attendance_date', 'branch', 'shift', 'college'].forEach(id => {
                document.getElementById(id).addEventListener('change', function() {
                    document.getElementById('dateForm').submit();
                });
            });

            // Add change listeners to all inputs to reset button state
            const inputs = document.querySelectorAll('.status-select, input[name="check_in_time[]"], input[name="check_out_time[]"], input[name="remarks[]"]');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Find the parent row
                    const row = this.closest('tr');
                    if (row) {
                        const btn = row.querySelector('.save-btn');
                        if (btn) {
                            resetButton(btn);
                        }
                    }
                });

                // Also listen for input events on text fields for immediate feedback
                if (input.tagName === 'INPUT') {
                    input.addEventListener('input', function() {
                        const row = this.closest('tr');
                        if (row) {
                            const btn = row.querySelector('.save-btn');
                            if (btn) {
                                resetButton(btn);
                            }
                        }
                    });
                }
            });

            // Mark all present button functionality
            document.getElementById('markAllPresent').addEventListener('click', function() {
                const statusSelects = document.querySelectorAll('.status-select');
                statusSelects.forEach(select => {
                    select.value = 'Present';
                    // Trigger change event to reset buttons
                    select.dispatchEvent(new Event('change'));
                });
                
                // Show feedback
                this.innerHTML = '<i class="fas fa-check me-1"></i> Applied!';
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-success');
                
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-check-circle me-1"></i> Mark All Present';
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-primary');
                }, 2000);
            });

            // Form validation before submit
            document.getElementById('attendanceForm').addEventListener('submit', function(event) {
                const checkInTimes = document.querySelectorAll('input[name="check_in_time[]"]');
                const checkOutTimes = document.querySelectorAll('input[name="check_out_time[]"]');

                for (let i = 0; i < checkInTimes.length; i++) {
                    const checkIn = checkInTimes[i].value;
                    const checkOut = checkOutTimes[i].value;

                    if (checkIn && checkOut && checkIn >= checkOut) {
                        alert('Check-out time must be after check-in time for employee #' + (i + 1));
                        event.preventDefault();
                        return;
                    }
                }
                
                // Show loading state
                const submitBtn = document.querySelector('.btn-save-attendance');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                submitBtn.disabled = true;
            });

            // Add some interactive effects
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = 'var(--card-shadow-lg)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'var(--card-shadow)';
                });
            });

            // Add avatar circles styling
            const style = document.createElement('style');
            style.textContent = `
                .avatar-circle {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 0.875rem;
                    flex-shrink: 0;
                }
                
                .status-select:focus {
                    border-color: var(--primary-color);
                    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
                }
                
                .form-control-sm:focus {
                    border-color: var(--primary-color);
                    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
                }
                
                .table tbody tr {
                    transition: all 0.2s ease;
                }
                
                .table tbody tr:hover {
                    background: #f8fafc;
                    transform: translateX(2px);
                }
                
                .badge {
                    font-size: 0.75rem;
                    font-weight: 600;
                }
                
                .bg-light.text-dark {
                    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
                    color: var(--secondary-color) !important;
                    border: 1px solid #e2e8f0;
                }
                
                .bg-primary {
                    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%) !important;
                }
                
                .bg-secondary {
                    background: linear-gradient(135deg, var(--secondary-color) 0%, #475569 100%) !important;
                }
                
                /* Enhanced mobile responsiveness */
                @media (max-width: 992px) {
                    .stats-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }
                
                @media (max-width: 576px) {
                    .stats-grid {
                        grid-template-columns: 1fr;
                    }
                    
                    .card-header {
                        flex-direction: column;
                        align-items: stretch;
                        gap: 1rem;
                    }
                    
                    .card-header h5 {
                        text-align: center;
                    }
                    
                    .table th, .table td {
                        padding: 0.75rem 0.5rem;
                        font-size: 0.875rem;
                    }
                    
                    .avatar-circle {
                        width: 32px;
                        height: 32px;
                        font-size: 0.75rem;
                    }
                    
                    .form-control-sm, .form-select-sm {
                        font-size: 0.875rem;
                    }
                }
                
                /* Print styles */
                @media print {
                    .content {
                        margin-left: 0;
                        padding: 1rem;
                    }
                    
                    .controls-section,
                    .btn,
                    .alert {
                        display: none !important;
                    }
                    
                    .page-header {
                        background: white !important;
                        color: black !important;
                        box-shadow: none !important;
                        border-bottom: 2px solid #000;
                    }
                    
                    .attendance-card {
                        box-shadow: none !important;
                        border: 1px solid #000;
                    }
                    
                    .table th {
                        background: #f0f0f0 !important;
                        border: 1px solid #000 !important;
                    }
                    
                    .table td {
                        border: 1px solid #000 !important;
                    }
                    
                    .department-header {
                        background: #e0e0e0 !important;
                        color: black !important;
                    }
                }
                
                /* Loading animation */
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.5; }
                    100% { opacity: 1; }
                }
                
                .loading {
                    animation: pulse 1.5s ease-in-out infinite;
                }
                
                /* Success animation */
                @keyframes slideInUp {
                    from {
                        transform: translateY(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
                
                .alert {
                    animation: slideInUp 0.3s ease-out;
                }
                
                /* Hover effects for interactive elements */
                .form-control:hover,
                .form-select:hover {
                    border-color: #cbd5e1;
                }
                
                .status-select option {
                    padding: 0.5rem;
                }
                
                /* Enhanced focus states for accessibility */
                .btn:focus-visible,
                .form-control:focus-visible,
                .form-select:focus-visible {
                    outline: 2px solid var(--primary-color);
                    outline-offset: 2px;
                }
                
                /* Smooth transitions for all interactive elements */
                .btn,
                .form-control,
                .form-select,
                .card,
                .stat-card {
                    transition: all 0.2s ease;
                }
            `;
            document.head.appendChild(style);

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + S to save
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    const form = document.getElementById('attendanceForm');
                    if (form) {
                        form.submit();
                    }
                }
                
                // Ctrl/Cmd + P to mark all present
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    document.getElementById('markAllPresent').click();
                }
            });

            // Add tooltips for better UX
            const tooltips = [
                { selector: '#markAllPresent', title: 'Keyboard shortcut: Ctrl+P' },
                { selector: '.btn-save-attendance', title: 'Keyboard shortcut: Ctrl+S' },
                { selector: '#attendance_date', title: 'Select date to view attendance' },
                { selector: '#branch', title: 'Filter by branch location' },
                { selector: '#shift', title: 'Filter by work shift' },
                { selector: '#college', title: 'Filter by college affiliation' }
            ];

            tooltips.forEach(tooltip => {
                const element = document.querySelector(tooltip.selector);
                if (element) {
                    element.setAttribute('title', tooltip.title);
                    element.setAttribute('data-bs-toggle', 'tooltip');
                }
            });

            // Initialize Bootstrap tooltips if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });
    </script>
</body>
</html>
