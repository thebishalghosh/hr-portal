<?php
session_start();
include '../includes/auth.php';
include '../includes/exam_api_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM employees WHERE employee_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// --- API Call to Fetch Exams ---
$assigned_exams = [];
$candidate_email = isset($user['email']) ? $user['email'] : '';
$user_session_token = isset($_SESSION['session_token']) ? $_SESSION['session_token'] : '';

if (!empty($candidate_email)) {
    $assigned_exams = getAssignedExams($candidate_email, $user_session_token);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exams - HR Portal</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white' font-family='Arial'>👤</text></svg>">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">

    <style>
        :root {
            --primary-color: #4f46e5; /* Indigo 600 */
            --primary-light: #e0e7ff; /* Indigo 100 */
            --secondary-color: #64748b; /* Slate 500 */
            --success-color: #10b981; /* Emerald 500 */
            --warning-color: #f59e0b; /* Amber 500 */
            --danger-color: #ef4444; /* Red 500 */
            --bg-color: #f3f4f6; /* Gray 100 */
            --card-bg: #ffffff;
            --text-main: #111827; /* Gray 900 */
            --text-muted: #6b7280; /* Gray 500 */
            --border-radius: 1rem;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-weight: 700;
            font-size: 1.75rem;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }

        /* Exam Cards */
        .exam-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            height: 100%;
            border: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .exam-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .exam-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .exam-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.5;
        }

        .exam-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            border-top: 1px solid #f3f4f6;
            padding-top: 1rem;
        }

        .exam-meta i {
            color: var(--primary-color);
        }

        .exam-status {
            margin-bottom: 1rem;
        }

        .btn-start {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }

        .btn-start:hover {
            background-color: #4338ca; /* Indigo 700 */
            color: white;
        }

        .btn-disabled {
            background-color: var(--secondary-color);
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/hr-portal/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">My Assigned Exams</h1>
            <p class="page-subtitle">View and complete your pending assessments.</p>
        </div>

        <!-- Exam Grid -->
        <?php if (!empty($assigned_exams)): ?>
            <div class="row g-4">
                <?php foreach ($assigned_exams as $exam): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="exam-card">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="exam-title mb-0"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                    <?php
                                    $status = isset($exam['status']) ? strtolower($exam['status']) : 'assigned';
                                    $badgeClass = 'bg-secondary';
                                    if ($status === 'completed') $badgeClass = 'bg-success';
                                    elseif ($status === 'pending' || $status === 'assigned') $badgeClass = 'bg-warning text-dark';
                                    elseif ($status === 'disqualified') $badgeClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> rounded-pill">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>

                                <?php if (!empty($exam['description'])): ?>
                                    <p class="exam-description" title="<?php echo htmlspecialchars($exam['description']); ?>">
                                        <?php echo htmlspecialchars($exam['description']); ?>
                                    </p>
                                <?php else: ?>
                                    <p class="exam-description text-muted fst-italic">No description available.</p>
                                <?php endif; ?>

                                <div class="exam-meta">
                                    <span><i class="far fa-clock me-1"></i><?php echo htmlspecialchars($exam['duration']); ?> mins</span>
                                    <?php if (isset($exam['score']) && $status === 'completed'): ?>
                                        <span><i class="fas fa-star me-1"></i>Score: <?php echo htmlspecialchars($exam['score']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($status === 'completed' || $status === 'disqualified'): ?>
                                <button class="btn btn-secondary btn-sm w-100 disabled" disabled>
                                    <?php echo ($status === 'completed') ? 'Completed' : 'Unavailable'; ?>
                                </button>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($exam['start_link']); ?>" class="btn btn-primary btn-sm w-100" target="_blank">
                                    Start Exam <i class="fas fa-external-link-alt ms-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h3>No Exams Assigned</h3>
                <p class="text-muted">You currently have no pending exams. Check back later!</p>
            </div>
        <?php endif; ?>

    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
