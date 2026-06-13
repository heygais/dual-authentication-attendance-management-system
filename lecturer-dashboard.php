<?php
require_once '../includes/auth.php';
requireRole('lecturer');
require_once '../includes/db.php';

$uid      = $_SESSION['user_id'];
$date     = isset($_GET['date'])     ? $_GET['date']     : date('Y-m-d');
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

function scalar($conn, $sql) {
    $r = mysqli_query($conn, $sql);
    if (!$r) return 0;
    $row = mysqli_fetch_row($r);
    return $row ? (int)$row[0] : 0;
}

// Classes for this lecturer
$classes_res = mysqli_query($conn, "SELECT id, class_name FROM classes WHERE lecturer_id=$uid ORDER BY class_name");
$classes = [];
if ($classes_res) while ($c = mysqli_fetch_assoc($classes_res)) $classes[] = $c;

// Stats
$total_students = scalar($conn, "SELECT COUNT(DISTINCT sc.student_id) FROM student_classes sc JOIN classes c ON sc.class_id=c.id WHERE c.lecturer_id=$uid");
$present_today  = scalar($conn, "SELECT COUNT(*) FROM attendance a JOIN classes c ON a.class_id=c.id WHERE c.lecturer_id=$uid AND DATE(a.timestamp)='$date' AND a.is_present=1");
$total_classes  = count($classes);

// Daily bar chart Mon-Fri — filter by the lecturer who RAN the session
// (sessions.lecturer_id), so re-claimed classes don't pull in other
// lecturers' historical attendance.
$week_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $day = date('D', strtotime("-$i days"));
    $tot = scalar($conn, "SELECT COUNT(*) FROM attendance a JOIN sessions se ON a.session_id=se.id WHERE se.lecturer_id=$uid AND DATE(a.timestamp)='$d'");
    $pre = scalar($conn, "SELECT COUNT(*) FROM attendance a JOIN sessions se ON a.session_id=se.id WHERE se.lecturer_id=$uid AND DATE(a.timestamp)='$d' AND a.is_present=1");
    $pct = $tot ? round($pre/$tot*100) : 0;
    $week_data[] = ['day' => $day, 'pct' => $pct];
}

// Currently active session (queried below as well; pulled once early)
$active_sid = 0;
$peek = mysqli_query($conn, "SELECT id FROM sessions WHERE status='active' ORDER BY start_time DESC LIMIT 1");
if ($peek && $row = mysqli_fetch_assoc($peek)) $active_sid = (int)$row['id'];

// Attendance: if a session is active, show ITS scans regardless of class
// ownership. Otherwise fall back to the lecturer's own classes for the day.
if ($active_sid) {
    $where = "a.session_id = $active_sid";
} else {
    $where = "DATE(a.timestamp)='$date' AND c.lecturer_id=$uid";
}
if ($class_id) $where .= " AND a.class_id=$class_id";
$sql = "SELECT a.id, a.rfid_verified, a.finger_verified, a.is_present, a.method, a.timestamp, a.override_reason,
               u.name AS student_name, s.student_number, s.id AS student_id, c.class_name, c.id AS class_id
        FROM attendance a
        JOIN students s ON a.student_id=s.id
        JOIN users    u ON s.user_id=u.id
        JOIN classes  c ON a.class_id=c.id
        WHERE $where ORDER BY a.timestamp DESC";
$res = mysqli_query($conn, $sql);
$records = [];
while ($r = mysqli_fetch_assoc($res)) $records[] = $r;

$students_res = mysqli_query($conn, "SELECT s.id, u.name, s.student_number FROM students s JOIN users u ON s.user_id=u.id ORDER BY u.name");
$students = [];
while ($st = mysqli_fetch_assoc($students_res)) $students[] = $st;

// All classes — for the session-start dropdown (every lecturer can choose any)
$all_classes_res = mysqli_query($conn, "SELECT id, class_code, class_name FROM classes ORDER BY class_name");
$all_classes = [];
if ($all_classes_res) while ($c = mysqli_fetch_assoc($all_classes_res)) $all_classes[] = $c;

