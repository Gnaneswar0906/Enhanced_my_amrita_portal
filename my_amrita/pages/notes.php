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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_doubt') {
        $course = trim($_POST['course_code'] ?? '');
        $note_id = intval($_POST['note_id'] ?? 0);
        $issue   = trim($_POST['issue_text'] ?? '');
        if ($course && $note_id && $issue) {
            $pdo->prepare('INSERT INTO notes_doubts (student_id, course_code, note_id, issue_text) VALUES (?,?,?,?)')->execute([$student_id, $course, $note_id, $issue]);
            $msg = 'doubt_success';
        } else { $msg = 'doubt_error'; }
    }
}

$stmt = $pdo->prepare('SELECT n.*, (SELECT DISTINCT t.faculty_name FROM timetable t WHERE t.student_id = n.student_id AND t.course_code = n.course_code LIMIT 1) as faculty_name FROM notes n WHERE n.student_id = ? AND n.semester = ? ORDER BY n.course_code, n.uploaded_at DESC');
$stmt->execute([$student_id, $current_sem]);
$notes = $stmt->fetchAll();

// Get doubts
$doubts = [];
try {
    $stmt2 = $pdo->prepare('SELECT nd.*, n.title as note_title, (SELECT DISTINCT t.faculty_name FROM timetable t WHERE t.student_id = nd.student_id AND t.course_code = nd.course_code LIMIT 1) as faculty_name FROM notes_doubts nd JOIN notes n ON nd.note_id = n.id WHERE nd.student_id = ? ORDER BY nd.created_at DESC');
    $stmt2->execute([$student_id]);
    $doubts = $stmt2->fetchAll();
} catch(Exception $e) {}

// Get distinct courses
$courses_list = [];
foreach ($notes as $n) {
    if (!isset($courses_list[$n['course_code']])) {
        $courses_list[$n['course_code']] = ['name' => $n['course_name'], 'faculty' => $n['faculty_name'] ?? 'Faculty TBA', 'count' => 0];
    }
    $courses_list[$n['course_code']]['count']++;
}

