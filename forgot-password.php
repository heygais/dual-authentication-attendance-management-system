<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password — AttendX</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="app-name">AttendX</div>
      <div class="app-tagline">Password Reset</div>
    </div>

    <div id="step1">
      <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Enter your student ID or email address and we will send you a reset code.</p>

      <div class="role-toggle">
        <button class="role-btn active" onclick="setRole('student',this)">Student</button>
        <button class="role-btn"        onclick="setRole('lecturer',this)">Lecturer</button>
        <button class="role-btn"        onclick="setRole('admin',this)">Admin</button>
      </div>

      <div id="reqError" class="alert alert-danger hidden"></div>

      <div class="form-group">
        <label class="form-label">Student ID / Email</label>
        <input class="form-control" type="text" id="identifier" placeholder="e.g. 2021001234">
      </div>
      <button class="btn btn-primary w-full" onclick="requestReset()">Send Reset Code</button>
      <div style="text-align:center;margin-top:12px;">
        <a href="login.php" style="font-size:12px;color:#6b7280;">Back to login</a>
      </div>
    </div>

    <div id="step2" class="hidden">
      <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Enter the 6-digit code sent to your email and choose a new password.</p>
      <div id="verError" class="alert alert-danger hidden"></div>
      <div class="form-group">
        <label class="form-label">6-Digit Code</label>
        <input class="form-control" type="text" id="token" maxlength="6" placeholder="000000">
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input class="form-control" type="password" id="newPass" placeholder="Min 8 characters" oninput="checkStrength(this.value)">
        <div class="pw-strength mt-1">
          <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
          <div class="pw-text" id="pwText"></div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input class="form-control" type="password" id="confPass" placeholder="Repeat password">
      </div>
      <button class="btn btn-primary w-full" onclick="verifyReset()">Reset Password</button>
    </div>

    <div id="step3" class="hidden" style="text-align:center;padding:20px 0;">
      <div style="font-size:36px;margin-bottom:12px;">&#10003;</div>
      <p style="font-weight:600;margin-bottom:6px;">Password Reset!</p>
      <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Your password has been updated.</p>
      <a href="login.php" class="btn btn-primary">Go to Login</a>
    </div>
  </div>
</div>

<script>
let currentRole = 'student';
let userId = null;

function setRole(role, btn) {
  currentRole = role;
  document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

async function requestReset() {
  const id  = document.getElementById('identifier').value.trim();
  const err = document.getElementById('reqError');
  err.classList.add('hidden');
  if (!id) { err.textContent = 'Please enter your ID or email.'; err.classList.remove('hidden'); return; }

  const fd = new FormData();
  fd.append('action', 'request');
  fd.append('identifier', id);
  fd.append('role', currentRole);

  const res  = await fetch('/attendx/api/reset_password.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.status === 'success') {
    userId = data.user_id;
    document.getElementById('step1').classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
    alert(data.message);
  } else {
    err.textContent = data.message;
    err.classList.remove('hidden');
  }
}

function checkStrength(pw) {
  let score = 0;
  if (pw.length >= 8)              score++;
  if (/[A-Z]/.test(pw))           score++;
  if (/[0-9]/.test(pw))           score++;
  if (/[^A-Za-z0-9]/.test(pw))   score++;
  const fill   = document.getElementById('pwFill');
  const labels = ['','Weak','Fair','Good','Strong'];
  const colors = ['','#dc2626','#f59e0b','#2563eb','#16a34a'];
  fill.style.width   = (score * 25) + '%';
  fill.style.background = colors[score] || '#e9ecf0';
  document.getElementById('pwText').textContent = labels[score] || '';
}

async function verifyReset() {
  const token  = document.getElementById('token').value.trim();
  const newP   = document.getElementById('newPass').value;
  const confP  = document.getElementById('confPass').value;
  const err    = document.getElementById('verError');
  err.classList.add('hidden');

  if (newP !== confP) { err.textContent = 'Passwords do not match.'; err.classList.remove('hidden'); return; }
  if (newP.length < 6) { err.textContent = 'Password too short.'; err.classList.remove('hidden'); return; }

  const fd = new FormData();
  fd.append('action', 'verify');
  fd.append('user_id', userId);
  fd.append('token', token);
  fd.append('password', newP);

  const res  = await fetch('/attendx/api/reset_password.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.status === 'success') {
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step3').classList.remove('hidden');
  } else {
    err.textContent = data.message;
    err.classList.remove('hidden');
  }
}
</script>
</body>
</html>
