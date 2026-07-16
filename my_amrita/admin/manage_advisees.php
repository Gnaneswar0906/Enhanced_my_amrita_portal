<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'assign') {
        $uid = intval($_POST['teacher_id'] ?? 0);
        $sid = intval($_POST['student_id'] ?? 0);
        if ($uid && $sid) {
            try {
                $pdo->prepare('INSERT INTO teacher_advisees (user_id, student_id) VALUES (?,?)')->execute([$uid, $sid]);
                $msg = 'assign_success';
            } catch(Exception $e) { $msg = 'assign_error'; }
        }
    } elseif ($action === 'remove') {
        $aid = intval($_POST['advisee_id'] ?? 0);
        if ($aid) { $pdo->prepare('DELETE FROM teacher_advisees WHERE id=?')->execute([$aid]); $msg = 'remove_success'; }
    }
}

$table_alias = 's';
require_once 'filter_logic.php';
$filter_teacher = $_GET['teacher'] ?? 'all';

$sql = 'SELECT ta.id, u.name as teacher_name, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
        FROM teacher_advisees ta 
        JOIN users u ON ta.user_id = u.id 
        JOIN students s ON ta.student_id = s.id 
        WHERE 1=1 ' . $filter_sql;

if ($filter_teacher !== 'all') {
    $sql .= ' AND u.id = ?';
    $filter_params[] = $filter_teacher;
}

$sql .= ' ORDER BY u.name, s.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($filter_params);
$mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$teachers = $pdo->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();

$extra_filters = '<label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Advisor:</label>
    <select name="teacher" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:\'Inter\',sans-serif;">
        <option value="all">All Advisors</option>';
foreach ($teachers as $t) {
    $sel = ($filter_teacher == $t['id']) ? 'selected' : '';
    $extra_filters .= '<option value="' . $t['id'] . '" ' . $sel . '>' . htmlspecialchars($t['name']) . '</option>';
}
$extra_filters .= '</select>';

$students = $pdo->query('SELECT id, name, enrollment_no FROM students ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Admin - Manage Advisees</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>.delete-btn-sm{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;} .delete-btn-sm:hover{transform:translateY(-1px);}</style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Manage Advisees</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-link"></i> Advisor ↔ Student Mapping</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php 
        $filter_count = count($mappings);
        include 'filter_ui.php'; 
        ?>

        <?php if ($msg === 'assign_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Student assigned to advisor!</div>
        <?php elseif ($msg === 'assign_error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Assignment failed (may already exist).</div>
        <?php elseif ($msg === 'remove_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Mapping removed.</div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Current Mappings (<?php echo count($mappings); ?>)</h2>
            <?php if (empty($mappings)): ?><div class="empty-state"><i class="fa fa-link"></i><p>No advisor-student mappings.</p></div>
            <?php else: ?>
                <table class="data-table" style="width:100%; text-align:left;">
                    <thead><tr><th>#</th><th>Advisor</th><th>Student</th><th>Enrollment</th><th>Cohort</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($mappings as $i => $m): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($m['teacher_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($m['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($m['enrollment_no']); ?></td>
                            <td style="font-size:11px; color:#666;">
                                <?php echo htmlspecialchars($m['batch'] . ' | ' . $m['branch']); ?><br>
                                Sec <?php echo htmlspecialchars($m['section']); ?> | Sem <?php echo htmlspecialchars($m['current_sem']); ?>
                            </td>
                            <td><form method="POST" style="display:inline;" onsubmit="return confirm('Remove?');"><input type="hidden" name="action" value="remove"><input type="hidden" name="advisee_id" value="<?php echo $m['id']; ?>"><button type="submit" class="delete-btn-sm"><i class="fa fa-times"></i></button></form></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Assign Student to Advisor</h3>
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                <div class="form-row">
                    <div class="form-group"><label>Advisor</label><select class="form-control" name="teacher_id" required><option value="">Select advisor...</option><?php foreach ($teachers as $t): ?><option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Student</label><select class="form-control" name="student_id" required><option value="">Select student...</option><?php foreach ($students as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['enrollment_no'] . ' – ' . $s['name']); ?></option><?php endforeach; ?></select></div>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-link"></i> Assign</button>
            </form>
        </div>
    </div>
</body>
</html>
