<?php
session_start();
include '../includes/db_connect.php';
include '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = '/hr-portal/pages/login.php';</script>";
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

// Get attendance records for the user
$attendance_query = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$attendance_result = $stmt->get_result();

// Calculate attendance statistics
$total_days = 0;
$present_days = 0;
$absent_days = 0;
$late_days = 0;
$leave_days = 0;

while ($record = $attendance_result->fetch_assoc()) {
    $total_days++;
    
    // Check if remarks contain "late" (case insensitive)
    if (isset($record['remarks']) && stripos($record['remarks'], 'late') !== false) {
        $late_days++;
    } else {
        switch ($record['status']) {
            case 'Present':
                $present_days++;
                break;
            case 'Absent':
                $absent_days++;
                break;
            case 'Leave':
                $leave_days++;
                break;
        }
    }
}

// Reset pointer for display
$attendance_result->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - HR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .attendance-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .present { color: #28a745; }
        .absent { color: #dc3545; }
        .late { color: #ffc107; }
        .half-day { color: #17a2b8; }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <h2 class="mb-4">My Attendance</h2>
                    
                    <!-- Attendance Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon present">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h4><?php echo $present_days; ?></h4>
                                <p class="text-muted">Present Days</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon absent">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <h4><?php echo $absent_days; ?></h4>
                                <p class="text-muted">Absent Days</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon late">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h4><?php echo $late_days; ?></h4>
                                <p class="text-muted">Late Days</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon half-day">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <h4><?php echo $leave_days; ?></h4>
                                <p class="text-muted">Leave Days</p>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Records -->
                    <div class="attendance-card">
                        <h4 class="mb-4">Attendance History</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Total Hours</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($attendance_result->num_rows === 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No attendance records found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($record = $attendance_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        if (isset($record['remarks']) && stripos($record['remarks'], 'late') !== false) {
                                                            echo 'warning';
                                                            $status = 'Late';
                                                        } else {
                                                            echo $record['status'] === 'Present' ? 'success' : 
                                                                ($record['status'] === 'Absent' ? 'danger' : 'info');
                                                            $status = $record['status'];
                                                        }
                                                    ?>">
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo isset($record['check_in']) && !empty($record['check_in']) ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                                                <td><?php echo isset($record['check_out']) && !empty($record['check_out']) ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                                <td>
                                                    <?php
                                                    if (isset($record['check_in']) && isset($record['check_out']) && 
                                                        !empty($record['check_in']) && !empty($record['check_out'])) {
                                                        $check_in = strtotime($record['check_in']);
                                                        $check_out = strtotime($record['check_out']);
                                                        $diff = $check_out - $check_in;
                                                        echo floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo isset($record['remarks']) ? htmlspecialchars($record['remarks']) : '-'; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 