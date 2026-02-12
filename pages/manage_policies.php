<?php
include '../includes/auth.php';
include '../includes/db_connect.php';
include '../includes/sidebar.php';

// Check Admin Role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

// Handle Actions (Delete / Toggle Status)
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];

    if ($_GET['action'] === 'delete') {
        // Get file path to delete file
        $stmt = $conn->prepare("SELECT file_path FROM company_policies WHERE policy_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                unlink($row['file_path']);
            }
        }
        $stmt->close();

        // Delete record
        $stmt = $conn->prepare("DELETE FROM company_policies WHERE policy_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Policy deleted successfully.";
            $msg_type = "success";
        } else {
            $message = "Error deleting policy.";
            $msg_type = "danger";
        }
    } elseif ($_GET['action'] === 'toggle') {
        // Toggle Active Status
        $stmt = $conn->prepare("UPDATE company_policies SET is_active = NOT is_active WHERE policy_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Policy status updated.";
            $msg_type = "success";
        } else {
            $message = "Error updating status.";
            $msg_type = "danger";
        }
    }
}

// Fetch All Policies
$sql = "SELECT p.*, e.full_name as author_name
        FROM company_policies p
        LEFT JOIN employees e ON p.created_by = e.employee_id
        ORDER BY p.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Policies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
        }
        .status-active {
            color: #1cc88a;
            font-weight: bold;
        }
        .status-inactive {
            color: #e74a3b;
            font-weight: bold;
        }
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Manage Policies</h2>
                <a href="add_policy.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Policy
                </a>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Policies</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <a href="policy_view.php?id=<?php echo $row['policy_id']; ?>" class="text-decoration-none fw-bold">
                                                    <?php echo htmlspecialchars($row['title']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td><?php echo htmlspecialchars($row['version']); ?></td>
                                            <td class="text-center">
                                                <?php if ($row['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['author_name'] ?? 'Unknown'); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="edit_policy.php?id=<?php echo $row['policy_id']; ?>" class="btn btn-sm btn-warning text-white" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="manage_policies.php?action=toggle&id=<?php echo $row['policy_id']; ?>" class="btn btn-sm btn-secondary" title="Toggle Status">
                                                        <i class="fas fa-power-off"></i>
                                                    </a>
                                                    <a href="manage_policies.php?action=delete&id=<?php echo $row['policy_id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this policy? This cannot be undone.');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No policies found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>