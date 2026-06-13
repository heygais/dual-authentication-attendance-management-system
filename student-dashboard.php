<?php
require_once '../includes/auth.php';
requireRole('student');
require_once '../includes/db.php';

$uid = $_SESSION['user_id'];
$sid_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, student_number, programme FROM students WHERE user_id=$uid"));
$sid = $sid_row ? (int)$sid_row['id'] : 0;

function scalar($conn, $sql) {
    $r = mysqli_query($conn, $sql);
    if (!$r) return 0;
    $row = mysqli_fetch_row($r);
    return $row ? (int)$row[0] : 0;
}

// Stats
$total_sessions = scalar($conn, "SELECT COUNT(*) FROM attendance WHERE student_id=$sid");
$present        = scalar($conn, "SELECT COUNT(*) FROM attendance WHERE student_id=$sid AND is_present=1");
$rate           = $total_sessions ? round($present / $total_sessions * 100) : 0;
$enrolled       = scalar($conn, "SELECT COUNT(*) FROM student_classes WHERE student_id=$sid");

// Per-subject attendance %
$subj_res = mysqli_query($conn, "SELECT c.class_name, COUNT(a.id) tot, SUM(a.is_present) pre
    FROM student_classes sc
    JOIN classes c ON sc.class_id=c.id
    LEFT JOIN attendance a ON a.class_id=c.id AND a.student_id=$sid
    WHERE sc.student_id=$sid
    GROUP BY c.id");
$subjects = [];
while ($s = mysqli_fetch_assoc($subj_res)) {
    $s['pct'] = $s['tot'] ? round($s['pre']/$s['tot']*100) : 0;
    $subjects[] = $s;
}

// Recent 5 attendance records
$recent_res = mysqli_query($conn, "SELECT a.*, c.class_name FROM attendance a JOIN classes c ON a.class_id=c.id WHERE a.student_id=$sid ORDER BY a.timestamp DESC LIMIT 5");
$recent = [];
while ($r = mysqli_fetch_assoc($recent_res)) $recent[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — AttendX Student</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-student.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>My Dashboard</h1>
      <div class="header-actions">
        <span style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($sid_row['student_number'] ?? '') ?></span>
      </div>
    </header>
    <div class="content">

      <div class="stat-grid">
        <div class="stat-card <?= $rate>=80?'green':($rate>=60?'yellow':'red') ?>">
          <div class="stat-label">Overall Rate</div>
          <div class="stat-value"><?= $rate ?>%</div>
          <div class="stat-change <?= $rate>=80?'up':($rate<60?'down':'') ?>"><?= $rate>=80?'Good standing':($rate<80?'Below threshold':'') ?></div>
        </div>
        <div class="stat-card blue">
          <div class="stat-label">Sessions Present</div>
          <div class="stat-value"><?= $present ?></div>
          <div class="stat-change">of <?= $total_sessions ?> total</div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-label">Enrolled Classes</div>
          <div class="stat-value"><?= $enrolled ?></div>
          <div class="stat-change"><?= htmlspecialchars($sid_row['programme'] ?? '') ?></div>
        </div>
        <div class="stat-card blue">
          <div class="stat-label">Today</div>
          <div class="stat-value" style="font-size:18px;"><?= date('d M') ?></div>
          <div class="stat-change"><?= date('l') ?></div>
        </div>
      </div>

      <div class="grid-2" style="margin-bottom:20px;">
        <!-- Per-subject chart -->
        <div class="card">
          <div class="card-header"><h3>Attendance by Subject</h3></div>
          <div class="card-body">
            <?php if (empty($subjects)): ?>
            <p class="text-muted text-small">Not enrolled in any class yet.</p>
            <?php else: foreach ($subjects as $s): ?>
            <div style="margin-bottom:12px;">
              <div class="flex justify-between mb-1">
                <span style="font-size:13px;color:#374151;"><?= htmlspecialchars($s['class_name']) ?></span>
                <span style="font-size:12px;color:#6b7280;"><?= $s['pct'] ?>%</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill <?= $s['pct']<80?'danger':($s['pct']<90?'warn':'success') ?>" style="width:<?= $s['pct'] ?>%"></div>
              </div>
              <div style="font-size:11px;color:#9ca3af;margin-top:3px;"><?= $s['pre'] ?>/<?= $s['tot'] ?> sessions</div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <!-- Recent scans -->
        <div class="card">
          <div class="card-header"><h3>Recent Scans</h3></div>
          <div class="card-body" style="padding:0 18px;">
            <?php if (empty($recent)): ?>
            <p class="text-muted text-small" style="padding:20px 0;">No attendance records yet.</p>
            <?php else: foreach ($recent as $r): ?>
            <?php $dot = $r['is_present'] ? 'present' : 'absent'; ?>
            <div class="feed-item">
              <span class="feed-dot <?= $dot ?>"></span>
              <div class="feed-info">
                <div class="feed-name"><?= htmlspecialchars($r['class_name']) ?></div>
                <div class="feed-class"><?= $r['timestamp'] ? substr($r['timestamp'],0,16) : '' ?></div>
              </div>
              <div class="feed-right">
                <div class="feed-badges">
                  <?= $r['rfid_verified']   ? '<span class="badge badge-success">Card &#10003;</span>'   : '<span class="badge badge-danger">Card &#10007;</span>' ?>
                </div>
              </div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <?php if ($rate < 80): ?>
      <div class="alert alert-danger">
        Your attendance rate is <b><?= $rate ?>%</b>, which is below the 80% requirement. Please contact your lecturer.
      </div>
      <?php endif; ?>

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
