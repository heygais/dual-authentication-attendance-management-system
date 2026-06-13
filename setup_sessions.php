<?php
// Adds sessions table + attendance.session_id column. Safe to re-run.
// Visit: http://localhost/attendx/setup_sessions.php
require_once 'includes/db.php';

echo "<style>body{font-family:system-ui;padding:24px;background:#f4f6f8;color:#111;max-width:800px;margin:auto;}p{margin:5px 0;font-size:13px;}b{color:#16a34a;}.skip{color:#9ca3af;}.fail{color:#dc2626;}code{background:#eef2ff;padding:2px 6px;border-radius:4px;}</style>";
echo "<h2>AttendX — Sessions Migration</h2>";

// sessions table
$has = mysqli_query($conn, "SHOW TABLES LIKE 'sessions'");
if ($has && mysqli_num_rows($has) > 0) {
    echo "<p class='skip'>OK &mdash; <code>sessions</code> table exists</p>";
} else {
    $sql = "CREATE TABLE sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        lecturer_id INT DEFAULT NULL,
        start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        end_time DATETIME DEFAULT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        FOREIGN KEY (class_id)    REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (lecturer_id) REFERENCES users(id)   ON DELETE SET NULL
    )";
    if (mysqli_query($conn, $sql)) echo "<p><b>CREATED</b> &mdash; <code>sessions</code> table</p>";
    else echo "<p class='fail'>FAIL &mdash; " . mysqli_error($conn) . "</p>";
}

// attendance.session_id column
$has = mysqli_query($conn, "SHOW COLUMNS FROM attendance LIKE 'session_id'");
if ($has && mysqli_num_rows($has) > 0) {
    echo "<p class='skip'>OK &mdash; <code>attendance.session_id</code> exists</p>";
} else {
    if (mysqli_query($conn, "ALTER TABLE attendance ADD COLUMN session_id INT DEFAULT NULL AFTER class_id")) {
        echo "<p><b>ADDED</b> &mdash; <code>attendance.session_id</code></p>";
    } else {
        echo "<p class='fail'>FAIL &mdash; " . mysqli_error($conn) . "</p>";
    }
}

echo "<hr><p>Done. Delete <code>setup_sessions.php</code> after use.</p>";
echo "<p><a href='/attendx/assets/lecturer-dashboard.php'>&rarr; Lecturer dashboard</a></p>";
