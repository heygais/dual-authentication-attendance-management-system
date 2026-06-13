<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($action === 'list') {
    $res = mysqli_query($conn, "SELECT c.*, u.name AS lecturer_name FROM classes c LEFT JOIN users u ON c.lecturer_id=u.id ORDER BY c.class_name");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    echo json_encode(['status' => 'success', 'classes' => $rows]);
    exit;
}

if ($action === 'add') {
    $code    = mysqli_real_escape_string($conn, trim($_POST['class_code']));
    $name    = mysqli_real_escape_string($conn, trim($_POST['class_name']));
    $lid     = (int)($_POST['lecturer_id'] ?? 0);
    $venue   = mysqli_real_escape_string($conn, trim($_POST['venue'] ?? ''));
    $sched   = mysqli_real_escape_string($conn, trim($_POST['schedule'] ?? ''));
    $max     = (int)($_POST['max_students'] ?? 30);

    if (!$code || !$name) { echo json_encode(['status'=>'error','message'=>'Code and name required.']); exit; }

    if (mysqli_query($conn, "INSERT INTO classes (class_code,class_name,lecturer_id,venue,schedule,max_students) VALUES ('$code','$name'," . ($lid?:NULL) . ",'$venue','$sched',$max)")) {
        echo json_encode(['status'=>'success','message'=>'Class added.','id'=>mysqli_insert_id($conn)]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Class code already exists.']);
    }
    exit;
}

if ($action === 'edit') {
    $id      = (int)$_POST['id'];
    $code    = mysqli_real_escape_string($conn, trim($_POST['class_code']));
    $name    = mysqli_real_escape_string($conn, trim($_POST['class_name']));
    $lid     = (int)($_POST['lecturer_id'] ?? 0);
    $venue   = mysqli_real_escape_string($conn, trim($_POST['venue'] ?? ''));
    $sched   = mysqli_real_escape_string($conn, trim($_POST['schedule'] ?? ''));
    $max     = (int)($_POST['max_students'] ?? 30);
    mysqli_query($conn, "UPDATE classes SET class_code='$code',class_name='$name',lecturer_id=" . ($lid?:NULL) . ",venue='$venue',schedule='$sched',max_students=$max WHERE id=$id");
    echo json_encode(['status'=>'success','message'=>'Class updated.']);
    exit;
}

if ($action === 'delete') {
    $id = (int)$_POST['id'];
    mysqli_query($conn, "DELETE FROM classes WHERE id=$id");
    echo json_encode(['status'=>'success','message'=>'Class deleted.']);
    exit;
}

if ($action === 'enroll') {
    $sid = (int)$_POST['student_id'];
    $cid = (int)$_POST['class_id'];
    if (mysqli_query($conn, "INSERT IGNORE INTO student_classes (student_id,class_id) VALUES ($sid,$cid)")) {
        echo json_encode(['status'=>'success','message'=>'Student enrolled.']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Enrollment failed.']);
    }
    exit;
}

echo json_encode(['status'=>'error','message'=>'Unknown action.']);
