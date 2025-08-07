<?php
include '../includes/auth.php';
include '../includes/db_connect.php';
include '../includes/sidebar.php';

$user_id = $_SESSION['user_id'];

// Fetch user details
$sql = "SELECT * FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Handle file uploads
        $uploadDir = '../uploads/';
        
        // Profile picture
        if (!empty($_FILES['profile_picture']['name'])) {
            $profilePath = handleFileUpload($_FILES['profile_picture'], $allowedTypes, $maxFileSize, $uploadDir, 'profile');
            if ($profilePath) {
                // Delete old profile picture if exists
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
                $user['profile_picture'] = $profilePath;
            }
        }

        // Resume/CV
        if (!empty($_FILES['cv_file']['name'])) {
            $cvPath = handleFileUpload($_FILES['cv_file'], $allowedTypes, $maxFileSize, $uploadDir, 'resume');
            if ($cvPath) {
                // Delete old CV if exists
                if (!empty($user['cv_file']) && file_exists($user['cv_file'])) {
                    unlink($user['cv_file']);
                }
                $user['cv_file'] = $cvPath;
            }
        }

        // Update user details
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
                cv_file = ? 
                WHERE employee_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssssssssi",
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
            $user['profile_picture'],
            $user['cv_file'],
            $user_id
        );

        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
        } else {
            throw new Exception("Error updating profile: " . $stmt->error);
        }

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

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

// Get attendance summary
$attendance_query = "
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_days
    FROM attendance 
    WHERE employee_id = ? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$attendance_summary = $stmt->get_result()->fetch_assoc();

// Initialize attendance summary if no records found
if (!$attendance_summary) {
    $attendance_summary = [
        'total_days' => 0,
        'present_days' => 0,
        'absent_days' => 0,
        'leave_days' => 0
    ];
}

// Get task summary
$task_query = "
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.status = 'Overdue' THEN 1 ELSE 0 END) as overdue_tasks,
        SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks
    FROM task_assignments ta
    JOIN tasks t ON ta.task_id = t.task_id
    WHERE ta.employee_id = ?
