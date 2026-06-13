<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

// Stats — defensive (returns 0 if query fails or table missing)
function scalar($conn, $sql) {
    $r = mysqli_query($conn, $sql);
    if (!$r) return 0;
    $row = mysqli_fetch_row($r);
    return $row ? (int)$row[0] : 0;
}
$total_students  = scalar($conn, "SELECT COUNT(*) FROM students");
$total_lecturers = scalar($conn, "SELECT COUNT(*) FROM lecturers");
$total_classes   = scalar($conn, "SELECT COUNT(*) FROM classes");
$today           = date('Y-m-d');
$present_today   = scalar($conn, "SELECT COUNT(*) FROM attendance WHERE DATE(timestamp)='$today' AND is_present=1");
$at_risk         = scalar($conn, "SELECT COUNT(*) FROM (SELECT s.id FROM students s JOIN attendance a ON a.student_id=s.id GROUP BY s.id HAVING (SUM(a.is_present)/COUNT(a.id))*100 < 80) t");

// Weekly bar chart data
$week_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $day = date('D', strtotime("-$i days"));
    $tot = scalar($conn, "SELECT COUNT(*) FROM attendance WHERE DATE(timestamp)='$d'");
    $pre = scalar($conn, "SELECT COUNT(*) FROM attendance WHERE DATE(timestamp)='$d' AND is_present=1");
    $pct = $tot ? round($pre/$tot*100) : 0;
    $week_data[] = ['day' => $day, 'pct' => $pct];
}

