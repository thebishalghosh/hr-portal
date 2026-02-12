<?php
include '../includes/auth.php';
include '../includes/db_connect.php';
include '../includes/sidebar.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: policies.php');
    exit();
}

$policy_id = $_GET['id'];

// Fetch policy details
$sql = "SELECT p.*, e.full_name as author_name
        FROM company_policies p
        LEFT JOIN employees e ON p.created_by = e.employee_id
        WHERE p.policy_id = ? AND p.is_active = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $policy_id);
$stmt->execute();
$result = $stmt->get_result();
$policy = $result->fetch_assoc();

// Redirect if not found or inactive
if (!$policy) {
    echo "<script>alert('Policy not found or inactive.'); window.location.href='policies.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Policy - <?php echo htmlspecialchars($policy['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .policy-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 5px solid #0d6efd;
        }
        .policy-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-height: 400px;
        }
        .meta-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .badge-version {
            font-size: 0.9rem;
            background-color: #e9ecef;
            color: #495057;
            border: 1px solid #ced4da;
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

            <!-- Breadcrumb / Back Button -->
            <div class="mb-3">
                <a href="policies.php" class="text-decoration-none text-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Policies
                </a>
            </div>

            <!-- Header Section -->
            <div class="policy-header d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h2 class="mb-2"><?php echo htmlspecialchars($policy['title']); ?></h2>
                    <div class="d-flex gap-3 meta-info align-items-center flex-wrap">
                        <span class="badge bg-secondary">
                            <?php echo htmlspecialchars($policy['category']); ?>
                        </span>
                        <span>
                            <i class="far fa-clock me-1"></i>
                            Last Updated: <?php echo date('d M Y', strtotime($policy['updated_at'])); ?>
                        </span>
                        <span>
                            <i class="far fa-user me-1"></i>
                            Author: <?php echo htmlspecialchars($policy['author_name'] ?? 'Unknown'); ?>
                        </span>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge badge-version p-2 mb-2 d-inline-block">
                        Version <?php echo htmlspecialchars($policy['version']); ?>
                    </span>
                    <?php if (!empty($policy['file_path'])): ?>
                        <div class="mt-2">
                            <a href="<?php echo htmlspecialchars($policy['file_path']); ?>" class="btn btn-primary" download>
                                <i class="fas fa-download me-2"></i>Download PDF
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content Section -->
            <div class="policy-content">
                <h5 class="border-bottom pb-2 mb-3 text-muted">Policy Description</h5>
                <div class="description-text">
                    <?php echo nl2br(htmlspecialchars($policy['description'])); ?>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>