<?php
// Returns the classes a given student is enrolled in.
// Used by the Manual Override modal to filter the Class dropdown.
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';

$student_id = (int)($_GET['student_id'] ?? 0);
if (!$student_id) {
    echo json_encode(['status' => 'error', 'message' => 'student_id required', 'classes' => []]);
    exit;
}

$sql = "SELECT c.id, c.class_code, c.class_name
          FROM student_classes sc
          JOIN classes c ON sc.class_id = c.id
         WHERE sc.student_id = $student_id
         ORDER BY c.class_name";
$res = mysqli_query($conn, $sql);

$classes = [];
if ($res) while ($row = mysqli_fetch_assoc($res)) $classes[] = $row;

// If the student isn't enrolled anywhere, fall back to ALL classes
// so the override is still usable.
$fallback = false;
if (empty($classes)) {
    $fallback = true;
    $r = mysqli_query($conn, "SELECT id, class_code, class_name FROM classes ORDER BY class_name");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $classes[] = $row;
}

echo json_encode([
    'status'   => 'success',
    'classes'  => $classes,
    'fallback' => $fallback,
]);
