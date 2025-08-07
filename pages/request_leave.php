<?php
// Include the database connection file
include '../includes/db_connect.php';
include '../includes/auth.php';

// Include the sidebar
include '../includes/sidebar.php';

// Get employee ID from session (if available)
$employee_id = $_SESSION['user_id'] ?? 0;

// Fetch employee's pending leave requests
$pending_sql = "SELECT leave_id, leave_type, start_date, end_date, status, reason, 
                DATEDIFF(end_date, start_date) + 1 as days
                FROM `leaves` 
                WHERE employee_id = ? AND status = 'Pending'
                ORDER BY start_date DESC";
$stmt = $conn->prepare($pending_sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$pending_result = $stmt->get_result();

// Fetch employee's recent leave history
$history_sql = "SELECT leave_id, leave_type, start_date, end_date, status, reason, 
                DATEDIFF(end_date, start_date) + 1 as days
                FROM `leaves` 
                WHERE employee_id = ? AND status != 'Pending'
                ORDER BY start_date DESC LIMIT 5";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$history_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Leave</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <!-- Flatpickr Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="/hr-portal/assets/css/sidebar.css">
    <style>
        /* Ensure the content is not hidden by the sidebar */
        .content {
            margin-left: 250px;
            padding: 20px;
        }

        /* Simple, classy design */
        .page-title {
            font-family: 'Arial', sans-serif;
            color: #333;
            font-weight: 700;
            margin-bottom: 20px;
        }

        /* Card styling */
        .leave-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .leave-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
        }

        .leave-card .card-body {
            padding: 20px;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-control, .form-select {
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Button styling */
        .btn-submit-leave {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            cursor: pointer;
            margin-top: 20px;
        }

        .btn-submit-leave:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Status badges */
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-approved {
            background-color: #28a745;
        }

        .badge-rejected {
            background-color: #dc3545;
        }

        /* Date range preview */
        #dateRangePreview {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <!-- Request Leave Content -->
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="page-title">
                <i class="fas fa-calendar-alt me-2"></i>Request Leave
            </h4>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Leave Request Form -->
                <div class="card leave-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>New Leave Request
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="/hr-portal/modules/submit_leave_request.php" method="POST" id="leaveRequestForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="leave_type">Leave Type</label>
                                        <select class="form-select" id="leave_type" name="leave_type" required>
                                            <option value="">Select Leave Type</option>
                                            <option value="Sick Leave">Sick Leave</option>
                                            <option value="Casual Leave">Casual Leave</option>
                                            <option value="Annual Leave">Annual Leave</option>
                                            <option value="Unpaid Leave">Unpaid Leave</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="half_day">Duration Type</label>
                                        <select class="form-select" id="half_day" name="half_day">
                                            <option value="0">Full Day</option>
                                            <option value="1">Half Day - Morning</option>
                                            <option value="2">Half Day - Afternoon</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" required />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" required />
                                    </div>
                                </div>
                            </div>

                            <div id="dateRangePreview" class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Selected Days: <strong id="selectedDays">0</strong></span>
                                    <span>Working Days: <strong id="workingDays">0</strong></span>
                                    <span>Weekends: <strong id="weekends">0</strong></span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="reason">Reason for Leave</label>
                                <textarea class="form-control" id="reason" name="reason" rows="4" required></textarea>
                                <div class="form-text">Please provide a detailed reason for your leave request.</div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="emergency_leave" name="emergency_leave" value="1">
                                <label class="form-check-label" for="emergency_leave">
                                    This is an emergency leave request
                                </label>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-submit-leave">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Leave Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Pending Requests -->
                <?php if ($pending_result->num_rows > 0): ?>
                <div class="card leave-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Pending Leave Requests
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Duration</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($request = $pending_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                        <td><?php echo $request['days']; ?> day(s)</td>
                                        <td>
                                            <?php 
                                                echo date('M d, Y', strtotime($request['start_date']));
                                                if ($request['start_date'] != $request['end_date']) {
                                                    echo ' - ' . date('M d, Y', strtotime($request['end_date']));
                                                }
                                            ?>
                                        </td>
                                        <td><span class="badge bg-warning">Pending</span></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info view-request" 
                                                    data-id="<?php echo $request['leave_id']; ?>"
                                                    data-type="<?php echo htmlspecialchars($request['leave_type']); ?>"
                                                    data-start="<?php echo date('M d, Y', strtotime($request['start_date'])); ?>"
                                                    data-end="<?php echo date('M d, Y', strtotime($request['end_date'])); ?>"
                                                    data-days="<?php echo $request['days']; ?>"
                                                    data-reason="<?php echo htmlspecialchars($request['reason']); ?>"
                                                    data-status="<?php echo htmlspecialchars($request['status']); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Leave History -->
                <?php if ($history_result->num_rows > 0): ?>
                <div class="card leave-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Recent Leave History
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Duration</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($history = $history_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($history['leave_type']); ?></td>
                                        <td><?php echo $history['days']; ?> day(s)</td>
                                        <td>
                                            <?php 
                                                echo date('M d, Y', strtotime($history['start_date']));
                                                if ($history['start_date'] != $history['end_date']) {
                                                    echo ' - ' . date('M d, Y', strtotime($history['end_date']));
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($history['status'] == 'Approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif ($history['status'] == 'Rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                        <button type="button" class="btn btn-sm btn-info view-request" 
                                                    data-id="<?php echo $history['leave_id']; ?>"
                                                    data-type="<?php echo htmlspecialchars($history['leave_type']); ?>"
                                                    data-start="<?php echo date('M d, Y', strtotime($history['start_date'])); ?>"
                                                    data-end="<?php echo date('M d, Y', strtotime($history['end_date'])); ?>"
                                                    data-days="<?php echo $history['days']; ?>"
                                                    data-reason="<?php echo htmlspecialchars($history['reason']); ?>"
                                                    data-status="<?php echo htmlspecialchars($history['status']); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <!-- Leave Guidelines Card -->
                <div class="card leave-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Leave Guidelines
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>Please submit leave requests at least 3 days in advance for proper planning.
                        </div>
                        
                        <h6 class="fw-bold mb-3">Key Points:</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="fas fa-check-circle text-success me-2"></i>Sick leave requires medical certificate for 3+ days
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-check-circle text-success me-2"></i>Annual leave must be approved by your manager
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-check-circle text-success me-2"></i>Casual leave cannot be taken for more than 2 consecutive days
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-check-circle text-success me-2"></i>Unpaid leave is granted in exceptional circumstances
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Leave Types Info Card -->
                <div class="card leave-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Leave Types
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="fw-bold"><i class="fas fa-thermometer text-danger me-2"></i>Sick Leave</h6>
                            <p class="small text-muted">For illness, injury, or medical appointments. Medical certificate required for 3+ days.</p>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="fw-bold"><i class="fas fa-coffee text-warning me-2"></i>Casual Leave</h6>
                            <p class="small text-muted">For personal matters, family events, or emergencies. Limited to 2 consecutive days.</p>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="fw-bold"><i class="fas fa-umbrella-beach text-success me-2"></i>Annual Leave</h6>
                            <p class="small text-muted">Planned vacation time. Should be requested at least 2 weeks in advance for proper planning.</p>
                        </div>
                        
                        <div>
                            <h6 class="fw-bold"><i class="fas fa-wallet text-secondary me-2"></i>Unpaid Leave</h6>
                            <p class="small text-muted">For extended absences when other leave types are exhausted. Requires special approval.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Request Details Modal -->
    <div class="modal fade" id="leaveDetailsModal" tabindex="-1" aria-labelledby="leaveDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveDetailsModalLabel">Leave Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Leave Type:</strong>
                        <span id="modal-leave-type"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Duration:</strong>
                        <span id="modal-duration"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Dates:</strong>
                        <span id="modal-dates"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong>
                        <span id="modal-status"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Reason:</strong>
                        <p id="modal-reason" class="mt-1"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Set minimum date for date inputs to today
            const today = new Date().toISOString().split('T')[0];
            $('#start_date').attr('min', today);
            $('#end_date').attr('min', today);
            
            // Calculate days between dates
            function calculateDays() {
                const startDate = new Date($('#start_date').val());
                const endDate = new Date($('#end_date').val());
                
                if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                    // Calculate total days
                    const diffTime = Math.abs(endDate - startDate);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    
                    // Calculate weekends
                    let weekends = 0;
                    let workingDays = 0;
                    
                    const currentDate = new Date(startDate);
                    while (currentDate <= endDate) {
                        const dayOfWeek = currentDate.getDay();
                        
                        if (dayOfWeek === 0 || dayOfWeek === 6) {
                            weekends++;
                        } else {
                            workingDays++;
                        }
                        
                        currentDate.setDate(currentDate.getDate() + 1);
                    }
                    
                    // Update the preview
                    $('#selectedDays').text(diffDays);
                    $('#workingDays').text(workingDays);
                    $('#weekends').text(weekends);
                    $('#dateRangePreview').show();
                    
                    // Check if half day is selected
                    if ($('#half_day').val() !== '0' && diffDays > 1) {
                        $('#half_day').val('0');
                        alert('Half day option can only be selected for a single day leave.');
                    }
                }
            }
            
            // Update date range preview when dates change
            $('#start_date, #end_date').change(function() {
                calculateDays();
            });
            
            // Form validation before submit
            $('#leaveRequestForm').submit(function(e) {
                const leaveType = $('#leave_type').val();
                const startDate = $('#start_date').val();
                const endDate = $('#end_date').val();
                const reason = $('#reason').val();
                
                if (!leaveType || !startDate || !endDate || !reason) {
                    alert('Please fill in all required fields.');
                    e.preventDefault();
                    return false;
                }
                
                // Validate date range
                if (new Date(startDate) > new Date(endDate)) {
                    alert('End date cannot be before start date.');
                    e.preventDefault();
                    return false;
                }
                
                // Validate reason length
                if (reason.length < 10) {
                    alert('Please provide a more detailed reason for your leave request (at least 10 characters).');
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Half day option should only be available for single day selections
            $('#half_day').change(function() {
                if ($(this).val() !== '0') {
                    // If half day is selected, make sure start and end date are the same
                    const startDate = $('#start_date').val();
                    const endDate = $('#end_date').val();
                    
                    if (startDate && endDate && startDate !== endDate) {
                        alert('Half day option can only be selected for a single day leave.');
                        $(this).val('0');
                    }
                }
            });
            
            // Handle view request button clicks
            $('.view-request').click(function() {
                const id = $(this).data('id');
                const type = $(this).data('type');
                const start = $(this).data('start');
                const end = $(this).data('end');
                const days = $(this).data('days');
                const reason = $(this).data('reason');
                const status = $(this).data('status');
                
                // Set modal content
                $('#modal-leave-type').text(type);
                $('#modal-duration').text(days + ' day(s)');
                $('#modal-dates').text(start === end ? start : start + ' - ' + end);
                
                // Set status with appropriate badge
                let statusHtml = '';
                if (status === 'Pending') {
                    statusHtml = '<span class="badge bg-warning">Pending</span>';
                } else if (status === 'Approved') {
                    statusHtml = '<span class="badge bg-success">Approved</span>';
                } else if (status === 'Rejected') {
                    statusHtml = '<span class="badge bg-danger">Rejected</span>';
                }
                
                $('#modal-status').html(statusHtml);
                $('#modal-reason').text(reason);
                
                // Show the modal
                $('#leaveDetailsModal').modal('show');
            });
        });
    </script>
</body>
</html>

<?php
// Close the connection
$conn->close();
?>