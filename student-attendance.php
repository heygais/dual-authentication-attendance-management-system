<?php
require_once '../includes/auth.php';
requireRole('student');
require_once '../includes/db.php';

$uid    = $_SESSION['user_id'];
$sid_r  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM students WHERE user_id=$uid"));
$sid    = $sid_r ? (int)$sid_r['id'] : 0;
$month  = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$from   = $month . '-01';
$to     = date('Y-m-t', strtotime($from));

// Monthly attendance map
$att_res = mysqli_query($conn, "SELECT DATE(timestamp) dt, is_present FROM attendance WHERE student_id=$sid AND DATE(timestamp) BETWEEN '$from' AND '$to'");
$att_map = [];
if ($att_res) while ($r = mysqli_fetch_assoc($att_res)) {
    $dt = $r['dt'];
    if (!isset($att_map[$dt])) $att_map[$dt] = ['present'=>0,'partial'=>0,'absent'=>0];
    if ($r['is_present']) $att_map[$dt]['present']++;
    else $att_map[$dt]['absent']++;
}

// Per-subject progress
$subj_res = mysqli_query($conn, "SELECT c.class_name, COUNT(a.id) tot, SUM(a.is_present) pre
    FROM student_classes sc JOIN classes c ON sc.class_id=c.id
    LEFT JOIN attendance a ON a.class_id=c.id AND a.student_id=$sid
    WHERE sc.student_id=$sid GROUP BY c.id");
$subjects = [];
while ($s = mysqli_fetch_assoc($subj_res)) {
    $s['pct'] = $s['tot'] ? round($s['pre']/$s['tot']*100) : 0;
    $subjects[] = $s;
}

// All records this month
$records_res = mysqli_query($conn, "SELECT a.*, c.class_name FROM attendance a JOIN classes c ON a.class_id=c.id WHERE a.student_id=$sid AND DATE(a.timestamp) BETWEEN '$from' AND '$to' ORDER BY a.timestamp DESC");
$records = [];
while ($r = mysqli_fetch_assoc($records_res)) $records[] = $r;

// Calendar setup
$first_dow  = (int)date('N', strtotime($from)); // 1=Mon
$days_in_mo = (int)date('t', strtotime($from));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Attendance — AttendX</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-student.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>My Attendance</h1>
      <div class="header-actions">
        <a href="?month=<?= date('Y-m', strtotime($from . ' -1 month')) ?>" class="btn btn-secondary btn-sm">&larr;</a>
        <span style="font-size:13px;font-weight:500;min-width:110px;text-align:center;"><?= date('F Y', strtotime($from)) ?></span>
        <a href="?month=<?= date('Y-m', strtotime($from . ' +1 month')) ?>" class="btn btn-secondary btn-sm">&rarr;</a>
      </div>
    </header>
    <div class="content">

      <div class="grid-2" style="margin-bottom:20px;">
        <!-- Calendar -->
        <div class="card">
          <div class="card-header"><h3>Calendar — <?= date('F Y', strtotime($from)) ?></h3></div>
          <div class="card-body">
            <div class="calendar">
              <?php foreach (['Mo','Tu','We','Th','Fr','Sa','Su'] as $d): ?>
              <div class="cal-head"><?= $d ?></div>
              <?php endforeach; ?>
              <?php for ($i = 1; $i < $first_dow; $i++): ?>
              <div class="cal-day empty"></div>
              <?php endfor; ?>
              <?php for ($d = 1; $d <= $days_in_mo; $d++):
                $dt  = sprintf('%s-%02d', $month, $d);
                $cls = 'empty';
                if (isset($att_map[$dt])) {
                    if ($att_map[$dt]['present'])      $cls = 'present';
                    elseif ($att_map[$dt]['partial'])  $cls = 'incomplete';
                    elseif ($att_map[$dt]['absent'])   $cls = 'absent';
                }
                if ($dt === date('Y-m-d')) $cls .= ' today';
              ?>
              <div class="cal-day <?= $cls ?>"><?= $d ?></div>
              <?php endfor; ?>
            </div>
            <div style="display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;">
              <span style="font-size:11px;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;background:#dcfce7;border-radius:2px;display:inline-block;"></span> Present</span>
              <span style="font-size:11px;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;background:#fef3c7;border-radius:2px;display:inline-block;"></span> Incomplete</span>
              <span style="font-size:11px;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;background:#fee2e2;border-radius:2px;display:inline-block;"></span> Absent</span>
            </div>
          </div>
        </div>

        <!-- Subject progress -->
        <div class="card">
          <div class="card-header"><h3>Subject Progress</h3></div>
          <div class="card-body">
            <?php if (empty($subjects)): ?>
            <p class="text-muted text-small">Not enrolled in any class.</p>
            <?php else: foreach ($subjects as $s): ?>
            <div style="margin-bottom:14px;">
              <div class="flex justify-between mb-1">
                <span style="font-size:13px;font-weight:500;"><?= htmlspecialchars($s['class_name']) ?></span>
                <span class="badge <?= $s['pct']>=80?'badge-success':($s['pct']>=60?'badge-warn':'badge-danger') ?>"><?= $s['pct'] ?>%</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill <?= $s['pct']<80?'danger':($s['pct']<90?'warn':'success') ?>" style="width:<?= $s['pct'] ?>%"></div>
              </div>
              <div style="font-size:11px;color:#9ca3af;margin-top:3px;"><?= $s['pre'] ?>/<?= $s['tot'] ?> present</div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <!-- Record history -->
      <div class="card">
        <div class="card-header"><h3>Scan History — <?= date('F Y', strtotime($from)) ?></h3></div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Date / Time</th><th>Class</th><th>RFID</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if (empty($records)): ?>
              <tr><td colspan="4" class="table-empty">No records for this month.</td></tr>
              <?php else: foreach ($records as $r): ?>
              <tr>
                <td style="font-size:12px;"><?= substr($r['timestamp'],0,16) ?></td>
                <td><?= htmlspecialchars($r['class_name']) ?></td>
                <td><?= $r['rfid_verified']   ? '<span class="badge badge-success">Card &#10003;</span>'   : '<span class="badge badge-danger">Card &#10007;</span>' ?></td>
                <td><?= $r['is_present']
                  ? '<span class="badge badge-success">Present</span>'
                  : '<span class="badge badge-danger">Absent</span>' ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('open'); }
function closeSidebar()   { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }
</script>
</body>
</html>
