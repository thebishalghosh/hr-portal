<?php
session_start();
include '../includes/db_connect.php';
include '../includes/auth.php';

// Ensure user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: /hr-portal/dashboard.php");
    exit();
}

// Fetch all tickets with employee details
$sql = "SELECT st.*, e.full_name, e.email
        FROM support_tickets st
        JOIN employees e ON st.employee_id = e.employee_id
        ORDER BY
            CASE
                WHEN st.status = 'Open' THEN 1
                WHEN st.status = 'In Progress' THEN 2
                WHEN st.status = 'On Hold' THEN 3
                ELSE 4
            END,
            st.created_at DESC";
$result = $conn->query($sql);

// Store results in an array to safely check if it's empty
$tickets = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}

// Fetch admins for the "Assign To" dropdown
$admins_sql = "SELECT employee_id, full_name FROM employees WHERE role = 'admin' AND status = 'Active'";
$admins_result = $conn->query($admins_sql);
$admin_list = [];
while($admin = $admins_result->fetch_assoc()) {
    $admin_list[] = $admin;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Support Tickets - HR Portal</title>

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
            <h4 class="page-title"><i class="fas fa-tasks me-2 text-primary"></i>Manage Support Tickets</h4>
        </div>

        <div class="card ticket-card">
            <div class="card-body">
                <?php if (!empty($tickets)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Employee</th>
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
                                    // Make sure we have fallbacks for potentially null values
                                    $ticket_code = $row['ticket_code'] ?? 'N/A';
                                    $full_name = $row['full_name'] ?? 'Unknown';
                                    $email = $row['email'] ?? 'No email';
                                    $subject = $row['subject'] ?? '';
                                    $issue_type = $row['issue_type'] ?? 'Unknown';
                                    $priority = $row['priority'] ?? 'Low';
                                    $status = $row['status'] ?? 'Open';
                                    $created_at = $row['created_at'] ?? date('Y-m-d H:i:s');
                                    $description = $row['description'] ?? '';
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ticket_code); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($full_name); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($email); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars((strlen($subject) > 25) ? substr($subject, 0, 25) . '...' : $subject); ?></td>
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
                                            <button type="button" class="btn btn-sm btn-primary text-white update-ticket"
                                                    data-bs-toggle="modal" data-bs-target="#updateModal"
                                                    data-id="<?php echo $row['ticket_id'] ?? 0; ?>"
                                                    data-code="<?php echo htmlspecialchars($ticket_code); ?>"
                                                    data-emp="<?php echo htmlspecialchars($full_name); ?>"
                                                    data-subject="<?php echo htmlspecialchars($subject); ?>"
                                                    data-desc="<?php echo htmlspecialchars($description); ?>"
                                                    data-status="<?php echo htmlspecialchars($status); ?>"
                                                    data-notes="<?php echo htmlspecialchars($row['admin_notes'] ?? ''); ?>"
                                                    data-assigned="<?php echo $row['assigned_to'] ?? ''; ?>"
                                                    data-attachment="<?php echo htmlspecialchars($row['attachment'] ?? ''); ?>">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3 opacity-50"></i>
                        <h5 class="text-muted">All caught up!</h5>
                        <p class="text-muted">There are no support tickets in the system right now.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Update Ticket Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="/hr-portal/modules/update_ticket.php" method="POST">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title" id="updateModalLabel">
                            Update Ticket <span id="m_code" class="text-primary fw-bold"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <input type="hidden" name="ticket_id" id="m_ticket_id">

                        <div class="mb-3">
                            <p class="mb-1 text-muted small">Employee: <strong class="text-dark" id="m_emp"></strong></p>
                            <p class="mb-1 text-muted small">Subject: <strong class="text-dark" id="m_subject"></strong></p>
                        </div>

                        <div class="mb-3">
                            <p class="mb-1 text-muted small">Description</p>
                            <div class="p-3 bg-light rounded border" style="white-space: pre-wrap;" id="m_desc"></div>
                        </div>

                        <div id="m_attachment_container" class="mb-3" style="display: none;">
                            <a id="m_attachment_link" href="#" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-paperclip me-1"></i> View Attached File
                            </a>
                        </div>
                        <hr>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label fw-bold">Update Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Open">Open</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="On Hold">On Hold</option>
                                    <option value="Resolved">Resolved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="assigned_to" class="form-label fw-bold">Assign To (Optional)</label>
                                <select class="form-select" id="assigned_to" name="assigned_to">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($admin_list as $admin): ?>
                                        <option value="<?php echo $admin['employee_id']; ?>">
                                            <?php echo htmlspecialchars($admin['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="admin_notes" class="form-label fw-bold">Admin Notes / Resolution</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4" placeholder="Enter notes, resolution steps, or reason for rejection..."></textarea>
                            <div class="form-text">These notes will be visible to the employee.</div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.update-ticket').click(function() {
                // Populate Modal Data
                $('#m_ticket_id').val($(this).data('id'));
                $('#m_code').text($(this).data('code'));
                $('#m_emp').text($(this).data('emp'));
                $('#m_subject').text($(this).data('subject'));
                $('#m_desc').text($(this).data('desc'));

                // Form Fields
                $('#status').val($(this).data('status'));
                $('#admin_notes').val($(this).data('notes'));

                let assigned = $(this).data('assigned');
                if(assigned) {
                    $('#assigned_to').val(assigned);
                } else {
                    $('#assigned_to').val('');
                }

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