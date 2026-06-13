<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

// Only lecturers or admins may start a session
$role = $_SESSION['role'] ?? '';
if ($role !== 'lecturer' && $role !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];

// Accept either class_id (preferred) or subject (class_code / class_name)
$class_id = (int)($_POST['class_id'] ?? 0);
$subject  = trim($_POST['subject']   ?? '');

if (!$class_id && $subject !== '') {
    $esc  = mysqli_real_escape_string($conn, $subject);
    $cr   = mysqli_query($conn, "SELECT id FROM classes WHERE class_code='$esc' OR class_name='$esc' LIMIT 1");
    if ($cr && $row = mysqli_fetch_assoc($cr)) $class_id = (int)$row['id'];
}

if (!$class_id) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a subject.']);
    exit;
}

// Sanity check the class exists
$ck = mysqli_query($conn, "SELECT class_name, class_code FROM classes WHERE id=$class_id");
$cls = $ck ? mysqli_fetch_assoc($ck) : null;
if (!$cls) {
    echo json_encode(['status' => 'error', 'message' => 'Class not found.']);
    exit;
}

// Claim this class for the lecturer running the session, so it shows up
// in their dashboard (My Classes / My Students / records / chart).
mysqli_query($conn, "UPDATE classes SET lecturer_id=$lecturer_id WHERE id=$class_id");

// Deactivate every previously active session (global, per spec)
mysqli_query($conn, "UPDATE sessions SET status='inactive', end_time=NOW() WHERE status='active'");

// Insert new active session
$stmt = mysqli_prepare($conn, "INSERT INTO sessions (class_id, lecturer_id, status) VALUES (?, ?, 'active')");
mysqli_stmt_bind_param($stmt, 'ii', $class_id, $lecturer_id);
if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to start session: ' . mysqli_stmt_error($stmt)]);
    exit;
}
$session_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

echo json_encode([
    'status'     => 'success',
    'message'    => 'Session started for ' . $cls['class_name'] . '.',
    'session_id' => $session_id,
    'class_id'   => $class_id,
    'class_name' => $cls['class_name'],
    'class_code' => $cls['class_code'],
]);