";
$stmt = $conn->prepare($task_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$task_summary = $stmt->get_result()->fetch_assoc();

// Initialize task summary if no records found
if (!$task_summary) {
    $task_summary = [
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'overdue_tasks' => 0,
        'in_progress_tasks' => 0
    ];
}

// Get leave summary
$leave_query = "
    SELECT 
        COUNT(*) as total_leaves,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_leaves,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_leaves,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_leaves
    FROM leaves 
    WHERE employee_id = ?
";
$stmt = $conn->prepare($leave_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$leave_summary = $stmt->get_result()->fetch_assoc();

// Initialize leave summary if no records found
if (!$leave_summary) {
    $leave_summary = [
        'total_leaves' => 0,
        'approved_leaves' => 0,
        'pending_leaves' => 0,
        'rejected_leaves' => 0
    ];
}

// Get performance reviews
$reviews_query = "
    SELECT 
        pr.*,
        e.full_name as reviewer_name
    FROM performance_reviews pr
    LEFT JOIN employees e ON pr.reviewer_id = e.employee_id
    WHERE pr.employee_id = ?
    ORDER BY pr.review_date DESC
";
$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .profile-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .document-icon {
            font-size: 2rem;
            color: #6c757d;
        }
        .review-card {
            border-left: 4px solid #007bff;
            margin-bottom: 15px;
        }
        .rating {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <?php
                        $profilePicPath = !empty($user['profile_picture']) ? 
                            (strpos($user['profile_picture'], 'http') === 0 ? 
                                $user['profile_picture'] : 
                                '/hr-portal/' . ltrim($user['profile_picture'], './')) : 
                            '/hr-portal/assets/images/default-profile.png';
                        ?>
                        <img src="<?php echo htmlspecialchars($profilePicPath); ?>" 
                             alt="Profile Picture" class="profile-picture">
                    </div>
                    <div class="col-md-10">
                        <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                        <p class="text-muted">
                            <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($user['department'] ?? 'Not assigned'); ?> |
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?> |
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['mobile_number'] ?? 'Not provided'); ?>
                        </p>
                        <div class="d-flex gap-2">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($user['role'] ?? 'Employee'); ?></span>
                            <span class="badge <?php echo ($user['status'] ?? 'Active') === 'Active' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo htmlspecialchars($user['status'] ?? 'Active'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <!-- Attendance Stats -->
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $attendance_summary['present_days']; ?>/<?php echo $attendance_summary['total_days']; ?></div>
                        <div class="stat-label">Present Days (Last 30 Days)</div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" style="width: <?php echo $attendance_summary['total_days'] > 0 ? ($attendance_summary['present_days'] / $attendance_summary['total_days']) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Task Stats -->
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $task_summary['completed_tasks']; ?>/<?php echo $task_summary['total_tasks']; ?></div>
                        <div class="stat-label">Completed Tasks</div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-primary" style="width: <?php echo $task_summary['total_tasks'] > 0 ? ($task_summary['completed_tasks'] / $task_summary['total_tasks']) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Leave Stats -->
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $leave_summary['approved_leaves']; ?></div>
                        <div class="stat-label">Approved Leaves</div>
                        <div class="small text-muted">
                            Pending: <?php echo $leave_summary['pending_leaves']; ?> |
                            Rejected: <?php echo $leave_summary['rejected_leaves']; ?>
                        </div>
                    </div>
                </div>

                <!-- Performance Stats -->
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            $total_rating = 0;
                            $review_count = 0;
                            while ($review = $reviews->fetch_assoc()) {
                                $total_rating += $review['rating'];
                                $review_count++;
                            }
                            $reviews->data_seek(0);
                            echo $review_count > 0 ? round($total_rating / $review_count, 1) : 'N/A';
                            ?>
                        </div>
                        <div class="stat-label">Average Performance Rating</div>
                        <div class="rating">
                            <?php 
                            $avg_rating = $review_count > 0 ? $total_rating / $review_count : 0;
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $avg_rating ? '<i class="fas fa-star"></i>' : 
                                     ($i - 0.5 <= $avg_rating ? '<i class="fas fa-star-half-alt"></i>' : 
                                     '<i class="far fa-star"></i>');
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Information -->
            <div class="row mt-4">
                <!-- Personal Information -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Date of Birth:</strong> <?php echo !empty($user['dob']) ? date('d M Y', strtotime($user['dob'])) : 'Not provided'; ?></p>
                                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($user['gender'] ?? 'Not specified'); ?></p>
                                    <p><strong>Hire Date:</strong> <?php echo !empty($user['hire_date']) ? date('d M Y', strtotime($user['hire_date'])) : 'Not provided'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($user['emergency_contact_person'] ?? 'Not provided'); ?></p>
                                    <p><strong>Emergency Number:</strong> <?php echo htmlspecialchars($user['emergency_contact_number'] ?? 'Not provided'); ?></p>
                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($user['department'] ?? 'Not assigned'); ?></p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <h6>Address</h6>
                                <p><?php echo !empty($user['communication_address']) ? nl2br(htmlspecialchars($user['communication_address'])) : 'Not provided'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Documents</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($user['adhaar_front'])): ?>
                                <div class="col-md-4 text-center mb-3">
                                    <i class="fas fa-id-card document-icon"></i>
                                    <p class="mb-0">Aadhaar Card</p>
                                    <a href="<?php echo htmlspecialchars($user['adhaar_front']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">View</a>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($user['pan_card'])): ?>
                                <div class="col-md-4 text-center mb-3">
                                    <i class="fas fa-file-alt document-icon"></i>
                                    <p class="mb-0">PAN Card</p>
                                    <a href="<?php echo htmlspecialchars($user['pan_card']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">View</a>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($user['bank_document'])): ?>
                                <div class="col-md-4 text-center mb-3">
                                    <i class="fas fa-university document-icon"></i>
                                    <p class="mb-0">Bank Details</p>
                                    <a href="<?php echo htmlspecialchars($user['bank_document']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">View</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Reviews -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Performance Reviews</h5>
                </div>
                <div class="card-body">
                    <?php if ($reviews->num_rows === 0): ?>
                        <p class="text-muted">No performance reviews available.</p>
                    <?php else: ?>
                        <?php while ($review = $reviews->fetch_assoc()): ?>
                            <div class="review-card p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Review by <?php echo htmlspecialchars($review['reviewer_name']); ?></h6>
                                    <span class="text-muted small"><?php echo date('d M Y', strtotime($review['review_date'])); ?></span>
                                </div>
                                <div class="rating mt-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($review['comments'])); ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 