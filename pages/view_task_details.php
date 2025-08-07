<?php
include '../includes/db_connect.php';
include '../includes/sidebar.php';

// Get task ID from URL
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

// Query to get task details
$query = "
    SELECT 
        t.*,
        e.full_name as assigned_by_name,
        GROUP_CONCAT(DISTINCT ae.full_name ORDER BY ae.full_name SEPARATOR ', ') as assigned_employees,
        GROUP_CONCAT(DISTINCT ae.employee_id ORDER BY ae.full_name SEPARATOR ',') as assigned_employee_ids
    FROM tasks t
    LEFT JOIN employees e ON t.assigned_by = e.employee_id
    LEFT JOIN task_assignments ta ON t.task_id = ta.task_id
    LEFT JOIN employees ae ON ta.employee_id = ae.employee_id
    WHERE t.task_id = ?
    GROUP BY t.task_id
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: view_tasks.php");
    exit;
}

$task = $result->fetch_assoc();

// Get task progress history
$progress_query = "
    SELECT 
        tp.*,
        e.full_name as employee_name,
        GROUP_CONCAT(
            CONCAT(tl.link_url, '|', COALESCE(tl.link_description, ''))
            SEPARATOR '||'
        ) as links
    FROM task_progress tp
    JOIN employees e ON tp.employee_id = e.employee_id
    LEFT JOIN task_links tl ON tp.progress_id = tl.progress_id
    WHERE tp.task_id = ?
    GROUP BY tp.progress_id
    ORDER BY tp.update_date DESC, tp.created_at DESC
";

$stmt = $conn->prepare($progress_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$progress_result = $stmt->get_result();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - <?php echo htmlspecialchars($task['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .task-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .task-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline-item {
            position: relative;
            padding-left: 40px;
            margin-bottom: 25px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #ddd;
        }
        .timeline-item:last-child:before {
            bottom: 50%;
        }
        .timeline-badge {
            position: absolute;
            left: 0;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #007bff;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #007bff;
        }
        .timeline-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .timeline-content {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .task-links {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .task-link {
            display: block;
            margin-bottom: 5px;
        }
        .badge {
            font-size: 0.85rem;
            padding: 5px 10px;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Task Details</h2>
                <a href="view_tasks.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Tasks
                </a>
            </div>

            <!-- Task Header Section -->
            <div class="task-header">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h3><?php echo htmlspecialchars($task['title']); ?></h3>
                    <span class="badge <?php echo getStatusClass($task['status']); ?>">
                        <?php echo htmlspecialchars($task['status']); ?>
                    </span>
                </div>
                
                <div class="task-meta">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Priority:</strong> 
                                <span class="badge <?php echo getPriorityClass($task['priority']); ?>">
                                    <?php echo htmlspecialchars($task['priority']); ?>
                                </span>
                            </p>
                            <p><strong>Due Date:</strong> <?php echo date('d M Y', strtotime($task['due_date'])); ?></p>
                            <p><strong>Created By:</strong> <?php echo htmlspecialchars($task['assigned_by_name']); ?></p>
                            <p><strong>Created On:</strong> <?php echo date('d M Y', strtotime($task['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if (!empty($task['assigned_employees'])): ?>
                                <p><strong>Assigned To:</strong></p>
                                <ul class="list-unstyled">
                                    <?php 
                                    $assigned_employees = explode(', ', $task['assigned_employees']);
                                    $assigned_ids = explode(',', $task['assigned_employee_ids']);
                                    foreach ($assigned_employees as $index => $employee): 
                                    ?>
                                        <li>
                                            <i class="fas fa-user"></i> 
                                            <?php echo htmlspecialchars($employee); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Description -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Description</h5>
                </div>
                <div class="card-body">
                    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                </div>
            </div>

            <!-- Progress Timeline -->
            <div class="info-card">
                <h4 class="section-title">Progress Timeline</h4>
                <div class="timeline">
                    <?php if ($progress_result->num_rows === 0): ?>
                        <p class="text-muted">No progress updates yet.</p>
                    <?php else: ?>
                        <?php while ($progress = $progress_result->fetch_assoc()): ?>
                            <div class="timeline-item">
                                <div class="timeline-badge"></div>
                                <div class="timeline-date">
                                    <?php echo date('d M Y', strtotime($progress['update_date'])); ?>
                                    <small class="text-muted d-block">
                                        <?php echo date('h:i A', strtotime($progress['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($progress['employee_name']); ?></h6>
                                        <span class="badge bg-<?php 
                                            echo $progress['status'] === 'Completed' ? 'success' : 
                                                ($progress['status'] === 'In Progress' ? 'info' : 
                                                ($progress['status'] === 'Overdue' ? 'danger' : 'warning')); 
                                        ?>">
                                            <?php echo htmlspecialchars($progress['status']); ?>
                                        </span>
                                    </div>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($progress['description'])); ?></p>
                                    
                                    <?php if (!empty($progress['links'])): ?>
                                        <div class="mt-2">
                                            <?php 
                                            $links = explode('||', $progress['links']);
                                            foreach ($links as $link):
                                                list($url, $description) = explode('|', $link);
                                            ?>
                                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                                    <i class="fas fa-link"></i> <?php echo htmlspecialchars($description); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 