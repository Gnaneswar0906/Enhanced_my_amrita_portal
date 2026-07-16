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

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_marks_issue') {
    $course = trim($_POST['course_code'] ?? '');
    $cname  = trim($_POST['course_name'] ?? '');
    $etype  = trim($_POST['exam_type'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    if ($course && $etype && $desc) {
        $stmt = $pdo->prepare('INSERT INTO marks_issues (student_id, course_code, course_name, exam_type, description) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$student_id, $course, $cname, $etype, $desc]);
        $issue_id = $pdo->lastInsertId();
        if (!empty($_FILES['proof_files']['name'][0])) {
            foreach ($_FILES['proof_files']['name'] as $key => $fname) {
                if ($_FILES['proof_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($fname, PATHINFO_EXTENSION);
                    $newName = 'marks_proof_' . $issue_id . '_' . ($key+1) . '.' . $ext;
                    $dest = '../uploads/proofs/' . $newName;
                    @mkdir('../uploads/proofs', 0777, true);
                    move_uploaded_file($_FILES['proof_files']['tmp_name'][$key], $dest);
                    $pdo->prepare('INSERT INTO file_attachments (ref_type, ref_id, file_name, file_path) VALUES (?, ?, ?, ?)')->execute(['marks_issue', $issue_id, $fname, '/uploads/proofs/' . $newName]);
                }
            }
        }
        $msg = 'success';
    } else { $msg = 'error'; }
}

$stmt = $pdo->prepare('SELECT m.*, (SELECT DISTINCT t.faculty_name FROM timetable t WHERE t.student_id = m.student_id AND t.course_code = m.course_code LIMIT 1) as faculty_name FROM marks m WHERE m.student_id = ? AND m.semester = ? ORDER BY m.course_code');
$stmt->execute([$student_id, $current_sem]);
$marks = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT mi.*, (SELECT COUNT(*) FROM file_attachments fa WHERE fa.ref_type="marks_issue" AND fa.ref_id=mi.id) as file_count FROM marks_issues mi WHERE mi.student_id = ? ORDER BY mi.created_at DESC');
$stmt->execute([$student_id]);
$marks_issues = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM courses WHERE semester = ? ORDER BY course_code');
$stmt->execute([$current_sem]);
$courses = $stmt->fetchAll();

$stmt_w = $pdo->prepare('SELECT course_code, SUM(weightage) as max_w FROM course_evaluation_components GROUP BY course_code');
$stmt_w->execute();
$weight_map = $stmt_w->fetchAll(PDO::FETCH_KEY_PAIR);

// SGPA calculation
$total_credit_points = 0; $total_credits = 0;
$total_internal_all = 0;
$total_max_w_all = 0;
foreach ($marks as $m) {
    $cp = 0;
    switch ($m['grade']) {
        case 'O': $cp = 10; break;
        case 'A+': $cp = 9; break;
        case 'A': $cp = 8; break;
        case 'B+': $cp = 7; break;
        case 'B': $cp = 6; break;
        case 'C': $cp = 5; break;
        case 'P': $cp = 4; break;
        default: $cp = 0; break;
    }
    foreach ($courses as $c) {
        if ($c['course_code'] === $m['course_code']) {
            $total_credits += $c['credits'];
            $total_credit_points += $cp * $c['credits'];
            break;
        }
    }
    
    $total_internal_all += $m['internal'];
    $mw = isset($weight_map[$m['course_code']]) ? $weight_map[$m['course_code']] : 50;
    $total_max_w_all += $mw;
}
$sgpa = ($total_credits > 0) ? $total_credit_points / $total_credits : 0;

$selected_course = $_GET['course'] ?? '';
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Marks</title>
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
        .semester-selector .sem-badge { padding:6px 14px; background:#c2185b; color:#fff; border-radius:8px; font-size:12px; font-weight:600; }
        
        /* Subject Cards */
        .subject-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; cursor:pointer; transition:all 0.25s; text-decoration:none; color:#333; display:block; border-left:4px solid #27ae60; }
        .subject-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,0.08); }
        .subject-card.active { border: 1px solid #c2185b; border-left:4px solid #c2185b; box-shadow:0 6px 20px rgba(194,24,91,0.12); }
        .subject-card .code { font-size:12px; color:#c2185b; font-weight:700; text-transform:uppercase; margin-bottom: 2px; }
        .subject-card .name { font-size:15px; font-weight:600; color:#1a5276; margin:0 0 6px; }
        .subject-card .faculty { font-size:12px; color:#888; }
        .subjects-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px; margin-bottom: 24px; }
        
        .tab-btn { background:transparent; border:none; color:#666; font-size:14px; font-weight:600; padding:10px 0; margin-right:24px; cursor:pointer; position:relative; }
        .tab-btn.active { color:#333; }
        .tab-btn.active::after { content:''; position:absolute; bottom:-2px; left:0; width:100%; height:3px; background:#c2185b; border-radius:3px 3px 0 0; }
        
        /* Stats blocks */
        .stats-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
        .stat-block { padding:24px; border-radius:10px; position:relative; overflow:hidden; }
        .stat-block-red { background:#d41a4f; color:#fff; }
        .stat-block-blue { background:#2c3e50; color:#fff; }
        .stat-block .stat-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.8; margin-bottom:8px; }
        .stat-block .stat-val { font-size:36px; font-weight:700; margin:0; line-height:1; }
        .stat-block .stat-sub { font-size:12px; opacity:0.8; margin-top:12px; }
        .stat-block .circle-bg { position:absolute; right:-30px; top:-30px; width:120px; height:120px; background:rgba(255,255,255,0.08); border-radius:50%; }
        
        /* Result Table */
        .result-table { width:100%; border-collapse:collapse; background:#fff; }
        .result-table th { font-size:11px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; padding:16px 20px; border-bottom:1px solid #eaeaea; text-align:left; }
        .result-table td { font-size:14px; color:#444; padding:16px 20px; border-bottom:1px solid #f8f9fa; }
        .result-table tr:nth-child(even) td { background:#fafafa; }
        
        /* Single Subject Components */
        .report-btn { background:#fff; color:#e74c3c; border:1px solid #e74c3c; font-size:12px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:4px; text-decoration:none; transition:0.2s; }
        .report-btn:hover { background:#fdf5f5; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> <?php if ($selected_course): ?><a href="marks.php?year=<?php echo $selected_year; ?>&sem_type=<?php echo $selected_type; ?>">Marks</a> <span class="sep">/</span> <?php echo htmlspecialchars($selected_course); ?><?php else: ?>Marks<?php endif; ?></div>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-bar-chart"></i> Marks & Internals</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>
        
        <?php if ($msg === 'success'): ?>
            <div class="msg-success" style="margin-bottom:16px;"><i class="fa fa-check-circle"></i> Marks issue reported!</div>
        <?php elseif ($msg === 'error'): ?>
            <div class="msg-error" style="margin-bottom:16px;"><i class="fa fa-times-circle"></i> Please fill all fields.</div>
        <?php endif; ?>

        <!-- Semester Selector & Stats Box -->
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

        <div class="stats-grid">
            <div class="stat-block stat-block-red">
                <div class="stat-label">Total Internal Marks</div>
                <div class="stat-val"><?php echo number_format($total_internal_all, 1); ?></div>
                <div class="stat-sub">Semester <?php echo $current_sem; ?> • <?php echo count($marks); ?> Subjects</div>
                <div class="circle-bg"></div>
            </div>
            <div class="stat-block stat-block-blue">
                <div class="stat-label">Total Credits</div>
                <div class="stat-val"><?php echo $total_credits; ?></div>
                <div class="stat-sub">Semester <?php echo $current_sem; ?></div>
                <div class="circle-bg"></div>
            </div>
        </div>

        <?php if (!$selected_course): ?>
        
        <div style="margin-bottom:20px; border-bottom:1px solid #e0e0e0;">
            <button class="tab-btn active" onclick="switchTab('subjects')">Subject-wise</button>
            <button class="tab-btn" onclick="switchTab('results')">Semester Results</button>
            <button class="tab-btn" onclick="switchTab('marks-summary')">Marks</button>
            <button class="tab-btn" onclick="switchTab('issues')">Issues (<?php echo count($marks_issues); ?>)</button>
        </div>

        <!-- SUBJECT CARDS VIEW -->
        <div class="tab-content active" id="tab-subjects">
            <h2 style="font-size:16px; color:#333; font-weight:700; margin-bottom:16px;">Select Subject to View Marks</h2>
            <?php if (empty($marks)): ?>
                <div class="empty-state"><i class="fa fa-bar-chart"></i><p>No marks data for Semester <?php echo $current_sem; ?>.</p></div>
            <?php else: ?>
            <div class="subjects-grid">
                <?php foreach ($marks as $m): ?>
                <a href="?year=<?php echo $selected_year; ?>&sem_type=<?php echo $selected_type; ?>&course=<?php echo urlencode($m['course_code']); ?>" class="subject-card">
                    <div class="code"><?php echo htmlspecialchars($m['course_code']); ?></div>
                    <div class="name"><?php echo htmlspecialchars($m['course_name']); ?></div>
                    <div class="faculty" style="margin-bottom:14px;"><i class="fa fa-user" style="color:#c2185b; margin-right:4px;"></i> <?php echo htmlspecialchars($m['faculty_name'] ?? 'Faculty TBA'); ?></div>
                    
                    <div style="font-size:13px; color:#888;">
                        <?php if (!empty($m['grade']) && $m['external'] > 0): ?>
                            <div>Int: <?php echo number_format($m['internal'], 1); ?> | Ext: <?php echo number_format($m['external'], 1); ?></div>
                            <div style="font-weight:700; color:#333; margin-top:2px;">Total: <?php echo number_format($m['total'], 1); ?> | Grade: <span style="color:#c2185b;"><?php echo htmlspecialchars($m['grade']); ?></span></div>
                        <?php else: ?>
                            <div>Int: <?php echo number_format($m['internal'], 1); ?> | Ext: -</div>
                            <div style="font-weight:700; color:#333; margin-top:2px;">Total: <?php echo number_format($m['internal'], 1); ?> | Grade: -</div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Full Results Table -->
        <div class="tab-content" id="tab-results">
            <div class="card" style="padding:0; border:1px solid #eaeaea; border-radius:10px; overflow:hidden;">
                <h2 style="padding:20px; margin:0; border-bottom:1px solid #eaeaea; font-size:16px; color:#333;">Semester Results</h2>
                <?php if (empty($marks)): ?>
                    <div class="empty-state"><i class="fa fa-bar-chart"></i><p>No marks data.</p></div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                    <table class="result-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Internal</th>
                                <th>External</th>
                                <th>Total</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marks as $m): 
                                $mw = isset($weight_map[$m['course_code']]) ? $weight_map[$m['course_code']] : 50;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($m['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($m['course_name']); ?></td>
                                <?php if (!empty($m['grade']) && $m['external'] > 0): ?>
                                    <td><strong style="color:#c2185b;"><?php echo number_format($m['internal'], 2); ?></strong></td>
                                    <td><strong><?php echo number_format($m['external'], 2); ?></strong></td>
                                    <td><strong><?php echo number_format($m['total'], 2); ?></strong></td>
                                    <td><span style="background:#e8f5e9; color:#2e7d32; padding:3px 8px; border-radius:4px; font-weight:700;"><?php echo htmlspecialchars($m['grade']); ?></span></td>
                                <?php else: ?>
                                    <td><strong style="color:#c2185b;"><?php echo number_format($m['internal'], 2); ?></strong></td>
                                    <td colspan="3" style="color:#999; font-style:italic; text-align:center;"><i class="fa fa-clock-o"></i> Total: <?php echo number_format($m['internal'], 2); ?> | Pending Final Grading</td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:#fafafa;">
                                <td colspan="2"><strong style="color:#333;">Aggregate Internal Marks</strong></td>
                                <td colspan="4"><strong style="color:#333;"><?php echo number_format($total_internal_all, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Marks Summary -->
        <div class="tab-content" id="tab-marks-summary">
            <div class="card" style="padding:0; border:1px solid #eaeaea; border-radius:10px; overflow:hidden;">
                <h2 style="padding:20px; margin:0; border-bottom:1px solid #eaeaea; font-size:16px; color:#333;">Detailed Marks Evaluation</h2>
                <?php if (empty($marks)): ?>
                    <div class="empty-state"><i class="fa fa-bar-chart"></i><p>No marks data.</p></div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                    <table class="result-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Internal Marks</th>
                                <th>Total Evaluation Conducted (Weightage)</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marks as $m): 
                                $stmt_eval = $pdo->prepare("SELECT SUM(weightage) as total_weightage FROM course_evaluation_components WHERE course_code = ?");
                                $stmt_eval->execute([$m['course_code']]);
                                $eval = $stmt_eval->fetch();
                                $total_weightage = $eval['total_weightage'] ?? 0;
                                $pct = $total_weightage > 0 ? ($m['internal'] / $total_weightage) * 100 : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($m['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($m['course_name']); ?></td>
                                <td><strong style="color:#c2185b;"><?php echo number_format($m['internal'], 2); ?></strong></td>
                                <td><strong><?php echo number_format($total_weightage, 2); ?></strong></td>
                                <td><strong style="color:#27ae60;"><?php echo number_format($pct, 2); ?>%</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Marks Issues -->
        <div class="tab-content" id="tab-issues">
            <div class="card">
                <h2 class="card-title">Reported Marks Issues</h2>
                <?php if (empty($marks_issues)): ?>
                    <div class="empty-state"><i class="fa fa-flag"></i><p>No marks issues reported.</p></div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Course</th><th>Exam Type</th><th>Description</th><th>Files</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($marks_issues as $i => $mi): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($mi['course_code']); ?></strong><br><small><?php echo htmlspecialchars($mi['course_name']); ?></small></td>
                                <td><?php echo htmlspecialchars($mi['exam_type']); ?></td>
                                <td><?php echo htmlspecialchars($mi['description']); ?></td>
                                  <td>
                                      <?php if ($mi['file_count'] > 0): 
                                          $stmt_fa = $pdo->prepare("SELECT file_name, file_path FROM file_attachments WHERE ref_type='marks_issue' AND ref_id=?");
                                          $stmt_fa->execute([$mi['id']]);
                                          $files = $stmt_fa->fetchAll();
                                          foreach ($files as $f): ?>
                                              <div style="margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                                                  <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; text-decoration:none;"><i class="fa fa-eye"></i> View</a>
                                                  <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" download style="color:#1565c0; font-size:11px; text-decoration:none;"><i class="fa fa-download"></i> DL</a>
                                              </div>
                                          <?php endforeach; 
                                      else: echo '-'; endif; ?>
                                  </td>
                                <td><span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $mi['status'])); ?>"><?php echo $mi['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="card form-section">
                <h3><i class="fa fa-plus-circle"></i> Report Marks Issue</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="report_marks_issue">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course</label>
                            <select class="form-control" name="course_code" required onchange="document.getElementById('cn_hidden').value=this.options[this.selectedIndex].getAttribute('data-name')||'';">
                                <option value="">Select course...</option>
                                <?php foreach ($marks as $m): ?><option value="<?php echo htmlspecialchars($m['course_code']); ?>" data-name="<?php echo htmlspecialchars($m['course_name']); ?>"><?php echo htmlspecialchars($m['course_code'] . ' – ' . $m['course_name']); ?></option><?php endforeach; ?>
                            </select>
                            <input type="hidden" name="course_name" id="cn_hidden">
                        </div>
                        <div class="form-group">
                            <label>Exam Type</label>
                            <select class="form-control" name="exam_type" required>
                                <option value="">Select...</option>
                                <option value="Assignment">Assignment</option><option value="Quiz">Quiz</option><option value="Mid-Term">Mid-Term</option><option value="End-Term">End-Term</option><option value="Lab">Lab</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;"><label>Description</label><textarea class="form-control" name="description" placeholder="Describe the marks discrepancy..." required></textarea></div>
                    <div class="form-group" style="margin-bottom:14px;"><label>Proof Files</label><input type="file" class="form-control" name="proof_files[]" accept=".pdf,.png,.jpg,.jpeg" multiple style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; background:#fafafa; cursor:pointer;" onchange="this.style.borderColor='#27ae60'; this.style.background='#f0fff4';"></div>
                    <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Issue</button>
                </form>
            </div>
        </div>

        <?php else: ?>
        
        <!-- SINGLE SUBJECT VIEW -->
        <?php
        $subj = null;
        foreach ($marks as $m) { if ($m['course_code'] === $selected_course) { $subj = $m; break; } }
        if ($subj):
        
        $stmt_comps = $pdo->prepare("SELECT c.*, s.scored_marks FROM course_evaluation_components c LEFT JOIN student_component_marks s ON c.id = s.component_id AND s.student_id = ? WHERE c.course_code = ? ORDER BY c.id");
        $stmt_comps->execute([$student_id, $selected_course]);
        $dynamic_comps = $stmt_comps->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <a href="marks.php?year=<?php echo $selected_year; ?>&sem_type=<?php echo $selected_type; ?>" style="display:inline-block; margin-bottom:16px; color:#c2185b; font-size:13px; font-weight:700; text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to All Subjects</a>
        
        <div style="background:#fff; border:1px solid #eaeaea; border-radius:10px; overflow:hidden; margin-bottom:20px; border-left:4px solid #27ae60;">
            <div style="padding:24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f8f9fa;">
                <div>
                    <div style="font-size:12px; color:#c2185b; font-weight:700; text-transform:uppercase; margin-bottom:4px;"><?php echo htmlspecialchars($subj['course_code']); ?></div>
                    <h2 style="margin:0 0 6px; font-size:22px; color:#333;"><?php echo htmlspecialchars($subj['course_name']); ?></h2>
                    <div style="font-size:13px; color:#c2185b;"><i class="fa fa-user"></i> <?php echo htmlspecialchars($subj['faculty_name'] ?? 'Faculty TBA'); ?></div>
                </div>
                <div style="text-align:right;">
                    <?php if (!empty($subj['grade']) && $subj['external'] > 0): ?>
                        <div style="font-size:42px; font-weight:700; color:#27ae60; line-height:1;"><?php echo htmlspecialchars($subj['grade']); ?></div>
                        <div style="font-size:11px; color:#888; text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-top:6px;">Final Grade</div>
                    <?php else: ?>
                        <div style="font-size:24px; font-weight:700; color:#999; line-height:1;"><i class="fa fa-clock-o"></i></div>
                        <div style="font-size:11px; color:#888; text-transform:uppercase; font-weight:600; letter-spacing:0.5px; margin-top:6px;">Pending</div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="background:#fafafa; display:grid; grid-template-columns:1fr 1fr 1fr; gap:1px; border-top:1px solid #eaeaea;">
                <div style="padding:20px; text-align:center; background:#fff; margin-bottom:-1px;">
                    <div style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px;">Internal Marks</div>
                    <div style="font-size:24px; font-weight:700; color:#333; margin-top:8px;"><?php echo number_format($subj['internal'], 1); ?></div>
                </div>
                <div style="padding:20px; text-align:center; background:#fff; margin-bottom:-1px;">
                    <div style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px;">External Marks</div>
                    <div style="font-size:24px; font-weight:700; color:#333; margin-top:8px;">
                        <?php echo (!empty($subj['grade']) && $subj['external'] > 0) ? number_format($subj['external'], 1) : '-'; ?>
                    </div>
                </div>
                <div style="padding:20px; text-align:center; background:#fff; margin-bottom:-1px;">
                    <div style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px;">Total</div>
                    <div style="font-size:24px; font-weight:700; color:#27ae60; margin-top:8px;">
                        <?php echo number_format($subj['internal'] + ((!empty($subj['grade']) && $subj['external'] > 0) ? $subj['external'] : 0), 1); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Component-wise Marks -->
        <div class="card" style="padding:0; border:1px solid #eaeaea; border-radius:10px; overflow:hidden;">
            <h2 style="padding:20px; margin:0; border-bottom:1px solid #eaeaea; font-size:15px; color:#333; display:flex; align-items:center; gap:8px;"><i class="fa fa-list-alt" style="color:#c2185b;"></i> Component-wise Marks</h2>
            <?php if (!empty($dynamic_comps)): ?>
                <div style="overflow-x: auto;">
                    <table class="result-table">
                        <thead>
                            <tr>
                                <th>Evaluation Component</th>
                                <th>Max Marks</th>
                                <th>Scored Marks</th>
                                <th>Weightage</th>
                                <th>Calculated Mark</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_max = 0; $total_score = 0; $total_weight = 0; $total_contrib = 0;
                            foreach ($dynamic_comps as $c):
                                $score = $c['scored_marks'] ?? 0;
                                $contrib = ($c['max_marks'] > 0) ? ($score / $c['max_marks']) * $c['weightage'] : 0;
                                
                                $total_max += $c['max_marks'];
                                $total_score += $score;
                                $total_weight += $c['weightage'];
                                $total_contrib += $contrib;
                            ?>
                            <tr>
                                <td style="color:#333; font-weight:600;"><?php echo htmlspecialchars($c['component_name']); ?></td>
                                <td><?php echo number_format($c['max_marks'], 1); ?></td>
                                <td><?php echo number_format($score, 1); ?></td>
                                <td><?php echo number_format($c['weightage'], 1); ?></td>
                                <td><strong style="color:#27ae60;"><?php echo number_format($contrib, 2); ?></strong></td>
                                <td>
                                    <button class="report-btn" onclick="document.getElementById('report-form-container').style.display='block'; document.querySelector('#report-form-container select[name=exam_type]').innerHTML += '<option value=\'<?php echo htmlspecialchars($c['component_name']); ?>\' selected><?php echo htmlspecialchars($c['component_name']); ?></option>'; document.querySelector('#report-form-container select[name=course_code]').value='<?php echo htmlspecialchars($selected_course); ?>'; document.getElementById('cn_hidden_single').value='<?php echo htmlspecialchars($subj['course_name']); ?>'; document.getElementById('report-form-container').scrollIntoView({behavior: 'smooth'});">
                                        <i class="fa fa-flag"></i> Report
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:#fafafa;">
                                <td><strong style="color:#333;">Total Aggregate</strong></td>
                                <td><strong style="color:#333;"><?php echo number_format($total_max, 1); ?></strong></td>
                                <td><strong style="color:#333;"><?php echo number_format($total_score, 1); ?></strong></td>
                                <td><strong style="color:#333;"><?php echo number_format($total_weight, 1); ?></strong></td>
                                <td><strong style="color:#c2185b; font-size:16px;"><?php echo number_format($total_contrib, 2); ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:16px; color:#888; font-size:13px; text-align:center;">
                    <p>Component-wise marks breakdown not defined for this course.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Inline Report Form for Single Subject View -->
        <div class="card form-section" id="report-form-container" style="display:none; margin-top:20px;">
            <h3><i class="fa fa-plus-circle"></i> Report Marks Issue</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="report_marks_issue">
                <div class="form-row">
                    <div class="form-group">
                        <label>Course</label>
                        <select class="form-control" name="course_code" required>
                            <option value="<?php echo htmlspecialchars($selected_course); ?>" selected><?php echo htmlspecialchars($selected_course . ' – ' . $subj['course_name']); ?></option>
                        </select>
                        <input type="hidden" name="course_name" id="cn_hidden_single" value="<?php echo htmlspecialchars($subj['course_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Exam Type</label>
                        <select class="form-control" name="exam_type" required>
                            <option value="">Select...</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;"><label>Description</label><textarea class="form-control" name="description" placeholder="Describe the marks discrepancy..." required></textarea></div>
                <div class="form-group" style="margin-bottom:14px;"><label>Proof Files</label><input type="file" class="form-control" name="proof_files[]" accept=".pdf,.png,.jpg,.jpeg" multiple style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; background:#fafafa; cursor:pointer;" onchange="this.style.borderColor='#27ae60'; this.style.background='#f0fff4';"></div>
                <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Issue</button>
            </form>
        </div>
        
        <?php else: ?>
            <div class="card"><p>Subject not found.</p></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <script>
    function changeSemester() {
        var y = document.getElementById('semYear').value;
        var t = document.getElementById('semType').value;
        window.location.href = 'marks.php?year=' + y + '&sem_type=' + t;
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

