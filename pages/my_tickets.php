<?php
session_start();
include '../includes/db_connect.php';
include '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['user_id'];

// Fetch user's tickets
$sql = "SELECT * FROM support_tickets WHERE employee_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

// Store results in an array to safely check if it's empty
$tickets = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - HR Portal</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">

    <style>
        .content {
            margin-left: 250px;
            padding: 20px;
        }

        .page-title {
            font-family: 'Arial', sans-serif;
            color: #333;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .ticket-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }

        .ticket-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
        }

        /* Status Badges */
        .badge-Open { background-color: #ffc107; color: #000; }
        .badge-InProgress { background-color: #17a2b8; color: #fff; }
        .badge-OnHold { background-color: #6c757d; color: #fff; }
        .badge-Resolved { background-color: #28a745; color: #fff; }
        .badge-Rejected { background-color: #dc3545; color: #fff; }

        /* Priority Badges */
        .priority-Low { color: #28a745; }
        .priority-Medium { color: #ffc107; }
        .priority-High { color: #fd7e14; }
        .priority-Critical { color: #dc3545; font-weight: bold; }

        .table th { background-color: #f8f9fa; }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 15px; }
        }
    </style>
</head>
<body>

    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="page-title"><i class="fas fa-list-ul me-2 text-primary"></i>My Support Tickets</h4>
            <a href="/hr-portal/pages/create_ticket.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Ticket
            </a>
        </div>

        <div class="card ticket-card">
            <div class="card-body">
                <?php if (!empty($tickets)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $row):
                                    $ticket_code = $row['ticket_code'] ?? 'N/A';
                                    $subject = $row['subject'] ?? '';
                                    $issue_type = $row['issue_type'] ?? 'Unknown';
                                    $priority = $row['priority'] ?? 'Low';
                                    $status = $row['status'] ?? 'Open';
                                    $created_at = $row['created_at'] ?? date('Y-m-d H:i:s');
                                    $description = $row['description'] ?? '';
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ticket_code); ?></strong></td>
                                        <td><?php echo htmlspecialchars((strlen($subject) > 30) ? substr($subject, 0, 30) . '...' : $subject); ?></td>
                                        <td><?php echo htmlspecialchars($issue_type); ?></td>
                                        <td class="priority-<?php echo str_replace(' ', '', $priority); ?>">
                                            <i class="fas fa-circle" style="font-size: 0.6rem; vertical-align: middle; margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($priority); ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($created_at)); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo str_replace(' ', '', $status); ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info text-white view-ticket"
                                                    data-bs-toggle="modal" data-bs-target="#ticketModal"
                                                    data-code="<?php echo htmlspecialchars($ticket_code); ?>"
                                                    data-subject="<?php echo htmlspecialchars($subject); ?>"
                                                    data-desc="<?php echo htmlspecialchars($description); ?>"
                                                    data-type="<?php echo htmlspecialchars($issue_type); ?>"
                                                    data-priority="<?php echo htmlspecialchars($priority); ?>"
                                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                                    data-date="<?php echo date('M d, Y H:i', strtotime($created_at)); ?>"
                                                    data-notes="<?php echo htmlspecialchars($row['admin_notes'] ?? 'No notes yet.'); ?>"
                                                    data-attachment="<?php echo htmlspecialchars($row['attachment'] ?? ''); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3 opacity-50"></i>
                        <h5 class="text-muted">No tickets found</h5>
                        <p class="text-muted">You haven't raised any support tickets yet.</p>
                        <a href="/hr-portal/pages/create_ticket.php" class="btn btn-outline-primary mt-2">Raise a Ticket</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ticket Details Modal -->
    <div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="ticketModalLabel">
                        <span id="m_code" class="text-primary fw-bold"></span>: <span id="m_subject"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Category</p>
                            <p class="fw-bold" id="m_type"></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Status</p>
                            <p id="m_status_container"></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Priority</p>
                            <p class="fw-bold" id="m_priority"></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Created On</p>
                            <p class="fw-bold" id="m_date"></p>
                        </div>
                    </div>

                    <hr>
                    <div class="mb-3">
                        <p class="mb-1 text-muted small">Description</p>
                        <div class="p-3 bg-light rounded" style="white-space: pre-wrap;" id="m_desc"></div>
                    </div>

                    <div id="m_attachment_container" class="mb-3" style="display: none;">
                        <p class="mb-1 text-muted small">Attachment</p>
                        <a id="m_attachment_link" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-paperclip me-1"></i> View Attachment
                        </a>
                    </div>

                    <hr>
                    <div>
                        <p class="mb-1 text-muted small"><i class="fas fa-comment-dots me-1"></i> Admin Notes</p>
                        <div class="p-3 bg-light border-start border-4 border-info rounded" style="white-space: pre-wrap;" id="m_notes"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.view-ticket').click(function() {
                // Populate Modal Data
                $('#m_code').text($(this).data('code'));
                $('#m_subject').text($(this).data('subject'));
                $('#m_desc').text($(this).data('desc'));
                $('#m_type').text($(this).data('type'));
                $('#m_priority').text($(this).data('priority'));
                $('#m_date').text($(this).data('date'));
                $('#m_notes').text($(this).data('notes'));

                // Status Badge styling in modal
                let status = $(this).data('status');
                let badgeClass = 'badge-' + status.replace(/\s+/g, '');
                $('#m_status_container').html(`<span class="badge ${badgeClass} fs-6">${status}</span>`);

                // Handle Attachment Link
                let attachment = $(this).data('attachment');
                if(attachment && attachment !== '') {
                    $('#m_attachment_link').attr('href', '/hr-portal/' + attachment);
                    $('#m_attachment_container').show();
                } else {
                    $('#m_attachment_container').hide();
                }
            });
        });
    </script>
</body>
</html>