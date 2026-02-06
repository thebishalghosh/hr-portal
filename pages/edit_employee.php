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
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 1rem 1.25rem;
        }
        .card-header h5 {
            margin-bottom: 0;
            color: #333;
            font-weight: 600;
        }
        .profile-img-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
            position: relative;
        }
        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .form-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 0.5rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .required-field::after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }
        .document-link {
            text-decoration: none;
            font-size: 0.875rem;
            display: inline-block;
            margin-top: 0.25rem;
        }
        .document-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 mb-0 text-gray-800">Edit Employee</h2>
                <a href="candidate.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <!-- Left Column: Profile & Basic Info -->
                    <div class="col-lg-4">
                        <!-- Profile Picture Card -->
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="profile-img-container">
                                    <?php
                                    $profilePicPath = !empty($employee['profile_picture']) ?
                                        (strpos($employee['profile_picture'], 'http') === 0 ?
                                            $employee['profile_picture'] :
                                            '/hr-portal/' . ltrim($employee['profile_picture'], './')) :
                                        '/hr-portal/assets/images/default-profile.png';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($profilePicPath); ?>" alt="Profile Picture" class="profile-img">
                                </div>
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($employee['full_name']); ?></h5>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($employee['role']); ?></p>

                                <div class="mb-3 text-start">
                                    <label for="profile_picture" class="form-label small">Change Photo</label>
                                    <input type="file" class="form-control form-control-sm" id="profile_picture" name="profile_picture" accept="image/*">
                                </div>
                            </div>
                        </div>

                        <!-- Employment Status Card -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Employment Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="status" class="form-label required-field">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Active" <?php echo $employee['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo $employee['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label required-field">Role</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $employee['role'] === $role ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($employee['hire_date']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Detailed Info -->
                    <div class="col-lg-8">
                        <!-- Personal Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="full_name" class="form-label required-field">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label required-field">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mobile_number" class="form-label required-field">Mobile Number</label>
                                        <input type="tel" class="form-control" id="mobile_number" name="mobile_number" value="<?php echo htmlspecialchars($employee['mobile_number']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="alternate_number" class="form-label">Alternate Number</label>
                                        <input type="tel" class="form-control" id="alternate_number" name="alternate_number" value="<?php echo htmlspecialchars($employee['alternate_number']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="gender" class="form-label required-field">Gender</label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo $employee['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $employee['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo $employee['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="dob" class="form-label required-field">Date of Birth</label>
                                        <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($employee['dob']); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="communication_address" class="form-label required-field">Communication Address</label>
                                        <textarea class="form-control" id="communication_address" name="communication_address" rows="2" required><?php echo htmlspecialchars($employee['communication_address']); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label for="permanent_address" class="form-label">Permanent Address</label>
                                        <textarea class="form-control" id="permanent_address" name="permanent_address" rows="2"><?php echo htmlspecialchars($employee['permanent_address']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Work Details -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Work Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="department" class="form-label required-field">Department</label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $employee['department'] === $dept ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="branch" class="form-label required-field">Branch</label>
                                        <select class="form-select" id="branch" name="branch" required>
                                            <option value="">Select Branch</option>
                                            <option value="Sarachi" <?php echo $employee['branch'] === 'Sarachi' ? 'selected' : ''; ?>>Sarachi</option>
                                            <option value="Astra" <?php echo $employee['branch'] === 'Astra' ? 'selected' : ''; ?>>Astra</option>
                                            <option value="PS-Qube" <?php echo $employee['branch'] === 'PS-Qube' ? 'selected' : ''; ?>>PS-Qube</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shift" class="form-label required-field">Shift</label>
                                        <select class="form-select" id="shift" name="shift" required>
                                            <option value="">Select Shift</option>
                                            <option value="Morning" <?php echo $employee['shift'] === 'Morning' ? 'selected' : ''; ?>>Morning</option>
                                            <option value="Evening" <?php echo $employee['shift'] === 'Evening' ? 'selected' : ''; ?>>Evening</option>
                                            <option value="Full" <?php echo $employee['shift'] === 'Full' ? 'selected' : ''; ?>>Full Day</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="college" class="form-label">College</label>
                                        <select class="form-select" id="college" name="college">
                                            <option value="">Select College</option>
                                            <?php foreach ($colleges as $college): ?>
                                                <option value="<?php echo htmlspecialchars($college); ?>" <?php echo $employee['college'] === $college ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($college); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents & Bank Info -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Documents & Bank Info</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- IDs -->
                                    <div class="col-md-6">
                                        <label for="adhaar_id_number" class="form-label">Aadhaar Number</label>
                                        <input type="text" class="form-control" id="adhaar_id_number" name="adhaar_id_number" value="<?php echo htmlspecialchars($employee['adhaar_id_number']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pan_card_number" class="form-label">PAN Number</label>
                                        <input type="text" class="form-control" id="pan_card_number" name="pan_card_number" value="<?php echo htmlspecialchars($employee['pan_card_number']); ?>">
                                    </div>

                                    <!-- Bank Info -->
                                    <div class="col-md-4">
                                        <label for="bank_account_name" class="form-label">Account Name</label>
                                        <input type="text" class="form-control" id="bank_account_name" name="bank_account_name" value="<?php echo htmlspecialchars($employee['bank_account_name']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="bank_account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="<?php echo htmlspecialchars($employee['bank_account_number']); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="ifsc_code" class="form-label">IFSC Code</label>
                                        <input type="text" class="form-control" id="ifsc_code" name="ifsc_code" value="<?php echo htmlspecialchars($employee['ifsc_code']); ?>">
                                    </div>

                                    <!-- File Uploads -->
                                    <div class="col-md-6">
                                        <label for="adhaar_front" class="form-label">Aadhaar Front</label>
                                        <input type="file" class="form-control" id="adhaar_front" name="adhaar_front" accept="image/*,.pdf">
                                        <?php if (!empty($employee['adhaar_front'])): ?>
                                            <a href="<?php echo htmlspecialchars($employee['adhaar_front']); ?>" target="_blank" class="document-link text-primary"><i class="fas fa-eye me-1"></i> View Current</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="adhaar_back" class="form-label">Aadhaar Back</label>
                                        <input type="file" class="form-control" id="adhaar_back" name="adhaar_back" accept="image/*,.pdf">
                                        <?php if (!empty($employee['adhaar_back'])): ?>
                                            <a href="<?php echo htmlspecialchars($employee['adhaar_back']); ?>" target="_blank" class="document-link text-primary"><i class="fas fa-eye me-1"></i> View Current</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pan_card" class="form-label">PAN Card</label>
                                        <input type="file" class="form-control" id="pan_card" name="pan_card" accept="image/*,.pdf">
                                        <?php if (!empty($employee['pan_card'])): ?>
                                            <a href="<?php echo htmlspecialchars($employee['pan_card']); ?>" target="_blank" class="document-link text-primary"><i class="fas fa-eye me-1"></i> View Current</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cv_file" class="form-label">Resume/CV</label>
                                        <input type="file" class="form-control" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx">
                                        <?php if (!empty($employee['cv_file'])): ?>
                                            <a href="<?php echo htmlspecialchars($employee['cv_file']); ?>" target="_blank" class="document-link text-primary"><i class="fas fa-eye me-1"></i> View Current</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
