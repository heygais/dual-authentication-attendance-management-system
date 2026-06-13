<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

// Pull classes grouped by course
$classes_res = mysqli_query($conn, "SELECT id, class_code, class_name, COALESCE(course,'General') AS course FROM classes ORDER BY course, class_code, class_name");
$by_course = [];
if ($classes_res) {
    while ($c = mysqli_fetch_assoc($classes_res)) {
        $by_course[$c['course']][] = $c;
    }
}
ksort($by_course);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register Student — AttendX Admin</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .enroll-block { margin-top: 22px; }
    .enroll-head {
      display: flex; align-items: center; gap: 10px;
      font-size: 13px; font-weight: 700; letter-spacing: .08em;
      color: #1f2937; margin-bottom: 10px;
    }
    .enroll-num {
      display: inline-flex; align-items: center; justify-content: center;
      width: 24px; height: 24px; border-radius: 50%;
      background: #2563eb; color: #fff; font-size: 12px; font-weight: 700;
      letter-spacing: 0;
    }
    .enroll-count { margin-left: auto; font-size: 12px; color: #6b7280; font-weight: 500; letter-spacing: 0; }
    .enroll-hint {
      display: flex; align-items: center; gap: 8px;
      margin-top: 8px; padding: 8px 12px;
      background: #eff6ff; border-radius: 8px;
      font-size: 12px; color: #1d4ed8;
    }
    .enroll-hint .dot { width: 6px; height: 6px; border-radius: 50%; background: #2563eb; }
    .enroll-actions { font-size: 12px; margin-bottom: 10px; }
    .enroll-actions a { color: #2563eb; text-decoration: none; font-weight: 500; }
    .enroll-actions a:hover { text-decoration: underline; }
    .enroll-actions .dot-sep { color: #d1d5db; margin: 0 6px; }
    .subject-list { display: flex; flex-direction: column; gap: 8px; }
    .subject-row {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 18px; background: #f5f5f0; border-radius: 12px;
      cursor: pointer; transition: background .15s, box-shadow .15s;
      font-size: 14px;
    }
    .subject-row:hover { background: #ecece4; }
    .subject-row input { accent-color: #2563eb; width: 16px; height: 16px; cursor: pointer; }
    .subject-row .subj-name { flex: 1; color: #1f2937; }
    .subject-row .subj-code {
      font-size: 11px; padding: 4px 10px; background: #e5e7eb;
      color: #6b7280; border-radius: 999px; letter-spacing: .04em;
    }
    .subject-row.checked { background: #dbeafe; }
    .subject-row.checked .subj-name { color: #1d4ed8; font-weight: 600; }
    .hidden { display: none; }
  </style>
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-admin.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Register Student</h1>
      <div class="header-actions">
        <a href="admin-students.php" class="btn btn-secondary btn-sm">&larr; Back</a>
      </div>
    </header>
    <div class="content">
      <div style="max-width:600px;">

        <!-- Steps -->
        <div class="steps" style="margin-bottom:24px;">
          <div class="step-item active" id="stepItem1">
            <div class="step-num">1</div>
            <div class="step-label">Personal Info</div>
          </div>
          <div class="step-line"></div>
          <div class="step-item" id="stepItem2">
            <div class="step-num">2</div>
            <div class="step-label">Account Setup</div>
          </div>
          <div class="step-line"></div>
          <div class="step-item" id="stepItem3">
            <div class="step-num">3</div>
            <div class="step-label">Confirm</div>
          </div>
        </div>

        <div id="errMsg" class="alert alert-danger hidden"></div>

        <!-- Step 1 -->
        <div class="card step-panel active" id="panel1">
          <div class="card-header"><h3>Step 1 — Personal Information</h3></div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input class="form-control" id="name" placeholder="Student full name" required>
              </div>
              <div class="form-group">
                <label class="form-label">Student Number *</label>
                <input class="form-control" id="studentNumber" placeholder="e.g. 2024001234">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Programme</label>
                <input class="form-control" id="programme" placeholder="e.g. B.Sc Computer Science">
              </div>
              <div class="form-group">
                <label class="form-label">Year of Study</label>
                <select class="form-control" id="year">
                  <option>1</option><option>2</option><option>3</option><option>4</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" id="email" placeholder="student@university.edu.my">
              </div>
              <div class="form-group">
                <label class="form-label">Phone</label>
                <input class="form-control" id="phone" placeholder="01x-xxxxxxx">
              </div>
            </div>
            <?php if (empty($by_course)): ?>
              <div class="form-group">
                <label class="form-label">Enroll in Classes</label>
                <div class="text-muted text-small">No classes available yet. You can enroll the student later.</div>
              </div>
            <?php else: ?>
              <!-- Step A: course select -->
              <div class="enroll-block">
                <div class="enroll-head"><span class="enroll-num">1</span> SELECT COURSE</div>
                <select class="form-control" id="courseSelect" onchange="onCourseChange()">
                  <option value="">— Choose a course —</option>
                  <?php foreach ($by_course as $course => $list): ?>
                  <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="enroll-hint hidden" id="courseHint"><span class="dot"></span><span id="courseHintText"></span></div>
              </div>

              <!-- Step B: subject checklist -->
              <div class="enroll-block hidden" id="subjectsBlock">
                <div class="enroll-head">
                  <span class="enroll-num">2</span> SELECT SUBJECTS
                  <span class="enroll-count" id="subjectCount"></span>
                </div>
                <div class="enroll-actions">
                  <a href="#" onclick="selectAll(true);return false;">Select All</a>
                  <span class="dot-sep">·</span>
                  <a href="#" onclick="selectAll(false);return false;">Deselect All</a>
                </div>
                <div class="subject-list" id="subjectList"></div>
              </div>

              <!-- Hidden data store: all classes keyed by course -->
              <script id="catalogue" type="application/json"><?= json_encode($by_course) ?></script>
            <?php endif; ?>
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
              <label class="form-label">Username (auto-filled from Student No.)</label>
              <input class="form-control" id="username" readonly style="background:#f9fafb;">
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
              <input class="form-control" type="password" id="confPassword" placeholder="Repeat password">
            </div>
            <div class="flex justify-between mt-2">
              <button class="btn btn-secondary" onclick="goStep(1)">&larr; Back</button>
              <button class="btn btn-primary" onclick="goStep(3)">Next &rarr;</button>
            </div>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="card step-panel hidden" id="panel3">
          <div class="card-header"><h3>Step 3 — Confirm Details</h3></div>
          <div class="card-body">
            <table style="width:100%;font-size:13px;">
              <tr><td style="color:#6b7280;padding:6px 0;width:140px;">Name</td><td id="c_name" style="font-weight:500;"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Student No.</td><td id="c_sno"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Programme</td><td id="c_prog"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Year</td><td id="c_year"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Email</td><td id="c_email"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;">Phone</td><td id="c_phone"></td></tr>
              <tr><td style="color:#6b7280;padding:6px 0;vertical-align:top;">Classes</td><td id="c_classes"></td></tr>
            </table>
            <hr class="divider">
            <div class="alert alert-info">Username will be <b id="c_user"></b>. Password is hidden for security.</div>
            <div class="flex justify-between mt-2">
              <button class="btn btn-secondary" onclick="goStep(2)">&larr; Back</button>
              <button class="btn btn-primary" onclick="submitForm()" id="submitBtn">Register Student</button>
            </div>
          </div>
        </div>

        <!-- Done -->
        <div class="card hidden" id="donePannel" style="text-align:center;padding:32px;">
          <div style="font-size:40px;margin-bottom:12px;">&#10003;</div>
          <p style="font-size:16px;font-weight:600;margin-bottom:6px;">Student Registered!</p>
          <p class="text-muted text-small" style="margin-bottom:20px;" id="doneMsg"></p>
          <div class="flex gap-2" style="justify-content:center;">
            <a href="register-student.php" class="btn btn-primary">Register Another</a>
            <a href="admin-students.php"   class="btn btn-secondary">View Students</a>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
const catalogueEl = document.getElementById('catalogue');
const catalogue = catalogueEl ? JSON.parse(catalogueEl.textContent) : {};

function onCourseChange() {
  const course = document.getElementById('courseSelect').value;
  const block  = document.getElementById('subjectsBlock');
  const list   = document.getElementById('subjectList');
  const hint   = document.getElementById('courseHint');
  const hintT  = document.getElementById('courseHintText');
  const count  = document.getElementById('subjectCount');
  list.innerHTML = '';
  if (!course || !catalogue[course]) {
    block.classList.add('hidden');
    hint.classList.add('hidden');
    return;
  }
  const subs = catalogue[course];
  hint.classList.remove('hidden');
  hintT.textContent = subs.length + ' subject' + (subs.length === 1 ? '' : 's') + ' available';
  count.textContent = subs.length + ' subject' + (subs.length === 1 ? '' : 's');
  subs.forEach(s => {
    const row = document.createElement('label');
    row.className = 'subject-row';
    row.innerHTML =
      '<input type="checkbox" class="class-cb" value="' + s.id + '" data-name="' + s.class_name.replace(/"/g,'&quot;') + '">' +
      '<span class="subj-name">' + s.class_name + '</span>' +
      '<span class="subj-code">' + (s.class_code || '') + '</span>';
    const cb = row.querySelector('input');
    cb.addEventListener('change', () => row.classList.toggle('checked', cb.checked));
    list.appendChild(row);
  });
  block.classList.remove('hidden');
}

function selectAll(state) {
  document.querySelectorAll('#subjectList .class-cb').forEach(cb => {
    cb.checked = state;
    cb.closest('.subject-row').classList.toggle('checked', state);
  });
}

function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('open'); }
function closeSidebar()   { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }
function showToast(msg, type='info') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}
function checkStrength(pw) {
  let score = 0;
  if (pw.length >= 8) score++; if (/[A-Z]/.test(pw)) score++; if (/[0-9]/.test(pw)) score++; if (/[^A-Za-z0-9]/.test(pw)) score++;
  const fill = document.getElementById('pwFill');
  const colors = ['','#dc2626','#f59e0b','#2563eb','#16a34a'];
  const labels = ['','Weak','Fair','Good','Strong'];
  fill.style.width = (score*25)+'%'; fill.style.background = colors[score]||'#e9ecf0';
  document.getElementById('pwText').textContent = labels[score]||'';
}

function goStep(n) {
  const err = document.getElementById('errMsg');
  err.classList.add('hidden');

  if (n === 2) {
    if (!document.getElementById('name').value.trim())         { err.textContent='Name is required.';          err.classList.remove('hidden'); return; }
    if (!document.getElementById('studentNumber').value.trim()){ err.textContent='Student number is required.'; err.classList.remove('hidden'); return; }
    document.getElementById('username').value = document.getElementById('studentNumber').value.trim();
  }
  if (n === 3) {
    const pw = document.getElementById('password').value;
    const cp = document.getElementById('confPassword').value;
    if (pw.length < 6) { err.textContent='Password must be at least 6 characters.'; err.classList.remove('hidden'); return; }
    if (pw !== cp)     { err.textContent='Passwords do not match.';                  err.classList.remove('hidden'); return; }
    document.getElementById('c_name').textContent  = document.getElementById('name').value;
    document.getElementById('c_sno').textContent   = document.getElementById('studentNumber').value;
    document.getElementById('c_prog').textContent  = document.getElementById('programme').value;
    document.getElementById('c_year').textContent  = 'Year ' + document.getElementById('year').value;
    document.getElementById('c_email').textContent = document.getElementById('email').value;
    document.getElementById('c_phone').textContent = document.getElementById('phone').value;
    document.getElementById('c_user').textContent  = document.getElementById('username').value;
    const picked = Array.from(document.querySelectorAll('.class-cb:checked')).map(cb => cb.dataset.name);
    document.getElementById('c_classes').textContent = picked.length ? picked.join(', ') : '— None —';
  }

  [1,2,3].forEach(i => {
    document.getElementById('panel'+i).classList.toggle('active', i===n);
    document.getElementById('panel'+i).classList.toggle('hidden', i!==n);
    const si = document.getElementById('stepItem'+i);
    si.classList.toggle('active', i===n);
    si.classList.toggle('done',   i<n);
  });
}

async function submitForm() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = 'Registering...';

  const fd = new FormData();
  fd.append('name',           document.getElementById('name').value.trim());
  fd.append('student_number', document.getElementById('studentNumber').value.trim());
  fd.append('programme',      document.getElementById('programme').value.trim());
  fd.append('year',           document.getElementById('year').value);
  fd.append('email',          document.getElementById('email').value.trim());
  fd.append('phone',          document.getElementById('phone').value.trim());
  document.querySelectorAll('.class-cb:checked').forEach(cb => fd.append('class_ids[]', cb.value));
  fd.append('password',       document.getElementById('password').value);

  const res  = await fetch('/attendx/api/register_student.php', { method:'POST', body:fd });
  const data = await res.json();

  if (data.status === 'success') {
    document.getElementById('panel3').classList.add('hidden');
    document.getElementById('donePannel').classList.remove('hidden');
    document.getElementById('doneMsg').textContent = 'Student ' + document.getElementById('name').value + ' has been registered.';
  } else {
    const err = document.getElementById('errMsg');
    err.textContent = data.message;
    err.classList.remove('hidden');
    btn.disabled = false; btn.textContent = 'Register Student';
  }
}
</script>
</body>
</html>
