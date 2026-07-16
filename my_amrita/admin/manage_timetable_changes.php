<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_change') {
        $course = trim($_POST['course']);
        $cparts = explode('||', $course);
        $ccode = $cparts[0] ?? '';
        $cname = $cparts[1] ?? '';
        
        $type = $_POST['change_type'];
        $date = $_POST['effective_date'];
        $day = date('l', strtotime($date));
        $old = trim($_POST['old_value']);
        $new = trim($_POST['new_value']);
        
        if ($ccode && $type && $date) {
            $stmt = $pdo->prepare("INSERT INTO timetable_changes (student_id, course_code, course_name, change_type, old_value, new_value, effective_date, day_name) VALUES (0, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ccode, $cname, $type, $old, $new, $date, $day]);
            $msg = 'added';
        }
    } elseif ($_POST['action'] === 'delete_change') {
        $id = intval($_POST['change_id']);
        $pdo->prepare("DELETE FROM timetable_changes WHERE id = ?")->execute([$id]);
        $msg = 'deleted';
    }
}

// Fetch all changes
$stmt = $pdo->query("SELECT * FROM timetable_changes WHERE student_id = 0 ORDER BY effective_date DESC, id DESC");
$changes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses for dropdown
$stmt2 = $pdo->query("SELECT DISTINCT course_code, course_name FROM courses ORDER BY course_code");
$courses = $stmt2->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>Manage Timetable Changes - Admin Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        .data-table th { background: #f9f9f9; font-weight: 600; color: #555; text-transform: uppercase; font-size: 11px; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #666; }
        .form-control { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-family: 'Inter', sans-serif; font-size: 13px; }
        .btn-submit { background: #1a5276; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #154360; }
        .btn-delete { background: #e74c3c; color: #fff; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-room { background: #e8f4f8; color: #1a5276; }
        .badge-time { background: #fef5e7; color: #d35400; }
        .badge-cancel { background: #fde8e8; color: #c0392b; }
        .badge-extra { background: #e8f5e9; color: #27ae60; }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Admin Portal</span>
        <div class="nav-links">
            <span><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Home</a> <span class="sep">/</span> Manage Timetable Changes</div>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-clock-o"></i> Global Timetable Changes</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($msg === 'added'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Timetable change published to all students!</div>
        <?php elseif ($msg === 'deleted'): ?><div class="msg-success"><i class="fa fa-trash"></i> Change removed.</div><?php endif; ?>

        <div class="card">
            <h3><i class="fa fa-plus-circle"></i> Add New Change</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_change">
                <div class="form-row">
                    <div class="form-group">
                        <label>Course</label>
                        <select name="course" class="form-control" required>
                            <option value="">Select Course...</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['course_code'] . '||' . $c['course_name']); ?>"><?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Change Type</label>
                        <select name="change_type" class="form-control" required>
                            <option value="Room Change">Room Change</option>
                            <option value="Time Change">Time Change (Period)</option>
                            <option value="Cancelled">Class Cancelled</option>
                            <option value="Extra Class">Extra Class</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Original Details (e.g. "Period 3", "Room 402")</label>
                        <input type="text" name="old_value" class="form-control" placeholder="Original Schedule">
                    </div>
                    <div class="form-group">
                        <label>New Details (e.g. "Period 5", "Room 301", "N/A")</label>
                        <input type="text" name="new_value" class="form-control" placeholder="New Schedule">
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fa fa-bullhorn"></i> Publish Change</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="fa fa-list"></i> Active Changes</h3>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Course</th><th>Type</th><th>Original</th><th>New</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (empty($changes)): ?><tr><td colspan="6" style="text-align:center; color:#888;">No global changes posted.</td></tr><?php endif; ?>
                    <?php foreach ($changes as $ch): 
                        $badge = 'badge-room';
                        if ($ch['change_type'] === 'Cancelled') $badge = 'badge-cancel';
                        if ($ch['change_type'] === 'Extra Class') $badge = 'badge-extra';
                        if ($ch['change_type'] === 'Time Change') $badge = 'badge-time';
                    ?>
                    <tr>
                        <td><strong><?php echo date('d M Y', strtotime($ch['effective_date'])); ?></strong><br><small style="color:#888;"><?php echo $ch['day_name']; ?></small></td>
                        <td><strong><?php echo htmlspecialchars($ch['course_code']); ?></strong><br><small style="color:#555;"><?php echo htmlspecialchars($ch['course_name']); ?></small></td>
                        <td><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($ch['change_type']); ?></span></td>
                        <td style="color:#666;"><del><?php echo htmlspecialchars($ch['old_value']); ?></del></td>
                        <td style="color:#27ae60; font-weight:600;"><?php echo htmlspecialchars($ch['new_value']); ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete this change?');">
                                <input type="hidden" name="action" value="delete_change">
                                <input type="hidden" name="change_id" value="<?php echo $ch['id']; ?>">
                                <button type="submit" class="btn-delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
