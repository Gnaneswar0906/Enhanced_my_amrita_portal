<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$warden_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_attendance') {
        $date = $_POST['attendance_date'] ?? date('Y-m-d');
        $students_data = $_POST['status'] ?? [];
        foreach ($students_data as $sid => $status) {
            $exists = $pdo->prepare('SELECT id FROM hostel_attendance WHERE student_id=? AND attendance_date=?');
            $exists->execute([$sid, $date]);
            if ($exists->fetch()) {
                $pdo->prepare('UPDATE hostel_attendance SET status=?, marked_by=? WHERE student_id=? AND attendance_date=?')->execute([$status, $warden_name, $sid, $date]);
            } else {
                $pdo->prepare('INSERT INTO hostel_attendance (student_id, attendance_date, status, marked_by) VALUES (?,?,?,?)')->execute([$sid, $date, $status, $warden_name]);
            }
        }
        $msg = 'success';
    }
}

$filter_block = $_GET['block'] ?? '';
$filter_date = $_GET['date'] ?? date('Y-m-d');

$query = 'SELECT s.id, s.name, s.enrollment_no, s.hostel_block, s.hostel_room, ha.status as att_status FROM students s LEFT JOIN hostel_attendance ha ON s.id = ha.student_id AND ha.attendance_date = ? WHERE s.hostel_room IS NOT NULL AND s.warden_name = ?';
$params = [$filter_date, $warden_name];
if ($filter_block) { $query .= ' AND s.hostel_block = ?'; $params[] = $filter_block; }
$query .= ' ORDER BY s.hostel_block, s.hostel_room, s.name';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$blocks_stmt = $pdo->prepare('SELECT DISTINCT hostel_block FROM students WHERE hostel_block IS NOT NULL AND warden_name = ? ORDER BY hostel_block');
$blocks_stmt->execute([$warden_name]);
$blocks = $blocks_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Warden - Hostel Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Warden Portal</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($warden_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Warden Home</a> <span class="sep">/</span> Hostel Attendance</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar-check-o"></i> Hostel Attendance</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <?php if ($msg === 'success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Attendance marked successfully!</div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card">
            <form method="GET" style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
                <div class="form-group" style="flex:1; min-width:150px;">
                    <label>Date</label>
                    <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="form-group" style="flex:1; min-width:150px;">
                    <label>Block</label>
                    <select class="form-control" name="block">
                        <option value="">All Blocks</option>
                        <?php foreach ($blocks as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $filter_block === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="submit-btn" style="height:42px;"><i class="fa fa-filter"></i> Filter</button>
            </form>
        </div>

        <!-- Mark Attendance -->
        <div class="card">
            <h2 class="card-title">Mark Attendance – <?php echo date('d M Y', strtotime($filter_date)); ?> (<?php echo count($students); ?> students)</h2>
            <?php if (empty($students)): ?>
                <div class="empty-state"><i class="fa fa-building"></i><p>No hostel students found.</p></div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_attendance">
                    <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Student</th><th>Block / Room</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $i => $s): ?>
                            <tr>
                                <td><?php echo $i+1; ?></td>
                                <td><strong><?php echo htmlspecialchars($s['name']); ?></strong><br><small><?php echo htmlspecialchars($s['enrollment_no']); ?></small></td>
                                <td><?php echo htmlspecialchars($s['hostel_room'] ?? '—'); ?></td>
                                <td>
                                    <select name="status[<?php echo $s['id']; ?>]" class="form-control" style="width:auto; display:inline-block; padding:4px 8px; font-size:13px;">
                                        <option value="Present" <?php echo ($s['att_status'] ?? '') === 'Present' ? 'selected' : ''; ?>>Present</option>
                                        <option value="Absent" <?php echo ($s['att_status'] ?? '') === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                        <option value="Late" <?php echo ($s['att_status'] ?? '') === 'Late' ? 'selected' : ''; ?>>Late</option>
                                        <option value="On Leave" <?php echo ($s['att_status'] ?? '') === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="submit-btn" style="margin-top:16px;"><i class="fa fa-save"></i> Save Attendance</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
