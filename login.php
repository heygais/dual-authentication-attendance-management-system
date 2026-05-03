<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

$data     = json_decode(file_get_contents('php://input'), true);
$username = mysqli_real_escape_string($conn, $data['username'] ?? '');
$password = $data['password'] ?? '';
$role     = mysqli_real_escape_string($conn, $data['role'] ?? '');

if (!$username || !$password || !$role) {
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit;
}

$query  = "SELECT * FROM users WHERE username = '$username' AND role = '$role'";
$result = mysqli_query($conn, $query);
$user   = mysqli_fetch_assoc($result);

// password_verify checks against the hashed password in DB
if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['name']     = $user['name'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['username'] = $user['username'];

    echo json_encode([
        'status' => 'success',
        'role'   => $user['role'],
        'name'   => $user['name'],
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
}
?>