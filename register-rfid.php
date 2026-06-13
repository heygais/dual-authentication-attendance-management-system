<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

$preselect = isset($_GET['sid']) ? (int)$_GET['sid'] : 0;
$students_res = mysqli_query($conn, "SELECT s.id, s.student_number, s.rfid_uid, u.name FROM students s JOIN users u ON s.user_id=u.id ORDER BY u.name");
$students = [];
while ($st = mysqli_fetch_assoc($students_res)) $students[] = $st;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register RFID — AttendX Admin</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-admin.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Register RFID Card</h1>
      <div class="header-actions"><a href="admin-students.php" class="btn btn-secondary btn-sm">&larr; Back</a></div>
    </header>
    <div class="content">
      <div style="max-width:500px;">

        <div class="card" id="formCard">
          <div class="card-header"><h3>Assign RFID Card to Student</h3></div>
          <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:16px;">
              Scan the RFID card using the reader, or manually enter the card UID below.
            </div>
            <div id="errMsg" class="alert alert-danger hidden"></div>

            <div class="form-group">
              <label class="form-label">Select Student *</label>
              <select class="form-control" id="studentId" onchange="loadCurrent()">
                <option value="">— Select Student —</option>
                <?php foreach ($students as $st): ?>
                <option value="<?= $st['id'] ?>" data-rfid="<?= htmlspecialchars($st['rfid_uid']) ?>" <?= $preselect===$st['id']?'selected':'' ?>>
                  <?= htmlspecialchars($st['name']) ?> (<?= $st['student_number'] ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div id="currentRfid" style="font-size:12px;color:#6b7280;margin-bottom:10px;"></div>

            <div class="form-group">
              <label class="form-label">RFID Card UID *</label>
              <div class="input-group">
                <input class="form-control" id="rfidUid" placeholder="e.g. A3B2C1D0" style="font-family:monospace;letter-spacing:1px;">
                <button class="btn btn-secondary" onclick="clearInput()">Clear</button>
              </div>
              <div class="form-hint">Tap card on reader — UID appears automatically. Or type manually.</div>
            </div>

            <div class="flex justify-between mt-2">
              <button class="btn btn-secondary" onclick="simulateScan()">Simulate Scan</button>
              <button class="btn btn-primary" onclick="registerRfid()" id="submitBtn">Register Card</button>
            </div>
          </div>
        </div>

        <!-- Confirmation -->
        <div class="card hidden" id="doneCard" style="text-align:center;padding:32px;">
          <div style="font-size:40px;margin-bottom:12px;">&#128243;</div>
          <p style="font-size:16px;font-weight:600;margin-bottom:6px;">RFID Card Registered!</p>
          <p class="text-muted text-small" style="margin-bottom:6px;" id="doneStudentName"></p>
          <p style="font-size:13px;font-weight:600;font-family:monospace;margin-bottom:20px;" id="doneUid"></p>
          <div class="flex gap-2" style="justify-content:center;">
            <button class="btn btn-primary" onclick="registerAnother()">Register Another</button>
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
  const sel  = document.getElementById('studentId');
  const opt  = sel.options[sel.selectedIndex];
  const rfid = opt ? opt.getAttribute('data-rfid') : '';
  const div  = document.getElementById('currentRfid');
  if (rfid && rfid !== 'null') {
    div.innerHTML = 'Current RFID: <b>' + rfid + '</b> (will be replaced)';
  } else {
    div.textContent = rfid ? '' : 'No RFID registered yet.';
  }
}

function clearInput() { document.getElementById('rfidUid').value = ''; }

function simulateScan() {
  const uid = Math.random().toString(16).substr(2,8).toUpperCase();
  document.getElementById('rfidUid').value = uid;
  showToast('Card scanned: ' + uid, 'info');
}

async function registerRfid() {
  const sid = document.getElementById('studentId').value;
  const uid = document.getElementById('rfidUid').value.trim();
  const err = document.getElementById('errMsg');
  err.classList.add('hidden');

  if (!sid) { err.textContent = 'Please select a student.'; err.classList.remove('hidden'); return; }
  if (!uid) { err.textContent = 'Please enter or scan the RFID UID.'; err.classList.remove('hidden'); return; }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = 'Saving...';

  const fd = new FormData();
  fd.append('student_id', sid);
  fd.append('rfid_uid', uid);

  const res  = await fetch('/attendx/api/register_rfid.php', { method:'POST', body:fd });
  const data = await res.json();

  if (data.status === 'success') {
    const sel  = document.getElementById('studentId');
    const name = sel.options[sel.selectedIndex].text;
    document.getElementById('doneStudentName').textContent = name;
    document.getElementById('doneUid').textContent = 'UID: ' + uid;
    document.getElementById('formCard').classList.add('hidden');
    document.getElementById('doneCard').classList.remove('hidden');
  } else {
    err.textContent = data.message;
    err.classList.remove('hidden');
    btn.disabled = false; btn.textContent = 'Register Card';
  }
}

function registerAnother() {
  document.getElementById('formCard').classList.remove('hidden');
  document.getElementById('doneCard').classList.add('hidden');
  document.getElementById('studentId').value = '';
  document.getElementById('rfidUid').value = '';
  document.getElementById('currentRfid').textContent = '';
  document.getElementById('submitBtn').disabled = false;
  document.getElementById('submitBtn').textContent = 'Register Card';
}

window.addEventListener('load', loadCurrent);
</script>
</body>
</html>
