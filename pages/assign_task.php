<?php
include '../includes/db_connect.php';
include '../includes/sidebar.php';

// Fetch employees with their department information
$employees = $conn->query("SELECT employee_id, full_name, department FROM employees WHERE status = 'Active'");

// Group employees by department
$departments = [];
while ($row = $employees->fetch_assoc()) {
    $departments[$row['department']][] = $row;
}

$success = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    $due_date = $_POST['due_date'];
    $assigned_by = $_SESSION['user_id']; 
    $assigned_to = isset($_POST['assigned_to']) ? $_POST['assigned_to'] : [];

    // Begin transaction to ensure atomicity
    $conn->begin_transaction();
    
    try {
        // Insert the task into the tasks table
        $stmt = $conn->prepare("INSERT INTO tasks (title, description, priority, due_date, assigned_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $title, $description, $priority, $due_date, $assigned_by);
        $stmt->execute();
        $task_id = $stmt->insert_id; // Get the last inserted task_id
        $stmt->close();

        // Insert into task_assignments table
        if (!empty($assigned_to)) {
            foreach ($assigned_to as $employee_id) {
                $stmt = $conn->prepare("INSERT INTO task_assignments (task_id, employee_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $task_id, $employee_id);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Commit the transaction
        $conn->commit();
        $success = true;
    } catch (Exception $e) {
        // Rollback in case of error
        $conn->rollback();
        error_log("Error assigning task: " . $e->getMessage());
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Assign Task</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.0/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --accent-color: #2e59d9;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .task-content-wrapper {
            margin-left: 250px;
            padding: 30px;
            transition: all 0.3s;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.5rem;
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
        }
        
        .card-title {
            color: #5a5c69;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d3e2;
            font-size: 0.9rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        textarea.form-control {
            min-height: 120px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #858796;
            border-color: #858796;
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            margin-bottom: 10px;
            border-radius: 6px;
        }
        
        .btn-secondary:hover {
            background-color: #717384;
            border-color: #717384;
        }
        
        .department-card {
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e3e6f0;
            background-color: white;
        }
        
        .department-header {
            background-color: #f8f9fc;
            padding: 12px 15px;
            border-bottom: 1px solid #e3e6f0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .department-body {
            padding: 15px;
        }
        
        .employee-checkbox {
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .employee-checkbox:hover {
            background-color: #f8f9fc;
        }
        
        .form-check-input {
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        
        .form-check-label {
            cursor: pointer;
            padding-left: 5px;
            font-size: 0.95rem;
        }
        
        .priority-badge {
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .priority-low {
            background-color: #edf7ed;
            color: #2e7d32;
        }
        
        .priority-medium {
            background-color: #fff4e5;
            color: #ed6c02;
        }
        
        .priority-high {
            background-color: #fdeded;
            color: #d32f2f;
        }
        
        .form-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0.5rem;
        }
        
        .required-field::after {
            content: " *";
            color: #e74a3b;
        }
        
        @media (max-width: 768px) {
            .task-content-wrapper {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="task-content-wrapper">
        <div class="container-fluid">
            <h1 class="h3 mb-4 text-gray-800">Task Management</h1>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-tasks me-2"></i>Assign New Task</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="taskForm">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required-field">Task Title</label>
                                    <input type="text" name="title" class="form-control" required placeholder="Enter task title">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required-field">Description</label>
                                    <textarea name="description" class="form-control" required placeholder="Describe the task details, objectives, and any specific instructions"></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label required-field">Priority</label>
                                    <select name="priority" class="form-select">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                    <div class="mt-2 d-flex gap-2">
                                        <span class="priority-badge priority-low">Low</span>
                                        <span class="priority-badge priority-medium">Medium</span>
                                        <span class="priority-badge priority-high">High</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required-field">Due Date</label>
                                    <input type="date" name="due_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label required-field">Assign to</label>
                            <p class="text-muted small">Select employees who will be responsible for completing this task</p>
                            
                            <div class="row">
                                <?php foreach ($departments as $department => $employeesInDept): ?>
                                <div class="col-md-4">
                                    <div class="department-card">
                                        <div class="department-header">
                                            <h6 class="mb-0"><i class="fas fa-users me-2"></i><?php echo $department; ?></h6>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll('<?php echo $department; ?>')">
                                                <i class="fas fa-check-double me-1"></i>Select All
                                            </button>
                                        </div>
                                        <div class="department-body">
                                            <?php foreach ($employeesInDept as $employee): ?>
                                            <div class="employee-checkbox">
                                                <input class="form-check-input" type="checkbox" name="assigned_to[]" 
                                                    value="<?php echo $employee['employee_id']; ?>" 
                                                    id="employee-<?php echo $employee['employee_id']; ?>" 
                                                    data-department="<?php echo $department; ?>">
                                                <label class="form-check-label" for="employee-<?php echo $employee['employee_id']; ?>">
                                                    <?php echo $employee['full_name']; ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="resetForm()">
                                <i class="fas fa-undo me-1"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i>Assign Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.0/dist/sweetalert2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function selectAll(department) {
            const checkboxes = document.querySelectorAll(`input[type="checkbox"][data-department="${department}"]`);
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }
        
        function resetForm() {
            document.getElementById('taskForm').reset();
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
                <?php if ($success): ?>
                    Swal.fire({
                        title: 'Success!',
                        text: 'Task has been assigned successfully!',
                        icon: 'success',
                        confirmButtonColor: '#4e73df'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            resetForm();
                        }
                    });
                <?php else: ?>
                    Swal.fire({
                        title: 'Error!',
                        text: 'There was a problem assigning the task. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#4e73df'
                    });
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
