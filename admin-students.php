<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

$search   = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$class_f  = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$where = '1=1';
if ($search)  $where .= " AND (u.name LIKE '%$search%' OR s.student_number LIKE '%$search%' OR s.programme LIKE '%$search%')";
if ($class_f) $where .= " AND sc.class_id = $class_f";

$sql = "SELECT s.id, s.student_number, s.programme, s.year_of_study, s.phone, s.rfid_uid, s.finger_id,
               u.name, u.email
        FROM students s
        JOIN users u ON s.user_id = u.id
        " . ($class_f ? "JOIN student_classes sc ON sc.student_id=s.id" : "") . "
        WHERE $where
        ORDER BY u.name";
$res     = mysqli_query($conn, $sql);
$sql_err = $res ? '' : mysqli_error($conn);

$classes_res = mysqli_query($conn, "SELECT id, class_name FROM classes ORDER BY class_name");
$classes = [];
if ($classes_res) while ($c = mysqli_fetch_assoc($classes_res)) $classes[] = $c;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Students — AttendX Admin</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-admin.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Students</h1>
      <div class="header-actions">
        <a href="register-student.php" class="btn btn-primary btn-sm">+ Add Student</a>
      </div>
    </header>
    <div class="content">

      <form method="GET" class="filter-bar">
        <input class="form-control search-box" name="search" placeholder="Search name, ID, programme..." value="<?= htmlspecialchars($search) ?>">
        <select class="form-control" name="class_id" style="max-width:180px;">
          <option value="">All Classes</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $class_f==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['class_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="admin-students.php" class="btn btn-ghost btn-sm">Clear</a>
      </form>

      <?php if ($sql_err): ?>
      <div class="alert alert-danger"><b>Database error:</b> <?= htmlspecialchars($sql_err) ?></div>
      <?php endif; ?>

      <div class="card">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Student No.</th>
                <th>Programme</th>
                <th>Year</th>
                <th>RFID</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$res || mysqli_num_rows($res) === 0): ?>
              <tr><td colspan="6" class="table-empty">No students found.</td></tr>
              <?php else: ?>
              <?php while ($row = mysqli_fetch_assoc($res)): ?>
              <tr>
                <td>
                  <div style="font-weight:500;"><?= htmlspecialchars($row['name']) ?></div>
                  <div style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($row['email']) ?></div>
                </td>
                <td><?= htmlspecialchars($row['student_number']) ?></td>
                <td><?= htmlspecialchars($row['programme']) ?></td>
                <td><?= $row['year_of_study'] ?></td>
                <td>
                  <?php if ($row['rfid_uid']): ?>
                  <span class="badge badge-success">Card &#10003;</span>
                  <?php else: ?>
                  <span class="badge badge-gray">Not set</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="flex gap-2">
                    <a href="register-rfid.php?sid=<?= $row['id'] ?>" class="btn btn-secondary btn-xs">RFID</a>
                    <button class="btn btn-danger btn-xs" onclick="deleteStudent(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')">Del</button>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
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
async function deleteStudent(id, name) {
  if (!confirm('Delete student: ' + name + '?\nThis cannot be undone.')) return;
  const fd = new FormData();
  fd.append('action', 'delete_student');
  fd.append('id', id);
  const res = await fetch('/attendx/api/register_student.php', { method:'POST', body:fd });
  showToast('Student deleted.', 'success');
  setTimeout(() => location.reload(), 1000);
}
</script>
</body>
</html>
