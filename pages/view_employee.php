<?php
// 1. Auth first (starts session, includes db_connect)
include '../includes/auth.php';

// 2. Validation & Redirects (Must happen before any other includes that might output)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: candidate.php'); // Redirect to candidate list, not employees.php (which doesn't exist)
    exit();
}

$employee_id = $_GET['id'];

// Fetch employee basic information
$employee_query = "SELECT * FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($employee_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    header('Location: candidate.php');
    exit();
}

// 3. Now safe to include other files
include '../includes/exam_api_config.php';

// --- API Call to Fetch Exams for this Employee ---
$assigned_exams = [];
$candidate_email = isset($employee['email']) ? $employee['email'] : '';

if (!empty($candidate_email)) {
    $request_url = $exam_api_url . '?email=' . urlencode($candidate_email);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $exam_api_key
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (!curl_errno($ch)) {
        $data = json_decode($response, true);
        if ($http_code === 200 && isset($data['status']) && $data['status'] === 'success' && !empty($data['assigned_exams'])) {
            $assigned_exams = $data['assigned_exams'];
        }
    }
    curl_close($ch);
}

// Fetch attendance statistics
$attendance_query = "    
    SELECT         
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_days,
        SUM(CASE WHEN remarks LIKE '%late%' THEN 1 ELSE 0 END) as late_days
    FROM attendance     
    WHERE employee_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$attendance_stats = $stmt->get_result()->fetch_assoc();

// Fetch recent attendance records
$recent_attendance_query = "
    SELECT * FROM attendance 
    WHERE employee_id = ? 
    ORDER BY date DESC 
    LIMIT 5
