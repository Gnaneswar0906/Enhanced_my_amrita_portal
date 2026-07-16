<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_entry') {
        $mode = $_POST['mode'] ?? 'cohort';
        $day = trim($_POST['day_name'] ?? '');
        $time = trim($_POST['time_slot'] ?? '');
        $code = trim($_POST['course_code'] ?? '');
        $name = trim($_POST['course_name'] ?? '');
        $room = trim($_POST['room'] ?? '');
        $faculty = trim($_POST['faculty_name'] ?? '');
        
        $b_batch = $_POST['b_batch'] ?? 'all';
        $b_branch = $_POST['b_branch'] ?? 'all';
        $b_section = $_POST['b_section'] ?? 'all';
        $b_sem = $_POST['b_sem'] ?? 'all';

        if ($day && $time && $code) {
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare('INSERT INTO timetable (student_id, day_name, time_slot, course_code, course_name, room, faculty_name, batch, department, section, semester) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                
                if ($mode === 'student') {
                    $sid = intval($_POST['student_id'] ?? 0);
                    if ($sid) {
                        $s_stmt = $pdo->prepare("SELECT batch, department, section, semester FROM students WHERE id = ?");
                        $s_stmt->execute([$sid]);
                        $s = $s_stmt->fetch();
                        $ins->execute([$sid, $day, $time, $code, $name, $room, $faculty, $s['batch'], $s['department'], $s['section'], $s['semester']]);
                        $msg = 'add_success';
                    }
                } else {
                    $s_sql = "SELECT id, batch, department, section, semester FROM students WHERE 1=1";
                    $s_params = [];
                    if ($b_batch !== 'all') { $s_sql .= " AND batch = ?"; $s_params[] = $b_batch; }
                    if ($b_branch !== 'all') { $s_sql .= " AND department = ?"; $s_params[] = $b_branch; }
                    if ($b_section !== 'all') { $s_sql .= " AND section = ?"; $s_params[] = $b_section; }
                    if ($b_sem !== 'all') { $s_sql .= " AND semester = ?"; $s_params[] = intval($b_sem); }
                    
                    $s_stmt = $pdo->prepare($s_sql);
                    $s_stmt->execute($s_params);
                    $students_to_add = $s_stmt->fetchAll();
                    
                    foreach ($students_to_add as $std) {
                        $ins->execute([$std['id'], $day, $time, $code, $name, $room, $faculty, $std['batch'], $std['department'], $std['section'], $std['semester']]);
                    }
                    $msg = count($students_to_add) . ' timetable entries added!';
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'add_error';
            }
        }
    } elseif ($action === 'delete_entry') {
        $tid = intval($_POST['timetable_id'] ?? 0);
        if ($tid) { $pdo->prepare('DELETE FROM timetable WHERE id = ?')->execute([$tid]); $msg = 'Entry deleted.'; }
    }
}

$table_alias = 's';
require_once 'filter_logic.php';

$students = $pdo->query('SELECT id, name, enrollment_no FROM students ORDER BY name')->fetchAll();
$teachers = $pdo->query('SELECT id, name FROM users WHERE role = "teacher" ORDER BY name')->fetchAll();

$sql = 'SELECT t.*, s.name as student_name, s.enrollment_no, s.batch as s_batch, s.department as s_branch, s.section as s_section, s.semester as s_sem 
        FROM timetable t JOIN students s ON t.student_id = s.id 
        WHERE 1=1 ' . $filter_sql . ' 
        ORDER BY s.name, FIELD(t.day_name,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), t.time_slot';
