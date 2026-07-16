<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

$selected_course = $_GET['course'] ?? '';
$mark_date = $_GET['date'] ?? date('Y-m-d');

$table_alias = 's';
require_once 'filter_logic.php';

// Check if course is a lab
$is_lab = false;
if ($selected_course) {
    $lab_check = $pdo->prepare("SELECT COUNT(*) FROM timetable WHERE course_code = ? AND course_name LIKE '%Lab%' LIMIT 1");
    $lab_check->execute([$selected_course]);
    $is_lab = $lab_check->fetchColumn() > 0;
}

// Get period from timetable for today
$today_day = date('l', strtotime($mark_date));
$periods_today = [];
if ($selected_course) {
    $ps = $pdo->prepare("SELECT DISTINCT time_slot FROM timetable WHERE course_code = ? AND day_name = ? LIMIT 4");
    $ps->execute([$selected_course, $today_day]);
    $periods_today = $ps->fetchAll(PDO::FETCH_COLUMN);
}

// Handle marking attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_attendance') {
    $date = $_POST['mark_date'];
    $course = $_POST['course_code'];
    $start_period = intval($_POST['period_number']);
    $num_periods = intval($_POST['num_periods'] ?? 1);
    $statuses = $_POST['status'] ?? [];
    
    foreach ($statuses as $sid => $status) {
        // Loop for number of periods
        for ($p = 0; $p < $num_periods; $p++) {
            $current_period = $start_period + $p;
            // Delete existing for same date/course/period
            $pdo->prepare("DELETE FROM attendance_records WHERE student_id=? AND course_code=? AND date=? AND period_number=?")->execute([$sid, $course, $date, $current_period]);
            $pdo->prepare("INSERT INTO attendance_records (student_id, course_code, date, status, period_number, marked_by, is_lab) VALUES (?,?,?,?,?,?,?)")
                ->execute([$sid, $course, $date, $status, $current_period, $admin_name . ' (Admin)', $is_lab ? 1 : 0]);
        }
        
        if ($is_lab && $num_periods == 1) {
            $pdo->prepare("DELETE FROM attendance_records WHERE student_id=? AND course_code=? AND date=? AND period_number=?")->execute([$sid, $course, $date, $start_period+1]);
            $pdo->prepare("INSERT INTO attendance_records (student_id, course_code, date, status, period_number, marked_by, is_lab) VALUES (?,?,?,?,?,?,?)")
                ->execute([$sid, $course, $date, $status, $start_period+1, $admin_name . ' (Admin)', 1]);
        }
    }
    // Recalculate attendance summary
    foreach ($statuses as $sid => $status) {
        $total = $pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE student_id=? AND course_code=?");
        $total->execute([$sid, $course]); $t = $total->fetchColumn();
        $present = $pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE student_id=? AND course_code=? AND status='Present'");
        $present->execute([$sid, $course]); $p = $present->fetchColumn();
        $pct = $t > 0 ? round(($p/$t)*100, 2) : 0;
        $pdo->prepare("UPDATE attendance SET total_classes=?, attended=?, percentage=? WHERE student_id=? AND course_code=?")
            ->execute([$t, $p, $pct, $sid, $course]);
    }
    $msg = 'marked';
}

