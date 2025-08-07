<?php
include '../includes/auth.php';
include '../includes/db_connect.php';
include '../includes/sidebar.php';

$user_id = $_SESSION['user_id'];

// Query to get tasks assigned to the logged-in user
$query = "
    SELECT DISTINCT t.task_id, t.title, t.description, t.priority, t.due_date, t.status,
           DATEDIFF(t.due_date, CURDATE()) as days_remaining,
           e.full_name as assigned_by_name
    FROM tasks t
    LEFT JOIN task_assignments ta ON t.task_id = ta.task_id
    LEFT JOIN employees e ON t.assigned_by = e.employee_id
    WHERE ta.employee_id = ? OR t.assigned_by = ?
    GROUP BY t.task_id
    ORDER BY 
        CASE 
            WHEN t.status = 'Overdue' THEN 1
            WHEN t.status = 'In Progress' THEN 2
            WHEN t.status = 'Pending' THEN 3
            WHEN t.status = 'Completed' THEN 4
        END,
        CASE 
            WHEN t.due_date < CURDATE() THEN 1
            ELSE 2
        END,
        t.due_date ASC;
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
// Function to get status badge class
function getStatusClass($status) {
    switch ($status) {
        case "Pending": return "badge-warning";
        case "In Progress": return "badge-info";
        case "Completed": return "badge-success";
        case "Overdue": return "badge-danger";
        default: return "badge-secondary";
    }
}

// Function to get days remaining badge class
function getDaysRemainingClass($days, $status) {
    if ($status == 'Completed') return "text-success";
    if ($days < 0) return "text-danger";
    if ($days <= 2) return "text-warning";
    return "text-info";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assigned Tasks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        body {
            background-color: #f4f7fc;
            font-family: 'Poppins', sans-serif;
        }
        .main-content {
            margin-left: 260px; /* Adjust for sidebar */
            padding: 30px;
        }
        .task-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
        }
        h2 {
            font-weight: 600;
            color: #343a40;
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        .table th {
            background-color: #007bff !important;
            color: white;
            text-align: center;
            font-size: 16px;
        }
        .table td {
            vertical-align: middle;
            font-size: 15px;
        }
        /* Priority Badges */
        .priority-high {
            background-color: #dc3545 !important;
            color: white;
        }
        .priority-medium {
            background-color: #fd7e14 !important;
            color: white;
        }
        .priority-low {
            background-color: #28a745 !important;
            color: white;
        }
        /* Status Badges */
        .badge-warning {
            background-color: #ffc107 !important;
            color: black;
        }
        .badge-info {
            background-color: #17a2b8 !important;
            color: white;
        }
        .badge-success {
            background-color: #28a745 !important;
            color: white;
        }
        .badge-danger {
            background-color: #dc3545 !important;
            color: white;
        }
        /* Hover Effect */
        .table tbody tr:hover {
            background-color: #f1f3f5;
            transition: 0.3s;
            cursor: pointer;
        }
        .task-card {
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            padding: 12px 20px;
        }
        .card-body {
            padding: 20px;
        }
        .task-actions {
            display: flex;
            justify-content: flex-end;
        }
        .btn-view {
            background-color: #007bff;
            color: white;
        }
        .btn-view:hover {
            background-color: #0069d9;
            color: white;
        }
        .days-remaining {
            font-weight: 600;
        }
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="task-container">
            <h2 class="mb-4">📋 My Assigned Tasks</h2>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $result->num_rows; ?></h3>
                            <p class="mb-0">Total Tasks</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h3>
                                <?php 
                                $pending_count = 0;
                                $result->data_seek(0);
                                while ($task = $result->fetch_assoc()) {
                                    if ($task['status'] == 'Pending') $pending_count++;
                                }
                                echo $pending_count;
                                $result->data_seek(0);
                                ?>
                            </h3>
                            <p class="mb-0">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3>
                                <?php 
                                $in_progress_count = 0;
                                $result->data_seek(0);
                                while ($task = $result->fetch_assoc()) {
                                    if ($task['status'] == 'In Progress') $in_progress_count++;
                                }
                                echo $in_progress_count;
                                $result->data_seek(0);
                                ?>
                            </h3>
                            <p class="mb-0">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3>
                                <?php 
                                $completed_count = 0;
                                $result->data_seek(0);
                                while ($task = $result->fetch_assoc()) {
                                    if ($task['status'] == 'Completed') $completed_count++;
                                }
                                echo $completed_count;
                                $result->data_seek(0);
                                ?>
                            </h3>
                            <p class="mb-0">Completed</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <?php if ($result->num_rows === 0): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            You don't have any assigned tasks at the moment.
                        </div>
                    </div>
                <?php else: ?>
                    <?php while ($task = $result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card task-card">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-truncate" title="<?php echo htmlspecialchars($task['title']); ?>">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                    </h5>
                                    <span class="badge <?php echo getStatusClass($task['status']); ?>">
                                        <?php echo htmlspecialchars($task['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <p class="text-muted mb-2">
                                            <strong>Priority:</strong> 
                                            <span class="badge 
                                                <?php echo ($task['priority'] == 'High') ? 'priority-high' : 
                                                    (($task['priority'] == 'Medium') ? 'priority-medium' : 'priority-low'); ?>">
                                                <?php echo htmlspecialchars($task['priority']); ?>
                                            </span>
                                        </p>
                                        <p class="text-muted mb-2">
                                            <strong>Due Date:</strong> <?php echo date("d M Y", strtotime($task['due_date'])); ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Time Remaining:</strong> 
                                            <span class="days-remaining <?php echo getDaysRemainingClass($task['days_remaining'], $task['status']); ?>">
                                                <?php 
                                                if ($task['status'] == 'Completed') {
                                                    echo "Completed";
                                                } else if ($task['days_remaining'] < 0) {
                                                    echo "Overdue by " . abs($task['days_remaining']) . " days";
                                                } else if ($task['days_remaining'] == 0) {
                                                    echo "Due today";
                                                } else {
                                                    echo $task['days_remaining'] . " days left";
                                                }
                                                ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="task-description mb-3">
                                        <p class="text-truncate" title="<?php echo htmlspecialchars($task['description']); ?>">
                                            <?php echo htmlspecialchars(substr($task['description'], 0, 100)) . (strlen($task['description']) > 100 ? '...' : ''); ?>
                                        </p>
                                        </div>
                                    <div class="task-actions">
                                        <a href="task_details.php?task_id=<?php echo $task['task_id']; ?>" class="btn btn-sm btn-view">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>