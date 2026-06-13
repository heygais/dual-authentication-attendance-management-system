<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    $redirects = ['student'=>'student-dashboard.php','lecturer'=>'lecturer-dashboard.php','admin'=>'admin-dashboard.php'];
    header('Location: ' . ($redirects[$_SESSION['role']] ?? 'login.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — AttendX</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="app-name">AttendX</div>
      <div class="app-tagline">Hybrid Attendance System</div>
    </div>

    <div class="role-toggle" id="roleToggle">
      <button class="role-btn active" data-role="student"  onclick="setRole('student',this)">Student</button>
      <button class="role-btn"        data-role="lecturer" onclick="setRole('lecturer',this)">Lecturer</button>
      <button class="role-btn"        data-role="admin"    onclick="setRole('admin',this)">Admin</button>
    </div>

    <div class="demo-box" id="demoBox">
      <h4>Demo Credentials</h4>
      <p id="demoUser"><b>Username:</b> 2021001234</p>
      <p id="demoPass"><b>Password:</b> password123</p>
    </div>

    <div id="errorMsg" class="alert alert-danger hidden"></div>

    <form id="loginForm">
      <div class="form-group">
        <label class="form-label" for="username">Username / Student ID</label>
        <input class="form-control" type="text" id="username" placeholder="Enter your username" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" placeholder="Enter your password" required>
      </div>
      <button type="submit" class="btn btn-primary w-full" id="submitBtn">Sign In</button>
    </form>

    <div style="text-align:center;margin-top:14px;">
      <a href="forgot-password.php" style="font-size:12px;color:#6b7280;">Forgot password?</a>
    </div>
  </div>
</div>

<script>
const demoCredentials = {
  student:  { user: '2021001234', pass: 'password123' },
  lecturer: { user: 'lecturer01', pass: 'password123' },
  admin:    { user: 'admin',      pass: 'admin123' },
};
let currentRole = 'student';

function setRole(role, btn) {
  currentRole = role;
  document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const c = demoCredentials[role];
  document.getElementById('demoUser').innerHTML = '<b>Username:</b> ' + c.user;
  document.getElementById('demoPass').innerHTML = '<b>Password:</b> ' + c.pass;
}

document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  const err = document.getElementById('errorMsg');
  btn.disabled = true;
  btn.textContent = 'Signing in...';
  err.classList.add('hidden');

  try {
    const res = await fetch('/attendx/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username: document.getElementById('username').value.trim(),
        password: document.getElementById('password').value,
        role: currentRole,
      }),
    });
    const data = await res.json();
    if (data.status === 'success') {
      window.location.href = data.redirect;
    } else {
      err.textContent = data.message || 'Login failed.';
      err.classList.remove('hidden');
      btn.disabled = false;
      btn.textContent = 'Sign In';
    }
  } catch (ex) {
    err.textContent = 'Server error. Is XAMPP running?';
    err.classList.remove('hidden');
    btn.disabled = false;
    btn.textContent = 'Sign In';
  }
});
</script>
</body>
</html>
