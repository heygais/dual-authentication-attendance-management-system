<?php
/* ============================================================
   AttendX — attendance.php  (RFID-only, single-step)
   ------------------------------------------------------------
   Receives a scan from the ESP32:
       POST uid = RFID card UID (string, e.g. "04 A3 B2 C1")

   Returns a PLAIN-TEXT status string for the microcontroller:
       NO_SESSION      – no active session in `sessions`
       NOT_ENROLLED    – student is not in this subject
       ALREADY_MARKED  – student already scanned this session
       NOT_FOUND       – UID doesn't match any student
       SUCCESS         – attendance recorded (present)

   Also still supports:
       GET ?override=1 + POST fields  – lecturer/admin manual override
   ============================================================ */

header('Content-Type: text/plain');
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ─── 1. MANUAL OVERRIDE (RFID-only) ─────────────────────────
if (isset($_GET['override']) && $_GET['override'] === '1') {
    header('Content-Type: application/json');
    $att_id     = (int)($_POST['att_id']     ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);
    $class_id   = (int)($_POST['class_id']   ?? 0);
    $rfid       = (int)($_POST['rfid']        ?? 0);
    $is_present = (int)($_POST['is_present']  ?? 0);
    $date       = isset($_POST['date'])   ? mysqli_real_escape_string($conn, $_POST['date']) : date('Y-m-d');
    $reason     = isset($_POST['reason']) ? mysqli_real_escape_string($conn, trim($_POST['reason'])) : '';
    $by         = (int)($_SESSION['user_id'] ?? 0);

    if (!$student_id || !$class_id) {
        echo json_encode(['status'=>'error','message'=>'Student and class are required.']);
        exit;
    }

    if ($att_id) {
        mysqli_query($conn, "UPDATE attendance SET rfid_verified=$rfid, is_present=$is_present, method='Manual', override_by=$by, override_reason='$reason' WHERE id=$att_id");
    } else {
        $ts = $date . ' ' . date('H:i:s');
        mysqli_query($conn, "INSERT INTO attendance (student_id,class_id,rfid_verified,finger_verified,method,is_present,override_by,override_reason,timestamp) VALUES ($student_id,$class_id,$rfid,0,'Manual',$is_present,$by,'$reason','$ts')");
    }

    echo json_encode(['status'=>'success','message'=>'Attendance override saved.']);
    exit;
}

// ─── 2. READ INPUT ──────────────────────────────────────────
// Accept form-encoded POST (ESP32) and legacy JSON {uid|identifier}
$uid = trim($_POST['uid'] ?? '');

if ($uid === '') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (is_array($body)) {
        $uid = trim($body['uid'] ?? $body['identifier'] ?? '');
    }
}

if ($uid === '') {
    echo "NO_DATA";
    exit;
}

$uid_esc = mysqli_real_escape_string($conn, $uid);

// ─── 3. CHECK FOR ACTIVE SESSION ────────────────────────────
$sr = mysqli_query($conn,
    "SELECT s.id, s.class_id, c.class_name, c.class_code
       FROM sessions s
       JOIN classes c ON s.class_id = c.id
      WHERE s.status = 'active'
      ORDER BY s.start_time DESC
      LIMIT 1");
$session = $sr ? mysqli_fetch_assoc($sr) : null;

if (!$session) {
    echo "NO_SESSION";
    exit;
}

$session_id = (int)$session['id'];
$class_id   = (int)$session['class_id'];

// ─── 4. RESOLVE STUDENT BY RFID ─────────────────────────────
$r = mysqli_query($conn,
    "SELECT s.id, s.student_number, u.name
       FROM students s
       JOIN users u ON s.user_id = u.id
      WHERE s.rfid_uid = '$uid_esc'
      LIMIT 1");
$student = $r ? mysqli_fetch_assoc($r) : null;

if (!$student) {
    echo "NOT_FOUND";
    exit;
}

$student_id = (int)$student['id'];

// ─── 5. CHECK ENROLLMENT ────────────────────────────────────
$er = mysqli_query($conn,
    "SELECT id FROM student_classes
      WHERE student_id = $student_id AND class_id = $class_id
      LIMIT 1");
if (!$er || mysqli_num_rows($er) === 0) {
    echo "NOT_ENROLLED";
    exit;
}

// ─── 6. PREVENT DUPLICATE FOR THIS SESSION ──────────────────
$dr = mysqli_query($conn,
    "SELECT id FROM attendance
      WHERE student_id = $student_id AND session_id = $session_id
      LIMIT 1");
if ($dr && mysqli_num_rows($dr) > 0) {
    echo "ALREADY_MARKED";
    exit;
}

// ─── 7. INSERT — single RFID tap marks PRESENT ──────────────
$ok = mysqli_query($conn,
    "INSERT INTO attendance
       (student_id, class_id, session_id, rfid_verified, finger_verified, method, is_present)
     VALUES
       ($student_id, $class_id, $session_id, 1, 0, 'RFID', 1)");

if (!$ok) {
    echo "DB_ERROR: " . mysqli_error($conn);
    exit;
}

echo "SUCCESS";
