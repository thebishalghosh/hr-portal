<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include '../includes/auth.php';
// Check if user is admin
// if ($_SESSION['user_role'] !== 'admin') {
//     header('Location: /hr-portal/dashboard.php');
//     exit();
// }

// Include the database connection file
include('../includes/db_connect.php');
// include('../includes/sidebar.php');

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
    $typeDir = $uploadDir . ($type === 'profile' ? 'profile_pictures/' : 'resumes/');
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $uploadDir = '../uploads/';
        
        // Handle file uploads with appropriate types
        $imagePath = handleFileUpload($_FILES['profile_picture'], $allowedTypes, $maxFileSize, $uploadDir, 'profile');
        $aadhaarFrontPath = handleFileUpload($_FILES['aadhaar_front'], $allowedTypes, $maxFileSize, $uploadDir, 'document');
        $aadhaarBackPath = handleFileUpload($_FILES['aadhaar_back'], $allowedTypes, $maxFileSize, $uploadDir, 'document');
        $panCardPath = handleFileUpload($_FILES['pan_card'], $allowedTypes, $maxFileSize, $uploadDir, 'document');
        $additionalDocumentPath = handleFileUpload($_FILES['additional_documents'], $allowedTypes, $maxFileSize, $uploadDir, 'document');
        $bankDocumentPath = handleFileUpload($_FILES['bank_document'], $allowedTypes, $maxFileSize, $uploadDir, 'document');
        $cvFilePath = handleFileUpload($_FILES['cv'], $allowedTypes, $maxFileSize, $uploadDir, 'resume');

        // Get form data
        $name = $_POST['full_name'];
        $email = $_POST['email'];
        $dob = $_POST['dob'];
        $password = password_hash($dob, PASSWORD_DEFAULT);
        $phone = $_POST['mobile_number'];
        $alternatePhone = $_POST['alternate_number'];
        $gender = $_POST['gender'];
        $address = $_POST['communication_address'];
        $role = $_POST['role'];
        $department = $_POST['department'];
        
        $sql = "INSERT INTO employees (full_name, email, password_hash, mobile_number, alternate_number, gender, dob, communication_address, permanent_address, emergency_contact_number, emergency_contact_person, adhaar_id_number, pan_card_number, bank_account_name, bank_account_number, ifsc_code, profile_picture, adhaar_front, adhaar_back, pan_card, additional_document, bank_document, cv_file, hire_date, status, created_at, updated_at, role, department, shift, branch, college)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp(), 'Active', current_timestamp(), current_timestamp(), ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(
                'ssssssssssssssssssssssssssss', 
                $name,
                $email,
                $password,
                $phone,
                $alternatePhone,
                $gender,
                $dob,
                $address,
                $_POST['permanent_address'],
                $_POST['emergency_contact'],
                $_POST['emergency_contact_person'],
                $_POST['aadhaar_id'],
                $_POST['pan_number'],
                $_POST['bank_name'],
                $_POST['bank_account'],
                $_POST['ifsc_code'],
                $imagePath,
                $aadhaarFrontPath,
                $aadhaarBackPath,
                $panCardPath,
                $additionalDocumentPath,
                $bankDocumentPath,
                $cvFilePath,
                $role,
                $department,
                $_POST['shift'],
                $_POST['branch'],
                $_POST['college']
            );

            if ($stmt->execute()) {
                $message = "Candidate added successfully! Default password is Date of Birth.";
            } else {
                throw new Exception("Error: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}


// Fetch all ENUM values for college column
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
    <title>Employee Registration Form</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white' font-family='Arial'>ðŸ‘¤</text></svg>">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.0/dist/sweetalert2.min.css" rel="stylesheet">
    
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 3rem;
            color: white;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .form-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow-hover);
            overflow: hidden;
            transition: var(--transition);
        }

        .form-container:hover {
            transform: translateY(-2px);
        }

        .form-body {
            padding: 3rem;
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
            display: flex;
            align-items: center;
            gap: 0.25rem;
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

        .form-control:hover, .form-select:hover {
            border-color: #d1d5db;
            background-color: white;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            z-index: 10;
        }

        .input-group .form-control {
            padding-left: 2.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 8px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover);
            background: linear-gradient(135deg, var(--primary-dark) 0%, #1e40af 100%);
        }

        .btn-secondary {
            background-color: #6b7280;
            border: none;
            border-radius: 8px;
            padding: 1rem 2rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-secondary:hover {
            background-color: #4b5563;
            transform: translateY(-1px);
        }

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            background-color: #fafafa;
        }

        .file-upload-wrapper:hover {
            border-color: var(--primary-color);
            background-color: #f0f9ff;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }

        .file-upload-label {
            cursor: pointer;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .file-upload-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .progress-bar {
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--success-color) 100%);
            border-radius: 2px;
            margin-bottom: 2rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

                .alert-danger {
            background-color: #fef2f2;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .card-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .form-body {
                padding: 1.5rem;
            }
            
            .form-header h1 {
                font-size: 2rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
            }
        }

        .tooltip-custom {
            position: relative;
            display: inline-block;
        }

        .tooltip-custom .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.8rem;
        }

        .tooltip-custom:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        .form-floating {
            position: relative;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            opacity: 0.65;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
        }

        .animate-fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <?php if (!empty($message)): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.0/dist/sweetalert2.all.min.js"></script>
        <script>
            Swal.fire({
                title: '<?= strpos($message, 'Error') === false ? 'Success!' : 'Error!' ?>',
                text: '<?= $message ?>',
                icon: '<?= strpos($message, 'Error') === false ? 'success' : 'error' ?>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#2563eb',
                background: '#fff',
                customClass: {
                    popup: 'animate-fade-in'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location = "add_candidate.php";
                }
            });
        </script>
    <?php endif; ?>

    <div class="main-container">
        <div class="form-header">
            <h1><i class="fas fa-user-plus"></i> Employee Registration</h1>
            <p>Complete the form below to register a new employee</p>
        </div>

        <div class="form-container animate-fade-in">
            <div class="form-body">
                <form action="add_candidate.php" method="POST" enctype="multipart/form-data" id="employeeForm">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label required">Full Name</label>
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="Enter full name">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="profile_picture" class="form-label required">Profile Picture</label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-camera file-upload-icon"></i>
                                    <input type="file" id="profile_picture" name="profile_picture" required accept="image/*">
                                    <label for="profile_picture" class="file-upload-label">
                                        Click to upload recent photograph
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label for="email" class="form-label required">Email Address</label>
                                <div class="input-group">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="Enter email address">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="mobile_number" class="form-label required">Mobile Number</label>
                                <div class="input-group">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" class="form-control" id="mobile_number" name="mobile_number" maxlength="10" required placeholder="Enter mobile number">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="alternate_number" class="form-label">Alternate Number</label>
                                <div class="input-group">
                                    <i class="fas fa-phone-alt input-icon"></i>
                                    <input type="tel" class="form-control" id="alternate_number" name="alternate_number" maxlength="10" placeholder="Enter alternate number">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label for="gender" class="form-label required">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="dob" class="form-label required">Date of Birth</label>
                                <div class="input-group">
                                    <i class="fas fa-calendar input-icon"></i>
                                    <input type="date" class="form-control" id="dob" name="dob" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="role" class="form-label required">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="user">Employee</option>
                                    <option value="user">Intern</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Work Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-briefcase"></i>
                            Work Information
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="department" class="form-label required">Department</label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Technical">Technical</option>
                                    <option value="Marketing">Marketing</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="shift" class="form-label required">Shift</label>
                                <select class="form-select" id="shift" name="shift" required>
                                    <option value="">Select Shift</option>
                                    <option value="Morning">Morning</option>
                                    <option value="Evening">Evening</option>
                                    <option value="Full" selected>Full</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="branch" class="form-label required">Branch</label>
                                <select class="form-select" id="branch" name="branch" required>
                                    <option value="">Select Branch</option>
                                    <option value="Sarachi" selected>Sarachi</option>
                                    <option value="Astra">Astra</option>
                                    <option value="PS-Qube">PS-Qube</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="college" class="form-label">College</label>
                                <select class="form-select" id="college" name="college">
                                    <option value="">Select College</option>
                                    <?php foreach ($colleges as $college): ?>
                                        <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                        </div>
                    </div>

                    <!-- Address Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Address Information
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="communication_address" class="form-label required">Communication Address</label>
                                <textarea class="form-control" id="communication_address" name="communication_address" rows="3" required placeholder="Enter communication address"></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="permanent_address" class="form-label required">Permanent Address</label>
                                <textarea class="form-control" id="permanent_address" name="permanent_address" rows="3" required placeholder="Enter permanent address"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-phone-square-alt"></i>
                            Emergency Contact
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="emergency_contact_person" class="form-label required">Emergency Contact Person</label>
                                <div class="input-group">
                                    <i class="fas fa-user-friends input-icon"></i>
                                    <input type="text" class="form-control" id="emergency_contact_person" name="emergency_contact_person" required placeholder="Enter contact person name">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="emergency_contact" class="form-label required">Emergency Contact Number</label>
                                <div class="input-group">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" maxlength="10" required placeholder="Enter emergency contact number">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Identity Documents Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-id-card"></i>
                            Identity Documents
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="aadhaar_id" class="form-label">Aadhaar ID Number</label>
                                <div class="input-group">
                                    <i class="fas fa-id-card input-icon"></i>
                                    <input type="text" class="form-control" id="aadhaar_id" name="aadhaar_id" placeholder="Enter Aadhaar number">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="pan_number" class="form-label">PAN Card Number</label>
                                <div class="input-group">
                                    <i class="fas fa-credit-card input-icon"></i>
                                    <input type="text" class="form-control" id="pan_number" name="pan_number" placeholder="Enter PAN number">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label for="aadhaar_front" class="form-label">Aadhaar Card (Front)</label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-file-upload file-upload-icon"></i>
                                    <input type="file" id="aadhaar_front" name="aadhaar_front" accept=".pdf,.jpg,.jpeg,.png">
                                    <label for="aadhaar_front" class="file-upload-label">
                                        Upload Aadhaar front side
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                                                <label for="aadhaar_back" class="form-label">Aadhaar Card (Back)</label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-file-upload file-upload-icon"></i>
                                    <input type="file" id="aadhaar_back" name="aadhaar_back" accept=".pdf,.jpg,.jpeg,.png">
                                    <label for="aadhaar_back" class="file-upload-label">
                                        Upload Aadhaar back side
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label for="pan_card" class="form-label">PAN Card</label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-file-upload file-upload-icon"></i>
                                    <input type="file" id="pan_card" name="pan_card" accept=".pdf,.jpg,.jpeg,.png">
                                    <label for="pan_card" class="file-upload-label">
                                        Upload PAN card
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="additional_documents" class="form-label">Additional Documents</label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-file-upload file-upload-icon"></i>
                                    <input type="file" id="additional_documents" name="additional_documents" accept=".pdf,.jpg,.jpeg,.png">
                                    <label for="additional_documents" class="file-upload-label">
                                        Upload passport/voter ID/rent agreement
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Banking Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-university"></i>
                            Banking Information
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="bank_name" class="form-label">Account Holder Name</label>
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="Name as per bank account">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="bank_account" class="form-label">Bank Account Number</label>
                                <div class="input-group">
                                    <i class="fas fa-credit-card input-icon"></i>
                                    <input type="text" class="form-control" id="bank_account" name="bank_account" placeholder="Enter account number">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="ifsc_code" class="form-label">IFSC Code</label>
                                <div class="input-group">
                                    <i class="fas fa-code input-icon"></i>
                                    <input type="text" class="form-control" id="ifsc_code" name="ifsc_code" placeholder="Enter IFSC code">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-12">
                                <label for="bank_document" class="form-label">Bank Document</label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-file-upload file-upload-icon"></i>
                                    <input type="file" id="bank_document" name="bank_document" accept=".pdf,.jpg,.jpeg,.png">
                                    <label for="bank_document" class="file-upload-label">
                                        Upload bank passbook or cancelled cheque
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resume Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Resume & Documents
                        </h3>
                        
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="cv" class="form-label">Resume/CV</label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-file-pdf file-upload-icon"></i>
                                    <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx">
                                    <label for="cv" class="file-upload-label">
                                        Upload recent CV/Resume (Optional)
                                    </label>
                                </div>
                                <small class="text-muted">Accepted formats: PDF, DOC, DOCX (Max 5MB)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Register Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('employeeForm');
            const fileInputs = document.querySelectorAll('input[type="file"]');
            
            // File upload enhancement
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const wrapper = this.closest('.file-upload-wrapper');
                    const label = wrapper.querySelector('.file-upload-label');
                    
                    if (this.files.length > 0) {
                        const fileName = this.files[0].name;
                        const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
                        label.innerHTML = `<i class="fas fa-check-circle" style="color: #059669;"></i> ${fileName} (${fileSize} MB)`;
                        wrapper.style.borderColor = '#059669';
                        wrapper.style.backgroundColor = '#ecfdf5';
                    } else {
                        label.innerHTML = label.getAttribute('data-original') || 'Click to upload file';
                        wrapper.style.borderColor = '#d1d5db';
                        wrapper.style.backgroundColor = '#fafafa';
                    }
                });
            });

            // Phone number validation
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            });

            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
                
                // Re-enable button after 5 seconds as fallback
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            });

            // Auto-resize textareas
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });

            // Copy communication address to permanent address
            const commAddress = document.getElementById('communication_address');
            const permAddress = document.getElementById('permanent_address');
            
            commAddress.addEventListener('blur', function() {
                if (permAddress.value === '') {
                    permAddress.value = this.value;
                }
            });

            // Email validation
            const emailInput = document.getElementById('email');
            emailInput.addEventListener('blur', function() {
                const email = this.value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    this.style.borderColor = '#dc2626';
                    this.style.backgroundColor = '#fef2f2';
                } else {
                    this.style.borderColor = '#059669';
                    this.style.backgroundColor = '#ecfdf5';
                }
            });

            // PAN card format validation
            const panInput = document.getElementById('pan_number');
            panInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
                
                if (this.value.length === 10) {
                    if (panRegex.test(this.value)) {
                        this.style.borderColor = '#059669';
                        this.style.backgroundColor = '#ecfdf5';
                    } else {
                        this.style.borderColor = '#dc2626';
                        this.style.backgroundColor = '#fef2f2';
                    }
                }
            });

            // Aadhaar format validation
            const aadhaarInput = document.getElementById('aadhaar_id');
            aadhaarInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                
                if (this.value.length === 12) {
                    this.style.borderColor = '#059669';
                    this.style.backgroundColor = '#ecfdf5';
                } else if (this.value.length > 0) {
                    this.style.borderColor = '#d97706';
                    this.style.backgroundColor = '#fffbeb';
                }
            });
        });

        // Reset form function
        function resetForm() {
            Swal.fire({
                title: 'Reset Form?',
                text: 'All entered data will be lost. Are you sure?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, reset it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('employeeForm').reset();
                    
                    // Reset file upload labels
                    const fileWrappers = document.querySelectorAll('.file-upload-wrapper');
                    fileWrappers.forEach(wrapper => {
                        const label = wrapper.querySelector('.file-upload-label');
                        const input = wrapper.querySelector('input[type="file"]');
                        
                        if (input.id === 'profile_picture') {
                            label.innerHTML = 'Click to upload recent photograph';
                        } else {
                            label.innerHTML = 'Click to upload file';
                        }
                        
                        wrapper.style.borderColor = '#d1d5db';
                        wrapper.style.backgroundColor = '#fafafa';
                    });
                    
                    // Reset input styles
                    const inputs = document.querySelectorAll('.form-control, .form-select');
                    inputs.forEach(input => {
                        input.style.borderColor = '#e5e7eb';
                        input.style.backgroundColor = '#fafafa';
                    });
                    
                    Swal.fire({
                        title: 'Form Reset!',
                        text: 'The form has been cleared successfully.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }

        // Smooth scroll to error fields
        function scrollToError(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.focus();
            }
        }

        // Add animation classes on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe form sections
        document.querySelectorAll('.form-section').forEach(section => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';
            section.style.transition = 'all 0.6s ease-in-out';
            observer.observe(section);
        });
    </script>
</body>
</html>


