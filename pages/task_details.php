<?php
ob_start();
include '../includes/auth.php';
include '../includes/db_connect.php';
include '../includes/sidebar.php';

$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

// Verify the task belongs to the user (check task_assignments table)
$verify_query = "
    SELECT t.*, e.full_name as assigned_by_name
    FROM tasks t
    LEFT JOIN task_assignments ta ON t.task_id = ta.task_id
    LEFT JOIN employees e ON t.assigned_by = e.employee_id
    WHERE t.task_id = ? AND (ta.employee_id = ? OR t.assigned_by = ?)
";

$stmt = $conn->prepare($verify_query);
$stmt->bind_param("iii", $task_id, $user_id, $user_id);
$stmt->execute();
$task_result = $stmt->get_result();

if ($task_result->num_rows === 0) {
    // Task not found or not assigned to this user
    header("Location: my_tasks.php");
    exit;
}

$task = $task_result->fetch_assoc();

// Get assigned employees for this task
$assigned_employees_query = "
    SELECT e.employee_id, e.full_name, e.email
    FROM task_assignments ta
    JOIN employees e ON ta.employee_id = e.employee_id
    WHERE ta.task_id = ?
    ORDER BY e.full_name
";

$stmt = $conn->prepare($assigned_employees_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$assigned_employees = $stmt->get_result();

// Get task progress history
$progress_query = "
    SELECT 
        tp.*,
        e.full_name as employee_name,
        GROUP_CONCAT(tl.link_url, '|', tl.link_description SEPARATOR '||') as links
    FROM task_progress tp
    LEFT JOIN employees e ON tp.employee_id = e.employee_id
    LEFT JOIN task_links tl ON tp.progress_id = tl.progress_id
    WHERE tp.task_id = ?
    GROUP BY tp.progress_id
    ORDER BY tp.update_date DESC, tp.created_at DESC
";
$stmt = $conn->prepare($progress_query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$progress_result = $stmt->get_result();

// Handle form submission for task update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    $status = $_POST['status'];
    $description = $_POST['description'];
    $update_date = date('Y-m-d');
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert progress update
        $insert_progress = "
            INSERT INTO task_progress (task_id, employee_id, update_date, status, description)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($insert_progress);
        $stmt->bind_param("iisss", $task_id, $user_id, $update_date, $status, $description);
        $stmt->execute();
        $progress_id = $conn->insert_id;
        
        // Update task status
        $update_task = "
            UPDATE tasks 
            SET status = ?, updated_at = NOW()
            WHERE task_id = ?
        ";
        $stmt = $conn->prepare($update_task);
        $stmt->bind_param("si", $status, $task_id);
        $stmt->execute();
        
        // Handle links if provided
        if (isset($_POST['links']) && is_array($_POST['links'])) {
            $insert_link = "
                INSERT INTO task_links (progress_id, link_url, link_description)
                VALUES (?, ?, ?)
            ";
            $stmt = $conn->prepare($insert_link);
            
            foreach ($_POST['links'] as $index => $link) {
                if (!empty($link)) {
                    $description = isset($_POST['link_descriptions'][$index]) ? $_POST['link_descriptions'][$index] : '';
                    $stmt->bind_param("iss", $progress_id, $link, $description);
                    $stmt->execute();
                }
            }
        }
        
        $conn->commit();
        
        // Redirect to refresh the page
        header("Location: task_details.php?task_id=$task_id&updated=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating task: " . $e->getMessage();
    }
}

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

// Get links for each progress update
function getProgressLinks($conn, $progress_id) {
    $links_query = "
        SELECT * FROM task_links
        WHERE progress_id = ?
        ORDER BY created_at ASC
    ";
    $stmt = $conn->prepare($links_query);
    $stmt->bind_param("i", $progress_id);
    $stmt->execute();
    return $stmt->get_result();
}
ob_end_flush();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details</title>
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
        .card {
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: 600;
            border-radius: 12px 12px 0 0 !important;
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
        .link-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
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
        <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Task progress updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Task Details</h5>
                <span class="badge <?php echo getStatusClass($task['status']); ?>"><?php echo htmlspecialchars($task['status']); ?></span>
            </div>
            <div class="card-body">
                <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <p><strong>Priority:</strong> 
                            <span class="badge 
                                <?php echo ($task['priority'] == 'High') ? 'priority-high' : 
                                    (($task['priority'] == 'Medium') ? 'priority-medium' : 'priority-low'); ?>">
                                <?php echo htmlspecialchars($task['priority']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Due Date:</strong> <?php echo date("d M Y", strtotime($task['due_date'])); ?></p>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>Description:</h6>
                    <p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Update Task Progress</h5>
            </div>
            <div class="card-body">
                <div id="alert-container"></div>
                <form method="post" action="" id="task-progress-form" data-task-id="<?php echo $task_id; ?>">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Pending" <?php echo ($task['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo ($task['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo ($task['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Overdue" <?php echo ($task['status'] == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Progress Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required placeholder="Describe what you've accomplished or any challenges you're facing..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Add Links (Optional)</label>
                        <div id="links-container">
                            <div class="row mb-2 link-row">
                                <div class="col-md-6">
                                    <input type="url" class="form-control" name="links[]" placeholder="https://example.com">
                                </div>
                                <div class="col-md-5">
                                    <input type="text" class="form-control" name="link_descriptions[]" placeholder="Link description">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger remove-link"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="add-link">+ Add Another Link</button>
                    </div>
                    
                    <button type="submit" name="update_task" class="btn btn-primary">Update Progress</button>
                </form>
            </div>
        </div>
        
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add link functionality
            document.getElementById('add-link').addEventListener('click', function() {
                const linksContainer = document.getElementById('links-container');
                const newRow = document.createElement('div');
                newRow.className = 'row mb-2 link-row';
                newRow.innerHTML = `
                    <div class="col-md-6">
                        <input type="url" class="form-control" name="links[]" placeholder="https://example.com">
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="link_descriptions[]" placeholder="Link description">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger remove-link"><i class="fas fa-times"></i></button>
                    </div>
                `;
                linksContainer.appendChild(newRow);
                
                // Add event listener to the new remove button
                newRow.querySelector('.remove-link').addEventListener('click', function() {
                    linksContainer.removeChild(newRow);
                });
            });
            
            // Remove link functionality for existing buttons
            document.querySelectorAll('.remove-link').forEach(button => {
                button.addEventListener('click', function() {
                    const row = this.closest('.link-row');
                    row.parentNode.removeChild(row);
                });
            });
        });
    </script>
    <script src="../assets/js/task_progress.js"></script>
</body>
</html>