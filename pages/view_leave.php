<?php
// Include the database connection file
include '../includes/db_connect.php';

// Include the sidebar
include '../includes/sidebar.php';

// Check if user is logged in and get role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ../pages/login.php');
    exit;
}

$userRole = $_SESSION['user_role'];
$isAdmin = ($userRole === 'admin');
$userId = $_SESSION['user_id'];

// Handle status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$statusFilterClause = ($statusFilter !== 'all') ? "AND leaves.status = '$statusFilter'" : "";

// Fetch all leave requests from the database
// If user is not admin, only show their own leave requests
if ($isAdmin) {
    $sql = "SELECT leaves.leave_id, leaves.employee_id, leaves.leave_type, leaves.start_date, leaves.end_date, 
            leaves.reason, leaves.status, 
            employees.full_name AS employee_name
            FROM leaves
            JOIN employees ON leaves.employee_id = employees.employee_id
            WHERE 1=1 $statusFilterClause
            ";
} else {
    $sql = "SELECT leaves.leave_id, leaves.employee_id, leaves.leave_type, leaves.start_date, leaves.end_date, 
            leaves.reason, leaves.status,
            employees.full_name AS employee_name
            FROM leaves
            JOIN employees ON leaves.employee_id = employees.employee_id
            WHERE leaves.employee_id = $userId $statusFilterClause
            ";
}

$result = $conn->query($sql);

