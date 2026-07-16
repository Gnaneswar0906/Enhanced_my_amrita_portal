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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'report_attendance') {
        $course = trim($_POST['course_code'] ?? '');
        $cname  = trim($_POST['course_name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        if ($course && $desc) {
            $stmt = $pdo->prepare('INSERT INTO attendance_issues (student_id, course_code, course_name, description) VALUES (?, ?, ?, ?)');
            $stmt->execute([$student_id, $course, $cname, $desc]);
            $issue_id = $pdo->lastInsertId();
            if (!empty($_FILES['proof_files']['name'][0])) {
                foreach ($_FILES['proof_files']['name'] as $key => $fname) {
                    if ($_FILES['proof_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($fname, PATHINFO_EXTENSION);
                        $newName = 'att_proof_' . $issue_id . '_' . ($key+1) . '.' . $ext;
                        $dest = '../uploads/proofs/' . $newName;
                        @mkdir('../uploads/proofs', 0777, true);
                        move_uploaded_file($_FILES['proof_files']['tmp_name'][$key], $dest);
                        $pdo->prepare('INSERT INTO file_attachments (ref_type, ref_id, file_name, file_path) VALUES (?, ?, ?, ?)')->execute(['attendance_issue', $issue_id, $fname, '/uploads/proofs/' . $newName]);
                    }
                }
            }
            $msg = 'success';
        } else { $msg = 'error'; }
    }
}

// Fetch attendance with faculty from timetable
$stmt = $pdo->prepare('SELECT a.*, (SELECT GROUP_CONCAT(DISTINCT t.faculty_name SEPARATOR ", ") FROM timetable t WHERE t.student_id = a.student_id AND t.course_code = a.course_code AND t.faculty_name != "") as faculty_name FROM attendance a WHERE a.student_id = ? AND a.semester = ? ORDER BY a.course_code');
$stmt->execute([$student_id, $current_sem]);
$attendance = $stmt->fetchAll();

// Get attendance alerts
$att_alerts = [];
try { $stmt2 = $pdo->prepare('SELECT * FROM attendance_alerts WHERE student_id = ? AND is_read = 0'); $stmt2->execute([$student_id]); $att_alerts = $stmt2->fetchAll(); } catch(Exception $e) {}

// Get attendance issues
$att_issues = [];
try { $stmt3 = $pdo->prepare('SELECT ai.*, (SELECT COUNT(*) FROM file_attachments fa WHERE fa.ref_type="attendance_issue" AND fa.ref_id=ai.id) as file_count FROM attendance_issues ai WHERE ai.student_id = ? ORDER BY ai.created_at DESC'); $stmt3->execute([$student_id]); $att_issues = $stmt3->fetchAll(); } catch(Exception $e) {}

// Get absent records with period
$absent_records = [];
try {
    $stmt4 = $pdo->prepare('SELECT * FROM attendance_records WHERE student_id = ? AND status = "Absent" ORDER BY course_code, date DESC');
    $stmt4->execute([$student_id]);
    $abs_all = $stmt4->fetchAll();
    foreach ($abs_all as $r) {
        $key = $r['course_code'];
        if (!isset($absent_records[$key])) $absent_records[$key] = [];
        $absent_records[$key][] = $r;
    }
} catch(Exception $e) {}

$overall_attended = 0; $overall_total = 0;
foreach ($attendance as $a) { $overall_attended += ($a['attended'] ?? 0); $overall_total += ($a['total_classes'] ?? 0); }
$overall_pct = $overall_total > 0 ? ($overall_attended / $overall_total) * 100 : 0;

// Enrollment for class name
$enrollment = $_SESSION['enrollment_no'];
$selected_course = $_GET['course'] ?? '';
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .semester-selector { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
        .semester-selector label { font-size:12px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-right:4px; }
        .semester-selector select { padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; }
        .semester-selector .sem-badge { padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600; }
        .att-summary-table { width:100%; border-collapse:collapse; font-size:13px; }
        .att-summary-table th { background:#1a1a2e; color:#fff; padding:12px 10px; font-size:11px; font-weight:600; text-align:center; text-transform:uppercase; letter-spacing:0.4px; white-space:nowrap; }
        .att-summary-table td { padding:10px; border:1px solid #e0e0e0; text-align:center; vertical-align:middle; }
        .att-summary-table tbody tr:hover { background:#f8f9fa; }
        .att-summary-table .course-cell { text-align:left; }
        .att-summary-table .faculty-cell { text-align:left; font-size:12px; }
        .pct-good { color:#27ae60; font-weight:700; }
        .pct-warn { color:#f39c12; font-weight:700; background:#fff8e1; }
        .pct-bad { color:#fff; font-weight:700; background:#e74c3c; }
        .btn-report { background: transparent; color: #e74c3c; border: none; font-size: 11px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 4px; text-decoration: none; border: 1px solid #e74c3c; transition: 0.2s; white-space: nowrap; }
        .btn-report:hover { background: #e74c3c; color: white; }
        .absent-inner-table { width:100%; border-collapse:collapse; font-size:12px; }
        .absent-inner-table th { background:#eee; padding:4px 8px; font-size:10px; text-transform:uppercase; font-weight:600; color:#666; }
        .absent-inner-table td { padding:4px 8px; border-bottom:1px solid #f0f0f0; font-size:12px; }
        .class-name-cell { font-size:11px; color:#555; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Class Attendance</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar-check-o"></i> Class Attendance</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Attendance issue reported successfully!</div>
        <?php elseif ($msg === 'error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all fields.</div><?php endif; ?>

        <!-- Attendance Alerts -->
        <?php
        $critical_subjects = []; $warning_subjects = [];
        foreach ($attendance as $a) {
            $pct = ($a['total_classes'] ?? 0) > 0 ? (($a['attended'] ?? 0) / ($a['total_classes'] ?? 1)) * 100 : 100;
            if ($pct < 75) $critical_subjects[] = $a;
            elseif ($pct < 80) $warning_subjects[] = $a;
        }
        ?>
        <?php if (!empty($critical_subjects)): ?>
        <div style="background:linear-gradient(135deg,#fde8e8,#f8d7da); border:1px solid #f5c6c6; border-left:5px solid #c0392b; border-radius:10px; padding:16px 20px; margin-bottom:14px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <i class="fa fa-exclamation-triangle" style="font-size:20px; color:#c0392b;"></i>
                <strong style="color:#721c24; font-size:14px;">CRITICAL: Attendance less than 75%! You will get FA (Forced Absence).</strong>
            </div>
            <?php foreach ($critical_subjects as $cs): $cpct = ($cs['total_classes'] > 0) ? ($cs['attended'] / $cs['total_classes']) * 100 : 0; ?>
            <div style="background:#fff; border-radius:8px; padding:10px 14px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                <div><strong style="color:#c0392b;"><?php echo htmlspecialchars($cs['course_code']); ?></strong> — <?php echo htmlspecialchars($cs['course_name']); ?></div>
                <div style="font-weight:700; color:#c0392b; font-size:16px;"><?php echo number_format($cpct, 1); ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($warning_subjects)): ?>
        <div style="background:linear-gradient(135deg,#fff8e6,#fff3cd); border:1px solid #f0d060; border-left:5px solid #e67e22; border-radius:10px; padding:16px 20px; margin-bottom:14px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <i class="fa fa-exclamation-circle" style="font-size:20px; color:#e67e22;"></i>
                <strong style="color:#856404; font-size:14px;">WARNING: Maintain attendance above 80%. Don't let it drop below 75%.</strong>
            </div>
            <?php foreach ($warning_subjects as $ws): $wpct = ($ws['total_classes'] > 0) ? ($ws['attended'] / $ws['total_classes']) * 100 : 0; ?>
            <div style="background:#fff; border-radius:8px; padding:10px 14px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                <div><strong style="color:#e67e22;"><?php echo htmlspecialchars($ws['course_code']); ?></strong> — <?php echo htmlspecialchars($ws['course_name']); ?></div>
                <div style="font-weight:700; color:#e67e22; font-size:16px;"><?php echo number_format($wpct, 1); ?>%</div>
            </div>
            <?php endforeach; ?>
        </div>
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

        <!-- Overall Stats -->
        <div class="sgpa-display">
            <div class="sgpa-card"><div class="sgpa-label">Overall Attendance</div><div class="sgpa-value"><?php echo number_format($overall_pct, 1); ?>%</div><div class="sgpa-sub"><?php echo $overall_attended; ?> / <?php echo $overall_total; ?> classes</div></div>
            <div class="sgpa-card secondary"><div class="sgpa-label">Subjects</div><div class="sgpa-value"><?php echo count($attendance); ?></div><div class="sgpa-sub">Semester <?php echo $current_sem; ?></div></div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('summary')"><i class="fa fa-table"></i> Summary</button>
            <button class="tab-btn" onclick="switchTab('absent')"><i class="fa fa-calendar-times-o"></i> Absent Dates</button>
            <button class="tab-btn" onclick="switchTab('issues')"><i class="fa fa-flag"></i> Issues (<?php echo count($att_issues); ?>)</button>
        </div>

        <!-- ========== SUMMARY TABLE ========== -->
        <div class="tab-content active" id="tab-summary">
        <div class="card">
            <h2 class="card-title"><i class="fa fa-table" style="color:#a4123f;"></i> Attendance Summary</h2>
            <?php if (empty($attendance)): ?>
                <div class="empty-state"><i class="fa fa-calendar-check-o"></i><p>No attendance data for Semester <?php echo $current_sem; ?>.</p></div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="att-summary-table">
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>Class Name</th>
                        <th>Course</th>
                        <th>Faculty</th>
                        <th>Total</th>
                        <th>Present</th>
                        <th>Duty Leave</th>
                        <th>Percentage</th>
                        <th>Medical</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($attendance as $i => $a):
                    $pct = $a['total_classes'] > 0 ? ($a['attended'] / $a['total_classes']) * 100 : 0;
                    $absent = $a['total_classes'] - $a['attended'];
                    $duty = $a['duty_leave'] ?? 0;
                    $medical = $a['medical_leave'] ?? 0;
                    $class_name = 'B.Tech..2023.R.CSE.2.' . $a['course_code'];
                    $pct_class = $pct >= 85 ? 'pct-good' : ($pct >= 75 ? 'pct-warn' : 'pct-bad');
                    $faculty = $a['faculty_name'] ?? 'TBA';
                ?>
                <tr>
                    <td><strong><?php echo $i + 1; ?></strong></td>
                    <td class="class-name-cell"><?php echo $class_name; ?></td>
                    <td class="course-cell">
                        <div style="font-size:12px; color:#a4123f; font-weight:700;"><?php echo htmlspecialchars($a['course_code']); ?></div>
                        <div style="font-size:13px; font-weight:600;"><?php echo htmlspecialchars($a['course_name']); ?></div>
                    </td>
                    <td class="faculty-cell"><?php echo nl2br(htmlspecialchars(str_replace(',', "\n", $faculty))); ?></td>
                    <td><strong><?php echo $a['total_classes']; ?></strong></td>
                    <td><strong style="color:#27ae60;"><?php echo $a['attended']; ?></strong></td>
                    <td><?php echo $duty; ?></td>
                    <td><span class="<?php echo $pct_class; ?>" style="padding:4px 10px; border-radius:4px; display:inline-block;"><?php echo number_format($pct, 2); ?></span></td>
                    <td><?php echo $medical; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        </div>

        <!-- ========== ABSENT DATES TABLE ========== -->
        <div class="tab-content" id="tab-absent">
        <div class="card">
            <h2 class="card-title"><i class="fa fa-calendar-times-o" style="color:#e74c3c;"></i> Absent Dates by Subject</h2>
            <?php if (empty($attendance)): ?>
                <div class="empty-state"><i class="fa fa-calendar-check-o"></i><p>No data.</p></div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="att-summary-table">
                <thead>
                    <tr><th>Sl No</th><th>Class Name</th><th>Course</th><th>Faculty</th><th>Absent Dates</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($attendance as $i => $a):
                    $class_name = 'B.Tech..2023.R.CSE.2.' . $a['course_code'];
                    $faculty = $a['faculty_name'] ?? 'TBA';
                    $abs_recs = $absent_records[$a['course_code']] ?? [];
                ?>
                <tr>
                    <td><strong><?php echo $i + 1; ?></strong></td>
                    <td class="class-name-cell"><?php echo $class_name; ?></td>
                    <td class="course-cell">
                        <div style="font-size:12px; color:#a4123f; font-weight:700;"><?php echo htmlspecialchars($a['course_code']); ?></div>
                        <div style="font-size:13px; font-weight:600;"><?php echo htmlspecialchars($a['course_name']); ?></div>
                    </td>
                    <td class="faculty-cell"><?php echo nl2br(htmlspecialchars(str_replace(',', "\n", $faculty))); ?></td>
                    <td style="padding:6px;">
                        <?php if (empty($abs_recs) && ($a['total_classes'] - $a['attended']) == 0): ?>
                            <span style="color:#27ae60; font-size:12px;"><i class="fa fa-check-circle"></i> No absents</span>
                        <?php else: ?>
                        <table class="absent-inner-table">
                            <thead><tr><th>Sl No</th><th>Date</th><th>Period</th></tr></thead>
                            <tbody>
                            <?php foreach ($abs_recs as $ai => $ar): ?>
                            <tr>
                                <td><?php echo $ai + 1; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($ar['date'])); ?></td>
                                <td><?php echo $ar['period_number'] ?? '-'; ?></td>
                            </tr>
                            <?php endforeach; 
                            $actual_absent = $a['total_classes'] - $a['attended'];
                            $recorded_absent = count($abs_recs);
                            if ($actual_absent > $recorded_absent):
                                $diff = $actual_absent - $recorded_absent;
                                for ($k=0; $k<$diff; $k++):
                            ?>
                            <tr>
                                <td><?php echo $recorded_absent + $k + 1; ?></td>
                                <td style="color:#e74c3c; font-style:italic;">Date not recorded</td>
                                <td>-</td>
                            </tr>
                            <?php endfor; endif; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($abs_recs)): ?>
                        <button class="btn-report" onclick="switchTab('issues'); document.querySelector('[name=course_code]').value='<?php echo htmlspecialchars($a['course_code']); ?>'; document.getElementById('att_cn').value='<?php echo htmlspecialchars($a['course_name']); ?>'; document.querySelector('[name=course_code]').scrollIntoView({behavior: 'smooth'});">
                            <i class="fa fa-flag"></i> Report Issue
                        </button>
                        <?php else: ?>
                        <span style="color:#888; font-size:11px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        </div>

        <!-- ========== ISSUES TAB ========== -->
        <div class="tab-content" id="tab-issues">
            <?php if (!empty($att_issues)): ?>
            <div class="card">
                <h2 class="card-title">Reported Issues</h2>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Course</th><th>Description</th><th>Files</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($att_issues as $i => $ai): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($ai['course_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($ai['description']); ?></td>
                                  <td>
                                      <?php if ($ai['file_count'] > 0): 
                                          $stmt_fa = $pdo->prepare("SELECT file_name, file_path FROM file_attachments WHERE ref_type='attendance_issue' AND ref_id=?");
                                          $stmt_fa->execute([$ai['id']]);
                                          $files = $stmt_fa->fetchAll();
                                          foreach ($files as $f): ?>
                                              <div style="margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                                                  <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; text-decoration:none;"><i class="fa fa-eye"></i> View</a>
                                                  <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" download style="color:#1565c0; font-size:11px; text-decoration:none;"><i class="fa fa-download"></i> DL</a>
                                              </div>
                                          <?php endforeach; 
                                      else: echo '-'; endif; ?>
                                  </td>
                            <td><span class="badge badge-<?php echo strtolower(str_replace(' ','-',$ai['status'])); ?>"><?php echo $ai['status']; ?></span></td>
                            <td><?php echo date('d M Y', strtotime($ai['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card"><div class="empty-state"><i class="fa fa-flag"></i><p>No issues reported.</p></div></div>
            <?php endif; ?>

            <div class="card form-section">
                <h3><i class="fa fa-plus-circle"></i> Report Attendance Issue</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="report_attendance">
                    <div class="form-row"><div class="form-group">
                        <label>Course</label>
                        <select class="form-control" name="course_code" required onchange="document.getElementById('att_cn').value=this.options[this.selectedIndex].getAttribute('data-name')||'';">
                            <option value="">Select course...</option>
                            <?php foreach ($attendance as $a): ?><option value="<?php echo htmlspecialchars($a['course_code']); ?>" data-name="<?php echo htmlspecialchars($a['course_name']); ?>"><?php echo htmlspecialchars($a['course_code'] . ' – ' . $a['course_name']); ?></option><?php endforeach; ?>
                        </select>
                        <input type="hidden" name="course_name" id="att_cn">
                    </div></div>
                    <div class="form-group" style="margin-bottom:14px;"><label>Description</label><textarea class="form-control" name="description" placeholder="Describe the attendance issue..." required></textarea></div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Proof Files (Multiple allowed)</label>
                        <input type="file" class="form-control" name="proof_files[]" accept=".pdf,.png,.jpg,.jpeg" multiple style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; background:#fafafa; cursor:pointer; width:100%;">
                    </div>
                    <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Issue</button>
                </form>
            </div>
        </div>
    </div>
    <script>
    function changeSemester() {
        var y = document.getElementById('semYear').value;
        var t = document.getElementById('semType').value;
        window.location.href = 'attendance.php?year=' + y + '&sem_type=' + t;
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

