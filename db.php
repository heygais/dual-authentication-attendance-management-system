<?php
// Keep PHP and MySQL on the SAME timezone, otherwise "today" can differ
// between date() (PHP) and NOW()/CURRENT_TIMESTAMP (MySQL), which makes
// attendance records fall outside the dashboard's date filter.
date_default_timezone_set('Asia/Kuala_Lumpur');   // UTC+8 (Malaysia)

$conn = mysqli_connect('localhost', 'root', '', 'attendx_db');
if (!$conn) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . mysqli_connect_error()]));
}
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+08:00'");   // match PHP timezone
