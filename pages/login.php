<?php
// Include necessary files
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Initialize variables
$error = '';
$success = '';

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

// Handle forgot password request
if (isset($_POST['forgot_password'])) {
    $email = trim($_POST['forgot_email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } else {
        // Check if email exists in database
        $query = "SELECT employee_id, full_name, email FROM employees WHERE email = ? AND status = 'Active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
            
            // Store reset token in database (you might want to create a password_resets table)
            // For now, we'll use a simple approach with a temporary table or add columns to employees table
            $update_query = "UPDATE employees SET reset_token = ?, reset_token_expires = ? WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sss", $reset_token, $reset_expires, $email);
            
            if ($update_stmt->execute()) {
                // Send reset email
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $reset_token;
                
                $to = $email;
                $subject = "Password Reset Request - HR Portal";
                $message = "
                <html>
                <head>
                    <title>Password Reset Request</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>Dear " . htmlspecialchars($user['full_name']) . ",</p>
                    <p>You have requested to reset your password for the HR Portal.</p>
                    <p>Please click the link below to reset your password:</p>
                    <p><a href='" . $reset_link . "'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you did not request this password reset, please ignore this email.</p>
                    <br>
                    <p>Best regards,<br>HR Team</p>
                </body>
                </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: HR Portal <noreply@travarsa.net>" . "\r\n";
                
                if (mail($to, $subject, $message, $headers)) {
                    $success = "Password reset link has been sent to your email address.";
                } else {
                    $error = "Failed to send reset email. Please try again.";
                }
            } else {
                $error = "Failed to process reset request. Please try again.";
            }
        } else {
            // Don't reveal if email exists or not for security
            $success = "If the email exists in our system, a password reset link has been sent.";
        }
    }
}

// Handle regular login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        // Prepare the query to get user details
        $query = "SELECT employee_id, full_name, email, password_hash, role, status
                  FROM employees
                  WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
                        
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Check if user is active
                if ($user['status'] === 'Active') {
                    // Generate session token
                    $session_token = bin2hex(random_bytes(32));
                                        
                    // Create database session
                    if (createSession($user['employee_id'], $session_token)) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['employee_id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['session_token'] = $session_token;
                        $_SESSION['last_activity'] = time();
                        
                        // Redirect to dashboard
                        header('Location: ../dashboard.php');
                        exit;
                    } else {
                        $error = "Failed to create session. Please try again.";
                    }
                } else {
                    $error = "Your account is inactive. Please contact HR.";
                }
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HR Portal</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='45' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white' font-family='Arial'>👤</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
        }

        body {
            height: 100vh;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }

        .login-wrapper {
            display: flex;
            height: 100%;
            width: 100%;
        }

        .login-image {
            flex: 1;
            background: url('https://images.unsplash.com/photo-1497215728101-856f4ea42174?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80') no-repeat center center;
            background-size: cover;
            position: relative;
            display: none;
        }

        .login-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.8) 0%, rgba(67, 56, 202, 0.8) 100%);
        }

        .login-image-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            color: white;
        }

        .login-form-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ffffff;
            padding: 1.5rem; /* Reduced padding */
            overflow-y: auto;
        }

        .login-card {
            width: 100%;
            max-width: 380px; /* Reduced max-width */
        }

        .company-logo {
            max-width: 100px; /* Reduced logo size */
            width: 100%;
            height: auto;
            margin-bottom: 1.5rem; /* Reduced margin */
        }

        h2 {
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.25rem; /* Reduced margin */
            font-size: 1.75rem; /* Adjusted font size */
        }

        .text-muted {
            color: #6b7280 !important;
            font-size: 0.9rem; /* Smaller text */
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem; /* Smaller label */
            margin-bottom: 0.35rem;
        }

        .form-control {
            border-radius: 0.5rem;
            padding: 0.6rem 0.875rem; /* Reduced padding */
            border: 1px solid #d1d5db;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .input-group-text {
            background-color: #f9fafb;
            border: 1px solid #d1d5db;
            border-right: none;
            color: #9ca3af;
            padding: 0.6rem 0.875rem; /* Match input padding */
        }

        .input-group .form-control {
            border-left: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.6rem; /* Reduced padding */
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .forgot-password-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .forgot-password-link:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .back-to-login {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }

        .back-to-login:hover {
            color: #111827;
        }

        .alert {
            border-radius: 0.5rem;
            font-size: 0.85rem;
            border: none;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        #togglePassword {
            cursor: pointer;
            background-color: #fff;
            border-left: none;
        }

        #togglePassword:hover {
            color: var(--primary-color);
        }

        .mb-4 {
            margin-bottom: 1rem !important; /* Reduced spacing */
        }

        .mb-3 {
            margin-bottom: 0.75rem !important; /* Reduced spacing */
        }

        /* Desktop Styles */
        @media (min-width: 992px) {
            .login-image {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side: Image -->
        <div class="login-image">
            <div class="login-image-content">
                <h1 class="display-4 fw-bold mb-4">Welcome to<br>Travarsa HR Portal</h1>
                <p class="lead mb-0">Manage your workspace efficiently. Access attendance, tasks, and more in one place.</p>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="login-form-container">
            <div class="login-card">
                <div class="text-center">
                    <img src="https://travarsa.com/wp-content/uploads/2023/03/Travarsa-Logo.png" alt="Company Logo" class="company-logo">
                </div>

                <div id="login-section">
                    <div class="mb-4 text-center">
                        <h2>Sign In</h2>
                        <p class="text-muted">Enter your credentials to access your account</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <div><?php echo $success; ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required
                                        placeholder="name@company.com"
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                                <span class="input-group-text" id="togglePassword">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mb-4">
                            <a href="#" class="forgot-password-link" onclick="showForgotPassword()">Forgot Password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary mb-4">
                            Sign In
                        </button>
                    </form>
                </div>

                <!-- Forgot Password Section -->
                <div id="forgot-section" style="display: none;">
                    <div class="mb-4 text-center">
                        <h2>Reset Password</h2>
                        <p class="text-muted">Enter your email to receive reset instructions</p>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="forgot_email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="forgot_email" name="forgot_email" required placeholder="name@company.com">
                            </div>
                        </div>

                        <button type="submit" name="forgot_password" class="btn btn-primary mb-4">
                            Send Reset Link
                        </button>

                        <div class="text-center">
                            <a href="#" class="back-to-login" onclick="showLogin()">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </form>
                </div>

                <div class="text-center mt-3">
                    <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> Travarsa. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showForgotPassword() {
            document.getElementById('login-section').style.display = 'none';
            document.getElementById('forgot-section').style.display = 'block';
        }
        
        function showLogin() {
            document.getElementById('forgot-section').style.display = 'none';
            document.getElementById('login-section').style.display = 'block';
        }

        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const toggleIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            toggleIcon.classList.toggle('fa-eye');
            toggleIcon.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
