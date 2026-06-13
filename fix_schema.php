<?php
// Adds any missing columns to the AttendX tables.
// Visit: http://localhost/attendx/fix_schema.php
// Delete this file after running it.

require_once 'includes/db.php';

echo "<style>body{font-family:system-ui;padding:24px;background:#f4f6f8;color:#111;}p{margin:6px 0;font-size:13px;}b{color:#16a34a;}.skip{color:#9ca3af;}.fail{color:#dc2626;}</style>";
echo "<h2>AttendX — Schema Repair</h2>";

// Each entry: [table, column, full column definition]
$expected = [
    // students
    ['students', 'programme',       "ALTER TABLE students ADD COLUMN programme VARCHAR(100) AFTER student_number"],
    ['students', 'year_of_study',   "ALTER TABLE students ADD COLUMN year_of_study INT DEFAULT 1"],
    ['students', 'phone',           "ALTER TABLE students ADD COLUMN phone VARCHAR(20)"],
    ['students', 'rfid_uid',        "ALTER TABLE students ADD COLUMN rfid_uid VARCHAR(50)"],
    ['students', 'finger_id',       "ALTER TABLE students ADD COLUMN finger_id INT"],

    // lecturers
    ['lecturers', 'department',     "ALTER TABLE lecturers ADD COLUMN department VARCHAR(100)"],
    ['lecturers', 'designation',    "ALTER TABLE lecturers ADD COLUMN designation VARCHAR(50)"],
    ['lecturers', 'phone',          "ALTER TABLE lecturers ADD COLUMN phone VARCHAR(20)"],

    // classes
    ['classes', 'venue',            "ALTER TABLE classes ADD COLUMN venue VARCHAR(50)"],
    ['classes', 'schedule',         "ALTER TABLE classes ADD COLUMN schedule VARCHAR(100)"],
    ['classes', 'max_students',     "ALTER TABLE classes ADD COLUMN max_students INT DEFAULT 30"],
    ['classes', 'lecturer_id',      "ALTER TABLE classes ADD COLUMN lecturer_id INT"],

    // attendance
    ['attendance', 'rfid_verified', "ALTER TABLE attendance ADD COLUMN rfid_verified TINYINT(1) DEFAULT 0"],
    ['attendance', 'finger_verified',"ALTER TABLE attendance ADD COLUMN finger_verified TINYINT(1) DEFAULT 0"],
    ['attendance', 'method',        "ALTER TABLE attendance ADD COLUMN method ENUM('RFID','Fingerprint','QR','Manual') NOT NULL DEFAULT 'Manual'"],
    ['attendance', 'is_present',    "ALTER TABLE attendance ADD COLUMN is_present TINYINT(1) DEFAULT 0"],
    ['attendance', 'override_by',   "ALTER TABLE attendance ADD COLUMN override_by INT DEFAULT NULL"],
    ['attendance', 'override_reason',"ALTER TABLE attendance ADD COLUMN override_reason TEXT DEFAULT NULL"],
    ['attendance', 'timestamp',     "ALTER TABLE attendance ADD COLUMN timestamp DATETIME DEFAULT CURRENT_TIMESTAMP"],

    // users
    ['users', 'email',              "ALTER TABLE users ADD COLUMN email VARCHAR(100)"],
    ['users', 'created_at',         "ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"],
];

function columnExists($conn, $table, $column) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && mysqli_num_rows($r) > 0;
}

function tableExists($conn, $table) {
    $t = mysqli_real_escape_string($conn, $table);
    $r = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    return $r && mysqli_num_rows($r) > 0;
}

$added = 0; $skipped = 0; $failed = 0;
foreach ($expected as [$table, $column, $sql]) {
    if (!tableExists($conn, $table)) {
        echo "<p class='fail'>SKIP &mdash; table <code>$table</code> does not exist (run setup.sql first)</p>";
        $skipped++;
        continue;
    }
    if (columnExists($conn, $table, $column)) {
        echo "<p class='skip'>OK &mdash; <code>$table.$column</code> already exists</p>";
        $skipped++;
        continue;
    }
    if (mysqli_query($conn, $sql)) {
        echo "<p><b>ADDED</b> &mdash; <code>$table.$column</code></p>";
        $added++;
    } else {
        echo "<p class='fail'>FAIL &mdash; <code>$table.$column</code>: " . mysqli_error($conn) . "</p>";
        $failed++;
    }
}

echo "<hr><p><b>$added added</b>, $skipped already present, $failed failed.</p>";
echo "<p>Delete <code>fix_schema.php</code> after use.</p>";
echo "<p><a href='/attendx/assets/admin-students.php'>&rarr; Back to Students page</a></p>";
