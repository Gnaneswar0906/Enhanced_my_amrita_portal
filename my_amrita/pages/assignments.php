<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$semester_map = [
    '2023-24' => ['odd' => 1, 'even' => 2],
    '2024-25' => ['odd' => 3, 'even' => 4],
    '2025-26' => ['odd' => 5, 'even' => 6],
    '2026-27' => ['odd' => 7, 'even' => 8],
];
$selected_year = $_GET['year'] ?? '2025-26';
$selected_type = $_GET['sem_type'] ?? 'even';
$current_sem = $semester_map[$selected_year][$selected_type] ?? 6;

$stmt = $pdo->prepare('SELECT DISTINCT course_code, course_name FROM attendance WHERE student_id = ? AND semester = ?');
$stmt->execute([$student_id, $current_sem]);
$courseCodes = $stmt->fetchAll();

$student_details = $pdo->prepare("SELECT batch, department as branch, section FROM students WHERE id = ?");
$student_details->execute([$student_id]);
$s_info = $student_details->fetch();

$selected = $_GET['course'] ?? '';
$assignments = [];
if ($selected) {
    $stmt = $pdo->prepare("SELECT * FROM assignments WHERE course_code = ? AND (semester IS NULL OR semester = ?) AND (batch IS NULL OR batch = ?) AND (branch IS NULL OR branch = ?) AND (section IS NULL OR section = ?) ORDER BY due_date ASC");
    $stmt->execute([$selected, $current_sem, $s_info['batch'], $s_info['branch'], $s_info['section']]);
    $assignments = $stmt->fetchAll();
} else {
    if (!empty($courseCodes)) {
        $codes = array_column($courseCodes, 'course_code');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $params = array_merge($codes, [$current_sem, $s_info['batch'], $s_info['branch'], $s_info['section']]);
        $stmt = $pdo->prepare("SELECT * FROM assignments WHERE course_code IN ($placeholders) AND (semester IS NULL OR semester = ?) AND (batch IS NULL OR batch = ?) AND (branch IS NULL OR branch = ?) AND (section IS NULL OR section = ?) ORDER BY due_date ASC");
        $stmt->execute($params);
        $assignments = $stmt->fetchAll();
    }
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_assignment') {
    $aid = intval($_POST['assignment_id'] ?? 0);
    if ($aid && !empty($_FILES['submission_files']['name'][0])) {
        @mkdir('../uploads/assignments', 0777, true);
        $all_names = [];
        $all_paths = [];
        foreach ($_FILES['submission_files']['name'] as $key => $fname) {
            if ($_FILES['submission_files']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($fname, PATHINFO_EXTENSION);
                $newName = 'assign_' . $aid . '_' . $student_id . '_' . time() . '_' . ($key+1) . '.' . $ext;
                $dest = '../uploads/assignments/' . $newName;
                move_uploaded_file($_FILES['submission_files']['tmp_name'][$key], $dest);
                $all_names[] = $fname;
                $all_paths[] = '/uploads/assignments/' . $newName;
            }
        }
        if (!empty($all_names)) {
            $combined_name = implode(', ', $all_names);
            $combined_path = $all_paths[0]; // store first path as primary
            $chk = $pdo->prepare('SELECT id FROM assignment_submissions WHERE assignment_id=? AND student_id=?');
            $chk->execute([$aid, $student_id]);
            if ($chk->fetch()) {
                $pdo->prepare('UPDATE assignment_submissions SET file_name=?, file_path=?, submitted_at=NOW() WHERE assignment_id=? AND student_id=?')
                    ->execute([$combined_name, $combined_path, $aid, $student_id]);
            } else {
                $pdo->prepare('INSERT INTO assignment_submissions (assignment_id, student_id, file_name, file_path) VALUES (?,?,?,?)')
                    ->execute([$aid, $student_id, $combined_name, $combined_path]);
            }
            $msg = 'submitted';
        }
    }
}

$today = date('Y-m-d');
$upcoming = array_filter($assignments, fn($a) => $a['due_date'] >= $today);
$past = array_filter($assignments, fn($a) => $a['due_date'] < $today);

