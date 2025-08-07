<?php
include '../includes/auth.php';
include '../includes/db_connect.php';
include '../includes/sidebar.php';

// Query to fetch tasks with their assigned employees and calculate overall progress
$query = "
    SELECT 
        t.*,
        e.full_name as assigned_by_name,
        GROUP_CONCAT(DISTINCT ae.full_name ORDER BY ae.full_name SEPARATOR ', ') as assigned_employees,
        COUNT(DISTINCT ta.employee_id) as total_assigned,
        COUNT(DISTINCT CASE WHEN tp.status = 'Completed' THEN tp.employee_id END) as completed_count,
        COUNT(DISTINCT CASE WHEN tp.status = 'In Progress' THEN tp.employee_id END) as in_progress_count,
        COUNT(DISTINCT CASE WHEN tp.status = 'Overdue' THEN tp.employee_id END) as overdue_count,
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN tp.status = 'Overdue' THEN tp.employee_id END) > 0 THEN 'Overdue'
            WHEN COUNT(DISTINCT ta.employee_id) = COUNT(DISTINCT CASE WHEN tp.status = 'Completed' THEN tp.employee_id END) 
                AND COUNT(DISTINCT ta.employee_id) > 0 THEN 'Completed'
            WHEN COUNT(DISTINCT CASE WHEN tp.status = 'In Progress' THEN tp.employee_id END) > 0 THEN 'In Progress'
            ELSE 'Pending'
        END as task_status,
        ROUND(
            CASE 
                WHEN COUNT(DISTINCT ta.employee_id) = 0 THEN 0
                ELSE (COUNT(DISTINCT CASE WHEN tp.status = 'Completed' THEN tp.employee_id END) * 100.0 / COUNT(DISTINCT ta.employee_id))
            END
        ) as completion_percentage
    FROM tasks t
    LEFT JOIN employees e ON t.assigned_by = e.employee_id
    LEFT JOIN task_assignments ta ON t.task_id = ta.task_id
    LEFT JOIN employees ae ON ta.employee_id = ae.employee_id
    LEFT JOIN task_progress tp ON t.task_id = tp.task_id AND ta.employee_id = tp.employee_id
    GROUP BY t.task_id, t.title, t.description, t.priority, t.due_date, t.status, t.created_at, t.updated_at, t.assigned_by, e.full_name
    ORDER BY 
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN tp.status = 'Overdue' THEN tp.employee_id END) > 0 THEN 1
            WHEN COUNT(DISTINCT CASE WHEN tp.status = 'In Progress' THEN tp.employee_id END) > 0 THEN 2
            WHEN COUNT(DISTINCT ta.employee_id) = COUNT(DISTINCT CASE WHEN tp.status = 'Completed' THEN tp.employee_id END) 
                AND COUNT(DISTINCT ta.employee_id) > 0 THEN 4
            ELSE 3
        END,
        CASE 
            WHEN t.due_date < CURDATE() THEN 1
            ELSE 2
        END,
        t.due_date ASC;
";

$stmt = $conn->prepare($query);
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

// Function to get priority badge class
function getPriorityClass($priority) {
    switch ($priority) {
        case "High": return "badge-danger";
        case "Medium": return "badge-warning";
        case "Low": return "badge-success";
        default: return "badge-secondary";
    }
}

