<?php
// One-shot AttendX repair/setup. Safe to re-run.
// Visit: http://localhost/attendx/setup_all.php
// Delete this file after use.

require_once 'includes/db.php';

echo "<style>body{font-family:system-ui;padding:24px;background:#f4f6f8;color:#111;max-width:900px;margin:auto;}h2{margin-top:0;}h3{margin-top:28px;border-top:1px solid #e5e7eb;padding-top:14px;}p{margin:4px 0;font-size:13px;}b{color:#16a34a;}.skip{color:#9ca3af;}.fail{color:#dc2626;}code{background:#eef2ff;padding:2px 6px;border-radius:4px;}</style>";
echo "<h2>AttendX — Full Setup / Repair</h2>";

function tableExists($conn, $t) {
    $t = mysqli_real_escape_string($conn, $t);
    $r = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    return $r && mysqli_num_rows($r) > 0;
}
function columnExists($conn, $t, $c) {
    $t = mysqli_real_escape_string($conn, $t);
    $c = mysqli_real_escape_string($conn, $c);
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && mysqli_num_rows($r) > 0;
}
function userExists($conn, $u) {
    $u = mysqli_real_escape_string($conn, $u);
    $r = mysqli_query($conn, "SELECT id FROM users WHERE username='$u'");
    return $r && mysqli_num_rows($r) > 0;
}

// ───────────────────────────────────────────────────────────
// 1. CREATE TABLES IF MISSING
// ───────────────────────────────────────────────────────────
echo "<h3>1. Tables</h3>";
$tables = [
    'users' => "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('student','lecturer','admin') NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    'students' => "CREATE TABLE students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        student_number VARCHAR(20) UNIQUE NOT NULL,
        programme VARCHAR(100),
        year_of_study INT DEFAULT 1,
        phone VARCHAR(20),
        rfid_uid VARCHAR(50),
        finger_id INT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    'lecturers' => "CREATE TABLE lecturers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        staff_id VARCHAR(20) UNIQUE NOT NULL,
        department VARCHAR(100),
        designation VARCHAR(50),
        phone VARCHAR(20),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    'classes' => "CREATE TABLE classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_code VARCHAR(20) UNIQUE NOT NULL,
        class_name VARCHAR(100) NOT NULL,
        course VARCHAR(100),
        lecturer_id INT,
        venue VARCHAR(50),
        schedule VARCHAR(100),
        max_students INT DEFAULT 30
    )",
    'student_classes' => "CREATE TABLE student_classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        UNIQUE KEY unique_enroll (student_id, class_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    )",
    'attendance' => "CREATE TABLE attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        rfid_verified TINYINT(1) DEFAULT 0,
        finger_verified TINYINT(1) DEFAULT 0,
        method ENUM('RFID','Fingerprint','QR','Manual') NOT NULL DEFAULT 'Manual',
        is_present TINYINT(1) DEFAULT 0,
        override_by INT DEFAULT NULL,
        override_reason TEXT DEFAULT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    'password_resets' => "CREATE TABLE password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(100) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
];
foreach ($tables as $name => $sql) {
    if (tableExists($conn, $name)) {
        echo "<p class='skip'>OK &mdash; <code>$name</code> exists</p>";
    } else {
        if (mysqli_query($conn, $sql)) echo "<p><b>CREATED</b> &mdash; <code>$name</code></p>";
        else echo "<p class='fail'>FAIL &mdash; $name: " . mysqli_error($conn) . "</p>";
    }
}

