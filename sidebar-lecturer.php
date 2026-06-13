<?php $cur = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text">AttendX</div>
    <div class="logo-sub">Lecturer Portal</div>
  </div>
  <nav class="sidebar-nav">
    <a href="lecturer-dashboard.php" class="<?= $cur==='lecturer-dashboard.php' ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> Dashboard</a>
    <a href="admin-attendance.php"   class="<?= $cur==='admin-attendance.php'   ? 'active':'' ?>"><span class="nav-icon">&#9632;</span> Class Records</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Lecturer') ?></div>
    <div class="user-role">Lecturer</div>
    <a class="logout-link" href="/attendx/api/logout.php">Sign out</a>
  </div>
</aside>
