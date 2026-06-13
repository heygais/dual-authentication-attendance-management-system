<?php
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

$role      = $_SESSION['role'];
$date      = isset($_GET['date'])     ? $_GET['date']     : date('Y-m-d');
$class_id  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$tab       = isset($_GET['tab'])      ? $_GET['tab']      : 'class';

$classes_res = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name");
$classes = [];
while ($c = mysqli_fetch_assoc($classes_res)) $classes[] = $c;

$students_res = mysqli_query($conn, "SELECT s.id, u.name, s.student_number FROM students s JOIN users u ON s.user_id=u.id ORDER BY u.name");
$students = [];
while ($st = mysqli_fetch_assoc($students_res)) $students[] = $st;

// Fetch records
$where = "DATE(a.timestamp) = '$date'";
if ($class_id) $where .= " AND a.class_id = $class_id";
if ($role === 'lecturer') {
    $lid = $_SESSION['user_id'];
    $where .= " AND c.lecturer_id = $lid";
}

$sql = "SELECT a.id, a.rfid_verified, a.finger_verified, a.method, a.is_present, a.timestamp,
               a.override_reason,
               u.name AS student_name, s.student_number, s.id AS student_id,
               c.class_name, c.id AS class_id
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN users    u ON s.user_id    = u.id
        JOIN classes  c ON a.class_id   = c.id
        WHERE $where
        ORDER BY a.timestamp DESC";
