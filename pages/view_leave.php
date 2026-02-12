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

// --- 1. Calculate Summary Statistics (Dynamic) ---
$stats = [
    'total_taken' => 0,
    'pending_count' => 0,
    'approved_count' => 0,
    'rejected_count' => 0
];

if ($isAdmin) {
    // Admin sees stats for ALL employees
    $statsQuery = "SELECT
        SUM(CASE WHEN status = 'Approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as total_taken,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'Rejected' THEN 1 END) as rejected_count
        FROM leaves WHERE YEAR(start_date) = YEAR(CURDATE())";
} else {
    // User sees their own stats
    $statsQuery = "SELECT
        SUM(CASE WHEN status = 'Approved' THEN DATEDIFF(end_date, start_date) + 1 ELSE 0 END) as total_taken,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'Rejected' THEN 1 END) as rejected_count
        FROM leaves
        WHERE employee_id = $userId AND YEAR(start_date) = YEAR(CURDATE())";
}

$statsResult = $conn->query($statsQuery);
if ($statsResult) {
    $stats = $statsResult->fetch_assoc();
}

// --- 2. Fetch Pending Requests (ALL) ---
// We always show all pending requests so they don't get lost
if ($isAdmin) {
    $pendingSql = "SELECT leaves.leave_id, leaves.employee_id, leaves.leave_type, leaves.start_date, leaves.end_date,
            leaves.reason, leaves.status, 
            employees.full_name AS employee_name, employees.profile_picture
            FROM leaves
            JOIN employees ON leaves.employee_id = employees.employee_id
            WHERE leaves.status = 'Pending'
            ORDER BY leaves.start_date ASC"; // Oldest pending first
} else {
    $pendingSql = "SELECT leaves.leave_id, leaves.employee_id, leaves.leave_type, leaves.start_date, leaves.end_date,
            leaves.reason, leaves.status,
            employees.full_name AS employee_name
            FROM leaves
            JOIN employees ON leaves.employee_id = employees.employee_id
            WHERE leaves.employee_id = $userId AND leaves.status = 'Pending'
            ORDER BY leaves.start_date ASC";
}
$pendingResult = $conn->query($pendingSql);
$pendingLeaves = [];
if ($pendingResult) {
    while ($row = $pendingResult->fetch_assoc()) {
        $pendingLeaves[] = $row;
    }
}

// --- 3. Fetch History Requests (Paginated) ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Updated Query to fetch Action By name
if ($isAdmin) {
    $countSql = "SELECT COUNT(*) as total FROM leaves WHERE status != 'Pending'";
    $historySql = "SELECT l.leave_id, l.employee_id, l.leave_type, l.start_date, l.end_date,
            l.reason, l.status,
            e.full_name AS employee_name, e.profile_picture,
            admin.full_name AS action_by_name
            FROM leaves l
            JOIN employees e ON l.employee_id = e.employee_id
            LEFT JOIN employees admin ON l.action_by = admin.employee_id
            WHERE l.status != 'Pending'
            ORDER BY l.start_date DESC
            LIMIT $limit OFFSET $offset";
} else {
    $countSql = "SELECT COUNT(*) as total FROM leaves WHERE employee_id = $userId AND status != 'Pending'";
    $historySql = "SELECT l.leave_id, l.employee_id, l.leave_type, l.start_date, l.end_date,
            l.reason, l.status,
            e.full_name AS employee_name,
            admin.full_name AS action_by_name
            FROM leaves l
            JOIN employees e ON l.employee_id = e.employee_id
            LEFT JOIN employees admin ON l.action_by = admin.employee_id
            WHERE l.employee_id = $userId AND l.status != 'Pending'
            ORDER BY l.start_date DESC
            LIMIT $limit OFFSET $offset";
}

