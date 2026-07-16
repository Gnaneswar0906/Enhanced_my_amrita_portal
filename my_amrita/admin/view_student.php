<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];

$sid = intval($_GET['id'] ?? 0);
if (!$sid) { header('Location: students.php'); exit(); }

$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$sid]);
$student = $stmt->fetch();
if (!$student) { header('Location: students.php'); exit(); }

// Fetch related data
$attendance = $pdo->prepare('SELECT * FROM attendance WHERE student_id = ? ORDER BY course_code'); $attendance->execute([$sid]); $attendance = $attendance->fetchAll();
$marks = $pdo->prepare('SELECT * FROM marks WHERE student_id = ? ORDER BY course_code'); $marks->execute([$sid]); $marks = $marks->fetchAll();
$leaves = $pdo->prepare('SELECT * FROM leaves WHERE student_id = ? ORDER BY created_at DESC'); $leaves->execute([$sid]); $leaves = $leaves->fetchAll();
$gatepasses = $pdo->prepare('SELECT * FROM gate_passes WHERE student_id = ? ORDER BY created_at DESC'); $gatepasses->execute([$sid]); $gatepasses = $gatepasses->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita Admin - View Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>

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
    <div class="breadcrumb-bar">
        <a href="home.php">Admin Home</a> <span class="sep">/</span> <a href="students.php">Students</a> <span class="sep">/</span> <?php echo htmlspecialchars($student['name']); ?>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-user"></i> <?php echo htmlspecialchars($student['name']); ?></h1>
            <a href="students.php" class="back-btn"><i class="fa fa-arrow-left"></i> All Students</a>
        </div>

        <!-- Profile Card -->
        <div class="card">
            <h2 class="card-title">Student Profile</h2>
            <div class="profile-details" style="display:grid; grid-template-columns:1fr 1fr; gap:12px 24px;">
                <div class="profile-field"><div class="label">Enrollment No.</div><div class="value"><?php echo htmlspecialchars($student['enrollment_no']); ?></div></div>
                <div class="profile-field"><div class="label">Name</div><div class="value"><?php echo htmlspecialchars($student['name']); ?></div></div>
                <div class="profile-field"><div class="label">Email</div><div class="value"><?php echo htmlspecialchars($student['email']); ?></div></div>
                <div class="profile-field"><div class="label">Phone</div><div class="value"><?php echo htmlspecialchars($student['phone']); ?></div></div>
                <div class="profile-field"><div class="label">Department</div><div class="value"><?php echo htmlspecialchars($student['department']); ?></div></div>
                <div class="profile-field"><div class="label">Semester</div><div class="value"><?php echo $student['semester']; ?></div></div>
            </div>
        </div>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('att')"><i class="fa fa-calendar-check-o"></i> Attendance</button>
            <button class="tab-btn" onclick="switchTab('marks')"><i class="fa fa-bar-chart"></i> Marks</button>
            <button class="tab-btn" onclick="switchTab('leaves')"><i class="fa fa-calendar"></i> Leaves</button>
            <button class="tab-btn" onclick="switchTab('gp')"><i class="fa fa-ticket"></i> Gate Passes</button>
        </div>

        <div class="tab-content active" id="tab-att">
            <div class="card">
                <h2 class="card-title">Attendance</h2>
                <?php if (empty($attendance)): ?>
                    <div class="empty-state"><i class="fa fa-calendar-check-o"></i><p>No attendance data.</p></div>
                <?php else: ?>
                    <table class="data-table"><thead><tr><th>Course</th><th>Total</th><th>Attended</th><th>%</th></tr></thead><tbody>
                    <?php foreach ($attendance as $a): ?>
                    <tr><td><strong><?php echo htmlspecialchars($a['course_code']); ?></strong><br><small><?php echo htmlspecialchars($a['course_name']); ?></small></td><td><?php echo $a['total_classes']; ?></td><td><?php echo $a['attended']; ?></td><td><strong><?php echo number_format($a['percentage'],1); ?>%</strong></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content" id="tab-marks">
            <div class="card">
                <h2 class="card-title">Marks</h2>
                <?php if (empty($marks)): ?>
                    <div class="empty-state"><i class="fa fa-bar-chart"></i><p>No marks data.</p></div>
                <?php else: ?>
                    <table class="data-table"><thead><tr><th>Course</th><th>Internal</th><th>Eval Weightage</th><th>Percentage</th><th>External</th><th>Total</th><th>Grade</th></tr></thead><tbody>
                    <?php foreach ($marks as $m): 
                        $stmt_eval = $pdo->prepare("SELECT SUM(weightage) as total_weightage FROM course_evaluation_components WHERE course_code = ?");
                        $stmt_eval->execute([$m['course_code']]);
                        $eval = $stmt_eval->fetch();
                        $total_weightage = $eval['total_weightage'] ?? 0;
                        $pct = $total_weightage > 0 ? ($m['internal'] / $total_weightage) * 100 : 0;
                        $ext_display = (!empty($m['grade']) && $m['external'] > 0) ? number_format($m['external'], 2) : '-';
                        $total_val = $m['internal'] + ((!empty($m['grade']) && $m['external'] > 0) ? $m['external'] : 0);
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($m['course_code']); ?></strong><br><small><?php echo htmlspecialchars($m['course_name']); ?></small></td>
                        <td><strong style="color:#c2185b;"><?php echo number_format($m['internal'], 2); ?></strong></td>
                        <td><strong><?php echo number_format($total_weightage, 2); ?></strong></td>
                        <td><strong style="color:#27ae60;"><?php echo number_format($pct, 2); ?>%</strong></td>
                        <td><?php echo $ext_display; ?></td>
                        <td><strong><?php echo number_format($total_val, 2); ?></strong></td>
                        <td><?php if(!empty($m['grade']) && $m['external'] > 0): ?><span class="badge badge-approved"><?php echo htmlspecialchars($m['grade']); ?></span><?php else: ?><span style="color:#999;">-</span><?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content" id="tab-leaves">
            <div class="card">
                <h2 class="card-title">Leaves</h2>
                <?php if (empty($leaves)): ?>
                    <div class="empty-state"><i class="fa fa-calendar"></i><p>No leave records.</p></div>
                <?php else: ?>
                    <table class="data-table"><thead><tr><th>Type</th><th>From</th><th>To</th><th>Reason</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($leaves as $l): ?>
                    <tr><td><?php echo htmlspecialchars($l['leave_type']); ?></td><td><?php echo date('d M Y', strtotime($l['from_date'])); ?></td><td><?php echo date('d M Y', strtotime($l['to_date'])); ?></td><td><?php echo htmlspecialchars($l['reason']); ?></td><td><span class="badge badge-<?php echo strtolower($l['status']); ?>"><?php echo $l['status']; ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content" id="tab-gp">
            <div class="card">
                <h2 class="card-title">Gate Passes</h2>
                <?php if (empty($gatepasses)): ?>
                    <div class="empty-state"><i class="fa fa-ticket"></i><p>No gate passes.</p></div>
                <?php else: ?>
                    <table class="data-table"><thead><tr><th>Reason</th><th>From</th><th>To</th><th>Urgency</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($gatepasses as $g): ?>
                    <tr><td><?php echo htmlspecialchars($g['reason']); ?></td><td><?php echo date('d M Y', strtotime($g['from_date'])); ?></td><td><?php echo date('d M Y', strtotime($g['to_date'])); ?></td><td><?php echo $g['urgency'] ?? 'Normal'; ?></td><td><span class="badge badge-<?php echo strtolower($g['status']); ?>"><?php echo $g['status']; ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');
    }
    </script>
</body>
</html>