// Calculate leave statistics for the current user
$statsQuery = "SELECT 
    SUM(CASE WHEN status = 'Approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as approved_days,
    SUM(CASE WHEN status = 'Pending' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as pending_days,
    COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_count,
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'Rejected' THEN 1 END) as rejected_count
    FROM leaves 
    WHERE employee_id = $userId";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
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

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .content-wrapper {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            background: white;
            transition: box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--card-shadow-lg);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .card-header h5 {
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .table-container {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8fafc;
            border: none;
            padding: 1rem 0.75rem;
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            border: none;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .badge-pending {
            background-color: var(--warning-color);
            color: white;
        }

        .badge-approved {
            background-color: var(--success-color);
            color: white;
        }

        .badge-rejected {
            background-color: var(--danger-color);
            color: white;
        }

        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* Hamburger menu button - keep it small like other pages */
        .btn.btn-primary.d-lg-none {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            transform: none;
        }

        .btn.btn-primary.d-lg-none:hover {
            transform: none;
        }

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .stats-card {
            transition: transform 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-card .card-body {
            text-align: center;
            padding: 1.5rem 1rem;
        }

        .stats-card .display-6 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .stats-card .card-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            color: var(--secondary-color);
        }

        .filter-section {
            margin-bottom: 1.5rem;
        }

        .filter-section .card-body {
            padding: 1.5rem;
        }

        .form-select {
            border-radius: var(--border-radius-sm);
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .leave-days {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.875rem;
        }

        .btn-group {
            border-radius: var(--border-radius-sm);
            overflow: hidden;
        }

        .btn-group .btn {
            border-radius: 0;
            margin: 0;
        }

        .btn-group .btn:first-child {
            border-top-left-radius: var(--border-radius-sm);
            border-bottom-left-radius: var(--border-radius-sm);
        }

        .btn-group .btn:last-child {
            border-top-right-radius: var(--border-radius-sm);
            border-bottom-right-radius: var(--border-radius-sm);
        }

        /* Responsive Design */
        @media (max-width: 991.98px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .card-header {
                padding: 1rem 1.25rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-sm {
                padding: 0.5rem 0.75rem;
                font-size: 0.75rem;
            }
            
            .badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.7rem;
            }
        }

        @media (max-width: 767.98px) {
            .content-wrapper {
                padding: 0.75rem;
            }
            
            .page-title {
                font-size: 1.25rem;
                margin-bottom: 1rem;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }
            
            .d-flex.justify-content-between .btn {
                align-self: flex-start;
            }
            
            .stats-card .card-body {
                padding: 1.25rem 1rem;
            }
            
            .stats-card .display-6 {
                font-size: 1.75rem;
            }
            
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
            }
            
            .btn-group {
                flex-direction: column;
                gap: 0.25rem;
                border-radius: var(--border-radius-sm);
            }
            
            .btn-group .btn {
                border-radius: var(--border-radius-sm) !important;
                margin: 0 !important;
            }
            
            .filter-section .card-body {
                padding: 1rem;
            }
            
            .filter-section .row {
                margin: 0;
            }
            
            .filter-section .col-md-4 {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .content-wrapper {
                padding: 0.5rem;
            }
            
            .page-title {
                font-size: 1.1rem;
            }
            
            .card {
                margin-bottom: 1rem;
                border-radius: var(--border-radius-sm);
            }
            
            .card-header {
                padding: 0.75rem 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .stats-card .card-body {
                padding: 1rem 0.75rem;
            }
            
            .stats-card .display-6 {
                font-size: 1.5rem;
            }
            
            .table-responsive {
                font-size: 0.75rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
            }
            
            .badge {
                padding: 0.3rem 0.6rem;
                font-size: 0.65rem;
            }
            
            .btn-sm {
                padding: 0.4rem 0.6rem;
                font-size: 0.7rem;
            }
            
            .leave-days {
                font-size: 0.8rem;
            }
            
            /* Stack table columns on very small screens */
            .table-responsive table {
                display: block;
            }
            
            .table-responsive thead {
                display: none;
            }
            
            .table-responsive tbody,
            .table-responsive tr,
            .table-responsive td {
                display: block;
                width: 100%;
            }
            
            .table-responsive tr {
                border: 1px solid #e2e8f0;
                margin-bottom: 1rem;
                padding: 1rem;
                border-radius: var(--border-radius-sm);
                background: white;
                box-shadow: var(--card-shadow);
            }
            
            .table-responsive td {
                border: none;
                padding: 0.5rem 0;
                text-align: left;
            }
            
            .table-responsive td:before {
                content: attr(data-label) ": ";
                font-weight: 600;
                color: var(--primary-color);
                text-transform: uppercase;
                font-size: 0.7rem;
                letter-spacing: 0.025em;
            }
        }

        /* Print styles */
        @media print {
            .content-wrapper {
                margin-left: 0;
                padding: 0;
                background: white;
            }
            
            .btn,
            .filter-section {
                display: none;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }
        }
    </style>
</head>

<body>
    <!-- Main Content Area -->
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="page-title">Leave Management</h4>
            <?php if (!$isAdmin): ?>
            <a href="../pages/apply_leave.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Apply for Leave
            </a>
            <?php endif; ?>
        </div>

        <?php if (!$isAdmin): ?>
        <!-- Leave Statistics for Regular Users -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title text-primary">Approved Leaves</h5>
                        <p class="card-text display-6"><?php echo $stats['approved_count']; ?></p>
                        <p class="text-muted"><?php echo $stats['approved_days']; ?> days</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title text-warning">Pending Leaves</h5>
                        <p class="card-text display-6"><?php echo $stats['pending_count']; ?></p>
                        <p class="text-muted"><?php echo $stats['pending_days']; ?> days</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title text-danger">Rejected Leaves</h5>
                        <p class="card-text display-6"><?php echo $stats['rejected_count']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title text-success">Available Balance</h5>
                        <p class="card-text display-6">20</p>
                        <p class="text-muted">Annual leaves</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="card filter-section">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="Pending" <?php echo ($statusFilter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo ($statusFilter === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo ($statusFilter === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Admin View - All Leaves Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Leave Requests</h5>
                <span class="badge bg-primary"><?php echo $result->num_rows; ?> requests</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    // Calculate number of days
                                    $startDate = new DateTime($row['start_date']);
                                    $endDate = new DateTime($row['end_date']);
                                    $interval = $startDate->diff($endDate);
                                    $days = $interval->days + 1; // Include both start and end days
                                    
                                    // Format dates for display
                                    $formattedStartDate = $startDate->format('M d, Y');
                                    $formattedEndDate = $endDate->format('M d, Y');
                                    
                                    echo "<tr>";
                                    echo "<td data-label='Employee'>" . htmlspecialchars($row['employee_name']) . "</td>";
                                    echo "<td data-label='Leave Type'>" . htmlspecialchars($row['leave_type']) . "</td>";
                                    echo "<td data-label='Duration'>" . $formattedStartDate . " to " . $formattedEndDate . "</td>";
                                    echo "<td data-label='Days'><span class='leave-days'>" . $days . " day" . ($days > 1 ? "s" : "") . "</span></td>";
                                    echo "<td data-label='Reason'>" . htmlspecialchars($row['reason']) . "</td>";
                                    
                                    // Status with appropriate badge
                                    $statusClass = '';
                                    switch($row['status']) {
                                        case 'Pending':
                                            $statusClass = 'badge-pending';
                                            break;
                                        case 'Approved':
                                            $statusClass = 'badge-approved';
                                            break;
                                        case 'Rejected':
                                            $statusClass = 'badge-rejected';
                                            break;
                                        default:
                                            $statusClass = 'badge-secondary';
                                    }
                                    
                                    echo "<td data-label='Status'><span class='badge {$statusClass}'>" . htmlspecialchars($row['status']) . "</span></td>";
                                    
                                    // Show action buttons for all leaves to admin, but only enable for pending leaves
                                    echo "<td data-label='Actions'>";
                                    if ($row['status'] == 'Pending') {
                                        echo "<div class='btn-group' role='group'>";
                                        echo "<a href='../modules/approve_leave.php?leave_id=" . $row['leave_id'] . "' class='btn btn-success btn-sm'>Approve</a>";
                                        echo "<a href='../modules/reject_leave.php?leave_id=" . $row['leave_id'] . "' class='btn btn-danger btn-sm'>Reject</a>";
                                        echo "</div>";
                                    } else {
                                        // Disabled buttons for non-pending leaves
                                        echo "<div class='btn-group' role='group'>";
                                        echo "<button class='btn btn-outline-secondary btn-sm' disabled>Approve</button>";
                                        echo "<button class='btn btn-outline-secondary btn-sm' disabled>Reject</button>";
                                        echo "</div>";
                                    }
                                    echo "</td>";
                                    
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center py-4'>";
                                echo "<div class='text-muted'><i class='fas fa-calendar-times fa-3x mb-3'></i>";
                                echo "<p>No leave requests found.</p></div>";
                                echo "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Regular User View - My Leaves -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">My Leave Requests</h5>
                <span class="badge bg-primary"><?php echo $result->num_rows; ?> requests</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    // Calculate number of days
                                    $startDate = new DateTime($row['start_date']);
                                    $endDate = new DateTime($row['end_date']);
                                    $interval = $startDate->diff($endDate);
                                    $days = $interval->days + 1; // Include both start and end days
                                    
                                    // Format dates for display
                                    $formattedStartDate = $startDate->format('M d, Y');
                                    $formattedEndDate = $endDate->format('M d, Y');
                                    
                                    echo "<tr>";
                                    echo "<td data-label='Leave Type'>" . htmlspecialchars($row['leave_type']) . "</td>";
                                    echo "<td data-label='Duration'>" . $formattedStartDate . " to " . $formattedEndDate . "</td>";
                                    echo "<td data-label='Days'><span class='leave-days'>" . $days . " day" . ($days > 1 ? "s" : "") . "</span></td>";
                                    echo "<td data-label='Reason'>" . htmlspecialchars($row['reason']) . "</td>";
                                    // Status with appropriate badge
                                    $statusClass = '';
                                    switch($row['status']) {
                                        case 'Pending':
                                            $statusClass = 'badge-pending';
                                            break;
                                        case 'Approved':
                                            $statusClass = 'badge-approved';
                                            break;
                                        case 'Rejected':
                                            $statusClass = 'badge-rejected';
                                            break;
                                        default:
                                            $statusClass = 'badge-secondary';
                                    }
                                    
                                    echo "<td data-label='Status'><span class='badge {$statusClass}'>" . htmlspecialchars($row['status']) . "</span></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center py-4'>";
                                echo "<div class='text-muted'><i class='fas fa-calendar-times fa-3x mb-3'></i>";
                                echo "<p>No leave requests found.</p></div>";
                                echo "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
</body>

</html>

<?php
// Close the connection
$conn->close();
?>