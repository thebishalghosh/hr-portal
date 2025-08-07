<?php
include '../includes/db_connect.php';
include '../includes/auth.php';

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = intval($_POST['employee_id']);
    $rating = intval($_POST['rating']);
    $comments = trim($_POST['comments']);
    $review_date = date('Y-m-d');
    $reviewer_id = $_SESSION['user_id'];

    // Validate rating (1-5)
    if ($rating < 1 || $rating > 5) {
        $error = "Rating must be between 1 and 5";
    } else {
        // Insert review into database
        $query = "INSERT INTO performance_reviews (employee_id, reviewer_id, review_date, comments, rating)
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iissi", $employee_id, $reviewer_id, $review_date, $comments, $rating);
        
        if ($stmt->execute()) {
            $success = "Performance review submitted successfully";
        } else {
            $error = "Error submitting review: " . $conn->error;
        }
    }
}

// Get all employees for the dropdown
$employees_query = "SELECT employee_id, full_name, department FROM employees WHERE status = 'Active' ORDER BY full_name";
$employees_result = $conn->query($employees_query);

// Get total count of reviews for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM performance_reviews pr
    JOIN employees e ON pr.employee_id = e.employee_id
    JOIN employees r ON pr.reviewer_id = r.employee_id";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get existing reviews with pagination
$reviews_query = "
    SELECT 
        pr.*,
        e.full_name as employee_name,
        e.department,
        r.full_name as reviewer_name
    FROM performance_reviews pr
    JOIN employees e ON pr.employee_id = e.employee_id
    JOIN employees r ON pr.reviewer_id = r.employee_id
    ORDER BY pr.review_date DESC
    LIMIT ? OFFSET ?";
