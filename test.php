<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$result = mysqli_query($conn, "SELECT id, username, role, name FROM users");
$users = [];
while($row = mysqli_fetch_assoc($result)){
    $users[] = $row;
}

echo json_encode([
    'status'   => 'ok',
    'message'  => 'Database connected successfully',
    'users'    => $users
]);
?>