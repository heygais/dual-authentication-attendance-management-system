<?php
// Repair any student-role users that don't have a matching `students` row.
// Visit: http://localhost/attendx/fix_orphans.php
// Delete this file after running it.

require_once 'includes/db.php';

echo "<style>body{font-family:system-ui;padding:24px;background:#f4f6f8;color:#111;}p{margin:6px 0;font-size:13px;}b{color:#16a34a;}</style>";
echo "<h2>AttendX — Repair Orphan Students</h2>";

$sql = "SELECT u.id, u.username, u.name FROM users u LEFT JOIN students s ON s.user_id=u.id WHERE u.role='student' AND s.id IS NULL";
$res = mysqli_query($conn, $sql);

if (!$res) {
    echo "<p style='color:#dc2626;'>Query failed: " . mysqli_error($conn) . "</p>";
    exit;
}

if (mysqli_num_rows($res) === 0) {
    echo "<p><b>OK</b> &mdash; No orphan student accounts found.</p>";
} else {
    while ($row = mysqli_fetch_assoc($res)) {
        $uid  = (int)$row['id'];
        $uname= mysqli_real_escape_string($conn, $row['username']);
        // Use username as student_number since that's how registration sets it
        if (mysqli_query($conn, "INSERT INTO students (user_id, student_number, programme, year_of_study) VALUES ($uid, '$uname', '', 1)")) {
            echo "<p><b>OK</b> &mdash; Created student profile for <code>{$row['username']}</code> ({$row['name']})</p>";
        } else {
            echo "<p style='color:#dc2626;'>FAIL &mdash; {$row['username']}: " . mysqli_error($conn) . "</p>";
        }
    }
}

echo "<hr><p>Done. Delete <code>fix_orphans.php</code> after use.</p>";
echo "<p><a href='/attendx/assets/login.php'>&rarr; Go to login</a></p>";
