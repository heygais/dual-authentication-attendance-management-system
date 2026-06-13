<?php
// One-time script to fix bcrypt password hashes for the seed accounts.
// Visit: http://localhost/attendx/fix_passwords.php
// Delete this file after running it.

require_once 'includes/db.php';

$hash_password123 = password_hash('password123', PASSWORD_BCRYPT);
$hash_admin123    = password_hash('admin123',    PASSWORD_BCRYPT);

$updates = [
    ['admin',      $hash_admin123],
    ['lecturer01', $hash_password123],
    ['2021001234', $hash_password123],
    ['2021001235', $hash_password123],
    ['2021001236', $hash_password123],
    ['2021001237', $hash_password123],
    ['2021001238', $hash_password123],
];

echo "<style>body{font-family:system-ui;padding:24px;background:#f4f6f8;color:#111;}p{margin:6px 0;font-size:13px;}b{color:#16a34a;}</style>";
echo "<h2>AttendX — Password Fix</h2>";

foreach ($updates as [$user, $hash]) {
    $u = mysqli_real_escape_string($conn, $user);
    $h = mysqli_real_escape_string($conn, $hash);
    if (mysqli_query($conn, "UPDATE users SET password='$h' WHERE username='$u'")) {
        echo "<p><b>OK</b> &mdash; updated <code>$user</code></p>";
    } else {
        echo "<p style='color:#dc2626;'>FAIL &mdash; $user: " . mysqli_error($conn) . "</p>";
    }
}

echo "<hr><p>Done. Delete this file (<code>fix_passwords.php</code>) for safety.</p>";
echo "<p><a href='/attendx/assets/login.php'>&rarr; Go to login</a></p>";
