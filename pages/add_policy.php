<?php
include '../includes/auth.php';
include '../includes/db_connect.php';
include '../includes/sidebar.php';

// Check Admin Role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $version = trim($_POST['version']);
    $description = trim($_POST['description']);
    $created_by = $_SESSION['user_id'];

    // File Upload Logic
    $file_path = null;
    $upload_error = false;

    if (isset($_FILES['policy_file']) && $_FILES['policy_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file_tmp = $_FILES['policy_file']['tmp_name'];
        $file_name = $_FILES['policy_file']['name'];
        $file_size = $_FILES['policy_file']['size'];
        $file_type = mime_content_type($file_tmp);

        if (!in_array($file_type, $allowed_types)) {
            $message = "Invalid file type. Only PDF and Word documents are allowed.";
            $msg_type = "danger";
            $upload_error = true;
        } elseif ($file_size > $max_size) {
            $message = "File size exceeds 5MB limit.";
            $msg_type = "danger";
            $upload_error = true;
        } else {
            // Create upload directory if not exists
            $upload_dir = '../uploads/policies/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate unique filename
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = uniqid('policy_', true) . '.' . $ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                $file_path = $destination;
            } else {
                $message = "Failed to upload file.";
                $msg_type = "danger";
                $upload_error = true;
            }
        }
    }

    if (!$upload_error) {
        if (empty($title) || empty($category)) {
            $message = "Title and Category are required.";
            $msg_type = "danger";
        } else {
            $sql = "INSERT INTO company_policies (title, category, version, description, file_path, created_by, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $title, $category, $version, $description, $file_path, $created_by);

            if ($stmt->execute()) {
                $message = "Policy added successfully!";
                $msg_type = "success";
            } else {
                $message = "Database error: " . $stmt->error;
                $msg_type = "danger";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Policy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.0/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">

    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f3f4f6; /* Match dashboard bg */
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: #6b7280;
            font-size: 1rem;
        }

        .form-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .form-body {
            padding: 2.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--primary-color);
        }

        .form-section {
            margin-bottom: 2.5rem;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .required::after {
            content: " *";
            color: var(--danger-color);
            font-weight: 600;
        }

        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: var(--transition);
            background-color: #fafafa;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background-color: white;
            outline: none;
        }

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            background-color: #fafafa;
            cursor: pointer;
        }

        .file-upload-wrapper:hover {
            border-color: var(--primary-color);
            background-color: #f0f9ff;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: block;
        }

        .file-upload-label {
            color: var(--secondary-color);
            font-weight: 500;
            display: block;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            box-shadow: var(--card-shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover);
        }

        .btn-secondary {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }

        .btn-secondary:hover {
            background-color: #e5e7eb;
            color: #1f2937;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($message)): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.0/dist/sweetalert2.all.min.js"></script>
        <script>
            Swal.fire({
                title: '<?= strpos($message, 'Error') === false && strpos($message, 'Database error') === false ? 'Success!' : 'Error!' ?>',
                text: '<?= $message ?>',
                icon: '<?= strpos($message, 'Error') === false && strpos($message, 'Database error') === false ? 'success' : 'error' ?>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#2563eb'
            }).then((result) => {
                if (result.isConfirmed && '<?= $msg_type ?>' === 'success') {
                    window.location = "manage_policies.php";
                }
            });
        </script>
    <?php endif; ?>

    <div class="main-content">
        <div class="container-fluid">

            <div class="form-header d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-file-contract me-2 text-primary"></i>Add New Policy</h1>
                    <p>Create a new company policy document</p>
                </div>
                <a href="manage_policies.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>

            <div class="form-container">
                <div class="form-body">
                    <form action="" method="POST" enctype="multipart/form-data" id="policyForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <!-- Policy Details Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle"></i>
                                Policy Details
                            </h3>

                            <div class="row g-4">
                                <div class="col-md-8">
                                    <label for="title" class="form-label required">Policy Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required placeholder="e.g. Remote Work Policy">
                                </div>
                                <div class="col-md-4">
                                    <label for="version" class="form-label fw-bold">Version</label>
                                    <input type="text" class="form-control" id="version" name="version" value="1.0" placeholder="e.g. 1.0">
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <div class="col-md-6">
                                    <label for="category" class="form-label required">Category</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="HR">Human Resources</option>
                                        <option value="IT">IT & Security</option>
                                        <option value="Finance">Finance & Payroll</option>
                                        <option value="Operations">Operations</option>
                                        <option value="Legal">Legal & Compliance</option>
                                        <option value="General">General</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-4 mt-2">
                                <div class="col-12">
                                    <label for="description" class="form-label">Description / Summary</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Enter a brief description of the policy..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Document Upload Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-file-upload"></i>
                                Document Upload
                            </h3>

                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label">Policy Document (PDF/Word)</label>
                                    <div class="file-upload-wrapper">
                                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                        <input type="file" id="policy_file" name="policy_file" accept=".pdf,.doc,.docx">
                                        <span class="file-upload-label" id="file-label">
                                            Drag & drop or click to upload document
                                        </span>
                                        <div class="mt-2 text-muted small">Max size: 5MB. Allowed formats: PDF, DOC, DOCX</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('policyForm').reset(); resetFileLabel();">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Policy
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload interaction
        const fileInput = document.getElementById('policy_file');
        const fileLabel = document.getElementById('file-label');
        const wrapper = document.querySelector('.file-upload-wrapper');

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                fileLabel.innerHTML = `<span class="text-success fw-bold"><i class="fas fa-check-circle me-2"></i>${fileName}</span> <span class="text-muted">(${fileSize} MB)</span>`;
                wrapper.style.borderColor = 'var(--success-color)';
                wrapper.style.backgroundColor = '#ecfdf5';
            } else {
                resetFileLabel();
            }
        });

        function resetFileLabel() {
            fileLabel.innerHTML = 'Drag & drop or click to upload document';
            wrapper.style.borderColor = '#d1d5db';
            wrapper.style.backgroundColor = '#fafafa';
        }
    </script>
</body>
</html>