// Get total pages
$countResult = $conn->query($countSql);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Get history data
$historyResult = $conn->query($historySql);
$historyLeaves = [];
if ($historyResult) {
    while ($row = $historyResult->fetch_assoc()) {
        $historyLeaves[] = $row;
    }
}
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
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-bg: #f8f9fc;
            --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Nunito', sans-serif;
        }

        .content-wrapper {
            margin-left: 250px;
            padding: 1.5rem;
        }

        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #5a5c69;
        }

        /* Summary Cards */
        .summary-card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s;
            height: 100%;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-card .card-body {
            padding: 1.25rem;
        }

        .text-xs {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05rem;
        }

        .h5 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .border-left-primary { border-left: .25rem solid var(--primary-color) !important; }
        .border-left-success { border-left: .25rem solid var(--success-color) !important; }
        .border-left-warning { border-left: .25rem solid var(--warning-color) !important; }
        .border-left-info { border-left: .25rem solid var(--info-color) !important; }
        .border-left-danger { border-left: .25rem solid var(--danger-color) !important; }

        .text-primary { color: var(--primary-color) !important; }
        .text-success { color: var(--success-color) !important; }
        .text-warning { color: var(--warning-color) !important; }
        .text-info { color: var(--info-color) !important; }
        .text-gray-300 { color: #dddfeb !important; }

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid #eaecf4;
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-bottom: 2px solid transparent;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom: 2px solid var(--primary-color);
        }

        /* Table Styles */
        .table-card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: var(--card-shadow);
            background: white;
            overflow: hidden;
        }

        .table thead th {
            background-color: #f8f9fc;
            color: var(--secondary-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #5a5c69;
            font-size: 0.95rem;
        }

        .badge-status {
            padding: 0.5em 0.75em;
            font-size: 85%;
            font-weight: 700;
            border-radius: 0.35rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-approved { background-color: #d4edda; color: #155724; }
        .badge-rejected { background-color: #f8d7da; color: #721c24; }

        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 0.75rem;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .date-range {
            font-weight: 600;
            color: #4e73df;
        }

        .duration-badge {
            background-color: #eaecf4;
            color: #5a5c69;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* Pagination */
        .pagination {
            margin-bottom: 0;
        }

        .page-link {
            color: var(--primary-color);
            border: 1px solid #dddfeb;
        }

        .page-link:hover {
            background-color: #eaecf4;
            border-color: #dddfeb;
            color: #224abe;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
            .summary-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center page-header">
            <h1 class="page-title">
                <i class="fas fa-calendar-alt me-2 text-gray-300"></i>Leave Management
            </h1>
            <?php if (!$isAdmin): ?>
            <a href="../pages/request_leave.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50 me-2"></i>Request Leave
            </a>
            <?php endif; ?>
        </div>

        <!-- Summary Cards Row -->
        <div class="row mb-4">
            <!-- Total Leaves Taken -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-primary">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Leaves Taken (Year)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['total_taken'] ?? 0; ?> Days
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-warning">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Pending Requests</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['pending_count'] ?? 0; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approved Requests -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-success">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Approved Requests</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['approved_count'] ?? 0; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rejected Requests -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-danger">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    Rejected Requests</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['rejected_count'] ?? 0; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="leaveTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                    <i class="fas fa-clock me-2"></i>Pending Requests
                    <?php if($stats['pending_count'] > 0): ?>
                        <span class="badge bg-warning text-dark ms-2"><?php echo $stats['pending_count']; ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                    <i class="fas fa-history me-2"></i>Leave History
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="leaveTabsContent">

            <!-- Pending Requests Tab -->
            <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                <div class="card table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php if ($isAdmin): ?><th>Employee</th><?php endif; ?>
                                    <th>Leave Type</th>
                                    <th>Dates</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($pendingLeaves) > 0) {
                                    foreach ($pendingLeaves as $leave) {
                                        $start = new DateTime($leave['start_date']);
                                        $end = new DateTime($leave['end_date']);
                                        $days = $start->diff($end)->days + 1;

                                        $reason = htmlspecialchars($leave['reason']);
                                        $truncatedReason = strlen($reason) > 50 ? substr($reason, 0, 50) . '...' : $reason;
                                        ?>
                                        <tr>
                                            <?php if ($isAdmin): ?>
                                            <td>
                                                <div class="user-info">
                                                    <div class="avatar-circle bg-primary d-flex align-items-center justify-content-center text-white">
                                                        <?php echo strtoupper(substr($leave['employee_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($leave['employee_name']); ?></div>
                                                        <div class="small text-muted">ID: #<?php echo $leave['employee_id']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                            <td>
                                                <div class="date-range">
                                                    <?php echo $start->format('M d, Y'); ?> <i class="fas fa-arrow-right mx-1 text-muted small"></i> <?php echo $end->format('M d, Y'); ?>
                                                </div>
                                            </td>
                                            <td><span class="duration-badge"><?php echo $days; ?> Days</span></td>
                                            <td>
                                                <?php echo $truncatedReason; ?>
                                                <?php if (strlen($reason) > 50): ?>
                                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-bs-toggle="modal" data-bs-target="#reasonModal" data-reason="<?php echo $reason; ?>">
                                                        View
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-pending">
                                                    <i class="fas fa-hourglass-half"></i> Pending
                                                </span>
                                            </td>
                                            <?php if ($isAdmin): ?>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../modules/approve_leave.php?leave_id=<?php echo $leave['leave_id']; ?>" class="btn btn-success" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="../modules/reject_leave.php?leave_id=<?php echo $leave['leave_id']; ?>" class="btn btn-danger" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="' . ($isAdmin ? 7 : 5) . '" class="text-center py-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3 text-gray-300"></i><br>No pending leave requests.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Leave History Tab -->
            <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                <div class="card table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php if ($isAdmin): ?><th>Employee</th><?php endif; ?>
                                    <th>Leave Type</th>
                                    <th>Dates</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Action By</th> <!-- New Column -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($historyLeaves) > 0) {
                                    foreach ($historyLeaves as $leave) {
                                        $start = new DateTime($leave['start_date']);
                                        $end = new DateTime($leave['end_date']);
                                        $days = $start->diff($end)->days + 1;

                                        $statusClass = $leave['status'] === 'Approved' ? 'badge-approved' : 'badge-rejected';
                                        $statusIcon = $leave['status'] === 'Approved' ? 'fa-check-circle' : 'fa-times-circle';

                                        $reason = htmlspecialchars($leave['reason']);
                                        $truncatedReason = strlen($reason) > 50 ? substr($reason, 0, 50) . '...' : $reason;

                                        $actionBy = !empty($leave['action_by_name']) ? htmlspecialchars($leave['action_by_name']) : '-';
                                        ?>
                                        <tr>
                                            <?php if ($isAdmin): ?>
                                            <td>
                                                <div class="user-info">
                                                    <div class="avatar-circle bg-secondary d-flex align-items-center justify-content-center text-white">
                                                        <?php echo strtoupper(substr($leave['employee_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($leave['employee_name']); ?></div>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                            <td>
                                                <div class="text-muted">
                                                    <?php echo $start->format('M d, Y'); ?> - <?php echo $end->format('M d, Y'); ?>
                                                </div>
                                            </td>
                                            <td><span class="duration-badge"><?php echo $days; ?> Days</span></td>
                                            <td>
                                                <?php echo $truncatedReason; ?>
                                                <?php if (strlen($reason) > 50): ?>
                                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-bs-toggle="modal" data-bs-target="#reasonModal" data-reason="<?php echo $reason; ?>">
                                                        View
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-status <?php echo $statusClass; ?>">
                                                    <i class="fas <?php echo $statusIcon; ?>"></i> <?php echo $leave['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted small">
                                                    <i class="fas fa-user-shield me-1"></i> <?php echo $actionBy; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="' . ($isAdmin ? 7 : 6) . '" class="text-center py-5 text-muted"><i class="fas fa-history fa-3x mb-3 text-gray-300"></i><br>No leave history found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-white border-0 py-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <!-- Previous Link -->
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>#history" tabindex="-1" aria-disabled="true">Previous</a>
                                </li>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>#history"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next Link -->
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>#history">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Reason Modal -->
    <div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reasonModalLabel">Leave Reason</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="fullReasonText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script to activate correct tab based on URL hash and handle modal -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Tab Logic
            var hash = window.location.hash;
            if (hash) {
                var triggerEl = document.querySelector('button[data-bs-target="' + hash + '"]');
                if (triggerEl) {
                    var tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                }
            }

            // Modal Logic
            var reasonModal = document.getElementById('reasonModal');
            reasonModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var reason = button.getAttribute('data-reason');
                var modalBody = reasonModal.querySelector('#fullReasonText');
                modalBody.textContent = reason;
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