// Function to get status icon
function getStatusIcon($status) {
    switch ($status) {
        case "Pending": return "fa-clock";
        case "In Progress": return "fa-spinner fa-spin";
        case "Completed": return "fa-check-circle";
        case "Overdue": return "fa-exclamation-circle";
        default: return "fa-question-circle";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Tasks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .task-card {
            transition: transform 0.2s;
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .task-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .task-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .assigned-to {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .badge {
            font-size: 0.85rem;
            padding: 5px 10px;
        }
        .priority-badge .badge {
            font-size: 0.9rem;
            font-weight: 600;
        }
        .priority-badge .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .priority-badge .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .priority-badge .badge-success {
            background-color: #28a745;
            color: white;
        }
        .status-badge {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .status-badge i {
            font-size: 1rem;
        }
        .progress {
            height: 8px;
            margin-top: 5px;
            border-radius: 4px;
            background-color: #e9ecef;
        }
        .progress-bar {
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .task-progress {
            margin: 15px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .progress-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 0.85rem;
        }
        .progress-stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>All Tasks</h2>
                <a href="assign_task.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Task
                </a>
            </div>

            <div class="row">
                <?php while ($task = $result->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card task-card">
                            <div class="card-body">
                                <div class="task-header">
                                    <div class="d-flex justify-content-between align-items-start w-100">
                                        <div>
                                            <h5 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                            <div class="priority-badge mb-2">
                                                <span class="badge <?php echo getPriorityClass($task['priority']); ?> p-2">
                                                    <i class="fas fa-<?php 
                                                        echo $task['priority'] === 'High' ? 'exclamation-triangle' : 
                                                            ($task['priority'] === 'Medium' ? 'exclamation-circle' : 'info-circle'); 
                                                    ?>"></i> 
                                                    <?php echo htmlspecialchars($task['priority']); ?> Priority
                                                </span>
                                            </div>
                                        </div>
                                        <span class="badge <?php echo getStatusClass($task['task_status']); ?> status-badge">
                                            <i class="fas <?php echo getStatusIcon($task['task_status']); ?>"></i>
                                            <?php echo htmlspecialchars($task['task_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                
                                <div class="task-meta">
                                    <div class="mb-2">
                                        <strong>Due Date:</strong>
                                        <?php 
                                        $due_date = strtotime($task['due_date']);
                                        $today = strtotime('today');
                                        $is_overdue = $due_date < $today;
                                        ?>
                                        <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                            <?php echo date('d M Y', $due_date); ?>
                                            <?php if ($is_overdue): ?>
                                                <i class="fas fa-exclamation-circle text-danger"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Created By:</strong>
                                        <?php echo htmlspecialchars($task['assigned_by_name']); ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Created On:</strong>
                                        <?php echo date('d M Y', strtotime($task['created_at'])); ?>
                                    </div>
                                </div>

                                <?php if (!empty($task['assigned_employees'])): ?>
                                    <div class="assigned-to">
                                        <strong>Assigned To:</strong>
                                        <p class="mb-0"><?php echo htmlspecialchars($task['assigned_employees']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="task-progress">
                                    <div class="progress-stats">
                                        <div class="progress-stat-item">
                                            <i class="fas fa-check-circle text-success"></i>
                                            <span>Completed: <?php echo $task['completed_count']; ?></span>
                                        </div>
                                        <div class="progress-stat-item">
                                            <i class="fas fa-spinner text-info"></i>
                                            <span>In Progress: <?php echo $task['in_progress_count']; ?></span>
                                        </div>
                                        <?php if ($task['overdue_count'] > 0): ?>
                                        <div class="progress-stat-item">
                                            <i class="fas fa-exclamation-circle text-danger"></i>
                                            <span>Overdue: <?php echo $task['overdue_count']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php 
                                            echo $task['completion_percentage'] == 100 ? 'success' : 
                                                ($task['completion_percentage'] >= 50 ? 'info' : 'warning'); 
                                        ?>" 
                                            role="progressbar" 
                                            style="width: <?php echo $task['completion_percentage']; ?>%" 
                                            aria-valuenow="<?php echo $task['completion_percentage']; ?>" 
                                            aria-valuemin="0" 
                                            aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="progress-info">
                                        <span>Overall Progress</span>
                                        <span><?php echo $task['completion_percentage']; ?>%</span>
                                    </div>
                                </div>

                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">
                                            <?php echo date('d M Y', strtotime($task['created_at'])); ?>
                                        </span>
                                        <a href="view_task_details.php?task_id=<?php echo $task['task_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
