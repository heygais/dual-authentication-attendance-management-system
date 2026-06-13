<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register Lecturer — AttendX Admin</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-admin.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Register Lecturer</h1>
      <div class="header-actions"><a href="admin-dashboard.php" class="btn btn-secondary btn-sm">&larr; Back</a></div>
    </header>
    <div class="content">
      <div style="max-width:600px;">

        <div class="steps" style="margin-bottom:24px;">
          <div class="step-item active" id="stepItem1"><div class="step-num">1</div><div class="step-label">Staff Details</div></div>
          <div class="step-line"></div>
          <div class="step-item" id="stepItem2"><div class="step-num">2</div><div class="step-label">Account Setup</div></div>
          <div class="step-line"></div>
          <div class="step-item" id="stepItem3"><div class="step-num">3</div><div class="step-label">Confirm</div></div>
        </div>

        <div id="errMsg" class="alert alert-danger hidden"></div>

        <!-- Step 1 -->
        <div class="card step-panel active" id="panel1">
          <div class="card-header"><h3>Step 1 — Staff Details</h3></div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input class="form-control" id="name" placeholder="Dr. / Mr. / Ms. Full Name">
              </div>
              <div class="form-group">
                <label class="form-label">Staff ID *</label>
                <input class="form-control" id="staffId" placeholder="e.g. STF001">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Department</label>
                <input class="form-control" id="department" placeholder="e.g. Computer Science">
              </div>
              <div class="form-group">
                <label class="form-label">Designation</label>
                <input class="form-control" id="designation" placeholder="e.g. Senior Lecturer">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" id="email" placeholder="lecturer@university.edu.my">
              </div>
              <div class="form-group">
                <label class="form-label">Phone</label>
                <input class="form-control" id="phone" placeholder="01x-xxxxxxx">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Login Username</label>
              <input class="form-control" id="username" placeholder="Username for login (default: staff ID)">
            </div>
            <div class="flex justify-between mt-2">
              <span></span>
              <button class="btn btn-primary" onclick="goStep(2)">Next &rarr;</button>
            </div>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="card step-panel hidden" id="panel2">
          <div class="card-header"><h3>Step 2 — Account Setup</h3></div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Username</label>
              <input class="form-control" id="usernameDisplay" readonly style="background:#f9fafb;">
            </div>
            <div class="form-group">
              <label class="form-label">Password *</label>
              <input class="form-control" type="password" id="password" placeholder="Min 8 characters" oninput="checkStrength(this.value)">
              <div class="pw-strength mt-1">
                <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
                <div class="pw-text" id="pwText"></div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Confirm Password *</label>
              <input class="form-control" type="password" id="confPassword">
            </div>
            <div class="flex justify-between mt-2">
              <button class="btn btn-secondary" onclick="goStep(1)">&larr; Back</button>
              <button class="btn btn-primary" onclick="goStep(3)">Next &rarr;</button>
            </div>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="card step-panel hidden" id="panel3">
          <div class="card-header"><h3>Step 3 — Confirm</h3></div>
          <div class="card-body">
            <table style="width:100%;font-size:13px;">
              <tr><td style="color:#6b7280;padding:6px 0;width:140px;">Name</td><td id="c_name" style="font-weight:500;"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Staff ID</td><td id="c_sid"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Department</td><td id="c_dept"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Designation</td><td id="c_desig"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Email</td><td id="c_email"></td></tr>
            </table>
            <hr class="divider">
            <div class="alert alert-info">Username will be <b id="c_user"></b>.</div>
            <div class="flex justify-between mt-2">
              <button class="btn btn-secondary" onclick="goStep(2)">&larr; Back</button>
              <button class="btn btn-primary" onclick="submitForm()" id="submitBtn">Register Lecturer</button>
            </div>
          </div>
        </div>

        <div class="card hidden" id="donePanel" style="text-align:center;padding:32px;">
          <div style="font-size:40px;margin-bottom:12px;">&#10003;</div>
          <p style="font-size:16px;font-weight:600;margin-bottom:6px;">Lecturer Registered!</p>
          <div class="flex gap-2" style="justify-content:center;margin-top:16px;">
            <a href="register-lecturer.php" class="btn btn-primary">Register Another</a>
            <a href="admin-dashboard.php"   class="btn btn-secondary">Dashboard</a>
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
function checkStrength(pw) {
  let score = 0;
  if (pw.length>=8) score++; if(/[A-Z]/.test(pw)) score++; if(/[0-9]/.test(pw)) score++; if(/[^A-Za-z0-9]/.test(pw)) score++;
  const fill=document.getElementById('pwFill');
  const colors=['','#dc2626','#f59e0b','#2563eb','#16a34a'];
  const labels=['','Weak','Fair','Good','Strong'];
  fill.style.width=(score*25)+'%'; fill.style.background=colors[score]||'#e9ecf0';
  document.getElementById('pwText').textContent=labels[score]||'';
}
function goStep(n) {
  const err = document.getElementById('errMsg');
  err.classList.add('hidden');
  if (n===2) {
    if (!document.getElementById('name').value.trim())   { err.textContent='Name required.'; err.classList.remove('hidden'); return; }
    if (!document.getElementById('staffId').value.trim()){ err.textContent='Staff ID required.'; err.classList.remove('hidden'); return; }
    const uname = document.getElementById('username').value.trim() || document.getElementById('staffId').value.trim();
    document.getElementById('usernameDisplay').value = uname;
    document.getElementById('username').value = uname;
  }
  if (n===3) {
    const pw=document.getElementById('password').value, cp=document.getElementById('confPassword').value;
    if (pw.length<6) { err.textContent='Password too short.'; err.classList.remove('hidden'); return; }
    if (pw!==cp)     { err.textContent='Passwords do not match.'; err.classList.remove('hidden'); return; }
    document.getElementById('c_name').textContent  = document.getElementById('name').value;
    document.getElementById('c_sid').textContent   = document.getElementById('staffId').value;
    document.getElementById('c_dept').textContent  = document.getElementById('department').value;
    document.getElementById('c_desig').textContent = document.getElementById('designation').value;
    document.getElementById('c_email').textContent = document.getElementById('email').value;
    document.getElementById('c_user').textContent  = document.getElementById('usernameDisplay').value;
  }
  [1,2,3].forEach(i => {
    document.getElementById('panel'+i).classList.toggle('active', i===n);
    document.getElementById('panel'+i).classList.toggle('hidden', i!==n);
    const si = document.getElementById('stepItem'+i);
    si.classList.toggle('active', i===n);
    si.classList.toggle('done', i<n);
  });
}
async function submitForm() {
  const btn = document.getElementById('submitBtn');
  btn.disabled=true; btn.textContent='Registering...';
  const fd = new FormData();
  fd.append('name',        document.getElementById('name').value.trim());
  fd.append('staff_id',    document.getElementById('staffId').value.trim());
  fd.append('department',  document.getElementById('department').value.trim());
  fd.append('designation', document.getElementById('designation').value.trim());
  fd.append('email',       document.getElementById('email').value.trim());
  fd.append('phone',       document.getElementById('phone').value.trim());
  fd.append('username',    document.getElementById('usernameDisplay').value.trim());
  fd.append('password',    document.getElementById('password').value);
  const res=await fetch('/attendx/api/register_lecturer.php',{method:'POST',body:fd});
  const data=await res.json();
  if (data.status==='success') {
    document.getElementById('panel3').classList.add('hidden');
    document.getElementById('donePanel').classList.remove('hidden');
  } else {
    const err=document.getElementById('errMsg');
    err.textContent=data.message; err.classList.remove('hidden');
    btn.disabled=false; btn.textContent='Register Lecturer';
  }
}
</script>
</body>
</html>