$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("ii", $records_per_page, $offset);
$stmt->execute();
$reviews_result = $stmt->get_result();

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $review_id = intval($_POST['review_id']);
    $rating = intval($_POST['rating']);
    $comments = trim($_POST['comments']);
    
    // Validate rating (1-5)
    if ($rating < 1 || $rating > 5) {
        $error = "Rating must be between 1 and 5";
    } else {
        // Update review in database
        $query = "UPDATE performance_reviews SET rating = ?, comments = ? WHERE review_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isi", $rating, $comments, $review_id);
        
        if ($stmt->execute()) {
            $success = "Performance review updated successfully";
        } else {
            $error = "Error updating review: " . $conn->error;
        }
    }
}
// Handle delete form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $review_id = intval($_POST['review_id']);
    
    // Delete review from database
    $query = "DELETE FROM performance_reviews WHERE review_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $review_id);
    
    if ($stmt->execute()) {
        $success = "Performance review deleted successfully";
    } else {
        $error = "Error deleting review: " . $conn->error;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Reviews</title>
    <!-- Add favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white' font-family='Arial'>ðŸ‘¤</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        /* Classy review card styles */
        .review-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            border-left: 5px solid #007bff;
            margin-bottom: 24px;
            transition: box-shadow 0.2s;
        }
        .review-card:hover {
            box-shadow: 0 6px 24px rgba(0,0,0,0.13);
        }
        .review-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }
        .review-employee {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .review-department {
            background: #e3eafc;
            color: #1a237e;
            border-radius: 8px;
            padding: 2px 10px;
            font-size: 0.85rem;
            margin-left: 8px;
        }
        .review-meta {
            color: #6c757d;
            font-size: 0.95rem;
        }
        .review-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .review-comments {
            margin-top: 10px;
            font-size: 1.05rem;
            color: #333;
        }
        .review-actions {
            margin-left: 10px;
        }
        .pagination-info {
            color: #6c757d;
            font-size: 0.9em;
        }
        .pagination .page-link {
            color: #007bff;
        }
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }
        @media (max-width: 767.98px) {
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            .review-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }
            .review-actions {
                margin-left: 0;
                margin-top: 8px;
            }
        }
        .review-actions .btn {
            margin-right: 5px;
        }
        
        .review-actions .btn:last-child {
            margin-right: 0;
        }

    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <h2>Performance Reviews</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <!-- New Review Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Submit New Review</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="employee_id" class="form-label">Employee</label>
                                        <select class="form-select" id="employee_id" name="employee_id" required>
                                            <option value="">Select Employee</option>
                                            <?php while ($employee = $employees_result->fetch_assoc()): ?>
                                                <option value="<?php echo $employee['employee_id']; ?>">
                                                    <?php echo htmlspecialchars($employee['full_name']); ?>
                                                     (<?php echo htmlspecialchars($employee['department']); ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="rating" class="form-label">Rating</label>
                                        <select class="form-select" id="rating" name="rating" required>
                                            <option value="">Select Rating</option>
                                            <option value="1">1 - Poor</option>
                                            <option value="2">2 - Fair</option>
                                            <option value="3">3 - Good</option>
                                            <option value="4">4 - Very Good</option>
                                            <option value="5">5 - Excellent</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="comments" class="form-label">Comments</label>
                                    <textarea class="form-control" id="comments" name="comments" rows="4" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Submit Review</button>
                            </form>
                        </div>
                    </div>

                    <!-- Existing Reviews -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Previous Reviews</h5>
                            <?php if ($total_records > 0): ?>
                                <div class="pagination-info">
                                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $records_per_page, $total_records); ?> 
                                    of <?php echo $total_records; ?> reviews
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($reviews_result->num_rows === 0): ?>
                                <p class="text-muted">No performance reviews available.</p>
                            <?php else: ?>
                                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                                    <div class="review-card p-3">
                                        <div class="review-header">
                                            <div>
                                                <span class="review-employee"><?php echo htmlspecialchars($review['employee_name']); ?></span>
                                                <span class="review-department"><?php echo htmlspecialchars($review['department']); ?></span>
                                            </div>
                                            <div class="d-flex align-items-center flex-wrap">
                                                <span class="review-meta me-3">
                                                    Reviewed by <b><?php echo htmlspecialchars($review['reviewer_name']); ?></b>
                                                    on <?php echo date('d M Y', strtotime($review['review_date'])); ?>
                                                </span>
                                                <div class="review-actions">
                                                    <button class="btn btn-sm btn-outline-primary me-2" onclick="editReview(<?php echo $review['review_id']; ?>, <?php echo $review['rating']; ?>, '<?php echo addslashes($review['comments']); ?>')">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $review['review_id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </div>

                                            </div>
                                        </div>
                                        <div class="review-stars mt-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-comments">
                                            <?php echo nl2br(htmlspecialchars($review['comments'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Reviews pagination" class="mt-4">
                                        <ul class="pagination justify-content-center">
                                            <!-- Previous button -->
                                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>

                                            <!-- Page numbers -->
                                            <?php
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);
                                            
                                            // Show first page if not in range
                                            if ($start_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=1">1</a>
                                                </li>
                                                <?php if ($start_page > 2): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- Current page range -->
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <!-- Show last page if not in range -->
                                            <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                                </li>
                                            <?php endif; ?>

                                            <!-- Next button -->
                                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Review Modal -->
    <div class="modal fade" id="editReviewModal" tabindex="-1" aria-labelledby="editReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editReviewModalLabel">Edit Performance Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="review_id" id="edit_review_id">
                        
                        <div class="mb-3">
                            <label for="edit_rating" class="form-label">Rating</label>
                            <select class="form-select" id="edit_rating" name="rating" required>
                                <option value="1">1 - Poor</option>
                                <option value="2">2 - Fair</option>
                                <option value="3">3 - Good</option>
                                <option value="4">4 - Very Good</option>
                                <option value="5">5 - Excellent</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_comments" class="form-label">Comments</label>
                            <textarea class="form-control" id="edit_comments" name="comments" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteReviewModal" tabindex="-1" aria-labelledby="deleteReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteReviewModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this performance review? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="review_id" id="delete_review_id">
                        <button type="submit" class="btn btn-danger">Delete Review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <script>
    function editReview(reviewId, rating, comments) {
        document.getElementById('edit_review_id').value = reviewId;
        document.getElementById('edit_rating').value = rating;
        document.getElementById('edit_comments').value = comments;
        
        var editModal = new bootstrap.Modal(document.getElementById('editReviewModal'));
        editModal.show();
    }

    // Optional: Add page size selector
    function changePageSize(pageSize) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('per_page', pageSize);
        urlParams.delete('page'); // Reset to first page when changing page size
        window.location.search = urlParams.toString();
    }
    function confirmDelete(reviewId) {
        document.getElementById('delete_review_id').value = reviewId;
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteReviewModal'));
        deleteModal.show();
    }

    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
