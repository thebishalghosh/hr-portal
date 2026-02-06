<?php
session_start();
// db_connect.php is included inside auth.php, so we don't need to include it here again
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

// --- API Call to Fetch Exams ---
$assigned_exams = [];
$candidate_email = isset($user['email']) ? $user['email'] : '';

if (!empty($candidate_email)) {
    $user_session_token = isset($_SESSION['session_token']) ? $_SESSION['session_token'] : '';
    $request_url = $exam_api_url . '?email=' . urlencode($candidate_email) . '&session_token=' . urlencode($user_session_token);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

    $headers = [
        'X-API-KEY: ' . trim($exam_api_key),
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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

// Calculate Attendance Percentage
$total_recorded_days = $attendance_stats['present_days'] + $attendance_stats['absent_days'] + $attendance_stats['leave_days'];
$attendance_percentage = $total_recorded_days > 0 ? round(($attendance_stats['present_days'] / $total_recorded_days) * 100) : 0;

// Get task completion stats
$task_stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_tasks,
    COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress_tasks,
    COUNT(CASE WHEN status = 'Overdue' THEN 1 END) as overdue_tasks,
    COUNT(*) as total_tasks
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

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">

    <!-- Google Charts -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

    <style>
        :root {
            --primary-color: #4f46e5; /* Indigo 600 */
            --primary-light: #e0e7ff; /* Indigo 100 */
            --secondary-color: #64748b; /* Slate 500 */
            --success-color: #10b981; /* Emerald 500 */
            --warning-color: #f59e0b; /* Amber 500 */
            --danger-color: #ef4444; /* Red 500 */
            --bg-color: #f3f4f6; /* Gray 100 */
            --card-bg: #ffffff;
            --text-main: #111827; /* Gray 900 */
            --text-muted: #6b7280; /* Gray 500 */
            --border-radius: 1rem;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Welcome Section */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color), #4338ca);
            color: white;
            padding: 2.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTAwIDBMMjAwIDIwMEgwWjIiIGZpbGw9IndoaXRlIiBmaWxsLW9wYWNpdHk9IjAuMDUiLz48L3N2Zz4=') no-repeat right center;
            background-size: cover;
            opacity: 0.5;
        }

        .welcome-title {
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .welcome-date {
            font-weight: 400;
            opacity: 0.9;
            font-size: 1rem;
        }

        /* Cards */
        .dashboard-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            height: 100%;
            border: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .card-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-title-custom {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
            margin: 0;
        }

        /* Stat Cards */
        .stat-card {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.blue { background-color: var(--primary-light); color: var(--primary-color); }
        .stat-icon.green { background-color: #d1fae5; color: var(--success-color); }
        .stat-icon.orange { background-color: #fef3c7; color: var(--warning-color); }
        .stat-icon.red { background-color: #fee2e2; color: var(--danger-color); }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .stat-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Lists */
        .list-item-custom {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .list-item-custom:last-child {
            border-bottom: none;
        }

        .list-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--secondary-color);
        }

        .list-content {
            flex: 1;
        }

        .list-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
            display: block;
            color: var(--text-main);
            text-decoration: none;
        }

        .list-title:hover {
            color: var(--primary-color);
        }

        .list-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Exam Notification */
        .exam-card {
            background: white;
            border-left: 5px solid var(--primary-color);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            animation: slideIn 0.5s ease-out;
        }

        .btn-pulse {
            animation: pulse 2s infinite;
            background-color: var(--primary-color);
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
            100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Charts */
        .chart-container {
            height: 300px;
            width: 100%;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 1rem; }
            .welcome-banner { padding: 1.5rem; }
            .welcome-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/hr-portal/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
                    <p class="welcome-date"><i class="far fa-calendar-alt me-2"></i><?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="col-md-4 text-md-end d-none d-md-block">
                    <!-- Optional: Add a small illustration or action button here -->
                </div>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="row g-4 mb-4">
            <!-- Attendance Today -->
            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card stat-card">
                    <div class="stat-icon <?php echo ($today_attendance['status'] ?? '') === 'Present' ? 'green' : 'orange'; ?>">
                        <i class="fas <?php echo ($today_attendance['status'] ?? '') === 'Present' ? 'fa-check' : 'fa-clock'; ?>"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $today_attendance['status'] ?? 'Not Marked'; ?></h3>
                        <p>Today's Status</p>
                    </div>
                </div>
            </div>

            <!-- Monthly Attendance Score -->
            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $attendance_percentage; ?>%</h3>
                        <p>Attendance Rate</p>
                    </div>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card stat-card">
                    <div class="stat-icon <?php echo ($task_stats['in_progress_tasks'] > 0) ? 'orange' : 'blue'; ?>">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $task_stats['in_progress_tasks'] ?? 0; ?></h3>
                        <p>Tasks In Progress</p>
                    </div>
                </div>
            </div>

            <!-- Pending Leaves -->
            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-plane-departure"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $leaves_result->num_rows; ?></h3>
                        <p>Pending Leaves</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Exams Section -->
        <?php if (!empty($assigned_exams)): ?>
        <div class="exam-card p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="text-primary mb-1"><i class="fas fa-graduation-cap me-2"></i>Assigned Exams</h4>
                    <p class="text-muted mb-0">You have pending exams to complete.</p>
                </div>
            </div>
            <hr class="my-3 opacity-10">
            <div class="row g-3">
                <?php foreach ($assigned_exams as $exam): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="bg-light p-3 rounded-3 border d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($exam['title']); ?></h6>
                            <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo htmlspecialchars($exam['duration']); ?> mins</small>
                        </div>
                        <?php
                        $status = isset($exam['status']) ? strtolower($exam['status']) : 'assigned';
                        if ($status === 'completed' || $status === 'disqualified'):
                        ?>
                            <span class="badge bg-<?php echo $status === 'completed' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($status); ?>
                                <?php if (isset($exam['score'])) echo ' (' . $exam['score'] . ')'; ?>
                            </span>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($exam['start_link']); ?>" class="btn btn-primary btn-sm btn-pulse text-white" target="_blank">
                                Start <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="row g-4">
            <!-- Left Column: Charts -->
            <div class="col-lg-8">
                <div class="row g-4">
                    <!-- Attendance Chart -->
                    <div class="col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header-custom">
                                <h5 class="card-title-custom">Attendance Overview</h5>
                                <a href="pages/my_attendance.php" class="text-decoration-none small text-primary">View All</a>
                            </div>
                            <div id="attendanceChart" class="chart-container"></div>
                        </div>
                    </div>

                    <!-- Task Chart -->
                    <div class="col-md-6">
                        <div class="dashboard-card">
                            <div class="card-header-custom">
                                <h5 class="card-title-custom">Task Status</h5>
                                <a href="pages/my_tasks.php" class="text-decoration-none small text-primary">View All</a>
                            </div>
                            <div id="taskChart" class="chart-container"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Lists -->
            <div class="col-lg-4">
                <!-- Pending Tasks List -->
                <div class="dashboard-card mb-4">
                    <div class="card-header-custom">
                        <h5 class="card-title-custom">Upcoming Deadlines</h5>
                    </div>
                    <?php if ($tasks_result->num_rows > 0): ?>
                        <div class="d-flex flex-column gap-2">
                            <?php while ($task = $tasks_result->fetch_assoc()): ?>
                                <div class="list-item-custom">
                                    <div class="list-icon">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div class="list-content">
                                        <a href="pages/my_tasks.php" class="list-title"><?php echo htmlspecialchars($task['title']); ?></a>
                                        <span class="list-subtitle text-danger">
                                            <i class="far fa-calendar me-1"></i>Due: <?php echo date('M d', strtotime($task['due_date'])); ?>
                                        </span>
                                    </div>
                                    <span class="badge bg-<?php
                                        echo $task['priority'] === 'High' ? 'danger' : ($task['priority'] === 'Medium' ? 'warning' : 'info');
                                    ?> rounded-pill"><?php echo $task['priority']; ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0">No pending tasks!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Leaves List -->
                <div class="dashboard-card">
                    <div class="card-header-custom">
                        <h5 class="card-title-custom">Leave Requests</h5>
                        <a href="pages/view_leave.php" class="text-decoration-none small text-primary">History</a>
                    </div>
                    <?php if ($leaves_result->num_rows > 0): ?>
                        <div class="d-flex flex-column gap-2">
                            <?php while ($leave = $leaves_result->fetch_assoc()): ?>
                                <div class="list-item-custom">
                                    <div class="list-icon">
                                        <i class="fas fa-plane"></i>
                                    </div>
                                    <div class="list-content">
                                        <span class="list-title"><?php echo htmlspecialchars($leave['leave_type']); ?></span>
                                        <span class="list-subtitle">
                                            <?php echo date('M d', strtotime($leave['start_date'])); ?> - <?php echo date('M d', strtotime($leave['end_date'])); ?>
                                        </span>
                                    </div>
                                    <span class="badge bg-warning text-dark rounded-pill">Pending</span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <p class="mb-0">No pending requests.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Google Charts Script -->
    <script>
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            // Common options for cleaner look
            const commonOptions = {
                backgroundColor: 'transparent',
                chartArea: { width: '85%', height: '75%' },
                legend: { position: 'bottom', textStyle: { color: '#6b7280', fontSize: 12 } },
                titleTextStyle: { color: '#111827', fontSize: 14, bold: true },
                fontName: 'Inter'
            };

            // --- Attendance Chart (Pie) ---
            var attendanceData = google.visualization.arrayToDataTable([
                ['Status', 'Days'],
                ['Present', <?php echo $attendance_stats['present_days'] ?? 0; ?>],
                ['Absent', <?php echo $attendance_stats['absent_days'] ?? 0; ?>],
                ['Leave', <?php echo $attendance_stats['leave_days'] ?? 0; ?>]
            ]);

            var attendanceOptions = {
                ...commonOptions,
                pieHole: 0.5,
                colors: ['#10b981', '#ef4444', '#f59e0b'],
                pieSliceBorderColor: 'transparent'
            };

            var attendanceChart = new google.visualization.PieChart(document.getElementById('attendanceChart'));
            attendanceChart.draw(attendanceData, attendanceOptions);

            // --- Task Chart (Column) ---
            var taskData = google.visualization.arrayToDataTable([
                ['Status', 'Count', { role: 'style' }],
                ['Completed', <?php echo $task_stats['completed_tasks'] ?? 0; ?>, '#10b981'],
                ['In Progress', <?php echo $task_stats['in_progress_tasks'] ?? 0; ?>, '#4f46e5'],
                ['Overdue', <?php echo $task_stats['overdue_tasks'] ?? 0; ?>, '#ef4444']
            ]);

            var taskOptions = {
                ...commonOptions,
                legend: { position: 'none' },
                vAxis: {
                    minValue: 0,
                    format: '0',
                    gridlines: { color: '#f3f4f6' },
                    textStyle: { color: '#9ca3af' }
                },
                hAxis: {
                    textStyle: { color: '#6b7280' }
                }
            };

            var taskChart = new google.visualization.ColumnChart(document.getElementById('taskChart'));
            taskChart.draw(taskData, taskOptions);
        }

        // Redraw charts on window resize
        window.addEventListener('resize', drawCharts);
    </script>
</body>
</html>
