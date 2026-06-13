<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'request') {
    $identifier = isset($_POST['identifier']) ? mysqli_real_escape_string($conn, trim($_POST['identifier'])) : '';
    $role       = isset($_POST['role'])       ? mysqli_real_escape_string($conn, $_POST['role']) : 'student';

    $ur = mysqli_query($conn, "SELECT id, name FROM users WHERE (username='$identifier' OR email='$identifier') AND role='$role'");
    $user = mysqli_fetch_assoc($ur);
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'No account found with that ID or email.']);
        exit;
    }

    $token   = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    $uid     = (int)$user['id'];

    mysqli_query($conn, "UPDATE password_resets SET used=1 WHERE user_id=$uid AND used=0");
    mysqli_query($conn, "INSERT INTO password_resets (user_id, token, expires_at) VALUES ($uid, '$token', '$expires')");

    echo json_encode(['status' => 'success', 'message' => 'Reset code sent. (Demo code: ' . $token . ')', 'user_id' => $uid]);
    exit;
}

if ($action === 'verify') {
    $uid   = isset($_POST['user_id'])  ? (int)$_POST['user_id'] : 0;
    $token = isset($_POST['token'])    ? mysqli_real_escape_string($conn, trim($_POST['token'])) : '';
    $pass  = isset($_POST['password']) ? $_POST['password'] : '';

    if (!$uid || !$token || !$pass) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $pr  = mysqli_query($conn, "SELECT * FROM password_resets WHERE user_id=$uid AND token='$token' AND used=0 AND expires_at > '$now'");
    $pr_row = mysqli_fetch_assoc($pr);
    if (!$pr_row) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code.']);
        exit;
    }

    $hashed = password_hash($pass, PASSWORD_BCRYPT);
    mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE id=$uid");
    mysqli_query($conn, "UPDATE password_resets SET used=1 WHERE id={$pr_row['id']}");

    echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
