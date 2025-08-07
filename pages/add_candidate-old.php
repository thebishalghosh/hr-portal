<?php
include '../includes/auth.php';

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /hr-portal/dashboard.php');
    exit();
}

// Include the database connection file
include('../includes/db_connect.php');
include('../includes/sidebar.php');

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

        $sql = "INSERT INTO employees (full_name, email, password_hash, mobile_number, alternate_number, gender, dob, communication_address, permanent_address, emergency_contact_number, emergency_contact_person, adhaar_id_number, pan_card_number, bank_account_name, bank_account_number, ifsc_code, profile_picture, adhaar_front, adhaar_back, pan_card, additional_document, bank_document, cv_file, hire_date, status, created_at, updated_at, role, department)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp(), 'Active', current_timestamp(), current_timestamp(), ?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                'sssssssssssssssssssssssss', 
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
                $department
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

    $conn->close();
}
?>
<!-- 
<!DOCTYPE html>
<html lang="en">
<head>
    </head>
<body>
    <?php if (!empty($message)): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.0/dist/sweetalert2.all.min.js"></script>
        <script>
            Swal.fire({
                title: '<?= strpos($message, 'Error') === false ? 'Success' : 'Error' ?>',
                text: '<?= $message ?>',
                icon: '<?= strpos($message, 'Error') === false ? 'success' : 'error' ?>',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location = "add_candidate.php"; 
                }
            });
        </script>
    <?php endif; ?>

    </body>
</html> -->



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration Form</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.0/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
    .content-wrapper {
        flex-grow: 1;
        padding: 20px;
        margin-left: 270px; /* Sidebar width + some padding */
    }


    .form-container {
        max-width: 950px;
        margin: 20px auto;
        background-color: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    }

    h2 {
        text-align: center;
        color: #333;
        margin-bottom: 30px;
    }

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="date"],
    select {
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 14px;
    }

    button {
        width: 100%;
        padding: 12px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
    }

    button:hover {
        background-color: #0056b3;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .form-container {
            padding: 20px;
        }
    }

    </style>
</head>
<body>
<?php if (!empty($message)): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.0/dist/sweetalert2.all.min.js"></script>
    <script>
        Swal.fire({
            title: '<?= strpos($message, 'Error') === false ? 'Success' : 'Error' ?>',
            text: '<?= $message ?>',
            icon: '<?= strpos($message, 'Error') === false ? 'success' : 'error' ?>',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location = "add_candidate.php"; // Redirect to the form page after confirmation
            }
        });
    </script>
<?php endif; ?>
<div class="content-wrapper">
<div class="form-container">
    <h2 class="text-center">Employee Registration Form</h2>
    <form action="add_candidate.php" method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="full_name" class="form-label required">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="profile_picture" class="form-label required">Upload Your Recent Photograph</label>
                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" required>
                </div>
            </div>
        </div>
        <!-- Adding the Role and Department dropdown -->
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="role" class="form-label required">Select Role</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="user">Employee</option>
                        <option value="user">Intern</option>
                        <!-- Add more roles as needed -->
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="department" class="form-label required">Select Department</label>
                    <select class="form-control" id="department" name="department" required>
                        <option value="">Select Department</option>
                        <option value="Technical">Technical</option>
                        <option value="Marketing">Marketing</option>
                        <!-- Add more departments if necessary -->
                    </select>
                </div>
            </div>
        </div>


        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="email" class="form-label required">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="mobile_number" class="form-label required">Mobile Number</label>
                    <input type="tel" class="form-control" id="mobile_number" name="mobile_number" maxlength="10" required>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="alternate_number" class="form-label">Alternate Number</label>
                    <input type="tel" class="form-control" id="alternate_number" name="alternate_number" maxlength="10">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="gender" class="form-label required">Select Your Gender</label>
                    <select class="form-control" id="gender" name="gender" required>
                        <option value="">Select Your Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="dob" class="form-label required">Enter Your Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="communication_address" class="form-label required">Communication Address</label>
                    <textarea class="form-control" id="communication_address" name="communication_address" rows="2" required></textarea>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="permanent_address" class="form-label required">Permanent Address</label>
                    <textarea class="form-control" id="permanent_address" name="permanent_address" rows="2" required></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="emergency_contact" class="form-label required">Emergency Contact Number</label>
                    <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" maxlength="10" required>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="emergency_contact_person" class="form-label required">Name of Emergency Contact Person</label>
                    <input type="text" class="form-control" id="emergency_contact_person" name="emergency_contact_person" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="aadhaar_id" class="form-label required">Enter Aadhaar ID Number</label>
                    <input type="text" class="form-control" id="aadhaar_id" name="aadhaar_id">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="aadhaar_front" class="form-label">Upload Aadhaar Card [Front Side - Bio Page]</label>
                    <input type="file" class="form-control" id="aadhaar_front" name="aadhaar_front">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="aadhaar_back" class="form-label">Upload Aadhaar Card [Back Side - Address Info]</label>
                    <input type="file" class="form-control" id="aadhaar_back" name="aadhaar_back">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="pan_number" class="form-label">PAN Card Number</label>
                    <input type="text" class="form-control" id="pan_number" name="pan_number">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="pan_card" class="form-label">Upload PAN Card [Front Side Only]</label>
                    <input type="file" class="form-control" id="pan_card" name="pan_card">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="mb-3">
                    <label for="additional_documents" class="form-label">Upload Additional Document [Passport / Voter ID / Rent Agreement]</label>
                    <input type="file" class="form-control" id="additional_documents" name="additional_documents">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="bank_name" class="form-label">Name as Per Bank Account</label>
                    <input type="text" class="form-control" id="bank_name" name="bank_name">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="bank_account" class="form-label">Bank Account Number</label>
                    <input type="text" class="form-control" id="bank_account" name="bank_account">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="ifsc_code" class="form-label">Bank IFSC Code</label>
                    <input type="text" class="form-control" id="ifsc_code" name="ifsc_code">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="bank_document" class="form-label">Upload Bank Passbook or Cancelled Cheque</label>
                    <input type="file" class="form-control" id="bank_document" name="bank_document">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="mb-3">
                    <label for="cv" class="form-label">Upload Recent CV / Cover Letter / Resume (Optional)</label>
                    <input type="file" class="form-control" id="cv" name="cv">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
</div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
