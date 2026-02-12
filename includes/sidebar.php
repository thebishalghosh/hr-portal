<?php
// Include the database connection file
include 'db_connect.php';
include 'auth.php';

// Count pending leaves for admin badge
$pending_leaves_count = 0;
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
    $count_query = "SELECT COUNT(*) as count FROM leaves WHERE status = 'Pending'";
    $count_result = $conn->query($count_query);
    if ($count_result) {
        $row = $count_result->fetch_assoc();
        $pending_leaves_count = $row['count'];
    }
}

// Determine Environment and Set Portal URLs
$host = $_SERVER['HTTP_HOST'];
$is_local = ($host === 'localhost' || $host === '127.0.0.1');

$exam_portal_url = $is_local
    ? 'http://localhost/exam/login/sso'
    : 'https://exam.travarsa.net/login/sso';

$interview_portal_url = $is_local
    ? 'http://localhost/interview-management/'
    : 'https://interview.travarsa.net';
?>

<!-- Add favicon -->
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white' font-family='Arial'>👤</text></svg>">

<!-- Hamburger button for mobile -->
<button class="btn btn-primary d-lg-none m-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Offcanvas for mobile, fixed for desktop -->
<div class="sidebar offcanvas offcanvas-start offcanvas-lg" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <!-- Close button for offcanvas (mobile only) -->
    <button type="button" class="btn-close text-reset d-lg-none m-2 ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    <!-- Company Logo -->
    <div class="logo text-center">
        <img src="https://travarsa.com/wp-content/uploads/2023/03/Travarsa-Logo.png" alt="Company Logo">
    </div>
    <!-- Sidebar Navigation -->
    <div class="offcanvas-body">
        <div class="nav flex-column">
            <a href="/hr-portal/dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="/hr-portal/pages/profile.php" class="nav-link">
                <i class="fas fa-user-circle"></i> Profile
            </a>
            <?php
            if ($_SESSION['user_role'] == 'admin') {
                echo '<a href="/hr-portal/pages/candidate.php" class="nav-link">
                        <i class="fas fa-users"></i> Candidate
                    </a>';
            }
            ?>
            <?php
            if ($_SESSION['user_role'] == 'admin') {
                echo '<a href="/hr-portal/pages/attendance.php" class="nav-link">
                        <i class="fas fa-calendar-check"></i> Attendance
                    </a>';
            } else {
                echo '<a href="/hr-portal/pages/my_attendance.php" class="nav-link">
                        <i class="fas fa-calendar-check"></i> My Attendance
                    </a>';
            }
            ?>
            <?php
            if ($_SESSION['user_role'] == 'admin') {
                echo '<div class="dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="tasksDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-tasks"></i> Tasks
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="tasksDropdown">
                            <li><a class="dropdown-item" href="/hr-portal/pages/assign_task.php">Assign Task</a></li>
                            <li><a class="dropdown-item" href="/hr-portal/pages/view_tasks.php">View All Tasks</a></li>
                            <li><a class="dropdown-item" href="/hr-portal/pages/my_tasks.php">My Tasks</a></li>
                            </ul>
                        </div>';
            } else {
                echo '<a class="nav-link" href="/hr-portal/pages/my_tasks.php"><i class="fas fa-tasks"></i>My Tasks</a>';
            }
            ?>

            <!-- Policies Dropdown -->
            <div class="dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="policiesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-book-open"></i> Policies
                </a>
                <ul class="dropdown-menu" aria-labelledby="policiesDropdown">
                    <li><a class="dropdown-item" href="/hr-portal/pages/policies.php">Company Policies</a></li>
                    <?php if ($_SESSION['user_role'] == 'admin'): ?>
                        <li><a class="dropdown-item" href="/hr-portal/pages/manage_policies.php">Manage Policies</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php
            if ($_SESSION['user_role'] == 'admin') {
                echo '<a href="/hr-portal/pages/performance_review.php" class="nav-link">
                        <i class="fas fa-star"></i> Performance Reviews
                    </a>';
            }
            ?>
            <?php if ($_SESSION['user_role'] == 'admin'): ?>
            <div class="dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-chart-line"></i> Reports
                </a>
                <ul class="dropdown-menu" aria-labelledby="reportsDropdown">
                    <li><a class="dropdown-item" href="/hr-portal/pages/attendance_report.php">Attendance Report</a></li>
                    <li><a class="dropdown-item" href="/hr-portal/pages/performance_report.php">Performance Report</a></li>
                    <li><a class="dropdown-item" href="/hr-portal/pages/view_tasks.php">Task Report</a></li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($_SESSION['user_role'] == 'admin'): ?>
                <a href="<?php echo $exam_portal_url; ?>?session_token=<?php echo isset($_SESSION['session_token']) ? htmlspecialchars($_SESSION['session_token']) : ''; ?>" class="nav-link" target="_blank">
                    <i class="fas fa-graduation-cap"></i> Exam Portal
                </a>
                <a href="<?php echo $interview_portal_url; ?>" class="nav-link" target="_blank">
                    <i class="fas fa-user-tie"></i> Interview Portal
                </a>
            <?php endif; ?>

            <div class="dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center justify-content-between" href="#" id="leaveDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <span><i class="fas fa-calendar-check"></i> Leaves</span>
                    <?php if ($pending_leaves_count > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size: 0.7rem;"><?php echo $pending_leaves_count; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu" aria-labelledby="leaveDropdown">
                    <li><a class="dropdown-item" href="/hr-portal/pages/request_leave.php">Request Leave</a></li>
                    <li>
                        <a class="dropdown-item d-flex justify-content-between align-items-center" href="/hr-portal/pages/view_leave.php">
                            View Leaves
                            <?php if ($pending_leaves_count > 0): ?>
                                <span class="badge bg-danger rounded-pill" style="font-size: 0.6rem;"><?php echo $pending_leaves_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- Logout Button -->
            <div class="logout-btn">
                <a href="/hr-portal/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>
