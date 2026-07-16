<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];

// Get all feedback with student and faculty info
$filter_course = $_GET['course'] ?? '';
$filter_faculty = $_GET['faculty'] ?? '';

$table_alias = 's';
require_once 'filter_logic.php';

$sql = "SELECT cf.*, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
        FROM course_feedback cf 
        JOIN students s ON cf.student_id = s.id 
        WHERE 1=1 " . $filter_sql;

if ($filter_course) { $sql .= " AND cf.course_code = ?"; $filter_params[] = $filter_course; }
if ($filter_faculty) { $sql .= " AND cf.faculty_name = ?"; $filter_params[] = $filter_faculty; }

$sql .= " ORDER BY cf.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($filter_params);
$feedbacks = $stmt->fetchAll();

$extra_filters = '<label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Course:</label>
    <select name="course" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:\'Inter\',sans-serif;">
        <option value="">All Courses</option>';
$courses = $pdo->query("SELECT DISTINCT course_code, course_name FROM courses ORDER BY course_code")->fetchAll();
foreach ($courses as $c) {
    $sel = ($filter_course == $c['course_code']) ? 'selected' : '';
    $extra_filters .= '<option value="' . htmlspecialchars($c['course_code']) . '" ' . $sel . '>' . htmlspecialchars($c['course_code']) . '</option>';
}
$extra_filters .= '</select>
    <label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Faculty:</label>
    <select name="faculty" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:\'Inter\',sans-serif;">
        <option value="">All Faculty</option>';
$faculties = $pdo->query("SELECT DISTINCT faculty_name FROM course_feedback WHERE faculty_name IS NOT NULL AND faculty_name != '' ORDER BY faculty_name")->fetchAll();
foreach ($faculties as $f) {
    $sel = ($filter_faculty == $f['faculty_name']) ? 'selected' : '';
    $extra_filters .= '<option value="' . htmlspecialchars($f['faculty_name']) . '" ' . $sel . '>' . htmlspecialchars($f['faculty_name']) . '</option>';
}
$extra_filters .= '</select>';

// Get unique courses and faculty for filters
$courses = $pdo->query("SELECT DISTINCT course_code, course_name FROM courses ORDER BY course_code")->fetchAll();
$faculties = $pdo->query("SELECT DISTINCT faculty_name FROM course_feedback WHERE faculty_name IS NOT NULL AND faculty_name != '' ORDER BY faculty_name")->fetchAll();

// Stats
$total = count($feedbacks);
$total_all = $pdo->query("SELECT COUNT(*) FROM course_feedback")->fetchColumn();
$unique_students = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM course_feedback")->fetchColumn();
$unique_courses = $pdo->query("SELECT COUNT(DISTINCT course_code) FROM course_feedback")->fetchColumn();

$detail_id = intval($_GET['detail'] ?? 0);

$questions = [
    'r_content' => "Course Content Quality",
    'r_delivery' => "Teaching & Delivery",
    'r_assessment' => "Assessment & Evaluation",
    'r_resources' => "Learning Resources"
];

