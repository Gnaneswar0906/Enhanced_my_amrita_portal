<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_marks') {
    $mid = intval($_POST['mark_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    $course_code = trim($_POST['course_code'] ?? '');
    
    $stmt_comps = $pdo->prepare("SELECT * FROM course_evaluation_components WHERE course_code = ? ORDER BY id");
    $stmt_comps->execute([$course_code]);
    $course_comps = $stmt_comps->fetchAll();
    
    $total_internal = 0;
    if (!empty($course_comps)) {
        foreach ($course_comps as $comp) {
            $cid = $comp["id"];
            $scored = floatval($_POST["comp_" . $cid] ?? 0);
            
            $chk = $pdo->prepare("SELECT id FROM student_component_marks WHERE student_id=? AND component_id=?");
            $chk->execute([$student_id, $cid]);
            if ($chk->fetch()) {
                $pdo->prepare("UPDATE student_component_marks SET scored_marks=? WHERE student_id=? AND component_id=?")->execute([$scored, $student_id, $cid]);
            } else {
                $pdo->prepare("INSERT INTO student_component_marks (student_id, component_id, scored_marks) VALUES (?,?,?)")->execute([$student_id, $cid, $scored]);
            }
            if ($comp["max_marks"] > 0) {
                $total_internal += ($scored / $comp["max_marks"]) * $comp["weightage"];
            }
        }
    } else {
        $a1 = floatval($_POST["assignment1"] ?? 0);
        $a2 = floatval($_POST["assignment2"] ?? 0);
        $q1 = floatval($_POST["quiz1"] ?? 0);
        $q2 = floatval($_POST["quiz2"] ?? 0);
        $midterm = floatval($_POST["midterm"] ?? 0);
        $total_internal = $a1 + $a2 + $q1 + $q2 + $midterm;
    }

    $ext = floatval($_POST["external"] ?? 0);
    $grade = trim($_POST["grade"] ?? "");
    $total = $total_internal + $ext;
    if ($ext == 0) { $grade = ""; }
    
    if ($mid) {
        $pdo->prepare("UPDATE marks SET internal=?, external=?, total=?, grade=? WHERE id=?")->execute([$total_internal, $ext, $total, $grade, $mid]);
        $msg = 'update_success';
    }
}

$table_alias = 's';
require_once 'filter_logic.php';

$selected_course = $_GET['course'] ?? '';

// Get all distinct courses for this cohort from marks table
$courses_sql = "SELECT DISTINCT m.course_code, m.course_name 
                FROM marks m 
                JOIN students s ON m.student_id = s.id 
                WHERE 1=1 " . $filter_sql . " ORDER BY m.course_code";
$stmt = $pdo->prepare($courses_sql);
$stmt->execute($filter_params);
$all_courses = $stmt->fetchAll();

// Get students for selected course
$dynamic_comps = [];
$marks = [];
if ($selected_course) {
    $stmt_comps = $pdo->prepare("SELECT * FROM course_evaluation_components WHERE course_code = ? ORDER BY id");
    $stmt_comps->execute([$selected_course]);
    $dynamic_comps = $stmt_comps->fetchAll();

    $m_sql = "SELECT m.*, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem
              FROM marks m 
              JOIN students s ON m.student_id = s.id 
              WHERE m.course_code = ? " . $filter_sql . " 
              ORDER BY s.enrollment_no";
    $m_params = array_merge([$selected_course], $filter_params);
    $stmt = $pdo->prepare($m_sql);
    $stmt->execute($m_params);
    $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($marks) && !empty($dynamic_comps)) {
        $student_ids = array_column($marks, 'student_id');
        $in = str_repeat('?,', count($student_ids) - 1) . '?';
        $sm_q = $pdo->prepare("SELECT student_id, component_id, scored_marks FROM student_component_marks WHERE student_id IN ($in)");
        $sm_q->execute($student_ids);
        $sm_rows = $sm_q->fetchAll(PDO::FETCH_ASSOC);
        
        $sm_map = [];
        foreach ($sm_rows as $row) {
            $sm_map[$row['student_id']][$row['component_id']] = $row['scored_marks'];
        }
        
        foreach ($marks as &$m) {
            $m['comp_marks'] = $sm_map[$m['student_id']] ?? [];
        }
        unset($m);
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita Admin - All Marks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .edit-inline { width:60px; padding:4px 6px; border:1px solid #ddd; border-radius:4px; font-size:12px; text-align:center; }
        .grade-inline { width:40px; padding:4px 6px; border:1px solid #ddd; border-radius:4px; font-size:12px; text-align:center; }
        .save-btn { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; padding:4px 10px; border-radius:4px; font-size:11px; cursor:pointer; font-weight:600; }
        .course-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;}
        .course-pill{display:block;padding:12px 16px;border:1px solid #e0e0e0;border-radius:8px;text-decoration:none;color:#333;transition:.2s;border-left:4px solid #a4123f;}
        .course-pill:hover{border-color:#a4123f;background:#fef5f7;}.course-pill.active{background:#f0fff4;border-left-color:#27ae60;}
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
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> All Marks</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-bar-chart"></i> All Marks</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php if ($msg === 'update_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Marks updated successfully!</div>
        <?php endif; ?>

        <?php 
        $filter_count = count($all_courses);
        include 'filter_ui.php'; 
        ?>

        <!-- Branch/Course Selection -->
        <div class="card">
            <h2 class="card-title"><i class="fa fa-sitemap" style="color:#a4123f;"></i> Select Course for this Cohort</h2>
            <p style="font-size:12px;color:#888;margin-bottom:12px;">Click a course below to view/edit student marks for the selected batch/branch/section/sem.</p>
            <div class="course-grid">
                <?php if (empty($all_courses)): ?>
                    <div style="font-size:12px; color:#888;">No courses found for this cohort. Adjust filters.</div>
                <?php else: ?>
                    <?php foreach ($all_courses as $c): ?>
                    <a href="manage_marks.php?course=<?php echo urlencode($c['course_code']); ?>&batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="course-pill <?php echo $selected_course===$c['course_code']?'active':''; ?>">
                        <div style="font-size:11px;color:#a4123f;font-weight:700;"><?php echo htmlspecialchars($c['course_code']); ?></div>
                        <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($c['course_name']); ?></div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_course && !empty($marks)): ?>
        <div class="card">
            <h2 class="card-title"><i class="fa fa-bar-chart" style="color:#a4123f;"></i> Marks – <?php echo htmlspecialchars($selected_course); ?></h2>
                <table class="data-table" style="font-size:11px; min-width:800px;">
                    <thead><tr>
                        <th>Student</th>
                        <th>Course</th>
                        <?php if (!empty($dynamic_comps)): ?>
                            <?php foreach ($dynamic_comps as $c): ?>
                                <th title="Max: <?php echo $c['max_marks']; ?> | Weightage: <?php echo $c['weightage']; ?>">
                                    <?php echo htmlspecialchars($c['component_name']); ?><br>
                                    <small style="color:#888;">Max: <?php echo rtrim(rtrim($c['max_marks'], '0'), '.'); ?> (W:<?php echo rtrim(rtrim($c['weightage'], '0'), '.'); ?>)</small>
                                </th>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <th>Assignment 1</th><th>Assignment 2</th><th>Quiz 1</th><th>Quiz 2</th><th>Midterm</th>
                        <?php endif; ?>
                        <th>Internal</th>
                        <th>External</th>
                        <th>Total</th>
                        <th>Grade</th>
                        <th>Save</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($marks as $m): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_marks">
                        <input type="hidden" name="mark_id" value="<?php echo $m['id']; ?>">
                        <input type="hidden" name="student_id" value="<?php echo $m['student_id']; ?>">
                        <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($m['course_code']); ?>">
                        <tr>
                            <td><strong><?php echo htmlspecialchars($m['student_name']); ?></strong><br><small><?php echo htmlspecialchars($m['enrollment_no']); ?></small></td>
                            <td><strong><?php echo htmlspecialchars($m['course_code']); ?></strong><br><small><?php echo htmlspecialchars($m['course_name']); ?></small></td>
                            <?php if (!empty($dynamic_comps)): ?>
                                <?php foreach ($dynamic_comps as $c): 
                                    $scored = $m['comp_marks'][$c['id']] ?? 0;
                                ?>
                                <td><input type="number" step="0.01" class="edit-inline" name="comp_<?php echo $c['id']; ?>" value="<?php echo $scored; ?>" max="<?php echo $c['max_marks']; ?>"></td>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td><input type="number" step="0.01" class="edit-inline" name="assignment1" value="0"></td>
                                <td><input type="number" step="0.01" class="edit-inline" name="assignment2" value="0"></td>
                                <td><input type="number" step="0.01" class="edit-inline" name="quiz1" value="0"></td>
                                <td><input type="number" step="0.01" class="edit-inline" name="quiz2" value="0"></td>
                                <td><input type="number" step="0.01" class="edit-inline" name="midterm" value="0"></td>
                            <?php endif; ?>
                            <td><strong style="color:#a4123f;"><?php echo number_format($m['internal'], 2); ?></strong></td>
                            <td><input type="number" step="0.01" class="edit-inline" name="external" value="<?php echo $m['external']; ?>" style="width:45px;"></td>
                            <td><strong><?php echo number_format($m['total'], 1); ?></strong></td>
                            <td><input type="text" class="grade-inline" name="grade" value="<?php echo htmlspecialchars($m['grade']); ?>"></td>
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