$selected = $_GET['course'] ?? '';
$categories = ['Class Notes', 'Lab', 'Case Studies', 'Reference Books', 'Other'];
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Course Notes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .subject-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; cursor:pointer; transition:all 0.25s; text-decoration:none; color:#333; display:block; }
        .subject-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(164,18,63,0.12); border-color:#d4264f; }
        .subjects-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:14px; }
        .cat-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600; }
        .cat-badge.class-notes { background:#e8f5e9; color:#2e7d32; }
        .cat-badge.lab { background:#e3f2fd; color:#1565c0; }
        .cat-badge.case-studies { background:#fff3e0; color:#e65100; }
        .cat-badge.reference-books { background:#f3e5f5; color:#7b1fa2; }
        .cat-badge.other { background:#f5f5f5; color:#666; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> <?php if ($selected): ?><a href="notes.php">Course Notes</a> <span class="sep">/</span> <?php echo htmlspecialchars($selected); ?><?php else: ?>Course Notes<?php endif; ?></div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-book"></i> Course Notes</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <div class="alert-banner info"><i class="fa fa-info-circle"></i><span class="alert-text">Course notes are uploaded by faculty members. Contact your course instructor if you need additional materials.</span></div>

        <!-- Semester Selector -->
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px;">
            <label style="font-size:12px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-right:4px;">Academic Year:</label>
            <select onchange="changeSemester()" id="semYear" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; cursor:pointer;">
                <?php foreach (array_keys($semester_map) as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr === $selected_year ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>
            <label style="font-size:12px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-right:4px;">Semester:</label>
            <select onchange="changeSemester()" id="semType" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; cursor:pointer;">
                <option value="odd" <?php echo $selected_type === 'odd' ? 'selected' : ''; ?>>Odd</option>
                <option value="even" <?php echo $selected_type === 'even' ? 'selected' : ''; ?>>Even</option>
            </select>
            <span style="padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600;">Semester <?php echo $current_sem; ?></span>
        </div>

        <?php if ($msg === 'doubt_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Doubt submitted to teacher!</div>
        <?php endif; ?>

        <?php if (!$selected): ?>
        <!-- Subject Cards -->
        <div class="card">
            <h2 class="card-title">Select Subject</h2>
            <?php if (empty($courses_list)): ?>
                <div class="empty-state"><i class="fa fa-book"></i><p>No course notes available yet.</p></div>
            <?php else: ?>
            <div class="subjects-grid">
                <?php foreach ($courses_list as $code => $info): ?>
                <a href="?course=<?php echo urlencode($code); ?>" class="subject-card" style="border-left:4px solid #a4123f;">
                    <div style="font-size:12px; color:#a4123f; font-weight:700;"><?php echo htmlspecialchars($code); ?></div>
                    <div style="font-size:14px; font-weight:600; margin:4px 0;"><?php echo htmlspecialchars($info['name']); ?></div>
                    <div style="font-size:12px; color:#666;"><i class="fa fa-user" style="color:#a4123f; margin-right:4px;"></i> <?php echo htmlspecialchars($info['faculty']); ?></div>
                    <div style="margin-top:10px; font-size:12px; color:#a4123f; font-weight:600;"><i class="fa fa-file-text-o"></i> <?php echo $info['count']; ?> materials</div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Single Subject View -->
        <a href="notes.php" style="display:inline-block; margin-bottom:16px; color:#a4123f; font-size:13px; font-weight:600; text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to All Subjects</a>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('materials')"><i class="fa fa-file-text-o"></i> Materials</button>
            <button class="tab-btn" onclick="switchTab('doubts')"><i class="fa fa-question-circle"></i> Doubts / Issues</button>
        </div>

        <div class="tab-content active" id="tab-materials">
            <div class="card">
                <h2 class="card-title">Materials for <?php echo htmlspecialchars($selected); ?></h2>
                <?php
                $subj_notes = array_filter($notes, fn($n) => $n['course_code'] === $selected);
                if (empty($subj_notes)): ?>
                    <div class="empty-state"><i class="fa fa-book"></i><p>No materials for this subject.</p></div>
                <?php else: ?>
                    <!-- Category filter -->
                    <div style="margin-bottom:14px; display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="filter-btn active" onclick="filterNotes('all', this)">All</button>
                        <?php foreach ($categories as $cat): ?>
                        <button class="filter-btn" onclick="filterNotes('<?php echo strtolower(str_replace(' ','-',$cat)); ?>', this)"><?php echo $cat; ?></button>
                        <?php endforeach; ?>
                    </div>
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Title</th><th>Category</th><th>File</th><th>Uploaded</th></tr></thead>
                        <tbody>
                            <?php foreach (array_values($subj_notes) as $i => $n): ?>
                            <?php $catSlug = strtolower(str_replace(' ','-',$n['category'] ?? 'class-notes')); ?>
                            <tr class="note-row" data-cat="<?php echo $catSlug; ?>">
                                <td><?php echo $i + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($n['title']); ?></strong></td>
                                <td><span class="cat-badge <?php echo $catSlug; ?>"><?php echo htmlspecialchars($n['category'] ?? 'Class Notes'); ?></span></td>
                                <td>
                                    <a href="..<?php echo htmlspecialchars($n['file_path']); ?>" target="_blank" style="color:#2e7d32; font-size:11px; font-weight:600; padding:4px 10px; background:#e8f5e9; border-radius:6px; margin-right:4px; text-decoration:none;"><i class="fa fa-eye"></i> View</a>
                                    <a href="..<?php echo htmlspecialchars($n['file_path']); ?>" download style="color:#1565c0; font-size:11px; font-weight:600; padding:4px 10px; background:#e3f2fd; border-radius:6px; text-decoration:none;"><i class="fa fa-download"></i> Download</a>
                                </td>
                                <td><?php echo date('d M Y', strtotime($n['uploaded_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content" id="tab-doubts">
            <?php $subj_doubts = array_filter($doubts, fn($d) => $d['course_code'] === $selected); ?>
            <div class="card">
                <h2 class="card-title">My Doubts</h2>
                <?php if (empty($subj_doubts)): ?>
                    <div class="empty-state"><i class="fa fa-question-circle"></i><p>No doubts submitted.</p></div>
                <?php else: ?>
                    <?php foreach ($subj_doubts as $d): ?>
                    <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:16px; margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <strong style="font-size:14px;">Regarding: <?php echo htmlspecialchars($d['note_title']); ?></strong>
                            <span class="badge badge-<?php echo strtolower($d['status']); ?>"><?php echo $d['status']; ?></span>
                        </div>
                        <p style="font-size:13px; color:#555; margin:0;"><strong>Q: </strong><?php echo htmlspecialchars($d['issue_text']); ?></p>
                        <?php if ($d['response']): ?>
                        <div style="margin-top:10px; padding:10px; background:#e8f5e9; border-radius:8px; font-size:13px; color:#2e7d32;">
                            <i class="fa fa-reply"></i> <strong>Response by <?php echo htmlspecialchars($d['faculty_name'] ?? 'Teacher'); ?>:</strong> <?php echo htmlspecialchars($d['response']); ?>
                        </div>
                        <?php endif; ?>
                        <div style="font-size:11px; color:#888; margin-top:6px;"><?php echo date('d M Y, h:i A', strtotime($d['created_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card form-section">
                <h3><i class="fa fa-plus-circle"></i> Ask a Doubt</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="submit_doubt">
                    <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($selected); ?>">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Select Note / Material</label>
                        <select name="note_id" class="form-control" required>
                            <option value="">Select the material...</option>
                            <?php foreach ($subj_notes as $n): ?>
                                <option value="<?php echo $n['id']; ?>"><?php echo htmlspecialchars($n['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;"><label>Your Question</label><textarea class="form-control" name="issue_text" placeholder="Describe your doubt..." required></textarea></div>
                    <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Doubt</button>
                    <p style="font-size:11px; color:#888; margin-top:8px;"><i class="fa fa-info-circle"></i> This will be sent to <strong><?php echo htmlspecialchars($courses_list[$selected]['faculty'] ?? 'the teacher'); ?></strong>.</p>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script>
    function changeSemester() {
        var y = document.getElementById('semYear').value;
        var t = document.getElementById('semType').value;
        window.location.href = 'notes.php?year=' + y + '&sem_type=' + t;
    }
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');
    }
    function filterNotes(cat, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.note-row').forEach(r => { r.style.display = (cat === 'all' || r.getAttribute('data-cat') === cat) ? '' : 'none'; });
    }
    </script>
</body>
</html>