// Today — by class (ALL classes)
$class_res = mysqli_query($conn, "SELECT c.id, c.class_name,
        COUNT(a.id) AS tot,
        SUM(a.is_present=1) AS pre
    FROM classes c
    LEFT JOIN attendance a ON a.class_id=c.id AND DATE(a.timestamp)=CURDATE()
    GROUP BY c.id
    ORDER BY c.class_name");
$class_data = [];
if ($class_res) {
    while ($r = mysqli_fetch_assoc($class_res)) {
        $pct = $r['tot'] ? round($r['pre']/$r['tot']*100) : 0;
        $class_data[] = [
            'id'   => (int)$r['id'],
            'name' => $r['class_name'],
            'pct'  => $pct,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — AttendX Admin</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .byclass-scroll { max-height: 320px; overflow-y: auto; padding-right: 6px; }
    .byclass-scroll::-webkit-scrollbar { width: 6px; }
    .byclass-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
    .byclass-row {
      padding: 8px 8px 6px; border-radius: 8px; cursor: pointer;
      transition: background .12s; margin-bottom: 2px;
    }
    .byclass-row:hover { background: #f6f8fb; }
    .byclass-caret { display: inline-block; font-size: 9px; color: #9ca3af; transition: transform .15s; }
    .byclass-row.open .byclass-caret { transform: rotate(90deg); }
    .roster-panel { display: none; margin-top: 8px; cursor: default; }
    .byclass-row.open .roster-panel { display: block; }
    .roster-group { margin-bottom: 8px; }
    .roster-group-head {
      font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
      display: flex; align-items: center; gap: 6px; margin-bottom: 4px;
    }
    .roster-group-head .cnt { color: #9ca3af; font-weight: 500; }
    .roster-item {
      display: flex; align-items: center; gap: 8px;
      font-size: 12px; color: #374151; padding: 4px 8px;
      background: #f9fafb; border-radius: 6px; margin-bottom: 3px;
    }
    .roster-item .num { color: #9ca3af; font-size: 11px; margin-left: auto; }
    .roster-item .mini { font-size: 10px; padding: 1px 6px; border-radius: 999px; }
    .mini-ok   { background: #dcfce7; color: #16a34a; }
    .mini-no   { background: #fee2e2; color: #dc2626; }
    .sw-green  { color: #16a34a; }
    .sw-orange { color: #f59e0b; }
    .sw-red    { color: #dc2626; }
    .roster-empty { font-size: 11px; color: #9ca3af; padding: 6px 0; }
  </style>
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-admin.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Dashboard</h1>
      <div class="header-actions">
        <span style="font-size:12px;color:#6b7280;"><?= date('l, d F Y') ?></span>
      </div>
    </header>
    <div class="content">

      <!-- Stat cards -->
      <div class="stat-grid">
        <div class="stat-card blue">
          <div class="stat-label">Total Students</div>
          <div class="stat-value"><?= $total_students ?></div>
          <div class="stat-change">Registered students</div>
        </div>
        <div class="stat-card green">
          <div class="stat-label">Present Today</div>
          <div class="stat-value"><?= $present_today ?></div>
          <div class="stat-change up"><?= date('d M Y') ?></div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-label">Active Classes</div>
          <div class="stat-value"><?= $total_classes ?></div>
          <div class="stat-change"><?= $total_lecturers ?> lecturers</div>
        </div>
        <div class="stat-card red">
          <div class="stat-label">At Risk (&lt;80%)</div>
          <div class="stat-value"><?= $at_risk ?></div>
          <div class="stat-change down">Students below threshold</div>
        </div>
      </div>

      <div class="grid-2" style="margin-bottom:20px;">
        <!-- Weekly attendance chart -->
        <div class="card">
          <div class="card-header"><h3>This Week — Attendance %</h3></div>
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

        <!-- By-class chart -->
        <div class="card">
          <div class="card-header"><h3>Today — By Class</h3></div>
          <div class="card-body">
            <?php if (empty($class_data)): ?>
            <p class="text-muted text-small">No classes found.</p>
            <?php else: ?>
            <div class="byclass-scroll">
              <?php foreach ($class_data as $cd): ?>
              <div class="byclass-row" onclick="toggleRoster(this, <?= $cd['id'] ?>)">
                <div style="margin-bottom:6px;">
                  <div class="flex justify-between mb-1" style="align-items:center;">
                    <span style="font-size:12px;color:#374151;display:inline-flex;align-items:center;gap:6px;">
                      <span class="byclass-caret">&#9656;</span>
                      <?= htmlspecialchars($cd['name']) ?>
                    </span>
                    <span style="font-size:12px;color:#6b7280;"><?= $cd['pct'] ?>%</span>
                  </div>
                  <div class="progress-bar">
                    <div class="progress-fill <?= $cd['pct']<80?'warn':'' ?>" style="width:<?= $cd['pct'] ?>%"></div>
                  </div>
                </div>
                <div class="roster-panel" onclick="event.stopPropagation()"></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Live feed -->
      <div class="card">
        <div class="card-header">
          <h3>Live Attendance Feed</h3>
          <span style="font-size:12px;color:#9ca3af;" id="feedTime">Auto-refreshes every 5s</span>
        </div>
        <div class="card-body" style="padding:0 18px;">
          <div id="liveFeed" style="min-height:80px;">
            <p class="text-muted text-small" style="padding:20px 0;">Loading...</p>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>
<div class="toast-container" id="toastContainer"></div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
}
function showToast(msg, type='info') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

const SERVER_TODAY = <?= json_encode(date('Y-m-d')) ?>;  // server's date (UTC+8), not browser UTC

// ─── Today — By Class: expandable roster ───────────────────────
function escHtml(s){return String(s ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

async function toggleRoster(rowEl, classId) {
  const panel = rowEl.querySelector('.roster-panel');
  const isOpen = rowEl.classList.contains('open');
  if (isOpen) { rowEl.classList.remove('open'); return; }
  rowEl.classList.add('open');
  if (!panel.dataset.loaded) {
    panel.innerHTML = '<div class="roster-empty">Loading...</div>';
    try {
      const res  = await fetch('/attendx/api/class_roster.php?class_id=' + classId + '&date=' + SERVER_TODAY + '&_=' + Date.now());
      const data = await res.json();
      panel.innerHTML = renderRoster(data);
      panel.dataset.loaded = '1';
    } catch (e) {
      panel.innerHTML = '<div class="roster-empty">Failed to load roster.</div>';
    }
  }
}

function renderRoster(data) {
  const group = (title, items, swatch, badge) => {
    let rows = items.length
      ? items.map(p => {
          const mini = (badge === 'present')
            ? '<span class="mini mini-ok">Card &#10003;</span>'
            : '';
          return `<div class="roster-item">
            <span>${escHtml(p.name)}</span>
            ${mini}
            <span class="num">${escHtml(p.student_number)}</span>
          </div>`;
        }).join('')
      : '<div class="roster-empty">None</div>';
    return `<div class="roster-group">
      <div class="roster-group-head ${swatch}">${title} <span class="cnt">(${items.length})</span></div>
      ${rows}
    </div>`;
  };
  return group('&#10003; Present', data.present || [], 'sw-green', 'present')
       + group('&#10007; Absent',  data.absent  || [], 'sw-red',   'absent');
}
async function fetchLiveFeed() {
  const res  = await fetch('/attendx/api/get_attendance.php?date=' + SERVER_TODAY + '&_=' + Date.now());
  const data = await res.json();
  const feed = document.getElementById('liveFeed');

  if (!data.records || data.records.length === 0) {
    feed.innerHTML = '<p class="text-muted text-small" style="padding:20px 0;">No attendance records for today.</p>';
    return;
  }

  const s = data.summary;
  feed.innerHTML = `
    <div class="flex gap-3" style="padding:12px 0;border-bottom:1px solid #f3f4f6;flex-wrap:wrap;">
      <span style="font-size:12px;"><b>${s.total}</b> total</span>
      <span style="font-size:12px;color:#16a34a;"><b>${s.present}</b> present</span>
      <span style="font-size:12px;color:#dc2626;"><b>${s.absent}</b> absent</span>
    </div>` +
    data.records.slice(0,15).map(r => {
      // MySQL returns these as strings ("0"/"1"); "0" is truthy in JS, so
      // compare numerically to reflect the real status.
      const present = Number(r.is_present)    === 1;
      const rfidOk  = Number(r.rfid_verified) === 1;
      const dot   = present ? 'present' : 'absent';
      const badge = present
        ? '<span class="badge badge-success">&#10003; Present</span>'
        : '<span class="badge badge-danger">&#10007; Absent</span>';
      const rfid  = rfidOk ? '<span class="badge badge-success">Card &#10003;</span>' : '<span class="badge badge-danger">Card &#10007;</span>';
      const time  = r.timestamp ? r.timestamp.slice(11,16) : '';
      return `<div class="feed-item">
        <span class="feed-dot ${dot}"></span>
        <div class="feed-info">
          <div class="feed-name">${r.student_name}</div>
          <div class="feed-class">${r.class_name}</div>
        </div>
        <div class="feed-right">
          <div class="feed-badges">${rfid} ${badge}</div>
          <div class="feed-time">${time}</div>
        </div>
      </div>`;
    }).join('');
  document.getElementById('feedTime').textContent = 'Updated ' + new Date().toLocaleTimeString();
}

fetchLiveFeed();
setInterval(fetchLiveFeed, 5000);
</script>
</body>
</html>
