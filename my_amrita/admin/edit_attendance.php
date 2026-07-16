<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

$selected_course = $_GET['course'] ?? '';
$selected_student = intval($_GET['student'] ?? 0);

// Handle edit request approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'resolve_request') {
        $rid = intval($_POST['request_id']);
        $status = $_POST['status'];
        $note = trim($_POST['admin_note'] ?? '');
        $pdo->prepare("UPDATE attendance_edit_requests SET status=?, admin_note=?, resolved_at=NOW() WHERE id=?")->execute([$status, $note, $rid]);
        $msg = 'request_resolved';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_attendance') {
        $aid = intval($_POST['att_id']);
        $total = intval($_POST['total_classes']);
        $attended = intval($_POST['attended']);
        $duty = intval($_POST['duty_leave']);
        $medical = intval($_POST['medical_leave']);
        $pct = $total > 0 ? round(($attended/$total)*100, 2) : 0;
        $pdo->prepare("UPDATE attendance SET total_classes=?, attended=?, percentage=?, duty_leave=?, medical_leave=? WHERE id=?")->execute([$total, $attended, $pct, $duty, $medical, $aid]);
        $msg = 'updated';
    }
}

$table_alias = 's';
require_once 'filter_logic.php';

// Get all courses for this cohort
$courses_sql = "SELECT DISTINCT a.course_code, a.course_name 
                FROM attendance a 
                JOIN students s ON a.student_id = s.id 
                WHERE 1=1 " . $filter_sql . " ORDER BY a.course_code";
$stmt = $pdo->prepare($courses_sql);
$stmt->execute($filter_params);
$all_courses = $stmt->fetchAll();

// Get students for selected course
$students = [];
if ($selected_course) {
    $s_sql = "SELECT a.*, s.name, s.enrollment_no, s.section 
              FROM attendance a 
              JOIN students s ON a.student_id = s.id 
              WHERE a.course_code = ? " . $filter_sql . " 
              ORDER BY s.enrollment_no";
    $s_params = array_merge([$selected_course], $filter_params);
    $stmt = $pdo->prepare($s_sql);
    $stmt->execute($s_params);
    $students = $stmt->fetchAll();
}

