<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$data       = json_decode(file_get_contents('php://input'), true);
$identifier = mysqli_real_escape_string($conn, $data['identifier'] ?? '');
$method     = mysqli_real_escape_string($conn, $data['method'] ?? '');
$class_code = mysqli_real_escape_string($conn, $data['class_name'] ?? '');

// Find class
$classResult = mysqli_query($conn, "SELECT id FROM classes WHERE class_code = '$class_code'");
$class       = mysqli_fetch_assoc($classResult);
if (!$class) { echo json_encode(['status' => 'error', 'message' => 'Class not found']); exit; }
$class_id = $class['id'];

// Find student by RFID or Finger ID
if ($method === 'RFID') {
    $query = "SELECT s.id, u.name FROM students s JOIN users u ON s.user_id = u.id WHERE s.rfid_uid = '$identifier'";
} else {
    $query = "SELECT s.id, u.name FROM students s JOIN users u ON s.user_id = u.id WHERE s.finger_id = '$identifier'";
}
$result  = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($result);

if (!$student) {
    echo json_encode(['status' => 'not_found', 'name' => '']);
    exit;
}

$student_id = $student['id'];
$name       = $student['name'];

// Duplicate check — within last 5 minutes
$dupCheck = mysqli_query($conn, "
    SELECT id FROM attendance
    WHERE student_id = '$student_id'
    AND class_id = '$class_id'
    AND timestamp > NOW() - INTERVAL 5 MINUTE
");

if (mysqli_num_rows($dupCheck) > 0) {
    echo json_encode(['status' => 'duplicate', 'name' => $name]);
    exit;
}

// Insert attendance
$rfid   = ($method === 'RFID') ? 1 : 0;
$finger = ($method === 'Fingerprint') ? 1 : 0;

mysqli_query($conn, "
    INSERT INTO attendance (student_id, class_id, rfid_verified, finger_verified, method, is_present)
    VALUES ('$student_id', '$class_id', '$rfid', '$finger', '$method', 1)
");

echo json_encode(['status' => 'success', 'name' => $name]);
?>