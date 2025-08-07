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
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #fff;
            border-bottom: none;
            text-align: center;
            padding: 20px;
        }
        .company-logo {
            max-width: 100px;
            margin-bottom: 20px;
        }
        .form-control {
            border-radius: 5px;
            padding: 12px;
        }
        .btn-primary {
            padding: 12px;
            border-radius: 5px;
        }
        .forgot-password-link {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9em;
        }
        .forgot-password-link:hover {
            color: #495057;
            text-decoration: underline;
        }
        .back-to-login {
            color: #007bff;
            cursor: pointer;
            text-decoration: none;
        }
        .back-to-login:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <img src="https://travarsa.com/wp-content/uploads/2023/03/Travarsa-Logo.png" alt="Company Logo" class="company-logo">
                <h4 id="form-title">HR Portal Login</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" action="" id="login-form">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="forgot-password-link" onclick="showForgotPassword()">
                            <i class="fas fa-key me-1"></i>Forgot Password?
                        </a>
                    </div>
                </form>
                
                <!-- Forgot Password Form -->
                <form method="POST" action="" id="forgot-form" style="display: none;">
                    <div class="mb-3">
                        <label for="forgot_email" class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="forgot_email" name="forgot_email" required>
                        </div>
                        <div class="form-text">Enter your email address to receive a password reset link.</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="forgot_password" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                        </button>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="back-to-login" onclick="showLogin()">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showForgotPassword() {
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('forgot-form').style.display = 'block';
            document.getElementById('form-title').textContent = 'Reset Password';
        }
        
        function showLogin() {
            document.getElementById('forgot-form').style.display = 'none';
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('form-title').textContent = 'HR Portal Login';
        }
    </script>
</body>
</html>
