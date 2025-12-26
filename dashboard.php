<?php
session_start();
include 'includes/db_connect.php';
include 'includes/auth.php';
include 'includes/exam_api_config.php'; // Include API configuration

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'pages/login.php';</script>";
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// --- API Call to Fetch Exams (Moved to top) ---
$assigned_exams = [];
$candidate_email = isset($user['email']) ? $user['email'] : '';

if (!empty($candidate_email)) {
    // Get the session token from the current session
    $user_session_token = isset($_SESSION['session_token']) ? $_SESSION['session_token'] : '';

    // Append email and session_token to the API request URL
    $request_url = $exam_api_url . '?email=' . urlencode($candidate_email) . '&session_token=' . urlencode($user_session_token);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $exam_api_key
    ]);

    // Disable SSL verification for debugging (if needed, but be careful in production)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    if (!curl_errno($ch)) {
        $data = json_decode($response, true);
        if ($http_code === 200 && isset($data['status']) && $data['status'] === 'success' && !empty($data['assigned_exams'])) {
            $assigned_exams = $data['assigned_exams'];
        } else {
            // Log API error for debugging
            error_log("Exam API Error: HTTP Code: $http_code, Response: $response");
        }
    } else {
        // Log cURL error
        error_log("Exam API cURL Error: $curl_error");
    }
    curl_close($ch);
}

// Get today's attendance
$today = date('Y-m-d');
$attendance_query = "SELECT * FROM attendance WHERE employee_id = ? AND date = ?";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$attendance_result = $stmt->get_result();
$today_attendance = $attendance_result->fetch_assoc();

// Get monthly attendance stats
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$attendance_stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_days,
    COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_days,
    COUNT(CASE WHEN status = 'Leave' THEN 1 END) as leave_days
    FROM attendance 
    WHERE employee_id = ? AND date BETWEEN ? AND ?";
$stmt = $conn->prepare($attendance_stats_query);
$stmt->bind_param("iss", $user_id, $month_start, $month_end);
$stmt->execute();
$attendance_stats = $stmt->get_result()->fetch_assoc();

// Get task completion stats
$task_stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_tasks,
    COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_tasks,
    COUNT(CASE WHEN status = 'Overdue' THEN 1 END) as overdue_tasks
    FROM tasks t
    INNER JOIN task_assignments ta ON t.task_id = ta.task_id
    WHERE ta.employee_id = ?";
