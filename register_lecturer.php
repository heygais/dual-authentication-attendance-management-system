<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$name        = trim($_POST['name']        ?? '');
$staff_id    = trim($_POST['staff_id']    ?? '');
$department  = trim($_POST['department']  ?? '');
$designation = trim($_POST['designation'] ?? '');
$email       = trim($_POST['email']       ?? '');
$phone       = trim($_POST['phone']       ?? '');
$password    = $_POST['password']         ?? '';
$username    = trim($_POST['username']    ?? '') ?: $staff_id;

if ($name === '' || $staff_id === '' || $password === '') {
    echo json_encode(['status' => 'error', 'message' => 'Name, staff ID and password are required.']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// Duplicate checks
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username=?");
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'error', 'message' => 'Username already exists.']);
    exit;
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT id FROM lecturers WHERE staff_id=?");
mysqli_stmt_bind_param($stmt, 's', $staff_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    echo json_encode(['status' => 'error', 'message' => 'Staff ID already exists.']);
    exit;
}
mysqli_stmt_close($stmt);

$hashed = password_hash($password, PASSWORD_BCRYPT);
mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role, name, email) VALUES (?, ?, 'lecturer', ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssss', $username, $hashed, $name, $email);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('users insert: ' . mysqli_stmt_error($stmt));
    $user_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "INSERT INTO lecturers (user_id, staff_id, department, designation, phone) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'issss', $user_id, $staff_id, $department, $designation, $phone);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('lecturers insert: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Lecturer registered successfully.', 'user_id' => $user_id]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $e->getMessage()]);
}
