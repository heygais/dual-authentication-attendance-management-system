<?php
require_once '../includes/auth.php';
requireRole('student');
require_once '../includes/db.php';

$uid    = $_SESSION['user_id'];
$user_r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$uid")) ?: [];
$stu_r  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM students WHERE user_id=$uid")) ?: [];
$stu_id = $stu_r['student_number'] ?? '';
$sid    = (int)($stu_r['id'] ?? 0);

// Classes
$classes = [];
if ($sid) {
    $class_res = mysqli_query($conn, "SELECT c.class_name, c.class_code, c.venue, c.schedule FROM student_classes sc JOIN classes c ON sc.class_id=c.id WHERE sc.student_id=$sid");
    if ($class_res) while ($c = mysqli_fetch_assoc($class_res)) $classes[] = $c;
}

// QR pattern — simple deterministic grid from student ID
$qr_bits = '';
for ($i = 0; $i < 49; $i++) $qr_bits .= (crc32($stu_id . $i) % 2 == 0) ? '1' : '0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile — AttendX</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-student.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>My Profile</h1>
    </header>
    <div class="content">
      <div class="grid-2" style="align-items:start;">

        <!-- Profile info -->
        <div class="card">
          <div class="card-header"><h3>Profile Information</h3></div>
          <div class="card-body">
            <div style="display:flex;gap:16px;align-items:flex-start;margin-bottom:20px;">
              <div style="width:60px;height:60px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;color:#2563eb;flex-shrink:0;">
                <?= strtoupper(substr($user_r['name'],0,1)) ?>
              </div>
              <div>
                <div style="font-size:16px;font-weight:600;"><?= htmlspecialchars($user_r['name']) ?></div>
                <div style="font-size:12px;color:#9ca3af;"><?= htmlspecialchars($stu_r['student_number'] ?? '') ?></div>
                <div style="font-size:12px;color:#9ca3af;"><?= htmlspecialchars($stu_r['programme'] ?? '') ?> — Year <?= $stu_r['year_of_study'] ?? '' ?></div>
              </div>
            </div>

            <table style="width:100%;font-size:13px;">
              <tr><td style="color:#6b7280;padding:5px 0;width:130px;">Email</td><td><?= htmlspecialchars($user_r['email']) ?></td></tr>
              <tr><td style="color:#6b7280;padding:5px 0;">Phone</td><td><?= htmlspecialchars($stu_r['phone'] ?? '—') ?></td></tr>
              <tr><td style="color:#6b7280;padding:5px 0;">Username</td><td><?= htmlspecialchars($user_r['username'] ?? '') ?></td></tr>
              <tr><td style="color:#6b7280;padding:5px 0;">Registered</td><td><?= !empty($user_r['created_at']) ? substr($user_r['created_at'],0,10) : '—' ?></td></tr>
            </table>

            <?php if (empty($stu_r)): ?>
            <hr class="divider">
            <div class="alert alert-warn">No student profile record found. Please contact your administrator to complete your enrollment.</div>
            <?php else: ?>
            <hr class="divider">
            <div style="font-size:13px;font-weight:600;margin-bottom:10px;">Card Status</div>
            <div class="flex gap-2">
              <?php if (!empty($stu_r['rfid_uid'])): ?>
              <span class="badge badge-success">Card &#10003; Registered</span>
              <?php else: ?>
              <span class="badge badge-danger">Card &#10007; Not set</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <hr class="divider">
            <a href="forgot-password.php" class="btn btn-secondary btn-sm">Change Password</a>
          </div>
        </div>

        <div>
          <!-- QR Code (backup auth) -->
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><h3>QR Backup Code</h3></div>
            <div class="card-body" style="display:flex;flex-direction:column;align-items:center;gap:10px;">
              <div class="qr-box">
                <div class="qr-pattern">
                  <?php for ($i = 0; $i < 49; $i++): ?>
                  <div class="qr-cell <?= $qr_bits[$i]==='0' ? 'w' : '' ?>"></div>
                  <?php endfor; ?>
                </div>
              </div>
              <div style="font-size:13px;font-weight:600;letter-spacing:2px;"><?= htmlspecialchars($stu_id) ?></div>
              <p style="font-size:11px;color:#9ca3af;text-align:center;">Show this to your lecturer if the RFID scanner is unavailable.</p>
            </div>
          </div>

          <!-- Enrolled classes -->
          <div class="card">
            <div class="card-header"><h3>Enrolled Classes</h3></div>
            <div class="card-body" style="padding:0 18px;">
              <?php if (empty($classes)): ?>
              <p class="text-muted text-small" style="padding:16px 0;">Not enrolled in any class.</p>
              <?php else: foreach ($classes as $c): ?>
              <div class="feed-item">
                <div class="feed-info">
                  <div class="feed-name"><?= htmlspecialchars($c['class_name']) ?></div>
                  <div class="feed-class"><?= htmlspecialchars($c['venue']) ?> — <?= htmlspecialchars($c['schedule']) ?></div>
                </div>
                <span class="badge badge-info"><?= htmlspecialchars($c['class_code']) ?></span>
              </div>
              <?php endforeach; endif; ?>
            </div>
          </div>
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
