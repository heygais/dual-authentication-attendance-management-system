<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$rfid_uid   = isset($_POST['rfid_uid'])   ? mysqli_real_escape_string($conn, trim($_POST['rfid_uid'])) : '';

if (!$student_id || !$rfid_uid) {
    echo json_encode(['status' => 'error', 'message' => 'Student and RFID UID are required.']);
    exit;
}

// Check uniqueness
$cr = mysqli_query($conn, "SELECT id FROM students WHERE rfid_uid='$rfid_uid' AND id != $student_id");
if (mysqli_num_rows($cr) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'This RFID is already registered to another student.']);
    exit;
}

mysqli_query($conn, "UPDATE students SET rfid_uid='$rfid_uid' WHERE id=$student_id");

echo json_encode(['status' => 'success', 'message' => 'RFID registered successfully.']);
