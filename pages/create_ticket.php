<?php
session_start();
include '../includes/db_connect.php';
include '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Support Ticket - HR Portal</title>

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
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }

        .form-control, .form-select {
            padding: 10px;
            border-radius: 5px;
        }

        .btn-submit {
            background-color: #4f46e5;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
        }

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
            <h4 class="page-title"><i class="fas fa-life-ring me-2 text-primary"></i>Raise a Support Ticket</h4>
            <a href="/hr-portal/pages/my_tickets.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i> My Tickets
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card ticket-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-ticket-alt me-2 text-primary"></i>New Ticket Form</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="/hr-portal/modules/submit_ticket.php" method="POST" enctype="multipart/form-data" id="ticketForm">

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="issue_type">Issue Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="issue_type" name="issue_type" required>
                                        <option value="" disabled selected>Select Category</option>
                                        <option value="Training Server">Training Server</option>
                                        <option value="Internet">Internet / Network</option>
                                        <option value="Cables">Cables / Hardware</option>
                                        <option value="Facilities">Facilities</option>
                                        <option value="Furniture">Furniture</option>
                                        <option value="Password Reset">Password Reset / Access</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="priority">Priority <span class="text-danger">*</span></label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="" disabled selected>Select Priority</option>
                                        <option value="Low">Low (General Inquiry)</option>
                                        <option value="Medium">Medium (Affects some work)</option>
                                        <option value="High">High (Blocking work)</option>
                                        <option value="Critical">Critical (System Down)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="subject">Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="subject" name="subject" placeholder="Brief summary of the issue" required>
                            </div>

                            <div class="form-group">
                                <label for="description">Detailed Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="5" placeholder="Please describe the issue in detail, including any error messages..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="attachment">Attachment (Optional)</label>
                                <input class="form-control" type="file" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt">
                                <div class="form-text">Max size: 5MB. Allowed types: JPG, PNG, PDF, DOC, TXT.</div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-submit">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Ticket
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card ticket-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><i class="fas fa-check text-success me-2"></i> Be as descriptive as possible.</li>
                            <li class="list-group-item"><i class="fas fa-check text-success me-2"></i> Select the correct category for faster routing.</li>
                            <li class="list-group-item"><i class="fas fa-exclamation-triangle text-warning me-2"></i> Only use "Critical" priority for total system blockages.</li>
                            <li class="list-group-item"><i class="fas fa-camera text-secondary me-2"></i> Attach screenshots if you have an error message.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple client-side validation
        document.getElementById('ticketForm').addEventListener('submit', function(e) {
            const subject = document.getElementById('subject').value.trim();
            const desc = document.getElementById('description').value.trim();

            if(subject.length < 5) {
                e.preventDefault();
                alert('Subject must be at least 5 characters long.');
            }
            if(desc.length < 10) {
                e.preventDefault();
                alert('Please provide a more detailed description (at least 10 characters).');
            }
        });
    </script>
</body>
</html>