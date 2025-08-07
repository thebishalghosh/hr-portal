<?php
include '../includes/auth.php';

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /hr-portal/dashboard.php');
    exit();
}

// Include the database connection file
include '../includes/db_connect.php';

// Include the sidebar
include '../includes/sidebar.php';

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

// Fetch all departments for the filter dropdown
$dept_sql = "SELECT DISTINCT department FROM employees ORDER BY department";
$dept_result = $conn->query($dept_sql);
$departments = [];
while ($dept_row = $dept_result->fetch_assoc()) {
    $departments[] = $dept_row['department'];
}

// Fetch all roles for the filter dropdown
$role_sql = "SELECT DISTINCT role FROM employees ORDER BY role";
$role_result = $conn->query($role_sql);
$roles = [];
while ($role_row = $role_result->fetch_assoc()) {
    $roles[] = $role_row['role'];
}

// Build the SQL query with filters
$sql = "SELECT * FROM employees WHERE 1=1";

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR mobile_number LIKE '%$search%')";
}

if (!empty($department)) {
    $department = $conn->real_escape_string($department);
    $sql .= " AND department = '$department'";
}

if (!empty($status)) {
    $status = $conn->real_escape_string($status);
    $sql .= " AND status = '$status'";
}

if (!empty($role)) {
    $role = $conn->real_escape_string($role);
    $sql .= " AND role = '$role'";
}

$sql .= " ORDER BY full_name ASC";

// Execute the query
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        /* Ensure the content is not hidden by the sidebar */
        .content {
            margin-left: 250px;
            padding: 20px;
        }

        /* Simple, classy design */
        .page-title {
            font-family: 'Arial', sans-serif;
            color: #333;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .btn-add-employee {
            margin-bottom: 20px;
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn-add-employee:hover {
            background-color: #0056b3;
        }

        /* Card styling */
        .filter-card, .employee-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        /* Table styling */
        .table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #343a40;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
        }

        /* Status badge styling */
        .badge-active {
            background-color: #28a745;
        }

        .badge-inactive {
            background-color: #dc3545;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }

        /* Action button styling */
        .btn-action {
            margin: 0 3px;
            padding: 5px 10px;
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }

            .table {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <!-- Employees Content -->
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="page-title">
                <i class="fas fa-users me-2"></i>Employee Management
            </h4>
            <a href="add_candidate.php" class="btn btn-add-employee">
                <i class="fas fa-user-plus me-2"></i>Add Employee
            </a>
        </div>

        <!-- Filter Section -->
        <div class="card filter-card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter Options
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Name, Email, Phone" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                        <?php echo ($department == $dept) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo htmlspecialchars($r); ?>" 
                                        <?php echo ($role == $r) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Pending" <?php echo ($status == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" id="resetFilters">
                            <i class="fas fa-undo me-2"></i>Reset Filters
                        </button>
                        <a href="export_employees.php" class="btn btn-success ms-2">
                            <i class="fas fa-file-excel me-2"></i>Export to Excel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="card employee-card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Employee List
                    <span class="badge bg-primary ms-2"><?php echo $result->num_rows; ?> Employees</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="employeesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Mobile Number</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($employee = $result->fetch_assoc()) {
                                    // Determine status badge class
                                    $status_class = '';
                                    if ($employee['status'] == 'Active') {
                                        $status_class = 'badge-active';
                                    } elseif ($employee['status'] == 'Inactive') {
                                        $status_class = 'badge-inactive';
                                    } elseif ($employee['status'] == 'Pending') {
                                        $status_class = 'badge-pending';
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td>" . $employee['employee_id'] . "</td>";
                                    echo "<td>" . htmlspecialchars($employee['full_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($employee['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($employee['mobile_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars($employee['role']) . "</td>";
                                    echo "<td>" . htmlspecialchars($employee['department']) . "</td>";
                                    echo "<td><span class='badge " . $status_class . "'>" . $employee['status'] . "</span></td>";
                                    echo "<td>
                                        <a href='view_employee.php?id=" . $employee['employee_id'] . "' class='btn btn-sm btn-info btn-action' title='View Details'>
                                            <i class='fas fa-eye'></i>
                                        </a>
                                        <a href='edit_employee.php?id=" . $employee['employee_id'] . "' class='btn btn-sm btn-warning btn-action' title='Edit'>
                                            <i class='fas fa-edit'></i>
                                        </a>
                                        <button type='button' class='btn btn-sm btn-danger btn-action delete-employee' 
                                                data-id='" . $employee['employee_id'] . "' 
                                                data-name='" . htmlspecialchars($employee['full_name']) . "' title='Delete'>
                                            <i class='fas fa-trash'></i>
                                        </button>
                                    </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center'>No employees found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="employeeName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#employeesTable').DataTable({
                "pageLength": 25,
                "order": [[1, "asc"]],
                "responsive": true,
                "dom": 'lrtip', // Hide the default search box
                "language": {
                    "emptyTable": "No employees found",
                    "zeroRecords": "No matching employees found"
                }
            });
            
            // Reset filters button
            $('#resetFilters').click(function() {
                window.location.href = 'candidate.php';
            });
            
            // Delete employee confirmation
            $('.delete-employee').click(function() {
                var employeeId = $(this).data('id');
                var employeeName = $(this).data('name');
                $('#employeeName').text(employeeName);
                $('#confirmDelete').attr('href', 'delete_employee.php?id=' + employeeId);
                $('#deleteModal').modal('show');
            });
            
            // Apply filters when form inputs change
            $('#department, #status, #role').change(function() {
                $('#filterForm').submit();
            });
        });
    </script>
</body>
</html>

<?php
// Close the connection
$conn->close();
?>