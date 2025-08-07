<?php
include '../includes/auth.php';

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /hr-portal/dashboard.php');
    exit();
}

// Include the database connection file
include '../includes/db_connect.php';
include '../includes/sidebar.php';

// Initialize an error or success message variable
$message = '';

// Define allowed file types and size limits
$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/jpg',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Function to handle file uploads
function handleFileUpload($file, $allowedTypes, $maxFileSize, $uploadDir, $type = 'document') {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid file parameters.');
    }

    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return null; // No file uploaded
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    // Check file size
    if ($file['size'] > $maxFileSize) {
        throw new RuntimeException('Exceeded filesize limit.');
    }

    // Check file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) {
        throw new RuntimeException('Invalid file format.');
    }

    // Create appropriate upload directory based on file type
    $typeDir = $uploadDir . ($type === 'profile' ? 'profile_pictures/' : 'documents/');
    if (!file_exists($typeDir)) {
        mkdir($typeDir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9]/", "", basename($file['name'])) . '.' . $extension;
    $filepath = $typeDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return $filepath;
}

// Get employee ID from URL
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($employee_id === 0) {
    header('Location: candidate.php');
    exit();
}

// Fetch employee details
$sql = "SELECT * FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    header('Location: candidate.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Handle file uploads
        $uploadDir = '../uploads/';
        
        // Profile picture
        if (!empty($_FILES['profile_picture']['name'])) {
            $profilePath = handleFileUpload($_FILES['profile_picture'], $allowedTypes, $maxFileSize, $uploadDir, 'profile');
            if ($profilePath) {
                // Delete old profile picture if exists and is a file
                if (!empty($employee['profile_picture']) &&
                     file_exists($employee['profile_picture']) &&
                     is_file($employee['profile_picture'])) {
                    unlink($employee['profile_picture']);
                }
                $employee['profile_picture'] = $profilePath;
            }
        }

        // Resume/CV
        if (!empty($_FILES['cv_file']['name'])) {
            $cvPath = handleFileUpload($_FILES['cv_file'], $allowedTypes, $maxFileSize, $uploadDir, 'resume');
            if ($cvPath) {
                // Delete old CV if exists and is a file
                if (!empty($employee['cv_file']) &&
                     file_exists($employee['cv_file']) &&
                     is_file($employee['cv_file'])) {
                    unlink($employee['cv_file']);
                }
                $employee['cv_file'] = $cvPath;
            }
        }

        // Handle document uploads
        $documentFields = ['adhaar_front', 'adhaar_back', 'pan_card', 'additional_document', 'bank_document'];
        foreach ($documentFields as $field) {
            if (!empty($_FILES[$field]['name'])) {
                $docPath = handleFileUpload($_FILES[$field], $allowedTypes, $maxFileSize, $uploadDir, 'document');
                if ($docPath) {
                    // Delete old document if exists and is a file
                    if (!empty($employee[$field]) &&
                         file_exists($employee[$field]) &&
                         is_file($employee[$field])) {
                        unlink($employee[$field]);
                    }
                    $employee[$field] = $docPath;
                }
            }
        }

        // Update employee details
        $sql = "UPDATE employees SET 
                full_name = ?,
                email = ?,
                mobile_number = ?,
                alternate_number = ?,
                gender = ?,
                dob = ?,
                communication_address = ?,
                permanent_address = ?,
                emergency_contact_number = ?,
                emergency_contact_person = ?,
                adhaar_id_number = ?,
                pan_card_number = ?,
                bank_account_name = ?,
                bank_account_number = ?,
                ifsc_code = ?,
                profile_picture = ?,
                adhaar_front = ?,
                adhaar_back = ?,
                pan_card = ?,
                additional_document = ?,
                bank_document = ?,
                cv_file = ?,
                hire_date = ?,
                role = ?,
                department = ?,
                branch = ?,
                shift = ?,
                college = ?,
                status = ?
                WHERE employee_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssssssssssssssssssssssi",
            $_POST['full_name'],
            $_POST['email'],
            $_POST['mobile_number'],
            $_POST['alternate_number'],
            $_POST['gender'],
            $_POST['dob'],
            $_POST['communication_address'],
            $_POST['permanent_address'],
            $_POST['emergency_contact_number'],
            $_POST['emergency_contact_person'],
            $_POST['adhaar_id_number'],
            $_POST['pan_card_number'],
            $_POST['bank_account_name'],
            $_POST['bank_account_number'],
            $_POST['ifsc_code'],
            $employee['profile_picture'],
            $employee['adhaar_front'],
            $employee['adhaar_back'],
            $employee['pan_card'],
            $employee['additional_document'],
            $employee['bank_document'],
            $employee['cv_file'],
            $_POST['hire_date'],
            $_POST['role'],
            $_POST['department'],
            $_POST['branch'],
            $_POST['shift'],
            $_POST['college'],
            $_POST['status'],
            $employee_id
        );

        if ($stmt->execute()) {
            $message = "Employee details updated successfully!";
            // Refresh employee data
            $sql = "SELECT * FROM employees WHERE employee_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result->fetch_assoc();
        } else {
            throw new Exception("Error updating employee: " . $stmt->error);
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Fetch all departments for the dropdown
$dept_sql = "SELECT DISTINCT department FROM employees ORDER BY department";
$dept_result = $conn->query($dept_sql);
$departments = [];
while ($dept_row = $dept_result->fetch_assoc()) {
    $departments[] = $dept_row['department'];
}

// Fetch all roles for the dropdown
$role_sql = "SELECT DISTINCT role FROM employees ORDER BY role";
$role_result = $conn->query($role_sql);
$roles = [];
while ($role_row = $role_result->fetch_assoc()) {
    $roles[] = $role_row['role'];
}

// Fetch all colleges for the dropdown
$college_sql = "SHOW COLUMNS FROM employees LIKE 'college'";
$college_result = $conn->query($college_sql);
$colleges = [];

if ($college_result && $college_row = $college_result->fetch_assoc()) {
    // Extract ENUM values from the Type column
    $enum_string = $college_row['Type'];
    
    // Check if it's actually an ENUM
    if (strpos($enum_string, 'enum(') === 0) {
        // Remove 'enum(' and ')' and split by comma
        $enum_string = substr($enum_string, 5, -1);
        $enum_values = str_getcsv($enum_string, ',', "'");
        
        // Clean up the values and add to colleges array
        foreach ($enum_values as $value) {
            $clean_value = trim($value, "'\"");
            if (!empty($clean_value)) {
                $colleges[] = $clean_value;
            }
        }
    }
}

// Sort colleges alphabetically
sort($colleges);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - HR Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --secondary-color: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .form-container {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .form-section {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .section-header i {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .section-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            background-color: white;
        }

        .profile-section {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .profile-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid white;
            box-shadow: var(--shadow-lg);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .profile-preview:hover {
            transform: scale(1.05);
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .file-upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .document-preview {
            background: #f8fafc;
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .document-preview:hover {
            border-color: var(--primary-color);
            background: rgba(79, 70, 229, 0.05);
        }

        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .document-link:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, var(--primary-dark), #3730a3);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
        }

        .form-actions {
            background: #f8fafc;
            padding: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid var(--border-color);
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .required-field::after {
            content: " *";
            color: var(--danger-color);
            font-weight: bold;
        }

        .form-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .progress-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .progress-step::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            right: -50%;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
        }

        .progress-step:last-child::before {
            display: none;
        }

        .progress-step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            position: relative;
            z-index: 2;
        }

        .progress-step-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .form-section {
                padding: 1.5rem;
            }

            .profile-preview {
                width: 120px;
                height: 120px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header fade-in">
            <h1><i class="fas fa-user-edit me-3"></i>Edit Employee</h1>
            <p>Update employee information and documents</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show fade-in" role="alert">
                <i class="fas <?php echo strpos($message, 'Error') === 0 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="form-container fade-in">
            <form method="POST" enctype="multipart/form-data">
                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="progress-step">
                        <div class="progress-step-circle">1</div>
                        <div class="progress-step-label">Personal Info</div>
                    </div>
                    <div class="progress-step">
                        <div class="progress-step-circle">2</div>
                        <div class="progress-step-label">Documents</div>
                    </div>
                    <div class="progress-step">
                        <div class="progress-step-circle">3</div>
                        <div class="progress-step-label">Employment</div>
                    </div>
                </div>

                <!-- Profile Section -->
                <div class="profile-section">
                    <div class="section-header justify-content-center">
                        <i class="fas fa-user-circle"></i>
                        <h3>Profile Picture</h3>
                    </div>
                    <?php
                    $profilePicPath = !empty($employee['profile_picture']) ?
                         (strpos($employee['profile_picture'], 'http') === 0 ?
                             $employee['profile_picture'] :
                             '/hr-portal/' . ltrim($employee['profile_picture'], './')) :
                         '/hr-portal/assets/images/default-profile.png';
                    ?>
                    <img src="<?php echo htmlspecialchars($profilePicPath); ?>"
                          alt="Profile Picture" class="profile-preview">
                    <div class="input-group">
                        <label for="profile_picture" class="form-label">Change Profile Picture</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                        <div class="form-text">Supported formats: JPG, PNG. Max size: 5MB</div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-user"></i>
                        <h3>Personal Information</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="full_name" class="form-label required-field">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                        value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="email" class="form-label required-field">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="mobile_number" class="form-label required-field">Mobile Number</label>
                                <input type="tel" class="form-control" id="mobile_number" name="mobile_number"
                                        value="<?php echo htmlspecialchars($employee['mobile_number']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="alternate_number" class="form-label">Alternate Number</label>
                                <input type="tel" class="form-control" id="alternate_number" name="alternate_number"
                                        value="<?php echo htmlspecialchars($employee['alternate_number']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <label for="gender" class="form-label required-field">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo $employee['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $employee['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $employee['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <label for="dob" class="form-label required-field">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob"
                                        value="<?php echo htmlspecialchars($employee['dob']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <label for="college" class="form-label">College</label>
                                <select class="form-select" id="college" name="college">
                                    <option value="">Select College</option>
                                    <?php foreach ($colleges as $college): ?>
                                        <option value="<?php echo htmlspecialchars($college); ?>"
                                                 <?php echo $employee['college'] === $college ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($college); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="communication_address" class="form-label required-field">Communication Address</label>
                                <textarea class="form-control" id="communication_address" name="communication_address"
                                           rows="3" required><?php echo htmlspecialchars($employee['communication_address']); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="permanent_address" class="form-label">Permanent Address</label>
                                <textarea class="form-control" id="permanent_address" name="permanent_address"
                                           rows="3"><?php echo htmlspecialchars($employee['permanent_address']); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="emergency_contact_person" class="form-label">Emergency Contact Person</label>
                                <input type="text" class="form-control" id="emergency_contact_person" name="emergency_contact_person"
                                        value="<?php echo htmlspecialchars($employee['emergency_contact_person']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="emergency_contact_number" class="form-label">Emergency Contact Number</label>
                                <input type="tel" class="form-control" id="emergency_contact_number" name="emergency_contact_number"
                                        value="<?php echo htmlspecialchars($employee['emergency_contact_number']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-file-alt"></i>
                        <h3>Document Information</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="adhaar_id_number" class="form-label">Aadhaar ID Number</label>
                                <input type="text" class="form-control" id="adhaar_id_number" name="adhaar_id_number"
                                        value="<?php echo htmlspecialchars($employee['adhaar_id_number']); ?>"
                                        placeholder="Enter 12-digit Aadhaar number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="pan_card_number" class="form-label">PAN Card Number</label>
                                <input type="text" class="form-control" id="pan_card_number" name="pan_card_number"
                                        value="<?php echo htmlspecialchars($employee['pan_card_number']); ?>"
                                        placeholder="Enter PAN number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="adhaar_front" class="form-label">Aadhaar Front Image</label>
                                <?php if (!empty($employee['adhaar_front'])): ?>
                                    <div class="document-preview">
                                        <i class="fas fa-file-image document-icon mb-2"></i>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($employee['adhaar_front']); ?>" 
                                               target="_blank" class="document-link">
                                                <i class="fas fa-eye"></i> View Current Aadhaar Front
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="adhaar_front" name="adhaar_front" accept="image/*,.pdf">
                                <div class="form-text">Upload new file to replace existing document</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="adhaar_back" class="form-label">Aadhaar Back Image</label>
                                <?php if (!empty($employee['adhaar_back'])): ?>
                                    <div class="document-preview">
                                        <i class="fas fa-file-image document-icon mb-2"></i>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($employee['adhaar_back']); ?>" 
                                               target="_blank" class="document-link">
                                                <i class="fas fa-eye"></i> View Current Aadhaar Back
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="adhaar_back" name="adhaar_back" accept="image/*,.pdf">
                                <div class="form-text">Upload new file to replace existing document</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="pan_card" class="form-label">PAN Card Document</label>
                                <?php if (!empty($employee['pan_card'])): ?>
                                    <div class="document-preview">
                                        <i class="fas fa-file-pdf document-icon mb-2"></i>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($employee['pan_card']); ?>" 
                                               target="_blank" class="document-link">
                                                <i class="fas fa-eye"></i> View Current PAN Card
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="pan_card" name="pan_card" accept="image/*,.pdf">
                                <div class="form-text">Upload new file to replace existing document</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="additional_document" class="form-label">Additional Document</label>
                                <?php if (!empty($employee['additional_document'])): ?>
                                    <div class="document-preview">
                                        <i class="fas fa-file document-icon mb-2"></i>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($employee['additional_document']); ?>" 
                                               target="_blank" class="document-link">
                                                <i class="fas fa-eye"></i> View Additional Document
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="additional_document" name="additional_document" 
                                       accept="image/*,.pdf,.doc,.docx">
                                <div class="form-text">Upload new file to replace existing document</div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="input-group">
                                <label for="cv_file" class="form-label">Resume/CV</label>
                                <?php if (!empty($employee['cv_file'])): ?>
                                    <div class="document-preview">
                                        <i class="fas fa-file-pdf document-icon mb-2"></i>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($employee['cv_file']); ?>" 
                                               target="_blank" class="document-link">
                                                <i class="fas fa-eye"></i> View Current CV
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx">
                                <div class="form-text">Upload new file to replace existing CV</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-university"></i>
                        <h3>Bank Information</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="bank_account_name" class="form-label">Bank Account Name</label>
                                <input type="text" class="form-control" id="bank_account_name" name="bank_account_name"
                                        value="<?php echo htmlspecialchars($employee['bank_account_name']); ?>"
                                        placeholder="Account holder name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="bank_account_number" class="form-label">Bank Account Number</label>
                                <input type="text" class="form-control" id="bank_account_number" name="bank_account_number"
                                        value="<?php echo htmlspecialchars($employee['bank_account_number']); ?>"
                                        placeholder="Account number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="ifsc_code" class="form-label">IFSC Code</label>
                                <input type="text" class="form-control" id="ifsc_code" name="ifsc_code"
                                        value="<?php echo htmlspecialchars($employee['ifsc_code']); ?>"
                                        placeholder="Bank IFSC code">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="bank_document" class="form-label">Bank Document</label>
                                <?php if (!empty($employee['bank_document'])): ?>
                                    <div class="document-preview">
                                        <i class="fas fa-file-pdf document-icon mb-2"></i>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($employee['bank_document']); ?>" 
                                               target="_blank" class="document-link">
                                                <i class="fas fa-eye"></i> View Bank Document
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="bank_document" name="bank_document" accept="image/*,.pdf">
                                <div class="form-text">Upload new file to replace existing document</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employment Information -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-briefcase"></i>
                        <h3>Employment Information</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="hire_date" class="form-label">Hire Date</label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date"
                                        value="<?php echo htmlspecialchars($employee['hire_date']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="status" class="form-label required-field">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Active" <?php echo $employee['status'] === 'Active' ? 'selected' : ''; ?>>
                                        <i class="fas fa-check-circle"></i> Active
                                    </option>
                                    <option value="Inactive" <?php echo $employee['status'] === 'Inactive' ? 'selected' : ''; ?>>
                                        <i class="fas fa-times-circle"></i> Inactive
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="role" class="form-label required-field">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role); ?>"
                                                 <?php echo $employee['role'] === $role ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="department" class="form-label required-field">Department</label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"
                                                 <?php echo $employee['department'] === $dept ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="branch" class="form-label required-field">Branch</label>
                                <select class="form-select" id="branch" name="branch" required>
                                    <option value="">Select Branch</option>
                                    <option value="Sarachi" <?php echo $employee['branch'] === 'Sarachi' ? 'selected' : ''; ?>>
                                        <i class="fas fa-building"></i> Sarachi
                                    </option>
                                    <option value="Astra" <?php echo $employee['branch'] === 'Astra' ? 'selected' : ''; ?>>
                                        <i class="fas fa-building"></i> Astra
                                    </option>
                                    <option value="PS-Qube" <?php echo $employee['branch'] === 'PS-Qube' ? 'selected' : ''; ?>>
                                        <i class="fas fa-building"></i> PS-Qube
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <label for="shift" class="form-label required-field">Shift</label>
                                <select class="form-select" id="shift" name="shift" required>
                                    <option value="">Select Shift</option>
                                    <option value="Morning" <?php echo $employee['shift'] === 'Morning' ? 'selected' : ''; ?>>
                                        <i class="fas fa-sun"></i> Morning
                                    </option>
                                    <option value="Evening" <?php echo $employee['shift'] === 'Evening' ? 'selected' : ''; ?>>
                                        <i class="fas fa-moon"></i> Evening
                                    </option>
                                    <option value="Full" <?php echo $employee['shift'] === 'Full' ? 'selected' : ''; ?>>
                                        <i class="fas fa-clock"></i> Full Day
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="candidate.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to List
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const fileInputs = document.querySelectorAll('input[type="file"]');
            
            // File input enhancement
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const maxSize = 5 * 1024 * 1024; // 5MB
                        if (file.size > maxSize) {
                            alert('File size exceeds 5MB limit. Please choose a smaller file.');
                            this.value = '';
                            return;
                        }
                        
                        // Show file name
                        const fileName = file.name;
                        const fileInfo = document.createElement('div');
                        fileInfo.className = 'file-info mt-2 p-2 bg-light rounded';
                        fileInfo.innerHTML = `<i class="fas fa-file me-2"></i>Selected: ${fileName}`;
                        
                        // Remove existing file info
                        const existingInfo = this.parentNode.querySelector('.file-info');
                        if (existingInfo) {
                            existingInfo.remove();
                        }
                        
                        this.parentNode.appendChild(fileInfo);
                    }
                });
            });

            // Profile picture preview
            const profilePictureInput = document.getElementById('profile_picture');
            const profilePreview = document.querySelector('.profile-preview');
            
            profilePictureInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePreview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Form validation
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                // Email validation
                const email = document.getElementById('email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (email.value && !emailRegex.test(email.value)) {
                    email.classList.add('is-invalid');
                    isValid = false;
                }

                // Mobile number validation
                const mobile = document.getElementById('mobile_number');
                const mobileRegex = /^[0-9]{10}$/;
                if (mobile.value && !mobileRegex.test(mobile.value.replace(/\D/g, ''))) {
                    mobile.classList.add('is-invalid');
                    isValid = false;
                }

                // Aadhaar validation
                const aadhaar = document.getElementById('adhaar_id_number');
                if (aadhaar.value) {
                    const aadhaarRegex = /^[0-9]{12}$/;
                    if (!aadhaarRegex.test(aadhaar.value.replace(/\s/g, ''))) {
                        aadhaar.classList.add('is-invalid');
                        isValid = false;
                    }
                }

                // PAN validation
                const pan = document.getElementById('pan_card_number');
                if (pan.value) {
                    const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
                    if (!panRegex.test(pan.value.toUpperCase())) {
                        pan.classList.add('is-invalid');
                        isValid = false;
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first invalid field
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                    
                    // Show error message
                    showAlert('Please fill in all required fields correctly.', 'danger');
                }
            });

            // Real-time validation
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
            });

            function validateField(field) {
                const value = field.value.trim();
                
                // Required field validation
                if (field.hasAttribute('required') && !value) {
                    field.classList.add('is-invalid');
                    return false;
                }
                
                // Specific field validations
                switch (field.type) {
                    case 'email':
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (value && !emailRegex.test(value)) {
                            field.classList.add('is-invalid');
                            return false;
                        }
                        break;
                        
                    case 'tel':
                        if (field.id === 'mobile_number' || field.id === 'alternate_number' || field.id === 'emergency_contact_number') {
                            const mobileRegex = /^[0-9]{10}$/;
                            if (value && !mobileRegex.test(value.replace(/\D/g, ''))) {
                                field.classList.add('is-invalid');
                                return false;
                            }
                        }
                        break;
                        
                    case 'text':
                        if (field.id === 'adhaar_id_number') {
                            const aadhaarRegex = /^[0-9]{12}$/;
                            if (value && !aadhaarRegex.test(value.replace(/\s/g, ''))) {
                                field.classList.add('is-invalid');
                                return false;
                            }
                        } else if (field.id === 'pan_card_number') {
                            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
                            if (value && !panRegex.test(value.toUpperCase())) {
                                field.classList.add('is-invalid');
                                return false;
                            }
                        }
                        break;
                }
                
                field.classList.remove('is-invalid');
                return true;
            }

            // Auto-format inputs
            document.getElementById('adhaar_id_number').addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length > 12) value = value.substr(0, 12);
                // Format as XXXX XXXX XXXX
                if (value.length > 8) {
                    value = value.substr(0, 4) + ' ' + value.substr(4, 4) + ' ' + value.substr(8);
                } else if (value.length > 4) {
                    value = value.substr(0, 4) + ' ' + value.substr(4);
                }
                this.value = value;
            });

            document.getElementById('pan_card_number').addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });

            // Mobile number formatting
            const mobileInputs = ['mobile_number', 'alternate_number', 'emergency_contact_number'];
            mobileInputs.forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', function() {
                        let value = this.value.replace(/\D/g, '');
                        if (value.length > 10) value = value.substr(0, 10);
                        this.value = value;
                    });
                }
            });

            // Copy address functionality
            const copyAddressBtn = document.createElement('button');
            copyAddressBtn.type = 'button';
            copyAddressBtn.className = 'btn btn-outline-primary btn-sm mt-2';
            copyAddressBtn.innerHTML = '<i class="fas fa-copy me-1"></i>Copy from Communication Address';
            
            const permanentAddressGroup = document.getElementById('permanent_address').parentNode;
            permanentAddressGroup.appendChild(copyAddressBtn);
            
            copyAddressBtn.addEventListener('click', function() {
                const commAddress = document.getElementById('communication_address').value;
                document.getElementById('permanent_address').value = commAddress;
            });

            // Show success/error alerts
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const container = document.querySelector('.main-content');
                const firstChild = container.firstElementChild.nextElementSibling;
                container.insertBefore(alertDiv, firstChild);
                
                // Auto dismiss after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }

            // Smooth scrolling for form sections
            const sectionHeaders = document.querySelectorAll('.section-header');
            sectionHeaders.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    const section = this.parentNode;
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            // Progress indicator update
            function updateProgressIndicator() {
                const sections = document.querySelectorAll('.form-section');
                const progressSteps = document.querySelectorAll('.progress-step');
                
                sections.forEach((section, index) => {
                    const requiredFields = section.querySelectorAll('[required]');
                    let completed = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            completed = false;
                        }
                    });
                    
                    if (progressSteps[index]) {
                        const circle = progressSteps[index].querySelector('.progress-step-circle');
                        if (completed) {
                            circle.style.background = 'var(--success-color)';
                            circle.innerHTML = '<i class="fas fa-check"></i>';
                        } else {
                            circle.style.background = 'var(--primary-color)';
                            circle.textContent = index + 1;
                        }
                    }
                });
            }

            // Update progress on input change
            inputs.forEach(input => {
                input.addEventListener('input', updateProgressIndicator);
            });

            // Initial progress update
            updateProgressIndicator();

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+S to save
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    form.submit();
                }
                
                // Escape to go back
                if (e.key === 'Escape') {
                    window.location.href = 'candidate.php';
                }
            });

            // Prevent form submission on Enter key (except for textareas and submit buttons)
            form.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') {
                    e.preventDefault();
                }
            });

            // Auto-save draft functionality (optional)
            let autoSaveTimer;
            function autoSaveDraft() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    const formData = new FormData(form);
                    formData.append('auto_save', '1');
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if (response.ok) {
                            console.log('Draft saved automatically');
                        }
                    }).catch(error => {
                        console.log('Auto-save failed:', error);
                    });
                }, 30000); // Auto-save every 30 seconds
            }

            // Enable auto-save on input change
            inputs.forEach(input => {
                input.addEventListener('input', autoSaveDraft);
            });
        });

        // Confirmation before leaving page with unsaved changes
        let formChanged = false;
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        form.addEventListener('submit', () => {
            formChanged = false;
        });
    </script>
</body>
</html>
