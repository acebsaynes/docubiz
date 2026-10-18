
<?php
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "docubiz_db"
);

if ($conn->connect_errno) {
    error_log("Database connection error: " . $conn->connect_error);
    http_response_code(500);
    exit("Cannot connect to the database. Check that MySQL is running and docubiz_db exists.");
}

$conn->set_charset("utf8mb4");
?>
