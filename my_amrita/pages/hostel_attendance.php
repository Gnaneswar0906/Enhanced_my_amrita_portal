<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$selected_month = $_GET['month'] ?? date('m');
$selected_year_num = $_GET['yr'] ?? '2026';

$stmt = $pdo->prepare('SELECT * FROM hostel_attendance WHERE student_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ? AND attendance_date <= CURDATE() ORDER BY attendance_date DESC');
$stmt->execute([$student_id, $selected_month, $selected_year_num]);
$records = $stmt->fetchAll();

// Full stats
$stmt2 = $pdo->prepare('SELECT * FROM hostel_attendance WHERE student_id = ? AND attendance_date <= CURDATE() ORDER BY attendance_date DESC');
$stmt2->execute([$student_id]);
$all_records = $stmt2->fetchAll();

$total = count($records);
$present = count(array_filter($records, function($r) { return $r['status'] === 'Present'; }));
$absent  = count(array_filter($records, function($r) { return $r['status'] === 'Absent'; }));
$late    = count(array_filter($records, function($r) { return $r['status'] === 'Late'; }));
$pct = $total > 0 ? ($present / $total * 100) : 0;

$total_all = count($all_records);
$present_all = count(array_filter($all_records, fn($r) => $r['status'] === 'Present'));
$pct_all = $total_all > 0 ? ($present_all / $total_all * 100) : 0;

$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Hostel Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .hostel-present { background:#e8f5e9 !important; }
        .hostel-present td { color:#2e7d32 !important; }
        .hostel-absent { background:#fde8e8 !important; }
        .hostel-absent td { color:#c62828 !important; }
        .hostel-late { background:#fff3cd !important; }
        .hostel-late td { color:#856404 !important; }
        .scan-time { font-weight:600; font-size:13px; }
        .month-selector { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
        .month-selector label { font-size:12px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; }
        .month-selector select { padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; cursor:pointer; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Hostel Attendance</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-building"></i> Hostel Attendance</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- Month Selector -->
        <div class="month-selector">
            <label>Month:</label>
            <select onchange="changeMonth()" id="selMonth">
                <?php foreach ($months as $mv => $mn): ?>
                    <option value="<?php echo $mv; ?>" <?php echo $mv == $selected_month ? 'selected' : ''; ?>><?php echo $mn; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Year:</label>
            <select onchange="changeMonth()" id="selYear">
                <option value="2025" <?php echo $selected_year_num=='2025'?'selected':''; ?>>2025</option>
                <option value="2026" <?php echo $selected_year_num=='2026'?'selected':''; ?>>2026</option>
            </select>
            <span style="padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600;"><?php echo $months[$selected_month] ?? 'Unknown'; ?> <?php echo $selected_year_num; ?></span>
            <span style="margin-left:auto; font-size:12px; color:#888;">Overall: <strong style="color:#a4123f;"><?php echo number_format($pct_all,1); ?>%</strong> (<?php echo $total_all; ?> days)</span>
        </div>

        <!-- Stats -->
        <div class="sgpa-display">
            <div class="sgpa-card"><div class="sgpa-label">Present</div><div class="sgpa-value" style="color:#27ae60;"><?php echo $present; ?></div><div class="sgpa-sub">days</div></div>
            <div class="sgpa-card"><div class="sgpa-label">Absent</div><div class="sgpa-value" style="color:#e74c3c;"><?php echo $absent; ?></div><div class="sgpa-sub">days</div></div>
            <div class="sgpa-card"><div class="sgpa-label">Late</div><div class="sgpa-value" style="color:#f5a623;"><?php echo $late; ?></div><div class="sgpa-sub">days</div></div>
            <div class="sgpa-card secondary"><div class="sgpa-label">Monthly %</div><div class="sgpa-value"><?php echo number_format($pct, 1); ?>%</div><div class="sgpa-sub"><?php echo $total; ?> records</div></div>
        </div>

        <div class="card">
            <h2 class="card-title"><?php echo $months[$selected_month]; ?> <?php echo $selected_year_num; ?> – Attendance Records</h2>
            <?php if (empty($records)): ?>
                <div class="empty-state"><i class="fa fa-building"></i><p>No hostel attendance records for this month.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Date</th><th>Status</th><th>Scan Time</th><th>Marked By</th><th>Remarks</th></tr></thead>
                    <tbody>
                        <?php foreach ($records as $i => $r): ?>
                        <?php $rowClass = match($r['status']) { 'Present' => 'hostel-present', 'Absent' => 'hostel-absent', 'Late' => 'hostel-late', default => '' }; ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td style="font-weight:600;"><?php echo date('d M Y (l)', strtotime($r['attendance_date'])); ?></td>
                            <td>
                                <?php if ($r['status'] === 'Present'): ?>
                                    <i class="fa fa-check-circle" style="color:#27ae60; font-size:18px;"></i> <strong style="margin-left:4px; color:#27ae60;">Present</strong>
                                <?php elseif ($r['status'] === 'Absent'): ?>
                                    <i class="fa fa-times-circle" style="color:#e74c3c; font-size:18px;"></i> <strong style="margin-left:4px; color:#e74c3c;">Absent</strong>
                                <?php elseif ($r['status'] === 'Late'): ?>
                                    <i class="fa fa-exclamation-circle" style="color:#f5a623; font-size:18px;"></i> <strong style="margin-left:4px; color:#f5a623;">Late</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['scan_time']): ?>
                                    <span class="scan-time"><i class="fa fa-clock-o" style="color:#a4123f;"></i> <?php echo date('h:i A', strtotime($r['scan_time'])); ?></span>
                                <?php elseif ($r['status'] === 'Absent'): ?>
                                    <span style="color:#e74c3c; font-size:12px;">No scan</span>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['marked_by'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($r['remarks'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function changeMonth() {
        var m = document.getElementById('selMonth').value;
        var y = document.getElementById('selYear').value;
        window.location.href = 'hostel_attendance.php?month=' + m + '&yr=' + y;
    }
    </script>
</body>
</html>
