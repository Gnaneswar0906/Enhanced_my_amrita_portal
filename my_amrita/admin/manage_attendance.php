<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_attendance') {
    $aid = intval($_POST['att_id'] ?? 0);
    $total = intval($_POST['total_classes'] ?? 0);
    $attended = intval($_POST['attended'] ?? 0);
    $pct = $total > 0 ? round(($attended / $total) * 100, 2) : 0;
    if ($aid) {
        $pdo->prepare('UPDATE attendance SET total_classes=?, attended=?, percentage=? WHERE id=?')->execute([$total, $attended, $pct, $aid]);
        $msg = 'update_success';
    }
}

$attendance = $pdo->query('SELECT a.*, s.name as student_name, s.enrollment_no FROM attendance a JOIN students s ON a.student_id = s.id ORDER BY s.name, a.course_code')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita Admin - All Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .edit-inline { width:60px; padding:4px 6px; border:1px solid #ddd; border-radius:4px; font-size:12px; text-align:center; }
        .save-btn { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer; font-weight:600; }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Admin Panel (Beta)</span>
        <div class="nav-links">
            <span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> All Attendance</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar-check-o"></i> All Attendance</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php if ($msg === 'update_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Attendance updated! Percentage auto-recalculated.</div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Attendance – All Students</h2>
            <?php if (empty($attendance)): ?>
                <div class="empty-state"><i class="fa fa-calendar-check-o"></i><p>No attendance data.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Course</th><th>Total Classes</th><th>Attended</th><th>%</th><th>Save</th></tr></thead>
                    <tbody>
                    <?php foreach ($attendance as $a): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_attendance">
                        <input type="hidden" name="att_id" value="<?php echo $a['id']; ?>">
                        <tr>
                            <td><strong><?php echo htmlspecialchars($a['student_name']); ?></strong><br><small><?php echo htmlspecialchars($a['enrollment_no']); ?></small></td>
                            <td><strong><?php echo htmlspecialchars($a['course_code']); ?></strong><br><small><?php echo htmlspecialchars($a['course_name']); ?></small></td>
                            <td><input type="number" class="edit-inline" name="total_classes" value="<?php echo $a['total_classes']; ?>"></td>
                            <td><input type="number" class="edit-inline" name="attended" value="<?php echo $a['attended']; ?>"></td>
                            <td><strong><?php echo number_format($a['percentage'], 1); ?>%</strong></td>
                            <td><button type="submit" class="save-btn"><i class="fa fa-save"></i></button></td>
                        </tr>
                    </form>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
