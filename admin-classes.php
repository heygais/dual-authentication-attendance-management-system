<?php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

$classes_res = mysqli_query($conn, "SELECT c.*, u.name AS lecturer_name, (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id=c.id) AS enrolled FROM classes c LEFT JOIN users u ON c.lecturer_id=u.id ORDER BY c.class_name");

$lecturers_res = mysqli_query($conn, "SELECT u.id, u.name FROM users u WHERE u.role='lecturer' ORDER BY u.name");
$lecturers = [];
while ($l = mysqli_fetch_assoc($lecturers_res)) $lecturers[] = $l;

$students_res = mysqli_query($conn, "SELECT s.id, s.student_number, u.name FROM students s JOIN users u ON s.user_id=u.id ORDER BY u.name");
$students = [];
while ($st = mysqli_fetch_assoc($students_res)) $students[] = $st;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Classes — AttendX Admin</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar-admin.php'; ?>
  <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
  <main class="main">
    <header class="page-header">
      <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
      <h1>Classes</h1>
      <div class="header-actions">
        <button class="btn btn-primary btn-sm" onclick="openModal('addModal')">+ Add Class</button>
      </div>
    </header>
    <div class="content">

      <div class="card" style="margin-bottom:20px;">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Code</th>
                <th>Class Name</th>
                <th>Lecturer</th>
                <th>Venue</th>
                <th>Schedule</th>
                <th>Enrolled</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($classes_res) === 0): ?>
              <tr><td colspan="7" class="table-empty">No classes yet. Add one above.</td></tr>
              <?php else: mysqli_data_seek($classes_res, 0); while ($row = mysqli_fetch_assoc($classes_res)): ?>
              <tr>
                <td><span class="badge badge-info"><?= htmlspecialchars($row['class_code']) ?></span></td>
                <td style="font-weight:500;"><?= htmlspecialchars($row['class_name']) ?></td>
                <td><?= htmlspecialchars($row['lecturer_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['venue']) ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($row['schedule']) ?></td>
                <td><?= $row['enrolled'] ?>/<?= $row['max_students'] ?></td>
                <td>
                  <div class="flex gap-2">
                    <button class="btn btn-secondary btn-xs" onclick="openEnroll(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['class_name'])) ?>')">Enroll</button>
                    <button class="btn btn-secondary btn-xs" onclick="editClass(<?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                    <button class="btn btn-danger btn-xs" onclick="deleteClass(<?= $row['id'] ?>)">Del</button>
                  </div>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Add Class Modal -->
<div class="modal-overlay hidden" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Add Class</h3>
      <button class="modal-close" onclick="closeModal('addModal')">&#10005;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editId" value="">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Class Code *</label>
          <input class="form-control" id="classCode" placeholder="e.g. CS101">
        </div>
        <div class="form-group">
          <label class="form-label">Max Students</label>
          <input class="form-control" type="number" id="maxStudents" value="30">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Class Name *</label>
        <input class="form-control" id="className" placeholder="e.g. Data Structures">
      </div>
      <div class="form-group">
        <label class="form-label">Lecturer</label>
        <select class="form-control" id="lecturerId">
          <option value="">— Select Lecturer —</option>
          <?php foreach ($lecturers as $l): ?>
          <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Venue</label>
          <input class="form-control" id="venue" placeholder="e.g. Lab 3">
        </div>
        <div class="form-group">
          <label class="form-label">Schedule</label>
          <input class="form-control" id="schedule" placeholder="e.g. Mon/Wed 9-11am">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveClass()">Save Class</button>
    </div>
  </div>
</div>

<!-- Enroll Modal -->
<div class="modal-overlay hidden" id="enrollModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Enroll Student — <span id="enrollClassName"></span></h3>
      <button class="modal-close" onclick="closeModal('enrollModal')">&#10005;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Select Student</label>
        <select class="form-control" id="enrollStudentId">
          <option value="">— Select Student —</option>
          <?php foreach ($students as $st): ?>
          <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?> (<?= $st['student_number'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('enrollModal')">Cancel</button>
      <button class="btn btn-primary" onclick="enrollStudent()">Enroll</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
let enrollClassId = null;
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('open'); }
function closeSidebar()   { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function showToast(msg, type='info') {
  const c = document.getElementById('toastContainer');
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

function editClass(c) {
  document.getElementById('modalTitle').textContent = 'Edit Class';
  document.getElementById('editId').value      = c.id;
  document.getElementById('classCode').value   = c.class_code;
  document.getElementById('className').value   = c.class_name;
  document.getElementById('lecturerId').value  = c.lecturer_id || '';
  document.getElementById('venue').value       = c.venue;
  document.getElementById('schedule').value    = c.schedule;
  document.getElementById('maxStudents').value = c.max_students;
  openModal('addModal');
}

async function saveClass() {
  const id  = document.getElementById('editId').value;
  const fd  = new FormData();
  fd.append('action',       id ? 'edit' : 'add');
  if (id) fd.append('id',   id);
  fd.append('class_code',   document.getElementById('classCode').value.trim());
  fd.append('class_name',   document.getElementById('className').value.trim());
  fd.append('lecturer_id',  document.getElementById('lecturerId').value);
  fd.append('venue',        document.getElementById('venue').value.trim());
  fd.append('schedule',     document.getElementById('schedule').value.trim());
  fd.append('max_students', document.getElementById('maxStudents').value);

  const res  = await fetch('/attendx/api/classes.php', { method:'POST', body:fd });
  const data = await res.json();
  if (data.status === 'success') {
    showToast(data.message, 'success');
    setTimeout(() => location.reload(), 900);
  } else {
    showToast(data.message, 'error');
  }
}

function openEnroll(classId, name) {
  enrollClassId = classId;
  document.getElementById('enrollClassName').textContent = name;
  openModal('enrollModal');
}

async function enrollStudent() {
  const sid = document.getElementById('enrollStudentId').value;
  if (!sid) { showToast('Select a student first.', 'error'); return; }
  const fd = new FormData();
  fd.append('action', 'enroll');
  fd.append('student_id', sid);
  fd.append('class_id', enrollClassId);
  const res  = await fetch('/attendx/api/classes.php', { method:'POST', body:fd });
  const data = await res.json();
  showToast(data.message, data.status === 'success' ? 'success' : 'error');
  if (data.status === 'success') { closeModal('enrollModal'); setTimeout(() => location.reload(), 900); }
}

async function deleteClass(id) {
  if (!confirm('Delete this class? All attendance records will remain.')) return;
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', id);
  const res  = await fetch('/attendx/api/classes.php', { method:'POST', body:fd });
  const data = await res.json();
  showToast(data.message, 'success');
  setTimeout(() => location.reload(), 900);
}
</script>
</body>
</html>