// ───────────────────────────────────────────────────────────
// 2. ADD ANY MISSING COLUMNS (idempotent)
// ───────────────────────────────────────────────────────────
echo "<h3>2. Columns</h3>";
$cols = [
    ['students', 'programme',        "ALTER TABLE students ADD COLUMN programme VARCHAR(100)"],
    ['students', 'year_of_study',    "ALTER TABLE students ADD COLUMN year_of_study INT DEFAULT 1"],
    ['students', 'phone',            "ALTER TABLE students ADD COLUMN phone VARCHAR(20)"],
    ['students', 'rfid_uid',         "ALTER TABLE students ADD COLUMN rfid_uid VARCHAR(50)"],
    ['students', 'finger_id',        "ALTER TABLE students ADD COLUMN finger_id INT"],
    ['lecturers','department',       "ALTER TABLE lecturers ADD COLUMN department VARCHAR(100)"],
    ['lecturers','designation',      "ALTER TABLE lecturers ADD COLUMN designation VARCHAR(50)"],
    ['lecturers','phone',            "ALTER TABLE lecturers ADD COLUMN phone VARCHAR(20)"],
    ['classes',  'course',           "ALTER TABLE classes ADD COLUMN course VARCHAR(100) AFTER class_name"],
    ['classes',  'venue',            "ALTER TABLE classes ADD COLUMN venue VARCHAR(50)"],
    ['classes',  'schedule',         "ALTER TABLE classes ADD COLUMN schedule VARCHAR(100)"],
    ['classes',  'max_students',     "ALTER TABLE classes ADD COLUMN max_students INT DEFAULT 30"],
    ['classes',  'lecturer_id',      "ALTER TABLE classes ADD COLUMN lecturer_id INT"],
    ['attendance','rfid_verified',   "ALTER TABLE attendance ADD COLUMN rfid_verified TINYINT(1) DEFAULT 0"],
    ['attendance','finger_verified', "ALTER TABLE attendance ADD COLUMN finger_verified TINYINT(1) DEFAULT 0"],
    ['attendance','method',          "ALTER TABLE attendance ADD COLUMN method ENUM('RFID','Fingerprint','QR','Manual') NOT NULL DEFAULT 'Manual'"],
    ['attendance','is_present',      "ALTER TABLE attendance ADD COLUMN is_present TINYINT(1) DEFAULT 0"],
    ['attendance','override_by',     "ALTER TABLE attendance ADD COLUMN override_by INT DEFAULT NULL"],
    ['attendance','override_reason', "ALTER TABLE attendance ADD COLUMN override_reason TEXT DEFAULT NULL"],
    ['users',    'email',            "ALTER TABLE users ADD COLUMN email VARCHAR(100)"],
    ['users',    'created_at',       "ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"],
];
foreach ($cols as [$t, $c, $sql]) {
    if (!tableExists($conn, $t)) continue;
    if (columnExists($conn, $t, $c)) {
        echo "<p class='skip'>OK &mdash; <code>$t.$c</code></p>";
    } else {
        if (mysqli_query($conn, $sql)) echo "<p><b>ADDED</b> &mdash; <code>$t.$c</code></p>";
        else echo "<p class='fail'>FAIL &mdash; $t.$c: " . mysqli_error($conn) . "</p>";
    }
}

// ───────────────────────────────────────────────────────────
// 3. SEED USERS (admin, lecturer, 5 students)
// ───────────────────────────────────────────────────────────
echo "<h3>3. Default users</h3>";
$pwStudent = password_hash('password123', PASSWORD_BCRYPT);
$pwAdmin   = password_hash('admin123',    PASSWORD_BCRYPT);

$seedUsers = [
    ['admin',      $pwAdmin,   'admin',    'Administrator',         'admin@university.edu.my'],
    ['lecturer01', $pwStudent, 'lecturer', 'Dr. Amirul Hakim',      'amirul@university.edu.my'],
    ['2021001234', $pwStudent, 'student',  'Ahmad Rizal',           'ahmad@university.edu.my'],
    ['2021001235', $pwStudent, 'student',  'Nurul Ain Binti Aziz',  'nurul@university.edu.my'],
    ['2021001236', $pwStudent, 'student',  'Hafiz Zulkarnain',      'hafiz@university.edu.my'],
    ['2021001237', $pwStudent, 'student',  'Siti Nabilah',          'siti@university.edu.my'],
    ['2021001238', $pwStudent, 'student',  'Muhammad Faris',        'faris@university.edu.my'],
];
foreach ($seedUsers as [$u, $p, $r, $n, $e]) {
    if (userExists($conn, $u)) {
        // Reset password too in case it's broken
        $pe = mysqli_real_escape_string($conn, $p);
        $ue = mysqli_real_escape_string($conn, $u);
        mysqli_query($conn, "UPDATE users SET password='$pe' WHERE username='$ue'");
        echo "<p class='skip'>OK &mdash; <code>$u</code> (password reset)</p>";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role, name, email) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssss', $u, $p, $r, $n, $e);
        if (mysqli_stmt_execute($stmt)) echo "<p><b>ADDED</b> &mdash; <code>$u</code></p>";
        else echo "<p class='fail'>FAIL &mdash; $u: " . mysqli_stmt_error($stmt) . "</p>";
        mysqli_stmt_close($stmt);
    }
}