$res = mysqli_query($conn, $sql);
$records = [];
while ($r = mysqli_fetch_assoc($res)) $records[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Attendance — AttendX</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php if ($role==='admin'): include '../includes/sidebar-admin.php';
  elseif ($role==='lecturer'): include '../includes/sidebar-lecturer.php';
  else: include '../includes/sidebar-student.php'; endif; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Attendance Records</h1>
      <?php if ($role==='admin' || $role==='lecturer'): ?>
      <div class="header-actions">
        <button class="btn btn-secondary btn-sm" onclick="openModal('overrideModal')">Manual Override</button>
        <a href="?date=<?= $date ?>&class_id=<?= $class_id ?>&export=csv" class="btn btn-primary btn-sm">Export CSV</a>
      </div>
      <?php endif; ?>
    </header>
    <div class="content">

      <!-- Filters -->
      <form method="GET" class="filter-bar" style="margin-bottom:16px;">
        <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($date) ?>" style="max-width:160px;">
        <select class="form-control" name="class_id" style="max-width:200px;">
          <option value="">All Classes</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
      </form>

      <!-- Summary -->
      <?php
      $tot=0; $pre=0; $abs=0;
      foreach ($records as $r) {
          $tot++;
          if ($r['is_present']) $pre++;
          else $abs++;
      }
      ?>
      <div class="flex gap-3 mb-2" id="summaryBar" style="flex-wrap:wrap;margin-bottom:14px;align-items:center;">
        <span class="badge badge-gray"    id="sumTotal"><?= $tot ?> total</span>
        <span class="badge badge-success" id="sumPresent">&#10003; <?= $pre ?> present</span>
        <span class="badge badge-danger"  id="sumAbsent">&#10007; <?= $abs ?> absent</span>
        <span style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#6b7280;">
          <span id="liveDot" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#16a34a;box-shadow:0 0 0 0 rgba(22,163,74,.5);animation:livePulse 1.6s infinite;"></span>
          <span id="liveText">Live · updates every 4s</span>
        </span>
      </div>
      <style>
        @keyframes livePulse {
          0% { box-shadow: 0 0 0 0 rgba(22,163,74,.5); }
          70%{ box-shadow: 0 0 0 8px rgba(22,163,74,0); }
          100%{ box-shadow: 0 0 0 0 rgba(22,163,74,0); }
        }
        tr.row-new td { animation: rowFlash 1.5s ease-out; }
        @keyframes rowFlash {
          0%   { background: #d1fae5; }
          100% { background: transparent; }
        }
      </style>

      <!-- Records table -->
      <div class="card">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Class</th>
                <th>RFID</th>
                <th>Status</th>
                <th>Method</th>
                <th>Time</th>
                <?php if ($role==='admin' || $role==='lecturer'): ?><th>Action</th><?php endif; ?>
              </tr>
            </thead>
            <tbody id="recordsBody">
              <?php if (empty($records)): ?>
              <tr><td colspan="7" class="table-empty">No records for this date/class.</td></tr>
              <?php else: ?>
              <?php foreach ($records as $r): ?>
              <?php
              $rfid_badge  = $r['rfid_verified']   ? '<span class="badge badge-success">Card &#10003;</span>'   : '<span class="badge badge-danger">Card &#10007;</span>';
              $status_badge = $r['is_present']
                ? '<span class="badge badge-success">&#10003; Present</span>'
                : '<span class="badge badge-danger">&#10007; Absent</span>';
              ?>
              <tr>
                <td>
                  <div style="font-weight:500;"><?= htmlspecialchars($r['student_name']) ?></div>
                  <div style="font-size:11px;color:#9ca3af;"><?= $r['student_number'] ?></div>
                </td>
                <td><?= htmlspecialchars($r['class_name']) ?></td>
                <td><?= $rfid_badge ?></td>
                <td><?= $status_badge ?> <?= $r['override_reason'] ? '<span class="badge badge-info">Overridden</span>' : '' ?></td>
                <td><span class="badge badge-gray"><?= $r['method'] ?></span></td>
                <td style="font-size:12px;"><?= $r['timestamp'] ? substr($r['timestamp'],11,5) : '' ?></td>
                <?php if ($role==='admin' || $role==='lecturer'): ?>
                <td><button class="btn btn-ghost btn-xs" onclick="setOverride(<?= $r['id'] ?>, <?= $r['student_id'] ?>, <?= $r['class_id'] ?>)">Override</button></td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Override Modal -->
<?php if ($role==='admin' || $role==='lecturer'): ?>
<div class="modal-overlay hidden" id="overrideModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3>Manual Attendance Override</h3>
      <button class="modal-close" onclick="closeModal('overrideModal')">&#10005;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ovAttId">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Student</label>
          <select class="form-control" id="ovStudentId" onchange="loadOvClasses(this.value)">
            <option value="">— Select —</option>
            <?php foreach ($students as $st): ?>
            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= $st['student_number'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Class <span id="ovClassHint" style="font-weight:400;color:#6b7280;font-size:11px;"></span></label>
          <select class="form-control" id="ovClassId">
            <option value="">— Select student first —</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">RFID Status</label>
          <select class="form-control" id="ovRfid">
            <option value="1">Verified</option>
            <option value="0">Not Verified</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Mark As</label>
          <select class="form-control" id="ovPresent">
            <option value="1">Present</option>
            <option value="0">Absent</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date</label>
          <input class="form-control" type="date" id="ovDate" value="<?= $date ?>">
        </div>
        <div class="form-group"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Reason / Notes</label>
        <textarea class="form-control" id="ovReason" rows="2" placeholder="Reason for override..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('overrideModal')">Cancel</button>
      <button class="btn btn-warn" onclick="saveOverride()">Save Override</button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="toast-container" id="toastContainer"></div>

<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('open'); }
function closeSidebar()   { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }
function openModal(id)    { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id)   { document.getElementById(id).classList.add('hidden'); }
function showToast(msg, type='info') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}
function setOverride(attId, stuId, clsId) {
  document.getElementById('ovAttId').value    = attId;
  document.getElementById('ovStudentId').value = stuId;
  openModal('overrideModal');
  loadOvClasses(stuId, clsId);   // populate then pre-select the row's class
}

async function loadOvClasses(studentId, preselectId) {
  const sel  = document.getElementById('ovClassId');
  const hint = document.getElementById('ovClassHint');
  if (!studentId) {
    sel.innerHTML = '<option value="">— Select student first —</option>';
    hint.textContent = '';
    return;
  }
  sel.innerHTML = '<option value="">Loading...</option>';
  try {
    const res  = await fetch('/attendx/api/student_classes.php?student_id=' + encodeURIComponent(studentId), { cache: 'no-store' });
    const data = await res.json();
    if (!data.classes || data.classes.length === 0) {
      sel.innerHTML = '<option value="">No classes available</option>';
      hint.textContent = '';
      return;
    }
    sel.innerHTML = '<option value="">— Select —</option>' +
      data.classes.map(c => `<option value="${c.id}">${c.class_code ? c.class_code + ' — ' : ''}${c.class_name}</option>`).join('');
    if (preselectId) sel.value = preselectId;
    hint.textContent = data.fallback
      ? '(student not enrolled — showing all classes)'
      : `(${data.classes.length} enrolled)`;
  } catch (e) {
    sel.innerHTML = '<option value="">Failed to load classes</option>';
    hint.textContent = '';
  }
}
async function saveOverride() {
  const fd = new FormData();
  fd.append('action',     'override');
  fd.append('att_id',     document.getElementById('ovAttId').value);
  fd.append('student_id', document.getElementById('ovStudentId').value);
  fd.append('class_id',   document.getElementById('ovClassId').value);
  fd.append('rfid',       document.getElementById('ovRfid').value);
  fd.append('is_present', document.getElementById('ovPresent').value);
  fd.append('date',       document.getElementById('ovDate').value);
  fd.append('reason',     document.getElementById('ovReason').value.trim());

  const res  = await fetch('/attendx/api/attendance.php?override=1', { method:'POST', body:fd });
  const data = await res.json().catch(() => ({status:'error',message:'Server error'}));
  showToast(data.message || 'Saved.', data.status==='success'?'success':'error');
  if (data.status==='success') {
    closeModal('overrideModal');
    fetchAttendance(); // refresh live, no full reload
  }
}

// ─── LIVE POLLING ──────────────────────────────────────────────
const FILTER_DATE  = <?= json_encode($date) ?>;
const FILTER_CLASS = <?= (int)$class_id ?>;
const ROLE         = <?= json_encode($role) ?>;
const POLL_MS      = 4000;
let knownIds = new Set(<?= json_encode(array_map(fn($r)=>(int)$r['id'], $records)) ?>);
let pollTimer = null;

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function rowHtml(r, isNew) {
  const rfid = Number(r.rfid_verified) === 1
    ? '<span class="badge badge-success">Card &#10003;</span>'
    : '<span class="badge badge-danger">Card &#10007;</span>';
  const status = Number(r.is_present) === 1
    ? '<span class="badge badge-success">&#10003; Present</span>'
    : '<span class="badge badge-danger">&#10007; Absent</span>';
  const ovr  = r.override_reason ? ' <span class="badge badge-info">Overridden</span>' : '';
  const time = r.timestamp ? r.timestamp.substring(11,16) : '';
  const showAction = (ROLE === 'admin' || ROLE === 'lecturer');
  const actionTd = showAction
    ? `<td><button class="btn btn-ghost btn-xs" onclick="setOverride(${r.att_id}, ${r.student_id}, ${r.class_id})">Override</button></td>`
    : '';
  return `<tr class="${isNew ? 'row-new' : ''}" data-id="${r.att_id}">
    <td>
      <div style="font-weight:500;">${esc(r.student_name)}</div>
      <div style="font-size:11px;color:#9ca3af;">${esc(r.student_number)}</div>
    </td>
    <td>${esc(r.class_name)}</td>
    <td>${rfid}</td>
    <td>${status}${ovr}</td>
    <td><span class="badge badge-gray">${esc(r.method)}</span></td>
    <td style="font-size:12px;">${time}</td>
    ${actionTd}
  </tr>`;
}

async function fetchAttendance() {
  // Pull current filter values (so live updates respect the form)
  const form = document.querySelector('.filter-bar');
  const date  = form?.elements['date']?.value     || FILTER_DATE;
  const cls   = form?.elements['class_id']?.value || FILTER_CLASS;

  try {
    const res  = await fetch(`/attendx/api/get_attendance.php?date=${encodeURIComponent(date)}&class=${encodeURIComponent(cls)}`, { cache: 'no-store' });
    const data = await res.json();
    if (data.status !== 'success') return;

    const tbody = document.getElementById('recordsBody');
    if (!data.records.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="table-empty">No records for this date/class.</td></tr>';
    } else {
      tbody.innerHTML = data.records
        .map(r => rowHtml(r, !knownIds.has(parseInt(r.att_id))))
        .join('');
    }
    // Track ids so we can flash genuinely new rows
    knownIds = new Set(data.records.map(r => parseInt(r.att_id)));

    // Update summary
    const sum = data.summary || {};
    document.getElementById('sumTotal').textContent      = `${sum.total || 0} total`;
    document.getElementById('sumPresent').innerHTML      = `&#10003; ${sum.present || 0} present`;
    document.getElementById('sumAbsent').innerHTML       = `&#10007; ${sum.absent || 0} absent`;

    document.getElementById('liveText').textContent = 'Live · last update ' + new Date().toLocaleTimeString();
  } catch (err) {
    document.getElementById('liveText').textContent = 'Offline — retrying...';
  }
}

function startPolling() {
  if (pollTimer) return;
  pollTimer = setInterval(fetchAttendance, POLL_MS);
}
function stopPolling() {
  clearInterval(pollTimer);
  pollTimer = null;
}

// Pause polling while override modal is open so user can fill it in
document.addEventListener('click', e => {
  const modal = document.getElementById('overrideModal');
  if (!modal) return;
  const opening = e.target.closest('[onclick*="overrideModal"]');
  if (opening) stopPolling();
});
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); stopPolling(); }
function closeModal(id) { document.getElementById(id).classList.add('hidden');   startPolling(); }

// Also pause when tab is hidden, resume when visible
document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopPolling(); else { fetchAttendance(); startPolling(); }
});

startPolling();
</script>
</body>
</html>
