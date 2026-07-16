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
$sem_label = $selected_type === 'even' ? 'EVEN' : 'ODD';

// Get student info
$st = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$st->execute([$student_id]);
$student = $st->fetch();
$roll_no = $student['enrollment_no'] ?? '';

// Get admit cards grouped by exam_type
$stmt = $pdo->prepare('SELECT * FROM admit_cards WHERE student_id = ? AND semester = ? ORDER BY course_code');
$stmt->execute([$student_id, $current_sem]);
$all_cards = $stmt->fetchAll();

$mid_cards = array_filter($all_cards, fn($c) => ($c['exam_type'] ?? '') === 'Mid');
$end_cards = array_filter($all_cards, fn($c) => ($c['exam_type'] ?? '') === 'End' || ($c['exam_type'] ?? '') !== 'Mid');
// If exam_type not set, separate by name
if (empty($mid_cards) && !empty($all_cards)) {
    $mid_cards = array_filter($all_cards, fn($c) => stripos($c['exam_name'], 'Mid') !== false);
    $end_cards = array_filter($all_cards, fn($c) => stripos($c['exam_name'], 'Mid') === false);
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Admit Card</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .admit-wrapper { max-width:750px; margin:0 auto 30px; background:#fff; border:2px solid #333; padding:36px 40px; font-family:'Times New Roman',serif; }
        .admit-header { text-align:center; margin-bottom:20px; }
        .admit-header h2 { margin:0; font-size:22px; font-weight:bold; color:#000; font-family:'Times New Roman',serif; }
        .admit-header h3 { margin:4px 0 0; font-size:17px; font-weight:bold; color:#000; font-style:italic; }
        .admit-header .exam-title { margin:12px 0 4px; font-size:15px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; }
        .admit-header .term-info { font-size:13px; font-weight:bold; margin-top:2px; }
        .admit-info-row { display:flex; justify-content:space-between; margin:6px 0; font-size:14px; }
        .admit-info-row .admit-label { font-weight:bold; min-width:120px; color:#000; }
        .admit-info-row .colon { margin:0 6px; }
        .exam-table { width:100%; border-collapse:collapse; margin:16px 0; font-size:13px; }
        .exam-table th, .exam-table td { border:1px solid #333; padding:8px 10px; text-align:center; }
        .exam-table th { background:#f0f0f0; font-weight:bold; font-size:12px; }
        .exam-table td { font-size:13px; }
        .exam-table td.left { text-align:left; }
        .admit-note { font-size:12px; margin-top:16px; line-height:1.6; }
        .admit-note strong { display:block; margin-bottom:4px; }
        .admit-note ol { padding-left:18px; margin:4px 0; }
        .admit-note ol li { margin-bottom:4px; }
        .signature-area { text-align:right; margin-top:24px; font-size:13px; }
        .signature-area .sign-line { margin-bottom:4px; font-style:italic; font-size:18px; }
        @media print {
            .no-print { display:none !important; }
            .admit-wrapper { border:2px solid #000; box-shadow:none; margin:0 auto; }
            body { background:#fff !important; }
            .main-content { padding:0 !important; }
        }
    </style>
</head>
<body>
    <nav class="top-navbar no-print"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar no-print"><a href="../home.php">Home</a> <span class="sep">/</span> Admit Card</div>
    <div class="main-content">
        <div class="page-header no-print">
            <h1><i class="fa fa-file-text-o"></i> Admit Cards</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- Semester Selector -->
        <div class="no-print" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px;">
            <label style="font-size:12px; font-weight:600; color:#888; text-transform:uppercase;">Academic Year:</label>
            <select onchange="changeSemester()" id="semYear" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
                <?php foreach (array_keys($semester_map) as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr === $selected_year ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>
            <label style="font-size:12px; font-weight:600; color:#888; text-transform:uppercase;">Semester:</label>
            <select onchange="changeSemester()" id="semType" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
                <option value="odd" <?php echo $selected_type === 'odd' ? 'selected' : ''; ?>>Odd</option>
                <option value="even" <?php echo $selected_type === 'even' ? 'selected' : ''; ?>>Even</option>
            </select>
            <span style="padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600;">Semester <?php echo $current_sem; ?></span>
        </div>

        <?php if (empty($all_cards)): ?>
            <div class="card"><div class="empty-state"><i class="fa fa-credit-card"></i><p>No admit cards available for Semester <?php echo $current_sem; ?>.</p></div></div>
        <?php else: ?>

        <div class="tab-nav no-print">
            <button class="tab-btn active" onclick="switchTab('mid')"><i class="fa fa-file-text-o"></i> Mid Semester (<?php echo count($mid_cards); ?>)</button>
            <button class="tab-btn" onclick="switchTab('end')"><i class="fa fa-file-text-o"></i> End Semester (<?php echo count($end_cards); ?>)</button>
        </div>

        <?php
        // Render admit card function
        function renderAdmitCard($cards, $type, $student, $roll_no, $current_sem, $selected_year, $sem_label, $card_id) {
            if (empty($cards)) {
                echo '<div class="card"><div class="empty-state"><i class="fa fa-file-text-o"></i><p>No '.strtolower($type).' semester admit cards available.</p></div></div>';
                return;
            }
            $type_label = $type === 'Mid' ? 'MID SEMESTER EXAM ADMIT CARD' : 'END SEMESTER EXAM ADMIT CARD';
        ?>
            <div class="admit-wrapper" id="<?php echo $card_id; ?>">
                <div class="admit-header">
                    <h2>Amrita Vishwa Vidyapeetham</h2>
                    <h3>School of Computing- Bengaluru</h3>
                    <div class="exam-title"><?php echo $type_label; ?></div>
                    <div class="term-info">Academic Term : <?php echo $selected_year; ?> <?php echo $sem_label; ?> Semester</div>
                </div>

                <div style="margin-bottom:14px; border:1px solid #ccc; padding:12px;">
                    <div class="admit-info-row">
                        <div style="flex:1;"><span class="admit-label">Name</span><span class="colon">:</span> <?php echo htmlspecialchars(strtoupper($student['name'])); ?></div>
                        <div style="flex:1; text-align:right;"><span class="admit-label">Roll No</span><span class="colon">:</span> <?php echo htmlspecialchars($roll_no); ?></div>
                    </div>
                    <div class="admit-info-row">
                        <div style="flex:1;"><span class="admit-label">Degree</span><span class="colon">:</span> B.Tech</div>
                        <div style="flex:1; text-align:right;"><span class="admit-label">ABC ID</span><span class="colon">:</span> <?php echo htmlspecialchars($student['abc_id'] ?? ''); ?></div>
                    </div>
                    <div class="admit-info-row">
                        <div style="flex:1;"><span class="admit-label">Program</span><span class="colon">:</span> Computer Science and Engineering</div>
                        <div style="flex:1; text-align:right;"><span class="admit-label">Semester</span><span class="colon">:</span> <?php echo $current_sem; ?></div>
                    </div>
                </div>

                <table class="exam-table">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>Type</th>
                            <th>Credit</th>
                            <th style="min-width:80px;">Signature</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_values($cards) as $idx => $c): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo htmlspecialchars($c['course_code'] ?? ''); ?></td>
                            <td class="left"><?php echo htmlspecialchars($c['course_name'] ?? $c['exam_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($c['course_type'] ?? 'Regular'); ?></td>
                            <td><?php echo $c['credit'] ?? 3; ?></td>
                            <td></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="admit-note">
                    <p><em>Note:</em><br>
                    Students can appear for the <?php echo strtolower($type); ?> semester exams of the courses listed above only if they satisfy the minimum attendance requirement specified by the University.</p>
                </div>

                <div class="signature-area">
                    <div class="sign-line">&#x2728;</div>
                    <div>Deputy Controller of Examination</div>
                </div>

                <div class="admit-note" style="margin-top:16px; border-top:1px solid #ccc; padding-top:12px;">
                    <strong>Important Note:</strong>
                    <ol>
                        <li>Students should carry the Admit Card along with the College ID Card for the Examinations.</li>
                        <li>Read the Instructions given in the front page of the main answer booklet before start answering.</li>
                        <li>Mobile phones, smart watches, programmable calculators and incriminating materials are strictly prohibited inside the exam hall.</li>
                    </ol>
                </div>
            </div>
            <div class="no-print" style="text-align:center; margin-bottom:24px;">
                <button onclick="printCard('<?php echo $card_id; ?>')" class="submit-btn" style="padding:12px 28px;"><i class="fa fa-print"></i> Print <?php echo $type; ?> Semester Admit Card</button>
            </div>
        <?php } ?>

        <!-- Mid-Term Admit Card -->
        <div class="tab-content active" id="tab-mid">
            <?php renderAdmitCard($mid_cards, 'Mid', $student, $roll_no, $current_sem, $selected_year, $sem_label, 'midAdmitCard'); ?>
        </div>

        <!-- End-Term Admit Card -->
        <div class="tab-content" id="tab-end">
            <?php renderAdmitCard($end_cards, 'End', $student, $roll_no, $current_sem, $selected_year, $sem_label, 'endAdmitCard'); ?>
        </div>
        <?php endif; ?>
    </div>
    <script>
    function changeSemester() {
        var y = document.getElementById('semYear').value;
        var t = document.getElementById('semType').value;
        window.location.href = 'admitcard.php?year=' + y + '&sem_type=' + t;
    }
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');
    }
    function printCard(id) {
        var el = document.getElementById(id);
        var win = window.open('','','width=850,height=700');
        win.document.write('<html><head><title>Admit Card</title><style>body{font-family:"Times New Roman",serif;padding:20px;margin:0;} .admit-wrapper{max-width:750px;margin:0 auto;border:2px solid #333;padding:36px 40px;} .admit-header{text-align:center;margin-bottom:20px;} .admit-header h2{margin:0;font-size:22px;font-weight:bold;} .admit-header h3{margin:4px 0 0;font-size:17px;font-weight:bold;font-style:italic;} .admit-header .exam-title{margin:12px 0 4px;font-size:15px;font-weight:bold;text-transform:uppercase;} .admit-header .term-info{font-size:13px;font-weight:bold;} .admit-info-row{display:flex;justify-content:space-between;margin:6px 0;font-size:14px;} .admit-info-row .label{font-weight:bold;} .admit-info-row .colon{margin:0 6px;} .exam-table{width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;} .exam-table th,.exam-table td{border:1px solid #333;padding:8px 10px;text-align:center;} .exam-table th{background:#f0f0f0;font-weight:bold;font-size:12px;} .exam-table td.left{text-align:left;} .admit-note{font-size:12px;margin-top:16px;line-height:1.6;} .admit-note strong{display:block;margin-bottom:4px;} .admit-note ol{padding-left:18px;margin:4px 0;} .signature-area{text-align:right;margin-top:24px;font-size:13px;} .signature-area .sign-line{margin-bottom:4px;font-style:italic;font-size:18px;}</style></head><body>');
        win.document.write(el.outerHTML);
        win.document.write('</body></html>');
        win.document.close();
        setTimeout(function() { win.print(); }, 300);
    }
    </script>
</body>
</html>