// ───────────────────────────────────────────────────────────
// 4. REPAIR ORPHAN STUDENT/LECTURER ROWS
// ───────────────────────────────────────────────────────────
echo "<h3>4. Orphan student/lecturer profiles</h3>";
$res = mysqli_query($conn, "SELECT u.id, u.username, u.name FROM users u LEFT JOIN students s ON s.user_id=u.id WHERE u.role='student' AND s.id IS NULL");
$fix = 0;
if ($res) while ($r = mysqli_fetch_assoc($res)) {
    $uid = (int)$r['id'];
    $un  = mysqli_real_escape_string($conn, $r['username']);
    if (mysqli_query($conn, "INSERT INTO students (user_id, student_number, programme, year_of_study) VALUES ($uid, '$un', '', 1)")) {
        echo "<p><b>FIXED</b> &mdash; student profile for <code>{$r['username']}</code></p>";
        $fix++;
    }
}
$res = mysqli_query($conn, "SELECT u.id, u.username FROM users u LEFT JOIN lecturers l ON l.user_id=u.id WHERE u.role='lecturer' AND l.id IS NULL");
if ($res) while ($r = mysqli_fetch_assoc($res)) {
    $uid = (int)$r['id'];
    $un  = mysqli_real_escape_string($conn, $r['username']);
    if (mysqli_query($conn, "INSERT INTO lecturers (user_id, staff_id) VALUES ($uid, '$un')")) {
        echo "<p><b>FIXED</b> &mdash; lecturer profile for <code>{$r['username']}</code></p>";
        $fix++;
    }
}
if (!$fix) echo "<p class='skip'>OK &mdash; no orphan profiles found</p>";

// ───────────────────────────────────────────────────────────
// 5. SEED COURSES + SUBJECTS
// ───────────────────────────────────────────────────────────
echo "<h3>5. Courses &amp; Subjects</h3>";
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
$added = 0;
foreach ($catalogue as $course => $subs) {
    echo "<p style='margin-top:10px;'><b style='color:#1f2937;'>$course</b></p>";
    foreach ($subs as [$code, $name]) {
        $c  = mysqli_real_escape_string($conn, $code);
        $n  = mysqli_real_escape_string($conn, $name);
        $co = mysqli_real_escape_string($conn, $course);
        $chk = mysqli_query($conn, "SELECT id, course FROM classes WHERE class_code='$c'");
        if ($chk && $row = mysqli_fetch_assoc($chk)) {
            if (empty($row['course'])) {
                mysqli_query($conn, "UPDATE classes SET course='$co' WHERE id=" . (int)$row['id']);
                echo "<p style='padding-left:18px;'><b>UPDATED</b> &mdash; $code $name (course assigned)</p>";
            } else {
                echo "<p class='skip' style='padding-left:18px;'>OK &mdash; $code $name</p>";
            }
        } else {
            if (mysqli_query($conn, "INSERT INTO classes (class_code, class_name, course) VALUES ('$c', '$n', '$co')")) {
                echo "<p style='padding-left:18px;'><b>ADDED</b> &mdash; $code $name</p>";
                $added++;
            }
        }
    }
}

echo "<hr><h3 style='color:#16a34a;border:none;padding:0;'>&#10003; Setup complete.</h3>";
echo "<p><b>Login credentials</b></p>";
echo "<ul style='font-size:13px;'>";
echo "<li>Admin &mdash; <code>admin</code> / <code>admin123</code></li>";
echo "<li>Lecturer &mdash; <code>lecturer01</code> / <code>password123</code></li>";
echo "<li>Student &mdash; <code>2021001234</code> / <code>password123</code></li>";
echo "</ul>";
echo "<p style='color:#dc2626;'><b>Now delete <code>setup_all.php</code> for safety.</b></p>";
echo "<p><a href='/attendx/assets/login.php' style='display:inline-block;padding:10px 16px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-size:13px;'>Go to login &rarr;</a></p>";
