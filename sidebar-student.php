<?php $cur = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text">AttendX</div>
    <div class="logo-sub">Student Portal</div>
  </div>
  <nav class="sidebar-nav">
    <a href="student-dashboard.php"  class="<?= $cur==='student-dashboard.php'  ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> Dashboard</a>
    <a href="student-attendance.php" class="<?= $cur==='student-attendance.php' ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> My Attendance</a>
    <a href="student-profile.php"    class="<?= $cur==='student-profile.php'    ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> My Profile</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Student') ?></div>
    <div class="user-role">Student</div>
    <a class="logout-link" href="/attendx/api/logout.php">Sign out</a>
  </div>
</aside>
