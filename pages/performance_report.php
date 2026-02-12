<?php
session_start();
include '../includes/db_connect.php';
include '../includes/auth.php';

// Admin-only access
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /hr-portal/dashboard.php');
    exit();
}

// Get filter parameters
$department = isset($_GET['department']) ? $_GET['department'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get unique departments for filter
$dept_query = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department";
$dept_result = $conn->query($dept_query);

// For charts: get department performance and rating distribution
$chart_dept_query = "SELECT e.department, AVG(pr.rating) as avg_rating
FROM employees e
LEFT JOIN performance_reviews pr
    ON e.employee_id = pr.employee_id
    AND pr.review_date BETWEEN ? AND ?
WHERE e.status = 'Active'";

$chart_dept_params = [$start_date, $end_date];
$chart_dept_types = 'ss';

if (!empty($department)) {
    $chart_dept_query .= " AND e.department = ?";
    $chart_dept_params[] = $department;
    $chart_dept_types .= 's';
}
$chart_dept_query .= " GROUP BY e.department ORDER BY e.department";

$chart_dept_stmt = $conn->prepare($chart_dept_query);
$chart_dept_stmt->bind_param($chart_dept_types, ...$chart_dept_params);
$chart_dept_stmt->execute();
$chart_dept_result = $chart_dept_stmt->get_result();
$dept_labels = [];
$dept_ratings = [];
while ($row = $chart_dept_result->fetch_assoc()) {
    $dept_labels[] = $row['department'] ?: 'Unassigned';
    $avg_rating = $row['avg_rating'];
    $dept_ratings[] = ($avg_rating !== null) ? round($avg_rating, 2) : 0;
}

$chart_rating_query = "SELECT rating, COUNT(*) as count FROM performance_reviews WHERE review_date BETWEEN ? AND ? GROUP BY rating ORDER BY rating";
$chart_rating_stmt = $conn->prepare($chart_rating_query);
$chart_rating_stmt->bind_param('ss', $start_date, $end_date);
$chart_rating_stmt->execute();
$chart_rating_result = $chart_rating_stmt->get_result();
$rating_labels = [];
$rating_counts = [];
while ($row = $chart_rating_result->fetch_assoc()) {
    $rating_labels[] = $row['rating'];
    $rating_counts[] = $row['count'];
}

// Top 5 Performers Query
$top_performers_query = "SELECT
    e.employee_id,
    e.full_name,
    e.department,
    e.profile_picture,
    AVG(pr.rating) as avg_rating,
    COUNT(pr.review_id) as total_reviews
FROM employees e
JOIN performance_reviews pr ON e.employee_id = pr.employee_id
WHERE e.status = 'Active'
  AND pr.review_date BETWEEN ? AND ?";

$top_params = [$start_date, $end_date];
$top_types = 'ss';

if (!empty($department)) {
    $top_performers_query .= " AND e.department = ?";
    $top_params[] = $department;
    $top_types .= 's';
}

$top_performers_query .= " GROUP BY e.employee_id
ORDER BY avg_rating DESC, total_reviews DESC
LIMIT 5";

$top_stmt = $conn->prepare($top_performers_query);
$top_stmt->bind_param($top_types, ...$top_params);
$top_stmt->execute();
$top_performers_result = $top_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Report - HR Portal</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white' font-family='Arial'>👤</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .main-content {
            margin-left: 260px; /* Matches sidebar width */
            padding: 2rem;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .info-card, .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        .rating-badge {
            font-size: 0.9rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
        }
        .performer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid #e9ecef;
        }
        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2>Performance Report</h2>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <?php if ($dept_result) while ($dept = $dept_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                    <?php echo $department === $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>

            <!-- Charts -->
            <div class="row">
                <div class="col-md-6">
                    <div class="info-card">
                        <h5>Department Performance</h5>
                        <div class="chart-container">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h5>Rating Distribution</h5>
                        <div class="chart-container">
                            <canvas id="ratingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top 5 Performers -->
            <div class="row">
                <div class="col-12">
                    <div class="info-card">
                        <h5 class="mb-4"><i class="fas fa-trophy text-warning me-2"></i>Top 5 Performers</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Reviews</th>
                                        <th>Average Rating</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($top_performers_result && $top_performers_result->num_rows > 0):
                                        $rank = 1;
                                        while ($performer = $top_performers_result->fetch_assoc()):
                                            $avatar = !empty($performer['profile_picture']) ? $performer['profile_picture'] : '../assets/images/default-avatar.png';
                                            // Fix path if it starts with ./ or ../
                                            if (strpos($avatar, './') === 0) {
                                                $avatar = str_replace('./', '/', $avatar);
                                                $avatar = '/hr-portal' . $avatar;
                                            } elseif (strpos($avatar, '../') === 0) {
                                                $avatar = str_replace('../', '/', $avatar);
                                                $avatar = '/hr-portal' . $avatar;
                                            }
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank == 1): ?>
                                                    <i class="fas fa-medal text-warning fa-lg"></i>
                                                <?php elseif ($rank == 2): ?>
                                                    <i class="fas fa-medal text-secondary fa-lg"></i>
                                                <?php elseif ($rank == 3): ?>
                                                    <i class="fas fa-medal text-danger fa-lg" style="color: #cd7f32 !important;"></i>
                                                <?php else: ?>
                                                    <span class="fw-bold text-muted ms-1">#<?php echo $rank; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="performer-avatar">
                                                    <span class="fw-bold"><?php echo htmlspecialchars($performer['full_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($performer['department']); ?></span></td>
                                            <td><?php echo $performer['total_reviews']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="rating-badge bg-success text-white me-2">
                                                        <?php echo number_format($performer['avg_rating'], 1); ?>
                                                    </span>
                                                    <div class="text-warning small">
                                                        <?php
                                                        $stars = round($performer['avg_rating']);
                                                        for($i=1; $i<=5; $i++) {
                                                            echo $i <= $stars ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-muted"></i>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary view-reviews-btn"
                                                        data-id="<?php echo $performer['employee_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($performer['full_name']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php
                                        $rank++;
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-chart-bar fa-2x mb-3 opacity-50"></i>
                                                <p class="mb-0">No performance data available for this period.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Reviews Modal -->
    <div class="modal fade" id="reviewsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Performance Reviews - <span id="modalEmployeeName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="reviewsLoader" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="reviewsContent" class="d-none">
                        <!-- Reviews will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Department Performance Chart
        const deptLabels = <?php echo json_encode($dept_labels); ?>;
        const deptRatings = <?php echo json_encode($dept_ratings); ?>;
        const ctxDept = document.getElementById('departmentChart').getContext('2d');
        new Chart(ctxDept, {
            type: 'bar',
            data: {
                labels: deptLabels,
                datasets: [{
                    label: 'Average Rating',
                    data: deptRatings,
                    backgroundColor: '#2563eb',
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, max: 5 }
                }
            }
        });

        // Rating Distribution Chart
        const ratingLabels = <?php echo json_encode($rating_labels); ?>;
        const ratingCounts = <?php echo json_encode($rating_counts); ?>;
        const ctxRating = document.getElementById('ratingChart').getContext('2d');
        new Chart(ctxRating, {
            type: 'pie',
            data: {
                labels: ratingLabels,
                datasets: [{
                    data: ratingCounts,
                    backgroundColor: ['#059669', '#2563eb', '#f59e0b', '#dc2626', '#64748b'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    title: { display: false }
                }
            }
        });

        // Handle View Reviews Modal
        document.addEventListener('DOMContentLoaded', function() {
            const modal = new bootstrap.Modal(document.getElementById('reviewsModal'));
            const buttons = document.querySelectorAll('.view-reviews-btn');
            const loader = document.getElementById('reviewsLoader');
            const content = document.getElementById('reviewsContent');
            const nameSpan = document.getElementById('modalEmployeeName');

            buttons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');

                    nameSpan.textContent = name;
                    loader.classList.remove('d-none');
                    content.classList.add('d-none');
                    modal.show();

                    // Fetch reviews
                    fetch(`../api/get_employee_reviews.php?id=${id}`)
                        .then(response => response.json())
                        .then(data => {
                            loader.classList.add('d-none');
                            content.classList.remove('d-none');

                            if (data.reviews && data.reviews.length > 0) {
                                let html = '<div class="list-group">';
                                data.reviews.forEach(review => {
                                    let stars = '';
                                    for(let i=1; i<=5; i++) {
                                        stars += i <= review.rating ? '<i class="fas fa-star text-warning"></i>' : '<i class="far fa-star text-muted"></i>';
                                    }

                                    html += `
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">Review by ${review.reviewer}</h6>
                                                <small class="text-muted">${review.date}</small>
                                            </div>
                                            <div class="mb-2">${stars}</div>
                                            <p class="mb-1">${review.comments}</p>
                                        </div>
                                    `;
                                });
                                html += '</div>';
                                content.innerHTML = html;
                            } else {
                                content.innerHTML = '<div class="text-center text-muted py-3">No reviews found for this employee.</div>';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            loader.classList.add('d-none');
                            content.classList.remove('d-none');
                            content.innerHTML = '<div class="alert alert-danger">Error loading reviews.</div>';
                        });
                });
            });
        });
    </script>
</body>
</html>