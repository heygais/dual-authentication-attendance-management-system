<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

$preselect    = isset($_GET['sid']) ? (int)$_GET['sid'] : 0;
$students_res = mysqli_query($conn, "SELECT s.id, s.student_number, s.finger_id, u.name FROM students s JOIN users u ON s.user_id=u.id ORDER BY u.name");
$students = [];
while ($st = mysqli_fetch_assoc($students_res)) $students[] = $st;

// Used slots
$used_slots_r = mysqli_query($conn, "SELECT finger_id FROM students WHERE finger_id IS NOT NULL");
$used_slots = [];
while ($r = mysqli_fetch_assoc($used_slots_r)) $used_slots[] = (int)$r['finger_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register Fingerprint — AttendX Admin</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-admin.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Register Fingerprint</h1>
      <div class="header-actions"><a href="admin-students.php" class="btn btn-secondary btn-sm">&larr; Back</a></div>
    </header>
    <div class="content">
      <div style="max-width:500px;">

        <div class="card" id="formCard">
          <div class="card-header"><h3>Enroll Fingerprint for Student</h3></div>
          <div class="card-body">
            <div id="errMsg" class="alert alert-danger hidden"></div>

            <div class="form-group">
              <label class="form-label">Select Student *</label>
              <select class="form-control" id="studentId" onchange="loadCurrent()">
                <option value="">— Select Student —</option>
                <?php foreach ($students as $st): ?>
                <option value="<?= $st['id'] ?>" data-fid="<?= $st['finger_id'] ?>" <?= $preselect===$st['id']?'selected':'' ?>>
                  <?= htmlspecialchars($st['name']) ?> (<?= $st['student_number'] ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div id="currentFid" style="font-size:12px;color:#6b7280;margin-bottom:10px;"></div>

            <div class="form-group">
              <label class="form-label">Fingerprint Slot (1–127) *</label>
              <input class="form-control" type="number" id="fingerId" min="1" max="127" placeholder="e.g. 5">
              <div class="form-hint">
                Each slot stores one fingerprint on the sensor module.
                <?php if (!empty($used_slots)): ?>
                Used slots: <?= implode(', ', $used_slots) ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Enrollment animation -->
            <div id="enrollSection" class="hidden" style="text-align:center;margin:20px 0;">
              <div class="fp-ring" id="fpRing">&#128376;</div>
              <p style="font-size:13px;font-weight:500;" id="fpStatus">Place finger on sensor...</p>
              <p class="text-muted text-small" id="fpSub">Scan 1 of 2</p>
            </div>

            <div class="flex justify-between mt-2">
              <span></span>
              <button class="btn btn-primary" onclick="startEnroll()" id="submitBtn">Start Enrollment</button>
            </div>
          </div>
        </div>

        <!-- Done -->
        <div class="card hidden" id="doneCard" style="text-align:center;padding:32px;">
          <div style="font-size:40px;margin-bottom:12px;">&#10003;</div>
          <p style="font-size:16px;font-weight:600;margin-bottom:6px;">Fingerprint Enrolled!</p>
          <p class="text-muted text-small" style="margin-bottom:6px;" id="doneStudentName"></p>
          <p style="font-size:13px;font-weight:600;margin-bottom:20px;" id="doneSlot"></p>
          <div class="flex gap-2" style="justify-content:center;">
            <button class="btn btn-primary" onclick="enrollAnother()">Enroll Another</button>
            <a href="admin-students.php" class="btn btn-secondary">View Students</a>
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
function showToast(msg, type='info') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

function loadCurrent() {
  const sel = document.getElementById('studentId');
  const opt = sel.options[sel.selectedIndex];
  const fid = opt ? opt.getAttribute('data-fid') : '';
  const div = document.getElementById('currentFid');
  div.textContent = fid && fid !== 'null' ? 'Current slot: ' + fid + ' (will be replaced)' : 'No fingerprint registered.';
}

async function startEnroll() {
  const sid   = document.getElementById('studentId').value;
  const fid   = parseInt(document.getElementById('fingerId').value);
  const err   = document.getElementById('errMsg');
  err.classList.add('hidden');

  if (!sid)              { err.textContent='Select a student.'; err.classList.remove('hidden'); return; }
  if (!fid||fid<1||fid>127) { err.textContent='Enter a valid slot (1-127).'; err.classList.remove('hidden'); return; }

  document.getElementById('submitBtn').disabled = true;
  document.getElementById('enrollSection').classList.remove('hidden');

  const ring   = document.getElementById('fpRing');
  const status = document.getElementById('fpStatus');
  const sub    = document.getElementById('fpSub');

  // Scan 1 simulation
  ring.classList.add('scanning');
  status.textContent = 'Place finger on sensor...';
  sub.textContent    = 'Scan 1 of 2';
  await delay(2000);
  ring.classList.remove('scanning');
  status.textContent = 'Scan 1 captured!';
  showToast('First scan captured.', 'success');
  await delay(800);

  // Scan 2
  ring.classList.add('scanning');
  status.textContent = 'Lift finger and place again...';
  sub.textContent    = 'Scan 2 of 2';
  await delay(2000);
  ring.classList.remove('scanning');
  ring.classList.add('done');
  status.textContent = 'Scan 2 captured!';
  showToast('Second scan captured.', 'success');
  await delay(600);

  // Save
  status.textContent = 'Saving to database...';
  const fd = new FormData();
  fd.append('student_id', sid);
  fd.append('finger_id', fid);
  const res  = await fetch('/attendx/api/register_fingerprint.php', { method:'POST', body:fd });
  const data = await res.json();

  if (data.status === 'success') {
    const sel  = document.getElementById('studentId');
    const name = sel.options[sel.selectedIndex].text;
    document.getElementById('doneStudentName').textContent = name;
    document.getElementById('doneSlot').textContent = 'Fingerprint Slot: ' + fid;
    document.getElementById('formCard').classList.add('hidden');
    document.getElementById('doneCard').classList.remove('hidden');
  } else {
    err.textContent = data.message;
    err.classList.remove('hidden');
    ring.classList.remove('scanning','done');
    document.getElementById('enrollSection').classList.add('hidden');
    document.getElementById('submitBtn').disabled = false;
  }
}

function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

function enrollAnother() {
  document.getElementById('formCard').classList.remove('hidden');
  document.getElementById('doneCard').classList.add('hidden');
  document.getElementById('enrollSection').classList.add('hidden');
  document.getElementById('studentId').value = '';
  document.getElementById('fingerId').value  = '';
  document.getElementById('currentFid').textContent = '';
  document.getElementById('fpRing').classList.remove('scanning','done');
  document.getElementById('submitBtn').disabled = false;
}

window.addEventListener('load', loadCurrent);
</script>
</body>
</html>
