<?php
include '../includes/auth.php';
include '../includes/db_connect.php';
include '../includes/sidebar.php';

// Fetch active policies
$sql = "SELECT policy_id, title, category, version, updated_at, file_path
        FROM company_policies
        WHERE is_active = 1
        ORDER BY category ASC, title ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Policies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 260px; /* Matches sidebar width */
            padding: 20px;
        }
        .policy-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .category-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
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
                <h2><i class="fas fa-book-open me-2"></i>Company Policies</h2>
                <!-- Admin link could go here later -->
            </div>

            <div class="policy-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Version</th>
                                <th>Last Updated</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-primary">
                                                <?php echo htmlspecialchars($row['title']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary category-badge">
                                                <?php echo htmlspecialchars($row['category']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                v<?php echo htmlspecialchars($row['version']); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            <?php echo date('d M Y', strtotime($row['updated_at'])); ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="policy_view.php?id=<?php echo $row['policy_id']; ?>" class="btn btn-sm btn-info text-white me-1" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>

                                            <?php if (!empty($row['file_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($row['file_path']); ?>" class="btn btn-sm btn-primary" download title="Download Document">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="No file attached">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-folder-open fa-2x mb-3"></i>
                                        <p class="mb-0">No active policies found.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>