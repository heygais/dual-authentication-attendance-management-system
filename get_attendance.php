<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';

$date     = isset($_GET['date'])  ? mysqli_real_escape_string($conn, $_GET['date'])  : date('Y-m-d');
$class_id = isset($_GET['class']) ? (int)$_GET['class'] : 0;

$where = "DATE(a.timestamp) = '$date'";
if ($class_id) $where .= " AND a.class_id = $class_id";

// Lecturer scope — only their own classes
if (isset($_SESSION['role']) && $_SESSION['role'] === 'lecturer') {
    $lid = (int)$_SESSION['user_id'];
    $where .= " AND c.lecturer_id = $lid";
}

$sql = "SELECT a.id AS att_id, a.rfid_verified, a.finger_verified, a.method, a.is_present, a.timestamp,
               a.override_reason, a.override_by,
               s.id AS student_id, s.student_number,
               u.name AS student_name,
               c.id AS class_id, c.class_name, c.class_code
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN users    u ON s.user_id    = u.id
        JOIN classes  c ON a.class_id   = c.id
        WHERE $where
        ORDER BY a.timestamp DESC
        LIMIT 200";

$res = mysqli_query($conn, $sql);
$records = [];
$total = 0; $present = 0; $absent = 0;

if ($res) while ($row = mysqli_fetch_assoc($res)) {
    $total++;
    if ($row['is_present']) $present++;
    else                    $absent++;

    $records[] = $row;
}

echo json_encode([
    'status'  => 'success',
    'records' => $records,
    'summary' => compact('total','present','absent'),
]);