// Currently active session (global)
$active_session = null;
$as_r = mysqli_query($conn, "SELECT s.id, s.class_id, s.start_time, c.class_name, c.class_code, u.name AS lecturer_name
                             FROM sessions s
                             JOIN classes c ON s.class_id=c.id
                             LEFT JOIN users u ON s.lecturer_id=u.id
                             WHERE s.status='active'
                             ORDER BY s.start_time DESC LIMIT 1");
if ($as_r) $active_session = mysqli_fetch_assoc($as_r);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — AttendX Lecturer</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-lecturer.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Lecturer Dashboard</h1>
      <div class="header-actions">
        <span style="font-size:12px;color:#6b7280;">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
      </div>
    </header>
    <div class="content">

      <!-- Session Control Panel -->
      <div class="card" id="sessionPanel" style="margin-bottom:20px;border-left:4px solid <?= $active_session ? '#16a34a' : '#9ca3af' ?>;">
        <div class="card-body" style="padding:18px 22px;">
          <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
              <div style="font-size:11px;letter-spacing:.08em;font-weight:700;color:#6b7280;text-transform:uppercase;">Attendance Session</div>
              <?php if ($active_session): ?>
                <div style="font-size:18px;font-weight:700;color:#16a34a;margin-top:4px;">
                  <span class="session-dot"></span> <?= htmlspecialchars($active_session['class_name']) ?>
                </div>
                <div style="font-size:12px;color:#6b7280;margin-top:2px;">
                  Started <?= date('h:i A', strtotime($active_session['start_time'])) ?>
                  <?php if (!empty($active_session['lecturer_name'])): ?>
                    · by <?= htmlspecialchars($active_session['lecturer_name']) ?>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div style="font-size:18px;font-weight:700;color:#6b7280;margin-top:4px;">No active session</div>
                <div style="font-size:12px;color:#9ca3af;margin-top:2px;">ESP32 scans will be rejected until you start one.</div>
              <?php endif; ?>
            </div>

            <select class="form-control" id="sessionClassId" style="max-width:260px;" <?= $active_session ? 'disabled' : '' ?>>
              <option value="">— Select subject —</option>
              <?php foreach ($all_classes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_code'] . ' — ' . $c['class_name']) ?></option>
              <?php endforeach; ?>
            </select>

            <?php if ($active_session): ?>
              <button class="btn btn-danger" onclick="stopSession()">&#9632; Stop Attendance</button>
            <?php else: ?>
              <button class="btn btn-primary" onclick="startSession()">&#9654; Start Attendance</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <style>
        .session-dot {
          display: inline-block; width: 10px; height: 10px; border-radius: 50%;
          background: #16a34a; box-shadow: 0 0 0 0 rgba(22,163,74,.5);
          animation: pulse 1.6s infinite;
          margin-right: 6px; vertical-align: middle;
        }
        @keyframes pulse {
          0%   { box-shadow: 0 0 0 0 rgba(22,163,74,.5); }
          70%  { box-shadow: 0 0 0 10px rgba(22,163,74,0); }
          100% { box-shadow: 0 0 0 0 rgba(22,163,74,0); }
        }
      </style>

      <div class="stat-grid">
        <div class="stat-card blue">
          <div class="stat-label">My Students</div>
          <div class="stat-value"><?= $total_students ?></div>
          <div class="stat-change">Enrolled across classes</div>
        </div>
        <div class="stat-card green">
          <div class="stat-label">Present Today</div>
          <div class="stat-value"><?= $present_today ?></div>
          <div class="stat-change up"><?= date('d M') ?></div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-label">My Classes</div>
          <div class="stat-value"><?= $total_classes ?></div>
          <div class="stat-change">Active this semester</div>
        </div>
        <div class="stat-card red">
          <div class="stat-label">Session Date</div>
          <div class="stat-value" style="font-size:18px;"><?= date('d M Y') ?></div>
          <div class="stat-change"><?= date('l') ?></div>
        </div>
      </div>

      <div class="grid-2" style="margin-bottom:20px;">
        <!-- Weekly chart -->
        <div class="card">
          <div class="card-header"><h3>This Week — My Classes</h3></div>
          <div class="card-body">
            <div class="bar-chart" style="padding-bottom:36px;">
              <?php foreach ($week_data as $d): ?>
              <?php $cl = $d['pct'] >= 80 ? 'hi' : ($d['pct'] >= 60 ? 'mid' : 'low'); ?>
              <div class="bar-group">
                <div class="bar <?= $cl ?>" style="height:<?= max(4,$d['pct']) ?>%;"></div>
                <div class="bar-label"><?= $d['day'] ?></div>
                <div class="bar-val"><?= $d['pct'] ?>%</div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Absent Students (live roster) -->
        <div class="card">
          <div class="card-header"><h3>Absent Students — <span id="absentDate"><?= $date ?></span></h3></div>
          <div class="card-body" style="padding:12px 18px;">
            <div id="absentMeta" style="font-size:12px;color:#6b7280;margin-bottom:10px;"></div>
            <div id="absentBody">
              <p class="text-muted text-small">Start a session or pick a class below to see the live roster.</p>
            </div>
            <button id="notifyBtn" class="btn btn-warn btn-sm mt-2" onclick="sendNotifications()" style="display:none;">Send Notifications</button>
          </div>
        </div>
      </div>

      <!-- Class attendance records -->
      <div class="card">
        <div class="card-header">
          <h3>Class Records</h3>
          <button class="btn btn-secondary btn-sm" onclick="openModal('overrideModal')">Manual Override</button>
        </div>
        <div class="card-body" style="padding:12px 18px;">
          <form method="GET" class="filter-bar" style="margin-bottom:12px;">
            <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($date) ?>" style="max-width:155px;">
            <select class="form-control" name="class_id" style="max-width:200px;">
              <option value="">All My Classes</option>
              <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
          </form>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Student</th><th>Class</th><th>RFID</th><th>Status</th><th>Time</th><th></th></tr>
            </thead>
            <tbody id="recordsBody">
              <?php if (empty($records)): ?>
              <tr><td colspan="6" class="table-empty">No records for selected date/class.</td></tr>
              <?php else: foreach ($records as $r): ?>
              <?php
              $rfid = $r['rfid_verified']   ? '<span class="badge badge-success">Card &#10003;</span>'   : '<span class="badge badge-danger">Card &#10007;</span>';
              $stat = $r['is_present']
                ? '<span class="badge badge-success">Present</span>'
                : '<span class="badge badge-danger">Absent</span>';
              ?>
              <tr>
                <td><div style="font-weight:500;"><?= htmlspecialchars($r['student_name']) ?></div><div style="font-size:11px;color:#9ca3af;"><?= $r['student_number'] ?></div></td>
                <td><?= htmlspecialchars($r['class_name']) ?></td>
                <td><?= $rfid ?></td>
                <td><?= $stat ?></td>
                <td style="font-size:12px;"><?= $r['timestamp'] ? substr($r['timestamp'],11,5) : '' ?></td>
                <td><button class="btn btn-ghost btn-xs" onclick="setOverride(<?= $r['id'] ?>,<?= $r['student_id'] ?>,<?= $r['class_id'] ?>)">Override</button></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Override Modal -->
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
          <label class="form-label">RFID</label>
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
        <label class="form-label">Reason</label>
        <textarea class="form-control" id="ovReason" rows="2" placeholder="Reason for override..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('overrideModal')">Cancel</button>
      <button class="btn btn-warn" onclick="saveOverride()">Save Override</button>
    </div>
  </div>
</div>

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
  document.getElementById('ovAttId').value     = attId;
  document.getElementById('ovStudentId').value = stuId;
  openModal('overrideModal');
  loadOvClasses(stuId, clsId);
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
  const data = await res.json().catch(() => ({status:'error',message:'Error'}));
  showToast(data.message || 'Saved.', data.status==='success'?'success':'error');
  if (data.status==='success') { closeModal('overrideModal'); setTimeout(() => location.reload(), 900); }
}
function sendNotifications() {
  showToast('Notifications sent to absent students. (Demo)', 'success');
}

// ── Live polling ────────────────────────────────────────────
// Replaces the old full-page reload. Every few seconds we refresh the
// Absent roster + Class Records together from JSON endpoints, so an RFID
// scan moves a student out of "Absent" and into "Class Records" without
// a page flash. We still reload once when the active session starts/stops
// (so the top panel, stat cards and Start/Stop button re-sync).
const BASE              = '/attendx';
const ACTIVE_SESSION_ID = <?= (int)$active_sid ?>;
const ACTIVE_CLASS_ID   = <?= $active_session ? (int)$active_session['class_id'] : 0 ?>;
const FILTER_CLASS_ID   = <?= (int)$class_id ?>;
const ROSTER_DATE       = '<?= htmlspecialchars($date, ENT_QUOTES) ?>';

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
// Roster is scoped to the active session's class; if no session, to the
// class chosen in the Class Records filter. 0 = nothing to show live.
function targetClassId() {
  return ACTIVE_SESSION_ID ? ACTIVE_CLASS_ID : FILTER_CLASS_ID;
}
function modalOpen() { return !!document.querySelector('.modal-overlay:not(.hidden)'); }

function renderAbsent(data) {
  const meta   = document.getElementById('absentMeta');
  const body   = document.getElementById('absentBody');
  const notify = document.getElementById('notifyBtn');
  const c       = data.counts || { present: 0, absent: 0, total: 0 };
  const absent  = data.absent || [];

  if (c.total === 0) {
    meta.textContent  = 'No students enrolled in this class.';
    body.innerHTML    = '';
    notify.style.display = 'none';
    return;
  }
  meta.textContent = `${c.present} / ${c.total} present · ${c.absent} absent`;

  if (absent.length === 0) {
    body.innerHTML = '<p class="text-small" style="color:#15803d;font-weight:600;margin:4px 0;">&#10003; All students present</p>';
    notify.style.display = 'none';
    return;
  }
  body.innerHTML = absent.map(a => `
    <div class="feed-item">
      <span class="feed-dot absent"></span>
      <div class="feed-info">
        <div class="feed-name">${esc(a.name)}</div>
        <div class="feed-class">${esc(a.student_number)}</div>
      </div>
    </div>`).join('');
  notify.style.display = '';
}

function renderRecords(data) {
  const body = document.getElementById('recordsBody');
  const recs = data.records || [];
  if (recs.length === 0) {
    body.innerHTML = '<tr><td colspan="6" class="table-empty">No records for selected date/class.</td></tr>';
    return;
  }
  body.innerHTML = recs.map(r => {
    const rfid = (+r.rfid_verified)
      ? '<span class="badge badge-success">Card &#10003;</span>'
      : '<span class="badge badge-danger">Card &#10007;</span>';
    const stat = (+r.is_present)
      ? '<span class="badge badge-success">Present</span>'
      : '<span class="badge badge-danger">Absent</span>';
    const time = r.timestamp ? esc(String(r.timestamp).substr(11,5)) : '';
    return `<tr>
      <td><div style="font-weight:500;">${esc(r.student_name)}</div>
          <div style="font-size:11px;color:#9ca3af;">${esc(r.student_number)}</div></td>
      <td>${esc(r.class_name)}</td>
      <td>${rfid}</td>
      <td>${stat}</td>
      <td style="font-size:12px;">${time}</td>
      <td><button class="btn btn-ghost btn-xs"
            onclick="setOverride(${+r.att_id},${+r.student_id},${+r.class_id})">Override</button></td>
    </tr>`;
  }).join('');
}

async function poll() {
  if (modalOpen()) return;   // don't yank rows / reload mid-override

  // 1) Detect session start/stop → resync the whole page once.
  try {
    const sres = await fetch(`${BASE}/api/active_session.php`, { cache: 'no-store' });
    const sdata = await sres.json();
    const liveId = sdata.status === 'active' && sdata.session ? +sdata.session.id : 0;
    if (liveId !== ACTIVE_SESSION_ID) { location.reload(); return; }
  } catch (e) { /* network blip — try again next tick */ }

  // 2) Refresh roster + records for the target class.
  const cid = targetClassId();
  if (!cid) return;          // no session and no class picked — nothing live
  try {
    const [rosterRes, recRes] = await Promise.all([
      fetch(`${BASE}/api/class_roster.php?class_id=${cid}&date=${encodeURIComponent(ROSTER_DATE)}`, { cache: 'no-store' }),
      fetch(`${BASE}/api/get_attendance.php?class=${cid}&date=${encodeURIComponent(ROSTER_DATE)}`,   { cache: 'no-store' }),
    ]);
    renderAbsent(await rosterRes.json());
    renderRecords(await recRes.json());
  } catch (e) { /* leave last good render in place */ }
}

// Poll right away, then every 4s.
poll();
setInterval(poll, 4000);

async function startSession() {
  const sel = document.getElementById('sessionClassId');
  const classId = sel.value;
  if (!classId) { showToast('Please select a subject first.', 'error'); return; }
  const fd = new FormData(); fd.append('class_id', classId);
  const res  = await fetch('/attendx/api/start_session.php', { method:'POST', body:fd });
  const data = await res.json().catch(() => ({status:'error',message:'Error'}));
  showToast(data.message || '...', data.status === 'success' ? 'success' : 'error');
  if (data.status === 'success') setTimeout(() => location.reload(), 700);
}
async function stopSession() {
  if (!confirm('End the current attendance session?')) return;
  const res  = await fetch('/attendx/api/stop_session.php', { method:'POST', body:new FormData() });
  const data = await res.json().catch(() => ({status:'error',message:'Error'}));
  showToast(data.message || '...', data.status === 'success' ? 'success' : 'error');
  if (data.status === 'success') setTimeout(() => location.reload(), 700);
}
</script>
</body>
</html>
