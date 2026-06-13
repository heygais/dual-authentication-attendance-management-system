<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

// Returns the most recent active session, if any.
$sql = "SELECT s.id, s.class_id, s.start_time, s.lecturer_id,
               c.class_name, c.class_code,
               u.name AS lecturer_name
        FROM sessions s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON s.lecturer_id = u.id
        WHERE s.status = 'active'
        ORDER BY s.start_time DESC
        LIMIT 1";
$r = mysqli_query($conn, $sql);
$row = $r ? mysqli_fetch_assoc($r) : null;

if ($row) {
    echo json_encode(['status' => 'active', 'session' => $row]);
} else {
    echo json_encode(['status' => 'inactive', 'session' => null]);
}