";
$stmt = $conn->prepare($recent_attendance_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$recent_attendance = $stmt->get_result();

// Fetch task statistics
$task_query = "
    SELECT 
        COUNT(DISTINCT t.task_id) as total_tasks,
        SUM(CASE WHEN tp.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN tp.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN tp.status = 'Overdue' THEN 1 ELSE 0 END) as overdue_tasks
    FROM task_assignments ta
    LEFT JOIN tasks t ON ta.task_id = t.task_id
    LEFT JOIN task_progress tp ON t.task_id = tp.task_id AND ta.employee_id = tp.employee_id
    WHERE ta.employee_id = ?
";
$stmt = $conn->prepare($task_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$task_stats = $stmt->get_result()->fetch_assoc();

// Fetch recent tasks
$recent_tasks_query = "
    SELECT 
        t.task_id,
        t.title,
        t.priority,
        t.due_date,
        t.status as task_status,
        e.full_name as assigned_by_name
    FROM task_assignments ta
    JOIN tasks t ON ta.task_id = t.task_id
    LEFT JOIN employees e ON t.assigned_by = e.employee_id
    WHERE ta.employee_id = ?
    ORDER BY t.due_date DESC
    LIMIT 5
";

$stmt = $conn->prepare($recent_tasks_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$recent_tasks = $stmt->get_result();

// Fetch performance reviews
$performance_query = "
    SELECT 
        pr.*,
        e.full_name as reviewer_name
    FROM performance_reviews pr
    LEFT JOIN employees e ON pr.reviewer_id = e.employee_id
    WHERE pr.employee_id = ?
    ORDER BY pr.review_date DESC
";
$stmt = $conn->prepare($performance_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$performance_reviews = $stmt->get_result();

// Fetch leave statistics
$leaves_query = "
    SELECT 
        COUNT(*) as total_leaves,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_leaves,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_leaves,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_leaves
    FROM leaves 
    WHERE employee_id = ? AND YEAR(start_date) = YEAR(CURDATE())
";
$stmt = $conn->prepare($leaves_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$leave_stats = $stmt->get_result()->fetch_assoc();

// Fetch leave history
$leave_history_query = "
    SELECT * FROM leaves 
    WHERE employee_id = ? 
    ORDER BY start_date DESC
";
$stmt = $conn->prepare($leave_history_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$leave_history = $stmt->get_result();

// Fetch attendance statistics for absents
$absent_query = "
    SELECT COUNT(*) as total_absents
    FROM attendance 
    WHERE employee_id = ? 
    AND status = 'Absent' 
    AND YEAR(date) = YEAR(CURDATE())
";
$stmt = $conn->prepare($absent_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$absent_stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - <?php echo htmlspecialchars($employee['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 260px; /* Matches sidebar width */
            padding: 20px;
        }
        .profile-header {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-card h5 {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }
        .progress {
            height: 8px;
            margin-top: 5px;
        }
        .badge {
            padding: 5px 10px;
            font-weight: 500;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .rating-stars {
            font-size: 1.2rem;
        }
        .rating-stars .fas {
            margin-right: 2px;
        }
        .review-comments {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #6B73FF;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-marker {
            position: absolute;
            left: -22px;
            top: 20px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1;
        }
        .timeline-content {
            margin-left: 10px;
        }
        .leave-reason {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .modal .info-label {
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .modal .info-value {
            color: #2c3e50;
            font-weight: 500;
            margin-bottom: 15px;
        }
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
            .timeline {
                padding-left: 20px;
            }
            .timeline-marker {
                left: -15px;
                width: 24px;
                height: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar Here, AFTER logic and BEFORE content -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <img src="<?php echo htmlspecialchars($employee['profile_picture'] ?? '../assets/images/default-avatar.png'); ?>" 
                             alt="Profile Picture" class="profile-picture">
                    </div>
                    <div class="col-md-10">
                        <h1 class="mb-2"><?php echo htmlspecialchars($employee['full_name']); ?></h1>
                        <p class="mb-1">
                            <i class="fas fa-briefcase"></i> 
                            <?php echo htmlspecialchars($employee['department']); ?> Department
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-envelope"></i> 
                            <?php echo htmlspecialchars($employee['email']); ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-phone"></i> 
                            <?php echo htmlspecialchars($employee['mobile_number']); ?>
                        </p>
                        <span class="badge bg-<?php echo $employee['status'] === 'Active' ? 'success' : 'danger'; ?>">
                            <?php echo htmlspecialchars($employee['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistics Row -->
            <div class="row mb-4">
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="stat-card">
                        <h5>Attendance Rate (30 Days)</h5>
                        <div class="value">
                            <?php
                            $attendance_rate = $attendance_stats['total_days'] > 0 ?
                                round(($attendance_stats['present_days'] / $attendance_stats['total_days']) * 100) : 0;
                            echo $attendance_rate; ?>%
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?php echo $attendance_rate; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="stat-card">
                        <h5>Task Completion Rate</h5>
                        <div class="value">
                            <?php
                            $completion_rate = $task_stats['total_tasks'] > 0 ?
                                round(($task_stats['completed_tasks'] / $task_stats['total_tasks']) * 100) : 0;
                            echo $completion_rate; ?>%
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-info" style="width: <?php echo $completion_rate; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="stat-card">
                        <h5>Average Performance Rating</h5>
                        <div class="value">
                            <?php
                            $total_rating = 0;
                            $review_count = 0;
                            while ($review = $performance_reviews->fetch_assoc()) {
                                $total_rating += $review['rating'];
                                $review_count++;
                            }
                            $avg_rating = $review_count > 0 ? round($total_rating / $review_count, 1) : 0;
                            echo $avg_rating; ?>/5
                            </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" style="width: <?php echo ($avg_rating / 5) * 100; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="stat-card">
                        <h5>Late Arrivals (30 Days)</h5>
                        <div class="value text-info">
                            <?php echo $attendance_stats['late_days'] ?? 0; ?> days
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                Late Rate: <?php 
                                $late_rate = $attendance_stats['total_days'] > 0 ? 
                                    round(($attendance_stats['late_days'] / $attendance_stats['total_days']) * 100, 1) : 0;
                                echo $late_rate; ?>%
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-3">
                    <div class="stat-card">
                        <h5>Total Leaves & Absents (This Year)</h5>
                        <div class="value">
                            <?php
                            $total_approved_leaves = $leave_stats['approved_leaves'] ?? 0;
                            $total_absents = $absent_stats['total_absents'] ?? 0;
                            $total_leave_days = $total_approved_leaves + $total_absents;
                            echo $total_leave_days; ?> days
                            </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                Approved Leaves: <?php echo $total_approved_leaves; ?> |
                                Absents: <?php echo $total_absents; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Personal Information -->
            <div class="row">
                <div class="col-md-6">
                    <div class="info-card">
                        <h4 class="section-title">Personal Information</h4>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="info-label">Date of Birth</div>
                                <div class="info-value"><?php echo date('d M Y', strtotime($employee['dob'])); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Gender</div>
                                <div class="info-value"><?php echo htmlspecialchars($employee['gender']); ?></div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="info-label">Communication Address</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($employee['communication_address'])); ?></div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="info-label">Permanent Address</div>
                                <div class="info-value"><?php echo nl2br(htmlspecialchars($employee['permanent_address'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h4 class="section-title">Professional Information</h4>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="info-label">Employee ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($employee['employee_id']); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Role</div>
                                <div class="info-value"><?php echo htmlspecialchars($employee['role']); ?></div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="info-label">Hire Date</div>
                                <div class="info-value"><?php echo date('d M Y', strtotime($employee['hire_date'])); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo htmlspecialchars($employee['department']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Tasks -->
            <div class="info-card">
                <h4 class="section-title">Recent Tasks</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Assigned By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($task = $recent_tasks->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $task['priority'] === 'High' ? 'danger' : 
                                                ($task['priority'] === 'Medium' ? 'warning' : 'success'); 
                                        ?>">
                                            <?php echo htmlspecialchars($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($task['due_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($task['assigned_by_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $task['task_status'] === 'Completed' ? 'success' : 
                                                ($task['task_status'] === 'In Progress' ? 'info' : 
                                                ($task['task_status'] === 'Overdue' ? 'danger' : 'warning')); 
                                        ?>">
                                            <?php echo htmlspecialchars($task['task_status'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Exam Scores Section -->
            <?php if (!empty($assigned_exams)): ?>
            <div class="info-card">
                <h4 class="section-title">Exam Scores</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Exam Title</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_exams as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['duration']); ?> mins</td>
                                    <td>
                                        <?php
                                        $status = isset($exam['status']) ? strtolower($exam['status']) : 'assigned';
                                        $badgeClass = 'secondary';
                                        if ($status === 'completed') $badgeClass = 'success';
                                        elseif ($status === 'disqualified') $badgeClass = 'danger';
                                        elseif ($status === 'started') $badgeClass = 'info';
                                        elseif ($status === 'assigned') $badgeClass = 'primary';
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if (isset($exam['score']) && $exam['score'] !== null) {
                                            echo '<span class="fw-bold text-success">' . htmlspecialchars($exam['score']) . '</span>';
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Performance Reviews -->
            <div class="info-card">
                <h4 class="section-title">
                    <i class="fas fa-star text-warning me-2"></i>Performance Reviews
                </h4>
                <?php
                $performance_reviews->data_seek(0); // Reset pointer
                if ($performance_reviews->num_rows > 0):
                ?>
                    <div class="row">
                        <?php while ($review = $performance_reviews->fetch_assoc()): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="card-title mb-1">
                                                    <i class="fas fa-calendar-alt text-muted me-2"></i>
                                                    <?php echo date('M d, Y', strtotime($review['review_date'])); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    Reviewed by <?php echo htmlspecialchars($review['reviewer_name'] ?? 'N/A'); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <div class="rating-stars mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted fw-bold"><?php echo $review['rating']; ?>/5</small>
                                            </div>
                                        </div>
                                        <?php if (!empty($review['comments'])): ?>
                                            <div class="review-comments">
                                                <p class="mb-2 text-dark">
                                                    <i class="fas fa-quote-left text-muted me-2"></i>
                                                    <?php
                                                    $truncated_comments = strlen($review['comments']) > 100 ?
                                                        substr($review['comments'], 0, 100) . '...' :
                                                        $review['comments'];
                                                    echo nl2br(htmlspecialchars($truncated_comments));
                                                    ?>
                                                </p>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $review['review_id']; ?>">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-star-half-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No performance reviews available yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Leave History -->
            <div class="info-card">
                <h4 class="section-title">
                    <i class="fas fa-calendar-times text-info me-2"></i>Leave History
                </h4>
                <?php if ($leave_history && $leave_history->num_rows > 0): ?>
                    <div class="timeline">
                        <?php while ($leave = $leave_history->fetch_assoc()):
                            $start_date = strtotime($leave['start_date']);
                            $end_date = strtotime($leave['end_date']);
                            $duration = ceil(($end_date - $start_date) / (60*60*24)) + 1; // Including end date

                            $status_class = '';
                            $status_icon = '';
                            if ($leave['status'] === 'Approved') {
                                $status_class = 'success';
                                $status_icon = 'fas fa-check-circle';
                            } elseif ($leave['status'] === 'Rejected') {
                                $status_class = 'danger';
                                $status_icon = 'fas fa-times-circle';
                            } else {
                                $status_class = 'warning';
                                $status_icon = 'fas fa-clock';
                            }

                            $leave_icon = '';
                            switch (strtolower($leave['leave_type'])) {
                                case 'sick leave':
                                    $leave_icon = 'fas fa-thermometer-half';
                                    break;
                                case 'casual leave':
                                    $leave_icon = 'fas fa-umbrella-beach';
                                    break;
                                case 'annual leave':
                                    $leave_icon = 'fas fa-plane';
                                    break;
                                case 'maternity leave':
                                    $leave_icon = 'fas fa-baby';
                                    break;
                                default:
                                    $leave_icon = 'fas fa-calendar-day';
                            }
                        ?>
                            <div class="timeline-item mb-4">
                                <div class="timeline-marker bg-<?php echo $status_class; ?>">
                                    <i class="<?php echo $status_icon; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="card-title mb-1">
                                                        <i class="<?php echo $leave_icon; ?> text-primary me-2"></i>
                                                        <?php echo htmlspecialchars($leave['leave_type']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?php echo date('M d, Y', $start_date); ?> - <?php echo date('M d, Y', $end_date); ?>
                                                        <span class="badge bg-light text-dark ms-2"><?php echo $duration; ?> day<?php echo $duration > 1 ? 's' : ''; ?></span>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?php echo $status_class; ?> fs-6">
                                                    <i class="<?php echo $status_icon; ?> me-1"></i>
                                                    <?php echo htmlspecialchars($leave['status']); ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($leave['reason'])): ?>
                                                <div class="leave-reason">
                                                    <p class="mb-2 text-muted small">
                                                        <i class="fas fa-comment-dots me-2"></i>
                                                        <?php
                                                        $truncated_reason = strlen($leave['reason']) > 80 ?
                                                            substr($leave['reason'], 0, 80) . '...' :
                                                            $leave['reason'];
                                                        echo nl2br(htmlspecialchars($truncated_reason));
                                                        ?>
                                                    </p>
                                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#leaveModal<?php echo $leave['leave_id']; ?>">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#leaveModal<?php echo $leave['leave_id']; ?>">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No leave records found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Performance Review Modals -->
    <?php
    $performance_reviews->data_seek(0); // Reset pointer
    while ($review = $performance_reviews->fetch_assoc()):
    ?>
    <div class="modal fade" id="reviewModal<?php echo $review['review_id']; ?>" tabindex="-1" aria-labelledby="reviewModalLabel<?php echo $review['review_id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel<?php echo $review['review_id']; ?>">
                        <i class="fas fa-star text-warning me-2"></i>Performance Review Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-label">Review Date</div>
                            <div class="info-value"><?php echo date('F d, Y', strtotime($review['review_date'])); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Reviewer</div>
                            <div class="info-value"><?php echo htmlspecialchars($review['reviewer_name'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-label">Rating</div>
                            <div class="info-value">
                                <div class="rating-stars mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?> fa-lg"></i>
                                    <?php endfor; ?>
                                </div>
                                <strong><?php echo $review['rating']; ?>/5</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Employee</div>
                            <div class="info-value"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                        </div>
                    </div>
                    <?php if (!empty($review['comments'])): ?>
                        <div class="mb-3">
                            <div class="info-label">Comments</div>
                            <div class="info-value">
                                <div class="bg-light p-3 rounded">
                                    <i class="fas fa-quote-left text-muted me-2"></i>
                                    <?php echo nl2br(htmlspecialchars($review['comments'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <!-- Leave History Modals -->
    <?php
    $leave_history->data_seek(0); // Reset pointer
    while ($leave = $leave_history->fetch_assoc()):
        $start_date = strtotime($leave['start_date']);
        $end_date = strtotime($leave['end_date']);
        $duration = ceil(($end_date - $start_date) / (60*60*24)) + 1;

        $status_class = '';
        if ($leave['status'] === 'Approved') {
            $status_class = 'success';
        } elseif ($leave['status'] === 'Rejected') {
            $status_class = 'danger';
        } else {
            $status_class = 'warning';
        }

        $leave_icon = '';
        switch (strtolower($leave['leave_type'])) {
            case 'sick leave':
                $leave_icon = 'fas fa-thermometer-half';
                break;
            case 'casual leave':
                $leave_icon = 'fas fa-umbrella-beach';
                break;
            case 'annual leave':
                $leave_icon = 'fas fa-plane';
                break;
            case 'maternity leave':
                $leave_icon = 'fas fa-baby';
                break;
            default:
                $leave_icon = 'fas fa-calendar-day';
        }
    ?>
    <div class="modal fade" id="leaveModal<?php echo $leave['leave_id']; ?>" tabindex="-1" aria-labelledby="leaveModalLabel<?php echo $leave['leave_id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveModalLabel<?php echo $leave['leave_id']; ?>">
                        <i class="<?php echo $leave_icon; ?> text-primary me-2"></i>Leave Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-label">Leave Type</div>
                            <div class="info-value">
                                <i class="<?php echo $leave_icon; ?> text-primary me-2"></i>
                                <?php echo htmlspecialchars($leave['leave_type']); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="badge bg-<?php echo $status_class; ?> fs-6">
                                    <i class="fas fa-<?php echo $leave['status'] === 'Approved' ? 'check' : ($leave['status'] === 'Rejected' ? 'times' : 'clock'); ?>-circle me-1"></i>
                                    <?php echo htmlspecialchars($leave['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="info-label">Start Date</div>
                            <div class="info-value"><?php echo date('F d, Y', $start_date); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">End Date</div>
                            <div class="info-value"><?php echo date('F d, Y', $end_date); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Duration</div>
                            <div class="info-value"><strong><?php echo $duration; ?> day<?php echo $duration > 1 ? 's' : ''; ?></strong></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-label">Applied On</div>
                            <div class="info-value"><?php echo date('F d, Y', strtotime($leave['applied_on'])); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Employee</div>
                            <div class="info-value"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                        </div>
                    </div>
                    <?php if (!empty($leave['reason'])): ?>
                        <div class="mb-3">
                            <div class="info-label">Reason</div>
                            <div class="info-value">
                                <div class="bg-light p-3 rounded">
                                    <i class="fas fa-comment-dots text-muted me-2"></i>
                                    <?php echo nl2br(htmlspecialchars($leave['reason'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>