$stmt = $pdo->prepare($sql);
$stmt->execute($filter_params);
$entries = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita Admin - Timetable</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .delete-btn { background:linear-gradient(135deg,#c0392b,#e74c3c); color:#fff; border:none; padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600; cursor:pointer; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Timetable</div>
    <div class="main-content">
        <div class="page-header"><h1><i class="fa fa-clock-o"></i> Manage Timetable</h1><a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a></div>
        <?php if ($msg === 'add_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Timetable updated successfully!</div>
        <?php elseif (!empty($msg)): ?><div class="msg-success"><i class="fa fa-info-circle"></i> <?php echo $msg; ?></div><?php endif; ?>

        <?php 
        $filter_count = count($entries);
        include 'filter_ui.php'; 
        ?>

        <div class="card form-section" style="background:#fff; border-radius:10px; padding:20px; border:1px solid #e8e8e8; margin-bottom:20px;">
            <h3><i class="fa fa-plus-circle"></i> Add Timetable Entry</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_entry">
                
                <div style="margin-bottom:15px; padding:10px; background:#f8f9fa; border-radius:6px; border:1px solid #ddd;">
                    <label style="margin-right:15px;"><input type="radio" name="mode" value="cohort" checked onclick="document.getElementById('cohort_div').style.display='block'; document.getElementById('student_div').style.display='none';"> Assign to Cohort (Bulk)</label>
                    <label><input type="radio" name="mode" value="student" onclick="document.getElementById('cohort_div').style.display='none'; document.getElementById('student_div').style.display='block';"> Assign to Specific Student</label>
                </div>

                <div id="cohort_div" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
                    <select name="b_batch" class="form-control" style="flex:1;">
                        <option value="all">All Batches</option>
                        <?php foreach (['2022-2026', '2023-2027', '2024-2028', '2025-2029'] as $b): ?><option value="<?php echo $b; ?>"><?php echo $b; ?></option><?php endforeach; ?>
                    </select>
                    <select name="b_branch" class="form-control" style="flex:1;">
                        <option value="all">All Branches</option>
                        <option value="Computer Science & Engineering">CSE</option>
                        <option value="Artificial Intelligence & Data Science">AIDS</option>
                        <option value="Robotics & Artificial Intelligence">RAI</option>
                        <option value="Electronics & Communication">ECE</option>
                        <option value="Electrical & Electronics">EEE</option>
                        <option value="Electronics & Computer">EAC</option>
                        <option value="Mechanical Engineering">MECHANICAL</option>
                    </select>
                    <select name="b_section" class="form-control" style="flex:1;">
                        <option value="all">All Sections</option>
                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                    </select>
                    <select name="b_sem" class="form-control" style="flex:1;">
                        <option value="all">All Semesters</option>
                        <?php for ($i=1; $i<=8; $i++): ?><option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option><?php endfor; ?>
                    </select>
                </div>

                <div id="student_div" class="form-group" style="display:none; margin-bottom:15px;">
                    <label>Specific Student</label>
                    <select class="form-control" name="student_id">
                        <option value="">-- Select --</option>
                        <?php foreach ($students as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name'].' ('.$s['enrollment_no'].')'); ?></option><?php endforeach; ?>
                    </select>
                </div>
                    <div class="form-group"><label>Day</label>
                        <select class="form-control" name="day_name" required>
                            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?><option><?php echo $d; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Time Slot</label><input type="text" class="form-control" name="time_slot" placeholder="09:00-10:00" required></div>
                    <div class="form-group"><label>Course Code</label><input type="text" class="form-control" name="course_code" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Course Name</label><input type="text" class="form-control" name="course_name"></div>
                    <div class="form-group"><label>Teacher / Faculty</label>
                        <select class="form-control" name="faculty_name" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $t): ?><option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Room</label><input type="text" class="form-control" name="room"></div>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-plus"></i> Add Entry</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">All Timetable Entries (<?php echo count($entries); ?>)</h2>
            <?php if (empty($entries)): ?><div class="empty-state"><i class="fa fa-clock-o"></i><p>No entries.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Cohort</th><th>Day</th><th>Time</th><th>Course</th><th>Faculty & Room</th><th>Del</th></tr></thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($e['student_name']); ?></strong><br><span style="font-size:11px; color:#888;"><?php echo htmlspecialchars($e['enrollment_no']); ?></span></td>
                    <td style="font-size:11px; color:#666;">
                        <?php echo htmlspecialchars($e['s_batch'] . ' | ' . $e['s_branch']); ?><br>
                        Sec <?php echo htmlspecialchars($e['s_section']); ?> | Sem <?php echo htmlspecialchars($e['s_sem']); ?>
                    </td>
                    <td><?php echo $e['day_name']; ?></td>
                    <td><?php echo $e['time_slot']; ?></td>
                    <td><strong><?php echo htmlspecialchars($e['course_code']); ?></strong><br><small><?php echo htmlspecialchars($e['course_name']); ?></small></td>
                    <td><?php echo htmlspecialchars($e['faculty_name']); ?><br><span style="font-size:11px; color:#888;">Room: <?php echo htmlspecialchars($e['room']); ?></span></td>
                    <td><form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete_entry"><input type="hidden" name="timetable_id" value="<?php echo $e['id']; ?>"><button type="submit" class="delete-btn"><i class="fa fa-trash"></i></button></form></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
