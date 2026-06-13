<?php
// Adds a `course` column to `classes` and seeds subjects for 5 courses.
// Visit: http://localhost/attendx/fix_courses.php
// Safe to re-run — uses INSERT IGNORE.
// Delete this file after use.

require_once 'includes/db.php';

echo "<style>body{font-family:system-ui;padding:24px;background:#f4f6f8;color:#111;}p{margin:6px 0;font-size:13px;}b{color:#16a34a;}h3{margin-top:24px;}.skip{color:#9ca3af;}.fail{color:#dc2626;}</style>";
echo "<h2>AttendX — Courses + Subjects Seed</h2>";

// 1. Add `course` column if missing
$has = mysqli_query($conn, "SHOW COLUMNS FROM classes LIKE 'course'");
if ($has && mysqli_num_rows($has) > 0) {
    echo "<p class='skip'>OK &mdash; <code>classes.course</code> already exists</p>";
} else {
    if (mysqli_query($conn, "ALTER TABLE classes ADD COLUMN course VARCHAR(100) AFTER class_name")) {
        echo "<p><b>ADDED</b> &mdash; <code>classes.course</code></p>";
    } else {
        echo "<p class='fail'>FAIL &mdash; " . mysqli_error($conn) . "</p>";
        exit;
    }
}

// 2. Seed subjects
$catalogue = [
    'Computer Science' => [
        ['CS201', 'Database Systems'],
        ['CS202', 'Web Programming'],
        ['CS203', 'Computer Networking'],
        ['CS204', 'Artificial Intelligence'],
        ['CS205', 'Operating Systems'],
        ['CS206', 'Software Engineering'],
    ],
    'Engineering' => [
        ['EN201', 'Engineering Mathematics'],
        ['EN202', 'Thermodynamics'],
        ['EN203', 'Circuit Analysis'],
        ['EN204', 'Mechanics of Materials'],
        ['EN205', 'Fluid Mechanics'],
    ],
    'Business Administration' => [
        ['BA201', 'Marketing Principles'],
        ['BA202', 'Financial Accounting'],
        ['BA203', 'Business Statistics'],
        ['BA204', 'Organizational Behavior'],
        ['BA205', 'Operations Management'],
    ],
    'Medicine' => [
        ['MD201', 'Human Anatomy'],
        ['MD202', 'Physiology'],
        ['MD203', 'Biochemistry'],
        ['MD204', 'Pharmacology'],
        ['MD205', 'Pathology'],
    ],
    'Law' => [
        ['LA201', 'Constitutional Law'],
        ['LA202', 'Contract Law'],
        ['LA203', 'Criminal Law'],
        ['LA204', 'Tort Law'],
        ['LA205', 'Legal Research'],
    ],
];

$added = 0; $existed = 0;
foreach ($catalogue as $course => $subjects) {
    echo "<h3>$course</h3>";
    foreach ($subjects as [$code, $name]) {
        $c = mysqli_real_escape_string($conn, $code);
        $n = mysqli_real_escape_string($conn, $name);
        $co= mysqli_real_escape_string($conn, $course);

        // Check existing row
        $chk = mysqli_query($conn, "SELECT id, course FROM classes WHERE class_code='$c'");
        if ($chk && $row = mysqli_fetch_assoc($chk)) {
            // Already there — just ensure course is set
            if (empty($row['course'])) {
                mysqli_query($conn, "UPDATE classes SET course='$co' WHERE id=" . (int)$row['id']);
                echo "<p><b>UPDATED</b> &mdash; <code>$code</code> $name (assigned course)</p>";
            } else {
                echo "<p class='skip'>SKIP &mdash; <code>$code</code> $name (exists)</p>";
            }
            $existed++;
        } else {
            if (mysqli_query($conn, "INSERT INTO classes (class_code, class_name, course) VALUES ('$c', '$n', '$co')")) {
                echo "<p><b>ADDED</b> &mdash; <code>$code</code> $name</p>";
                $added++;
            } else {
                echo "<p class='fail'>FAIL &mdash; $code: " . mysqli_error($conn) . "</p>";
            }
        }
    }
}

echo "<hr><p><b>$added added</b>, $existed already present.</p>";
echo "<p>Delete <code>fix_courses.php</code> after use.</p>";
echo "<p><a href='/attendx/assets/register-student.php'>&rarr; Register Student</a></p>";
