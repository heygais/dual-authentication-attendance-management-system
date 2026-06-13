<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

// ─── Handle delete ───────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    $id = (int)$_POST['id'];
    $ur = mysqli_query($conn, "SELECT user_id FROM students WHERE id=$id");
    if ($ur && $row = mysqli_fetch_assoc($ur)) {
        mysqli_query($conn, "DELETE FROM users WHERE id=" . (int)$row['user_id']);
    }
    echo json_encode(['status' => 'success', 'message' => 'Student deleted.']);
    exit;
}

// ─── Read inputs ─────────────────────────────────────────────
$name           = trim($_POST['name']           ?? '');
$student_number = trim($_POST['student_number'] ?? '');
$programme      = trim($_POST['programme']      ?? '');
$year           = (int)($_POST['year']          ?? 1);
$email          = trim($_POST['email']          ?? '');
$phone          = trim($_POST['phone']          ?? '');
// Accept either a single class_id (old form) or class_ids[] (new checklist form)
$class_ids = [];
if (!empty($_POST['class_ids']) && is_array($_POST['class_ids'])) {
    foreach ($_POST['class_ids'] as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) $class_ids[] = $cid;
    }
} elseif (!empty($_POST['class_id'])) {
    $cid = (int)$_POST['class_id'];
    if ($cid > 0) $class_ids[] = $cid;
}
$password       = $_POST['password']            ?? '';

if ($name === '' || $student_number === '' || $password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Name, student number and password are required.']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
    exit;
}
if ($year < 1) $year = 1;

// ─── Duplicate checks ────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username=?");
mysqli_stmt_bind_param($stmt, 's', $student_number);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'error', 'message' => 'A user account with this student number already exists.']);
    exit;
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE student_number=?");
mysqli_stmt_bind_param($stmt, 's', $student_number);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'error', 'message' => 'This student number is already registered.']);
    exit;
}
mysqli_stmt_close($stmt);

// ─── Insert in transaction ───────────────────────────────────
$hashed = password_hash($password, PASSWORD_BCRYPT);
mysqli_begin_transaction($conn);

try {
    // users
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role, name, email) VALUES (?, ?, 'student', ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssss', $student_number, $hashed, $name, $email);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('users insert: ' . mysqli_stmt_error($stmt));
    }
    $user_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // students
    $stmt = mysqli_prepare($conn, "INSERT INTO students (user_id, student_number, programme, year_of_study, phone) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'issis', $user_id, $student_number, $programme, $year, $phone);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('students insert: ' . mysqli_stmt_error($stmt));
    }
    $student_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // optional enrollment — one row per selected class
    if (!empty($class_ids)) {
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO student_classes (student_id, class_id) VALUES (?, ?)");
        foreach ($class_ids as $cid) {
            mysqli_stmt_bind_param($stmt, 'ii', $student_id, $cid);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($conn);
    echo json_encode([
        'status'     => 'success',
        'message'    => 'Student registered successfully.',
        'user_id'    => $user_id,
        'student_id' => $student_id,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $e->getMessage()]);
}