// Get faculty per course from timetable
$faculty_map = [];
try {
    $stmt = $pdo->prepare('SELECT DISTINCT course_code, faculty_name FROM timetable WHERE student_id = ? AND semester = ?');
    $stmt->execute([$student_id, $current_sem]);
    foreach ($stmt->fetchAll() as $f) { $faculty_map[$f['course_code']] = $f['faculty_name']; }
} catch(Exception $e) {}

// Get submissions by this student
$submissions = [];
try {
    $stmt3 = $pdo->prepare('SELECT * FROM assignment_submissions WHERE student_id = ?');
    $stmt3->execute([$student_id]);
    foreach ($stmt3->fetchAll() as $s) { $submissions[$s['assignment_id']] = $s; }
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Assignments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .semester-selector { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
        .semester-selector label { font-size:12px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-right:4px; }
        .semester-selector select { padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; cursor:pointer; }
        .semester-selector .sem-badge { padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600; }
        .subject-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; cursor:pointer; transition:all 0.25s; text-decoration:none; color:#333; display:block; }
        .subject-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(164,18,63,0.12); border-color:#d4264f; }
        .subjects-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:14px; }
        .cat-pill { display:inline-block; padding:2px 10px; border-radius:12px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; }
        .cat-pill.homework { background:#e3f2fd; color:#1565c0; }
        .cat-pill.lab { background:#e8f5e9; color:#2e7d32; }
        .cat-pill.case-study { background:#fff3e0; color:#e65100; }
        .cat-pill.project { background:#f3e5f5; color:#7b1fa2; }
        .cat-pill.quiz { background:#fce4ec; color:#c62828; }
        .cat-pill.other { background:#f5f5f5; color:#666; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> <?php if ($selected): ?><a href="assignments.php?year=<?php echo $selected_year; ?>&sem_type=<?php echo $selected_type; ?>">Assignments</a> <span class="sep">/</span> <?php echo htmlspecialchars($selected); ?><?php else: ?>Assignments<?php endif; ?></div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-tasks"></i> Assignments</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'submitted'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Assignment submitted successfully!</div>
        <?php endif; ?>

        <!-- Semester Selector -->
        <div class="semester-selector">
            <label>Academic Year:</label>
            <select onchange="changeSemester()" id="semYear">
                <?php foreach (array_keys($semester_map) as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr === $selected_year ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Semester:</label>
            <select onchange="changeSemester()" id="semType">
                <option value="odd" <?php echo $selected_type === 'odd' ? 'selected' : ''; ?>>Odd</option>
                <option value="even" <?php echo $selected_type === 'even' ? 'selected' : ''; ?>>Even</option>
            </select>
            <span class="sem-badge">Semester <?php echo $current_sem; ?></span>
        </div>

        <?php if (!$selected): ?>
        <!-- Subject Cards -->
        <div class="card">
            <h2 class="card-title">Select Subject</h2>
            <?php if (empty($courseCodes)): ?>
                <div class="empty-state"><i class="fa fa-tasks"></i><p>No subjects found for Semester <?php echo $current_sem; ?>.</p></div>
            <?php else: ?>
            <div class="subjects-grid">
                <?php foreach ($courseCodes as $cc): ?>
                <?php
                $course_assignments = array_filter($assignments, fn($a) => $a['course_code'] === $cc['course_code']);
                $upcoming_count = count(array_filter($course_assignments, fn($a) => $a['due_date'] >= $today));
                ?>
                <a href="?year=<?php echo $selected_year; ?>&sem_type=<?php echo $selected_type; ?>&course=<?php echo urlencode($cc['course_code']); ?>" class="subject-card" style="border-left:4px solid #a4123f;">
                    <div style="font-size:12px; color:#a4123f; font-weight:700;"><?php echo htmlspecialchars($cc['course_code']); ?></div>
                    <div style="font-size:14px; font-weight:600; margin:4px 0;"><?php echo htmlspecialchars($cc['course_name']); ?></div>
                    <div style="font-size:12px; color:#666;"><i class="fa fa-user" style="color:#a4123f; margin-right:4px;"></i> <?php echo htmlspecialchars($faculty_map[$cc['course_code']] ?? 'Faculty TBA'); ?></div>
                    <div style="margin-top:10px; display:flex; gap:12px; font-size:12px;">
                        <span style="color:#a4123f; font-weight:600;"><?php echo $upcoming_count; ?> upcoming</span>
                        <span style="color:#888;"><?php echo count($course_assignments); ?> total</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Single Subject Assignments -->
        <a href="assignments.php?year=<?php echo $selected_year; ?>&sem_type=<?php echo $selected_type; ?>" style="display:inline-block; margin-bottom:16px; color:#a4123f; font-size:13px; font-weight:600; text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to All Subjects</a>

        <div class="card" style="border-left:4px solid #a4123f;">
            <div style="font-size:12px; color:#a4123f; font-weight:600; text-transform:uppercase;"><?php echo htmlspecialchars($selected); ?></div>
            <h2 style="margin:4px 0 0; font-size:18px;"><?php echo htmlspecialchars($assignments[0]['course_name'] ?? $selected); ?></h2>
            <div style="font-size:13px; color:#666; margin-top:4px;"><i class="fa fa-user" style="color:#a4123f;"></i> <?php echo htmlspecialchars($faculty_map[$selected] ?? 'Faculty TBA'); ?></div>
        </div>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('upcoming')"><i class="fa fa-clock-o"></i> Upcoming (<?php echo count($upcoming); ?>)</button>
            <button class="tab-btn" onclick="switchTab('past')"><i class="fa fa-history"></i> Past (<?php echo count($past); ?>)</button>
        </div>

        <div class="tab-content active" id="tab-upcoming">
            <?php if (empty($upcoming)): ?>
                <div class="card"><div class="empty-state"><i class="fa fa-tasks"></i><p>No upcoming assignments.</p></div></div>
            <?php else: foreach ($upcoming as $a):
                $sub = $submissions[$a['id']] ?? null;
                $status_label = $sub ? $sub['status'] : 'Pending';
                $status_color = match($status_label) { 'Graded' => '#27ae60', 'Submitted' => '#3498db', default => '#f5a623' };
                $days_left = (strtotime($a['due_date']) - strtotime($today)) / 86400;
            ?>
                <div class="card" style="border-left:4px solid <?php echo $status_color; ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                        <div style="flex:1;">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                <h3 style="margin:0; font-size:16px; color:#333;"><?php echo htmlspecialchars($a['title']); ?></h3>
                                <?php $catSlug = strtolower(str_replace(' ','-',$a['category'] ?? 'other')); ?>
                                <span class="cat-pill <?php echo $catSlug; ?>"><?php echo htmlspecialchars($a['category'] ?? 'Homework'); ?></span>
                            </div>
                            <?php if ($a['description']): ?><div style="font-size:13px; color:#555; margin-bottom:8px;"><?php echo htmlspecialchars($a['description']); ?></div><?php endif; ?>
                            <div style="display:flex; gap:16px; font-size:12px; color:#888; flex-wrap:wrap;">
                                <span><i class="fa fa-user" style="color:#a4123f;"></i> <?php echo htmlspecialchars($a['assigned_by'] ?? '—'); ?></span>
                                <span><i class="fa fa-star" style="color:#f5a623;"></i> Total Marks: <strong><?php echo $a['total_marks'] ?? 10; ?></strong></span>
                                <?php if ($sub && $status_label === 'Graded'): ?>
                                    <span><i class="fa fa-check-circle" style="color:#27ae60;"></i> Marks: <strong style="color:#27ae60;"><?php echo $sub['marks_awarded']; ?>/<?php echo $a['total_marks'] ?? 10; ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align:right; min-width:100px;">
                            <div style="font-size:12px; color:#888;">Due Date</div>
                            <div style="font-size:14px; font-weight:600; color:#a4123f;"><?php echo date('d M Y', strtotime($a['due_date'])); ?></div>
                            <div style="font-size:11px; color:<?php echo $days_left <= 2 ? '#e74c3c' : '#888'; ?>; margin-top:2px;"><?php echo (int)$days_left; ?> days left</div>
                            <div style="margin-top:6px;"><span class="badge badge-<?php echo strtolower($status_label); ?>"><?php echo $status_label; ?></span></div>
                        </div>
                    </div>
                    <?php if ($a['file_path']): ?><a href="..<?php echo htmlspecialchars($a['file_path']); ?>" style="color:#a4123f; font-size:13px; font-weight:600; margin-top:8px; display:inline-block;" download><i class="fa fa-download"></i> Download Assignment</a>
                    <a href="..<?php echo htmlspecialchars($a['file_path']); ?>" target="_blank" style="color:#1565c0; font-size:13px; font-weight:600; margin-top:8px; display:inline-block; margin-left:12px;"><i class="fa fa-eye"></i> View</a><?php endif; ?>
                    <?php if ($sub && $sub['feedback']): ?>
                    <div style="margin-top:10px; padding:10px; background:#e8f5e9; border-radius:8px; font-size:12px; color:#2e7d32;"><i class="fa fa-reply"></i> <strong>Feedback:</strong> <?php echo htmlspecialchars($sub['feedback']); ?></div>
                    <?php endif; ?>

                    <!-- Submission Section -->
                    <?php if ($sub && !empty($sub['file_path'])): 
                          $disp_path = $sub['file_path'];
                          if (strpos($disp_path, '/uploads/') === 0) {
                              $disp_path = '..' . $disp_path;
                          }
                    ?>
                    <div style="margin-top:10px; padding:10px; background:#e3f2fd; border-radius:8px; font-size:12px;">
                        <strong><i class="fa fa-file" style="color:#1565c0;"></i> Your Submission:</strong> <?php echo htmlspecialchars($sub['file_name']); ?>
                        <span style="color:#888; margin-left:8px;">(<?php echo date('d M Y H:i', strtotime($sub['submitted_at'])); ?>)</span>
                        <a href="<?php echo htmlspecialchars($disp_path); ?>" target="_blank" style="color:#1565c0; font-weight:600; margin-left:8px;"><i class="fa fa-eye"></i> View</a>
                        <a href="<?php echo htmlspecialchars($disp_path); ?>" download style="color:#2e7d32; font-weight:600; margin-left:8px;"><i class="fa fa-download"></i> Download</a>
                    </div>
                    <?php endif; ?>
                    <?php if (!$sub || $status_label !== 'Graded'): ?>
                    <form method="POST" enctype="multipart/form-data" style="margin-top:10px; padding:12px; background:#f8f9fa; border-radius:8px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="action" value="submit_assignment">
                        <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                        <input type="file" name="submission_files[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.ppt,.pptx" required multiple style="font-size:12px; font-family:'Inter',sans-serif;">
                        <button type="submit" style="background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer;"><i class="fa fa-upload"></i> <?php echo $sub ? 'Re-submit' : 'Submit'; ?></button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="tab-content" id="tab-past">
            <?php if (empty($past)): ?>
                <div class="card"><div class="empty-state"><i class="fa fa-history"></i><p>No past assignments.</p></div></div>
            <?php else: foreach ($past as $a):
                $sub = $submissions[$a['id']] ?? null;
                $status_label = $sub ? $sub['status'] : 'Missing';
                $status_color = match($status_label) { 'Graded' => '#27ae60', 'Submitted' => '#3498db', default => '#e74c3c' };
            ?>
                <div class="card" style="border-left:4px solid <?php echo $status_color; ?>; opacity:0.85;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <h3 style="margin:0; font-size:15px;"><?php echo htmlspecialchars($a['title']); ?></h3>
                                <?php $catSlug = strtolower(str_replace(' ','-',$a['category'] ?? 'other')); ?>
                                <span class="cat-pill <?php echo $catSlug; ?>"><?php echo htmlspecialchars($a['category'] ?? 'Homework'); ?></span>
                            </div>
                            <div style="font-size:12px; color:#888; margin-top:4px;">Due: <?php echo date('d M Y', strtotime($a['due_date'])); ?> • Marks: <?php echo $a['total_marks'] ?? 10; ?></div>
                        </div>
                        <div style="text-align:right;">
                            <span class="badge badge-<?php echo strtolower($status_label); ?>"><?php echo $status_label; ?></span>
                            <?php if ($sub && $status_label === 'Graded'): ?>
                                <div style="font-size:14px; font-weight:700; color:#27ae60; margin-top:4px;"><?php echo $sub['marks_awarded']; ?>/<?php echo $a['total_marks'] ?? 10; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <script>
    function changeSemester() {
        var y = document.getElementById('semYear').value;
        var t = document.getElementById('semType').value;
        window.location.href = 'assignments.php?year=' + y + '&sem_type=' + t;
    }
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');
    }
    </script>
<script src="../js/upload_validator.js"></script>
</body>
</html>

