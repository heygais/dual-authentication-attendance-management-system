<?php $cur = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text">AttendX</div>
    <div class="logo-sub">Attendance System</div>
  </div>
  <nav class="sidebar-nav">
    <a href="admin-dashboard.php"  class="<?= $cur==='admin-dashboard.php'  ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> Dashboard</a>
    <a href="admin-students.php"   class="<?= $cur==='admin-students.php'   ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> Students</a>
    <a href="admin-classes.php"    class="<?= $cur==='admin-classes.php'    ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> Classes</a>
    <a href="admin-attendance.php" class="<?= $cur==='admin-attendance.php' ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> Attendance</a>
    <a href="admin-reports.php"    class="<?= $cur==='admin-reports.php'    ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> Reports</a>
    <div class="sidebar-divider"></div>
    <a href="register-student.php"  class="<?= $cur==='register-student.php'  ? 'active':'' ?>"><span class="nav-icon">+</span> Add Student</a>
    <a href="register-lecturer.php" class="<?= $cur==='register-lecturer.php' ? 'active':'' ?>"><span class="nav-icon">+</span> Add Lecturer</a>
    <a href="register-rfid.php"        class="<?= $cur==='register-rfid.php'        ? 'active':'' ?>"><span class="nav-icon">+</span> Register RFID</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></div>
    <div class="user-role">Administrator</div>
    <a class="logout-link" href="/attendx/api/logout.php">Sign out</a>
  </div>
</aside>
