<?php
// Database configuration
$host = 'cp-in-9';
$dbname = 'travawko_hr';
$username = 'travawko_hr';
$password = 'nsg_swd5686';
// Create connection
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Log the error (in production, you might want to log to a file)
    error_log("Database connection error: " . $e->getMessage());
    
    // Display a user-friendly error message
    die("Sorry, we're experiencing technical difficulties. Please try again later.");
}

// Function to check if a column exists
if (!function_exists('columnExists')) {
    function columnExists($conn, $table, $column) {
        $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $result->num_rows > 0;
    }
}

// Check and add columns if they don't exist
$columns_to_add = [
    'ip_address' => "ALTER TABLE user_sessions ADD COLUMN ip_address VARCHAR(45) NOT NULL AFTER session_token",
    'user_agent' => "ALTER TABLE user_sessions ADD COLUMN user_agent VARCHAR(255) NOT NULL AFTER ip_address",
    'status' => "ALTER TABLE user_sessions ADD COLUMN status ENUM('active', 'expired', 'revoked') DEFAULT 'active' AFTER user_agent",
    'last_activity' => "ALTER TABLE user_sessions ADD COLUMN last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
];

foreach ($columns_to_add as $column => $sql) {
    if (!columnExists($conn, 'user_sessions', $column)) {
        $conn->query($sql);
    }
}

// Add index if it doesn't exist
$result = $conn->query("SHOW INDEX FROM user_sessions WHERE Key_name = 'status'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE user_sessions ADD INDEX (status)");
}

// Modify expires_at column if needed
$result = $conn->query("SHOW COLUMNS FROM user_sessions WHERE Field = 'expires_at' AND `Default` IS NULL");
if ($result->num_rows > 0) {
    $conn->query("ALTER TABLE user_sessions MODIFY COLUMN expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
}

// Add foreign key if it doesn't exist
$result = $conn->query("SELECT * FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '$dbname' AND TABLE_NAME = 'user_sessions' AND CONSTRAINT_NAME = 'user_sessions_ibfk_1'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE user_sessions ADD FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE");
}
?>
