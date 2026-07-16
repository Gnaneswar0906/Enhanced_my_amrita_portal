<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_seating') {
        $sid = intval($_POST['student_id'] ?? 0);
        $exam = trim($_POST['exam_name'] ?? '');
        $code = trim($_POST['course_code'] ?? '');
        $date = $_POST['exam_date'] ?? '';
        $hall = trim($_POST['hall_name'] ?? '');
        $seat = trim($_POST['seat_number'] ?? '');
        $floor = trim($_POST['floor'] ?? '');
        $block = trim($_POST['block'] ?? '');
        if ($sid && $exam && $date) {
            $pdo->prepare('INSERT INTO seating_arrangements (student_id, exam_name, course_code, exam_date, hall_name, seat_number, floor, block) VALUES (?,?,?,?,?,?,?,?)')->execute([$sid,$exam,$code,$date,$hall,$seat,$floor,$block]);
            $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?, ?, ?, "seating")')->execute([
                $sid, 'Seating Arrangement Published', 'Your seating for "' . $exam . '" on ' . $date . ': ' . $hall . ', Seat ' . $seat . '.'
            ]);
            $msg = 'add_success';
        }
    } elseif ($action === 'delete_seating') {
        $sid2 = intval($_POST['seating_id'] ?? 0);
        if ($sid2) { $pdo->prepare('DELETE FROM seating_arrangements WHERE id = ?')->execute([$sid2]); $msg = 'delete_success'; }
    }
}

$students = $pdo->query('SELECT id, name, enrollment_no FROM students ORDER BY name')->fetchAll();
$seats = $pdo->query('SELECT sa.*, s.name as student_name, s.enrollment_no FROM seating_arrangements sa JOIN students s ON sa.student_id = s.id ORDER BY sa.exam_date')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita Admin - Seating</title>
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
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Seating</div>
    <div class="main-content">
        <div class="page-header"><h1><i class="fa fa-th"></i> Manage Seating Arrangements</h1><a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a></div>
        <?php if ($msg === 'add_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Seating added & student notified!</div>
        <?php elseif ($msg === 'delete_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Entry deleted.</div><?php endif; ?>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Add Seating Entry</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_seating">
                <div class="form-row">
                    <div class="form-group"><label>Student</label>
                        <select class="form-control" name="student_id" required><option value="">-- Select --</option>
                        <?php foreach ($students as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name'].' ('.$s['enrollment_no'].')'); ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="form-group"><label>Exam Name</label><input type="text" class="form-control" name="exam_name" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Course Code</label><input type="text" class="form-control" name="course_code"></div>
                    <div class="form-group"><label>Exam Date</label><input type="date" class="form-control" name="exam_date" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Hall Name</label><input type="text" class="form-control" name="hall_name"></div>
                    <div class="form-group"><label>Seat Number</label><input type="text" class="form-control" name="seat_number"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Floor</label><input type="text" class="form-control" name="floor"></div>
                    <div class="form-group"><label>Block</label><input type="text" class="form-control" name="block"></div>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-plus"></i> Add Seating</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">All Seating (<?php echo count($seats); ?>)</h2>
            <?php if (empty($seats)): ?><div class="empty-state"><i class="fa fa-th"></i><p>No seating data.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Exam</th><th>Date</th><th>Hall</th><th>Seat</th><th>Floor/Block</th><th>Del</th></tr></thead>
                <tbody>
                <?php foreach ($seats as $se): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($se['student_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($se['exam_name']); ?></td>
                    <td><?php echo date('d M Y', strtotime($se['exam_date'])); ?></td>
                    <td><?php echo htmlspecialchars($se['hall_name']); ?></td>
                    <td><strong><?php echo htmlspecialchars($se['seat_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($se['floor'].' / '.$se['block']); ?></td>
                    <td><form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete_seating"><input type="hidden" name="seating_id" value="<?php echo $se['id']; ?>"><button type="submit" class="delete-btn"><i class="fa fa-trash"></i></button></form></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
