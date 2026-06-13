<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$finger_id  = isset($_POST['finger_id'])  ? (int)$_POST['finger_id']  : 0;

if (!$student_id || $finger_id < 1 || $finger_id > 127) {
    echo json_encode(['status' => 'error', 'message' => 'Valid student and slot ID (1-127) are required.']);
    exit;
}

$cr = mysqli_query($conn, "SELECT id FROM students WHERE finger_id=$finger_id AND id != $student_id");
if (mysqli_num_rows($cr) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'This fingerprint slot is already in use.']);
    exit;
}

mysqli_query($conn, "UPDATE students SET finger_id=$finger_id WHERE id=$student_id");

echo json_encode(['status' => 'success', 'message' => 'Fingerprint slot registered successfully.']);
