<?php
// Returns the enrolled students of a class, grouped by today's attendance:
//   present — has an attendance record with is_present = 1 (RFID tap)
//   absent  — no record today, or record not marked present
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';

$class_id = (int)($_GET['class_id'] ?? 0);
$date     = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : date('Y-m-d');

if (!$class_id) {
    echo json_encode(['status' => 'error', 'message' => 'class_id required']);
    exit;
}

$cn = mysqli_query($conn, "SELECT class_name FROM classes WHERE id=$class_id");
$class_name = ($cn && $row = mysqli_fetch_assoc($cn)) ? $row['class_name'] : '';

$sql = "SELECT s.id, s.student_number, u.name,
               COUNT(a.id)           AS rec_count,
               MAX(a.is_present)     AS is_present,
               MAX(a.rfid_verified)  AS rfid
        FROM student_classes sc
        JOIN students s ON sc.student_id = s.id
        JOIN users    u ON s.user_id     = u.id
        LEFT JOIN attendance a
               ON a.student_id = s.id
              AND a.class_id   = sc.class_id
              AND DATE(a.timestamp) = '$date'
        WHERE sc.class_id = $class_id
        GROUP BY s.id
        ORDER BY u.name";
$res = mysqli_query($conn, $sql);

$present = []; $absent = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $person = [
            'name'           => $r['name'],
            'student_number' => $r['student_number'],
            'rfid'           => (int)$r['rfid'],
        ];
        if ((int)$r['rec_count'] > 0 && (int)$r['is_present'] === 1) {
            $present[] = $person;
        } else {
            $absent[] = $person;
        }
    }
}

echo json_encode([
    'status'     => 'success',
    'class_name' => $class_name,
    'present'    => $present,
    'absent'     => $absent,
    'counts'     => [
        'present' => count($present),
        'absent'  => count($absent),
        'total'   => count($present) + count($absent),
    ],
]);