// Calculate Averages for the selected course
$course_avgs = null;
if ($filter_course) {
    $avg_stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, AVG(content_rating) as avg_content, AVG(delivery_rating) as avg_delivery, AVG(assessment_rating) as avg_assessment, AVG(resource_rating) as avg_resource FROM course_feedback WHERE course_code = ?");
    $avg_stmt->execute([$filter_course]);
    $course_avgs = $avg_stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Admin - TLP Feedback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;}
        body{margin:0;font-family:'Inter','Segoe UI',sans-serif;background:#f5f5f5;color:#333;}
        .top-navbar{background:linear-gradient(135deg,#a4123f,#c2185b);color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 8px rgba(0,0,0,.15);position:sticky;top:0;z-index:1000;}
        .top-navbar .brand{font-size:18px;font-weight:600;}
        .top-navbar .nav-links{display:flex;align-items:center;gap:18px;}
        .logout-btn{background:none;border:1px solid rgba(255,255,255,.4);color:#fff;padding:5px 14px;border-radius:6px;font-size:12px;text-decoration:none;transition:all .2s;}
        .logout-btn:hover{background:rgba(255,255,255,.15);}
        .breadcrumb-bar{background:#fff;padding:8px 20px;font-size:13px;color:#888;border-bottom:1px solid #e0e0e0;}
        .breadcrumb-bar a{color:#a4123f;text-decoration:none;}
        .main-content{max-width:1200px;margin:0 auto;padding:20px;}
        .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
        .page-header h1{font-size:22px;color:#a4123f;margin:0;}
        .back-btn{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;padding:8px 16px;border-radius:8px;font-size:12px;text-decoration:none;font-weight:600;}
        .card{background:#fff;border:1px solid #e8e8e8;border-radius:12px;padding:24px;margin-bottom:20px;}
        .card-title{font-size:16px;font-weight:700;color:#333;margin:0 0 16px;}
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;}
        .stat-card{background:#fff;border:1px solid #e8e8e8;border-radius:8px;padding:16px;display:flex;align-items:center;gap:12px;}
        .stat-icon{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;background:linear-gradient(135deg,#a4123f,#d4264f);}
        .stat-info .sv{font-size:20px;font-weight:700;color:#333;}
        .stat-info .sl{font-size:10px;color:#888;text-transform:uppercase;letter-spacing:.5px;}
        .filter-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;padding:12px 16px;background:#fff;border:1px solid #e8e8e8;border-radius:10px;}
        .filter-bar select{padding:6px 12px;border:1px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:'Inter',sans-serif;}
        .filter-bar label{font-size:11px;font-weight:600;color:#888;text-transform:uppercase;}
        .data-table{width:100%;border-collapse:collapse;font-size:13px;}
        .data-table th{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;padding:10px 12px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;}
        .data-table td{padding:10px 12px;border-bottom:1px solid #eee;vertical-align:middle;}
        .data-table tr:hover{background:#f8f9fa;}
        .course-chip{display:inline-block;background:#fef5f7;color:#a4123f;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;}
        .answer-row{display:flex;gap:8px;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0f0;}
        .answer-row .aq{font-weight:500;color:#333;font-size:13px;flex:1;}
        .answer-row .aa{font-weight:600;font-size:12px;padding:3px 10px;border-radius:6px;}
        .aa-excellent,.aa-good,.aa-strongly-agree,.aa-agree,.aa-very-satisfied,.aa-satisfied,.aa-below-5{background:#e8f5e9;color:#2e7d32;}
        .aa-average,.aa-neutral,.aa-5---10{background:#fff8e1;color:#e65100;}
        .aa-poor,.aa-disagree,.aa-strongly-disagree,.aa-dissatisfied,.aa-very-dissatisfied,.aa-10---20,.aa-more-than-20{background:#fde8e8;color:#e74c3c;}
        .empty-state{text-align:center;padding:40px;color:#bbb;}
        .empty-state i{font-size:36px;margin-bottom:10px;display:block;}
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Admin Panel</span>
        <div class="nav-links"><span style="font-size:13px;opacity:.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Home</a> <span>/</span> TLP Feedback</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-commenting-o"></i> TLP Feedback – All Responses</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-icon"><i class="fa fa-comments"></i></div><div class="stat-info"><div class="sv"><?php echo $total_all; ?></div><div class="sl">Total Responses</div></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fa fa-users"></i></div><div class="stat-info"><div class="sv"><?php echo $unique_students; ?></div><div class="sl">Students</div></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fa fa-book"></i></div><div class="stat-info"><div class="sv"><?php echo $unique_courses; ?></div><div class="sl">Courses</div></div></div>
        </div>

        <?php if ($detail_id): ?>
        <!-- Detail View -->
        <?php
        $detail = $pdo->prepare("SELECT cf.*, s.name as student_name, s.enrollment_no FROM course_feedback cf JOIN students s ON cf.student_id = s.id WHERE cf.id = ?");
        $detail->execute([$detail_id]);
        $detail = $detail->fetch();
        if ($detail):
            $answers = json_decode($detail['answers_json'] ?? '{}', true) ?: [];
        ?>
        <div class="card">
            <h2 class="card-title"><i class="fa fa-file-text" style="color:#a4123f;"></i> Feedback Detail</h2>
            <div style="margin-bottom:16px;font-size:13px;color:#666;display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                <div><strong>Student:</strong> <?php echo htmlspecialchars($detail['student_name']); ?></div>
                <div><strong>Enrollment:</strong> <?php echo htmlspecialchars($detail['enrollment_no']); ?></div>
                <div><strong>Course:</strong> <span class="course-chip"><?php echo htmlspecialchars($detail['course_code']); ?></span> <?php echo htmlspecialchars($detail['course_name']); ?></div>
                <div><strong>Faculty:</strong> <?php echo htmlspecialchars($detail['faculty_name'] ?? '—'); ?></div>
                <div><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($detail['created_at'])); ?></div>
            </div>
            <?php 
            $q_keys = ['r_content' => 'content_rating', 'r_delivery' => 'delivery_rating', 'r_assessment' => 'assessment_rating', 'r_resources' => 'resource_rating'];
            foreach ($questions as $qk => $qt):
                $ans = $detail[$q_keys[$qk]] ?? 0;
                $cls = ($ans >= 4) ? 'excellent' : (($ans >= 3) ? 'average' : 'poor');
            ?>
            <div class="answer-row">
                <div class="aq"><?php echo $qt; ?></div>
                <div class="aa aa-<?php echo $cls; ?>"><?php echo $ans ? $ans . '/5' : '—'; ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($detail['comments'])): ?>
            <div style="margin-top:16px;padding:14px;background:#f8f9fa;border-radius:8px;">
                <strong style="font-size:12px;color:#888;text-transform:uppercase;">Additional Comments</strong>
                <p style="margin:6px 0 0;font-size:13px;"><?php echo nl2br(htmlspecialchars($detail['comments'])); ?></p>
            </div>
            <?php endif; ?>
            <a href="view_feedback.php<?php echo $filter_course ? '?course='.$filter_course : ''; ?>" style="display:inline-block;margin-top:16px;color:#a4123f;font-weight:600;font-size:13px;text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to list</a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Filters -->
        <?php 
        $filter_count = count($feedbacks);
        include 'filter_ui.php'; 
        ?>

        <?php if ($filter_course && $course_avgs && $course_avgs['avg_rating']): ?>
        <div class="card" style="background:linear-gradient(135deg,#fef5f7,#fff); border-left:4px solid #a4123f;">
            <h2 class="card-title" style="color:#a4123f;"><i class="fa fa-line-chart"></i> Overall Averages for <?php echo htmlspecialchars($filter_course); ?></h2>
            <div class="stats-row" style="margin-bottom:0;">
                <div class="stat-card" style="flex-direction:column; align-items:center; text-align:center; padding:12px;">
                    <div class="sl">Overall Rating</div><div class="sv" style="color:#f5a623; font-size:24px;"><?php echo number_format($course_avgs['avg_rating'],1); ?><span style="font-size:12px;color:#888;">/5</span></div>
                </div>
                <div class="stat-card" style="flex-direction:column; align-items:center; text-align:center; padding:12px;">
                    <div class="sl">Content Quality</div><div class="sv"><?php echo number_format($course_avgs['avg_content'],1); ?></div>
                </div>
                <div class="stat-card" style="flex-direction:column; align-items:center; text-align:center; padding:12px;">
                    <div class="sl">Teaching & Delivery</div><div class="sv"><?php echo number_format($course_avgs['avg_delivery'],1); ?></div>
                </div>
                <div class="stat-card" style="flex-direction:column; align-items:center; text-align:center; padding:12px;">
                    <div class="sl">Assessments</div><div class="sv"><?php echo number_format($course_avgs['avg_assessment'],1); ?></div>
                </div>
                <div class="stat-card" style="flex-direction:column; align-items:center; text-align:center; padding:12px;">
                    <div class="sl">Resources</div><div class="sv"><?php echo number_format($course_avgs['avg_resource'],1); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Feedback Table -->
        <div class="card">
            <h2 class="card-title"><i class="fa fa-list" style="color:#a4123f;"></i> All TLP Feedback Responses</h2>
            <?php if (empty($feedbacks)): ?>
                <div class="empty-state"><i class="fa fa-inbox"></i><p>No feedback found.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>Student</th><th>Enrollment</th><th>Course</th><th>Faculty</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($feedbacks as $fb): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($fb['student_name']); ?></strong></td>
                        <td><code style="font-size:11px;"><?php echo htmlspecialchars($fb['enrollment_no']); ?></code></td>
                        <td><span class="course-chip"><?php echo htmlspecialchars($fb['course_code']); ?></span> <?php echo htmlspecialchars($fb['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($fb['faculty_name'] ?? '—'); ?></td>
                        <td><?php echo date('d M Y', strtotime($fb['created_at'])); ?></td>
                        <td><a href="view_feedback.php?detail=<?php echo $fb['id']; ?>&course=<?php echo urlencode($filter_course); ?>" style="color:#1565c0;font-weight:600;font-size:12px;">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <script>
    // Handled by filter form submit
    </script>
</body>
</html>
