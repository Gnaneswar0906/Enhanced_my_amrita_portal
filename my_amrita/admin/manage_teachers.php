<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_teacher') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $uname = trim($_POST['username'] ?? '');
        $upass = $_POST['password'] ?? 'teacher';
        $dept = trim($_POST['department'] ?? '');
        if ($name && $uname) {
            try {
                $hash = password_hash($upass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password, role, name, email, department) VALUES (?, ?, "teacher", ?, ?, ?)');
                $stmt->execute([$uname, $hash, $name, $email, $dept]);
                $msg = 'add_success';
            } catch(Exception $e) { $msg = 'add_error'; }
        } else { $msg = 'add_error'; }
    } elseif ($action === 'delete_teacher') {
        $tid = intval($_POST['teacher_id'] ?? 0);
        if ($tid) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM teacher_courses WHERE user_id = ?')->execute([$tid]);
                $pdo->prepare('DELETE FROM teacher_advisees WHERE user_id = ?')->execute([$tid]);
                $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "teacher"')->execute([$tid]);
                $pdo->commit();
                $msg = 'delete_success';
            } catch(Exception $e) { $pdo->rollBack(); $msg = 'delete_error'; }
        }
    }
}
// Filters
$filter_batch = $_GET['batch'] ?? 'all';
$filter_branch = $_GET['branch'] ?? 'all';
$filter_section = $_GET['section'] ?? 'all';
$filter_semester = $_GET['semester'] ?? 'all';

$sql = 'SELECT u.*, GROUP_CONCAT(DISTINCT tc.course_code SEPARATOR ", ") as courses 
        FROM users u 
        LEFT JOIN teacher_courses tc ON u.id = tc.user_id ';

$where = 'WHERE u.role = "teacher"';
$params = [];

if ($filter_batch !== 'all' || $filter_branch !== 'all' || $filter_section !== 'all' || $filter_semester !== 'all') {
    $sql .= ' JOIN timetable t ON u.name = t.faculty_name JOIN students s ON t.student_id = s.id ';
    if ($filter_batch !== 'all') { $where .= ' AND s.batch = ?'; $params[] = $filter_batch; }
    if ($filter_branch !== 'all') { $where .= ' AND s.department = ?'; $params[] = $filter_branch; }
    if ($filter_section !== 'all') { $where .= ' AND s.section = ?'; $params[] = $filter_section; }
    if ($filter_semester !== 'all') { $where .= ' AND s.semester = ?'; $params[] = intval($filter_semester); }
}

$sql .= " $where GROUP BY u.id ORDER BY u.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Admin - Manage Teachers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>.delete-btn{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif;} .delete-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(192,57,43,0.3);}</style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Manage Teachers</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-book"></i> Manage Teachers</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php if ($msg === 'add_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Teacher added!</div>
        <?php elseif ($msg === 'add_error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Failed to add teacher.</div>
        <?php elseif ($msg === 'delete_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Teacher removed.</div>
        <?php elseif ($msg === 'delete_error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Error removing teacher.</div>
        <?php endif; ?>

        <?php 
        $filter_count = count($teachers);
        include 'filter_ui.php'; 
        ?>

        <div class="card">
            <h2 class="card-title">All Teachers (<?php echo count($teachers); ?>)</h2>
            <?php if (empty($teachers)): ?>
                <div class="empty-state"><i class="fa fa-book"></i><p>No teachers found.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Department</th><th>Email</th><th>Courses</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($teachers as $i => $t): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($t['username']); ?></td>
                            <td><?php echo htmlspecialchars($t['department'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($t['email'] ?? '—'); ?></td>
                            <td style="font-size:12px;"><?php echo htmlspecialchars($t['courses'] ?? '—'); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this teacher?');">
                                    <input type="hidden" name="action" value="delete_teacher">
                                    <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" class="delete-btn"><i class="fa fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Add New Teacher</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_teacher">
                <div class="form-row">
                    <div class="form-group"><label>Full Name</label><input type="text" class="form-control" name="name" required></div>
                    <div class="form-group"><label>Username</label><input type="text" class="form-control" name="username" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Password</label><input type="text" class="form-control" name="password" value="teacher"></div>
                    <div class="form-group"><label>Email</label><input type="email" class="form-control" name="email"></div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Department</label>
                    <select class="form-control" name="department">
                        <option value="Computer Science & Engineering">CSE</option>
                        <option value="Electronics & Communication">ECE</option>
                        <option value="Mechanical Engineering">ME</option>
                        <option value="Civil Engineering">CE</option>
                    </select>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-plus"></i> Add Teacher</button>
            </form>
        </div>
    </div>
</body>
</html>
