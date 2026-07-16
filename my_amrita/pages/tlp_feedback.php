<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
$view = $_GET['view'] ?? 'list'; // 'list' or 'form'
$fb_course = $_GET['course'] ?? '';

// Get student's courses from timetable (unique, non-break)
$courses_stmt = $pdo->prepare("SELECT DISTINCT course_code, course_name, faculty_name FROM timetable WHERE student_id = ? AND course_code NOT IN ('BREAK','LUNCH','BUS','ADDSLOT') AND faculty_name != '' AND course_name NOT IN ('Evaluation','Counselling','Tea Break','Lunch Break','Additional Slot','Buses Departure') AND course_name NOT LIKE '%Lab%' AND course_name NOT LIKE '%Eval%' ORDER BY course_code");
$courses_stmt->execute([$student_id]);
$my_courses = $courses_stmt->fetchAll();

// Deduplicate by course_code (keep first/primary entry with actual subject name)
$course_map = [];
foreach ($my_courses as $c) {
    if (!isset($course_map[$c['course_code']])) {
        $course_map[$c['course_code']] = $c;
    }
}
$courses = array_values($course_map);

// Check which courses already have feedback
$fb_check = $pdo->prepare("SELECT course_code FROM course_feedback WHERE student_id = ?");
$fb_check->execute([$student_id]);
$submitted = array_column($fb_check->fetchAll(), 'course_code');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_tlp'])) {
    $code = trim($_POST['course_code'] ?? '');
    $cname = trim($_POST['course_name'] ?? '');
    $faculty = trim($_POST['faculty_name'] ?? '');
    $answers = [];
    for ($q = 1; $q <= 9; $q++) {
        $answers["q$q"] = $_POST["q$q"] ?? '';
    }
    $comments = trim($_POST['comments'] ?? '');
    $answers_json = json_encode($answers);

    if ($code && $cname && !empty(array_filter($answers))) {
        // Delete existing feedback for this course
        $pdo->prepare("DELETE FROM course_feedback WHERE student_id = ? AND course_code = ?")->execute([$student_id, $code]);

        try { $pdo->exec("ALTER TABLE course_feedback ADD COLUMN faculty_name VARCHAR(100) DEFAULT NULL"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE course_feedback ADD COLUMN answers_json TEXT DEFAULT NULL"); } catch(Exception $e) {}

        $stmt = $pdo->prepare("INSERT INTO course_feedback (student_id, course_code, course_name, faculty_name, rating, comments, answers_json) VALUES (?,?,?,?,?,?,?)");
        $avg_rating = 0; // We'll compute from answers
        $stmt->execute([$student_id, $code, $cname, $faculty, 4, $comments, $answers_json]);
        $msg = 'success';
        $submitted[] = $code;
        $view = 'list';
    } else {
        $msg = 'error';
    }
}

// Get course info for form view
$form_course = null;
if ($view === 'form' && $fb_course) {
    foreach ($courses as $c) {
        if ($c['course_code'] === $fb_course) { $form_course = $c; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - TLP Feedback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .tlp-table { width:100%; border-collapse:collapse; font-size:13px; }
        .tlp-table th { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; padding:12px 14px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
        .tlp-table td { padding:10px 14px; border-bottom:1px solid #eee; vertical-align:middle; }
        .tlp-table tr:hover { background:#f8f9fa; }
        .tlp-table .code { font-weight:700; color:#a4123f; }
        .enter-fb { color:#1565c0; font-weight:600; text-decoration:underline; cursor:pointer; font-size:13px; }
        .enter-fb:hover { color:#0d47a1; }
        .submitted-badge { background:#e8f5e9; color:#2e7d32; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600; }
        .question-block { margin-bottom:20px; }
        .question-block .q-text { font-weight:600; color:#333; font-size:14px; margin-bottom:8px; }
        .question-block .q-options { display:flex; flex-wrap:wrap; gap:8px; }
        .question-block .q-options label { display:flex; align-items:center; gap:5px; font-size:13px; color:#555; cursor:pointer; padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; transition:all 0.2s; }
        .question-block .q-options label:hover { border-color:#a4123f; background:#fef5f7; }
        .question-block .q-options input[type="radio"] { accent-color:#a4123f; }
        .question-block .q-options input[type="radio"]:checked + span { color:#a4123f; font-weight:600; }
        .form-header { background:linear-gradient(135deg,#a4123f,#d4264f); padding:20px 24px; border-radius:12px; color:#fff; margin-bottom:24px; }
        .form-header .fh-title { font-size:18px; font-weight:700; }
        .form-header .fh-info { font-size:13px; opacity:0.9; margin-top:6px; }
        .form-header .fh-faculty { font-size:14px; margin-top:8px; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> <a href="tlp_feedback.php">TLP Feedback</a><?php if ($view === 'form'): ?> <span class="sep">/</span> Enter Feedback<?php endif; ?></div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-commenting-o"></i> TLP Feedback</h1>
            <a href="<?php echo $view === 'form' ? 'tlp_feedback.php' : '../home.php'; ?>" class="back-btn"><i class="fa fa-arrow-left"></i> <?php echo $view === 'form' ? 'Back to List' : 'Back to Home'; ?></a>
        </div>

        <?php if ($msg === 'success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> TLP Feedback submitted successfully!</div>
        <?php elseif ($msg === 'error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Please answer all questions.</div><?php endif; ?>

        <?php if ($view === 'list'): ?>
        <!-- ========== COURSE LIST VIEW ========== -->
        <div class="card">
            <h2 class="card-title"><i class="fa fa-list" style="color:#a4123f;"></i> My Courses – Semester 6</h2>
            <p style="font-size:13px; color:#888; margin-bottom:16px;">Click "Enter Feedback" to submit TLP feedback for each course and faculty.</p>
            <table class="tlp-table">
                <thead>
                    <tr>
                        <th>Sl. No.</th>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th>Faculty</th>
                        <th>Feedback Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $i => $c): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td class="code"><?php echo htmlspecialchars($c['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($c['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($c['faculty_name']); ?></td>
                        <td>
                            <?php if (in_array($c['course_code'], $submitted)): ?>
                                <span class="submitted-badge"><i class="fa fa-check"></i> Submitted</span>
                            <?php else: ?>
                                <a href="tlp_feedback.php?view=form&course=<?php echo urlencode($c['course_code']); ?>" class="enter-fb">Enter Feedback</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($view === 'form' && $form_course): ?>
        <!-- ========== TLP FEEDBACK FORM ========== -->
        <div class="card" style="max-width:800px; margin:0 auto;">
            <div class="form-header">
                <div class="fh-title">Student Feedback System – TLP</div>
                <div class="fh-info">Branch: CSE &nbsp;&nbsp; Semester: 6</div>
                <div class="fh-info">Course: <?php echo htmlspecialchars($form_course['course_code'] . ' ' . $form_course['course_name']); ?></div>
                <div class="fh-faculty">Faculty: <?php echo htmlspecialchars($form_course['faculty_name']); ?></div>
            </div>

            <form method="POST">
                <input type="hidden" name="submit_tlp" value="1">
                <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($form_course['course_code']); ?>">
                <input type="hidden" name="course_name" value="<?php echo htmlspecialchars($form_course['course_name']); ?>">
                <input type="hidden" name="faculty_name" value="<?php echo htmlspecialchars($form_course['faculty_name']); ?>">

                <div class="question-block">
                    <div class="q-text">1. How would you rate the instructor's knowledge of the subject matter?</div>
                    <div class="q-options">
                        <?php foreach (['Excellent','Good','Average','Poor'] as $o): ?>
                        <label><input type="radio" name="q1" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">2. How effectively did the instructor communicate the course material?</div>
                    <div class="q-options">
                        <?php foreach (['Excellent','Good','Average','Poor'] as $o): ?>
                        <label><input type="radio" name="q2" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">3. Was the instructor available and helpful during office hours or for extra assistance?</div>
                    <div class="q-options">
                        <?php foreach (['Strongly Agree','Agree','Neutral','Disagree','Strongly Disagree'] as $o): ?>
                        <label><input type="radio" name="q3" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">4. Did the instructor encourage participation and facilitate a positive learning environment?</div>
                    <div class="q-options">
                        <?php foreach (['Strongly Agree','Agree','Neutral','Disagree','Strongly Disagree'] as $o): ?>
                        <label><input type="radio" name="q4" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">5. Were the assignments and exams fair and reflective of the course material?</div>
                    <div class="q-options">
                        <?php foreach (['Strongly Agree','Agree','Neutral','Disagree','Strongly Disagree'] as $o): ?>
                        <label><input type="radio" name="q5" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">6. How satisfied were you with the pacing of the teacher?</div>
                    <div class="q-options">
                        <?php foreach (['Very Satisfied','Satisfied','Neutral','Dissatisfied','Very Dissatisfied'] as $o): ?>
                        <label><input type="radio" name="q6" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">7. How many classes has the teacher missed in the past?</div>
                    <div class="q-options">
                        <?php foreach (['Below 5%','5 - 10%','10 - 20%','More than 20%'] as $o): ?>
                        <label><input type="radio" name="q7" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">8. How would you rate the teacher's overall attendance and punctuality?</div>
                    <div class="q-options">
                        <?php foreach (['Excellent','Good','Average','Poor'] as $o): ?>
                        <label><input type="radio" name="q8" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">9. How would you rate the instructor's overall teaching effectiveness?</div>
                    <div class="q-options">
                        <?php foreach (['Excellent','Good','Average','Poor'] as $o): ?>
                        <label><input type="radio" name="q9" value="<?php echo $o; ?>" required><span><?php echo $o; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="question-block">
                    <div class="q-text">Please provide any additional comments or suggestions for the instructor's improvement:</div>
                    <textarea name="comments" class="form-control" rows="3" placeholder="Your comments and suggestions..." style="margin-top:8px;"></textarea>
                </div>

                <button type="submit" class="submit-btn" style="width:100%; padding:14px; margin-top:10px;"><i class="fa fa-paper-plane"></i> Submit Feedback</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
