<?php
include '../includes/db_connect.php';
include '../includes/auth.php';

header('Content-Type: application/json');

// Check Admin Role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid Employee ID']);
    exit();
}

$employee_id = $_GET['id'];

// Fetch reviews
$sql = "SELECT pr.review_date, pr.rating, pr.comments, e.full_name as reviewer_name
        FROM performance_reviews pr
        LEFT JOIN employees e ON pr.reviewer_id = e.employee_id
        WHERE pr.employee_id = ?
        ORDER BY pr.review_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = [
        'date' => date('d M Y', strtotime($row['review_date'])),
        'reviewer' => $row['reviewer_name'] ?? 'Unknown',
        'rating' => $row['rating'],
        'comments' => $row['comments']
    ];
}

echo json_encode(['reviews' => $reviews]);
?>