$stmt = $conn->prepare($task_stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$task_stats = $stmt->get_result()->fetch_assoc();

// Get pending tasks
$tasks_query = "SELECT * FROM tasks 
                INNER JOIN task_assignments ON tasks.task_id = task_assignments.task_id 
                WHERE task_assignments.employee_id = ? AND tasks.status != 'Completed' 
                ORDER BY tasks.due_date ASC LIMIT 5";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tasks_result = $stmt->get_result();

// Get pending leaves
$leaves_query = "SELECT * FROM leaves WHERE employee_id = ? AND status = 'Pending' ORDER BY start_date ASC";
$stmt = $conn->prepare($leaves_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$leaves_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HR Portal</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white' font-family='Arial'>👤</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 100%;
        }
        .welcome-section {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .task-item {
            border-left: 4px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .leave-item {
            border-left: 4px solid #28a745;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chart-container {
            position: relative;
            height: 200px;
            margin-bottom: 10px;
        }
        .row {
            margin-bottom: 20px;
        }
        .card-content {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .card-header {
            margin-bottom: 20px;
        }
        .card-body {
            flex: 1;
        }
        .card-footer {
            margin-top: auto;
            padding-top: 15px;
        }

        /* Exam Notification Styles */
        .exam-notification {
            border-left: 5px solid #0d6efd !important;
            animation: slideInDown 0.5s ease-out;
            background-color: #fff;
        }

        @keyframes slideInDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .btn-pulse {
            animation: pulse-blue 2s infinite;
        }

        @keyframes pulse-blue {
            0% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/hr-portal/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h2>Welcome, <?php 
                    if (isset($user['full_name'])) {
                        echo htmlspecialchars($user['full_name']);
                    } else {
                        echo 'User';
                    }
                ?>!</h2>
                <p class="mb-0"><?php echo date('l, F j, Y'); ?></p>
            </div>

            <!-- Assigned Exams Section (Displayed only if exams exist) -->
            <?php if (!empty($assigned_exams)): ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card border-0 shadow-sm exam-notification">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h4 class="text-primary mb-1"><i class="fas fa-clipboard-list me-2"></i>Assigned Exams Available</h4>
                                    <p class="mb-0 text-muted">You have pending exams to complete.</p>
                                </div>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($assigned_exams as $exam): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                                    <div>
                                        <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($exam['title']); ?></h5>
                                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars($exam['description']); ?></p>
                                        <span class="badge bg-info text-dark"><i class="far fa-clock me-1"></i><?php echo htmlspecialchars($exam['duration']); ?> mins</span>
                                    </div>

                                    <?php
                                    $status = isset($exam['status']) ? strtolower($exam['status']) : 'assigned';
                                    if ($status === 'completed' || $status === 'disqualified'):
                                    ?>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $status === 'completed' ? 'success' : 'danger'; ?> mb-1">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                            <?php if (isset($exam['score']) && $exam['score'] !== null): ?>
                                                <div class="fw-bold text-success">Score: <?php echo htmlspecialchars($exam['score']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($exam['start_link']); ?>" class="btn btn-primary btn-pulse" target="_blank">
                                            Start Exam <i class="fas fa-arrow-right ms-2"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Today's Attendance -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <div class="card-content">
                            <div class="card-header">
                                <h4>Today's Attendance</h4>
                            </div>
                            <div class="card-body">
                                <?php if ($today_attendance): ?>
                                    <div class="d-flex flex-column h-100">
                                        <div class="text-center mb-4">
                                            <i class="fas fa-<?php 
                                                echo isset($today_attendance['status']) && $today_attendance['status'] === 'Present' ? 'check-circle' : 
                                                    (isset($today_attendance['status']) && $today_attendance['status'] === 'Absent' ? 'times-circle' : 'question-circle'); 
                                            ?> fa-3x text-<?php 
                                                echo isset($today_attendance['status']) && $today_attendance['status'] === 'Present' ? 'success' : 
                                                    (isset($today_attendance['status']) && $today_attendance['status'] === 'Absent' ? 'danger' : 'warning'); 
                                            ?>"></i>
                                        </div>
                                        <div class="text-center mb-4">
                                            <h3 class="mb-0"><?php echo isset($today_attendance['status']) ? htmlspecialchars($today_attendance['status']) : 'Not Recorded'; ?></h3>
                                        </div>
                                        <div class="d-flex justify-content-around mb-4">
                                            <?php if (isset($today_attendance['check_in_time']) && !empty($today_attendance['check_in_time'])): ?>
                                                <div class="text-center">
                                                    <i class="fas fa-sign-in-alt text-primary"></i>
                                                    <p class="mb-0">Check In</p>
                                                    <p class="mb-0 fw-bold"><?php echo date('h:i A', strtotime($today_attendance['check_in_time'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (isset($today_attendance['check_out_time']) && !empty($today_attendance['check_out_time'])): ?>
                                                <div class="text-center">
                                                    <i class="fas fa-sign-out-alt text-primary"></i>
                                                    <p class="mb-0">Check Out</p>
                                                    <p class="mb-0 fw-bold"><?php echo date('h:i A', strtotime($today_attendance['check_out_time'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex flex-column h-100">
                                        <div class="text-center mb-4">
                                            <i class="fas fa-clock fa-3x text-warning"></i>
                                        </div>
                                        <div class="text-center mb-4">
                                            <h3 class="mb-0">No Record</h3>
                                            <p class="text-muted">No attendance recorded for today</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer text-center">
                                <a href="pages/my_attendance.php" class="btn btn-outline-primary">View Attendance History</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Attendance Stats -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <div class="card-content">
                            <div class="card-header">
                                <h4>Monthly Attendance</h4>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="attendanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Task Stats -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <div class="card-content">
                            <div class="card-header">
                                <h4>Task Overview</h4>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="taskChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Tasks -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <div class="card-content">
                            <div class="card-header">
                                <h4>Pending Tasks</h4>
                            </div>
                            <div class="card-body">
                                <?php if ($tasks_result->num_rows > 0): ?>
                                    <?php while ($task = $tasks_result->fetch_assoc()): ?>
                                        <div class="task-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <p class="mb-1 text-muted">Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?></p>
                                            <span class="badge bg-<?php 
                                                echo isset($task['priority']) && $task['priority'] === 'High' ? 'danger' : 
                                                    (isset($task['priority']) && $task['priority'] === 'Medium' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo isset($task['priority']) ? htmlspecialchars($task['priority']) : 'Normal'; ?>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">No pending tasks</p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer text-center">
                                <a href="pages/my_tasks.php" class="btn btn-outline-primary">View All Tasks</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Leaves -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <div class="card-content">
                            <div class="card-header">
                                <h4>Pending Leaves</h4>
                            </div>
                            <div class="card-body">
                                <?php if ($leaves_result->num_rows > 0): ?>
                                    <?php while ($leave = $leaves_result->fetch_assoc()): ?>
                                        <div class="leave-item">
                                            <h6 class="mb-1"><?php echo isset($leave['leave_type']) ? htmlspecialchars($leave['leave_type']) : 'Leave'; ?></h6>
                                            <p class="mb-1 text-muted">
                                                <?php echo isset($leave['start_date']) ? date('M d', strtotime($leave['start_date'])) : ''; ?> - 
                                                <?php echo isset($leave['end_date']) ? date('M d, Y', strtotime($leave['end_date'])) : ''; ?>
                                            </p>
                                            <p class="mb-0"><?php echo isset($leave['reason']) ? htmlspecialchars($leave['reason']) : ''; ?></p>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">No pending leaves</p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer text-center">
                                <a href="pages/view_leave.php" class="btn btn-outline-primary">View All Leaves</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Leave'],
                datasets: [{
                    data: [
                        <?php echo $attendance_stats['present_days'] ?? 0; ?>,
                        <?php echo $attendance_stats['absent_days'] ?? 0; ?>,
                        <?php echo $attendance_stats['leave_days'] ?? 0; ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Task Chart
        const taskCtx = document.getElementById('taskChart').getContext('2d');
        new Chart(taskCtx, {
            type: 'bar',
            data: {
                labels: ['Completed', 'In Progress', 'Overdue'],
                datasets: [{
                    data: [
                        <?php echo $task_stats['completed_tasks'] ?? 0; ?>,
                        <?php echo $task_stats['in_progress_tasks'] ?? 0; ?>,
                        <?php echo $task_stats['overdue_tasks'] ?? 0; ?>
                    ],
                    backgroundColor: ['#28a745', '#007bff', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
