<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $type     = isset($_GET['type'])     ? $_GET['type']     : 'overall';
    $from     = isset($_GET['from'])     ? $_GET['from']     : date('Y-m-01');
    $to       = isset($_GET['to'])       ? $_GET['to']       : date('Y-m-d');
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

    $where = "DATE(a.timestamp) BETWEEN '$from' AND '$to'";
    if ($class_id) $where .= " AND a.class_id=$class_id";
    if ($type === 'below80') {
        $where .= " GROUP BY s.id HAVING (SUM(a.is_present)/COUNT(a.id)*100) < 80";
    }

    $sql = "SELECT u.name, s.student_number, s.rfid_uid, c.class_name,
                   a.rfid_verified, a.is_present, a.method, a.timestamp
            FROM attendance a
            JOIN students s ON a.student_id=s.id
            JOIN users    u ON s.user_id=u.id
            JOIN classes  c ON a.class_id=c.id
            WHERE $where
            ORDER BY a.timestamp DESC";
    $res = mysqli_query($conn, $sql);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendx_report_' . $type . '_' . date('Ymd') . '.csv"');
    echo "Name,Student No.,RFID UID,Class,RFID Verified,Present,Method,Timestamp\n";
    if ($res) while ($row = mysqli_fetch_assoc($res)) {
        echo implode(',', array_map(fn($v) => '"' . str_replace('"','""',$v) . '"', [
            $row['name'], $row['student_number'], $row['rfid_uid'],
            $row['class_name'], $row['rfid_verified'],
            $row['is_present'], $row['method'], $row['timestamp'],
        ])) . "\n";
    }
    exit;
}

$from     = isset($_GET['from'])     ? $_GET['from']     : date('Y-m-01');
$to       = isset($_GET['to'])       ? $_GET['to']       : date('Y-m-d');
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$type     = isset($_GET['type'])     ? $_GET['type']     : 'overall';

$classes_res = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name");
$classes = [];
while ($c = mysqli_fetch_assoc($classes_res)) $classes[] = $c;

$where = "DATE(a.timestamp) BETWEEN '$from' AND '$to'";
if ($class_id) $where .= " AND a.class_id=$class_id";

$sql = "SELECT u.name, s.student_number, c.class_name, a.rfid_verified, a.is_present, a.method, a.timestamp
        FROM attendance a
        JOIN students s ON a.student_id=s.id
        JOIN users    u ON s.user_id=u.id
        JOIN classes  c ON a.class_id=c.id
        WHERE $where
        ORDER BY a.timestamp DESC
        LIMIT 500";

$res = mysqli_query($conn, $sql);
$records = [];
if ($res) while ($r = mysqli_fetch_assoc($res)) $records[] = $r;

// Below 80% per student
$below_sql = "SELECT u.name, s.student_number, COUNT(a.id) tot, SUM(a.is_present) pre,
                     ROUND(SUM(a.is_present)/COUNT(a.id)*100,1) pct
              FROM attendance a JOIN students s ON a.student_id=s.id JOIN users u ON s.user_id=u.id
              WHERE DATE(a.timestamp) BETWEEN '$from' AND '$to'"
              . ($class_id ? " AND a.class_id=$class_id" : '') .
              " GROUP BY s.id HAVING pct < 80 ORDER BY pct ASC";
$below_res  = mysqli_query($conn, $below_sql);
$below      = [];
while ($r = mysqli_fetch_assoc($below_res)) $below[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports — AttendX Admin</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-admin.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Reports</h1>
      <div class="header-actions">
        <button class="btn btn-secondary btn-sm" onclick="window.print()">Print</button>
      </div>
    </header>
    <div class="content">

      <!-- Filter -->
      <form method="GET" class="card" style="padding:16px 18px;margin-bottom:20px;">
        <div class="filter-bar" style="margin-bottom:0;">
          <select class="form-control" name="type" style="max-width:180px;">
            <option value="overall"    <?= $type==='overall'   ?'selected':'' ?>>Overall</option>
            <option value="daily"      <?= $type==='daily'     ?'selected':'' ?>>Daily</option>
            <option value="below80"    <?= $type==='below80'   ?'selected':'' ?>>Below 80%</option>
          </select>
          <input type="date" class="form-control" name="from" value="<?= $from ?>" style="max-width:155px;">
          <span style="font-size:13px;color:#6b7280;">to</span>
          <input type="date" class="form-control" name="to" value="<?= $to ?>" style="max-width:155px;">
          <select class="form-control" name="class_id" style="max-width:180px;">
            <option value="">All Classes</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_id==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['class_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Generate</button>
          <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-secondary btn-sm">Export CSV</a>
        </div>
      </form>

      <?php if ($type === 'below80'): ?>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h3>Students Below 80% Attendance</h3></div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Name</th><th>Student No.</th><th>Sessions</th><th>Present</th><th>Rate</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($below)): ?>
              <tr><td colspan="6" class="table-empty">No at-risk students in this period.</td></tr>
              <?php else: foreach ($below as $b): ?>
              <tr>
                <td style="font-weight:500;"><?= htmlspecialchars($b['name']) ?></td>
                <td><?= $b['student_number'] ?></td>
                <td><?= $b['tot'] ?></td>
                <td><?= $b['pre'] ?></td>
                <td>
                  <div class="progress-bar" style="width:80px;display:inline-block;">
                    <div class="progress-fill danger" style="width:<?= $b['pct'] ?>%"></div>
                  </div>
                  <span style="font-size:12px;margin-left:6px;"><?= $b['pct'] ?>%</span>
                </td>
                <td><span class="badge badge-danger">At Risk</span></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Records -->
      <div class="card">
        <div class="card-header">
          <h3><?= ucfirst($type) ?> Report — <?= $from ?> to <?= $to ?></h3>
          <span style="font-size:12px;color:#9ca3af;"><?= count($records) ?> records</span>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Student No.</th>
                <th>Class</th>
                <th>RFID</th>
                <th>Status</th>
                <th>Method</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($records)): ?>
              <tr><td colspan="7" class="table-empty">No records found.</td></tr>
              <?php else: foreach ($records as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= $r['student_number'] ?></td>
                <td><?= htmlspecialchars($r['class_name']) ?></td>
                <td><?= $r['rfid_verified']   ? '<span class="badge badge-success">&#10003;</span>' : '<span class="badge badge-danger">&#10007;</span>' ?></td>
                <td><?= $r['is_present'] ? '<span class="badge badge-success">Present</span>' : '<span class="badge badge-danger">Absent</span>' ?></td>
                <td><span class="badge badge-gray"><?= $r['method'] ?></span></td>
                <td style="font-size:12px;"><?= substr($r['timestamp'],0,16) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('open'); }
function closeSidebar()   { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }
</script>
</body>
</html>
