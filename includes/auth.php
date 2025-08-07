<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'db_connect.php';

// Function to check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
    }
}

// Function to check user role
if (!function_exists('checkRole')) {
    function checkRole($requiredRole) {
        if (!isLoggedIn()) {
            return false;
        }
        return strtolower($_SESSION['user_role']) === strtolower($requiredRole) || strtolower($_SESSION['user_role']) === 'admin';
    }
}

// Function to create a new session
if (!function_exists('createSession')) {
    function createSession($employee_id, $token) {
        global $conn;
        
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $conn->prepare("INSERT INTO user_sessions (employee_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $employee_id, $token, $ip_address, $user_agent, $expires_at);
        return $stmt->execute();
    }
}

// Function to validate session
if (!function_exists('validateSession')) {
    function validateSession() {
        global $conn;
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        $stmt = $conn->prepare("SELECT * FROM user_sessions WHERE employee_id = ? AND session_token = ? AND status = 'active' AND expires_at > NOW()");
        $stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['session_token']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        // Update last activity
        $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE employee_id = ? AND session_token = ?");
        $stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['session_token']);
        $stmt->execute();
        
        return true;
    }
}

// Function to invalidate session
if (!function_exists('invalidateSession')) {
    function invalidateSession() {
        global $conn;
        
        if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
            $stmt = $conn->prepare("UPDATE user_sessions SET status = 'revoked' WHERE employee_id = ? AND session_token = ?");
            $stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['session_token']);
            $stmt->execute();
        }
        
        session_unset();
        session_destroy();
    }
}

// Function to clean up expired sessions
if (!function_exists('cleanupExpiredSessions')) {
    function cleanupExpiredSessions() {
        global $conn;
        
        $stmt = $conn->prepare("UPDATE user_sessions SET status = 'expired' WHERE expires_at <= NOW() AND status = 'active'");
        $stmt->execute();
    }
}

// Clean up expired sessions on each request
cleanupExpiredSessions();

// Main session validation and redirect logic
if (!isLoggedIn() || !validateSession()) {
    // If not logged in or session is invalid, redirect to login
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header('Location: /hr-portal/pages/login.php');
        exit();
    }
} else {
    // If logged in and trying to access login page, redirect to dashboard
    if (basename($_SERVER['PHP_SELF']) === 'login.php') {
        header('Location: /hr-portal/dashboard.php');
        exit();
    }
}

// Additional check for role-based access control
if (!function_exists('isAuthorized')) {
    function isAuthorized($requiredRole) {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        // Check if the user's role matches the required role or if the user is Admin
        return strtolower($_SESSION['user_role']) === strtolower($requiredRole) || strtolower($_SESSION['user_role']) === 'admin';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    invalidateSession();
    header('Location: /hr-portal/pages/login.php');
    exit();
}
