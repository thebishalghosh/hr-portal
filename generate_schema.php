<?php
// Include the database connection
require_once 'includes/db_connect.php';

// Get all table names
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Open the schema.sql file for writing
$schema_file = fopen('schema.sql', 'w');

// Iterate through each table and get the CREATE TABLE statement
foreach ($tables as $table) {
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_assoc();
    fwrite($schema_file, $row['Create Table'] . ";\n\n");
}

// Close the file and the database connection
fclose($schema_file);
$conn->close();

echo "schema.sql has been generated successfully.";
?>
