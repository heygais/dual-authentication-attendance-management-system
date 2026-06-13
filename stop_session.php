<?php
header('Content-Type: application/json');
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

$role = $_SESSION['role'] ?? '';
if ($role !== 'lecturer' && $role !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
    exit;
}

$session_id = (int)($_POST['session_id'] ?? 0);

if ($session_id) {
    $ok = mysqli_query($conn, "UPDATE sessions SET status='inactive', end_time=NOW() WHERE id=$session_id AND status='active'");
} else {
    // No id given — stop every active session
    $ok = mysqli_query($conn, "UPDATE sessions SET status='inactive', end_time=NOW() WHERE status='active'");
}

if ($ok) {
    echo json_encode(['status' => 'success', 'message' => 'Attendance session ended.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to stop session: ' . mysqli_error($conn)]);
}