$students = [];
if ($selected_course) {
    // Add cohort filters to students fetch
    $stmt = $pdo->prepare("SELECT DISTINCT s.id, s.name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
                           FROM students s JOIN attendance a ON s.id = a.student_id 
                           WHERE a.course_code = ? " . $filter_sql . " 
                           ORDER BY s.enrollment_no");
    $params = array_merge([$selected_course], $filter_params);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    // Get existing records for selected date
    $existing = [];
    if (!empty($students)) {
        $ex = $pdo->prepare("SELECT student_id, status, period_number FROM attendance_records WHERE course_code = ? AND date = ?");
        $ex->execute([$selected_course, $mark_date]);
        foreach ($ex->fetchAll() as $r) $existing[$r['student_id'].'_'.$r['period_number']] = $r['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Admin - Daily Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;background:#f5f5f5;color:#333;}
        .top-navbar{background:linear-gradient(135deg,#a4123f,#c2185b);color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 8px rgba(0,0,0,.15);position:sticky;top:0;z-index:1000;}
        .top-navbar .brand{font-size:18px;font-weight:600;}.logout-btn{background:none;border:1px solid rgba(255,255,255,.4);color:#fff;padding:5px 14px;border-radius:6px;font-size:12px;text-decoration:none;}
        .breadcrumb-bar{background:#fff;padding:8px 20px;font-size:13px;color:#888;border-bottom:1px solid #e0e0e0;}.breadcrumb-bar a{color:#a4123f;text-decoration:none;}
        .main-content{max-width:1200px;margin:0 auto;padding:20px;}
        .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}.page-header h1{font-size:22px;color:#a4123f;margin:0;}
        .back-btn{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;padding:8px 16px;border-radius:8px;font-size:12px;text-decoration:none;font-weight:600;}
        .card{background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:24px;margin-bottom:20px;}
        .card-title{font-size:16px;font-weight:700;color:#333;margin:0 0 16px;}
        .mark-table{width:100%;border-collapse:collapse;font-size:13px;}
        .mark-table th{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;}
        .mark-table td{padding:10px 12px;border-bottom:1px solid #eee;text-align:center;}
        .mark-table tbody tr:hover{background:#f8f9fa;}
        .radio-p,.radio-a{appearance:none;width:20px;height:20px;border:2px solid #ccc;border-radius:50%;cursor:pointer;transition:.2s;}
        .radio-p:checked{background:#27ae60;border-color:#27ae60;box-shadow:inset 0 0 0 3px #fff;}
        .radio-a:checked{background:#e74c3c;border-color:#e74c3c;box-shadow:inset 0 0 0 3px #fff;}
        .date-input{padding:8px 14px;border:1px solid #e0e0e0;border-radius:8px;font-size:14px;font-family:'Inter',sans-serif;}
        .submit-btn{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:.2s;}
        .submit-btn:hover{box-shadow:0 4px 12px rgba(164,18,63,.2);}
        .msg-success{background:#e8f5e9;border:1px solid #a5d6a7;padding:12px 16px;border-radius:8px;color:#2e7d32;font-size:13px;margin-bottom:16px;}
        .mark-all-btn{padding:4px 12px;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;}
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel</span><div style="display:flex;align-items:center;gap:18px;"><span style="font-size:13px;opacity:.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Home</a> <span>/</span> <a href="edit_attendance.php">Attendance</a> <span>/</span> Daily Editor</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar"></i> Daily Attendance Editor</h1>
            <a href="edit_attendance.php?course=<?php echo urlencode($selected_course); ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Summary</a>
        </div>

        <?php if ($msg === 'marked'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Daily Attendance saved and overall summary recalculated successfully!</div><?php endif; ?>

        <?php if ($selected_course && !empty($students)): ?>
        <div class="card" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div>
                <label style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;display:block;">Date</label>
                <input type="date" class="date-input" value="<?php echo $mark_date; ?>" onchange="window.location.href='edit_daily_attendance.php?course=<?php echo urlencode($selected_course); ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>&date='+this.value">
            </div>
            <?php if ($is_lab): ?><span style="background:#f3e5f5;color:#7b1fa2;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;"><i class="fa fa-flask"></i> LAB</span><?php endif; ?>
            <?php if (!empty($periods_today)): ?>
            <div style="font-size:12px;color:#666;"><i class="fa fa-clock-o" style="color:#a4123f;"></i> Slots: <?php echo implode(', ', $periods_today); ?></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-title" style="margin-bottom:15px;"><i class="fa fa-users" style="color:#a4123f;"></i> <?php echo htmlspecialchars($selected_course); ?> — <?php echo date('d M Y (l)', strtotime($mark_date)); ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="mark_attendance">
                <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($selected_course); ?>">
                <input type="hidden" name="mark_date" value="<?php echo $mark_date; ?>">
                <div style="margin-bottom:12px;display:flex;gap:10px;align-items:center;">
                    <label style="font-size:12px;font-weight:600;color:#888;">Start Period:</label>
                    <select name="period_number" class="date-input" style="padding:6px 12px;">
                        <?php for ($p = 1; $p <= 8; $p++): ?>
                        <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                        <?php endfor; ?>
                    </select>
                    <label style="font-size:12px;font-weight:600;color:#888;margin-left:10px;">Number of Periods:</label>
                    <input type="number" name="num_periods" value="1" min="1" max="8" class="date-input" style="padding:6px 12px; width:60px;">
                    <button type="button" class="mark-all-btn" style="background:#e8f5e9;color:#27ae60;margin-left:auto;" onclick="document.querySelectorAll('.radio-p').forEach(r=>r.checked=true)">✓ All Present</button>
                    <button type="button" class="mark-all-btn" style="background:#fde8e8;color:#e74c3c;" onclick="document.querySelectorAll('.radio-a').forEach(r=>r.checked=true)">✗ All Absent</button>
                </div>
                <table class="mark-table">
                    <thead><tr><th>#</th><th style="text-align:left;">Student Name</th><th>Registration No.</th><th>Present</th><th>Absent</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $si => $s):
                        $ex_key = $s['id'].'_1';
                        $ex_status = $existing[$ex_key] ?? '';
                    ?>
                    <tr>
                        <td><strong><?php echo $si+1; ?></strong></td>
                        <td style="text-align:left;">
                            <strong style="font-weight:600;"><?php echo htmlspecialchars($s['name']); ?></strong><br>
                            <span style="font-size:10px; color:#888;"><?php echo htmlspecialchars($s['batch'] . ' | ' . $s['branch'] . ' | Sec ' . $s['section'] . ' | Sem ' . $s['current_sem']); ?></span>
                        </td>
                        <td><code style="font-size:11px;"><?php echo htmlspecialchars($s['enrollment_no']); ?></code></td>
                        <td><input type="radio" name="status[<?php echo $s['id']; ?>]" value="Present" class="radio-p" <?php echo ($ex_status==='Present' || !$ex_status)?'checked':''; ?>></td>
                        <td><input type="radio" name="status[<?php echo $s['id']; ?>]" value="Absent" class="radio-a" <?php echo $ex_status==='Absent'?'checked':''; ?>></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="submit-btn" style="width:100%;padding:14px;margin-top:16px;"><i class="fa fa-save"></i> Save Daily Attendance</button>
            </form>
        </div>
        <?php else: ?>
        <div class="card"><div style="padding:20px;text-align:center;color:#888;"><i class="fa fa-exclamation-circle" style="font-size:24px;margin-bottom:10px;"></i><br>No course selected or no students found.</div></div>
        <?php endif; ?>
    </div>
</body>
</html>
