<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'register_supp') {
        $code = trim($_POST['course_code'] ?? '');
        $cname = trim($_POST['course_name'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($code) {
            // Check if already registered
            $chk = $pdo->prepare('SELECT id FROM supplementary_registrations WHERE student_id = ? AND course_code = ?');
            $chk->execute([$student_id, $code]);
            if (!$chk->fetch()) {
                $pdo->prepare('INSERT INTO supplementary_registrations (student_id, course_code, course_name, reason) VALUES (?,?,?,?)')->execute([$student_id, $code, $cname, $reason]);
                $msg = 'registered';
            } else { $msg = 'already'; }
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM supplementary WHERE student_id = ? ORDER BY exam_date');
$stmt->execute([$student_id]);
$supps = $stmt->fetchAll();

$regs = [];
try {
    $stmt2 = $pdo->prepare('SELECT * FROM supplementary_registrations WHERE student_id = ? ORDER BY created_at DESC');
    $stmt2->execute([$student_id]);
    $regs = $stmt2->fetchAll();
} catch(Exception $e) {}

// Get failed courses for registration
$failed = [];
try {
    $stmt3 = $pdo->prepare("SELECT * FROM marks WHERE student_id = ? AND (grade = 'F' OR grade = 'P')");
    $stmt3->execute([$student_id]);
    $failed = $stmt3->fetchAll();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Supplementary</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Supplementary</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-plus-square"></i> Supplementary Exams</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'registered'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Registration submitted! Admin will review and assign schedule.</div>
        <?php elseif ($msg === 'already'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> You have already registered for this course.</div>
        <?php endif; ?>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('exams')"><i class="fa fa-calendar"></i> Assigned Exams</button>
            <button class="tab-btn" onclick="switchTab('regs')"><i class="fa fa-pencil-square-o"></i> My Registrations (<?php echo count($regs); ?>)</button>
            <button class="tab-btn" onclick="switchTab('register')"><i class="fa fa-plus-circle"></i> Register</button>
        </div>

        <!-- TAB 1: Assigned Exams -->
        <div class="tab-content active" id="tab-exams">
            <div class="card">
                <h2 class="card-title">Supplementary Exam Schedule</h2>
                <?php if (empty($supps)): ?>
                    <div class="empty-state"><i class="fa fa-plus-square"></i><p>No supplementary exams assigned.</p></div>
                <?php else: ?>
                    <?php foreach ($supps as $s): ?>
                    <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:18px; margin-bottom:14px; border-left:4px solid <?php echo $s['status']==='Registered'?'#27ae60':($s['status']==='Completed'?'#3498db':'#f5a623'); ?>;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div>
                                <div style="font-size:12px; color:#a4123f; font-weight:700;"><?php echo htmlspecialchars($s['course_code']); ?></div>
                                <strong style="font-size:15px;"><?php echo htmlspecialchars($s['course_name']); ?></strong>
                            </div>
                            <span class="badge badge-<?php echo strtolower(str_replace(' ','-',$s['status'])); ?>"><?php echo $s['status']; ?></span>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px; font-size:13px; color:#555;">
                            <div><strong style="font-size:10px; color:#888; text-transform:uppercase;">Exam Date</strong><br><?php echo date('d M Y', strtotime($s['exam_date'])); ?></div>
                            <div><strong style="font-size:10px; color:#888; text-transform:uppercase;">Teacher</strong><br><?php echo htmlspecialchars($s['assigned_teacher'] ?? 'TBA'); ?></div>
                            <div><strong style="font-size:10px; color:#888; text-transform:uppercase;">Time</strong><br><?php echo htmlspecialchars($s['schedule_time'] ?? 'TBA'); ?></div>
                            <div><strong style="font-size:10px; color:#888; text-transform:uppercase;">Classroom</strong><br><?php echo htmlspecialchars($s['classroom'] ?? 'TBA'); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 2: My Registrations -->
        <div class="tab-content" id="tab-regs">
            <div class="card">
                <h2 class="card-title">My Registrations</h2>
                <?php if (empty($regs)): ?>
                    <div class="empty-state"><i class="fa fa-pencil-square-o"></i><p>No registrations yet.</p></div>
                <?php else: ?>
                    <?php foreach ($regs as $r): ?>
                    <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:18px; margin-bottom:14px; border-left:4px solid <?php echo $r['status']==='Accepted'?'#27ae60':($r['status']==='Rejected'?'#e74c3c':'#f5a623'); ?>;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div>
                                <div style="font-size:12px; color:#a4123f; font-weight:700;"><?php echo htmlspecialchars($r['course_code']); ?></div>
                                <strong style="font-size:15px;"><?php echo htmlspecialchars($r['course_name']); ?></strong>
                            </div>
                            <span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo $r['status']; ?></span>
                        </div>
                        <?php if ($r['status'] === 'Accepted'): ?>
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; font-size:13px; color:#555; margin-top:8px;">
                                <div><i class="fa fa-user" style="color:#a4123f;"></i> <?php echo htmlspecialchars($r['assigned_teacher'] ?? 'TBA'); ?></div>
                                <div><i class="fa fa-clock-o" style="color:#a4123f;"></i> <?php echo htmlspecialchars($r['exam_time'] ?? 'TBA'); ?></div>
                                <div><i class="fa fa-building" style="color:#a4123f;"></i> <?php echo htmlspecialchars($r['classroom'] ?? 'TBA'); ?></div>
                            </div>
                            <?php if (!empty($r['schedule_details'])): ?>
                            <div style="margin-top:8px; padding:10px; background:#e8f5e9; border-radius:8px; font-size:12px; color:#2e7d32;">
                                <i class="fa fa-info-circle"></i> <strong>Schedule:</strong> <?php echo htmlspecialchars($r['schedule_details']); ?>
                            </div>
                            <?php endif; ?>
                        <?php elseif ($r['status'] === 'Rejected' && $r['reject_reason']): ?>
                            <div style="margin-top:8px; padding:10px; background:#fde8e8; border-radius:8px; font-size:12px; color:#721c24;">
                                <i class="fa fa-exclamation-triangle"></i> <strong>Reason:</strong> <?php echo htmlspecialchars($r['reject_reason']); ?>
                            </div>
                        <?php endif; ?>
                        <div style="font-size:11px; color:#888; margin-top:6px;">Submitted: <?php echo date('d M Y', strtotime($r['created_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 3: Register -->
        <div class="tab-content" id="tab-register">
            <div class="card form-section">
                <h3><i class="fa fa-plus-circle"></i> Register for Supplementary Exam</h3>
                <?php if (empty($failed)): ?>
                    <div class="alert-banner info"><i class="fa fa-info-circle"></i><span class="alert-text">No courses require supplementary registration at this time.</span></div>
                <?php else: ?>
                    <p style="font-size:13px; color:#666; margin-bottom:16px;">Register for courses where you need to retake the exam. Admin will review and assign teacher, schedule, and classroom.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="register_supp">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Course</label>
                                <select class="form-control" name="course_code" required onchange="document.getElementById('supp_cn').value=this.options[this.selectedIndex].getAttribute('data-name')||'';">
                                    <option value="">Select course...</option>
                                    <?php foreach ($failed as $f): ?>
                                    <option value="<?php echo htmlspecialchars($f['course_code']); ?>" data-name="<?php echo htmlspecialchars($f['course_name']); ?>"><?php echo htmlspecialchars($f['course_code'] . ' – ' . $f['course_name'] . ' (Grade: ' . $f['grade'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="course_name" id="supp_cn">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:14px;">
                            <label>Reason for Supplementary</label>
                            <textarea class="form-control" name="reason" placeholder="Explain why you need to retake this exam (e.g. Failed in end semester, low attendance, etc.)" required></textarea>
                        </div>
                        <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Registration</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');
    }
    </script>
</body>
</html>