// Get pending edit requests
$requests = $pdo->query("SELECT er.*, s.name as student_name, s.enrollment_no FROM attendance_edit_requests er LEFT JOIN students s ON er.student_id = s.id ORDER BY er.status ASC, er.created_at DESC LIMIT 20")->fetchAll();
$pending_count = 0;
foreach ($requests as $r) if ($r['status'] === 'Pending') $pending_count++;
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Admin - Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
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
        .course-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;}
        .course-pill{display:block;padding:12px 16px;border:1px solid #e0e0e0;border-radius:8px;text-decoration:none;color:#333;transition:.2s;border-left:4px solid #a4123f;}
        .course-pill:hover{border-color:#a4123f;background:#fef5f7;}.course-pill.active{background:#f0fff4;border-left-color:#27ae60;}
        .data-table{width:100%;border-collapse:collapse;font-size:13px;}
        .data-table th{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;}
        .data-table td{padding:8px 12px;border-bottom:1px solid #eee;text-align:center;}
        .data-table tbody tr:hover{background:#f8f9fa;}
        .edit-inline{width:60px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:12px;text-align:center;font-family:'Inter',sans-serif;}
        .save-btn{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;border:none;padding:4px 10px;border-radius:4px;font-size:11px;cursor:pointer;font-weight:600;}
        .msg-success{background:#e8f5e9;border:1px solid #a5d6a7;padding:12px 16px;border-radius:8px;color:#2e7d32;font-size:13px;margin-bottom:16px;}
        .req-card{padding:14px;border:1px solid #f0d060;background:#fff8e1;border-radius:8px;margin-bottom:10px;}
        .req-card.approved{border-color:#a5d6a7;background:#f0fff4;}.req-card.rejected{border-color:#ef9a9a;background:#fff5f5;}
        .badge-pending{background:#fff3cd;color:#856404;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;}
        .badge-approved{background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;}
        .badge-rejected{background:#fde8e8;color:#e74c3c;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:600;}
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel</span><div style="display:flex;align-items:center;gap:18px;"><span style="font-size:13px;opacity:.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Home</a> <span>/</span> Attendance Management</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar-check-o"></i> Attendance Management</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <?php if ($msg === 'updated'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Attendance updated!</div>
        <?php elseif ($msg === 'request_resolved'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Edit request resolved.</div><?php endif; ?>

        <!-- Pending Edit Requests -->
        <?php if ($pending_count > 0): ?>
        <div class="card" style="border-left:4px solid #f39c12;">
            <h2 class="card-title"><i class="fa fa-key" style="color:#f39c12;"></i> Teacher Edit Requests <span class="badge-pending"><?php echo $pending_count; ?> pending</span></h2>
            <?php foreach ($requests as $r): if ($r['status'] !== 'Pending') continue; ?>
            <div class="req-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <strong><?php echo htmlspecialchars($r['teacher_name']); ?></strong>
                        <span style="font-size:12px;color:#888;">wants to edit <?php echo htmlspecialchars($r['course_code']); ?> on <?php echo $r['record_date']; ?></span>
                        <div style="font-size:13px;color:#555;margin-top:4px;"><?php echo htmlspecialchars($r['reason']); ?></div>
                    </div>
                </div>
                <form method="POST" style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="action" value="resolve_request">
                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                    <input type="text" name="admin_note" placeholder="Note..." class="edit-inline" style="width:200px;">
                    <button type="submit" name="status" value="Approved" style="background:#27ae60;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;"><i class="fa fa-check"></i> Approve</button>
                    <button type="submit" name="status" value="Rejected" style="background:#e74c3c;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;"><i class="fa fa-times"></i> Reject</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php 
        $filter_count = count($all_courses);
        include 'filter_ui.php'; 
        ?>

        <!-- Branch/Course Selection -->
        <div class="card">
            <h2 class="card-title"><i class="fa fa-sitemap" style="color:#a4123f;"></i> Select Course for this Cohort</h2>
            <p style="font-size:12px;color:#888;margin-bottom:12px;">Click a course below to view/edit student attendance for the selected batch/branch/section/sem.</p>
            <div class="course-grid">
                <?php if (empty($all_courses)): ?>
                    <div style="font-size:12px; color:#888;">No courses found for this cohort. Adjust filters.</div>
                <?php else: ?>
                    <?php foreach ($all_courses as $c): ?>
                    <a href="edit_attendance.php?course=<?php echo urlencode($c['course_code']); ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="course-pill <?php echo $selected_course===$c['course_code']?'active':''; ?>">
                        <div style="font-size:11px;color:#a4123f;font-weight:700;"><?php echo htmlspecialchars($c['course_code']); ?></div>
                        <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($c['course_name']); ?></div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_course && !empty($students)): ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2 class="card-title" style="margin:0;"><i class="fa fa-users" style="color:#a4123f;"></i> <?php echo htmlspecialchars($selected_course); ?> — All Students</h2>
                <a href="edit_daily_attendance.php?course=<?php echo urlencode($selected_course); ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="save-btn" style="text-decoration:none; padding:8px 16px; display:inline-block;"><i class="fa fa-calendar"></i> Edit Daily Attendance</a>
            </div>
            <table class="data-table">
                <thead><tr><th>#</th><th style="text-align:left;">Student</th><th>Enrollment</th><th>Total</th><th>Present</th><th>Duty Leave</th><th>Absent</th><th>%</th><th>Medical</th><th>Save</th></tr></thead>
                <tbody>
                <?php foreach ($students as $si => $s):
                    $absent = $s['total_classes'] - $s['attended'];
                    $pct = $s['percentage'];
                    $pct_style = $pct >= 90 ? 'color:#27ae60;' : ($pct >= 75 ? 'color:#f39c12;' : 'color:#fff;background:#e74c3c;padding:2px 8px;border-radius:4px;');
                ?>
                <form method="POST">
                <input type="hidden" name="action" value="update_attendance">
                <input type="hidden" name="att_id" value="<?php echo $s['id']; ?>">
                <tr>
                    <td><?php echo $si+1; ?></td>
                    <td style="text-align:left;"><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                    <td><code style="font-size:11px;"><?php echo htmlspecialchars($s['enrollment_no']); ?></code></td>
                    <td><input type="number" name="total_classes" value="<?php echo $s['total_classes']; ?>" class="edit-inline"></td>
                    <td><input type="number" name="attended" value="<?php echo $s['attended']; ?>" class="edit-inline"></td>
                    <td><input type="number" name="duty_leave" value="<?php echo $s['duty_leave'] ?? 0; ?>" class="edit-inline"></td>
                    <td><strong style="color:#e74c3c;"><?php echo $absent; ?></strong></td>
                    <td><strong style="<?php echo $pct_style; ?>;font-weight:700;"><?php echo number_format($pct,2); ?></strong></td>
                    <td><input type="number" name="medical_leave" value="<?php echo $s['medical_leave'] ?? 0; ?>" class="edit-inline"></td>
                    <td><button type="submit" class="save-btn"><i class="fa fa-save"></i></button></td>
                </tr>
                </form>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
